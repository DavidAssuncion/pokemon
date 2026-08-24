<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

interface FabricaBatallaInterface
{
    public function createBattle(): AgregadoBatalla;

    public function crearEquiposMock(): EquipoBatalla;
}
