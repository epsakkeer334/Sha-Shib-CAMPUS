{{-- Select. Params: name, label, options (value => label), required=false, col=6, live=false, placeholder=null, disabled=false, help=null --}}
@php $model = !empty($live) ? 'wire:model' : 'wire:model.defer'; @endphp
<div class="col-md-{{ $col ?? 6 }}">
    <label class="form-label fw-medium small">
        {{ $label }} @if(!empty($required))<span class="text-danger">*</span>@endif
    </label>
    <select class="form-select @error($name) is-invalid @enderror" {{ $model }}="{{ $name }}" @if(!empty($disabled)) disabled @endif>
        <option value="">{{ $placeholder ?? 'Select ' . strtolower($label) }}</option>
        @foreach($options as $value => $optionLabel)
            <option value="{{ $value }}">{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($name) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    @if(!empty($help))<small class="text-muted">{{ $help }}</small>@endif
</div>
