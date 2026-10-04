{{-- Text-like input. Params: name, label, type=text, required=false, col=6, placeholder='', live=false, help=null,
     icon=null (leading Tabler icon, e.g. 'ti ti-mail'), step=null, maxlength=null --}}
@php
    $type = $type ?? 'text';
    $model = !empty($live) ? 'wire:model' : 'wire:model.defer';
    $fieldId = 'f_' . $name;
@endphp
<div class="col-md-{{ $col ?? 6 }}">
    <label class="form-label fw-medium small" for="{{ $fieldId }}">
        {{ $label }} @if(!empty($required))<span class="text-danger">*</span>@endif
    </label>
    <div class="{{ !empty($icon) ? 'fx-icon-field' : '' }} {{ $type === 'textarea' ? 'fx-textarea' : '' }}">
        @if(!empty($icon))<i class="{{ $icon }} fx-icon" aria-hidden="true"></i>@endif
        @if($type === 'textarea')
            <textarea id="{{ $fieldId }}" class="form-control @error($name) is-invalid @enderror" {{ $model }}="{{ $name }}" rows="{{ $rows ?? 2 }}"
                      placeholder="{{ $placeholder ?? '' }}"></textarea>
        @else
            <input id="{{ $fieldId }}" type="{{ $type }}" class="form-control @error($name) is-invalid @enderror" {{ $model }}="{{ $name }}"
                   placeholder="{{ $placeholder ?? '' }}" @if(isset($step)) step="{{ $step }}" @endif @if(isset($maxlength)) maxlength="{{ $maxlength }}" @endif>
        @endif
    </div>
    @error($name) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    @if(!empty($help))<small class="text-muted fx-help">{{ $help }}</small>@endif
</div>
