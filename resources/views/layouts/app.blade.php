<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
    <header class="bg-white dark:bg-gray-900 sticky top-0 z-30 relative">
        <div class="px-4 sm:px-6">
            <nav class="relative flex items-center justify-center h-14" aria-label="Navegación principal">
                <div class="flex items-center gap-1 overflow-x-auto">
                    @php
                        $navItems = [
                            ['route' => '/pokedex', 'label' => 'Pokédex'],
                            ['route' => '/habitats', 'label' => 'Hábitats'],
                            ['route' => '/exploraciones', 'label' => 'Exploraciones'],
                            ['route' => '/equipos', 'label' => 'Equipos'],
                            ['route' => '/reclutamiento', 'label' => 'Reclutamiento'],
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
                </div>
                <!-- Usuario autenticado + Nivel jugador + Dark mode toggle -->
                <div class="absolute right-0 top-1/2 -translate-y-1/2 flex items-center gap-2">
                    @auth
                        <span class="hidden sm:inline-flex text-sm font-medium text-gray-700 dark:text-gray-300 max-w-[10rem] truncate" title="{{ auth()->user()->name }}">
                            {{ auth()->user()->name }}
                        </span>
                        <form method="POST" action="{{ url('/logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="px-2.5 py-1 text-xs font-medium text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
                                Salir
                            </button>
                        </form>
                    @endauth
                    <span class="px-2.5 py-1 bg-blue-600 text-white text-xs font-bold rounded-full" title="Nivel {{ $nivelJugador ?? 1 }}">
                        Nv {{ $nivelJugador ?? 1 }}
                    </span>
                    <button x-data="{ dark: localStorage.getItem('theme') === 'dark' }"
                            @click="dark = !dark; localStorage.setItem('theme', dark ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', dark)"
                            class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                            aria-label="Cambiar tema">
                        <span x-text="dark ? '☀️' : '🌙'"></span>
                    </button>
                </div>
            </nav>
        </div>
        <!-- Level progress bar (5px) -->
        <div class="absolute bottom-0 left-0 right-0 h-[5px] bg-gray-200 dark:bg-gray-700" aria-hidden="true">
            <div class="h-full bg-blue-500 transition-all duration-500" style="width: {{ $progresoNivel ?? 0 }}%"></div>
        </div>
    </header>

    <main class="px-4 sm:px-6 py-6">
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
