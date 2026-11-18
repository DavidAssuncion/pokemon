<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Piezas;

use Src\Forja\Domain\Piezas\Collections\DefinicionesPiezaCollection;
use Src\Forja\Domain\Piezas\DataTransferObjects\BonosEquipamiento;
use Src\Forja\Domain\Piezas\DataTransferObjects\DefinicionPieza;
use Src\Forja\Domain\Ports\PiezasPorEspecie;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Collections\IntCollection;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\Exceptions\ItemDesconocido;
use Src\Shared\Domain\Items\ClavePieza;

/**
 * Agrega los bonos y habilidades de un conjunto de claves de pieza.
 *
 * Dominio puro: recibe las definiciones ya resueltas y las claves equipadas.
 * Las claves sin definición se ignoran (pieza de una especie inexistente) y
 * los `StatsValue` de origen nunca se mutan.
 */
final class AgregadorBonosPiezas
{
    public function paraClaves(
        DefinicionesPiezaCollection $definiciones,
        StringCollection $claves,
    ): BonosEquipamiento {
        $porClave = $this->indexarPorClave($definiciones);
        $bonos = StatsValue::ceros();
        $habilidades = [];

        foreach ($claves as $clave) {
            $definicion = $porClave[$clave] ?? null;

            if ($definicion === null) {
                continue;
            }

            $bonos = $this->sumar($bonos, $definicion->bonos);

            if ($definicion->habilidadClave !== null) {
                $habilidades[] = $definicion->habilidadClave;
            }
        }

        return new BonosEquipamiento($bonos, new StringCollection($habilidades));
    }

    /**
     * Resuelve las definiciones de un conjunto de claves equipadas.
     *
     * Reúne los `species_id` de las claves válidas (las corruptas se ignoran)
     * y delega la lectura en el puerto, una sola vez por llamada. Sin claves
     * válidas no se consulta el puerto.
     */
    public function definicionesDe(
        PiezasPorEspecie $piezasPorEspecie,
        StringCollection $claves,
    ): DefinicionesPiezaCollection {
        $especies = new IntCollection();

        foreach ($claves as $clave) {
            try {
                $especies->add(ClavePieza::desde($clave)->speciesId);
            } catch (ItemDesconocido) {
                continue;
            }
        }

        if ($especies->isEmpty()) {
            return new DefinicionesPiezaCollection();
        }

        return $piezasPorEspecie->definicionesDeEspecies($especies);
    }

    /**
     * @return array<string, DefinicionPieza>
     */
    private function indexarPorClave(DefinicionesPiezaCollection $definiciones): array
    {
        $porClave = [];

        foreach ($definiciones as $definicion) {
            $porClave[$definicion->clave()] = $definicion;
        }

        return $porClave;
    }

    private function sumar(StatsValue $total, StatsValue $bono): StatsValue
    {
        return new StatsValue(
            hp: ($total->hp ?? 0.0) + ($bono->hp ?? 0.0),
            attack: ($total->attack ?? 0.0) + ($bono->attack ?? 0.0),
            defense: ($total->defense ?? 0.0) + ($bono->defense ?? 0.0),
            spAtk: ($total->spAtk ?? 0.0) + ($bono->spAtk ?? 0.0),
            spDef: ($total->spDef ?? 0.0) + ($bono->spDef ?? 0.0),
            speed: ($total->speed ?? 0.0) + ($bono->speed ?? 0.0),
        );
    }
}
