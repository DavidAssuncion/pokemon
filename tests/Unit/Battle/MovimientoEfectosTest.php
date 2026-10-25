<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Collections\CambiosStatsCollection;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoPantalla;
use Src\Battle\Domain\Effects\Movimiento\ColeccionEjecutoresMovimiento;
use Src\Battle\Domain\Effects\Movimiento\EjecutorCuracion;
use Src\Battle\Domain\Effects\Movimiento\EjecutorEquipoProteccion;
use Src\Battle\Domain\Effects\Movimiento\EjecutorPantalla;
use Src\Battle\Domain\Effects\Movimiento\EjecutorTerreno;
use Src\Battle\Domain\Effects\Movimiento\EjecutorTrampa;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\ObjetivoMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\Enums\TipoTerreno;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ServicioEjecucionBatalla;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Verifica que los ejecutores de efectos de movimiento aplican el estado,
 * las condiciones, los cambios de stats y los efectos de campo esperados.
 */
class MovimientoEfectosTest extends TestCase
{
    use ConstruyeCombatientes;

    private ColeccionEjecutoresMovimiento $ejecutores;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ejecutores = ColeccionEjecutoresMovimiento::conEjecutoresPorDefecto();
    }

    // ─── Registro ────────────────────────────────────────────

    public function test_registro_contiene_las_claves_principales(): void
    {
        $claves = [
            ClaveEfectoMovimiento::DRENADORAS,
            ClaveEfectoMovimiento::MALDICION,
            ClaveEfectoMovimiento::CANTO_MORTAL,
            ClaveEfectoMovimiento::DESAFIO,
            ClaveEfectoMovimiento::TENAZA,
            ClaveEfectoMovimiento::TRICK_ROOM,
            ClaveEfectoMovimiento::TAILWIND,
            ClaveEfectoMovimiento::SUSTITUTO,
            ClaveEfectoMovimiento::PROTECCION,
            ClaveEfectoMovimiento::AGUANTE,
            ClaveEfectoMovimiento::CLIMA_SEQUIA,
            ClaveEfectoMovimiento::TERRENO_ELECTRICO,
            ClaveEfectoMovimiento::PUAS,
            ClaveEfectoMovimiento::PANTALLA_LUZ,
            ClaveEfectoMovimiento::CURACION_EQUIPO,
            ClaveEfectoMovimiento::TAMBOR,
            ClaveEfectoMovimiento::INVERTIR_STATS,
        ];

        foreach ($claves as $clave) {
            $this->assertTrue($this->ejecutores->tiene($clave), "Falta el ejecutor de {$clave->value}");
        }
    }

    public function test_clave_sin_ejecutor_no_lanza_excepcion(): void
    {
        $this->assertFalse($this->ejecutores->tiene(ClaveEfectoMovimiento::PICO_TALADRO));

        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $this->ejecutarSinEjecutor(
            ClaveEfectoMovimiento::PICO_TALADRO,
            $this->movimiento('Pico Taladro', 80, TipoPokemon::ACERO, CategoriaMovimiento::FISICO),
            $atacante,
            $defensor,
        );

        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
    }

    // ─── Condiciones sobre el objetivo ───────────────────────

    public function test_drenadoras_aplica_condicion_sin_acumular(): void
    {
        $atacante = $this->combatiente(id: 'a1', nombre: 'Atacante');
        $defensor = $this->combatiente(id: 'd1', nombre: 'Defensor');
        $movimiento = $this->movimiento('Drenadoras', 0, TipoPokemon::PLANTA);

        $this->ejecutar(ClaveEfectoMovimiento::DRENADORAS, $movimiento, $atacante, $defensor);
        $this->ejecutar(ClaveEfectoMovimiento::DRENADORAS, $movimiento, $atacante, $defensor);

        $condicion = $defensor->condicion(ClaveCondicion::DRENADORAS);

        $this->assertNotNull($condicion);
        $this->assertSame(0.125, $condicion->fraccionDano);
        $this->assertTrue($condicion->vaDirectoAHp);
        $this->assertSame('a1', $condicion->origenId);
    }

    public function test_maldicion_paga_mitad_de_hp_y_maldice_al_objetivo(): void
    {
        $atacante = $this->combatiente(stats: ['hp' => 80], id: 'a1', nombre: 'Atacante');
        $defensor = $this->combatiente(id: 'd1', nombre: 'Defensor');
        $hpMax = $this->hpMax($atacante);

        $this->ejecutar(
            ClaveEfectoMovimiento::MALDICION,
            $this->movimiento('Maldición', 0, TipoPokemon::FANTASMA),
            $atacante,
            $defensor,
        );

        $this->assertSame($hpMax - max(1.0, $hpMax * 0.5), $atacante->hpActual());
        $condicion = $defensor->condicion(ClaveCondicion::MALDICION);
        $this->assertNotNull($condicion);
        $this->assertSame(0.0625, $condicion->fraccionDano);
        $this->assertTrue($condicion->vaDirectoAHp);
    }

    public function test_canto_mortal_aplica_tres_turnos(): void
    {
        $defensor = $this->combatiente(id: 'd1');

        $this->ejecutar(
            ClaveEfectoMovimiento::CANTO_MORTAL,
            $this->movimiento('Canto Mortal', 0, TipoPokemon::NORMAL),
            $this->combatiente(id: 'a1'),
            $defensor,
        );

        $this->assertSame(3, $defensor->condicion(ClaveCondicion::CANTO_MORTAL)?->turnos);
    }

    public function test_desafio_fuerza_al_atacante_como_objetivo(): void
    {
        $defensor = $this->combatiente(id: 'd1');

        $this->ejecutar(
            ClaveEfectoMovimiento::DESAFIO,
            $this->movimiento('Desafío', 0, TipoPokemon::SINIESTRO),
            $this->combatiente(id: 'a1'),
            $defensor,
        );

        $condicion = $defensor->condicion(ClaveCondicion::DESAFIO);
        $this->assertNotNull($condicion);
        $this->assertSame(3, $condicion->turnos);
        $this->assertSame('a1', $condicion->objetivoForzadoId);
    }

    public function test_tenaza_atrapa_entre_dos_y_cinco_turnos(): void
    {
        $defensor = $this->combatiente(id: 'd1');

        $this->ejecutar(
            ClaveEfectoMovimiento::TENAZA,
            $this->movimiento('Tenaza', 35, TipoPokemon::AGUA, CategoriaMovimiento::FISICO),
            $this->combatiente(id: 'a1'),
            $defensor,
        );

        $condicion = $defensor->condicion(ClaveCondicion::ATRAPADO);
        $this->assertNotNull($condicion);
        $this->assertGreaterThanOrEqual(2, $condicion->turnos);
        $this->assertLessThanOrEqual(5, $condicion->turnos);
        $this->assertSame(0.125, $condicion->fraccionDano);
        $this->assertTrue($condicion->esFisico);
    }

    public function test_marca_aplica_multiplicador_de_dano(): void
    {
        $defensor = $this->combatiente(id: 'd1');
        $movimiento = $this->movimiento(
            'Marca',
            0,
            TipoPokemon::SINIESTRO,
            perfil: new PerfilMovimiento(turnosMarcado: 2, multiplicadorMarcado: 1.5),
        );

        $this->ejecutar(ClaveEfectoMovimiento::MARCA, $movimiento, $this->combatiente(id: 'a1'), $defensor);

        $condicion = $defensor->condicion(ClaveCondicion::MARCADO);
        $this->assertNotNull($condicion);
        $this->assertSame(2, $condicion->turnos);
        $this->assertSame(1.5, $condicion->multiplicadorDanoRecibido);
    }

    public function test_mofa_aplica_bloqueo_de_estado(): void
    {
        $defensor = $this->combatiente(id: 'd1');

        $this->ejecutar(
            ClaveEfectoMovimiento::MOFA,
            $this->movimiento('Mofa', 0, TipoPokemon::SINIESTRO),
            $this->combatiente(id: 'a1'),
            $defensor,
        );

        $this->assertSame(3, $defensor->condicion(ClaveCondicion::MOFA)?->turnos);
    }

    public function test_canto_encantador_confunde_solo_con_boosts(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $movimiento = $this->movimiento('Canto Encantador', 0, TipoPokemon::HADA);

        $this->ejecutar(ClaveEfectoMovimiento::CANTO_ENCANTADOR, $movimiento, $atacante, $defensor);
        $this->assertNull($defensor->condicion(ClaveCondicion::CONFUSION));

        $defensor->setMultiplicadores(
            CambioStat::desdeEtapas(StatClave::ATAQUE, 1)->aplicadoTemporalEn($defensor->multiplicadores()),
        );
        $this->ejecutar(ClaveEfectoMovimiento::CANTO_ENCANTADOR, $movimiento, $atacante, $defensor);

        $this->assertNotNull($defensor->condicion(ClaveCondicion::CONFUSION));
    }

    // ─── Autocondiciones y protección ────────────────────────

    public function test_aguante_aplica_condicion_de_aguante(): void
    {
        $atacante = $this->combatiente(id: 'a1');

        $this->ejecutar(
            ClaveEfectoMovimiento::AGUANTE,
            $this->movimiento('Aguante', 0, TipoPokemon::NORMAL),
            $atacante,
            $this->combatiente(id: 'd1'),
        );

        $this->assertSame(1, $atacante->condicion(ClaveCondicion::AGUANTE)?->turnos);
    }

    public function test_proteccion_aplica_condicion(): void
    {
        $atacante = $this->combatiente(id: 'a1');

        $this->ejecutar(
            ClaveEfectoMovimiento::PROTECCION,
            $this->movimiento('Protección', 0, TipoPokemon::NORMAL),
            $atacante,
            $this->combatiente(id: 'd1'),
        );

        $this->assertSame(1, $atacante->condicion(ClaveCondicion::PROTEGIDO)?->turnos);
    }

    public function test_proteccion_reduce_el_dano_al_cuarenta_por_ciento(): void
    {
        $atacante = $this->combatiente(stats: ['atk' => 100], tipos: [TipoPokemon::LUCHA], id: 'a1');
        $defensor = $this->combatiente(stats: ['hp' => 200, 'def' => 100], tipos: [TipoPokemon::NORMAL], id: 'd1');
        $movimiento = $this->movimiento('Golpe', 80, TipoPokemon::LUCHA, CategoriaMovimiento::FISICO);
        $accion = new AccionBatalla($atacante, $defensor, $movimiento);
        $cadena = new CadenaDanio();

        mt_srand(42);
        $sinProteccion = $cadena->calculate($accion);

        $defensor->reemplazarCondicion(new CondicionVolatil(ClaveCondicion::PROTEGIDO, 1));
        mt_srand(42);
        $conProteccion = $cadena->calculate($accion);

        $this->assertGreaterThan(0.0, $sinProteccion);
        $this->assertLessThan($sinProteccion, $conProteccion);
        $this->assertEqualsWithDelta(0.4, $conProteccion / $sinProteccion, 0.05);
    }

    public function test_aguante_deja_el_dano_letal_a_un_hp(): void
    {
        $atacante = $this->combatiente(stats: ['atk' => 200], tipos: [TipoPokemon::LUCHA], id: 'a1');
        $defensor = $this->combatiente(stats: ['hp' => 10, 'def' => 100], tipos: [TipoPokemon::NORMAL], id: 'd1');
        $defensor->reemplazarCondicion(new CondicionVolatil(ClaveCondicion::AGUANTE, 1));
        $accion = new AccionBatalla(
            $atacante,
            $defensor,
            $this->movimiento('Golpe', 200, TipoPokemon::LUCHA, CategoriaMovimiento::FISICO),
        );

        $dano = (new CadenaDanio())->calculate($accion);
        $hp = $defensor->hpActual();

        $this->assertLessThanOrEqual($hp - 1, $dano);
        $this->assertFalse($defensor->tieneCondicion(ClaveCondicion::AGUANTE));
    }

    public function test_sustituto_crea_clon_con_hp(): void
    {
        $atacante = $this->combatiente(stats: ['hp' => 80], id: 'a1');
        $movimiento = $this->movimiento(
            'Sustituto',
            0,
            TipoPokemon::NORMAL,
            perfil: new PerfilMovimiento(fraccionHpSustituto: 0.25, turnosSustituto: 0),
            objetivo: ObjetivoMovimiento::SELF,
        );

        $this->ejecutar(ClaveEfectoMovimiento::SUSTITUTO, $movimiento, $atacante, $this->combatiente(id: 'd1'));

        $hpMax = $this->hpMax($atacante);
        $condicion = $atacante->condicion(ClaveCondicion::SUSTITUTO);
        $this->assertNotNull($condicion);
        $this->assertSame($hpMax * 0.25, $condicion->hpSustituto);
        $this->assertSame($hpMax - $hpMax * 0.25, $atacante->hpActual());
    }

    public function test_cambio_tipo_aplica_el_tipo_del_perfil(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $movimiento = $this->movimiento(
            'Camuflaje',
            0,
            TipoPokemon::NORMAL,
            perfil: new PerfilMovimiento(tipoOverride: TipoPokemon::AGUA, turnosTipo: 5),
            objetivo: ObjetivoMovimiento::SELF,
        );

        $this->ejecutar(ClaveEfectoMovimiento::CAMBIO_TIPO, $movimiento, $atacante, $this->combatiente(id: 'd1'));

        $condicion = $atacante->condicion(ClaveCondicion::TIPO_ALTERADO);
        $this->assertSame(TipoPokemon::AGUA, $condicion?->tipoOverride);
    }

    // ─── Cambios de estadísticas ─────────────────────────────

    public function test_tambor_paga_hp_y_maximiza_ataque(): void
    {
        $atacante = $this->combatiente(stats: ['hp' => 80], id: 'a1');
        $hpMax = $this->hpMax($atacante);

        $this->ejecutar(
            ClaveEfectoMovimiento::TAMBOR,
            $this->movimiento('Tambor', 0, TipoPokemon::NORMAL),
            $atacante,
            $this->combatiente(id: 'd1'),
        );

        $this->assertSame($hpMax - $hpMax * 0.5, $atacante->hpActual());
        $this->assertGreaterThan(1.0, $atacante->multiplicadores()->obtenerMultiplicador(StatClave::ATAQUE));
    }

    public function test_fillet_away_paga_hp_y_sube_ataque_especial_y_velocidad(): void
    {
        $atacante = $this->combatiente(stats: ['hp' => 80], id: 'a1');
        $hpMax = $this->hpMax($atacante);

        $this->ejecutar(
            ClaveEfectoMovimiento::FILLET_AWAY,
            $this->movimiento('Deslome', 0, TipoPokemon::NORMAL),
            $atacante,
            $this->combatiente(id: 'd1'),
        );

        $this->assertSame($hpMax - $hpMax * 0.5, $atacante->hpActual());
        foreach ([StatClave::ATAQUE, StatClave::ATAQUE_ESPECIAL, StatClave::VELOCIDAD] as $stat) {
            $this->assertGreaterThan(1.0, $atacante->multiplicadores()->obtenerMultiplicador($stat));
        }
    }

    public function test_atiborramiento_cura_y_sube_defensa(): void
    {
        $atacante = $this->combatiente(stats: ['hp' => 80], id: 'a1');
        $atacante->setHpActual(30.0);
        $hpMax = $this->hpMax($atacante);

        $this->ejecutar(
            ClaveEfectoMovimiento::ATIBORRAMIENTO,
            $this->movimiento('Atiborramiento', 0, TipoPokemon::NORMAL),
            $atacante,
            $this->combatiente(id: 'd1'),
        );

        $this->assertSame(min($hpMax, 30.0 + $hpMax * 0.5), $atacante->hpActual());
        $this->assertGreaterThan(1.0, $atacante->multiplicadores()->obtenerMultiplicador(StatClave::DEFENSA));
    }

    public function test_tomar_aliento_cura_estado_y_sube_todas_las_stats(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $hpMax = $this->hpMax($atacante);
        $atacante->setEstado(EstadoPokemon::BURN);
        $atacante->setTurnosEstado(3);

        $this->ejecutar(
            ClaveEfectoMovimiento::TOMAR_ALIENTO,
            $this->movimiento('Bálsamo Osado', 0, TipoPokemon::NORMAL),
            $atacante,
            $this->combatiente(id: 'd1'),
        );

        $this->assertSame(EstadoPokemon::NONE, $atacante->estado());
        $this->assertSame($hpMax, $atacante->hpActual());
        foreach ([StatClave::ATAQUE, StatClave::DEFENSA, StatClave::ATAQUE_ESPECIAL, StatClave::DEFENSA_ESPECIAL, StatClave::VELOCIDAD] as $stat) {
            $this->assertGreaterThan(1.0, $atacante->multiplicadores()->obtenerMultiplicador($stat));
        }
    }

    public function test_acupresion_sube_una_estadistica_dos_etapas(): void
    {
        $atacante = $this->combatiente(id: 'a1');

        $this->ejecutar(
            ClaveEfectoMovimiento::ACUPRESION,
            $this->movimiento('Acupresión', 0, TipoPokemon::NORMAL, objetivo: ObjetivoMovimiento::SELF),
            $atacante,
            $this->combatiente(id: 'd1'),
        );

        $stats = [StatClave::ATAQUE, StatClave::DEFENSA, StatClave::ATAQUE_ESPECIAL, StatClave::DEFENSA_ESPECIAL, StatClave::VELOCIDAD];
        $subidas = array_filter(
            $stats,
            fn (StatClave $stat): bool => $atacante->multiplicadores()->obtenerMultiplicador($stat) > 1.0,
        );

        $this->assertCount(1, $subidas);
    }

    public function test_invertir_stats_invierte_las_etapas_del_objetivo(): void
    {
        $defensor = $this->combatiente(id: 'd1');
        $defensor->setMultiplicadores(
            CambioStat::desdeEtapas(StatClave::ATAQUE, 2)->aplicadoTemporalEn($defensor->multiplicadores()),
        );

        $this->ejecutar(
            ClaveEfectoMovimiento::INVERTIR_STATS,
            $this->movimiento('Reversión', 0, TipoPokemon::NORMAL),
            $this->combatiente(id: 'a1'),
            $defensor,
        );

        $this->assertLessThan(1.0, $defensor->multiplicadores()->obtenerMultiplicador(StatClave::ATAQUE));
    }

    public function test_haze_limpia_las_stats_de_todos(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $cambio = CambioStat::desdeEtapas(StatClave::ATAQUE, 2);
        $atacante->setMultiplicadores($cambio->aplicadoTemporalEn($atacante->multiplicadores()));
        $defensor->setMultiplicadores($cambio->aplicadoTemporalEn($defensor->multiplicadores()));

        $this->ejecutar(
            ClaveEfectoMovimiento::NIEBLA,
            $this->movimiento('Niebla', 0, TipoPokemon::NORMAL),
            $atacante,
            $defensor,
        );

        $this->assertTrue($atacante->multiplicadores()->esNeutro());
        $this->assertTrue($defensor->multiplicadores()->esNeutro());
    }

    public function test_niebla_clara_limpia_solo_al_objetivo(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $cambio = CambioStat::desdeEtapas(StatClave::ATAQUE, 2);
        $atacante->setMultiplicadores($cambio->aplicadoTemporalEn($atacante->multiplicadores()));
        $defensor->setMultiplicadores($cambio->aplicadoTemporalEn($defensor->multiplicadores()));

        $this->ejecutar(
            ClaveEfectoMovimiento::NIEBLA_CLARA,
            $this->movimiento('Niebla Clara', 0, TipoPokemon::NORMAL),
            $atacante,
            $defensor,
        );

        $this->assertGreaterThan(1.0, $atacante->multiplicadores()->obtenerMultiplicador(StatClave::ATAQUE));
        $this->assertTrue($defensor->multiplicadores()->esNeutro());
    }

    public function test_psicocarga_copia_las_stats_del_objetivo(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $defensor->setMultiplicadores(
            CambioStat::desdeEtapas(StatClave::ATAQUE_ESPECIAL, 2)->aplicadoTemporalEn($defensor->multiplicadores()),
        );

        $this->ejecutar(
            ClaveEfectoMovimiento::PSICOCARGA,
            $this->movimiento('Psicocarga', 0, TipoPokemon::PSIQUICO),
            $atacante,
            $defensor,
        );

        $this->assertGreaterThan(1.0, $atacante->multiplicadores()->obtenerMultiplicador(StatClave::ATAQUE_ESPECIAL));
    }

    public function test_intercambio_poder_intercambia_el_ataque(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $atacante->setMultiplicadores(
            CambioStat::desdeEtapas(StatClave::ATAQUE, 2)->aplicadoTemporalEn($atacante->multiplicadores()),
        );
        $defensor->setMultiplicadores(
            CambioStat::desdeEtapas(StatClave::ATAQUE, -1)->aplicadoTemporalEn($defensor->multiplicadores()),
        );

        $this->ejecutar(
            ClaveEfectoMovimiento::INTERCAMBIO_PODER,
            $this->movimiento('Intercambio de Poder', 0, TipoPokemon::PSIQUICO),
            $atacante,
            $defensor,
        );

        $this->assertLessThan(1.0, $atacante->multiplicadores()->obtenerMultiplicador(StatClave::ATAQUE));
        $this->assertGreaterThan(1.0, $defensor->multiplicadores()->obtenerMultiplicador(StatClave::ATAQUE));
    }

    public function test_rototiller_sube_ataque_y_ataque_especial_a_planta(): void
    {
        $planta = $this->combatiente(tipos: [TipoPokemon::PLANTA], id: 'p1');
        $agua = $this->combatiente(tipos: [TipoPokemon::AGUA], id: 'a2');
        $battle = $this->batalla([$planta], [$agua]);

        $this->ejecutar(
            ClaveEfectoMovimiento::ROTOTILLER,
            $this->movimiento('Rototiller', 0, TipoPokemon::TIERRA),
            $planta,
            $agua,
            battle: $battle,
        );

        $this->assertGreaterThan(1.0, $planta->multiplicadores()->obtenerMultiplicador(StatClave::ATAQUE));
        $this->assertGreaterThan(1.0, $planta->multiplicadores()->obtenerMultiplicador(StatClave::ATAQUE_ESPECIAL));
        $this->assertTrue($agua->multiplicadores()->esNeutro());
    }

    public function test_drenaje_estadistica_cura_y_baja_el_ataque_del_objetivo(): void
    {
        $atacante = $this->combatiente(stats: ['hp' => 80], id: 'a1');
        $atacante->setHpActual(30.0);
        $defensor = $this->combatiente(stats: ['atk' => 100], id: 'd1');

        $this->ejecutar(
            ClaveEfectoMovimiento::DRENAJE_ESTADISTICA,
            $this->movimiento('Absorbefuerza', 0, TipoPokemon::PLANTA),
            $atacante,
            $defensor,
        );

        $this->assertGreaterThan(30.0, $atacante->hpActual());
        $this->assertLessThan(1.0, $defensor->multiplicadores()->obtenerMultiplicador(StatClave::ATAQUE));
    }

    public function test_aguijon_letal_sube_ataque_solo_si_el_objetivo_cae(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $movimiento = $this->movimiento('Aguijón Letal', 80, TipoPokemon::BICHO, CategoriaMovimiento::FISICO);

        $this->ejecutar(ClaveEfectoMovimiento::AGUIJON_LETAL, $movimiento, $atacante, $defensor);
        $this->assertTrue($atacante->multiplicadores()->esNeutro());

        $defensor->setHpActual(0.0);
        $this->ejecutar(ClaveEfectoMovimiento::AGUIJON_LETAL, $movimiento, $atacante, $defensor);
        $this->assertGreaterThan(1.0, $atacante->multiplicadores()->obtenerMultiplicador(StatClave::ATAQUE));
    }

    // ─── Daño y curación ─────────────────────────────────────

    public function test_superdiente_reduce_el_hp_del_objetivo_a_la_mitad(): void
    {
        $defensor = $this->combatiente(stats: ['hp' => 80], id: 'd1');

        $this->ejecutar(
            ClaveEfectoMovimiento::SUPERDIENTE,
            $this->movimiento('Superdiente', 0, TipoPokemon::NORMAL),
            $this->combatiente(id: 'a1'),
            $defensor,
        );

        $this->assertSame($this->hpMax($defensor) * 0.5, $defensor->hpActual());
    }

    public function test_represion_metal_devuelve_el_ciento_cincuenta_por_ciento(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $atacante->registrarDanoRecibido(20.0, 'x');
        $defensor = $this->combatiente(stats: ['hp' => 100], id: 'd1');

        $this->ejecutar(
            ClaveEfectoMovimiento::REPRESION_METAL,
            $this->movimiento('Represión Metal', 0, TipoPokemon::ACERO),
            $atacante,
            $defensor,
        );

        $this->assertSame($this->hpMax($defensor) - 30.0, $defensor->hpActual());
    }

    public function test_divide_dolor_iguala_el_hp_de_ambos(): void
    {
        $atacante = $this->combatiente(stats: ['hp' => 100], id: 'a1');
        $defensor = $this->combatiente(stats: ['hp' => 60], id: 'd1');
        $atacante->setHpActual(20.0);
        $defensor->setHpActual(60.0);

        $this->ejecutar(
            ClaveEfectoMovimiento::DIVIDE_DOLOR_MOVIMIENTO,
            $this->movimiento('Divide Dolor', 0, TipoPokemon::NORMAL),
            $atacante,
            $defensor,
        );

        $this->assertSame(40.0, $atacante->hpActual());
        $this->assertSame(40.0, $defensor->hpActual());
    }

    public function test_descanso_duerme_y_cura_todo_el_hp(): void
    {
        $atacante = $this->combatiente(stats: ['hp' => 80], id: 'a1');
        $atacante->setHpActual(10.0);
        $hpMax = $this->hpMax($atacante);

        $this->ejecutar(
            ClaveEfectoMovimiento::DESCANSO,
            $this->movimiento('Descanso', 0, TipoPokemon::PSIQUICO),
            $atacante,
            $this->combatiente(id: 'd1'),
        );

        $this->assertSame(EstadoPokemon::SLEEP, $atacante->estado());
        $this->assertSame(2, $atacante->turnosEstado());
        $this->assertSame($hpMax, $atacante->hpActual());
    }

    public function test_brecha_negra_duerme_y_cura_sin_escudo_especial(): void
    {
        $atacante = $this->combatiente(stats: ['hp' => 80], id: 'a1');
        $atacante->setHpActual(30.0);
        $defensor = $this->combatiente(id: 'd1');
        $defensor->setDefensaEspHpActual(0.0);

        $this->ejecutar(
            ClaveEfectoMovimiento::BRECHA_NEGRA,
            $this->movimiento('Brecha Negra', 70, TipoPokemon::SINIESTRO, CategoriaMovimiento::ESPECIAL),
            $atacante,
            $defensor,
            dano: 40.0,
        );

        $this->assertSame(EstadoPokemon::SLEEP, $defensor->estado());
        $this->assertSame(1, $defensor->turnosEstado());
        $this->assertGreaterThan(30.0, $atacante->hpActual());
    }

    public function test_brecha_negra_no_duerme_si_hay_escudo_especial(): void
    {
        $defensor = $this->combatiente(id: 'd1');
        $defensor->setDefensaEspHpActual(20.0);

        $this->ejecutar(
            ClaveEfectoMovimiento::BRECHA_NEGRA,
            $this->movimiento('Brecha Negra', 70, TipoPokemon::SINIESTRO, CategoriaMovimiento::ESPECIAL),
            $this->combatiente(id: 'a1'),
            $defensor,
            dano: 40.0,
        );

        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
    }

    public function test_aromaterapia_cura_los_estados_del_equipo(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $aliado = $this->combatiente(id: 'a2');
        $defensor = $this->combatiente(id: 'd1');
        $atacante->setEstado(EstadoPokemon::BURN);
        $aliado->setEstado(EstadoPokemon::POISON);
        $battle = $this->batalla([$atacante, $aliado], [$defensor]);

        $this->ejecutar(
            ClaveEfectoMovimiento::AROMATERAPIA,
            $this->movimiento('Aromaterapia', 0, TipoPokemon::PLANTA),
            $atacante,
            $defensor,
            battle: $battle,
        );

        $this->assertSame(EstadoPokemon::NONE, $atacante->estado());
        $this->assertSame(EstadoPokemon::NONE, $aliado->estado());
    }

    public function test_aria_burbuja_cura_quemadura_del_objetivo(): void
    {
        $defensor = $this->combatiente(id: 'd1');
        $defensor->setEstado(EstadoPokemon::BURN);
        $defensor->setTurnosEstado(2);

        $this->ejecutar(
            ClaveEfectoMovimiento::ARIA_BURBUJA,
            $this->movimiento('Aria Burbuja', 0, TipoPokemon::AGUA),
            $this->combatiente(id: 'a1'),
            $defensor,
        );

        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertSame(0, $defensor->turnosEstado());
    }

    public function test_refresh_cura_el_estado_del_usuario(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $atacante->setEstado(EstadoPokemon::PARALYSIS);
        $atacante->setTurnosEstado(3);

        $this->ejecutar(
            ClaveEfectoMovimiento::REFRESCO,
            $this->movimiento('Refresco', 0, TipoPokemon::NORMAL),
            $atacante,
            $this->combatiente(id: 'd1'),
        );

        $this->assertSame(EstadoPokemon::NONE, $atacante->estado());
        $this->assertSame(0, $atacante->turnosEstado());
    }

    public function test_purificar_cura_al_objetivo_y_al_usuario(): void
    {
        $atacante = $this->combatiente(stats: ['hp' => 80], id: 'a1');
        $atacante->setHpActual(30.0);
        $defensor = $this->combatiente(id: 'd1');
        $defensor->setEstado(EstadoPokemon::POISON);

        $this->ejecutar(
            ClaveEfectoMovimiento::PURIFICAR,
            $this->movimiento('Purificación', 0, TipoPokemon::NORMAL),
            $atacante,
            $defensor,
        );

        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertGreaterThan(30.0, $atacante->hpActual());
    }

    public function test_revivir_devuelve_al_primer_aliado_debilitado(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $caido = $this->combatiente(stats: ['hp' => 80], id: 'a2');
        $caido->setHpActual(0.0);
        $defensor = $this->combatiente(id: 'd1');
        $battle = $this->batalla([$atacante, $caido], [$defensor]);

        $this->ejecutar(
            ClaveEfectoMovimiento::REVIVIR,
            $this->movimiento(
                'Bendición Revivir',
                0,
                TipoPokemon::NORMAL,
                perfil: new PerfilMovimiento(fraccionRevivir: 0.5),
            ),
            $atacante,
            $defensor,
            battle: $battle,
        );

        $this->assertTrue($caido->estaVivo());
        $this->assertSame($this->hpMax($caido) * 0.5, $caido->hpActual());
    }

    public function test_marioneta_inerte_revive_y_marca_al_aliado(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $caido = $this->combatiente(stats: ['hp' => 80], id: 'a2');
        $caido->setHpActual(0.0);
        $defensor = $this->combatiente(id: 'd1');
        $battle = $this->batalla([$atacante, $caido], [$defensor]);

        $this->ejecutar(
            ClaveEfectoMovimiento::MARIONETA_INERTE,
            $this->movimiento('Marioneta Inerte', 0, TipoPokemon::NORMAL),
            $atacante,
            $defensor,
            battle: $battle,
        );

        $this->assertTrue($caido->estaVivo());
        $this->assertSame($this->hpMax($caido) * 0.35, $caido->hpActual());
        $this->assertSame(3, $caido->condicion(ClaveCondicion::MARIONETA_INERTE)?->turnos);
    }

    public function test_invocacion_agrega_un_combatiente_temporal(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $battle = $this->batalla([$atacante], [$defensor]);

        $this->ejecutar(
            ClaveEfectoMovimiento::INVOCAR,
            $this->movimiento(
                'Invocación',
                0,
                TipoPokemon::NORMAL,
                perfil: new PerfilMovimiento(turnosInvocacion: 3, maxInvocaciones: 1),
            ),
            $atacante,
            $defensor,
            battle: $battle,
        );

        $this->assertCount(2, $battle->team1->combatientesCollection()->all());
        $invocacion = $battle->team1->combatientesCollection()->all()[1];
        $this->assertTrue($invocacion->esInvocacion());
        $this->assertSame('a1', $invocacion->invocadoPorId());
    }

    // ─── Efectos de campo y equipo ──────────────────────────

    public function test_trick_room_activa_cinco_turnos(): void
    {
        $battle = $this->batallaMinima($this->combatiente(id: 'a1'), $this->combatiente(id: 'd1'));

        $this->ejecutar(
            ClaveEfectoMovimiento::TRICK_ROOM,
            $this->movimiento('Trick Room', 0, TipoPokemon::PSIQUICO),
            $battle->team1->combatientesCollection()->all()[0],
            $battle->team2->combatientesCollection()->all()[0],
            battle: $battle,
        );

        $this->assertTrue($battle->trickRoomActivo());
        $this->assertSame(5, $battle->trickRoomTurnos());
    }

    public function test_tailwind_agrega_efecto_al_equipo(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $battle = $this->batallaMinima($atacante, $this->combatiente(id: 'd1'));

        $this->ejecutar(
            ClaveEfectoMovimiento::TAILWIND,
            $this->movimiento('Tailwind', 0, TipoPokemon::VOLADOR),
            $atacante,
            $battle->team2->combatientesCollection()->all()[0],
            battle: $battle,
        );

        $this->assertTrue($this->tieneEfectoEquipo($battle->team1, 'tailwind'));
    }

    public function test_clima_cambia_el_clima_de_la_batalla(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $battle = $this->batallaMinima($atacante, $this->combatiente(id: 'd1'));

        $this->ejecutar(
            ClaveEfectoMovimiento::CLIMA_SEQUIA,
            $this->movimiento('Día Soleado', 0, TipoPokemon::FUEGO),
            $atacante,
            $battle->team2->combatientesCollection()->all()[0],
            battle: $battle,
        );

        $this->assertSame(TipoClima::SEQUIA, $battle->weather());
    }

    public function test_terreno_cambia_el_terreno_de_la_batalla(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $battle = $this->batallaMinima($atacante, $this->combatiente(id: 'd1'));
        $ejecutor = new EjecutorTerreno(ClaveEfectoMovimiento::TERRENO_ELECTRICO, TipoTerreno::ELECTRICO);

        $this->ejecutarCon(
            $ejecutor,
            $this->movimiento('Campo Eléctrico', 0, TipoPokemon::ELECTRICO),
            $atacante,
            $battle->team2->combatientesCollection()->all()[0],
            $battle,
        );

        $this->assertSame(TipoTerreno::ELECTRICO, $battle->terreno());
    }

    public function test_trampa_agrega_efecto_al_lado_rival(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $battle = $this->batallaMinima($atacante, $defensor);
        $ejecutor = new EjecutorTrampa(ClaveEfectoMovimiento::PUAS);

        $this->ejecutarCon($ejecutor, $this->movimiento('Púas', 0, TipoPokemon::ACERO), $atacante, $defensor, $battle);
        $this->ejecutarCon($ejecutor, $this->movimiento('Púas', 0, TipoPokemon::ACERO), $atacante, $defensor, $battle);

        $this->assertTrue($this->tieneEfectoEquipo($battle->team2, 'puas'));
        $this->assertCount(1, array_filter(
            $battle->team2->efectos()->all(),
            fn ($efecto): bool => $efecto->obtenerClave() === 'puas',
        ));
    }

    public function test_pantalla_agrega_efecto_de_equipo(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $battle = $this->batallaMinima($atacante, $this->combatiente(id: 'd1'));
        $ejecutor = new EjecutorPantalla(ClaveEfectoMovimiento::PANTALLA_LUZ);

        $this->ejecutarCon(
            $ejecutor,
            $this->movimiento('Pantalla de Luz', 0, TipoPokemon::PSIQUICO),
            $atacante,
            $battle->team2->combatientesCollection()->all()[0],
            $battle,
        );

        $this->assertTrue($this->tieneEfectoEquipo($battle->team1, 'pantalla_luz'));
    }

    public function test_aurora_veil_no_se_duplica(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $battle = $this->batallaMinima($atacante, $this->combatiente(id: 'd1'));

        $this->ejecutar(
            ClaveEfectoMovimiento::PANTALLA_AURORA,
            $this->movimiento('Velo Aurora', 0, TipoPokemon::HIELO),
            $atacante,
            $battle->team2->combatientesCollection()->all()[0],
            battle: $battle,
        );
        $this->ejecutar(
            ClaveEfectoMovimiento::PANTALLA_AURORA,
            $this->movimiento('Velo Aurora', 0, TipoPokemon::HIELO),
            $atacante,
            $battle->team2->combatientesCollection()->all()[0],
            battle: $battle,
        );

        $this->assertCount(1, array_filter(
            $battle->team1->efectos()->all(),
            fn ($efecto): bool => $efecto->obtenerClave() === 'pantalla_aurora',
        ));
    }

    public function test_mud_sport_reduce_dano_electrico_en_ambos_equipos(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $battle = $this->batallaMinima($atacante, $this->combatiente(id: 'd1'));

        $this->ejecutar(
            ClaveEfectoMovimiento::MUD_SPORT,
            $this->movimiento('Chapoteo Lodo', 0, TipoPokemon::TIERRA),
            $atacante,
            $battle->team2->combatientesCollection()->all()[0],
            battle: $battle,
        );

        $this->assertTrue($this->tieneEfectoEquipo($battle->team1, 'mud_sport'));
        $this->assertTrue($this->tieneEfectoEquipo($battle->team2, 'mud_sport'));
    }

    public function test_equipo_proteccion_agrega_safeguard(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $battle = $this->batallaMinima($atacante, $this->combatiente(id: 'd1'));
        $ejecutor = new EjecutorEquipoProteccion(ClaveEfectoMovimiento::SAFEGUARD);
        $movimiento = $this->movimiento(
            'Velo Sagrado',
            0,
            TipoPokemon::NORMAL,
            perfil: new PerfilMovimiento(claveEfecto: ClaveEfectoMovimiento::SAFEGUARD),
        );

        $this->ejecutarCon($ejecutor, $movimiento, $atacante, $battle->team2->combatientesCollection()->all()[0], $battle);

        $this->assertTrue($this->tieneEfectoEquipo($battle->team1, 'safeguard'));
    }

    public function test_furia_taurina_limpia_pantallas_rivales(): void
    {
        $atacante = $this->combatiente(tipos: [TipoPokemon::FUEGO], id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $battle = $this->batallaMinima($atacante, $defensor);
        $battle->team2->agregarEfecto(new EfectoEquipoPantalla('pantalla_luz', true, 5, 100.0));

        $this->ejecutar(
            ClaveEfectoMovimiento::FURIA_TAURINA,
            $this->movimiento('Furia Taurina', 0, TipoPokemon::FUEGO),
            $atacante,
            $defensor,
            battle: $battle,
        );

        $this->assertFalse($this->tieneEfectoEquipo($battle->team2, 'pantalla_luz'));
    }

    public function test_allanador_ferreo_elimina_el_terreno(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $battle = $this->batallaMinima($atacante, $this->combatiente(id: 'd1'));
        $battle->setTerreno(TipoTerreno::ELECTRICO, 5);

        $this->ejecutar(
            ClaveEfectoMovimiento::ALLANADOR_FERREO,
            $this->movimiento('Allanador Férreo', 0, TipoPokemon::ACERO),
            $atacante,
            $battle->team2->combatientesCollection()->all()[0],
            battle: $battle,
        );

        $this->assertSame(TipoTerreno::NONE, $battle->terreno());
    }

    public function test_dano_diferido_se_resuelve_en_la_ronda_indicada(): void
    {
        $atacante = $this->combatiente(stats: ['spAtk' => 100], tipos: [TipoPokemon::PSIQUICO], id: 'a1');
        $defensor = $this->combatiente(stats: ['hp' => 100, 'spDef' => 50], tipos: [TipoPokemon::NORMAL], id: 'd1');
        $battle = $this->batallaMinima($atacante, $defensor);
        $hpInicial = $defensor->hpActual();
        $defensor->setDefensaEspHpActual(0.0);

        $this->ejecutar(
            ClaveEfectoMovimiento::DANO_DIFERIDO,
            $this->movimiento(
                'Premonición',
                80,
                TipoPokemon::PSIQUICO,
                CategoriaMovimiento::ESPECIAL,
                perfil: new PerfilMovimiento(turnosDanoDiferido: 1),
            ),
            $atacante,
            $defensor,
            battle: $battle,
        );

        $this->assertSame($hpInicial, $defensor->hpActual());

        $battle->turnManager()->startNewRound();
        $battle->procesarDanioDiferido();

        $this->assertLessThan($hpInicial, $defensor->hpActual());
    }

    public function test_curacion_equipo_cura_a_todos_los_aliados(): void
    {
        $atacante = $this->combatiente(stats: ['hp' => 80], id: 'a1');
        $aliado = $this->combatiente(stats: ['hp' => 80], id: 'a2');
        $atacante->setHpActual(20.0);
        $aliado->setHpActual(20.0);
        $battle = $this->batalla([$atacante, $aliado], [$this->combatiente(id: 'd1')]);
        $ejecutor = new EjecutorCuracion(ClaveEfectoMovimiento::CURACION_EQUIPO, true);
        $movimiento = $this->movimiento(
            'Curación Equipo',
            0,
            TipoPokemon::NORMAL,
            perfil: new PerfilMovimiento(curacionFraccion: 0.5),
        );

        $this->ejecutarCon($ejecutor, $movimiento, $atacante, $battle->team2->combatientesCollection()->all()[0], $battle);

        $this->assertGreaterThan(20.0, $atacante->hpActual());
        $this->assertGreaterThan(20.0, $aliado->hpActual());
    }

    // ─── Potencia y tipo efectivos ──────────────────────────

    public function test_persecucion_aumenta_potencia_con_el_hp_faltante(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(stats: ['hp' => 100], id: 'd1');
        $movimiento = $this->movimiento('Persecución', 50, TipoPokemon::SINIESTRO, CategoriaMovimiento::FISICO);
        $battle = $this->batallaMinima($atacante, $defensor);
        $ejecutor = $this->ejecutores;

        $this->assertSame(50, $ejecutor->potenciaEfectiva(ClaveEfectoMovimiento::PERSECUCION, new AccionBatalla($atacante, $defensor, $movimiento), $battle));

        $defensor->setHpActual($this->hpMax($defensor) * 0.5);

        $this->assertSame(75, $ejecutor->potenciaEfectiva(ClaveEfectoMovimiento::PERSECUCION, new AccionBatalla($atacante, $defensor, $movimiento), $battle));
    }

    public function test_meteorobola_cambia_tipo_y_potencia_con_el_clima(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $movimiento = $this->movimiento('Meteorobola', 50, TipoPokemon::NORMAL, CategoriaMovimiento::ESPECIAL);
        $battle = $this->batallaMinima($atacante, $defensor);
        $accion = new AccionBatalla($atacante, $defensor, $movimiento);

        $this->assertNull($this->ejecutores->potenciaEfectiva(ClaveEfectoMovimiento::METEOROBOLA, $accion, $battle));

        $battle->setWeather(TipoClima::SEQUIA);

        $this->assertSame(100, $this->ejecutores->potenciaEfectiva(ClaveEfectoMovimiento::METEOROBOLA, $accion, $battle));
        $this->assertSame(TipoPokemon::FUEGO, $this->ejecutores->tipoEfectivo(ClaveEfectoMovimiento::METEOROBOLA, $accion, $battle));
    }

    public function test_pulso_campo_cambia_tipo_y_potencia_con_el_terreno(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $movimiento = $this->movimiento('Pulso de Campo', 50, TipoPokemon::NORMAL, CategoriaMovimiento::ESPECIAL);
        $battle = $this->batallaMinima($atacante, $defensor);
        $accion = new AccionBatalla($atacante, $defensor, $movimiento);

        $this->assertNull($this->ejecutores->potenciaEfectiva(ClaveEfectoMovimiento::PULSO_CAMPO, $accion, $battle));

        $battle->setTerreno(TipoTerreno::ELECTRICO, 5);

        $this->assertSame(100, $this->ejecutores->potenciaEfectiva(ClaveEfectoMovimiento::PULSO_CAMPO, $accion, $battle));
        $this->assertSame(TipoPokemon::ELECTRICO, $this->ejecutores->tipoEfectivo(ClaveEfectoMovimiento::PULSO_CAMPO, $accion, $battle));
    }

    public function test_homenaje_postumo_escala_con_aliados_debilitados(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $caido1 = $this->combatiente(id: 'a2');
        $caido2 = $this->combatiente(id: 'a3');
        $caido1->setHpActual(0.0);
        $caido2->setHpActual(0.0);
        $defensor = $this->combatiente(id: 'd1');
        $battle = $this->batalla([$atacante, $caido1, $caido2], [$defensor]);

        $potencia = $this->ejecutores->potenciaEfectiva(
            ClaveEfectoMovimiento::HOMENAJE_POSTUMO,
            new AccionBatalla($atacante, $defensor, $this->movimiento('Homenaje Póstumo', 50, TipoPokemon::NORMAL)),
            $battle,
        );

        $this->assertSame(150, $potencia);
    }

    public function test_paliza_escala_con_los_aliados_vivos(): void
    {
        $atacante = $this->combatiente(stats: ['atk' => 100], id: 'a1');
        $aliado = $this->combatiente(stats: ['atk' => 100], id: 'a2');
        $defensor = $this->combatiente(id: 'd1');
        $battle = $this->batalla([$atacante, $aliado], [$defensor]);

        $potencia = $this->ejecutores->potenciaEfectiva(
            ClaveEfectoMovimiento::PALIZA,
            new AccionBatalla($atacante, $defensor, $this->movimiento('Paliza', 10, TipoPokemon::SINIESTRO)),
            $battle,
        );

        $esperado = max(5, (int) round($atacante->obtenerStatEfectivo(StatClave::ATAQUE) / 10))
            + max(5, (int) round($aliado->obtenerStatEfectivo(StatClave::ATAQUE) / 10));

        $this->assertSame($esperado, $potencia);
    }

    public function test_danza_revelacion_usa_el_tipo_del_usuario(): void
    {
        $atacante = $this->combatiente(tipos: [TipoPokemon::FUEGO, TipoPokemon::VOLADOR], id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $battle = $this->batallaMinima($atacante, $defensor);

        $tipo = $this->ejecutores->tipoEfectivo(
            ClaveEfectoMovimiento::DANZA_REVELACION,
            new AccionBatalla($atacante, $defensor, $this->movimiento('Danza Despertar', 0, TipoPokemon::NORMAL)),
            $battle,
        );

        $this->assertSame(TipoPokemon::FUEGO, $tipo);
    }

    public function test_cambios_propios_negativos_se_ven_en_la_vista(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $movimiento = new MovimientoBatalla(
            nombre: 'A Bocajarro',
            potencia: 120,
            tipo: TipoPokemon::LUCHA,
            categoria: CategoriaMovimiento::FISICO,
            selfStatChanges: new CambiosStatsCollection([
                CambioStat::desdeEtapas(StatClave::DEFENSA, -1),
                CambioStat::desdeEtapas(StatClave::DEFENSA_ESPECIAL, -1),
            ]),
        );
        $battle = $this->batallaMinima($atacante, $defensor);

        (new ServicioEjecucionBatalla(new CadenaDanio()))->aplicarEstadoYStatChanges(
            new AccionBatalla($atacante, $defensor, $movimiento),
            $battle,
        );

        $vista = $atacante->aArrayVista(0);

        $this->assertSame(-1, $vista['stages']['defense']);
        $this->assertSame(-1, $vista['stages']['spDef']);
    }

    public function test_climas_establecen_el_clima_correspondiente(): void
    {
        $climas = [
            [ClaveEfectoMovimiento::CLIMA_SEQUIA, TipoClima::SEQUIA],
            [ClaveEfectoMovimiento::CLIMA_DILUVIO, TipoClima::DILUVIO],
            [ClaveEfectoMovimiento::CLIMA_ARENA, TipoClima::TORMENTA_ARENA],
            [ClaveEfectoMovimiento::CLIMA_GRANIZO, TipoClima::GRANIZO],
        ];

        foreach ($climas as [$clave, $climaEsperado]) {
            $atacante = $this->combatiente(id: 'a1');
            $battle = $this->batallaMinima($atacante, $this->combatiente(id: 'd1'));

            $this->ejecutar(
                $clave,
                $this->movimiento('Clima', 0, TipoPokemon::NORMAL),
                $atacante,
                $battle->team2->combatientesCollection()->all()[0],
                battle: $battle,
            );

            $this->assertSame($climaEsperado, $battle->weather(), $clave->value);
        }
    }

    public function test_terrenos_establecen_el_terreno_correspondiente(): void
    {
        $terrenos = [
            [ClaveEfectoMovimiento::TERRENO_HIERBA, TipoTerreno::HIERBA],
            [ClaveEfectoMovimiento::TERRENO_ELECTRICO, TipoTerreno::ELECTRICO],
            [ClaveEfectoMovimiento::TERRENO_PSIQUICO, TipoTerreno::PSIQUICO],
            [ClaveEfectoMovimiento::TERRENO_NIEBLA, TipoTerreno::NIEBLA],
        ];

        foreach ($terrenos as [$clave, $terrenoEsperado]) {
            $atacante = $this->combatiente(id: 'a1');
            $battle = $this->batallaMinima($atacante, $this->combatiente(id: 'd1'));

            $this->ejecutar(
                $clave,
                $this->movimiento('Terreno', 0, TipoPokemon::NORMAL),
                $atacante,
                $battle->team2->combatientesCollection()->all()[0],
                battle: $battle,
            );

            $this->assertSame($terrenoEsperado, $battle->terreno(), $clave->value);
        }
    }

    public function test_trampas_disponibles_agregan_su_tipo_al_lado_rival(): void
    {
        $trampas = [
            [ClaveEfectoMovimiento::PUAS, 'puas'],
            [ClaveEfectoMovimiento::PUAS_TOXICAS, 'puas_toxicas'],
            [ClaveEfectoMovimiento::TRAMPA_ROCAS, 'trampa_rocas'],
        ];

        foreach ($trampas as [$clave, $efectoEsperado]) {
            $atacante = $this->combatiente(id: 'a1');
            $defensor = $this->combatiente(id: 'd1');
            $battle = $this->batallaMinima($atacante, $defensor);
            $ejecutor = new EjecutorTrampa($clave);

            $this->ejecutarCon(
                $ejecutor,
                $this->movimiento('Trampa', 0, TipoPokemon::NORMAL),
                $atacante,
                $defensor,
                $battle,
            );

            $this->assertTrue($this->tieneEfectoEquipo($battle->team2, $efectoEsperado), $clave->value);
        }
    }

    public function test_pantallas_disponibles_agregan_su_efecto(): void
    {
        $pantallas = [
            [ClaveEfectoMovimiento::PANTALLA_LUZ, 'pantalla_luz'],
            [ClaveEfectoMovimiento::REFLEJO, 'reflejo'],
        ];

        foreach ($pantallas as [$clave, $efectoEsperado]) {
            $atacante = $this->combatiente(id: 'a1');
            $battle = $this->batallaMinima($atacante, $this->combatiente(id: 'd1'));
            $ejecutor = new EjecutorPantalla($clave);

            $this->ejecutarCon(
                $ejecutor,
                $this->movimiento('Pantalla', 0, TipoPokemon::PSIQUICO),
                $atacante,
                $battle->team2->combatientesCollection()->all()[0],
                $battle,
            );

            $this->assertTrue($this->tieneEfectoEquipo($battle->team1, $efectoEsperado), $clave->value);
        }
    }

    public function test_protecciones_de_equipo_agregan_su_efecto(): void
    {
        $protecciones = [
            [ClaveEfectoMovimiento::SAFEGUARD, 'safeguard'],
            [ClaveEfectoMovimiento::MIST, 'mist'],
            [ClaveEfectoMovimiento::LUCKY_CHANT, 'lucky_chant'],
        ];

        foreach ($protecciones as [$clave, $efectoEsperado]) {
            $atacante = $this->combatiente(id: 'a1');
            $defensor = $this->combatiente(id: 'd1');
            $battle = $this->batallaMinima($atacante, $defensor);
            $ejecutor = new EjecutorEquipoProteccion($clave);
            $movimiento = $this->movimiento(
                'Protección de equipo',
                0,
                TipoPokemon::NORMAL,
                perfil: new PerfilMovimiento(claveEfecto: $clave),
            );

            $this->ejecutarCon($ejecutor, $movimiento, $atacante, $defensor, $battle);

            $this->assertTrue($this->tieneEfectoEquipo($battle->team1, $efectoEsperado), $clave->value);
        }
    }

    public function test_intercambios_de_defensa_y_velocidad(): void
    {
        $intercambios = [
            [ClaveEfectoMovimiento::INTERCAMBIO_DEFENSA, StatClave::DEFENSA],
            [ClaveEfectoMovimiento::INTERCAMBIO_VELOCIDAD, StatClave::VELOCIDAD],
        ];

        foreach ($intercambios as [$clave, $stat]) {
            $atacante = $this->combatiente(id: 'a1');
            $defensor = $this->combatiente(id: 'd1');
            $atacante->setMultiplicadores(
                CambioStat::desdeEtapas($stat, 2)->aplicadoTemporalEn($atacante->multiplicadores()),
            );
            $defensor->setMultiplicadores(
                CambioStat::desdeEtapas($stat, -1)->aplicadoTemporalEn($defensor->multiplicadores()),
            );

            $this->ejecutar(
                $clave,
                $this->movimiento('Intercambio', 0, TipoPokemon::PSIQUICO),
                $atacante,
                $defensor,
            );

            $this->assertLessThan(1.0, $atacante->multiplicadores()->obtenerMultiplicador($stat), $clave->value);
            $this->assertGreaterThan(1.0, $defensor->multiplicadores()->obtenerMultiplicador($stat), $clave->value);
        }
    }

    public function test_foco_energia_aplica_critico_mejorado(): void
    {
        $atacante = $this->combatiente(id: 'a1');

        $this->ejecutar(
            ClaveEfectoMovimiento::FOCO_ENERGIA,
            $this->movimiento('Foco Energía', 0, TipoPokemon::NORMAL),
            $atacante,
            $this->combatiente(id: 'd1'),
        );

        $this->assertSame(5, $atacante->condicion(ClaveCondicion::CRITICO_MEJORADO)?->turnos);
    }

    public function test_aqua_ring_aplica_condicion(): void
    {
        $atacante = $this->combatiente(id: 'a1');

        $this->ejecutar(
            ClaveEfectoMovimiento::AQUA_RING,
            $this->movimiento('Acua Aro', 0, TipoPokemon::AGUA),
            $atacante,
            $this->combatiente(id: 'd1'),
        );

        $this->assertNotNull($atacante->condicion(ClaveCondicion::AQUA_RING));
    }

    public function test_levitacion_aplica_cinco_turnos(): void
    {
        $atacante = $this->combatiente(id: 'a1');

        $this->ejecutar(
            ClaveEfectoMovimiento::LEVITACION_TEMPORAL,
            $this->movimiento('Levitón', 0, TipoPokemon::ELECTRICO),
            $atacante,
            $this->combatiente(id: 'd1'),
        );

        $this->assertSame(5, $atacante->condicion(ClaveCondicion::LEVITACION)?->turnos);
    }

    public function test_sin_retirada_aplica_condicion(): void
    {
        $atacante = $this->combatiente(id: 'a1');

        $this->ejecutar(
            ClaveEfectoMovimiento::SIN_RETIRADA,
            $this->movimiento('Bastión Final', 0, TipoPokemon::LUCHA),
            $atacante,
            $this->combatiente(id: 'd1'),
        );

        $this->assertNotNull($atacante->condicion(ClaveCondicion::SIN_RETIRADA));
    }

    public function test_dragon_cheer_mejora_el_critico_de_los_aliados(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $aliado = $this->combatiente(id: 'a2');
        $defensor = $this->combatiente(id: 'd1');
        $battle = $this->batalla([$atacante, $aliado], [$defensor]);

        $this->ejecutar(
            ClaveEfectoMovimiento::DRAGON_CHEER,
            $this->movimiento('Bramido Dragón', 0, TipoPokemon::DRAGON),
            $atacante,
            $defensor,
            battle: $battle,
        );

        $this->assertSame(5, $aliado->condicion(ClaveCondicion::CRITICO_MEJORADO)?->turnos);
        $this->assertNull($atacante->condicion(ClaveCondicion::CRITICO_MEJORADO));
    }

    public function test_aguante_falla_en_rondas_consecutivas(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $battle = $this->batallaMinima($atacante, $defensor);
        $movimiento = $this->movimiento('Aguante', 0, TipoPokemon::NORMAL);

        $battle->turnManager()->startNewRound();
        $this->ejecutar(ClaveEfectoMovimiento::AGUANTE, $movimiento, $atacante, $defensor, battle: $battle);
        $this->assertNotNull($atacante->condicion(ClaveCondicion::AGUANTE));
        $atacante->quitarCondicion(ClaveCondicion::AGUANTE);

        // Se salta la ronda 2: en la 3 vuelve a funcionar.
        $battle->turnManager()->startNewRound();
        $battle->turnManager()->startNewRound();
        $this->ejecutar(ClaveEfectoMovimiento::AGUANTE, $movimiento, $atacante, $defensor, battle: $battle);
        $this->assertNotNull($atacante->condicion(ClaveCondicion::AGUANTE));
        $atacante->quitarCondicion(ClaveCondicion::AGUANTE);

        // Ronda 4 consecutiva: falla.
        $battle->turnManager()->startNewRound();
        $this->ejecutar(ClaveEfectoMovimiento::AGUANTE, $movimiento, $atacante, $defensor, battle: $battle);
        $this->assertNull($atacante->condicion(ClaveCondicion::AGUANTE));
    }

    public function test_proteccion_falla_en_rondas_consecutivas(): void
    {
        $atacante = $this->combatiente(id: 'a1');
        $defensor = $this->combatiente(id: 'd1');
        $battle = $this->batallaMinima($atacante, $defensor);
        $movimiento = $this->movimiento('Protección', 0, TipoPokemon::NORMAL);

        $battle->turnManager()->startNewRound();
        $this->ejecutar(ClaveEfectoMovimiento::PROTECCION, $movimiento, $atacante, $defensor, battle: $battle);
        $this->assertNotNull($atacante->condicion(ClaveCondicion::PROTEGIDO));
        $atacante->quitarCondicion(ClaveCondicion::PROTEGIDO);

        $battle->turnManager()->startNewRound();
        $this->ejecutar(ClaveEfectoMovimiento::PROTECCION, $movimiento, $atacante, $defensor, battle: $battle);
        $this->assertNull($atacante->condicion(ClaveCondicion::PROTEGIDO));
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function movimiento(
        string $nombre = 'Prueba',
        int $potencia = 0,
        TipoPokemon $tipo = TipoPokemon::NORMAL,
        CategoriaMovimiento $categoria = CategoriaMovimiento::ESTADO,
        ?PerfilMovimiento $perfil = null,
        ObjetivoMovimiento $objetivo = ObjetivoMovimiento::NORMAL,
    ): MovimientoBatalla {
        return new MovimientoBatalla(
            nombre: $nombre,
            potencia: $potencia,
            tipo: $tipo,
            categoria: $categoria,
            perfil: $perfil ?? PerfilMovimiento::porDefecto(),
            objetivo: $objetivo,
        );
    }

    private function hpMax(Combatiente $combatiente): float
    {
        return $combatiente->pokemon()->battleStats()->hp;
    }

    private function ejecutar(
        ClaveEfectoMovimiento $clave,
        MovimientoBatalla $movimiento,
        Combatiente $atacante,
        Combatiente $defensor,
        float $dano = 0.0,
        ?AgregadoBatalla $battle = null,
    ): void {
        $battle ??= $this->batallaMinima($atacante, $defensor);
        $accion = new AccionBatalla($atacante, $defensor, $movimiento);
        $this->ejecutores->ejecutar($clave, $accion, $dano, $battle);
    }

    private function ejecutarCon(
        \Src\Battle\Domain\Effects\Movimiento\InterfazEjecutorMovimiento $ejecutor,
        MovimientoBatalla $movimiento,
        Combatiente $atacante,
        Combatiente $defensor,
        AgregadoBatalla $battle,
        float $dano = 0.0,
    ): void {
        $ejecutor->ejecutar(new AccionBatalla($atacante, $defensor, $movimiento), $dano, $battle);
    }

    private function ejecutarSinEjecutor(
        ClaveEfectoMovimiento $clave,
        MovimientoBatalla $movimiento,
        Combatiente $atacante,
        Combatiente $defensor,
    ): void {
        $battle = $this->batallaMinima($atacante, $defensor);
        $this->ejecutores->ejecutar($clave, new AccionBatalla($atacante, $defensor, $movimiento), 0.0, $battle);
    }

    /**
     * @param  list<Combatiente>  $team1
     * @param  list<Combatiente>  $team2
     */
    private function batalla(array $team1, array $team2): AgregadoBatalla
    {
        $equipo1 = new EquipoBatalla('T1');
        foreach ($team1 as $combatiente) {
            $equipo1->agregarCombatiente($combatiente, $combatiente->posicion());
        }

        $equipo2 = new EquipoBatalla('T2');
        foreach ($team2 as $combatiente) {
            $equipo2->agregarCombatiente($combatiente, $combatiente->posicion());
        }

        return new AgregadoBatalla($equipo1, $equipo2);
    }

    private function batallaMinima(Combatiente $atacante, Combatiente $defensor): AgregadoBatalla
    {
        return $this->batalla([$atacante], [$defensor]);
    }

    private function tieneEfectoEquipo(EquipoBatalla $equipo, string $clave): bool
    {
        foreach ($equipo->efectos()->all() as $efecto) {
            if ($efecto->obtenerClave() === $clave) {
                return true;
            }
        }

        return false;
    }
}
