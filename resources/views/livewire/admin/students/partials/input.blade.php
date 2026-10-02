{{-- Text-like input. Params: name, label, type=text, required=false, col=6, placeholder='', live=false, help=null --}}
@php
    $type = $type ?? 'text';
    $model = !empty($live) ? 'wire:model' : 'wire:model.defer';
@endphp
<div class="col-md-{{ $col ?? 6 }}">
    <label class="form-label fw-medium small">
        {{ $label }} @if(!empty($required))<span class="text-danger">*</span>@endif
    </label>
    @if($type === 'textarea')
        <textarea class="form-control @error($name) is-invalid @enderror" {{ $model }}="{{ $name }}" rows="2"
                  placeholder="{{ $placeholder ?? '' }}"></textarea>
    @else
        <input type="{{ $type }}" class="form-control @error($name) is-invalid @enderror" {{ $model }}="{{ $name }}"
               placeholder="{{ $placeholder ?? '' }}" @if(isset($step)) step="{{ $step }}" @endif>
    @endif
    @error($name) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    @if(!empty($help))<small class="text-muted">{{ $help }}</small>@endif
</div>
