<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain;

use Src\Gimnasios\Domain\Collections\IntCollection;
use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;
use Src\Gimnasios\Domain\Exceptions\GimnasioNoExiste;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Catálogo de los 18 gimnasios del juego. Definidos en código (no en BD).
 * Cada gimnasio tiene: slug, medalla, tipo, nivel mínimo y equipos por etapa
 * (EquipoEtapaGimnasio: vanguardia | retaguardia con species_id).
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
                    1 => new EquipoEtapaGimnasio(new IntCollection([268, 266]), new IntCollection([900])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([11]), new IntCollection([15, 269])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([14]), new IntCollection([12, 267])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([213, 212]), new IntCollection([127])),
                ],
            ),
            'poison' => new Gimnasio(
                slug: 'poison',
                medalla: 'Medalla Veneno',
                tipo: TipoPokemon::VENENO,
                nivelMinimo: 15,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([72]), new IntCollection([33, 92])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([30]), new IntCollection([42, 10112])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([14]), new IntCollection([169, 93])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([31, 34]), new IntCollection([407])),
                ],
            ),
            'normal' => new Gimnasio(
                slug: 'normal',
                medalla: 'Medalla Normal',
                tipo: TipoPokemon::NORMAL,
                nivelMinimo: 20,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([288]), new IntCollection([432, 18])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([108]), new IntCollection([128, 398])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([241]), new IntCollection([53, 22])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([143]), new IntCollection([242, 115])),
                ],
            ),
            'grass' => new Gimnasio(
                slug: 'grass',
                medalla: 'Medalla Planta',
                tipo: TipoPokemon::PLANTA,
                nivelMinimo: 25,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([470]), new IntCollection([455, 407])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([388]), new IntCollection([286, 465])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([272]), new IntCollection([253, 2])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([154]), new IntCollection([71, 3])),
                ],
            ),
            'flying' => new Gimnasio(
                slug: 'flying',
                medalla: 'Medalla Volador',
                tipo: TipoPokemon::VOLADOR,
                nivelMinimo: 31,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([426]), new IntCollection([398, 123])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([472]), new IntCollection([22, 468])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([227]), new IntCollection([142, 169])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([630, 279]), new IntCollection([130])),
                ],
            ),
            'rock' => new Gimnasio(
                slug: 'rock',
                medalla: 'Medalla Roca',
                tipo: TipoPokemon::ROCA,
                nivelMinimo: 36,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([411]), new IntCollection([139, 409])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([346]), new IntCollection([141, 112])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([338]), new IntCollection([464, 464])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([306]), new IntCollection([248, 142])),
                ],
            ),
            'electric' => new Gimnasio(
                slug: 'electric',
                medalla: 'Medalla Eléctrico',
                tipo: TipoPokemon::ELECTRICO,
                nivelMinimo: 41,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([171]), new IntCollection([26, 10100])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([125]), new IntCollection([181, 405])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([10111]), new IntCollection([135, 135])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([462]), new IntCollection([466, 1021])),
                ],
            ),
            'ice' => new Gimnasio(
                slug: 'ice',
                medalla: 'Medalla Hielo',
                tipo: TipoPokemon::HIELO,
                nivelMinimo: 46,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([87]), new IntCollection([362, 461])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([365, 10102]), new IntCollection([10104])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([131]), new IntCollection([473, 460])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([91, 131]), new IntCollection([975])),
                ],
            ),
            'fire' => new Gimnasio(
                slug: 'fire',
                medalla: 'Medalla Fuego',
                tipo: TipoPokemon::FUEGO,
                nivelMinimo: 52,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([59]), new IntCollection([78, 38])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([219]), new IntCollection([609, 937])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([10115]), new IntCollection([229, 936])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([324]), new IntCollection([257, 6])),
                ],
            ),
            'water' => new Gimnasio(
                slug: 'water',
                medalla: 'Medalla Agua',
                tipo: TipoPokemon::AGUA,
                nivelMinimo: 57,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([279]), new IntCollection([319, 121])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([131]), new IntCollection([342, 160])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([91]), new IntCollection([80, 199])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([395]), new IntCollection([230, 260])),
                ],
            ),
            'ground' => new Gimnasio(
                slug: 'ground',
                medalla: 'Medalla Tierra',
                tipo: TipoPokemon::TIERRA,
                nivelMinimo: 62,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([472, 980]), new IntCollection([389])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([450]), new IntCollection([464, 901])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([344]), new IntCollection([51, 530])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([208]), new IntCollection([445, 260])),
                ],
            ),
            'psychic' => new Gimnasio(
                slug: 'psychic',
                medalla: 'Medalla Psíquico',
                tipo: TipoPokemon::PSIQUICO,
                nivelMinimo: 67,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([376, 437]), new IntCollection([121])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([480]), new IntCollection([481, 482])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([249]), new IntCollection([65, 65])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([488, 10002]), new IntCollection([150])),
                ],
            ),
            'dark' => new Gimnasio(
                slug: 'dark',
                medalla: 'Medalla Siniestro',
                tipo: TipoPokemon::SINIESTRO,
                nivelMinimo: 73,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([197]), new IntCollection([319, 229])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([248]), new IntCollection([302, 635])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([215,215,215, 215, 215]), new IntCollection([461])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([630, 862]), new IntCollection([491])),
                ],
            ),
            'ghost' => new Gimnasio(
                slug: 'ghost',
                medalla: 'Medalla Fantasma',
                tipo: TipoPokemon::FANTASMA,
                nivelMinimo: 78,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([979, 711]), new IntCollection([10233])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([477]), new IntCollection([302, 302])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([563]), new IntCollection([478, 429])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([487]), new IntCollection([94, 94])),
                ],
            ),
            'fighting' => new Gimnasio(
                slug: 'fighting',
                medalla: 'Medalla Lucha',
                tipo: TipoPokemon::LUCHA,
                nivelMinimo: 83,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([107]), new IntCollection([237, 106])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([214]), new IntCollection([62, 475])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([297]), new IntCollection([68, 392])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([448, 560]), new IntCollection([257])),
                ],
            ),
            'fairy' => new Gimnasio(
                slug: 'fairy',
                medalla: 'Medalla Hada',
                tipo: TipoPokemon::HADA,
                nivelMinimo: 88,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([959]), new IntCollection([184, 468])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([210]), new IntCollection([10104, 282])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([36]), new IntCollection([40, 122])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([468]), new IntCollection([1006, 282])),
                ],
            ),
            'steel' => new Gimnasio(
                slug: 'steel',
                medalla: 'Medalla Acero',
                tipo: TipoPokemon::ACERO,
                nivelMinimo: 94,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([208, 411]), new IntCollection([227])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([376]), new IntCollection([448, 10102])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([306]), new IntCollection([212, 462])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([379]), new IntCollection([483, 395])),
                ],
            ),
            'dragon' => new Gimnasio(
                slug: 'dragon',
                medalla: 'Medalla Dragón',
                tipo: TipoPokemon::DRAGON,
                nivelMinimo: 100,
                equipos: [
                    1 => new EquipoEtapaGimnasio(new IntCollection([334, 330]), new IntCollection([784])),
                    2 => new EquipoEtapaGimnasio(new IntCollection([706]), new IntCollection([635, 691])),
                    3 => new EquipoEtapaGimnasio(new IntCollection([230]), new IntCollection([445, 373])),
                    4 => new EquipoEtapaGimnasio(new IntCollection([487]), new IntCollection([384, 1007])),
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
