<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
use Src\Shared\Domain\Sendas\MapaSendaJugador;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Factores de Senda en la cadena de daño: N5 aporta ×1.2 al daño infligido
 * del tipo del movimiento y ×0.8 al daño recibido de ese mismo tipo.
 */
class CadenaDanioSendaTest extends TestCase
{
    use ConstruyeCombatientes;

    private CadenaDanio $chain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chain = new CadenaDanio();
    }

    public function test_sin_senda_no_cambia_el_dano(): void
    {
        // Base 44: atk=def=100 a nivel 100, potencia 50, sin STAB ni efectividad.
        $this->assertSame(44.0, $this->calcular());
    }

    public function test_senda_ofensiva_n5_multiplica_el_dano_por_1_2(): void
    {
        $daño = $this->calcular(sendaAtacante: $this->sendaN5(TipoPokemon::NORMAL));

        $this->assertSame(52.0, $daño); // floor(44 * 1.2) = 52
    }

    public function test_senda_defensiva_n5_reduce_el_dano_por_0_8(): void
    {
        $daño = $this->calcular(sendaDefensora: $this->sendaN5(TipoPokemon::NORMAL));

        $this->assertSame(35.0, $daño); // floor(44 * 0.8) = 35
    }

    public function test_sendas_ofensiva_y_defensiva_se_combinan(): void
    {
        $daño = $this->calcular(
            sendaAtacante: $this->sendaN5(TipoPokemon::NORMAL),
            sendaDefensora: $this->sendaN5(TipoPokemon::NORMAL),
        );

        $this->assertSame(42.0, $daño); // floor(44 * 1.2 * 0.8) = 42
    }

    public function test_vector_exacto_con_stab_y_sendas_n5(): void
    {
        // BattleStats convierte atk 141 / def 120 en 287 / 245: la base queda
        // en 100,4 (formulaBase); NORMAL→AGUA = 1.0. STAB ×1.5; Senda N5 ×1.2/×0.8.
        $move = new MovimientoBatalla('Golpe', 100, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO);

        $this->assertSame(150.0, $this->calcular(
            move: $move,
            tiposAtacante: [TipoPokemon::NORMAL],
            atkAtacante: 141,
            defDefensor: 120,
        ));

        $this->assertSame(180.0, $this->calcular(
            sendaAtacante: $this->sendaN5(TipoPokemon::NORMAL),
            move: $move,
            tiposAtacante: [TipoPokemon::NORMAL],
            atkAtacante: 141,
            defDefensor: 120,
        ));

        $this->assertSame(144.0, $this->calcular(
            sendaAtacante: $this->sendaN5(TipoPokemon::NORMAL),
            sendaDefensora: $this->sendaN5(TipoPokemon::NORMAL),
            move: $move,
            tiposAtacante: [TipoPokemon::NORMAL],
            atkAtacante: 141,
            defDefensor: 120,
        ));
    }

    public function test_senda_de_otro_tipo_no_afecta_al_dano(): void
    {
        $daño = $this->calcular(
            sendaAtacante: $this->sendaN5(TipoPokemon::FUEGO),
            sendaDefensora: $this->sendaN5(TipoPokemon::FUEGO),
        );

        $this->assertSame(44.0, $daño);
    }

    public function test_dano_fijo_no_recibe_senda(): void
    {
        // Potencia 1: la cadena clasifica como estado cualquier movimiento
        // con potencia <= 0; el daño real lo fija el perfil.
        $move = new MovimientoBatalla(
            'Sacrificio',
            1,
            TipoPokemon::NORMAL,
            CategoriaMovimiento::FISICO,
            perfil: new PerfilMovimiento(formulaDano: 'fijo_40'),
        );

        $daño = $this->calcular(
            sendaAtacante: $this->sendaN5(TipoPokemon::NORMAL),
            sendaDefensora: $this->sendaN5(TipoPokemon::NORMAL),
            move: $move,
        );

        $this->assertSame(40.0, $daño);
    }

    /**
     * @param  TipoPokemon[]  $tiposAtacante
     */
    private function calcular(
        ?MapaSendaJugador $sendaAtacante = null,
        ?MapaSendaJugador $sendaDefensora = null,
        ?MovimientoBatalla $move = null,
        array $tiposAtacante = [TipoPokemon::FUEGO],
        int $atkAtacante = 100,
        int $defDefensor = 100,
    ): float {
        $move ??= new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO);

        // Por defecto, atacante FUEGO con movimiento NORMAL: sin STAB. Defensor
        // AGUA: NORMAL→AGUA = 1.0. Así el daño base depende solo de la fórmula.
        $atacante = $this->combatiente(
            stats: ['atk' => $atkAtacante, 'def' => 100],
            moves: [$move],
            tipos: $tiposAtacante,
            id: 'a1',
            nombre: 'Atacante',
        );
        if ($sendaAtacante !== null) {
            $atacante->setSenda($sendaAtacante);
        }

        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => $defDefensor],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Defensor',
        );
        if ($sendaDefensora !== null) {
            $defensor->setSenda($sendaDefensora);
        }

        $accion = new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: $move,
            defenderTeamHasVanguard: false,
            weather: TipoClima::NONE,
        );

        mt_srand(1); // sin crítico

        return $this->chain->calculate($accion);
    }

    private function sendaN5(TipoPokemon $tipo): MapaSendaJugador
    {
        return MapaSendaJugador::desdeArray([$tipo->value => 5]);
    }
}
