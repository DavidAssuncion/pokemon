<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Procesamiento;

use App\Models\Pokemon;
use Src\Exploraciones\App\CombateExploracion;
use Src\Exploraciones\Domain\ValueObjects\EstadoExplorador;
use Src\Exploraciones\Domain\ValueObjects\ResultadoBatallaExploracion;
use Src\Shared\Domain\EscaladorNivelRival;
use Src\Shared\Domain\NivelHelper;

/**
 * Ejecuta un combate de exploración y actualiza el estado del explorador.
 *
 * Tras victoria NO-emboscada regenera las barreras al 100 %; en emboscada
 * secuencial no regenera entre sub-combates. El HP nunca se cura por combate.
 */
final class EjecutorCombateExplorador
{
    public function __construct(
        private readonly CombateExploracion $combate,
        private readonly EscaladorNivelRival $escalador,
    ) {
    }

    public function combatir(
        ContextoTickExploracion $contexto,
        Pokemon $salvaje,
        bool $emboscadaSecuencial,
    ): ResultadoBatallaExploracion {
        $reclutado = $contexto->exploracion->reclutado;

        $resultado = $this->combate->combatir(
            reclutado: $reclutado,
            salvaje: $salvaje,
            nivelRival: $this->nivelRival($contexto),
            estadoInicial: $this->estadoInicial($contexto->estado),
            modificadorDanio: $contexto->capacidades->bonusDanoCombate($contexto->dificultad),
        );

        $contexto->estado = $contexto->estado->actualizarTrasCombate($resultado, $emboscadaSecuencial);

        return $resultado;
    }

    /**
     * Nivel del rival escalado: min_lvl del hábitat para el nivel de
     * exploración, escalado con el nivel del Pokémon explorador.
     */
    private function nivelRival(ContextoTickExploracion $contexto): int
    {
        $exploracion = $contexto->exploracion;
        $nivelPokemon = NivelHelper::nivelDesdeExperiencia(
            $exploracion->reclutado?->exp->total() ?? 0
        );
        $minLvl = $exploracion->habitat?->minLvlParaNivel($exploracion->nivel);

        if ($minLvl === null) {
            return $nivelPokemon;
        }

        return $this->escalador->escalar($minLvl, $nivelPokemon);
    }

    /**
     * @return array{hp: float, barrera_fisica: float, barrera_especial: float}|null
     */
    private function estadoInicial(EstadoExplorador $estadoExplorador): ?array
    {
        if ($estadoExplorador->sinCombate()) {
            return null;
        }

        return [
            'hp' => $estadoExplorador->hp,
            'barrera_fisica' => $estadoExplorador->barreraFisica,
            'barrera_especial' => $estadoExplorador->barreraEspecial,
        ];
    }
}
