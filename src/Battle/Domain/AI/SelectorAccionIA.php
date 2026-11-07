<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\AI\ValueObjects\EvaluacionAccion;
use Src\Battle\Domain\AI\ValueObjects\ResultadoDecision;
use Src\Battle\Domain\Catalog\CatalogoEfectosAliado;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Collections\AccionesPosiblesCollection;
use Src\Battle\Domain\Collections\CombatientesCollection;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\Bando;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\ClaveCondicion;
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
        bool $incluirSoporte = true,
    ): ResultadoDecision {
        $contexto = $this->construirContexto($battle, $actor, $dificultad);

        $amenazas = $this->analizadorAmenazas->analizar($contexto);

        $acciones = $this->generarAccionesCandidatas($contexto, $incluirSoporte);

        $evaluaciones = $acciones->map(
            fn (AccionBatalla $accion) => $this->evaluadorAccion->evaluar($contexto, $amenazas, $accion),
        );

        // El apoyo sin efecto real no compite, y nunca lo hace contra un KO.
        $evaluaciones = array_values(array_filter(
            $evaluaciones,
            fn (EvaluacionAccion $evaluacion): bool => ! ClasificadorAccionIA::esApoyo(
                $contexto->battle,
                $evaluacion->accion,
            ) || $evaluacion->supportValue > 0.0,
        ));

        if ($this->hayKOEn($evaluaciones, $contexto->battle)) {
            $evaluaciones = array_values(array_filter(
                $evaluaciones,
                fn (EvaluacionAccion $evaluacion): bool => ! ClasificadorAccionIA::esApoyo(
                    $contexto->battle,
                    $evaluacion->accion,
                ),
            ));
        }

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

        if ($equipoEnemigo->combatientesCollection()->vivos()->isEmpty()) {
            return null;
        }

        $resultado = $this->elegirAccion($battle, $actor, incluirSoporte: false);

        return $resultado->accion->defender;
    }

    /**
     * Elige el mejor movimiento contra un defensor (API legacy).
     */
    public function elegirMejorMovimiento(Combatiente $attacker, Combatiente $defender): ?MovimientoBatalla
    {
        if ($attacker->pokemon()->moves()->isEmpty()) {
            return self::movimientoBase();
        }

        $best = null;
        $bestScore = -1;

        foreach ($attacker->pokemon()->moves() as $move) {
            if ($move instanceof MovimientoBatalla && $attacker->movimientoDisponible($move)) {
                $efectividad = $defender->efectividadContra($move->tipo);
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

        $aliados = $equipoActor->combatientesCollection()->vivos()->filter(
            fn (Combatiente $c) => $c->id() !== $actor->id()
        );

        $enemigos = $equipoEnemigo->combatientesCollection()->vivos();

        return new ContextoDecisionIA(
            battle: $battle,
            actor: $actor,
            dificultad: $dificultad,
            aliados: $aliados,
            enemigos: $enemigos,
            turno: 0,
            memoria: $this->memoria,
            equipoActor: $esTeam1 ? Bando::UNO : Bando::DOS,
        );
    }

    // ─── Generación de acciones (con filtro de posición) ───

    /**
     * Genera acciones candidatas respetando la restricción de posición del juego:
     * - Vanguardia solo puede atacar vanguardia enemiga.
     * - Retaguardia puede atacar a cualquier enemigo vivo.
     */
    private function generarAccionesCandidatas(
        ContextoDecisionIA $contexto,
        bool $incluirSoporte = true,
    ): AccionesPosiblesCollection {
        $acciones = new AccionesPosiblesCollection();
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

            if (! $actor->movimientoDisponible($movimiento)) {
                continue;
            }

            if ($movimiento->esAutodirigido()) {
                $acciones->add(new AccionBatalla(
                    attacker: $actor,
                    defender: $actor,
                    move: $movimiento,
                    defenderTeamHasVanguard: false,
                    weather: $battle->weather(),
                    terreno: $battle->terreno(),
                ));

                continue;
            }

            $objetivos = $enemigosLegales->all();
            if ($movimiento->objetivo->esFila() && $objetivos !== []) {
                $objetivos = [$objetivos[0]];
            }

            $desafio = $actor->condicion(ClaveCondicion::DESAFIO);
            if ($desafio !== null && $desafio->objetivoForzadoId !== '') {
                $forzado = $battle->team1->findCombatantById($desafio->objetivoForzadoId)
                    ?? $battle->team2->findCombatantById($desafio->objetivoForzadoId);
                if ($forzado !== null && $forzado->estaVivo()) {
                    $objetivos = [$forzado];
                }
            }

            foreach ($objetivos as $enemigo) {
                $acciones->add(new AccionBatalla(
                    attacker: $actor,
                    defender: $enemigo,
                    move: $movimiento,
                    defenderTeamHasVanguard: $defenderTeamHasVanguard,
                    weather: $battle->weather(),
                    terreno: $battle->terreno(),
                ));
            }
        }

        if ($incluirSoporte) {
            $this->agregarAccionesSoporte($contexto, $acciones);
        }

        return $acciones;
    }

    /**
     * Añade las variantes de apoyo sobre aliados vivos alcanzables.
     *
     * El bloque enemigo ya generó las variantes autodirigidas apuntando al
     * actor; aquí se añaden las que apuntan a un aliado para que compitan por
     * la misma evaluación.
     */
    private function agregarAccionesSoporte(
        ContextoDecisionIA $contexto,
        AccionesPosiblesCollection $acciones,
    ): void {
        if ($contexto->aliados->isEmpty()) {
            return;
        }

        $actor = $contexto->actor;
        $battle = $contexto->battle;

        $movimientos = [];
        foreach ($actor->pokemon()->moves() as $movimiento) {
            if (! $movimiento instanceof MovimientoBatalla) {
                continue;
            }

            if (! $actor->movimientoDisponible($movimiento)) {
                continue;
            }

            if (! CatalogoEfectosAliado::admite($movimiento->slug)) {
                continue;
            }

            $movimientos[] = $movimiento;
        }

        if ($movimientos === []) {
            return;
        }

        $equipoActor = $contexto->equipoActor === Bando::UNO
            ? $battle->team1
            : $battle->team2;
        $vanguardiaViva = $equipoActor->tieneVanguardiaViva();

        foreach ($contexto->aliados as $aliado) {
            if ($aliado->id() === $actor->id() || ! $aliado->estaVivo()) {
                continue;
            }

            if ($vanguardiaViva && $aliado->estaEnRetaguardia()) {
                continue;
            }

            foreach ($movimientos as $movimiento) {
                $acciones->add(new AccionBatalla(
                    attacker: $actor,
                    defender: $aliado,
                    move: $movimiento,
                    defenderTeamHasVanguard: $vanguardiaViva,
                    weather: $battle->weather(),
                    terreno: $battle->terreno(),
                    equipoDefensor: $equipoActor,
                ));
            }
        }
    }

    /**
     * Filtra enemigos según la restricción de posición:
     * - Actor en vanguardia → vanguardia enemiga mientras quede alguna viva.
     * - Si la vanguardia enemiga está caída, puede atacar a la retaguardia.
     * - Actor en retaguardia → cualquier enemigo vivo.
     */
    private function filtrarEnemigosPorPosicion(CombatientesCollection $enemigos, Combatiente $actor): CombatientesCollection
    {
        if (! $actor->estaEnVanguardia()) {
            return $enemigos;
        }

        $vanguardiaEnemiga = $enemigos->filter(
            fn (Combatiente $e) => $e->estaEnVanguardia()
        );

        return $vanguardiaEnemiga->isEmpty() ? $enemigos : $vanguardiaEnemiga;
    }

    /**
     * Indica si alguna evaluación no-apoyo consigue un KO.
     *
     * @param  EvaluacionAccion[]  $evaluaciones
     */
    private function hayKOEn(array $evaluaciones, AgregadoBatalla $battle): bool
    {
        foreach ($evaluaciones as $evaluacion) {
            if ($evaluacion->koValue > 0.0
                && ! ClasificadorAccionIA::esApoyo($battle, $evaluacion->accion)) {
                return true;
            }
        }

        return false;
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
            function (EvaluacionAccion $a, EvaluacionAccion $b): int {
                $score = $b->score <=> $a->score;

                if ($score !== 0) {
                    return $score;
                }

                // Empate: prioriza el daño efectivo y, después, la potencia.
                // Evita que un movimiento de estado (p. ej. Aguante) gane
                // sistemáticamente a un ataque cuando las puntuaciones igualan.
                $damage = $b->damageValue <=> $a->damageValue;

                if ($damage !== 0) {
                    return $damage;
                }

                return $b->accion->move->potencia <=> $a->accion->move->potencia;
            },
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

    /**
     * Movimiento por defecto cuando el pokémon no tiene movimientos.
     */
    private static function movimientoBase(): MovimientoBatalla
    {
        return new MovimientoBatalla('Placaje', 40, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO);
    }

    private function accionFallback(ContextoDecisionIA $contexto): EvaluacionAccion
    {
        $actor = $contexto->actor;
        $enemigo = $contexto->enemigos->first();

        if ($enemigo === null) {
            throw new \LogicException('No hay enemigos vivos para la IA');
        }

        $movimiento = $actor->pokemon()->moves()->isEmpty()
            ? self::movimientoBase()
            : new MovimientoBatalla('Espera', 0, TipoPokemon::NORMAL, CategoriaMovimiento::ESTADO);

        $accion = new AccionBatalla(
            attacker: $actor,
            defender: $enemigo,
            move: $movimiento,
            defenderTeamHasVanguard: false,
            weather: $contexto->battle->weather(),
            terreno: $contexto->battle->terreno(),
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
