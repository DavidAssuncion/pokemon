<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\ValueObjects\EstadoRecursos;

/**
 * Recursos de combo del combatiente.
 */
final class RecursosCombatiente
{
    public function __construct(
        private EstadoRecursos $recursos = new EstadoRecursos(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function desdeArray(array $data): self
    {
        $recursos = new EstadoRecursos();
        $recursos->restaurar(
            (int) ($data['combo'] ?? 0),
            (string) ($data['comboMovimiento'] ?? ''),
        );

        return new self($recursos);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(): array
    {
        return [
            'combo' => $this->recursos->combo(),
            'comboMovimiento' => $this->recursos->comboMovimiento(),
        ];
    }

    public function __clone(): void
    {
        $this->recursos = clone $this->recursos;
    }

    public function combo(): int
    {
        return $this->recursos->combo();
    }

    public function comboMovimiento(): string
    {
        return $this->recursos->comboMovimiento();
    }

    public function comboMultiplicador(): float
    {
        return $this->recursos->comboMultiplicador();
    }

    public function comboMultiplicadorPara(string $movimiento, bool $potenciaCreciente): float
    {
        return $this->recursos->comboMultiplicadorPara($movimiento, $potenciaCreciente);
    }

    public function prepararCombo(string $movimiento, bool $potenciaCreciente): void
    {
        $this->recursos->prepararCombo($movimiento, $potenciaCreciente);
    }

    public function registrarGolpe(string $movimiento, bool $potenciaCreciente): void
    {
        if (! $potenciaCreciente) {
            $this->recursos->romperCombo();

            return;
        }

        $this->recursos->registrarGolpe($movimiento);
    }

    public function romperCombo(): void
    {
        $this->recursos->romperCombo();
    }

    public function reiniciar(): void
    {
        $this->recursos = new EstadoRecursos();
    }
}
