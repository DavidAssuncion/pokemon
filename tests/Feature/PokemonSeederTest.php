<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Pokemon;
use Database\Seeders\PokemonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Reclutamiento\App\ServicioEvolucion;
use Tests\TestCase;

class PokemonSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El seeder cataloga: species normales (is_default=1) + formas regionales
     * (-alola, -galar, -hisui, -paldea). Las demás formas alternas se omiten.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PokemonSeeder::class);
    }

    // ==========================================
    // Presencia / ausencia
    // ==========================================

    public function test_especies_normales_presentes(): void
    {
        $this->assertDatabaseHas('pokemon', ['id' => 19, 'name' => 'rattata']);
        $this->assertDatabaseHas('pokemon', ['id' => 37, 'name' => 'vulpix']);
    }

    public function test_formas_regionales_presentes(): void
    {
        $this->assertDatabaseHas('pokemon', ['id' => 10091, 'name' => 'rattata-alola']);
        $this->assertDatabaseHas('pokemon', ['id' => 10104, 'name' => 'ninetales-alola']);
    }

    public function test_formas_no_regionales_ausentes(): void
    {
        // Formas alternas que NO son regionales: no deben sembrarse.
        $this->assertDatabaseMissing('pokemon', ['id' => 10008]); // rotom-heat
        $this->assertDatabaseMissing('pokemon', ['id' => 10181]); // zygarde-10
        $this->assertDatabaseMissing('pokemon', ['id' => 10276]); // terapagos-terastal
        $this->assertDatabaseMissing('pokemon', ['id' => 10264]); // koraidon-limited-build
        $this->assertDatabaseMissing('pokemon', ['id' => 10013]); // castform-sunny
    }

    public function test_formas_no_regionales_se_omiten_por_completo(): void
    {
        // Ni stats, ni types, ni evolución: la fila se descarta entera.
        $this->assertDatabaseMissing('pokemon_stats', ['pokemon_id' => 10008]);
        $this->assertDatabaseMissing('pokemon_types', ['pokemon_id' => 10008]);
        $this->assertDatabaseMissing('pokemon_evolution', ['evolved_species_id' => 10008]);
    }

    // ==========================================
    // Invariante regional: species_id propio
    // ==========================================

    public function test_regional_tiene_species_id_propio(): void
    {
        $rattataAlola = Pokemon::findOrFail(10091);

        $this->assertSame(10091, $rattataAlola->species_id);
        $this->assertNotSame(19, $rattataAlola->species_id);
    }

    public function test_regional_tiene_cadena_evolutiva_propia(): void
    {
        $rattataAlola = Pokemon::findOrFail(10091);
        $rattata = Pokemon::findOrFail(19);

        // chain normal de rattata (species 19) = 7 → alola = 10000 + 1*1000 + 7 = 11007
        $this->assertSame(11007, $rattataAlola->evolution_chain_id);
        $this->assertGreaterThanOrEqual(10000, $rattataAlola->evolution_chain_id);
        $this->assertNotSame($rattata->evolution_chain_id, $rattataAlola->evolution_chain_id);
    }

    public function test_meowth_alola_y_galar_no_comparten_cadena(): void
    {
        $meowthAlola = Pokemon::findOrFail(10107);
        $meowthGalar = Pokemon::findOrFail(10161);

        // Misma familia normal (meowth, chain 22) pero distinta región → cadenas distintas.
        $this->assertSame(11022, $meowthAlola->evolution_chain_id);
        $this->assertSame(12022, $meowthGalar->evolution_chain_id);
        $this->assertNotSame($meowthAlola->evolution_chain_id, $meowthGalar->evolution_chain_id);
    }

    // ==========================================
    // Evolución re-parentada
    // ==========================================

    public function test_evolucion_ninetales_alola_reparentada_a_vulpix_alola(): void
    {
        $this->assertDatabaseHas('pokemon_evolution', [
            'evolved_species_id' => 10104,
            'evolves_from_species_id' => 10103,
        ]);
    }

    public function test_no_existe_evolucion_10104_desde_37_y_si_38_desde_37(): void
    {
        // La forma regional NO puede apuntar a la species normal de su pre-evolución.
        $this->assertDatabaseMissing('pokemon_evolution', [
            'evolved_species_id' => 10104,
            'evolves_from_species_id' => 37,
        ]);

        // La evolución normal (ninetales 38 desde vulpix 37) queda intacta.
        $this->assertDatabaseHas('pokemon_evolution', [
            'evolved_species_id' => 38,
            'evolves_from_species_id' => 37,
        ]);
    }

    public function test_servicio_evolucion_de_vulpix_devuelve_ninetales_normal(): void
    {
        $siguiente = ServicioEvolucion::siguienteEvolucion(Pokemon::findOrFail(37));

        $this->assertNotNull($siguiente);
        $this->assertSame(38, $siguiente->id);
        $this->assertSame('ninetales', $siguiente->name);
    }

    public function test_total_pokemon_es_1083(): void
    {
        // 1025 especies normales + 58 regionales.
        $this->assertDatabaseCount('pokemon', 1083);
    }

    // ==========================================
    // Casos límite
    // ==========================================

    public function test_slowpoke_galar_familia_reparentada(): void
    {
        // slowpoke-galar (10164) base; slowbro-galar (10165) y slowking-galar (10172) desde 10164.
        $this->assertDatabaseMissing('pokemon_evolution', [
            'evolved_species_id' => 10164,
        ]);
        $this->assertDatabaseHas('pokemon_evolution', [
            'evolved_species_id' => 10165,
            'evolves_from_species_id' => 10164,
        ]);
        $this->assertDatabaseHas('pokemon_evolution', [
            'evolved_species_id' => 10172,
            'evolves_from_species_id' => 10164,
        ]);
    }

    public function test_darmanitan_galar_desde_darumaka_galar(): void
    {
        // darumaka-galar (10176) base; darmanitan-galar-standard (10177) y -zen (10178) desde 10176.
        $this->assertDatabaseHas('pokemon_evolution', [
            'evolved_species_id' => 10177,
            'evolves_from_species_id' => 10176,
        ]);
        $this->assertDatabaseHas('pokemon_evolution', [
            'evolved_species_id' => 10178,
            'evolves_from_species_id' => 10176,
        ]);
    }

    public function test_raichu_alola_apunta_a_pikachu_normal_sin_variante_regional(): void
    {
        // No existe pikachu-alola → el pre sin variante se queda con el species normal (25).
        $this->assertDatabaseHas('pokemon_evolution', [
            'evolved_species_id' => 10100,
            'evolves_from_species_id' => 25,
        ]);
    }

    public function test_regional_base_sin_evolucion_no_crea_fila(): void
    {
        // vulpix-alola (10103) y wooper-paldea (10253) no tienen pre-evolución → sin fila.
        $this->assertDatabaseMissing('pokemon_evolution', [
            'evolved_species_id' => 10103,
        ]);
        $this->assertDatabaseMissing('pokemon_evolution', [
            'evolved_species_id' => 10253,
        ]);
    }

    // ==========================================
    // Familias de 1 miembro (casos límite aceptados)
    // ==========================================

    public function test_raichu_alola_es_una_familia_de_un_miembro(): void
    {
        // raichu-alola (10100) ← pikachu (25): no existe pikachu-alola, así que el
        // pre sin variante se queda con el species normal (25). Su cadena propia
        // (11010) NO la comparte ningún otro pokémon → familia de 1 miembro.
        $ralola = Pokemon::findOrFail(10100);

        $this->assertSame(11010, $ralola->evolution_chain_id);
        $this->assertSame(1, Pokemon::where('evolution_chain_id', $ralola->evolution_chain_id)->count());
    }

    public function test_exeggutor_alola_y_marowak_alola_son_familias_de_un_miembro(): void
    {
        // exeggutor-alola (10114, chain 11045) y marowak-alola (10115, chain 11046):
        // sus pre-s (exeggcute 102, cubone 104) no tienen variante regional → species
        // normal conservada → sus cadenas propias son de 1 miembro (aceptado).
        foreach ([10114 => 11045, 10115 => 11046] as $id => $chain) {
            $regional = Pokemon::findOrFail($id);
            $this->assertSame($chain, $regional->evolution_chain_id);
            $this->assertSame(1, Pokemon::where('evolution_chain_id', $chain)->count());
        }
    }

    // ==========================================
    // Evolución selectable: todas las alternativas regionales presentes
    // ==========================================

    public function test_cubone_puede_evolucionar_a_marowak_normal_y_a_marowak_alola(): void
    {
        $this->assertDatabaseHas('pokemon_evolution', [
            'evolved_species_id' => 105,
            'evolves_from_species_id' => 104,
        ]);
        $this->assertDatabaseHas('pokemon_evolution', [
            'evolved_species_id' => 10115,
            'evolves_from_species_id' => 104,
        ]);
    }

    public function test_exeggcute_puede_evolucionar_a_exeggutor_normal_y_a_exeggutor_alola(): void
    {
        $this->assertDatabaseHas('pokemon_evolution', [
            'evolved_species_id' => 103,
            'evolves_from_species_id' => 102,
        ]);
        $this->assertDatabaseHas('pokemon_evolution', [
            'evolved_species_id' => 10114,
            'evolves_from_species_id' => 102,
        ]);
    }

    public function test_pikachu_puede_evolucionar_a_raichu_normal_y_a_raichu_alola(): void
    {
        $this->assertDatabaseHas('pokemon_evolution', [
            'evolved_species_id' => 26,
            'evolves_from_species_id' => 25,
        ]);
        $this->assertDatabaseHas('pokemon_evolution', [
            'evolved_species_id' => 10100,
            'evolves_from_species_id' => 25,
        ]);
    }

    public function test_no_existe_sandshrew_alola_evolucionando_de_sandshrew(): void
    {
        // sandshrew-alola (10101) NO evoluciona de sandshrew normal (27).
        $this->assertDatabaseMissing('pokemon_evolution', [
            'evolved_species_id' => 10101,
            'evolves_from_species_id' => 27,
        ]);
    }

    public function test_no_existe_rattata_alola_evolucionando_de_rattata(): void
    {
        // rattata-alola (10091) NO evoluciona de rattata normal (19).
        $this->assertDatabaseMissing('pokemon_evolution', [
            'evolved_species_id' => 10091,
            'evolves_from_species_id' => 19,
        ]);
    }

    public function test_no_existe_vulpix_alola_evolucionando_de_vulpix(): void
    {
        // vulpix-alola (10103) NO evoluciona de vulpix normal (37).
        $this->assertDatabaseMissing('pokemon_evolution', [
            'evolved_species_id' => 10103,
            'evolves_from_species_id' => 37,
        ]);
    }

    public function test_servicio_evolucion_pico_sigue_eligiendo_evolucion_normal(): void
    {
        // vulpix (37) → ninetales normal (38), no la variante alola.
        $vulpix = ServicioEvolucion::siguienteEvolucion(Pokemon::findOrFail(37));
        $this->assertNotNull($vulpix);
        $this->assertSame(38, $vulpix->id);
        $this->assertSame('ninetales', $vulpix->name);

        // cubone (104) → marowak normal (105), no la variante alola.
        $cubone = ServicioEvolucion::siguienteEvolucion(Pokemon::findOrFail(104));
        $this->assertNotNull($cubone);
        $this->assertSame(105, $cubone->id);
        $this->assertSame('marowak', $cubone->name);
    }

    // ==========================================
    // Regionales hisui / paldea presentes
    // ==========================================

    public function test_regionales_hisui_y_paldea_presentes(): void
    {
        $this->assertDatabaseHas('pokemon', ['id' => 10229, 'name' => 'growlithe-hisui']);
        $this->assertDatabaseHas('pokemon', ['id' => 10253, 'name' => 'wooper-paldea']);
        $this->assertDatabaseHas('pokemon', ['id' => 10250, 'name' => 'tauros-paldea-combat-breed']);

        // Sus species_id son propios (no comparten la species normal).
        $this->assertSame(10229, Pokemon::findOrFail(10229)->species_id);
        $this->assertSame(10253, Pokemon::findOrFail(10253)->species_id);
        $this->assertSame(10250, Pokemon::findOrFail(10250)->species_id);

        // Cadenas en los bloques correctos (hisui=13xxx, paldea=14xxx).
        $this->assertSame(13025, Pokemon::findOrFail(10229)->evolution_chain_id);
        $this->assertSame(14096, Pokemon::findOrFail(10253)->evolution_chain_id);
        $this->assertSame(14063, Pokemon::findOrFail(10250)->evolution_chain_id);
    }
}
