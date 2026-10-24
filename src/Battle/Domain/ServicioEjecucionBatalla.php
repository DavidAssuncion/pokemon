<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Collections\CombatientesCollection;
use Src\Battle\Domain\Danio\AplicadorPosturaDanio;
use Src\Battle\Domain\Danio\GestorEfectosMovimiento;
use Src\Battle\Domain\Danio\ResolutorObjetivosDanio;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoEspejoMagico;
use Src\Battle\Domain\Effects\Movimiento\ColeccionEjecutoresMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;
use Src\Battle\Presentation\DTOResultadoDanio;

/**
 * Servicio compartido que ejecuta el cálculo y aplicación de un movimiento.
 * Unifica la lógica duplicada entre Combate (Livewire) y AgregadoBatalla::ejecutarAccion (auto).
 */
class ServicioEjecucionBatalla
{
    public function __construct(
        private readonly CadenaDanio $damageChain,
        ?ColeccionEjecutoresMovimiento $ejecutores = null,
        private readonly AplicadorPosturaDanio $aplicadorPostura = new AplicadorPosturaDanio(),
        private readonly GestorEfectosMovimiento $gestorEfectos = new GestorEfectosMovimiento(),
    ) {
        $this->ejecutores = $ejecutores ?? ColeccionEjecutoresMovimiento::conEjecutoresPorDefecto();
    }

    private readonly ColeccionEjecutoresMovimiento $ejecutores;

    /**
     * Calcula y aplica el daño de un movimiento siguiendo su PerfilMovimiento:
     * multi-golpe, precisión/esquiva, multiplicadores de escudo/salud, drenaje
     * y recoil. No maneja logging, animaciones ni el resto de efectos.
     */
    /**
     * Calcula y aplica el daño. Si el movimiento es de fila, golpea a todos
     * los vivos de la vanguardia o retaguardia rival con daño completo.
     */
    public function calcularYAplicarDano(AccionBatalla $accion, ?AgregadoBatalla $battle = null): DTOResultadoDanio
    {
        if ($battle === null) {
            $resultado = $this->calcularYAplicarDanoContra($accion, null);
            $this->aplicarAutodestruccion($accion, null);

            return $resultado;
        }

        $defensores = ResolutorObjetivosDanio::resolver($accion, $battle);
        if ($defensores === null) {
            $resultado = $this->calcularYAplicarDanoContra($accion, $battle);
            $this->aplicarAutodestruccion($accion, $battle);

            return $resultado;
        }

        if ($defensores->isEmpty()) {
            $this->aplicarAutodestruccion($accion, $battle);

            return new DTOResultadoDanio(dano: 0.0, directPct: 0.0, resultado: ResultadoDanio::sinDano(), golpes: 0);
        }

        $dano = 0.0;
        $danoHpTotal = 0.0;
        $golpes = 0;
        $total = new ResultadoDanio();
        $directPct = 0.0;
        $ultimo = ResultadoDanio::sinDano();
        /** @var list<Combatiente> $objetivos */
        $objetivos = [];

        foreach ($defensores as $defensor) {
            if (! $accion->attacker->estaVivo()) {
                break;
            }

            $equipoDefensor = $battle->equipoDe($defensor);
            $accionDefensor = new AccionBatalla(
                attacker: $accion->attacker,
                defender: $defensor,
                move: $accion->move,
                defenderTeamHasVanguard: $equipoDefensor?->tieneVanguardiaViva() ?? false,
                weather: $accion->weather,
                isPreview: $accion->isPreview,
                equipoDefensor: $equipoDefensor,
                terreno: $accion->terreno,
            );

            // En multiobjetivo, drenaje, recoil y coste de velocidad se aplican
            // una sola vez al final, sobre el daño acumulado.
            $resultado = $this->calcularYAplicarDanoContra($accionDefensor, $battle, false);
            if ($resultado->golpes > 0) {
                $objetivos[] = $defensor;
            }
            $dano += $resultado->dano;
            $danoHpTotal += $resultado->resultado->danoHp ?? 0.0;
            $golpes += $resultado->golpes;
            $directPct = $resultado->directPct;
            $ultimo = $resultado->resultado ?? $ultimo;
            $total = new ResultadoDanio(
                danoHp: $total->danoHp + ($resultado->resultado->danoHp ?? 0.0),
                danoEscudo: $total->danoEscudo + ($resultado->resultado->danoEscudo ?? 0.0),
                excedente: $total->excedente + ($resultado->resultado->excedente ?? 0.0),
                rompioEscudo: $total->rompioEscudo || ($resultado->resultado->rompioEscudo ?? false),
            );
        }

        $curado = $this->aplicarDrenaje($accion, $danoHpTotal);
        $recoil = $this->aplicarRecoil($accion, $dano, $battle);
        $this->aplicarCosteVelocidad($accion);
        $this->aplicarAutodestruccion($accion, $battle);

        return new DTOResultadoDanio(
            dano: $dano,
            directPct: $directPct,
            resultado: $total,
            golpes: $golpes,
            curado: $curado,
            recoil: $recoil,
            objetivos: new CombatientesCollection($objetivos),
        );
    }

    private function calcularYAplicarDanoContra(
        AccionBatalla $accion,
        ?AgregadoBatalla $battle = null,
        bool $aplicarEfectosGlobales = true,
    ): DTOResultadoDanio {
        $movimiento = $accion->move;
        $perfil = $movimiento->perfil;
        $directPct = min(1.0, $accion->attacker->obtenerPorcentajeDanioDirecto() + $perfil->danoDirectoFraccion);

        // La racha solo vive mientras se repita un movimiento encadenable.
        $accion->attacker->prepararCombo($movimiento);

        if ($movimiento->esEstado()) {
            $accion->attacker->romperCombo();
        }

        if ($battle !== null
            && $accion->defender->tieneEfecto('humedad')
            && $perfil->autodestruccion) {
            $battle->agregarLog("¡La humedad impide que {$accion->attacker->nombre()} use {$movimiento->nombre}!");

            return new DTOResultadoDanio(dano: 0.0, directPct: $directPct, resultado: ResultadoDanio::sinDano(), golpes: 0);
        }

        if ($battle !== null
            && $perfil->fallaSinTerreno
            && ! $accion->terreno->esActivo()) {
            return new DTOResultadoDanio(dano: 0.0, directPct: $directPct, resultado: ResultadoDanio::sinDano(), golpes: 0);
        }

        if ($battle !== null
            && $perfil->requiereObjetivoDormido
            && $accion->defender->estado() !== \Src\Battle\Domain\Enums\EstadoPokemon::SLEEP) {
            $battle->agregarLog("{$accion->move->nombre} falla porque {$accion->defender->nombre()} no está dormido");

            return new DTOResultadoDanio(dano: 0.0, directPct: $directPct, resultado: ResultadoDanio::sinDano(), golpes: 0);
        }

        foreach ([\Src\Battle\Domain\Enums\FlagMovimiento::BALA, \Src\Battle\Domain\Enums\FlagMovimiento::SONIDO, \Src\Battle\Domain\Enums\FlagMovimiento::POLVO] as $flag) {
            if ($accion->move->flags->tiene($flag) && $accion->defender->esInmuneAFlag($flag)) {
                $battle?->agregarLog("{$accion->defender->nombre()} es inmune a {$accion->move->nombre}");

                return new DTOResultadoDanio(dano: 0.0, directPct: $directPct, resultado: ResultadoDanio::sinDano(), golpes: 0);
            }
        }

        if ($battle !== null
            && $accion->defender->tieneEfecto('telepatia')
            && $battle->equipoDe($accion->attacker) === $battle->equipoDe($accion->defender)) {
            $battle->agregarLog("{$accion->defender->nombre()} evita el ataque de su aliado por Telepatía");

            return new DTOResultadoDanio(dano: 0.0, directPct: $directPct, resultado: ResultadoDanio::sinDano(), golpes: 0);
        }

        if ($battle !== null && $perfil->turnosDanoDiferido > 0) {
            $this->ejecutores->ejecutar(
                $perfil->claveEfecto ?? \Src\Battle\Domain\Enums\ClaveEfectoMovimiento::DANO_DIFERIDO,
                $accion,
                0.0,
                $battle,
            );

            return new DTOResultadoDanio(dano: 0.0, directPct: $directPct, resultado: ResultadoDanio::sinDano(), golpes: 0);
        }

        if ($battle !== null && $perfil->claveEfecto !== null) {
            $potencia = $this->ejecutores->potenciaEfectiva($perfil->claveEfecto, $accion, $battle);
            if ($potencia !== null) {
                $movimiento = $movimiento->conPotencia($potencia);
                $perfil = $movimiento->perfil;
            }

            $tipo = $this->ejecutores->tipoEfectivo($perfil->claveEfecto, $accion, $battle);
            if ($tipo !== null) {
                $movimiento = $movimiento->conTipo($tipo);
                $perfil = $movimiento->perfil;
            }
        }

        if ($battle !== null && $accion->defender !== $accion->attacker) {
            $accion->defender->dispararSerObjetivo($accion, $battle);
        }

        if (! $perfil->multiaccuracy && $this->esquiva($accion)) {
            $accion->attacker->romperCombo();

            return new DTOResultadoDanio(dano: 0.0, directPct: $directPct, resultado: ResultadoDanio::sinDano(), golpes: 0);
        }

        $perfilMovimiento = $accion->move->perfil;
        if ($perfilMovimiento->formulaDano === 'normal'
            && $perfilMovimiento->danoPorcentajeHpObjetivo <= 0.0
            && $perfilMovimiento->danoPorcentajeHpAtacante <= 0.0) {
            $inmunidad = $accion->attacker->ignoraHabilidadRival()
                ? null
                : $accion->defender->inmunidadTipo($accion->attacker->tipoEfectivoMovimiento($accion));
            if ($inmunidad !== null) {
                if ($battle !== null) {
                    $inmunidad->alAbsorber(
                        $accion->defender,
                        $accion->attacker->tipoEfectivoMovimiento($accion),
                        $battle,
                    );
                }

                $accion->attacker->romperCombo();

                return new DTOResultadoDanio(dano: 0.0, directPct: $directPct, resultado: ResultadoDanio::sinDano(), golpes: 0);
            }
        }

        $golpes = $accion->attacker->tieneEfecto('encadenamiento')
            ? $perfil->golpesMax
            : mt_rand($perfil->golpesMin, $perfil->golpesMax);
        $fraccionPotencia = $perfil->fraccionPotenciaPorGolpe;
        $ajusteGolpes = $accion->attacker->ajustarGolpes($accion);
        if ($ajusteGolpes !== null) {
            $golpes = $ajusteGolpes->golpes;
            $fraccionPotencia = $ajusteGolpes->fraccionPotencia;
        }

        $potenciaGolpe = $golpes > 1
            ? max(1, (int) round($movimiento->potencia * $fraccionPotencia))
            : $movimiento->potencia;

        $danoTotal = 0.0;
        $danoHpTotal = 0.0;
        $golpesRealizados = 0;
        $ultimo = ResultadoDanio::sinDano();

        for ($golpe = 0; $golpe < $golpes; $golpe++) {
            $movimientoGolpe = $golpes > 1 ? $movimiento->conPotencia($potenciaGolpe) : $movimiento;
            $accionGolpe = new AccionBatalla(
                attacker: $accion->attacker,
                defender: $accion->defender,
                move: $movimientoGolpe,
                defenderTeamHasVanguard: $accion->defenderTeamHasVanguard,
                weather: $accion->weather,
                isPreview: $accion->isPreview,
                equipoDefensor: $battle?->equipoDe($accion->defender),
                terreno: $accion->terreno,
            );

            if ($perfil->multiaccuracy && $this->esquiva($accionGolpe)) {
                $accion->attacker->romperCombo();
                break;
            }

            $daño = $this->damageChain->calculate($accionGolpe);
            $resultado = $this->aplicadorPostura->aplicar(
                $accionGolpe,
                $movimientoGolpe,
                $daño,
                $directPct,
                $perfil,
                $battle,
            );

            $golpesRealizados++;
            $ultimo = $resultado;
            $danoTotal += $resultado->danoAplicado();
            $danoHpTotal += $resultado->danoHp;

            if ($resultado->huboDano()) {
                if ($movimiento->perfil->potenciaCreciente) {
                    $accion->attacker->registrarGolpeCombo($movimiento);
                }
            } else {
                $accion->attacker->romperCombo();
            }

            if ($battle !== null && $resultado->huboDano()) {
                $this->dispararReacciones(
                    $accion,
                    $accionGolpe,
                    $movimientoGolpe,
                    $resultado,
                    $battle,
                );
            }

            if (! $accion->defender->estaVivo() || ! $accion->attacker->estaVivo() || ! $resultado->huboDano()) {
                break;
            }
        }

        $curado = $aplicarEfectosGlobales
            ? $this->aplicarDrenaje($accion, $danoHpTotal)
            : 0.0;

        $recoil = $aplicarEfectosGlobales
            ? $this->aplicarRecoil($accion, $danoTotal, $battle)
            : 0.0;

        if ($battle !== null && $perfil->claveEfecto !== null) {
            $this->ejecutores->ejecutar($perfil->claveEfecto, $accion, $danoTotal, $battle);
        }

        if ($battle !== null && $movimiento->perfil->rompePantallas && $danoTotal > 0.0) {
            $battle->equipoDe($accion->defender)?->limpiarPantallas();
            $battle->agregarLog('¡Las pantallas del equipo rival se rompen!');
        }

        if ($battle !== null && $movimiento->perfil->eliminaTerreno && $battle->terreno()->esActivo()) {
            $battle->setTerreno(\Src\Battle\Domain\Enums\TipoTerreno::NONE);
            $battle->agregarLog('¡El terreno quedó destruido!');
        }

        if ($battle !== null) {
            $this->aplicarCuracion($accion, $battle);
        }

        if ($aplicarEfectosGlobales) {
            $this->aplicarCosteVelocidad($accion);
        }

        if ($battle !== null) {
            $this->gestorEfectos->aplicarSecundarios($accion, $movimiento, $battle);

            $this->gestorEfectos->aplicarControl($accion, $movimiento, $battle);

            if (! $movimiento->esEstado()
                && $accion->defender->estaVivo()
                && $accion->attacker->estaVivo()) {
                $accion->defender->contraatacar($accion, max(1.0, $danoTotal), $battle);
            }
        }

        return new DTOResultadoDanio(
            dano: $danoTotal,
            directPct: $directPct,
            resultado: $ultimo,
            golpes: $golpesRealizados,
            curado: $curado,
            recoil: $recoil,
            objetivos: $golpesRealizados > 0
                ? new CombatientesCollection([$accion->defender])
                : new CombatientesCollection(),
        );
    }

    /**
     * Aplica el estado secundario del movimiento al objetivo.
     */
    public function aplicarEstado(
        Combatiente $objetivo,
        MovimientoBatalla $movimiento,
        ?AgregadoBatalla $battle = null,
        ?Combatiente $atacante = null,
    ): void {
        if ($movimiento->esEstado() && $objetivo->tieneEfecto('cuerpo_aureo')) {
            return;
        }

        foreach ([\Src\Battle\Domain\Enums\FlagMovimiento::BALA, \Src\Battle\Domain\Enums\FlagMovimiento::SONIDO, \Src\Battle\Domain\Enums\FlagMovimiento::POLVO] as $flag) {
            if ($movimiento->flags->tiene($flag) && $objetivo->esInmuneAFlag($flag)) {
                return;
            }
        }

        if ($atacante !== null && $battle !== null) {
            foreach ($battle->equipoDe($objetivo)?->efectos()->all() ?? [] as $efecto) {
                if ($efecto instanceof EfectoEquipoEspejoMagico && $efecto->estaActivo()) {
                    $this->gestorEfectos->aplicarEstado($atacante, $movimiento, $battle);
                    $battle->agregarLog("¡Espejo Mágico devuelve {$movimiento->nombre} a {$atacante->nombre()}!");

                    return;
                }
            }
        }

        $this->gestorEfectos->aplicarEstado($objetivo, $movimiento, $battle);
    }

    /**
     * Aplica cambios de estadísticas del movimiento al actor y al objetivo.
     * Los cambios son CambioStat (StatClave, factor) ya traducidos desde etapas;
     * cada uno se aplica sobre los multiplicadores actuales del combatiente.
     */
    public function aplicarStatChanges(Combatiente $actor, Combatiente $objetivo, MovimientoBatalla $movimiento): void
    {
        $this->gestorEfectos->aplicarStatChanges($actor, $objetivo, $movimiento);
    }

    /**
     * Aplica el estado principal y los cambios de estadísticas del movimiento
     * a todos los objetivos resueltos (fila, allAdjacent, etc.). Los cambios
     * propios se aplican una sola vez; los del objetivo, una vez por objetivo.
     */
    /**
     * @param  CombatientesCollection|null  $objetivos  Objetivos realmente alcanzados;
     *        null resuelve todos los del movimiento, colección vacía no aplica a ninguno.
     */
    public function aplicarEstadoYStatChanges(
        AccionBatalla $accion,
        ?AgregadoBatalla $battle = null,
        ?CombatientesCollection $objetivos = null,
    ): void {
        if ($objetivos === null) {
            $objetivos = $battle !== null
                ? ResolutorObjetivosDanio::resolver($accion, $battle)
                : null;
            $objetivos ??= new CombatientesCollection([$accion->defender]);
        }

        $objetivos = $objetivos->filter(
            fn (Combatiente $objetivo): bool => ! ($accion->move->esEstado() && $objetivo->tieneEfecto('cuerpo_aureo')),
        );

        foreach ($objetivos as $objetivo) {
            $this->aplicarEstado($objetivo, $accion->move, $battle, $accion->attacker);
        }

        $this->gestorEfectos->aplicarStatChangesAMuchos($accion->attacker, $objetivos->all(), $accion->move);
    }

    /**
     * Genera el mensaje de log descriptivo del movimiento ejecutado.
     */
    public function generarLogMovimiento(
        Combatiente $actor,
        Combatiente $objetivo,
        MovimientoBatalla $movimiento,
        float $daño,
        float $directPct,
        bool $defenderTeamHasVanguard,
    ): string {
        $dmgPart = $daño > 0 ? " → {$daño} de daño a {$objetivo->nombre()}" : '';
        $logMsg = "{$actor->nombre()} usa {$movimiento->nombre}{$dmgPart}";

        // El label del log está derivado de ReglasBatalla::MULTIPLICADOR_RETAGUARDIA
        // (reducción del 50% cuando el defensor está en retaguardia).
        if ($daño > 0 && $objetivo->estaEnRetaguardia() && $defenderTeamHasVanguard) {
            $logMsg .= ' (-'.(int) ((1 - ReglasBatalla::MULTIPLICADOR_RETAGUARDIA) * 100).'% retaguardia)';
        }

        if ($daño > 0 && $directPct > 0) {
            $logMsg .= ' ['.($directPct * 100).'% directo]';
        }

        return $logMsg;
    }

    /**
     * Aplica el daño teniendo en cuenta Guardia y Cubrir.
     *
     * Orden: reparto por cubridores → Guardia de cada receptor → daño.
     * El cubierto siempre recibe 60 % con un cubridor y 20 % con dos.
     */
    private function esquiva(AccionBatalla $accion): bool
    {
        $perfil = $accion->move->perfil;
        $precision = $perfil->precisionPorClima[$accion->weather->value] ?? $perfil->precision;

        if ($accion->move->esEstado()) {
            $precision *= $accion->defender->multiplicadorPrecisionEstado();

            return $precision < 1.0 && mt_rand() / mt_getrandmax() >= $precision;
        }

        $precision *= $accion->attacker->multiplicadorPrecisionAtaque();
        if ($precision < 1.0 && mt_rand() / mt_getrandmax() >= $precision) {
            return true;
        }

        if ($accion->attacker->tieneEfecto('indefenso') || $accion->defender->tieneEfecto('indefenso') || $accion->attacker->tieneEfecto('ojo_mental')) {
            return false;
        }

        $esquiva = $accion->defender->probabilidadEsquiva();

        return $esquiva > 0.0 && mt_rand() / mt_getrandmax() < $esquiva;
    }

    private function dispararReacciones(
        AccionBatalla $accion,
        AccionBatalla $accionGolpe,
        MovimientoBatalla $movimiento,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        $accion->defender->dispararDanioRecibidoCompleto($accionGolpe, $resultado, $battle);

        if ($movimiento->esContacto()) {
            $accion->defender->dispararContacto($accion->attacker, $battle);
        }

        if ($resultado->rompioEscudo) {
            $accion->defender->dispararEscudoRoto($accion->attacker, $resultado, $battle);
            $accion->attacker->dispararEscudoRoto($accion->defender, $resultado, $battle);
        }

        if (! $accion->defender->estaVivo()) {
            $accion->attacker->effects()->alDebilitarRival($accion->attacker, $accion->defender, $battle);
        }
    }

    private function aplicarAutodestruccion(AccionBatalla $accion, ?AgregadoBatalla $battle): void
    {
        if (! $accion->move->perfil->autodestruccion || ! $accion->attacker->estaVivo()) {
            return;
        }

        $accion->attacker->setHpActual(0.0);
        $battle?->agregarLog("¡{$accion->attacker->nombre()} se autodestruye!");
    }

    private function aplicarDrenaje(AccionBatalla $accion, float $danoHpTotal): float
    {
        $perfil = $accion->move->perfil;

        if (! $accion->attacker->estaVivo()
            || $perfil->drenajeFraccion <= 0.0
            || $danoHpTotal <= 0.0) {
            return 0.0;
        }

        if ($accion->defender->tieneEfecto('lodo_liquido')) {
            $cantidad = $danoHpTotal * $perfil->drenajeFraccion;
            $accion->attacker->recibirDaño($cantidad, false, 1.0);

            return 0.0;
        }

        return $accion->attacker->curarConExcedente($danoHpTotal * $perfil->drenajeFraccion);
    }

    private function aplicarCuracion(AccionBatalla $accion, AgregadoBatalla $battle): void
    {
        $perfil = $accion->move->perfil;
        if ($perfil->curacionFraccion <= 0.0
            || $accion->attacker !== $accion->defender
            || ! $accion->attacker->estaVivo()) {
            return;
        }

        $maxHp = max(1.0, $accion->attacker->pokemon()->battleStats()->hp);
        $curado = $accion->attacker->curarHpCantidad($maxHp * $perfil->curacionFraccion);
        if ($curado > 0.0) {
            $battle->agregarLog("{$accion->attacker->nombre()} recupera ".round($curado).' PS');
        }
    }

    private function aplicarCosteVelocidad(AccionBatalla $accion): void
    {
        $perfil = $accion->move->perfil;

        if (! $accion->attacker->estaVivo() || $perfil->costeVelocidadFraccion <= 0.0) {
            return;
        }

        $accion->attacker->aplicarCosteVelocidad($perfil->costeVelocidadFraccion);
    }

    private function aplicarRecoil(AccionBatalla $accion, float $danoTotal, ?AgregadoBatalla $battle): float
    {
        $perfil = $accion->move->perfil;

        if (($perfil->recoilFraccion <= 0.0 && $perfil->recoilHpMaximoFraccion <= 0.0)
            || ! $accion->attacker->estaVivo()
            || $accion->attacker->esInmuneARecoil()) {
            return 0.0;
        }

        $recoil = $perfil->recoilHpMaximoFraccion > 0.0
            ? $accion->attacker->pokemon()->battleStats()->hp * $perfil->recoilHpMaximoFraccion
            : $danoTotal * $perfil->recoilFraccion;

        if ($recoil <= 0.0) {
            return 0.0;
        }

        $accion->attacker->recibirDaño($recoil, false, 1.0);
        $battle?->agregarLog("{$accion->attacker->nombre()} recibe ".round($recoil).' de recoil');

        return $recoil;
    }
}
