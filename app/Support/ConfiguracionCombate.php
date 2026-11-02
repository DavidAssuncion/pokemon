<?php

declare(strict_types=1);

namespace App\Support;

use Src\Battle\Domain\Selector\PerfilMovesetRival;

/**
 * Adaptador de configuración del selector de movimientos rivales.
 *
 * Lee el bloque `combate.rival` de `config/combate.php`, lo normaliza y
 * construye los perfiles de dominio. Así `PerfilMovesetRival` y el selector no
 * dependen de `config()` ni del framework: el dominio queda como Value Object
 * puro y este adaptador concentra los valores ajustables.
 */
final class ConfiguracionCombate
{
    /** Utilidad mínima del líder: la tabla del plan le da 2 en todos los tramos. */
    private const MAX_UTILIDAD_LIDER = 2;

    public function paraRuta(int $nivel, int $slot): PerfilMovesetRival
    {
        $tier = PerfilMovesetRival::tierDeNivel($nivel);

        return $this->perfil('ruta', $tier, "ruta:tier-{$tier}");
    }

    public function paraEntrenador(int $nivel, int $slot): PerfilMovesetRival
    {
        $tier = PerfilMovesetRival::tierDeNivel($nivel);

        return $this->perfil('entrenador', $tier, "entrenador:tier-{$tier}");
    }

    public function paraGimnasio(int $indice, bool $esLider, int $slot): PerfilMovesetRival
    {
        $indice = max(
            PerfilMovesetRival::INDICE_GIMNASIO_MINIMO,
            min(PerfilMovesetRival::INDICE_GIMNASIO_MAXIMO, $indice),
        );
        $tier = PerfilMovesetRival::tierDeGimnasio($indice);
        $clave = $esLider ? 'lider' : 'gimnasio';
        $contexto = $esLider ? "lider:{$indice}" : "gimnasio:{$indice}";

        return $this->perfil($clave, $tier, $contexto);
    }

    private function perfil(string $clave, int $tier, string $contexto): PerfilMovesetRival
    {
        $config = $this->rival();

        $slots = $config['slots'] ?? PerfilMovesetRival::SLOTS_DEFECTO;
        $slots = is_numeric($slots)
            ? PerfilMovesetRival::normalizarSlots((int) $slots)
            : PerfilMovesetRival::SLOTS_DEFECTO;

        $caps = $this->tablaCaps($config, $clave);
        $maxUtilidad = $this->tablaPorTier($config, 'max_utilidad', PerfilMovesetRival::MAX_UTILIDAD_DEFECTO);
        $maxSetup = $this->tablaPorTier($config, 'max_setup', PerfilMovesetRival::MAX_SETUP_DEFECTO);
        $precisionMin = $this->precisionMinima($config['precision_min'] ?? null);

        $utilidadPermitida = max(0, $maxUtilidad[$tier] ?? 0);
        if ($clave === 'lider') {
            $utilidadPermitida = max(self::MAX_UTILIDAD_LIDER, $utilidadPermitida);
        }

        return new PerfilMovesetRival(
            slots: $slots,
            cap: max(0, $caps[$tier] ?? PerfilMovesetRival::CAPS_DEFECTO[$clave][$tier] ?? 0),
            maxUtilidad: $utilidadPermitida,
            maxSetup: max(0, $maxSetup[$tier] ?? 0),
            contexto: $contexto,
            precisionMin: $precisionMin,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rival(): array
    {
        $config = config('combate.rival');

        if (! is_array($config)) {
            return [];
        }

        $normalizada = [];
        foreach ($config as $clave => $valor) {
            $normalizada[(string) $clave] = $valor;
        }

        return $normalizada;
    }

    /**
     * Tabla de topes de potencia del contenido, completada con los valores por
     * defecto para los tiers ausentes.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, int>
     */
    private function tablaCaps(array $config, string $clave): array
    {
        $defecto = PerfilMovesetRival::CAPS_DEFECTO[$clave] ?? [];
        $caps = $config['caps'] ?? null;

        if (! is_array($caps)) {
            return $defecto;
        }

        $tabla = $caps[$clave] ?? null;
        if (! is_array($tabla)) {
            return $defecto;
        }

        $resultado = $defecto;
        foreach ($tabla as $tier => $valor) {
            if (is_numeric($tier) && is_numeric($valor)) {
                $resultado[(int) $tier] = (int) $valor;
            }
        }

        return $resultado;
    }

    /**
     * Tabla plana por tier (max_utilidad / max_setup), completada con los
     * valores por defecto.
     *
     * @param  array<string, mixed>  $config
     * @param  array<int, int>  $defecto
     * @return array<int, int>
     */
    private function tablaPorTier(array $config, string $clave, array $defecto): array
    {
        $valor = $config[$clave] ?? null;

        if (! is_array($valor)) {
            return $defecto;
        }

        $resultado = $defecto;
        foreach ($valor as $tier => $entero) {
            if (is_numeric($tier) && is_numeric($entero)) {
                $resultado[(int) $tier] = (int) $entero;
            }
        }

        return $resultado;
    }

    private function precisionMinima(mixed $valor): float
    {
        if (! is_numeric($valor)) {
            return PerfilMovesetRival::PRECISION_MIN_DEFECTO;
        }

        return max(0.0, min(1.0, (float) $valor));
    }
}
