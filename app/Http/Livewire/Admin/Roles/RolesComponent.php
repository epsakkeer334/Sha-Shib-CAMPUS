<?php

namespace App\Http\Livewire\Admin\Roles;

use App\Models\User;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Roles & Permissions (Super Admin only). Super Admin itself is not editable: it passes every
 * check through Gate::before.
 * Two views: "By role" (role list + that role's permissions in collapsible groups with
 * group switches and search) and "All roles" (group-wise comparison grid).
 * Changes are kept in $matrix until saved; unsaved changes are counted per role.
 */
class RolesComponent extends Component
{
    use RecordsAuditTrail;

    /** @var array<string, array<string, bool>> role => permission key => granted (key = name with '.' as '__', for wire:model) */
    public $matrix = [];

    public $selectedRole = null;
    public $view = 'role';      // role | overview
    public $search = '';

    protected $queryString = [
        'selectedRole' => ['except' => null, 'as' => 'role'],
        'view' => ['except' => 'role'],
    ];

    public function mount()
    {
        abort_unless(Auth::user()->can('roles.manage'), 403);

        $this->loadMatrix();
        $names = $this->editableRoles()->pluck('name')->values();
        if (!$names->contains($this->selectedRole)) {
            $this->selectedRole = $names->first();
        }
        $this->view = in_array($this->view, ['role', 'overview'], true) ? $this->view : 'role';
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('roles.manage'), 403);
    }

    protected function editableRoles()
    {
        return Role::with('permissions')
            ->whereIn('name', array_diff(array_keys(config('camp.roles')), ['super-admin']))
            ->get()
            ->sortBy(fn ($role) => array_search($role->name, array_keys(config('camp.roles'))))
            ->values();
    }

    /**
     * Permission groups shown in the matrix (Super-Admin-only permissions are left out).
     */
    public static function matrixPermissions(): array
    {
        $hidden = config('camp.super_admin_only_permissions', []);

        return collect(config('camp.permissions'))
            ->map(fn ($group) => array_diff_key($group, array_flip($hidden)))
            ->filter()
            ->all();
    }

    protected function permissionNames(): array
    {
        return collect(static::matrixPermissions())->flatMap(fn ($group) => array_keys($group))->all();
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

    public function selectRole($role)
    {
        if (array_key_exists($role, $this->matrix)) {
            $this->selectedRole = $role;
            $this->view = 'role';
        }
    }

    public function setView($view)
    {
        $this->view = $view === 'overview' ? 'overview' : 'role';
    }

    /** Group switch: grant the whole group to the role, or revoke it when all are granted. */
    public function toggleGroup($group, $role = null)
    {
        $role = $role ?: $this->selectedRole;
        $permissions = static::matrixPermissions()[$group] ?? null;
        if (!$permissions || !isset($this->matrix[$role])) {
            return;
        }
        $keys = array_map([static::class, 'key'], array_keys($permissions));
        $allOn = collect($keys)->every(fn ($k) => !empty($this->matrix[$role][$k]));
        foreach ($keys as $k) {
            $this->matrix[$role][$k] = !$allOn;
        }
    }

    public function setAll($grant)
    {
        if (!isset($this->matrix[$this->selectedRole])) {
            return;
        }
        foreach (array_keys($this->matrix[$this->selectedRole]) as $k) {
            $this->matrix[$this->selectedRole][$k] = (bool) $grant;
        }
    }

    public function discard()
    {
        $this->loadMatrix();
        $this->dispatchBrowserEvent('show-toast', ['type' => 'info', 'message' => 'Unsaved changes discarded.']);
    }

    public function save()
    {
        abort_unless(Auth::user()->can('roles.manage'), 403);

        $allowed = $this->permissionNames();
        $changed = 0;

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
                $changed++;
            }
        }

        $this->loadMatrix();
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => $changed
            ? 'Permissions saved for ' . $changed . ' ' . Str::plural('role', $changed) . '.'
            : 'No changes to save.']);
    }

    public function render()
    {
        $roles = $this->editableRoles();
        $groups = static::matrixPermissions();
        $total = count($this->permissionNames());

        // unsaved changes per role (current matrix vs database)
        $pending = [];
        foreach ($roles as $role) {
            $granted = $role->permissions->pluck('name')->all();
            $pending[$role->name] = collect($this->permissionNames())
                ->filter(fn ($p) => (bool) ($this->matrix[$role->name][static::key($p)] ?? false) !== in_array($p, $granted))
                ->count();
        }

        // search narrows the permissions shown in the role view
        $term = Str::lower(trim($this->search));
        $visibleGroups = collect($groups)->map(fn ($perms, $group) => $term === '' || Str::contains(Str::lower($group), $term)
            ? $perms
            : array_filter($perms, fn ($label, $name) => Str::contains(Str::lower($label . ' ' . $name), $term), ARRAY_FILTER_USE_BOTH))
            ->filter();

        $userCounts = User::query()->join('model_has_roles', function ($j) {
            $j->on('model_has_roles.model_id', '=', 'users.id')->where('model_has_roles.model_type', User::class);
        })->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereNull('users.deleted_at')
            ->groupBy('roles.name')->selectRaw('roles.name as role, COUNT(*) as total')->pluck('total', 'role');

        return view('livewire.admin.roles.roles-component', [
            'roles' => $roles,
            'groups' => $groups,
            'visibleGroups' => $visibleGroups,
            'totalPermissions' => $total,
            'pending' => $pending,
            'pendingTotal' => array_sum($pending),
            'userCounts' => $userCounts,
            'superAdmins' => (int) ($userCounts['super-admin'] ?? 0),
        ])->layout('layouts.admin.master');
    }
}
