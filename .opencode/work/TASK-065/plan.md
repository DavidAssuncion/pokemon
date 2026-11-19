# TASK-065 — Plan

- **ID:** TASK-065
- **Versión:** v1
- **Fecha:** 2026-11-19
- **Estado:** APROBADO — implementado y revisado; validación manual pendiente

## Objetivo

Rediseñar la habilidad **Ausente** de Slaking para que:

1. Anuncie visualmente que estará ausente en su siguiente turno.
2. Si completa el descanso sin recibir daño directo, recupere 1/16 de sus escudos
   (barreras física y especial).
3. Si recibe daño directo **antes de su turno**, se enfade: cancele la ausencia,
   pueda actuar ese turno y gane +1 nivel de Ataque temporal de un turno.
4. El daño recibido **después** del descanso no le devuelva la acción.

## Comportamiento resultante (para validar en la aprobación)

- Ronda 1: actúa. Al terminar, se anuncia que estará ausente en la ronda 2.
- Ronda 2:
  - Si un rival más rápido le hace daño directo antes de su hueco: se enfada, se
    quita el aviso, sube +1 Ataque (×1.30 temporal), actúa y, al terminar, vuelve a
    quedar anunciado para la ronda 3.
  - Si no recibe daño antes de su hueco: pierde el turno, recupera 1/16 de ambas
    barreras en ese momento y el aviso desaparece. Si un rival más lento le ataca
    después, ya no recupera la acción.
  - Daño indirecto (clima, veneno, retroceso) no provoca enfado.
- Multigolpe: solo un enfado y un boost.
- El boost se retira al terminar su turno.

## Alcance

### Backend

1. `ClaveCondicion`: nueva `AUSENTE` + label.
2. `EstadosCombatiente`: rama `AUSENTE` primera en `veredictoCondicionBloqueante`.
3. `MultiplicadoresStats`: `retirarTemporal(StatClave, float)` para quitar un stack
   temporal concreto y dejar `turnosTemporales` a 0 si no quedan.
4. Nueva capacidad `ReaccionaAlPerderTurno` en
   `src/Battle/Domain/Effects/Capacidades/`.
5. `ColeccionEfectos`: `dispararTurnoPerdido()`.
6. `EfectoTruant`: máquina de estados completa (`descansar`, `ausenciaAnunciada`,
   `enfadado`), `onRoundStart`, `alRecibirDanio`, `alPerderTurno`, `onTurnEnd`,
   `onRoundEnd`, `onFainted`, `__clone`, helper de cargas pendientes; elimina
   `onTurnStart`.
7. `GestorCicloTurnosBatalla`, `GestorPrioridadesBatalla` y `MotorRondas`: una
   llamada genérica a `dispararTurnoPerdido` en la rama de denegación.
8. `CombatienteVista`: campos `ausente` y `ausenteTooltip`; excluir `'ausente'` de
   `condicionesBadge`.
9. `storage/data/abilities_descripciones.json`: actualizar solo la descripción de
   `ausente` (el archivo está sin commitear por TASK-061; cambio aditivo y preciso).

### Frontend

10. `resources/views/livewire/_pokemon-card.blade.php`: badge `AUSENTE` con tooltip.

## Fuera de alcance

- ADR y documentación (el AGENTS.md del proyecto solo permite crearla si se pide).
- `active/catalogo_final_habilidades.csv`.
- Cambiar el orden de `onTurnStart`/`dispararInicioTurno` para `EfectoVeleta` u otros
  efectos (deuda separada).
- Otras habilidades, UI adicional, migraciones o dependencias.
- Tests: no se crean, actualizan ni ejecutan.

## Diseño de referencia

`architecture.md` contiene la máquina de estados, el contrato de la capacidad, los
tres puntos de despacho, la serialización y las alternativas descartadas.

Puntos clave:

- El anuncio ocurre en `onRoundStart`, antes de elegir acción; no se reordena
  Livewire y se corrige de paso el bloqueo poco fiable actual.
- La condición `AUSENTE` la consume `puedeActuar`; el enfado la retira antes.
- La curación se ejecuta en `alPerderTurno` (al perder el turno), no en
  `onRoundEnd`.
- El boost usa `CambioStat::desdeEtapas(ATAQUE, 1)->aplicadoTemporalEn()` y se retira
  en `onTurnEnd` con `retirarTemporal`.
- `SESSION_VERSION` se mantiene en 21; las propiedades nuevas tienen default.

## Archivos y responsables

| # | Responsable | Archivo | Cambio |
|---|---|---|---|
| 1 | `backend` | `src/Battle/Domain/Enums/ClaveCondicion.php` | `AUSENTE` + label |
| 2 | `backend` | `src/Battle/Domain/Componentes/Combatiente/EstadosCombatiente.php` | Rama `AUSENTE` |
| 3 | `backend` | `src/Battle/Domain/ValueObjects/MultiplicadoresStats.php` | `retirarTemporal` |
| 4 | `backend` | `src/Battle/Domain/Effects/Capacidades/ReaccionaAlPerderTurno.php` | nueva interfaz |
| 5 | `backend` | `src/Battle/Domain/Effects/ColeccionEfectos.php` | `dispararTurnoPerdido` |
| 6 | `backend` | `src/Battle/Domain/Effects/EfectoTruant.php` | rediseño |
| 7 | `backend` | `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php` | despacho al denegar |
| 8 | `backend` | `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` | despacho al denegar |
| 9 | `backend` | `src/Battle/Domain/Componentes/Agregado/MotorRondas.php` | despacho al denegar |
| 10 | `backend` | `src/Battle/Domain/CombatienteVista.php` | campos + filtro |
| 11 | `frontend` | `resources/views/livewire/_pokemon-card.blade.php` | badge |
| 12 | `backend` | `storage/data/abilities_descripciones.json` | descripción |

Sin `database`, `documenter` ni `security`.

## Orden de ejecución

1. Backend 1 → 2 → 3 → 4 → 5 → 6.
2. Backend 7, 8 y 9 (tres llamadas genéricas).
3. Backend 10 y descripción 12.
4. Frontend 11.
5. Verificación estática.
6. Revisión independiente.
7. Validación manual del desarrollador.

## Criterios de aceptación

1. `AUSENTE` se anuncia al inicio de la ronda y el badge es visible; la condición se
   consume una sola vez en el hueco del portador.
2. Daño directo antes del hueco: un solo enfado, se retira la condición, +1 Ataque
   temporal (×1.30), puede actuar; el boost desaparece al terminar su turno sin
   `buffTurns=5` residual.
3. Sin daño antes del hueco: pierde el turno y cura 1/16 de ambas barreras en ese
   momento (con tope; sin log si no hay cambio).
4. Con enfado no cura; daño posterior al descanso no devuelve la acción; daño
   indirecto no enfada.
5. Multigolpe y multiobjetivo: un enfado por portador y golpe directo, sin apilar.
6. Tras el enfado, vuelve a descansar la ronda siguiente.
7. Las cargas de dos turnos no se comen: el descanso se programa tras la liberación.
8. La regla no se duplica en `app/Livewire/**`: allí solo hay una notificación
   genérica de turno perdido.
9. Solo se modifican los archivos autorizados; TASK-061/TASK-063/TASK-064 y demás
   cambios locales quedan intactos.
10. `php -l`, PHPStan dirigido y `composer lint` en verde; `composer analyse`/`arch`
    global se reportan con su deuda preexistente si sigue en rojo.
11. Sesión antigua con `EfectoTruant` previo carga sin error; `SESSION_VERSION` sigue
    en 21.

## Verificación requerida

Análisis estático:

```bash
php -l src/Battle/Domain/Enums/ClaveCondicion.php
php -l src/Battle/Domain/Componentes/Combatiente/EstadosCombatiente.php
php -l src/Battle/Domain/ValueObjects/MultiplicadoresStats.php
php -l src/Battle/Domain/Effects/Capacidades/ReaccionaAlPerderTurno.php
php -l src/Battle/Domain/Effects/ColeccionEfectos.php
php -l src/Battle/Domain/Effects/EfectoTruant.php
php -l app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php
php -l app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php
php -l src/Battle/Domain/Componentes/Agregado/MotorRondas.php
php -l src/Battle/Domain/CombatienteVista.php
composer analyse
composer arch
composer lint
grep -rn "AUSENTE\|ausente" src app resources
grep -rn "dispararTurnoPerdido" src app
grep -rn "onTurnStart" src/Battle/Domain/Effects
```

Comprobación manual (desarrollador, tras implementar):

- Batalla con Slaking; comprobar anuncio, enfado antes de su turno, descanso sin
  daño con curación, daño posterior sin acción, daño indirecto sin enfado,
  multigolpe y tope de escudos.
- Cargar una batalla guardada antes del cambio.

Sin tests.

## Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Curación en el momento incorrecto | Medio | Se cura en `alPerderTurno`, no en `onRoundEnd`; verificación manual con rival más lento. |
| `retirarTemporal` deja `buffTurns` residual | Medio | Si no quedan temporales, `turnosTemporales = 0`; criterio 2. |
| Detección de cargas con `isEmpty()` | Medio | Usar “alguna carga > 0”; criterio 7. |
| Condición `AUSENTE` huérfana al anular la habilidad | Bajo | Residual aceptado (un turno bloqueado sin curación); se limpia en `onRoundStart`/`onRoundEnd`. |
| Conflicto con TASK-061 en descripciones | Bajo | Editar solo la clave `ausente` sobre el estado actual, sin revertir nada. |
| Rollback con sesiones que contengan `AUSENTE` | Bajo | Revertir archivos y limpiar `battle_*`, o mantener la case del enum. |
| Cambio de balance | Medio | Esperado por diseño; validar una batalla completa. |

## Rollback

Revertir los archivos de la tabla y, si hay sesiones guardadas con la condición
`AUSENTE`, limpiar las claves `battle_*` correspondientes o mantener la case del enum.
No hay cambios de esquema, datos, configuración ni dependencias. No se usará
`git reset --hard`, `git clean` ni `git checkout --` sin aprobación explícita.
