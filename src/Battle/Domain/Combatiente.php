<?php

namespace Src\Battle\Domain;

use Src\Battle\Domain\Effects\ColeccionEfectos;
use Src\Battle\Domain\Effects\InterfazEfecto;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Pokemon\Domain\PokemonEntity;

#[AllowDynamicProperties]
class Combatiente
{
    public const STATUS_LABELS = [
        'burn' => 'quemadura',
        'poison' => 'envenenamiento',
        'bad_poison' => 'envenenamiento grave',
        'paralysis' => 'parálisis',
        'sleep' => 'sueño',
        'freeze' => 'congelación',
        'confusion' => 'confusión',
    ];
    public float $hpActual;
    public float $defensaHpActual;
    public float $defensaEspHpActual;
    public float $velocidadAcumulada = 0;
    public int $vecesActuadoEstaRonda = 0;
    public string $estado = 'none';
    public int $contadorVenenoGrave = 0;
    public int $turnosEstado = 0;

    /** @var array<string, int> Stat stages de -6 a +6 */
    public array $etapas = [
        'attack' => 0,
        'defense' => 0,
        'spAtk' => 0,
        'spDef' => 0,
        'speed' => 0,
        'accuracy' => 0,
        'evasion' => 0,
    ];
    public string $id = '';
    public string $nombre = '';
    public string $iconName = '';
    public bool $shiny = false;
    public string $item = '';
    public ColeccionEfectos $effects;

    public function __construct(
        public readonly PokemonEntity $pokemon,
        public Posicion $posicion,
    ) {
        $this->hpActual = $pokemon->battleStats->hp;
        $this->defensaHpActual = $pokemon->battleStats->defenseHp;
        $this->defensaEspHpActual = $pokemon->battleStats->spDefenseHp;
        $this->effects = new ColeccionEfectos();
    }

    public function __wakeup(): void
    {
        // Migrar desde antiguos nombres de propiedades en inglés
        // (sesiones guardadas durante el refactor a español)
        // property_exists detecta propiedades dinámicas (AllowDynamicProperties)
        if (property_exists($this, 'position')) {
            $this->posicion = $this->position;
            unset($this->position);
        }
        if (property_exists($this, 'currentHp')) {
            $this->hpActual = (float)$this->currentHp;
        }
        if (property_exists($this, 'currentDefenseHp')) {
            $this->defensaHpActual = (float)$this->currentDefenseHp;
        }
        if (property_exists($this, 'currentSpDefenseHp')) {
            $this->defensaEspHpActual = (float)$this->currentSpDefenseHp;
        }
        if (property_exists($this, 'accumulatedSpeed')) {
            $this->velocidadAcumulada = (float)$this->accumulatedSpeed;
        }
        if (property_exists($this, 'timesActedThisRound')) {
            $this->vecesActuadoEstaRonda = (int)$this->timesActedThisRound;
        }
        if (property_exists($this, 'status')) {
            $this->estado = (string)$this->status;
        }
        if (property_exists($this, 'badPoisonCounter')) {
            $this->contadorVenenoGrave = (int)$this->badPoisonCounter;
        }
        if (property_exists($this, 'statusTurns')) {
            $this->turnosEstado = (int)$this->statusTurns;
        }
        if (property_exists($this, 'stages')) {
            $this->etapas = $this->stages;
        }

        // Asegurar defaults (??= no asigna si ya tiene valor no-null)
        $this->hpActual ??= 0;
        $this->defensaHpActual ??= 0;
        $this->defensaEspHpActual ??= 0;
        $this->velocidadAcumulada ??= 0;
        $this->vecesActuadoEstaRonda ??= 0;
        $this->estado ??= 'none';
        $this->contadorVenenoGrave ??= 0;
        $this->turnosEstado ??= 0;
        $this->etapas ??= [];
        $this->id ??= '';
        $this->nombre ??= '';
        $this->iconName ??= '';
        $this->shiny ??= false;
        $this->item ??= '';
        $this->effects ??= new ColeccionEfectos();
        $this->posicion ??= Posicion::VANGUARDIA;
    }

    public function aArrayVista(int $teamIdx): array
    {
        $iconName = $this->iconName ?: strtolower($this->nombre);
        $icon = $this->shiny ? "/iconos/shiny/{$iconName}.png" : "/iconos/{$iconName}.png";

        return [
            'refId' => $this->id,
            'nombre' => $this->nombre,
            'icon' => $icon,
            'hp' => $this->hpActual,
            'maxHp' => $this->pokemon->battleStats->hp,
            'defHp' => $this->defensaHpActual,
            'maxDefHp' => $this->pokemon->battleStats->defenseHp,
            'spDefHp' => $this->defensaEspHpActual,
            'maxSpDefHp' => $this->pokemon->battleStats->spDefenseHp,
            'posicion' => $this->posicion->value,
            'alive' => $this->estaVivo(),
            'speed' => $this->pokemon->battleStats->speed,
            'accumulatedSpeed' => $this->velocidadAcumulada,
            'status' => $this->estado,
            'statusTurns' => $this->turnosEstado,
            'stages' => $this->etapas,
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
        if (!array_key_exists($stat, $this->etapas)) {
            return;
        }
        $this->etapas[$stat] = max(-6, min(6, $this->etapas[$stat] + $change));
    }

    /**
     * Multiplicador según el stage:
     *  N>0: (2+N)/2   (ej: +1→×1.5, +2→×2, +6→×4)
     *  N<0: 2/(2-N)   (ej: -1→×0.66, -2→×0.5, -6→×0.25)
     */
    public function obtenerMultiplicadorEtapa(int $stage): float
    {
        if ($stage >= 0) {
            return (2 + $stage) / 2;
        }
        return 2 / (2 - $stage);
    }

    /**
     * Retorna el stat base modificado por los stages actuales.
     * La parálisis reduce la velocidad a la mitad.
     */
    public function obtenerStatEfectivo(string $stat): float
    {
        $baseStat = match ($stat) {
            'attack' => $this->pokemon->battleStats->attack,
            'defense' => $this->pokemon->battleStats->defense,
            'spAtk' => $this->pokemon->battleStats->spAtk,
            'spDef' => $this->pokemon->battleStats->spDef,
            'speed' => $this->pokemon->battleStats->speed,
            default => 0,
        };

        $stage = $this->etapas[$stat] ?? 0;
        $value = $stage === 0 ? $baseStat : $baseStat * $this->obtenerMultiplicadorEtapa($stage);

        // La parálisis reduce la velocidad a la mitad
        if ($stat === 'speed' && $this->estado === 'paralysis') {
            $value *= 0.5;
        }

        return $value;
    }

    /**
     * Retorna un array con los stages no neutros para mostrar en UI.
     * @return array<string, int>
     */
    public function obtenerEtapasNoNeutras(): array
    {
        return array_filter($this->etapas, fn(int $v) => $v !== 0);
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
        if ($this->estado === 'none' || !$this->estaVivo()) {
            return ['canAct' => true, 'reason' => '', 'selfDamage' => 0.0];
        }

        return match ($this->estado) {
            'sleep' => $this->procesarSleep(),
            'freeze' => $this->procesarFreeze(),
            'paralysis' => $this->procesarParalysis(),
            'confusion' => $this->procesarConfusion(),
            default => ['canAct' => true, 'reason' => '', 'selfDamage' => 0.0],
        };
    }

    private function procesarSleep(): array
    {
        // Ya se cumplieron los turnos de sueño?
        if ($this->turnosEstado <= 0) {
            $this->estado = 'none';
            return ['canAct' => true, 'reason' => 'despertó', 'selfDamage' => 0.0];
        }

        $this->turnosEstado--;
        return ['canAct' => false, 'reason' => 'está dormido', 'selfDamage' => 0.0];
    }

    private function procesarFreeze(): array
    {
        // 20% de descongelarse cada turno
        if (mt_rand(1, 100) <= 20) {
            $this->estado = 'none';
            return ['canAct' => true, 'reason' => 'se descongeló', 'selfDamage' => 0.0];
        }

        return ['canAct' => false, 'reason' => 'está congelado', 'selfDamage' => 0.0];
    }

    private function procesarParalysis(): array
    {
        // 25% de no poder moverse
        if (mt_rand(1, 100) <= 25) {
            return ['canAct' => false, 'reason' => 'está paralizado', 'selfDamage' => 0.0];
        }

        return ['canAct' => true, 'reason' => '', 'selfDamage' => 0.0];
    }

    private function procesarConfusion(): array
    {
        // La confusión se agota ANTES de verificar auto-daño
        $seAgoto = $this->turnosEstado <= 0;

        if ($seAgoto) {
            $this->estado = 'none';
            $this->turnosEstado = 0;
            return ['canAct' => true, 'reason' => 'salió de confusión', 'selfDamage' => 0.0];
        }

        // Decrementar contador
        $this->turnosEstado--;

        // 33% de lastimarse a sí mismo (solo si no se agotó este turno)
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

        // Aplica daño directo a la salud (ignora barreras)
        $this->hpActual -= $dañoDirecto;

        // Aplica el resto a barreras
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
            $this->pokemon->battleStats->hp,
            $this->hpActual + $this->pokemon->battleStats->hp * $porcentaje / 100
        );
    }

    /**
     * Aplica el daño por efecto de estado al final de la ronda.
     * @return float Daño real infligido
     */
    public function aplicarDañoStatus(): float
    {
        if (!$this->estaVivo() || $this->estado === 'none') {
            return 0;
        }

        $maxHp = $this->pokemon->battleStats->hp;
        $daño = match ($this->estado) {
            'burn' => max(1, $maxHp * 0.0625),        // 1/16
            'poison' => max(1, $maxHp * 0.125),        // 1/8
            'bad_poison' => max(1, $maxHp * $this->contadorVenenoGrave / 16), // aumenta cada ronda
            default => 0,
        };

        if ($daño <= 0) {
            return 0;
        }

        $this->hpActual = max(0, $this->hpActual - $daño);

        // Incrementar contador de tóxico grave para la próxima ronda
        if ($this->estado === 'bad_poison') {
            $this->contadorVenenoGrave++;
        }

        return $daño;
    }

    public function curarBarreras(float $porcentaje): void
    {
        $this->defensaHpActual = min(
            $this->pokemon->battleStats->defenseHp,
            $this->defensaHpActual + $this->pokemon->battleStats->defenseHp * $porcentaje / 100
        );
        $this->defensaEspHpActual = min(
            $this->pokemon->battleStats->spDefenseHp,
            $this->defensaEspHpActual + $this->pokemon->battleStats->spDefenseHp * $porcentaje / 100
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
