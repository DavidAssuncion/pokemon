@extends('layouts.app')

@section('title', 'Sendas')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Sendas</h1>
    <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
        Sube el nivel de cada tipo con la experiencia que ganan tus combatientes y exploradores.
        Los movimientos desbloqueados quedan disponibles para toda la cuenta.
        Niveles 1-3 desbloquean ataques; los niveles 4-5 añaden maestrías pasivas que mejoran a todos tus combatientes.
    </p>
    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
        <span class="rounded-full bg-blue-100 px-3 py-1 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">Nv cuenta {{ $nivelJugador ?? 1 }}</span>
        <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">{{ $totalDesbloqueados }} / {{ $totalNodos }} movimientos desbloqueados</span>
    </div>
</div>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
    @foreach($tipos as $tipo)
        <a
            href="/sendas/{{ $tipo['slug'] }}"
            class="group rounded-2xl border border-gray-200 bg-white p-3 shadow-sm transition-all hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600"
        >
            <div class="flex items-center gap-2">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" style="background-color: {{ $tipo['color'] }}22">
                    <img src="/images/type/{{ $tipo['slug'] }}.webp" alt="{{ $tipo['nombre'] }}" loading="lazy" decoding="async" class="h-8 w-8 object-contain">
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-gray-900 dark:text-white">{{ $tipo['nombre'] }}</p>
                    <p class="text-[10px] font-medium text-gray-500 dark:text-gray-400">
                        Nivel {{ $tipo['nivel'] }} / {{ $tipo['nivel_maximo'] }}
                        @if($tipo['nivel'] >= 4)
                            <span class="ml-1 rounded-full bg-purple-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">Maestrías</span>
                        @endif
                    </p>
                </div>
                <span class="text-xs font-bold" style="color: {{ $tipo['color'] }}">{{ $tipo['porcentaje'] }}%</span>
            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" role="progressbar" aria-valuenow="{{ $tipo['porcentaje'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progreso de {{ $tipo['nombre'] }}">
                <div class="h-full rounded-full transition-all duration-500" style="width: {{ $tipo['porcentaje'] }}%; background-color: {{ $tipo['color'] }}"></div>
            </div>

            <div class="mt-2 flex items-center justify-between gap-2 text-[10px] text-gray-500 dark:text-gray-400">
                <span>
                    {{ number_format($tipo['exp'], 0, ',', '.') }} / {{ number_format($tipo['exp_siguiente'], 0, ',', '.') }} exp
                </span>
                <span class="font-semibold text-gray-600 dark:text-gray-300">{{ $tipo['desbloqueados'] }}/{{ $tipo['total_nodos'] }}</span>
            </div>

            <div class="mt-2 flex items-center justify-between gap-2 border-t border-gray-100 pt-2 dark:border-gray-700">
                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-700 dark:text-amber-300">
                    <img src="/images/candy_type/{{ $tipo['slug'] }}.webp" alt="Caramelos de {{ $tipo['nombre'] }}" loading="lazy" decoding="async" class="h-4 w-4 object-contain" onerror="this.style.display='none'">
                    {{ $tipo['caramelos'] }}
                </span>
                <span class="text-[10px] font-bold text-blue-600 group-hover:underline dark:text-blue-400">Ver Senda →</span>
            </div>
        </a>
    @endforeach
</div>
@endsection
