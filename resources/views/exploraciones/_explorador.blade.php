@php
    $explorador = $explorador ?? [];
    $nombre = $explorador['nombre'] ?? 'Sin reclutado';
    $tipos = $explorador['tipos'] ?? [];
    $animo = $explorador['animo'] ?? ['texto' => '', 'tono' => 'neutro'];
    $hpEstado = $explorador['hp_estado'] ?? 'sin_datos';
    $hpPct = isset($explorador['hp_pct']) ? max(0, min(100, (int) $explorador['hp_pct'])) : null;
    $progresoNivel = max(0, min(100, (int) ($explorador['progreso_nivel'] ?? 0)));

    $claseAnimo = match ($animo['tono'] ?? 'neutro') {
        'positivo' => 'text-green-700 dark:text-green-400',
        'negativo' => 'text-amber-700 dark:text-amber-400',
        'alerta' => 'text-red-700 dark:text-red-400',
        default => 'text-gray-600 dark:text-gray-300',
    };

    $claseHpBarra = match ($hpEstado) {
        'critico' => 'bg-red-500',
        'tocado' => 'bg-amber-500',
        'sano' => 'bg-green-500',
        default => 'bg-gray-400 dark:bg-gray-500',
    };
@endphp

<section
    class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 px-3 py-2.5"
    aria-label="Ficha del explorador"
>
    <div class="flex items-center gap-3 min-w-0">
        @if(!empty($explorador['icono']))
            <div class="relative shrink-0">
                <img
                    src="{{ $explorador['icono'] }}"
                    alt="Icono de {{ $nombre }}"
                    loading="lazy"
                    decoding="async"
                    class="w-12 h-12 object-contain"
                    onerror="this.style.display='none'"
                >
                @if($explorador['es_shiny'] ?? false)
                    <span
                        class="absolute -top-1 -right-1 text-amber-400 text-sm leading-none"
                        role="img"
                        aria-label="Variante shiny"
                        title="Variante shiny"
                    >★</span>
                @endif
            </div>
        @endif

        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $nombre }}</span>
                <span class="px-1.5 py-0.5 rounded bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-[10px] font-bold uppercase">
                    Nv {{ $explorador['nivel'] ?? 1 }}
                </span>
                @if(!empty($explorador['rol_label']))
                    <span class="px-1.5 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[10px] font-bold uppercase">
                        {{ $explorador['rol_label'] }}
                    </span>
                @endif
                @foreach($tipos as $tipo)
                    <x-tipo-badge :slug="$tipo['slug'] ?? null" :label="$tipo['nombre'] ?? null" size="xs" />
                @endforeach
            </div>

            @if(!empty($animo['texto']))
                <p class="mt-1 text-[11px] italic {{ $claseAnimo }}">{{ $animo['texto'] }}</p>
            @endif
        </div>
    </div>

    <div class="flex-1 min-w-[10rem]">
        <div class="flex items-center justify-between text-[10px] text-gray-500 dark:text-gray-400">
            <span>EXP {{ $explorador['exp_total'] ?? 0 }}</span>
            <span>{{ $progresoNivel }}% al siguiente nivel</span>
        </div>
        <div
            class="mt-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden"
            role="progressbar"
            aria-label="Progreso de nivel de {{ $nombre }}"
            aria-valuenow="{{ $progresoNivel }}"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div class="h-1.5 bg-indigo-500 rounded-full transition-all" style="width: {{ $progresoNivel }}%"></div>
        </div>
    </div>

    <div class="shrink-0">
        @if($hpEstado === 'sin_datos' || $hpPct === null)
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $explorador['hp_clase'] ?? '' }}">
                {{ $explorador['hp_label'] ?? 'Sin incidencias' }}
            </span>
        @else
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $explorador['hp_clase'] ?? '' }}">
                    {{ $explorador['hp_label'] ?? '' }}
                </span>
                <div
                    class="w-20 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden"
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
