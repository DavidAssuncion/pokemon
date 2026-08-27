@extends('layouts.app')

@section('title', config('app.name', 'Pokémon'))

@section('content')
<div class="flex flex-col items-center justify-center min-h-[calc(100vh-10rem)] text-center">
    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Pokémon Explorer</h1>
    <p class="text-gray-500 dark:text-gray-400 mb-8 max-w-md">
        Gestiona tus equipos, explora hábitats y recluta Pokémon.
    </p>
    <a href="/habitats"
       class="px-6 py-3 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700 transition-colors">
        Ir a Hábitats
    </a>
</div>
@endsection
