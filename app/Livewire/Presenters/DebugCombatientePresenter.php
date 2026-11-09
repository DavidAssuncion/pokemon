<?php

declare(strict_types=1);

namespace App\Livewire\Presenters;

use Src\Battle\Domain\Catalog\CatalogoObjetos;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\InterfazEfecto;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Presentador del payload de depuración de un combatiente rival.
 *
 * Construye el detalle que el tooltip de debug necesita (tipos, habilidad,
 * objeto, stats y movimientos) a partir únicamente del dominio. No usa
 * Eloquent, Livewire ni la vista; el gate de debug vive en el sincronizador.
 */
final class DebugCombatientePresenter
{
    /**
     * @return array{
     *     tipos: list<string>,
     *     habilidad: array{base: ?string, activas: list<string>},
     *     objeto: array{clave: string, nombre: string, cargas: int, activo: bool, bloqueado: bool}|null,
     *     stats: list<array{clave: string, label: string, actual: int, max: int}>,
     *     movimientos: list<array{
     *         nombre: string,
     *         tipo: string,
     *         categoria: string,
     *         potencia: int,
     *         precision: int,
     *         prioridad: int,
     *         usosMax: ?int,
     *         usos: ?int,
     *         disponible: bool,
     *         motivo: ?string
     *     }>
     * }
     */
    public static function para(Combatiente $combatiente): array
    {
        $battleStats = $combatiente->pokemon()->battleStats();
        $item = $combatiente->item();

        return [
            'tipos' => $combatiente->tiposDefensivos()->nombres(),
            'habilidad' => [
                'base' => $combatiente->pokemon()->habilidad(),
                'activas' => self::efectosActivos($combatiente),
            ],
            'objeto' => $item === ''
                ? null
                : [
                    'clave' => $item,
                    'nombre' => CatalogoObjetos::nombreDe($item),
                    'cargas' => $combatiente->itemCargas(),
                    'activo' => $combatiente->itemActivo(),
                    'bloqueado' => $combatiente->itemBloqueado(),
                ],
            'stats' => [
                self::stat('hp', 'PS', (int) round($combatiente->hpActual()), (int) round($battleStats->hp)),
                self::stat('attack', 'Ataque', (int) round($combatiente->obtenerStatEfectivo(StatClave::ATAQUE)), (int) round($battleStats->attack)),
                self::stat('defense', 'Defensa', (int) round($combatiente->obtenerStatEfectivo(StatClave::DEFENSA)), (int) round($battleStats->defense)),
                self::stat('spAtk', 'At. Esp.', (int) round($combatiente->obtenerStatEfectivo(StatClave::ATAQUE_ESPECIAL)), (int) round($battleStats->spAtk)),
                self::stat('spDef', 'Def. Esp.', (int) round($combatiente->obtenerStatEfectivo(StatClave::DEFENSA_ESPECIAL)), (int) round($battleStats->spDef)),
                self::stat('speed', 'Velocidad', (int) round($combatiente->obtenerStatEfectivo(StatClave::VELOCIDAD)), (int) round($battleStats->speed)),
            ],
            'movimientos' => array_map(
                static fn (MovimientoBatalla $movimiento): array => self::movimiento($combatiente, $movimiento),
                $combatiente->pokemon()->moves()->all(),
            ),
        ];
    }

    /**
     * @return array{clave: string, label: string, actual: int, max: int}
     */
    private static function stat(string $clave, string $label, int $actual, int $max): array
    {
        return [
            'clave' => $clave,
            'label' => $label,
            'actual' => $actual,
            'max' => $max,
        ];
    }

    /**
     * Claves de los efectos activos de habilidad, sin repetir y sin los
     * efectos aportados por el objeto equipado (que ya se muestran aparte).
     *
     * @return list<string>
     */
    private static function efectosActivos(Combatiente $combatiente): array
    {
        $clavesObjeto = CatalogoObjetos::clavesDeEfecto();

        $claves = array_map(
            static fn (InterfazEfecto $efecto): string => $efecto->obtenerClave(),
            $combatiente->effects()->all(),
        );

        $claves = array_filter(
            $claves,
            static fn (string $clave): bool => ! $clavesObjeto->contiene($clave),
        );

        return array_values(array_unique($claves));
    }

    /**
     * @return array{
     *     nombre: string,
     *     tipo: string,
     *     categoria: string,
     *     potencia: int,
     *     precision: int,
     *     prioridad: int,
     *     usosMax: ?int,
     *     usos: ?int,
     *     disponible: bool,
     *     motivo: ?string
     * }
     */
    private static function movimiento(Combatiente $combatiente, MovimientoBatalla $movimiento): array
    {
        $usosMax = $movimiento->perfil->usosMaximos > 0 ? $movimiento->perfil->usosMaximos : null;

        return [
            'nombre' => $movimiento->nombre,
            'tipo' => $movimiento->tipo->label(),
            'categoria' => self::categoria($movimiento->categoria),
            'potencia' => $movimiento->potencia,
            'precision' => (int) round($movimiento->perfil->precision * 100),
            'prioridad' => $movimiento->priority,
            'usosMax' => $usosMax,
            'usos' => $usosMax === null ? null : max(0, $combatiente->usosRestantes($movimiento)),
            'disponible' => $combatiente->movimientoDisponible($movimiento),
            'motivo' => $combatiente->motivoMovimientoNoDisponible($movimiento),
        ];
    }

    private static function categoria(CategoriaMovimiento $categoria): string
    {
        return match ($categoria) {
            CategoriaMovimiento::FISICO => 'Físico',
            CategoriaMovimiento::ESPECIAL => 'Especial',
            CategoriaMovimiento::ESTADO => 'Estado',
        };
    }
}
