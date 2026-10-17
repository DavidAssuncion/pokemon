# RESUMEN_TAREA — Cierres documentales de iteraciones (índice de análisis activos)

Documento índice: cada sección corresponde a una tarea cerrada cuyo conocimiento ya fue
absorbido en `docs/context.md` / `docs/architecture.md`. Los análisis fuente viven en
`active/ANALISIS_*.md` (NO borrar; tabla índice al final).

---

## Iteración actual — Combate 3v3: equipos de 3, motor de 1 acción por ronda e invocaciones

### Estado: CERRADA (QA ✅ / Cleaner ✅ / Arquitecto ✅ B:0 / Hardener ✅ PASS con observaciones)

El modelo estándar de combate pasa de 5v5 a **3v3**: equipos de exactamente 3 miembros, una acción
por pokémon vivo y ronda ordenada por velocidad efectiva, y puerta explícita de invocaciones en el
motor. Testing diferido (comportamientos documentados en `docs/testing.md`; no se ejecutan tests).

Commits de la iteración (15):

- `3039027d89` — backend base 3v3 (equipos, motor, escalados, mock) + migración `2026_10_15_000001` [backend]
- `f846f1ca5e` — fix `escalarATres` (roster >3 → recorte, vacío → []) [backend]
- `2067d1fbb9` — frontend 3v3 (`effectiveSpeed`, slots 1-3, textos) [frontend]
- `c89cf977de` — traza B1 (`CrearEquipoInicial` sin diff; WIP ajeno) [qa]
- `729f51dc81` — guarda KO al cierre de ronda + limpieza de logs debug [backend]
- `a62fb1b61f` — limpieza de logs debug en la vista de equipos [backend]
- `3ba2515586` — comentarios 5→3 [backend]
- `67a15e5197` — `Combatiente::velocidadEfectiva()` [backend]
- `9b8aa95927` — predicado de acción pendiente en la colección [backend]
- `a445a40912` — helper de guarda KO [cleaner]
- `3d1cf137e0` — `urlIcono()` (webp/shiny/formSuffix) [cleaner]
- `cff45616e8` — DRY del reparto de EXP [cleaner]
- `f429271af3` — 422 rival vacío en entrenador y gimnasio [backend]
- `9fcc9ff5e3` — log de ataque restaurado con corchetes [backend]
- `047782440e` — traza post-Arquitecto (O1/O2) [docs]

### Alcance implementado

1. **Equipos estándar de exactamente 3** — `TeamController` slot `max:3`; migración
   `2026_10_15_000001` borra `team_members` con `slot > 3` (`down()` no-op);
   `ConstruirEquipoJugador::desdeEquipo` lanza 422 «exactamente 3 miembros» (punto único de los 4
   iniciadores); `IniciarCombateRuta` pierde su pre-check. Exploraciones sin validación nueva
   (ratificado); jefe de mazmorra 3v1 sin cambios.
2. **Motor de turnos** — una acción por pokémon vivo y ronda; `getNextActor()` por velocidad
   efectiva con empates estables por inserción; `consumeAction()` solo marca; `startNewRound()`
   resetea; invariante anti-null con pendientes; bloqueado consume su acción. Eliminada la
   velocidad acumulada.
3. **Invocaciones (solo motor)** — `AgregadoBatalla::incorporarCombatiente(EquipoBatalla,
   Combatiente, Posicion)`: sin tope, contador 0 en la ronda, sin efectos de entrada ni log, misma
   colección que `GestorTurnos`.
4. **Rivales exactamente 3** — ruta (3 tiradas), entrenador (3 especies con repetición ponderada),
   gimnasio (`escalarATres`: recorte/ciclo/vacío→[]). Rival vacío → 422 en los tres inicios, sin
   persistir sesión.
5. **Sesión y contrato de vista** — `SESSION_VERSION` 11 (mínimo 9; v9/v10 con `velocidadAcumulada`
   se ignoran); `effectiveSpeed` sustituye a `accumulatedSpeed`; `buildTurnQueue` por velocidad
   efectiva estable.
6. **Log, EXP y mock** — log de ataque restaurado con corchetes y velocidad efectiva (sin nombres);
   `MIEMBROS_EQUIPO = 3` para el reparto de EXP; mock 3v3.

### Documentación generada en el cierre

- `docs/context.md` — resumen de la iteración, 3v3 en todo el documento, motor de turnos,
  `SESSION_VERSION` 11, 60 migraciones, deuda 17 y referencias nuevas.
- `docs/architecture.md` — motor de turnos/invocaciones (Battle), CombateRuta/CombateEntrenadores
  3v3, guards 422, ER y conteo de migraciones.
- `src/Battle/context.md` — `GestorTurnos` real, `Combatiente` (velocidad efectiva, contrato),
  `EquipoBatalla` (invocaciones/deprecaciones), sesión v11 y sección de la iteración 3v3.
- `src/CombateRuta/context.md` — 3v3, guard centralizado, rival/equipo de 3, sesión v11.
- `src/CombateEntrenadores/context.md` y `src/Gimnasios/context.md` — rival de 3, guard 422, sesión
  v11, `escalarATres`.
- `docs/testing.md` — secciones 8-13 (motor, invocaciones, exactamente-3, contrato/sesión/log,
  invariantes y property tests) y acceptance ampliada.

### Deuda pendiente (no bloqueante)

- DRY preexistente `GeneradorEquipoRuta` ↔ `GeneradorEquipoEntrenador` (pool + ponderación) →
  refactor futuro; constante «3» repetida cross-módulo (futuro `MIEMBROS_EQUIPO` compartido).
- `Combatiente::$iconName` serializado sin uso en `aArrayVista`; doble `session->guardar()` en
  `Combate::initMockBattle`; `lowestSpeed()`/`menorVelocidadBase` retenidos (test
  `EquipoBatallaTest`); `combatants()` deprecado en `app/Livewire/Combate.php:513,619,624`; PHPDoc
  de `buildTurnQueue`; métodos largos (PHPMD) preexistentes.
- Tooling: `phpstan-hardener.neon` inválido con PHPStan 2.2.9 (parámetros 1.x), shim deptrac 1.0.2
  no parsea constantes tipadas (`MultiplicadoresStats`), sin baseline PHPStan; CRAP no medido
  (testing diferido).

---

## Iteración anterior — Ruta/shiny/huevos/EV/IV/CP (6 mejoras de progresión)

### Estado: CERRADA (QA ✅ / Hardener ✅ / cierre documental ✅)

6 mejoras de progresión implementadas, QA-aprobadas y commiteadas. Cierre documental
(Bibliotecario). Testing diferido al cierre del módulo (comportamientos en `docs/testing.md`).

Commits de la iteración:

- `79b7e45b27` — backend funcional (shiny ruta, huevos, IVs, CP, dar-evs, anim-hit) [backend]
- `a8c46c1a57` — correcciones QA B1/B2/O1/O2/O3/O6 [backend]
- `3f217ff372` — frontend base: anim-hit, detalle IV/EV/habilidades, pestaña 🥚 Huevos, badges shiny [frontend]
- `af1b676d4c` — frontend QA: EV +10 (O4), huevoProgreso en horas, es_shiny tolerado [frontend]
- `51cc0c9`, `b661fad`, `8d3fd57`, `8eee2ed` — refactor Cleaner [backend]
- `869f84a217` — idempotencia eclosión + SESSION_VERSION 10 (mínimo 9) [backend + arquitecto]
- `f1ea5d2ebc` (+ `7c919d8f9d`) — hardening P1 guards capture_rate/hatch [backend + hardener]

### Alcance implementado

1. **Animación de daño en combate** — `.anim-hit` (600 ms) al defensor vía `$animDefenderId`;
   `prefers-reduced-motion` respetado.
2. **Shiny salvaje en ruta** — 1/2048 por slot; captura directa con IVs 15 y `es_shiny`; iconos
   `/images/iconos_webp/shiny/{id}_s.webp`; contrato `es_shiny` (snake) en DTOs.
3. **Sistema de huevos** — módulo `src/Huevos/` (DDD): puesta `255/capture_rate` h, eclosión
   `ceil(hatch/15)` h; estados derivados por timestamps; límites 1 gestando + 1 incubando + 1
   activo por familia; pareja ≥2 reclutados de la misma cadena; shiny 1/512 (IVs 15); eclosión
   idempotente (422) + 404 ajeno.
4. **Detalle pokémon + EVs** — modal con IVs/EVs/habilidades y `POST /reclutado/{id}/dar-evs`
   (+10, tope 255, 422 stock/exploración, `lockForUpdate`).
5. **IVs 0-15 ×4 por stat** — `BattleStats::$ivs` opcional; reparto top-2 por dificultad
   (gimnasio 12/10, líder 14/12, entrenador por nivel 8/6-12/10).
6. **CP al nivel del usuario** — `ReclutadoSerializer::cpDe(nivelUsuario)` + EVs reales.
   `SESSION_VERSION` 9 → 10 (mínimo 9). 3 migraciones nuevas → 59 totales.

### Documentación generada en el cierre

- `docs/context.md` — iteración, SESSION_VERSION 10, 59 migraciones, pendientes 14-16,
  referencias nuevas.
- `docs/architecture.md` — sección del módulo `src/Huevos/`, ER actualizado (huevos,
  reclutados.ivs/evs), 59 migraciones.
- `src/Huevos/context.md` — contexto canónico del módulo (nuevo).
- `docs/testing.md` — comportamientos diferidos + invariantes para property tests (nuevo).

### Deuda pendiente (no bloqueante)

- OBS-01/02 — UI más restrictiva que backend en límites de huevos (decisión de producto).
- `habilidades=[]` — pendiente de `app/Models/Ability` + pivot (boundary del Arquitecto).
- WIP ajeno sin commitear (untracked `app/Support/AvistamientosPokedex.php`,
  `app/Support/CargadorPokemonsDerrotados.php` y `storage/data/*`) — NO tocar.
  `src/Equipos/App/CrearEquipoInicial.php` YA NO es WIP ajeno: es canónico y está commiteado.
- Fase de testing diferida: ejecutar `docs/testing.md` al cierre del módulo.

---

## Iteración anterior — Eliminación de la tabla `evolution_chains` (bug 23503)

### Estado: CERRADA (QA ✅ / Hardener ✅ HARDENED)

Bug corregido, endurecido y con cierre documental (Bibliotecario). Tests pendientes solo
de ejecución en CI (sin BD local).

Commits de la iteración:

- `daeadef198` — fix(exploraciones): eliminar tabla evolution_chains y su FK rota (bug 23503) [backend]
- `c6877c5` — refactor(exploraciones): guard clause explicita en fase() [cleaner]
- `PENDIENTE` — fix(exploraciones): eliminar evolution_chains (bug 23503) + endurecimiento P1 + cierre documental
  (TODO junto: 3 archivos de código + `active/ANALISIS_BACKEND.md` + `docs/context.md` + `docs/architecture.md` + este doc)

### Causa raíz

`caramelos.evolution_chain_id` tenía FK → `evolution_chains.id`, pero la tabla NUNCA se
poblaba (solo los tests la creaban). Insertar caramelos con cadenas sin fila (ej. 51) →
FK violation. La tabla solo tenía `id` + `data` (json nunca leído).

### Solución definitiva (Opción B aprobada por el cliente)

- 2 migraciones nuevas: `2026_08_29_000001_drop_foreign_evolution_chain_from_caramelos`
  (up: drop FK; down: recrea FK, best-effort) y
  `2026_08_29_000002_drop_evolution_chains_table` (up: drop tabla; down: recrea).
- Modelo `app/Models/EvolutionChain.php` borrado; relaciones `evolutionChain()` quitadas
  de `Pokemon` y `Caramelo`.
- Las familias se agrupan por la COLUMNA `pokemon.evolution_chain_id` (sin tabla ni FK):
  `FinalizarExploracionHandler::cargarMiembrosDeCadenas()` y
  `ReclutamientoController::miembrosDeLasCadenas()` construyen el mapa
  `chainId => Collection<Pokemon>` (miembros ligeros: id, name, species_id,
  evolution_chain_id, vía `whereIn(...)->get()->groupBy()`); lo consumen
  `NormalizadorPokemonDerrotado::fase()` (fase = miembros del mapa con species_id ≤
  actual; sin cadena → fase 1) y `TransformadorResultadoExploracion::pokemonBaseDeCadena()`
  (min species_id del mapa; fallback determinista con `sortBy('species_id')`).
- Comportamiento preservado: fase, base min species_id, `pokemon_id`, agrupación de
  caramelos y columna `caramelos.evolution_chain_id` (unique conservada, sin FK).

### Desvío justificado del brief

El brief decía "ReclutamientoController NO lo toques", pero el grep mostró que SÍ usaba
la relación: eager load `pokemon.evolutionChain.pokemon` en `discard()`/`discardAll()` y
fase vía `$pokemon->evolutionChain->pokemon->where(...)->count()` en `otorgarCaramelos()`.
Se corrigió con la columna (cambio mínimo; contrato de endpoints preservado por tests
existentes). BONUS: cadenas huérfanas → fase 1 (antes Error fatal con la relación null).

### Cadena de calidad

- QA ✅ APROBADO: desvío ReclutamientoController verificado, bug latente del transformador
  (null → null intencional), down best-effort documentado, tests a CI.
- Hardener ✅ HARDENED con 3 P1 aplicados:
  1. Fallback determinista en `pokemonBaseDeCadena()`: `sortBy('species_id')` antes del
     `first()` (antes indeterminista respecto a su docblock).
  2. `test_finalizacion_con_fase_dos_determinista_persiste_cantidad_por_fase` (mata
     M1 `whereIn('id')` y M2 pérdida de `species_id` en el select del mapa).
  3. `test_caramelos_familia_fallback_sin_mapa_elige_el_derrotado_de_menor_species_id`
     + `test_caramelos_familia_con_dos_cadenas_devuelve_entradas_independientes_ordenadas_por_cadena`.
- Cleaner: `fase()` con guard clause explícita (`c6877c5`).
- Detalle M2 documentado: en PHP `null <= 4` evalúa `true`, por lo que si infection muta
  el select del mapa (species_id → null) la fase NO se degrada a 1 (cuenta todos los
  miembros) y el test de fase 2 podría no matar M2; refuerzo documentado si infection lo
  revela en el futuro.

### Tests clave

- `test_finalizacion_con_chain_id_sin_tabla_evolution_chains_finaliza_ok` — regresión del
  bug 23503 (chain 51 sin tabla → finaliza OK, `caramelos` se inserta sin error de FK).
- `test_finalizacion_con_fase_dos_determinista_persiste_cantidad_por_fase` — fase 2
  determinista (2× charmander → UNA fila `caramelos` cantidad 4 = 2 × fase 2).
- `test_caramelos_familia_fallback_sin_mapa_elige_el_derrotado_de_menor_species_id` y
  `test_caramelos_familia_con_dos_cadenas_devuelve_entradas_independientes_ordenadas_por_cadena`
  (unit del transformador; 2 cadenas → entradas independientes ordenadas por chain id).
- `MigrationStatusTest::test_evolution_chains_table_no_existe` (test rojo de la migración;
  también assert unique de `caramelos.evolution_chain_id` conservado).
- Grep final: 0 referencias de `EvolutionChain` en código (solo docs/ y active/).

### Estado

- Tests NO ejecutados en local (sin BD desde el host); listos para CI
  (RefreshDatabase + SQLite `:memory:`).
- Cierre documental: `docs/context.md` y `docs/architecture.md` actualizados (tabla
  eliminada de listados, mapa por columna documentado, diagrama ER y conteos corregidos).

### Deuda pendiente

- Ejecutar la suite completa en CI.
- Pendientes preexistentes de `docs/context.md` (god-class `HabitatRepository`, etc.).

---

## Tarea anterior — Caramelos con imagen + primer integrante = menor species_id + alineación de botones (HISTÓRICO — preservado)

### Estado: CERRADA (QA ✅)

Feature implementada, QA-aprobada y commiteada. Cierre documental (Bibliotecario).
Tests pendientes solo de ejecución en CI.

Commits de cierre de la iteración:

- `3f9c869` — feat: primer integrante de familia = menor species_id (caramelos_familia, admin habitats, stat_slug) [backend]
- `3d4e940` — refactor(exploraciones): unificar STAT_NOMBRES y STAT_SLUGS en STATS [controlador]
- `47a2cbc` — feat(exploraciones,habitats): imagenes de caramelos con fallback a placeholder y alineacion de iconos en botones [frontend]

### Alcance implementado

1. **Imágenes de caramelos en `/exploraciones`** (`resources/views/exploraciones/index.blade.php`):
   TODOS los caramelos muestran su asset correspondiente — familia →
   `/images/candy_pokemon/{id}.webp`, EV → `/images/candy_ev/{slug}.webp`, tipo →
   `/images/candy_type/{slug}.webp` (ruta corregida: antes apuntaba a `type_candy/{slug}.png`,
   que NO existe). Fallback único: constante Blade `$candyFallback` → placeholder
   `/images/candy_pokemon/0.webp` con `this.onerror=null` (anti-loop). En la bitácora, si falta
   la clave (datos legacy) → SVG genérico.
2. **Regla de negocio "primer integrante = menor `species_id`"** (confirmada por el cliente):
   los assets `candy_pokemon/` se generaron con ese criterio. Aplicada en:
   - `HabitatRepository` (Admin Gestión): `getFamilyMembersByChain` ordena por species_id asc
     (desempate id); la "base" de display = `$members[0]` (min species_id); GET families y
     GET unassigned-families ordenadas por el species_id mínimo de la cadena
     (`sortChainIdsByMinSpeciesId`).
   - `TransformadorResultadoExploracion`: `caramelos_familia[].pokemon_id` (id del min
     species_id; null si no hay base).
3. **`stat_slug` para caramelos EV**: const `STATS` unificada en `ExploracionActivaController`
   (`[1=>['nombre'=>'PS','slug'=>'hp'], 2=>['nombre'=>'Ataque','slug'=>'atk'],
   3=>['nombre'=>'Defensa','slug'=>'def'], 4=>['nombre'=>'Ataque Especial','slug'=>'atksp'],
   5=>['nombre'=>'Defensa Especial','slug'=>'defsp'], 6=>['nombre'=>'Velocidad','slug'=>'spd']]`
   — refactor del Cleaner que unificó STAT_NOMBRES + STAT_SLUGS).
4. **Alineación de iconos en botones de hábitat** (`habitats/show.blade.php`): los 4 botones
   apilados (Granjas/Entrenadores/Mazmorras/Admin-Gestión) usan slot de icono fijo
   `<span class="w-8 shrink-0 flex justify-center">` + texto `<span class="flex-1 text-center">`
   → iconos alineados a la misma X independientemente de la longitud del texto.

### Regla de negocio y quirk semántico documentado

- **El reparto de NIVELES no cambió**: los stages se calculan por BFS evolutivo (base evolutiva
  → stage 1 → nivel 1, etc.). En Happiny(440)/Chansey(113)/Blissey(242): Happiny sigue en el
  nivel 1 (stage 1), Chansey → nivel 2, Blissey → nivel 3.
- **Quirk semántico**: la X (quitar familia completa) está en la tarjeta base de DISPLAY
  (Chansey 113, nivel 2), mientras Happiny (nivel 1) aparece como "evolución" sin X. El min
  species_id solo cambia la IDENTIDAD de display (qué pokémon es la tarjeta "base" con la X) y
  el ORDEN de las familias; los niveles son del BFS.

### Assets verificados

| Asset | Estado |
|---|---|
| `candy_pokemon/113.webp` (Chansey) | EXISTE |
| `candy_pokemon/0.webp` (placeholder genérico) | EXISTE |
| `candy_pokemon/440.webp` (Happiny), `239.webp` (Elekid), `242.webp` (Blissey), `466.webp` (Electivire) | NO existen (por diseño: min species_id) |
| `candy_ev/{hp,atk,def,atksp,defsp,spd}.webp` | EXISTEN (6) |
| `candy_type/{slug}.webp` | EXISTEN (18 slugs en español) |
| `type_candy/` | NO existe (ruta antigua rota, corregida) |

### Tests

- `tests/Feature/Habitats/FamiliesTest.php` +2: bebé posterior → base 113; orden por
  species_id mínimo.
- `tests/Feature/ExploracionesTransformadorTest.php` NUEVO +2: `pokemon_id` = 113 (no 440);
  `pokemon_id` null sin base.
- `ExploracionesPageTest` / `ExploracionesTest`: asserts aditivos de `stat_slug` y `pokemon_id`.
- **NO ejecutados en local** (sin BD desde el host); listos para CI
  (RefreshDatabase + SQLite `:memory:`).

### Estado QA

✅ APROBADO: contrato frontend↔backend 1:1, regla min species_id correcta, BFS intacto,
edge cases cubiertos.

### Deuda pendiente

- Ejecutar la suite de tests en CI (no ejecutados en local).
- God-class `src/Habitats/Infra/HabitatRepository.php` (~477 líneas, 12 métodos) pendiente de
  dividir (ver pendiente 5 de `docs/context.md`).
- Este documento funciona como índice de los análisis activos (tabla abajo).

---

## Tarea anterior — Admin "Gestión" de familias en hábitats (HISTÓRICO — preservado)

### Estado: CERRADA

Feature implementada, QA-aprobada, endurecida y revalidada por el Arquitecto.
Código commiteado en `fd96a0a` (20 archivos) + cierre documental `8c8138d`.
Sección preservada como histórico de la tarea.

### Objetivo

Añadir al detalle de hábitat (`habitats/show.blade.php`) un modal **"Admin - Gestión"**
para administrar qué familias evolutivas viven en cada hábitat y en qué nivel, con
optimización de UI (sin refresco pesado).

### Alcance implementado

- Modal Alpine `habitatShow()` en `resources/views/habitats/show.blade.php` con dos pestañas:
  - **Asignar**: familias sin hábitat (solo base), filtros por nombre y tipo, grid con chips de tipos.
  - **Ya Asignados**: pokémon agrupados por nivel 1/2/3; reordenamiento por pokémon vía PATCH;
    X en la tarjeta base que quita la familia COMPLETA del hábitat.
- API:
  - `GET /api/habitats/{id}/families` — familias asignadas con niveles reales por miembro.
  - `POST /api/habitats/{id}/families` — asigna familia; devuelve **201 con la familia COMPLETA**
    y niveles reales (fix de ramificación; sin inferencia client-side).
  - `DELETE /api/habitats/{id}/families/{chainId}` — quita TODA la familia del hábitat.
  - `PATCH /api/habitats/{habitat}/pokemon/{pokemon}` (NUEVO) — mueve un pokémon de nivel
    (`MoverPokemonDeNivel` / `HabitatRepository::movePokemonToLevel`).
  - `GET /api/habitats/unassigned-families` — familias sin hábitat.
- **Contrato JSON aditivo** `types: array<{id: int, name: string}>` (unión deduplicada de tipos de
  TODOS los miembros, ordenada por id) en `DTOFamiliaDisponible` y `DTOFamiliaSinHabitat`.
- **Optimización "sin refresco pesado"**: la query inicial se ejecuta SOLO al abrir el modal; las
  mutaciones posteriores (asignar/quitar/mover) son locales tras 200 OK, sin recargar listados.
- Eliminado código muerto: `app/Livewire/Habitats/FamilyModal.php`,
  `resources/views/livewire/habitats/family-modal.blade.php`,
  `resources/views/habitats/_family-modal.blade.php`, `resources/views/habitats/_level-preview.blade.php`,
  `src/Habitats/Presentation/DTOFamiliaAsignada.php`, `HabitatRepository::getFamilyPokemonsByChain()`.
  Hábitats queda **sin dependencia de Livewire** (Alpine + fetch API).

### Decisiones de negocio (aprobadas)

- La **X quita la familia COMPLETA** (no un pokémon suelto).
- Familias unicetapa → **nivel 2** (`levelForStage`: `totalStages === 1 → 2`).
- Reparto por fases: base→1, 2ª evolución→2, 3ª→3; familias ramificadas → **todas las evoluciones
  al mismo nivel real** (Eevee: base 1, Vaporeon/Jolteon 2 — no 2/3).
- Reordenamiento manual **POR POKÉMON** (no por familia).

### Tests

- `tests/Feature/Habitats/FamiliesTest.php` (671 líneas, 21 tests) cubre GET/POST/DELETE/PATCH:
  happy paths, validaciones (422), multi-hábitat (PATCH/DELETE no afectan a otros hábitats con la
  misma familia) y los nuevos P0 (tipos de familia: unión dedup ordenada) y P1 (multi-hábitat).
- **NO ejecutados en local** (sin conexión a Postgres desde el host); listos para CI
  (RefreshDatabase + SQLite `:memory:`).

### Deuda técnica pendiente (fuera de alcance)

- God-class `src/Habitats/Infra/HabitatRepository.php` (~477 líneas, 12 métodos) pendiente de dividir.
- Violaciones de Clean Architecture preexistentes: `src/Equipos/Domain/TeamSrv.php`,
  `src/Reclutamiento/Domain/ReclutamientoSrv.php`, `src/Battle/Domain/BattleSrv.php`.
- Discrepancia documental corregida en ese cierre: `docs/context.md`/`docs/architecture.md`
  decían "SQLite" cuando el entorno de ejecución usa PostgreSQL (tests: SQLite `:memory:`).

---

## Índice de análisis históricos (NO borrar; preservar como referencia)

| Archivo | Contenido |
|---------|-----------|
| `active/ANALISIS_BACKEND.md` | POST `/families` devuelve familia completa con niveles reales (histórico) + sección "El primer integrante de una familia es el de menor species_id" (2026-08-29; con nota de cierre: `STAT_SLUGS` unificada en `STATS` por `3d4e940`) + sección "Eliminar la tabla `evolution_chains` (bug 23503)" (2026-08-29; decisión, mapa por columna, desvío ReclutamientoController, riesgos QA) + sección "Endurecimiento P1 (Hardener)" (2026-08-29; 3 P1 sobre commits `daeadef198` + `c6877c5`) + sección "Correctaciones QA del módulo Huevos + CP + Ruta" (2026-09-15; B1/B2/O1/O2/O3/O6) + sección "3 correcciones menores del Arquitecto (idempotencia eclosión + defensa en profundidad + SESSION_VERSION 10)" (2026-09-16) + sección "Hardening P1 de guards capture_rate/hatch en Huevos" (2026-09-16; 4 patches validados por el Hardener) + sección **"Equipos de 3, motor de 1 acción por ronda e invocaciones (R1–R7)"** (2026-10-15) + **"Corrección post-revisión del Analista"** (`escalarATres` exacto) + **"Post-Arquitecto — O1/O2"** (2026-10-17; rival vacío 422 + formato del log). |
| `active/ANALISIS_FRONTEND_HABITAT_ASSIGNFAMILY.md` | Consumo del contrato nuevo en `assignFamily`; eliminación de inferencia client-side. |
| `active/ANALISIS_FRONTEND.md` | Pokédex asíncrona, exploraciones, modal Admin-Gestión (Ya Asignados), header con nivel + sección "Iconos de caramelos (bitácora + resultados) y alineación de botones de construcción" (2026-08-29) + sección "Animación de daño, detalle con IVs/EVs/habilidades, pestaña Huevos, iconos shiny" (2026-09-15; base `3f217ff372`) + sección "Coordinación QA: caramelo EV +10, contrato /api/huevos en horas, es_shiny snake_case" (2026-09-15; O4, contrato horas, snake case) + sección **"Equipos de 3 y turnos con `effectiveSpeed`"** (2026-10-15; slots 1-3, `effectiveSpeed`, textos 3v3). |
| `active/revision.md` | Revisión post-refactor de la traducción del módulo batalla (tarea anterior; histórico). |

---

## Tarea anterior — Corrección de bugs del módulo de combate (6 getters privados) + tests TDD (HISTÓRICO — preservado)

### Estado: BACKEND COMPLETADO (pendiente QA)

El módulo de combate tenía 6 bugs de runtime que accedían a PROPIEDADES PRIVADAS de
`PokemonEntity` (`$moves`, `$tiposCollection`) en lugar de usar los getters `moves()` /
`tiposCollection()`. La ruta `/combate` estaba archivada (comentada por el Frontend) y el
módulo quedó inaccesible hasta arreglar los bugs.

### Bugs corregidos (6)

| # | Archivo | Línea | Fix |
|---|---------|-------|-----|
| 1 | `src/Battle/Domain/AgregadoBatalla.php` | 306 | `->moves->isEmpty()` → `->moves()->isEmpty()` |
| 2 | `src/Battle/Domain/AgregadoBatalla.php` | 313 | `->moves as $move` → `->moves() as $move` |
| 3 | `app/Livewire/Combate.php` | 228 | `->moves->all()` → `->moves()->all()` |
| 4 | `app/Livewire/Combate.php` | 427 | `->moves as $move` → `->moves() as $move` |
| 5 | `app/Livewire/Combate.php` | 476 | `->moves->get($index)` → `->moves()->get($index)` |
| 6 | `app/Livewire/Combate.php` | 533 | `->tiposCollection as $tipo` → `->tiposCollection() as $tipo` |

### Tests añadidos (TDD, PHPUnit)

- `tests/Feature/PokemonBattleTest.php` — MIGRADO de `BattleAggregate` (@deprecated) a
  `AgregadoBatalla` + `FabricaBatallaMock::createBattle()`: verifica 2 equipos × 3
  combatientes y acceso a movimientos vía getter.
- `tests/Unit/Battle/` (10 archivos, 26 tests) — regresión sobre mecánicas críticas sin BD:
  - `AgregadoBatallaTest` — regresión bugs 1-2 (`elegirMejorMovimiento`), fallback Placaje.
  - `CadenaDanioTest` — daño base fórmula + STAB ×1.5 + calculate > 0.
  - `EfectoOrbeVidaTest` — ×1.3 en cadena + recoil 10% HP + log.
  - `EfectoRestosTest` — cura 1/16 sin superar máximo.
  - `EfectoInvocadorClimaTest` — SEQUIA establece clima en battle start.
  - `SujetoBatallaTest` — observer notifyDamaged/notifyFainted.
  - `EstadoPokemonTest` — BURN daño por ronda, SLEEP/PARALYSIS bloquean, NONE actúa.
  - `EtapasStatsTest` — clamp ±6 y multiplicadores (+2 → ×2, -2 → ×0.5).
  - `ManejadorPosicionTest` — -50% a retaguardia con vanguardia enemiga viva.
  - `ManejadorClimaTest` — SEQUIA ±25% según tipo.
  - Helper compartido `ConstruyeCombatientes` (trait).

### Calidad

- `php artisan test --filter=Battle` → 28 passed (48 assertions).
- Suite completa: 421 passed, 1 failed PRE-EXISTENTE (`ServicioCapturaTest`, causado por el
  commit `a3f4ad7` que añadió `min(capture_rate, 45)` sin actualizar el test; ajeno a Battle).
- PHPStan nivel 6: 22 errores pre-existentes en los 2 archivos tocados (missingType/empty/etc.),
  los 6 errores de acceso a propiedad privada fueron ELIMINADOS por los fixes.
- PHPMD: solo advertencias de ExcessiveMethodLength pre-existentes en todo el módulo Battle.
- Infection `src/Battle`: MSI 42.33% (cobertura del módulo era casi nula antes; los 10 tests
  pedidos cubren las mecánicas críticas. Pendiente ampliar cobertura al resto del módulo).
- Pint aplicado (`--dirty`).

### Desviaciones del plan del Analista

1. **Entorno de tests**: `phpunit.xml` usaba credenciales `pokemon`/`secret` inexistentes y
   `.env` apuntaba al puerto 5433 (no existía). Corregido a `laravel`/`laravel` en
   `127.0.0.1:5432` (PostgreSQL local). SQLite `:memory:` descartado (sin `pdo_sqlite` en el PHP
   del entorno; los unit tests no requieren BD).
2. **Infection MSI 42%** (no 80%): la regla del 80% aplica a código con cobertura; el módulo
   `src/Battle` partía de cobertura casi nula y el brief solo pedía 10 mecánicas. Documentado
   como deuda en `active/ANALISIS_BACKEND.md`.
3. `docs/conventions.md` fue modificado por el agente Frontend/Analista en paralelo (no es de
   este backend; no se tocó).

### Deuda pendiente

- Ejecutar suite en CI (el `ServicioCapturaTest` falla por cambio de `a3f4ad7`).
- Ampliar cobertura Infection de `src/Battle` al resto de clases (GestorTurnos,
  ServicioEjecucionBatalla, BattleAggregate, etc.).

---

## Tarea anterior — Corrección UI del módulo de combate + reactivación de `/combate` (FRONTEND, HISTÓRICO — preservado)

### Estado: COMPLETADA (pendiente QA visual)

El backend corrigió los 6 bugs de runtime (`5bda03f`); el frontend reactivó el acceso y
corrigió la UI. Detalle completo en `active/ANALISIS_FRONTEND.md` (sección 2026-08-30).

### Cambios

1. **Weather banner** (`resources/views/livewire/partials/battle-field.blade.php`):
   el `@switch` comparaba valores ingleses (`'sandstorm'`, `'sun'`...) pero el enum
   `TipoClima` produce valores españoles (`'sequia'`, `'diluvio'`, `'niebla'`,
   `'granizo'`, `'tormenta_arena'`, `'turbulencias'`) → el banner NUNCA se mostraba.
   Ahora usa `TipoClima::tryFrom($weather)?->label()` para el texto en español,
   `match` con los valores reales para el icono y mantiene `weather-{{ $weather }}`.
2. **Ruta `/combate`** (`routes/web.php`): descomentada dentro del grupo
   `middleware('auth')` → `Route::get('/combate', \App\Livewire\Combate::class);`.
3. **Navegación** (`resources/views/layouts/app.blade.php`): enlace "Combate" en el nav
   (entre Equipos y Reclutamiento), mismo estilo que el resto.
4. **CSS** (`resources/css/app.css`, Tailwind 4 + Vite): 6 clases `weather-*` nuevas con
   los colores del banner legacy. `public/css/app.css` NO se carga (el layout usa solo
   Vite); se revirtió el cambio legacy y se añadió al CSS real.
   ⚠️ Requiere `npm run build` o `npm run dev` para regenerar el manifest/estilos.
5. `resources/views/combate_page.blade.php`: verificado correcto (extiende `layouts.app`
   + `@livewire('combate')`); sin cambios.

### Observación (documentada, NO modificada)

- `$weather` se asigna en `Combate::syncViewData()` (línea 628) desde
  `$battle->weather()->value` → llega en español. El partial `battle-field` recibe
  `$weather` vía `@include` desde `combate.blade.php` (las propiedades Livewire están
  disponibles en los partials). Si un estado concreto no entregara clima, NO se toca
  (dominio).

### Nota build

- Tras tocar `resources/css/app.css` hay que ejecutar `npm run build` (o `npm run dev`).
