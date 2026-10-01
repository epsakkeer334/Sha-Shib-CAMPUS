{{-- resources/views/livewire/admin/components/table/columns/badge.blade.php --}}
@if($value)
    <span class="badge {{ $column['badge_class'] ?? 'bg-secondary' }} bg-opacity-10 text-{{ str_replace('bg-', '', $column['badge_class'] ?? 'secondary') }} px-3 py-1 rounded-pill">
        <i class="{{ $column['badge_icon'] ?? 'ti ti-tag' }} me-1"></i> {{ $value }}
    </span>
@else
    <span class="text-muted">—</span>
@endif
