<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Habitat;
use App\Models\Pokemon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Configura `habitats.mazmorra` a partir del pool real de cada hábitat.
 *
 * Contrato: `{"pisos":[{"piso":1,"species_id":25}, ...]}`. Un piso por especie,
 * ordenado por nivel del pivote (1 → 2 → 3) y, dentro de cada nivel, por suma de
 * stats base (BST) ascendente y `species_id` ascendente. La numeración es
 * contigua 1..N. Solo escribe donde `mazmorra` es NULL.
 */
class MazmorraSeeder extends Seeder
{
    /**
     * Hábitat de legendarios: queda fuera de la configuración de mazmorras.
     */
    private const HABITAT_EXCLUIDO = 49;

    /**
     * Especies sin pool salvaje: Ditto, Unown y Smeargle.
     *
     * @var list<int>
     */
    private const ESPECIES_EXCLUIDAS = [132, 201, 235];

    /**
     * Niveles posibles del pivote, en el orden en que deben aparecer los pisos.
     *
     * @var list<int>
     */
    private const NIVELES = [1, 2, 3];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $habitats = Habitat::query()
            ->where('id', '<>', self::HABITAT_EXCLUIDO)
            ->whereNull('mazmorra')
            ->with([
                'pokemon' => fn (BelongsToMany $query): BelongsToMany => $query
                    ->select('pokemon.*')
                    ->whereNotIn('pokemon.id', self::ESPECIES_EXCLUIDAS)
                    ->with('stats'),
            ])
            ->orderBy('id')
            ->get();

        $habitatsGenerados = 0;
        $pisosGenerados = 0;
        $habitatsVacios = [];

        foreach ($habitats as $habitat) {
            $pisos = $this->construirPisos($habitat->pokemon);

            if ($pisos === []) {
                $habitatsVacios[] = $habitat->id;

                continue;
            }

            $habitat->update(['mazmorra' => ['pisos' => $pisos]]);

            $habitatsGenerados++;
            $pisosGenerados += count($pisos);
        }

        if (isset($this->command)) {
            $this->command->info("Mazmorras generadas: {$habitatsGenerados} hábitats, {$pisosGenerados} pisos.");
        }

        if ($habitatsVacios !== [] && isset($this->command)) {
            $this->command->warn('Hábitats sin especies elegibles: '.implode(', ', $habitatsVacios));
        }
    }

    /**
     * Aplana el pool por nivel y asigna la numeración contigua de pisos.
     *
     * @param  Collection<int, Pokemon>  $pokemon
     * @return list<array{piso: int, species_id: int}>
     */
    private function construirPisos(Collection $pokemon): array
    {
        $pisos = [];
        $numero = 1;

        foreach (self::NIVELES as $nivel) {
            $delNivel = $pokemon
                ->filter(fn (Pokemon $especie): bool => (int) $especie->pivot->level === $nivel)
                ->sortBy([
                    fn (Pokemon $a, Pokemon $b): int => $a->stats->sum('base_stat') <=> $b->stats->sum('base_stat'),
                    fn (Pokemon $a, Pokemon $b): int => (int) $a->species_id <=> (int) $b->species_id,
                ]);

            foreach ($delNivel as $especie) {
                $pisos[] = [
                    'piso' => $numero++,
                    'species_id' => (int) $especie->species_id,
                ];
            }
        }

        return $pisos;
    }
}
