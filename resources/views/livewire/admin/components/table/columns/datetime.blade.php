{{-- resources/views/livewire/admin/components/table/columns/datetime.blade.php --}}
@if($value)
    @if(isset($column['format']))
        {{ \Carbon\Carbon::parse($value)->format($column['format']) }}
    @else
        {{ \Carbon\Carbon::parse($value)->format('d M Y, h:i A') }}
    @endif
@else
    <span class="text-muted">—</span>
@endif
