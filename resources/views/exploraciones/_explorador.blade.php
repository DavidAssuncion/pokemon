@php
    $explorador = $explorador ?? [];
    $nombre = $explorador['nombre'] ?? 'Sin reclutado';
    $tipos = $explorador['tipos'] ?? [];
    $animo = $explorador['animo'] ?? ['texto' => '', 'tono' => 'neutro'];
    $hpEstado = $explorador['hp_estado'] ?? 'sin_datos';
    $hpPct = isset($explorador['hp_pct']) ? max(0, min(100, (int) $explorador['hp_pct'])) : null;
    $progresoNivel = max(0, min(100, (int) ($explorador['progreso_nivel'] ?? 0)));

    $claseAnimo = match ($animo['tono'] ?? 'neutro') {
        'positivo' => 'text-success-ink',
        'negativo' => 'text-warning-ink',
        'alerta' => 'text-danger-ink',
        default => 'text-ink-muted',
    };

    $claseHpBarra = match ($hpEstado) {
        'critico' => 'bg-danger',
        'tocado' => 'bg-warning',
        'sano' => 'bg-success',
        default => 'bg-ink-subtle',
    };
@endphp

<section
    class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-control border border-line bg-surface-sunken px-3 py-2.5"
    aria-label="Ficha del explorador"
>
    <div class="flex items-center gap-3 min-w-0">
        @if(!empty($explorador['icono']))
            <div class="relative shrink-0">
                <x-sprite
                    :src="$explorador['icono']"
                    :name="'Icono de '.$nombre"
                    size="64"
                />
                @if($explorador['es_shiny'] ?? false)
                    <span
                        class="absolute -top-1 -right-1 text-warning text-sm leading-none"
                        role="img"
                        aria-label="Variante shiny"
                        title="Variante shiny"
                    >★</span>
                @endif
            </div>
        @endif

        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-sm font-semibold text-ink">{{ $nombre }}</span>
                <span class="px-1.5 py-0.5 rounded bg-warning-soft text-warning-ink border border-warning text-2xs font-bold uppercase">
                    Nv {{ $explorador['nivel'] ?? 1 }}
                </span>
                @if(!empty($explorador['rol_label']))
                    <span class="px-1.5 py-0.5 rounded bg-info-soft text-info-ink border border-info text-2xs font-bold uppercase">
                        {{ $explorador['rol_label'] }}
                    </span>
                @endif
                @foreach($tipos as $tipo)
                    <x-tipo-badge :slug="$tipo['slug'] ?? null" :label="$tipo['nombre'] ?? null" size="xs" />
                @endforeach
            </div>

            @if(!empty($animo['texto']))
                <p class="mt-1 text-3xs italic {{ $claseAnimo }}">{{ $animo['texto'] }}</p>
            @endif
        </div>
    </div>

    <div class="flex-1 min-w-[10rem]">
        <div class="flex items-center justify-between text-2xs text-ink-muted">
            <span>EXP {{ $explorador['exp_total'] ?? 0 }}</span>
            <span>{{ $progresoNivel }}% al siguiente nivel</span>
        </div>
        <div
            class="mt-1 h-1.5 bg-line rounded-full overflow-hidden"
            role="progressbar"
            aria-label="Progreso de nivel de {{ $nombre }}"
            aria-valuenow="{{ $progresoNivel }}"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div class="h-1.5 bg-primary rounded-full transition-all" style="width: {{ $progresoNivel }}%"></div>
        </div>
    </div>

    <div class="shrink-0">
        @if($hpEstado === 'sin_datos' || $hpPct === null)
            <span class="px-2 py-0.5 rounded-full text-2xs font-bold uppercase {{ $explorador['hp_clase'] ?? '' }}">
                {{ $explorador['hp_label'] ?? 'Sin incidencias' }}
            </span>
        @else
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded-full text-2xs font-bold {{ $explorador['hp_clase'] ?? '' }}">
                    {{ $explorador['hp_label'] ?? '' }}
                </span>
                <div
                    class="w-20 h-1.5 bg-line rounded-full overflow-hidden"
                    role="progressbar"
                    aria-label="HP del explorador"
                    aria-valuenow="{{ $hpPct }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div class="h-1.5 {{ $claseHpBarra }}" style="width: {{ $hpPct }}%"></div>
                </div>
            </div>
        @endif
    </div>
</section>
