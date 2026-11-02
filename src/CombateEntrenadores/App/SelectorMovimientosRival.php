<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Models\Pokemon;
use App\Support\PreferenciasShowdown;
use Src\Battle\Domain\Collections\MovimientoBatallaCollection;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Selector\PerfilMovesetRival;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\Pokemon\Domain\Stats\DatosStats;
use Src\Shared\Tipos\TiposCollection;

/**
 * Selecciona hasta 6 movimientos para un rival.
 *
 * Universo de candidatos (D30):
 * - movimientos de nivel disponibles al nivel del rival;
 * - movimientos de nivel de cualquier altura que el jugador haya desbloqueado;
 * - movimientos desbloqueados por el jugador que la especie pueda aprender por
 *   cualquier vía (MT, tutor, huevo o nivel superior).
 *
 * Los candidatos se filtran por implementación, precisión y tope de potencia,
 * se puntúan con STAB, categoría, cobertura, utilidad y precisión, y se
 * seleccionan respetando los máximos de utilidad/setup del perfil. Si el pool
 * no llena los slots, se completan con los mejores restantes; si aun así no se
 * alcanzan 4 movimientos, se recuperan los mejores descartados por cap o
 * precisión y, por último, se añaden movimientos sintéticos por tipo. Nunca se
 * devuelven más movimientos que slots ni nombres duplicados. Los sets manuales
 * tienen prioridad absoluta.
 */
final class SelectorMovimientosRival
{
    /** Movimientos excluidos por nombre (autodestrucción y OHKO localizados). */
    private const NOMBRES_EXCLUIDOS = [
        'Explosión',
        'Autodestrucción',
        'Guillotina',
        'Fisura',
        'Perforador',
        'Superdiente',
    ];

    /** Potencia a partir de la cual se comprueba el coste en HP del atacante. */
    private const POTENCIA_AUTODESTRUCTIVA = 150;

    /** Potencia base asignada a los movimientos de estado al puntuar. */
    private const BASE_ESTADO = 40;

    /** Mínimo de movimientos que debe llevar un rival si el pool lo permite. */
    private const MINIMO_MOVIMIENTOS = 4;

    public function __construct(
        private readonly MapeadorPokemonBatalla $mapeador,
        private readonly ?PreferenciasShowdown $showdown = null,
        private readonly ?GeneradorMovimientosTipo $generador = null,
    ) {
    }

    /**
     * @param  list<string>  $movimientosDesbloqueados  slugs desbloqueados por el jugador
     * @param  list<string>|null  $movimientosManuales  set curado del gimnasio
     */
    public function seleccionar(
        Pokemon $pokemon,
        int $nivel,
        PerfilMovesetRival $perfil,
        array $movimientosDesbloqueados = [],
        ?array $movimientosManuales = null,
    ): MovimientoBatallaCollection {
        if ($movimientosManuales !== null && $movimientosManuales !== []) {
            return $this->seleccionManual($movimientosManuales, $perfil);
        }

        $pokemon->loadMissing('moves');

        $tipos = $this->mapeador->tiposDe($pokemon);
        $stats = $this->mapeador->statsDe($pokemon);

        $puntuados = [];
        $descartadosPorLimite = [];
        foreach ($this->candidatos($pokemon, $nivel, $movimientosDesbloqueados) as $movimiento) {
            if (! $this->esAptoPorImplementacion($movimiento)) {
                continue;
            }

            $bonusShowdown = $this->showdown?->bonus($pokemon->name, $movimiento->nombre) ?? 0.0;

            $candidato = [
                'movimiento' => $movimiento,
                'score' => $this->puntuar($movimiento, $stats, $tipos) + $bonusShowdown,
                'stab' => $tipos->contiene($movimiento->tipo),
                'utilidad' => $movimiento->esEstado(),
                'setup' => $this->esSetup($movimiento),
            ];

            if ($this->respetaLimites($movimiento, $perfil)) {
                $puntuados[] = $candidato;

                continue;
            }

            $descartadosPorLimite[] = $candidato;
        }

        return new MovimientoBatallaCollection(
            $this->completarSeleccion(
                $this->seleccionarPuntuados($puntuados, $perfil),
                $descartadosPorLimite,
                $tipos,
                $perfil,
            ),
        );
    }

    /**
     * @param  list<string>  $movimientosManuales
     */
    private function seleccionManual(array $movimientosManuales, PerfilMovesetRival $perfil): MovimientoBatallaCollection
    {
        $movimientos = $this->mapeador->depurarMovimientos(
            $this->mapeador->movimientosDeSeleccion($movimientosManuales),
        );

        return new MovimientoBatallaCollection(
            array_slice($movimientos->all(), 0, max(0, $perfil->slots)),
        );
    }

    /**
     * Construye la unión deduplicada de candidatos.
     *
     * @param  list<string>  $movimientosDesbloqueados
     * @return list<MovimientoBatalla>
     */
    private function candidatos(Pokemon $pokemon, int $nivel, array $movimientosDesbloqueados): array
    {
        $desbloqueados = [];
        foreach ($movimientosDesbloqueados as $slug) {
            if ($slug !== '') {
                $desbloqueados[$slug] = true;
            }
        }

        $slugPorNombre = [];
        $slugsEspecie = [];
        foreach ($pokemon->moves as $move) {
            $slug = $move->slug;
            if ($slug === '') {
                continue;
            }

            $slugsEspecie[$slug] = true;
            $slugPorNombre[$move->name] = $slug;
        }

        $candidatos = [];

        foreach ($this->mapeador->movimientosDe($pokemon, $nivel) as $movimiento) {
            $candidatos[$movimiento->nombre] = $movimiento;
        }

        foreach ($this->mapeador->movimientosDe($pokemon, null) as $movimiento) {
            $slug = $slugPorNombre[$movimiento->nombre] ?? null;
            if ($slug !== null && isset($desbloqueados[$slug])) {
                $candidatos[$movimiento->nombre] = $movimiento;
            }
        }

        $slugsDesbloqueados = [];
        foreach (array_keys($desbloqueados) as $slug) {
            if (isset($slugsEspecie[$slug])) {
                $slugsDesbloqueados[] = $slug;
            }
        }

        foreach ($this->mapeador->movimientosDeSeleccion($slugsDesbloqueados) as $movimiento) {
            $candidatos[$movimiento->nombre] = $movimiento;
        }

        return $this->mapeador->depurarMovimientos(
            new MovimientoBatallaCollection(array_values($candidatos)),
        )->all();
    }

    /**
     * Filtros que no se relajan aunque el pool sea pequeño: nombres excluidos,
     * fórmulas de daño no soportadas y movimientos autodestructivos.
     */
    private function esAptoPorImplementacion(MovimientoBatalla $movimiento): bool
    {
        if (in_array($movimiento->nombre, self::NOMBRES_EXCLUIDOS, true)) {
            return false;
        }

        if ($movimiento->perfil->formulaDano !== 'normal') {
            return false;
        }

        return ! (
            $movimiento->potencia >= self::POTENCIA_AUTODESTRUCTIVA
            && $movimiento->perfil->danoPorcentajeHpAtacante >= 1.0
        );
    }

    /**
     * Límites de composición que sí se relajan si el pool no alcanza el
     * mínimo de movimientos: precisión mínima y tope de potencia.
     */
    private function respetaLimites(MovimientoBatalla $movimiento, PerfilMovesetRival $perfil): bool
    {
        return $movimiento->perfil->precision >= $perfil->precisionMin
            && $movimiento->potencia <= $perfil->cap;
    }

    private function puntuar(MovimientoBatalla $movimiento, DatosStats $stats, TiposCollection $tipos): float
    {
        $base = $movimiento->potencia > 0
            ? (float) $movimiento->potencia
            : (float) self::BASE_ESTADO;

        $esStab = $tipos->contiene($movimiento->tipo);
        $stab = $esStab ? 1.5 : 1.0;
        $categoria = $this->factorCategoria($movimiento, $stats);
        $cobertura = ! $esStab && $movimiento->potencia > 0 ? 1.1 : 1.0;
        $utilidad = $movimiento->esEstado() ? 1.2 : 1.0;

        return $base * $stab * $categoria * $cobertura * $utilidad * $movimiento->perfil->precision;
    }

    private function factorCategoria(MovimientoBatalla $movimiento, DatosStats $stats): float
    {
        if ($movimiento->esFisico() && $stats->atk >= $stats->spAtk) {
            return 1.15;
        }

        if ($movimiento->esEspecial() && $stats->spAtk >= $stats->atk) {
            return 1.15;
        }

        return 1.0;
    }

    /**
     * Setup: movimiento de estado que sube stats propias o baja stats del
     * objetivo.
     */
    private function esSetup(MovimientoBatalla $movimiento): bool
    {
        if (! $movimiento->esEstado()) {
            return false;
        }

        foreach ($movimiento->selfStatChanges as $cambio) {
            if ($cambio->factor > 1.0 || $cambio->etapas > 0) {
                return true;
            }
        }

        foreach ($movimiento->targetStatChanges as $cambio) {
            if ($cambio->factor < 1.0 || $cambio->etapas < 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{movimiento: MovimientoBatalla, score: float, stab: bool, utilidad: bool, setup: bool}>  $puntuados
     * @return list<MovimientoBatalla>
     */
    private function seleccionarPuntuados(array $puntuados, PerfilMovesetRival $perfil): array
    {
        $slots = max(0, $perfil->slots);
        if ($slots === 0 || $puntuados === []) {
            return [];
        }

        usort($puntuados, self::compararCandidatos(...));

        /** @var array<string, array{movimiento: MovimientoBatalla, score: float, stab: bool, utilidad: bool, setup: bool}> $seleccionados */
        $seleccionados = [];
        $utilidad = 0;
        $setup = 0;
        $diferidos = [];

        foreach ($puntuados as $candidato) {
            if (count($seleccionados) >= $slots) {
                break;
            }

            $nombre = $candidato['movimiento']->nombre;
            if (isset($seleccionados[$nombre])) {
                continue;
            }

            if ($candidato['utilidad'] && $utilidad >= $perfil->maxUtilidad) {
                $diferidos[] = $candidato;

                continue;
            }

            if ($candidato['setup'] && $setup >= $perfil->maxSetup) {
                $diferidos[] = $candidato;

                continue;
            }

            $seleccionados[$nombre] = $candidato;

            if ($candidato['utilidad']) {
                $utilidad++;
            }

            if ($candidato['setup']) {
                $setup++;
            }
        }

        foreach ($diferidos as $candidato) {
            if (count($seleccionados) >= $slots) {
                break;
            }

            $seleccionados[$candidato['movimiento']->nombre] ??= $candidato;
        }

        $this->asegurarStabOfensivo($seleccionados, $puntuados, $slots);

        $lista = array_values($seleccionados);
        usort($lista, self::compararCandidatos(...));

        $resultado = [];
        foreach (array_slice($lista, 0, $slots) as $candidato) {
            $resultado[] = $candidato['movimiento'];
        }

        return $resultado;
    }

    /**
     * Completa la selección hasta el mínimo (4 movimientos o los slots
     * disponibles) sin superar el número de slots ni duplicar nombres.
     *
     * Primero recupera los mejores candidatos descartados solo por cap o
     * precisión y, si aún faltan, añade movimientos sintéticos por tipo.
     *
     * @param  list<MovimientoBatalla>  $seleccionados
     * @param  list<array{movimiento: MovimientoBatalla, score: float, stab: bool, utilidad: bool, setup: bool}>  $descartados
     * @return list<MovimientoBatalla>
     */
    private function completarSeleccion(
        array $seleccionados,
        array $descartados,
        TiposCollection $tipos,
        PerfilMovesetRival $perfil,
    ): array {
        $slots = max(0, $perfil->slots);
        $objetivo = min(self::MINIMO_MOVIMIENTOS, $slots);

        if ($slots === 0 || count($seleccionados) >= $objetivo) {
            return $seleccionados;
        }

        $nombres = [];
        foreach ($seleccionados as $movimiento) {
            $nombres[$movimiento->nombre] = true;
        }

        usort($descartados, self::compararCandidatos(...));

        foreach ($descartados as $candidato) {
            if (count($seleccionados) >= $objetivo) {
                return $seleccionados;
            }

            $nombre = $candidato['movimiento']->nombre;
            if (isset($nombres[$nombre])) {
                continue;
            }

            $seleccionados[] = $candidato['movimiento'];
            $nombres[$nombre] = true;
        }

        return $this->completarConSinteticos($seleccionados, $nombres, $tipos, $objetivo);
    }

    /**
     * Rellena con movimientos sintéticos del generador de tipos, sin duplicar
     * por nombre y sin superar el objetivo.
     *
     * @param  list<MovimientoBatalla>  $seleccionados
     * @param  array<string, true>  $nombres
     * @return list<MovimientoBatalla>
     */
    private function completarConSinteticos(
        array $seleccionados,
        array $nombres,
        TiposCollection $tipos,
        int $objetivo,
    ): array {
        if (count($seleccionados) >= $objetivo) {
            return $seleccionados;
        }

        $generador = $this->generador ?? new GeneradorMovimientosTipo();

        foreach ($generador->generar($tipos) as $sintetico) {
            if (count($seleccionados) >= $objetivo) {
                break;
            }

            if (isset($nombres[$sintetico->nombre])) {
                continue;
            }

            $seleccionados[] = new MovimientoBatalla(
                nombre: $sintetico->nombre,
                potencia: $sintetico->potencia,
                tipo: $sintetico->tipo,
                categoria: $sintetico->categoria,
            );
            $nombres[$sintetico->nombre] = true;
        }

        return $seleccionados;
    }

    /**
     * Garantiza al menos un movimiento STAB ofensivo si el pool lo permite.
     *
     * @param  array<string, array{movimiento: MovimientoBatalla, score: float, stab: bool, utilidad: bool, setup: bool}>  $seleccionados
     * @param  list<array{movimiento: MovimientoBatalla, score: float, stab: bool, utilidad: bool, setup: bool}>  $puntuados
     */
    private function asegurarStabOfensivo(array &$seleccionados, array $puntuados, int $slots): void
    {
        foreach ($seleccionados as $candidato) {
            if ($this->esStabOfensivo($candidato)) {
                return;
            }
        }

        $mejorStab = null;
        foreach ($puntuados as $candidato) {
            if ($this->esStabOfensivo($candidato)) {
                $mejorStab = $candidato;

                break;
            }
        }

        if ($mejorStab === null
            || isset($seleccionados[$mejorStab['movimiento']->nombre])) {
            return;
        }

        if (count($seleccionados) >= $slots) {
            $eliminar = null;
            foreach (array_reverse(array_keys($seleccionados)) as $nombre) {
                if (! $this->esStabOfensivo($seleccionados[$nombre])) {
                    $eliminar = $nombre;

                    break;
                }
            }

            if ($eliminar !== null) {
                unset($seleccionados[$eliminar]);
            }
        }

        $seleccionados[$mejorStab['movimiento']->nombre] = $mejorStab;
    }

    /**
     * @param  array{movimiento: MovimientoBatalla, score: float, stab: bool, utilidad: bool, setup: bool}  $candidato
     */
    private function esStabOfensivo(array $candidato): bool
    {
        return $candidato['stab'] && $candidato['movimiento']->potencia > 0;
    }

    /**
     * @param  array{movimiento: MovimientoBatalla, score: float, stab: bool, utilidad: bool, setup: bool}  $a
     * @param  array{movimiento: MovimientoBatalla, score: float, stab: bool, utilidad: bool, setup: bool}  $b
     */
    private static function compararCandidatos(array $a, array $b): int
    {
        $porScore = $b['score'] <=> $a['score'];

        return $porScore !== 0
            ? $porScore
            : strcmp($a['movimiento']->nombre, $b['movimiento']->nombre);
    }
}
