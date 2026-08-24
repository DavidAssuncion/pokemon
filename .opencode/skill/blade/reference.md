# Blade Skill Reference

## Components

### Anonymous Components (resources/views/components/)
```blade
<!-- components/input.blade.php -->
<props name="name" type="string" required />
<props name="label" type="string" default="" />
<props name="errors" type="\Illuminate\Support\ViewErrorBag" default="new \Illuminate\Support\ViewErrorBag" />

<div class="form-group">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
    @endif
    <input id="{{ $name }}" name="{{ $name }}" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" {{ $attributes->merge(['class' => '']) }}>
    @if($errors->has($name))
        <p class="mt-1 text-sm text-red-600">{{ $errors->first($name) }}</p>
    @endif
</div>
```

Usage:
```blade
<x-input name="email" label="Email" :errors="$errors" wire:model.defer="form.email" />
```

### Class Components (app/View/Components/)
```php
// app/View/Components/Modal.php
class Modal extends Component {
    public function __construct(
        public string $title,
        public bool $isOpen = false,
        public string $size = 'md', // sm, md, lg, xl
    ) {}
    public function render(): View { return view('components.modal'); }
}
```

```blade
<!-- resources/views/components/modal.blade.php -->
@props(['title', 'isOpen', 'size'])
<div x-data="{ open: @js($isOpen) }" x-show="open" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative w-full max-w-{{ $size }} bg-white rounded-lg shadow-xl">
            <div class="flex items-center justify-between border-b p-4">
                <h3 class="text-lg font-semibold">{{ $title }}</h3>
                <button @click="open = false" class="text-gray-400 hover:text-gray-500">✕</button>
            </div>
            <div class="p-4">{{ $slot }}</div>
        </div>
    </div>
</div>
```

Usage:
```blade
<x-modal title="Confirmar" :isOpen="$showModal" size="lg">
    <p>¿Estás seguro?</p>
    <x-button wire:click="confirm">Sí</x-button>
</x-modal>
```

## Wireable DTOs (Livewire)

```php
// app/DTOs/Wireable/DTOAccionBatalla.php
readonly class DTOAccionBatalla implements Wireable {
    public function __construct(
        public int $pokemonId,
        public int $movimientoId,
        public ?int $objetivoId = null,
    ) {}
    public static function fromLivewire($value): self { return new self(...$value); }
    public function toLivewire(): array { return ['pokemonId' => $this->pokemonId, 'movimientoId' => $this->movimientoId, 'objetivoId' => $this->objetivoId]; }
}
```

Blade:
```blade
<select wire:model.defer="form.movimientoId">
    @foreach($movimientos as $m)
        <option value="{{ $m->id }}">{{ $m->nombre }}</option>
    @endforeach
</select>
```

## Directives

```blade
@php($var = $value)           {{-- Inline PHP --}}
@isset($var) ... @endisset    {{-- Check isset --}}
@empty($var) ... @endempty    {{-- Check empty --}}
@foreach($items as $item) ... @endforeach
@forelse($items as $item) ... @empty ... @endforelse
@if($cond) ... @elseif($cond) ... @else ... @endif
@switch($var) @case('val') ... @break @default ... @endswitch
@include('view', ['var' => $val])
@includeIf('view', ['var' => $val])
@includeWhen($cond, 'view', ['var' => $val])
@each('partial', $items, 'item', 'empty-partial')
@stack('scripts') @push('scripts') <script>...</script> @endpush
@prepend('scripts') <script>...</script> @endprepend
@component('components.modal', ['title' => 'X']) ... @slot('footer') ... @endslot @endcomponent
@auth @guest @endauth @endguest
@can('permission') @cannot('permission') @endcan @endcannot
```

## Alpine.js Integration

```blade
<div x-data="{ open: false, count: 0 }">
    <button @click="open = !open">Toggle</button>
    <div x-show="open" x-transition>Content</div>
    <button @click="count++" x-text="count"></button>
</div>

<!-- Livewire + Alpine -->
<div x-data="{ local: @entangle($wire.entangle('form.field')) }">
    <input x-model="local" type="text">
</div>
```

## Best Practices

1. **NUNCA lógica de negocio en Blade** — solo presentación
2. **DTOs Wireable** para datos complejos Livewire ↔ Domain
3. **Componentes reutilizables** — extraer a `components/`
4. **Accesibilidad** — labels, aria-*, focus visible, contraste
5. **Estados UI** — loading, error, empty, success siempre cubiertos
6. **Nombres descriptivos** — `x-user-avatar`, no `x-ua`
7. **CSS: Tailwind** — utility-first, sin CSS custom si posible
8. **JS mínimo** — Alpine.js para interactividad simple