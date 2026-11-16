<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Aplicación')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.tipos-tokens')
    @stack('styles')
    @livewireStyles
    <style>[x-cloak] { display: none !important; }</style>
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-canvas text-ink min-h-screen"
      :class="{ 'overflow-hidden': $store.modales.pila.length > 0 }"
      x-data="{ cosechando: false, cosechaEstado: 'idle', cosechaMensaje: '', cosechaDetalle: '', cosechaItems: [], cosechaTimer: null, cosecharTodo() { if (this.cosechando) { return; } this.cosechando = true; this.cosechaEstado = 'idle'; this.cosechaMensaje = ''; this.cosechaDetalle = ''; this.cosechaItems = []; if (this.cosechaTimer) { clearTimeout(this.cosechaTimer); this.cosechaTimer = null; } const controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), 15000); const csrf = document.querySelector('meta[name=csrf-token]')?.content || ''; fetch('/api/farm/harvest-all', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, signal: controller.signal }).then(async (response) => { if (!response.ok) { const data = await response.json().catch(() => ({})); throw new Error(data.message || 'No se pudo cosechar'); } return response.json(); }).then((data) => { const bayas = Number(data.total_bayas ?? 0); const errores = Array.isArray(data.errores) ? data.errores.length : 0; if (bayas > 0) { this.cosechaEstado = 'ok'; this.cosechaMensaje = 'Cosecha completada · ' + bayas + (bayas === 1 ? ' baya' : ' bayas'); this.cosechaDetalle = errores > 0 ? errores + ' error(es)' : ''; this.cosechaItems = Array.isArray(data.bayas) ? data.bayas : []; } else if (errores > 0) { this.cosechaEstado = 'error'; this.cosechaMensaje = 'Error'; this.cosechaDetalle = errores + ' error(es)'; this.cosechaItems = []; } else { this.cosechaEstado = 'ok'; this.cosechaMensaje = 'Nada que cosechar'; this.cosechaDetalle = ''; this.cosechaItems = []; } window.dispatchEvent(new CustomEvent('inventario:actualizado', { detail: data })); this.ocultarMensajeCosecha(); }).catch((error) => { this.cosechaEstado = 'error'; this.cosechaMensaje = 'Error'; this.cosechaDetalle = error?.name === 'AbortError' ? 'tiempo de espera' : ''; this.cosechaItems = []; this.ocultarMensajeCosecha(); }).finally(() => { clearTimeout(timeout); this.cosechando = false; }); }, ocultarMensajeCosecha() { this.cosechaTimer = setTimeout(() => { this.cosechaEstado = 'idle'; this.cosechaMensaje = ''; this.cosechaDetalle = ''; this.cosechaItems = []; this.cosechaTimer = null; }, 6000); } }"
      x-init="$store.expedicionesNavbar.iniciar(); $store.reclutamientoNavbar.iniciar()">
    <header class="bg-surface sticky top-0 z-30 relative">
        <div class="px-4 sm:px-6">
            <nav
                class="relative flex items-center gap-2 h-14"
                aria-label="Navegación principal"
            >
                <div class="flex items-center gap-1 min-w-0 flex-1 overflow-x-auto">
                    @if(!request()->is('combate*'))
                        <!-- Cosecha global -->
                        <div class="relative shrink-0">
                            <button
                                type="button"
                                @click="cosecharTodo()"
                                :disabled="cosechando"
                                aria-label="Cosechar todas las plantaciones"
                                title="Cosechar todas las plantaciones"
                                class="w-8 h-8 rounded-full border border-line text-ink-muted hover:bg-surface-sunken hover:text-ink transition-colors flex items-center justify-center focus-ring disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <i class="fa-solid" :class="cosechando ? 'fa-spinner fa-spin' : 'fa-seedling'" aria-hidden="true"></i>
                            </button>
                        </div>
                    @endif

                    @php
                        $navItems = [
                            ['route' => '/pokedex', 'label' => 'Pokédex'],
                            ['route' => '/habitats', 'label' => 'Hábitats'],
                            ['route' => '/equipos', 'label' => 'Equipos'],
                            ['route' => '/sendas', 'label' => 'Sendas'],
                            ['route' => '/gimnasios', 'label' => 'Gimnasios'],
                            ['route' => '/forja', 'label' => 'Forja'],
                            //['route' => '/misiones', 'label' => 'Misiones'],
                        ];
                    @endphp
                    @foreach($navItems as $item)
                        <a href="{{ $item['route'] }}"
                           class="px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors focus-ring
                                  {{ request()->is(trim($item['route'], '/') . '*')
                                      ? 'bg-primary-soft text-primary-ink'
                                      : 'text-ink-muted hover:bg-surface-sunken hover:text-ink' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>

                @include('layouts.partials.navbar-acciones')

                <!-- Usuario autenticado + Nivel jugador + Dark mode toggle -->
                <div class="flex items-center gap-2 shrink-0">
                    @auth
                        <span class="hidden sm:inline-flex text-sm font-medium text-ink max-w-[10rem] truncate" title="{{ auth()->user()->name }}">
                            {{ auth()->user()->name }}
                        </span>
                        <form method="POST" action="{{ url('/logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="px-2.5 py-1 text-xs font-medium text-ink-muted border border-line rounded-full hover:bg-surface-sunken hover:text-ink transition-colors focus-ring">
                                Salir
                            </button>
                        </form>
                    @endauth
                    <span class="px-2.5 py-1 bg-primary text-on-primary text-xs font-bold rounded-full" title="Nivel {{ $nivelJugador ?? 1 }}">
                        Nv {{ $nivelJugador ?? 1 }}
                    </span>
                    <button x-data="{ oscuro: localStorage.getItem('theme') === 'dark' }"
                            @click="oscuro = !oscuro; localStorage.setItem('theme', oscuro ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', oscuro)"
                            class="p-2 rounded-lg text-ink-muted hover:bg-surface-sunken transition-colors focus-ring"
                            aria-label="Cambiar tema">
                        <span x-text="oscuro ? '☀️' : '🌙'"></span>
                    </button>
                </div>
            </nav>
        </div>
        <!-- Level progress bar (5px) -->
        <div class="absolute bottom-0 left-0 right-0 h-[5px] bg-surface-sunken" aria-hidden="true">
            <div class="h-full bg-primary transition-all duration-500" style="width: {{ $progresoNivel ?? 0 }}%"></div>
        </div>
    </header>

    @include('layouts.partials.modal-historial-expediciones')
    @include('layouts.partials.modal-detalle-expedicion')
    @include('layouts.partials.modal-reclutamiento')
    @include('reclutamiento.partials._modal-descartar')
    @include('reclutamiento.partials._modal-descartar-todos')

    <!-- Toast de cosecha -->
    <div
        x-show="cosechando || cosechaEstado !== 'idle'"
        x-cloak
        x-transition.opacity.duration.200ms
        role="status"
        aria-live="polite"
        aria-atomic="true"
        class="fixed right-4 top-20 z-[70] w-[min(28rem,calc(100vw_-_2rem))] rounded-control border px-3 py-2.5 text-sm font-medium shadow-card"
        :class="cosechando
            ? 'border-info bg-info-soft text-info-ink'
            : (cosechaEstado === 'error'
                ? 'border-danger bg-danger-soft text-danger-ink'
                : 'border-success bg-success-soft text-success-ink')"
    >
        <div class="flex items-start gap-2">
            <i class="fa-solid mt-0.5 shrink-0" :class="cosechando ? 'fa-spinner fa-spin' : (cosechaEstado === 'error' ? 'fa-triangle-exclamation' : 'fa-seedling')" aria-hidden="true"></i>
            <div class="min-w-0 flex-1">
                <p class="break-words" x-text="cosechando ? 'Cosechando…' : cosechaMensaje"></p>
                <p x-show="cosechaDetalle" class="mt-0.5 break-words text-xs font-normal opacity-80" x-text="cosechaDetalle"></p>
                <ul x-show="cosechaEstado === 'ok' && cosechaItems.length > 0" class="mt-1.5 grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-1 max-h-48 overflow-y-auto pr-1 text-xs font-normal">
                    <template x-for="item in cosechaItems" :key="item.clave ?? item.nombre">
                        <li class="flex min-w-0 items-center gap-1.5">
                            <img :src="item.icono" alt="" loading="lazy" decoding="async" class="h-4 w-4 shrink-0 object-contain" aria-hidden="true">
                            <span class="min-w-0 flex-1 truncate" x-text="item.nombre"></span>
                            <span class="shrink-0 font-semibold tabular-nums" x-text="'×' + item.cantidad"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </div>

    <main class="px-4 sm:px-6 py-6">
        @if(session('success'))
            <x-ui.alert variant="success" class="mb-4">
                {{ session('success') }}
            </x-ui.alert>
        @endif
        @if(session('error'))
            <x-ui.alert variant="danger" class="mb-4">
                {{ session('error') }}
            </x-ui.alert>
        @endif

        @yield('content')
    </main>

    @stack('scripts')

    @livewireScriptConfig
</body>
</html>
