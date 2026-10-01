<?php

namespace App\Http\Livewire\Admin\Components;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class SoftDeleteManager extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    /**
     * Configuration:
     * [
     *   'model' => Model::class,
     *   'relations' => ['user','documents'] // optional
     * ]
     */
    public $restoreConfig = null;

    public $columns = [];
    public $title = 'Deleted Records';

    protected $listeners = ['openSoftDeleteModal'];

    /**
     * Backward compatible:
     * - Accepts Model::class OR restore config array
     */
    public function openSoftDeleteModal($modelOrConfig, $columns, $title = null)
    {
        // Normalize config
        if (is_array($modelOrConfig)) {
            $this->restoreConfig = $modelOrConfig;
        } else {
            $this->restoreConfig = [
                'model' => $modelOrConfig,
                'relations' => []
            ];
        }

        $this->columns = $columns;
        $this->title = $title ?? 'Deleted Records';

        $this->resetPage();

        $this->dispatchBrowserEvent('open-soft-delete-modal');
    }

    /**
     * Restore main model + optional relations
     */
    public function restore($id)
    {
        DB::transaction(function () use ($id) {

            $modelClass = $this->restoreConfig['model'];
            $relations  = $this->restoreConfig['relations'] ?? [];

            $record = $modelClass::onlyTrashed()
                ->with($relations)
                ->findOrFail($id);

            $record->restore();

            foreach ($relations as $relation) {

                if (!method_exists($record, $relation)) {
                    continue;
                }

                $related = $record->$relation;

                if ($related instanceof \Illuminate\Database\Eloquent\Collection) {
                    foreach ($related as $item) {
                        if (method_exists($item, 'restore')) {
                            $item->restore();
                        }
                    }
                } else {
                    if ($related && method_exists($related, 'restore')) {
                        $related->restore();
                    }
                }
            }
        });

        $this->dispatchBrowserEvent('show-toast', [
            'type' => 'success',
            'message' => 'Record and related data restored successfully.'
        ]);

        $this->emit('refreshTable');
    }


    /**
     * Force delete (main model only)
     */
    public function forceDelete($id)
    {
        $modelClass = $this->restoreConfig['model'];

        $modelClass::onlyTrashed()->findOrFail($id)->forceDelete();

        $this->dispatchBrowserEvent('show-toast', [
            'type' => 'danger',
            'message' => 'Record permanently deleted.'
        ]);

        $this->emit('refreshTable');
    }

    /**
     * Always return paginator (safe)
     */
    public function render()
    {
        $records = $this->restoreConfig
            ? $this->restoreConfig['model']::onlyTrashed()->latest()->paginate(10)
            : $this->emptyPaginator();

        return view('livewire.admin.components.soft-delete-manager', compact('records'));
    }

    /**
     * Prevent Collection::links() error
     */
    protected function emptyPaginator()
    {
        return new LengthAwarePaginator(
            collect(),
            0,
            10
        );
    }
}
