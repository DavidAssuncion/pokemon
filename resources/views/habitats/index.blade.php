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
