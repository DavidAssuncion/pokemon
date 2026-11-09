{{--
    Progreso de EXP del modal de recompensas (cuenta + Sendas de tipo).

    El payload $progreso lo calcula el backend. Si no existe, no se pinta
    nada y el modal conserva su aspecto anterior.
--}}
@php($progreso = $progreso ?? null)

@if (is_array($progreso) && $progreso !== [])
    <div class="reward-progreso"
         wire:key="reward-progreso-{{ md5((string) json_encode($progreso)) }}"
         x-data="rewardProgreso(@js($progreso))"
         x-init="iniciar()"
         @reward-modal-visible.window="iniciar()">

        <div class="visually-hidden" aria-live="polite" aria-atomic="true">
            {{ $progreso['resumen_accesible'] ?? '' }}
        </div>

        @if (($progreso['cuenta'] ?? null) !== null)
            @include('livewire.partials._reward-progreso-barra', [
                'barra' => $progreso['cuenta'],
                'tipo' => 'cuenta',
            ])
        @endif

        @foreach ($progreso['sendas'] ?? [] as $senda)
            @include('livewire.partials._reward-progreso-barra', [
                'barra' => $senda,
                'tipo' => 'senda',
            ])
        @endforeach
    </div>
@endif
