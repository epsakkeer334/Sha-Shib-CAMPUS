<?php

namespace App\Http\Livewire\Admin\Users;

use App\Models\Admin\Institute;
use App\Models\User;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * User management.
 *  - Super Admin: any role, chooses the institute (none for Super Admin users).
 *  - Institute Admin: staff roles only (Accounts, TM, BiC, EM, HoT, Faculty), own institute only.
 * Rules come from config('camp.assignable_roles').
 */
class UsersComponent extends Component
{
    use RecordsAuditTrail;

    // Form fields
    public $name, $email, $phone, $employee_code, $role, $institute_id, $password, $password_confirmation;
    public $status = 1;
    public $recordId;
    public $isEdit = false;
    public $confirmingDeleteId = null;

    // Set when opened from Institutes > Users (route admin.institute-users.institute)
    public $scopeInstituteId = null;
    public $scopeInstituteName = null;

    protected $listeners = [
        'editRecord' => 'edit',
        'deleteRecord' => 'confirmDelete',
    ];

    protected $messages = [
        'institute_id.required' => 'Please select the institute for this user.',
        'role.in' => 'You are not allowed to assign this role.',
        'password.min' => 'Password must be at least 8 characters.',
    ];

    /**
     * @param  int|null  $institute  route {institute}. Deliberately not named like the
     *                   $institute_id form field: Livewire 2 rebuilds the page URL from public
     *                   properties named like route parameters, so a clash breaks every action.
     */
    public function mount($institute = null)
    {
        abort_unless(Auth::user()->can('users.view'), 403);

        if ($institute) {
            $institute = Institute::visibleTo(Auth::user())->findOrFail($institute);
            $this->scopeInstituteId = $institute->id;
            $this->scopeInstituteName = $institute->name . ' (' . $institute->code . ')';
        }
    }

    protected function rules()
    {
        $actor = Auth::user();

        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->recordId)],
            'phone' => ['nullable', 'string', 'max:15', Rule::unique('users', 'phone')->ignore($this->recordId)],
            'employee_code' => 'nullable|string|max:50',
            'role' => ['required', Rule::in($this->isEditingSelf() ? [$this->role] : $actor->assignableRoles())],
            'institute_id' => [
                Rule::requiredIf(fn () => $this->role && $this->role !== 'super-admin'),
                'nullable',
                Rule::exists('institutes', 'id')->whereNull('deleted_at'),
            ],
            'password' => [$this->isEdit ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'status' => 'boolean',
        ];
    }

    protected function isEditingSelf(): bool
    {
        return $this->isEdit && (int) $this->recordId === (int) Auth::id();
    }

    /**
     * Can the logged-in user edit/delete this user?
     */
    protected function canManage(User $target): bool
    {
        $actor = Auth::user();

        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $actor->institute_id
            && (int) $target->institute_id === (int) $actor->institute_id
            &&$target->roles->pluck('name')->diff($actor->assignableRoles())->isEmpty();
    }

    public function updatedRole($value)
    {
        if ($value === 'super-admin') {
            $this->institute_id = null;
        }
    }

    public function openModal()
    {
        abort_unless(Auth::user()->can('users.create'), 403);

        $this->resetValidation();
        $this->resetFields();
        $this->institute_id = $this->defaultInstituteId();
        $this->dispatchBrowserEvent('open-user-modal');
    }

    protected function defaultInstituteId()
    {
        $actor = Auth::user();

        return $actor->isSuperAdmin() ? $this->scopeInstituteId : $actor->institute_id;
    }

    public function edit($id)
    {
        abort_unless(Auth::user()->can('users.update'), 403);

        $user = User::visibleTo(Auth::user())->with('roles')->findOrFail($id);

        if (!$this->canManage($user)) {
            return $this->toast('danger', 'You are not allowed to edit this user.');
        }

        $this->resetValidation();
        $this->resetFields();
        $this->fill([
            'isEdit' => true,
            'recordId' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'employee_code' => $user->employee_code,
            'role' => $user->roles->pluck('name')->first(),
            'institute_id' => $user->institute_id,
            'status' => $user->status ? 1 : 0,
        ]);

        $this->dispatchBrowserEvent('open-user-modal');
    }

    public function save()
    {
        abort_unless(Auth::user()->can($this->isEdit ? 'users.update' : 'users.create'), 403);

        $this->enforceInstitute();
        $this->validate();

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'employee_code' => $this->employee_code ?: null,
            'institute_id' => $this->role === 'super-admin' ? null : $this->institute_id,
            'status' => (bool) $this->status,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->isEdit) {
            $user = User::visibleTo(Auth::user())->with('roles')->findOrFail($this->recordId);
            abort_unless($this->canManage($user), 403);

            if ($this->isEditingSelf()) {
                // Cannot deactivate yourself or move yourself to another institute.
                $data['status'] = $user->status;
                $data['institute_id'] = $user->institute_id;
            }

            $old = $user->only(array_keys($data)) + ['role' => $user->roles->pluck('name')->first()];
            unset($old['password']);

            $user->update($data);
            if (!$this->isEditingSelf()) {
                $user->syncRoles([$this->role]);
            }

            $new = $user->only(array_keys($data)) + ['role' => $user->getRoleNames()->first()];
            unset($new['password']);
            if ($this->password) {
                $new['password'] = 'changed';
            }

            $this->auditUpdate($user, 'users', $old, $new, "Updated user: {$user->name} ({$user->email})");
            $message = 'User updated successfully.';
        } else {
            $data['email_verified_at'] = now();
            $user = User::create($data);
            $user->assignRole($this->role);

            $this->auditCreate($user->load('roles'), 'users', "Created user: {$user->name} ({$user->email}) as {$this->role}");
            $message = 'User created successfully.';
        }

        $this->closeModal();
        $this->emit('refreshTable');
        $this->toast('success', $message);
    }

    /**
     * Institute Admins can only work inside their own institute, whatever the browser sends.
     */
    protected function enforceInstitute()
    {
        $actor = Auth::user();

        if (!$actor->isSuperAdmin()) {
            $this->institute_id = $actor->institute_id;
        }
    }

    public function confirmDelete($id)
    {
        $this->confirmingDeleteId = $id;
        $this->dispatchBrowserEvent('open-user-delete-modal');
    }

    public function delete()
    {
        abort_unless(Auth::user()->can('users.delete'), 403);

        $user = User::visibleTo(Auth::user())->with('roles')->find($this->confirmingDeleteId);

        if (!$user) {
            $this->toast('danger', 'User not found.');
        } elseif ($user->id === Auth::id()) {
            $this->toast('danger', 'You cannot delete your own account.');
        } elseif (!$this->canManage($user)) {
            $this->toast('danger', 'You are not allowed to delete this user.');
        } elseif ($user->isSuperAdmin() && User::role('super-admin')->count() <= 1) {
            $this->toast('danger', 'The last Super Admin cannot be deleted.');
        } else {
            $this->auditDelete($user, 'users', "Deleted user: {$user->name} ({$user->email})");
            $user->delete();
            $this->emit('refreshTable');
            $this->toast('danger', 'User deleted successfully.');
        }

        $this->confirmingDeleteId = null;
        $this->dispatchBrowserEvent('close-user-delete-modal');
    }

    public function closeModal()
    {
        $this->resetFields();
        $this->dispatchBrowserEvent('close-user-modal');
    }

    protected function resetFields()
    {
        $this->reset([
            'recordId', 'name', 'email', 'phone', 'employee_code', 'role', 'institute_id',
            'password', 'password_confirmation', 'isEdit',
        ]);
        $this->status = 1;
    }

    protected function toast($type, $message)
    {
        $this->dispatchBrowserEvent('show-toast', ['type' => $type, 'message' => $message]);
    }

    public function render()
    {
        $actor = Auth::user();
        $roles = $this->isEditingSelf() ? [$this->role] : $actor->assignableRoles();

        return view('livewire.admin.users.users-component', [
            'roleOptions' => collect($roles)->mapWithKeys(fn ($r) => [$r => config("camp.roles.{$r}", $r)])->all(),
            'instituteOptions' => $actor->isSuperAdmin()
                ? Institute::active()->orderBy('name')->get(['id', 'name', 'code'])
                : Institute::whereKey($actor->institute_id)->get(['id', 'name', 'code']),
            'isSuperAdmin' => $actor->isSuperAdmin(),
            'editingSelf' => $this->isEditingSelf(),
        ])->layout('layouts.admin.master');
    }
}
