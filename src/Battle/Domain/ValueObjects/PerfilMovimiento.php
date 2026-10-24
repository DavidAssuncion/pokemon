<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\ModoInteraccionHabilidad;
use Src\Battle\Domain\Enums\ModoInteraccionObjeto;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\Enums\TipoTerreno;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Perfil numérico de un movimiento: todo lo que el motor necesita saber
 * además de nombre, potencia, tipo y categoría.
 *
 * Es un Value Object inmutable y serializable. Los ataques con lógica
 * especial usan `claveEfecto` para delegar en un ejecutor dedicado.
 */
final readonly class PerfilMovimiento
{
    public function __construct(
        public int $golpesMin = 1,
        public int $golpesMax = 1,
        public float $fraccionPotenciaPorGolpe = 1.0,
        public float $precision = 1.0,
        public int $ratioCritico = 1,
        public bool $criticoSeguro = false,
        public float $probabilidadEstado = 1.0,
        public int $cargasVeneno = 1,
        public float $drenajeFraccion = 0.0,
        public float $recoilFraccion = 0.0,
        public float $danoDirectoFraccion = 0.0,
        public float $multiplicadorDanoEscudo = 1.0,
        public float $multiplicadorDanoSalud = 1.0,
        public float $costeVelocidadFraccion = 0.0,
        public float $curacionFraccion = 0.0,
        public int $turnosDanoDiferido = 0,
        public float $fraccionDanoDiferido = 0.0,
        public ?TipoPokemon $tipoDanoDiferido = null,
        public bool $esContacto = false,
        public bool $puedeCriticar = true,
        // Combo encadenable: el movimiento acumula racha al repetirse.
        public bool $potenciaCreciente = false,
        public int $turnosMaximosPotencia = 0,
        public ?ClaveEfectoMovimiento $claveEfecto = null,
        public int $cargaMaxima = 0,
        public int $cargaPorRonda = 1,
        public int $usosMaximos = 0,
        public bool $rompeGuardia = false,
        public int $silencioTurnos = 0,
        public bool $interrumpe = false,
        public bool $aturde = false,
        public bool $recarga = false,
        // Multiplicador aplicado a las defensas del objetivo en la fórmula base.
        // 0.5 = usa la mitad de la defensa.
        public float $multiplicadorDefensaObjetivo = 1.0,
        // El usuario queda debilitado después de resolver el movimiento.
        public bool $autodestruccion = false,
        // Marca/hunt: duración y multiplicador de daño recibido.
        public int $turnosMarcado = 0,
        public float $multiplicadorMarcado = 1.3,
        // Vínculo de daño: duración y fracción transferida al aliado.
        public int $turnosVinculoDano = 0,
        public float $fraccionVinculoDano = 0.0,
        // Revive: fracción del HP máximo con la que vuelve el aliado.
        public float $fraccionRevivir = 0.5,
        // Invocaciones: duración, HP, límite activo y nombre visible.
        public int $turnosInvocacion = 3,
        public float $fraccionHpInvocacion = 0.35,
        public int $maxInvocaciones = 1,
        public string $nombreInvocacion = '',
        // Interacción con objetos: destruir, robar, intercambiar, bloquear…
        public ?ModoInteraccionObjeto $modoObjeto = null,
        public int $turnosObjeto = 5,
        // Interacción con habilidades: anular, copiar, intercambiar o ignorar.
        public ?ModoInteraccionHabilidad $modoHabilidad = null,
        public int $turnosHabilidad = 5,
        // Sustituto/clon: fracción de HP máximo y duración (0 = hasta romperse).
        public float $fraccionHpSustituto = 0.25,
        public int $turnosSustituto = 0,
        // Cambio temporal de tipo defensivo.
        public ?TipoPokemon $tipoOverride = null,
        public int $turnosTipo = 0,
        // Mecánicas avanzadas
        public string $calculoStats = 'normal', // normal|body_press|foul_play|psyshock
        public string $formulaPotencia = 'fija', // fija|hp_ratio|peso|velocidad|objetivo_hp|estado|volatil
        public float $factorPotencia = 1.0,
        public bool $usaHpMaximoAtacante = false,
        public bool $usaHpMaximoObjetivo = false,
        public bool $ignoraDefensas = false,
        public float $danoPorcentajeHpObjetivo = 0.0,
        public float $danoPorcentajeHpAtacante = 0.0,
        public bool $danoFijoPorPorcentaje = false,
        public string $formulaDano = 'normal', // normal|hp_atacante|mitad_hp_objetivo|diferencia_hp
        public int $potenciaMaxima = 0,
        public ?TipoPokemon $tipoEfectividadEspecial = null,
        public float $multiplicadorEfectividadEspecial = 1.0,
        public float $multiplicadorSiSupereficaz = 1.0,
        public ?TipoClima $climaPotenciador = null,
        public float $factorClima = 1.0,
        public bool $ignoraDebuffClima = false,
        public ?TipoTerreno $terrenoPotenciador = null,
        public float $factorTerreno = 1.0,
        public bool $fallaSinTerreno = false,
        public bool $eliminaTerreno = false,
        public bool $rompePantallas = false,
        public bool $requiereObjetivoDormido = false,
        public float $recoilHpMaximoFraccion = 0.0,
        /** @var array<string, float> Precisión (0–1) según TipoClima->value. */
        public array $precisionPorClima = [],
        public bool $multiaccuracy = false,
    ) {
    }

    public static function porDefecto(): self
    {
        return new self();
    }

    public function esMultigolpe(): bool
    {
        return $this->golpesMax > 1;
    }

    public function golpesFijos(): bool
    {
        return $this->golpesMin === $this->golpesMax;
    }

    public function golpesEsperados(): float
    {
        return ($this->golpesMin + $this->golpesMax) / 2;
    }

    public function tieneDanoDiferido(): bool
    {
        return $this->turnosDanoDiferido > 0 && $this->fraccionDanoDiferido > 0.0;
    }

    public function requiereCarga(): bool
    {
        return $this->cargaMaxima > 0;
    }

    public function tieneUsosLimitados(): bool
    {
        return $this->usosMaximos > 0;
    }

    /**
     * Serialización para sesión y para el DTO de Livewire.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'golpesMin' => $this->golpesMin,
            'golpesMax' => $this->golpesMax,
            'fraccionPotenciaPorGolpe' => $this->fraccionPotenciaPorGolpe,
            'precision' => $this->precision,
            'ratioCritico' => $this->ratioCritico,
            'criticoSeguro' => $this->criticoSeguro,
            'probabilidadEstado' => $this->probabilidadEstado,
            'cargasVeneno' => $this->cargasVeneno,
            'drenajeFraccion' => $this->drenajeFraccion,
            'recoilFraccion' => $this->recoilFraccion,
            'danoDirectoFraccion' => $this->danoDirectoFraccion,
            'multiplicadorDanoEscudo' => $this->multiplicadorDanoEscudo,
            'multiplicadorDanoSalud' => $this->multiplicadorDanoSalud,
            'costeVelocidadFraccion' => $this->costeVelocidadFraccion,
            'curacionFraccion' => $this->curacionFraccion,
            'turnosDanoDiferido' => $this->turnosDanoDiferido,
            'fraccionDanoDiferido' => $this->fraccionDanoDiferido,
            'tipoDanoDiferido' => $this->tipoDanoDiferido?->value,
            'esContacto' => $this->esContacto,
            'puedeCriticar' => $this->puedeCriticar,
            'potenciaCreciente' => $this->potenciaCreciente,
            'turnosMaximosPotencia' => $this->turnosMaximosPotencia,
            'claveEfecto' => $this->claveEfecto?->value,
            'cargaMaxima' => $this->cargaMaxima,
            'cargaPorRonda' => $this->cargaPorRonda,
            'usosMaximos' => $this->usosMaximos,
            'rompeGuardia' => $this->rompeGuardia,
            'silencioTurnos' => $this->silencioTurnos,
            'interrumpe' => $this->interrumpe,
            'aturde' => $this->aturde,
            'recarga' => $this->recarga,
            'multiplicadorDefensaObjetivo' => $this->multiplicadorDefensaObjetivo,
            'autodestruccion' => $this->autodestruccion,
            'turnosMarcado' => $this->turnosMarcado,
            'multiplicadorMarcado' => $this->multiplicadorMarcado,
            'turnosVinculoDano' => $this->turnosVinculoDano,
            'fraccionVinculoDano' => $this->fraccionVinculoDano,
            'fraccionRevivir' => $this->fraccionRevivir,
            'turnosInvocacion' => $this->turnosInvocacion,
            'fraccionHpInvocacion' => $this->fraccionHpInvocacion,
            'maxInvocaciones' => $this->maxInvocaciones,
            'nombreInvocacion' => $this->nombreInvocacion,
            'modoObjeto' => $this->modoObjeto?->value,
            'turnosObjeto' => $this->turnosObjeto,
            'modoHabilidad' => $this->modoHabilidad?->value,
            'turnosHabilidad' => $this->turnosHabilidad,
            'fraccionHpSustituto' => $this->fraccionHpSustituto,
            'turnosSustituto' => $this->turnosSustituto,
            'tipoOverride' => $this->tipoOverride?->value,
            'turnosTipo' => $this->turnosTipo,
            'calculoStats' => $this->calculoStats,
            'formulaPotencia' => $this->formulaPotencia,
            'factorPotencia' => $this->factorPotencia,
            'usaHpMaximoAtacante' => $this->usaHpMaximoAtacante,
            'usaHpMaximoObjetivo' => $this->usaHpMaximoObjetivo,
            'ignoraDefensas' => $this->ignoraDefensas,
            'danoPorcentajeHpObjetivo' => $this->danoPorcentajeHpObjetivo,
            'danoPorcentajeHpAtacante' => $this->danoPorcentajeHpAtacante,
            'danoFijoPorPorcentaje' => $this->danoFijoPorPorcentaje,
            'formulaDano' => $this->formulaDano,
            'potenciaMaxima' => $this->potenciaMaxima,
            'tipoEfectividadEspecial' => $this->tipoEfectividadEspecial?->value,
            'multiplicadorEfectividadEspecial' => $this->multiplicadorEfectividadEspecial,
            'multiplicadorSiSupereficaz' => $this->multiplicadorSiSupereficaz,
            'climaPotenciador' => $this->climaPotenciador?->value,
            'factorClima' => $this->factorClima,
            'ignoraDebuffClima' => $this->ignoraDebuffClima,
            'terrenoPotenciador' => $this->terrenoPotenciador?->value,
            'factorTerreno' => $this->factorTerreno,
            'fallaSinTerreno' => $this->fallaSinTerreno,
            'eliminaTerreno' => $this->eliminaTerreno,
            'rompePantallas' => $this->rompePantallas,
            'requiereObjetivoDormido' => $this->requiereObjetivoDormido,
            'recoilHpMaximoFraccion' => $this->recoilHpMaximoFraccion,
            'precisionPorClima' => $this->precisionPorClima,
            'multiaccuracy' => $this->multiaccuracy,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArray(array $datos): self
    {
        $tipo = $datos['tipoDanoDiferido'] ?? null;
        $clave = $datos['claveEfecto'] ?? null;
        $modoObjeto = $datos['modoObjeto'] ?? null;
        $modoHabilidad = $datos['modoHabilidad'] ?? null;
        $tipoOverride = $datos['tipoOverride'] ?? null;

        $golpesLegacy = (int) ($datos['golpes'] ?? 1);

        return new self(
            golpesMin: max(1, (int) ($datos['golpesMin'] ?? $golpesLegacy)),
            golpesMax: max(1, (int) ($datos['golpesMax'] ?? $golpesLegacy)),
            fraccionPotenciaPorGolpe: (float) ($datos['fraccionPotenciaPorGolpe'] ?? 1.0),
            precision: (float) ($datos['precision'] ?? 1.0),
            ratioCritico: max(1, (int) ($datos['ratioCritico'] ?? 1)),
            criticoSeguro: (bool) ($datos['criticoSeguro'] ?? false),
            probabilidadEstado: (float) ($datos['probabilidadEstado'] ?? 1.0),
            cargasVeneno: max(1, (int) ($datos['cargasVeneno'] ?? 1)),
            drenajeFraccion: (float) ($datos['drenajeFraccion'] ?? 0.0),
            recoilFraccion: (float) ($datos['recoilFraccion'] ?? 0.0),
            danoDirectoFraccion: (float) ($datos['danoDirectoFraccion'] ?? 0.0),
            multiplicadorDanoEscudo: (float) ($datos['multiplicadorDanoEscudo'] ?? 1.0),
            multiplicadorDanoSalud: (float) ($datos['multiplicadorDanoSalud'] ?? 1.0),
            costeVelocidadFraccion: (float) ($datos['costeVelocidadFraccion'] ?? 0.0),
            curacionFraccion: (float) ($datos['curacionFraccion'] ?? 0.0),
            turnosDanoDiferido: (int) ($datos['turnosDanoDiferido'] ?? 0),
            fraccionDanoDiferido: (float) ($datos['fraccionDanoDiferido'] ?? 0.0),
            tipoDanoDiferido: is_int($tipo) || is_string($tipo) ? TipoPokemon::tryFrom((int) $tipo) : null,
            esContacto: (bool) ($datos['esContacto'] ?? false),
            puedeCriticar: (bool) ($datos['puedeCriticar'] ?? true),
            potenciaCreciente: (bool) ($datos['potenciaCreciente'] ?? false),
            turnosMaximosPotencia: max(0, (int) ($datos['turnosMaximosPotencia'] ?? 0)),
            claveEfecto: is_string($clave) ? ClaveEfectoMovimiento::tryFrom($clave) : null,
            cargaMaxima: max(0, (int) ($datos['cargaMaxima'] ?? 0)),
            cargaPorRonda: max(1, (int) ($datos['cargaPorRonda'] ?? 1)),
            usosMaximos: max(0, (int) ($datos['usosMaximos'] ?? 0)),
            rompeGuardia: (bool) ($datos['rompeGuardia'] ?? false),
            silencioTurnos: max(0, (int) ($datos['silencioTurnos'] ?? 0)),
            interrumpe: (bool) ($datos['interrumpe'] ?? false),
            aturde: (bool) ($datos['aturde'] ?? false),
            recarga: (bool) ($datos['recarga'] ?? false),
            multiplicadorDefensaObjetivo: max(0.01, (float) ($datos['multiplicadorDefensaObjetivo'] ?? 1.0)),
            autodestruccion: (bool) ($datos['autodestruccion'] ?? false),
            turnosMarcado: max(0, (int) ($datos['turnosMarcado'] ?? 0)),
            multiplicadorMarcado: max(1.0, (float) ($datos['multiplicadorMarcado'] ?? 1.3)),
            turnosVinculoDano: max(0, (int) ($datos['turnosVinculoDano'] ?? 0)),
            fraccionVinculoDano: max(0.0, min(0.5, (float) ($datos['fraccionVinculoDano'] ?? 0.0))),
            fraccionRevivir: max(0.01, min(1.0, (float) ($datos['fraccionRevivir'] ?? 0.5))),
            turnosInvocacion: max(1, (int) ($datos['turnosInvocacion'] ?? 3)),
            fraccionHpInvocacion: max(0.01, min(1.0, (float) ($datos['fraccionHpInvocacion'] ?? 0.35))),
            maxInvocaciones: max(1, (int) ($datos['maxInvocaciones'] ?? 1)),
            nombreInvocacion: (string) ($datos['nombreInvocacion'] ?? ''),
            modoObjeto: is_string($modoObjeto) ? ModoInteraccionObjeto::tryFrom($modoObjeto) : null,
            turnosObjeto: max(0, (int) ($datos['turnosObjeto'] ?? 5)),
            modoHabilidad: is_string($modoHabilidad) ? ModoInteraccionHabilidad::tryFrom($modoHabilidad) : null,
            turnosHabilidad: max(0, (int) ($datos['turnosHabilidad'] ?? 5)),
            fraccionHpSustituto: max(0.01, min(1.0, (float) ($datos['fraccionHpSustituto'] ?? 0.25))),
            turnosSustituto: max(0, (int) ($datos['turnosSustituto'] ?? 0)),
            tipoOverride: is_int($tipoOverride) || is_string($tipoOverride)
                ? TipoPokemon::tryFrom((int) $tipoOverride)
                : null,
            turnosTipo: max(0, (int) ($datos['turnosTipo'] ?? 0)),
            calculoStats: (string) ($datos['calculoStats'] ?? 'normal'),
            formulaPotencia: (string) ($datos['formulaPotencia'] ?? 'fija'),
            factorPotencia: (float) ($datos['factorPotencia'] ?? 1.0),
            usaHpMaximoAtacante: (bool) ($datos['usaHpMaximoAtacante'] ?? false),
            usaHpMaximoObjetivo: (bool) ($datos['usaHpMaximoObjetivo'] ?? false),
            ignoraDefensas: (bool) ($datos['ignoraDefensas'] ?? false),
            danoPorcentajeHpObjetivo: (float) ($datos['danoPorcentajeHpObjetivo'] ?? 0.0),
            danoPorcentajeHpAtacante: (float) ($datos['danoPorcentajeHpAtacante'] ?? 0.0),
            danoFijoPorPorcentaje: (bool) ($datos['danoFijoPorPorcentaje'] ?? false),
            formulaDano: (string) ($datos['formulaDano'] ?? 'normal'),
            potenciaMaxima: max(0, (int) ($datos['potenciaMaxima'] ?? 0)),
            tipoEfectividadEspecial: self::tipoDesde($datos['tipoEfectividadEspecial'] ?? null),
            multiplicadorEfectividadEspecial: (float) ($datos['multiplicadorEfectividadEspecial'] ?? 1.0),
            multiplicadorSiSupereficaz: (float) ($datos['multiplicadorSiSupereficaz'] ?? 1.0),
            climaPotenciador: self::climaDesde($datos['climaPotenciador'] ?? null),
            factorClima: (float) ($datos['factorClima'] ?? 1.0),
            ignoraDebuffClima: (bool) ($datos['ignoraDebuffClima'] ?? false),
            terrenoPotenciador: self::terrenoDesde($datos['terrenoPotenciador'] ?? null),
            factorTerreno: (float) ($datos['factorTerreno'] ?? 1.0),
            fallaSinTerreno: (bool) ($datos['fallaSinTerreno'] ?? false),
            eliminaTerreno: (bool) ($datos['eliminaTerreno'] ?? false),
            rompePantallas: (bool) ($datos['rompePantallas'] ?? false),
            requiereObjetivoDormido: (bool) ($datos['requiereObjetivoDormido'] ?? false),
            recoilHpMaximoFraccion: max(0.0, (float) ($datos['recoilHpMaximoFraccion'] ?? 0.0)),
            precisionPorClima: is_array($datos['precisionPorClima'] ?? null)
                ? array_map(static fn (mixed $valor): float => (float) $valor, $datos['precisionPorClima'])
                : [],
            multiaccuracy: (bool) ($datos['multiaccuracy'] ?? false),
        );
    }

    private static function tipoDesde(mixed $valor): ?TipoPokemon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return is_int($valor) || is_numeric($valor)
            ? TipoPokemon::tryFrom((int) $valor)
            : null;
    }

    private static function climaDesde(mixed $valor): ?TipoClima
    {
        return is_string($valor) && $valor !== '' ? TipoClima::tryFrom($valor) : null;
    }

    private static function terrenoDesde(mixed $valor): ?TipoTerreno
    {
        return is_string($valor) && $valor !== '' ? TipoTerreno::tryFrom($valor) : null;
    }
}
