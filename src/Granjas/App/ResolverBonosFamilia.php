<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use App\Models\Granja;
use App\Models\Nido;
use App\Models\Refugio;
use Illuminate\Support\Facades\DB;
use Src\Granjas\Domain\CalculadoraBonosGranja;
use Src\Granjas\Domain\Collections\NivelesComplementoCollection;
use Src\Granjas\Domain\Collections\RefugiosHabitatCollection;
use Src\Granjas\Domain\DataTransferObjects\BonosFamilia;
use Src\Granjas\Domain\DataTransferObjects\NidoBonos;
use Src\Granjas\Domain\DataTransferObjects\NivelComplemento;
use Src\Granjas\Domain\DataTransferObjects\RefugioBonos;
use Src\Granjas\Domain\DataTransferObjects\RefugioEstado;
use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * Resuelve los bonos de granja de una familia: nido (IV/tiempo), refugio
 * (aparición/captura) y complementos EV. Se usa desde huevos y exploraciones.
 */
final class ResolverBonosFamilia
{
    public function paraFamilia(int $userId, int $evolutionChainId): BonosFamilia
    {
        $habitatId = $this->habitatDeFamilia($evolutionChainId);

        if ($habitatId === null) {
            return $this->neutros();
        }

        $granja = $this->granjaDe($userId, $habitatId);

        if ($granja === null) {
            return $this->neutros();
        }

        $refugios = $this->refugiosDeGranja($granja->id);
        $nido = Nido::where('farm_id', $granja->id)
            ->where('evolution_chain_id', $evolutionChainId)
            ->first();
        $refugio = $refugios->paraChain($evolutionChainId);
        $niveles = $this->nivelesComplementos($granja->id, $evolutionChainId);
        $insalubres = $refugio !== null && $refugio->insalubres;
        $nidoNivel = $nido !== null ? $nido->level : 0;

        return new BonosFamilia(
            nido: new NidoBonos(
                nivel: $nidoNivel,
                rangoIvs: CalculadoraBonosGranja::rangoIvs($nidoNivel),
                factorTiempo: CalculadoraBonosGranja::factorTiempo($nidoNivel),
            ),
            refugio: new RefugioBonos(
                nivel: $refugio !== null ? $refugio->nivel : 0,
                insalubres: $insalubres,
                factorAparicion: $this->factorAparicion($refugios, $evolutionChainId),
                factorCaptura: $this->factorCaptura($refugios, $evolutionChainId),
            ),
            evsFamilia: CalculadoraBonosGranja::evsComplemento($niveles, $insalubres),
        );
    }

    public function evsIniciales(int $userId, int $evolutionChainId): StatsValue
    {
        return $this->paraFamilia($userId, $evolutionChainId)->evsFamilia;
    }

    public function nidoNivel(int $userId, int $evolutionChainId): int
    {
        return $this->paraFamilia($userId, $evolutionChainId)->nido->nivel;
    }

    public function refugiosDeHabitat(int $userId, int $habitatId): RefugiosHabitatCollection
    {
        $granja = $this->granjaDe($userId, $habitatId);

        return $granja !== null ? $this->refugiosDeGranja($granja->id) : new RefugiosHabitatCollection();
    }

    public function factorAparicion(RefugiosHabitatCollection $refugios, int $evolutionChainId): float
    {
        $exclusivo = $refugios->exclusivo();

        if ($exclusivo !== null) {
            if ($exclusivo->insalubres) {
                return $exclusivo->chainId === $evolutionChainId ? 0.0 : 1.0;
            }

            return $exclusivo->chainId === $evolutionChainId ? 1.0 : 0.0;
        }

        $refugio = $refugios->paraChain($evolutionChainId);
        if ($refugio === null || $refugio->nivel === 0) {
            return 1.0;
        }

        return CalculadoraBonosGranja::factorRefugio($refugio->nivel, $refugio->insalubres)->factorAparicion;
    }

    public function factorCaptura(RefugiosHabitatCollection $refugios, int $evolutionChainId): float
    {
        $exclusivo = $refugios->exclusivo();

        if ($exclusivo !== null) {
            if ($exclusivo->insalubres) {
                return $exclusivo->chainId === $evolutionChainId ? 0.2 : 1.0;
            }

            return $exclusivo->chainId === $evolutionChainId ? 3.0 : 1.0;
        }

        $refugio = $refugios->paraChain($evolutionChainId);
        if ($refugio === null || $refugio->nivel === 0) {
            return 1.0;
        }

        return CalculadoraBonosGranja::factorRefugio($refugio->nivel, $refugio->insalubres)->factorCaptura;
    }

    private function refugiosDeGranja(int $farmId): RefugiosHabitatCollection
    {
        $refugios = new RefugiosHabitatCollection();

        foreach (Refugio::where('farm_id', $farmId)->get() as $refugio) {
            $refugios->add(new RefugioEstado(
                chainId: $refugio->evolution_chain_id,
                nivel: $refugio->level,
                insalubres: $refugio->unhealthy_conditions,
            ));
        }

        return $refugios;
    }

    private function nivelesComplementos(int $farmId, int $evolutionChainId): NivelesComplementoCollection
    {
        $niveles = new NivelesComplementoCollection();

        $refugio = Refugio::with('complementos')
            ->where('farm_id', $farmId)
            ->where('evolution_chain_id', $evolutionChainId)
            ->first();

        if ($refugio === null) {
            return $niveles;
        }

        foreach ($refugio->complementos as $complemento) {
            $niveles->add(new NivelComplemento(
                stat: $complemento->stat,
                nivel: $complemento->level,
            ));
        }

        return $niveles;
    }

    private function granjaDe(int $userId, int $habitatId): ?Granja
    {
        return Granja::withoutUserScope()
            ->where('user_id', $userId)
            ->where('habitat_id', $habitatId)
            ->first();
    }

    private function habitatDeFamilia(int $evolutionChainId): ?int
    {
        $habitatId = DB::table('pokemon_habitat')
            ->join('pokemon', 'pokemon.id', '=', 'pokemon_habitat.pokemon_id')
            ->where('pokemon.evolution_chain_id', $evolutionChainId)
            ->value('pokemon_habitat.habitat_id');

        return $habitatId !== null ? $habitatId : null;
    }

    private function neutros(): BonosFamilia
    {
        $nidoNivel = 0;

        return new BonosFamilia(
            nido: new NidoBonos(
                nivel: $nidoNivel,
                rangoIvs: CalculadoraBonosGranja::rangoIvs($nidoNivel),
                factorTiempo: 1.0,
            ),
            refugio: new RefugioBonos(
                nivel: 0,
                insalubres: false,
                factorAparicion: 1.0,
                factorCaptura: 1.0,
            ),
            evsFamilia: StatsValue::ceros(),
        );
    }
}
