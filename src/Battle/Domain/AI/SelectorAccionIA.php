<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\AI\ValueObjects\EvaluacionAccion;
use Src\Battle\Domain\AI\ValueObjects\ResultadoDecision;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Orquestador de la IA de combate.
 * Construye contexto, analiza amenazas, evalúa acciones candidatas y selecciona la mejor.
 *
 * Respeta la restricción de posición del juego:
 * - Vanguardia solo puede atacar a vanguardia enemiga.
 * - Retaguardia puede atacar a cualquier enemigo vivo.
 */
class SelectorAccionIA
{
    private CalculadoraDanioIA $calculadoraDanio;

    private AnalizadorAmenazasImpl $analizadorAmenazas;

    private EvaluadorAccionIAImpl $evaluadorAccion;

    private EvaluadorPosicionIA $evaluadorPosicion;

    private MemoriaCombateIA $memoria;

    public function __construct(
        ?PesosAmenaza $pesos = null,
        ?MemoriaCombateIA $memoria = null,
        ?AnalizadorAmenazasImpl $analizadorAmenazas = null,
        ?EvaluadorAccionIAImpl $evaluadorAccion = null,
        ?EvaluadorPosicionIA $evaluadorPosicion = null,
    ) {
        $pesos ??= PesosAmenaza::porDefecto();
        $cadenaDanio = new CadenaDanio();
        $this->calculadoraDanio = new CalculadoraDanioIA($cadenaDanio);
        $this->memoria = $memoria ?? new MemoriaCombateIA($pesos);
        $this->analizadorAmenazas = $analizadorAmenazas ?? new AnalizadorAmenazasImpl($this->calculadoraDanio, $pesos);
        $this->evaluadorAccion = $evaluadorAccion ?? new EvaluadorAccionIAImpl($this->calculadoraDanio, $pesos);
        $this->evaluadorPosicion = $evaluadorPosicion ?? new EvaluadorPosicionIA($pesos);
    }

    /**
     * Selecciona la mejor acción completa (atacante + movimiento + objetivo).
     */
    public function elegirAccion(
        AgregadoBatalla $battle,
        Combatiente $actor,
        NivelDificultad $dificultad = NivelDificultad::PERFECTA,
    ): ResultadoDecision {
        $contexto = $this->construirContexto($battle, $actor, $dificultad);

        $amenazas = $this->analizadorAmenazas->analizar($contexto);

        $acciones = $this->generarAccionesCandidatas($contexto);

        $evaluaciones = array_map(
            fn (AccionBatalla $accion) => $this->evaluadorAccion->evaluar($contexto, $amenazas, $accion),
            $acciones,
        );

        $evaluaciones = array_values($evaluaciones);

        $mejor = $this->seleccionarSegunDificultad($evaluaciones, $dificultad);

        if ($mejor === null) {
            $mejor = $this->accionFallback($contexto);
        }

        return new ResultadoDecision(
            accion: $mejor->accion,
            amenazas: $amenazas,
            evaluaciones: $evaluaciones,
        );
    }

    /**
     * Elige un objetivo enemigo para el actor dado (API legacy).
     */
    public function elegirObjetivoPara(AgregadoBatalla $battle, Combatiente $actor): ?Combatiente
    {
        $equipoEnemigo = $battle->team1->findCombatant($actor) !== null
            ? $battle->team2
            : $battle->team1;

        if (count($equipoEnemigo->combatientesVivos()) === 0) {
            return null;
        }

        $resultado = $this->elegirAccion($battle, $actor);

        return $resultado->accion->defender;
    }

    /**
     * Elige el mejor movimiento contra un defensor (API legacy).
     */
    public function elegirMejorMovimiento(Combatiente $attacker, Combatiente $defender): ?MovimientoBatalla
    {
        if ($attacker->pokemon()->moves()->isEmpty()) {
            return new MovimientoBatalla('Placaje', 40, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO);
        }

        $best = null;
        $bestScore = -1;

        foreach ($attacker->pokemon()->moves() as $move) {
            if ($move instanceof MovimientoBatalla) {
                $efectividad = $move->tipo->effectiveness($defender->pokemon());
                $score = $efectividad * $move->potencia;

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $move;
                }
            }
        }

        return $best;
    }

    public function memoria(): MemoriaCombateIA
    {
        return $this->memoria;
    }

    /**
     * Registra una acción enemiga observada en la memoria compartida.
     */
    public function registrarAccionEnemiga(AccionBatalla $accion, float $dano, int $turno = 0): void
    {
        $this->memoria->registrarAccionEnemiga($turno, $accion, $dano);
    }

    // ─── Contexto ──────────────────────────────────────────

    private function construirContexto(
        AgregadoBatalla $battle,
        Combatiente $actor,
        NivelDificultad $dificultad,
    ): ContextoDecisionIA {
        $esTeam1 = $battle->team1->findCombatant($actor) !== null;
        $equipoActor = $esTeam1 ? $battle->team1 : $battle->team2;
        $equipoEnemigo = $esTeam1 ? $battle->team2 : $battle->team1;

        $aliados = array_values(array_filter(
            $equipoActor->combatientesVivos(),
            fn (Combatiente $c) => $c->id() !== $actor->id()
        ));

        $enemigos = array_values($equipoEnemigo->combatientesVivos());

        return new ContextoDecisionIA(
            battle: $battle,
            actor: $actor,
            dificultad: $dificultad,
            aliados: $aliados,
            enemigos: $enemigos,
            turno: 0,
            memoria: $this->memoria,
            equipoActor: $esTeam1 ? 'team1' : 'team2',
        );
    }

    // ─── Generación de acciones (con filtro de posición) ───

    /**
     * Genera acciones candidatas respetando la restricción de posición del juego:
     * - Vanguardia solo puede atacar vanguardia enemiga.
     * - Retaguardia puede atacar a cualquier enemigo vivo.
     *
     * @return AccionBatalla[]
     */
    private function generarAccionesCandidatas(ContextoDecisionIA $contexto): array
    {
        $acciones = [];
        $actor = $contexto->actor;
        $battle = $contexto->battle;

        $equipoEnemigo = $battle->team1->findCombatant($actor) !== null
            ? $battle->team2
            : $battle->team1;

        $defenderTeamHasVanguard = $equipoEnemigo->tieneVanguardiaViva();

        // ─── FIX: Filtrar enemigos según posición del actor ───
        $enemigosLegales = $this->filtrarEnemigosPorPosicion($contexto->enemigos, $actor);

        foreach ($actor->pokemon()->moves() as $movimiento) {
            if (! $movimiento instanceof MovimientoBatalla) {
                continue;
            }

            foreach ($enemigosLegales as $enemigo) {
                $acciones[] = new AccionBatalla(
                    attacker: $actor,
                    defender: $enemigo,
                    move: $movimiento,
                    fromPosition: $actor->posicion(),
                    defenderTeamHasVanguard: $defenderTeamHasVanguard,
                    weather: $battle->weather(),
                );
            }
        }

        return $acciones;
    }

    /**
     * Filtra enemigos según la restricción de posición:
     * - Actor en vanguardia → solo vanguardia enemiga.
     * - Actor en retaguardia → cualquier enemigo vivo.
     *
     * @param  Combatiente[]  $enemigos
     * @return Combatiente[]
     */
    private function filtrarEnemigosPorPosicion(array $enemigos, Combatiente $actor): array
    {
        if ($actor->estaEnVanguardia()) {
            return array_values(array_filter(
                $enemigos,
                fn (Combatiente $e) => $e->estaEnVanguardia()
            ));
        }

        return $enemigos;
    }

    // ─── Selección por dificultad ──────────────────────────

    /**
     * Selecciona la mejor acción según el nivel de dificultad.
     * PERFECTA: siempre la mejor.
     * DIFÍCIL: entre las 2 mejores.
     * NORMAL: entre las 3 mejores.
     *
     * @param  EvaluacionAccion[]  $evaluaciones
     */
    private function seleccionarSegunDificultad(array $evaluaciones, NivelDificultad $dificultad): ?EvaluacionAccion
    {
        if ($evaluaciones === []) {
            return null;
        }

        usort(
            $evaluaciones,
            fn (EvaluacionAccion $a, EvaluacionAccion $b) => $b->score <=> $a->score
        );

        $candidatas = match ($dificultad) {
            NivelDificultad::PERFECTA => 1,
            NivelDificultad::DIFICIL => 2,
            NivelDificultad::NORMAL => 3,
        };

        $top = array_slice($evaluaciones, 0, $candidatas);

        return $top[array_rand($top)];
    }

    // ─── Fallback ──────────────────────────────────────────

    private function accionFallback(ContextoDecisionIA $contexto): EvaluacionAccion
    {
        $actor = $contexto->actor;
        $enemigo = $contexto->enemigos[0] ?? null;

        if ($enemigo === null) {
            throw new \LogicException('No hay enemigos vivos para la IA');
        }

        $movimiento = new MovimientoBatalla('Placaje', 40, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO);

        $accion = new AccionBatalla(
            attacker: $actor,
            defender: $enemigo,
            move: $movimiento,
            fromPosition: $actor->posicion(),
            defenderTeamHasVanguard: false,
            weather: $contexto->battle->weather(),
        );

        return new EvaluacionAccion(
            accion: $accion,
            score: 0,
            koValue: 0,
            damageValue: 0,
            threatReduction: 0,
            survivalValue: 0,
            risk: 0,
        );
    }
}
