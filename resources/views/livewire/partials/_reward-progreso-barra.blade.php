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

    // Estado inicial de la animación: el primer segmento ya trae el porcentaje
    // y el nivel previos. Si no hay segmentos (tope ya alcanzado), se pinta el
    // estado final y no se anima. Así la barra nunca retrocede al abrirse.
    $segmentos = $barra['segmentos'];
    $sinSegmentos = $segmentos === [];
    $porcentajeInicial = $sinSegmentos ? $porcentaje : (int) $segmentos[0]['desde'];
    $nivelInicial = $sinSegmentos ? (int) $barra['nivel_despues'] : (int) $segmentos[0]['nivel'];
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
            Nv {{ $nivelInicial }}
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
         aria-valuenow="{{ $porcentajeInicial }}"
         aria-valuetext="{{ $etiqueta }}: nivel {{ $nivelInicial }}, {{ $porcentajeInicial }} por ciento"
         aria-label="{{ $etiqueta }}">

        <div class="reward-progreso__relleno"
             data-reward-fill
             data-segmentos='@json($barra['segmentos'])'
             style="width: {{ $porcentajeInicial }}%;@if (! $esCuenta) background-color: {{ $barra['color'] }};@endif"></div>
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
