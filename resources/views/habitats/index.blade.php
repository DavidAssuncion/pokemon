@extends('layouts.app')

@section('title', 'Hábitats')

@section('content')
<h1 class="text-2xl font-bold text-ink mb-6">Provincias</h1>

<div x-data="habitatsApp()">
    @php
        $temporalIndex = count($provincias);
    @endphp

    <!-- Tabs -->
    <div class="flex flex-wrap gap-2 mb-6" role="tablist" aria-label="Provincias">
        @foreach($provincias as $i => $province)
            <button
                type="button"
                role="tab"
                @click="activeTab = {{ $i }}"
                :class="activeTab === {{ $i }}
                    ? 'bg-primary text-on-primary shadow'
                    : 'bg-surface text-ink border border-line hover:bg-surface-sunken'"
                class="px-4 py-2 rounded-control text-sm font-medium transition-colors focus-ring"
            >
                {{ $province['name'] }}
            </button>
        @endforeach

        @if($provinciaTemporal !== null)
            <button
                type="button"
                role="tab"
                @click="activeTab = {{ $temporalIndex }}"
                :class="activeTab === {{ $temporalIndex }}
                    ? 'bg-primary text-on-primary shadow'
                    : 'bg-surface text-ink border border-line hover:bg-surface-sunken'"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-control text-sm font-medium transition-colors focus-ring"
            >
                Temporal
                <span class="rounded-full bg-surface-sunken px-2 py-0.5 text-2xs font-bold text-ink">
                    {{ $provinciaTemporal['total'] }}
                </span>
            </button>
        @endif
    </div>

    <!-- Province panels -->
    @foreach($provincias as $i => $province)
        <div x-show="activeTab === {{ $i }}" x-cloak class="province-panel">
            <h2 class="text-lg font-semibold text-ink mb-4">{{ $province['name'] }}</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-6">
                @foreach($province['habitats'] as $habitat)
                    <div>
                        <a
                            href="/habitats/{{ $habitat['id'] }}"
                            class="block bg-surface rounded-card shadow-sm border border-line overflow-hidden hover:shadow-md transition-all hover:border-primary"
                        >
                            <!-- Marco 3:2: la imagen se estira para llenar todo el marco -->
                            <div class="w-full aspect-[3/2] bg-surface-sunken overflow-hidden">
                                @php
                                    $habitatImagePath = resource_path('habitats_img/'.$habitat['id'].'.webp');
                                    $habitatImageVersion = is_file($habitatImagePath) ? filemtime($habitatImagePath) : 0;
                                @endphp
                                <img
                                    src="/habitats-img/{{ $habitat['id'] }}.webp?v={{ $habitatImageVersion }}"
                                    alt="{{ $habitat['name'] }}"
                                    class="block w-full h-full object-fill"
                                    onerror="this.style.display='none'"
                                >
                            </div>
                            <div class="h-11 px-4 bg-surface border-t border-line flex items-center justify-center">
                                <p class="w-full text-sm font-medium text-center text-ink line-clamp-2">{{ $habitat['name'] }}</p>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    @if($provinciaTemporal !== null)
        <div x-show="activeTab === {{ $temporalIndex }}" x-cloak class="province-panel">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-ink">{{ $provinciaTemporal['nombre'] }}</h2>
                    <p class="text-sm text-ink-muted">{{ $provinciaTemporal['descripcion'] }}</p>
                </div>
                <span class="rounded-full bg-warning-soft px-3 py-1 text-2xs font-bold uppercase tracking-wide text-warning-ink">
                    Evento temporal
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                @foreach($provinciaTemporal['facciones'] as $faccion)
                    <a
                        href="{{ $faccion['enlace'] }}"
                        class="group block overflow-hidden rounded-card border-4 bg-surface shadow-sm transition-all hover:shadow-md focus-ring"
                        style="border-color: {{ $faccion['color'] }};"
                        aria-label="Entrar en el hábitat de {{ $faccion['nombre'] }}"
                    >
                        <div class="w-full aspect-[3/2] overflow-hidden bg-surface-sunken p-3">
                            <img
                                src="{{ $faccion['imagen_url'] }}"
                                alt="{{ $faccion['nombre'] }}"
                                class="block h-full w-full object-contain transition-transform duration-300 group-hover:scale-105"
                                loading="lazy"
                                decoding="async"
                                onerror="this.onerror=null;this.src='/images/misc/raid.webp'"
                            >
                        </div>
                        <div class="space-y-2 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="min-w-0 truncate text-sm font-bold text-ink">{{ $faccion['nombre'] }}</h3>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-2xs font-bold uppercase {{ $faccion['estado'] === 'disponible' ? 'bg-success-soft text-success-ink' : 'bg-warning-soft text-warning-ink' }}">
                                    {{ $faccion['estado'] }}
                                </span>
                            </div>
                            <p class="line-clamp-2 text-xs text-ink-muted">{{ $faccion['descripcion'] }}</p>
                            <div class="flex items-center justify-center text-2xs text-ink-muted">
                                <span>Combate {{ $faccion['combate_actual'] }} de {{ $faccion['combates_por_oleada'] }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
function habitatsApp() {
    return {
        activeTab: 0,
    };
}
</script>
@endpush
@endsection
