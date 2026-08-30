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
        $this->app->singleton(FabricaEfectos::class, function (): FabricaEfectos {
            $fabrica = new FabricaEfectos();

            // Registrar efectos de habilidad
            $fabrica->registrarEfecto('armor_pierce', \Src\Battle\Domain\Effects\EfectoPerforacionArmadura::class, 0.10);
            $fabrica->registrarEfecto('regen_def', \Src\Battle\Domain\Effects\EfectoRegeneracionDefensa::class, 10.0);
            $fabrica->registrarEfecto('sandstorm_summoner', \Src\Battle\Domain\Effects\EfectoInvocadorClima::class, TipoClima::TORMENTA_ARENA);

            // Climas (EfectoInvocadorClima recibe el tipo de clima como 2º argumento)
            $fabrica->registrarEfecto('sequia_summoner', \Src\Battle\Domain\Effects\EfectoInvocadorClima::class, TipoClima::SEQUIA);
            $fabrica->registrarEfecto('diluvio_summoner', \Src\Battle\Domain\Effects\EfectoInvocadorClima::class, TipoClima::DILUVIO);
            $fabrica->registrarEfecto('niebla_summoner', \Src\Battle\Domain\Effects\EfectoInvocadorClima::class, TipoClima::NIEBLA);
            $fabrica->registrarEfecto('granizo_summoner', \Src\Battle\Domain\Effects\EfectoInvocadorClima::class, TipoClima::GRANIZO);
            $fabrica->registrarEfecto('turbulencias_summoner', \Src\Battle\Domain\Effects\EfectoInvocadorClima::class, TipoClima::TURBULENCIAS);

            // Registrar efectos de objetos equipados
            $fabrica->registrarItem('leftovers', \Src\Battle\Domain\Effects\EfectoRestos::class);
            $fabrica->registrarItem('life_orb', \Src\Battle\Domain\Effects\EfectoOrbeVida::class);

            return $fabrica;
        });
    }
}
