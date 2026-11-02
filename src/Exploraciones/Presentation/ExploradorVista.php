<?php

declare(strict_types=1);

namespace Src\Exploraciones\Presentation;

use App\Enums\TipoEnum;
use App\Models\Pokemon;
use App\Models\PokemonType;
use App\Models\Reclutado;
use Src\Exploraciones\Domain\ValueObjects\EstadoExplorador;
use Src\Shared\Domain\NivelHelper;
use Src\Shared\Domain\SlugTipo;

/**
 * Presentador puro de la ficha del explorador individual (el reclutado que
 * realiza la expedición). No consulta la base de datos: usa el modelo ya
 * cargado y el estado persistido en `eventos['explorador']`.
 *
 * El contrato está pensado para que la vista no derive porcentajes, clases ni
 * textos de ánimo. Toda la información desconocida se expone como
 * `sin_datos`/`null`, sin inventar HP ni tipos.
 */
final class ExploradorVista
{
    /** @var array<string, string> */
    private const ROLES_LABEL = [
        'VANGUARDIA' => 'Vanguardia',
        'COMBATIENTE' => 'Combatiente',
        'RECOLECTOR' => 'Recolector',
        'RASTREADOR' => 'Rastreador',
    ];

    /**
     * @param  array<string, mixed>  $eventos  eventos completos de la exploración
     * @return array{
     *     id: int|null,
     *     nombre: string,
     *     pokemon_id: int|null,
     *     icono: string|null,
     *     nivel: int,
     *     exp_total: int,
     *     exp_tipos: array<string, int>,
     *     es_shiny: bool,
     *     rol: string,
     *     rol_label: string,
     *     tipos: list<array{nombre: string, slug: string}>,
     *     hp_actual: int,
     *     hp_max: int,
     *     hp_pct: int|null,
     *     hp_estado: string,
     *     hp_label: string,
     *     hp_clase: string,
     *     barreras: list<array{tipo: string, nombre: string, actual: int, max: int, pct: int}>,
     *     animo: array{texto: string, tono: string},
     *     progreso_nivel: int
     * }
     */
    public static function toView(?Reclutado $reclutado, array $eventos): array
    {
        $exp = $reclutado?->exp;
        $expTotal = $exp?->total() ?? 0;
        $expTipos = $exp?->toArray()['tipos'] ?? [];
        $rol = $reclutado?->rol()->value ?? '';
        $pokemonId = $reclutado?->pokemon_id;

        $estado = self::estadoExplorador($eventos);
        [$hpActual, $hpMax, $hpPct, $hpEstado] = self::hp($estado);

        return [
            'id' => $reclutado?->id,
            'nombre' => self::nombre($reclutado),
            'pokemon_id' => $pokemonId,
            'icono' => $pokemonId !== null && $pokemonId > 0
                ? "/images/iconos_webp/{$pokemonId}.webp"
                : null,
            'nivel' => NivelHelper::nivelDesdeExperiencia($expTotal),
            'exp_total' => $expTotal,
            'exp_tipos' => $expTipos,
            'es_shiny' => $reclutado->es_shiny ?? false,
            'rol' => $rol,
            'rol_label' => self::ROLES_LABEL[$rol] ?? '',
            'tipos' => self::tipos($reclutado),
            'hp_actual' => $hpActual,
            'hp_max' => $hpMax,
            'hp_pct' => $hpPct,
            'hp_estado' => $hpEstado,
            'hp_label' => self::hpLabel($hpActual, $hpMax, $hpPct, $hpEstado),
            'hp_clase' => self::hpClase($hpEstado),
            'barreras' => self::barreras($estado),
            'animo' => FraseAnimo::para($rol !== '' ? $rol : null, $hpPct, null),
            'progreso_nivel' => NivelHelper::progresoHaciaSiguienteNivel($expTotal),
        ];
    }

    /**
     * Nombre visible del explorador: alias del reclutado → nombre de su
     * Pokémon si la relación `pokemon` ya está cargada (nunca fuerza lazy
     * loading) → 'Sin reclutado'.
     */
    private static function nombre(?Reclutado $reclutado): string
    {
        $nombre = $reclutado?->nombre;
        if (is_string($nombre) && trim($nombre) !== '') {
            return $nombre;
        }

        if ($reclutado !== null && $reclutado->relationLoaded('pokemon')) {
            $pokemon = $reclutado->getRelation('pokemon');
            if ($pokemon instanceof Pokemon && trim($pokemon->name) !== '') {
                return $pokemon->name;
            }
        }

        return 'Sin reclutado';
    }

    /**
     * @param  array<string, mixed>  $eventos
     */
    private static function estadoExplorador(array $eventos): EstadoExplorador
    {
        $estado = $eventos['explorador'] ?? null;

        return is_array($estado) ? EstadoExplorador::desdeArray($estado) : EstadoExplorador::vacio();
    }

    /**
     * @return array{0: int, 1: int, 2: int|null, 3: string}
     */
    private static function hp(EstadoExplorador $estado): array
    {
        if ($estado->sinCombate()) {
            return [0, 0, null, 'sin_datos'];
        }

        $pct = max(0, min(100, (int) round($estado->pctHp())));
        $hpMax = max(0, (int) round($estado->hpMax));
        $hpActual = max(0, min($hpMax, (int) round($estado->hp)));

        $estadoHp = match (true) {
            $pct < 30 => 'critico',
            $pct < 60 => 'tocado',
            default => 'sano',
        };

        return [$hpActual, $hpMax, $pct, $estadoHp];
    }

    private static function hpLabel(int $actual, int $max, ?int $pct, string $estado): string
    {
        if ($estado === 'sin_datos') {
            return 'Sin incidencias';
        }

        return "{$actual} / {$max} PS ({$pct}%)";
    }

    private static function hpClase(string $estado): string
    {
        return match ($estado) {
            'critico' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
            'tocado' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
            'sano' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
            default => 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
        };
    }

    /**
     * @return list<array{tipo: string, nombre: string, actual: int, max: int, pct: int}>
     */
    private static function barreras(EstadoExplorador $estado): array
    {
        if ($estado->sinCombate()) {
            return [];
        }

        return [
            [
                'tipo' => 'fisica',
                'nombre' => 'Barrera física',
                'actual' => max(0, (int) round($estado->barreraFisica)),
                'max' => max(0, (int) round($estado->barreraFisicaMax)),
                'pct' => self::pct($estado->barreraFisica, $estado->barreraFisicaMax),
            ],
            [
                'tipo' => 'especial',
                'nombre' => 'Barrera especial',
                'actual' => max(0, (int) round($estado->barreraEspecial)),
                'max' => max(0, (int) round($estado->barreraEspecialMax)),
                'pct' => self::pct($estado->barreraEspecial, $estado->barreraEspecialMax),
            ],
        ];
    }

    private static function pct(float $actual, float $max): int
    {
        if ($max <= 0) {
            return 0;
        }

        return max(0, min(100, (int) round(($actual / $max) * 100)));
    }

    /**
     * Tipos del pokémon del reclutado. No fuerza carga de relaciones: si
     * `pokemon` o `types` no están cargados devuelve [] (4b añadirá el eager
     * loading `reclutado.pokemon.types`).
     *
     * @return list<array{nombre: string, slug: string}>
     */
    private static function tipos(?Reclutado $reclutado): array
    {
        if ($reclutado === null || ! $reclutado->relationLoaded('pokemon')) {
            return [];
        }

        $pokemon = $reclutado->getRelation('pokemon');
        if (! $pokemon instanceof Pokemon || ! $pokemon->relationLoaded('types')) {
            return [];
        }

        $types = $pokemon->getRelation('types');
        if (! is_iterable($types)) {
            return [];
        }

        $tipos = [];
        foreach ($types as $type) {
            $nombre = self::nombreTipo($type);
            if ($nombre === '') {
                continue;
            }

            $tipos[] = [
                'nombre' => $nombre,
                'slug' => SlugTipo::de($nombre),
            ];
        }

        return $tipos;
    }

    private static function nombreTipo(mixed $type): string
    {
        if ($type instanceof PokemonType) {
            return $type->type->label();
        }

        if (is_array($type) && isset($type['type'])) {
            $enum = $type['type'];

            return $enum instanceof TipoEnum
                ? $enum->label()
                : (is_string($enum) ? $enum : '');
        }

        return '';
    }
}
