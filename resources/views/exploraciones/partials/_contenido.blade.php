{{--
    Contenedor compartido de expediciones: secciones Activas, Resultados por
    revisar y —solo en el historial— Archivadas. Lo usan la página
    `/exploraciones` y el fragmento del historial.

    Recibe:
      - $activas, $terminadas, $archivadas: listas del PresentadorExploraciones.
      - $contexto: pagina|historial|detalle; prefija los IDs de sección para no
        duplicarlos entre la página y el modal de historial.
      - $nivelJugador: se conserva por contrato; los flags `requiere_nivel` ya
        llegan calculados en cada tarjeta.

    Sin Alpine ni cálculos: solo presenta los datos recibidos. Las acciones las
    gestiona el listener delegado del store mediante `data-expedicion-accion`.
--}}
@php
    $activas ??= [];
    $terminadas ??= [];
    $archivadas ??= [];
    $contexto ??= 'pagina';
    $nivelJugador ??= 1;
@endphp

<!-- Section 1: Activas -->
<section class="mb-8" aria-labelledby="{{ $contexto }}-activas-title">
    <div class="flex items-center justify-between bg-surface-sunken border border-line rounded-control px-4 py-2 mb-4">
        <h2 id="{{ $contexto }}-activas-title" class="text-sm font-bold uppercase tracking-wide text-ink">Activas</h2>
        @if(count($activas) > 0)
            <span class="px-2 py-0.5 bg-primary-soft text-primary-ink text-xs font-bold rounded-full">
                {{ count($activas) }}
            </span>
        @endif
    </div>

    @forelse($activas as $exp)
        @include('exploraciones.partials._tarjeta-activa', ['exp' => $exp, 'contexto' => $contexto])
    @empty
        <div class="bg-surface rounded-card shadow-card border border-line p-8 text-center" role="status">
            <svg class="w-12 h-12 mx-auto text-ink-subtle mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-ink-muted mb-1">No hay exploraciones activas</p>
            <p class="text-xs text-ink-subtle">
                Envía a un explorador desde un <a href="/habitats" class="text-primary-ink hover:underline focus-ring">hábitat</a>
            </p>
        </div>
    @endforelse
</section>

<!-- Section 2: Terminadas (resultados por revisar) -->
<section aria-labelledby="{{ $contexto }}-terminadas-title">
    <div class="flex items-center justify-between bg-surface-sunken border border-line rounded-control px-4 py-2 mb-4">
        <h2 id="{{ $contexto }}-terminadas-title" class="text-sm font-bold uppercase tracking-wide text-ink">Resultados por revisar</h2>
        @if(count($terminadas) > 0)
            <span class="px-2 py-0.5 bg-success-soft text-success-ink text-xs font-bold rounded-full">
                {{ count($terminadas) }}
            </span>
        @endif
    </div>

    @forelse($terminadas as $terminada)
        @include('exploraciones.partials._tarjeta-terminada', ['terminada' => $terminada, 'contexto' => $contexto])
    @empty
        <div class="bg-surface rounded-card shadow-card border border-line p-6 text-center" role="status">
            <p class="text-sm text-ink-muted">No hay resultados pendientes de revisar</p>
        </div>
    @endforelse
</section>

@if($contexto === 'historial' && count($archivadas) > 0)
    <!-- Section 3: Archivadas (solo historial) -->
    <section class="mt-8" aria-labelledby="{{ $contexto }}-archivadas-title">
        <div class="flex items-center justify-between bg-surface-sunken border border-line rounded-control px-4 py-2 mb-4">
            <h2 id="{{ $contexto }}-archivadas-title" class="text-sm font-bold uppercase tracking-wide text-ink">Archivadas</h2>
            <span class="px-2 py-0.5 bg-surface-sunken text-ink-muted text-xs font-bold rounded-full">
                {{ count($archivadas) }}
            </span>
        </div>

        @foreach($archivadas as $archivada)
            @include('exploraciones.partials._tarjeta-terminada', ['terminada' => $archivada, 'contexto' => $contexto])
        @endforeach
    </section>
@endif
