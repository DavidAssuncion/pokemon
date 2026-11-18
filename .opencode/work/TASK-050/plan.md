# TASK-050 — Plan v2

- **ID:** TASK-050
- **Versión:** v2 (v1 implementado y aprobado; v2 amplía alcance)
- **Fecha:** 2026-11-17
- **Proyecto:** `/home/david/pokemon`
- **Estado:** APROBADO v2 — implementado y revisado (APPROVED)

## Objetivo

Además de lo ya implementado en v1, aplicar dos ajustes solicitados por el desarrollador:

4. **Padding en las tablas de perfiles rivales** de todos los modales "Configurar
   Formación" (hábitats y gimnasios), para que el contenido no toque los bordes de la
   tarjeta.
5. **Layout de vanguardia/retaguardia en la tarjeta de equipo desplegada**, en 2
   columnas, mostrando los iconos de los integrantes como en el combate. La cabecera
   mantiene las 3 miniaturas y el modal de edición sigue siendo el único punto de
   edición de formación/postura/objeto.

## Alcance v1 ya implementado (aprobado y revisado)

1. Gimnasios: etapas en rejilla 1/2/4 columnas con el líder a la derecha; ficha a
   `max-w-6xl`.
2. Modal de formación de gimnasio `max-w-6xl`, cabecera/footer fijos, cuerpo scrollable
   y leyenda IV/EV colapsable en 3 columnas.
3. Equipos: tarjetas colapsables (colapsadas por defecto, varias abiertas), header con 3
   miniaturas, sin "Composición", icono de editar que abre un modal con formación,
   postura y objeto por slot, guardado único con 3 PATCH secuenciales.
4. Correcciones de revisión: bloqueo de cierre durante `edicionGuardando`, eliminación de
   `edicionFeedback` y `aria-label` en selects deshabilitados.

## Alcance v2 (nuevo)

### 4. Padding de las tablas de perfiles rivales

- Archivo: `resources/views/partials/leyenda-ivs-evs.blade.php`.
- Añadir padding interno a las dos tablas de la leyenda:
  - tabla de "Perfiles de IV/EV aplicables al rival";
  - tablas de desglose por Pokémon del rival.
- Propuesta: añadir `p-2` a los contenedores `overflow-x-auto rounded-control border
  border-line bg-surface` (los que envuelven cada `<table>`), de modo que el contenido
  quede separado del borde sin cambiar el layout de celdas ni la semántica.
- El partial es compartido por `habitats/partials/modal-formacion.blade.php` y
  `gimnasios/show.blade.php`; el cambio aplica a todos los "Configurar Formación".
- **Restricción crítica:** este archivo tiene cambios locales de TASK-049. Se parte del
  estado actual y solo se añade el padding; no se revierte, reformatea ni altera la
  estructura colapsable ni las 3 columnas.

### 5. Tarjeta de equipo: 2 columnas vanguardia/retaguardia

- Archivos: `resources/views/equipos/index.blade.php` y
  `resources/views/equipos/partials/panel-equipos.blade.php`.
- El cuerpo desplegable deja de ser una única fila de 3 slots y pasa a una rejilla de 2
  columnas: **Vanguardia** (izquierda) y **Retaguardia** (derecha).
- Cada columna tiene su cabecera con el nombre de la formación, un contador y un color
  coherente con el modal de edición (primario para vanguardia, peligro para retaguardia).
- Dentro de cada columna se listan los miembros cuyo `formacionDe(team, slot)` coincide,
  mostrando:
  - sprite grande del integrante;
  - nombre formateado;
  - postura (`posturaEtiqueta`) y objeto (`nombreObjeto`);
  - botón de quitar miembro con el bloqueo por exploración actual;
  - clic/Enter en el sprite para abrir el detalle del Pokémon.
- Slots vacíos por formación: mensaje "Sin Pokémon" con un placeholder de borde
  discontinuo, para que las dos columnas siempre estén presentes.
- Se añade un helper de presentación `miembrosDeFormacion(team, formacion)` que devuelve
  `[{ slot, reclutado }]` a partir de `getMember` y `formacionDe`, sin cálculos de negocio.
- La cabecera de la tarjeta conserva las 3 miniaturas, el nombre, las badges, el chevron
  y las acciones renombrar/editar/eliminar.
- La formación se sigue editando solo en el modal; al guardar, la tarjeta se
  redistribuye porque `formacionDe` lee `team.formacion` actualizado.
- La disposición es de 2 columnas también en móvil (como en el campo de combate), con
  sprites a tamaño 96 para evitar desbordamiento en paneles estrechos.

## Fuera de alcance

- Backend, rutas, controladores, casos de uso, DTOs, migraciones, base de datos.
- Cambios en la lógica de combate, en `battle-field.blade.php` o en las tarjetas de
  combate.
- Tests y ejecución de la suite.
- Migrar modales planos a `<x-ui.modal>`.
- Persistir el estado de colapso o de la leyenda entre páginas.
- Dependencias nuevas.
- Auditoría de seguridad: omitida por instrucción explícita del desarrollador
  (`Salta security`).

## Arquitectura y contratos

Sin cambios. Se reutilizan los contratos existentes:

- `PATCH /teams/{team}/formacion`, `PATCH /teams/{team}/posturas`,
  `PATCH /teams/{team}/items` (payloads ya implementados en v1).
- `POST /api/gimnasios/{slug}/combatir`.
- `GET /api/gimnasios/{slug}` (etapas 1→4).
- La leyenda IV/EV sigue leyendo `pcComparacion.perfil_ivs_evs` ya calculado por el
  backend.

La UI solo presenta datos; no replica reglas de negocio.

## Archivos y responsables

| Archivo | Acción | Responsable |
|---|---|---|
| `resources/views/partials/leyenda-ivs-evs.blade.php` | Añadir padding (preservando TASK-049) | `frontend` |
| `resources/views/equipos/index.blade.php` | Helper `miembrosDeFormacion` | `frontend` |
| `resources/views/equipos/partials/panel-equipos.blade.php` | Cuerpo en 2 columnas vanguardia/retaguardia | `frontend` |
| `resources/views/gimnasios/show.blade.php` | Sin cambios en v2 (v1 ya implementado) | — |
| `resources/views/equipos/partials/modal-editar-equipo.blade.php` | Sin cambios en v2 (v1 ya implementado) | — |

No se prevén tareas de `backend`, `database`, `documenter` ni `architect`.
`reviewer` revisará el diff de v2. `security` queda omitido por decisión del
desarrollador.

## Orden de ejecución y dependencias

1. `leyenda-ivs-evs.blade.php`: padding de las tablas, sin tocar la estructura de
   TASK-049.
2. `equipos/index.blade.php`: helper `miembrosDeFormacion`.
3. `panel-equipos.blade.php`: sustituir el cuerpo de 3 slots por las 2 columnas.
4. `php artisan view:cache`, `view:clear` y `npm run build`.
5. Revisión independiente del diff v2 y correcciones si procede.

## Criterios de aceptación verificables (v2)

12. En ambos modales "Configurar Formación" (hábitats y gimnasios), la tabla de perfiles
    rivales y las tablas de desglose por Pokémon tienen separación visible respecto al
    borde de la tarjeta; el contenido no toca el borde y no aparece scroll horizontal
    nuevo en escritorio.
13. La tarjeta de equipo desplegada muestra exactamente 2 columnas: Vanguardia y
    Retaguardia, con cabecera y contador; los miembros aparecen en la columna que
    corresponde a su formación y los iconos son visibles.
14. Cada miembro muestra sprite, nombre, postura y objeto; el botón de quitar miembro y
    el clic para abrir el detalle siguen funcionando; los miembros en exploración siguen
    bloqueados para quitarse.
15. Si una formación no tiene miembros, su columna muestra "Sin Pokémon" sin romper la
    rejilla de 2 columnas.
16. Al cambiar la formación en el modal y guardar, la tarjeta se redistribuye
    correctamente; al cancelar, conserva la formación persistida.
17. La cabecera de la tarjeta conserva las 3 miniaturas, el nombre, las badges, el
    chevron y las acciones; no reaparece "Composición".
18. El padding no altera el comportamiento colapsable de la leyenda ni sus 3 columnas en
    `xl`; los dos archivos de TASK-049 siguen sin revertirse.
19. Se mantienen los criterios 1-11 de v1.
20. `git diff --check` limpio; solo aparecen los archivos autorizados de v1/v2 (más los
    cambios preexistentes de TASK-049).

## Verificación requerida

Sin tests.

- `php artisan view:cache` y `php artisan view:clear`.
- `npm run build` y comprobación de las clases nuevas (`grid-cols-2`, `p-2`, etc.) en el
  CSS emitido.
- `git diff --check`, `git status --short`, `git diff --stat`.
- Comprobación manual:
  - ambos modales de formación con la leyenda abierta y cerrada;
  - tarjeta de equipo colapsada y desplegada;
  - equipos con 1, 2 y 3 miembros y con formaciones 3-0, 2-1, 1-2 y 0-3;
  - móvil 320/640 y escritorio 1024/1280;
  - teclado y foco en el modal de edición.

## Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Modificar `leyenda-ivs-evs.blade.php` con cambios locales de TASK-049 | Perder el colapsable o las 3 columnas | Diff mínimo: solo añadir `p-2` a los wrappers; revisar que el diff de TASK-049 se mantiene |
| Padding en contenedores `overflow-x-auto` | El padding se desplaza con el scroll horizontal | Es aceptable; se comprueba que no aparezca scroll nuevo en escritorio |
| Dos columnas en un panel estrecho | Sprites/nombres apretados o desbordados | `size="96"`, `min-w-0`, `truncate`, `grid-cols-2` y `gap-2`; verificar a 320 px |
| Formación por defecto toda en vanguardia | Columna de retaguardia vacía | Estado "Sin Pokémon" explícito; el usuario puede redistribuir en el modal |
| Miembros con `reclutado` nulo | Slots fantasma | `miembrosDeFormacion` filtra por `getMember` no nulo |
| Cambios de formación no reflejados en la tarjeta | Confusión tras guardar | `formacionDe` lee el estado actualizado y los drafts se limpian al guardar |
| Regresión de v1 | Romper el colapso o el modal | No tocar la cabecera ni el modal salvo lo indicado; re-revisión completa |

## Rollback

- v2: revertir `leyenda-ivs-evs.blade.php` al estado de TASK-049 (solo quitar el padding)
  y restaurar el cuerpo de 3 slots en `panel-equipos.blade.php` + eliminar
  `miembrosDeFormacion` de `index.blade.php`.
- v1: conservar la implementación ya aprobada. No hay cambios de datos ni de esquema.
- `npm run build` regenera los assets si se revierte el CSS.

## Aprobación

Este plan v2 no autoriza implementación hasta que el desarrollador responda con una
aprobación explícita que mencione la versión (`Aprobar plan v2` o equivalente). La
aprobación de v1 no cubre el nuevo alcance.
