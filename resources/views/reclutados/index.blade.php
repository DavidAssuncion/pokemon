@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Reclutados y Equipos</h1>

    @if(session('error'))
    <div style="color:tomato">{{ session('error') }}</div>
    @endif

    <div style="display:flex;gap:2rem">
        <div style="flex:1">
            <h2>Equipos</h2>

            <form method="POST" action="/teams">
                @csrf
                <input name="name" placeholder="Nombre del equipo" required />
                <button type="submit">Crear equipo</button>
            </form>

            @foreach($teams as $team)
            <div style="border:1px solid #334;padding:8px;margin-top:8px">
                <strong>{{ $team->name }}</strong>
                <form method="POST" action="/teams/{{ $team->id }}" style="display:inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit">Eliminar</button>
                </form>

                <div>
                    <h4>Miembros</h4>
                    @foreach($team->members as $member)
                    <div style="display:flex;align-items:center;gap:8px">
                        <div>{{ $member->slot }} - {{ $member->reclutado->nombre ?? '---' }}</div>
                        <div>{{ $member->behavior }}</div>
                        <form method="POST" action="/teams/remove-member">
                            @csrf
                            <input type="hidden" name="member_id" value="{{ $member->id }}" />
                            <button type="submit">Quitar</button>
                        </form>
                    </div>
                    @endforeach
                </div>

                <div style="margin-top:8px">
                    <form method="POST" action="/teams/add-member">
                        @csrf
                        <input type="hidden" name="team_id" value="{{ $team->id }}" />
                        <select name="reclutado_id">
                            @foreach($reclutados as $r)
                            @php
                            $inTeam = \App\Models\TeamMember::where('pokemon_id', $r->id)->exists();
                            @endphp
                            @if(!$inTeam)
                            <option value="{{ $r->id }}">{{ $r->nombre }} ({{ $r->pokemon->name ?? '-' }})</option>
                            @endif
                            @endforeach
                        </select>
                        <select name="slot">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                        </select>
                        <select name="behavior">
                            <option value="VANGUARDIA">Vanguardia</option>
                            <option value="COMBATIENTE">Combatiente</option>
                            <option value="RECOLECTOR">Recolector</option>
                            <option value="SOPORTE">Soporte</option>
                        </select>
                        <button type="submit">Añadir</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>

        <div style="flex:1">
            <h2>Pokémon Reclutados</h2>
            <div>
                @foreach($reclutados as $r)
                <div class="reclutado-item">
                    <img src="iconos/{{ $r->pokemon->name}}.png" alt="{{ $r->pokemon->name ?? '' }}" loading="lazy" decoding="async" class="icono">
                    <strong>{{ $r->nombre }}</strong>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection