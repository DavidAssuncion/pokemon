<?php

declare(strict_types=1);

namespace Src\Exploraciones\Presentation;

/**
 * Frases de ánimo del explorador, derivadas de datos ya conocidos (HP, rol y
 * categoría del resultado). Es determinista y no promete efectos de juego que
 * no existan.
 */
final class FraseAnimo
{
    /**
     * Prioridad: HP crítico/tocado → categoría negativa → éxito excepcional →
     * frase propia del rol → neutro.
     *
     * @return array{texto: string, tono: string}
     */
    public static function para(?string $rol, ?int $hpPct, ?string $categoria): array
    {
        if ($hpPct !== null && $hpPct < 30) {
            return [
                'texto' => 'Está muy debilitado; cada paso cuenta.',
                'tono' => 'alerta',
            ];
        }

        if ($hpPct !== null && $hpPct < 60) {
            return [
                'texto' => 'Ha recibido daño y avanza con cautela.',
                'tono' => 'negativo',
            ];
        }

        if ($categoria === 'fracaso') {
            return [
                'texto' => 'La expedición no salió como se esperaba.',
                'tono' => 'negativo',
            ];
        }

        if ($categoria === 'retirada') {
            return [
                'texto' => 'Volver a tiempo también es una decisión sabia.',
                'tono' => 'negativo',
            ];
        }

        if ($categoria === 'exito_excepcional') {
            return [
                'texto' => 'La expedición fue un éxito excepcional.',
                'tono' => 'positivo',
            ];
        }

        return match (strtoupper(trim((string) $rol))) {
            'RECOLECTOR' => [
                'texto' => 'Siempre atento a cualquier hallazgo.',
                'tono' => 'neutro',
            ],
            'VANGUARDIA' => [
                'texto' => 'Abre camino con paso firme.',
                'tono' => 'neutro',
            ],
            'RASTREADOR' => [
                'texto' => 'Sigue el rastro con paciencia.',
                'tono' => 'neutro',
            ],
            'COMBATIENTE' => [
                'texto' => 'Listo para cualquier combate.',
                'tono' => 'neutro',
            ],
            default => [
                'texto' => 'Explorando a su ritmo.',
                'tono' => 'neutro',
            ],
        };
    }
}
