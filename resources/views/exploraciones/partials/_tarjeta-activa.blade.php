{{--
    Tarjeta de expedición activa.
    Recibe: $exp (array de PresentadorExploraciones::toActiva) y $contexto
    (pagina|historial|detalle). Sin Alpine: reutilizable en fragmentos HTML
    inyectados. Los botones los gestiona el listener delegado del store.
--}}
@php
    $contexto ??= 'pagina';
    $progreso = (int) ($exp['progreso'] ?? 0);
@endphp

<article data-expediciones-contexto="{{ $contexto }}" class="bg-surface rounded-card shadow-card border border-line overflow-hidden mb-4">
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
                <span class="text-xs font-medium text-ink">{{ $progreso }}%</span>
            </div>
            <div
                class="h-2 bg-surface-sunken rounded-full overflow-hidden"
                role="progressbar"
                aria-label="Progreso de la expedición"
                aria-valuenow="{{ $progreso }}"
                aria-valuemin="0"
                aria-valuemax="100"
            >
                <div class="h-2 bg-primary rounded-full transition-all"
                     style="width: {{ $progreso }}%"></div>
            </div>
            @if(!empty($exp['restante_label']))
                @php
                    $restante = (string) $exp['restante_label'];
                    // Etiquetas de estado, no de tiempo: «Quedan Pendiente» no tiene sentido.
                    $restanteEsEstado = in_array($restante, ['Pendiente', 'Indefinida'], true)
                        || ($exp['indefinido'] ?? false)
                        || ($exp['estado'] ?? null) === 'pendiente_resolucion';
                @endphp
                <p class="mt-1 text-xs text-ink-muted">
                    @if($restanteEsEstado)
                        <span class="font-medium text-ink">{{ $restante }}</span>
                    @else
                        Quedan <span class="font-medium text-ink">{{ $restante }}</span>
                    @endif
                </p>
            @endif
        </div>

        <!-- Time info -->
        <p class="text-xs text-ink-muted">
            Inicio:
            <span class="font-medium text-ink">{{ $exp['inicio_label'] ?? '—' }}</span>
            · Vuelta:
            <span class="font-medium text-ink">{{ $exp['inicio_vuelta_label'] ?? '—' }}</span>
            · Fin:
            <span class="font-medium text-ink">{{ $exp['fin_label'] ?? '—' }}</span>
        </p>

        <div class="flex items-center gap-2 flex-wrap">
            @if($exp['puede_cancelar'] ?? true)
                <button
                    type="button"
                    data-expedicion-accion="cancelar"
                    data-expedicion-id="{{ $exp['id'] }}"
                    class="px-4 py-2 bg-surface-sunken text-ink rounded-control text-sm font-medium hover:bg-line transition-colors focus-ring"
                >
                    Cancelar
                </button>
            @endif
            @if($exp['puede_recoger'] ?? !empty($exp['indefinido']))
                <button
                    type="button"
                    data-expedicion-accion="recoger"
                    data-expedicion-id="{{ $exp['id'] }}"
                    class="px-4 py-2 bg-danger text-on-primary rounded-control text-sm font-medium hover:bg-danger/90 transition-colors focus-ring"
                >
                    Recoger resultados
                </button>
            @endif
        </div>

        <!-- Diario (collapsible, nativo) -->
        <details class="group border-t border-line pt-3">
            <summary class="flex items-center gap-2 text-xs font-medium text-ink-muted hover:text-ink transition-colors focus-ring cursor-pointer list-none">
                <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                Bitácora
                <span class="text-ink-subtle">({{ $exp['diario']['total'] ?? 0 }})</span>
            </summary>

            <div id="{{ $contexto }}-bitacora-{{ $exp['id'] }}" class="mt-3">
                @if(!empty($exp['diario']))
                    @include('exploraciones._diario', ['diario' => $exp['diario']])
                @else
                    <p class="text-sm text-ink-subtle">Sin eventos en la bitácora</p>
                @endif
            </div>
        </details>
    </div>
</article>
