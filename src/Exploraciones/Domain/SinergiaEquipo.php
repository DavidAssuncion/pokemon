<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Src\Exploraciones\Domain\Collections\RolesExploracionCollection;
use Src\Exploraciones\Domain\ValueObjects\ModificadoresSinergia;

/**
 * RF-13: sinergias de equipo como tabla config-driven (dominio puro).
 *
 * Devuelve los modificadores de la sinergia que aplica a un conjunto de roles:
 * pares (V+C=Asalto, V+T=Patrulla, C+T=Cacería, R+T=Prospección, V+R=Avance
 * seguro, C+R=Escolta), tríos (V+C+C=Dominio del combate, V+C+T=Cacería,
 * R+R+C=Recolección segura, V+R+T=Reconocimiento, V+R+C=Expedición equilibrada)
 * y negativas con 3 del mismo rol (VVV, CCC, RRR, TTT).
 *
 * Orden de resolución: negativa (3 mismo rol) → trío → par. La composición es
 * independiente del orden de los miembros.
 */
final class SinergiaEquipo
{
    /**
     * Sinergia que aplica al conjunto de roles.
     */
    public static function sinergiaPara(RolesExploracionCollection $roles): ?ModificadoresSinergia
    {
        $clave = self::clave($roles);
        if ($clave === '') {
            return null;
        }

        return self::tabla()[$clave] ?? null;
    }

    /**
     * Clave canónica: códigos V/C/R/T ordenados (la composición no depende del
     * orden). V=Vanguardia, C=Combatiente, R=Recolector, T=Rastreador.
     */
    private static function clave(RolesExploracionCollection $roles): string
    {
        $codigos = $roles->map(
            static fn (RolExploracion $rol): string => match ($rol) {
                RolExploracion::VANGUARDIA => 'V',
                RolExploracion::COMBATIENTE => 'C',
                RolExploracion::RECOLECTOR => 'R',
                RolExploracion::RASTREADOR => 'T',
            },
        );
        sort($codigos);

        return implode('', $codigos);
    }

    /**
     * Tabla config-driven de sinergias indexada por clave canónica.
     *
     * @return array<string, ModificadoresSinergia>
     */
    private static function tabla(): array
    {
        $entradas = [
            // Negativas (3 del mismo rol).
            self::entrada('VVV', 'exploracion_agresiva', bonusCapacidad: -10),
            self::entrada('CCC', 'fuerza_bruta', bonusResolucion: -5, multiplicadorCaramelos: 0.5),
            self::entrada('RRR', 'especialistas', bonusResolucion: -10),
            self::entrada('TTT', 'rastreo_intensivo', bonusCapacidad: -5),

            // Tríos.
            self::entrada('CCV', 'dominio_combate', bonusResolucion: 15),
            self::entrada('CTV', 'caceria', bonusResolucion: 10, multiplicadorHuidas: 0.5),
            self::entrada('CRR', 'recoleccion_segura', multiplicadorCaramelos: 1.75),
            self::entrada('RTV', 'reconocimiento', bonusCapacidad: 10),
            self::entrada('CRV', 'expedicion_equilibrada', bonusCapacidad: 8, bonusResolucion: 8),

            // Pares.
            self::entrada('CV', 'asalto', bonusResolucion: 10, multiplicadorRetirada: 0.5),
            self::entrada('TV', 'patrulla', bonusCapacidad: 5, detectaEmboscadas: true),
            self::entrada('CT', 'caceria', bonusResolucion: 5, multiplicadorHuidas: 0.5),
            self::entrada('RT', 'prospeccion', multiplicadorCaramelos: 1.5),
            self::entrada('RV', 'avance_seguro', bonusCapacidad: 5, reduccionTiempo: true),
            self::entrada('CR', 'escolta', multiplicadorRetirada: 0.5),
        ];

        $tabla = [];
        foreach ($entradas as $entrada) {
            $tabla[$entrada['clave']] = $entrada['modificadores'];
        }

        return $tabla;
    }

    /**
     * @return array{clave: string, modificadores: ModificadoresSinergia}
     */
    private static function entrada(
        string $clave,
        string $nombre,
        int $bonusCapacidad = 0,
        int $bonusResolucion = 0,
        float $multiplicadorCaramelos = 1.0,
        float $multiplicadorHuidas = 1.0,
        float $multiplicadorRetirada = 1.0,
        bool $detectaEmboscadas = false,
        bool $reduccionTiempo = false,
    ): array {
        return [
            'clave' => $clave,
            'modificadores' => new ModificadoresSinergia(
                nombre: $nombre,
                bonusCapacidad: $bonusCapacidad,
                bonusResolucion: $bonusResolucion,
                multiplicadorCaramelos: $multiplicadorCaramelos,
                multiplicadorHuidas: $multiplicadorHuidas,
                multiplicadorRetirada: $multiplicadorRetirada,
                detectaEmboscadas: $detectaEmboscadas,
                reduccionTiempo: $reduccionTiempo,
            ),
        ];
    }
}
