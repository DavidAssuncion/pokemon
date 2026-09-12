<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\ValueObjects;

/**
 * Value Object inmutable con el estado del explorador DURANTE la expedición
 * (persistido en eventos['explorador'] entre ticks): HP y barreras actuales
 * con sus máximos. Replica el array del pipeline de ProcesarExploracionHandler.
 */
final readonly class EstadoExplorador
{
    private const UMBRAL_DESCANSO_HP = 50;

    public function __construct(
        public readonly float $hp,
        public readonly float $hpMax,
        public readonly float $barreraFisica,
        public readonly float $barreraFisicaMax,
        public readonly float $barreraEspecial,
        public readonly float $barreraEspecialMax,
    ) {
    }

    /**
     * Estado vacío (primer tick → combate al 100 %).
     */
    public static function vacio(): self
    {
        return new self(hp: 0.0, hpMax: 0.0, barreraFisica: 0.0, barreraFisicaMax: 0.0, barreraEspecial: 0.0, barreraEspecialMax: 0.0);
    }

    /**
     * Frontera — lectura del estado persistido (eventos['explorador']).
     *
     * @param  array<string, mixed>  $estado
     */
    public static function desdeArray(array $estado): self
    {
        return new self(
            hp: (float) ($estado['hp'] ?? 0),
            hpMax: (float) ($estado['hp_max'] ?? 0),
            barreraFisica: (float) ($estado['barrera_fisica'] ?? 0),
            barreraFisicaMax: (float) ($estado['barrera_fisica_max'] ?? 0),
            barreraEspecial: (float) ($estado['barrera_especial'] ?? 0),
            barreraEspecialMax: (float) ($estado['barrera_especial_max'] ?? 0),
        );
    }

    /**
     * Frontera — shape exacto del estado persistido (claves snake_case en el
     * orden del pipeline actual).
     *
     * @return array{hp: float, hp_max: float, barrera_fisica: float, barrera_fisica_max: float, barrera_especial: float, barrera_especial_max: float}
     */
    public function aArray(): array
    {
        return [
            'hp' => $this->hp,
            'hp_max' => $this->hpMax,
            'barrera_fisica' => $this->barreraFisica,
            'barrera_fisica_max' => $this->barreraFisicaMax,
            'barrera_especial' => $this->barreraEspecial,
            'barrera_especial_max' => $this->barreraEspecialMax,
        ];
    }

    /**
     * ¿El explorador aún no ha combatido (no hay máximos conocidos)?
     */
    public function sinCombate(): bool
    {
        return $this->hpMax <= 0;
    }

    /**
     * Porcentaje de HP actual respecto al máximo (0 si no hay máximo).
     */
    public function pctHp(): float
    {
        if ($this->hpMax <= 0) {
            return 0.0;
        }

        return ($this->hp / $this->hpMax) * 100;
    }

    /**
     * Nueva instancia con el HP indicado.
     */
    public function conHp(float $hp): self
    {
        return new self($hp, $this->hpMax, $this->barreraFisica, $this->barreraFisicaMax, $this->barreraEspecial, $this->barreraEspecialMax);
    }

    /**
     * Nueva instancia con las barreras indicadas (máximos intactos).
     */
    public function conBarreras(float $barreraFisica, float $barreraEspecial): self
    {
        return new self($this->hp, $this->hpMax, $barreraFisica, $this->barreraFisicaMax, $barreraEspecial, $this->barreraEspecialMax);
    }

    /**
     * Nueva instancia con los máximos indicados (actuales intactos).
     */
    public function conMaximos(float $hpMax, float $barreraFisicaMax, float $barreraEspecialMax): self
    {
        return new self($this->hp, $hpMax, $this->barreraFisica, $barreraFisicaMax, $this->barreraEspecial, $barreraEspecialMax);
    }

    /**
     * Tras un combate: HP final del resultado, máximos del resultado y barreras
     * finales. En victoria NO-emboscada las barreras se regeneran al 100 %
     * (el HP nunca se cura por combate). Replica exacta de
     * ProcesarExploracionHandler::combatirEvento.
     */
    public function actualizarTrasCombate(ResultadoBatallaExploracion $resultado, bool $emboscadaSecuencial): self
    {
        $nuevo = new self(
            hp: $resultado->hpFinal,
            hpMax: $resultado->hpMax,
            barreraFisica: $resultado->barreraFisicaFinal,
            barreraFisicaMax: $resultado->barreraFisicaMax,
            barreraEspecial: $resultado->barreraEspecialFinal,
            barreraEspecialMax: $resultado->barreraEspecialMax,
        );

        if ($resultado->victoria && ! $emboscadaSecuencial) {
            return $nuevo->conBarreras($resultado->barreraFisicaMax, $resultado->barreraEspecialMax);
        }

        return $nuevo;
    }

    /**
     * ¿El explorador está por debajo del umbral de descanso (HP < 50 %)?
     */
    public function requiereDescanso(): bool
    {
        return ! $this->sinCombate() && $this->pctHp() < self::UMBRAL_DESCANSO_HP;
    }
}
