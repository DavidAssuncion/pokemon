@extends('layouts.app')

@section('title', 'Exploraciones')

@section('content')
<div x-data="exploracionesPage()">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-ink">Exploraciones</h1>
        <p class="text-sm text-ink-muted mt-1">Cada explorador vive su propia expedición</p>
    </div>

    @if($avisoProcesamiento ?? false)
        <div
            role="status"
            class="mb-4 px-4 py-3 rounded-control bg-warning-soft border border-warning/40 text-sm text-warning-ink"
        >
            <p>No se pudieron actualizar todas las expediciones; se reintentará automáticamente.</p>
            @if($agotadoPorTiempo ?? false)
                <p class="mt-1">Se actualizaron las primeras expediciones; recarga para continuar.</p>
            @endif
        </div>
    @endif

    <!-- Section 1: Activas -->
    <section class="mb-8" aria-labelledby="activas-title">
        <div class="flex items-center justify-between bg-surface-sunken border border-line rounded-control px-4 py-2 mb-4">
            <h2 id="activas-title" class="text-sm font-bold uppercase tracking-wide text-ink">Activas</h2>
            @if(count($activas ?? []) > 0)
                <span class="px-2 py-0.5 bg-primary-soft text-primary-ink text-xs font-bold rounded-full">
                    {{ count($activas) }}
                </span>
            @endif
        </div>

        @forelse(($activas ?? []) as $exp)
            <article class="bg-surface rounded-card shadow-card border border-line overflow-hidden mb-4">
                <!-- Card header: explorer / habitat / level + status -->
                <div class="px-4 py-3 border-b border-line bg-surface-sunken flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-sm font-semibold text-ink">
                            {{ $exp['explorador']['nombre'] ?? $exp['reclutado'] ?? 'Sin reclutado' }}
                        </h3>
                        <svg class="w-4 h-4 text-ink-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-sm text-ink-muted">{{ $exp['habitat'] ?? 'Sin hábitat' }}</span>
                        <span class="px-2 py-0.5 bg-warning-soft text-warning-ink border border-warning text-2xs font-bold rounded-full uppercase">
                            Nivel {{ $exp['nivel'] ?? 1 }}
                        </span>
                        @if($exp['requiere_nivel'] ?? false)
                            <span class="px-2 py-0.5 bg-warning-soft text-warning-ink border border-warning text-2xs font-bold rounded-full uppercase inline-flex items-center gap-1"
                                  title="Requiere nivel {{ $exp['min_lvl'] }} de jugador">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                                Requiere Nv {{ $exp['min_lvl'] }}
                            </span>
                        @endif
                    </div>
                    <span class="px-2 py-0.5 text-2xs font-bold rounded-full uppercase {{ $exp['estado_clase'] ?? '' }}">
                        {{ $exp['estado_label'] ?? 'Explorando' }}
                    </span>
                </div>

                <div class="px-4 py-3 space-y-3">
                    <!-- Ficha del explorador -->
                    @include('exploraciones._explorador', ['explorador' => $exp['explorador'] ?? []])

                    <!-- Progress bar -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs text-ink-muted">Progreso</span>
                            <span class="text-xs font-medium text-ink">{{ $exp['progreso'] ?? 0 }}%</span>
                        </div>
                        <div
                            class="h-2 bg-surface-sunken rounded-full overflow-hidden"
                            role="progressbar"
                            aria-label="Progreso de la expedición"
                            aria-valuenow="{{ (int) ($exp['progreso'] ?? 0) }}"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div class="h-2 bg-primary rounded-full transition-all"
                                 style="width: {{ (int) ($exp['progreso'] ?? 0) }}%"></div>
                        </div>
                        @if(!empty($exp['restante_label']))
                            <p class="mt-1 text-xs text-ink-muted">
                                Quedan <span class="font-medium text-ink">{{ $exp['restante_label'] }}</span>
                            </p>
                        @endif
                    </div>

                    <!-- Time info -->
                    <p class="text-xs text-ink-muted">
                        Inicio:
                        <span class="font-medium text-ink" x-text="fmtDateTime('{{ $exp['inicio'] ?? '' }}')"></span>
                        · Vuelta:
                        <span class="font-medium text-ink" x-text="fmtDateTime('{{ $exp['inicio_vuelta'] ?? '' }}')"></span>
                        · Fin:
                        <span class="font-medium text-ink" x-text="fmtDateTime('{{ $exp['fin'] ?? '' }}')"></span>
                    </p>

                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            type="button"
                            @click="cancelarExploracion({{ $exp['id'] }})"
                            class="px-4 py-2 bg-surface-sunken text-ink rounded-control text-sm font-medium hover:bg-line transition-colors focus-ring"
                        >
                            Cancelar
                        </button>
                        @if(!empty($exp['indefinido']))
                            <button
                                type="button"
                                @click="recogerResultados({{ $exp['id'] }})"
                                class="px-4 py-2 bg-danger text-on-primary rounded-control text-sm font-medium hover:bg-danger/90 transition-colors focus-ring"
                            >
                                Recoger resultados
                            </button>
                        @endif
                    </div>

                    <!-- Diario (collapsible) -->
                    <div x-data="{ open: false }" class="border-t border-line pt-3">
                        <button
                            type="button"
                            @click="open = !open"
                            :aria-expanded="open"
                            aria-controls="bitacora-{{ $exp['id'] }}"
                            class="flex items-center gap-2 text-xs font-medium text-ink-muted hover:text-ink transition-colors focus-ring"
                        >
                            <svg class="w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            Bitácora
                            <span class="text-ink-subtle">({{ $exp['diario']['total'] ?? 0 }})</span>
                        </button>

                        <div id="bitacora-{{ $exp['id'] }}" x-show="open" x-cloak class="mt-3">
                            @if(!empty($exp['diario']))
                                @include('exploraciones._diario', ['diario' => $exp['diario']])
                            @else
                                <p class="text-sm text-ink-subtle">Sin eventos en la bitácora</p>
                            @endif
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div class="bg-surface rounded-card shadow-card border border-line p-8 text-center" role="status">
                <svg class="w-12 h-12 mx-auto text-ink-subtle mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-ink-muted mb-1">No hay exploraciones activas</p>
                <p class="text-xs text-ink-subtle">
                    Envía a un explorador desde un <a href="/habitats" class="text-primary-ink hover:underline focus-ring">hábitat</a>
                </p>
            </div>
        @endforelse
    </section>

    <!-- Section 2: Terminadas (resultados por revisar) -->
    <section aria-labelledby="terminadas-title">
        <div class="flex items-center justify-between bg-surface-sunken border border-line rounded-control px-4 py-2 mb-4">
            <h2 id="terminadas-title" class="text-sm font-bold uppercase tracking-wide text-ink">Resultados por revisar</h2>
            @if(count($terminadas ?? []) > 0)
                <span class="px-2 py-0.5 bg-success-soft text-success-ink text-xs font-bold rounded-full">
                    {{ count($terminadas) }}
                </span>
            @endif
        </div>

        @forelse(($terminadas ?? []) as $terminada)
            @php
                $resultado = $terminada['vista'] ?? [];
            @endphp
            <article class="bg-surface rounded-card shadow-card border border-line overflow-hidden mb-4">
                <div class="px-4 py-3 border-b border-line bg-surface-sunken flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-sm font-semibold text-ink">
                            {{ $terminada['explorador']['nombre'] ?? $terminada['reclutado'] ?? 'Sin reclutado' }}
                        </h3>
                        <svg class="w-4 h-4 text-ink-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-sm text-ink-muted">{{ $terminada['habitat'] ?? 'Sin hábitat' }}</span>
                        <span class="px-2 py-0.5 bg-warning-soft text-warning-ink border border-warning text-2xs font-bold rounded-full uppercase">
                            Nivel {{ $terminada['nivel'] ?? 1 }}
                        </span>
                        @if($terminada['requiere_nivel'] ?? false)
                            <span class="px-2 py-0.5 bg-warning-soft text-warning-ink border border-warning text-2xs font-bold rounded-full uppercase inline-flex items-center gap-1"
                                  title="Requiere nivel {{ $terminada['min_lvl'] }} de jugador">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                                Requiere Nv {{ $terminada['min_lvl'] }}
                            </span>
                        @endif
                        <span class="px-2 py-0.5 bg-info-soft text-info-ink border border-info text-2xs font-bold rounded-full"
                              aria-label="Experiencia para la cuenta: {{ (int) ($resultado['exp'] ?? 0) }} puntos">
                            +{{ (int) ($resultado['exp'] ?? 0) }} EXP cuenta
                        </span>
                        <span class="px-2 py-0.5 bg-primary-soft text-primary-ink border border-primary text-2xs font-bold rounded-full"
                              aria-label="Experiencia para el explorador: {{ (int) ($terminada['exp_reclutado'] ?? 0) }} puntos">
                            +{{ (int) ($terminada['exp_reclutado'] ?? 0) }} EXP explorador
                        </span>
                    </div>
                    <button
                        @click="cerrarResultados({{ $terminada['id'] }})"
                        class="px-3 py-1.5 bg-surface-sunken text-ink rounded-control text-xs font-medium hover:bg-line transition-colors focus-ring"
                    >
                        Cerrar resultados
                    </button>
                </div>

                <div class="p-4">
                    @if(!empty($terminada['explorador']))
                        @include('exploraciones._explorador', ['explorador' => $terminada['explorador']])
                    @endif

                    @if(!empty($terminada['hitos']))
                        <ul class="flex flex-wrap items-center gap-2 mt-4" aria-label="Hitos de la expedición">
                            @foreach($terminada['hitos'] as $hito)
                                <li>
                                    <span
                                        class="inline-block px-2 py-0.5 rounded-full bg-info-soft text-info-ink border border-info text-2xs font-bold uppercase tracking-wide"
                                        @if(!empty($hito['descripcion'])) title="{{ $hito['descripcion'] }}" @endif
                                    >
                                        {{ $hito['etiqueta'] ?? '' }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if($resultado['tiene_resumen'] ?? false)
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-4 mb-4">
                            @if(($resultado['label'] ?? null) !== null)
                                <span class="px-2.5 py-1 text-2xs font-bold rounded-full uppercase tracking-wide {{ $resultado['clase'] ?? '' }}">
                                    {{ $resultado['label'] }}
                                </span>
                            @endif
                            @if(($resultado['incidentes'] ?? []) !== [])
                                <span class="text-xs text-ink-muted">
                                    Encuentros {{ (int) ($resultado['incidentes']['encuentros'] ?? 0) }}
                                    · Victorias {{ (int) ($resultado['incidentes']['victorias'] ?? 0) }}
                                    · Huidas {{ (int) ($resultado['incidentes']['huidas'] ?? 0) }}
                                    · Emboscadas {{ (int) ($resultado['incidentes']['emboscadas'] ?? 0) }}
                                    · Contratiempos {{ (int) ($resultado['incidentes']['contratiempos'] ?? 0) }}
                                </span>
                            @endif
                            @if(($resultado['duration_real'] ?? 0) > 0 || ($resultado['tiempo_perdido'] ?? 0) > 0)
                                <span class="text-xs text-ink-muted">
                                    {{ $resultado['efectivos'] ?? 0 }} min efectivos · {{ $resultado['tiempo_perdido'] ?? 0 }} min perdidos
                                </span>
                            @endif
                        </div>
                    @endif

                    @if($resultado['tiene_resultado'] ?? false)
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
                            @if($terminada['tieneDerrotados'] ?? false)
                                <div class="bg-surface-sunken rounded-control p-3 {{ $terminada['spanDerrotados'] ?? '' }}">
                                    <p class="text-xs font-semibold text-ink-muted uppercase tracking-wide mb-2 flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-ink-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Derrotados
                                    </p>
                                    <div class="flex flex-wrap gap-3">
                                        @foreach($terminada['derrotadosAgrupados'] ?? [] as $derrotado)
                                            <div class="text-center" title="Derrotado ×{{ $derrotado['cantidad'] }}">
                                                <div class="relative inline-block">
                                                    <x-sprite
                                                        :src="$derrotado['icono']"
                                                        size="64"
                                                        name="Derrotado"
                                                        wrapper-class="grayscale opacity-50"
                                                    />
                                                    <span class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 bg-ink-muted text-ink-inverse text-2xs font-bold rounded-full">
                                                        ×{{ $derrotado['cantidad'] }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($terminada['tienePerdidas'] ?? $terminada['tiene_perdidas'] ?? false)
                                @include('exploraciones._perdidas', [
                                    'perdidas' => $resultado['objetos_perdidos'] ?? [],
                                    'motivo' => $resultado['motivo_perdida'] ?? null,
                                    'span' => $terminada['spanPerdidas'] ?? $terminada['span_perdidas'] ?? '',
                                ])
                            @endif

                            @if($terminada['tieneObtenidas'] ?? $terminada['tiene_obtenidas'] ?? false)
                                <div class="bg-surface-sunken rounded-control p-3 {{ $terminada['spanObtenidas'] ?? $terminada['span_obtenidas'] ?? '' }}">
                                    <p class="text-xs font-semibold text-ink-muted uppercase tracking-wide mb-2">Recompensas obtenidas</p>

                                    @if($terminada['tieneCapturados'] ?? false)
                                        <p class="text-xs font-semibold text-ink-muted uppercase tracking-wide mb-2">Capturados</p>
                                        <div class="flex flex-wrap gap-3">
                                            @foreach(($resultado['capturados'] ?? []) as $capturado)
                                                <div class="text-center" title="{{ $capturado['nombre'] }}">
                                                    <div class="relative inline-block">
                                                        <x-sprite
                                                            :src="$capturado['icono']"
                                                            :name="$capturado['nombre']"
                                                            size="64"
                                                        />
                                                        <span class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 bg-success text-on-primary text-2xs font-bold rounded-full">
                                                            ×{{ $capturado['cantidad'] }}
                                                        </span>
                                                    </div>
                                                    <p class="text-2xs text-ink-muted truncate w-16">{{ $capturado['nombre'] }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                        @if($terminada['tieneRecompensas'] ?? false)
                                            <hr class="my-3 border-line">
                                        @endif
                                    @endif

                                    @if($terminada['tieneRecompensas'] ?? false)
                                        @if(!empty($resultado['caramelos_familia']))
                                            <div class="flex flex-wrap gap-3">
                                                @foreach($resultado['caramelos_familia'] as $candy)
                                                    @include('exploraciones._caramelo', ['caramelo' => $candy])
                                                @endforeach
                                            </div>
                                            @if(!empty($resultado['caramelos_ev']) || !empty($resultado['caramelos_tipo']))
                                                <hr class="my-3 border-line">
                                            @endif
                                        @endif

                                        @if(!empty($resultado['caramelos_ev']))
                                            <div class="flex flex-wrap gap-3">
                                                @foreach($resultado['caramelos_ev'] as $candy)
                                                    @include('exploraciones._caramelo', ['caramelo' => $candy])
                                                @endforeach
                                            </div>
                                            @if(!empty($resultado['caramelos_tipo']))
                                                <hr class="my-3 border-line">
                                            @endif
                                        @endif

                                        @if(!empty($resultado['caramelos_tipo']))
                                            <div class="flex flex-wrap gap-3">
                                                @foreach($resultado['caramelos_tipo'] as $candy)
                                                    @include('exploraciones._caramelo', ['caramelo' => $candy])
                                                @endforeach
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-ink-subtle text-center py-4">Sin resultados</p>
                    @endif

                    @if(($terminada['diario']['total'] ?? 0) > 0)
                        <div x-data="{ open: false }" class="border-t border-line pt-3 mt-4">
                            <button
                                type="button"
                                @click="open = !open"
                                :aria-expanded="open"
                                aria-controls="diario-{{ $terminada['id'] }}"
                                class="flex items-center gap-2 text-xs font-medium text-ink-muted hover:text-ink transition-colors focus-ring"
                            >
                                <svg class="w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                Diario
                                <span class="text-ink-subtle">({{ $terminada['diario']['total'] }})</span>
                            </button>

                            <div id="diario-{{ $terminada['id'] }}" x-show="open" x-cloak class="mt-3">
                                @include('exploraciones._diario', ['diario' => $terminada['diario'], 'mostrarHitos' => false])
                            </div>
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <div class="bg-surface rounded-card shadow-card border border-line p-6 text-center" role="status">
                <p class="text-sm text-ink-muted">No hay resultados pendientes de revisar</p>
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

        fmtDateTime(iso) {
            if (!iso) return '—';
            const date = new Date(iso);
            if (Number.isNaN(date.getTime())) return '—';
            return date.toLocaleString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
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

        cancelarExploracion(id) {
            this.postAction(`/exploraciones/${id}/cancelar`, '¿Cancelar la exploración? No recibirás recompensas.');
        },

        cerrarResultados(id) {
            this.postAction(`/exploraciones/${id}/cerrar`, '¿Cerrar resultados? Esta acción eliminará el registro.');
        },
    };
}
</script>
@endpush
