<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Shared\Tipos\TipoPokemon;

/**
 * Resistencias temporales por tipo de ataque.
 *
 * Guarda un factor por tipo y una duración compartida de cinco turnos.
 * Reaplicar un tipo refresca su factor y la duración sin acumular.
 */
final class ResistenciasTemporales
{
    public const int TURNOS = 5;

    /** @var array<int, float> */
    private array $factores;

    private int $turnos;

    /**
     * @param  array<int, float>  $factores
     */
    private function __construct(array $factores, int $turnos)
    {
        $this->factores = $factores;
        $this->turnos = max(0, $turnos);
    }

    public static function vacia(): self
    {
        return new self([], 0);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArray(array $datos): self
    {
        $factoresRaw = $datos['factores'] ?? [];

        if (! is_array($factoresRaw) || $factoresRaw === []) {
            return self::vacia();
        }

        $factores = [];
        foreach ($factoresRaw as $clave => $factor) {
            $tipo = TipoPokemon::tryFrom((int) $clave);
            if ($tipo === null) {
                continue;
            }

            $factores[$tipo->value] = (float) $factor;
        }

        if ($factores === []) {
            return self::vacia();
        }

        return new self(
            $factores,
            max(1, (int) ($datos['turnos'] ?? self::TURNOS)),
        );
    }

    /**
     * Aplica/refresca la resistencia al tipo y reinicia la duración compartida.
     */
    public function aplicar(TipoPokemon $tipo, float $factor): self
    {
        $factores = $this->factores;
        $factores[$tipo->value] = $factor;

        return new self($factores, self::TURNOS);
    }

    public function factorDe(TipoPokemon $tipo): float
    {
        return $this->factores[$tipo->value] ?? 1.0;
    }

    /**
     * Descuenta un turno. Al llegar a cero se limpian todos los factores.
     */
    public function avanzar(): self
    {
        if ($this->turnos <= 0) {
            return $this;
        }

        $turnos = $this->turnos - 1;

        return $turnos > 0
            ? new self($this->factores, $turnos)
            : self::vacia();
    }

    public function reiniciar(): self
    {
        return self::vacia();
    }

    /**
     * @return array{factores: array<int, float>, turnos: int}
     */
    public function toArray(): array
    {
        return [
            'factores' => $this->factores,
            'turnos' => $this->turnos,
        ];
    }

    public function turnos(): int
    {
        return $this->turnos;
    }

    public function tieneResistencias(): bool
    {
        return $this->factores !== [] && $this->turnos > 0;
    }
}
