{{--
    Barra de EXP de una cuenta o de una Senda de tipo.

    Contrato esperado en $barra (snake_case, calculado en backend):
    - porcentaje_despues, nivel_despues, exp_siguiente, segmentos, niveles_omitidos, tope
    - Cuenta: exp_despues
    - Senda: label, icono, color, exp_ganada, exp_mostrada_despues

    La vista solo formatea y decide qué bloque mostrar según flags ya resueltos.
--}}
@php
    $esCuenta = $tipo === 'cuenta';
    $etiqueta = $esCuenta ? 'Cuenta' : 'Senda de '.$barra['label'];
    $porcentaje = $barra['porcentaje_despues'];
    $expMostrada = $barra['exp_mostrada_despues'] ?? $barra['exp_despues'];
@endphp

<div class="reward-progreso__fila"
     data-reward-barra
     data-nivel-despues="{{ $barra['nivel_despues'] }}"
     data-porcentaje-final="{{ $porcentaje }}">

    <div class="reward-progreso__cabecera">
        @if (! $esCuenta)
            <img src="{{ $barra['icono'] }}"
                 alt=""
                 class="reward-progreso__icono"
                 loading="lazy"
                 decoding="async">
        @endif

        <span class="reward-progreso__nombre">{{ $etiqueta }}</span>

        <span class="reward-progreso__nivel" data-reward-nivel>
            Nv {{ $barra['nivel_despues'] }}
        </span>

        @if (! $esCuenta)
            <span class="reward-progreso__delta">
                +{{ number_format($barra['exp_ganada'], 0, ',', '.') }} EXP
            </span>
        @endif
    </div>

    <div class="reward-progreso__barra"
         role="progressbar"
         aria-valuemin="0"
         aria-valuemax="100"
         aria-valuenow="{{ $porcentaje }}"
         aria-valuetext="{{ $etiqueta }}: nivel {{ $barra['nivel_despues'] }}, {{ $porcentaje }} por ciento"
         aria-label="{{ $etiqueta }}">

        <div class="reward-progreso__relleno"
             data-reward-fill
             data-segmentos='@json($barra['segmentos'])'
             style="width: {{ $porcentaje }}%;@if (! $esCuenta) background-color: {{ $barra['color'] }};@endif"></div>
    </div>

    <div class="reward-progreso__detalle">
        <span>
            {{ number_format($expMostrada, 0, ',', '.') }}
            / {{ number_format($barra['exp_siguiente'], 0, ',', '.') }} EXP
        </span>

        @if ($barra['niveles_omitidos'] > 0)
            <span class="reward-progreso__omitidos">
                +{{ $barra['niveles_omitidos'] }} niveles sin animar
            </span>
        @endif

        @if ($barra['tope'] === true)
            <span class="reward-progreso__tope">Tope alcanzado</span>
        @endif
    </div>
</div>
