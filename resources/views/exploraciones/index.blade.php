@extends('layouts.app')

@section('title', 'Exploraciones')

@section('content')
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-ink">Exploraciones</h1>
        <p class="text-sm text-ink-muted mt-1">Cada explorador vive su propia expedición</p>
    </div>

    @if($avisoProcesamiento ?? false)
        <div
            role="status"
            class="mb-4 px-4 py-3 rounded-control bg-warning-soft border border-warning/40 text-sm text-warning-ink"
        >
            <p>No se pudieron actualizar todas las expediciones; se reintentará automáticamente.</p>
            @if($agotadoPorTiempo ?? false)
                <p class="mt-1">Se actualizaron las primeras expediciones; recarga para continuar.</p>
            @endif
        </div>
    @endif

    <div id="expediciones-contenido" data-expediciones-contexto="pagina">
        @include('exploraciones.partials._contenido', [
            'activas' => $activas ?? [],
            'terminadas' => $terminadas ?? [],
            'archivadas' => [],
            'contexto' => 'pagina',
            'nivelJugador' => $nivelJugador ?? 1,
        ])
    </div>
@endsection
