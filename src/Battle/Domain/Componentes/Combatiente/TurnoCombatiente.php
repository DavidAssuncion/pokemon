<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ValueObjects\EstadoTurno;

/**
 * Estado de turno: ronda, acciones extra, retraso, postura y prioridad.
 */
final class TurnoCombatiente
{
    public function __construct(
        private EstadoTurno $turno = new EstadoTurno(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function desdeArray(array $data): self
    {
        $prioridad = $data['prioridadDeclarada'] ?? null;
        $turno = new EstadoTurno();
        $turno->restaurar(
            vecesActuado: (int) ($data['vecesActuadoEstaRonda'] ?? 0),
            accionesExtra: (int) ($data['accionesExtra'] ?? 0),
            extraOtorgada: (bool) ($data['extraOtorgada'] ?? false),
            turnoRetrasado: (bool) ($data['turnoRetrasado'] ?? false),
            retrasoUsado: (bool) ($data['retrasoUsado'] ?? false),
            rondaActual: (int) ($data['rondaActual'] ?? 0),
            postura: PosturaCombate::tryFrom((string) ($data['postura'] ?? PosturaCombate::ATACAR->value))
                ?? PosturaCombate::ATACAR,
            objetivoCubiertoId: (string) ($data['objetivoCubiertoId'] ?? ''),
            prioridad: $prioridad instanceof MovimientoBatalla ? $prioridad : null,
            costeVelocidad: (float) ($data['costeVelocidad'] ?? 0.0),
            costeVelocidadHastaRonda: (int) ($data['costeVelocidadHastaRonda'] ?? 0),
        );

        return new self($turno);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(): array
    {
        return [
            'vecesActuadoEstaRonda' => $this->turno->vecesActuado(),
            'accionesExtra' => $this->turno->accionesExtra(),
            'extraOtorgada' => $this->turno->extraOtorgada(),
            'costeVelocidad' => $this->turno->costeVelocidad(),
            'rondaActual' => $this->turno->rondaActual(),
            'costeVelocidadHastaRonda' => $this->turno->costeVelocidadHastaRonda(),
            'postura' => $this->turno->postura()->value,
            'objetivoCubiertoId' => $this->turno->objetivoCubiertoId(),
            'prioridadDeclarada' => $this->turno->prioridad(),
            'turnoRetrasado' => $this->turno->turnoRetrasado(),
            'retrasoUsado' => $this->turno->retrasoUsado(),
        ];
    }

    public function __clone(): void
    {
        $this->turno = clone $this->turno;
    }

    public function vecesActuado(): int
    {
        return $this->turno->vecesActuado();
    }

    public function setVecesActuado(int $vecesActuado): void
    {
        $this->turno->setVecesActuado($vecesActuado);
    }

    public function tieneAccionPendiente(): bool
    {
        return $this->turno->tieneAccionPendiente();
    }

    public function accionesExtra(): int
    {
        return $this->turno->accionesExtra();
    }

    public function setAccionesExtra(int $accionesExtra): void
    {
        $this->turno->setAccionesExtra($accionesExtra);
    }

    public function otorgarAccionExtra(): void
    {
        $this->turno->otorgarAccionExtra();
    }

    public function reiniciarAccionExtra(): void
    {
        $this->turno->reiniciarAccionExtra();
    }

    public function retrasar(): bool
    {
        return $this->turno->retrasar();
    }

    public function turnoRetrasado(): bool
    {
        return $this->turno->turnoRetrasado();
    }

    public function retrasoUsado(): bool
    {
        return $this->turno->retrasoUsado();
    }

    public function reiniciarRetraso(): void
    {
        $this->turno->reiniciarRetraso();
    }

    public function limpiarRetraso(): void
    {
        $this->turno->limpiarRetraso();
    }

    public function rondaActual(): int
    {
        return $this->turno->rondaActual();
    }

    public function setRondaActual(int $ronda): void
    {
        $this->turno->setRondaActual($ronda);
    }

    public function postura(): PosturaCombate
    {
        return $this->turno->postura();
    }

    public function setPostura(PosturaCombate $postura): void
    {
        $this->turno->setPostura($postura);
    }

    public function declararPrioridad(MovimientoBatalla $movimiento): void
    {
        $this->turno->declararPrioridad($movimiento);
    }

    public function prioridad(): ?MovimientoBatalla
    {
        return $this->turno->prioridad();
    }

    public function limpiarPrioridad(): void
    {
        $this->turno->limpiarPrioridad();
    }

    public function tienePrioridad(): bool
    {
        return $this->turno->tienePrioridad();
    }

    public function objetivoCubiertoId(): string
    {
        return $this->turno->objetivoCubiertoId();
    }

    public function setObjetivoCubiertoId(string $objetivoCubiertoId): void
    {
        $this->turno->setObjetivoCubiertoId($objetivoCubiertoId);
    }

    public function costeVelocidad(): float
    {
        return $this->turno->costeVelocidad();
    }

    public function costeVelocidadHastaRonda(): int
    {
        return $this->turno->costeVelocidadHastaRonda();
    }

    public function aplicarCosteVelocidad(float $fraccion): void
    {
        $this->turno->aplicarCosteVelocidad($fraccion);
    }

    public function reiniciarParaRevivir(): void
    {
        $this->turno->reiniciarParaRevivir();
    }

    public function reiniciar(): void
    {
        $this->turno->reiniciar();
    }
}
