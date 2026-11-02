<?php

declare(strict_types=1);

namespace Tests\Support\Battle;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\AI\NivelDificultad;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Effects\FabricaEfectos;
use Src\Battle\Domain\EquipoBatalla;
use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * Marca Interna: solo los datos creados por `ConstructorBatallaHarness::dato()`
 * acreditan que las IVs se pasaron de forma explícita.
 *
 * `DatosPokemonBatalla` sustituye un `ivs: null` por `GeneradorIvs::aleatorios()`
 * (que usa `random_int()` no sembrable). Una vez construido el objeto no hay
 * forma de distinguir ese caso de unas IVs explícitas, así que el arnés exige
 * el tipo marcado que solo produce su propia fábrica.
 */
interface DatoConIvsExplicitas
{
}

/**
 * Construye el agregado de batalla con la fábrica de efectos real del
 * contenedor. No dispara efectos de inicio ni siembra el generador: eso es
 * responsabilidad de `EjecutorBatallaHarness`.
 */
final class ConstructorBatallaHarness
{
    /**
     * Crea un `DatosPokemonBatalla` con IVs explícitas obligatorias.
     *
     * @param  array<string, mixed>  $atributos  Argumentos con nombre de `DatosPokemonBatalla`.
     *
     * @throws \InvalidArgumentException si no se declaran las IVs.
     */
    public static function dato(array $atributos, ?StatsValue $ivs = null): DatosPokemonBatalla
    {
        if ($ivs === null) {
            throw new \InvalidArgumentException(
                'Los datos de un escenario del arnés requieren IVs explícitas (StatsValue::ceros() o GeneradorIvs::maximos())',
            );
        }

        unset($atributos['ivs']);

        return new class (...$atributos, ivs: $ivs) extends DatosPokemonBatalla implements DatoConIvsExplicitas {
        };
    }

    /**
     * Construye un agregado listo para sembrar y ejecutar.
     *
     * @throws \InvalidArgumentException si algún dato no acredita IVs explícitas.
     */
    public function construir(EscenarioBatalla $escenario): AgregadoBatalla
    {
        $this->exigirIvsExplicitas($escenario->equipo1, 'equipo1');
        $this->exigirIvsExplicitas($escenario->equipo2, 'equipo2');

        $fabrica = app(FabricaEfectos::class);

        $equipo1 = EquipoBatalla::fromData($escenario->equipo1, 'A', $fabrica);
        $equipo2 = EquipoBatalla::fromData($escenario->equipo2, 'B', $fabrica);

        $batalla = new AgregadoBatalla($equipo1, $equipo2);
        $batalla->setDificultad(NivelDificultad::PERFECTA);

        return $batalla;
    }

    private function exigirIvsExplicitas(DatosPokemonBatallaCollection $equipo, string $nombreEquipo): void
    {
        foreach ($equipo as $indice => $dato) {
            if (! $dato instanceof DatoConIvsExplicitas) {
                throw new \InvalidArgumentException(sprintf(
                    'El combatiente %d de %s no declara IVs explícitas; usa ConstructorBatallaHarness::dato()',
                    $indice,
                    $nombreEquipo,
                ));
            }
        }
    }
}
