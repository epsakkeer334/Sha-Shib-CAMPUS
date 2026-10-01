@if($value)
    <img src="{{ $value }}" alt="image"
        class="rounded-circle" style="width:40px;height:40px;object-fit:cover;">
@else
    <i class="ti ti-photo text-muted"></i>
@endif
