@props(['value', 'label', 'icon' => null, 'color' => 'blue', 'trend' => null])

@php
    $colorClasses = [
        'blue' => 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400',
        'green' => 'bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400',
        'red' => 'bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400',
        'yellow' => 'bg-yellow-50 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400',
        'purple' => 'bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6']) }}>
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $value }}</p>
            @if($trend)
                <p class="text-xs mt-1 {{ str_starts_with($trend, '+') ? 'text-green-600' : 'text-red-600' }}">{{ $trend }}</p>
            @endif
        </div>
        @if($icon)
            <div class="w-12 h-12 rounded-xl {{ $colorClasses[$color] }} flex items-center justify-center text-xl">
                {{ $icon }}
            </div>
        @endif
    </div>
</div>
