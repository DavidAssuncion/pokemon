@extends('layouts.app')

@section('title', 'Hábitats')

@section('content')
<h1 class="text-2xl font-bold text-ink mb-6">Provincias</h1>

<div x-data="habitatsApp()">
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
    </div>

    <!-- Province panels -->
    @foreach($provincias as $i => $province)
        <div x-show="activeTab === {{ $i }}" x-cloak class="province-panel">
            <h2 class="text-lg font-semibold text-ink mb-4">{{ $province['name'] }}</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-6">
                @foreach($province['habitats'] as $habitat)
                    <div class="relative group">
                        <a
                            href="/habitats/{{ $habitat['id'] }}"
                            class="block bg-surface rounded-card shadow-sm border border-line overflow-hidden hover:shadow-md transition-all hover:border-primary"
                            :class="{ 'ring-2 ring-warning': isFavorito({{ $habitat['id'] }}) }"
                        >
                            <!-- Image container: fixed 300x300 -->
                            <div class="w-full aspect-square bg-surface-sunken flex items-center justify-center overflow-hidden">
                                @php
                                    $habitatImagePath = resource_path('habitats_img/'.$habitat['id'].'.webp');
                                    $habitatImageVersion = is_file($habitatImagePath) ? filemtime($habitatImagePath) : 0;
                                @endphp
                                <img
                                    src="/habitats-img/{{ $habitat['id'] }}.webp?v={{ $habitatImageVersion }}"
                                    alt="{{ $habitat['name'] }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                    onerror="this.style.display='none'"
                                >
                            </div>
                            <!-- Name: white padding area -->
                            <div class="px-4 py-3 bg-surface border-t border-line">
                                <p class="text-sm font-medium text-center text-ink">{{ $habitat['name'] }}</p>
                            </div>
                        </a>
                        <!-- Favorite toggle button (corner) -->
                        <button
                            @click.stop="toggleFavoritoHabitat({{ $habitat['id'] }})"
                            :disabled="togglingHabitat === {{ $habitat['id'] }}"
                            class="absolute top-2 right-2 w-8 h-8 rounded-full flex items-center justify-center transition-all shadow-sm focus-ring"
                            :class="isFavorito({{ $habitat['id'] }})
                                ? 'bg-warning text-on-primary ring-2 ring-warning hover:bg-warning/90'
                                : 'bg-surface/80 text-ink-subtle hover:bg-warning-soft hover:text-warning'"
                            :title="isFavorito({{ $habitat['id'] }}) ? 'Quitar de favoritos' : 'Añadir a favoritos'"
                            :aria-label="isFavorito({{ $habitat['id'] }}) ? 'Quitar de favoritos' : 'Añadir a favoritos'"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

@push('scripts')
<script>
function habitatsApp() {
    return {
        activeTab: 0,
        favoritosIds: @json($favoritosIds ?? []),
        togglingHabitat: null,

        isFavorito(id) {
            return this.favoritosIds.includes(id);
        },

        async toggleFavoritoHabitat(id) {
            if (this.togglingHabitat === id) return;
            this.togglingHabitat = id;
            try {
                const response = await fetch('/api/habitats/' + id + '/toggle-favorito', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    const data = await response.json();
                    if (data.favorito) {
                        if (!this.favoritosIds.includes(id)) {
                            this.favoritosIds.push(id);
                        }
                    } else {
                        this.favoritosIds = this.favoritosIds.filter(f => f !== id);
                    }
                } else {
                    const data = await response.json().catch(() => ({}));
                    alert(data.message || 'No se pudo actualizar el favorito.');
                }
            } catch (err) {
                console.error('Error toggling habitat favorito:', err);
            } finally {
                this.togglingHabitat = null;
            }
        },
    };
}
</script>
@endpush
@endsection
