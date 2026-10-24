<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Estados y condiciones pendientes de implementar.
 *
 * Se dejan aquí, con una descripción por caso, para que el catálogo y la UI
 * puedan referenciarlos sin inventar strings. No tienen comportamiento aún:
 * cuando se implementen pasarán a ClaveCondicion o a una condición de equipo.
 */
enum EstadoPendiente: string
{
    // Obliga al objetivo a repetir su último movimiento durante N turnos.
    case ENCORE = 'encore';

    // Bloquea uno de los movimientos del objetivo durante N turnos.
    case DESACTIVAR = 'disable';

    // El objetivo no puede repetir el mismo movimiento dos veces seguidas.
    case TORMENTO = 'torment';

    // El objetivo solo puede usar movimientos de ataque (no de estado).
    case MOFA = 'taunt';

    // Probabilidad de que el objetivo no actúe por atracción.
    case ATRACCION = 'attract';

    // Aplica sueño al final del turno siguiente.
    case BOSTEZO = 'yawn';

    // El objetivo dormido recibe daño adicional cada turno.
    case PESADILLA = 'nightmare';

    // Impide curarse durante N turnos.
    case BLOQUEO_CURACION = 'heal_block';

    // El objetivo no puede usar su objeto equipado.
    case EMBARGO = 'embargo';

    // Inmunidad a estados alterados para el equipo.
    case SALVAGUARDA = 'safeguard';

    // Inmunidad a reducciones de estadísticas para el equipo.
    case NIEBLA = 'mist';

    // Inmunidad a críticos para el equipo.
    case CANTO_SUERTE = 'lucky_chant';

    // Elimina las etapas de estadísticas de todos los combatientes.
    case RESET_ESTADISTICAS = 'haze';

    // Si el portador cae, el rival que lo debilitó también cae.
    case DESTINO_FATAL = 'destiny_bond';

    // El rival pierde PP/recursos del movimiento que debilitó al portador.
    case RENCOR = 'grudge';

    // Reparte la diferencia de HP entre ambos combatientes.
    case DIVISION_DOLOR = 'pain_split';

    // Curación diferida al final del segundo turno.
    case DESEO = 'wish';

    // Sacrifica al usuario para curar por completo a un aliado.
    case DESEO_CURATIVO = 'healing_wish';

    // Sacrifica al usuario para curar y restaurar estado a un aliado.
    case DANZA_LUNAR = 'lunar_dance';

}
