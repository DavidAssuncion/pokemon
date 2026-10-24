<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Flags de movimiento de Pokémon Showdown. Solo se guardan las que afectan a
 * mecánicas del juego; las puramente cosméticas o de formatos no se usan.
 */
enum FlagMovimiento: string
{
    case CONTACTO = 'contact';
    case PROTECCION = 'protect';
    case ESPEJO = 'mirror';
    case PUÑETAZO = 'punch';
    case SONIDO = 'sound';
    case BALA = 'bullet';
    case PULSO = 'pulse';
    case MORDISCO = 'bite';
    case CORTE = 'slicing';
    case VIENTO = 'wind';
    case DANZA = 'dance';
    case POLVO = 'powder';
    case CURACION = 'heal';
    case CARGA = 'charge';
    case RECARGA = 'recharge';
    case MOVIMIENTO_FUTURO = 'futuremove';
    case METRONOMO = 'metronome';
    case DESCONGELA = 'defrost';
    case MINIMIZAR = 'minimize';
    case GRAVEDAD = 'gravity';
    case SIN_CIELO = 'nonsky';
    case REFLEJABLE = 'reflectable';
    case ARREBATAR = 'snatch';
    case DISTANCIA = 'distance';
    case ANIMACION_ALIADO = 'allyanim';
    case IGNORA_SUSTITUTO = 'bypasssub';
    case NO_REPETIBLE = 'cantusetwice';
    case NO_COPIABLE = 'failcopycat';
    case NO_ENCORE = 'failencore';
    case NO_INSTRUCT = 'failinstruct';
    case NO_ME_FIRST = 'failmefirst';
    case NO_MIMIC = 'failmimic';
    case NO_ASSIST = 'noassist';
    case NO_PARENTAL_BOND = 'noparentalbond';
    case NO_SKETCH = 'nosketch';
    case NO_SLEEP_TALK = 'nosleeptalk';
    case PLEDGE_COMBO = 'pledgecombo';
    case PRESION_EXTRA = 'mustpressure';

    public function label(): string
    {
        return match ($this) {
            self::CONTACTO => 'contacto',
            self::PUÑETAZO => 'puñetazo',
            self::SONIDO => 'sonido',
            self::BALA => 'bala',
            self::PULSO => 'pulso',
            self::MORDISCO => 'mordisco',
            self::CORTE => 'corte',
            self::VIENTO => 'viento',
            self::DANZA => 'danza',
            self::POLVO => 'polvo',
            default => $this->value,
        };
    }
}
