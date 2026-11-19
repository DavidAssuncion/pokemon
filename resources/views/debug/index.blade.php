@extends('layouts.app')

@section('title', 'Debug QA')

@section('content')
@php
    // TASK-064: formateo exclusivamente visual de los deltas (+1.000 / -1.000).
    $formatearDelta = static fn (int $delta): string => ($delta > 0 ? '+' : '-') . number_format(abs($delta), 0, ',', '.');
@endphp

<div class="mx-auto max-w-5xl">
    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-ink">Debug QA</h1>
            <p class="mt-1 text-sm text-ink-muted">
                Ajusta experiencia e inventario de caramelos de tu cuenta.
            </p>
        </div>
        <a href="{{ url('/pokedex') }}" class="text-sm font-medium text-primary-ink hover:underline focus-ring">
            &larr; Volver a la Pokédex
        </a>
    </header>

    <x-ui.alert variant="warning" class="mb-6">
        <p class="font-semibold">Entorno de pruebas</p>
        <p class="mt-0.5">
            Esta página solo existe en <code>local</code>/<code>testing</code> y modifica datos reales de tu cuenta.
        </p>
    </x-ui.alert>

    <x-ui.card title="Experiencia" subtitle="Experiencia total, nivel actual y objetivo del siguiente nivel." padding="sm" as="section" class="mb-6">
        <dl class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-panel border border-line bg-surface-sunken p-3">
                <dt class="text-2xs uppercase tracking-wide text-ink-subtle">Experiencia</dt>
                <dd class="mt-1 text-lg font-bold tabular-nums text-ink">{{ number_format($panel->experiencia, 0, ',', '.') }}</dd>
            </div>
            <div class="rounded-panel border border-line bg-surface-sunken p-3">
                <dt class="text-2xs uppercase tracking-wide text-ink-subtle">Nivel</dt>
                <dd class="mt-1 text-lg font-bold tabular-nums text-ink">{{ number_format($panel->nivel, 0, ',', '.') }}</dd>
            </div>
            <div class="rounded-panel border border-line bg-surface-sunken p-3">
                <dt class="text-2xs uppercase tracking-wide text-ink-subtle">Siguiente nivel</dt>
                <dd class="mt-1 text-lg font-bold tabular-nums text-ink">{{ number_format($panel->experienciaSiguienteNivel, 0, ',', '.') }}</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('qa.debug.experiencia') }}" class="flex flex-wrap items-center gap-2">
            @csrf
            <span class="mr-1 text-sm font-medium text-ink-muted">Ajustar:</span>
            @foreach ($deltasExperiencia as $delta)
                <x-ui.button
                    type="submit"
                    name="delta"
                    value="{{ $delta }}"
                    variant="secondary"
                    size="sm"
                    aria-label="Ajustar experiencia en {{ $formatearDelta($delta) }}"
                    title="Ajustar experiencia en {{ $formatearDelta($delta) }}"
                >
                    {{ $formatearDelta($delta) }}
                </x-ui.button>
            @endforeach
        </form>
    </x-ui.card>

    @include('debug.partials._seccion-caramelos', [
        'titulo' => 'Caramelos de tipo',
        'descripcion' => 'Añade o retira caramelos de tipo del inventario.',
        'filas' => $panel->caramelosTipo,
        'deltasCaramelos' => $deltasCaramelos,
    ])

    @include('debug.partials._seccion-caramelos', [
        'titulo' => 'Caramelos de estadística',
        'descripcion' => 'Añade o retira caramelos EV del inventario.',
        'filas' => $panel->caramelosEv,
        'deltasCaramelos' => $deltasCaramelos,
    ])
</div>
@endsection
