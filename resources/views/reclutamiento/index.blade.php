@extends('layouts.app')

@section('title', 'Reclutamiento')

@section('content')
<div x-data x-init="$store.reclutamientoNavbar.hidratar(@js($reclutables ?? []))">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-ink">Reclutamiento</h1>
            <p class="text-sm text-ink-muted mt-1">
                Pokémons que sueñan con unirse a tus filas
            </p>
        </div>
        <button
            type="button"
            x-cloak
            x-show="$store.reclutamientoNavbar.items.length > 0"
            @click="$store.reclutamientoNavbar.solicitarDescarteTodos()"
            class="px-4 py-2 bg-danger text-on-primary rounded-control text-sm font-medium hover:bg-danger/90 focus-ring transition-colors flex items-center gap-2 self-start"
        >
            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
            Descartar todos
        </button>
    </div>

    @include('reclutamiento.partials._grid')
</div>
@endsection
