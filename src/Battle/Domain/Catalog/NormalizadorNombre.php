<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Catalog;

/**
 * Normaliza nombres de habilidades y movimientos de los CSV: minúsculas, sin
 * acentos, sin paréntesis ni puntuación final y con espacios colapsados.
 */
final class NormalizadorNombre
{
    /** @var array<string, string> */
    private const ACENTOS = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];

    public static function normalizar(string $nombre): string
    {
        $texto = trim($nombre);
        $texto = str_replace("\u{00A0}", ' ', $texto);
        $texto = strtr(mb_strtolower($texto), self::ACENTOS);
        $texto = str_replace(['(', ')', '.', ',', ';', ':'], ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }
}
