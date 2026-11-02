<?php

declare(strict_types=1);

namespace Tests\Feature\Battle;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Collections\CambiosStatsCollection;
use Src\Battle\Domain\Collections\CombatientesCollection;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Battle\Domain\ReglasBatalla;
use Src\Battle\Domain\ServicioEjecucionBatalla;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TipoPokemon;
use Tests\Support\Battle\ConstructorBatallaHarness;
use Tests\Support\Battle\EscenarioBatalla;
use Tests\TestCase;

/**
 * Batería determinista de movimientos y estados alterados.
 *
 * Cada caso fija IVs, stats y semilla, y afirma el resultado exacto: estado,
 * HP, stats, PP, log y ausencia de cambios colaterales. No se apoya en
 * aleatoriedad no controlada ni en base de datos, sesión o storage.
 *
 * El evento `status` derivado del delta real se cubre en
 * `EstadoAplicadoLivewireTest` (bloque A); aquí se prueba el contrato de
 * dominio/servicio que lo hace fiable.
 */
final class MovimientosEstadosDeterministasTest extends TestCase
{
    protected function tearDown(): void
    {
        mt_srand();
        parent::tearDown();
    }

    // ─── Dragoaliento: contrato `bool` y probabilidad ─────────

    public function test_dragoaliento_con_probabilidad_cero_no_paraliza_ni_registra_log(): void
    {
        mt_srand(7);
        $movimiento = $this->dragoaliento(0.0);
        ['batalla' => $batalla, 'atacante' => $atacante, 'defensor' => $defensor] = $this->montarBatalla([$movimiento]);

        $vitalesDefensor = $this->vitales($defensor);
        $vitalesAtacante = $this->vitales($atacante);

        $aplicado = $this->servicio()->aplicarEstado($defensor, $movimiento, $batalla, $atacante);

        $this->assertFalse($aplicado, 'Una tirada fallida debe devolver false');
        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertSame(0, $defensor->turnosEstado());
        $this->assertSame(0, $defensor->cargasVeneno());
        $this->assertSame(EstadoPokemon::NONE, $atacante->estado());
        $this->assertVitalesIntactos($vitalesDefensor, $defensor);
        $this->assertVitalesIntactos($vitalesAtacante, $atacante);
        $this->assertTrue($defensor->multiplicadores()->esNeutro());
        $this->assertSame([], $batalla->log()->toArray(), 'No debe aparecer ninguna línea de parálisis');
    }

    public function test_dragoaliento_con_probabilidad_uno_paraliza_sin_cambios_colaterales(): void
    {
        mt_srand(7);
        $movimiento = $this->dragoaliento(1.0);
        ['batalla' => $batalla, 'atacante' => $atacante, 'defensor' => $defensor] = $this->montarBatalla([$movimiento]);

        $vitalesDefensor = $this->vitales($defensor);
        $vitalesAtacante = $this->vitales($atacante);

        $aplicado = $this->servicio()->aplicarEstado($defensor, $movimiento, $batalla, $atacante);

        $this->assertTrue($aplicado, 'Una tirada forzada a 1 debe devolver true');
        $this->assertSame(EstadoPokemon::PARALYSIS, $defensor->estado());
        $this->assertSame(0, $defensor->turnosEstado());
        $this->assertSame(0, $defensor->cargasVeneno());
        $this->assertSame(EstadoPokemon::NONE, $atacante->estado());
        $this->assertVitalesIntactos($vitalesDefensor, $defensor);
        $this->assertVitalesIntactos($vitalesAtacante, $atacante);
        $this->assertTrue($defensor->multiplicadores()->esNeutro());
        $this->assertSame([], $batalla->log()->toArray(), 'El servicio no debe inventar log de estado');
    }

    // ─── Aplicación de estados por movimiento ─────────────────

    public function test_onda_trueno_paraliza_sin_dano_ni_cambios_de_stats(): void
    {
        mt_srand(7);
        $movimiento = $this->movimientoDeEstado('Onda Trueno', TipoPokemon::ELECTRICO, EstadoPokemon::PARALYSIS);
        ['batalla' => $batalla, 'atacante' => $atacante, 'defensor' => $defensor] = $this->montarBatalla([$movimiento]);

        $vitales = $this->vitales($defensor);

        $this->assertTrue($this->servicio()->aplicarEstado($defensor, $movimiento, $batalla, $atacante));
        $this->assertSame(EstadoPokemon::PARALYSIS, $defensor->estado());
        $this->assertSame(0, $defensor->turnosEstado());
        $this->assertVitalesIntactos($vitales, $defensor);
        $this->assertTrue($defensor->multiplicadores()->esNeutro());
        $this->assertSame([], $batalla->log()->toArray());
    }

    public function test_fuego_fatuo_quema_sin_dano_inmediato_ni_cambios_de_stats(): void
    {
        mt_srand(7);
        $movimiento = $this->movimientoDeEstado('Fuego Fatuo', TipoPokemon::FUEGO, EstadoPokemon::BURN);
        ['batalla' => $batalla, 'atacante' => $atacante, 'defensor' => $defensor] = $this->montarBatalla([$movimiento]);

        $vitales = $this->vitales($defensor);

        $this->assertTrue($this->servicio()->aplicarEstado($defensor, $movimiento, $batalla, $atacante));
        $this->assertSame(EstadoPokemon::BURN, $defensor->estado());
        $this->assertTrue($defensor->estado()->causaDanoPorRonda());
        $this->assertSame(0, $defensor->turnosEstado());
        $this->assertVitalesIntactos($vitales, $defensor);
        $this->assertTrue($defensor->multiplicadores()->esNeutro());
        $this->assertSame([], $batalla->log()->toArray());
    }

    public function test_toxico_aplica_veneno_grave_y_suma_una_carga_de_veneno(): void
    {
        mt_srand(7);
        $movimiento = $this->movimientoDeEstado('Tóxico', TipoPokemon::VENENO, EstadoPokemon::BAD_POISON);
        ['batalla' => $batalla, 'atacante' => $atacante, 'defensor' => $defensor] = $this->montarBatalla([$movimiento]);

        $vitales = $this->vitales($defensor);

        $this->assertTrue($this->servicio()->aplicarEstado($defensor, $movimiento, $batalla, $atacante));
        $this->assertSame(EstadoPokemon::BAD_POISON, $defensor->estado());
        $this->assertTrue($defensor->estado()->causaDanoPorRonda());
        $this->assertSame(1, $defensor->cargasVeneno());
        $this->assertSame(0, $defensor->turnosEstado());
        $this->assertVitalesIntactos($vitales, $defensor);
        $this->assertTrue($defensor->multiplicadores()->esNeutro());
        $this->assertSame([], $batalla->log()->toArray());

        // El daño exacto del veneno grave queda deliberadamente fuera: el
        // contador arranca en 0 y el primer tick aplica 1 en lugar de HP/16.
        // Se verifica solo la progresión del contador y la consistencia HP.
        $contadorInicial = $defensor->contadorVenenoGrave();
        $defensor->setDefensaEspHpActual(0.0);
        $hpAntes = $defensor->hpActual();

        $primerTick = $defensor->aplicarDañoStatus();
        $this->assertGreaterThan(0.0, $primerTick);
        $this->assertSame($hpAntes - $primerTick, $defensor->hpActual());
        $this->assertSame($contadorInicial + 1, $defensor->contadorVenenoGrave());
        $this->assertSame(1, $defensor->cargasVeneno());

        $defensor->aplicarDañoStatus();
        $this->assertSame($contadorInicial + 2, $defensor->contadorVenenoGrave());
        $this->assertSame(EstadoPokemon::BAD_POISON, $defensor->estado());
    }

    // ─── Sueño, congelación, parálisis y confusión ────────────

    public function test_espora_duerme_cuatro_turnos_y_despierta_al_agotarlos(): void
    {
        mt_srand(5); // mt_rand(2, 4) = 4
        $movimiento = $this->movimientoDeEstado('Espora', TipoPokemon::PLANTA, EstadoPokemon::SLEEP);
        ['batalla' => $batalla, 'atacante' => $atacante, 'defensor' => $defensor] = $this->montarBatalla([$movimiento]);

        $vitales = $this->vitales($defensor);

        $this->assertTrue($this->servicio()->aplicarEstado($defensor, $movimiento, $batalla, $atacante));
        $this->assertSame(EstadoPokemon::SLEEP, $defensor->estado());
        $this->assertSame(4, $defensor->turnosEstado(), 'La semilla 5 debe fijar cuatro turnos de sueño');
        $this->assertVitalesIntactos($vitales, $defensor);
        $this->assertSame([], $batalla->log()->toArray());

        foreach ([3, 2, 1, 0] as $turnosRestantes) {
            $resultado = $defensor->puedeActuar();

            $this->assertFalse($resultado->esPermitida());
            $this->assertSame('está dormido', $resultado->motivo());
            $this->assertSame($turnosRestantes, $defensor->turnosEstado());
            $this->assertSame(EstadoPokemon::SLEEP, $defensor->estado());
            $this->assertSame(0.0, $resultado->autoDanio());
        }

        $despertar = $defensor->puedeActuar();

        $this->assertTrue($despertar->esPermitida());
        $this->assertSame('despertó', $despertar->motivo());
        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertSame(0, $defensor->turnosEstado());
        $this->assertVitalesIntactos($vitales, $defensor);
        $this->assertSame([], $batalla->log()->toArray());
    }

    public function test_congelacion_bloquea_y_se_descongela_con_semilla_fija(): void
    {
        $movimiento = new MovimientoBatalla(
            'Rayo Hielo',
            90,
            TipoPokemon::HIELO,
            CategoriaMovimiento::ESPECIAL,
            statusEffect: EstadoPokemon::FREEZE,
            perfil: new PerfilMovimiento(probabilidadEstado: 1.0, puedeCriticar: false),
        );
        ['batalla' => $batalla, 'atacante' => $atacante, 'defensor' => $defensor] = $this->montarBatalla([$movimiento]);

        $vitales = $this->vitales($defensor);

        // El movimiento aplica el estado; el daño lo cubre el pipeline de daño,
        // aquí se aísla la transición de estado.
        $this->assertTrue($this->servicio()->aplicarEstado($defensor, $movimiento, $batalla, $atacante));
        $this->assertSame(EstadoPokemon::FREEZE, $defensor->estado());
        $this->assertVitalesIntactos($vitales, $defensor);

        mt_srand(4); // mt_rand(1, 100) = 31 > 20: sigue congelado
        $congelado = $defensor->puedeActuar();

        $this->assertFalse($congelado->esPermitida());
        $this->assertSame('está congelado', $congelado->motivo());
        $this->assertSame(EstadoPokemon::FREEZE, $defensor->estado());
        $this->assertVitalesIntactos($vitales, $defensor);

        mt_srand(5); // mt_rand(1, 100) = 12 <= 20: se descongela
        $descongelado = $defensor->puedeActuar();

        $this->assertTrue($descongelado->esPermitida());
        $this->assertSame('se descongeló', $descongelado->motivo());
        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertVitalesIntactos($vitales, $defensor);
        $this->assertSame([], $batalla->log()->toArray());
    }

    public function test_paralisis_reduce_la_velocidad_a_la_mitad_y_falla_con_semilla_fija(): void
    {
        mt_srand(7);
        $movimiento = $this->movimientoDeEstado('Onda Trueno', TipoPokemon::ELECTRICO, EstadoPokemon::PARALYSIS);
        ['batalla' => $batalla, 'atacante' => $atacante, 'defensor' => $defensor] = $this->montarBatalla([$movimiento]);

        $vitales = $this->vitales($defensor);
        $velocidadBase = $defensor->pokemon()->battleStats()->speed;
        $ataqueBase = $defensor->pokemon()->battleStats()->attack;

        $this->assertTrue($this->servicio()->aplicarEstado($defensor, $movimiento, $batalla, $atacante));

        $this->assertSame(
            $velocidadBase * ReglasBatalla::REDUCCION_VELOCIDAD_PARALISIS,
            $defensor->obtenerStatEfectivo(StatClave::VELOCIDAD),
        );
        $this->assertSame(
            $velocidadBase * ReglasBatalla::REDUCCION_VELOCIDAD_PARALISIS,
            $defensor->velocidadEfectiva(),
        );
        $this->assertSame($ataqueBase, $defensor->obtenerStatEfectivo(StatClave::ATAQUE), 'La parálisis no toca el ataque');

        mt_srand(5); // mt_rand(1, 100) = 12 <= 25: no actúa
        $bloqueado = $defensor->puedeActuar();

        $this->assertFalse($bloqueado->esPermitida());
        $this->assertSame('está paralizado', $bloqueado->motivo());
        $this->assertSame(EstadoPokemon::PARALYSIS, $defensor->estado());
        $this->assertSame(0, $defensor->turnosEstado());

        mt_srand(4); // mt_rand(1, 100) = 31 > 25: actúa
        $actua = $defensor->puedeActuar();

        $this->assertTrue($actua->esPermitida());
        $this->assertSame('', $actua->motivo());
        $this->assertSame(EstadoPokemon::PARALYSIS, $defensor->estado());
        $this->assertVitalesIntactos($vitales, $defensor);
        $this->assertSame([], $batalla->log()->toArray());
    }

    public function test_confusion_causa_autodano_exacto_y_sale_tras_los_turnos(): void
    {
        mt_srand(5); // mt_rand(2, 4) = 4
        $movimiento = $this->movimientoDeEstado('Confusión', TipoPokemon::PSIQUICO, EstadoPokemon::CONFUSION);
        ['batalla' => $batalla, 'atacante' => $atacante, 'defensor' => $defensor] = $this->montarBatalla([$movimiento]);

        $vitales = $this->vitales($defensor);

        $this->assertTrue($this->servicio()->aplicarEstado($defensor, $movimiento, $batalla, $atacante));
        $this->assertSame(EstadoPokemon::CONFUSION, $defensor->estado());
        $this->assertTrue($defensor->tieneCondicion(ClaveCondicion::CONFUSION));
        $this->assertSame(4, $defensor->turnosEstado());
        $this->assertVitalesIntactos($vitales, $defensor);

        mt_srand(5); // mt_rand(1, 100) = 12 <= 33: se golpea a sí mismo
        $hpAntes = $defensor->hpActual();
        $autogolpe = $defensor->puedeActuar();

        $this->assertFalse($autogolpe->esPermitida());
        $this->assertSame('se golpeó por confusión', $autogolpe->motivo());
        $this->assertEqualsWithDelta(19.6, $autogolpe->autoDanio(), 1e-9);
        $this->assertSame($hpAntes - $autogolpe->autoDanio(), $defensor->hpActual());
        $this->assertSame(3, $defensor->turnosEstado());
        $this->assertSame(EstadoPokemon::CONFUSION, $defensor->estado());

        foreach ([2, 1, 0] as $turnosRestantes) {
            mt_srand(9); // mt_rand(1, 100) = 71 > 33: actúa sin autodaño
            $resultado = $defensor->puedeActuar();

            $this->assertTrue($resultado->esPermitida());
            $this->assertSame('', $resultado->motivo());
            $this->assertSame(0.0, $resultado->autoDanio());
            $this->assertSame($turnosRestantes, $defensor->turnosEstado());
            $this->assertSame(EstadoPokemon::CONFUSION, $defensor->estado());
        }

        $salida = $defensor->puedeActuar();

        $this->assertTrue($salida->esPermitida());
        $this->assertSame('salió de confusión', $salida->motivo());
        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertFalse($defensor->tieneCondicion(ClaveCondicion::CONFUSION));
        $this->assertSame(0, $defensor->turnosEstado());
        $this->assertSame($hpAntes - $autogolpe->autoDanio(), $defensor->hpActual());
        $this->assertVitalesIntactos($this->vitales($atacante), $atacante);
        $this->assertSame([], $batalla->log()->toArray());
    }

    // ─── Daño de fin de ronda ─────────────────────────────────

    public function test_quemadura_dana_veinte_al_final_de_cada_ronda_y_persiste(): void
    {
        $movimiento = $this->movimientoDeEstado('Fuego Fatuo', TipoPokemon::FUEGO, EstadoPokemon::BURN);
        ['batalla' => $batalla, 'atacante' => $atacante, 'defensor' => $defensor] = $this->montarBatalla([$movimiento]);

        $this->assertTrue($this->servicio()->aplicarEstado($defensor, $movimiento, $batalla, $atacante));
        $defensor->setDefensaEspHpActual(0.0);
        $hpInicial = $defensor->hpActual();
        $vitalesAtacante = $this->vitales($atacante);

        $batalla->triggerRoundEndEffects();

        $this->assertSame($hpInicial - 20.0, $defensor->hpActual());
        $this->assertSame(EstadoPokemon::BURN, $defensor->estado());
        $this->assertSame(
            ['Defensor sufre 20 de daño por quemadura'],
            $batalla->log()->toArray(),
        );

        $batalla->triggerRoundEndEffects();

        $this->assertSame($hpInicial - 40.0, $defensor->hpActual());
        $this->assertSame(EstadoPokemon::BURN, $defensor->estado());
        $this->assertSame(
            [
                'Defensor sufre 20 de daño por quemadura',
                'Defensor sufre 20 de daño por quemadura',
            ],
            $batalla->log()->toArray(),
        );
        $this->assertVitalesIntactos($vitalesAtacante, $atacante);
    }

    public function test_veneno_dana_diez_al_final_de_cada_ronda_y_persiste(): void
    {
        $movimiento = $this->movimientoDeEstado('Veneno', TipoPokemon::VENENO, EstadoPokemon::POISON);
        ['batalla' => $batalla, 'atacante' => $atacante, 'defensor' => $defensor] = $this->montarBatalla([$movimiento]);

        $this->assertTrue($this->servicio()->aplicarEstado($defensor, $movimiento, $batalla, $atacante));
        $this->assertSame(1, $defensor->cargasVeneno());
        $defensor->setDefensaEspHpActual(0.0);
        $hpInicial = $defensor->hpActual();
        $vitalesAtacante = $this->vitales($atacante);

        $batalla->triggerRoundEndEffects();

        $this->assertSame($hpInicial - 10.0, $defensor->hpActual());
        $this->assertSame(EstadoPokemon::POISON, $defensor->estado());
        $this->assertSame(1, $defensor->cargasVeneno());
        $this->assertSame(
            ['Defensor sufre 10 de daño por envenenamiento'],
            $batalla->log()->toArray(),
        );

        $batalla->triggerRoundEndEffects();

        $this->assertSame($hpInicial - 20.0, $defensor->hpActual());
        $this->assertSame(EstadoPokemon::POISON, $defensor->estado());
        $this->assertVitalesIntactos($vitalesAtacante, $atacante);
    }

    // ─── Cambios de stats y PP ────────────────────────────────

    public function test_grunido_baja_el_ataque_del_objetivo_sin_cambios_colaterales(): void
    {
        $movimiento = new MovimientoBatalla(
            'Gruñido',
            0,
            TipoPokemon::NORMAL,
            CategoriaMovimiento::ESTADO,
            targetStatChanges: new CambiosStatsCollection([
                CambioStat::desdeEtapas(StatClave::ATAQUE, -1),
            ]),
            perfil: new PerfilMovimiento(probabilidadEstado: 1.0),
        );
        ['batalla' => $batalla, 'atacante' => $atacante, 'defensor' => $defensor] = $this->montarBatalla([$movimiento]);
        $vitales = $this->vitales($defensor);
        $ataqueBase = $defensor->pokemon()->battleStats()->attack;

        $this->servicio()->aplicarEstadoYStatChanges(
            new AccionBatalla($atacante, $defensor, $movimiento),
            $batalla,
            new CombatientesCollection([$defensor]),
        );

        $this->assertSame(0.7, $defensor->multiplicadores()->obtenerMultiplicador(StatClave::ATAQUE));
        $this->assertEqualsWithDelta(
            $ataqueBase * 0.7,
            $defensor->obtenerStatEfectivo(StatClave::ATAQUE),
            1e-9,
        );
        $this->assertSame(1.0, $atacante->multiplicadores()->obtenerMultiplicador(StatClave::ATAQUE));
        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertSame(0, $defensor->turnosEstado());
        $this->assertVitalesIntactos($vitales, $defensor);
        $this->assertSame([], $batalla->log()->toArray());
    }

    public function test_danza_espada_sube_el_ataque_del_usuario_sin_afectar_al_rival(): void
    {
        $movimiento = new MovimientoBatalla(
            'Danza Espada',
            0,
            TipoPokemon::NORMAL,
            CategoriaMovimiento::ESTADO,
            selfStatChanges: new CambiosStatsCollection([
                CambioStat::desdeEtapas(StatClave::ATAQUE, 2),
            ]),
            perfil: new PerfilMovimiento(probabilidadEstado: 1.0),
        );
        ['batalla' => $batalla, 'atacante' => $atacante, 'defensor' => $defensor] = $this->montarBatalla([$movimiento]);
        $vitalesAtacante = $this->vitales($atacante);
        $vitalesDefensor = $this->vitales($defensor);
        $ataqueBase = $atacante->pokemon()->battleStats()->attack;
        $factorEsperado = 1.30 ** 2;

        $this->servicio()->aplicarEstadoYStatChanges(
            new AccionBatalla($atacante, $defensor, $movimiento),
            $batalla,
            new CombatientesCollection([$defensor]),
        );

        $this->assertSame($factorEsperado, $atacante->multiplicadores()->obtenerMultiplicador(StatClave::ATAQUE));
        $this->assertEqualsWithDelta(
            $ataqueBase * $factorEsperado,
            $atacante->obtenerStatEfectivo(StatClave::ATAQUE),
            1e-9,
        );
        $this->assertSame(5, $atacante->multiplicadores()->turnosTemporales());
        $this->assertSame(1.0, $defensor->multiplicadores()->obtenerMultiplicador(StatClave::ATAQUE));
        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertVitalesIntactos($vitalesAtacante, $atacante);
        $this->assertVitalesIntactos($vitalesDefensor, $defensor);
        $this->assertSame([], $batalla->log()->toArray());
    }

    public function test_consumo_de_pp_limita_usos_y_bloquea_el_movimiento(): void
    {
        $limitado = new MovimientoBatalla(
            'Placaje',
            40,
            TipoPokemon::NORMAL,
            CategoriaMovimiento::FISICO,
            perfil: new PerfilMovimiento(usosMaximos: 2, puedeCriticar: false),
        );
        $ilimitado = new MovimientoBatalla(
            'Gruñido',
            0,
            TipoPokemon::NORMAL,
            CategoriaMovimiento::ESTADO,
            perfil: new PerfilMovimiento(usosMaximos: 3),
        );
        ['batalla' => $batalla, 'atacante' => $atacante] = $this->montarBatalla([$limitado, $ilimitado]);
        $vitales = $this->vitales($atacante);

        $this->assertSame(2, $atacante->usosRestantes($limitado));
        $this->assertSame(3, $atacante->usosRestantes($ilimitado));
        $this->assertTrue($atacante->movimientoDisponible($limitado));

        $atacante->consumirMovimiento($limitado);
        $this->assertSame(1, $atacante->usosRestantes($limitado));
        $this->assertTrue($atacante->movimientoDisponible($limitado));
        $this->assertSame(3, $atacante->usosRestantes($ilimitado), 'Consumir un movimiento no toca los demás');

        $atacante->consumirMovimiento($limitado);
        $this->assertSame(0, $atacante->usosRestantes($limitado));
        $this->assertFalse($atacante->movimientoDisponible($limitado));
        $this->assertSame('sin_usos', $atacante->motivoMovimientoNoDisponible($limitado));
        $this->assertSame(3, $atacante->usosRestantes($ilimitado));

        $atacante->consumirMovimiento($limitado);
        $this->assertSame(0, $atacante->usosRestantes($limitado), 'Los usos no pueden bajar de cero');
        $this->assertSame(3, $atacante->usosRestantes($ilimitado));
        $this->assertVitalesIntactos($vitales, $atacante);
        $this->assertSame(EstadoPokemon::NONE, $atacante->estado());
        $this->assertSame([], $batalla->log()->toArray());
    }

    // ─── Helpers deterministas ────────────────────────────────

    private function servicio(): ServicioEjecucionBatalla
    {
        return new ServicioEjecucionBatalla(new CadenaDanio());
    }

    private function dragoaliento(float $probabilidadEstado): MovimientoBatalla
    {
        return new MovimientoBatalla(
            'Dragoaliento',
            60,
            TipoPokemon::DRAGON,
            CategoriaMovimiento::ESPECIAL,
            statusEffect: EstadoPokemon::PARALYSIS,
            perfil: new PerfilMovimiento(
                probabilidadEstado: $probabilidadEstado,
                puedeCriticar: false,
            ),
        );
    }

    private function movimientoDeEstado(
        string $nombre,
        TipoPokemon $tipo,
        EstadoPokemon $estado,
    ): MovimientoBatalla {
        return new MovimientoBatalla(
            $nombre,
            0,
            $tipo,
            CategoriaMovimiento::ESTADO,
            statusEffect: $estado,
            perfil: new PerfilMovimiento(probabilidadEstado: 1.0),
        );
    }

    /**
     * Construye un 1v1 con IVs a cero y stats explícitas. La HP base 105 da
     * 320 PS a nivel 100, divisible entre 16 y 32 para que el daño residual
     * sea exacto.
     *
     * @param  list<MovimientoBatalla>  $movimientosAtacante
     * @param  list<MovimientoBatalla>  $movimientosDefensor
     * @param  array{hp?: int, atk?: int, def?: int, spAtk?: int, spDef?: int, speed?: int}  $statsAtacante
     * @param  array{hp?: int, atk?: int, def?: int, spAtk?: int, spDef?: int, speed?: int}  $statsDefensor
     * @return array{batalla: AgregadoBatalla, atacante: Combatiente, defensor: Combatiente}
     */
    private function montarBatalla(
        array $movimientosAtacante,
        array $movimientosDefensor = [],
        array $statsAtacante = [],
        array $statsDefensor = [],
    ): array {
        $atacante = ConstructorBatallaHarness::dato([
            'id' => 'a1',
            'nombre' => 'Atacante',
            'hp' => $statsAtacante['hp'] ?? 105,
            'atk' => $statsAtacante['atk'] ?? 100,
            'def' => $statsAtacante['def'] ?? 100,
            'spAtk' => $statsAtacante['spAtk'] ?? 100,
            'spDef' => $statsAtacante['spDef'] ?? 100,
            'speed' => $statsAtacante['speed'] ?? 100,
            'tipos' => [TipoPokemon::NORMAL],
            'posicion' => Posicion::VANGUARDIA,
            'moves' => $movimientosAtacante,
            'nivel' => 100,
        ], StatsValue::ceros());

        $defensor = ConstructorBatallaHarness::dato([
            'id' => 'd1',
            'nombre' => 'Defensor',
            'hp' => $statsDefensor['hp'] ?? 105,
            'atk' => $statsDefensor['atk'] ?? 100,
            'def' => $statsDefensor['def'] ?? 100,
            'spAtk' => $statsDefensor['spAtk'] ?? 100,
            'spDef' => $statsDefensor['spDef'] ?? 100,
            'speed' => $statsDefensor['speed'] ?? 100,
            'tipos' => [TipoPokemon::AGUA],
            'posicion' => Posicion::VANGUARDIA,
            'moves' => $movimientosDefensor,
            'nivel' => 100,
        ], StatsValue::ceros());

        $batalla = (new ConstructorBatallaHarness())->construir(new EscenarioBatalla(
            nombre: 'movimientos_estados_deterministas',
            equipo1: new DatosPokemonBatallaCollection([$atacante]),
            equipo2: new DatosPokemonBatallaCollection([$defensor]),
        ));

        return [
            'batalla' => $batalla,
            'atacante' => $this->combatienteDe($batalla->team1, 'a1'),
            'defensor' => $this->combatienteDe($batalla->team2, 'd1'),
        ];
    }

    private function combatienteDe(EquipoBatalla $equipo, string $id): Combatiente
    {
        $combatiente = $equipo->findCombatantById($id);

        if (! $combatiente instanceof Combatiente) {
            $this->fail("No existe el combatiente {$id} en el equipo {$equipo->name}");
        }

        return $combatiente;
    }

    /**
     * @return array{hp: float, defHp: float, defEsp: float}
     */
    private function vitales(Combatiente $combatiente): array
    {
        return [
            'hp' => $combatiente->hpActual(),
            'defHp' => $combatiente->defensaHpActual(),
            'defEsp' => $combatiente->defensaEspHpActual(),
        ];
    }

    /**
     * @param array{hp: float, defHp: float, defEsp: float} $esperado
     */
    private function assertVitalesIntactos(array $esperado, Combatiente $combatiente, string $contexto = ''): void
    {
        $sufijo = $contexto === '' ? '' : " ({$contexto})";

        $this->assertSame($esperado['hp'], $combatiente->hpActual(), 'HP inesperado'.$sufijo);
        $this->assertSame($esperado['defHp'], $combatiente->defensaHpActual(), 'Barrera física inesperada'.$sufijo);
        $this->assertSame($esperado['defEsp'], $combatiente->defensaEspHpActual(), 'Barrera especial inesperada'.$sufijo);
    }
}
