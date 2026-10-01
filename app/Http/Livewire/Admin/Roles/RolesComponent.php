<?php

namespace App\Http\Livewire\Admin\Roles;

use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Role × permission matrix (Super Admin only). Super Admin itself is not editable:
 * it passes every check through Gate::before.
 */
class RolesComponent extends Component
{
    use RecordsAuditTrail;

    /** @var array<string, array<string, bool>> role => permission key => granted (key = name with '.' as '__', for wire:model) */
    public $matrix = [];

    public function mount()
    {
        abort_unless(Auth::user()->can('roles.manage'), 403);

        $this->loadMatrix();
    }

    protected function editableRoles()
    {
        return Role::with('permissions')
            ->whereIn('name', array_diff(array_keys(config('camp.roles')), ['super-admin']))
            ->get()
            ->sortBy(fn ($role) => array_search($role->name, array_keys(config('camp.roles'))));
    }

    protected function permissionNames(): array
    {
        return collect(config('camp.permissions'))->flatMap(fn ($group) => array_keys($group))->all();
    }

    protected function loadMatrix()
    {
        $this->matrix = [];

        foreach ($this->editableRoles() as $role) {
            $granted = $role->permissions->pluck('name')->all();
            foreach ($this->permissionNames() as $permission) {
                $this->matrix[$role->name][static::key($permission)] = in_array($permission, $granted);
            }
        }
    }

    public static function key(string $permission): string
    {
        return str_replace('.', '__', $permission);
    }

    public function save()
    {
        abort_unless(Auth::user()->can('roles.manage'), 403);

        $allowed = $this->permissionNames();

        foreach ($this->editableRoles() as $role) {
            $old = $role->permissions->pluck('name')->sort()->values()->all();
            $new = collect($this->matrix[$role->name] ?? [])
                ->filter()
                ->keys()
                ->map(fn ($key) => str_replace('__', '.', $key))
                ->intersect($allowed)
                ->sort()
                ->values()
                ->all();

            if ($old !== $new) {
                $role->syncPermissions($new);
                $this->auditUpdate($role, 'roles', ['permissions' => $old], ['permissions' => $new], "Updated permissions of role: {$role->name}");
            }
        }

        $this->loadMatrix();
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => 'Permissions saved.']);
    }

    public function render()
    {
        return view('livewire.admin.roles.roles-component', [
            'roles' => $this->editableRoles(),
        ])->layout('layouts.admin.master');
    }
}
