@extends('layouts.app')

@section('title', 'Hábitat - ' . ($habitat['name'] ?? ''))

@section('content')
<a href="/habitats" class="back-link">← Volver a hábitats</a>
<h1 class="habitat-name">{{ $habitat['name'] }}</h1>
<div class="layout">
    <div class="habitat-image">
        <img src="{{ $habitat['image'] }}" alt="{{ $habitat['name'] }}">
    </div>
    <div class="habitat-details">
        <div class="habitat-levels">
            @foreach([1,2,3] as $level)
            <div class="level-row" style="margin-bottom:0.5rem;">
                @if(!empty($habitat['levels'][$level]))
                @foreach($habitat['levels'][$level] as $pokemon)
                <img
                    src="{{ $pokemon['icon'] }}"
                    alt="{{ $pokemon['name'] }}"
                    title="{{ $pokemon['name'] }}"
                    class="icono"
                    onerror="this.style.display='none'">
                @endforeach
                @else
                <span style="font-size:0.9rem; color:#666;">No hay pokemons en este nivel.</span>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection