<?php

declare(strict_types=1);

namespace Src\Huevos\App;

use App\Models\Huevo;
use App\Models\Pokemon;
use App\Models\Reclutado;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Src\Granjas\App\ResolverBonosFamilia;
use Src\Granjas\Domain\CalculadoraBonosGranja;
use Src\Huevos\Domain\CalculadorEstadoHuevo;
use Src\Huevos\Domain\CalculadorTiemposHuevo;
use Src\Huevos\Domain\Collections\HuevoDisponibleCollection;
use Src\Huevos\Domain\Collections\HuevoResumenCollection;
use Src\Huevos\Domain\DataTransferObjects\ContenidoHuevo;
use Src\Huevos\Domain\DataTransferObjects\DatosTiempoHuevo;
use Src\Huevos\Domain\DataTransferObjects\EspecieHuevo;
use Src\Huevos\Domain\DataTransferObjects\HuevoDisponible;
use Src\Huevos\Domain\DataTransferObjects\HuevoResumen;
use Src\Huevos\Domain\DataTransferObjects\LimitesHuevos;
use Src\Huevos\Domain\DataTransferObjects\NidoHuevo;
use Src\Huevos\Domain\DataTransferObjects\ResumenHuevos;
use Src\Huevos\Domain\DataTransferObjects\TiemposHuevo;
use Src\Huevos\Domain\EstadoCalculadoHuevo;
use Src\Huevos\Domain\EstadoHuevo;
use Src\Pokemon\Domain\Stats\GeneradorIvs;
use Src\Shared\Domain\DataTransferObjects\PokemonResumen;

/**
 * Lista huevos del usuario, especies disponibles para crear huevos y límites.
 */
final class ListarHuevos
{
    public function __construct(
        private readonly ResolverBonosFamilia $bonosFamilia,
    ) {
    }

    /**
     * Resumen de huevos activos, familias disponibles y límites.
     *
     * Contrato de tiempos (B1): `tiempoTotalHoras` y `tiempoRestante` están en
     * la misma unidad — horas, float — y `progreso` es un entero 0-100.
     */
    public function listar(int $userId): ResumenHuevos
    {
        $huevos = Huevo::where('user_id', $userId)
            ->with('pokemon')
            ->whereNull('eclosionado_en')
            ->orderByDesc('id')
            ->get();

        $ahora = now();

        $huevosResumen = new HuevoResumenCollection();
        foreach ($huevos as $huevo) {
            $huevosResumen->add($this->serializarUno($huevo, $ahora));
        }

        return new ResumenHuevos(
            huevos: $huevosResumen,
            disponibles: $this->disponibles($userId),
            limites: $this->limites($huevos, $ahora),
        );
    }

    /**
     * Serializa un huevo individual (usado también por HuevoController::store).
     */
    public function serializarUno(Huevo $huevo, ?Carbon $ahora = null): HuevoResumen
    {
        $ahora ??= now();
        $captureRate = $huevo->capture_rate ?? 0;
        $hatch = $huevo->hatch ?? 1;
        $nidoNivel = $huevo->nido_nivel ?? 0;
        $estado = self::estadoDe($huevo, $ahora);
        $totalHoras = round(CalculadorTiemposHuevo::sumaHoras($captureRate, $hatch, $nidoNivel), 2);
        $rangoIvs = GeneradorIvs::rangoHuevo($nidoNivel);
        $factorTiempo = CalculadoraBonosGranja::factorTiempo($nidoNivel);

        return new HuevoResumen(
            id: $huevo->id,
            estado: $estado->estado->value,
            tiempos: new TiemposHuevo(
                totalHoras: $totalHoras,
                restanteHoras: $estado->restanteHoras,
                progreso: self::progresoDe($totalHoras, $estado->restanteHoras),
                puedeEclosionar: $estado->estado === EstadoHuevo::LISTO,
            ),
            contenido: new ContenidoHuevo(
                pokemon: self::resumenPokemon($huevo->pokemon),
                nido: new NidoHuevo(
                    nivel: $nidoNivel,
                    rangoIvs: $rangoIvs,
                    factorTiempo: $factorTiempo,
                ),
            ),
        );
    }

    /**
     * Familias con ≥2 reclutados DISTINTOS (pueden ser especies distintas de la
     * misma cadena), sin huevo activo de esa familia y cuyo representante tiene
     * hatch. 1 entrada por evolution_chain_id; se elige el miembro con menor
     * species_id (regla "primer integrante" del proyecto).
     */
    private function disponibles(int $userId): HuevoDisponibleCollection
    {
        $reclutados = Reclutado::with('pokemon')
            ->where('user_id', $userId)
            ->get()
            ->reject(
                fn (Reclutado $reclutado): bool => $reclutado->pokemon === null
                    || $reclutado->pokemon->evolution_chain_id === null,
            );

        $familiasActivas = Huevo::where('user_id', $userId)
            ->whereNull('eclosionado_en')
            ->whereNotNull('evolution_chain_id')
            ->pluck('evolution_chain_id')
            ->map(fn ($cadena): int => (int) $cadena);

        $grupos = $reclutados
            ->groupBy(fn (Reclutado $reclutado): ?int => $reclutado->pokemon->evolution_chain_id)
            ->filter(function (Collection $grupo, $cadena) use ($familiasActivas): bool {
                return is_int($cadena)
                    && $grupo->count() >= 2
                    && ! $familiasActivas->contains($cadena);
            });

        $disponibles = new HuevoDisponibleCollection();
        foreach ($grupos->sortKeys() as $cadena => $grupo) {
            /** @var Reclutado $representante */
            $representante = $grupo
                ->sortBy(fn (Reclutado $reclutado): int => $reclutado->pokemon->species_id)
                ->first();
            $pokemon = $representante->pokemon;

            if ($pokemon === null || $pokemon->hatch === null) {
                continue;
            }

            $chainId = (int) $cadena;
            $bonos = $this->bonosFamilia->paraFamilia($userId, $chainId);

            $disponibles->add(new HuevoDisponible(
                evolutionChainId: $chainId,
                pokemon: self::resumenPokemon($pokemon),
                especie: new EspecieHuevo(
                    captureRate: $pokemon->capture_rate,
                    hatch: $pokemon->hatch,
                ),
                nido: new NidoHuevo(
                    nivel: $bonos->nido->nivel,
                    rangoIvs: $bonos->nido->rangoIvs,
                    factorTiempo: $bonos->nido->factorTiempo,
                ),
            ));
        }

        return $disponibles;
    }

    /**
     * @param  Collection<int, Huevo>  $huevos
     */
    private function limites(Collection $huevos, Carbon $ahora): LimitesHuevos
    {
        $gestacionActiva = false;
        $incubacionActiva = false;

        foreach ($huevos as $huevo) {
            $estado = self::estadoDe($huevo, $ahora)->estado;

            if ($estado === EstadoHuevo::GESTANDO) {
                $gestacionActiva = true;
            }
            if ($estado === EstadoHuevo::INCUBANDO || $estado === EstadoHuevo::LISTO) {
                $incubacionActiva = true;
            }
        }

        return new LimitesHuevos(
            maxGestacion: 1,
            maxIncubando: 1,
            gestacionActiva: $gestacionActiva,
            incubacionActiva: $incubacionActiva,
        );
    }

    private static function progresoDe(float $totalHoras, float $restanteHoras): int
    {
        if ($totalHoras <= 0.0) {
            return 0;
        }

        return (int) round(max(0.0, min(100.0, ($totalHoras - $restanteHoras) / $totalHoras * 100)));
    }

    /**
     * Estado calculado de un huevo (requiere pokemon cargado con ->with).
     */
    private static function estadoDe(Huevo $huevo, Carbon $ahora): EstadoCalculadoHuevo
    {
        return CalculadorEstadoHuevo::estadoDe(
            $huevo->created_at,
            new DatosTiempoHuevo(
                captureRate: $huevo->capture_rate ?? 0,
                hatch: $huevo->hatch ?? 1,
                nidoNivel: $huevo->nido_nivel ?? 0,
            ),
            $ahora,
        );
    }

    private static function resumenPokemon(Pokemon $pokemon): PokemonResumen
    {
        return PokemonResumen::desdeIdNombre($pokemon->id, $pokemon->name);
    }
}
