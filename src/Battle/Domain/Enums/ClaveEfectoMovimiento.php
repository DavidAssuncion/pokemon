<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Claves de los ataques que necesitan una lógica propia (efectos de campo,
 * temporales o reacciones) y que no se pueden expresar solo con el perfil
 * numérico del movimiento.
 */
enum ClaveEfectoMovimiento: string
{
    case DRENADORAS = 'drenadoras';
    case MALDICION = 'maldicion';
    case PUAS = 'puas';
    case PUAS_TOXICAS = 'puas_toxicas';
    case TRAMPA_ROCAS = 'trampa_rocas';
    case CANTO_MORTAL = 'canto_mortal';
    case DESAFIO = 'desafio';
    case MARIONETA_INERTE = 'marioneta_inerte';
    case PANTALLA_LUZ = 'pantalla_luz';
    case REFLEJO = 'reflejo';
    case REPRESION_METAL = 'represion_metal';
    case PICO_TALADRO = 'pico_taladro';
    case PERSECUCION = 'persecucion';
    case SUPERDIENTE = 'superdiente';
    case PODER_PASADO = 'poder_pasado';
    case TENAZA = 'tenaza';
    case FOCO_RESPLANDOR = 'foco_resplandor';
    case PUNO_METEORO = 'puno_meteoro';
    case PUNO_SOMBRA = 'puno_sombra';
    case BRECHA_NEGRA = 'brecha_negra';
    case VELOCIDAD_EXTREMA = 'velocidad_extrema';
    case TRICK_ROOM = 'trick_room';
    case TAILWIND = 'tailwind';
    case MARCA = 'marca';
    case VINCULO_DANO = 'vinculo_dano';
    case REVIVIR = 'revivir';
    case INVOCAR = 'invocar';
    case INTERACCION_OBJETO = 'interaccion_objeto';
    case INTERACCION_HABILIDAD = 'interaccion_habilidad';
    case SUSTITUTO = 'sustituto';
    case CAMBIO_TIPO = 'cambio_tipo';
    case DANO_DIFERIDO = 'dano_diferido';
    case METEOROBOLA = 'meteorobola';
    case PULSO_CAMPO = 'pulso_campo';
    case FURIA_TAURINA = 'furia_taurina';
    case HOMENAJE_POSTUMO = 'homenaje_postumo';
    case ALLANADOR_FERREO = 'allanador_ferreo';
    case PALIZA = 'paliza';
    case PANTALLA_AURORA = 'pantalla_aurora';
    case TERRENO_ELECTRICO = 'terreno_electrico';
    case TERRENO_HIERBA = 'terreno_hierba';
    case TERRENO_PSIQUICO = 'terreno_psiquico';
    case TERRENO_NIEBLA = 'terreno_niebla';
    case MUD_SPORT = 'mud_sport';
    case ROTOTILLER = 'rototiller';
    case AGUIJON_LETAL = 'aguijon_letal';
    case NIEBLA_CLARA = 'niebla_clara';
    case ARIA_BURBUJA = 'aria_burbuja';
    case CANTO_ENCANTADOR = 'canto_encantador';
    case NIEBLA = 'niebla';
    case REFRESCO = 'refresco';
    case PSICOCARGA = 'psicocarga';
    case FOCO_ENERGIA = 'foco_energia';
    case DESCANSO = 'descanso';
    case DIVIDE_DOLOR_MOVIMIENTO = 'divide_dolor_movimiento';
    case MOFA = 'mofa';
    case INTERCAMBIO_PODER = 'intercambio_poder';
    case INTERCAMBIO_DEFENSA = 'intercambio_defensa';
    case INTERCAMBIO_VELOCIDAD = 'intercambio_velocidad';
    case AROMATERAPIA = 'aromaterapia';
    case PROTECCION = 'proteccion';
    case AGUANTE = 'aguante';
    case CLIMA_SEQUIA = 'clima_sequia';
    case CLIMA_DILUVIO = 'clima_diluvio';
    case CLIMA_ARENA = 'clima_arena';
    case CLIMA_GRANIZO = 'clima_granizo';
    case SAFEGUARD = 'safeguard';
    case MIST = 'mist';
    case LUCKY_CHANT = 'lucky_chant';
    case CURACION_ALIADO = 'curacion_aliado';
    case CURACION_EQUIPO = 'curacion_equipo';
    case CURACION_EQUIPO_ESTADO = 'curacion_equipo_estado';
    case PURIFICAR = 'purificar';
    case DRENAJE_ESTADISTICA = 'drenaje_estadistica';
    case AQUA_RING = 'aqua_ring';
    case INVERTIR_STATS = 'invertir_stats';
    case TAMBOR = 'tambor';
    case ACUPRESION = 'acupresion';
    case LEVITACION_TEMPORAL = 'levitacion_temporal';
    case SIN_RETIRADA = 'sin_retirada';
    case ATIBORRAMIENTO = 'atiborramiento';
    case FILLET_AWAY = 'fillet_away';
    case TOMAR_ALIENTO = 'tomar_aliento';
    case STATS_ALIADO = 'stats_aliado';
    case DRAGON_CHEER = 'dragon_cheer';
    case DANZA_REVELACION = 'danza_revelacion';
}
