<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Condiciones volátiles que conviven con el estado mayor.
 *
 * `CONFUSION` se mantiene también en EstadoPokemon por compatibilidad de API,
 * pero internamente vive aquí para permitir, por ejemplo, quemadura +
 * confusión a la vez.
 */
enum ClaveCondicion: string
{
    case CONFUSION = 'confusion';
    case DRENADORAS = 'drenadoras';
    case MALDICION = 'maldicion';
    case CANTO_MORTAL = 'canto_mortal';
    case DESAFIO = 'desafio';
    case ATRAPADO = 'atrapado';
    case FLINCH = 'flinch';

    // El objetivo recibe más daño durante N turnos. El origen del efecto
    // permite identificar al cazador que colocó la marca.
    case MARCADO = 'marcado';

    // Parte del daño recibido por el portador se transfiere al aliado
    // vinculado. La fracción y el id del vínculo viven en CondicionVolatil.
    case VINCULO_DANO = 'vinculo_dano';

    // Bloquea movimientos de estado; no impide atacar.
    case SILENCIO = 'silencio';

    // Cancela prioridad/carga declarada y consume la siguiente acción.
    case INTERRUPCION = 'interrupcion';

    // Rompe la postura y hace perder la siguiente acción.
    case ATURDIDO = 'aturdido';

    // El usuario descansa la siguiente acción tras un movimiento de recarga.
    case RECARGA = 'recarga';

    // Aliado derrotado controlado temporalmente por Marioneta Inerte.
    case MARIONETA_INERTE = 'marioneta_inerte';

    // El objeto equipado queda bloqueado durante N turnos (Embargo/Zona Mágica).
    case EMBARGO = 'embargo';

    // La habilidad del portador queda anulada durante N turnos (Jugo Gástrico).
    case HABILIDAD_ANULADA = 'habilidad_anulada';

    // El portador ignora las habilidades defensivas del rival durante N turnos.
    case IGNORA_HABILIDAD = 'ignora_habilidad';

    // Sustituto/clon: absorbe daño directo hasta agotar sus PS.
    case SUSTITUTO = 'sustituto';

    // Punto débil de jefe roto: recibe más daño durante N turnos.
    case VULNERABLE = 'vulnerable';

    // El Pokémon cambia temporalmente de tipo defensivo.
    case TIPO_ALTERADO = 'tipo_alterado';
    case MOFA = 'mofa';
    case CRITICO_MEJORADO = 'critico_mejorado';
    case PROTEGIDO = 'protegido';
    case AGUANTE = 'aguante';
    case AQUA_RING = 'aqua_ring';
    case LEVITACION = 'levitacion';
    case SIN_RETIRADA = 'sin_retirada';

    public function label(): string
    {
        return match ($this) {
            self::CONFUSION => 'confusión',
            self::DRENADORAS => 'drenadoras',
            self::MALDICION => 'maldición',
            self::CANTO_MORTAL => 'canto mortal',
            self::DESAFIO => 'desafío',
            self::ATRAPADO => 'atrapado',
            self::FLINCH => 'retroceso',
            self::MARCADO => 'marcado',
            self::VINCULO_DANO => 'vínculo de daño',
            self::SILENCIO => 'silencio',
            self::INTERRUPCION => 'interrupción',
            self::ATURDIDO => 'aturdido',
            self::RECARGA => 'recargando',
            self::MARIONETA_INERTE => 'marioneta inerte',
            self::EMBARGO => 'objeto bloqueado',
            self::HABILIDAD_ANULADA => 'habilidad anulada',
            self::IGNORA_HABILIDAD => 'ignorando habilidades',
            self::SUSTITUTO => 'sustituto',
            self::VULNERABLE => 'vulnerable',
            self::TIPO_ALTERADO => 'tipo alterado',
            self::MOFA => 'mofa',
            self::CRITICO_MEJORADO => 'crítico mejorado',
            self::PROTEGIDO => 'protegido',
            self::AGUANTE => 'aguantando',
            self::AQUA_RING => 'acua aro',
            self::LEVITACION => 'levitando',
            self::SIN_RETIRADA => 'sin retirada',
        };
    }

    /**
     * Traduce los volatileStatus de Pokémon Showdown a nuestras claves.
     */
    public static function desdePs(string $clave): ?self
    {
        return match ($clave) {
            'confusion' => self::CONFUSION,
            'leechseed' => self::DRENADORAS,
            'curse' => self::MALDICION,
            'perishsong' => self::CANTO_MORTAL,
            'partiallytrapped' => self::ATRAPADO,
            'flinch' => self::FLINCH,
            'taunt' => self::DESAFIO,
            'mark' => self::MARCADO,
            'hunt' => self::MARCADO,
            'damage_link' => self::VINCULO_DANO,
            'silence' => self::SILENCIO,
            'interrupt' => self::INTERRUPCION,
            'stagger' => self::ATURDIDO,
            'recharge' => self::RECARGA,
            'marionette' => self::MARIONETA_INERTE,
            'embargo' => self::EMBARGO,
            'gastroacid' => self::HABILIDAD_ANULADA,
            'substitute' => self::SUSTITUTO,
            'vulnerable' => self::VULNERABLE,
            'typechange' => self::TIPO_ALTERADO,
            'taunt' => self::MOFA,
            'focusenergy' => self::CRITICO_MEJORADO,
            'protect' => self::PROTEGIDO,
            'endure' => self::AGUANTE,
            'aquaring' => self::AQUA_RING,
            'magnetrise' => self::LEVITACION,
            'noretreat' => self::SIN_RETIRADA,
            default => null,
        };
    }
}
