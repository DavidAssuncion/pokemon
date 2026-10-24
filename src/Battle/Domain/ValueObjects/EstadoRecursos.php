<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\ReglasBatalla;

/**
 * Recursos de combate del portador: racha de combo.
 */
final class EstadoRecursos
{
    private int $combo = 0;

    /** Movimiento que mantiene la racha actual ('' si no hay combo). */
    private string $comboMovimiento = '';

    public function combo(): int
    {
        return $this->combo;
    }

    public function comboMovimiento(): string
    {
        return $this->comboMovimiento;
    }

    /**
     * Prepara la racha antes de resolver un movimiento. Un movimiento que no
     * sea encadenable, o distinto del actual, rompe la racha.
     */
    public function prepararCombo(string $movimiento, bool $encadenable): void
    {
        if (! $encadenable || $this->comboMovimiento !== $movimiento) {
            $this->combo = 0;
            $this->comboMovimiento = $encadenable ? $movimiento : '';
        }
    }

    public function comboMultiplicador(): float
    {
        $golpes = min(ReglasBatalla::COMBO_MAXIMO, max(0, $this->combo));

        return 1.0 + ($golpes * ReglasBatalla::COMBO_INCREMENTO);
    }

    public function comboMultiplicadorPara(string $movimiento, bool $encadenable): float
    {
        if (! $encadenable || $this->comboMovimiento !== $movimiento) {
            return 1.0;
        }

        return $this->comboMultiplicador();
    }

    public function registrarGolpe(string $movimiento): void
    {
        if ($this->comboMovimiento !== $movimiento) {
            $this->comboMovimiento = $movimiento;
            $this->combo = 0;
        }

        $this->combo = min(ReglasBatalla::COMBO_MAXIMO, $this->combo + 1);
    }

    public function romperCombo(): void
    {
        $this->combo = 0;
        $this->comboMovimiento = '';
    }

    public function reiniciar(): void
    {
        $this->romperCombo();
    }

    public function restaurar(int $combo, string $comboMovimiento = ''): void
    {
        $this->combo = max(0, min(ReglasBatalla::COMBO_MAXIMO, $combo));
        $this->comboMovimiento = $combo > 0 ? $comboMovimiento : '';
    }
}
