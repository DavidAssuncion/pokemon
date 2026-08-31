@extends('layouts.app')

@section('title', 'Elige tu equipo inicial')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Elige tu equipo inicial</h1>
    <p class="text-gray-500 dark:text-gray-400 mb-8">Selecciona uno de los tres equipos predefinidos para comenzar tu aventura.</p>

    <div class="grid md:grid-cols-3 gap-6">
        @foreach($equipos as $equipo)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-2">{{ $equipo['nombre'] }}</h2>
            <ul class="space-y-1 mb-4">
                @foreach($equipo['pokemon_nombres'] as $nombre)
                <li class="text-sm text-gray-600 dark:text-gray-400">• {{ $nombre }}</li>
                @endforeach
            </ul>
            <form method="POST" action="{{ url('/onboarding/equipo-inicial') }}">
                @csrf
                <input type="hidden" name="team_key" value="{{ $equipo['key'] }}">
                <button
                    type="submit"
                    class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 transition-colors"
                >
                    Elegir {{ $equipo['nombre'] }}
                </button>
            </form>
        </div>
        @endforeach
    </div>
</div>
@endsection