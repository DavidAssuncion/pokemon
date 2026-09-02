<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Illuminate\Support\Collection;
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
    ) {
        $pesos ??= PesosAmenaza::porDefecto();
        $cadenaDanio = new CadenaDanio();
        $this->calculadoraDanio = new CalculadoraDanioIA($cadenaDanio);
        $this->analizadorAmenazas = new AnalizadorAmenazasImpl($this->calculadoraDanio, $pesos);
        $this->evaluadorAccion = new EvaluadorAccionIAImpl($this->calculadoraDanio, $pesos);
        $this->evaluadorPosicion = new EvaluadorPosicionIA($pesos);
        $this->memoria = $memoria ?? new MemoriaCombateIA($pesos);
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

        $evaluaciones = $acciones->map(
            fn (AccionBatalla $accion) => $this->evaluadorAccion->evaluar($contexto, $amenazas, $accion)
        );

        $mejor = $this->seleccionarSegunDificultad($evaluaciones, $dificultad);

        if ($mejor === null) {
            $mejor = $this->accionFallback($contexto);
        }

        return new ResultadoDecision(
            accion: $mejor->accion,
            amenazas: $amenazas,
            evaluaciones: $evaluaciones->values(),
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

    // ─── Contexto ──────────────────────────────────────────

    private function construirContexto(
        AgregadoBatalla $battle,
        Combatiente $actor,
        NivelDificultad $dificultad,
    ): ContextoDecisionIA {
        $esTeam1 = $battle->team1->findCombatant($actor) !== null;
        $equipoActor = $esTeam1 ? $battle->team1 : $battle->team2;
        $equipoEnemigo = $esTeam1 ? $battle->team2 : $battle->team1;

        $aliados = collect($equipoActor->combatientesVivos())->filter(
            fn (Combatiente $c) => $c->id() !== $actor->id()
        );

        $enemigos = collect($equipoEnemigo->combatientesVivos());

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
     * @return Collection<int, AccionBatalla>
     */
    private function generarAccionesCandidatas(ContextoDecisionIA $contexto): Collection
    {
        $acciones = new Collection();
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
                $acciones->add(new AccionBatalla(
                    attacker: $actor,
                    defender: $enemigo,
                    move: $movimiento,
                    fromPosition: $actor->posicion(),
                    defenderTeamHasVanguard: $defenderTeamHasVanguard,
                    weather: $battle->weather(),
                ));
            }
        }

        return $acciones;
    }

    /**
     * Filtra enemigos según la restricción de posición:
     * - Actor en vanguardia → solo vanguardia enemiga.
     * - Actor en retaguardia → cualquier enemigo vivo.
     *
     * @param  Collection<int, Combatiente> $enemigos
     * @return Collection<int, Combatiente>
     */
    private function filtrarEnemigosPorPosicion(Collection $enemigos, Combatiente $actor): Collection
    {
        if ($actor->estaEnVanguardia()) {
            return $enemigos->filter(
                fn (Combatiente $e) => $e->estaEnVanguardia()
            )->values();
        }

        return $enemigos;
    }

    // ─── Selección por dificultad ──────────────────────────

    /**
     * Selecciona la mejor acción según el nivel de dificultad.
     * PERFECTA: siempre la mejor.
     * DIFÍCIL: entre las 2 mejores.
     * NORMAL: entre las 3 mejores.
     */
    private function seleccionarSegunDificultad(
        Collection $evaluaciones,
        NivelDificultad $dificultad,
    ): ?EvaluacionAccion {
        $ordenadas = $evaluaciones->sortByDesc(fn ($e) => $e->score)->values();

        if ($ordenadas->isEmpty()) {
            return null;
        }

        return match ($dificultad) {
            NivelDificultad::PERFECTA => $ordenadas->first(),
            NivelDificultad::DIFICIL => $ordenadas->take(2)->random(),
            NivelDificultad::NORMAL => $ordenadas->take(3)->random(),
        };
    }

    // ─── Fallback ──────────────────────────────────────────

    private function accionFallback(ContextoDecisionIA $contexto): EvaluacionAccion
    {
        $actor = $contexto->actor;
        $enemigo = $contexto->enemigos->first();

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
