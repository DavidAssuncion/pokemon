<div class="{{ $p['claseCarta'] }}"
     data-ref-id="{{ $p['refId'] }}"
     data-team="{{ $p['team'] }}"
     @if($p['selectable']) wire:click="previewTarget({{ $p['team'] }}, {{ $idx }})" role="button" tabindex="0" @endif>
    <div class="card-body p-2 d-flex flex-column gap-1">
        {{-- Sin nombre: el sprite y las insignias identifican al pokémon --}}
        <div class="d-flex flex-wrap align-items-center gap-1">
            @if(! empty($p['cp']))
                <span class="badge bg-purple-100 text-purple-700 small" title="Poder de combate">CP {{ number_format($p['cp']) }}</span>
            @endif
            @if($p['alive'])
                <span class="badge {{ $p['posturaClase'] }}" title="Postura de ronda: {{ $p['posturaLabel'] }}">
                    {{ $p['posturaCorta'] }}
                </span>
            @endif
            @if(!empty($p['item']))
                <span class="badge bg-light border text-dark {{ $p['itemActivo'] ? '' : 'opacity-50' }}"
                      title="{{ $p['itemNombre'] }}{{ $p['itemActivo'] ? '' : ' (bloqueado)' }}">
                    {{ $p['itemIcono'] }}{{ $p['itemBloqueado'] ? ' 🚫' : '' }}
                </span>
            @endif
            @if($p['habilidadAnulada'])
                <span class="badge bg-dark" title="Habilidad anulada">HAB ✖</span>
            @endif
            @if($p['esJefe'])
                <span class="badge bg-danger" title="Jefe">JEFE</span>
                <span class="badge bg-warning text-dark" title="Fase actual">
                    {{ $p['jefe']['nombreFase'] ?? 'Fase 1' }}
                </span>
                @if(!empty($p['jefe']['enrage']))
                    <span class="badge bg-danger" title="Enrage activo">ENRAGE</span>
                @endif
                @foreach($p['jefePartes'] as $parte)
                    <span class="badge {{ $parte['clase'] }}"
                          title="{{ $parte['nombre'] }}: {{ $parte['hpTexto'] }}/{{ $parte['hpMaxTexto'] }}">
                        {{ $parte['nombre'] }} {{ $parte['texto'] }}
                    </span>
                @endforeach
            @endif
            @if($p['statusLabel'] !== '')
                <span class="badge bg-danger status-badge" title="{{ $p['statusLabel'] }}{{ $p['statusTurnsTexto'] }}">
                    @if($p['statusIcono'] !== null)
                        <img src="{{ $p['statusIcono'] }}" alt="{{ $p['statusLabel'] }}" style="width:20px;height:20px;object-fit:contain">
                    @else
                        {{ $p['statusEmoji'] }}
                    @endif
                </span>
            @endif
            @if(! $p['alive'])
                <span class="badge bg-dark ko-badge">K.O.</span>
            @endif
            @if($p['esInvocacion'])
                <span class="badge bg-dark" title="Invocación temporal">INV {{ $p['turnosInvocacion'] }}</span>
            @endif
            @if($p['combo'] > 0)
                <span class="badge bg-info text-dark" title="Racha de combo">
                    COMBO {{ $p['combo'] }} · ×{{ number_format($p['comboMultiplicador'], 2) }}
                </span>
            @endif
            @foreach($p['condicionesBadge'] as $condicion)
                <span class="badge bg-secondary" title="{{ $condicion['turnos'] }} turnos">
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
            <img src="{{ $p['icon'] }}" alt="{{ $p['nombre'] }}" class="img-fluid rounded pokemon-sprite" style="width:72px; height:72px; object-fit:contain; margin: 0 auto;" loading="lazy" @if($p['es_shiny']) onerror="this.onerror=null; this.src='{{ $p['iconoNormal'] }}';" @endif>
        </div>
    </div>
</div>
