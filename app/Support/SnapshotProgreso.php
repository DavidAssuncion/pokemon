<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Instantánea inmutable del estado de EXP de un usuario.
 *
 * Se captura con dos SELECT de solo lectura: `users.experiencia` y las filas
 * de `player_type_paths` del usuario. No escribe nada.
 */
final readonly class SnapshotProgreso
{
    /**
     * @param  array<int, int>  $experienciaPorTipo  EXP real indexada por `type_id`.
     */
    public function __construct(
        public int $experienciaCuenta,
        public array $experienciaPorTipo,
    ) {
    }

    /**
     * EXP real de la Senda del tipo indicado; 0 si no existe fila.
     */
    public function experienciaDe(int $tipoId): int
    {
        return $this->experienciaPorTipo[$tipoId] ?? 0;
    }

    /**
     * Identificadores de tipo presentes en la instantánea.
     *
     * @return list<int>
     */
    public function tiposConExperiencia(): array
    {
        return array_keys($this->experienciaPorTipo);
    }
}
