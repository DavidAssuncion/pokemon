<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

final class FaseEvolutiva
{
    /**
     * Fase del pokemon en su cadena evolutiva (1 = forma base).
     *
     * @param  list<array{species_id: int}>  $miembrosCadena
     */
    public static function de(int $speciesId, array $miembrosCadena): int
    {
        return count(array_filter($miembrosCadena, fn (array $miembro): bool => $miembro['species_id'] <= $speciesId));
    }

    /** Fase mínima segura: nunca menor que 1.
     *
     * @param  list<array{species_id: int}>  $miembrosCadena
     */
    public static function deSegura(int $speciesId, array $miembrosCadena): int
    {
        return max(1, self::de($speciesId, $miembrosCadena));
    }
}
