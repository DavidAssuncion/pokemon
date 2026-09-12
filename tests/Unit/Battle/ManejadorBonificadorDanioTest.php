<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Chain\ManejadorBonificadorDanio;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Pokemon\Domain\PokemonEntity;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TipoPokemon;
use Src\Shared\Tipos\TiposCollection;

class ManejadorBonificadorDanioTest extends TestCase
{
    public function test_bonificador_maestro_multiplica_el_dano(): void
    {
        $atacante = $this->combatiente();
        $atacante->setBonificadorDanio(1.15);
        $accion = $this->accion($atacante, $this->combatiente('d1', 'Defensor'));

        $manejador = new ManejadorBonificadorDanio();

        $this->assertEqualsWithDelta(115.0, $manejador->handle($accion, 100.0), 0.000001);
    }

    public function test_bonificador_por_defecto_no_cambia_el_dano(): void
    {
        $accion = $this->accion($this->combatiente(), $this->combatiente('d1', 'Defensor'));

        $manejador = new ManejadorBonificadorDanio();

        $this->assertSame(100.0, $manejador->handle($accion, 100.0));
    }

    public function test_atacante_muerto_con_bonificador_no_multiplica(): void
    {
        $atacante = $this->combatiente();
        $atacante->setBonificadorDanio(1.15);
        $atacante->setHpActual(0);
        $accion = $this->accion($atacante, $this->combatiente('d1', 'Defensor'));

        $manejador = new ManejadorBonificadorDanio();

        $this->assertSame(100.0, $manejador->handle($accion, 100.0));
    }

    public function test_bonificador_se_serializa_y_restaura(): void
    {
        $atacante = $this->combatiente();
        $atacante->setBonificadorDanio(1.05);
        $serializado = serialize($atacante);

        $restaurado = unserialize($serializado);

        $this->assertSame(1.05, $restaurado->bonificadorDanio());
    }

    public function test_serializacion_antigua_restaura_bonificador_por_defecto(): void
    {
        $atacante = $this->combatiente();
        $data = $atacante->__serialize();
        unset($data['bonificadorDanio']); // v4 (antes de bonificador)

        $restaurado = new \Src\Battle\Domain\Combatiente(
            new PokemonEntity(
                stats: new StatsValue(60, 100, 100, 100, 100, 100),
                evs: new StatsValue(0, 0, 0, 0, 0, 0),
                moves: [],
                tiposCollection: new TiposCollection([]),
            ),
            Posicion::VANGUARDIA,
        );
        $restaurado->__unserialize($data);

        $this->assertSame(1.0, $restaurado->bonificadorDanio());
    }

    private function combatiente(string $id = 'a1', string $nombre = 'Atacante'): \Src\Battle\Domain\Combatiente
    {
        $pokemon = new PokemonEntity(
            stats: new StatsValue(
                hp: 100,
                attack: 100,
                defense: 100,
                spAtk: 100,
                spDef: 100,
                speed: 100,
            ),
            evs: new StatsValue(0, 0, 0, 0, 0, 0),
            moves: [],
            tiposCollection: new TiposCollection([TipoPokemon::NORMAL]),
        );

        $combatiente = new \Src\Battle\Domain\Combatiente($pokemon, Posicion::VANGUARDIA);
        $combatiente->setId($id);
        $combatiente->setNombre($nombre);

        return $combatiente;
    }

    private function accion(\Src\Battle\Domain\Combatiente $atacante, \Src\Battle\Domain\Combatiente $defensor): AccionBatalla
    {
        return new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
            defenderTeamHasVanguard: false,
            weather: TipoClima::NONE,
        );
    }
}
