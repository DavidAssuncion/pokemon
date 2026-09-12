<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Effects\ColeccionEfectos;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\MultiplicadoresStats;
use Src\Battle\Domain\ValueObjects\ResultadoAccion;
use Src\Pokemon\Domain\PokemonEntity;

class Combatiente
{
    private float $hpActual;

    private float $defensaHpActual;

    private float $defensaEspHpActual;

    private float $velocidadAcumulada = 0;

    private int $vecesActuadoEstaRonda = 0;

    private EstadoPokemon $estado = EstadoPokemon::NONE;

    private int $contadorVenenoGrave = 0;

    private int $turnosEstado = 0;

    private MultiplicadoresStats $multiplicadores;

    private string $id = '';

    private string $nombre = '';

    private string $iconName = '';

    private int $speciesId = 0;

    private string $formSuffix = '';

    private bool $shiny = false;

    private string $item = '';

    private float $bonificadorDanio = 1.0;

    private ColeccionEfectos $effects;

    private PokemonEntity $pokemon;

    private Posicion $posicion;

    private EstadoManager $estadoManager;

    private BarrerasVidaManager $barrerasVidaManager;

    public function __construct(
        PokemonEntity $pokemon,
        Posicion $posicion,
    ) {
        $this->pokemon = $pokemon;
        $this->posicion = $posicion;
        $this->hpActual = $pokemon->battleStats()->hp;
        $this->defensaHpActual = $pokemon->battleStats()->defenseHp;
        $this->defensaEspHpActual = $pokemon->battleStats()->spDefenseHp;
        $this->effects = new ColeccionEfectos();
        $this->estadoManager = new EstadoManager();
        $this->barrerasVidaManager = new BarrerasVidaManager();
        $this->multiplicadores = MultiplicadoresStats::vacia();
    }

    // ─── Serialización para sesión ────────────────────────────

    public function __serialize(): array
    {
        return [
            'hpActual' => $this->hpActual,
            'defensaHpActual' => $this->defensaHpActual,
            'defensaEspHpActual' => $this->defensaEspHpActual,
            'velocidadAcumulada' => $this->velocidadAcumulada,
            'vecesActuadoEstaRonda' => $this->vecesActuadoEstaRonda,
            'estado' => $this->estado->value,
            'contadorVenenoGrave' => $this->contadorVenenoGrave,
            'turnosEstado' => $this->turnosEstado,
            'multiplicadores' => $this->multiplicadores->obtenerModificadores()->factores(),
            'id' => $this->id,
            'nombre' => $this->nombre,
            'iconName' => $this->iconName,
            'speciesId' => $this->speciesId,
            'formSuffix' => $this->formSuffix,
            'shiny' => $this->shiny,
            'item' => $this->item,
            'bonificadorDanio' => $this->bonificadorDanio,
            'effects' => serialize($this->effects),
            'pokemon' => serialize($this->pokemon),
            'posicion' => $this->posicion->value,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->hpActual = (float) ($data['hpActual'] ?? 0);
        $this->defensaHpActual = (float) ($data['defensaHpActual'] ?? 0);
        $this->defensaEspHpActual = (float) ($data['defensaEspHpActual'] ?? 0);
        $this->velocidadAcumulada = (float) ($data['velocidadAcumulada'] ?? 0);
        $this->vecesActuadoEstaRonda = (int) ($data['vecesActuadoEstaRonda'] ?? 0);
        $this->estado = EstadoPokemon::tryFrom($data['estado'] ?? EstadoPokemon::NONE->value) ?? EstadoPokemon::NONE;
        $this->contadorVenenoGrave = (int) ($data['contadorVenenoGrave'] ?? 0);
        $this->turnosEstado = (int) ($data['turnosEstado'] ?? 0);
        // Compatibilidad: payloads antiguos ('etapas' con int) y nuevos ('multiplicadores' con float)
        if (isset($data['multiplicadores']) && is_array($data['multiplicadores'])) {
            $this->multiplicadores = MultiplicadoresStats::desdeSerializado($data['multiplicadores']);
        } elseif (isset($data['etapas']) && is_array($data['etapas'])) {
            $this->multiplicadores = MultiplicadoresStats::desdeEtapas($data['etapas']);
        } else {
            $this->multiplicadores = MultiplicadoresStats::vacia();
        }
        $this->id = (string) ($data['id'] ?? '');
        $this->nombre = (string) ($data['nombre'] ?? '');
        $this->iconName = (string) ($data['iconName'] ?? '');
        $this->speciesId = (int) ($data['speciesId'] ?? 0);
        $this->formSuffix = (string) ($data['formSuffix'] ?? '');
        $this->shiny = (bool) ($data['shiny'] ?? false);
        $this->item = (string) ($data['item'] ?? '');
        $this->bonificadorDanio = (float) ($data['bonificadorDanio'] ?? 1.0);
        $this->effects = isset($data['effects']) ? unserialize($data['effects']) : new ColeccionEfectos();
        $this->pokemon = isset($data['pokemon']) ? unserialize($data['pokemon']) : throw new \RuntimeException('Missing pokemon data');
        $this->posicion = Posicion::tryFrom($data['posicion'] ?? Posicion::VANGUARDIA->value) ?? Posicion::VANGUARDIA;
        $this->estadoManager = new EstadoManager();
        $this->barrerasVidaManager = new BarrerasVidaManager();
    }

    // ─── Getters ──────────────────────────────────────────────

    public function hpActual(): float
    {
        return $this->hpActual;
    }

    public function defensaHpActual(): float
    {
        return $this->defensaHpActual;
    }

    public function defensaEspHpActual(): float
    {
        return $this->defensaEspHpActual;
    }

    public function velocidadAcumulada(): float
    {
        return $this->velocidadAcumulada;
    }

    public function vecesActuadoEstaRonda(): int
    {
        return $this->vecesActuadoEstaRonda;
    }

    public function estado(): EstadoPokemon
    {
        return $this->estado;
    }

    public function contadorVenenoGrave(): int
    {
        return $this->contadorVenenoGrave;
    }

    public function turnosEstado(): int
    {
        return $this->turnosEstado;
    }

    public function multiplicadores(): MultiplicadoresStats
    {
        return $this->multiplicadores;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function iconName(): string
    {
        return $this->iconName;
    }

    public function speciesId(): int
    {
        return $this->speciesId;
    }

    public function formSuffix(): string
    {
        return $this->formSuffix;
    }

    public function shiny(): bool
    {
        return $this->shiny;
    }

    public function item(): string
    {
        return $this->item;
    }

    public function bonificadorDanio(): float
    {
        return $this->bonificadorDanio;
    }

    public function effects(): ColeccionEfectos
    {
        return $this->effects;
    }

    public function pokemon(): PokemonEntity
    {
        return $this->pokemon;
    }

    public function posicion(): Posicion
    {
        return $this->posicion;
    }

    // ─── Setters (mínimos necesarios) ─────────────────────────

    public function setHpActual(float $hpActual): void
    {
        $this->hpActual = $hpActual;
    }

    public function setDefensaHpActual(float $defensaHpActual): void
    {
        $this->defensaHpActual = $defensaHpActual;
    }

    public function setDefensaEspHpActual(float $defensaEspHpActual): void
    {
        $this->defensaEspHpActual = $defensaEspHpActual;
    }

    public function setVelocidadAcumulada(float $velocidadAcumulada): void
    {
        $this->velocidadAcumulada = $velocidadAcumulada;
    }

    public function setVecesActuadoEstaRonda(int $vecesActuadoEstaRonda): void
    {
        $this->vecesActuadoEstaRonda = $vecesActuadoEstaRonda;
    }

    public function setEstado(EstadoPokemon $estado): void
    {
        $this->estado = $estado;
    }

    public function setContadorVenenoGrave(int $contadorVenenoGrave): void
    {
        $this->contadorVenenoGrave = $contadorVenenoGrave;
    }

    public function setTurnosEstado(int $turnosEstado): void
    {
        $this->turnosEstado = $turnosEstado;
    }

    public function setMultiplicadores(MultiplicadoresStats $multiplicadores): void
    {
        $this->multiplicadores = $multiplicadores;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setNombre(string $nombre): void
    {
        $this->nombre = $nombre;
    }

    public function setIconName(string $iconName): void
    {
        $this->iconName = $iconName;
    }

    public function setSpeciesId(int $speciesId): void
    {
        $this->speciesId = $speciesId;
    }

    public function setFormSuffix(string $formSuffix): void
    {
        $this->formSuffix = $formSuffix;
    }

    public function setShiny(bool $shiny): void
    {
        $this->shiny = $shiny;
    }

    public function setItem(string $item): void
    {
        $this->item = $item;
    }

    public function setBonificadorDanio(float $bonificador): void
    {
        $this->bonificadorDanio = $bonificador;
    }

    public function setPosicion(Posicion $posicion): void
    {
        $this->posicion = $posicion;
    }

    // ─── Métodos existentes (adaptados a getters/setters) ────

    public function aArrayVista(int $teamIdx): array
    {
        $icon = $this->speciesId === 0
            ? '/images/iconos_webp/0.webp'
            : ($this->formSuffix !== ''
                ? "/images/iconos_webp/{$this->speciesId}_{$this->formSuffix}.webp"
                : "/images/iconos_webp/{$this->speciesId}.webp");

        return [
            'refId' => $this->id,
            'nombre' => $this->nombre,
            'icon' => $icon,
            'hp' => $this->hpActual,
            'maxHp' => $this->pokemon->battleStats()->hp,
            'defHp' => $this->defensaHpActual,
            'maxDefHp' => $this->pokemon->battleStats()->defenseHp,
            'spDefHp' => $this->defensaEspHpActual,
            'maxSpDefHp' => $this->pokemon->battleStats()->spDefenseHp,
            'posicion' => $this->posicion->value,
            'alive' => $this->estaVivo(),
            'speed' => $this->pokemon->battleStats()->speed,
            'accumulatedSpeed' => $this->velocidadAcumulada,
            'status' => $this->estado->value,
            'statusTurns' => $this->turnosEstado,
            'stages' => $this->multiplicadores->obtenerEtapas(),
            'team' => $teamIdx,
            'item' => $this->item,
        ];
    }

    public function estaVivo(): bool
    {
        return $this->barrerasVidaManager->estaVivo($this->hpActual);
    }

    public function agregarVelocidad(): void
    {
        $this->velocidadAcumulada += $this->obtenerStatEfectivo(StatClave::VELOCIDAD);
    }

    public function reducirVelocidad(float $amount): void
    {
        $this->velocidadAcumulada -= $amount;
    }

    // ─── Stat Stages ─────────────────────────────────────────

    /**
     * Retorna el stat base modificado por los multiplicadores actuales.
     * La parálisis reduce la velocidad a la mitad.
     */
    public function obtenerStatEfectivo(StatClave $stat): float
    {
        $baseStat = match ($stat) {
            StatClave::ATAQUE => $this->pokemon->battleStats()->attack,
            StatClave::DEFENSA => $this->pokemon->battleStats()->defense,
            StatClave::ATAQUE_ESPECIAL => $this->pokemon->battleStats()->spAtk,
            StatClave::DEFENSA_ESPECIAL => $this->pokemon->battleStats()->spDef,
            StatClave::VELOCIDAD => $this->pokemon->battleStats()->speed,
            default => 0,
        };

        $value = $baseStat * $this->multiplicadores->obtenerMultiplicador($stat);

        // La parálisis reduce la velocidad a la mitad
        if ($stat === StatClave::VELOCIDAD && $this->estado === EstadoPokemon::PARALYSIS) {
            $value *= ReglasBatalla::REDUCCION_VELOCIDAD_PARALISIS;
        }

        return $value;
    }

    // ─── Estados (parálisis, sueño, hielo, confusión) ────────

    /**
     * Verifica si el combatiente puede actuar este turno según su estado.
     * Delega la lógica a EstadoManager y aplica las mutaciones resultantes.
     */
    public function puedeActuar(): ResultadoAccion
    {
        if ($this->estado === EstadoPokemon::NONE || ! $this->estaVivo()) {
            return ResultadoAccion::permitida();
        }

        $resultado = $this->estadoManager->puedeActuar(
            $this->estado,
            $this->turnosEstado,
            $this->hpActual,
            fn (StatClave $stat): float => $this->obtenerStatEfectivo($stat),
        );

        $this->estado = $resultado['estadoResultado'];
        $this->turnosEstado = $resultado['turnosResultado'];
        $this->hpActual = $resultado['hpResultado'];

        return self::resultadoAccionDesde($resultado);
    }

    /**
     * Traduce el veredicto de EstadoManager (canAct/reason/selfDamage) al VO.
     *
     * @param  array{canAct: bool, reason: string, selfDamage: float}  $resultado
     */
    private static function resultadoAccionDesde(array $resultado): ResultadoAccion
    {
        if (! $resultado['canAct']) {
            return $resultado['selfDamage'] > 0
                ? ResultadoAccion::denegadaConAutoDanio($resultado['reason'], $resultado['selfDamage'])
                : ResultadoAccion::denegada($resultado['reason']);
        }

        return $resultado['reason'] === ''
            ? ResultadoAccion::permitida()
            : ResultadoAccion::permitidaConMotivo($resultado['reason']);
    }

    public function tieneEfecto(string $clave): bool
    {
        return $this->effects->find($clave) !== null;
    }

    /**
     * Porcentaje de daño directo a HP que ignora barreras,
     * calculado a partir de los efectos del portador.
     */
    public function obtenerPorcentajeDanioDirecto(): float
    {
        $pct = 0.0;
        foreach ($this->effects->all() as $effect) {
            $pct += $effect->obtenerPorcentajeDanioDirecto();
        }

        return min($pct, 1.0);
    }

    public function recibirDaño(float $daño, bool $isSpecial, float $directPct = 0.0): float
    {
        $resultado = $this->barrerasVidaManager->recibirDaño(
            $this->hpActual,
            $this->defensaHpActual,
            $this->defensaEspHpActual,
            $daño,
            $isSpecial,
            $directPct,
        );

        $this->hpActual = $resultado['hpNuevo'];
        $this->defensaHpActual = $resultado['defensaHpNuevo'];
        $this->defensaEspHpActual = $resultado['defensaEspHpNuevo'];

        return $daño;
    }

    public function curarHp(float $porcentaje): void
    {
        $this->hpActual = $this->barrerasVidaManager->curarHp(
            $this->hpActual,
            $this->pokemon->battleStats()->hp,
            $porcentaje,
        );
    }

    /**
     * Aplica el daño por efecto de estado al final de la ronda.
     * Delega el cálculo a EstadoManager y aplica las mutaciones resultantes.
     *
     * @return float Daño real infligido
     */
    public function aplicarDañoStatus(): float
    {
        if (! $this->estaVivo() || $this->estado === EstadoPokemon::NONE) {
            return 0;
        }

        $resultado = $this->estadoManager->calcularDanoStatus(
            $this->estado,
            $this->contadorVenenoGrave,
            $this->pokemon->battleStats()->hp,
            $this->hpActual,
        );

        $this->hpActual = $resultado['hpActualNueva'];
        $this->contadorVenenoGrave = $resultado['contadorNuevo'];

        return $resultado['dano'];
    }

    public function curarBarreras(float $porcentaje): void
    {
        $resultado = $this->barrerasVidaManager->curarBarreras(
            $this->defensaHpActual,
            $this->defensaEspHpActual,
            $this->pokemon->battleStats()->defenseHp,
            $this->pokemon->battleStats()->spDefenseHp,
            $porcentaje,
        );

        $this->defensaHpActual = $resultado['defensaHpNuevo'];
        $this->defensaEspHpActual = $resultado['defensaEspHpNuevo'];
    }

    public function estaEnVanguardia(): bool
    {
        return $this->posicion === Posicion::VANGUARDIA;
    }

    public function estaEnRetaguardia(): bool
    {
        return $this->posicion === Posicion::RETAGUARDIA;
    }

    // ─── Event triggers (delegan a ColeccionEfectos) ─────────

    public function dispararDanioInfligido(Combatiente $target, float $daño, AgregadoBatalla $battle): void
    {
        $this->effects->dispararDanioInfligido($this, $target, $daño, $battle);
    }

    public function dispararDanioRecibido(float $daño, AgregadoBatalla $battle): void
    {
        $this->effects->dispararDanioRecibido($this, $daño, $battle);
    }

}
