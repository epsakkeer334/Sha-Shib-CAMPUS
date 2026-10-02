{{-- Portal form field. Params: name, label, type=text, required=false, options (select), placeholder, hint, live=false, disabled=false, attrs='' --}}
@php
    $type = $type ?? 'text';
    $model = !empty($live) ? 'wire:model' : 'wire:model.defer';
    $id = 'f_' . $name;
@endphp
<label class="field" for="{{ $id }}">
    <span class="label">{{ $label }}@if(!empty($required)) *@endif</span>
    @if($type === 'select')
        <select id="{{ $id }}" class="input @error($name) invalid @enderror" {{ $model }}="{{ $name }}" @if(!empty($disabled)) disabled @endif>
            <option value="">{{ $placeholder ?? 'Select' }}</option>
            @foreach($options as $value => $optionLabel)
                <option value="{{ $value }}">{{ $optionLabel }}</option>
            @endforeach
        </select>
    @elseif($type === 'textarea')
        <textarea id="{{ $id }}" rows="2" class="input @error($name) invalid @enderror" {{ $model }}="{{ $name }}" placeholder="{{ $placeholder ?? '' }}"></textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" class="input @error($name) invalid @enderror {{ $class ?? '' }}" {{ $model }}="{{ $name }}"
               placeholder="{{ $placeholder ?? '' }}" @if(isset($inputmode)) inputmode="{{ $inputmode }}" @endif
               @if(isset($autocomplete)) autocomplete="{{ $autocomplete }}" @endif @if(isset($step)) step="{{ $step }}" @endif>
    @endif
    @error($name)<span class="error">{{ $message }}</span>@elseif(!empty($hint))<span class="hint">{{ $hint }}</span>@enderror
</label>
