<?php

declare(strict_types=1);

namespace Src\Exploraciones\Presentation;

/**
 * Hitos de una expedición terminada, derivados exclusivamente del resumen
 * `eventos['resultado']`. No inventa datos: sin resultado devuelve [].
 */
final class HitoExploracion
{
    /**
     * @param  array<string, mixed>  $resultado
     * @return list<array{codigo: string, etiqueta: string, descripcion: string}>
     */
    public static function desde(array $resultado): array
    {
        if ($resultado === []) {
            return [];
        }

        $incidentes = is_array($resultado['incidentes'] ?? null) ? $resultado['incidentes'] : [];
        $emboscadas = (int) ($incidentes['emboscadas'] ?? 0);
        $victorias = (int) ($incidentes['victorias'] ?? 0);
        $contratiempos = (int) ($incidentes['contratiempos'] ?? 0);
        $durationReal = (int) ($resultado['duration_real'] ?? 0);

        $hitos = [];

        if ($emboscadas > 0 && $victorias > 0) {
            $hitos[] = [
                'codigo' => 'emboscada_superada',
                'etiqueta' => 'Emboscada superada',
                'descripcion' => 'Sobrevivió a una emboscada y respondió con fuerza.',
            ];
        }

        if ($victorias >= 5) {
            $hitos[] = [
                'codigo' => 'veterano',
                'etiqueta' => 'Veterano',
                'descripcion' => 'Acumuló cinco o más victorias en la expedición.',
            ];
        }

        if (self::hayCaramelos($resultado)) {
            $hitos[] = [
                'codigo' => 'recolector',
                'etiqueta' => 'Recolector',
                'descripcion' => 'Encontró caramelos durante la expedición.',
            ];
        }

        if (self::hayActividad($incidentes) && $contratiempos === 0) {
            $hitos[] = [
                'codigo' => 'sin_contratiempos',
                'etiqueta' => 'Sin contratiempos',
                'descripcion' => 'La expedición transcurrió sin contratiempos.',
            ];
        }

        if ($durationReal > 0 && $durationReal <= 60) {
            $hitos[] = [
                'codigo' => 'expedicion_express',
                'etiqueta' => 'Expedición exprés',
                'descripcion' => 'Regresó en menos de una hora.',
            ];
        }

        return $hitos;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private static function hayCaramelos(array $resultado): bool
    {
        foreach (['caramelos_familia', 'caramelos_ev', 'caramelos_tipo'] as $clave) {
            $caramelos = $resultado[$clave] ?? null;
            if (! is_iterable($caramelos)) {
                continue;
            }

            foreach ($caramelos as $caramelo) {
                if (! is_array($caramelo)) {
                    continue;
                }

                if ((int) ($caramelo['cantidad'] ?? 0) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * ¿Hubo actividad registrada en el bloque de incidentes? Evita conceder
     * "sin contratiempos" cuando el resultado no trae datos reales.
     *
     * @param  array<string, mixed>  $incidentes
     */
    private static function hayActividad(array $incidentes): bool
    {
        $total = 0;
        foreach (['encuentros', 'victorias', 'huidas', 'emboscadas', 'contratiempos'] as $clave) {
            $total += (int) ($incidentes[$clave] ?? 0);
        }

        return $total > 0;
    }
}
