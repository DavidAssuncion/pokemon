# Testing diferido — Iteraciones «ruta/shiny/huevos/EV/IV/CP» y «combate 3v3»

Fecha de cierre: 2026-09-16 (primera iteración) y 2026-10-17 (combate 3v3).

Esta página recopila los **comportamientos a cubrir** cuando se ejecute la fase de testing del
módulo (convención del proyecto: el testing se difiere al cierre del módulo completo; NO ejecutar
tests durante el desarrollo normal). Fuentes: `active/ANALISIS_BACKEND.md` (3 secciones del
Arquitecto/QA: B1/B2/O1/O2/O3/O6, idempotencia+SESSION_VERSION 10, hardening P1, **«Equipos de 3,
motor de 1 acción por ronda e invocaciones (R1–R7)»**, **«Corrección post-revisión del Analista»** y
**«Post-Arquitecto — O1/O2»**), `active/ANALISIS_FRONTEND.md` (coordinación QA frontend y sección
«Equipos de 3 y turnos con `effectiveSpeed`») y la verificación de cierre del Bibliotecario.

Convenciones de ejecución (vigentes en `AGENTS.md` / `docs/conventions.md`):

- PHPUnit con `tests/Unit/` y `tests/Feature/`; SQLite en memoria (`:memory:`).
- NO eliminar tests existentes (p. ej. `ReclutadoSerializerCPTest`) sin aprobación.
- Los tests que referencian formato de sesión de batalla serializan `SESSION_VERSION = 9/10`; la
  compuerta **v11** (mínimo compatible = 9) debe seguir aceptándolos.

## 1 · Huevos — tiempos y estados (Unit, Domain puro)

- **`CalculadorTiemposHuevo::puestaHoras`** — captura_rate 255 → 1.0 h; 3 → 85.0 h; 0 o negativo →
  255.0 h (guard `max($captureRate, 1)`).
- **`CalculadorTiemposHuevo::eclosionHoras`** — hatch 15 → 1.0; 16 → 2.0; 30 → 2.0; 1 → 1.0
  (ceil, guard `max($hatch, 1)`); 0 o -5 → 1.0 (hardening P1); 10240 → 683.0.
- **`CalculadorTiemposHuevo::sumaHoras`** — puesta + eclosión.
- **`CalculadorEstadoHuevo::estadoDe`** — GESTANDO con restante en horas; transición
  GESTANDO→INCUBANDO en `puesta_fin`; INCUBANDO→LISTO en `hatched_at`; `restante_horas` 0 en
  LISTO; redondeo a 2 decimales (evita «84h 60m» por 84.9999∞); `captureRate = 0` (caso `?? 0`)
  no lanza división/TypeError y devuelve un estado coherente.

## 2 · Huevos — API (Feature)

- `GET /api/huevos` — `tiempo_total_horas` y `tiempo_restante` en horas (float), `progreso` 0-100;
  contrato exacto del response (B1).
- `POST /api/huevos` (pareja por familia, semántica O2):
  - 422 con 1 solo individuo de la familia.
  - 422 con solo 2 individuos de distinta familia.
  - OK con 2 individuos de distinta especie, misma `evolution_chain_id`.
  - 422 con huevo GESTANDO activo (otra familia).
  - OK con huevo INCUBANDO activo de otra familia (2 huevos activos de distinto estado conviven).
  - 422 con huevo activo (cualquier estado) de la MISMA familia (O1).
  - 422 si la especie representativa no tiene `hatch` o tiene `capture_rate` null (hardening P1).
  - 201 huevo con `estado gestando` cuando crea.
- `GET /api/huevos` y `POST /api/huevos` con huevos legacy (`capture_rate`/`hatch` null) — el
  listado serializa tiempos/progreso válidos (sin 500) y `CrearHuevo` rechaza con 422.
- `POST /api/huevos/{huevo}/eclosionar`:
  - Reclutado serializado trae `cp` con el **nivel del usuario** (B2).
  - Idempotente: segunda llamada al mismo huevo → 422 «Este huevo ya eclosionó.» sin duplicar
    reclutados (Arquitecto).
  - Huevo ajeno → 404 (route-model binding lo cubre; el check interno de `user_id` es cinturón de
    seguridad).
  - Huevo legacy con `capture_rate` null → estado calculado sin 500; al estar LISTO eclosiona OK.

## 3 · IVs / CP / serialización (Unit + Feature)

- **`GeneradorIvs`** — IVs aleatorios en rango 0-15; `maximos()` = 15 en los 6 stats; `huevo()`
  en rango 10-15 por stat.
- **`BattleStats`** — propiedad `ivs` opcional (`?StatsValue`): con IVs en 0 y EVs en 0 el
  resultado equivale al cálculo legacy; cada punto de IV suma ×4 al stat (fórmula
  `floor((2*base + iv*4 + floor(evs/4)) * nivel/100 + 5)`); payload de sesión **v9** sin la clave
  `ivs` deserializa con `ivs = null` sin TypeError.
- **`DistribuidorStatsValor`** — los 2 mejores stats base reciben `ivPrincipal` y los 4 restantes
  `ivResto`; empates resueltos por orden fijo (hp, atk, def, spAtk, spDef, speed); determinista.
- **`IvsRangoEntrenador`** — `rangoPorNivelEntrenador`: nivel ≤ 1 → [8, 6]; nivel 2 → [10, 8];
  nivel ≥ 3 → [12, 10]; constantes gimnasio/líder (12/10 y 14/12).
- **`ReclutadoSerializer::serializar`** — con `nivelUsuario` dado, `cpDe` usa ese nivel y los EVs
  reales (`floor(array_sum($evs->toArray()))` como 2º argumento); sin `nivelUsuario` → fallback al
  nivel del reclutado (el test existente `ReclutadoSerializerCPTest` debe seguir pasando).
- `/equipos`, `listarTodos`, `listarFavoritos` — `cp` calculado con `auth()->user()->nivel()` y
  EVs del reclutado (Feature).

## 4 · Shiny (Feature + Unit)

- Ruta: combate ganado con slot shiny → la captura del slot aparece en el modal con
  `capturas[].es_shiny = true`; el resto `false` (O3). El reclutado shiny se crea con IVs máximos
  (15) y `es_shiny = true` sin pasar por reclutables.
- `ProbabilidadesShiny` — `SALVAJE = 2048` y `HUEVO = 512`; la decisión es función del roll
  aleatorio inyectado (determinismo en tests).
- `Combatiente::aArrayVista()` — emite `es_shiny` (snake), no `esShiny` (O6).
- `ResultadoRuta::aArray()` — cada captura lleva `es_shiny` (bool) (O3).
- Eclosión: huevo shiny → reclutado `es_shiny = true` con IVs 15; huevo no-shiny → IVs 10-15.

## 5 · EVs (Feature)

- `POST /reclutado/{reclutado}/dar-evs` body `{stat}` con `stat ∈ hp|atk|def|spAtk|spDef|speed`:
  - +10 al stat indicado (contrato O4 del frontend: tooltip y feedback «¡+10 EV!»; NO confundir
    con «+100 exp» de caramelos de tipo).
  - Tope 255 por stat: alcanzado → 422 (sin consumir caramelo).
  - Sin caramelos `candy_ev/{slug}` → 422 con feedback inline.
  - Equipo/reclutado en exploración → 422 (bloqueado).
  - Concurrencia: consume y actualiza con `lockForUpdate` (antifraude, no se duplican caramelos).

## 6 · Animación de daño y UI (View / markers)

- Combate: el defensor con `refId === $animDefenderId` recibe la clase `anim-hit` (600 ms dentro de
  la ventana de 700 ms); card debilitada (`opacity-50`) no anima; `prefers-reduced-motion` desactiva
  sacudida y transición.
- Datagrid `reclutado` — `es_shiny` filterable/sortable/visible (whitelist aditiva).
- Vista equipos: pestaña 🥚 Huevos con loading/error+Reintentar/empty/success; límites
  (`limiteExcedido`/`incubacionActiva`) deshabilitan «Poner huevo»; barra con `progreso` presente
  vs ausente; etiquetas de tiempo con horas decimales (<24h, ≥24h, restante 0); modal detalle con
  IVs/EVs/habilidades ocultos ante data legacy; badge ✨ shiny con contrato `es_shiny` (snake) y
  `esShiny` (camel, legacy) tolerados; fallback onerror de icono shiny → normal.

## 7 · Invariantes de dominio para property tests (validación del Arquitecto)

Los Domain puros de la iteración son candidatos a property tests al cierre del módulo. Invariantes
que deben cumplirse (por lo tanto, propiedades a verificar):

- **Tiempos de huevo**: `puestaHoras` y `eclosionHoras` nunca devuelven < 1.0 h para entrada
  legítima (≥ 1); a mayor `capture_rate` no crece la puesta y a mayor `hatch` no crece la eclosión
  (monotonía); `eclosionHoras` es múltiplo entero de horas (ceil) — es decir, resultado ∈ ℤ+ × 1.0 h.
- **Estados**: la secuencia GESTANDO → INCUBANDO → LISTO es monótona en el tiempo — para un mismo
  huevo, `estadoDe(t1)` no puede ser posterior a `estadoDe(t2)` con `t1 > t2`… (la transición es
  irreversible en un único eje); en LISTO, `restanteHoras == 0`.
- **Distribución de IVs**: los 2 stats base máximos reciben `ivPrincipal`; para `ivPrincipal >=
  ivResto` el valor mayor nunca va a un stat con estadística base menor (con el orden fijo de
  desempate, la distribución es determinista).
- **IVs**: todo IV generado está en [0, 15]; `huevo()` en [10, 15]; shiny == máximo en los 6 stats.
- **Probabilidades shiny**: `esShiny(aleatorio)` es true exactamente cuando `aleatorio() % 2048
  == 0` (salvaje) o `% 512 == 0` (huevo); con la misma semilla, la misma decisión (determinismo).
- **BattleStats**: los stats son no decrecientes en `ivs` y `evs`; incrementar un IV en 1 nunca
  reduce el stat final ni el CP; con `ivs` nulo el resultado coincide con el cálculo sin IVs.
- **CP**: `cpDe` con `nivelUsuario` es no decreciente respecto al nivel; con EVs a 0 coincide con
  el CP base del reclutado.

## 8 · Motor de turnos — una acción por vivo y ronda (Unit)

- **`GestorTurnos::startNewRound`** — incrementa la ronda y pone `vecesActuadoEstaRonda = 0` a todos
  los combatientes (vivos y debilitados).
- **`GestorTurnos::getNextActor`** — devuelve el vivo con acción pendiente (`contador === 0`) de
  mayor **velocidad efectiva**; ignora muertos y ya actuados; empate → orden de inserción (team1
  antes que team2).
- **`GestorTurnos::consumeAction`** — solo incrementa el contador del actor (no altera velocidades);
  el actor no vuelve a ser elegido en la ronda.
- **`hayAlgunoConAccionPendiente`** — true con vivos sin actuar; false si todos actuaron o no hay
  vivos.
- **Invariante anti-bucle** — si `hayAlgunoConAccionPendiente()` es true, `getNextActor()` **no** es
  null.
- **Velocidad efectiva ≤ 0** (stats base anómalos) no cortocircuita la ronda: el combatiente actúa.
- **Parálisis/etapas** — la velocidad efectiva (mitad por parálisis, multiplicadores de etapa) altera
  el orden de actuación.
- **Bloqueado consume su acción** — actor con sueño/hielo/parálisis/confusión: `Combate::nextActor()`
  registra el estado, llama a `consumeAction()` sin atacar y avanza (sin bucle infinito).
- **Serialización** — `Combatiente::__serialize()` no incluye `velocidadAcumulada`;
  `__unserialize()` de un payload legacy v9/v10 con esa clave no lanza y reconstruye el combatiente
  (best-effort).
- **`aArrayVista`** — expone `effectiveSpeed` (float con etapas/parálisis) y **no** `accumulatedSpeed`.
- **`Combate::buildTurnQueue`** (Feature/Unit Livewire) — pendientes ordenados por velocidad efectiva
  descendente, orden estable y coherente con `getNextActor()`; la barra (`turn-bar.blade.php`) pinta
  `round($p['effectiveSpeed'])` sin índice indefinido.

## 9 · Invocaciones (Unit)

- **`AgregadoBatalla::incorporarCombatiente(EquipoBatalla, Combatiente, Posicion)`** — añade el
  combatiente al equipo indicado con contador 0 (puede actuar en la ronda en curso), aparece en
  `allCombatants()`/`combatientesVivos()` (misma colección que consume `GestorTurnos`), **no** genera
  log ni dispara efectos de entrada y **no** tiene tope (puede superar los 3 estándar).
- **`EquipoBatalla::agregarCombatiente`** — fija la posición y añade al final de la colección: el
  orden de inserción define el desempate de turnos.

## 10 · Equipos y rivales exactamente 3 (Unit + Feature)

- **`ConstruirEquipoJugador::desdeEquipo`** — 3 combatientes → equipo válido con formación (popup >
  `teams.formacion` > clasificación automática); 2 o 4 → `ViolacionReglaNegocio` 422 con mensaje que
  contiene «exactamente 3 miembros»; los miembros sin reclutado/pokémon no cuentan.
- **`TeamController::addMember`** — slot 1-3 aceptado; slot 4 rechazado; slot ocupado y reclutado ya
  presente siguen rechazados.
- **`IniciarCombateRuta`** — equipo de 3 → batalla 3v3 en sesión (team1 3 / team2 3) y meta correcta;
  equipo de 2 → 422 centralizado.
- **`GeneradorEquipoRuta`** — 3 rivales con reemplazo ponderado por slot e ids `ruta_*_0..2`; pool
  vacío/sin peso → `[]`.
- **`GeneradorEquipoEntrenador`** — 3 especies únicas con pool suficiente; repetición ponderada hasta
  3 con pool corto (ids `entrenador_*_0..2`); determinismo por semilla/fecha.
- **`GeneradorPokemonGimnasio::escalarATres`** — roster > 3 → recorte a los 3 primeros preservando
  vanguardia → retaguardia; < 3 → repetición cíclica hasta 3; vacío → `[]`.
- **Guards de rival vacío (post-Arquitecto O1)** — `IniciarCombateRuta`,
  `IniciarCombateEntrenador` y `IniciarCombateGimnasio` lanzan `ViolacionReglaNegocio` 422 y **no
  persisten sesión** de batalla (nunca 0v3); un combate válido sigue creando 3v3.
- **`CalculadorRecompensas::MIEMBROS_EQUIPO = 3`** — el reparto del 80 % de EXP usa divisor 3.
- **Migración `2026_10_15_000001`** — un equipo legacy con slots 4-5 queda recortado a 1-3 tras
  `migrate`; `migrate:rollback` no restaura datos (`down()` no-op).
- **`FabricaBatallaMock`** — 3 combatientes por equipo (`player_1..3`, `enemy_1..3`) con los
  ids/valores de los índices 0-2.
- **Exploraciones** — sin validación nueva de tamaño (regresión: su comportamiento no cambia).

## 11 · Contrato de vista, sesión y log de ataque (Unit + Feature)

- **`Combatiente::aArrayVista()`** — claves `effectiveSpeed` (float), `es_shiny`, `icon` webp
  (normal/shiny/sufijo de forma/placeholder `0`); sin `accumulatedSpeed`.
- **`BattleSessionService`** — `SESSION_VERSION = 11`, mínimo compatible 9; round-trip
  `guardar`/`cargar`; payload v9/v10 con `velocidadAcumulada` carga sin error y sin acumulado;
  payload < 9 se descarta; payload corrupto → null.
- **Log de ataque (batalla automática)** — `AgregadoBatalla::ejecutarBatalla` emite
  `"{vel}vel [{hp}hp] ataca a [{hp}hp] con {mov} -> {daño} de daño"` (corchetes y velocidad efectiva;
  sin nombres por decisión).
- **KO al cierre de ronda (QA O1)** — si veneno/quemadura/clima debilita al último pokémon de un
  bando al cerrar la ronda, la batalla termina antes de seleccionar actor (sin acción extra).

## 12 · Invariantes y property tests — combate 3v3

Propiedades a verificar al cierre del módulo (complementan las de la sección 7):

- **Exactamente 1 acción por vivo y ronda** — en cualquier batalla, cada combatiente vivo actúa como
  máximo una vez por ronda; si sigue vivo al final de la ronda, actúa exactamente una vez.
- **Determinismo del orden** — misma composición/estado → misma secuencia de actores (el orden no usa
  aleatoriedad).
- **Estabilidad de empates** — a igual velocidad efectiva, el orden es el de inserción de la
  colección (team1 antes que team2), también para combatientes invocados.
- **Anti-bucle** — `hayAlgunoConAccionPendiente() === true` ⇒ `getNextActor() !== null`; toda ronda
  termina con todos los vivos con contador > 0.
- **Composición de la velocidad efectiva** — `velocidadEfectiva() == base × multiplicador(etapas,
  VELOCIDAD) × (parálisis ? 0.5 : 1)`; monótona creciente con la etapa y no creciente con parálisis.
- **Divisor EXP = 3** — `expMiembro`/reparto de exploración usan siempre `MIEMBROS_EQUIPO` (3),
  independientemente del número de derrotados.
- **Rival exactamente 3** — todo combate iniciado por ruta/entrenador/gimnasio tiene `team2` de tamaño
  3 o falla con 422 (nunca 0v3 ni 4v3).

## 13 · Acceptance (ambas iteraciones)

- Una partida en curso con sesión de batalla **v9** (guardada antes del despliegue) sigue cargando
  tras el bump a **v11** (sesiones activas no se pierden: `velocidadAcumulada` se ignora y
  `BattleStats.$ivs` es opcional).
- Combate de ruta manual: 3v3; cada pokémon vivo actúa exactamente una vez por ronda en orden de
  velocidad efectiva; la barra de turnos consume `effectiveSpeed`.
- Mazmorra: jefe 1×10 contra equipo de 3 (3v1); las recompensas de exploración reparten el 80 % de la
  EXP entre 3 miembros.
- Un combate de ruta/entrenador/gimnasio sin rival generable responde 422 y no crea batalla.
- El cierre del módulo no elimina tests existentes (p. ej. `ReclutadoSerializerCPTest`, tests de
  Gimnasios/Mazmorras que serializan v9).

---

**Pendientes de propiedad/documentación que NO bloquean el testing** (ver `docs/context.md`):

- OBS-01/02 — discrepancia UI/backend en límites de huevos (`limiteExcedido()` más restrictivo que
  `CrearHuevo`); decisión de producto pendiente; el backend ya expone `limites` correctos.
- `habilidades=[]` en `ReclutadoSerializer` — sin `app/Models/Ability` + pivot aún.
- Filas legacy de huevos con `capture_rate` null en `disponibles` (posible ampliación futura del
  filtro de disponibilidad, fuera de alcance).
- Combate 3v3: CRAP no medido (testing diferido); `phpstan-hardener.neon` inválido con PHPStan 2.2.9
  y shim deptrac 1.0.2 sin soporte de constantes tipadas; sin baseline PHPStan (ver deuda 17 de
  `docs/context.md`).