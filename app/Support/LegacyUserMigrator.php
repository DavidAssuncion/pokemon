<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Backfill condicional de la Fase 1 multiplayer: si cualquiera de las tablas
 * player-owned (o las antiguas de caramelos) tiene filas, crea (o reutiliza)
 * el usuario legacy 'Legacy' (legacy@local) y devuelve su id. Sin filas,
 * devuelve null y NO se crea ningún usuario (los tests corren con tablas
 * vacías y no deben fabricar usuarios).
 */
final class LegacyUserMigrator
{
    /** Tablas de la fase que pueden contener filas a migrar al usuario legacy. */
    private const TABLAS_LEGACY = [
        'reclutados',
        'teams',
        'reclutables',
        'pokedex',
        'exploraciones_activas',
        'caramelos',
        'caramelos_ev',
        'caramelos_tipo',
        'reclutados_exp_tipo',
    ];

    /**
     * Devuelve el id del usuario legacy (creándolo si hay datos que migrar)
     * o null si no hay ninguna fila que migrar.
     *
     * Sin cache estática: en los tests PHPUnit cada caso corre contra una BD
     * fresca en el mismo proceso y un id cacheado apuntaría a un usuario inexistente.
     */
    public static function ensureLegacyUserId(): ?int
    {
        if (! self::hayFilasQueMigrar()) {
            return null;
        }

        $legacy = DB::table('users')->where('email', 'legacy@local')->first();
        if ($legacy !== null) {
            return $legacy->id;
        }

        return DB::table('users')->insertGetId([
            'name' => 'Legacy',
            'email' => 'legacy@local',
            'email_verified_at' => null,
            'password' => Hash::make(Str::random(64)),
            'remember_token' => null,
            'experiencia' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function hayFilasQueMigrar(): bool
    {
        foreach (self::TABLAS_LEGACY as $tabla) {
            if (Schema::hasTable($tabla) && DB::table($tabla)->exists()) {
                return true;
            }
        }

        return false;
    }
}
