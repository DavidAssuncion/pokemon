<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Selector;

/**
 * Perfil que describe cómo debe componerse el moveset de un rival: cuántos
 * movimientos puede llevar, el tope de potencia permitido, los máximos de
 * utilidad/setup y la precisión mínima aceptada.
 *
 * Es un Value Object puro: no consulta `config()` ni conoce Eloquent. La
 * lectura de `config/combate.php` corresponde a `App\Support\ConfiguracionCombate`,
 * que construye el perfil con el constructor de esta clase. Los valores por
 * defecto replican la configuración aprobada para que el dominio pueda probarse
 * de forma aislada.
 *
 * Las factorías de perfil (`paraRuta`, `paraEntrenador`, `paraGimnasio`) viven
 * en el adaptador de configuración; aquí quedan las reglas puras de tier que
 * usan esas factorías.
 */
final readonly class PerfilMovesetRival
{
    public const SLOTS_DEFECTO = 6;

    public const SLOTS_MINIMO = 1;

    public const SLOTS_MAXIMO = 6;

    public const PRECISION_MIN_DEFECTO = 0.70;

    public const NIVEL_MINIMO = 1;

    public const NIVEL_MAXIMO = 3;

    public const INDICE_GIMNASIO_MINIMO = 1;

    public const INDICE_GIMNASIO_MAXIMO = 18;

    public const GIMNASIOS_POR_TIER = 6;

    /** @var array<string, array<int, int>> */
    public const CAPS_DEFECTO = [
        'ruta' => [1 => 60, 2 => 90, 3 => 110],
        'entrenador' => [1 => 70, 2 => 95, 3 => 120],
        'gimnasio' => [1 => 80, 2 => 100, 3 => 120],
        'lider' => [1 => 90, 2 => 110, 3 => 130],
    ];

    /** @var array<int, int> */
    public const MAX_UTILIDAD_DEFECTO = [1 => 1, 2 => 2, 3 => 2];

    /** @var array<int, int> */
    public const MAX_SETUP_DEFECTO = [1 => 0, 2 => 1, 3 => 1];

    public function __construct(
        public int $slots,
        public int $cap,
        public int $maxUtilidad,
        public int $maxSetup,
        public string $contexto,
        public float $precisionMin = self::PRECISION_MIN_DEFECTO,
    ) {
    }

    /**
     * Tier 1-3 a partir del nivel de ruta o entrenador.
     */
    public static function tierDeNivel(int $nivel): int
    {
        return max(self::NIVEL_MINIMO, min(self::NIVEL_MAXIMO, $nivel));
    }

    /**
     * Tier 1-3 a partir del índice de gimnasio (1-18). Se agrupa en tramos de
     * 6 gimnasios: 1-6 → 1, 7-12 → 2, 13-18 → 3.
     */
    public static function tierDeGimnasio(int $indice): int
    {
        $indice = max(
            self::INDICE_GIMNASIO_MINIMO,
            min(self::INDICE_GIMNASIO_MAXIMO, $indice),
        );

        return max(
            self::NIVEL_MINIMO,
            min(
                self::NIVEL_MAXIMO,
                intdiv($indice - 1, self::GIMNASIOS_POR_TIER) + 1,
            ),
        );
    }

    /**
     * Acota el número de slots a 1-6.
     */
    public static function normalizarSlots(int $slots): int
    {
        return max(self::SLOTS_MINIMO, min(self::SLOTS_MAXIMO, $slots));
    }
}
