@props([
    'label',
    'name',
    'type' => 'text',
    'placeholder' => '',
    'required' => false,
    'autocomplete' => null,
    'value' => null,
])

@php
    $inputId = $attributes->get('id', $name);
    $hasError = $errors->has($name);
    $valueAttribute = $type === 'password' ? null : old($name, $value);

    $inputBaseClasses = 'mt-2 block w-full rounded-md border bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400';
    $inputStateClasses = $hasError
        ? 'border-danger-700 focus:border-danger-700 focus:ring-2 focus:ring-danger-700/20'
        : 'border-slate-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20';
@endphp

<div>
    <label for="{{ $inputId }}" class="text-sm font-medium text-slate-700">
        {{ $label }}
        @if ($required)
            <span class="text-danger-700" aria-hidden="true">*</span>
        @endif
    </label>

    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $valueAttribute }}"
        placeholder="{{ $placeholder }}"
        autocomplete="{{ $autocomplete }}"
        @required($required)
        {{ $attributes->merge(['class' => $inputBaseClasses.' '.$inputStateClasses]) }}
    />

    @error($name)
        <p class="mt-1 text-sm text-danger-700">{{ $message }}</p>
    @enderror
</div>
