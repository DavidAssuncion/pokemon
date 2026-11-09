<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

/**
 * Memoria de daño recibido y aviso único de debilitamiento.
 */
final class RegistroDanio
{
    public function __construct(
        private float $ultimoDanoRecibido = 0.0,
        private string $ultimoAtacanteId = '',
        private float $danoRecibidoEstaRonda = 0.0,
        private int $golpesRecibidos = 0,
        private int $ultimaRondaProteccion = 0,
        private bool $debilidadNotificada = false,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function desdeArray(array $data): self
    {
        return new self(
            ultimoDanoRecibido: (float) ($data['ultimoDanoRecibido'] ?? 0.0),
            ultimoAtacanteId: (string) ($data['ultimoAtacanteId'] ?? ''),
            danoRecibidoEstaRonda: (float) ($data['danoRecibidoEstaRonda'] ?? 0.0),
            golpesRecibidos: max(0, (int) ($data['golpesRecibidos'] ?? 0)),
            ultimaRondaProteccion: max(0, (int) ($data['ultimaRondaProteccion'] ?? 0)),
            debilidadNotificada: (bool) ($data['debilidadNotificada'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(): array
    {
        return [
            'ultimoDanoRecibido' => $this->ultimoDanoRecibido,
            'ultimoAtacanteId' => $this->ultimoAtacanteId,
            'danoRecibidoEstaRonda' => $this->danoRecibidoEstaRonda,
            'golpesRecibidos' => $this->golpesRecibidos,
            'ultimaRondaProteccion' => $this->ultimaRondaProteccion,
            'debilidadNotificada' => $this->debilidadNotificada,
        ];
    }

    public function ultimoDanoRecibido(): float
    {
        return $this->ultimoDanoRecibido;
    }

    public function ultimoAtacanteId(): string
    {
        return $this->ultimoAtacanteId;
    }

    public function danoRecibidoEstaRonda(): float
    {
        return $this->danoRecibidoEstaRonda;
    }

    public function golpesRecibidos(): int
    {
        return $this->golpesRecibidos;
    }

    public function ultimaRondaProteccion(): int
    {
        return $this->ultimaRondaProteccion;
    }

    public function puedeUsarProteccion(int $ronda): bool
    {
        return ! ($this->ultimaRondaProteccion > 0
            && $this->ultimaRondaProteccion >= $ronda - 1);
    }

    public function setUltimaRondaProteccion(int $ronda): void
    {
        $this->ultimaRondaProteccion = max(0, $ronda);
    }

    public function registrarDanoRecibido(float $dano, string $atacanteId): void
    {
        if ($dano > 0.0) {
            $this->ultimoDanoRecibido = $dano;
            $this->ultimoAtacanteId = $atacanteId;
            $this->danoRecibidoEstaRonda += $dano;
            $this->golpesRecibidos++;
        }
    }

    public function reiniciarDanioRonda(): void
    {
        $this->danoRecibidoEstaRonda = 0.0;
    }

    public function reiniciarParaInvocacion(): void
    {
        $this->ultimoDanoRecibido = 0.0;
        $this->ultimoAtacanteId = '';
        $this->debilidadNotificada = false;
    }

    public function debilidadNotificada(): bool
    {
        return $this->debilidadNotificada;
    }

    public function marcarDebilidadNotificada(bool $notificada): void
    {
        $this->debilidadNotificada = $notificada;
    }
}
