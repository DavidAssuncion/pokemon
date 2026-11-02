# TASK-003 — Análisis

> Informes de los especialistas `analyst`, guardados sin alterar sus conclusiones. El orchestrator
> no añade análisis propio.

---

# A. Flujo funcional y bug individual/equipo — `analyst` A

## 1. Mapa end-to-end

**Entradas de UI (dos, ambas individuales):**
- `resources/views/habitats/show.blade.php:2025` — panel "Tus favoritos para este hábitat" (`:259-324`), modal `:486` «¿Quieres enviar a explorar a X?», POST a `/api/exploraciones/store-individual` con `reclutado_id`, `level`, `habitat_id`, `duracion_horas`/`return_time` (`:2010-2033`).
- `resources/views/equipos/index.blade.php:3288` — modal por Pokémon (`:641`), mismo endpoint y mismo body (`:3278-3296`).
- Preview: ambos llaman `GET /exploraciones/preview` (`habitats/show.blade.php:1945`, `equipos/index.blade.php:3250`) con `reclutado_id`, `habitat_id`, `level`.

**Ruta → controlador → validación → creación**
- `routes/exploraciones.php:16` → `ExploracionActivaController::storeIndividual` (`app/Http/Controllers/ExploracionActivaController.php:229-237`).
- Validación en `validarYNuevaExploracion` `:246-294`: `reclutado_id` con `Rule::exists('reclutados','id')->where('user_id', Auth::id())` (`:249`), disponibilidad (`:258`), nivel mínimo (`:264-270`), duración (`:272-278`), creación (`:280-291`).
- Se crea con `inicio_exploracion=null`, `llegada_destino=null`, `regreso=null` (`:288-290`). **Nadie escribe `inicio_exploracion` ni `llegada_destino` en producción** (`grep` solo devuelve creación y modelo; `src/Exploraciones/App/ProcesarExploracionHandler.php:594` y `FinalizarExploracionHandler.php:393` los *leen* con fallback a `created_at`).

**Tick programado**
- `routes/console.php:13`: `Schedule::command('exploraciones:procesar')->everyFiveMinutes()`; **sin `withoutOverlapping()`**.
- `app/Console/Commands/ProcesarExploraciones.php:32-42`: `withoutUserScope()->whereNull('regreso')` con eager load `reclutado.pokemon.stats|types`, `habitat`; despacha un `ProcesarExploracionCommand` por exploración.

**Procesar (`src/Exploraciones/App/ProcesarExploracionHandler.php`)**
- Guardas: sale si `regreso != null` (`:72`), sin reclutado/user (`:76`).
- `inicio` = `inicio_exploracion ?? created_at ?? now()` (`:592-603`).
- `fin` = `CalculadorFinExploracion::calcular(hora_limite, duracion_horas, inicio)` (`:81`); `inicioVuelta` = `fin − duración/4` (`:82`; `CalculadorVueltaExploracion.php:23-32`).
- `desde` = `eventos.ultimo_procesado ?? inicio` (`:86`); `hasta` = `min(now, fin, inicioVuelta)` (`:87`; `limiteTick` `:605-621`).
- Escrituras en `eventos` (JSON): `bitacora` (`:207-208`, `:581-587`), `tiempo_perdido` (`:209`), `explorador` (`:210`), `ultimo_procesado` (`:129`, `:149`, `:220`), `retirada`/`derrota` (`:100-103`, `:212-217`); persiste con `save()` (`:222`).
- Si completada: añade `derrota`/`retirada`, `save()`, despacha `FinalizarExploracionCommand` (`:99-108`).

**Finalizar (`src/Exploraciones/App/FinalizarExploracionHandler.php`)**
- `refresh()` y guarda de idempotencia por `regreso` (`:73-78`).
- Recompensas: `repartirRecompensas` (`:99`) → `CalculadorRecompensas::calcular` (`:148-153`), hallazgos (`:163-173`), pérdidas por derrota (`:177-184`), `PersistirRecompensas::persistir` (`:186`), avistados (`:187`), `registrarResultado` escribe `eventos.derrotados` y `eventos.resultado` (`:505-516`), y finalmente `update(['regreso'=>now(), 'eventos'=>$eventos])` (`:203`).

**Presentación/recogida**
- `GET /exploraciones` → `index()` (`ExploracionActivaController.php:43-57`) → `PresentadorExploraciones::toView` (`src/Exploraciones/Presentation/PresentadorExploraciones.php:118-147`).
- `recoger` → `ProcesarExploracionCommand(forzarRegreso:true)` (`:340-349`).
- `cancelar` escribe `eventos.cancelada` y `regreso=now()` (`:302-327`); `cerrar` borra el registro (`:354-365`).

**Quién escribe cada campo**
| Campo | Escritor |
|---|---|
| `inicio_exploracion` | **nadie** (solo null al crear) |
| `llegada_destino` | **nadie** (columna muerta) |
| `regreso` | `FinalizarExploracionHandler:203`, `cancelar:319` |
| `eventos` | `ProcesarExploracionHandler` (bitácora/tick), `FinalizarExploracionHandler` (resultado), `cancelar` |

## 2. ¿Dónde se elige y se envía? IDs que viajan

- Ambos formularios envían **solo `reclutado_id`** (+ `habitat_id`, `level`, duración). Ver `habitats/show.blade.php:2010-2020` y `equipos/index.blade.php:3278-3287`.
- `equipo_id`/`team_id` **no** se envían nunca a `/exploraciones` ni a `/api/exploraciones/*`. Los `team_id` que aparecen en `habitats/show.blade.php:1433,1464,1594` van a endpoints de **combate de entrenadores/ruta/mazmorra**, no de exploración.
- `POST /exploraciones` (`store`, `ExploracionActivaController.php:210-222`) existe pero **ninguna vista lo usa** (`grep` de `fetch('/exploraciones'` solo encuentra preview y store-individual). Es un endpoint duplicado/legado con la misma lógica individual.

## 3. Bug: discrepancia concreta

**No hay ningún envío real de equipos.** Hay tres residuos que producen la percepción de «se envían equipos»:

1. **Nomenclatura de presentación (alto impacto visual, bajo técnico).**
   - `PresentadorExploraciones.php:182` y `:260` publican la clave `'equipo' => $reclutado->nombre` (es un reclutado).
   - `resources/views/exploraciones/index.blade.php:10` «Gestión de expediciones de tus equipos»; `:33`/`:157` renderizan `$exp['equipo']`; `:131` «Envía a tu equipo desde un hábitat».
   - Tests que fijan ese contrato: `tests/Feature/ExploracionesPageTest.php:155,241`; `tests/Feature/ExploracionesViewTest.php:84,137`.

2. **Bloqueo de equipo completo por un solo miembro (comportamiento funcional real).**
   - `PlayerController.php:142-152`: `$reclutadosEnExploracion` → `$equiposEnExploracion` vía `TeamMember::whereIn('pokemon_id', …)->pluck('team_id')`.
   - `HabitatsController.php:57-69`: mismo cálculo; `habitats/show.blade.php:185-219` marca el **equipo entero** «En exploración (hábitat)» y lo bloquea.
   - `equipos/index.blade.php:70-91,1397-1401` muestra badge «En exploración» a nivel de equipo.
   - `Team::isExploring()` (`app/Models/Team.php:46-53`) bloquea renombrar/editar/borrar/formación/posturas/ítems/roles del equipo completo (`src/Equipos/App/*`; `TeamController.php:236`).
   - Contradicción: el backend **permite** enviar a otro miembro del mismo equipo (`tests/Feature/ExploracionesIndividualEndpointsTest.php:315`), pero la UI bloquea el equipo.

3. **Reparto de EXP como si fueran 3 miembros (defecto de dominio).**
   - `CalculadorRecompensas.php:36` `MIEMBROS_EQUIPO = ReglasEquipo::MIEMBROS` (3); `:337` y `:361` dividen `exp × 0.8 / 3`.
   - `PersistirRecompensas.php:141` aplica `expPorMiembro` a **un único reclutado** (rama `Reclutado`), por lo que el explorador individual recibe ~26,7 % de la EXP que debería.
   - `ReglasExploracion.php:12` («reparte el 80 % entre los 3 miembros») y `FinalizarExploracionHandler.php:353-366` (comentario «multiplicadorCaramelosEquipo») mantienen el marco mental de equipo.

**No hay combate 3v3 en exploración:** `CombateExploracion.php:95-96` construye dos `EquipoBatalla` de **un solo** `DatosPokemonBatalla` (1v1). La emboscada sí encadena 2-3 salvajes 1v1 secuenciales (`ProcesarExploracionHandler.php:347-374`), pero es contra un solo explorador.

**Veredicto: causa raíz compuesta, no un único bug.** Confianza alta (0,9) en que:
- el flujo de datos es 100 % individual;
- el síntoma se explica por (2) bloqueo/etiquetado de equipo completo + (1) nomenclatura «equipo», agravado por (3) EXP dividida entre 3 en recompensas.

## 4. Camino real vs legado

| Elemento | Estado |
|---|---|
| `GET /exploraciones`, `preview`, `store-individual`, `recoger`, `cancelar`, `cerrar` | **Producción** |
| `POST /exploraciones` (`store`) | **Legado/no usado** (duplica lógica individual) |
| `CalculadorRiesgo`, `CalculadorMatchups`, `CalculadorCapacidadEquipo` | **Solo tests** (grep: sin uso en `app/` ni `src/` salvo entre ellos) |
| `SinergiaEquipo` | **Producción pero fuera de exploración**: `PlayerController.php:101` para mostrar sinergia en `/equipos` |
| `MIEMBROS_EQUIPO` / `expPorMiembro` | **Producción** (afecta a exploración individual) |
| `equipoDisponible` / `equipoDisponibleParaCombate` en `ValidadorExploracion` | Definidos (`:20-51`), **sin llamadas** en app/src (grep) |
| `llegada_destino` | Columna y cast vivos, **sin escritor** |
| `Team::isExploring` + bloqueo UI | Producción, semántica de equipo |

## 5. Modelo de datos

Columnas efectivas de `exploraciones_activas` (según migraciones): `id`, `user_id` (FK users, NOT NULL, índice), `reclutado_id` (FK reclutados, NOT NULL, índice), `habitat_id` (FK habitats, índice), `nivel` tinyint, `duracion_horas` int nullable, `hora_limite` time nullable, `indefinido` bool default false, `eventos` jsonb nullable, `inicio_exploracion` timestamp nullable, `llegada_destino` timestamp nullable, `regreso` timestamp nullable, `created_at`, `updated_at`.
- Origen: `2026_05_27_000004_create...:15-25`, `2026_08_26_000004_add_habitat...:15-20`, `2026_08_29_000006_add_user_id...:17-32`, `2026_09_02_000003_change_equipo...:17-48`.
- **No queda `equipo_id`**: se elimina en `2026_09_02_000003:44-48`. La migración borra filas sin backfill (`:34-35`) — posible pérdida histórica ya consumada, no huérfanos actuales.
- No hay constraint de unicidad parcial que impida dos exploraciones activas del mismo `reclutado_id`; la garantía es solo la comprobación en aplicación (`ValidadorExploracion.php:29-33`), sujeta a carrera.
- **DUDA**: no verifiqué datos huérfanos en la BD real (prohibido consultar). Por esquema, FK cascade evita huérfanos de `reclutado_id`/`habitat_id`/`user_id`.

## 6. Estructuras de equipo vivas

| Estructura | Ubicación | Uso |
|---|---|---|
| `CalculadorCapacidadEquipo` | `src/Exploraciones/Domain/CalculadorCapacidadEquipo.php:20` | Solo `CalculadorRiesgo` + tests |
| `SinergiaEquipo` | `Domain/SinergiaEquipo.php:22` | `PlayerController.php:101` (vista `/equipos`) |
| `CalculadorRiesgo` / `CalculadorMatchups` | `Domain/CalculadorRiesgo.php:40-53`, `CalculadorMatchups` | Solo tests |
| `MIEMBROS_EQUIPO = 3` | `CalculadorRecompensas.php:36,337,361` | **Producción** |
| `FRACCION_EXP_REPARTO = 0.8` | `ReglasExploracion.php:20` | Producción |
| `EquipoBatalla` en `CombateExploracion` | `App/CombateExploracion.php:95-96` | Producción, pero 1v1 |
| `Team::isExploring` / `equiposEnExploracion` | `Team.php:46-53`, `PlayerController.php:146-152`, `HabitatsController.php:61-69` | Producción, bloqueo de equipo |
| `team_members` consultado para exploración | `HabitatsController.php:62`, `PlayerController.php:147` | Producción (acoplamiento) |
| `RolExploracion` con comentario `team_members.behavior` | `Domain/RolExploracion.php:10` | Doc obsoleta: el rol real es `reclutados.behavior` (`Reclutado.php:39-47`) |

## 7. Progresión temporal y eventos

- **Generación**: `SimuladorEncuentros::generarEventosDesdePool` (`SimuladorEncuentros.php:129-172`), `numEncuentros` = `intdiv(minutos, intervaloEfectivo) + bonus` (`ProcesarExploracionHandler.php:411-420`), intervalo base `ReglasExploracion::MINUTOS_POR_ENCUENTRO = 30` (`ReglasExploracion.php:16`). Eventos con timestamps repartidos en slots dentro de `[desde,hasta]` (`SimuladorEncuentros.php:150-158`).
- **Consumo/persistencia**: se resuelven en el tick y se anexan a `eventos.bitacora` (`ProcesarExploracionHandler.php:207-208`); estado `eventos.explorador` (`:210`); `eventos.resultado` al finalizar (`FinalizarExploracionHandler.php:506-514`).
- **Offline**: al no haber worker en tiempo real, el siguiente tick procesa todo el intervalo `[ultimo_procesado, min(now, fin, inicioVuelta)]` de golpe (`:86-87`). Los combates se resuelven secuencialmente en ese instante, no en tiempo real.
- **Idempotencia**: *blanda* vía `ultimo_procesado` (`:219-220`) y guarda `regreso` en finalizar (`FinalizarExploracionHandler.php:73-78`). **No es estrictamente idempotente**:
  - no hay lock/transacción al leer `ultimo_procesado`; dos ticks concurrentes (scheduler + `recoger`) pueden procesar el mismo tramo;
  - `hasta <= desde` reescribe `ultimo_procesado = hasta` (`:128-134`), lo que puede **retroceder** el cursor si `perdidoTick` lo había adelantado (`:220`);
  - el scheduler no usa `withoutOverlapping()` (`routes/console.php:13`).
- **Reloj/fechas**: `Carbon::today()->setTimeFromTimeString(hora_limite)` (`CalculadorFinExploracion.php:23-25`). Riesgo real: `return_time` en el pasado o cruce de medianoche (23:00 → 01:00) produce `fin` en el pasado → la exploración finaliza en el siguiente tick. `hora_limite` es `time` sin fecha.
- **Nº de eventos**: proporcional a minutos/30, más bonus de capacidades; probabilidades 45/20/15/10/10 (`SimuladorEncuentros.php:33-42`).
- **Doc desincronizada**: `src/Exploraciones/context.md:36,39` dice 15 min y 3 %/min; el código dice 30 min y 1 %/min (`ReglasExploracion.php:16-18`).

## 8. Tests actuales y test faltante

**Cubren:** `tests/Feature/ExploracionesTest.php` (tick, finalización, rollback, idempotencia de reintento `:729`, resultados, emboscadas), `ExploracionesPageTest.php`, `ExploracionesViewTest.php`, `ExploracionesIndividualEndpointsTest.php` (store-individual `:242`, rechazo de ajeno `:292`, permite otro miembro del equipo `:315`), `MigracionExploracionIndividualTest.php` (esquema `:27-34`, backfill `:71`), `tests/Unit/Exploraciones/*`.

**Codifican el bug:** `ExploracionesTest.php:446-447` afirma `floor(exp*0.8/3)` para **un** reclutado; `:310-311` y `:643` igual. Es decir, el test actual *protege* la división entre 3.

**Tests que faltan:**
1. Individual: la EXP del reclutado debe ser el 100 % de la EXP de derrotas (sin `/3`).
2. Regresión de etiquetado: la vista de exploraciones no debe decir «equipos» ni exponer `equipo` cuando el sujeto es un reclutado.
3. Que un miembro en exploración no marque el equipo completo como «En exploración» (o que se documente como decisión).
4. Cobertura `POST /exploraciones` legado: decidir eliminarlo o testear equivalencia.
5. `inicio_exploracion`/`llegada_destino`: test de que el estado «En curso» se alcanza (hoy imposible).
6. `return_time` nocturno/pasado: comportamiento esperado del `fin`.
7. Concurrencia: dos `ProcesarExploracionCommand` sobre la misma exploración no duplican bitácora.

## 9. Riesgos y puntos de corte para refactor

- **God-handler**: `ProcesarExploracionHandler` ~700 líneas (`context.md:101-103`); mezcla generación, combate, descanso, captura y bitácora.
- **Preview duplicado**: `ExploracionActivaController::preview` calcula riesgo simple (`:84-91`) mientras `CalculadorRiesgo` mantiene otro contrato (solo tests). Dos verdades de riesgo.
- **Arrays de presentación**: `PresentadorExploraciones::toView` devuelve arrays anidados (`:118-147`) y `_evento.blade.php` invoca presentador desde Blade (`context.md:110-112`).
- **Acoplamiento a Eloquent**: `App/` handlers usan modelos directamente (`ProcesarExploracionHandler.php:43-45` lo justifica por convención). `FinalizarExploracionHandler` propaga `array<int, Collection<int,Pokemon>>` (`:123`).
- **Acoplamiento Exploraciones→Equipos**: `HabitatsController` y `PlayerController` consultan `team_members` para derivar bloqueos; cualquier refactor debe decidir si la exploración sigue bloqueando equipos.
- **Migración**: quitar el `/3` cambia economía; requiere decisión de producto y recalibración (afecta `expTotal`/`expPorMiembro` y tests).
- **Duplicidad de endpoints** `store` vs `storeIndividual`.

## Tabla de hallazgos

| # | Severidad | Evidencia | Impacto |
|---|---|---|---|
| 1 | Alta | `PresentadorExploraciones.php:182,260`; `exploraciones/index.blade.php:10,33,131,157` | La UI llama «equipo» a un reclutado; genera la percepción del síntoma |
| 2 | Alta | `PlayerController.php:142-152`; `HabitatsController.php:57-69`; `Team.php:46-53`; `equipos/index.blade.php:70-91` | Un Pokémon explorando bloquea y etiqueta el equipo entero «En exploración» |
| 3 | Alta | `CalculadorRecompensas.php:36,337,361`; `PersistirRecompensas.php:141` | Explorador individual recibe ~26,7 % de EXP (división entre 3) |
| 4 | Media | `ExploracionActivaController.php:288-290`; `ProcesarExploracionHandler.php:594`; `habitats/show.blade.php:165-168` | `inicio_exploracion` nunca se escribe → siempre «Preparando» |
| 5 | Media | `2026_05_27_000004:20`; sin escritores | `llegada_destino` es columna muerta |
| 6 | Media | `routes/console.php:13`; `ProcesarExploracionHandler.php:128-134,219-220` | Tick no idempotente bajo concurrencia; cursor puede retroceder |
| 7 | Media | `CalculadorFinExploracion.php:23-25` | `return_time` nocturno/pasado finaliza de inmediato |
| 8 | Media | `ValidadorExploracion.php:29-33`; sin índice único | Carrera check-then-act permite doble exploración del mismo reclutado |
| 9 | Baja | `ExploracionActivaController.php:210-222` | Endpoint `POST /exploraciones` duplicado y sin uso |
| 10 | Baja | `context.md:36,39` vs `ReglasExploracion.php:16-18` | Documentación desincronizada (15/3 vs 30/1) |
| 11 | Baja | `CalculadorRiesgo`/`CalculadorMatchups`/`CalculadorCapacidadEquipo` solo en tests | Código muerto de producción |
| 12 | Baja | `RolExploracion.php:10` | Comentario obsoleto (`team_members.behavior`) |

## Causa raíz del síntoma

**No se envían equipos.** La exploración es 100 % individual en datos (`reclutado_id`, 1v1, un solo destinatario de recompensas) y ambos formularios envían solo `reclutado_id`. El síntoma «la UI muestra Pokémon individuales, pero luego se envían equipos» se produce por la combinación de:

1. **Etiquetado de equipo** en la presentación (`clave 'equipo'`, textos «tus equipos», «Envía a tu equipo»), que hace que el registro individual se lea como equipo.
2. **Bloqueo y badge de equipo completo** cuando un solo miembro explora, derivado de consultar `team_members` por `pokemon_id`.
3. **Reparto de EXP entre 3** (`MIEMBROS_EQUIPO`) aplicado a un único explorador, que refuerza la sensación de que se ha enviado un equipo.

**Confianza: 0,9.** El elemento puramente cosmético es (1); el funcional verificable es (2); el defecto económico verificable es (3). No existe flujo de envío de equipo en rutas, controladores ni JS: descartado con evidencia.

## Riesgos del refactor

- **Producto/economía**: corregir `MIEMBROS_EQUIPO` → 1 (o tamaño real) multiplica la EXP del explorador por ~3,75; exige recalibrar y actualizar tests que hoy fijan el `/3`.
- **Contrato de vista**: renombrar `equipo` a `reclutado` rompe `ExploracionesPageTest`/`ExploracionesViewTest` y posibles consumidores; conviene clave aditiva y deprecación.
- **Semántica de bloqueo**: dejar de bloquear el equipo completo habilita modificar un equipo con un miembro fuera; hay que decidir si el bloqueo pasa a nivel de reclutado (afecta `Team::isExploring`, `src/Equipos/App/*`, `TeamController`).
- **Concurrencia**: introducir lock/transacción y `withoutOverlapping()` sin cambiar el contrato de `eventos` requiere migración cuidadosa del cursor `ultimo_procesado`.
- **Columnas muertas**: eliminar `llegada_destino` y decidir si `inicio_exploracion` se escribe al crear (cambia progreso y estado).
- **Alcance DDD**: el god-handler y el preview duplicado deben abordarse en incrementos separados para no mezclar economía, UX y persistencia.

## Suposiciones

- `EquiposVistaPresenter`/`PlayerController::equipos` entregan `equiposEnExploracion` como lista de `team_id` (visto `:146-152`; la vista lo consume en `equipos/index.blade.php:1397-1401`).
- No hay más entradas de exploración fuera de las dos vistas citadas (grep de `fetch('/exploraciones` y `/api/exploraciones`).
- No hay datos huérfanos actuales por FKs cascade; no verificado en BD.

## Preguntas bloqueantes

1. ¿El producto quiere que **un miembro** en exploración bloquee su equipo de combate, o el bloqueo debe ser solo del reclutado?
2. ¿La EXP individual debe ser el 100 % (o el 80 % sin división entre 3)? ¿Se recalibra la curva?
3. ¿Se elimina `POST /exploraciones` y se deja solo `store-individual`?
4. ¿Se corrige `return_time` nocturno con fecha completa o se restringe a duración en horas?
5. ¿`inicio_exploracion` debe escribirse al crear (para estado «En curso» y progreso correcto)?

**Estado**: análisis entregado con evidencia. No se modificó ningún archivo, no se ejecutaron migraciones ni tests. Quedan sin verificar por límite de pasos: lectura íntegra de `CalculadorRiesgo`/`CalculadorMatchups` y comprobación de datos reales en BD.

---

# B. Presentación, eventos y personalidad — `analyst` B

## 1. Inventario de la UI actual

**Framework:** Blade + Tailwind CSS 4 (`@tailwindcss/vite ^4.0.0`) + Alpine.js 3.16 (`package.json:10,14,19`). No hay Livewire ni polling. Toda actualización exige recarga completa (`location.reload()`, `resources/views/exploraciones/index.blade.php:377`).

**Estructura de `/exploraciones`** (`index.blade.php`):
- Header: título «Exploraciones» + subtítulo **«Gestión de expediciones de tus equipos»** (líneas 9-10) — nomenclatura de equipo con exploración individual.
- Sección «Activas» (14-135) y «Resultados por revisar» (138-336).
- Tarjeta activa: cabecera con `$exp['equipo']` (= nombre del reclutado), hábitat, badge «Nivel N», badge «Requiere Nv X» y badge de estado (33-53).
- Cuerpo: barra de progreso con porcentaje (57-67), línea «Inicio · Vuelta · Fin» (69-77), botones «Cancelar» (80-86) y «Recoger resultados» solo si `indefinido` (87-95), bitácora colapsable con contador (99-121).
- Estado vacío: «No hay exploraciones activas / Envía a tu equipo desde un hábitat» (125-133).

**Tarjeta terminada:** cabecera con nombre, hábitat, nivel, badge `+{{ exp }} EXP` y botón «Cerrar resultados» (155-185). Cuerpo: badge de categoría, línea de incidentes, línea «N min efectivos · N min perdidos» (187-210), alerta «Perdiste objetos» (212-232), grid con Derrotados / Capturados / Recompensas (234-325). Sin resultados → «Sin resultados» (327).

**Qué NO muestra:** retrato/icono del explorador, su nivel, EXP, HP actual, rol, rasgos, ánimo, fecha de inicio, duración restante en texto, ni historial. Tampoco refresca el progreso en vivo: `progreso` se calcula en servidor una sola vez al renderizar (`PresentadorExploraciones.php:164-169`).

**Accesibilidad:** botón de bitácora con `aria-expanded`/`aria-controls` (103-104) y `role="alert"` en pérdidas (213); imágenes con `alt` y `onerror`. Faltan: `role="progressbar"` con `aria-valuenow` en la barra (63-66), `aria-label` en «Cancelar», y los SVG decorativos del empty state sin `aria-hidden` (126-128). Los mensajes de error usan `alert()` nativo (373, 379).

**Responsive:** grid `lg:grid-cols-4` con spans calculados en `ResultadoExploracionVista::spans()` (112-138). Correcto en líneas generales.

**Bug visible de tiempos:** `fmtTime()` usa `toLocaleTimeString('es-ES', {hour,minute})` (`index.blade.php:344-349`), sin fecha. Con duraciones de hasta 72 h (`duracion_horas` 1-72, `ExploracionActivaController.php:71,252`) la UI muestra horas ambiguas o engañosas.

## 2. Pipeline de eventos a UI

**Dónde se generan** (`SimuladorEncuentros`): `encuentro` con subtree normal/grupo/excepcional (249-254), `emboscada` con 2–3 `pokemon_ids` (401-406), `hallazgo` con subtree `caramelo_familia|caramelo_ev|caramelo_tipo` (277-302), `contratiempo` con subtree de 4 valores (409-415), `neutral` (418-420). Probabilidades en `context.md`.

**Dónde se resuelven** (`ProcesarExploracionHandler`): los eventos se amplían con `resolucion`, `duration_loss`, `hp_final`, `log`… (237-270); el descanso se inyecta como `['tipo'=>'descanso','duracion_minutos'=>N,'hp_recuperado'=>N]` (581-588). En el cierre, `FinalizarExploracionHandler` escribe `derrotados`, `resultado` (505-520) y `objetos_perdidos` (180-185).

**Shapes persistidos** (`exploraciones_activas.eventos`, jsonb, cast `collection`, `ExploracionActiva.php:33-39`):
- `bitacora`: lista de eventos.
- `tiempo_perdido`, `ultimo_procesado`, `explorador` (`EstadoExplorador::aArray()` → hp/hp_max/barreras, `EstadoExplorador.php:56-66`).
- `derrotados: list<int>`, `resultado: array`, `objetos_perdidos`, `retirada|derrota` y `cancelada` como claves raíz (`ProcesarExploracionHandler.php:100-103,213-216`; `ExploracionActivaController.php:313-317`).

**Contrato de `resultado`** (`TransformadorResultadoExploracion.php:57-69`): `capturados[]`, `caramelos_familia[]`, `caramelos_ev[{stat,cantidad}]`, `caramelos_tipo[{tipo,slug,cantidad}]`, `exp`, `resultado` (categoría), `duration_real`, `tiempo_perdido`, `incidentes{encuentros,victorias,huidas,emboscadas,contratiempos}`.

**Transformación:** `PresentadorExploraciones::toView()` → `toActiva()`/`toTerminada()` (118-147,153-284). `toActiva` construye `_vista`, estado, progreso, bitácora transformada y datos de cabecera. `toTerminada` delega en `ResultadoExploracionVista::toView()` (258), que añade clases, spans, iconos y alias. `EventoExploracionVista::toView()` centraliza textos e iconos por tipo (31-114).

**Textos hardcodeados** (todos en `EventoExploracionVista.php`): «Encontraste un…» (79), «Un … salvaje huye…» (82), «El equipo repele el ataque» (122), «El equipo pierde el rastro / El terreno dificulta…» (149-155), «El equipo se retira» (89), «El equipo se encuentra con un grupo salvaje» (93), «Evento neutral» (100), «El equipo descansa» (`_evento.blade.php:232`). Nótese el sujeto colectivo «el equipo» pese a ser un solo reclutado.

**¿La vista invoca dominio?** No directamente; invoca **Presentation** desde Blade: `index.blade.php:26-27` y `150-151` llaman `app(PresentadorExploraciones::class)`. `_evento.blade.php:2` llama `EventoExploracionVista::toView()` (idempotente por `_vista`). Además `PresentadorExploraciones` **consulta Eloquent** (`Pokemon::whereIn`, línea 80), deuda ya reconocida en `context.md`.

**Eventos generados pero nunca renderizados en producción:** `retirada`/`derrota` se guardan como claves raíz, no en `bitacora`, y la vista solo itera `bitacora` (115) → la narrativa de retirada/derrota/cancelación es invisible; solo se percibe por la categoría final. Los tipos `pokemon` y `combate` existen en el presentador (78-80,102-106) pero el simulador ya no los emite (legado/tests).

**Bug de contrato:** el evento `descanso` guarda `duracion_minutos`, pero el presentador lee `duration_loss ?? duracion` (53) → **siempre muestra 0 min**, y `hp_recuperado` nunca se pinta.

**Bug de acceso previo (preview):** `EstimadorRecompensasExploracion::item()` recibe `label: null` para familia y EV (`estimarDePool`: `$this->item('familia', …, null)`, `$this->item('ev', …, null)`), y el Blade pinta `item.label` (`habitats/show.blade.php:549-554`) → filas con etiqueta vacía. El ítem `tipo` usa el nombre de tipo como label. Ningún test cubre esas etiquetas (`grep` en `ExploracionesPreviewTest.php` sin coincidencias).

## 3. Datos reales disponibles (sin inventar)

**`reclutados`** (migraciones 000001, 000003, 000005, 000009, 000010 + `Reclutado.php:22-36`): `nombre` (nullable), `pokemon_id`, `exp` json `{total,tipos}` (`ExpReclutado`), `es_shiny`, `obj_equipados` json, `movimientos` json, `behavior` (VANGUARDIA/COMBATIENTE/RECOLECTOR/RASTREADOR, `RolExploracion.php:18-21`), `ivs`/`evs`, `habilidad_activa_id`, `user_id`, timestamps.
- Nivel **no está persistido**: se deriva con `NivelHelper::nivelDesdeExperiencia()` (`NivelHelper.php:15-34`).
- No hay naturaleza, género, ánimo, heridas, nivel de amistad, contador de expediciones, ni biografía.

**`pokemon`** (`2026_02_24_171456` + `Pokemon.php`): `name`, `species_id`, `capture_rate`, `base_experience`, `height`, `weight`, `hatch`, `evolution_chain_id`; relaciones `stats` (effort), `types`, `moves`, `abilities`. No hay descripción ni flavor text.

**`habitats`** (`Habitat.php:19-32`): `name`, `province_id`, `pokemons` json, `peligro`, `mazmorra` json, `min_lvl_1..3`; `Province` solo tiene `name`. Imagen en `resources/habitats_img/{id}.webp` (uso en `habitats/index.blade.php:41`, no en la tarjeta de exploración).

**`teams`/`team_members`**: siguen existiendo (`Team.php`, `TeamMember`), con `behavior` y `isExploring()`; alimentan «En exploración» en `equipos/index.blade.php:70-102` y en `habitats/show.blade.php:185-218`. Es legado incoherente con la exploración individual.

**Recompensas:** `PlayerInventory` con `item_key` (`familia:{chain}`, `ev:{stat}`, `tipo:{slug}`, `ItemCatalogo.php:24-44`), `Reclutables` para capturas, `PlayerTypePath` para EXP de tipo.

**No disponible sin añadir persistencia:** historial de expediciones (al «Cerrar resultados» se hace `delete()`, `ExploracionActivaController.php:358`), hitos, relaciones entre reclutados, heridas permanentes.

## 4. Comparación honesta con Fallout Shelter

| Rasgo de Fallout Shelter | ¿Existe aquí? | Evidencia |
|---|---|---|
| Explorador individual enviado al yermo por tiempo | Sí | `reclutado_id`, `duracion_horas` 1-72 (`ExploracionActivaController.php:71,280-291`) |
| El tiempo transcurre y se generan eventos | Sí, por cron cada 5 min | `routes/console.php:13`; `SimuladorEncuentros` |
| Diario/log de eventos | Sí, básico | `bitacora` + `_evento.blade.php` |
| Recursos que se acumulan | Sí: caramelos familia/EV/tipo, capturas | `TransformadorResultadoExploracion.php:57-69` |
| XP | Sí: jugador 100 %, reclutado `expPorMiembro` | `PersistirRecompensas.php:119-148` |
| Encuentros/combates | Sí, 1v1 con motor real | `ProcesarExploracionHandler::resolverEncuentro` (280+) |
| Lesiones/HP y descanso | Sí en datos (`explorador`, eventos `descanso`), **no visible** | `EstadoExplorador`, `ProcesarExploracionHandler.php:581-588` |
| Regreso automático | Sí por cálculo de fin; sin UI de «está volviendo a casa» real | `CalculadorFinExploracion`, `CalculadorVueltaExploracion` |
| Botín con nombre propio / objetos únicos | **No**: solo caramelos y capturas | `ItemCatalogo` |
| Rasgos de personalidad del explorador | **No** | sin columnas |
| Decisiones/eventos con elección del jugador | **No**: todo es automático | pipeline sin input |
| Historial entre expediciones | **No**: se borra el registro | `cerrar()` |
| Diario narrativo con variación | **No**: textos fijos por tipo | `EventoExploracionVista` |

Conclusión: el **esqueleto funcional** de Fallout Shelter ya está (tiempo + eventos + recompensas + XP + combate). Lo que falta es casi todo **capa narrativa y de identidad del explorador**, más el «regreso a casa» como momento visible.

## 5. Fricciones de UX y producto

1. **Nomenclatura**: «equipos» en el subtítulo y en la UI de hábitat/equipos frente a exploración individual (`index.blade.php:10`; `equipos/index.blade.php:70`; `habitats/show.blade.php:185-218`).
2. **Sin feedback de envío**: `confirmExploration()` usa `alert()` y recarga completa; sin estado intermedio ni toast (`habitats/show.blade.php:1995-2045`).
3. **Progreso estático**: no se actualiza; el usuario debe recargar.
4. **Recogida asimétrica**: «Recoger resultados» solo para indefinidas; las temporizadas no se pueden recoger antes y dependen del cron (`index.blade.php:87-95`).
5. **Sin historial**: «Cerrar resultados» borra (`ExploracionActivaController.php:358`).
6. **Preview duplicado/engañoso**: dos fórmulas de riesgo (`preview` simple en controlador vs `CalculadorRiesgo`), etiquetas de recompensa vacías, y `riesgo === 'Extremo'` en JS (2001) que el backend nunca devuelve (solo Bajo/Medio/Alto, líneas 89-91).
7. **Textos genéricos**: «Evento neutral», «Hallazgo registrado», sujeto «el equipo» para un individuo.
8. **Tiempos sin fecha** y sin «faltan X h».
9. **Estados pobres**: solo «Explorando»/«Volviendo»; el estado «Preparando» existe en hábitat pero no en `/exploraciones`.
10. **Narrativa clave invisible**: retirada, derrota y cancelación no aparecen en la bitácora.

## 6. Oportunidades priorizadas (coste/impacto)

| # | Oportunidad | Impacto | Coste | Datos necesarios | Riesgos |
|---|---|---|---|---|---|
| 1 | Reescribir textos por rol y en singular («Rex encontró…») | Alto | Bajo | `behavior`, `nombre` | Cambiar contrato de tests de vista |
| 2 | Mostrar cabecera rica: icono, nivel, EXP, rol, shiny | Alto | Bajo | `pokemon_id`, `exp`, `behavior`, `es_shiny` | N+1 si no se precarga |
| 3 | Barra de HP/estado del explorador + descanso legible | Alto | Bajo-Medio | `eventos['explorador']`, `hp_recuperado` | Exponer estado inconsistente entre ticks |
| 4 | «Diario del explorador»: agrupar por franjas y añadir hora/fecha | Alto | Bajo | `timestamp` de cada evento | Cambio visual amplio |
| 5 | Hitos simples (primera expedición, 10 encuentros) sin persistencia nueva: derivados de `resultado.incidentes` | Medio | Bajo | `incidentes` | Sin historial no hay hitos acumulados reales |
| 6 | Narrativa de regreso/derrota/cancelación en la tarjeta | Alto | Bajo | claves raíz `retirada/derrota/cancelada` | Requiere exponerlas en el presentador |
| 7 | Frases de «ánimo» derivadas del estado (HP bajo, muchas huidas) | Medio | Bajo | HP, incidentes | Evitar frases que prometan efectos inexistentes |
| 8 | Etiquetas correctas en el preview de recompensas | Medio | Bajo | ya existe | Cambia contrato del estimador |
| 9 | Botín con nombre (caramelos ya tienen nombre; añadir «objetos» requiere catálogo y persistencia) | Medio | Alto | tabla/catálogo nuevos | Sobreingeniería; descartar por ahora |
| 10 | Relaciones entre reclutados / heridas persistentes | Bajo-Medio | Alto | tablas nuevas, migraciones | Sobreingeniería clara para el MVP |

**Descartar explícitamente:** sistema de relaciones, heridas permanentes con efectos, rasgos con modificadores numéricos, diario generado proceduralmente con LLM, historia por explorador persistida. Todo eso exige modelo de datos y reglas nuevas sin demanda demostrada.

## 7. Cobertura de pruebas

**Existentes:** `ExploracionesViewTest` (10 tests: render completo, empty states, botón recoger, contrato parcial, formato caramelos, derrotados, tipos de evento de riesgo, categoría/incidentes, defensivo), `ExploracionesPageTest` (12: index, bitácora transformada, volviendo/progreso, indefinida, terminadas, cerrar), `FrontendExploracionesCancelarTest` (1), `ExploracionesTransformadorTest` (5, solo caramelos de familia), más `ExploracionesTest`, `ExploracionesAutoResolucionTest`, `ExploracionesIndividualEndpointsTest`, `ExploracionesPreviewTest`, `Unit/Exploraciones/*`.

**Faltan:** tests de `EventoExploracionVista` como unidad (textos por tipo/subtipo), del evento `descanso` (hoy el bug pasaría), de la narrativa de retirada/derrota/cancelación, de las etiquetas de `recompensas_esperadas`, de accesibilidad básica (`progressbar`, `aria-*`), de `PresentadorExploraciones::toActiva` con `nombre` nulo, y de contrato del shape final de `eventos` (snapshot).

## 8. Restricciones de arquitectura

- Blade solo presentación: hoy se cumple parcialmente; sí hay llamadas a `app(PresentadorExploraciones::class)` en la vista (`index.blade.php:26,150`) y `EventoExploracionVista::toView` en `_evento.blade.php:2`. Debería precalcularse en el controlador.
- Presentation con Eloquent: `PresentadorExploraciones::nombresPokemon()` consulta `Pokemon` (línea 80), violando el aislamiento deseado.
- Dominio sin Eloquent: correcto en `SimuladorEncuentros`, `EvaluadorExploracion`, `Calculador*`.
- DTOs en la frontera: existen (`ResultadoExploracionVista`, `DTOIncidentesExploracion`, `DTOObjetoPerdido`), pero persisten arrays anidados como contrato de vista (`context.md`, deuda media).

## Qué es personalidad real vs cosmética

- **Real (barato y con datos existentes):** singularizar la narrativa con nombre y rol, mostrar HP/estado y descanso, narrar el regreso/derrota/cancelación, diario con fechas y agrupación, cabecera con nivel/EXP/shiny, hitos derivados de `incidentes`. Todo sale de datos ya persistidos; no requiere migraciones.
- **Cosmética sin sustancia:** cambiar colores, añadir iconos nuevos, frases aleatorias sin relación con el estado, badges decorativos.
- **Sobreingeniería:** rasgos con efectos, relaciones, heridas persistentes, historial completo, objetos únicos con reglas. Requieren tablas, migraciones y reglas de balance; no hay datos ni demanda que lo justifiquen hoy.

## Recomendación

Veredicto: **sí se puede dar mucha más personalidad sin tocar el dominio ni la base de datos.** El orden óptimo es: (1) narrativa singular por rol y nombre, (2) cabecera rica con nivel/EXP/rol/shiny, (3) HP/descanso legibles y diario con fechas, (4) narrar retirada/derrota/cancelación, (5) hitos derivados de `incidentes`. Antes de implementar conviene corregir dos bugs de contrato: `descanso` (`duracion_minutos` vs `duracion`) y etiquetas nulas en `recompensas_esperadas`. Cualquier propuesta de botín con nombre, relaciones o heridas debe posponerse.

**Pendiente de verificación (no bloqueante):** confirmar el shape exacto de `DTOIncidentesExploracion::toArray()` y de `ResultadoRecompensas` para citar líneas precisas; y revisar `Equipos` controller para cuantificar el legado de equipo.

---

## Nota del orchestrator

Ambos informes coinciden en el diagnóstico: el flujo persistido es individual; el síntoma
«equipos» proviene de nomenclatura, bloqueo de equipo completo y reparto de EXP entre 3. Antes del
diseño hay decisiones de producto pendientes que afectan al alcance (modelo individual vs equipo,
economía de EXP y profundidad de la personalidad). Se preguntan al desarrollador antes de delegar
en `architect`.
