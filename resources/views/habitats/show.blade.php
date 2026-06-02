@extends('layouts.app')

@section('title', 'Hábitat - ' . ($habitat['name'] ?? ''))

@section('content')
<a href="/habitats" class="back-link">← Volver a hábitats</a>
<h1 class="habitat-name">{{ $habitat['name'] }}</h1>
<button id="start-exploration-btn" disabled>INICIAR EXPLORACIÓN</button>
<div class="layout">
    <div class="habitat-image">
        <img src="{{ $habitat['image'] }}" alt="{{ $habitat['name'] }}">
        <div class="explorer-team">
            @foreach($teams as $team)
            <div style="border:1px solid #334;padding:8px;margin-top:8px">
                <div>
                    @foreach($team->members as $member)
                    <div style="display:inline-grid;align-items:center;">
                        <div>{{ $member->slot }} - {{ $member->reclutado->nombre ?? '---' }}</div>
                        <div>{{ $member->behavior }}</div>
                        <img
                            src="/iconos/{{ $member->reclutado['nombre'] }}.png"
                            alt="{{ $member->reclutado['name'] }}"
                            title="{{ $member->reclutado['name'] }}"
                            class="icono"
                            onerror="this.style.display='none'">
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
    <div class="habitat-details">
        <div class="habitat-levels">
            @foreach([1,2,3] as $level)
            <div class="level-row" data-level="{{ $level }}  margin-bottom:0.5rem;" onclick="selectLevel(this)">
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

<script>
    let selectedRow = null;

    function selectLevel(row) {
        // si ya estaba seleccionada, la deselecciona
        if (selectedRow === row) {
            row.style.backgroundColor = '';
            selectedRow = null;
            document.getElementById('start-exploration-btn').disabled = true;
            return;
        }

        // limpiar selección anterior
        if (selectedRow) {
            selectedRow.style.backgroundColor = '';
        }

        // seleccionar nueva
        row.style.backgroundColor = '#fea';
        selectedRow = row;

        document.getElementById('start-exploration-btn').disabled = false;
    }

    function startExploration() {
        const selectedLevel = document.querySelector('.level-row[level]:not([style*="background-color: yellow"])');
        if (selectedLevel) {
            alert('Expedición iniciada en nivel ' + selectedLevel.getAttribute('level'));
            // Aquí se registrará la expedición
        } else {
            alert('Por favor selecciona un nivel antes de iniciar la exploración.');
        }
    }

    document.getElementById('start-exploration-btn').addEventListener('click', startExploration);
</script>
@endsection