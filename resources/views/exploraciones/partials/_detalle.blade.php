{{--
    Detalle de una expedición servido como fragmento HTML.
    Recibe: $expedicion (array del PresentadorExploraciones) y $contexto
    (pagina|historial|detalle). Elige la tarjeta según el contrato ya calculado
    por el backend: las terminadas/archivadas/canceladas llevan `resultado`.
--}}
@php
    $contexto ??= 'detalle';
    $estado = (string) ($expedicion['estado'] ?? '');
    $esTerminada = isset($expedicion['resultado'])
        || in_array($estado, ['terminada', 'archivada', 'cancelada'], true);
@endphp

@if($esTerminada)
    @include('exploraciones.partials._tarjeta-terminada', [
        'terminada' => $expedicion,
        'contexto' => $contexto,
    ])
@else
    @include('exploraciones.partials._tarjeta-activa', [
        'exp' => $expedicion,
        'contexto' => $contexto,
    ])
@endif
