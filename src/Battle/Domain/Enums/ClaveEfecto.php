<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

enum ClaveEfecto: string
{
    case PERFORACION_ARMADURA = 'armor_pierce';
    case REGEN_DEF = 'regen_def';
    case TORMENTA_ARENA = 'sandstorm_summoner';
    case SEQUIA = 'sequia_summoner';
    case DILUVIO = 'diluvio_summoner';
    case NIEBLA = 'niebla_summoner';
    case GRANIZO = 'granizo_summoner';
    case TURBULENCIAS = 'turbulencias_summoner';
}
