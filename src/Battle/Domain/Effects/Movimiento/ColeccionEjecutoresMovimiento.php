<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Registro de ejecutores de ataques especiales. Una clave ausente no hace
 * nada: el movimiento conserva su daño normal y su perfil numérico.
 */
class ColeccionEjecutoresMovimiento
{
    /** @var array<string, InterfazEjecutorMovimiento> */
    private array $ejecutores = [];

    public static function conEjecutoresPorDefecto(): self
    {
        $coleccion = new self();
        $coleccion->registrar(new EjecutorDrenadoras());
        $coleccion->registrar(new EjecutorMaldicion());
        $coleccion->registrar(new EjecutorCantoMortal());
        $coleccion->registrar(new EjecutorDesafio());
        $coleccion->registrar(new EjecutorTenaza());
        $coleccion->registrar(new EjecutorBrechaNegra());
        $coleccion->registrar(new EjecutorRepresionMetal());
        $coleccion->registrar(new EjecutorSuperdiente());
        $coleccion->registrar(new EjecutorPersecucion());
        $coleccion->registrar(new EjecutorTrampa(ClaveEfectoMovimiento::PUAS));
        $coleccion->registrar(new EjecutorTrampa(ClaveEfectoMovimiento::PUAS_TOXICAS));
        $coleccion->registrar(new EjecutorTrampa(ClaveEfectoMovimiento::TRAMPA_ROCAS));
        $coleccion->registrar(new EjecutorPantalla(ClaveEfectoMovimiento::PANTALLA_LUZ));
        $coleccion->registrar(new EjecutorPantalla(ClaveEfectoMovimiento::REFLEJO));
        $coleccion->registrar(new EjecutorMarionetaInerte());
        $coleccion->registrar(new EjecutorTrickRoom());
        $coleccion->registrar(new EjecutorTailwind());
        $coleccion->registrar(new EjecutorMarca());
        $coleccion->registrar(new EjecutorVinculoDano());
        $coleccion->registrar(new EjecutorRevivir());
        $coleccion->registrar(new EjecutorInvocacion());
        $coleccion->registrar(new EjecutorInteraccionObjeto());
        $coleccion->registrar(new EjecutorInteraccionHabilidad());
        $coleccion->registrar(new EjecutorSustituto());
        $coleccion->registrar(new EjecutorCambioTipo());
        $coleccion->registrar(new EjecutorDanoDiferido());
        $coleccion->registrar(new EjecutorMeteorobola());
        $coleccion->registrar(new EjecutorPulsoCampo());
        $coleccion->registrar(new EjecutorFuriaTaurina());
        $coleccion->registrar(new EjecutorHomenajePostumo());
        $coleccion->registrar(new EjecutorAllanadorFerreo());
        $coleccion->registrar(new EjecutorPaliza());
        $coleccion->registrar(new EjecutorAuroraVeil());
        $coleccion->registrar(new EjecutorTerreno(ClaveEfectoMovimiento::TERRENO_ELECTRICO, \Src\Battle\Domain\Enums\TipoTerreno::ELECTRICO));
        $coleccion->registrar(new EjecutorTerreno(ClaveEfectoMovimiento::TERRENO_HIERBA, \Src\Battle\Domain\Enums\TipoTerreno::HIERBA));
        $coleccion->registrar(new EjecutorTerreno(ClaveEfectoMovimiento::TERRENO_PSIQUICO, \Src\Battle\Domain\Enums\TipoTerreno::PSIQUICO));
        $coleccion->registrar(new EjecutorTerreno(ClaveEfectoMovimiento::TERRENO_NIEBLA, \Src\Battle\Domain\Enums\TipoTerreno::NIEBLA));
        $coleccion->registrar(new EjecutorMudSport());
        $coleccion->registrar(new EjecutorRototiller());
        $coleccion->registrar(new EjecutorHaze());
        $coleccion->registrar(new EjecutorNieblaClara());
        $coleccion->registrar(new EjecutorRefresh());
        $coleccion->registrar(new EjecutorPsicocarga());
        $coleccion->registrar(new EjecutorFocoEnergia());
        $coleccion->registrar(new EjecutorDescanso());
        $coleccion->registrar(new EjecutorDivideDolor());
        $coleccion->registrar(new EjecutorMofa());
        $coleccion->registrar(new EjecutorIntercambioStats(ClaveEfectoMovimiento::INTERCAMBIO_PODER, \Src\Battle\Domain\Enums\StatClave::ATAQUE));
        $coleccion->registrar(new EjecutorIntercambioStats(ClaveEfectoMovimiento::INTERCAMBIO_DEFENSA, \Src\Battle\Domain\Enums\StatClave::DEFENSA));
        $coleccion->registrar(new EjecutorIntercambioStats(ClaveEfectoMovimiento::INTERCAMBIO_VELOCIDAD, \Src\Battle\Domain\Enums\StatClave::VELOCIDAD));
        $coleccion->registrar(new EjecutorAromaterapia());
        $coleccion->registrar(new EjecutorAguijonLetal());
        $coleccion->registrar(new EjecutorAriaBurbuja());
        $coleccion->registrar(new EjecutorCantoEncantador());
        $coleccion->registrar(new EjecutorProteccion());
        $coleccion->registrar(new EjecutorAguante());
        $coleccion->registrar(new EjecutorClima(ClaveEfectoMovimiento::CLIMA_SEQUIA, \Src\Battle\Domain\Enums\TipoClima::SEQUIA));
        $coleccion->registrar(new EjecutorClima(ClaveEfectoMovimiento::CLIMA_DILUVIO, \Src\Battle\Domain\Enums\TipoClima::DILUVIO));
        $coleccion->registrar(new EjecutorClima(ClaveEfectoMovimiento::CLIMA_ARENA, \Src\Battle\Domain\Enums\TipoClima::TORMENTA_ARENA));
        $coleccion->registrar(new EjecutorClima(ClaveEfectoMovimiento::CLIMA_GRANIZO, \Src\Battle\Domain\Enums\TipoClima::GRANIZO));
        $coleccion->registrar(new EjecutorEquipoProteccion(ClaveEfectoMovimiento::SAFEGUARD));
        $coleccion->registrar(new EjecutorEquipoProteccion(ClaveEfectoMovimiento::MIST));
        $coleccion->registrar(new EjecutorEquipoProteccion(ClaveEfectoMovimiento::LUCKY_CHANT));
        $coleccion->registrar(new EjecutorCuracion(ClaveEfectoMovimiento::CURACION_ALIADO));
        $coleccion->registrar(new EjecutorCuracion(ClaveEfectoMovimiento::CURACION_EQUIPO, true));
        $coleccion->registrar(new EjecutorCuracion(ClaveEfectoMovimiento::CURACION_EQUIPO_ESTADO, true, true));
        $coleccion->registrar(new EjecutorPurificar());
        $coleccion->registrar(new EjecutorDrenajeEstadistica());
        $coleccion->registrar(new EjecutorAquaRing());
        $coleccion->registrar(new EjecutorInvertirStats());
        $coleccion->registrar(new EjecutorTambor());
        $coleccion->registrar(new EjecutorAcupresion());
        $coleccion->registrar(new EjecutorLevitacion());
        $coleccion->registrar(new EjecutorSinRetirada());
        $coleccion->registrar(new EjecutorAtiborramiento());
        $coleccion->registrar(new EjecutorFilletAway());
        $coleccion->registrar(new EjecutorTomarAliento());
        $coleccion->registrar(new EjecutorDragonCheer());
        $coleccion->registrar(new EjecutorDanzaRevelacion());

        return $coleccion;
    }

    public function registrar(InterfazEjecutorMovimiento $ejecutor): void
    {
        $this->ejecutores[$ejecutor->clave()->value] = $ejecutor;
    }

    public function ejecutar(ClaveEfectoMovimiento $clave, AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $ejecutor = $this->ejecutores[$clave->value] ?? null;
        $ejecutor?->ejecutar($accion, $danoAplicado, $battle);
    }

    public function potenciaEfectiva(ClaveEfectoMovimiento $clave, AccionBatalla $accion, AgregadoBatalla $battle): ?int
    {
        return ($this->ejecutores[$clave->value] ?? null)?->potenciaEfectiva($accion, $battle);
    }

    public function tipoEfectivo(ClaveEfectoMovimiento $clave, AccionBatalla $accion, AgregadoBatalla $battle): ?TipoPokemon
    {
        return ($this->ejecutores[$clave->value] ?? null)?->tipoEfectivo($accion, $battle);
    }

    public function tiene(ClaveEfectoMovimiento $clave): bool
    {
        return isset($this->ejecutores[$clave->value]);
    }
}
