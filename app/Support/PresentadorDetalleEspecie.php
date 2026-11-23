<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Ability;
use App\Models\Move;
use App\Models\Pokemon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Src\Battle\Domain\Enums\ObjetivoMovimiento;
use Src\Shared\UI\TipoBadges;

/**
 * Detalle ampliado de una especie.
 *
 * Reutiliza el contrato de `PresentadorDetallePokemon` (tipos, stats, hábitat
 * y familia) y añade habilidades, learnset y metadatos de especie. `para()`
 * conserva el learnset completo para el endpoint de hábitats; `paraPokedex()`
 * lo acota a `level-up` para el modal de la Pokédex.
 */
final class PresentadorDetalleEspecie
{
    private const FUENTE_NIVEL = 'level-up';

    /**
     * @return array<string, mixed>
     */
    public static function para(Pokemon $pokemon, ?bool $visto = null, ?bool $atrapado = null): array
    {
        return self::componer($pokemon, false, $visto, $atrapado);
    }

    /**
     * Detalle para el modal de la Pokédex: learnset acotado a `level-up`.
     *
     * @return array<string, mixed>
     */
    public static function paraPokedex(Pokemon $pokemon, ?bool $visto = null, ?bool $atrapado = null): array
    {
        return self::componer($pokemon, true, $visto, $atrapado);
    }

    /**
     * @return array<string, mixed>
     */
    private static function componer(Pokemon $pokemon, bool $soloNivel, ?bool $visto, ?bool $atrapado): array
    {
        $pokemon->loadMissing(['abilities', 'moves']);

        return [
            ...PresentadorDetallePokemon::para($pokemon, $visto, $atrapado),
            'abilities' => self::abilities($pokemon),
            'moves' => self::moves($pokemon, $soloNivel),
            'species' => self::species($pokemon),
        ];
    }

    /**
     * @return list<array{id: int, name: string, effect: string, slot: int, source: string}>
     */
    private static function abilities(Pokemon $pokemon): array
    {
        /** @var list<array{id: int, name: string, effect: string, slot: int, source: string}> $abilities */
        $abilities = $pokemon->abilities
            ->sortBy(fn (Ability $ability): int => self::intOrZero(self::pivotValue($ability, 'slot')))
            ->map(fn (Ability $ability): array => [
                'id' => (int) $ability->getAttribute('id'),
                'name' => (string) $ability->getAttribute('name'),
                'effect' => (string) $ability->getAttribute('effect'),
                'slot' => self::intOrZero(self::pivotValue($ability, 'slot')),
                'source' => (string) (self::pivotValue($ability, 'source') ?? ''),
            ])
            ->values()
            ->all();

        return $abilities;
    }

    /**
     * Learnset: level-up primero, después por nivel ascendente y nombre.
     *
     * Con `$soloNivel` se filtra por `source` antes de construir el payload.
     *
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     type_id: int,
     *     tipo_nombre: string,
     *     tipo_slug: string,
     *     category: string,
     *     category_label: string,
     *     base_power: int|null,
     *     accuracy: int|null,
     *     level: int|null,
     *     source: string,
     *     objetivo: string,
     *     objetivo_label: string|null,
     *     objetivo_corto: string|null,
     *     objetivo_multiple: bool,
     *     objetivo_incluye_aliados: bool,
     *     objetivo_es_fila: bool
     * }>
     */
    private static function moves(Pokemon $pokemon, bool $soloNivel = false): array
    {
        /** @var list<array{
         *     slug: string,
         *     name: string,
         *     type_id: int,
         *     tipo_nombre: string,
         *     tipo_slug: string,
         *     category: string,
         *     category_label: string,
         *     base_power: int|null,
         *     accuracy: int|null,
         *     level: int|null,
         *     source: string,
         *     objetivo: string,
         *     objetivo_label: string|null,
         *     objetivo_corto: string|null,
         *     objetivo_multiple: bool,
         *     objetivo_incluye_aliados: bool,
         *     objetivo_es_fila: bool
         * }> $moves
         */
        $moves = $pokemon->moves
            ->filter(fn (Move $move): bool => ! $soloNivel || (string) (self::pivotValue($move, 'source') ?? '') === self::FUENTE_NIVEL)
            ->map(function (Move $move): array {
                $typeId = (int) $move->getAttribute('type_id');
                $badge = TipoBadges::porId($typeId);
                $category = (string) $move->getAttribute('category');
                $objetivo = ObjetivoMovimiento::tryFrom((string) $move->getAttribute('target'))
                    ?? ObjetivoMovimiento::NORMAL;

                return [
                    'slug' => (string) $move->getAttribute('slug'),
                    'name' => (string) $move->getAttribute('name'),
                    'type_id' => $typeId,
                    'tipo_nombre' => $badge['label'],
                    'tipo_slug' => $badge['slug'],
                    'category' => $category,
                    'category_label' => self::categoryLabel($category),
                    'base_power' => self::intOrNull($move->getAttribute('base_power')),
                    'accuracy' => self::intOrNull($move->getAttribute('accuracy')),
                    'level' => self::intOrNull(self::pivotValue($move, 'level')),
                    'source' => (string) (self::pivotValue($move, 'source') ?? ''),
                    'objetivo' => $objetivo->value,
                    'objetivo_label' => $objetivo->etiquetaAlcance(),
                    'objetivo_corto' => $objetivo->etiquetaCortaAlcance(),
                    'objetivo_multiple' => $objetivo->esMultiobjetivo(),
                    'objetivo_incluye_aliados' => $objetivo->incluyeAliados(),
                    'objetivo_es_fila' => $objetivo->esFila(),
                ];
            })
            ->values()
            ->all();

        usort($moves, static fn (array $a, array $b): int => self::moveOrder($a) <=> self::moveOrder($b));

        return $moves;
    }

    /**
     * @param  array{slug: string, name: string, type_id: int, tipo_nombre: string, tipo_slug: string, category: string, category_label: string, base_power: int|null, accuracy: int|null, level: int|null, source: string, objetivo: string, objetivo_label: string|null, objetivo_corto: string|null, objetivo_multiple: bool, objetivo_incluye_aliados: bool, objetivo_es_fila: bool}  $move
     * @return array{0: int, 1: int, 2: string}
     */
    private static function moveOrder(array $move): array
    {
        return [
            $move['source'] === self::FUENTE_NIVEL ? 0 : 1,
            $move['level'] ?? PHP_INT_MAX,
            mb_strtolower($move['name']),
        ];
    }

    /**
     * Metadatos de especie; las claves nulas se omiten.
     *
     * `height` está en decímetros y `weight` en hectogramos: se añaden los
     * derivados de presentación `height_m` y `weight_kg`.
     *
     * @return array<string, int|float>
     */
    private static function species(Pokemon $pokemon): array
    {
        $height = self::intOrNull($pokemon->getAttribute('height'));
        $weight = self::intOrNull($pokemon->getAttribute('weight'));

        $species = [
            'species_id' => self::intOrNull($pokemon->getAttribute('species_id')),
            'evolution_chain_id' => self::intOrNull($pokemon->getAttribute('evolution_chain_id')),
            'capture_rate' => self::intOrNull($pokemon->getAttribute('capture_rate')),
            'base_experience' => self::intOrNull($pokemon->getAttribute('base_experience')),
            'height' => $height,
            'weight' => $weight,
            'hatch' => self::intOrNull($pokemon->getAttribute('hatch')),
            'height_m' => $height === null ? null : round($height / 10, 1),
            'weight_kg' => $weight === null ? null : round($weight / 10, 1),
        ];

        /** @var array<string, int|float> $resultado */
        $resultado = array_filter($species, static fn (int|float|null $value): bool => $value !== null);

        return $resultado;
    }

    private static function categoryLabel(string $category): string
    {
        return match ($category) {
            'fisico' => 'Físico',
            'especial' => 'Especial',
            'estado' => 'Estado',
            default => ucfirst($category),
        };
    }

    private static function pivotValue(Model $model, string $key): mixed
    {
        $pivot = $model->getAttribute('pivot');

        return $pivot instanceof Pivot ? $pivot->getAttribute($key) : null;
    }

    private static function intOrZero(mixed $value): int
    {
        return self::intOrNull($value) ?? 0;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
