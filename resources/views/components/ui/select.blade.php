{{--
    Selector nativo accesible.

    Props:
    - name: nombre del campo (obligatorio; base del id estable)
    - label: texto de la etiqueta; si es null no se renderiza <label>
    - error: mensaje de error asociado y anunciado con role="alert"
    - required: marca el campo como obligatorio
    - placeholder: opción inicial vacía (las opciones se pasan por slot)

    El id puede sobrescribirse pasando id="..." en los atributos.
--}}
@props([
    'name',
    'label' => null,
    'error' => null,
    'required' => false,
    'placeholder' => null,
])

@php
    $selectId = $attributes->get('id') ?? 'field-' . \Illuminate\Support\Str::slug($name);
    $selectAttributes = $attributes->except('id');
    $hasError = $error !== null && $error !== '';
    $oldValue = old($name);
    $classes = [
        'block w-full px-3 py-2 text-sm bg-surface text-ink border rounded-control focus-ring',
        $hasError ? 'border-danger' : 'border-line',
    ];
@endphp

<div>
    @if($label !== null && $label !== '')
        <label for="{{ $selectId }}" class="mb-1 block text-sm font-medium text-ink">{{ $label }}</label>
    @endif

    <select
        id="{{ $selectId }}"
        name="{{ $name }}"
        {{ $selectAttributes->class($classes) }}
        @if($required) required aria-required="true" @endif
        @if($hasError) aria-invalid="true" aria-describedby="{{ $selectId }}-error" @endif
    >
        @if($placeholder !== null && $placeholder !== '')
            <option value="" @selected($oldValue === null || $oldValue === '')>{{ $placeholder }}</option>
        @endif

        {{ $slot }}
    </select>

    @if($hasError)
        <p id="{{ $selectId }}-error" role="alert" class="mt-1 text-xs text-danger-ink">{{ $error }}</p>
    @endif
</div>
