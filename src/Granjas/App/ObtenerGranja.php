<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use App\Enums\StatEnum;
use App\Models\Habitat;
use App\Models\Nido;
use App\Models\Refugio;
use Src\Granjas\Domain\CalculadoraBonosGranja;
use Src\Granjas\Domain\Collections\ComplementosGranjaCollection;
use Src\Granjas\Domain\Collections\FamiliasGranjaCollection;
use Src\Granjas\Domain\Collections\NivelesComplementoCollection;
use Src\Granjas\Domain\DataTransferObjects\ComplementoGranja;
use Src\Granjas\Domain\DataTransferObjects\ContextoComplementos;
use Src\Granjas\Domain\DataTransferObjects\ContextoFamiliaGranja;
use Src\Granjas\Domain\DataTransferObjects\CosteCaramelos;
use Src\Granjas\Domain\DataTransferObjects\EfectoRefugio;
use Src\Granjas\Domain\DataTransferObjects\FamiliaGranja;
use Src\Granjas\Domain\DataTransferObjects\GranjaDetalle;
use Src\Granjas\Domain\DataTransferObjects\HabitatGranja;
use Src\Granjas\Domain\DataTransferObjects\MejoraComplemento;
use Src\Granjas\Domain\DataTransferObjects\MejoraNido;
use Src\Granjas\Domain\DataTransferObjects\MejoraRefugio;
use Src\Granjas\Domain\DataTransferObjects\NidoGranja;
use Src\Granjas\Domain\DataTransferObjects\NivelComplemento;
use Src\Granjas\Domain\DataTransferObjects\RefugioGranja;
use Src\Granjas\Domain\DataTransferObjects\StatGranja;
use Src\Habitats\App\ObtenerFamiliasDisponibles;
use Src\Habitats\Presentation\DTOFamiliaDisponible;

/**
 * Construye el detalle completo de la granja de un hábitat: familias, nidos,
 * refugios, complementos y saldos de caramelos.
 */
final class ObtenerGranja
{
    public function __construct(
        private readonly AsegurarGranja $asegurarGranja,
        private readonly ObtenerFamiliasDisponibles $obtenerFamilias,
        private readonly InventarioGranja $inventario,
        private readonly ResolverBonosFamilia $resolverBonos,
    ) {
    }

    public function handle(int $userId, int $habitatId): GranjaDetalle
    {
        $granja = $this->asegurarGranja->para($userId, $habitatId);
        $habitat = Habitat::find($habitatId);
        $inventario = $this->inventario->resumen($userId);

        $nidos = $granja->nidos()->get()->keyBy(fn (Nido $nido): int => $nido->evolution_chain_id);
        $refugiosModelo = $granja->refugios()->get()
            ->keyBy(fn (Refugio $refugio): int => $refugio->evolution_chain_id);
        $contexto = new ContextoFamiliaGranja(
            refugios: $this->resolverBonos->refugiosDeHabitat($userId, $habitatId),
            inventario: $inventario,
        );

        $familias = new FamiliasGranjaCollection();

        foreach ($this->obtenerFamilias->handle($habitatId) as $familia) {
            if (! $familia instanceof DTOFamiliaDisponible) {
                continue;
            }

            $familias->add($this->familiaGranja(
                familia: $familia,
                nido: $nidos->get($familia->evolutionChainId),
                refugio: $refugiosModelo->get($familia->evolutionChainId),
                contexto: $contexto,
            ));
        }

        return new GranjaDetalle(
            id: $granja->id,
            habitat: new HabitatGranja(
                id: $habitatId,
                name: $habitat !== null ? $habitat->name : '',
            ),
            familias: $familias,
            inventario: $inventario,
        );
    }

    private function familiaGranja(
        DTOFamiliaDisponible $familia,
        ?Nido $nido,
        ?Refugio $refugio,
        ContextoFamiliaGranja $contexto,
    ): FamiliaGranja {
        $chainId = $familia->evolutionChainId;
        $nidoNivel = $nido !== null ? $nido->level : 0;
        $refugioNivel = $refugio !== null ? $refugio->level : 0;
        $insalubres = $refugio !== null && $refugio->unhealthy_conditions;
        $saldoFamilia = $contexto->inventario->familia->cantidadDe($chainId);

        $niveles = new NivelesComplementoCollection();
        if ($refugio !== null) {
            foreach ($refugio->complementos as $complemento) {
                $niveles->add(new NivelComplemento(
                    stat: $complemento->stat,
                    nivel: $complemento->level,
                ));
            }
        }

        $costeNido = $nidoNivel < CalculadoraBonosGranja::MAX_NIDO
            ? CalculadoraBonosGranja::costeNido($nidoNivel + 1)
            : null;
        $puedeNido = $costeNido !== null && $saldoFamilia >= $costeNido;

        $costeRefugio = $refugioNivel < CalculadoraBonosGranja::MAX_REFUGIO
            ? CalculadoraBonosGranja::costeRefugio($refugioNivel + 1)
            : null;
        $puedeRefugio = $costeRefugio !== null && $saldoFamilia >= $costeRefugio;

        $factorAparicion = $this->resolverBonos->factorAparicion($contexto->refugios, $chainId);
        $factorCaptura = $this->resolverBonos->factorCaptura($contexto->refugios, $chainId);

        return new FamiliaGranja(
            familia: $familia,
            nido: new NidoGranja(
                nivel: $nidoNivel,
                rangoIvs: CalculadoraBonosGranja::rangoIvs($nidoNivel),
                factorTiempo: CalculadoraBonosGranja::factorTiempo($nidoNivel),
                mejora: new MejoraNido(
                    max: CalculadoraBonosGranja::MAX_NIDO,
                    coste: $costeNido,
                    puede: $puedeNido,
                    motivo: $this->motivoMejora($costeNido, $puedeNido),
                ),
            ),
            refugio: new RefugioGranja(
                nivel: $refugioNivel,
                insalubres: $insalubres,
                efecto: new EfectoRefugio(
                    factorAparicion: $factorAparicion,
                    factorCaptura: $factorCaptura,
                    excluido: $factorAparicion <= 0.0 && ! $insalubres,
                    exclusivo: $refugioNivel === CalculadoraBonosGranja::MAX_REFUGIO && ! $insalubres,
                ),
                mejora: new MejoraRefugio(
                    max: CalculadoraBonosGranja::MAX_REFUGIO,
                    coste: $costeRefugio,
                    puede: $puedeRefugio,
                    motivo: $this->motivoMejora($costeRefugio, $puedeRefugio),
                ),
            ),
            complementos: $this->complementos(
                niveles: $niveles,
                contexto: new ContextoComplementos(
                    refugioNivel: $refugioNivel,
                    insalubres: $insalubres,
                    inventario: $contexto->inventario,
                    saldoFamilia: $saldoFamilia,
                ),
            ),
        );
    }

    private function complementos(
        NivelesComplementoCollection $niveles,
        ContextoComplementos $contexto,
    ): ComplementosGranjaCollection {
        $complementos = new ComplementosGranjaCollection();

        foreach (StatEnum::cases() as $stat) {
            $nivel = $niveles->nivelDe($stat);
            $coste = $nivel < CalculadoraBonosGranja::MAX_COMPLEMENTO
                ? CalculadoraBonosGranja::costeComplemento($nivel + 1)
                : null;

            $complementos->add(new ComplementoGranja(
                stat: new StatGranja(stat: $stat, nombre: $stat->label(), slug: $stat->slug()),
                nivel: $nivel,
                ev: CalculadoraBonosGranja::evComplemento($nivel),
                mejora: new MejoraComplemento(
                    max: CalculadoraBonosGranja::MAX_COMPLEMENTO,
                    coste: $coste,
                    puede: $this->puedeMejorarComplemento($coste, $stat, $contexto),
                    motivo: $this->motivoComplemento($coste, $stat, $contexto),
                ),
            ));
        }

        return $complementos;
    }

    private function puedeMejorarComplemento(
        ?CosteCaramelos $coste,
        StatEnum $stat,
        ContextoComplementos $contexto,
    ): bool {
        if ($coste === null || $contexto->refugioNivel === 0 || $contexto->insalubres) {
            return false;
        }

        return $contexto->saldoFamilia >= $coste->familia
            && $contexto->inventario->ev->cantidadDe($stat) >= $coste->ev;
    }

    private function motivoMejora(?int $coste, bool $puede): ?string
    {
        if ($puede) {
            return null;
        }

        return $coste === null ? 'max' : 'saldo';
    }

    private function motivoComplemento(
        ?CosteCaramelos $coste,
        StatEnum $stat,
        ContextoComplementos $contexto,
    ): ?string {
        if ($coste === null) {
            return 'max';
        }

        if ($contexto->refugioNivel === 0) {
            return 'refugio';
        }

        if ($contexto->insalubres) {
            return 'insalubres';
        }

        if ($contexto->saldoFamilia < $coste->familia
            || $contexto->inventario->ev->cantidadDe($stat) < $coste->ev
        ) {
            return 'saldo';
        }

        return null;
    }
}
