<?php

declare(strict_types=1);

namespace Src\Huevos\Domain;

enum EstadoHuevo: string
{
    case GESTANDO = 'gestando';
    case INCUBANDO = 'incubando';
    case LISTO = 'listo';
}
