<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Piezas;

use Src\Forja\Domain\Piezas\Enums\TipoPieza;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\Items\ClaveBonguri;
use Src\Shared\Domain\Items\ClaveMaterial;
use Src\Shared\Domain\Items\ClavePieza;
use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Reglas puras de las piezas de forja: desbloqueo por nido, curva de stats,
 * composición de ingredientes y textos de motivo. Sin Eloquent, HTTP ni
 * facades.
 */
final class ReglasPiezas
{
    public const MAX_NIDO = 5;

    public const SLOTS = 3;

    public const BONO_AMULETO = 0.5;

    public const MOTIVO_NIDO_INSUFICIENTE = 'nido_insuficiente';

    public const MOTIVO_PIEZA_YA_POSEIDA = 'pieza_ya_poseida';

    public const MOTIVO_PIEZA_ANTERIOR_EQUIPADA = 'pieza_anterior_equipada';

    public const MOTIVO_REQUIERE_PIEZA_ANTERIOR = 'requiere_pieza_anterior';

    public const MOTIVO_FALTAN_MATERIALES = 'faltan_materiales';

    /**
     * Factor de stats por etapa. La etapa 4 o superior usa el tope de la 3.
     *
     * @var array<int, float>
     */
    private const FACTOR_ETAPA = [
        1 => 0.5,
        2 => 0.75,
        3 => 1.0,
    ];

    /**
     * Nivel de nido necesario para desbloquear una etapa según la profundidad
     * de la familia: 3 (sin evolución), 2×etapa (2 etapas) y
     * min(5, 2×etapa−1) (3 etapas o más).
     */
    public static function nivelNidoPara(int $profundidad, int $etapa): int
    {
        return match (true) {
            $profundidad <= 1 => 3,
            $profundidad === 2 => min(self::MAX_NIDO, 2 * $etapa),
            default => min(self::MAX_NIDO, 2 * $etapa - 1),
        };
    }

    public static function factorEtapa(int $etapa): float
    {
        return self::FACTOR_ETAPA[max(1, min(3, $etapa))];
    }

    public static function prioridadStatMaximo(): StringCollection
    {
        return new StringCollection(['atk', 'def', 'spAtk', 'spDef']);
    }

    /**
     * Ingredientes de una pieza: bonguri del hábitat ×(1+etapa), recipiente ×1,
     * baya rara ×etapa y pieza anterior ×1 desde la etapa 2.
     */
    public static function ingredientes(
        TipoPieza $tipo,
        int $etapa,
        ClaveBonguri $bonguri,
        ?ClavePieza $piezaAnterior,
    ): MapaConteo {
        $ingredientes = MapaConteo::vacio()
            ->con($bonguri->clave(), 1 + $etapa)
            ->con(ClaveMaterial::RECIPIENTE->clave(), 1)
            ->con($tipo->bayaRara()->clave(), $etapa);

        if ($piezaAnterior !== null) {
            $ingredientes = $ingredientes->con($piezaAnterior->clave(), 1);
        }

        return $ingredientes;
    }

    public static function textoMotivo(
        string $motivo,
        int $nivelRequerido = 0,
        ?string $piezaAnteriorNombre = null,
    ): string {
        return match ($motivo) {
            self::MOTIVO_NIDO_INSUFICIENTE => 'Requiere nido nivel '.$nivelRequerido,
            self::MOTIVO_PIEZA_YA_POSEIDA => 'Ya tienes esta pieza',
            self::MOTIVO_PIEZA_ANTERIOR_EQUIPADA => 'Desequipa la pieza anterior para mejorarla',
            self::MOTIVO_REQUIERE_PIEZA_ANTERIOR => $piezaAnteriorNombre !== null
                ? 'Requiere '.$piezaAnteriorNombre
                : 'Requiere la pieza anterior',
            self::MOTIVO_FALTAN_MATERIALES => 'Faltan materiales',
            default => $motivo,
        };
    }
}
