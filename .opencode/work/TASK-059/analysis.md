# TASK-059 — Análisis

> Resultado de `analyst` (sesión `ses_e12700fdaffecPbVtuHJat5itO`). El agente alcanzó el
> límite de pasos antes de emitir el informe formal, pero completó el diagnóstico. Se
> reproduce sin alterar sus conclusiones.

## Causa raíz del fallo de mazmorra — confirmada

- `resources/views/habitats/partials/modal-mazmorra.blade.php:105` — el botón llama
  `@click="toggleFormacionSlot(miembro.slot)"`, pero ese método
  (`resources/views/habitats/show.blade.php:258-260`) muta **`this.formacion`**, no
  `this.mazmorraFormacion`.
- El mismo botón lee su estado de `mazmorraFormacion`
  (`modal-mazmorra.blade.php:106,110`). `mazmorraFormacion` solo se inicializa en
  `show.blade.php:363,418,425,429` y nunca se vuelve a escribir con click alguno → el
  toggle es inerte (la UI no cambia y el payload siempre va con todo `vanguardia`).
- `confirmarPiso()` (`show.blade.php:434-465`) sí envía `formacion: this.mazmorraFormacion`
  (línea 449), por lo que el backend recibe un mapa completo de `vanguardia` aunque el
  usuario crea haber elegido.
- Backend correcto: `MazmorraController::combatir` valida `formacion` como array
  `in:vanguardia,retaguardia` y lo pasa a `FormacionEquipo::desde()` →
  `ConstruirEquipoJugador::desdeEquipo()` con prioridad popup > persistida > clasificación
  por stats.

**Segundo defecto:** `selectMazmorraTeam()` (`show.blade.php:422-432`) inicializa **todos**
los slots a `'vanguardia'` ignorando `team.formacion` persistida, a diferencia de
`formacionInicialDe()` (`show.blade.php:248-256`) usada por ruta/entrenadores
(`show.blade.php:200,232,242`) y por gimnasios (`gimnasios/show.blade.php:397-405`).

## Tipos de pelea (4) y flujos

| Tipo | Vista/modal | Endpoint | Payload |
|---|---|---|---|
| Ruta | `habitats/partials/modal-formacion.blade.php` + `show.blade.php` (`formacionContexto='ruta'`) | `POST /api/habitats/{h}/ruta/iniciar` (`routes/ruta.php:11`) | `{team_id, nivel, formacion, preview_id?}` |
| Entrenadores hábitat | mismo `modal-formacion` (`formacionContexto='entrenadores'`) | `POST /api/habitats/{h}/entrenadores/{n}/{t}/combatir` (`routes/entrenadores.php:9`) | `{team_id, formacion}` |
| Gimnasio | modal inline en `gimnasios/show.blade.php:148-240` + JS `255-452` | `POST /api/gimnasios/{gym}/combatir` (`routes/gimnasios.php:11`) | `{team_id, formacion}` |
| Mazmorra | `modal-mazmorra.blade.php` (modal único con pisos + equipo + formación) | `POST /api/habitats/{h}/mazmorra/{piso}/combatir` (`routes/mazmorras.php:9`) | `{team_id, formacion}` |

Los cuatro comparten contrato de formación idéntico: objeto
`{ "1": "vanguardia"|"retaguardia", ... }`, no array posicional.

## Duplicidades identificadas

- Tres implementaciones casi idénticas de `formacionInicialDe()` y
  `toggleFormacionSlot()` (`show.blade.php:248-260`, `gimnasios/show.blade.php:397-409`).
- Tres markup de lista de miembros con toggle (`modal-formacion:20-42`,
  `modal-mazmorra:93-117`, `gimnasios/show:200-228`).
- Ninguna reutiliza el componente existente `<x-ui.modal>`
  (`resources/views/components/ui/modal.blade.php`), que ya aporta focus trap y pila de
  modales (usado en `equipos/partials/modal-editar-equipo.blade.php:2`).

## Diferencias relevantes

- Mazmorra no muestra comparación de PC ni leyenda IV/EV (`modal-formacion:44-45`;
  gimnasio `show:236-237`), y **no existe endpoint PC de mazmorra** (`routes/mazmorras.php`
  solo tiene pisos y combatir).
- El modal de mazmorra integra selección de equipo (no hay panel de tarjetas previo).
- `modal-editar-equipo.blade.php:36-60` usa botones separados con `aria-pressed`/`aria-label`
  y persiste vía `PATCH /teams/{team}/formacion` — modelo distinto (edición, no inicio) y el
  único accesible.

## Tests que restringen el refactor (identificados, no ejecutados ni modificados)

- `tests/Feature/FrontendHabitatModalTest.php:105-106` — asserta literalmente
  `'Combate de Ruta · Configurar Formación'` y `'formacionInicialDe(this.selectedTeamId)'`
  en `habitats.show`; unificar el markup puede romper esta aserción sin cambiar
  comportamiento.
- `tests/Feature/Mazmorras/MazmorraApiTest.php:99,211` — envían `formacion: []`; validan el
  backend, no la UI.
- `tests/Feature/CombateEntrenadores/ConstruirEquipoJugadorTest.php`,
  `tests/Feature/CombateRuta/CombateRutaControllerTest.php`,
  `tests/Feature/Gimnasios/GimnasioApiTest.php`, `tests/Feature/EquiposControllerTest.php`
  — cubren prioridad de formación.

## Archivos con cambios locales ajenos (no tocar)

Árbol de trabajo sucio en TASK-049..058; los cuatro archivos clave ya están modificados:
`habitats/show.blade.php` (+23, solo sprites), `habitats/partials/modal-formacion.blade.php`
(+22/-…), `gimnasios/show.blade.php` (145 líneas),
`habitats/partials/columna-derecha.blade.php` (+24). El bug de mazmorra es preexistente y
está commiteado (`modal-mazmorra.blade.php` última modificación en
`0e96b9d refactor(task-024)`), no introducido por el trabajo en curso.

## Opciones de unificación

1. **Parcial Blade paramétrico de “lista de miembros + formación”** reutilizado por los tres
   modales existentes, con corrección mínima del bug.
2. **Componente `<x-combate.formacion>` completo** (equipo + formación + CTA) con props
   `abierto/cerrar/teams/teamId/formacion/endpoint/onConfirmar/pcDisponible`.
3. **Migrar todo a `<x-ui.modal>` + componente único**.

## Riesgos pendientes

- Romper asserts de `FrontendHabitatModalTest`.
- Ausencia de endpoint PC de mazmorra.
- `availableTeams` no filtra equipos con miembros en exploración (la tarjeta lateral sí lo
  hace, `columna-derecha.blade.php:70-90`) → 422 tardío.
- Posible regresión al compartir `formacion` entre ruta/entrenadores.
- Migración a `<x-ui.modal>` cambia `x-if` por `x-show` y el ciclo de vida del DOM.
- Decidir si `formacionDraft`/persistencia (`PATCH /teams/{team}/formacion`) debe ofrecerse
  también desde el modal de inicio (hoy no se persiste la formación elegida al combatir).

## Recomendación inmediata

Corrección mínima e independiente del refactor: añadir un `toggleFormacionSlotMazmorra(slot)`
que escriba en `mazmorraFormacion`, usarlo en `modal-mazmorra.blade.php:105`, y hacer que
`selectMazmorraTeam()` inicialice desde `formacionInicialDe(id)` en lugar de forzar
`'vanguardia'`. La unificación de modales debe abordarse como tarea separada con su propio
plan aprobado, dado el árbol de trabajo sucio y las aserciones de test existentes.
