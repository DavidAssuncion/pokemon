@extends('layouts.app')

@section('title', 'Exploraciones')

@section('content')
<div x-data="exploracionesPage()">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Exploraciones</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestión de expediciones de tus equipos</p>
    </div>

    <!-- Section 1: Activas -->
    <section class="mb-8" aria-labelledby="activas-title">
        <div class="flex items-center justify-between mb-4">
            <h2 id="activas-title" class="text-lg font-semibold text-gray-900 dark:text-white">Activas</h2>
            @if(count($activas ?? []) > 0)
                <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-xs font-bold rounded-full">
                    {{ count($activas) }}
                </span>
            @endif
        </div>

        @forelse(($activas ?? []) as $exp)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-4">
                <!-- Card header: team / habitat / level + status -->
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $exp['equipo'] }}</span>
                        <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $exp['habitat'] }}</span>
                        <span class="px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-[10px] font-bold rounded-full uppercase">
                            Nivel {{ $exp['nivel'] }}
                        </span>
                    </div>
                    @php
                        $volviendo = ($exp['estado'] ?? 'explorando') === 'volviendo';
                    @endphp
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase {{ $volviendo
                        ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400'
                        : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400' }}">
                        {{ $volviendo ? 'Volviendo' : 'Explorando' }}
                    </span>
                </div>

                <div class="px-4 py-3 space-y-3">
                    <!-- Progress bar -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Progreso</span>
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $exp['progreso'] ?? 0 }}%</span>
                        </div>
                        <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div class="h-2 bg-blue-500 rounded-full transition-all"
                                 style="width: {{ max(0, min(100, (int) ($exp['progreso'] ?? 0))) }}%"></div>
                        </div>
                    </div>

                    <!-- Time info -->
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Inicio:
                        <span class="font-medium text-gray-700 dark:text-gray-300" x-text="fmtTime('{{ $exp['inicio'] ?? '' }}')"></span>
                        · Vuelta:
                        <span class="font-medium text-gray-700 dark:text-gray-300" x-text="fmtTime('{{ $exp['inicio_vuelta'] ?? '' }}')"></span>
                        · Fin:
                        <span class="font-medium text-gray-700 dark:text-gray-300" x-text="fmtTime('{{ $exp['fin'] ?? '' }}')"></span>
                    </p>

                    @if(!empty($exp['indefinido']))
                        <button
                            @click="recogerResultados({{ $exp['id'] }})"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors"
                        >
                            Recoger resultados
                        </button>
                    @endif

                    <!-- Bitácora (collapsible) -->
                    <div x-data="{ open: false }" class="border-t border-gray-200 dark:border-gray-700 pt-3">
                        <button
                            type="button"
                            @click="open = !open"
                            :aria-expanded="open"
                            aria-controls="bitacora-{{ $exp['id'] }}"
                            class="flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors"
                        >
                            <svg class="w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            Bitácora
                            <span class="text-gray-400 dark:text-gray-500">({{ count($exp['bitacora'] ?? []) }})</span>
                        </button>

                        <ul id="bitacora-{{ $exp['id'] }}" x-show="open" x-cloak class="mt-3 space-y-2">
                            @forelse(($exp['bitacora'] ?? []) as $evento)
                                @php $tipo = $evento['tipo'] ?? 'desconocido'; @endphp
                                <li class="flex items-center gap-3 py-1.5">
                                    @if($tipo === 'pokemon')
                                        <img
                                            src="/images/iconos_webp/{{ $evento['pokemon_id'] ?? 0 }}.webp"
                                            loading="lazy"
                                            decoding="async"
                                            alt="{{ $evento['nombre'] ?? 'Pokémon' }}"
                                            class="w-10 h-10 object-contain"
                                            onerror="this.style.display='none'"
                                        >
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            Encontraste un <strong>{{ $evento['nombre'] ?? 'Pokémon' }}</strong>
                                        </span>
                                    @elseif($tipo === 'caramelo_familia')
                                        <svg class="w-5 h-5 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M10 1.5a3 3 0 013 3v.75h1.25a3 3 0 013 3v3.1a5 5 0 110 4.3v3.1a3 3 0 01-3 3H13v.75a3 3 0 01-6 0v-.75H5.75a3 3 0 01-3-3v-3.1a5 5 0 110-4.3v-3.1a3 3 0 013-3H7v-.75a3 3 0 013-3z"/>
                                        </svg>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            Caramelos de <strong>{{ $evento['nombre'] ?? 'Pokémon' }}</strong>
                                            <span class="font-semibold text-gray-900 dark:text-white">×{{ $evento['cantidad'] ?? 1 }}</span>
                                        </span>
                                    @elseif($tipo === 'caramelo_ev')
                                        <svg class="w-5 h-5 text-cyan-500 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M10 1.5a3 3 0 013 3v.75h1.25a3 3 0 013 3v3.1a5 5 0 110 4.3v3.1a3 3 0 01-3 3H13v.75a3 3 0 01-6 0v-.75H5.75a3 3 0 01-3-3v-3.1a5 5 0 110-4.3v-3.1a3 3 0 013-3H7v-.75a3 3 0 013-3z"/>
                                        </svg>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            Caramelo EV
                                            @if(!empty($evento['stat_nombre']))
                                                <strong>{{ $evento['stat_nombre'] }}</strong>
                                            @else
                                                <strong x-text="statName({{ $evento['stat'] ?? 0 }})"></strong>
                                            @endif
                                            <span class="font-semibold text-gray-900 dark:text-white">×{{ $evento['cantidad'] ?? 1 }}</span>
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Evento registrado</span>
                                    @endif
                                    <span class="ml-auto text-xs text-gray-400 dark:text-gray-500 shrink-0"
                                          x-text="fmtTime('{{ $evento['timestamp'] ?? '' }}')"></span>
                                </li>
                            @empty
                                <li class="text-sm text-gray-400 dark:text-gray-500">Sin eventos en la bitácora</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">No hay exploraciones activas</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Envía a tu equipo desde un <a href="/habitats" class="text-blue-600 dark:text-blue-400 hover:underline">hábitat</a>
                </p>
            </div>
        @endforelse
    </section>

    <!-- Section 2: Terminadas (resultados por revisar) -->
    <section aria-labelledby="terminadas-title">
        <div class="flex items-center justify-between mb-4">
            <h2 id="terminadas-title" class="text-lg font-semibold text-gray-900 dark:text-white">Resultados por revisar</h2>
            @if(count($terminadas ?? []) > 0)
                <span class="px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-bold rounded-full">
                    {{ count($terminadas) }}
                </span>
            @endif
        </div>

        @forelse(($terminadas ?? []) as $terminada)
            @php $resultado = $terminada['resultado'] ?? []; @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-4">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $terminada['equipo'] }}</span>
                        <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $terminada['habitat'] }}</span>
                        <span class="px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-[10px] font-bold rounded-full uppercase">
                            Nivel {{ $terminada['nivel'] }}
                        </span>
                    </div>
                    <button
                        @click="cerrarResultados({{ $terminada['id'] }})"
                        class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        Cerrar resultados
                    </button>
                </div>

                <div class="p-4">
                    @if(!empty($resultado))
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @if(!empty($resultado['avistados']))
                                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">Avistados</p>
                                    <div class="flex flex-wrap gap-3">
                                        @foreach($resultado['avistados'] as $avistado)
                                            <div class="text-center" title="{{ $avistado['nombre'] }}">
                                                <img
                                                    src="/images/iconos_webp/{{ $avistado['pokemon_id'] }}.webp"
                                                    loading="lazy"
                                                    decoding="async"
                                                    alt="{{ $avistado['nombre'] }}"
                                                    class="w-16 h-16 object-contain"
                                                    onerror="this.style.display='none'"
                                                >
                                                <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate w-16">{{ $avistado['nombre'] }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(!empty($resultado['capturados']))
                                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">Capturados</p>
                                    <div class="flex flex-wrap gap-3">
                                        @foreach($resultado['capturados'] as $capturado)
                                            <div class="text-center" title="{{ $capturado['nombre'] }}">
                                                <div class="relative inline-block">
                                                    <img
                                                        src="/images/iconos_webp/{{ $capturado['pokemon_id'] }}.webp"
                                                        loading="lazy"
                                                        decoding="async"
                                                        alt="{{ $capturado['nombre'] }}"
                                                        class="w-16 h-16 object-contain"
                                                        onerror="this.style.display='none'"
                                                    >
                                                    <span class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 bg-green-600 text-white text-[10px] font-bold rounded-full">
                                                        ×{{ $capturado['cantidad'] }}
                                                    </span>
                                                </div>
                                                <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate w-16">{{ $capturado['nombre'] }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(!empty($resultado['caramelos_familia']))
                                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">Caramelos de familia</p>
                                    <ul class="space-y-1.5">
                                        @foreach($resultado['caramelos_familia'] as $caramelo)
                                            <li class="flex items-center justify-between gap-2 text-sm">
                                                <span class="flex items-center gap-2 text-gray-700 dark:text-gray-300 min-w-0">
                                                    <svg class="w-4 h-4 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M10 1.5a3 3 0 013 3v.75h1.25a3 3 0 013 3v3.1a5 5 0 110 4.3v3.1a3 3 0 01-3 3H13v.75a3 3 0 01-6 0v-.75H5.75a3 3 0 01-3-3v-3.1a5 5 0 110-4.3v-3.1a3 3 0 013-3H7v-.75a3 3 0 013-3z"/>
                                                    </svg>
                                                    <span class="truncate">Caramelos de {{ $caramelo['nombre'] }}</span>
                                                </span>
                                                <span class="font-semibold text-gray-900 dark:text-white shrink-0">×{{ $caramelo['cantidad'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if(!empty($resultado['caramelos_ev']))
                                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">Caramelos EV</p>
                                    <ul class="space-y-1.5">
                                        @foreach($resultado['caramelos_ev'] as $caramelo)
                                            <li class="flex items-center justify-between gap-2 text-sm">
                                                <span class="flex items-center gap-2 text-gray-700 dark:text-gray-300 min-w-0">
                                                    <svg class="w-4 h-4 text-cyan-500 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M10 1.5a3 3 0 013 3v.75h1.25a3 3 0 013 3v3.1a5 5 0 110 4.3v3.1a3 3 0 01-3 3H13v.75a3 3 0 01-6 0v-.75H5.75a3 3 0 01-3-3v-3.1a5 5 0 110-4.3v-3.1a3 3 0 013-3H7v-.75a3 3 0 013-3z"/>
                                                    </svg>
                                                    <span class="truncate">Caramelo EV {{ $caramelo['stat_nombre'] }}</span>
                                                </span>
                                                <span class="font-semibold text-gray-900 dark:text-white shrink-0">×{{ $caramelo['cantidad'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if(!empty($resultado['caramelos_tipo']))
                                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">Caramelos de tipo</p>
                                    <div class="flex flex-wrap gap-3">
                                        @foreach($resultado['caramelos_tipo'] as $caramelo)
                                            <div class="text-center" title="{{ $caramelo['tipo'] }}">
                                                <div class="relative inline-block">
                                                    <img
                                                        src="/images/type_candy/{{ $caramelo['slug'] }}.png"
                                                        loading="lazy"
                                                        decoding="async"
                                                        alt="{{ $caramelo['tipo'] }}"
                                                        class="w-12 h-12 object-contain"
                                                        onerror="this.style.display='none'"
                                                    >
                                                    <span class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 bg-amber-600 text-white text-[10px] font-bold rounded-full">
                                                        ×{{ $caramelo['cantidad'] }}
                                                    </span>
                                                </div>
                                                <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate w-12">{{ $caramelo['tipo'] }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-3 flex items-center justify-center">
                                <p class="text-sm font-bold text-indigo-700 dark:text-indigo-300">+{{ $resultado['exp'] ?? 0 }} EXP</p>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">Sin resultados</p>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay resultados pendientes de revisar</p>
            </div>
        @endforelse
    </section>
</div>
@endsection

@push('scripts')
<script>
function exploracionesPage() {
    return {
        fmtTime(iso) {
            if (!iso) return '--:--';
            const date = new Date(iso);
            if (Number.isNaN(date.getTime())) return '--:--';
            return date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        },

        statName(statId) {
            const names = { 1: 'PS', 2: 'Ataque', 3: 'Defensa', 4: 'Ataque Especial', 5: 'Defensa Especial', 6: 'Velocidad' };
            return names[statId] || 'Desconocido';
        },

        async postAction(url, confirmMessage) {
            if (confirmMessage && !window.confirm(confirmMessage)) {
                return;
            }

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                });

                if (!response.ok) {
                    const body = await response.json().catch(() => ({}));
                    alert(body.message || 'Ocurrió un error al procesar la solicitud.');
                    return;
                }

                location.reload();
            } catch (error) {
                alert('Error de conexión. Inténtalo de nuevo.');
            }
        },

        recogerResultados(id) {
            this.postAction(`/exploraciones/${id}/recoger`, null);
        },

        cerrarResultados(id) {
            this.postAction(`/exploraciones/${id}/cerrar`, '¿Cerrar resultados? Esta acción eliminará el registro.');
        },
    };
}
</script>
@endpush
