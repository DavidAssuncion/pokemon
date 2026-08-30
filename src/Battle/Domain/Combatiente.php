<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Effects\ColeccionEfectos;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\ValueObjects\EtapasStats;
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

    private EtapasStats $etapas;

    private string $id = '';

    private string $nombre = '';

    private string $iconName = '';

    private int $speciesId = 0;

    private string $formSuffix = '';

    private bool $shiny = false;

    private string $item = '';

    private ColeccionEfectos $effects;

    private PokemonEntity $pokemon;

    private Posicion $posicion;

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
        $this->etapas = new EtapasStats([
            'attack' => 0,
            'defense' => 0,
            'spAtk' => 0,
            'spDef' => 0,
            'speed' => 0,
            'accuracy' => 0,
            'evasion' => 0,
        ]);
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
            'etapas' => $this->etapas->toArray(),
            'id' => $this->id,
            'nombre' => $this->nombre,
            'iconName' => $this->iconName,
            'speciesId' => $this->speciesId,
            'formSuffix' => $this->formSuffix,
            'shiny' => $this->shiny,
            'item' => $this->item,
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
        $this->estado = EstadoPokemon::tryFrom($data['estado'] ?? 'none') ?? EstadoPokemon::NONE;
        $this->contadorVenenoGrave = (int) ($data['contadorVenenoGrave'] ?? 0);
        $this->turnosEstado = (int) ($data['turnosEstado'] ?? 0);
        $this->etapas = new EtapasStats((array) ($data['etapas'] ?? []));
        $this->id = (string) ($data['id'] ?? '');
        $this->nombre = (string) ($data['nombre'] ?? '');
        $this->iconName = (string) ($data['iconName'] ?? '');
        $this->speciesId = (int) ($data['speciesId'] ?? 0);
        $this->formSuffix = (string) ($data['formSuffix'] ?? '');
        $this->shiny = (bool) ($data['shiny'] ?? false);
        $this->item = (string) ($data['item'] ?? '');
        $this->effects = isset($data['effects']) ? unserialize($data['effects']) : new ColeccionEfectos();
        $this->pokemon = isset($data['pokemon']) ? unserialize($data['pokemon']) : throw new \RuntimeException('Missing pokemon data');
        $this->posicion = Posicion::tryFrom($data['posicion'] ?? 'vanguardia') ?? Posicion::VANGUARDIA;
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

    public function etapas(): EtapasStats
    {
        return $this->etapas;
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

    public function setEtapas(EtapasStats $etapas): void
    {
        $this->etapas = $etapas;
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
            'stages' => $this->etapas->toArray(),
            'team' => $teamIdx,
            'item' => $this->item,
        ];
    }

    public function estaVivo(): bool
    {
        return $this->hpActual > 0;
    }

    public function reinicarVelocidadAcumulada(): void
    {
        $this->velocidadAcumulada = 0;
    }

    public function agregarVelocidad(): void
    {
        $this->velocidadAcumulada += $this->obtenerStatEfectivo('speed');
    }

    public function reducirVelocidad(float $amount): void
    {
        $this->velocidadAcumulada -= $amount;
    }

    // ─── Stat Stages ─────────────────────────────────────────

    /**
     * Aplica un cambio de stage (-6 a +6) a una estadística.
     */
    public function aplicarCambioEtapa(string $stat, int $change): void
    {
        $this->etapas = $this->etapas->aplicarCambio($stat, $change);
    }

    /**
     * Retorna el stat base modificado por los stages actuales.
     * La parálisis reduce la velocidad a la mitad.
     */
    public function obtenerStatEfectivo(string $stat): float
    {
        $baseStat = match ($stat) {
            'attack' => $this->pokemon->battleStats()->attack,
            'defense' => $this->pokemon->battleStats()->defense,
            'spAtk' => $this->pokemon->battleStats()->spAtk,
            'spDef' => $this->pokemon->battleStats()->spDef,
            'speed' => $this->pokemon->battleStats()->speed,
            default => 0,
        };

        $value = $this->etapas->obtener($stat) === 0
            ? $baseStat
            : $baseStat * $this->etapas->obtenerMultiplicador($stat);

        // La parálisis reduce la velocidad a la mitad
        if ($stat === 'speed' && $this->estado === EstadoPokemon::PARALYSIS) {
            $value *= 0.5;
        }

        return $value;
    }

    /**
     * Retorna un array con los stages no neutros para mostrar en UI.
     *
     * @return array<string, int>
     */
    public function obtenerEtapasNoNeutras(): array
    {
        return $this->etapas->obtenerNoNeutras();
    }

    // ─── Estados (parálisis, sueño, hielo, confusión) ────────

    /**
     * Verifica si el combatiente puede actuar este turno según su estado.
     * También gestiona contadores (sueño, confusión) y auto-daño (confusión).
     *
     * @return array{canAct: bool, reason: string, selfDamage: float}
     */
    public function puedeActuar(): array
    {
        if ($this->estado === EstadoPokemon::NONE || ! $this->estaVivo()) {
            return ['canAct' => true, 'reason' => '', 'selfDamage' => 0.0];
        }

        return match ($this->estado) {
            EstadoPokemon::SLEEP => $this->procesarSleep(),
            EstadoPokemon::FREEZE => $this->procesarFreeze(),
            EstadoPokemon::PARALYSIS => $this->procesarParalysis(),
            EstadoPokemon::CONFUSION => $this->procesarConfusion(),
            default => ['canAct' => true, 'reason' => '', 'selfDamage' => 0.0],
        };
    }

    private function procesarSleep(): array
    {
        if ($this->turnosEstado <= 0) {
            $this->estado = EstadoPokemon::NONE;

            return ['canAct' => true, 'reason' => 'despertó', 'selfDamage' => 0.0];
        }

        $this->turnosEstado--;

        return ['canAct' => false, 'reason' => 'está dormido', 'selfDamage' => 0.0];
    }

    private function procesarFreeze(): array
    {
        if (mt_rand(1, 100) <= 20) {
            $this->estado = EstadoPokemon::NONE;

            return ['canAct' => true, 'reason' => 'se descongeló', 'selfDamage' => 0.0];
        }

        return ['canAct' => false, 'reason' => 'está congelado', 'selfDamage' => 0.0];
    }

    private function procesarParalysis(): array
    {
        if (mt_rand(1, 100) <= 25) {
            return ['canAct' => false, 'reason' => 'está paralizado', 'selfDamage' => 0.0];
        }

        return ['canAct' => true, 'reason' => '', 'selfDamage' => 0.0];
    }

    private function procesarConfusion(): array
    {
        $seAgoto = $this->turnosEstado <= 0;

        if ($seAgoto) {
            $this->estado = EstadoPokemon::NONE;
            $this->turnosEstado = 0;

            return ['canAct' => true, 'reason' => 'salió de confusión', 'selfDamage' => 0.0];
        }

        $this->turnosEstado--;

        if (mt_rand(1, 100) <= 33) {
            $atk = $this->obtenerStatEfectivo('attack');
            $def = $this->obtenerStatEfectivo('defense');
            $daño = max(1, ((((2 * 50 / 5 + 2) * 40 * $atk / max($def, 1)) / 50) + 2));
            $this->hpActual = max(0, $this->hpActual - $daño);

            return ['canAct' => false, 'reason' => 'se golpeó por confusión', 'selfDamage' => $daño];
        }

        return ['canAct' => true, 'reason' => '', 'selfDamage' => 0.0];
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
        $dañoDirecto = $daño * $directPct;
        $dañoBarreras = $daño - $dañoDirecto;

        $this->hpActual -= $dañoDirecto;

        $barrera = $isSpecial ? $this->defensaEspHpActual : $this->defensaHpActual;
        $dañoBarrera = min($barrera, $dañoBarreras);

        if ($isSpecial) {
            $this->defensaEspHpActual -= $dañoBarrera;
        } else {
            $this->defensaHpActual -= $dañoBarrera;
        }

        $excedente = $dañoBarreras - $dañoBarrera;

        if ($excedente > 0) {
            $this->hpActual -= $excedente;
        }

        if ($this->hpActual < 0) {
            $this->hpActual = 0;
        }

        return $daño;
    }

    public function curarHp(float $porcentaje): void
    {
        $this->hpActual = min(
            $this->pokemon->battleStats()->hp,
            $this->hpActual + $this->pokemon->battleStats()->hp * $porcentaje / 100
        );
    }

    /**
     * Aplica el daño por efecto de estado al final de la ronda.
     *
     * @return float Daño real infligido
     */
    public function aplicarDañoStatus(): float
    {
        if (! $this->estaVivo() || $this->estado === EstadoPokemon::NONE) {
            return 0;
        }

        if (! $this->estado->causaDanoPorRonda()) {
            return 0;
        }

        $maxHp = $this->pokemon->battleStats()->hp;
        $daño = match ($this->estado) {
            EstadoPokemon::BURN => max(1, $maxHp * 0.0625),
            EstadoPokemon::POISON => max(1, $maxHp * 0.125),
            EstadoPokemon::BAD_POISON => max(1, $maxHp * $this->contadorVenenoGrave / 16),
            default => 0,
        };

        if ($daño <= 0) {
            return 0;
        }

        $this->hpActual = max(0, $this->hpActual - $daño);

        if ($this->estado === EstadoPokemon::BAD_POISON) {
            $this->contadorVenenoGrave++;
        }

        return $daño;
    }

    public function curarBarreras(float $porcentaje): void
    {
        $this->defensaHpActual = min(
            $this->pokemon->battleStats()->defenseHp,
            $this->defensaHpActual + $this->pokemon->battleStats()->defenseHp * $porcentaje / 100
        );
        $this->defensaEspHpActual = min(
            $this->pokemon->battleStats()->spDefenseHp,
            $this->defensaEspHpActual + $this->pokemon->battleStats()->spDefenseHp * $porcentaje / 100
        );
    }

    public function puedeAtacarRetaguardia(): bool
    {
        return $this->posicion === Posicion::RETAGUARDIA;
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

    public function triggerHealed(float $cantidad): void
    {
        $this->effects->triggerHealed($this, $cantidad);
    }

    public function dispararDebilitado(): void
    {
        $this->effects->dispararDebilitado($this);
    }

    public function dispararInicioTurno(AgregadoBatalla $battle): void
    {
        $this->effects->dispararInicioTurno($this, $battle);
    }

    public function dispararFinTurno(AgregadoBatalla $battle): void
    {
        $this->effects->dispararFinTurno($this, $battle);
    }
}
