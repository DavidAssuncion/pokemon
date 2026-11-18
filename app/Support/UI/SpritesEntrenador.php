<?php

declare(strict_types=1);

namespace App\Support\UI;

use JsonException;
use RuntimeException;

/**
 * Resuelve los sprites de entrenador del manifiesto de presentación.
 *
 * No consulta base de datos ni servicios: solo lee
 * `resources/data/entrenadores_sprites.json` y comprueba la existencia del
 * PNG en `public/images/trainers/`.
 */
final class SpritesEntrenador
{
    public const FALLBACK_URL = '/images/misc/trainer.webp';

    private const DIRECTORIO = 'images/trainers';

    private const PATRON_SLUG = '/^[a-z0-9-]+$/';

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $manifiesto = null;

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function manifiesto(): array
    {
        if (self::$manifiesto !== null) {
            return self::$manifiesto;
        }

        $ruta = resource_path('data/entrenadores_sprites.json');
        $contenido = @file_get_contents($ruta);

        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer el manifiesto de sprites: ' . $ruta);
        }

        /** @var array<string, mixed> $datos */
        $datos = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);

        self::$manifiesto = $datos;

        return self::$manifiesto;
    }

    public static function url(string $slug): string
    {
        if (preg_match(self::PATRON_SLUG, $slug) !== 1) {
            return self::FALLBACK_URL;
        }

        $relativa = self::DIRECTORIO . '/' . $slug . '.png';

        if (! is_file(public_path($relativa))) {
            return self::FALLBACK_URL;
        }

        return '/' . $relativa;
    }
}
