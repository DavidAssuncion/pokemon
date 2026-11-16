<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

/**
 * Estado de presentación de una expedición.
 *
 * `CANCELADA` no aparece en el panel ni en el historial, pero permite
 * representar la fila cuando se consulta directamente.
 */
enum EstadoExpedicion: string
{
    case EXPLORANDO = 'explorando';
    case VOLVIENDO = 'volviendo';
    case PENDIENTE_RESOLUCION = 'pendiente_resolucion';
    case TERMINADA = 'terminada';
    case ARCHIVADA = 'archivada';
    case CANCELADA = 'cancelada';

    /**
     * ¿La expedición ha completado su recorrido y puede revisarse/archivarse?
     */
    public function esTerminada(): bool
    {
        return $this === self::TERMINADA;
    }
}
