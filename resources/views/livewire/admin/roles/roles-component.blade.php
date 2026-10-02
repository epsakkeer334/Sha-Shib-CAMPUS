<div class="content">
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">Roles & Permissions</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Administration</li>
                    <li class="breadcrumb-item active">Roles & Permissions</li>
                </ol>
            </nav>
        </div>
        <button type="button" class="btn btn-primary d-flex align-items-center shadow-sm" wire:click="save" wire:loading.attr="disabled">
            <i class="ti ti-device-floppy me-2"></i> Save Permissions
        </button>
    </div>

    <div class="alert alert-info small">
        <i class="ti ti-info-circle me-1"></i>
        <strong>Super Admin</strong> always has every permission and is not listed.
        Menu items for modules not built yet appear automatically once those modules are added.
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 220px;">Permission</th>
                            @foreach($roles as $role)
                                <th class="text-center small">{{ config("camp.roles.{$role->name}", $role->name) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(\App\Http\Livewire\Admin\Roles\RolesComponent::matrixPermissions() as $group => $permissions)
                            <tr class="table-light">
                                <td colspan="{{ $roles->count() + 1 }}" class="fw-semibold small text-uppercase">{{ $group }}</td>
                            </tr>
                            @foreach($permissions as $permission => $label)
                                <tr>
                                    <td>
                                        {{ $label }}
                                        <div class="text-muted small"><code>{{ $permission }}</code></div>
                                    </td>
                                    @foreach($roles as $role)
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input"
                                                   wire:model.defer="matrix.{{ $role->name }}.{{ \App\Http\Livewire\Admin\Roles\RolesComponent::key($permission) }}">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
