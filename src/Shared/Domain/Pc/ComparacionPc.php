<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Pc;

/**
 * Resultado de comparar el PC del equipo del jugador con el del rival.
 *
 * Contrato de frontera (HTTP/Blade): `toArray()` devuelve el shape acordado en
 * el plan, con los totales agregados y sin composición del rival.
 */
final readonly class ComparacionPc
{
    public function __construct(
        public EstadoComparacionPc $estado,
        public int $miembrosEquipo,
        public ?int $pcEquipo,
        public bool $equipoCompleto,
        public ?int $pcRival,
        public bool $rivalAproximado,
        public ?MetodoPcRival $metodoRival,
        public ?float $ratio,
        public ?VeredictoPc $veredicto,
    ) {
    }

    public static function equipoIncompleto(
        int $miembros,
        MetodoPcRival $metodo,
        bool $aproximado,
    ): self {
        return new self(
            estado: EstadoComparacionPc::EQUIPO_INCOMPLETO,
            miembrosEquipo: $miembros,
            pcEquipo: null,
            equipoCompleto: false,
            pcRival: null,
            rivalAproximado: $aproximado,
            metodoRival: $metodo,
            ratio: null,
            veredicto: null,
        );
    }

    public static function sinRival(
        int $pcEquipo,
        int $miembros,
        MetodoPcRival $metodo,
        bool $aproximado,
    ): self {
        return new self(
            estado: EstadoComparacionPc::SIN_RIVALES,
            miembrosEquipo: $miembros,
            pcEquipo: $pcEquipo,
            equipoCompleto: true,
            pcRival: null,
            rivalAproximado: $aproximado,
            metodoRival: $metodo,
            ratio: null,
            veredicto: null,
        );
    }

    public static function noDisponible(MetodoPcRival $metodo): self
    {
        return new self(
            estado: EstadoComparacionPc::NO_DISPONIBLE,
            miembrosEquipo: 0,
            pcEquipo: null,
            equipoCompleto: false,
            pcRival: null,
            rivalAproximado: false,
            metodoRival: $metodo,
            ratio: null,
            veredicto: null,
        );
    }

    public static function ok(
        int $miembros,
        int $pcEquipo,
        int $pcRival,
        MetodoPcRival $metodo,
        bool $aproximado,
        ?float $ratio,
        ?VeredictoPc $veredicto,
    ): self {
        return new self(
            estado: EstadoComparacionPc::OK,
            miembrosEquipo: $miembros,
            pcEquipo: $pcEquipo,
            equipoCompleto: true,
            pcRival: $pcRival,
            rivalAproximado: $aproximado,
            metodoRival: $metodo,
            ratio: $ratio,
            veredicto: $veredicto,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'estado' => $this->estado->value,
            'equipo' => [
                'miembros' => $this->miembrosEquipo,
                'completo' => $this->equipoCompleto,
                'pc' => $this->pcEquipo,
            ],
            'rival' => [
                'pc' => $this->pcRival,
                'aproximado' => $this->rivalAproximado,
                'metodo' => $this->metodoRival?->value,
            ],
            'comparacion' => [
                'ratio' => $this->ratio,
                'veredicto' => $this->veredicto?->value,
            ],
        ];
    }
}
