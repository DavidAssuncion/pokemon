<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Cadena de daño: fórmula base, STAB y smoke test de calculate() > 0.
 */
class CadenaDanioTest extends TestCase
{
    use ConstruyeCombatientes;

    private CadenaDanio $chain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chain = new CadenaDanio();
    }

    public function test_calcula_dano_mayor_que_cero(): void
    {
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            moves: [new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
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

        $accion = new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
            fromPosition: Posicion::VANGUARDIA,
            defenderTeamHasVanguard: false,
            weather: TipoClima::NONE,
        );

        mt_srand(1); // sin crítico
        $daño = $this->chain->calculate($accion);

        $this->assertGreaterThan(0, $daño);
    }

    public function test_dano_base_sigue_formula(): void
    {
        // Base esperada: ((2*50/5+2) * 50 * atk / max(def,1)) / 50 + 2
        // atk=100, def=100 → tras BattleStats lv100 ambos 205 → ratio 1
        // = 22*50*1/50 + 2 = 24
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            moves: [new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA], // NORMAL→AGUA = 1.0
            id: 'd1',
            nombre: 'Defensor',
        );

        $accion = new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
            fromPosition: Posicion::VANGUARDIA,
            defenderTeamHasVanguard: false,
            weather: TipoClima::NONE,
        );

        mt_srand(1); // sin crítico
        $daño = $this->chain->calculate($accion);

        $this->assertSame(24.0, $daño);
    }

    public function test_stab_multiplica_por_1_5(): void
    {
        // Atacante 1: tipo NORMAL + move NORMAL → STAB ×1.5
        // Atacante 2: tipo FUEGO + move NORMAL → sin STAB
        // Ambos con mismo stats, defensor AGUA (efectividad 1.0)
        $move = new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO);

        $atacanteStab = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            moves: [$move],
            tipos: [TipoPokemon::NORMAL],
            id: 'a1',
            nombre: 'Stab',
        );
        $atacanteNoStab = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            moves: [$move],
            tipos: [TipoPokemon::FUEGO],
            id: 'a2',
            nombre: 'NoStab',
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Defensor',
        );

        $accionStab = new AccionBatalla(
            attacker: $atacanteStab,
            defender: $defensor,
            move: $move,
            fromPosition: Posicion::VANGUARDIA,
            defenderTeamHasVanguard: false,
            weather: TipoClima::NONE,
        );
        $accionNoStab = new AccionBatalla(
            attacker: $atacanteNoStab,
            defender: $defensor,
            move: $move,
            fromPosition: Posicion::VANGUARDIA,
            defenderTeamHasVanguard: false,
            weather: TipoClima::NONE,
        );

        mt_srand(1); // sin crítico
        $dañoStab = $this->chain->calculate($accionStab);
        $dañoNoStab = $this->chain->calculate($accionNoStab);

        $this->assertSame($dañoStab, $dañoNoStab * 1.5);
        $this->assertSame(36.0, $dañoStab);  // 24 * 1.5
        $this->assertSame(24.0, $dañoNoStab);
    }

    public function test_clamp_minimo_1_cuando_dano_seria_menor_que_1(): void
    {
        // Atacante muy débil (atk=1 → stat 7) vs defensor muy resistente (def=200 → stat 405)
        // + efectividad 0.5 (NORMAL vs ROCA) + posición -50% (retaguardia) → daño < 1
        $atacante = $this->combatiente(
            stats: ['atk' => 1, 'def' => 100],
            moves: [new MovimientoBatalla('Golpecito', 1, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [TipoPokemon::VENENO], // sin STAB con NORMAL
            id: 'a1',
            nombre: 'Débil',
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 200],
            tipos: [TipoPokemon::ROCA],
            id: 'd1',
            nombre: 'Resistente',
            posicion: Posicion::RETAGUARDIA,
        );

        $accion = new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: new MovimientoBatalla('Golpecito', 1, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
            fromPosition: Posicion::VANGUARDIA,
            defenderTeamHasVanguard: true,
            weather: TipoClima::NONE,
        );

        mt_srand(1); // sin crítico
        $daño = $this->chain->calculate($accion);

        $this->assertSame(1.0, $daño); // max(1, floor(<1)) = 1
    }
}
