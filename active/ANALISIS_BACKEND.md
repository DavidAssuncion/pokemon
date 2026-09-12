# ANALISIS_BACKEND — LOTE: Adaptar tests obsoletos de `tests/Unit/Battle/**` a la API nueva (F1–F9)

## Objetivo

Adaptar los tests obsoletos del motor de batalla a la API nueva sin cambiar producción:
`tests/Unit/Battle/**` + `CombateLivewireTest`/`PokemonBattleTest` (fallan por equipos de 5).
La producción es correcta; los tests usan APIs eliminadas (fromPosition, arrays de stats,
`puedeActuar(): array`, `log(): array`, `EtapasStats`, `assertCount(3)`, etc.).

## Archivos afectados

Tests (única capa permitida; producción NO se toca salvo bug real):

- tests/Unit/Battle/AgregadoBatallaTest.php (si falla)
- tests/Unit/Battle/AI/Fase2Test.php
- tests/Unit/Battle/AI/Fase3Test.php (si falla)
- tests/Unit/Battle/AI/SelectorAccionIATest.php
- tests/Unit/Battle/CadenaDanioTest.php
- tests/Unit/Battle/CalculadorDañoClimaTest.php (si falla)
- tests/Unit/Battle/CombatienteAvanzadoTest.php
- tests/Unit/Battle/CombatienteRecibirDanoTest.php (si falla)
- tests/Unit/Battle/EfectoOrbeVidaTest.php
- tests/Unit/Battle/EfectoRestosTest.php
- tests/Unit/Battle/EquipoBatallaTest.php (si falla)
- tests/Unit/Battle/EstadoPokemonTest.php
- tests/Unit/Battle/EtapasStatsTest.php (ya reescrito → verificar)
- tests/Unit/Battle/FabricaBatallaMockTest.php (syntax error + contrato desactualizado)
- tests/Unit/Battle/GestorTurnosTest.php
- tests/Unit/Battle/ManejadorBonificadorDanioTest.php
- tests/Unit/Battle/ManejadorClimaTest.php
- tests/Unit/Battle/ManejadorObjetosEquipadosTest.php
- tests/Unit/Battle/ManejadorPosicionTest.php
- tests/Unit/Battle/ServicioEjecucionBatallaTest.php
- tests/Feature/CombateLivewireTest.php
- tests/Feature/PokemonBattleTest.php

## Tests

Comportamientos cubiertos (tipo: Unit salvo los 2 Feature):

- AccionBatalla sin `fromPosition` (named arg eliminado).
- MovimientoBatalla con `selfStatChanges`/`targetStatChanges` como `CambiosStatsCollection`.
- `StatClave` enum en `obtenerStatEfectivo`/`CambioStat`.
- `Combatiente::puedeActuar(): ResultadoAccion` (getters esPermitida/motivo/autoDanio).
- BattleLog con `entries()`/`tieneContenido()`/`ultimas()`.
- GestorTurnos `allCombatants(): CombatientesCollection` (`->all()[0]` / `->primero()`).
- FabricaBatallaMock ampliada (5 por lado, tipos Giratina [DRAGON, FANTASMA], powermoves,
  selfStatChanges colección, Deoxys sin effectKeys, Mewtwo Paz Mental index 3).
- Equipos de 5 en `CombateLivewireTest`/`PokemonBattleTest` (assertCount 3 → 5).
- Defensa Férrea Aggron: factor 2.0 (DEFENSA +2 etapas).

## Diseño

Sin abstracciones nuevas. Solo uso de la API existente:
`StatClave`, `CambioStat::desdeEtapas`, `CambiosStatsCollection`, `ResultadoAccion`,
`BattleLog`, `CombatientesCollection`, `MultiplicadoresStats::factorDesdeStages`,
`Bando` para `RespuestaRival::generarRespuestas`.

## Riesgos

- Los tests pueden tener doble estado (snapshot de resultados obsoleto vs disco a medio migrar):
  validar SIEMPRE contra la ejecución real, no contra `resultado-tests.txt`.
- No tocar producción: si aparece un fallo cuyo origen es producción y no es evidente/seguro,
  documentarlo y seguir con la adaptación.
- `FabricaBatallaMockTest` tiene un bloque copy/paste roto (línea 282-294) que impide cargar la
  suite; corregirlo primero.
- No borrar el archivo `EtapasStatsTest.php` (ya reescrito hacia `MultiplicadoresStats`).

---

# ANALISIS_BACKEND — LOTE: Tests de EXPLORACIONES vs API nueva (colecciones/VOs) + 1 bug de producción

## Objetivo

Adaptar los tests de Exploraciones que aún tratan los resultados como arrays/APIs eliminadas a la
API nueva de VOs/colecciones (`src/Exploraciones/Domain/ValueObjects/`, `FabricaCapacidadesStats`,
dominio puro). Además, investigar y resolver el fallo de idempotencia
`ticks repetidos no duplican encuentros` (POSIBLE regresión de producción), que se reprodujo
aislado: 13 vs 10 eventos en la segunda pasada.

## Archivos afectados

Tests (capa principal):

- `tests/Unit/Exploraciones/CombateExploracionTest.php` — 6 asserts tratan `ResultadoBatallaExploracion` como array.
- `tests/Unit/Exploraciones/CalculadorTiemposTest.php` — apunta a clase eliminada (`CalculadorTiempos`).
- `tests/Feature/Exploraciones/CapacidadesStatsFactoryTest.php` — llama al método eliminado `CapacidadesStats::desdeReclutado`.
- `tests/Feature/Exploraciones/ProcesarExploracionCombateTest.php:270` — mock de `combatir()` devuelve array, la firma declara VO.

Producción (ÚNICO bug real confirmado):
- `src/Exploraciones/App/ProcesarExploracionHandler.php` — `numEncuentros()` (fix de idempotencia) y call-site de `inicioVuelta` (refactor a helper).
- `src/Exploraciones/Presentation/PresentadorExploraciones.php` — call-site de `inicioVuelta` (refactor a helper).

Nuevo:
- `src/Exploraciones/Domain/CalculadorVueltaExploracion.php` — helper de dominio puro (sustituye a `CalculadorTiempos`, eliminado).

## Tests

Comportamientos cubiertos (tipo: Unit/Feature conservando los escenarios originales):

- `ResultadoBatallaExploracion` con propiedades tipadas: `victoria`, `hpFinal`, `barreraFisicaFinal`,
  `barreraEspecialFinal`, `log->entries()` / `log->tieneContenido()`.
- Mock de `combatir()` devolviendo el VO con `BattleLog`.
- `FabricaCapacidadesStats::desdeReclutado` produce los mismos niveles/stats (50/20, 100/80/70/90/60/50).
- `CalculadorVueltaExploracion::inicioVuelta` (fórmula fin − duración/4; null si fin null; duración 0 → mismo momento)
  y `::progreso` (0/50/100, clamp, duración 0 → 100). Se conservan TODOS los escenarios de `CalculadorTiemposTest`.
- Idempotencia: dos `artisan('exploraciones:procesar')` seguidos NO duplican eventos.

## Diseño

- `CalculadorVueltaExploracion` (Domain puro, estático, sin estado): centraliza la fórmula de
  `inicioVuelta` replicada en `PresentadorExploraciones::inicioVuelta` y `ProcesarExploracionHandler`
  (líneas 83-85) + la semántica de `progreso` de tiempo del `CalculadorTiempos` eliminado.
- Ninguna abstracción nueva más allá del helper pedido por el Analista.

## Riesgos

- **Bug de idempotencia (CONFIRMADO, regresión F1-F9)**: `ProcesarExploracionHandler::numEncuentros()`
  suma `bonusEventosExploracion($dificultad)` INCONDICIONALMENTE. Con una ventana de 0 minutos
  (dos ejecuciones de cron consecutivas, `intdiv(0, X) = 0`) el bonus (4 para un explorador MAESTRO)
  genera eventos en una ventana de sub-segundo → cada pasada duplica eventos (~+4). El código OLD
  (`intdiv(minutos, 3)`) devolvía 0 → `$nuevos === []` → retorno temprano idempotente. NO es el bug
  que hipotetizaba el brief (`ultimo_procesado` SÍ se escribe en las 3 ramas de `procesarTick`,
  incluida la principal, línea 223 con `+ $perdidoTick`).
  Fix mínimo: guard `if ($minutos <= 0) return 0;` en `numEncuentros` (restaura la idempotencia para
  ventanas de 0 minutos y conserva el bonus "por tick" para ventanas reales con `minutos > 0`).
- No tocar la vista/progreso inline de `toActiva` (usa `diffInSeconds`, semántica más fina que el
  helper en minutos; fuera del alcance del brief).
- Los tests se validan contra ejecución real, no contra `resultado-tests.txt` (estado previo).

## Resultados (cierre del lote)

- **Bug de producción CONFIRMADO y FIX aplicado**: `ProcesarExploracionHandler::numEncuentros()`
  (guard `if ($minutos <= 0) return 0;`, línea 415). No es la hipótesis del brief (`ultimo_procesado`
  SÍ se escribe en las 3 ramas de `procesarTick`, incluida la principal línea 223 con
  `+ perdidoTick`); la causa raíz real es el bonus por rango (`bonusEventosExploracion`) aplicado a
  ventanas de 0 minutos entre dos ejecuciones de cron consecutivas. Test reproducido aislado antes
  (13 vs 10) y determinista después (3/3 pasadas aisladas + suite).
- **Helper nuevo**: `src/Exploraciones/Domain/CalculadorVueltaExploracion.php` con
  `inicioVuelta`/`progreso`. REFACTORIZADOS los 2 call-sites de `inicioVuelta`
  (`PresentadorExploraciones::inicioVuelta`, `ProcesarExploracionHandler` líneas 83-85).
  `progreso` queda disponible pero `toActiva` conserva su cálculo inline en segundos (no refactorizado).
- **Tests adaptados** (4 archivos): `CombateExploracionTest` (VO tipado), `ProcesarExploracionCombateTest`
  (mock → `ResultadoBatallaExploracion` + `BattleLog`), `CapacidadesStatsFactoryTest`
  (`FabricaCapacidadesStats::desdeReclutado` — mismos valores 50/20 y 100/80/70/90/60/50, la fábrica
  es la fuente de verdad y coincide con los asserts), `CalculadorTiemposTest` (apunta a
  `CalculadorVueltaExploracion`, todos los escenarios originales + 1 nuevo: fin null).
- Observaciones sin tocar: docblock de `numEncuentros` quedó obsoleto del refactor ("3 min" vs
  constante 15) — deuda del refactor F1-F9, fuera de alcance.

---

# ANALISIS_BACKEND — Adaptar tests obsoletos de GIMNASIOS + RECLUTAMIENTO + EQUIPOS a la API actual (VOs/colecciones/sesión/catálogo)

## Objetivo

Adaptar los tests obsoletos de los módulos GIMNASIOS, RECLUTAMIENTO y EQUIPOS para que reflejen
la API actual de producción: VOs tipados que sustituyeron arrays, colecciones tipadas, SESSION_VERSION=9,
catálogo de gimnasios corregido y rol individual del reclutado (`reclutados.behavior`). Cambios de
producción SOLO para regresiones claras (bug de datos del catálogo dark etapa 3).

## Archivos afectados

### Producción (1 bug de datos)
- `src/Gimnasios/Domain/CatalogoGimnasios.php` — etapa 3 de dark corrompida por `3941b5d`
  (`[215,215,215,215,215]`/`[461]` → debe ser `[560]`/`[461,861,461]`, mismo patrón 3 miembros del resto).

### Tests (adaptación)
- `tests/Unit/Gimnasios/EvsRangoEntrenadorTest.php` — pasar `DatosStats` tipado (no array) a `distribuir()`.
- `tests/Unit/Gimnasios/CatalogoGimnasiosTest.php` — sin cambios (es el test que exige el fix de datos).
- `tests/Feature/Gimnasios/GimnasioAdminViewTest.php` — endpoints reales `/api/admin/gyms`, campos
  `vanguardia`/`retaguardia`, método `PUT` (no `PATCH`).
- `tests/Feature/Gimnasios/GimnasioCombateTest.php` — `ResultadoGimnasio` es VO readonly (`->avance`...).
- `tests/Feature/Gimnasios/GimnasioLivewireTest.php` y `tests/Feature/Mazmorras/MazmorraLivewireTest.php`
  — `SESSION_VERSION = 8` → `9`.
- `tests/Feature/Gimnasios/MultiplicadorRecompensasTest.php` — `otorgar()` devuelve `?DatosModalVictoria`.
- `tests/Feature/ReclutadoEvolucionTest.php` — `StringCollection` / `RequisitoEvolucionCollection` tipadas.
- `tests/Feature/ReclutadoOpcionesEvolucionTest.php` — `OpcionEvolucionCollection` (vía `->toArray()` frontera).
- `tests/Feature/InventarioTest.php` — acceso tipado a `RequisitoEvolucionCollection`.
- `tests/Feature/EquiposControllerTest.php` — crear `Reclutado` con `behavior` (la sinergia usa `reclutado->rol()`).

## Tests

- Unit: `EvsRangoEntrenadorTest` (5), `CatalogoGimnasiosTest` (1 regresión de datos).
- Feature: `GimnasioCombateTest` (2), `GimnasioAdminViewTest` (3), `GimnasioLivewireTest` (4),
  `MazmorraLivewireTest` (4), `MultiplicadorRecompensasTest` (1), `ReclutadoEvolucionTest` (3),
  `ReclutadoOpcionesEvolucionTest` (1), `InventarioTest` (1), `EquiposControllerTest` (2).

## Diseño

Sin abstracciones nuevas. Uso de las APIs existentes:
- `DatosStats(hp:, atk:, def:, spAtk:, spDef:, speed:)` para `EvsRangoEntrenador::distribuir`.
- `ResultadoGimnasio->avance/completado/medalla`; `DatosModalVictoria->expTotal/expMiembro`.
- `StringCollection->toList()`, `RequisitoEvolucionCollection->first()->{tipo,slug,necesario,actual,caramelosDisponibles}`.
- `OpcionEvolucionCollection->toArray()` (frontera) en `ReclutadoOpcionesEvolucionTest`.
- Sinergia: `RolExploracion` vive en `reclutados.behavior`; los tests crean reclutados con el rol
  correcto (VANGUARDIA/COMBATIENTE/RECOLECTOR/RASTREADOR) para obtener CRV=expedicion_equilibrada,
  CTV=caceria, VVV=exploracion_agresiva.

## Riesgos

- `session` v8: la compuerta de `BattleSessionService::cargar` descarta payloads v8 (versión < 9) →
  los Livewire tests con `SESSION_VERSION=8` no cargan la batalla y quedan en fase inicial; solo se
  ajusta la constante del test (la carga acepta payloads v8 vía `MultiplicadoresStats::desdeEtapas`,
  por eso el guard compara versión y no rompe).
- TeamController legacy `updateMemberRole` sigue escribiendo SOLO `team_members.behavior` (no sincroniza
  `reclutados.behavior`): fuera de alcance (el frontend usa `/api/reclutado/{id}/rol` que sí sincroniza).
- El `first()` de `Collection` devuelve `?object`; se sigue el patrón ya existente en tests
  (`->first()->propiedad`), sin cambios de dominio.

---

# ANALISIS_BACKEND — LOTE: SyncCandyRegionales + FaseEvolutiva (clases borradas) + bug de orden en FamiliesTest

## Objetivo

Cubrir los 3 fallos pendientes del lote: (1) `tests/Unit/SyncCandyRegionalesTest.php` apunta a un
comando eliminado, (2) `tests/Unit/Shared/FaseEvolutivaTest.php` apunta a una clase eliminada
(reemplazo real: `ResolvedorCadenasEvolutivas`), (3) `tests/Feature/Habitats/FamiliesTest.php:790`
(regresión de PRODUCCIÓN del refactor F1-F9 en el orden de familias sin hábitat).

## Archivos afectados

### Tests
- `tests/Unit/SyncCandyRegionalesTest.php` — skip con decisión pendiente (sin reemplazo).
- `tests/Unit/Shared/FaseEvolutivaTest.php` — reescrito contra `ResolvedorCadenasEvolutivas::getFamilyMembersByChain`.

### Producción (fix de regresión real, no cubierto por lotes anteriores)
- `src/Habitats/Presentation/DTOPokemonFamilia.php` — añadir `$id` (id de pokémon, frontera API)
  separado de `$speciesId` (id de especie, regla de negocio "primer integrante = menor species_id").
- `src/Habitats/Infra/HabitatRepository.php` — builders `buildAvailableFamilyFromChain` /
  `buildUnassignedFamilyFromChain`: pasar `id: $member['id']` y `speciesId: $member['species_id']`.
- `src/Habitats/Presentation/ColeccionPokemonFamilia.php` — `porId()` debe buscar por `->id` (pokémon), no por `->speciesId`.

## Tests

- Unit `SyncCandyRegionalesTest` (7 escenarios) → SKIPPED (clase eliminada; asserts preservados).
- Unit `FaseEvolutivaTest` (4 escenarios) → `getFamilyMembersByChain` con fixtures pequeñas:
  (a) cadena simple de 2, (b) ramificada de 3, (c) fase 1/2/3 por miembro, (d) especie sin fila de
  evolución → estructura `{id,name,icon,stage,species_id}` con stage fallback 3.
- Feature `FamiliesTest::test_familias_sin_asignar_se_ordenan_por_species_id_minimo_de_la_cadena`
  (ya en verde) confirma el fix de producción.

## Diseño

- **SyncCandyRegionales**: grep `candy_regionales|candyRegionales|SyncCandy` en `app src routes` →
  SOLO el propio test. No existe `RegionalesCandyService` ni comando sustituto. Decisión (opción b del
  brief): `markTestSkipped('SyncCandyRegionales eliminado en el refactor (F*-series). Pendiente
  decisión: recrear comando o eliminar test.')` en `setUp()`. Los 7 tests y sus asserts quedan
  íntegros en el archivo. **PENDIENTE para el analista**: recrear el comando (mapeo variantes
  regionales + copia de WebP) o eliminar el test.
- **FaseEvolutiva**: la fase evolutiva ya no es un cálculo de dominio standalone; vive como `stage`
  (BFS) en `ResolvedorCadenasEvolutivas::getFamilyMembersByChain`. Se reescribe el test a la API
  nueva (mismo directorio `tests/Unit/Shared`), con `#[Test]` y fixtures charmander/eevee/rattata.
- **FamiliesTest — causa raíz (regresión del refactor F1-F9)**: el refactor extrajo
  `ResolvedorCadenasEvolutivas` y convirtió base/evoluciones de arrays a `DTOPokemonFamilia`, pero
  el builder pasó `$member['id']` a la propiedad `speciesId`, conflactando id de pokémon con id de
  especie. `minSpeciesId()` — usado por `ordenadasPorMinSpeciesId()` en `getUnassignedFamilies()` y
  `getFamiliesByHabitat()` — calculaba el mínimo del id de POKÉMON en vez del species_id: con un
  pokémon id=300/species_id=1 (fixture de test) la familia salía DESPUÉS de la de species 10
  (`assertLessThan(1, 4)`). Fix mínimo: `DTOPokemonFamilia` distingue `id` (pokémon, frontera: campo
  `id` de la API e icono) de `speciesId` (especie, regla de negocio). `toArray()` sigue emitiendo el
  id de pokémon → contrato de la API intacto (los tests de iconos y de base 113/242/440 lo prueban).
- Nota: `HabitatRepository::getFamiliesByHabitat()`/`getUnassignedFamilies()` YA llaman
  `ordenadasPorMinSpeciesId()` (working tree); el controller `unassignedFamilies()` NO necesita
  reordenar. El bug estaba en el dato que alimentaba el orden, no en el call-site.

## Riesgos

- La invariante real del seeder es `id == species_id` (normales y regionales con species propio),
  por lo que el fix no cambia datos reales; solo corrige el caso artificial del test donde difieren.
- `Compartes` ajenos del refactor (149 archivos dirty) NO se tocan; solo las 3 clases nombradas.
- No validar contra `resultado-tests.txt` (estado previo a LOTE 1-3); el único fallo comprobable de
  este lote es `FamiliesTest:790` (reproducido por el análisis del flujo, confirmado en el run final).