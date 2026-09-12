<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

/**
 * Constantes de reglas de batalla (valores numéricos del juego).
 * Punto único de definición para evitar duplicación de literales mágicos.
 */
final class ReglasBatalla
{
    /**
     * Fracción de HP máximo aplicada por ronda por clima (granizo / tormenta arena)
     * y por efectos como Restos. Equivale a 1/16 = 6.25%.
     */
    public const FRACCION_HP_POR_RONDA = 0.0625;

    /**
     * Multiplicador de daño por STAB (Same Type Attack Bonus).
     * Se aplica cuando el tipo del movimiento coincide con un tipo del atacante.
     */
    public const BONUS_STAB = 1.5;

    /**
     * Probabilidad de golpe crítico (1/16 ≈ 6.25%).
     */
    public const CHANCE_CRITICO = 0.0625;

    /**
     * Multiplicador de daño por golpe crítico.
     */
    public const BONUS_CRITICO = 1.5;

    /**
     * Multiplicador de daño recibido por el defensor cuando está en retaguardia
     * y su equipo tiene al menos otro combatiente en vanguardia (reducción del 50%).
     */
    public const MULTIPLICADOR_RETAGUARDIA = 0.5;

    /**
     * Porcentaje de HP máximo que el portador pierde como recoil al infligir
     * daño con el objeto Orbe Vida (10%).
     */
    public const RECOIL_ORBE_VIDA = 0.10;

    /**
     * Multiplicador de daño del objeto Orbe Vida (bonus ×1.3).
     */
    public const BONUS_ORBE_VIDA = 1.30;

    /**
     * Factor de reducción de velocidad por parálisis (la reduce a la mitad).
     */
    public const REDUCCION_VELOCIDAD_PARALISIS = 0.5;

    /**
     * Fracción de HP máximo perdida por ronda por el estado VENENO (1/8 = 12.5%).
     */
    public const FRACCION_HP_VENENO = 0.125;

    /**
     * Divisor del contador de VENENO GRAVE: el daño por ronda es HP_max × contador / 16.
     */
    public const DIVISOR_VENENO_GRAVE = 16;

    /**
     * Probabilidad (%) de descongelarse al intentar actuar (20%).
     */
    public const PORCIENTO_DESCONGELACION = 20;

    /**
     * Probabilidad (%) de no poder actuar por parálisis (25%).
     */
    public const PORCIENTO_PARALISIS = 25;

    /**
     * Probabilidad (%) de golpearse a sí mismo por confusión (33%).
     */
    public const PORCIENTO_CONFUSION_GOLPE = 33;
}
