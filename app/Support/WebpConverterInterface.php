<?php

declare(strict_types=1);

namespace App\Support;

interface WebpConverterInterface
{
    /**
     * True si hay un backend de conversión disponible (GD, Imagick o CLI).
     */
    public function available(): bool;

    /**
     * Nombre del backend en uso: gd, imagick, cwebp, convert o none.
     */
    public function backend(): string;

    /**
     * Convierte un PNG a WebP preservando el canal alfa.
     */
    public function convert(string $input, string $output): bool;
}
