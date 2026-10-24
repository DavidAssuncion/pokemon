<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Terrenos de combate. Los valores coinciden con los identificadores de
 * Pokémon Showdown para poder cargarlos desde datos sin traducciones.
 */
enum TipoTerreno: string
{
    case NONE = 'none';
    case HIERBA = 'grassy';
    case ELECTRICO = 'electric';
    case PSIQUICO = 'psychic';
    case NIEBLA = 'misty';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'sin terreno',
            self::HIERBA => 'terreno de hierba',
            self::ELECTRICO => 'terreno eléctrico',
            self::PSIQUICO => 'terreno psíquico',
            self::NIEBLA => 'terreno de niebla',
        };
    }

    public function esActivo(): bool
    {
        return $this !== self::NONE;
    }
}
