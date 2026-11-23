<?php

declare(strict_types=1);

namespace Src\Avistamientos\Infra\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Avistamientos\App\ObtenerAvistamientoVigente;
use Src\Avistamientos\Domain\DataTransferObjects\AvistamientoPagina;

/**
 * API del modal de avistamientos.
 *
 * Devuelve el contrato ya resuelto (hábitat, familia, factor, niveles,
 * especies y estado «visto»). El frontend no calcula reglas ni ordena.
 */
final class AvistamientoController
{
    public function __construct(
        private readonly ObtenerAvistamientoVigente $obtenerVigente,
    ) {
    }

    public function vigente(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $pagina = ($this->obtenerVigente)($userId);

        return response()->json($this->serializar($pagina));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(AvistamientoPagina $pagina): array
    {
        if (! $pagina->activo) {
            return [
                'activo' => false,
                'sin_efecto' => false,
                'niveles' => [],
            ];
        }

        $niveles = [];
        foreach ($pagina->niveles as $nivel) {
            $especies = [];
            foreach ($nivel->especies as $especie) {
                $especies[] = [
                    'id' => $especie->pokemonId,
                    'nombre' => $especie->nombre,
                    'visto' => $especie->visto,
                    'icono' => "/images/iconos_webp/{$especie->pokemonId}.webp",
                ];
            }

            $niveles[] = [
                'nivel' => $nivel->nivel,
                'especies' => $especies,
            ];
        }

        return [
            'activo' => true,
            'indice' => $pagina->indice,
            'habitat' => $pagina->habitatId !== null
                ? [
                    'id' => $pagina->habitatId,
                    'nombre' => $pagina->habitatNombre,
                    'url' => "/habitats/{$pagina->habitatId}",
                ]
                : null,
            'familia' => $pagina->familiaNombre,
            'factor' => $pagina->factor,
            'termina_en' => $pagina->terminaEn,
            'restante' => $pagina->restanteTexto,
            'sin_efecto' => $pagina->sinEfecto,
            'niveles' => $niveles,
        ];
    }
}
