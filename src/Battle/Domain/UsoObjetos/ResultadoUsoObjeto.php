<?php

declare(strict_types=1);

namespace Src\Battle\Domain\UsoObjetos;

use Src\Shared\Collections\StringCollection;

/**
 * Resultado de un intento de usar un objeto en combate.
 *
 * Un fallo no consume inventario ni acción; `motivo` explica la causa.
 */
final readonly class ResultadoUsoObjeto
{
    public function __construct(
        public bool $exito,
        public ?string $motivo,
        public string $clave,
        public string $actorNombre,
        public string $objetivoNombre,
        public string $log,
        public int $curado = 0,
        public bool $revivido = false,
    ) {
    }

    public static function fallo(string $clave, string $motivo): self
    {
        return new self(
            exito: false,
            motivo: $motivo,
            clave: $clave,
            actorNombre: '',
            objetivoNombre: '',
            log: '',
        );
    }

    /**
     * Líneas de log derivadas del resultado. Un fallo no genera log.
     */
    public function logLineas(): StringCollection
    {
        if (! $this->exito || $this->log === '') {
            return new StringCollection();
        }

        return new StringCollection([$this->log]);
    }
}
