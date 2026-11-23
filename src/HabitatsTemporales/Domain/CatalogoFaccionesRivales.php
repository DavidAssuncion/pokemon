<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain;

use InvalidArgumentException;
use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;
use Src\HabitatsTemporales\Domain\Collections\CombateRivalCollection;
use Src\HabitatsTemporales\Domain\Collections\FaccionRivalCollection;
use Src\HabitatsTemporales\Domain\Collections\OleadaRivalCollection;
use Src\HabitatsTemporales\Domain\Exceptions\FaccionNoExiste;
use Src\Shared\Collections\IntCollection;

/**
 * Catálogo en código de las 5 facciones rivales de los hábitats temporales.
 *
 * Cada facción define 3 plantillas de recluta y 3 jefes (2 secundarios + 1
 * capitán). Las plantillas rotan entre oleadas; lo que escala es el perfil
 * IV/EV y el jefe. Los equipos son provisionales hasta la confirmación del
 * desarrollador.
 */
final class CatalogoFaccionesRivales
{
    /** @var array<string, FaccionRival> */
    private array $facciones;

    public function __construct()
    {
        $this->facciones = [
            'rocket' => $this->rocket(),
            'magma' => $this->magma(),
            'aqua' => $this->aqua(),
            'galaxia' => $this->galaxia(),
            'skull' => $this->skull(),
        ];
    }

    public function todas(): FaccionRivalCollection
    {
        return (new FaccionRivalCollection(array_values($this->facciones)))
            ->sort(static fn (FaccionRival $a, FaccionRival $b): int => $a->orden <=> $b->orden);
    }

    public function porSlug(string $slug): FaccionRival
    {
        if (! isset($this->facciones[$slug])) {
            throw new FaccionNoExiste($slug);
        }

        return $this->facciones[$slug];
    }

    public function existe(string $slug): bool
    {
        return isset($this->facciones[$slug]);
    }

    private function rocket(): FaccionRival
    {
        return new FaccionRival(
            slug: 'rocket',
            nombre: 'Team Rocket',
            descripcion: 'Reclutas de Team Rocket con Pokémon de callejón y un capitán que no perdona.',
            color: '#D32F2F',
            imagen: 'Team_Rocket',
            orden: 1,
            medallasDesbloqueo: 2,
            oleadas: $this->oleadas(
                reclutas: [
                    $this->plantilla(
                        nombre: 'Recluta Rocket',
                        sprite: 'roughneck',
                        descripcion: 'Recluta novato con Pokémon de callejón.',
                        vanguardia: [41, 19],
                        retaguardia: [23],
                    ),
                    $this->plantilla(
                        nombre: 'Recluta Rocket veterano',
                        sprite: 'scientist',
                        descripcion: 'Veterano con Golbat, Raticate y Koffing.',
                        vanguardia: [42, 20],
                        retaguardia: [109],
                    ),
                    $this->plantilla(
                        nombre: 'Recluta Rocket de élite',
                        sprite: 'roughneck',
                        descripcion: 'Élite con Arbok, Weezing y Muk.',
                        vanguardia: [24, 110],
                        retaguardia: [89],
                    ),
                ],
                jefes: [
                    1 => $this->plantilla(
                        nombre: 'Proton',
                        sprite: 'scientist',
                        descripcion: 'Jefe secundario de Team Rocket.',
                        vanguardia: [42, 110],
                        retaguardia: [24],
                    ),
                    2 => $this->plantilla(
                        nombre: 'Ariana',
                        sprite: 'beauty',
                        descripcion: 'Jefa secundaria de Team Rocket.',
                        vanguardia: [24, 110],
                        retaguardia: [229],
                    ),
                    3 => $this->plantilla(
                        nombre: 'Giovanni',
                        sprite: 'giovanni',
                        descripcion: 'Capitán de Team Rocket.',
                        vanguardia: [31, 34],
                        retaguardia: [112],
                    ),
                ],
            ),
        );
    }

    private function magma(): FaccionRival
    {
        return new FaccionRival(
            slug: 'magma',
            nombre: 'Team Magma',
            descripcion: 'Reclutas de Team Magma con Pokémon de fuego y tierra.',
            color: '#E65100',
            imagen: 'Equipo_Magma_ROZA',
            orden: 2,
            medallasDesbloqueo: 3,
            oleadas: $this->oleadas(
                reclutas: [
                    $this->plantilla(
                        nombre: 'Recluta Magma',
                        sprite: 'kindler',
                        descripcion: 'Recluta con Pokémon de fuego y tierra.',
                        vanguardia: [322, 261],
                        retaguardia: [343],
                    ),
                    $this->plantilla(
                        nombre: 'Recluta Magma veterano',
                        sprite: 'hiker',
                        descripcion: 'Veterano con Camerupt, Mightyena y Claydol.',
                        vanguardia: [323, 262],
                        retaguardia: [344],
                    ),
                    $this->plantilla(
                        nombre: 'Recluta Magma de élite',
                        sprite: 'kindler',
                        descripcion: 'Élite con Torkoal, Houndoom y Claydol.',
                        vanguardia: [324, 229],
                        retaguardia: [344],
                    ),
                ],
                jefes: [
                    1 => $this->plantilla(
                        nombre: 'Tabitha',
                        sprite: 'hiker',
                        descripcion: 'Jefe secundario de Team Magma.',
                        vanguardia: [323, 262],
                        retaguardia: [344],
                    ),
                    2 => $this->plantilla(
                        nombre: 'Courtney',
                        sprite: 'kindler',
                        descripcion: 'Jefa secundaria de Team Magma.',
                        vanguardia: [323, 324],
                        retaguardia: [229],
                    ),
                    3 => $this->plantilla(
                        nombre: 'Magno',
                        sprite: 'magno',
                        descripcion: 'Capitán de Team Magma.',
                        vanguardia: [323, 324],
                        retaguardia: [76],
                    ),
                ],
            ),
        );
    }

    private function aqua(): FaccionRival
    {
        return new FaccionRival(
            slug: 'aqua',
            nombre: 'Team Aqua',
            descripcion: 'Reclutas de Team Aqua con Pokémon de agua y siniestro.',
            color: '#1565C0',
            imagen: 'Equipo_Aqua_ROZA',
            orden: 3,
            medallasDesbloqueo: 4,
            oleadas: $this->oleadas(
                reclutas: [
                    $this->plantilla(
                        nombre: 'Recluta Aqua',
                        sprite: 'sailor',
                        descripcion: 'Recluta con Pokémon de agua y siniestro.',
                        vanguardia: [318, 261],
                        retaguardia: [363],
                    ),
                    $this->plantilla(
                        nombre: 'Recluta Aqua veterano',
                        sprite: 'swimmerf',
                        descripcion: 'Veterana con Sharpedo, Mightyena y Sealeo.',
                        vanguardia: [319, 262],
                        retaguardia: [364],
                    ),
                    $this->plantilla(
                        nombre: 'Recluta Aqua de élite',
                        sprite: 'sailor',
                        descripcion: 'Élite con Sharpedo, Sealeo y Mightyena.',
                        vanguardia: [319, 364],
                        retaguardia: [262],
                    ),
                ],
                jefes: [
                    1 => $this->plantilla(
                        nombre: 'Matt',
                        sprite: 'sailor',
                        descripcion: 'Jefe secundario de Team Aqua.',
                        vanguardia: [319, 262],
                        retaguardia: [364],
                    ),
                    2 => $this->plantilla(
                        nombre: 'Shelly',
                        sprite: 'swimmerf',
                        descripcion: 'Jefa secundaria de Team Aqua.',
                        vanguardia: [319, 364],
                        retaguardia: [365],
                    ),
                    3 => $this->plantilla(
                        nombre: 'Aquiles',
                        sprite: 'aquiles',
                        descripcion: 'Capitán de Team Aqua.',
                        vanguardia: [319, 365],
                        retaguardia: [321],
                    ),
                ],
            ),
        );
    }

    private function galaxia(): FaccionRival
    {
        return new FaccionRival(
            slug: 'galaxia',
            nombre: 'Team Galaxia',
            descripcion: 'Reclutas de Team Galaxia con Pokémon psíquicos y venenosos.',
            color: '#00838F',
            imagen: 'Equipo_Galaxia_Logo',
            orden: 4,
            medallasDesbloqueo: 5,
            oleadas: $this->oleadas(
                reclutas: [
                    $this->plantilla(
                        nombre: 'Recluta Galaxia',
                        sprite: 'scientist',
                        descripcion: 'Recluta con Pokémon psíquicos y venenosos.',
                        vanguardia: [434, 41],
                        retaguardia: [453],
                    ),
                    $this->plantilla(
                        nombre: 'Recluta Galaxia veterano',
                        sprite: 'psychic',
                        descripcion: 'Veterano con Toxicroak, Golbat y Bronzor.',
                        vanguardia: [454, 42],
                        retaguardia: [436],
                    ),
                    $this->plantilla(
                        nombre: 'Recluta Galaxia de élite',
                        sprite: 'scientist',
                        descripcion: 'Élite con Skuntank, Crobat y Bronzong.',
                        vanguardia: [435, 169],
                        retaguardia: [437],
                    ),
                ],
                jefes: [
                    1 => $this->plantilla(
                        nombre: 'Mars',
                        sprite: 'psychicf',
                        descripcion: 'Jefa secundaria de Team Galaxia.',
                        vanguardia: [454, 42],
                        retaguardia: [436],
                    ),
                    2 => $this->plantilla(
                        nombre: 'Saturn',
                        sprite: 'psychic',
                        descripcion: 'Jefe secundario de Team Galaxia.',
                        vanguardia: [435, 169],
                        retaguardia: [437],
                    ),
                    3 => $this->plantilla(
                        nombre: 'Ciro',
                        sprite: 'ciro',
                        descripcion: 'Capitán de Team Galaxia.',
                        vanguardia: [461, 430],
                        retaguardia: [437],
                    ),
                ],
            ),
        );
    }

    private function skull(): FaccionRival
    {
        return new FaccionRival(
            slug: 'skull',
            nombre: 'Team Skull',
            descripcion: 'Reclutas de Team Skull con Pokémon venenosos y de tipo bicho.',
            color: '#546E7A',
            imagen: 'Team_Skull',
            orden: 5,
            medallasDesbloqueo: 6,
            oleadas: $this->oleadas(
                reclutas: [
                    $this->plantilla(
                        nombre: 'Recluta Skull novato',
                        sprite: 'punkgirl',
                        descripcion: 'Recluta novato con Pokémon de callejón.',
                        vanguardia: [19, 96],
                        retaguardia: [23],
                    ),
                    $this->plantilla(
                        nombre: 'Recluta Skull',
                        sprite: 'beauty',
                        descripcion: 'Recluta con Salandit, Golbat y Mareanie.',
                        vanguardia: [757, 42],
                        retaguardia: [747],
                    ),
                    $this->plantilla(
                        nombre: 'Recluta Skull de élite',
                        sprite: 'roughneck',
                        descripcion: 'Élite con Salazzle, Toxapex y Ariados.',
                        vanguardia: [758, 748],
                        retaguardia: [168],
                    ),
                ],
                jefes: [
                    1 => $this->plantilla(
                        nombre: 'Recluta Skull veterano',
                        sprite: 'roughneck',
                        descripcion: 'Jefe secundario de Team Skull.',
                        vanguardia: [757, 42],
                        retaguardia: [747],
                    ),
                    2 => $this->plantilla(
                        nombre: 'Plumeria',
                        sprite: 'beauty',
                        descripcion: 'Jefa secundaria de Team Skull.',
                        vanguardia: [758, 42],
                        retaguardia: [748],
                    ),
                    3 => $this->plantilla(
                        nombre: 'Guzmán',
                        sprite: 'guzman',
                        descripcion: 'Capitán de Team Skull.',
                        vanguardia: [768, 168],
                        retaguardia: [738],
                    ),
                ],
            ),
        );
    }

    /**
     * @param  list<PlantillaCombate>  $reclutas
     * @param  array<int, PlantillaCombate>  $jefes
     */
    private function oleadas(array $reclutas, array $jefes): OleadaRivalCollection
    {
        $oleadas = new OleadaRivalCollection();

        for ($numero = 1; $numero <= ReglasHabitatTemporal::TOTAL_OLEADAS; $numero++) {
            if (! isset($jefes[$numero])) {
                throw new InvalidArgumentException("Falta el jefe de la oleada {$numero}.");
            }

            $combates = new CombateRivalCollection();

            foreach ($this->rotar($reclutas, $numero - 1) as $indice => $plantilla) {
                $combates->add($this->combate($indice + 1, $plantilla, false));
            }

            $combates->add($this->combate(4, $jefes[$numero], true));

            $oleadas->add(new OleadaRival(
                numero: $numero,
                nombre: 'Oleada '.$numero,
                combates: $combates,
            ));
        }

        return $oleadas;
    }

    /**
     * @param  list<int>  $vanguardia
     * @param  list<int>  $retaguardia
     */
    private function plantilla(
        string $nombre,
        string $sprite,
        string $descripcion,
        array $vanguardia,
        array $retaguardia,
        ?string $entrenador = null,
    ): PlantillaCombate {
        return new PlantillaCombate(
            nombre: $nombre,
            entrenador: $entrenador ?? $nombre,
            sprite: $sprite,
            descripcion: $descripcion,
            vanguardia: new IntCollection($vanguardia),
            retaguardia: new IntCollection($retaguardia),
        );
    }

    private function combate(int $numero, PlantillaCombate $plantilla, bool $esJefe): CombateRival
    {
        return new CombateRival(
            numero: $numero,
            nombre: $plantilla->nombre,
            entrenador: $plantilla->entrenador,
            descripcion: $plantilla->descripcion,
            sprite: $plantilla->sprite,
            esJefe: $esJefe,
            equipo: new EquipoEtapaGimnasio(
                vanguardia: $plantilla->vanguardia,
                retaguardia: $plantilla->retaguardia,
            ),
        );
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private function rotar(array $items, int $desplazamiento): array
    {
        $total = count($items);

        if ($total === 0) {
            return [];
        }

        $desplazamiento %= $total;

        return array_merge(
            array_slice($items, $desplazamiento),
            array_slice($items, 0, $desplazamiento),
        );
    }
}
