# Contexto del módulo `Exploraciones`

> Fuente de verdad del módulo. Si cambias una regla de producto, un contrato o una decisión de
> diseño, actualiza este archivo en el mismo cambio. Las búsquedas guiadas están al final.
>
> Última revisión: 2026-11-02 (TASK-005). Estado: implementado y revisado; quedan deudas de Fase 2.

## Propósito

Simula y resuelve expediciones **individuales**: valida requisitos, genera eventos, estima riesgo y
recompensas, ejecuta combates 1v1 y persiste el resultado. La exploración actual es de **un único
reclutado** (`ExploracionActiva.reclutado_id`), no de un equipo de expedición; el jugador elige
hábitat, nivel y duración o hora de vuelta.

## Estructura y responsabilidades

| Ruta | Responsabilidad |
|---|---|
| `App/ProcesarExploracionCommand.php` + `ProcesarExploracionHandler.php` | Tick principal: eventos, combates 1v1, capturas, paradas, descanso. |
| `App/FinalizarExploracionCommand.php` + `FinalizarExploracionHandler.php` | Cierre: recompensas, derrotados, avistados, objetos perdidos, bitácora. |
| `App/ProcesarExploracionesPendientes.php` | Procesamiento bajo demanda con throttle y lock (ver más abajo). |
| `App/ResultadoProcesamientoPendiente.php` | Resumen tipado (`procesadas`, `omitidas`, `fallidas`, `agotadoPorTiempo`). |
| `App/CombateExploracion.php` | Adaptador de combate 1v1 para eventos. |
| `App/PersistirRecompensas.php` | Persistencia de caramelos/capturas/EXP en la cuenta. |
| `App/NormalizadorPokemonDerrotado.php` | Normaliza derrotados por fase evolutiva. |
| `App/FabricaCapacidadesStats.php` | Construye capacidades del explorador. |
| `Domain/EconomiaRecompensas.php` | Política económica: factor global y reparto de EXP. |
| `Domain/CalculadorFinExploracion.php` | Fin de expedición desde `hora_limite` o `duracion_horas`. |
| `Domain/Calculador*` | Capacidad, peligro, riesgo, matchups, recompensas, vuelta. |
| `Domain/EstimadorRecompensasExploracion.php` | Estimación del preview (aplica el factor 0.5). |
| `Domain/EvaluadorExploracion.php` | Dificultad, resultados de combate, contratiempos, categoría final. |
| `Domain/SimuladorEncuentros.php` | Generación de eventos y pool ponderado. |
| `Domain/SinergiaEquipo.php` | Sinergias por combinación de roles (legado de exploración por equipo). |
| `Domain/Recompensas/` | `RecompensaCaptura`, `RecompensaFamilia`, `RecompensaEv`, `RecompensaTipo`, `ResultadoRecompensas`, `PokemonDerrotado`. |
| `Domain/ValueObjects/` | `PoolHabitat`, `PokemonDelPool`, `ColeccionPoolPonderado`, `EventoExploracion`, `BitacoraExploracion`, `EstadoExplorador`, etc. |
| `Domain/DataTransferObjects/` | `ResolucionContratiempo`, `ResolucionEncuentro`, `ResolucionEmboscada`, `Matchup`, `EvaluacionRiesgo`, `HallazgosCalculados`. |
| `Domain/Collections/` | `MatchupCollection`, `RecompensaCapturaCollection`, `RolesExploracionCollection`. |
| `Presentation/` | `PresentadorExploraciones`, `TransformadorResultadoExploracion`, `ResultadoExploracionVista`, DTOs de vista (`ExploradorVista`, `DiarioExploracion`, `HitoExploracion`, `FraseAnimo`, `EventoExploracionVista`, `DTOIncidentesExploracion`, `DTOObjetoPerdido`). |

## Economía de recompensas (Fase 1)

La política vive en `Domain/EconomiaRecompensas`. El factor se aplica **una sola vez en dominio**;
la persistencia y la presentación no vuelven a descontar.

`EconomiaRecompensas::individual()` es la economía de la exploración individual:

- **Factor global 0.5** sobre EXP, caramelos y **probabilidad** de captura.
- **EXP**: `pool = floor(T × multiplicadorCategoría × 0.5)`;
  `expCuenta = intdiv(pool, 2)`, `expReclutado = pool - expCuenta`. La suma se conserva
  (invariante), por lo que no se pierde EXP por redondeo.
- **EXP de tipo**: mismo reparto. `expTipoCuenta` alimenta las Sendas y `expTipoPorMiembro` al
  reclutado.
- **Caramelos**: `floor(base × multiplicador × 0.5)`.
- **Capturas**: se reduce la **probabilidad** ×0.5, no la cantidad de capturas posibles.
- Los caramelos y las capturas van a la **cuenta**; los reclutados no tienen inventario.
- **`objetos_perdidos`** (derrota) se calcula sobre recompensas **ya reducidas**; no sufre un
  segundo 0.5.

`EconomiaRecompensas::legado()` conserva la economía histórica (factor 1.0, reparto del 80 % entre
3 miembros) y la usa `Src\CombateRuta\App\OtorgarRecompensasRuta` a través de
`CalculadorRecompensas` (que por defecto aplica `legado()`). No mezclar ambas políticas.

`FinalizarExploracionHandler` guarda `objetos_perdidos` en `eventos['objetos_perdidos']` (raíz) y
`PresentadorExploraciones::toTerminada()` lo reenvía a `resultado`; `ResultadoExploracionVista` lo
proyecta y la vista lo muestra. La resolución de nombres e imágenes es **de presentación** y no
cambia el contrato persistido (ver «Objetos perdidos»).

## Ciclo de vida de una expedición

- `inicio_exploracion = now()` al crear la exploración.
- `CalculadorFinExploracion::calcular()`:
  - `return_time` / `hora_limite` → **siguiente ocurrencia de esa hora desde el inicio**. Si la
    hora es igual o anterior al inicio, salta al día siguiente (p. ej. inicio 23:00, vuelta 01:00
    → 01:00 del día siguiente).
  - `duracion_horas` → `inicio + N` horas.
  - Ambos `null` → exploración **indefinida** (sin fin).
- `duracion_horas` y `return_time` **juntos** → rechazo **422** ("No puedes indicar una duración y
  una hora de vuelta a la vez.").
- `llegada_destino` está **deprecada**: la columna existe y se escribe `null`, pero no tiene
  escritores reales.
- `POST /api/exploraciones/store-individual` responde **201** con
  `{ok, id, inicio, fin, estado}` (`inicio`/`fin` en ISO 8601, `estado = "explorando"`).

## Procesamiento bajo demanda

`App/ProcesarExploracionesPendientes` es compartido por HTTP y consola:

- `paraUsuario(int $userId, int $limite = 10, int $presupuestoMs = 2000)` — usadas por
  `GET /exploraciones` antes de renderizar.
- `paraTodas(int $limite = 500)` — usada por `exploraciones:procesar` (schedule cada 5 min),
  sin presupuesto de tiempo.
- `una(ExploracionActiva $exploracion, bool $forzarRegreso = false)` — usada por `recoger`.

Reglas:

- **Lock** `exploracion:procesar:{id}`, TTL 60 s, **no bloqueante**, adquirido **fuera** de la
  transacción del `CommandBus` (se libera después del commit).
- **Throttle** de 30 s por `eventos['ultimo_procesado']`; `forzarRegreso` lo salta.
- **Límites**: 10 exploraciones y presupuesto de 2 s en HTTP; consola con `paraTodas(500)`.
- **`recoger`** usa `una(..., forzarRegreso: true)`; si el lock está ocupado responde **409**
  (JSON) o redirect con error ("La exploración se está procesando; recarga en unos segundos.").
- **Fallos aislados** por exploración: se registran con
  `Log::warning('exploraciones.procesar_pendientes.fallo', [...])` y se continúa.

## Bloqueo individual

- `Reclutado::estaEnExploracion()` — `true` si existe una `ExploracionActiva` con el mismo
  `reclutado_id` y `regreso IS NULL`.
- `Src\Habitats\App\ValidadorExploracion::reclutadoEnExploracion(int $id)` /
  `reclutadoDisponible(int $id)` — validación usada al iniciar una exploración.
- `Team::isExploring()` se eliminó y las guardas de equipo de `src/Equipos/App/*` se retiraron.
- **Solo se bloquea al reclutado que explora**; el equipo sigue visible y editable.
- **Combate**: `Src\CombateEntrenadores\App\ConstruirEquipoJugador::desdeEquipo()` exige
  exactamente 3 miembros y que **ninguno** esté en exploración.
- `equiposEnExploracion` queda como **alias vacío deprecado** en `PlayerController` y
  `HabitatsController`; el dato real está en `reclutadosEnExploracion`.

## Presentación

- `ExploradorVista` — ficha del explorador: nombre, nivel, EXP, shiny, rol, tipos, HP (actual/máx,
  barreras), ánimo y progreso de nivel. Presentación pura: no consulta la base de datos.
  - `nombre()` resuelve, en orden: alias del reclutado → `pokemon.name` **solo si la relación
    `pokemon` ya está cargada** (`relationLoaded('pokemon')`; nunca fuerza lazy loading) →
    `Sin reclutado`.
- `DiarioExploracion` — fusiona la bitácora con las raíces `retirada`, `derrota` y `cancelada`,
  ordena, transforma y agrupa por fecha. **Descarta los eventos con `oculto=true`** antes de
  calcular `eventos`, `total` y `grupos`.
- `HitoExploracion` y `FraseAnimo` — hitos y textos de ánimo.
- `EventoExploracionVista` — normaliza cada evento para la vista; corrige
  `duracion_minutos`/`hp_recuperado`, usa narrativa singular y cubre derrota/cancelada. Es la única
  capa que compone narrativa, nombres y rutas; la vista solo decide qué bloque pintar (ver
  «Contrato del diario»).
- `PresentadorExploraciones::nombresPokemon()` construye el mapa `pokemon_id → nombre` escaneando
  la bitácora de activas y terminadas, `resultado.capturados` y las capturas perdidas de
  `objetos_perdidos`; resuelve los nombres una vez por página. Para las cadenas usa una **caché de
  instancia por página** (`miembrosDeCadenas()`): consulta solo las cadenas aún no vistas, en lotes,
  de modo que no hay una única query global si cada tarjeta aporta cadenas nuevas.
- `eventos['resultado']` añade **`exp_reclutado`**; `exp` sigue siendo la EXP de **cuenta**.
- Preview (`GET /exploraciones/preview`):
  - `economia` = `{factor, fraccion_cuenta, fraccion_reclutado}` (0.5 / 0.5 / 0.5).
  - `aviso_economia` explica que las recompensas ya incluyen el 50 % y que la EXP se reparte
    50 % cuenta / 50 % explorador.
  - `EstimadorRecompensasExploracion` aplica el factor 0.5 y devuelve labels no nulos.
- El alias `equipo` en los payloads del presentador se mantiene **deprecado** por compatibilidad;
  usa `explorador`/`reclutado`.

### Contrato del diario (TASK-005)

`EventoExploracionVista::toView()` compone todos los textos y rutas. La vista
(`resources/views/exploraciones/_evento.blade.php`) solo elige el bloque HTML según `tipo`.

**Base de todo evento**

- `oculto` — `false` por defecto. Solo `caramelo_tipo` lo pone a `true` cuando no puede resolver el
  tipo; `DiarioExploracion` y `PresentadorExploraciones::activaToView()` filtran esos eventos.
- `nombre` — `evento.nombre` → `nombres[pokemon_id]` → `nombres[pokemon_ids[0]]`.
- `icono_pokemon` / `iconos_pokemon` — `/images/iconos_webp/{id}.webp`.

**Caramelos**

| Evento | Claves de presentación | Resolución |
|---|---|---|
| `caramelo_familia`, `hallazgo:caramelo_familia` | `candy_imagen`, `candy_src`, `candy_nombre`, `candy_alt`, `cantidad` | `candy_imagen` = sprite de especie `/images/iconos_webp/{pokemon_id}.webp`; `candy_src` = `/images/candy_pokemon/{id}.webp`. La vista cae a `candy_src` y luego a `/images/candy_pokemon/0.webp`. |
| `caramelo_tipo`, `hallazgo:caramelo_tipo` | `candy_slug`, `candy_nombre`, `candy_src`, `candy_alt`, `oculto`, `cantidad` | `tipo_id` → `TipoEnum::fromId()` → `label()` + `SlugTipo::de()`; fallback legacy a `slug`/`tipo_nombre`/`tipo_label`. Sin resolución: `oculto=true` y sin `candy_src`. |
| `caramelo_ev`, `hallazgo:caramelo_ev` | `stat_nombre`, `stat_slug`, `candy_src`, `candy_nombre`, `candy_nombre_js`, `cantidad` | `StatEnum::fromId($stat)` → `label()` + `slug()` reales (`hp`, `atk`, `def`, `atksp`, `defsp`, `spd`); `candy_src` = `/images/candy_ev/{slug}.webp`. `completarStats` solo rellena el catálogo en la vista activa; los slugs ya no dependen de `statName()` en JS. |

**Combate y derrota**

Claves persistidas por `resolucionCombate()` (aditivas; solo se escriben si no son `null`):

| Clave | Semántica |
|---|---|
| `hp_inicial` / `hp_max` | PV del explorador al empezar el **sub-combate decisivo**, tras el descanso previo y `triggerBattleStartEffects()`; `hp_max` es el máximo de ese combate. |
| `rival_hp_inicial` / `rival_hp_final` / `rival_hp_max` | PV del rival al empezar, al terminar y máximos. |

- `rondas` — número de líneas `--- Ronda N ---` del `log`; `null` si no hay log.
- `rival_nombre` — en emboscadas usa el `pokemon_id` del **último** `sub_combates` (el decisivo),
  resuelto contra el mapa de nombres; si no, `evento.nombre`.
- `detalle_derrota` — solo si `resolucion=derrota` y existe `hp_inicial`: «Entró con X/Y PS tras N
  rondas; a Rival le quedaban A/B PS.» o, sin datos del rival, «Entró con X/Y PS y cayó». Los
  eventos históricos sin claves nuevas no muestran bloque de PV.
- `reason_texto` (evento raíz `derrota`) — `explorador_debilitado` → «se quedó sin PS»; cualquier
  otro `reason` se humaniza (`_` → espacio). `reason` se conserva como dato.
- `rondas`, `rival_nombre` y `detalle_derrota` se emiten en `emboscada` y `encuentro`.

**Round-trip**

`ResultadoBatallaExploracion` y `EventoExploracion` (`desdeArray()`/`aArray()`) conservan las cinco
claves de PV; `toArray()` solo incluye las que no son `null`, de modo que los eventos históricos
siguen renderizando sin ellas.

**Objetos perdidos**

El contrato persistido (`eventos['objetos_perdidos']`) sigue siendo
`{tipo, id, label, cantidad_perdida}`. `PresentadorExploraciones::objetosPerdidosVista()` lo
enriquece **solo en presentación**:

| `tipo` | `id` | `label` | `imagen` |
|---|---|---|---|
| `familia` | `evolution_chain_id` | miembro base (menor `species_id`) de `CadenasEvolutivas::miembrosDe()` | `/images/candy_pokemon/{baseId}.webp` |
| `ev` | `stat` | `StatEnum::fromId()->label()` | `/images/candy_ev/{slug}.webp` |
| `tipo` | `slug` | `label` persistido o `ucfirst(slug)` | `/images/candy_type/{slug}.webp` |
| `captura` | `pokemon_id` | `nombres[id]` o `Pokémon #id` | `/images/iconos_webp/{id}.webp` |

- `tipo_label` (`Familia`, `EV`, `Tipo`, `Captura`) y `cantidad_perdida` acompañan siempre; `id` se
  conserva para trazabilidad.
- Un `label` numérico histórico se descarta (`labelPerdidaPersistido()`). Sin resolución,
  `label=null`: la vista muestra `tipo_label` como texto y `tipo_label #id` en el `title`, nunca el
  ID como único texto.
- Las cadenas se consultan una vez por página y se cachean entre tarjetas (`miembrosDeCadenas()`).
- `ResultadoExploracionVista::objetosPerdidos()` conserva los campos enriquecidos y, en llamadas
  directas con datos antiguos, deriva `tipo_label` e `imagen` de EV/tipo sin consultar la BD.
- `resources/views/exploraciones/_perdidas.blade.php` pinta el aviso «⚠️ Perdiste objetos» con
  imagen, tipo, nombre y cantidad. Se incluye **solo en la tarjeta de resultados** (`role="alert"`);
  el diario ya no muestra el aviso (v4).
- `motivo_perdida` — se deriva de `eventos['derrota']['reason']` (con `trim`; vacío → `null`):
  `explorador_debilitado` → «Tu explorador cayó debilitado en combate y perdió parte del botín.»;
  sin motivo → texto genérico; cualquier otro motivo se humaniza entre paréntesis. Solo se expone
  si hay pérdidas (`null` sin ellas) y `_perdidas` lo muestra solo en la tarjeta de resultados. No
  cambia la regla `ceil(cantidad/2)`.

## Contratos y puntos de entrada

Rutas (`routes/exploraciones.php`, bajo `auth`):

```text
GET  /exploraciones
GET  /exploraciones/preview
POST /exploraciones
POST /exploraciones/{exploracion}/recoger
POST /exploraciones/{exploracion}/cancelar
POST /exploraciones/{exploracion}/cerrar
POST /api/exploraciones/store-individual
```

- `App\Http\Controllers\ExploracionActivaController` orquesta vistas, preview, store, recogida,
  cancelación y cierre.
- `ProcesarExploracionesPendientes` se invoca en `index` (usuario), `recoger` (una) y
  `App\Console\Commands\ProcesarExploraciones` (todas).
- Tablas: `exploraciones_activas` (`reclutado_id`, `nivel`, `duracion_horas`, `hora_limite`,
  `regreso`, `inicio_exploracion`, `eventos`, `bitacora`, `llegada_destino` deprecada),
  `reclutados`, `caramelos`, `player_inventory`.
- Contrato de recompensas: `TransformadorResultadoExploracion` + `ExploracionActivaController`
  (claves `caramelos_familia`, `caramelos_ev`, `caramelos_tipo`, `capturados`, `exp`).

## Constantes (`Domain/ReglasExploracion`)

| Constante | Valor | Uso |
|---|---|---|
| `MINUTOS_POR_ENCUENTRO` | `30` | Minutos base consumidos por encuentro. |
| `HP_POR_MINUTO_DESCANSO` | `1` | HP recuperado por minuto real de descanso. |
| `FRACCION_EXP_REPARTO` | `0.8` | Reparto legado de EXP por miembro. |
| `FACTOR_RECOMPENSAS` | `0.5` | Factor global de la economía individual. |
| `FRACCION_EXP_INDIVIDUAL` | `0.5` | Fracción de EXP al reclutado (y a la cuenta). |

**Decisión confirmada (2026-11-01)**: los valores canónicos son `MINUTOS_POR_ENCUENTRO = 30` y
`HP_POR_MINUTO_DESCANSO = 1`. No hay discrepancia abierta ni referencia vigente a valores
anteriores.

## Dependencias

- `Src\Battle\Domain` — motor de combate 1v1 (`CombateExploracion`).
- `Src\Habitats\Domain` / `Src\Habitats\App\ValidadorExploracion` — pool, hábitats, cadenas y
  disponibilidad del reclutado.
- `Src\Reclutamiento\App\ServicioCaptura` / `App\Support\CrearReclutado` — capturas.
- `Src\Shared\Domain\NivelHelper`, `ProbabilidadCaptura`, `ReglasEquipo`, `ReglasExploracion`.
- `Src\Shared\Bus\CommandBus`.
- `App\Models\ExploracionActiva`, `App\Models\Reclutado`, `App\Models\Habitat`,
  `App\Models\Pokemon`.
- `App\Support\CadenasEvolutivas` — mapa de familias por `evolution_chain_id`.

## Estado de calidad (TASK-005)

- Reviewer: `APPROVED`.
- Suite relevante: **416 tests / 2555 aserciones en verde** (última corrida; incluye cambios
  concurrentes ajenos a TASK-005).
- `phpstan`: sin errores en los archivos de TASK-005; quedan **8 errores preexistentes** en
  `SendasController` (WIP ajeno).
- `pint`: pass.
- Sin migraciones ni dependencias nuevas.

## Deuda y Fase 2 pendiente

- **`ProcesarExploracionHandler` es un god-handler**: extraer resolvers por tipo de evento y un
  servicio de descanso/tiempo.
- **Preview único**: el controlador individual calcula riesgo simple mientras `CalculadorRiesgo`
  mantiene otro contrato. Unificar en un caso de uso `PreviewExploracion`.
- **Idempotencia fuerte** del procesamiento (más allá del lock de 60 s).
- `withoutOverlapping()` en el comando de consola.
- Eliminar alias (`equipo`, `equiposEnExploracion`) y columnas muertas (`llegada_destino`).
- Cast de `hora_limite` (hoy se lee con `getRawOriginal`).
- Tests end-to-end de los 4 flujos de combate.
- Verificar el lock con el driver de producción (cache/redis), no solo con el driver de test.
- `FinalizarExploracionHandler` propaga `array<int, Collection<int, Pokemon>>` para cadenas;
  introducir un VO/colección tipada.
- `Presentation/` sigue devolviendo arrays en la frontera de vista; migrar a DTOs y dejar el array
  solo en `@json`.

## Guía de búsqueda (grep)

- Clases: `ProcesarExploracionHandler`, `FinalizarExploracionHandler`,
  `ProcesarExploracionesPendientes`, `ResultadoProcesamientoPendiente`, `EconomiaRecompensas`,
  `CalculadorFinExploracion`, `CombateExploracion`, `EvaluadorExploracion`, `SimuladorEncuentros`,
  `CalculadorRiesgo`, `CalculadorMatchups`, `CalculadorRecompensas`,
  `EstimadorRecompensasExploracion`, `PresentadorExploraciones`,
  `TransformadorResultadoExploracion`, `ExploradorVista`, `DiarioExploracion`,
  `EventoExploracionVista`, `ResultadoExploracionVista`, `ResultadoBatallaExploracion`.
- Constantes: `ReglasExploracion::MINUTOS_POR_ENCUENTRO`, `HP_POR_MINUTO_DESCANSO`,
  `FRACCION_EXP_REPARTO`, `FACTOR_RECOMPENSAS`, `FRACCION_EXP_INDIVIDUAL`;
  `EvaluadorExploracion::DIFICULTAD_*`, `CalculadorRecompensas::MIEMBROS_EQUIPO`.
- Rutas/términos: `/exploraciones`, `preview`, `store-individual`, `recoger`, `cancelar`, `cerrar`,
  `duracion_horas`, `return_time`, `bitacora`, `eventos`, `ultimo_procesado`, `exp_reclutado`,
  `objetos_perdidos`, `tipo_label`, `caramelos_familia`, `caramelos_ev`, `candy_imagen`,
  `candy_src`, `oculto`, `hp_inicial`, `rival_hp_final`, `rondas`, `detalle_derrota`,
  `reason_texto`.
- Vistas: `resources/views/exploraciones/_evento.blade.php`,
  `resources/views/exploraciones/_perdidas.blade.php`.
- Tablas: `exploraciones_activas`, `reclutados`, `caramelos`, `player_inventory`.
- Tests: `tests/Unit/Exploraciones/` (economía, calculadores, simulador, sinergias, matchups,
  evaluador) y `tests/Feature/Exploraciones*`.
