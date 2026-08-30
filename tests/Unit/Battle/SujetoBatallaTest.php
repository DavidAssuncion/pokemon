<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Observer\ObservadorBatalla;
use Src\Battle\Domain\Observer\SujetoBatalla;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Observer de batalla: SujetoBatalla notifica daño y debilitamiento.
 */
class SujetoBatallaTest extends TestCase
{
    use ConstruyeCombatientes;

    private function observadorPrueba(): ObservadorBatalla
    {
        return new class () implements ObservadorBatalla {
            public ?string $damagedTargetId = null;
            public ?float $damageAmount = null;
            public ?string $faintedId = null;

            public function onEndTurn(Combatiente $combatant): void
            {
            }

            public function onDamaged(Combatiente $target, float $daño): void
            {
                $this->damagedTargetId = $target->id();
                $this->damageAmount = $daño;
            }

            public function onFainted(Combatiente $combatant): void
            {
                $this->faintedId = $combatant->id();
            }
        };
    }

    private function combatienteDePrueba(string $id, string $nombre): Combatiente
    {
        return $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: $id,
            nombre: $nombre,
        );
    }

    public function test_notify_damaged_notifica_observador(): void
    {
        $sujeto = new SujetoBatalla();
        $observer = $this->observadorPrueba();
        $sujeto->attach($observer);

        $target = $this->combatienteDePrueba('d1', 'Objetivo');
        $sujeto->notifyDamaged($target, 42.0);

        $this->assertSame('d1', $observer->damagedTargetId);
        $this->assertSame(42.0, $observer->damageAmount);
    }

    public function test_notify_fainted_notifica_observador(): void
    {
        $sujeto = new SujetoBatalla();
        $observer = $this->observadorPrueba();
        $sujeto->attach($observer);

        $combatant = $this->combatienteDePrueba('d1', 'Debilitado');
        $sujeto->notifyFainted($combatant);

        $this->assertSame('d1', $observer->faintedId);
    }
}
