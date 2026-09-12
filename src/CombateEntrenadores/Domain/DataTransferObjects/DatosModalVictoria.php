<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain\DataTransferObjects;

use Src\CombateEntrenadores\Domain\Collections\ItemCarameloCollection;

/**
 * Datos de presentación del modal de victoria
 * (OtorgarRecompensasEntrenador::otorgar()).
 *
 * Sustituye el array {exp_total, exp_miembro, caramelos} (deuda F6). toArray()
 * solo se usa en la frontera (presentación del modal). `medalla` se añade
 * solo cuando no es null (combate de gimnasio ganado).
 */
final readonly class DatosModalVictoria
{
    public function __construct(
        public readonly int $expTotal,
        public readonly int $expMiembro,
        public readonly ItemCarameloCollection $caramelos,
        public readonly ?string $medalla = null,
    ) {
    }

    /**
     * Nueva instancia con la medalla (inmutable): caso gimnasio completado.
     */
    public function conMedalla(string $medalla): self
    {
        return new self(
            expTotal: $this->expTotal,
            expMiembro: $this->expMiembro,
            caramelos: $this->caramelos,
            medalla: $medalla,
        );
    }

    /**
     * Frontera — shape exacto del contrato previo (claves snake_case).
     * `medalla` solo se incluye cuando no es null.
     *
     * @return array{exp_total: int, exp_miembro: int, caramelos: list<array{nombre: string, imagen: string, cantidad: int}>, medalla?: string}
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        $data = [
            'exp_total' => $this->expTotal,
            'exp_miembro' => $this->expMiembro,
            'caramelos' => $this->caramelos->toArray(),
        ];

        if ($this->medalla !== null) {
            $data['medalla'] = $this->medalla;
        }

        return $data;
    }
}
