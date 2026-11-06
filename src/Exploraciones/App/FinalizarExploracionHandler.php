<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\ExploracionActiva;
use App\Support\CargarMapaSendaJugador;
use App\Support\ConsultorPlantacionesFactory;
use Closure;
use Illuminate\Support\Collection as BaseCollection;
use LogicException;
use Src\Exploraciones\App\Finalizacion\CalculadoraTiemposExpedicion;
use Src\Exploraciones\App\Finalizacion\EscritorEventosResultado;
use Src\Exploraciones\App\Finalizacion\GeneradorTiradaCapturaExpedicion;
use Src\Exploraciones\App\Finalizacion\MapeadorPokemonsDerrotados;
use Src\Exploraciones\App\Finalizacion\MetodosCompatibilidadFinalizacion;
use Src\Exploraciones\App\Finalizacion\RepartidorRecompensasExploracion;
use Src\Exploraciones\App\Finalizacion\ResolutorBotinExploracion;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Domain\EvaluadorExploracion;
use Src\Exploraciones\Domain\Incidentes\CalculadoraIncidentes;
use Src\Exploraciones\Domain\ModoExploracion;
use Src\Exploraciones\Domain\PerfilExploracion;
use Src\Exploraciones\Domain\Ports\ConsultorPlantaciones;
use Src\Exploraciones\Domain\Recompensas\CalculadoraRecompensasExpedicion;
use Src\Exploraciones\Domain\Recompensas\ClasificadorEventosExploracion;
use Src\Exploraciones\Domain\ValueObjects\ColeccionEventosExploracion;
use Src\Exploraciones\Presentation\DTOIncidentesExploracion;
use Src\Exploraciones\Presentation\TransformadorResultadoExploracion;
use Src\Granjas\App\ResolverBonosFamilia;
use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandHandler;
use Src\Shared\Bus\UnitOfWork;
use Src\Shared\Domain\Ports\GeneradorAleatorio;
use Src\Shared\Domain\Ports\Reloj;
use Src\Shared\Domain\Random\GeneradorAleatorioPredeterminado;
use Src\Shared\Domain\Time\RelojPredeterminado;

/**
 * Cierra una expedición: calcula y persiste recompensas, escribe el resultado
 * y marca el regreso. Idempotente. El cálculo puro vive en Domain/Recompensas.
 */
final class FinalizarExploracionHandler implements CommandHandler
{
    use MetodosCompatibilidadFinalizacion;

    private readonly ?Closure $aleatorio;
    private readonly MapeadorPokemonsDerrotados $mapeador;
    private readonly RepartidorRecompensasExploracion $repartidor;

    public function __construct(
        UnitOfWork $unitOfWork,
        CalculadorRecompensas $calculador,
        PersistirRecompensas $persistir,
        TransformadorResultadoExploracion $transformador,
        ?callable $aleatorio = null,
        ?ResolverBonosFamilia $bonosFamilia = null,
        ?ConsultorPlantaciones $consultorPlantaciones = null,
        ?EsDelHabitat $esDelHabitat = null,
        ?Reloj $reloj = null,
        ?CargarMapaSendaJugador $cargarSenda = null,
        ?GeneradorAleatorio $generador = null,
    ) {
        $this->aleatorio = $aleatorio !== null ? Closure::fromCallable($aleatorio) : null;
        $this->tiempos = new CalculadoraTiemposExpedicion($reloj ?? new RelojPredeterminado());
        $this->clasificador = new ClasificadorEventosExploracion();
        $this->calculadora = new CalculadoraRecompensasExpedicion($calculador);
        $this->mapeador = new MapeadorPokemonsDerrotados($this->clasificador);
        $this->botin = new ResolutorBotinExploracion(
            $this->tiempos,
            $consultorPlantaciones ?? ConsultorPlantacionesFactory::crear(),
            $esDelHabitat,
        );
        $this->repartidor = new RepartidorRecompensasExploracion(
            $this->calculadora,
            $this->mapeador,
            $this->clasificador,
            new GeneradorTiradaCapturaExpedicion($generador ?? new GeneradorAleatorioPredeterminado()),
            $this->botin,
            new EscritorEventosResultado($transformador),
            $persistir,
            $unitOfWork,
            $bonosFamilia ?? new ResolverBonosFamilia(),
            $cargarSenda ?? new CargarMapaSendaJugador(),
            $this->tiempos,
        );
    }

    public function handle(Command $command): mixed
    {
        if (! $command instanceof FinalizarExploracionCommand) {
            throw new LogicException('FinalizarExploracionHandler requires a FinalizarExploracionCommand.');
        }

        $exploracion = $command->exploracion->refresh();

        if ($exploracion->regreso !== null) {
            return null;
        }

        /** @var BaseCollection<string, mixed> $eventos */
        $eventos = $exploracion->eventos ?? collect();
        /** @var list<array<string, mixed>> $bitacora */
        $bitacora = $eventos->get('bitacora', []);
        $bitacoraEventos = ColeccionEventosExploracion::desdeArray($bitacora);
        $datos = $this->mapeador->mapear($bitacoraEventos);
        $categoria = EvaluadorExploracion::categoriaFinalDeEventos($bitacoraEventos);
        $perfil = PerfilExploracion::de(ModoExploracion::desde($exploracion->modo));

        $this->repartidor->repartir(
            $exploracion,
            $datos,
            $bitacora,
            $eventos,
            $categoria,
            EvaluadorExploracion::multiplicador($categoria),
            $perfil,
            fn (array $bitacora): array => $this->incidentes($bitacora)->toArray(),
            fn (): int => $this->bonusCaramelosRecoleccion($exploracion),
            $this->aleatorio,
        );

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $bitacora
     */
    private function incidentes(array $bitacora): DTOIncidentesExploracion
    {
        $incidentes = (new CalculadoraIncidentes())->calcular($bitacora);

        return new DTOIncidentesExploracion(
            encuentros: $incidentes->encuentros,
            victorias: $incidentes->victorias,
            huidas: $incidentes->huidas,
            emboscadas: $incidentes->emboscadas,
            contratiempos: $incidentes->contratiempos,
        );
    }

    /**
     * Bonus de caramelos por recolección del rango Recolector del reclutado
     * (0/1/2/3 según dificultad del hábitat). Sin reclutado/usuario → 0.
     */
    private function bonusCaramelosRecoleccion(ExploracionActiva $exploracion): int
    {
        $reclutado = $exploracion->reclutado;
        $usuario = $exploracion->user;

        if ($reclutado === null || $usuario === null) {
            return 0;
        }

        $dificultad = $exploracion->habitat?->minLvlParaNivel($exploracion->nivel)
            ?? EvaluadorExploracion::dificultad('normal', (int) ($exploracion->habitat?->peligro ?? 1));

        return FabricaCapacidadesStats::desdeReclutado($reclutado, $usuario)->bonusCaramelosRecoleccion($dificultad);
    }

}
