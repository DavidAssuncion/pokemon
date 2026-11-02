<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Src\Battle\Domain\Combatiente;
use Src\Shared\Domain\Sendas\MapaSendaJugador;
use Src\Shared\Domain\Sendas\NivelSenda;
use Src\Shared\Tipos\TipoPokemon;

/**
 * El mapa de Sendas del combatiente sobrevive a la sesión y los payloads
 * anteriores (sin clave `senda`) se restauran como mapa vacío.
 */
class CombatienteSerializacionSendaTest extends TestCase
{
    use ConstruyeCombatientes;

    #[Test]
    public function un_combatiente_nuevo_tiene_el_mapa_vacio(): void
    {
        $combatiente = $this->combatiente(id: 'c1', tipos: [TipoPokemon::NORMAL]);

        $this->assertSame([], $combatiente->senda()->toArray());
        $this->assertSame(NivelSenda::N1, $combatiente->senda()->nivel(TipoPokemon::NORMAL));
    }

    #[Test]
    public function payload_antiguo_sin_senda_se_restaura_como_mapa_vacio(): void
    {
        $combatiente = $this->combatiente(id: 'c1', tipos: [TipoPokemon::NORMAL]);
        $combatiente->setSenda(MapaSendaJugador::desdeArray([TipoPokemon::FUEGO->value => 5]));

        $payload = $combatiente->__serialize();
        unset($payload['senda']);

        $restaurado = $this->instanciaSinConstructor();
        $restaurado->__unserialize($payload);

        $this->assertSame([], $restaurado->senda()->toArray());
        $this->assertSame('c1', $restaurado->id());
    }

    #[Test]
    public function payload_nuevo_conserva_el_mapa_en_el_round_trip(): void
    {
        $combatiente = $this->combatiente(id: 'c1', tipos: [TipoPokemon::NORMAL]);
        $combatiente->setSenda(MapaSendaJugador::desdeArray([
            TipoPokemon::FUEGO->value => 5,
            TipoPokemon::AGUA->value => 4,
        ]));

        /** @var Combatiente $restaurado */
        $restaurado = unserialize(serialize($combatiente));

        $this->assertSame(
            [TipoPokemon::FUEGO->value => 5, TipoPokemon::AGUA->value => 4],
            $restaurado->senda()->toArray(),
        );
        $this->assertSame(NivelSenda::N5, $restaurado->senda()->nivel(TipoPokemon::FUEGO));
        $this->assertSame(NivelSenda::N4, $restaurado->senda()->nivel(TipoPokemon::AGUA));
        $this->assertSame(NivelSenda::N1, $restaurado->senda()->nivel(TipoPokemon::PLANTA));
    }

    private function instanciaSinConstructor(): Combatiente
    {
        /** @var Combatiente $instancia */
        $instancia = (new ReflectionClass(Combatiente::class))->newInstanceWithoutConstructor();

        return $instancia;
    }
}
