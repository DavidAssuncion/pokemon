<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ReglasBatalla;
use Src\Battle\Domain\ValueObjects\MultiplicadoresStats;
use Src\Battle\Domain\ValueObjects\ResistenciasTemporales;
use Src\Pokemon\Domain\PokemonEntity;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Multiplicadores, resistencias temporales y estadísticas efectivas.
 */
final class StatsCombatiente
{
    public function __construct(
        private MultiplicadoresStats $multiplicadores,
        private ResistenciasTemporales $resistencias,
    ) {
    }

    public static function vacia(): self
    {
        return new self(MultiplicadoresStats::vacia(), ResistenciasTemporales::vacia());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function desdeArray(array $data): self
    {
        if (isset($data['multiplicadores']) && is_array($data['multiplicadores'])) {
            $multiplicadores = MultiplicadoresStats::desdeSerializado($data['multiplicadores']);
        } elseif (isset($data['etapas']) && is_array($data['etapas'])) {
            $multiplicadores = MultiplicadoresStats::desdeEtapas($data['etapas']);
        } else {
            $multiplicadores = MultiplicadoresStats::vacia();
        }

        return new self(
            multiplicadores: $multiplicadores,
            resistencias: ResistenciasTemporales::desdeArray(
                is_array($data['resistencias'] ?? null) ? $data['resistencias'] : [],
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(): array
    {
        return [
            'multiplicadores' => $this->multiplicadores->toArray(),
            'resistencias' => $this->resistencias->toArray(),
        ];
    }

    public function __clone(): void
    {
        $this->multiplicadores = clone $this->multiplicadores;
        $this->resistencias = clone $this->resistencias;
    }

    public function multiplicadores(): MultiplicadoresStats
    {
        return $this->multiplicadores;
    }

    public function setMultiplicadores(MultiplicadoresStats $multiplicadores): void
    {
        $this->multiplicadores = $multiplicadores;
    }

    public function avanzarTemporales(): void
    {
        $this->multiplicadores = $this->multiplicadores->avanzarTemporales();
        $this->resistencias = $this->resistencias->avanzar();
    }

    public function aplicarResistenciaTemporal(TipoPokemon $tipo, float $factor): void
    {
        $this->resistencias = $this->resistencias->aplicar($tipo, $factor);
    }

    public function multiplicadorResistenciaTemporal(AccionBatalla $accion): float
    {
        return $this->resistencias->factorDe($accion->move->tipo);
    }

    public function resistenciasTemporales(): ResistenciasTemporales
    {
        return $this->resistencias;
    }

    public function bonificacionesPositivas(): int
    {
        $total = 0;
        foreach ($this->multiplicadores->obtenerEtapas()->toArray() as $etapas) {
            if ($etapas > 0) {
                $total += $etapas;
            }
        }

        return $total;
    }

    public function bonificacionesPositivasSobreLimite(int $limite): int
    {
        $total = 0;
        foreach ($this->multiplicadores->obtenerEtapas()->toArray() as $etapas) {
            if ($etapas > $limite) {
                $total += $etapas - $limite;
            }
        }

        return $total;
    }

    public function aplicarBonusInicialStats(float $porcentaje): void
    {
        if ($porcentaje <= 0.0) {
            return;
        }

        foreach (StatClave::cases() as $stat) {
            $this->multiplicadores = $this->multiplicadores->aplicarPorcentaje($stat, $porcentaje);
        }
    }

    public function obtenerStatEfectivo(
        StatClave $stat,
        PokemonEntity $pokemon,
        EstadoPokemon $estado,
        float $multiplicadorEfecto,
    ): float {
        $baseStat = match ($stat) {
            StatClave::ATAQUE => $pokemon->battleStats()->attack,
            StatClave::DEFENSA => $pokemon->battleStats()->defense,
            StatClave::ATAQUE_ESPECIAL => $pokemon->battleStats()->spAtk,
            StatClave::DEFENSA_ESPECIAL => $pokemon->battleStats()->spDef,
            StatClave::VELOCIDAD => $pokemon->battleStats()->speed,
            default => 0,
        };

        $value = $baseStat * $this->multiplicadores->obtenerMultiplicador($stat);
        $value *= $multiplicadorEfecto;

        if ($stat === StatClave::VELOCIDAD && $estado === EstadoPokemon::PARALYSIS) {
            $value *= ReglasBatalla::REDUCCION_VELOCIDAD_PARALISIS;
        }

        return $value;
    }

    public function velocidadEfectiva(float $velocidad, TurnoCombatiente $turno, float $multiplicadorJefe): float
    {
        if ($turno->costeVelocidad() > 0.0
            && $turno->rondaActual() <= $turno->costeVelocidadHastaRonda()) {
            $velocidad *= max(0.0, 1.0 - $turno->costeVelocidad());
        }

        return $velocidad * $multiplicadorJefe;
    }

    public function reiniciarMultiplicadores(): void
    {
        $this->multiplicadores = MultiplicadoresStats::vacia();
    }

    public function reiniciar(): void
    {
        $this->multiplicadores = MultiplicadoresStats::vacia();
        $this->resistencias = ResistenciasTemporales::vacia();
    }
}
