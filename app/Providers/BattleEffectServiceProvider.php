<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Battle\Domain\Effects\FabricaEfectos;
use Src\Battle\Domain\Enums\TipoClima;

class BattleEffectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registrar efectos de habilidad
        FabricaEfectos::registrarEfecto('armor_pierce', \Src\Battle\Domain\Effects\EfectoPerforacionArmadura::class, 0.10);
        FabricaEfectos::registrarEfecto('regen_def', \Src\Battle\Domain\Effects\EfectoRegeneracionDefensa::class, 10.0);
        FabricaEfectos::registrarEfecto('sandstorm_summoner', \Src\Battle\Domain\Effects\EfectoInvocadorClima::class, TipoClima::TORMENTA_ARENA);

        // Climas (EfectoInvocadorClima recibe el tipo de clima como 2º argumento)
        FabricaEfectos::registrarEfecto('sequia_summoner', \Src\Battle\Domain\Effects\EfectoInvocadorClima::class, TipoClima::SEQUIA);
        FabricaEfectos::registrarEfecto('diluvio_summoner', \Src\Battle\Domain\Effects\EfectoInvocadorClima::class, TipoClima::DILUVIO);
        FabricaEfectos::registrarEfecto('niebla_summoner', \Src\Battle\Domain\Effects\EfectoInvocadorClima::class, TipoClima::NIEBLA);
        FabricaEfectos::registrarEfecto('granizo_summoner', \Src\Battle\Domain\Effects\EfectoInvocadorClima::class, TipoClima::GRANIZO);
        FabricaEfectos::registrarEfecto('turbulencias_summoner', \Src\Battle\Domain\Effects\EfectoInvocadorClima::class, TipoClima::TURBULENCIAS);

        // Registrar efectos de objetos equipados
        FabricaEfectos::registrarItem('leftovers', \Src\Battle\Domain\Effects\EfectoRestos::class);
        FabricaEfectos::registrarItem('life_orb', \Src\Battle\Domain\Effects\EfectoOrbeVida::class);
    }
}
