# TASK-019 — Análisis consolidado

- **Fecha:** 2026-11-05
- **Proyecto:** `/home/david/pokemon`
- **Base auditada:** rama `task-011`, commit `0317179` (checkpoint del working tree).
- **Alcance:** auditoría de solo lectura de backend, frontend, documentación y seguridad. Sin cambios de código, sin tests, sin `git push`.
- **Método:** análisis delegado a `analyst` (backend y frontend), `documenter` y `security`; evidencia contrastada por el orchestrator con `grep`, `route:list`, migraciones, configs y línea base estática.
- **Limitación:** revisión estática. No se ejecutó la aplicación ni pruebas funcionales; los hallazgos marcados como «riesgo» dependen de datos o concurrencia real.

---

## 1. Resumen ejecutivo

1. **No hay bugs críticos de pérdida masiva de datos ni bypass de autenticación**, pero sí **4 riesgos altos de concurrencia/datos** (reclutamiento, caramelos, creación de exploración y finalización) y **1 riesgo alto de autorización** (gestión global sin rol admin).
2. **La autorización de administración no existe**: el CRUD de gimnasios y las mutaciones globales de hábitats solo exigen `auth`. Cualquier jugador autenticado puede alterar contenido global. Verificado con `route:list` (solo `web + Authenticate`).
3. **No hay rate limiting** en login, registro ni acciones de juego. `bootstrap/app.php` tiene `withMiddleware` vacío y no hay `throttle`/`RateLimiter` en rutas.
4. **`composer check` está roto**: PHPStan L6 reporta **34 errores** (17 `cast.useless`, 7 `staticMethod.dynamicCall`, 5 de baseline/configuración, 1 `argument.type` real menor). Pint pasa.
5. **`composer arch` está roto**: deptrac reporta **113 violaciones**, todas por dependencias hacia `Presentation`: **78 Habitats**, **16 Battle**, **12 Exploraciones** y **7 controladores de módulo ubicados en `Infra/`**.
6. **Frontend frágil en `/equipos`**: al menos 9 acciones fetch fallan solo con `console.error`; el usuario no recibe feedback.
7. **Dos bugs de UI confirmados**: el dropdown de filtros de la Pokédex nunca se cierra al hacer clic fuera (`closest('[x-data]')` siempre encuentra el `<body>`), y el modal de exploración de `/equipos` no se puede abrir (`openExploracionModal()` solo existe como definición).
8. **Contrato roto en eclosión de huevos**: el backend devuelve `{reclutado: ...}` y el frontend lee `data.pokemon`, `data.nombre` o `data.pokemon_name`; se pierde el nombre del Pokémon nacido.
9. **Deuda estructural alta**: `Combatiente.php` (~65 KB), `app/Livewire/Combate.php` (~67 KB), `equipos/index.blade.php` (~194 KB), `habitats/show.blade.php` (~180 KB) y ~4.000 líneas de JavaScript inline.
10. **Documentación desfasada**: `Forja` y `Granjas` no tienen README; `src/Crud` se documenta pero no existe; `SESSION_VERSION` real es 21 y los docs dicen 10/11/20; hay endpoints inexistentes en READMEs de módulos.
11. **Seguridad media**: 17 avisos de `composer audit` (Laravel 12.67.0, Livewire 4.4.2, Symfony), `users.name` sin `unique`, `unserialize` sin `allowed_classes`, sesión de un año sin cifrar y mensajes internos expuestos.
12. **Fortalezas**: separación por módulos, DTOs/Collections tipadas, scope `BelongsToUser`, validación de propiedad en recursos, CSRF correcto, datagrid con whitelists y 0 enlaces relativos rotos en la documentación.

---

## 2. Bugs y riesgos funcionales

### 2.1 Backend

| ID | Severidad | Módulo | Evidencia | Impacto | Recomendación |
|---|---|---|---|---|---|
| B-01 | Alto | Reclutamiento | `app/Http/Controllers/ReclutamientoController.php:36` (sin lock) vs `:80` (`discard()` con `lockForUpdate()`) | Dos reclutamientos concurrentes pueden crear dos reclutados por la misma unidad | `lockForUpdate()` dentro de transacción y revalidar cantidad |
| B-02 | Alto | Reclutados | `ReclutadoController.php:81-108`: la transacción consume caramelos, pero `$reclutado->exp` se guarda fuera | Lost update de EXP entre alimentaciones concurrentes | Incluir lock del reclutado y guardado de EXP en la misma transacción |
| B-03 | Alto | Exploraciones | `ExploracionActivaController.php:331-355`: `reclutadoDisponible()` y `create()` no son atómicos; no hay índice único parcial en migraciones | Dos exploraciones activas para el mismo reclutado | Índice único parcial o lock sobre el reclutado + revalidación |
| B-04 | Alto | Exploraciones | `FinalizarExploracionHandler.php:91-94`: `refresh()` + `regreso !== null`, sin lock | Doble reparto de recompensas si se ejecuta fuera del lock de caché o en reintento | `lockForUpdate()` de la exploración al inicio del handler |
| B-05 | Alto | Seguridad | `routes/gimnasios.php:14-18` + `AdminGymController` sin `authorize`; `routes/habitats.php:25-29` + `HabitatsController` sin policy | Cualquier jugador autenticado modifica gimnasios y catálogo global de hábitats | Rol admin, `Gate`/policy y middleware `can:admin` |
| B-06 | Medio | Exploraciones | `ProcesarExploracionHandler.php:107-116`: una vuelta normal (`$motivo === ''`) se registra como `retirada` con `reason: grupo_enemigo` | Mensaje incorrecto en la UI para vueltas normales o forzadas | Distinguir retirada voluntaria, vuelta por tiempo y derrota |
| B-07 | Medio | Reclutados | `ReclutadoController.php:232-242`: `puedeEvolucionarPara()` fuera de la transacción | Doble evolución o doble consumo de EXP tipada | Revalidar bajo lock dentro de la transacción |
| B-08 | Medio | Reclutados | `ReclutadoController.php:476-491`: `count` + `toggle` no atómicos | Superar el límite de 6 favoritos | Transacción con lock o contador atómico |
| B-09 | Medio | Granjas | `src/Granjas/App/AsegurarGranja.php:20-36`: `first()` + `save()` sin lock; unique `(user_id, habitat_id)` | Dos primeras visitas simultáneas pueden lanzar 500 por unique | `firstOrCreate`/`upsert` con captura de unique |
| B-10 | Medio | Reclutados | `app/Support/ReclutadoSerializer.php:109` llama a `serializar()` por elemento; `:168` y `:177` consultan `PlayerMoveUnlock` y `TypePathNode` | N+1: 2 consultas por reclutado en `/api/reclutados` y `/equipos` | Precargar unlocks/nodos y pasar mapas al serializer |
| B-11 | Medio | Granjas | `src/Granjas/App/ObtenerGranja.php:63` (`refugios()->get()`) y `:163` (`$refugio->complementos`) | N+1 de complementos por refugio | Eager loading `refugios.complementos` |
| B-12 | Medio | Habitats | `HabitatGestionRepository.php:84-99`: `lockForUpdate()` + `update()` fuera de transacción | Lock sin cobertura real; `previousLevel` obsoleto | `DB::transaction` alrededor de lectura y update |
| B-13 | Medio | Shared | `CadenasEvolutivas.php:95,100`: indexa por `pokemon_id` pero consulta `whereIn('evolved_species_id', array_keys(...))` | Pérdida de aristas si `pokemon.id !== species_id`; fallback silencioso | Mapear por `species_id` o `array_values`; verificar datos |
| B-14 | Medio | Reclutados | `ReclutadoController.php:524-529` + `ReclutadoSerializer.php:43`: favorito huérfano con type-hint no nullable | `TypeError`/500 en favoritos | Filtrar nulos y garantizar FK `ON DELETE CASCADE` |
| B-15 | Medio | Reclutamiento | `ReclutamientoController.php:146`: `'user_id' => $userId ?? 1` | Si falta auth, los caramelos van al usuario 1 | Fallar explícitamente si `$userId` es null |
| B-16 | Medio | Reclutados | `ReclutadoController.php:166`: `max(1, $maxPorEv)` fuerza consumir 1 caramelo aunque el EV aplicado se recorte | Gasto de caramelo con beneficio parcial | Tratar 0 como «no consumir» y devolver 422 |
| B-17 | Bajo | Reclutados | `TeamMember::$pokemon_id` almacena el id de `reclutados`, no de `pokemon`; `ReclutadoController.php:589` actualiza por ese campo | Naming peligroso; alto riesgo de mal uso | Renombrar a `reclutado_id` o documentar el contrato |
| B-18 | Bajo | Hábitats | `routes/habitats.php:34` + `HabitatsController.php:176` (`toggleFavorito` no-op) y `resources/views/habitats/show.blade.php:706,2575` siguen llamándolo | Botón de favorito que no hace nada en el detalle de hábitat | Eliminar endpoint y handler, o implementarlo |
| B-19 | Bajo | Exploraciones | `ProcesarExploracionHandler.php:742-749`: `poolHabitat()` privado, `@deprecated`, `@phpstan-ignore method.unused` | Código muerto de producción | Eliminar o mover a test/helper |
| B-20 | Bajo | Varios | `100` EXP/caramelo y `6` favoritos como números mágicos | Reglas duplicadas y difíciles de ajustar | Constantes de dominio |

### 2.2 Frontend

| ID | Severidad | Vista | Evidencia | Impacto | Recomendación |
|---|---|---|---|---|---|
| F-01 | Alto | Pokédex | `resources/views/pokedex/index.blade.php:748` + `layouts/app.blade.php:20` | El dropdown de tipo/esfuerzo nunca se cierra al hacer clic fuera | Usar `@click.outside` o `this.$el.contains(e.target)` |
| F-02 | Alto | Equipos | `equipos/index.blade.php:2637-2639, 2669-2671, 2739-2741, 2763-2765, 3043-3045, 3096-3098, 3136-3138` | Acciones CRUD fallan en silencio (`console.error`) | Helper `fetchJson` con error visible y timeout |
| F-03 | Alto | Modales | Sin `x-trap` en `resources/views`; modales en `pokedex:260-281`, `equipos:850`, `habitats:480,744,987,1128` | Teclado/lector de pantalla navega por el fondo; scroll detrás | Focus trap, foco inicial, retorno al disparador y scroll lock |
| F-04 | Alto | Equipos | `openExploracionModal()` solo aparece en su definición (`equipos/index.blade.php:3158`); no hay llamadas | La acción «Enviar a explorar» es inalcanzable | Conectar el botón o eliminar modal/métodos |
| F-05 | Medio | Hábitats | `habitats/show.blade.php:231-239` usa `$team->members[$i]`; `app/Models/Team.php:41-44` no ordena por `slot` | Pokémon en slots equivocados | Ordenar por `slot` en repositorio/relación |
| F-06 | Medio | Hábitats | `habitats/show.blade.php:1581,1779`: `team.members.sort(...)` en getters | Muta estado reactivo y provoca re-renderizados | Copiar antes de ordenar (`[...members].sort`) |
| F-07 | Medio | Equipos | `equipos/index.blade.php:2674-2696`: `prompt()` y `.then(r => r.json())` sin `response.ok` | UX bloqueante, sin validación ni manejo HTTP | Modal/edición inline con `aria-live` |
| F-08 | Medio | Equipos | `equipos/index.blade.php:3199`: cualquier error se traduce como «Función en preparación» | Mensaje engañoso ante 500/red | Distinguir error de red, HTTP y vacío |
| F-09 | Medio | Pokédex | `pokedex/index.blade.php:25-61`: `role="tab"` sin `aria-controls`/`tabpanel` ni flechas/Home/End | Patrón ARIA incompleto | Roving tabindex o botones con `aria-pressed` |
| F-10 | Medio | Equipos | `equipos/index.blade.php:33-39` (nombre de equipo) y `:1014` (filtro de movimientos) sin `<label>`/`aria-label` | Campos sin nombre accesible | `<label for>` visible o `sr-only` |
| F-11 | Medio | Huevos | `equipos/index.blade.php:2426` lee `data.pokemon/nombre/pokemon_name`; `HuevoController.php:48-52` devuelve `{reclutado: ...}` | El mensaje pierde el nombre del Pokémon nacido | Leer `data.reclutado?.pokemon?.name` |
| F-12 | Medio | Misiones | `resources/views/misiones/index.blade.php` con fetch a `/misiones`, `/misiones/activa`, `/misiones/{id}/aceptar`; no hay rutas | Vista muerta que fallaría con 404 | Eliminar o completar backend |
| F-13 | Medio | Reclutados | `resources/views/reclutados/index.blade.php` sin referencia; `/reclutados` redirige a `/equipos` (`routes/player.php:28`) | Vista muerta | Eliminar |
| F-14 | Bajo | Global | 176 `<button>`, solo 32 con `type=` | 144 botones pueden actuar como `submit` dentro de formularios | `type="button"` por defecto |
| F-15 | Bajo | Pokédex | `pokedex/index.blade.php:271`: `w-[90vw] max-w-[90vw]` en todos los tamaños | Modal sobredimensionado en desktop | Ancho responsive por breakpoint |
| F-16 | Bajo | Varias | Iconos/fallbacks y formación duplicados en `equipos:1409-1465`, `habitats:2446-2467`, `gimnasios/show:299-320`, `pokedex:907-923` | Mantenimiento triplicado | Extraer helpers/componentes |
| F-17 | Bajo | CSS | `resources/css/components.css:44-57,78-81`: `.sprite-stack`, `.ui-modal` sin referencias | Peso y ruido | Eliminar o documentar |
| F-18 | Bajo | Equipos | `updateMemberRole`, `rolInicialDe`, `baseExperienceDe`, `categoriaIconoUrl`, `expTotalDe`, `habilidadesDe`, `movimientosDe`, `pokemonTeamNombre` sin referencias | Deuda y confusión | Borrar tras verificación |
| F-19 | Bajo | Global | 43 usos de `alert()`/`confirm()`/`prompt()` | Feedback inconsistente y bloqueante | Toasts/modales compartidos |
| F-20 | Info | Seguridad | `{!! $entry['html'] !!}` en `livewire/partials/battle-log.blade.php:5` y `x-html` de estrellas en `habitats/show.blade.php:503` | Revisados: productor escapa y valores son fijos; no explotables | Mantener revisión si cambia el productor |

---

## 3. Arquitectura y mantenibilidad

### 3.1 Línea base estática

| Herramienta | Resultado | Evidencia |
|---|---|---|
| Pint | **pass** | `{"result":"pass"}` |
| PHPStan L6 | **34 errores** (exit 1) | `.opencode/work/TASK-019/phpstan.log` |
| deptrac | **113 violaciones** (exit 1) | `.opencode/work/TASK-019/deptrac.log` |
| Rutas | **111** | `.opencode/work/TASK-019/routes.json` |

**PHPStan (34):** 17 `cast.useless`, 7 `staticMethod.dynamicCall` (Larastan con `lockForUpdate`), 3 `ignore.count`, 2 `ignore.unmatched`, 2 `function.alreadyNarrowedType`, 1 `varTag.type`, 1 `arrayValues.list`, 1 `argument.type` real menor. `composer check` ejecuta `analyse` + `lint`; falla por PHPStan.

**deptrac (113):** 78 `Habitats/Presentation`, 16 `Battle/Presentation`, 12 `Exploraciones/Presentation`, 7 controladores de módulo en `Infra/` (Gimnasios 2; Mazmorras, Huevos, Granjas, CombateRuta y CombateEntrenadores 1 cada uno). Las 106 primeras son inversión de capas real; las 7 restantes son un problema de clasificación/ubicación.

### 3.2 Puntos calientes

| Archivo | Tamaño | Problema |
|---|---:|---|
| `app/Livewire/Combate.php` | ~67 KB | Componente Livewire con estado, presentación y lógica de combate |
| `src/Battle/Domain/Combatiente.php` | ~65 KB | Entidad de dominio sobredimensionada |
| `src/Exploraciones/App/FinalizarExploracionHandler.php` | ~33 KB | Handler con reparto de recompensas, botín, incidentes y persistencia |
| `src/Exploraciones/App/ProcesarExploracionHandler.php` | ~29 KB | Tick, encuentros, retirada y transiciones |
| `src/Battle/Domain/AgregadoBatalla.php` | ~31 KB | Agregado con múltiples responsabilidades |
| `resources/views/equipos/index.blade.php` | ~194 KB | Vista + ~2.100 líneas de JS inline |
| `resources/views/habitats/show.blade.php` | ~180 KB | Vista + ~1.486 líneas de JS inline |
| `resources/views/pokedex/index.blade.php` | ~54 KB | Vista + ~400 líneas de JS inline |

### 3.3 Deuda arquitectónica

- **Inversión de capas:** dominio/aplicación de Habitats, Battle y Exploraciones importan DTOs de `Presentation` (deptrac).
- **`Shared` no es independiente:** `src/Shared/Domain/ClasificadorOfensivaDefensiva.php:7-10` importa `Src\Battle\Domain\Posicion` y `Src\Pokemon\Domain\Stats\*`; `src/Shared/Tipos/TipoPokemon.php:7` importa `PokemonEntity`. Contradice `docs/modulos/README.md` y `docs/ddd.md`.
- **`src/Crud` no existe:** `docs/architecture.md:539`, `docs/context.md:148`, `docs/conventions.md:98` y el README raíz lo documentan; `app/Crud/Base/Generators/RoutesGenerator.php:14` y `CrudGenerator.php:22` escriben en `src/Crud/`, una ruta inexistente.
- **Duplicación:** `CosecharTodo`/`CosecharPlantacion` recalculan cosecha y stock; `ValidadorExploracion::reclutadoEnExploracion()` replica `Reclutado::estaEnExploracion()`; `ReclutadoController::resolverDestino()` duplica parte de `ServicioEvolucion`.
- **N+1:** serializer de reclutados y complementos de granja.
- **Números mágicos:** 100 EXP/caramelo, 6 favoritos, límites de EV.

---

## 4. Frontend, accesibilidad y UX

- **Vistas gigantes y JS inline:** `equipos` (~2.100 líneas), `habitats` (~1.486), `pokedex` (~400). Sin módulos, sin bundling y con estado global (`window.TIPOS_BADGE`, `window.tipoBadge`).
- **Duplicación:** formación (`habitats/show:1673-1750` vs `gimnasios/show:360-410`), iconos/fallbacks en 4 vistas, preview de exploración en 2, modales repetidos en ≥12 sitios.
- **Accesibilidad:** 120 `aria-label` (positivo), pero modales sin foco atrapado, tabs incompletas, inputs sin label. `aria-live` existe en toasts.
- **Feedback:** conviven toasts, banners, `alert()`, `confirm()` y `prompt()`.
- **Navegación:** rutas hardcodeadas en `layouts/app.blade.php:44-55`; `/misiones` comentada; deriva con el backend.
- **Contratos:** el datagrid de Pokédex está alineado; la eclosión de huevos no. El resto de vistas principales se revisó por patrones y puntos calientes.
- **Seguridad de salida:** único `{!! !!}` y único `x-html` revisados; no explotables con los productores actuales.

---

## 5. Documentación de módulos

### 5.1 Cobertura

| Módulo | README | Context | ADR | Contratos | Estado |
|---|---|---|---|---|---|
| Battle | Sí | Sí | ADR-0001 (F5) | Parcial | Desfasado en métricas/versión y estructura |
| CombateEntrenadores | Sí | Sí | — | Parcial | Endpoint del README incorrecto |
| CombateRuta | Sí | Sí | — | Parcial | Endpoint del README incorrecto |
| Equipos | Sí | Sí | — | Sí | Vigente |
| Exploraciones | Sí | Sí | ADR-0001 | Sí | Spec histórica contradictoria |
| Forja | **No** | **No** | ADR-0001/0003 | Solo arquitectura | **Sin documentar** |
| Gimnasios | Sí | Sí | — | Sí | `docs/gimnasios.md` dice 5; hay 18 |
| Granjas | **No** | Sí, incompleto | ADR-0001/0002/0003 | Parcial | **Sin README**; faltan plantaciones/bayas |
| Habitats | Sí | Sí | ADR-0002 | Sí | Interfaz singular obsoleta; 78 violaciones |
| Huevos | Sí | Sí | — | Sí | README promete tests inexistentes |
| Mazmorras | Sí | Sí | — | Parcial | Endpoint del README incorrecto |
| Pokemon | Sí | Sí | — | Sí | Vigente |
| Reclutamiento | Sí | Sí | — | Sí | Vigente |
| Shared | Sí | Sí | ADR-0001 | Sí | «Sin dependencias» es falso |
| `app/` | Sí | — | — | Parcial | Rutas de forja/granjas ausentes |

### 5.2 Desfases confirmados

| ID | Evidencia | Corrección |
|---|---|---|
| D-01 | `ls src/Forja/README.md src/Forja/context.md src/Granjas/README.md` → no existen | Crear README de Forja y Granjas; context de Forja |
| D-02 | `docs/modulos/README.md:4` afirma «cada módulo tiene su README»; faltan Forja/Granjas | Actualizar índice y dependencias |
| D-03 | `src/Crud` no existe; `docs/architecture.md:539`, `docs/context.md:148`, `docs/conventions.md:98`, README raíz; generadores escriben ahí | Decidir eliminación o restauración; corregir docs/generadores |
| D-04 | `BattleSessionService.php:50` → 21; docs dicen 11 (`docs/context.md:96`), 20 (`src/Battle/context.md:614,944`), 11 (`CombateRuta/Gimnasios/testing`), 10 (`Huevos`) | Fuente única de versión |
| D-05 | 76 migraciones vs 60 (`docs/context.md`); 32 modelos vs 14 (`docs/architecture.md:812`); 13+base controladores vs 10 (`:810`); 24 archivos Chain vs 7 (`docs/context.md`) | Eliminar conteos manuales o actualizarlos |
| D-06 | READMEs: `/api/ruta…` vs `/api/habitats/{habitat}/ruta/...`; `/api/mazmorras…` vs `/api/habitats/{habitat}/mazmorra/...`; `/api/entrenadores…` vs `/api/habitats/{habitat}/entrenadores/...` | Corregir endpoints |
| D-07 | `docs/spec_evolucion_exploraciones.md:47` usa `team_id`; el código usa `reclutado_id` (`ExploracionActivaController.php:81,308`) | Marcar spec como superseded o reescribir |
| D-08 | `docs/gimnasios.md:5,54,67,84,136,180` dice 5; `CatalogoGimnasios.php:15` dice 18 | Actualizar o archivar |
| D-09 | `src/Habitats/README.md:23` cita `HabitatRepositoryInterface`; el código usa `HabitatLecturaRepositoryInterface` + `HabitatGestionRepositoryInterface` | Corregir README |
| D-10 | `docs/architecture.md` cita `DTOEquipoBatalla` (no existe), «4 providers» (hay 3) y estructura de Battle obsoleta | Actualizar secciones |
| D-11 | README raíz: 13 modelos (hay 32), 18 migraciones (hay 76), `IniciarBatalla` (no existe), `routes/reclutados.php` (no existe), 5 agentes (hay 8), SQLite vs PostgreSQL Docker | Reescribir README raíz |
| D-12 | `docs/skills-index.md` métricas obsoletas; `docs/testing.md:194` SESSION_VERSION 11; `documentacion/` histórico duplicado; `active/` operativo mezclado | Marcar canónico vs histórico |
| D-13 | Enlaces relativos de `docs/**`, `src/**/*.md`, `app/README.md`, `README.md` y `AGENTS.md`: **0 rotos** | Mantener verificación en CI |

### 5.3 Plantilla propuesta de README de módulo

```markdown
# {Módulo}

- Estado: vigente | en construcción | deprecado
- Última revisión: YYYY-MM-DD
- Responsable: {equipo/persona}

## Propósito y límites
## Estructura de carpetas
## Entradas públicas (casos de uso, controladores, rutas, comandos)
## Contratos (DTOs de entrada/salida, errores, códigos HTTP)
## Dependencias entrantes y salientes (diagrama Mermaid)
## Flujos principales
## Persistencia (tablas, migraciones, índices)
## Eventos y jobs
## Pruebas (existentes o «no hay tests»)
## Deuda conocida y riesgos
## ADR y documentación relacionada
## Guía de búsqueda (grep)
```

### 5.4 Plan documental priorizado

| # | Acción | Tipo | Impacto | Esfuerzo |
|---:|---|---|---|---|
| 1 | README + context de Forja | Nuevo | Alto | M |
| 2 | README de Granjas y completar plantaciones/bayas en su context | Nuevo | Alto | M |
| 3 | Actualizar `docs/modulos/README.md`, `src/README.md` y `app/README.md` | Actualizar | Alto | S |
| 4 | Reescribir README raíz (stack, conteos, agentes, rutas) | Actualizar | Alto | M |
| 5 | Corregir `docs/context.md`, `docs/architecture.md` y `SESSION_VERSION` | Actualizar | Alto | M |
| 6 | Corregir endpoints de CombateRuta, Mazmorras y CombateEntrenadores | Actualizar | Alto | S |
| 7 | Marcar `docs/spec_evolucion_exploraciones.md` como superseded | Actualizar | Medio | S |
| 8 | Corregir `docs/gimnasios.md` (5→18), Habitats y Shared | Actualizar | Medio | S |
| 9 | Actualizar `docs/testing.md` y `docs/skills-index.md`; separar histórico | Actualizar | Medio | S |
| 10 | ADRs: exploración individual, Forja, dependencia de Shared y gobierno documental | Nuevo | Medio | M |

---

## 6. Código muerto y deprecated

- `HabitatsController::toggleFavorito()` — no-op, ruta activa (`routes/habitats.php:34`), y el frontend de `habitats/show` sigue llamándolo.
- `ProcesarExploracionHandler::poolHabitat()` — privado, `@deprecated`, `@phpstan-ignore method.unused`.
- `HabitatsController::$equiposEnExploracion` — contrato vacío con comentario `@deprecated`.
- `routes/web.php:52` — `src/Crud/routes.php` comentado; generadores apuntan a `src/Crud` inexistente.
- `resources/views/misiones/index.blade.php` — sin rutas.
- `resources/views/reclutados/index.blade.php` — sin referencia; `/reclutados` redirige.
- `equipos/index.blade.php`: `openExploracionModal()` sin llamadas; `updateMemberRole`, `rolInicialDe`, `baseExperienceDe`, `categoriaIconoUrl`, `expTotalDe`, `habilidadesDe`, `movimientosDe`, `pokemonTeamNombre` sin referencias.
- `habitats/show.blade.php`: `cargandoDetalle`, `detalleActivoFamilia`, `errorDetalle`, `esTabActivaFamilia`, `navegarTabFamilia` sin referencias.
- `resources/css/components.css:44-57,78-81`: `.sprite-stack`, `.ui-modal` sin referencias.
- ~20 `@deprecated` en `src/Exploraciones`, `src/Battle`, `src/Reclutamiento`, `src/Mazmorras`, `src/Pokemon`; muchos son wrappers BC con tests. No eliminar sin verificar uso real.

---

## 7. Propuestas de mejora para el MVP

| # | Mejora | Tipo | Impacto | Esfuerzo | Dependencias |
|---:|---|---|---|---|---|
| 1 | Corregir concurrencia de reclutamiento, caramelos, evolución, exploración y granja | Bug/datos | Muy alto | S/M | Migración para unique parcial |
| 2 | Añadir rol admin, policies y middleware en `/api/admin/*` y mutaciones de hábitats | Seguridad | Muy alto | S | Decisión de rol |
| 3 | Rate limiting en login/registro y acciones de escritura | Seguridad | Alto | S | Ninguna |
| 4 | Corregir motivo de retirada y contrato de eclosión | Bug/contrato | Alto | S | Ninguna |
| 5 | Frontend: helper fetch con error visible, dropdown, slots ordenados y `sort` no mutante | Bug/UX | Alto | S | Ninguna |
| 6 | Recuperar `composer check`: PHPStan 34 y deptrac 113 | CI/deuda | Alto | S/M | Decisión baseline vs refactor |
| 7 | Eliminar código muerto y endpoints no-op verificados | Deuda | Medio | S | Verificación de referencias |
| 8 | Reducir N+1 de serializer y granja | Rendimiento | Medio | M | Ninguna |
| 9 | Documentar Forja/Granjas y corregir documentación desfasada | Documentación | Alto | M | Ninguna |
| 10 | Accesibilidad: focus trap, labels y tabs | UX | Medio | M | Componente modal |
| 11 | Refactor de Habitats para revertir Dominio→Presentación | Arquitectura | Alto | L | Plan específico |
| 12 | Dividir vistas/classes gigantes (`equipos`, `habitats`, `Combatiente`, handlers) | Mantenibilidad | Alto | L | Plan de regresión |

**Orden recomendado:** 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 10 → 11 → 12. Los bugs y la autorización primero; los refactors grandes al final y con aprobación específica.

---

## 8. Verificación ejecutada

- `git status`, `git branch --show-current`, `git log`: rama `task-011`, HEAD `0317179`, solo `.opencode/work/TASK-019/` sin rastrear.
- `vendor/bin/pint --test`: pass.
- `vendor/bin/phpstan analyse -c phpstan.neon`: 34 errores, exit 1.
- `vendor/bin/deptrac analyse --no-progress`: 113 violaciones, exit 1.
- `php artisan route:list --json`: 111 rutas.
- `composer audit --locked --format=json`: 17 avisos en Laravel, Livewire, Symfony y CommonMark.
- Verificación de middleware con `route:list`: `/api/admin/gyms` y mutaciones de hábitats solo tienen `web + Authenticate`.
- Verificación de migraciones: `users.name` sin `unique`; `exploraciones_activas` sin índice único parcial.
- Verificación de `config/session.php`: `lifetime=525600`, `encrypt=false`, `http_only=true`, `same_site=lax`.
- Verificación de `bootstrap/app.php`: `withMiddleware` vacío; mensajes de excepción devueltos al cliente.
- Verificación de contratos: `HuevoController` devuelve `reclutado`; la vista lee `pokemon/nombre/pokemon_name`.
- Verificación documental: 0 enlaces relativos rotos; conteos y versiones contrastados.
- **No se ejecutaron tests, no se modificó código de aplicación, no se hizo `git push`.**

---

## 9. Preguntas abiertas y riesgos residuales

1. ¿Debe existir una sola exploración activa por reclutado? Si sí, falta el índice único parcial.
2. ¿`pokemon.id` coincide siempre con `species_id`? De ello depende B-13.
3. ¿La gestión de gimnasios/hábitats es solo de administración? Si no, H-01/H-02 requieren otra solución.
4. ¿`favoritos.reclutado_id` tiene `ON DELETE CASCADE`? Si no, B-14 es un 500 reproducible.
5. ¿Se permite invocar `FinalizarExploracionHandler` fuera del lock de caché? Si no, B-04 baja a riesgo teórico.
6. ¿`src/Crud` se eliminó definitivamente? Hay que decidir si se restauran las rutas o se retiran los generadores.
7. ¿Cuál es la fuente de verdad documental: `docs/architecture.md`, `docs/context.md` o los `context.md` de módulo? Propuesta: architecture (estado), context de módulo (detalle), ADR (decisiones).
8. ¿`documentacion/`, `active/`, `Tareas_pdte/` y `docs/analysis/pvp-*` son archivo histórico o documentación viva?
9. ¿El entorno canónico es SQLite o PostgreSQL/Docker? El README raíz dice SQLite; Docker usa PostgreSQL.
10. ¿Se actualizará el README de módulo como parte del MVP o se deja para una tarea documental específica?

**Riesgos residuales:** los bugs de concurrencia no se han reproducido con carga real; la autorización de administración requiere una decisión de producto (rol); el refactor de Habitats es grande y puede introducir regresiones; la documentación desfasada puede inducir a agentes/devs a implementar endpoints o estructuras inexistentes.
