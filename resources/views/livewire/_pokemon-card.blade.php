@php($esDebugRival = ($p['team'] ?? 0) === 1 && ! empty($p['debug']))
<div class="{{ $p['claseCarta'] }}{{ $esDebugRival ? ' pokemon-card--debug-rival' : '' }}"
     wire:key="pokemon-{{ $p['team'] }}-{{ $p['posicion'] }}-{{ $p['refId'] }}"
     data-ref-id="{{ $p['refId'] }}"
     data-team="{{ $p['team'] }}"
     @if($p['selectable']) wire:click="previewTarget({{ $p['team'] }}, {{ $idx }})" role="button" tabindex="0" @endif>
    @if($esDebugRival)
        @php($debugRival = $p['debug'])
        <div class="debug-tooltip" role="tooltip" aria-hidden="true">
            <div class="debug-tooltip__head">DEBUG</div>

            <div class="debug-tooltip__row">
                <span class="debug-tooltip__key">Tipos</span>
                <span>{{ ! empty($debugRival['tipos']) ? implode(', ', $debugRival['tipos']) : '—' }}</span>
            </div>

            <div class="debug-tooltip__row">
                <span class="debug-tooltip__key">Habilidad</span>
                <span>
                    {{ $debugRival['habilidad']['base'] ?? '—' }}
                    @if(! empty($debugRival['habilidad']['activas']))
                        · {{ implode(', ', $debugRival['habilidad']['activas']) }}
                    @endif
                </span>
            </div>

            <div class="debug-tooltip__row">
                <span class="debug-tooltip__key">Objeto</span>
                <span>
                    @if(! empty($debugRival['objeto']))
                        {{ $debugRival['objeto']['nombre'] ?? ($debugRival['objeto']['clave'] ?? '—') }}
                        @isset($debugRival['objeto']['cargas'])
                            · {{ $debugRival['objeto']['cargas'] }} cargas
                        @endisset
                        · {{ ! empty($debugRival['objeto']['activo']) ? 'activo' : 'inactivo' }}
                        @if(! empty($debugRival['objeto']['bloqueado']))
                            · bloqueado
                        @endif
                    @else
                        Sin objeto
                    @endif
                </span>
            </div>

            <div class="debug-tooltip__stats">
                @foreach($debugRival['stats'] ?? [] as $stat)
                    <span class="debug-tooltip__stat">
                        <span class="debug-tooltip__stat-label">{{ $stat['label'] ?? '' }}</span>
                        <span>{{ $stat['actual'] ?? '—' }}/{{ $stat['max'] ?? '—' }}</span>
                    </span>
                @endforeach
            </div>

            <ul class="debug-tooltip__moves">
                @foreach($debugRival['movimientos'] ?? [] as $movimiento)
                    <li class="debug-tooltip__move{{ empty($movimiento['disponible']) ? ' is-unavailable' : '' }}">
                        <span class="debug-tooltip__move-name">{{ $movimiento['nombre'] ?? '—' }}</span>
                        <span class="debug-tooltip__move-meta">
                            {{ $movimiento['tipo'] ?? '—' }} · {{ $movimiento['categoria'] ?? '—' }}
                            · Pot {{ $movimiento['potencia'] ?? '—' }}
                            · Pre {{ $movimiento['precision'] ?? '—' }}
                            · Pri {{ $movimiento['prioridad'] ?? 0 }}
                            · {{ isset($movimiento['usosMax']) ? (($movimiento['usos'] ?? 0) . '/' . $movimiento['usosMax']) : '∞' }}
                        </span>
                        @if(empty($movimiento['disponible']) && ! empty($movimiento['motivo']))
                            <span class="debug-tooltip__move-reason">{{ $movimiento['motivo'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card-body p-2 d-flex flex-column gap-1">
        {{-- Sin nombre: el sprite y las insignias identifican al pokémon --}}
        <div class="d-flex flex-wrap align-items-center gap-1">
            @if(! empty($p['cp']))
                <span class="badge bg-info-soft text-info-ink small" title="Poder de combate">CP {{ number_format($p['cp']) }}</span>
            @endif
            @if($p['alive'])
                <span class="badge {{ $p['posturaClase'] }}" title="Postura de ronda: {{ $p['posturaLabel'] }}">
                    {{ $p['posturaCorta'] }}
                </span>
            @endif
            @if(!empty($p['item']))
                <span class="badge bg-surface border text-ink {{ $p['itemActivo'] ? '' : 'opacity-50' }}"
                      title="{{ $p['itemNombre'] }}{{ $p['itemActivo'] ? '' : ' (bloqueado)' }}">
                    {{ $p['itemNombre'] }}{{ $p['itemBloqueado'] ? ' (bloqueado)' : '' }}
                </span>
            @endif
            @if($p['habilidadAnulada'])
                <span class="badge bg-ink text-ink-inverse" title="Habilidad anulada">HAB</span>
            @endif
            @if($p['esJefe'])
                <span class="badge bg-danger-soft text-danger-ink" title="Jefe">JEFE</span>
                @foreach($p['statsJefe'] as $stat)
                    <span class="badge bg-warning-soft text-warning-ink"
                          title="Estadística mejorada de forma permanente (+{{ $stat['porcentaje'] }} %)">
                        +{{ $stat['porcentaje'] }} % {{ $stat['label'] }}
                    </span>
                @endforeach
                @foreach($p['jefePartes'] as $parte)
                    <span class="badge {{ $parte['clase'] }}"
                          title="{{ $parte['nombre'] }}: {{ $parte['hpTexto'] }}/{{ $parte['hpMaxTexto'] }}">
                        {{ $parte['nombre'] }} {{ $parte['texto'] }}
                    </span>
                @endforeach
            @endif
            @if($p['statusLabel'] !== '')
                <span class="badge bg-danger-soft text-danger-ink status-badge" title="{{ $p['statusLabel'] }}{{ $p['statusTurnsTexto'] }}">
                    @if($p['statusIcono'] !== null)
                        <img src="{{ $p['statusIcono'] }}" alt="{{ $p['statusLabel'] }}" style="width:20px;height:20px;object-fit:contain">
                    @else
                        {{ $p['statusLabel'] }}
                    @endif
                </span>
            @endif
            @if($p['ausente'])
                <span class="badge bg-surface-sunken text-ink-muted" title="{{ $p['ausenteTooltip'] }}">AUSENTE</span>
            @endif
            @if(! $p['alive'])
                <span class="badge bg-ink text-ink-inverse ko-badge">K.O.</span>
            @endif
            @if($p['esInvocacion'])
                <span class="badge bg-ink text-ink-inverse" title="Invocación temporal">INV {{ $p['turnosInvocacion'] }}</span>
            @endif
            @if($p['combo'] > 0)
                <span class="badge bg-info-soft text-info-ink" title="Racha de combo">
                    COMBO {{ $p['combo'] }} · ×{{ number_format($p['comboMultiplicador'], 2) }}
                </span>
            @endif
            @foreach($p['condicionesBadge'] as $condicion)
                <span class="badge bg-surface-sunken text-ink-muted" title="{{ $condicion['turnos'] }} turnos">
                    {{ $condicion['label'] }}
                </span>
            @endforeach
            @foreach($p['stagesDetalle'] as $stage)
                <span class="badge {{ $stage['clase'] }}" title="{{ $stage['titulo'] }}">
                    {{ $stage['texto'] }}
                </span>
            @endforeach
        </div>

        {{-- Barreras (def física y especial) — texto encima y barra a todo el ancho --}}
        <div class="d-flex gap-2 min-w-0">
            <div class="flex-fill min-w-0" title="Defensa: {{ $p['defTexto'] }}/{{ $p['maxDefTexto'] }}">
                <div class="vital-label-row">
                    <span class="vital-label text-truncate">DEF</span>
                    <span class="vital-value vital-def-num">{{ $p['defTexto'] }}/{{ $p['maxDefTexto'] }}</span>
                </div>
                <div class="progress barrier-progress">
                    <div class="progress-bar progress-bar-trail vital-def-trail" aria-hidden="true"></div>
                    <div class="progress-bar bg-info vital-def" style="width: {{ $p['defPct'] }}%" role="progressbar" aria-valuenow="{{ $p['defPct'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
            <div class="flex-fill min-w-0" title="Def. Especial: {{ $p['spDefTexto'] }}/{{ $p['maxSpDefTexto'] }}">
                <div class="vital-label-row">
                    <span class="vital-label text-truncate">DEF.ESP</span>
                    <span class="vital-value vital-spdef-num">{{ $p['spDefTexto'] }}/{{ $p['maxSpDefTexto'] }}</span>
                </div>
                <div class="progress barrier-progress">
                    <div class="progress-bar progress-bar-trail vital-spdef-trail" aria-hidden="true"></div>
                    <div class="progress-bar bg-primary vital-spdef" style="width: {{ $p['spDefPct'] }}%" role="progressbar" aria-valuenow="{{ $p['spDefPct'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>

        {{-- Vida — texto encima y barra a todo el ancho --}}
        <div title="PS: {{ $p['hpTexto'] }}/{{ $p['maxHpTexto'] }}">
            <div class="vital-label-row">
                <span class="vital-label">PS</span>
                <span class="vital-value vital-hp-num">{{ $p['hpTexto'] }}/{{ $p['maxHpTexto'] }}</span>
            </div>
            <div class="progress hp-progress">
                <div class="progress-bar progress-bar-trail vital-hp-trail" aria-hidden="true"></div>
                <div class="progress-bar {{ $p['hpClass'] }} vital-hp" style="width: {{ $p['hpPct'] }}%" role="progressbar" aria-valuenow="{{ $p['hpPct'] }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>

        {{-- El pokémon va debajo de las barras, centrado --}}
        <div class="text-center pt-1">
            <x-sprite
                size="combat"
                :src="$p['icon']"
                :name="$p['nombre']"
                :shiny="$p['es_shiny']"
                :fallback="$p['iconoNormal']"
                class="pokemon-sprite"
            />
        </div>
    </div>
</div>
