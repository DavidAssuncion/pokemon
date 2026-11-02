<?php

declare(strict_types=1);

namespace Tests\Support\Battle;

use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\Enums\TipoTerreno;

/**
 * Escenario reproducible del arnés IA vs IA.
 *
 * Contiene exclusivamente datos: la construcción del agregado, la semilla y
 * la ejecución viven en `ConstructorBatallaHarness` y `EjecutorBatallaHarness`.
 */
final readonly class EscenarioBatalla
{
    public function __construct(
        public string $nombre,
        public DatosPokemonBatallaCollection $equipo1,
        public DatosPokemonBatallaCollection $equipo2,
        public ?TipoClima $clima = null,
        public ?TipoTerreno $terreno = null,
        public int $semilla = 1,
        public int $maxRondas = 30,
    ) {
        if (trim($this->nombre) === '') {
            throw new \InvalidArgumentException('El escenario necesita un nombre');
        }

        if ($this->equipo1->isEmpty() || $this->equipo2->isEmpty()) {
            throw new \InvalidArgumentException("El escenario {$this->nombre} necesita combatientes en ambos equipos");
        }

        if ($this->semilla < 1) {
            throw new \InvalidArgumentException("La semilla del escenario {$this->nombre} debe ser mayor o igual que 1");
        }

        if ($this->maxRondas < 1) {
            throw new \InvalidArgumentException("El tope de rondas del escenario {$this->nombre} debe ser mayor o igual que 1");
        }
    }

    public function conSemilla(int $semilla): self
    {
        return new self(
            nombre: $this->nombre,
            equipo1: $this->equipo1,
            equipo2: $this->equipo2,
            clima: $this->clima,
            terreno: $this->terreno,
            semilla: $semilla,
            maxRondas: $this->maxRondas,
        );
    }

    public function conMaxRondas(int $maxRondas): self
    {
        return new self(
            nombre: $this->nombre,
            equipo1: $this->equipo1,
            equipo2: $this->equipo2,
            clima: $this->clima,
            terreno: $this->terreno,
            semilla: $this->semilla,
            maxRondas: $maxRondas,
        );
    }
}
