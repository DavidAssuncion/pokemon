@extends('layouts.app')

@section('title', 'Sendas')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-ink">Sendas</h1>
    <p class="mt-1 max-w-3xl text-sm text-ink-muted">
        Sube el nivel de cada tipo con la experiencia que ganan tus combatientes y exploradores.
        Los movimientos desbloqueados quedan disponibles para toda la cuenta.
        Niveles 1-3 desbloquean ataques; los niveles 4-5 añaden maestrías pasivas que mejoran a todos tus combatientes.
    </p>
    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
        <span class="rounded-full border border-primary bg-primary-soft px-3 py-1 text-primary-ink">Nv cuenta {{ $nivelJugador ?? 1 }}</span>
        <span class="rounded-full border border-success bg-success-soft px-3 py-1 text-success-ink">{{ $totalDesbloqueados }} / {{ $totalNodos }} movimientos desbloqueados</span>
    </div>
</div>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
    @foreach($tipos as $tipo)
        <a
            href="/sendas/{{ $tipo['slug'] }}"
            class="group rounded-panel border border-line bg-surface p-3 shadow-card transition-all hover:-translate-y-0.5 hover:border-line-strong hover:shadow-md focus-ring"
        >
            <div class="flex items-center gap-2">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-card" style="background-color: color-mix(in oklab, var(--type-{{ $tipo['slug'] }}) 14%, var(--color-surface))">
                    <img src="/images/type/{{ $tipo['slug'] }}.webp" alt="{{ $tipo['nombre'] }}" loading="lazy" decoding="async" class="h-8 w-8 object-contain">
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-ink">{{ $tipo['nombre'] }}</p>
                    <p class="text-2xs font-medium text-ink-muted">
                        Nivel {{ $tipo['nivel'] }} / {{ $tipo['nivel_maximo'] }}
                        @if($tipo['nivel'] >= 4)
                            <span class="ml-1 rounded-full border border-info bg-info-soft px-1.5 py-0.5 text-2xs font-bold uppercase tracking-wide text-info-ink">Maestrías</span>
                        @endif
                    </p>
                </div>
                <span class="text-xs font-bold" style="color: var(--type-{{ $tipo['slug'] }})">{{ $tipo['porcentaje'] }}%</span>
            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-surface-sunken" role="progressbar" aria-valuenow="{{ $tipo['porcentaje'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progreso de {{ $tipo['nombre'] }}">
                <div class="h-full rounded-full transition-all duration-500" style="width: {{ $tipo['porcentaje'] }}%; background-color: var(--type-{{ $tipo['slug'] }})"></div>
            </div>

            <div class="mt-2 flex items-center justify-between gap-2 text-2xs text-ink-muted">
                <span>
                    {{ number_format($tipo['exp'], 0, ',', '.') }} / {{ number_format($tipo['exp_siguiente'], 0, ',', '.') }} exp
                </span>
                <span class="font-semibold text-ink">{{ $tipo['desbloqueados'] }}/{{ $tipo['total_nodos'] }}</span>
            </div>

            <div class="mt-2 flex items-center justify-between gap-2 border-t border-line pt-2">
                <span class="inline-flex items-center gap-1 text-2xs font-semibold text-warning-ink">
                    <img src="/images/candy_type/{{ $tipo['slug'] }}.webp" alt="Caramelos de {{ $tipo['nombre'] }}" loading="lazy" decoding="async" class="h-4 w-4 object-contain" onerror="this.style.display='none'">
                    {{ $tipo['caramelos'] }}
                </span>
                <span class="text-2xs font-bold text-primary-ink group-hover:underline">Ver Senda →</span>
            </div>
        </a>
    @endforeach
</div>
@endsection
