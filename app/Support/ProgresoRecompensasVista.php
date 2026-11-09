<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Modelo de presentación raíz de la EXP ganada tras un combate.
 *
 * Es aditivo: se serializa como `rewards['progreso']` y solo existe cuando la
 * cuenta o alguna Senda han ganado EXP real.
 */
final readonly class ProgresoRecompensasVista
{
    /**
     * @param  list<ProgresoSendaVista>  $sendas  Ya ordenadas por el builder.
     */
    public function __construct(
        public ?ProgresoCuentaVista $cuenta,
        public array $sendas,
        public string $resumenAccesible,
    ) {
    }

    public function estaVacio(): bool
    {
        return $this->cuenta === null && $this->sendas === [];
    }

    /**
     * Frontera de vista (contrato snake_case del modal de recompensas).
     *
     * @return array{
     *     cuenta: array<string, mixed>|null,
     *     sendas: list<array<string, mixed>>,
     *     resumen_accesible: string
     * }
     */
    public function aArray(): array
    {
        return [
            'cuenta' => $this->cuenta?->aArray(),
            'sendas' => array_map(
                static fn (ProgresoSendaVista $senda): array => $senda->aArray(),
                $this->sendas,
            ),
            'resumen_accesible' => $this->resumenAccesible,
        ];
    }
}
