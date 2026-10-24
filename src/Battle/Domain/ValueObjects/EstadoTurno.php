<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Estado de turno/acción de un combatiente: ronda, acción pendiente, extras,
 * retraso, postura, prioridad declarada, objetivo cubierto y coste de velocidad.
 */
final class EstadoTurno
{
    private int $vecesActuado = 0;

    private int $accionesExtra = 0;

    private bool $extraOtorgada = false;

    private bool $turnoRetrasado = false;

    private bool $retrasoUsado = false;

    private int $rondaActual = 0;

    private PosturaCombate $postura = PosturaCombate::ATACAR;

    private string $objetivoCubiertoId = '';

    private ?MovimientoBatalla $prioridad = null;

    private float $costeVelocidad = 0.0;

    private int $costeVelocidadHastaRonda = 0;

    public function tieneAccionPendiente(): bool
    {
        return $this->vecesActuado === 0 || $this->accionesExtra > 0;
    }

    public function vecesActuado(): int
    {
        return $this->vecesActuado;
    }

    public function setVecesActuado(int $vecesActuado): void
    {
        $this->vecesActuado = max(0, $vecesActuado);
    }

    public function accionesExtra(): int
    {
        return $this->accionesExtra;
    }

    public function extraOtorgada(): bool
    {
        return $this->extraOtorgada;
    }

    public function setAccionesExtra(int $accionesExtra): void
    {
        $this->accionesExtra = max(0, min(1, $accionesExtra));
    }

    public function otorgarAccionExtra(): void
    {
        if ($this->extraOtorgada) {
            return;
        }

        $this->accionesExtra = 1;
        $this->extraOtorgada = true;
    }

    public function reiniciarAccionExtra(): void
    {
        $this->accionesExtra = 0;
        $this->extraOtorgada = false;
    }

    /**
     * Deja al combatiente listo para actuar de nuevo tras un revivir. Conserva
     * la ronda actual, pero limpia acciones consumidas, prioridad, postura y
     * penalizaciones de velocidad de la vida anterior.
     */
    public function reiniciarParaRevivir(): void
    {
        $this->vecesActuado = 0;
        $this->accionesExtra = 0;
        $this->extraOtorgada = false;
        $this->turnoRetrasado = false;
        $this->retrasoUsado = false;
        $this->postura = PosturaCombate::ATACAR;
        $this->objetivoCubiertoId = '';
        $this->prioridad = null;
        $this->costeVelocidad = 0.0;
        $this->costeVelocidadHastaRonda = 0;
    }

    public function retrasar(): bool
    {
        if ($this->retrasoUsado) {
            return false;
        }

        $this->turnoRetrasado = true;
        $this->retrasoUsado = true;

        return true;
    }

    public function turnoRetrasado(): bool
    {
        return $this->turnoRetrasado;
    }

    public function retrasoUsado(): bool
    {
        return $this->retrasoUsado;
    }

    public function reiniciarRetraso(): void
    {
        $this->turnoRetrasado = false;
        $this->retrasoUsado = false;
    }

    public function limpiarRetraso(): void
    {
        $this->turnoRetrasado = false;
    }

    public function rondaActual(): int
    {
        return $this->rondaActual;
    }

    public function setRondaActual(int $ronda): void
    {
        $this->rondaActual = max(0, $ronda);
    }

    public function postura(): PosturaCombate
    {
        return $this->postura;
    }

    public function setPostura(PosturaCombate $postura): void
    {
        $this->postura = $postura;
    }

    public function objetivoCubiertoId(): string
    {
        return $this->objetivoCubiertoId;
    }

    public function setObjetivoCubiertoId(string $objetivoCubiertoId): void
    {
        $this->objetivoCubiertoId = $objetivoCubiertoId;
    }

    public function prioridad(): ?MovimientoBatalla
    {
        return $this->prioridad;
    }

    public function declararPrioridad(MovimientoBatalla $movimiento): void
    {
        $this->prioridad = $movimiento->priority > 0 ? $movimiento : null;
    }

    public function limpiarPrioridad(): void
    {
        $this->prioridad = null;
    }

    public function tienePrioridad(): bool
    {
        return $this->prioridad !== null;
    }

    public function costeVelocidad(): float
    {
        return $this->costeVelocidad;
    }

    public function costeVelocidadHastaRonda(): int
    {
        return $this->costeVelocidadHastaRonda;
    }

    public function aplicarCosteVelocidad(float $fraccion): void
    {
        if ($fraccion <= 0.0) {
            return;
        }

        $this->costeVelocidad = min(1.0, max($this->costeVelocidad, $fraccion));
        $this->costeVelocidadHastaRonda = $this->rondaActual + 1;
    }

    public function restaurar(
        int $vecesActuado,
        int $accionesExtra,
        bool $extraOtorgada,
        bool $turnoRetrasado,
        bool $retrasoUsado,
        int $rondaActual,
        PosturaCombate $postura,
        string $objetivoCubiertoId,
        ?MovimientoBatalla $prioridad,
        float $costeVelocidad,
        int $costeVelocidadHastaRonda,
    ): void {
        $this->vecesActuado = max(0, $vecesActuado);
        $this->accionesExtra = max(0, min(1, $accionesExtra));
        $this->extraOtorgada = $extraOtorgada;
        $this->turnoRetrasado = $turnoRetrasado;
        $this->retrasoUsado = $retrasoUsado;
        $this->rondaActual = max(0, $rondaActual);
        $this->postura = $postura;
        $this->objetivoCubiertoId = $objetivoCubiertoId;
        $this->prioridad = $prioridad;
        $this->costeVelocidad = max(0.0, min(1.0, $costeVelocidad));
        $this->costeVelocidadHastaRonda = max(0, $costeVelocidadHastaRonda);
    }

    public function reiniciar(): void
    {
        $this->vecesActuado = 0;
        $this->accionesExtra = 0;
        $this->extraOtorgada = false;
        $this->turnoRetrasado = false;
        $this->retrasoUsado = false;
        $this->rondaActual = 0;
        $this->postura = PosturaCombate::ATACAR;
        $this->objetivoCubiertoId = '';
        $this->prioridad = null;
        $this->costeVelocidad = 0.0;
        $this->costeVelocidadHastaRonda = 0;
    }
}
