@props(['name', 'title' => null, 'maxWidth' => 'md'])

@php
    $maxWidths = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-lg', 'xl' => 'max-w-xl'];
@endphp

<div x-data="{ {{ $name }}: false }" @open-{{ $name }}.window="{{ $name }} = true" @close-{{ $name }}.window="{{ $name }} = false" x-cloak>
    <!-- Backdrop -->
    <div x-show="{{ $name }}" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50 z-50" @click="{{ $name }} = false"></div>

    <!-- Modal -->
    <div x-show="{{ $name }}" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="{{ $maxWidths[$maxWidth] }} w-full bg-white dark:bg-gray-800 rounded-xl shadow-xl" @click.stop>
            @if($title)
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
                    <button @click="{{ $name }} = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Cerrar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @endif
            <div class="p-6">{{ $slot }}</div>
            @if(isset($footer))
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">{{ $footer }}</div>
            @endif
        </div>
    </div>
</div>
