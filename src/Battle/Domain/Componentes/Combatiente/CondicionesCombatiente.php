<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\Collections\CondicionesVolatilesCollection;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Battle\Domain\ValueObjects\ResultadoSustituto;
use Src\Shared\Tipos\TipoPokemon;
use Src\Shared\Tipos\TiposCollection;

/**
 * Condiciones volátiles: silencio, interrupción, aturdimiento, tipo y sustituto.
 */
final class CondicionesCombatiente
{
    public function __construct(
        private CondicionesVolatilesCollection $condiciones = new CondicionesVolatilesCollection(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function desdeArray(array $data): self
    {
        return new self(SerializadorCombatiente::deserializarCondiciones($data['condiciones'] ?? null));
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(): array
    {
        return ['condiciones' => SerializadorCombatiente::serializarCondiciones($this->condiciones)];
    }

    public function __clone(): void
    {
        $this->condiciones = clone $this->condiciones;
    }

    public function coleccion(): CondicionesVolatilesCollection
    {
        return $this->condiciones;
    }

    public function tiene(ClaveCondicion $clave): bool
    {
        return $this->condiciones->tiene($clave);
    }

    public function buscar(ClaveCondicion $clave): ?CondicionVolatil
    {
        return $this->condiciones->buscar($clave);
    }

    public function agregar(CondicionVolatil $condicion): void
    {
        if (! $this->condiciones->tiene($condicion->clave)) {
            $this->condiciones->add($condicion);
        }
    }

    public function reemplazar(CondicionVolatil $condicion): void
    {
        $this->condiciones->quitar($condicion->clave);
        $this->condiciones->add($condicion);
    }

    public function quitar(ClaveCondicion $clave): void
    {
        $this->condiciones->quitar($clave);
    }

    public function aplicarSilencio(int $turnos = 2): void
    {
        $turnos = max(1, $turnos);
        $existente = $this->buscar(ClaveCondicion::SILENCIO);
        if ($existente !== null) {
            $existente->turnos = max($existente->turnos, $turnos);

            return;
        }

        $this->condiciones->add(new CondicionVolatil(ClaveCondicion::SILENCIO, $turnos));
    }

    public function aplicarInterrupcion(
        TurnoCombatiente $turno,
        MovimientosCombatiente $movimientos,
        RecursosCombatiente $recursos,
    ): void {
        $turno->limpiarPrioridad();
        $movimientos->cancelarCargas();
        $recursos->romperCombo();

        if (! $this->tiene(ClaveCondicion::INTERRUPCION)) {
            $this->condiciones->add(new CondicionVolatil(ClaveCondicion::INTERRUPCION, 1));
        }
    }

    public function aturdir(TurnoCombatiente $turno, RecursosCombatiente $recursos): void
    {
        $turno->setPostura(PosturaCombate::ATACAR);
        $turno->setObjetivoCubiertoId('');
        $turno->limpiarPrioridad();
        $recursos->romperCombo();

        if (! $this->tiene(ClaveCondicion::ATURDIDO)) {
            $this->condiciones->add(new CondicionVolatil(ClaveCondicion::ATURDIDO, 1));
        }
    }

    public function aplicarTipoOverride(TipoPokemon $tipo, int $turnos = 0): void
    {
        $this->reemplazar(new CondicionVolatil(
            clave: ClaveCondicion::TIPO_ALTERADO,
            turnos: max(0, $turnos),
            tipoOverride: $tipo,
        ));
    }

    public function tiposDefensivos(TiposCollection $base): TiposCollection
    {
        $alterado = $this->buscar(ClaveCondicion::TIPO_ALTERADO);
        if ($alterado?->tipoOverride !== null) {
            return new TiposCollection([$alterado->tipoOverride]);
        }

        return $base;
    }

    public function efectividadContra(TipoPokemon $tipo, TiposCollection $base): float
    {
        $multiplicador = 1.0;
        foreach ($this->tiposDefensivos($base) as $defensor) {
            $multiplicador *= $tipo->effectivenessAgainst($defensor);
        }

        return $multiplicador;
    }

    public function aplicarSustituto(
        float $fraccion,
        int $turnos,
        bool $pagarCoste,
        float $hpMax,
        VitalidadCombatiente $vitalidad,
    ): float {
        $hpSustituto = max(1.0, $hpMax * max(0.01, min(1.0, $fraccion)));

        if ($pagarCoste) {
            if ($vitalidad->hpActual() <= $hpSustituto) {
                return 0.0;
            }

            $vitalidad->restarHp($hpSustituto);
        }

        $this->reemplazar(new CondicionVolatil(
            clave: ClaveCondicion::SUSTITUTO,
            turnos: max(0, $turnos),
            hpSustituto: $hpSustituto,
            hpMaxSustituto: $hpSustituto,
        ));

        return $hpSustituto;
    }

    public function absorberConSustituto(float $dano): ResultadoSustituto
    {
        $sustituto = $this->buscar(ClaveCondicion::SUSTITUTO);
        if ($sustituto === null || $sustituto->hpSustituto <= 0.0 || $dano <= 0.0) {
            return new ResultadoSustituto(restante: $dano, absorbido: 0.0, roto: false);
        }

        $absorbido = min($dano, $sustituto->hpSustituto);
        $sustituto->hpSustituto -= $absorbido;
        $roto = $sustituto->hpSustituto <= 0.0;

        if ($roto) {
            $this->quitar(ClaveCondicion::SUSTITUTO);
        }

        return new ResultadoSustituto(
            restante: max(0.0, $dano - $absorbido),
            absorbido: $absorbido,
            roto: $roto,
        );
    }

    public function reiniciar(): void
    {
        $this->condiciones = new CondicionesVolatilesCollection();
    }
}
