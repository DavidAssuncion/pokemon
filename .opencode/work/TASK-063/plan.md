# TASK-063 — Plan

- **ID:** TASK-063
- **Versión:** v1
- **Fecha:** 2026-11-19
- **Estado:** AWAITING_APPROVAL

## Objetivo

Eliminar la concesión global e incondicional de un turno extra tras un KO en el
motor de combate, de modo que ningún Pokémon repita turno por el mero hecho de
debilitar a un rival. La capacidad `otorgarAccionExtra()` se conserva para que una
futura habilidad pueda invocarla desde el gancho de dominio correcto, con la regla
acordada: solo cuenta un KO causado por el propio actor.

## Alcance

1. Retirar el `if (... otorgarAccionExtra())` de los tres puntos detectados,
   conservando `procesarDebilitamientos()` y todo su efecto (notificación de bajas,
   `onFainted`, `notifyFainted`, Gas Reactivo, log).
2. No tocar la capacidad de turno extra ni su serialización.
3. Verificación estática y comprobación manual de criterios.

## Fuera de alcance

- Implementar o asignar la habilidad futura que conceda el turno extra.
- Cambiar el orden del turno extra futuro (inmediato vs final de ronda).
- Añadir mensajes de log o animaciones de "actúa de nuevo".
- Limpiar sesiones de batalla ya serializadas con `accionesExtra = 1`.
- Cambios en UI, migraciones, dependencias, tests o ADR (el AGENTS.md del proyecto
  solo permite crear documentación si se solicita explícitamente).

## Arquitectura y contratos

- Se conserva `EstadoTurno::accionesExtra`/`extraOtorgada`, `otorgarAccionExtra()`,
  el pool de extras de `GestorTurnos::getNextActor()`, el consumo en
  `consumeAction()` y la serialización actual (`SESSION_VERSION` no cambia).
- La futura habilidad se implementará como efecto de dominio que implemente
  `ReaccionaAlDebilitarRival`; el despacho ya existe en
  `AplicadorEfectosPostImpacto::dispararReacciones`, compartido por combate manual,
  objetos e IA/exploraciones. No se añaden abstracciones ahora.
- Se respeta `.ai/rules/arrays-dto-collections.md:69`: la presentación Livewire deja
  de contener esta regla de dominio.

## Archivos y responsables

| # | Archivo | Cambio | Responsable |
|---|---|---|---|
| 1 | `src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php` (126-129) | Eliminar el `if` y la variable `$nuevosDebilitados`; mantener `$batalla->procesarDebilitamientos();` | `backend` |
| 2 | `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php` (138-141, `aplicarMovimiento`) | Ídem | `backend` |
| 3 | `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php` (240-243, `resolverObjeto`) | Ídem | `backend` |

Sin cambios de `frontend`, `database` ni `documenter`.

## Orden de ejecución

1. Edición 1 (dominio).
2. Ediciones 2 y 3 (mismo archivo Livewire, en secuencia).
3. Verificación estática.
4. Revisión independiente (`reviewer`).
5. Validación manual del desarrollador (KO en combate y objeto que debilita).

## Criterios de aceptación

1. `grep -rn "otorgarAccionExtra" src app` devuelve únicamente las definiciones
   delegadas (`EstadoTurno`, `TurnoCombatiente`, `DelegacionTurnoCombatiente`), sin
   invocaciones desde `app/` ni desde `Componentes/Agregado/`.
2. Tras un KO en combate manual, el actor no repite turno en la misma ronda; el
   turno pasa al siguiente pendiente o se abre ronda.
3. Usar un objeto que debilite no concede turno extra.
4. `procesarDebilitamientos()` sigue invocándose en los tres puntos originales y en
   `aplicarMovimientoAliado()`; el log de debilitamiento se mantiene.
5. `accionesExtra` y `extraOtorgada` siguen en `TurnoCombatiente::serializar()` y en
   `SerializadorCombatiente::ORDEN`; `SESSION_VERSION` sin cambios.
6. Solo se modifican los dos archivos autorizados; cero cambios en `resources/**`,
   `database/**`, `composer.json` y `tests/`.
7. `php -l`, `composer analyse`, `composer arch` y `composer lint` en verde.

## Verificación requerida

Análisis estático:

```bash
php -l src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php
php -l app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php
composer analyse
composer arch
composer lint
grep -rn "otorgarAccionExtra" src app
git diff -- src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php \
  app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php
```

Comprobación manual de criterios (desarrollador, tras la implementación):

- Batalla 3v3: debilitar a un rival no debe volver a pedir acción al mismo Pokémon.
- Usar un objeto que debilite no debe conceder otro turno.
- El log de debilitamiento sigue apareciendo.

No se crean, actualizan ni ejecutan tests.

## Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Batallas en curso con `accionesExtra = 1` serializado | Bajo | No se cambia el esquema; el extra se consume una vez y se reinicia al abrir ronda. Sin migración. |
| Reintroducir la regla en Livewire en el futuro | Medio | Criterio de revisión 1 y contrato documentado en `architecture.md`. |
| Menos acciones por ronda y cambio de balance | Medio | Esperado por decisión del desarrollador; validar una batalla 3v3. |
| Duplicación residual en los tres puntos | Bajo | La corrección elimina la regla, no la replica; el archivo Livewire queda con dos llamadas a `procesarDebilitamientos()` sin concesión. |

## Rollback

Revertir los tres hunks en los dos archivos mediante `git diff`/edición manual. No
hay cambios de datos, esquema, configuración ni dependencias. No se usará
`git reset --hard`, `git clean` ni `git checkout --` sin aprobación explícita.
