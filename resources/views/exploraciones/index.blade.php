@extends('layouts.app')

@section('title', 'Exploraciones')

@section('content')
@php
    $candyFallback = "this.src='/images/candy_pokemon/0.webp'; this.onerror=null;";
@endphp
<div x-data="exploracionesPage()">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Exploraciones</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestión de expediciones de tus equipos</p>
    </div>

    <!-- Section 1: Activas -->
    <section class="mb-8" aria-labelledby="activas-title">
        <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2 mb-4">
            <h2 id="activas-title" class="text-sm font-bold uppercase tracking-wide text-gray-700 dark:text-gray-300">Activas</h2>
            @if(count($activas ?? []) > 0)
                <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-xs font-bold rounded-full">
                    {{ count($activas) }}
                </span>
            @endif
        </div>

        @forelse(($activas ?? []) as $exp)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-4">
                <!-- Card header: team / habitat / level + status -->
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $exp['equipo'] }}</span>
                        <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $exp['habitat'] }}</span>
                        <span class="px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-[10px] font-bold rounded-full uppercase">
                            Nivel {{ $exp['nivel'] }}
                        </span>
                        @if(($exp['min_lvl'] ?? null) !== null && ($nivelJugador ?? 1) < $exp['min_lvl'])
                            <span class="px-2 py-0.5 bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 text-[10px] font-bold rounded-full uppercase inline-flex items-center gap-1"
                                  title="Requiere nivel {{ $exp['min_lvl'] }} de jugador">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                                Requiere Nv {{ $exp['min_lvl'] }}
                            </span>
                        @endif
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
                                @include('exploraciones._evento', ['evento' => $evento])
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
        <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2 mb-4">
            <h2 id="terminadas-title" class="text-sm font-bold uppercase tracking-wide text-gray-700 dark:text-gray-300">Resultados por revisar</h2>
            @if(count($terminadas ?? []) > 0)
                <span class="px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-bold rounded-full">
                    {{ count($terminadas) }}
                </span>
            @endif
        </div>

        @forelse(($terminadas ?? []) as $terminada)
            @php $resultado = $terminada['resultado'] ?? []; @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-4">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $terminada['equipo'] }}</span>
                        <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $terminada['habitat'] }}</span>
                        <span class="px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-[10px] font-bold rounded-full uppercase">
                            Nivel {{ $terminada['nivel'] }}
                        </span>
                        @if(($terminada['min_lvl'] ?? null) !== null && ($nivelJugador ?? 1) < $terminada['min_lvl'])
                            <span class="px-2 py-0.5 bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 text-[10px] font-bold rounded-full uppercase inline-flex items-center gap-1"
                                  title="Requiere nivel {{ $terminada['min_lvl'] }} de jugador">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                                Requiere Nv {{ $terminada['min_lvl'] }}
                            </span>
                        @endif
                        @php $exp = $resultado['exp'] ?? 0; @endphp
                        <span class="px-2 py-0.5 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[10px] font-bold rounded-full"
                              aria-label="Experiencia ganada: {{ $exp }} puntos">
                            +{{ $exp }} EXP
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
                    @php
                        $resultadoCat = $resultado['resultado'] ?? null;
                        $incidentes = $resultado['incidentes'] ?? [];
                        $durationReal = (int) ($resultado['duration_real'] ?? 0);
                        $tiempoPerdido = (int) ($resultado['tiempo_perdido'] ?? 0);
                        $efectivos = max(0, $durationReal - $tiempoPerdido);
                        // Pérdidas por derrota (contrato: lista de {tipo, id/label, cantidad_perdida})
                        $objetosPerdidos = $resultado['objetos_perdidos'] ?? [];
                        $tienePerdidas = !empty($objetosPerdidos);
                        $resultadoLabel = match ($resultadoCat) {
                            'exito_excepcional' => 'Éxito excepcional',
                            'exito' => 'Éxito',
                            'exito_parcial' => 'Éxito parcial',
                            'fracaso' => 'Fracaso',
                            'retirada' => 'Retirada',
                            default => null,
                        };
                        $resultadoClass = match ($resultadoCat) {
                            'exito_excepcional' => 'bg-emerald-400 text-emerald-950 dark:bg-emerald-500 dark:text-emerald-950',
                            'exito' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                            'exito_parcial' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
                            'fracaso' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
                            'retirada' => 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300',
                            default => null,
                        };
                        $tieneResumen = $resultadoLabel !== null || !empty($incidentes) || $durationReal > 0 || $tiempoPerdido > 0;
                    @endphp

                    @if($tieneResumen)
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mb-4">
                            @if($resultadoLabel !== null)
                                <span class="px-2.5 py-1 text-[10px] font-bold rounded-full uppercase tracking-wide {{ $resultadoClass }}">
                                    {{ $resultadoLabel }}
                                </span>
                            @endif
                            @if(!empty($incidentes))
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    Encuentros {{ (int) ($incidentes['encuentros'] ?? 0) }}
                                    · Victorias {{ (int) ($incidentes['victorias'] ?? 0) }}
                                    · Huidas {{ (int) ($incidentes['huidas'] ?? 0) }}
                                    · Emboscadas {{ (int) ($incidentes['emboscadas'] ?? 0) }}
                                    · Contratiempos {{ (int) ($incidentes['contratiempos'] ?? 0) }}
                                </span>
                            @endif
                            @if($durationReal > 0 || $tiempoPerdido > 0)
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $efectivos }} min efectivos · {{ $tiempoPerdido }} min perdidos
                                </span>
                            @endif
                        </div>
                    @endif

                    @if($tienePerdidas)
                        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm" role="alert">
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span class="font-semibold text-red-700 dark:text-red-300">⚠️ Perdiste objetos</span>
                            </div>
                            <ul class="space-y-1 text-red-600 dark:text-red-400">
                                @foreach($objetosPerdidos as $perdida)
                                    <li class="flex items-center gap-2 text-xs">
                                        <span class="font-medium" x-text="'{{ $perdida['tipo'] ?? 'Objeto' }}'"></span>
                                        @if(!empty($perdida['id']) || !empty($perdida['label']))
                                            <span x-text="'{{ $perdida['label'] ?? $perdida['id'] ?? '' }}'"></span>
                                        @endif
                                        <span class="font-bold">x{{ $perdida['cantidad_perdida'] ?? 0 }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(!empty($resultado))
                        @php
                            $capturados = $resultado['capturados'] ?? [];
                            $caramelosFamilia = $resultado['caramelos_familia'] ?? [];
                            $caramelosEv = $resultado['caramelos_ev'] ?? [];
                            $caramelosTipo = $resultado['caramelos_tipo'] ?? [];

                            $derrotadosIds = $terminada['derrotados'] ?? [];
                            $derrotadosAgrupados = collect($derrotadosIds)->countBy()->all();
                            $tieneDerrotados = !empty($derrotadosAgrupados);
                            $tieneCapturados = !empty($capturados);
                            $tieneRecompensas = !empty($caramelosFamilia) || !empty($caramelosEv) || !empty($caramelosTipo);

                            // Reparto de columnas del grid lg:grid-cols-4
                            if ($tieneRecompensas) {
                                $spanDerrotados = $tieneDerrotados ? 'lg:col-span-1' : '';
                                $spanCapturados = $tieneCapturados ? 'lg:col-span-1' : '';
                                if ($tieneDerrotados && $tieneCapturados) {
                                    $spanRecompensas = 'lg:col-span-2';
                                } elseif ($tieneDerrotados || $tieneCapturados) {
                                    $spanRecompensas = 'lg:col-span-3';
                                } else {
                                    $spanRecompensas = 'lg:col-span-4';
                                }
                            } elseif ($tieneDerrotados && $tieneCapturados) {
                                $spanDerrotados = 'lg:col-span-2';
                                $spanCapturados = 'lg:col-span-2';
                                $spanRecompensas = '';
                            } elseif ($tieneDerrotados) {
                                $spanDerrotados = 'lg:col-span-4';
                                $spanCapturados = '';
                                $spanRecompensas = '';
                            } else {
                                $spanDerrotados = '';
                                $spanCapturados = 'lg:col-span-4';
                                $spanRecompensas = '';
                            }
                        @endphp

                        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                            @if($tieneDerrotados)
                                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3 {{ $spanDerrotados }}">
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Derrotados
                                    </p>
                                    <div class="flex flex-wrap gap-3">
                                        @foreach($derrotadosAgrupados as $pokeId => $cantidad)
                                            <div class="text-center" title="Derrotado ×{{ $cantidad }}">
                                                <div class="relative inline-block">
                                                    <img
                                                        src="/images/iconos_webp/{{ $pokeId }}.webp"
                                                        loading="lazy"
                                                        decoding="async"
                                                        alt="Derrotado"
                                                        class="w-16 h-16 object-contain opacity-50 grayscale"
                                                        onerror="this.style.display='none'"
                                                    >
                                                    <span class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 bg-gray-600 text-white text-[10px] font-bold rounded-full">
                                                        ×{{ $cantidad }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($tieneCapturados)
                                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3 {{ $spanCapturados }}">
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">Capturados</p>
                                    <div class="flex flex-wrap gap-3">
                                        @foreach($capturados as $capturado)
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

                            @if($tieneRecompensas)
                                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3 {{ $spanRecompensas }}">
                                    @if(!empty($caramelosFamilia))
                                        <div class="flex flex-wrap gap-3">
                                            @foreach($caramelosFamilia as $candy)
                                                @php
                                                    $hasPoke = !empty($candy['pokemon_id']);
                                                    $candy['src'] = $hasPoke
                                                        ? "/images/candy_pokemon/{$candy['pokemon_id']}.webp"
                                                        : '/images/candy_pokemon/0.webp';
                                                    $candy['alt'] = 'Caramelo de ' . ($candy['nombre'] ?? 'Pokémon');
                                                @endphp
                                                @include('exploraciones._caramelo', ['caramelo' => $candy])
                                            @endforeach
                                        </div>
                                        @if(!empty($caramelosEv) || !empty($caramelosTipo))
                                            <hr class="my-3 border-gray-200 dark:border-gray-700">
                                        @endif
                                    @endif

                                    @if(!empty($caramelosEv))
                                        <div class="flex flex-wrap gap-3">
                                            @foreach($caramelosEv as $candy)
                                                @php
                                                    $slug = $candy['stat_slug'] ?? null;
                                                    $candy['src'] = !empty($slug)
                                                        ? "/images/candy_ev/{$slug}.webp"
                                                        : '/images/candy_ev/.webp';
                                                    $candy['alt'] = 'Caramelo EV ' . ($candy['stat_nombre'] ?? '');
                                                    $candy['nombre'] = $candy['stat_nombre'] ?? null;
                                                    $candy['nombre_js'] = !empty($candy['stat_nombre'])
                                                        ? null
                                                        : 'statName(' . ($candy['stat'] ?? 0) . ')';
                                                @endphp
                                                @include('exploraciones._caramelo', ['caramelo' => $candy])
                                            @endforeach
                                        </div>
                                        @if(!empty($caramelosTipo))
                                            <hr class="my-3 border-gray-200 dark:border-gray-700">
                                        @endif
                                    @endif

                                    @if(!empty($caramelosTipo))
                                        <div class="flex flex-wrap gap-3">
                                            @foreach($caramelosTipo as $candy)
                                                @php
                                                    $candy['src'] = '/images/candy_type/' . ($candy['slug'] ?? '') . '.webp';
                                                    $candy['alt'] = $candy['tipo'] ?? 'Caramelo de tipo';
                                                    $candy['nombre'] = $candy['tipo'] ?? null;
                                                @endphp
                                                @include('exploraciones._caramelo', ['caramelo' => $candy])
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
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
