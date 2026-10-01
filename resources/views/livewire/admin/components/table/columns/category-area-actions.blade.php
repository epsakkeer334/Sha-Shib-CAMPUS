@php
    $allowed = $column['actions'] ?? ['edit', 'delete', 'view'];
@endphp

<div class="d-flex align-items-center">
    {{-- 👁️ View Areas button --}}
    @if(in_array('view', $allowed))
        <button wire:click="$emit('viewAreas', {{ $row->category_id }}, {{ $row->governorate_id }})"
                class="btn btn-sm btn-outline-info me-1"
                data-bs-toggle="tooltip" title="View Areas">
            <i class="ti ti-eye"></i>
        </button>
    @endif

    {{-- ✏️ Edit button --}}
    @if(in_array('edit', $allowed))
        <button wire:click="$emit('editRecord', {{ $row->id }})"
                class="btn btn-sm btn-outline-warning me-1"
                data-bs-toggle="tooltip" title="Edit Service">
            <i class="ti ti-edit"></i>
        </button>
    @endif

    {{-- 🗑️ Delete button --}}
    @if(in_array('delete', $allowed))
        <button wire:click="$emit('deleteRecord', {{ $row->id }})"
                class="btn btn-sm btn-outline-danger"
                data-bs-toggle="tooltip" title="Delete Service">
            <i class="ti ti-trash"></i>
        </button>
    @endif
</div>
