<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Shared CRUD screen for Master Data (Module 1A, Super Admin only).
 * A subclass only describes its model, labels, form fields and table columns;
 * the list, modal, status switch, audit trail and "in use" protection live here.
 */
abstract class MasterCrudComponent extends Component
{
    use RecordsAuditTrail;

    public $form = [];
    public $status = 1;
    public $recordId = null;
    public $isEdit = false;
    public $confirmingDeleteId = null;

    protected $listeners = [
        'editRecord' => 'edit',
        'deleteRecord' => 'confirmDelete',
    ];

    /** Eloquent model class. */
    abstract protected function modelClass(): string;

    /** Audit trail module key, e.g. "courses". */
    abstract protected function module(): string;

    /** Page title (plural), e.g. "Courses". */
    abstract protected function title(): string;

    /** One record, e.g. "Course". */
    abstract protected function singular(): string;

    /**
     * Form fields: key => [
     *   'label' => string, 'type' => text|number|textarea|select,
     *   'rules' => array, 'col' => 6, 'placeholder' => string,
     *   'options' => fn () => [value => label]   (select only)
     * ]
     */
    abstract protected function fields(): array;

    /** Table columns for the shared DataTable (before Status / Actions). */
    protected function columns(): array
    {
        return [['label' => 'Name', 'field' => 'name', 'sortable' => true]];
    }

    protected function icon(): string
    {
        return 'ti ti-database';
    }

    /** Menu group shown in the breadcrumb, e.g. "Academic". */
    protected function group(): string
    {
        return 'Master Data';
    }

    public function mount()
    {
        $this->authorizeMasters();
        $this->resetForm();
    }

    public function hydrate()
    {
        $this->authorizeMasters();
    }

    protected function authorizeMasters()
    {
        abort_unless(Auth::user() && Auth::user()->isSuperAdmin() && Auth::user()->can('masters.manage'), 403);
    }

    protected function rules()
    {
        $rules = ['status' => 'boolean'];
        foreach ($this->fields() as $key => $field) {
            $rules["form.{$key}"] = $field['rules'];
        }

        return $rules;
    }

    protected function validationAttributes()
    {
        return collect($this->fields())->mapWithKeys(fn ($f, $key) => ["form.{$key}" => strtolower($f['label'])])->all();
    }

    protected function resetForm()
    {
        $this->form = array_fill_keys(array_keys($this->fields()), null);
        $this->status = 1;
        $this->recordId = null;
        $this->isEdit = false;
    }

    public function openModal()
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatchBrowserEvent('open-master-modal');
    }

    public function edit($id)
    {
        $record = ($this->modelClass())::findOrFail($id);

        $this->resetValidation();
        $this->resetForm();
        $this->isEdit = true;
        $this->recordId = $record->id;
        foreach (array_keys($this->fields()) as $key) {
            $this->form[$key] = $record->{$key};
        }
        $this->status = $record->status ? 1 : 0;

        $this->dispatchBrowserEvent('open-master-modal');
    }

    public function save()
    {
        $this->validate();

        $data = collect($this->form)
            ->only(array_keys($this->fields()))
            ->map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value)
            ->all();
        $data['status'] = (bool) $this->status;

        if ($this->isEdit) {
            $record = ($this->modelClass())::findOrFail($this->recordId);
            $old = $record->only(array_keys($data));
            $record->update($data);
            $this->auditUpdate($record, $this->module(), $old, $record->only(array_keys($data)), "Updated {$this->singular()}: " . $this->recordLabel($record));
            $message = "{$this->singular()} updated successfully.";
        } else {
            $record = ($this->modelClass())::create($data);
            $this->auditCreate($record, $this->module(), "Created {$this->singular()}: " . $this->recordLabel($record));
            $message = "{$this->singular()} created successfully.";
        }

        $this->closeModal();
        $this->emit('refreshTable');
        $this->toast('success', $message);
    }

    protected function recordLabel($record): string
    {
        return $record->name ?? ('#' . $record->id);
    }

    public function confirmDelete($id)
    {
        $this->confirmingDeleteId = $id;
        $this->dispatchBrowserEvent('open-master-delete-modal');
    }

    public function delete()
    {
        $record = ($this->modelClass())::find($this->confirmingDeleteId);

        if (!$record) {
            $this->toast('danger', "{$this->singular()} not found.");
        } elseif ($record->isInUse()) {
            // Rule from plan.md: master rows in use are deactivated, never deleted.
            $this->toast('warning', "This {$this->singular()} is used by {$record->usageCount()} record(s), so it cannot be deleted. Set it Inactive instead.");
        } else {
            $this->auditDelete($record, $this->module(), "Deleted {$this->singular()}: " . $this->recordLabel($record));
            $record->delete();
            $this->emit('refreshTable');
            $this->toast('danger', "{$this->singular()} deleted successfully.");
        }

        $this->confirmingDeleteId = null;
        $this->dispatchBrowserEvent('close-master-delete-modal');
    }

    public function closeModal()
    {
        $this->resetForm();
        $this->dispatchBrowserEvent('close-master-modal');
    }

    protected function toast($type, $message)
    {
        $this->dispatchBrowserEvent('show-toast', ['type' => $type, 'message' => $message]);
    }

    public function render()
    {
        $fields = collect($this->fields())->map(function ($field) {
            if (isset($field['options']) && is_callable($field['options'])) {
                $field['options'] = call_user_func($field['options']);
            }

            return $field;
        })->all();

        return view('livewire.admin.masters.master-crud', [
            'fields' => $fields,
            'columns' => array_merge(
                [['label' => '#', 'field' => 'id', 'sortable' => true]],
                $this->columns(),
                [
                    ['label' => 'Status', 'field' => 'status', 'type' => 'status', 'sortable' => true],
                    ['label' => 'Actions', 'field' => 'actions', 'type' => 'actions', 'actions' => ['edit', 'delete']],
                ]
            ),
            'modelClass' => $this->modelClass(),
            'title' => $this->title(),
            'singular' => $this->singular(),
            'icon' => $this->icon(),
            'group' => $this->group(),
        ])->layout('layouts.admin.master');
    }
}
