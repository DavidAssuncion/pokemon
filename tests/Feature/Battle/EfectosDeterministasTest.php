<?php

declare(strict_types=1);

namespace Tests\Feature\Battle;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\EfectoBandaFocus;
use Src\Battle\Domain\Effects\EfectoBayaLum;
use Src\Battle\Domain\Effects\EfectoBayaSitrus;
use Src\Battle\Domain\Effects\EfectoInmunidadTipo;
use Src\Battle\Domain\Effects\EfectoInvocadorClima;
use Src\Battle\Domain\Effects\EfectoInvocadorTerreno;
use Src\Battle\Domain\Effects\EfectoMarcador;
use Src\Battle\Domain\Effects\EfectoOrbeVida;
use Src\Battle\Domain\Effects\EfectoRestos;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoEspejoMagico;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoSafeguard;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoVeloDulce;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoVeloFlor;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\Enums\TipoTerreno;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Battle\Domain\ServicioEjecucionBatalla;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;
use Src\Pokemon\Domain\PokemonEntity;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TipoPokemon;
use Src\Shared\Tipos\TiposCollection;
use Tests\TestCase;

/**
 * Batería determinista de efectos de objetos, equipo, inmunidades y campo.
 *
 * Cada caso construye combatientes con stats fijos y afirma el efecto exacto
 * (HP, estado, stats, cargas y log) sin base de datos, sesión ni storage.
 * No se apoya en aleatoriedad no controlada.
 */
final class EfectosDeterministasTest extends TestCase
{
    protected function tearDown(): void
    {
        mt_srand();
        parent::tearDown();
    }

    // ─── Objetos equipados ────────────────────────────────────

    public function test_restos_cura_un_dieciseisavo_sin_cambiar_estado_ni_stats(): void
    {
        $portador = $this->combatiente(
            stats: ['hp' => 160],
            tipos: [TipoPokemon::NORMAL],
            id: 'p1',
            nombre: 'Portador',
            item: 'leftovers',
        );
        $rival = $this->combatiente([], [TipoPokemon::AGUA], 'r1', 'Rival');
        $batalla = $this->batalla($portador, $rival);
        $portador->effects()->add(new EfectoRestos('leftovers'));

        $maxHp = $this->hpMax($portador);
        $portador->setHpActual($maxHp * 0.5);
        $portador->setEstado(EstadoPokemon::BURN);
        $portador->setTurnosEstado(2);

        $antes = $this->vitales($portador);

        $portador->effects()->triggerRoundEnd($portador, $batalla);

        $cura = max(1.0, $maxHp / 16.0);
        $despues = $this->vitales($portador);

        $this->assertSame($antes['hp'] + $cura, $despues['hp']);
        $this->assertSame($antes['estado'], $despues['estado']);
        $this->assertSame($antes['turnos'], $despues['turnos']);
        $this->assertSame($antes['veneno'], $despues['veneno']);
        $this->assertTrue($despues['statsNeutras']);
        $this->assertCount(1, $batalla->log()->toArray());
        $this->assertStringContainsString('Restos', $batalla->log()->toArray()[0]);
        $this->assertSame($this->hpMax($rival), $rival->hpActual());
    }

    public function test_orbe_vida_resta_diez_por_ciento_sin_cambiar_estado_ni_stats(): void
    {
        $atacante = $this->combatiente(
            stats: ['hp' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'a1',
            nombre: 'Atacante',
            item: 'life_orb',
        );
        $defensor = $this->combatiente(['hp' => 200], [TipoPokemon::AGUA], 'd1', 'Defensor');
        $batalla = $this->batalla($atacante, $defensor);
        $atacante->effects()->add(new EfectoOrbeVida('life_orb'));

        $atacante->setEstado(EstadoPokemon::PARALYSIS);
        $antes = $this->vitales($atacante);
        $hpDefensor = $defensor->hpActual();
        $recoil = max(1.0, $this->hpMax($atacante) * 0.10);

        $atacante->dispararDanioInfligido($defensor, 40.0, $batalla);

        $despues = $this->vitales($atacante);

        $this->assertSame($antes['hp'] - $recoil, $despues['hp']);
        $this->assertSame($antes['estado'], $despues['estado']);
        $this->assertSame($antes['turnos'], $despues['turnos']);
        $this->assertTrue($despues['statsNeutras']);
        $this->assertSame($hpDefensor, $defensor->hpActual());
        $this->assertCount(1, $batalla->log()->toArray());
        $this->assertStringContainsString('Orbe Vida', $batalla->log()->toArray()[0]);
    }

    public function test_banda_focus_deja_a_un_hp_y_se_consume_una_sola_vez(): void
    {
        $portador = $this->combatiente(
            stats: ['hp' => 120],
            tipos: [TipoPokemon::NORMAL],
            id: 'p1',
            nombre: 'Portador',
            item: 'focus_sash',
        );
        $rival = $this->combatiente([], [TipoPokemon::AGUA], 'r1', 'Rival');
        $batalla = $this->batalla($portador, $rival);
        $portador->effects()->add(new EfectoBandaFocus('focus_sash'));

        $portador->setEstado(EstadoPokemon::BURN);
        $antes = $this->vitales($portador);
        $accion = new AccionBatalla($rival, $portador, $this->movimiento('Golpe', 80, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO));
        $resultado = new ResultadoDanio(danoHp: $this->hpMax($portador));

        $portador->setHpActual(0.0);
        $portador->dispararDanioRecibidoCompleto($accion, $resultado, $batalla);

        $this->assertSame(1.0, $portador->hpActual());
        $this->assertFalse($portador->itemActivo(), 'La Banda Focus debe consumirse al activarse');
        $this->assertSame($antes['estado'], $portador->estado());
        $this->assertTrue($portador->multiplicadores()->esNeutro());

        $portador->setHpActual(0.0);
        $portador->dispararDanioRecibidoCompleto($accion, $resultado, $batalla);

        $this->assertSame(0.0, $portador->hpActual(), 'La segunda vez ya no debe salvar al portador');
        $this->assertCount(1, $batalla->log()->toArray());
        $this->assertStringContainsString('Banda Focus', $batalla->log()->toArray()[0]);
    }

    public function test_baya_sitrus_cura_al_umbral_y_se_consume(): void
    {
        $portador = $this->combatiente(
            stats: ['hp' => 160],
            tipos: [TipoPokemon::NORMAL],
            id: 'p1',
            nombre: 'Portador',
            item: 'sitrus_berry',
        );
        $rival = $this->combatiente([], [TipoPokemon::AGUA], 'r1', 'Rival');
        $batalla = $this->batalla($portador, $rival);
        $portador->effects()->add(new EfectoBayaSitrus('sitrus_berry'));

        $maxHp = $this->hpMax($portador);
        $portador->setHpActual($maxHp * 0.4);
        $portador->setEstado(EstadoPokemon::BURN);
        $hpAntes = $portador->hpActual();
        $curacion = $maxHp * 0.25;
        $accion = new AccionBatalla($rival, $portador, $this->movimiento('Golpe', 80, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO));

        $portador->dispararDanioRecibidoCompleto($accion, new ResultadoDanio(), $batalla);

        $this->assertSame($hpAntes + $curacion, $portador->hpActual());
        $this->assertFalse($portador->itemActivo(), 'La Baya Sitrus debe consumirse al curar');
        $this->assertSame(EstadoPokemon::BURN, $portador->estado());
        $this->assertTrue($portador->multiplicadores()->esNeutro());

        $portador->setHpActual($maxHp * 0.4);
        $portador->dispararDanioRecibidoCompleto($accion, new ResultadoDanio(), $batalla);

        $this->assertSame($maxHp * 0.4, $portador->hpActual(), 'Sin cargas no debe volver a curar');
        $this->assertCount(1, $batalla->log()->toArray());
    }

    public function test_baya_lum_cura_un_estado_y_se_consume(): void
    {
        $portador = $this->combatiente(
            stats: ['hp' => 120],
            tipos: [TipoPokemon::NORMAL],
            id: 'p1',
            nombre: 'Portador',
            item: 'lum_berry',
        );
        $rival = $this->combatiente([], [TipoPokemon::AGUA], 'r1', 'Rival');
        $batalla = $this->batalla($portador, $rival);
        $portador->effects()->add(new EfectoBayaLum('lum_berry'));

        $portador->setEstado(EstadoPokemon::BURN);
        $portador->setTurnosEstado(3);
        $hpAntes = $portador->hpActual();

        $portador->effects()->triggerRoundStart($portador, $batalla);

        $this->assertSame(EstadoPokemon::NONE, $portador->estado());
        $this->assertSame(0, $portador->turnosEstado());
        $this->assertSame($hpAntes, $portador->hpActual());
        $this->assertTrue($portador->multiplicadores()->esNeutro());
        $this->assertFalse($portador->itemActivo(), 'La Baya Lum debe consumirse al curar');

        $portador->setEstado(EstadoPokemon::PARALYSIS);
        $portador->effects()->triggerRoundStart($portador, $batalla);

        $this->assertSame(EstadoPokemon::PARALYSIS, $portador->estado(), 'Sin cargas no debe curar otro estado');
        $this->assertCount(1, $batalla->log()->toArray());
    }

    // ─── Efectos de equipo ────────────────────────────────────

    public function test_velo_flor_bloquea_estado_a_un_pokemon_planta(): void
    {
        $atacante = $this->combatiente([], [TipoPokemon::ELECTRICO], 'a1', 'Atacante');
        $defensor = $this->combatiente([], [TipoPokemon::PLANTA], 'd1', 'Defensor');
        $batalla = $this->batalla($atacante, $defensor);
        $batalla->team2->agregarEfecto(new EfectoEquipoVeloFlor('velo_flor', 'd1'));

        $antesDefensor = $this->vitales($defensor);
        $antesAtacante = $this->vitales($atacante);

        $aplicado = $this->servicio()->aplicarEstado(
            $defensor,
            $this->movimientoDeEstado('Paralizador', EstadoPokemon::PARALYSIS, TipoPokemon::ELECTRICO),
            $batalla,
            $atacante,
        );

        $this->assertFalse($aplicado);
        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertSame($antesDefensor, $this->vitales($defensor));
        $this->assertSame($antesAtacante, $this->vitales($atacante));
        $this->assertSame([], $batalla->log()->toArray());
    }

    public function test_velo_dulce_bloquea_sueno(): void
    {
        $atacante = $this->combatiente([], [TipoPokemon::PSIQUICO], 'a1', 'Atacante');
        $defensor = $this->combatiente([], [TipoPokemon::NORMAL], 'd1', 'Defensor');
        $batalla = $this->batalla($atacante, $defensor);
        $batalla->team2->agregarEfecto(new EfectoEquipoVeloDulce('velo_dulce', 'd1'));

        $antesDefensor = $this->vitales($defensor);

        $aplicado = $this->servicio()->aplicarEstado(
            $defensor,
            $this->movimientoDeEstado('Hipnosis', EstadoPokemon::SLEEP, TipoPokemon::PSIQUICO),
            $batalla,
            $atacante,
        );

        $this->assertFalse($aplicado);
        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertSame(0, $defensor->turnosEstado());
        $this->assertSame($antesDefensor, $this->vitales($defensor));
        $this->assertSame(EstadoPokemon::NONE, $atacante->estado());
        $this->assertSame([], $batalla->log()->toArray());
    }

    public function test_safeguard_bloquea_un_estado(): void
    {
        $atacante = $this->combatiente([], [TipoPokemon::FUEGO], 'a1', 'Atacante');
        $defensor = $this->combatiente([], [TipoPokemon::NORMAL], 'd1', 'Defensor');
        $batalla = $this->batalla($atacante, $defensor);
        $batalla->team2->agregarEfecto(new EfectoEquipoSafeguard('safeguard', 5));

        $antesDefensor = $this->vitales($defensor);

        $aplicado = $this->servicio()->aplicarEstado(
            $defensor,
            $this->movimientoDeEstado('Fuego Fatuo', EstadoPokemon::BURN, TipoPokemon::FUEGO),
            $batalla,
            $atacante,
        );

        $this->assertFalse($aplicado);
        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertSame($antesDefensor, $this->vitales($defensor));
        $this->assertSame(EstadoPokemon::NONE, $atacante->estado());
        $this->assertSame([], $batalla->log()->toArray());
    }

    public function test_espejo_magico_redirige_el_estado_al_atacante(): void
    {
        $atacante = $this->combatiente([], [TipoPokemon::ELECTRICO], 'a1', 'Atacante');
        $defensor = $this->combatiente([], [TipoPokemon::PSIQUICO], 'd1', 'Defensor');
        $batalla = $this->batalla($atacante, $defensor);
        $batalla->team2->agregarEfecto(new EfectoEquipoEspejoMagico('espejo_magico', 'd1'));

        $antesDefensor = $this->vitales($defensor);

        $aplicado = $this->servicio()->aplicarEstado(
            $defensor,
            $this->movimientoDeEstado('Onda Trueno', EstadoPokemon::PARALYSIS, TipoPokemon::ELECTRICO),
            $batalla,
            $atacante,
        );

        $this->assertFalse($aplicado);
        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertSame($antesDefensor, $this->vitales($defensor));
        $this->assertSame(EstadoPokemon::PARALYSIS, $atacante->estado());
        $this->assertSame(0, $atacante->turnosEstado());
        $this->assertTrue($atacante->multiplicadores()->esNeutro());
        $this->assertCount(1, $batalla->log()->toArray());
        $this->assertStringContainsString('Espejo Mágico', $batalla->log()->toArray()[0]);
    }

    // ─── Habilidades ──────────────────────────────────────────

    public function test_cuerpo_aureo_bloquea_un_movimiento_de_estado(): void
    {
        $atacante = $this->combatiente([], [TipoPokemon::FUEGO], 'a1', 'Atacante');
        $defensor = $this->combatiente([], [TipoPokemon::NORMAL], 'd1', 'Defensor');
        $batalla = $this->batalla($atacante, $defensor);
        $defensor->effects()->add(new EfectoMarcador('cuerpo_aureo'));

        $antesDefensor = $this->vitales($defensor);
        $antesAtacante = $this->vitales($atacante);

        $aplicado = $this->servicio()->aplicarEstado(
            $defensor,
            $this->movimientoDeEstado('Fuego Fatuo', EstadoPokemon::BURN, TipoPokemon::FUEGO),
            $batalla,
            $atacante,
        );

        $this->assertFalse($aplicado);
        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertSame($antesDefensor, $this->vitales($defensor));
        $this->assertSame($antesAtacante, $this->vitales($atacante));
        $this->assertSame([], $batalla->log()->toArray());
    }

    public function test_inmunidad_de_tipo_absorbe_el_ataque_y_cura_sin_dano(): void
    {
        $atacante = $this->combatiente(
            stats: ['spAtk' => 120],
            tipos: [TipoPokemon::AGUA],
            id: 'a1',
            nombre: 'Atacante',
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 160, 'spDef' => 80],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Defensor',
        );
        $batalla = $this->batalla($atacante, $defensor);
        $defensor->effects()->add(new EfectoInmunidadTipo('absorcion_agua', TipoPokemon::AGUA, 0.25));

        $maxHp = $this->hpMax($defensor);
        $defensor->setHpActual($maxHp * 0.5);
        $hpAntes = $defensor->hpActual();
        $movimiento = $this->movimiento(
            'Pistola Agua',
            40,
            TipoPokemon::AGUA,
            CategoriaMovimiento::ESPECIAL,
            new PerfilMovimiento(multiaccuracy: true),
        );

        $resultado = $this->servicio()->calcularYAplicarDano(
            new AccionBatalla($atacante, $defensor, $movimiento),
            $batalla,
        );

        $this->assertSame(0.0, $resultado->dano);
        $this->assertSame($hpAntes + $maxHp * 0.25, $defensor->hpActual());
        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertTrue($defensor->multiplicadores()->esNeutro());
        $this->assertSame($this->hpMax($atacante), $atacante->hpActual());
        $this->assertCount(1, $batalla->log()->toArray());
        $this->assertStringContainsString('absorbe', $batalla->log()->toArray()[0]);
    }

    // ─── Campo ────────────────────────────────────────────────

    public function test_invocadores_activan_clima_y_terreno_al_iniciar(): void
    {
        $invocadorClima = $this->combatiente([], [TipoPokemon::FUEGO], 'c1', 'Invocador Clima');
        $invocadorTerreno = $this->combatiente([], [TipoPokemon::ELECTRICO], 't1', 'Invocador Terreno');
        $batalla = $this->batalla($invocadorClima, $invocadorTerreno);
        $invocadorClima->effects()->add(new EfectoInvocadorClima('sequia', TipoClima::SEQUIA));
        $invocadorTerreno->effects()->add(new EfectoInvocadorTerreno('terreno_electrico', TipoTerreno::ELECTRICO));

        $this->assertSame(TipoClima::NONE, $batalla->weather());
        $this->assertSame(TipoTerreno::NONE, $batalla->terreno());

        $batalla->triggerBattleStartEffects();

        $this->assertSame(TipoClima::SEQUIA, $batalla->weather());
        $this->assertSame(TipoTerreno::ELECTRICO, $batalla->terreno());
        $this->assertSame(5, $batalla->turnosTerreno());
        $this->assertCount(2, $batalla->log()->toArray());
        $this->assertSame($this->hpMax($invocadorClima), $invocadorClima->hpActual());
        $this->assertSame($this->hpMax($invocadorTerreno), $invocadorTerreno->hpActual());
    }

    // ─── Helpers ──────────────────────────────────────────────

    /**
     * @param  array{hp?: int, atk?: int, def?: int, spAtk?: int, spDef?: int, speed?: int}  $stats
     * @param  list<TipoPokemon>  $tipos
     * @param  list<MovimientoBatalla>  $moves
     */
    private function combatiente(
        array $stats,
        array $tipos,
        string $id,
        string $nombre,
        array $moves = [],
        ?string $item = null,
    ): Combatiente {
        $pokemon = new PokemonEntity(
            stats: new StatsValue(
                hp: $stats['hp'] ?? 60,
                attack: $stats['atk'] ?? 100,
                defense: $stats['def'] ?? 100,
                spAtk: $stats['spAtk'] ?? 100,
                spDef: $stats['spDef'] ?? 100,
                speed: $stats['speed'] ?? 100,
            ),
            evs: new StatsValue(0, 0, 0, 0, 0, 0),
            moves: $moves,
            tiposCollection: new TiposCollection($tipos),
        );

        $combatiente = new Combatiente($pokemon, Posicion::VANGUARDIA);
        $combatiente->setId($id);
        $combatiente->setNombre($nombre);
        $combatiente->setItem($item ?? '');

        return $combatiente;
    }

    private function batalla(Combatiente $primero, Combatiente $segundo): AgregadoBatalla
    {
        $equipo1 = new EquipoBatalla('T1');
        $equipo1->agregarCombatiente($primero, $primero->posicion());

        $equipo2 = new EquipoBatalla('T2');
        $equipo2->agregarCombatiente($segundo, $segundo->posicion());

        return new AgregadoBatalla($equipo1, $equipo2);
    }

    private function servicio(): ServicioEjecucionBatalla
    {
        return new ServicioEjecucionBatalla(new CadenaDanio());
    }

    private function movimiento(
        string $nombre,
        int $potencia,
        TipoPokemon $tipo,
        CategoriaMovimiento $categoria,
        ?PerfilMovimiento $perfil = null,
    ): MovimientoBatalla {
        return new MovimientoBatalla(
            nombre: $nombre,
            potencia: $potencia,
            tipo: $tipo,
            categoria: $categoria,
            perfil: $perfil ?? PerfilMovimiento::porDefecto(),
        );
    }

    private function movimientoDeEstado(
        string $nombre,
        EstadoPokemon $estado,
        TipoPokemon $tipo,
    ): MovimientoBatalla {
        return new MovimientoBatalla(
            nombre: $nombre,
            potencia: 0,
            tipo: $tipo,
            categoria: CategoriaMovimiento::ESTADO,
            statusEffect: $estado,
        );
    }

    private function hpMax(Combatiente $combatiente): float
    {
        return $combatiente->pokemon()->battleStats()->hp;
    }

    /**
     * @return array{hp: float, estado: EstadoPokemon, turnos: int, veneno: int, statsNeutras: bool}
     */
    private function vitales(Combatiente $combatiente): array
    {
        return [
            'hp' => $combatiente->hpActual(),
            'estado' => $combatiente->estado(),
            'turnos' => $combatiente->turnosEstado(),
            'veneno' => $combatiente->cargasVeneno(),
            'statsNeutras' => $combatiente->multiplicadores()->esNeutro(),
        ];
    }
}
