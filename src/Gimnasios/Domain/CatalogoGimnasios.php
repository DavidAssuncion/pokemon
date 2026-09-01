<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain;

use Src\Gimnasios\Domain\Exceptions\GimnasioNoExiste;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Catálogo de los 5 gimnasios del juego. Definidos en código (no en BD).
 * Cada gimnasio tiene: slug, medalla, tipo, nivel mínimo y equipos por etapa
 * (species_id de los pokémon).
 */
final class CatalogoGimnasios
{
    /** @var array<string, Gimnasio> */
    private array $gimnasios;

    public function __construct()
    {
        $this->gimnasios = [
            'bug' => new Gimnasio(
                slug: 'bug',
                medalla: 'Medalla Bicho',
                tipo: TipoPokemon::BICHO,
                nivelMinimo: 10,
                equipos: [
                    1 => [268, 266, 900],
                    2 => [11, 15, 269],
                    3 => [14, 12, 267],
                    4 => [213, 212, 127],
                ],
            ),
            'poison' => new Gimnasio(
                slug: 'poison',
                medalla: 'Medalla Veneno',
                tipo: TipoPokemon::VENENO,
                nivelMinimo: 15,
                equipos: [
                    1 => [72, 33, 92],
                    2 => [30, 42, 316],
                    3 => [14, 169, 93],
                    4 => [31, 34, 407],
                ],
            ),
            'normal' => new Gimnasio(
                slug: 'normal',
                medalla: 'Medalla Normal',
                tipo: TipoPokemon::NORMAL,
                nivelMinimo: 20,
                equipos: [
                    1 => [288, 113, 18],
                    2 => [108, 40, 398],
                    3 => [241, 53, 22],
                    4 => [143, 242, 115],
                ],
            ),
            'grass' => new Gimnasio(
                slug: 'grass',
                medalla: 'Medalla Planta',
                tipo: TipoPokemon::PLANTA,
                nivelMinimo: 25,
                equipos: [
                    1 => [470, 455, 407],
                    2 => [388, 286, 465],
                    3 => [272, 253, 2],
                    4 => [154, 71, 3],
                ],
            ),
            'flying' => new Gimnasio(
                slug: 'flying',
                medalla: 'Medalla Volador',
                tipo: TipoPokemon::VOLADOR,
                nivelMinimo: 31,
                equipos: [
                    1 => [426, 398, 123],
                    2 => [472, 22, 468],
                    3 => [227, 142, 169],
                    4 => [630, 279, 130],
                ],
            ),
        ];
    }

    /** @return list<Gimnasio> */
    public function todos(): array
    {
        return array_values($this->gimnasios);
    }

    public function porSlug(string $slug): ?Gimnasio
    {
        return $this->gimnasios[$slug] ?? null;
    }

    /** @throws GimnasioNoExiste */
    public function porSlugOrFail(string $slug): Gimnasio
    {
        $gimnasio = $this->porSlug($slug);
        if ($gimnasio === null) {
            throw new GimnasioNoExiste();
        }

        return $gimnasio;
    }

    public function existe(string $slug): bool
    {
        return isset($this->gimnasios[$slug]);
    }
}
