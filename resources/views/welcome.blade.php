@extends('layouts.app')

@section('title', config('app.name', 'Pokémon'))

@section('content')
<div class="flex flex-col items-center justify-center min-h-[calc(100vh-10rem)] text-center">
    <h1 class="text-3xl font-bold text-ink mb-4">Pokémon Explorer</h1>
    <p class="text-ink-muted mb-8 max-w-md">
        Gestiona tus equipos, explora hábitats y recluta Pokémon.
    </p>
    <x-ui.button href="/habitats">Ir a Hábitats</x-ui.button>
</div>
@endsection
