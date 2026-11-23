<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\App;

use App\Support\UI\SpritesEntrenador;

/**
 * Resuelve URLs de presentación de las facciones: imagen de tarjeta,
 * sprite de entrenador y enlace al detalle.
 */
final class ResolutorAssetsFaccion
{
    public const IMAGEN_FALLBACK = '/images/misc/raid.webp';

    /** @var list<string> */
    private const EXTENSIONES_IMAGEN = ['webp', 'png', 'jpg', 'jpeg'];

    public function imagenUrl(string $imagen): string
    {
        foreach (self::EXTENSIONES_IMAGEN as $extension) {
            $rutaRelativa = 'images/rivales/'.$imagen.'.'.$extension;
            $rutaAbsoluta = public_path($rutaRelativa);

            if (! is_file($rutaAbsoluta)) {
                continue;
            }

            $version = filemtime($rutaAbsoluta);
            $sufijo = $version !== false ? '?v='.$version : '';

            return '/'.$rutaRelativa.$sufijo;
        }

        return self::IMAGEN_FALLBACK;
    }

    public function spriteUrl(string $sprite): string
    {
        return SpritesEntrenador::url($sprite);
    }

    public function enlace(string $slug): string
    {
        return '/habitats/temporales/'.$slug;
    }
}
