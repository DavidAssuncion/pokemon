<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Procesamiento;

/**
 * Resultado de procesar un tick de exploración.
 *
 * Indica si la expedición debe cerrarse, el motivo ('derrota'|'retirada'|'')
 * y si el orquestador debe persistir la exploración en este punto. El gestor
 * no guarda: solo comunica el timing al handler.
 */
final readonly class ResultadoTick
{
    public function __construct(
        public bool $terminada,
        public string $motivo,
        public bool $debePersistir,
    ) {
    }
}
