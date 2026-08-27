@props(['color' => 'gray', 'size' => 'md'])

@php
    $colors = [
        'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
        'green' => 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
        'red' => 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
        'yellow' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300',
        'purple' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300',
    ];
    $sizes = [
        'sm' => 'px-2 py-0.5 text-xs',
        'md' => 'px-2.5 py-1 text-xs',
        'lg' => 'px-3 py-1 text-sm',
    ];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center font-medium rounded-full {$sizes[$size]} {$colors[$color]}"]) }}>
    {{ $slot }}
</span>
