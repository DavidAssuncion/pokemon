{{--
    Tarjeta de expedición terminada o archivada.
    Recibe: $terminada (array de PresentadorExploraciones::toTerminada) y
    $contexto (pagina|historial|detalle). Sin Alpine: reutilizable en
    fragmentos HTML inyectados. Los botones los gestiona el listener
    delegado del store.
--}}
@php
    $contexto ??= 'pagina';
    $resultado = $terminada['vista'] ?? [];
@endphp

<article data-expediciones-contexto="{{ $contexto }}" class="bg-surface rounded-card shadow-card border border-line overflow-hidden mb-4">
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
            <span class="px-2 py-0.5 text-2xs font-bold rounded-full uppercase {{ $terminada['estado_clase'] ?? '' }}">
                {{ $terminada['estado_label'] ?? 'Terminada' }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            @if($terminada['puede_archivar'] ?? false)
                <button
                    type="button"
                    data-expedicion-accion="archivar"
                    data-expedicion-id="{{ $terminada['id'] }}"
                    aria-label="Archivar expedición de {{ $terminada['explorador']['nombre'] ?? $terminada['reclutado'] ?? 'Sin reclutado' }}"
                    title="Archivar"
                    class="flex h-8 w-8 items-center justify-center rounded-control text-ink-muted transition-colors hover:bg-line hover:text-ink focus-ring"
                >
                    <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                </button>
            @endif
            <button
                type="button"
                data-expedicion-accion="cerrar"
                data-expedicion-id="{{ $terminada['id'] }}"
                class="px-3 py-1.5 bg-surface-sunken text-ink rounded-control text-xs font-medium hover:bg-line transition-colors focus-ring"
            >
                Cerrar resultados
            </button>
        </div>
    </div>

    <div class="p-4">
        <p class="text-xs text-ink-muted">
            Inicio:
            <span class="font-medium text-ink">{{ $terminada['inicio_label'] ?? '—' }}</span>
            · Vuelta:
            <span class="font-medium text-ink">{{ $terminada['inicio_vuelta_label'] ?? '—' }}</span>
            · Fin:
            <span class="font-medium text-ink">{{ $terminada['fin_label'] ?? '—' }}</span>
        </p>

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
                                                :id="$capturado['pokemon_id']"
                                                :shiny="$capturado['es_shiny'] ?? false"
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
                            @if(($terminada['tieneRecompensas'] ?? false) || ($terminada['tiene_items'] ?? false))
                                <hr class="my-3 border-line">
                            @endif
                        @endif

                        @if(!empty($resultado['items']))
                            <p class="text-xs font-semibold text-ink-muted uppercase tracking-wide mb-2">Objetos encontrados</p>
                            <div class="flex flex-wrap gap-3">
                                @foreach($resultado['items'] as $item)
                                    <div class="text-center" title="{{ $item['nombre'] }}">
                                        <div class="relative inline-block">
                                            <img
                                                src="{{ $item['src'] }}"
                                                loading="lazy"
                                                decoding="async"
                                                alt="{{ $item['alt'] ?? $item['nombre'] }}"
                                                title="{{ $item['alt'] ?? $item['nombre'] }}"
                                                class="w-12 h-12 object-contain"
                                                onerror="this.style.display='none'"
                                            >
                                            <span class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 bg-primary text-on-primary text-2xs font-bold rounded-full">
                                                &times;{{ $item['cantidad'] }}
                                            </span>
                                        </div>
                                        <p class="text-2xs text-ink-muted truncate w-16">{{ $item['nombre'] }}</p>
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
            <details class="group border-t border-line pt-3 mt-4">
                <summary class="flex items-center gap-2 text-xs font-medium text-ink-muted hover:text-ink transition-colors focus-ring cursor-pointer list-none">
                    <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    Diario
                    <span class="text-ink-subtle">({{ $terminada['diario']['total'] }})</span>
                </summary>

                <div id="{{ $contexto }}-diario-{{ $terminada['id'] }}" class="mt-3">
                    @include('exploraciones._diario', ['diario' => $terminada['diario'], 'mostrarHitos' => false])
                </div>
            </details>
        @endif
    </div>
</article>
