<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Procesamiento;

use App\Models\ExploracionActiva;
use Illuminate\Support\Collection;
use Src\Exploraciones\Domain\CapacidadesStats;
use Src\Exploraciones\Domain\PerfilExploracion;
use Src\Exploraciones\Domain\Tick\VentanaTick;
use Src\Exploraciones\Domain\ValueObjects\EstadoExplorador;
use Src\Exploraciones\Domain\ValueObjects\PoolHabitat;

/**
 * Estado mutable compartido durante la resolución de un tick.
 *
 * Evita pasar el estado del explorador y los acumuladores por referencia entre
 * los resolutores. La bitácora y el resto del payload siguen viviendo en la
 * colección `eventos` para conservar el contrato persistido.
 */
final class ContextoTickExploracion
{
    public EstadoExplorador $estado;

    public int $perdidoTick = 0;

    public bool $derrota = false;

    public bool $retirada = false;

    /**
     * @param  Collection<string, mixed>  $eventos
     */
    public function __construct(
        public readonly ExploracionActiva $exploracion,
        public readonly Collection $eventos,
        public readonly CapacidadesStats $capacidades,
        public readonly int $dificultad,
        public readonly PoolHabitat $pool,
        public readonly PerfilExploracion $perfil,
        public readonly VentanaTick $ventana,
    ) {
        $this->estado = self::leerEstado($eventos);
    }

    /**
     * Estado persistido del explorador (eventos['explorador']) o vacío.
     *
     * @param  Collection<string, mixed>  $eventos
     */
    private static function leerEstado(Collection $eventos): EstadoExplorador
    {
        /** @var array<string, mixed>|null $estado */
        $estado = $eventos->get('explorador');

        return is_array($estado) ? EstadoExplorador::desdeArray($estado) : EstadoExplorador::vacio();
    }
}
