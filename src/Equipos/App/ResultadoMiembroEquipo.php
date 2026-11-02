<?php

declare(strict_types=1);

namespace Src\Equipos\App;

use App\Models\TeamMember;

/**
 * Resultado de AgregarMiembroEquipo: el miembro creado y el id del miembro
 * reemplazado, o un mensaje de rechazo de negocio.
 */
final readonly class ResultadoMiembroEquipo
{
    private function __construct(
        public ?TeamMember $miembro,
        public ?int $miembroReemplazadoId,
        public ?string $error,
    ) {
    }

    public static function creado(TeamMember $miembro, ?int $reemplazadoId): self
    {
        return new self($miembro, $reemplazadoId, null);
    }

    public static function rechazado(string $mensaje): self
    {
        return new self(null, null, $mensaje);
    }

    public function esRechazado(): bool
    {
        return $this->error !== null;
    }
}
