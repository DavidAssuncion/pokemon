@props(['type' => 'info', 'dismissible' => false])

@php
    $types = [
        'info' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 border-blue-200 dark:border-blue-800',
        'success' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300 border-green-200 dark:border-green-800',
        'warning' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300 border-yellow-200 dark:border-yellow-800',
        'error' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300 border-red-200 dark:border-red-800',
    ];
@endphp

<div x-data="{ show: true }" x-show="show" x-transition
     class="flex items-center gap-3 px-4 py-3 rounded-lg border text-sm mb-4 {{ $types[$type] }}">
    <span class="flex-1">{{ $slot }}</span>
    @if($dismissible)
        <button @click="show = false" class="flex-shrink-0 opacity-70 hover:opacity-100" aria-label="Cerrar">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    @endif
</div>
