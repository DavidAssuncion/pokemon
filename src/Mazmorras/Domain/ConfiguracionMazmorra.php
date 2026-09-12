<?php

declare(strict_types=1);

namespace Src\Mazmorras\Domain;

/**
 * Configuración de la mazmorra de un hábitat: lista ordenada de pisos, cada
 * uno con el species_id del jefe que se enfrenta al equipo del jugador (5v1).
 */
final class ConfiguracionMazmorra
{
    /**
     * @param  list<array{piso: int, species_id: int}>  $pisos
     */
    private function __construct(
        private readonly array $pisos,
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

        $pisos = [];
        foreach ($json['pisos'] as $piso) {
            if (! is_array($piso) || ! isset($piso['piso']) || ! isset($piso['species_id'])) {
                continue;
            }

            $numero = (int) $piso['piso'];
            $speciesId = (int) $piso['species_id'];
            if ($numero < 1 || $speciesId < 1) {
                continue;
            }

            $pisos[$numero] = ['piso' => $numero, 'species_id' => $speciesId];
        }

        ksort($pisos);

        if ($pisos === []) {
            return null;
        }

        return new self(array_values($pisos));
    }

    public function totalPisos(): int
    {
        return count($this->pisos);
    }

    public function speciesDe(int $piso): ?int
    {
        return $this->pisos[$piso - 1]['species_id'] ?? null;
    }

    /** @return list<array{piso: int, species_id: int}> */
    public function todos(): array
    {
        return $this->pisos;
    }
}
