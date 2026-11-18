<?php

declare(strict_types=1);

namespace App\Support\Piezas;

use Src\Equipos\App\Piezas\ObtenerPiezas;
use Src\Equipos\Domain\ValueObjects\EquipamientoPiezas;
use Src\Forja\Domain\Piezas\AgregadorBonosPiezas;
use Src\Forja\Domain\Piezas\Collections\DefinicionesPiezaCollection;
use Src\Forja\Domain\Ports\PiezasPorEspecie;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Collections\IntCollection;
use Src\Shared\Collections\StringCollection;
use stdClass;

/**
 * Presentador de las piezas equipadas de un listado de reclutados.
 *
 * Resuelve todo el listado con UNA consulta de equipamiento y UNA de
 * definiciones por especie (sin N+1) y expone los 3 slots de cada reclutado y
 * los bonos agregados que consume el CP. No es un VO de dominio: vive en
 * `app/Support` porque solo compone el contrato de presentación.
 */
final class PiezasReclutado
{
    /** @var array<int, EquipamientoPiezas> */
    private array $equipamientos = [];

    private readonly DefinicionesPiezaCollection $definiciones;

    public function __construct(
        ObtenerPiezas $obtenerPiezas,
        PiezasPorEspecie $piezasPorEspecie,
        private readonly AgregadorBonosPiezas $agregadorBonos,
        int $userId,
        IntCollection $reclutadoIds,
    ) {
        $equipamientos = $obtenerPiezas->paraReclutados($userId, $reclutadoIds);
        $claves = new StringCollection();

        foreach ($reclutadoIds as $reclutadoId) {
            $equipamiento = $equipamientos->obtener($reclutadoId);
            $this->equipamientos[$reclutadoId] = $equipamiento;

            foreach ($equipamiento->todas() as $clave) {
                $claves->add($clave);
            }
        }

        $this->definiciones = $piezasPorEspecie->definicionesDeEspecies(
            SlotsPiezas::especiesDe($claves),
        );
    }

    /**
     * @return list<array{
     *     slot: int,
     *     clave: string|null,
     *     tipo: string|null,
     *     nombre: string|null,
     *     icono: string|null,
     *     stats: array<string, int>|stdClass,
     *     habilidad: array{clave: string, nombre: string, descripcion: string}|null
     * }>
     */
    public function slotsDe(int $reclutadoId): array
    {
        return SlotsPiezas::construir(
            $this->equipamientoDe($reclutadoId),
            $this->definiciones,
        );
    }

    /**
     * Bonos agregados de las piezas equipadas; ceros si el reclutado no tiene
     * ninguna pieza resoluble.
     */
    public function bonosDe(int $reclutadoId): StatsValue
    {
        return $this->agregadorBonos
            ->paraClaves($this->definiciones, $this->equipamientoDe($reclutadoId)->todas())
            ->bonos;
    }

    private function equipamientoDe(int $reclutadoId): EquipamientoPiezas
    {
        return $this->equipamientos[$reclutadoId] ?? EquipamientoPiezas::vacio();
    }
}
