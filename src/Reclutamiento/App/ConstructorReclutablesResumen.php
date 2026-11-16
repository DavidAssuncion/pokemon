<?php

declare(strict_types=1);

namespace Src\Reclutamiento\App;

use App\Models\Reclutable;
use Src\Reclutamiento\Domain\Collections\ReclutablesResumenCollection;
use Src\Reclutamiento\Domain\DataTransferObjects\ReclutableResumen;

/**
 * Construye la colección compacta de reclutables del usuario, ordenada por
 * `updated_at` descendente (los últimos reclutables primero).
 *
 * El icono llega resuelto al cliente: el frontend no concatena rutas.
 */
final class ConstructorReclutablesResumen
{
    private const ICONO_BASE = '/images/iconos_webp/';

    public function todos(int $userId): ReclutablesResumenCollection
    {
        $reclutables = Reclutable::query()
            ->where('user_id', $userId)
            ->with('pokemon')
            ->latest('updated_at')
            ->get();

        $resumenes = [];
        foreach ($reclutables as $reclutable) {
            $pokemonId = $reclutable->pokemon_id;
            $pokemon = $reclutable->pokemon;
            $nombre = $pokemon?->name;
            if ($nombre === null) {
                $nombre = 'Desconocido';
            }
            $resumenes[] = new ReclutableResumen(
                id: $reclutable->id,
                pokemonId: $pokemonId,
                nombre: $nombre,
                icono: self::ICONO_BASE.$pokemonId.'.webp',
                cantidad: $reclutable->cantidad,
            );
        }

        return new ReclutablesResumenCollection($resumenes);
    }
}
