@extends('layouts.app')

@section('title', 'Elige tu equipo inicial')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-ink mb-6">Elige tu equipo inicial</h1>
    <p class="text-ink-muted mb-8">Selecciona uno de los tres equipos predefinidos para comenzar tu aventura.</p>

    <div class="grid md:grid-cols-3 gap-6">
        @foreach($equipos as $equipo)
        <x-ui.card :title="$equipo->nombre">
            <ul class="space-y-1 mb-4">
                @foreach($equipo->pokemonNombres as $nombre)
                <li class="text-sm text-ink-muted">• {{ $nombre }}</li>
                @endforeach
            </ul>
            <form method="POST" action="{{ url('/onboarding/equipo-inicial') }}">
                @csrf
                <input type="hidden" name="team_key" value="{{ $equipo->key }}">
                <x-ui.button type="submit" class="w-full">Elegir {{ $equipo->nombre }}</x-ui.button>
            </form>
        </x-ui.card>
        @endforeach
    </div>
</div>
@endsection
