<!-- Sidebar -->
<aside 
    x-data="{ open: true }"
    @toggle-sidebar.window="open = !open"
    :class="open ? 'w-64' : 'w-20'"
    class="fixed inset-y-0 left-0 z-50 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700 transition-all duration-300 flex flex-col"
>
    <!-- Logo / Brand -->
    <div class="h-16 flex items-center justify-center border-b border-gray-200 dark:border-gray-700">
        <a href="/" class="flex items-center gap-3">
            <span class="text-2xl">🎮</span>
            <span x-show="open" x-transition class="text-lg font-bold text-gray-900 dark:text-white whitespace-nowrap">Pokemon</span>
        </a>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1" aria-label="Navegación principal">
        @php
            $navItems = [
                ['route' => '/pokedex', 'icon' => '📋', 'label' => 'Pokédex', 'active' => request()->is('pokedex*')],
                ['route' => '/habitats', 'icon' => '🌿', 'label' => 'Hábitats', 'active' => request()->is('habitats*')],
                ['route' => '/equipos', 'icon' => '👥', 'label' => 'Equipos', 'active' => request()->is('equipos*')],
                ['route' => '/reclutamiento', 'icon' => '🎯', 'label' => 'Reclutamiento', 'active' => request()->is('reclutamiento*')],
            ];
        @endphp

        @foreach($navItems as $item)
            <a href="{{ $item['route'] }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ $item['active'] 
                          ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400' 
                          : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white' }}">
                <span class="text-lg flex-shrink-0">{{ $item['icon'] }}</span>
                <span x-show="open" x-transition class="whitespace-nowrap">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <!-- Collapse Toggle (bottom) -->
    <div class="border-t border-gray-200 dark:border-gray-700 p-3">
        <button @click="open = !open" 
                class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                aria-label="Colapsar barra lateral">
            <svg class="w-5 h-5 transition-transform" :class="open ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
            </svg>
            <span x-show="open" x-transition class="whitespace-nowrap">Colapsar</span>
        </button>
    </div>
</aside>

<!-- Mobile overlay -->
<div x-show="open" @click="open = false" 
     x-transition:enter="transition-opacity duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity duration-300"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/50 z-40 lg:hidden" 
     x-cloak></div>
