<?php

declare(strict_types=1);

namespace Src\Mazmorras\Domain;

use Src\Mazmorras\Domain\Collections\PisoConfiguracionCollection;
use Src\Mazmorras\Domain\DataTransferObjects\PisoConfiguracion;

/**
 * Configuración de la mazmorra de un hábitat: lista ordenada de pisos, cada
 * uno con el species_id del jefe que se enfrenta al equipo del jugador (3v1).
 */
final class ConfiguracionMazmorra
{
    private function __construct(
        private readonly PisoConfiguracionCollection $pisos,
    ) {
    }

    /**
     * A partir del json de la columna `habitats.mazmorra`. Si el json es null,
     * vacío o no tiene pisos válidos, devuelve null (mazmorra sin configurar).
     *
     * @param  mixed  $json
     */
    public static function desdeJson(mixed $json): ?self
    {
        if (! is_array($json) || ! isset($json['pisos']) || ! is_array($json['pisos'])) {
            return null;
        }

        $porNumero = [];
        foreach ($json['pisos'] as $piso) {
            if (! is_array($piso) || ! isset($piso['piso']) || ! isset($piso['species_id'])) {
                continue;
            }

            $numero = (int) $piso['piso'];
            $speciesId = (int) $piso['species_id'];
            if ($numero < 1 || $speciesId < 1) {
                continue;
            }

            $porNumero[$numero] = new PisoConfiguracion($numero, $speciesId);
        }

        ksort($porNumero);

        if ($porNumero === []) {
            return null;
        }

        return new self(new PisoConfiguracionCollection(array_values($porNumero)));
    }

    public function totalPisos(): int
    {
        return $this->pisos->count();
    }

    public function speciesDe(int $piso): ?int
    {
        return $this->pisos->porNumero($piso)?->speciesId;
    }

    public function pisos(): PisoConfiguracionCollection
    {
        return $this->pisos;
    }
}
