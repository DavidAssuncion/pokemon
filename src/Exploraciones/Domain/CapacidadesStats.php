<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use App\Enums\StatEnum;
use App\Models\Reclutado;
use App\Models\User;
use Src\Shared\Domain\NivelHelper;

/**
 * Capacidades de un pokémon reclutado para exploración, calculadas a partir
 * de sus stats base y niveles (dominio puro).
 *
 * @todo Excepción temporal a la regla de dependencias: el factory estático
 *       desdeReclutado() recibe App\Models\Reclutado y App\Models\User
 *       (deuda WIP). Ticket v2: extraer a un repositorio/factory de dominio.
 */
final class CapacidadesStats
{
    public function __construct(
        public readonly int $hp,
        public readonly int $atk,
        public readonly int $def,
        public readonly int $spAtk,
        public readonly int $spDef,
        public readonly int $speed,
        public readonly int $nivelPokemon,
        public readonly int $nivelEntrenador,
    ) {
    }

    /**
     * Capacidad de combate (atk + spAtk + def + spDef + niveles).
     */
    public function combate(): float
    {
        return 0.25 * $this->atk
            + 0.25 * $this->spAtk
            + 0.25 * $this->def
            + 0.25 * $this->spDef
            + $this->nivelPokemon
            + $this->nivelEntrenador;
    }

    /**
     * Capacidad de detección (speed + spDef + niveles).
     */
    public function deteccion(): float
    {
        return 0.60 * $this->speed
            + 0.40 * $this->spDef
            + $this->nivelPokemon
            + $this->nivelEntrenador;
    }

    /**
     * Capacidad de recolección (spDef + speed + hp + def + niveles).
     */
    public function recoleccion(): float
    {
        return 0.25 * $this->spDef
            + 0.25 * $this->speed
            + 0.25 * $this->hp
            + 0.25 * $this->def
            + $this->nivelPokemon
            + $this->nivelEntrenador;
    }

    /**
     * Capacidad de supervivencia (hp + def + spDef + niveles).
     */
    public function supervivencia(): float
    {
        return 0.33 * $this->hp
            + 0.33 * $this->def
            + 0.33 * $this->spDef
            + $this->nivelPokemon
            + $this->nivelEntrenador;
    }

    /**
     * Capacidad de exploración (speed + spDef + def + supervivencia + niveles).
     */
    public function exploracion(): float
    {
        return 0.40 * $this->speed
            + 0.20 * $this->spDef
            + 0.20 * $this->def
            + 0.20 * $this->supervivencia()
            + $this->nivelPokemon
            + $this->nivelEntrenador;
    }

    /**
     * Capacidad de movilidad (speed + niveles).
     */
    public function movilidad(): float
    {
        return 1.00 * $this->speed
            + $this->nivelPokemon
            + $this->nivelEntrenador;
    }

    /**
     * @return array{combate: float, deteccion: float, recoleccion: float, supervivencia: float, exploracion: float, movilidad: float}
     */
    public function todas(): array
    {
        return [
            'combate' => $this->combate(),
            'deteccion' => $this->deteccion(),
            'recoleccion' => $this->recoleccion(),
            'supervivencia' => $this->supervivencia(),
            'exploracion' => $this->exploracion(),
            'movilidad' => $this->movilidad(),
        ];
    }

    /**
     * Factory desde modelos Eloquent: calcula nivel y stats del reclutado.
     * Los stats base se obtienen del pokémon asociado (misma lógica que
     * MapeadorPokemonBatalla::statsDe()). Los faltantes se rellenan con 0.
     */
    public static function desdeReclutado(Reclutado $reclutado, User $user): self
    {
        $nivelPokemon = NivelHelper::nivelDesdeExperiencia($reclutado->exp->total());
        $nivelEntrenador = $user->nivel();

        $stats = ['hp' => 0, 'atk' => 0, 'def' => 0, 'spAtk' => 0, 'spDef' => 0, 'speed' => 0];
        foreach ($reclutado->pokemon->stats as $stat) {
            $clave = match ($stat->stat) {
                StatEnum::HP => 'hp',
                StatEnum::ATTACK => 'atk',
                StatEnum::DEFENSE => 'def',
                StatEnum::SPECIAL_ATTACK => 'spAtk',
                StatEnum::SPECIAL_DEFENSE => 'spDef',
                StatEnum::SPEED => 'speed',
            };
            $stats[$clave] = (int) $stat->base_stat;
        }

        return new self(
            hp: $stats['hp'],
            atk: $stats['atk'],
            def: $stats['def'],
            spAtk: $stats['spAtk'],
            spDef: $stats['spDef'],
            speed: $stats['speed'],
            nivelPokemon: $nivelPokemon,
            nivelEntrenador: $nivelEntrenador,
        );
    }
}
