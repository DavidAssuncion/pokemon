# TASK-056 — Ejecución

## Preflight (2026-11-17)

- Repo: `/home/david/pokemon`.
- Rama: `master` (1 commit por delante de `origin/master`; sin push).
- Cambios locales preexistentes que **no se tocan**: 40 archivos modificados y varios untracked de
  tareas anteriores (TASK-049 a TASK-055), incluidos CSS, vistas de equipos/hábitats/gimnasios,
  `src/Battle`, `src/Exploraciones`, `src/Shared/Tipos/TipoPokemon.php` y `docs/conventions.md`.
- La tarea arranca en una carpeta nueva `.opencode/work/TASK-056/`; cualquier archivo de
  implementación deberá delimitarse en el plan aprobado para no sobrescribir ese trabajo.
- No se ha leído `.env` ni credenciales.

## Estado

`RECEIVED → CLARIFYING → ANALYZING → DESIGNING → AWAITING_APPROVAL → IMPLEMENTING (F1, reanudada 2026-11-18)`

## Decisiones del desarrollador (2026-11-17)

- Enfoque: análisis + diseño primero; sin implementación hasta aprobación del plan.
- Nidos: «1 nido por bioma».
- Amuleto: Vel + 50 % del mayor entre Atk/SpA/Def/SpD (sin Vel/HP); Gengar = 110 Vel + 65.
- Bonguris: cada hábitat tiene un bonguri; el de la zona se usa/aporta el bono.
- Progresión: pieza única que evoluciona consumiendo la anterior.
- Equipamiento: 3 slots por Pokémon reclutado, persistentes en todos los modos.
- Habilidades: pasiva extra acumulable.
- Sin tests.

## Notas

- Se delegó el análisis en dos frentes independientes: (A) Forja/ítems/inventario/UI y
  (B) Granjas/nidos/Pokémon/equipamiento/combate. Ambos fueron solo lectura y completaron
  sus informes (con algunas lecturas pendientes por límite de pasos).
- `analysis.md` reproduce ambos informes completos.
- Conclusión operativa: la Forja es estática y no filtra por nido; el inventario no tiene
  instancias; el equipamiento actual es de 1 objeto (`obj_equipados`) y el motor solo admite 1
  objeto por combatiente; la UI de equipamiento está en archivos con cambios locales.
- Diseño arquitectónico completado y guardado en `architecture.md`: identidad por etapa en
  `player_inventory`, tabla nueva `recruit_gear` para 3 slots, colapso de stats/pasivas en el
  mapeo de batalla, puerto `NidosConstruidos` y plan en 3 fases.
- `plan.md` v1 redactado y **aprobado** con el texto literal `Aprobar plan v1` (`approval.md`).
- F3 condicionada a que se consolide o revierta el WIP de `resources/views/equipos/*`.

## Implementación F1 (en curso, pausada)

### Frontend — COMPLETADO

- `resources/views/forja/index.blade.php` modificado (+278/−50).
- Consume `secciones` y `categoria` del backend; eliminados `FORJA_CATEGORIAS`,
  `forjaCategoriaDeResultado` y `textoMotivo`.
- Nueva sección `piezas` con fila por familia/tipo, selector de bioma/bonguri, stats, habilidad,
  pieza anterior y estados bloqueado/desbloqueado.
- Verificación: `php artisan view:cache` OK, `node --check` del JS OK, `git diff --check` OK,
  sin estilos inline ni cálculos de negocio.
- **Riesgo de integración detectado:** las recetas `material:recipiente` heredan
  `categoria: "material"`; si el backend no emite una sección `materiales` o no las reasigna a
  `medicinas`, la UI las mostrará en el bloque de respaldo "Otras recetas". Hay que resolverlo al
  completar el backend.

### Backend — COMPLETADO (handoff recibido; pendiente integración/revisión)

- Sesión del subagente: `ses_e146d0308ffel4CiWwrFQkMRu3` (completada el 2026-11-18).
- Implementado: dominio `src/Forja/Domain/Piezas/**`, `ClavePieza`, `CategoriaItem` (+3),
  puerto `NidosConstruidos` + adaptador `NidosConstruidosEloquent`, `ListarPiezasForja`,
  `FabricarPieza`, `LectorFamiliasPiezas`, carve-out de whitelist del inventario, contrato aditivo
  de `GET /api/forja/recetas` y enrutado de `POST /api/forja/fabricar` a `FabricarPieza`.
- Verificación del subagente: `php -l` OK; `pint --dirty` OK; PHPStan L6/L8 sin errores nuevos en
  archivos de TASK-056 (solo ruido preexistente); validación pura de stats y de las 9 recetas de
  Gastly/Haunter/Gengar. Sin tests, migraciones ni seeders.
- No ha tocado `resources/views/forja/index.blade.php` (lo hizo `frontend`), `src/Battle/**`,
  `equipos/**`, `resources/css/**`, `tests/**`, migraciones ni seeders.
- **Riesgo abierto confirmado:** `ListarRecetas::detalle()` emite
  `categoria: $receta->categoriaResultado->value`; las recetas `material:recipiente` quedan con
  `categoria: "material"`, pero `ForjaController::SECCIONES` no tiene `materiales`. La UI las
  enviará al bloque "Otras recetas". Hay que mapearlas a `medicinas` o añadir la sección
  `materiales` antes de cerrar F1.

### Pendiente tras el backend

1. Revisar el diff completo de F1 y confirmar que no toca `src/Battle/**`, `equipos/**`,
   `resources/css/**` ni `tests/**`.
2. Resolver el mapeo de `material:recipiente` (`medicinas` o sección `materiales`).
3. `documenter`: `src/Forja/context.md`.
4. `reviewer` y `security` de F1.
5. F2 (migración `recruit_gear` + equipamiento + batalla) y F3 (condicionada al WIP de equipos).

## Integración F1 (2026-11-18)

- Revalidación: `git diff --check` OK; `php -l` de todos los archivos F1 OK;
  `php artisan view:cache` OK; `php artisan route:list --path=forja` muestra las 3 rutas.
- **Fallo crítico detectado:** el backend emite `categoria` con el valor de `CategoriaItem`
  (`pocion`, `mejora`, `reductor`, `resistencia`, `material`, `arma`, `armadura`, `amuleto`),
  pero `ForjaController::SECCIONES` usa claves de sección (`medicinas`, `mejoras`, `reductores`,
  `resistencias`, `piezas`). La UI agrupa por `receta.categoria`; por tanto, **todas** las
  recetas caen en “Otras recetas”. Además, `material:recipiente` no tiene sección.
- Corrección aplicada por `backend` (F1): `pocion` y `material` → `medicinas`; `mejora` →
  `mejoras`; `reductor` → `reductores`; `resistencia` → `resistencias`; `arma`/`armadura`/`amuleto`
  → `piezas`. `secciones` conserva las 5 claves. Verificado con `php -l`, Pint y `view:cache`.
- `docs/context.md` está modificado por el balance de mazmorra (TASK-055, 2026-11-18); no se toca.
- Además de `CombatienteVista.php`, ahora aparecen modificados `DatosPokemonBatalla.php`,
  `EquipoBatalla.php`, `ValueObjects/JefeEstado.php` y `src/Battle/context.md` por el mismo WIP de
  TASK-055; **no son cambios de TASK-056** y quedan excluidos de la revisión.
- Siguiente: `documenter` (`src/Forja/context.md`), `reviewer` y `security` de F1.

## Revisión, seguridad y correcciones F1 (2026-11-18)

- `documenter`: **no pudo escribir** `src/Forja/context.md` (`Permission denied`). Causa: la
  configuración global del agente deniega `edit` sobre `src/**` (solo permite `docs/**`, `*.md`
  sueltos, `AGENTS.md` y `.opencode/rules/**`). Bloqueo de configuración, no del proyecto.
- `reviewer` (1ª): **CHANGES_REQUESTED** — 2 HIGH + 3 MEDIUM. Informe en `review.md`.
- `security` (1ª): **CHANGES_REQUESTED** — 1 MEDIUM + 3 LOW. Informe en `security.md`.
- Correcciones aplicadas por `backend` (C1-C6):
  - C1: `InventarioJugador::bloquearClave()` + implementación (`insertOrIgnore` cantidad 0 +
    `SELECT ... FOR UPDATE`) y uso en `FabricarPieza` antes de revalidar la unicidad.
  - C2: `CatalogoPiezas::recetas()` omite etapas ≥2 sin padre resuelto.
  - C3: puerto `Forja\Domain\Ports\DefinicionesPieza`; `LectorFamiliasPiezas` lo implementa;
    binding en `AppServiceProvider`; `App` ya no importa `Infra`.
  - C4: `NidosConstruidos::bloquear(): ?int` implementado en `NidosConstruidosEloquent`;
    `FabricarPieza` ya no usa `DB::table`.
  - C5: `ClavePieza` ancla `\z` (rechaza `"arma:94\n"`).
  - C6: `RecetaNoExiste::paraClave()` sin parámetro, mensaje fijo; 3 call sites actualizados
    (incluido `CatalogoRecetas.php:48`, necesario para compilar).
- Verificación: `php -l` OK; Pint OK; `view:cache` OK; `git diff --check` OK; PHPStan L6 y L8
  **sin errores en archivos de TASK-056** (el global sigue con 34/168 errores preexistentes/WIP
  ajeno, fuera de alcance); deptrac sin violaciones de Forja (104 globales, 2050 uncovered).
- `reviewer` (2ª): **APPROVED** — C1-C6 PASS, sin regresiones.
- `security` (revalidación): **APPROVED** — C1/C5/C6 resueltos, sin vector nuevo explotable.
- Deuda aceptada: descendientes de etapa omitida quedan bloqueados (no saltan progresión);
  `throttle` fuera de alcance; `bloquearClave` no verifica transacción activa (único caller dentro
  de `DB::transaction`); `categoria` = clave de sección pendiente de registrar en el diseño.
- **F1 queda cerrada funcionalmente** (implementación + revisión + seguridad). Pendiente solo la
  documentación (`src/Forja/context.md` + decisión `categoria`), bloqueada por permisos.

## Cambio de requisito de UI (2026-11-18)

- Petición del desarrollador: «Solo mostramos uno de la familia, pulsar en el abre modal de craft
  con las pestañas de la familia y la posibilidad de buildear los 3 tipos de item».
- Es un cambio de alcance de la UI de F1: `plan.md` pasa a **v2** con la sección §13; `approval.md`
  queda **PENDIENTE v2** para ese ajuste. La aprobación v1 sigue vigente para F2/F3.
- Sin cambios de backend: el JSON actual ya expone `familia.evolution_chain_id`, `familia.nombre`,
  `especie.etapa`, `tipo_pieza`, nido, stats, habilidad, ingredientes y motivos.
- No se implementa hasta aprobación de v2.

## F2 en curso (2026-11-18)

- Migración `database/migrations/2026_11_17_000001_create_recruit_gear_table.php` creada por
  `database` (FK cascade, `unique(reclutado_id, slot)`, `unique(reclutado_id, item_key)`,
  `index(item_key)`). Verificada con `php -l`, Pint y `git diff --check`. Sin migrar.
- Decisión de migración: sin CHECK de `slot` (no portable a SQLite); el rango 1-3 lo valida
  `EquiparPieza`.
- `SESSION_VERSION` real es **21**, no 11 como decía `docs/context.md`; el diseño §8.5 es correcto.
- Delegado `backend` F2.2 (modelo `RecruitGear`, VO, `ObtenerPiezas`, `EquiparPieza`,
  `DesequiparPieza`, request, controlador y 3 rutas). En ejecución en segundo plano.

## Migración aplicada (2026-11-18)

- `php artisan migrate --path=database/migrations/2026_11_17_000001_create_recruit_gear_table.php --force`
  → DONE (59.40 ms). Era la única migración pendiente.
- Verificado con `php artisan db:table recruit_gear`: 6 columnas, FK `reclutado_id → reclutados`
  `on delete cascade`, `unique(reclutado_id, slot)`, `unique(reclutado_id, item_key)`,
  `index(item_key)`.
- La consulta exacta de `ObtenerPiezas::paraReclutados` para `user_id = 2` ya no lanza
  `42P01`; devuelve 0 filas.
- El error reportado por el desarrollador queda resuelto.

## Cierre F1-UI v2 y F2 (2026-11-18)

- **UI v2 aprobada e implementada** en `resources/views/forja/index.blade.php`: una tarjeta por
  familia, modal de craft con pestañas por etapa, 3 tipos por pestaña, selector de bioma y
  fabricación sin recarga completa. `view:cache` y `node --check` OK.
- **F2 implementada**:
  - Migración `recruit_gear` (no ejecutada).
  - Modelo, VO, mapa, casos de uso, endpoints y rutas de equipamiento.
  - `PiezasEquipadas` + D3 (inventario ∪ equipamiento).
  - `AplicadorBonusPiezas`, bonos en `MapeadorPokemonBatalla`/`ConstruirEquipoJugador`/
    `CombateExploracion`, `effectKeys` de pasivas. `SESSION_VERSION` intacto (21).
  - `piezas[]` en el serializer (3 slots enriquecidos) y CP con bonos.
- **Correcciones**: C9-C18 (locks globales, D3, catch acotado, forceCreate, equipadas fuera del
  stock, contrato enriquecido, `pieza_anterior_equipada`, baseline).
- **Revisión final:** APPROVED (reviewer) y APPROVED (security).
- **Estática:** `php -l`, Pint, PHPStan L6 (0 en alcance), `view:cache`, `route:list`,
  `git diff --check` OK. `composer run analyse` global sigue con errores ajenos (WIP).
- **Pendiente:** ejecutar la migración; documentación (permisos); F3 (WIP de equipos); QA visual.

## PAUSA (2026-11-17) — HISTÓRICA, reanudada el 2026-11-18

- **Estado:** `IMPLEMENTING (F1)` — **PAUSADO**, no completado.
- **Aprobación:** plan v1 (`approval.md`, Estado APROBADO, texto `Aprobar plan v1`).
- **Backend F1:** completado; handoff recibido el 2026-11-18. Pendiente integración, revisión y
  seguridad; no se considera F1 cerrada.
- **Árbol de trabajo:** cambios de TASK-056 en `resources/views/forja/index.blade.php`,
  `src/Shared/Domain/Items/CategoriaItem.php`, `src/Shared/Domain/Items/ClavePieza.php`,
  `src/Shared/Infra/Inventario/InventarioJugadorEloquent.php`, `src/Forja/**` y
  `app/Providers/AppServiceProvider.php`, mezclados con 40 archivos modificados y untracked de
  TASK-049…055. `docs/context.md` también está modificado por el balance de mazmorra
  (TASK-055/2026-11-18); no tocar. No hacer `git reset`, `git clean` ni `checkout` destructivo.
- **Reanudación:** revisar el diff de F1, corregir el mapeo `material:recipiente`, ejecutar
  verificación estática, documentar `src/Forja/context.md`, revisar y auditar F1; después F2 y F3.
  No ejecutar tests. No hacer `git push`.
- **Recordatorio:** F3 no puede empezar hasta que el desarrollador confirme que el WIP de
  `resources/views/equipos/*` y `resources/css/*` está consolidado o revertido.
