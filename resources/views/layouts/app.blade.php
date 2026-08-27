<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Aplicación')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen">
    <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4">
            <nav class="flex items-center justify-between h-14" aria-label="Navegación principal">
                <a href="/" class="text-lg font-bold text-gray-900 dark:text-white">🎮 Pokemon</a>
                <div class="flex items-center gap-1 overflow-x-auto">
                    @php
                        $navItems = [
                            ['route' => '/pokedex', 'label' => 'Pokédex'],
                            ['route' => '/habitats', 'label' => 'Hábitats'],
                            ['route' => '/equipos', 'label' => 'Equipos'],
                            ['route' => '/reclutamiento', 'label' => 'Reclutamiento'],
                            ['route' => '/combate', 'label' => 'Combate'],
                        ];
                    @endphp
                    @foreach($navItems as $item)
                        <a href="{{ $item['route'] }}"
                           class="px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors
                                  {{ request()->is(trim($item['route'], '/') . '*')
                                      ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'
                                      : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                    <!-- Dark mode toggle -->
                    <button x-data="{ dark: localStorage.getItem('theme') === 'dark' }"
                            @click="dark = !dark; localStorage.setItem('theme', dark ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', dark)"
                            class="p-2 ml-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <span x-text="dark ? '☀️' : '🌙'"></span>
                    </button>
                </div>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6">
        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded-lg bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300 border border-green-200 dark:border-green-800 text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300 border border-red-200 dark:border-red-800 text-sm">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
