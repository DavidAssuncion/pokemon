<?php

declare(strict_types=1);

namespace Src\Battle\Infra\Factories;

use App\Models\Move;
use Src\Battle\Domain\Collections\CambiosStatsCollection;
use Src\Battle\Domain\Collections\EfectosSecundariosCollection;
use Src\Battle\Domain\Collections\FlagsMovimientoCollection;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\FlagMovimiento;
use Src\Battle\Domain\Enums\ModoInteraccionHabilidad;
use Src\Battle\Domain\Enums\ModoInteraccionObjeto;
use Src\Battle\Domain\Enums\ObjetivoMovimiento;
use Src\Battle\Domain\Movimiento;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Battle\Domain\ValueObjects\GolpesMovimiento;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Convierte una fila `moves` en la entidad de catálogo `Movimiento`.
 *
 * Los campos estándar viven en columnas; las particularidades del juego
 * (multiplicadores de escudo/salud, daño directo, coste de velocidad, daño
 * diferido) viven en `data`.
 */
final class MovimientoFactory
{
    public function desdeModelo(Move $modelo): Movimiento
    {
        $flags = FlagsMovimientoCollection::desdeArray($modelo->flags ?? []);
        $secundarios = EfectosSecundariosCollection::desdeArray($modelo->secondaries ?? []);
        $autoEfectos = EfectosSecundariosCollection::desdeArray($modelo->self_effects ?? [], true);
        $claveEfecto = $this->claveEfecto($modelo->effect_key);
        $data = $modelo->data ?? [];

        $golpes = new GolpesMovimiento(
            max(1, $modelo->multihit_min),
            max(1, $modelo->multihit_max),
        );

        $perfil = new PerfilMovimiento(
            golpesMin: $golpes->minimo,
            golpesMax: $golpes->maximo,
            fraccionPotenciaPorGolpe: (float) ($data['fraccion_potencia_por_golpe'] ?? 1.0),
            precision: $modelo->accuracy === null ? 1.0 : $modelo->accuracy / 100,
            ratioCritico: $modelo->crit_ratio,
            criticoSeguro: $modelo->will_crit,
            probabilidadEstado: $modelo->status_chance / 100,
            cargasVeneno: (int) ($data['cargas_veneno'] ?? 1),
            drenajeFraccion: $modelo->drain_percent / 100,
            recoilFraccion: $modelo->recoil_percent / 100,
            danoDirectoFraccion: (float) ($data['dano_directo_fraccion'] ?? 0.0),
            multiplicadorDanoEscudo: (float) ($data['multiplicador_dano_escudo'] ?? 1.0),
            multiplicadorDanoSalud: (float) ($data['multiplicador_dano_salud'] ?? 1.0),
            costeVelocidadFraccion: (float) ($data['coste_velocidad_fraccion'] ?? 0.0),
            curacionFraccion: $modelo->heal_percent / 100,
            turnosDanoDiferido: (int) ($data['turnos_dano_diferido'] ?? 0),
            fraccionDanoDiferido: (float) ($data['fraccion_dano_diferido'] ?? 0.0),
            tipoDanoDiferido: $this->tipoDiferido($data['tipo_dano_diferido'] ?? null),
            esContacto: $flags->esContacto(),
            puedeCriticar: $modelo->crit_ratio > 0,
            potenciaCreciente: (bool) ($data['potencia_creciente'] ?? false),
            turnosMaximosPotencia: (int) ($data['turnos_max'] ?? 0),
            claveEfecto: $claveEfecto,
            cargaMaxima: (int) ($data['carga_maxima'] ?? (($data['carga'] ?? false) || $flags->tiene(FlagMovimiento::CARGA) ? 3 : 0)),
            cargaPorRonda: (int) ($data['carga_por_ronda'] ?? 1),
            usosMaximos: (int) ($data['usos_maximos'] ?? $data['usos'] ?? 0),
            rompeGuardia: (bool) ($data['rompe_guardia'] ?? $data['rompe_proteccion'] ?? false),
            silencioTurnos: (int) ($data['silencio_turnos'] ?? (($data['silencio'] ?? false) ? 2 : 0)),
            interrumpe: (bool) ($data['interrumpe'] ?? $data['interrupcion'] ?? false),
            aturde: (bool) ($data['aturde'] ?? $data['aturdimiento'] ?? false),
            recarga: (bool) ($data['recarga'] ?? $flags->tiene(FlagMovimiento::RECARGA)),
            multiplicadorDefensaObjetivo: (float) ($data['multiplicador_defensa_objetivo'] ?? 1.0),
            autodestruccion: (bool) ($data['autodestruccion'] ?? false),
            turnosMarcado: (int) ($data['marcado_turnos'] ?? 0),
            multiplicadorMarcado: (float) ($data['marcado_multiplicador'] ?? 1.3),
            turnosVinculoDano: (int) ($data['vinculo_turnos'] ?? 0),
            fraccionVinculoDano: (float) ($data['vinculo_fraccion'] ?? 0.0),
            fraccionRevivir: (float) ($data['revivir_fraccion'] ?? 0.5),
            turnosInvocacion: (int) ($data['invocacion_turnos'] ?? 3),
            fraccionHpInvocacion: (float) ($data['invocacion_fraccion_hp'] ?? 0.35),
            maxInvocaciones: (int) ($data['invocacion_max'] ?? 1),
            nombreInvocacion: (string) ($data['invocacion_nombre'] ?? ''),
            modoObjeto: $this->modoObjeto($data['modo_objeto'] ?? null),
            turnosObjeto: (int) ($data['turnos_objeto'] ?? 5),
            modoHabilidad: $this->modoHabilidad($data['modo_habilidad'] ?? null),
            turnosHabilidad: (int) ($data['turnos_habilidad'] ?? 5),
            fraccionHpSustituto: (float) ($data['sustituto_fraccion_hp'] ?? 0.25),
            turnosSustituto: (int) ($data['sustituto_turnos'] ?? 0),
            tipoOverride: $this->tipoDiferido($data['tipo_override'] ?? null),
            turnosTipo: (int) ($data['turnos_tipo'] ?? 0),
            calculoStats: (string) ($data['calculo_stats'] ?? $data['calculoStats'] ?? 'normal'),
            formulaPotencia: (string) ($data['formula_potencia'] ?? $data['formulaPotencia'] ?? 'fija'),
            factorPotencia: (float) ($data['factor_potencia'] ?? $data['factorPotencia'] ?? 1.0),
            usaHpMaximoAtacante: (bool) ($data['usa_hp_max_atacante'] ?? false),
            usaHpMaximoObjetivo: (bool) ($data['usa_hp_max_objetivo'] ?? false),
            ignoraDefensas: (bool) ($data['ignora_defensas'] ?? false),
            danoPorcentajeHpObjetivo: (float) ($data['dano_porcentaje_hp_objetivo'] ?? 0.0),
            danoPorcentajeHpAtacante: (float) ($data['dano_porcentaje_hp_atacante'] ?? 0.0),
            danoFijoPorPorcentaje: (bool) ($data['dano_fijo_por_porcentaje'] ?? false),
            formulaDano: (string) ($data['formula_dano'] ?? 'normal'),
            potenciaMaxima: (int) ($data['potencia_maxima'] ?? 0),
            tipoEfectividadEspecial: $this->tipoDiferido($data['tipo_efectividad_especial'] ?? null),
            multiplicadorEfectividadEspecial: (float) ($data['multiplicador_efectividad_especial'] ?? 1.0),
            multiplicadorSiSupereficaz: (float) ($data['multiplicador_si_supereficaz'] ?? 1.0),
            climaPotenciador: $this->clima($data['clima_potenciador'] ?? null),
            factorClima: (float) ($data['factor_clima'] ?? 1.0),
            ignoraDebuffClima: (bool) ($data['ignora_debuff_clima'] ?? false),
            terrenoPotenciador: $this->terreno($data['terreno_potenciador'] ?? null),
            factorTerreno: (float) ($data['factor_terreno'] ?? 1.0),
            fallaSinTerreno: (bool) ($data['falla_sin_terreno'] ?? false),
            eliminaTerreno: (bool) ($data['elimina_terreno'] ?? false),
            rompePantallas: (bool) ($data['rompe_pantallas'] ?? false),
            requiereObjetivoDormido: (bool) ($data['requiere_objetivo_dormido'] ?? false),
            recoilHpMaximoFraccion: (float) ($data['recoil_hp_maximo_fraccion'] ?? 0.0),
            precisionPorClima: is_array($data['precision_por_clima'] ?? null)
                ? array_map(static fn (mixed $valor): float => (float) $valor, $data['precision_por_clima'])
                : [],
            multiaccuracy: $modelo->multiaccuracy,
        );

        return new Movimiento(
            id: $modelo->id,
            slug: $modelo->slug,
            num: $modelo->num,
            nombre: $modelo->name,
            descripcion: $modelo->description,
            tipo: TipoPokemon::from($modelo->type_id),
            categoria: CategoriaMovimiento::from($modelo->category),
            potencia: $modelo->base_power,
            precision: $modelo->accuracy,
            prioridad: $modelo->priority,
            objetivo: ObjetivoMovimiento::from($modelo->target),
            ratioCritico: $modelo->crit_ratio,
            criticoSeguro: $modelo->will_crit,
            estado: $this->estado($modelo->status_effect),
            probabilidadEstado: $modelo->status_chance,
            golpes: $golpes,
            multiaccuracy: $modelo->multiaccuracy,
            drenajePorcentaje: $modelo->drain_percent,
            recoilPorcentaje: $modelo->recoil_percent,
            curacionPorcentaje: $modelo->heal_percent,
            flags: $flags,
            secundarios: $secundarios,
            autoEfectos: $autoEfectos,
            cambiosPropios: $this->cambiosIncondicionales($autoEfectos, true),
            cambiosObjetivo: $this->cambiosIncondicionales($secundarios, false),
            claveEfecto: $claveEfecto,
            perfil: $perfil,
            nivelRequerido: max(1, $modelo->nivel_requerido),
        );
    }

    private function estado(?string $estado): EstadoPokemon
    {
        if ($estado === null || $estado === '') {
            return EstadoPokemon::NONE;
        }

        return EstadoPokemon::from($estado);
    }

    private function claveEfecto(?string $clave): ?ClaveEfectoMovimiento
    {
        if ($clave === null || $clave === '') {
            return null;
        }

        return ClaveEfectoMovimiento::tryFrom($clave);
    }

    private function tipoDiferido(mixed $tipo): ?TipoPokemon
    {
        if ($tipo === null || $tipo === '') {
            return null;
        }

        return TipoPokemon::tryFrom((int) $tipo);
    }

    private function clima(mixed $clima): ?\Src\Battle\Domain\Enums\TipoClima
    {
        return is_string($clima) && $clima !== ''
            ? \Src\Battle\Domain\Enums\TipoClima::tryFrom($clima)
            : null;
    }

    private function terreno(mixed $terreno): ?\Src\Battle\Domain\Enums\TipoTerreno
    {
        return is_string($terreno) && $terreno !== ''
            ? \Src\Battle\Domain\Enums\TipoTerreno::tryFrom($terreno)
            : null;
    }

    private function modoObjeto(mixed $modo): ?ModoInteraccionObjeto
    {
        return is_string($modo) ? ModoInteraccionObjeto::tryFrom($modo) : null;
    }

    private function modoHabilidad(mixed $modo): ?ModoInteraccionHabilidad
    {
        return is_string($modo) ? ModoInteraccionHabilidad::tryFrom($modo) : null;
    }

    /**
     * Solo los efectos al 100 % se convierten en cambios incondicionales de
     * MovimientoBatalla; los que tienen probabilidad quedan en la colección
     * de secundarios para que el ejecutor los resuelva.
     */
    private function cambiosIncondicionales(EfectosSecundariosCollection $efectos, bool $soloAuto): CambiosStatsCollection
    {
        $cambios = [];
        foreach ($efectos as $efecto) {
            if ($efecto->probabilidad < 100 || $efecto->esAuto !== $soloAuto) {
                continue;
            }

            foreach ($efecto->cambios as $cambio) {
                $cambios[] = new CambioStat($cambio->stat, $cambio->factor);
            }
        }

        return new CambiosStatsCollection($cambios);
    }
}
