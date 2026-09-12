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
use Src\Battle\Domain\Enums\ClaveEfecto;
use Src\Battle\Domain\Enums\ClaveItem;
use Src\Battle\Domain\Enums\TipoClima;

class BattleEffectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FabricaEfectos::class, function (): FabricaEfectos {
            $fabrica = new FabricaEfectos();

            // Registrar efectos de habilidad
            $fabrica->registrarEfecto(ClaveEfecto::PERFORACION_ARMADURA, EfectoPerforacionArmadura::class, 0.10);
            $fabrica->registrarEfecto(ClaveEfecto::REGEN_DEF, EfectoRegeneracionDefensa::class, 10.0);
            $fabrica->registrarEfecto(ClaveEfecto::TORMENTA_ARENA, EfectoInvocadorClima::class, TipoClima::TORMENTA_ARENA);

            // Climas (EfectoInvocadorClima recibe el tipo de clima como 2º argumento)
            $fabrica->registrarEfecto(ClaveEfecto::SEQUIA, EfectoInvocadorClima::class, TipoClima::SEQUIA);
            $fabrica->registrarEfecto(ClaveEfecto::DILUVIO, EfectoInvocadorClima::class, TipoClima::DILUVIO);
            $fabrica->registrarEfecto(ClaveEfecto::NIEBLA, EfectoInvocadorClima::class, TipoClima::NIEBLA);
            $fabrica->registrarEfecto(ClaveEfecto::GRANIZO, EfectoInvocadorClima::class, TipoClima::GRANIZO);
            $fabrica->registrarEfecto(ClaveEfecto::TURBULENCIAS, EfectoInvocadorClima::class, TipoClima::TURBULENCIAS);

            // Registrar efectos de objetos equipados
            $fabrica->registrarItem(ClaveItem::RESTOS, EfectoRestos::class);
            $fabrica->registrarItem(ClaveItem::ORBE_VIDA, EfectoOrbeVida::class);

            return $fabrica;
        });
    }
}
