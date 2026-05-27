@extends('layouts.app')

@section('title', 'Hábitat - ' . ($habitat['name'] ?? ''))

@section('content')
    <a href="/habitats" class="back-link">← Volver a hábitats</a>
    <div class="layout">
        <div class="habitat-image">
            <img src="{{ $habitat['image'] }}" alt="{{ $habitat['name'] }}">
        </div>
        <div class="habitat-details">
            <div>
                <h1 class="habitat-name">{{ $habitat['name'] }}</h1>
                <p>Familia de Pokemons del habitat, ordenados por nivel evolutivo.</p>
            </div>
            <div class="habitat-levels">
                @foreach([1,2,3] as $level)
                    <div class="level-row">
                        <h3>Nivel {{ $level }}</h3>
                        @if(!empty($habitat['levels'][$level]))
                            <ul>
                                @foreach($habitat['levels'][$level] as $pokemon)
                                    <li>{{ $pokemon['name'] }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="empty">No hay pokemons en este nivel.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection

