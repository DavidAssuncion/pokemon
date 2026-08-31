<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Battle\Domain\Effects\EfectoInvocadorClima;
use Src\Battle\Domain\Effects\EfectoOrbeVida;
use Src\Battle\Domain\Effects\EfectoPerforacionArmadura;
use Src\Battle\Domain\Effects\EfectoRegeneracionDefensa;
use Src\Battle\Domain\Effects\EfectoRestos;
use Src\Battle\Domain\Effects\FabricaEfectos;
use Src\Battle\Domain\Enums\TipoClima;

class BattleEffectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FabricaEfectos::class, function (): FabricaEfectos {
            $fabrica = new FabricaEfectos();

            // Registrar efectos de habilidad
            $fabrica->registrarEfecto('armor_pierce', EfectoPerforacionArmadura::class, 0.10);
            $fabrica->registrarEfecto('regen_def', EfectoRegeneracionDefensa::class, 10.0);
            $fabrica->registrarEfecto('sandstorm_summoner', EfectoInvocadorClima::class, TipoClima::TORMENTA_ARENA);

            // Climas (EfectoInvocadorClima recibe el tipo de clima como 2º argumento)
            $fabrica->registrarEfecto('sequia_summoner', EfectoInvocadorClima::class, TipoClima::SEQUIA);
            $fabrica->registrarEfecto('diluvio_summoner', EfectoInvocadorClima::class, TipoClima::DILUVIO);
            $fabrica->registrarEfecto('niebla_summoner', EfectoInvocadorClima::class, TipoClima::NIEBLA);
            $fabrica->registrarEfecto('granizo_summoner', EfectoInvocadorClima::class, TipoClima::GRANIZO);
            $fabrica->registrarEfecto('turbulencias_summoner', EfectoInvocadorClima::class, TipoClima::TURBULENCIAS);

            // Registrar efectos de objetos equipados
            $fabrica->registrarItem('leftovers', EfectoRestos::class);
            $fabrica->registrarItem('life_orb', EfectoOrbeVida::class);

            return $fabrica;
        });
    }
}
