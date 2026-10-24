<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Condiciones declarativas que puede exigir un modificador de daño.
 */
enum CondicionDanio: string
{
    case SIEMPRE = 'siempre';
    case VIDA_BAJA = 'vida_baja';
    case VIDA_COMPLETA = 'vida_completa';
    case OBJETIVO_CON_ESTADO = 'objetivo_con_estado';
    case TIPO_MOVIMIENTO = 'tipo_movimiento';
    case TIPO_MOVIMIENTO_VIDA_BAJA = 'tipo_movimiento_vida_baja';
    case MOVIMIENTO_FISICO = 'movimiento_fisico';
    case MOVIMIENTO_ESPECIAL = 'movimiento_especial';
    case FLAG_MOVIMIENTO = 'flag_movimiento';
    case CLIMA = 'clima';
    case MOVIMIENTO_SUPEREFICAZ = 'movimiento_supereficaz';
    case MOVIMIENTO_CON_SECUNDARIO = 'movimiento_con_secundario';
    case MOVIMIENTO_POTENCIA_BAJA = 'movimiento_potencia_baja';
    case MOVIMIENTO_POCO_EFICAZ = 'movimiento_poco_eficaz';
    case MOVIMIENTO_CON_RECOIL = 'movimiento_con_recoil';
}
