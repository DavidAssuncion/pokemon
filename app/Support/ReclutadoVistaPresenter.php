<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Reclutado;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Src\Reclutamiento\App\ServicioEvolucion;
use Src\Shared\Domain\NivelHelper;

/**
 * Contrato de vista de la ficha de un reclutado.
 *
 * Conserva las claves históricas y añade los valores ya formateados que antes
 * se calculaban en `reclutado/show.blade.php`.
 */
final class ReclutadoVistaPresenter
{
    /**
     * @return array{
     *     reclutado: array<string, mixed>,
     *     siguiente: array<string, mixed>|null,
     *     requisitos: list<array<string, mixed>>,
     *     puedeEvolucionar: bool
     * }
     */
    public static function toView(Reclutado $reclutado, ?int $userId = null): array
    {
        $userId ??= (int) Auth::id();
        $reclutado->loadMissing('pokemon.types');

        $siguiente = ServicioEvolucion::siguienteEvolucion($reclutado->pokemon);
        $expTotal = $reclutado->exp->total();
        $nombre = $reclutado->nombre ?? $reclutado->pokemon?->name;
        $requisitos = [];

        foreach (ServicioEvolucion::requisitos($reclutado, $userId) as $requisito) {
            $requisitos[] = [
                'tipo' => $requisito->tipo,
                'slug' => $requisito->slug,
                'necesario' => $requisito->necesario,
                'actual' => $requisito->actual,
                'caramelosDisponibles' => $requisito->caramelosDisponibles,
                'actual_formateado' => number_format($requisito->actual),
                'necesario_formateado' => number_format($requisito->necesario),
            ];
        }

        return [
            'reclutado' => [
                'id' => $reclutado->id,
                'nombre' => $reclutado->nombre,
                'nombre_formateado' => Str::ucfirst($nombre ?? ''),
                'pokemon_id' => $reclutado->pokemon_id,
                'pokemon_nombre' => $reclutado->pokemon?->name,
                'nivel' => NivelHelper::nivelDesdeExperiencia($expTotal),
                'exp_total' => $expTotal,
                'exp_total_formateado' => number_format($expTotal),
                'imagen' => "/images/iconos/{$reclutado->pokemon_id}.png",
                'imagen_fallback' => "/images/iconos/{$reclutado->pokemon_id}.png",
            ],
            'siguiente' => $siguiente !== null
                ? [
                    'pokemon_id' => $siguiente->id,
                    'nombre' => $siguiente->name,
                    'nombre_formateado' => Str::ucfirst($siguiente->name),
                    'imagen' => "/images/iconos/{$siguiente->id}.png",
                ]
                : null,
            'requisitos' => $requisitos,
            'puedeEvolucionar' => ServicioEvolucion::puedeEvolucionar($reclutado, $userId),
        ];
    }
}
