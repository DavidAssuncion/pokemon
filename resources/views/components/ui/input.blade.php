{{--
    Campo de texto accesible.

    Props:
    - name: nombre del campo (obligatorio; base del id estable)
    - label: texto de la etiqueta; si es null no se renderiza <label>
    - type: text | email | password | number | search | ...
    - value: valor inicial; se repuebla con old() salvo en password
    - hint: ayuda asociada mediante aria-describedby
    - error: mensaje de error asociado y anunciado con role="alert"
    - required: marca el campo como obligatorio

    El id puede sobrescribirse pasando id="..." en los atributos.
--}}
@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])

@php
    $inputId = $attributes->get('id') ?? 'field-' . \Illuminate\Support\Str::slug($name);
    $inputAttributes = $attributes->except('id');
    $hasError = $error !== null && $error !== '';
    $hasHint = $hint !== null && $hint !== '';
    $describedBy = array_values(array_filter([
        $hasHint ? $inputId . '-hint' : null,
        $hasError ? $inputId . '-error' : null,
    ]));
    $inputValue = $type === 'password' ? $value : old($name, $value);
    $classes = [
        'block w-full px-3 py-2 text-sm bg-surface text-ink border rounded-control placeholder:text-ink-subtle focus-ring',
        $hasError ? 'border-danger' : 'border-line',
    ];
@endphp

<div>
    @if($label !== null && $label !== '')
        <label for="{{ $inputId }}" class="mb-1 block text-sm font-medium text-ink">{{ $label }}</label>
    @endif

    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $inputValue }}"
        {{ $inputAttributes->class($classes) }}
        @if($required) required aria-required="true" @endif
        @if($hasError) aria-invalid="true" @endif
        @if($describedBy !== []) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
    >

    @if($hasHint)
        <p id="{{ $inputId }}-hint" class="mt-1 text-xs text-ink-muted">{{ $hint }}</p>
    @endif

    @if($hasError)
        <p id="{{ $inputId }}-error" role="alert" class="mt-1 text-xs text-danger-ink">{{ $error }}</p>
    @endif
</div>
