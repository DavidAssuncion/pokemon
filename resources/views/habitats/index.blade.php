@extends('layouts.app')

@section('title', 'Hábitats')

@section('content')
<h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Provincias</h1>

<div x-data="{ activeTab: 0 }">
    <!-- Tabs -->
    <div class="flex flex-wrap gap-2 mb-6" role="tablist" aria-label="Provincias">
        @foreach($provincias as $i => $province)
            <button
                type="button"
                role="tab"
                @click="activeTab = {{ $i }}"
                :class="activeTab === {{ $i }}
                    ? 'bg-blue-600 text-white shadow'
                    : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'"
                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
            >
                {{ $province['name'] }}
            </button>
        @endforeach
    </div>

    <!-- Province panels -->
    @foreach($provincias as $i => $province)
        <div x-show="activeTab === {{ $i }}" x-cloak class="province-panel">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ $province['name'] }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($province['habitats'] as $habitat)
                    <a
                        href="/habitats/{{ $habitat['id'] }}"
                        class="group bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-md transition-all hover:border-blue-300 dark:hover:border-blue-600"
                    >
                        <!-- Image container: fixed 300x300 -->
                        <div class="w-full aspect-square bg-gray-100 dark:bg-gray-900 flex items-center justify-center overflow-hidden">
                            <img
                                src="/habitats-img/{{ $habitat['id'] }}.webp"
                                alt="{{ $habitat['name'] }}"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                onerror="this.style.display='none'"
                            >
                        </div>
                        <!-- Name: white padding area -->
                        <div class="px-4 py-3 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $habitat['name'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection
