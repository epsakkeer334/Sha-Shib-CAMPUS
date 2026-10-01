@php
    $actions = $column['actions'] ?? ['edit', 'delete'];
@endphp

<div class="d-flex align-items-center gap-1">
    @foreach($actions as $action)
        @if(is_string($action))
            {{-- Simple string actions --}}
            @switch($action)
                @case('view')
                    <button wire:click="$emit('viewRecord', {{ $row->id }})"
                            class="btn btn-sm btn-outline-info"
                            data-bs-toggle="tooltip" title="View Record">
                        <i class="ti ti-eye"></i>
                    </button>
                    @break

                @case('edit')
                    <button wire:click="$emit('editRecord', {{ $row->id }})"
                            class="btn btn-sm btn-outline-warning"
                            data-bs-toggle="tooltip" title="Edit Record">
                        <i class="ti ti-edit"></i>
                    </button>
                    @break

                @case('delete')
                    <button wire:click="$emit('deleteRecord', {{ $row->id }})"
                            class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="tooltip" title="Delete Record">
                        <i class="ti ti-trash"></i>
                    </button>
                    @break

                @case('reset_password')
                    <button wire:click="$emit('resetPasswordRecord', {{ $row->id }})"
                            class="btn btn-sm btn-outline-warning"
                            data-bs-toggle="tooltip" title="Reset Password (Random)">
                        <i class="ti ti-refresh"></i>
                    </button>
                    @break

                @case('change_password')
                    <button wire:click="$emit('changePasswordRecord', {{ $row->id }})"
                            class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="tooltip" title="Change Password (Custom)">
                        <i class="ti ti-key"></i>
                    </button>
                    @break
            @endswitch

        @elseif(is_array($action))
            {{-- Custom array actions --}}
            @php
                $url = '#';
                if (isset($action['route'])) {
                    $parameterName = $action['parameter'] ?? 'id';
                    $parameterValue = $row->{$action['parameter_value'] ?? 'id'};
                    $url = route($action['route'], [$parameterName => $parameterValue]);
                }
            @endphp

            @if(isset($action['route']))
                <a href="{{ $url }}"
                   class="btn btn-sm {{ $action['class'] ?? 'btn-outline-secondary' }}"
                   data-bs-toggle="tooltip" title="{{ $action['label'] ?? 'Action' }}">
                    <i class="{{ $action['icon'] ?? 'ti ti-click' }}"></i>
                    @if(isset($action['show_label']) && $action['show_label'])
                        <span class="ms-1">{{ $action['label'] }}</span>
                    @endif
                </a>
            @else
                <button wire:click="{{ $action['action'] ?? '#' }}({{ $row->id }})"
                        class="btn btn-sm {{ $action['class'] ?? 'btn-outline-secondary' }}"
                        data-bs-toggle="tooltip" title="{{ $action['label'] ?? 'Action' }}">
                    <i class="{{ $action['icon'] ?? 'ti ti-click' }}"></i>
                    @if(isset($action['show_label']) && $action['show_label'])
                        <span class="ms-1">{{ $action['label'] }}</span>
                    @endif
                </button>
            @endif
        @endif
    @endforeach
</div>
