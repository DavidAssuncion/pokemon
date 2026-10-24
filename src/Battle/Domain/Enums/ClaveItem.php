<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

enum ClaveItem: string
{
    case ORBE_VIDA = 'life_orb';
    case RESTOS = 'leftovers';
    case BANDA_FOCUS = 'focus_sash';
    case BAYA_SITRUS = 'sitrus_berry';
    case BAYA_LUM = 'lum_berry';
    case CINTA_ELECCION = 'choice_band';
    case GAFAS_ELECCION = 'choice_specs';
    case CHALECO_ASALTO = 'assault_vest';
}
