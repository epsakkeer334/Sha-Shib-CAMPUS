{{-- Select. Params: name, label, options (value => label), required=false, col=6, live=false, placeholder=null, disabled=false, help=null,
     icon=null (leading Tabler icon) --}}
@php
    $model = !empty($live) ? 'wire:model' : 'wire:model.defer';
    $fieldId = 'f_' . $name;
@endphp
<div class="col-md-{{ $col ?? 6 }}">
    <label class="form-label fw-medium small" for="{{ $fieldId }}">
        {{ $label }} @if(!empty($required))<span class="text-danger">*</span>@endif
    </label>
    <div class="{{ !empty($icon) ? 'fx-icon-field' : '' }}">
        @if(!empty($icon))<i class="{{ $icon }} fx-icon" aria-hidden="true"></i>@endif
        <select id="{{ $fieldId }}" class="form-select @error($name) is-invalid @enderror" {{ $model }}="{{ $name }}" @if(!empty($disabled)) disabled @endif>
            <option value="">{{ $placeholder ?? 'Select ' . strtolower($label) }}</option>
            @foreach($options as $value => $optionLabel)
                <option value="{{ $value }}">{{ $optionLabel }}</option>
            @endforeach
        </select>
    </div>
    @error($name) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    @if(!empty($help))<small class="text-muted fx-help">{{ $help }}</small>@endif
</div>
