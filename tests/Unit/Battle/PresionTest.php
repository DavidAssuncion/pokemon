<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\Effects\EfectoDanioFinTurno;
use Src\Battle\Domain\Enums\ObjetivoEfecto;
use Src\Battle\Infrastructure\FabricaBatallaMock;

class PresionTest extends TestCase
{
    #[Test]
    public function presion_reduce_ambas_barreras_del_rival_al_final_de_ronda(): void
    {
        $battle = (new FabricaBatallaMock())->createBattle();

        $portador = $battle->team1->combatientesCollection()->vivos()->en(0);
        $rival = $battle->team2->combatientesCollection()->vivos()->en(0);

        $this->assertNotNull($portador);
        $this->assertNotNull($rival);

        $maxFis = $rival->pokemon()->battleStats()->defenseHp;
        $maxEsp = $rival->pokemon()->battleStats()->spDefenseHp;

        $rival->setDefensaHpActual($maxFis);
        $rival->setDefensaEspHpActual($maxEsp);

        $portador->effects()->add(new EfectoDanioFinTurno(
            clave: 'presion',
            fraccion: 0.125,
            objetivo: ObjetivoEfecto::ESCUDOS_ENEMIGOS,
            afectaEscudos: true,
        ));

        $battle->triggerRoundEndEffects();

        $this->assertLessThan($maxFis, $rival->defensaHpActual());
        $this->assertLessThan($maxEsp, $rival->defensaEspHpActual());
    }
}
