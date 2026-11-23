<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\BarrerasVidaManager;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;
use Src\Pokemon\Domain\PokemonEntity;

/**
 * Vida y barreras del combatiente.
 */
final class VitalidadCombatiente
{
    public function __construct(
        private float $hpActual,
        private float $defensaHpActual,
        private float $defensaEspHpActual,
        private BarrerasVidaManager $barrerasVidaManager = new BarrerasVidaManager(),
    ) {
    }

    public static function desdePokemon(PokemonEntity $pokemon): self
    {
        return new self(
            hpActual: $pokemon->battleStats()->hp,
            defensaHpActual: $pokemon->battleStats()->defenseHp,
            defensaEspHpActual: $pokemon->battleStats()->spDefenseHp,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function desdeArray(array $data): self
    {
        return new self(
            hpActual: (float) ($data['hpActual'] ?? 0),
            defensaHpActual: (float) ($data['defensaHpActual'] ?? 0),
            defensaEspHpActual: (float) ($data['defensaEspHpActual'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(): array
    {
        return [
            'hpActual' => $this->hpActual,
            'defensaHpActual' => $this->defensaHpActual,
            'defensaEspHpActual' => $this->defensaEspHpActual,
        ];
    }

    public function __clone(): void
    {
        $this->barrerasVidaManager = new BarrerasVidaManager();
    }

    public function hpActual(): float
    {
        return $this->hpActual;
    }

    public function defensaHpActual(): float
    {
        return $this->defensaHpActual;
    }

    public function defensaEspHpActual(): float
    {
        return $this->defensaEspHpActual;
    }

    public function setHpActual(float $hpActual): void
    {
        $this->hpActual = max(0.0, $hpActual);
    }

    public function setDefensaHpActual(float $defensaHpActual): void
    {
        $this->defensaHpActual = $defensaHpActual;
    }

    public function setDefensaEspHpActual(float $defensaEspHpActual): void
    {
        $this->defensaEspHpActual = $defensaEspHpActual;
    }

    public function restarHp(float $cantidad): void
    {
        $this->hpActual -= $cantidad;
    }

    public function estaVivo(): bool
    {
        return $this->barrerasVidaManager->estaVivo($this->hpActual);
    }

    public function recibirDano(
        float $dano,
        bool $isSpecial,
        float $directPct = 0.0,
        float $multiplicadorEscudo = 1.0,
        float $multiplicadorSalud = 1.0,
        bool $divideDolor = false,
    ): ResultadoDanio {
        if ($divideDolor && $dano > 0.0) {
            return $this->recibirDanoDividido(
                $dano,
                $directPct,
                $multiplicadorEscudo,
                $multiplicadorSalud,
            );
        }

        $barreraActual = $isSpecial ? $this->defensaEspHpActual : $this->defensaHpActual;
        $resultado = $this->barrerasVidaManager->desglosarDaño(
            $dano,
            $barreraActual,
            $directPct,
            $multiplicadorEscudo,
            $multiplicadorSalud,
        );

        $this->hpActual = $this->hpActual - $resultado->danoHp;

        // Evita que un residuo de coma flotante deje al Pokémon "vivo" con 0 PS
        // mostrados y siga recibiendo clima/estado en la misma ronda.
        if ($this->hpActual < 0.0001) {
            $this->hpActual = 0.0;
        }

        if ($isSpecial) {
            $this->defensaEspHpActual = max(0.0, $this->defensaEspHpActual - $resultado->danoEscudo);
        } else {
            $this->defensaHpActual = max(0.0, $this->defensaHpActual - $resultado->danoEscudo);
        }

        return $resultado;
    }

    private function recibirDanoDividido(
        float $dano,
        float $directPct,
        float $multiplicadorEscudo,
        float $multiplicadorSalud,
    ): ResultadoDanio {
        $mitad = $dano / 2.0;
        $fisico = $this->barrerasVidaManager->desglosarDaño(
            $mitad,
            $this->defensaHpActual,
            $directPct,
            $multiplicadorEscudo,
            $multiplicadorSalud,
        );
        $especial = $this->barrerasVidaManager->desglosarDaño(
            $mitad,
            $this->defensaEspHpActual,
            $directPct,
            $multiplicadorEscudo,
            $multiplicadorSalud,
        );

        $this->hpActual = $this->hpActual - $fisico->danoHp - $especial->danoHp;
        if ($this->hpActual < 0.0001) {
            $this->hpActual = 0.0;
        }
        $this->defensaHpActual = max(0.0, $this->defensaHpActual - $fisico->danoEscudo);
        $this->defensaEspHpActual = max(0.0, $this->defensaEspHpActual - $especial->danoEscudo);

        return new ResultadoDanio(
            danoHp: $fisico->danoHp + $especial->danoHp,
            danoEscudo: $fisico->danoEscudo + $especial->danoEscudo,
            excedente: $fisico->excedente + $especial->excedente,
            rompioEscudo: $fisico->rompioEscudo || $especial->rompioEscudo,
        );
    }

    public function curarHp(float $porcentaje, float $hpMax): void
    {
        $this->hpActual = $this->barrerasVidaManager->curarHp($this->hpActual, $hpMax, $porcentaje);
    }

    public function curarHpCantidad(float $cantidad, float $hpMax): float
    {
        if ($cantidad <= 0.0 || ! $this->estaVivo()) {
            return 0.0;
        }

        $curado = min($cantidad, max(0.0, $hpMax - $this->hpActual));
        $this->hpActual += $curado;

        return $curado;
    }

    public function curarConExcedente(
        float $cantidad,
        float $hpMax,
        float $defensaHpMax,
        float $defensaEspHpMax,
        bool $excedenteAEscudoEspecial = true,
    ): float {
        if ($cantidad <= 0.0 || ! $this->estaVivo()) {
            return 0.0;
        }

        $curadoHp = min($cantidad, max(0.0, $hpMax - $this->hpActual));
        $this->hpActual += $curadoHp;
        $restante = $cantidad - $curadoHp;

        if ($restante <= 0.0) {
            return $curadoHp;
        }

        if ($excedenteAEscudoEspecial) {
            $curadoEsp = min($restante, max(0.0, $defensaEspHpMax - $this->defensaEspHpActual));
            $this->defensaEspHpActual += $curadoEsp;
            $restante -= $curadoEsp;
            $curadoFis = min($restante, max(0.0, $defensaHpMax - $this->defensaHpActual));
            $this->defensaHpActual += $curadoFis;
        } else {
            $curadoFis = min($restante, max(0.0, $defensaHpMax - $this->defensaHpActual));
            $this->defensaHpActual += $curadoFis;
            $restante -= $curadoFis;
            $curadoEsp = min($restante, max(0.0, $defensaEspHpMax - $this->defensaEspHpActual));
            $this->defensaEspHpActual += $curadoEsp;
        }

        return $curadoHp;
    }

    public function daniarBarreras(float $cantidad, bool $especial = true): float
    {
        if ($cantidad <= 0.0) {
            return 0.0;
        }

        if ($especial) {
            $destruido = min($cantidad, $this->defensaEspHpActual);
            $this->defensaEspHpActual -= $destruido;

            return $destruido;
        }

        $destruido = min($cantidad, $this->defensaHpActual);
        $this->defensaHpActual -= $destruido;

        return $destruido;
    }

    public function curarBarreras(
        float $porcentaje,
        float $defensaHpMax,
        float $defensaEspHpMax,
    ): void {
        $resultado = $this->barrerasVidaManager->curarBarreras(
            $this->defensaHpActual,
            $this->defensaEspHpActual,
            $defensaHpMax,
            $defensaEspHpMax,
            $porcentaje,
        );

        $this->defensaHpActual = $resultado['defensaHpNuevo'];
        $this->defensaEspHpActual = $resultado['defensaEspHpNuevo'];
    }

    public function prepararInvocacion(float $fraccion, float $hpMax): void
    {
        $this->hpActual = max(1.0, $hpMax * max(0.01, min(1.0, $fraccion)));
        $this->defensaHpActual = 0.0;
        $this->defensaEspHpActual = 0.0;
    }

    public function revivir(float $fraccion, float $hpMax): void
    {
        $this->hpActual = max(1.0, $hpMax * max(0.01, min(1.0, $fraccion)));
        $this->defensaHpActual = 0.0;
        $this->defensaEspHpActual = 0.0;
    }
}
