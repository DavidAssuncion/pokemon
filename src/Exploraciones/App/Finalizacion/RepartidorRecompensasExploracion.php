<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Finalizacion;

use App\Models\ExploracionActiva;
use App\Models\User;
use App\Support\CargarMapaSendaJugador;
use Illuminate\Support\Collection as BaseCollection;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\Domain\EconomiaRecompensas;
use Src\Exploraciones\Domain\PerfilExploracion;
use Src\Exploraciones\Domain\Recompensas\CalculadoraRecompensasExpedicion;
use Src\Exploraciones\Domain\Recompensas\ClasificadorEventosExploracion;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Exploraciones\Domain\ValueObjects\ColeccionEventosExploracion;
use Src\Granjas\App\ResolverBonosFamilia;
use Src\Granjas\Domain\Collections\RefugiosHabitatCollection;
use Src\Shared\Bus\UnitOfWork;
use Src\Shared\Domain\Sendas\MapaSendaJugador;

/**
 * Orquesta el reparto de recompensas de una expedición: usuario, bonos,
 * Senda, cálculo, persistencia, Pokédex y resultado. El cálculo puro vive en
 * dominio; este componente mantiene el orden de persistencia original.
 */
final class RepartidorRecompensasExploracion
{
    public function __construct(
        private readonly CalculadoraRecompensasExpedicion $calculadora,
        private readonly MapeadorPokemonsDerrotados $mapeador,
        private readonly ClasificadorEventosExploracion $clasificador,
        private readonly GeneradorTiradaCapturaExpedicion $tirada,
        private readonly ResolutorBotinExploracion $botin,
        private readonly EscritorEventosResultado $escritor,
        private readonly PersistirRecompensas $persistir,
        private readonly UnitOfWork $unitOfWork,
        private readonly ResolverBonosFamilia $bonosFamilia,
        private readonly CargarMapaSendaJugador $cargarSenda,
        private readonly CalculadoraTiemposExpedicion $tiempos,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $bitacora
     * @param  BaseCollection<string, mixed>  $eventos
     * @param  callable(list<array<string, mixed>>):array{encuentros: int, victorias: int, huidas: int, emboscadas: int, contratiempos: int}  $incidentes
     * @param  callable():int  $bonusCaramelos
     * @param  callable():float|null  $aleatorio
     */
    public function repartir(ExploracionActiva $exploracion, DatosDerrotadosExpedicion $datos, array $bitacora, BaseCollection $eventos, string $categoria, float $multiplicador, PerfilExploracion $perfil, callable $incidentes, callable $bonusCaramelos, ?callable $aleatorio = null): void
    {
        $usuario = $exploracion->user;
        $refugios = $this->bonosFamilia->refugiosDeHabitat($exploracion->user_id, $exploracion->habitat_id);

        $economia = EconomiaRecompensas::individual();
        $senda = $this->cargarSenda->para($exploracion->user_id);

        [$recompensas, $delHabitat] = $this->calcularRecompensas($exploracion, $datos, $bitacora, $eventos, $perfil, $usuario, $refugios, $senda, $economia, $multiplicador, $bonusCaramelos, $aleatorio);

        $this->persistir->persistir($recompensas, $exploracion->reclutado, $usuario);
        $bitacoraEventos = ColeccionEventosExploracion::desdeArray($bitacora);
        $this->despacharAvistados($this->clasificador->idsAvistados($bitacoraEventos), $exploracion->user_id);

        $tiempoPerdido = (int) $eventos->get('tiempo_perdido', 0);
        $this->registrarResultado(
            $exploracion,
            $datos,
            $recompensas,
            $categoria,
            $tiempoPerdido,
            $bitacora,
            $eventos,
            $delHabitat,
            $incidentes,
        );

        $exploracion->update(['regreso' => $this->tiempos->ahora(), 'eventos' => $eventos]);
    }

    /**
     * @param  list<array<string, mixed>>  $bitacora
     * @param  BaseCollection<string, mixed>  $eventos
     * @param  callable():int  $bonusCaramelos
     * @param  callable():float|null  $aleatorio
     * @return array{ResultadoRecompensas, bool}
     */
    private function calcularRecompensas(ExploracionActiva $exploracion, DatosDerrotadosExpedicion $datos, array $bitacora, BaseCollection $eventos, PerfilExploracion $perfil, ?User $usuario, RefugiosHabitatCollection $refugios, MapaSendaJugador $senda, EconomiaRecompensas $economia, float $multiplicador, callable $bonusCaramelos, ?callable $aleatorio): array
    {
        $multiplicadorCaramelos = $multiplicador * $perfil->multiplicadorCaramelos;
        $multiplicadorExp = $multiplicador * $perfil->multiplicadorExp;

        $recompensas = $this->calcularBase(
            $exploracion,
            $datos,
            $usuario,
            $refugios,
            $senda,
            $economia,
            $multiplicadorCaramelos,
            $multiplicadorExp,
            $perfil,
            $aleatorio,
        );

        return $this->aplicarHallazgosYBotin(
            $exploracion,
            $datos,
            $bitacora,
            $eventos,
            $recompensas,
            $perfil,
            $multiplicadorCaramelos,
            $economia,
            $bonusCaramelos,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $bitacora
     * @param  BaseCollection<string, mixed>  $eventos
     * @param  callable():int  $bonusCaramelos
     * @return array{ResultadoRecompensas, bool}
     */
    private function aplicarHallazgosYBotin(ExploracionActiva $exploracion, DatosDerrotadosExpedicion $datos, array $bitacora, BaseCollection $eventos, ResultadoRecompensas $recompensas, PerfilExploracion $perfil, float $multiplicadorCaramelos, EconomiaRecompensas $economia, callable $bonusCaramelos): array
    {
        $bitacoraEventos = ColeccionEventosExploracion::desdeArray($bitacora);
        $recompensas = $this->sumarHallazgos(
            $recompensas,
            $bitacoraEventos,
            $datos,
            $exploracion,
            $multiplicadorCaramelos,
            $economia,
            $bonusCaramelos,
        );

        return $this->aplicarBotinYPerdidas($exploracion, $perfil, $bitacora, $eventos, $recompensas);
    }

    private function calcularBase(ExploracionActiva $exploracion, DatosDerrotadosExpedicion $datos, ?User $usuario, RefugiosHabitatCollection $refugios, MapaSendaJugador $senda, EconomiaRecompensas $economia, float $multiplicadorCaramelos, float $multiplicadorExp, PerfilExploracion $perfil, ?callable $aleatorio): ResultadoRecompensas
    {
        return $this->calculadora->calcularBase(
            $datos->derrotados,
            $this->tirada->crear(
                $aleatorio,
                $this->bonosFamilia,
                $refugios,
                $senda,
                nivelHabitat: $exploracion->nivel,
                factorModo: $perfil->factorCaptura,
            ),
            $usuario !== null ? $usuario->nivel() : 1,
            $multiplicadorCaramelos,
            $economia,
            $multiplicadorExp,
            $senda,
        );
    }

    private function sumarHallazgos(
        ResultadoRecompensas $recompensas,
        ColeccionEventosExploracion $bitacoraEventos,
        DatosDerrotadosExpedicion $datos,
        ExploracionActiva $exploracion,
        float $multiplicadorCaramelos,
        EconomiaRecompensas $economia,
        callable $bonusCaramelos,
    ): ResultadoRecompensas {
        $hallazgos = $this->clasificador->hallazgos($bitacoraEventos);

        return $this->calculadora->sumarHallazgos(
            $recompensas,
            $hallazgos,
            $this->mapeador->chainPorPokemon($hallazgos, $datos->pokemons),
            $this->multiplicadorCaramelosEquipo($exploracion, $multiplicadorCaramelos, $economia->factor),
            $bonusCaramelos(),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $bitacora
     * @param  BaseCollection<string, mixed>  $eventos
     * @return array{ResultadoRecompensas, bool}
     */
    private function aplicarBotinYPerdidas(ExploracionActiva $exploracion, PerfilExploracion $perfil, array $bitacora, BaseCollection $eventos, ResultadoRecompensas $recompensas): array
    {
        $delHabitat = $this->botin->delHabitat($exploracion, $eventos);
        $recompensas = $this->calculadora->sumarBotin(
            $recompensas,
            $this->botin->botin($exploracion, $perfil, $bitacora, $eventos, $delHabitat),
        );

        $objetosPerdidos = $this->calculadora->calcularPerdidas(
            $recompensas,
            $this->botin->terminoPorDerrota($bitacora, $eventos),
        );

        if ($objetosPerdidos !== []) {
            $recompensas = $this->calculadora->restarPerdidas($recompensas, $objetosPerdidos);
            $this->escritor->escribirPerdidas($eventos, $objetosPerdidos);
        }

        return [$recompensas, $delHabitat];
    }

    /**
     * @param  list<array<string, mixed>>  $bitacora
     * @param  BaseCollection<string, mixed>  $eventos
     * @param  callable(list<array<string, mixed>>):array{encuentros: int, victorias: int, huidas: int, emboscadas: int, contratiempos: int}  $incidentes
     */
    private function registrarResultado(ExploracionActiva $exploracion, DatosDerrotadosExpedicion $datos, ResultadoRecompensas $recompensas, string $categoria, int $tiempoPerdido, array $bitacora, BaseCollection $eventos, bool $delHabitat, callable $incidentes): void
    {
        $this->escritor->registrar(
            $exploracion,
            $recompensas,
            $datos->pokemons,
            $datos->miembrosPorCadena,
            $datos->idsDerrotados,
            $categoria,
            $this->tiempos->duracionReal($exploracion, $tiempoPerdido),
            $tiempoPerdido,
            $incidentes($bitacora),
            $eventos,
            $delHabitat,
        );
    }

    private function multiplicadorCaramelosEquipo(
        ExploracionActiva $exploracion,
        float $multiplicadorCategoria,
        float $factorEconomia,
    ): float {
        $reclutado = $exploracion->reclutado;

        if ($reclutado === null) {
            return $multiplicadorCategoria * $factorEconomia;
        }

        return $multiplicadorCategoria
            * $reclutado->rol()->multiplicadorCaramelosHallazgo()
            * $factorEconomia;
    }

    /**
     * @param  list<int>  $pokemonIds
     */
    private function despacharAvistados(array $pokemonIds, int $userId): void
    {
        if ($pokemonIds === []) {
            return;
        }

        $this->unitOfWork->afterCommit(function () use ($pokemonIds, $userId): void {
            foreach (array_unique($pokemonIds) as $pokemonId) {
                \App\Jobs\ActualizarPokedexJob::dispatch($userId, $pokemonId, 'AVISTADO');
            }
        });
    }
}
