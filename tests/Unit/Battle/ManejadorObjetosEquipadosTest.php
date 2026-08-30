<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Chain\ManejadorObjetosEquipados;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Manejador genérico de objetos equipados: aplica el multiplicador de daño
 * del objeto del atacante (mapa objeto → multiplicador).
 */
class ManejadorObjetosEquipadosTest extends TestCase
{
    use ConstruyeCombatientes;

    public function test_life_orb_multiplica_por_1_3(): void
    {
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
            item: 'life_orb',
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Defensor',
        );

        $accion = $this->accion($atacante, $defensor);

        $manejador = new ManejadorObjetosEquipados();

        $this->assertSame(130.0, $manejador->handle($accion, 100.0));
    }

    public function test_sin_objeto_no_cambia_dano(): void
    {
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Defensor',
        );

        $accion = $this->accion($atacante, $defensor);

        $manejador = new ManejadorObjetosEquipados();

        $this->assertSame(100.0, $manejador->handle($accion, 100.0));
    }

    public function test_objeto_desconocido_no_cambia_dano(): void
    {
        // leftovers no modifica daño: solo cura en onRoundEnd
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
            item: 'leftovers',
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Defensor',
        );

        $accion = $this->accion($atacante, $defensor);

        $manejador = new ManejadorObjetosEquipados();

        $this->assertSame(100.0, $manejador->handle($accion, 100.0));
    }

    public function test_atacante_muerto_con_life_orb_no_multiplica(): void
    {
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
            item: 'life_orb',
        );
        $atacante->setHpActual(0);

        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Defensor',
        );

        $accion = $this->accion($atacante, $defensor);

        $manejador = new ManejadorObjetosEquipados();

        $this->assertSame(100.0, $manejador->handle($accion, 100.0));
    }

    public function test_mapa_custom_aplica_multiplicador_personalizado(): void
    {
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
            item: 'choice_band',
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Defensor',
        );

        $accion = $this->accion($atacante, $defensor);

        $manejador = new ManejadorObjetosEquipados(['choice_band' => 1.50]);

        $this->assertSame(150.0, $manejador->handle($accion, 100.0));
    }

    private function accion(\Src\Battle\Domain\Combatiente $atacante, \Src\Battle\Domain\Combatiente $defensor): AccionBatalla
    {
        return new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
            fromPosition: Posicion::VANGUARDIA,
            defenderTeamHasVanguard: false,
            weather: TipoClima::NONE,
        );
    }
}
