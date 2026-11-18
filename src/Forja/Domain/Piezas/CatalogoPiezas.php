<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Piezas;

use Src\Forja\Domain\Piezas\Collections\DefinicionesPiezaCollection;
use Src\Forja\Domain\Piezas\Collections\NidosConstruidosCollection;
use Src\Forja\Domain\Piezas\Collections\RecetasPiezaCollection;
use Src\Forja\Domain\Piezas\DataTransferObjects\DefinicionPieza;
use Src\Forja\Domain\Piezas\DataTransferObjects\NidoConstruido;
use Src\Forja\Domain\Piezas\DataTransferObjects\PiezaDesbloqueada;
use Src\Forja\Domain\Piezas\DataTransferObjects\RecetaPieza;
use Src\Forja\Domain\Piezas\Enums\TipoPieza;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Catálogo generativo de recetas de pieza.
 *
 * Combina las definiciones de las familias con nido con cada nido del jugador:
 * una receta por (pieza, nido) y el mismo resultado para todos los hábitats de
 * la misma familia. Sin Eloquent, HTTP ni facades.
 */
final class CatalogoPiezas
{
    public const PREFIJO_RECETA = 'receta:pieza:';

    public static function esRecetaDePieza(string $clave): bool
    {
        return str_starts_with($clave, self::PREFIJO_RECETA);
    }

    public static function claveReceta(TipoPieza $tipo, int $speciesId, int $habitatId): string
    {
        return self::PREFIJO_RECETA."{$tipo->value}:{$speciesId}:h{$habitatId}";
    }

    public static function recetas(
        DefinicionesPiezaCollection $definiciones,
        NidosConstruidosCollection $nidos,
    ): RecetasPiezaCollection {
        /** @var array<int, list<DefinicionPieza>> $porCadena */
        $porCadena = [];
        /** @var array<string, DefinicionPieza> $porClave */
        $porClave = [];

        foreach ($definiciones as $definicion) {
            $porCadena[$definicion->evolutionChainId][] = $definicion;
            $porClave[$definicion->clave()] = $definicion;
        }

        $recetas = new RecetasPiezaCollection();

        foreach ($nidos as $nido) {
            foreach ($porCadena[$nido->evolutionChainId] ?? [] as $definicion) {
                $anterior = self::definicionAnterior($definicion, $porClave);

                // Una etapa ≥ 2 sin padre resuelto se omite: emitirla sin pieza
                // anterior permitiría saltarse la progresión de la familia.
                if ($definicion->etapa >= 2 && $anterior === null) {
                    continue;
                }

                $recetas->add(new RecetaPieza(
                    clave: self::claveReceta($definicion->tipo, $definicion->speciesId, $nido->habitatId),
                    definicion: $definicion,
                    nido: $nido,
                    piezaAnterior: $anterior?->clavePieza(),
                    piezaAnteriorNombre: $anterior?->nombre(),
                    ingredientes: ReglasPiezas::ingredientes(
                        $definicion->tipo,
                        $definicion->etapa,
                        $nido->bonguri,
                        $anterior?->clavePieza(),
                    ),
                    etiqueta: self::etiqueta($nido),
                ));
            }
        }

        return $recetas;
    }

    /**
     * Stock (`saldos`) determina la pieza anterior y los materiales; las piezas
     * equipadas marcan el resultado como ya poseído y la pieza anterior como no
     * mejorable hasta desequiparla (no son stock consumible).
     */
    public static function evaluar(
        RecetaPieza $receta,
        MapaConteo $saldos,
        ?StringCollection $clavesEquipadas = null,
    ): PiezaDesbloqueada {
        $desbloqueada = $receta->nido->nivelNido >= $receta->definicion->nivelNidoRequerido;

        if (! $desbloqueada) {
            return new PiezaDesbloqueada(
                receta: $receta,
                desbloqueada: false,
                puedeFabricar: false,
                motivo: ReglasPiezas::MOTIVO_NIDO_INSUFICIENTE,
            );
        }

        if ($saldos->obtener($receta->resultado()->clave()) >= 1
            || $clavesEquipadas?->contiene($receta->resultado()->clave()) === true) {
            return new PiezaDesbloqueada(
                receta: $receta,
                desbloqueada: true,
                puedeFabricar: false,
                motivo: ReglasPiezas::MOTIVO_PIEZA_YA_POSEIDA,
            );
        }

        if ($receta->piezaAnterior !== null
            && $clavesEquipadas?->contiene($receta->piezaAnterior->clave()) === true) {
            return new PiezaDesbloqueada(
                receta: $receta,
                desbloqueada: true,
                puedeFabricar: false,
                motivo: ReglasPiezas::MOTIVO_PIEZA_ANTERIOR_EQUIPADA,
            );
        }

        if ($receta->piezaAnterior !== null && $saldos->obtener($receta->piezaAnterior->clave()) < 1) {
            return new PiezaDesbloqueada(
                receta: $receta,
                desbloqueada: true,
                puedeFabricar: false,
                motivo: ReglasPiezas::MOTIVO_REQUIERE_PIEZA_ANTERIOR,
            );
        }

        foreach ($receta->ingredientes->toArray() as $clave => $requerido) {
            if ($saldos->obtener($clave) < $requerido) {
                return new PiezaDesbloqueada(
                    receta: $receta,
                    desbloqueada: true,
                    puedeFabricar: false,
                    motivo: ReglasPiezas::MOTIVO_FALTAN_MATERIALES,
                );
            }
        }

        return new PiezaDesbloqueada(
            receta: $receta,
            desbloqueada: true,
            puedeFabricar: true,
            motivo: null,
        );
    }

    /**
     * @param  array<string, DefinicionPieza>  $porClave
     */
    private static function definicionAnterior(DefinicionPieza $definicion, array $porClave): ?DefinicionPieza
    {
        if ($definicion->etapa < 2 || $definicion->parentSpeciesId === null) {
            return null;
        }

        return $porClave[$definicion->tipo->clave($definicion->parentSpeciesId)] ?? null;
    }

    private static function etiqueta(NidoConstruido $nido): string
    {
        return 'Nido de '.$nido->habitatNombre.' · bonguri '.mb_strtolower($nido->bonguri->nombre());
    }
}
