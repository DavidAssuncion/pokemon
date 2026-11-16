# TASK-046 — Análisis

- **Estado:** COMPLETADO CON LIMITACIONES (dos investigaciones; una parcial)
- **Analistas:**
  - Parte A — Datos de niveles, Pokédex y economía: `analyst`, sesión `ses_e1c9ac408ffe62vtlksrwflOEX`. Alcanzó el límite de pasos; el informe es parcial y deja verificaciones pendientes (formas huérfanas en BD y comparativa completa de economía).
  - Parte B — Población, eventos y ledger: `analyst`, sesión `ses_e2aaad7d3ffebWLNbin0jNxvZE`. Alcanzó el límite de pasos; el informe es completo en lo revisado.
- **Nota:** se reproducen los informes sin alterar sus conclusiones. No se modificó ningún archivo de aplicación.

---

## Parte A — Datos de niveles, Pokédex y economía

# TASK-046 — Análisis (parcial, interrumpido por límite de pasos)

**Límite de pasos alcanzado.** No he podido cerrar el informe completo. A continuación reporto la evidencia ya verificada, con cifras reales, y lo que queda pendiente. Todo lo incluido está comprobado leyendo archivos y agregando CSV con `python3`; nada se modificó.

## Cifras verificadas

### 1. Niveles y pool
- 787 filas en `storage/data/pokemon_habitat.csv`; 779 pares hábitat+especie únicos (8 filas extra por formas con distinto `pokemon_id` y misma species).
- 44 hábitats con spawn de 56 en `habitats.csv` (12 sin ningún Pokémon). **Ningún hábitat tiene 1 nivel**: 37 tienen niveles 1/2/3 y 7 tienen solo 1/2 (Bahía Crustáceo, Campo Terroso, Mar Sereno, Lago Místico, Pradera Salvaje, Cámara Omega, Vieja Reliquia).
- Niveles distintos = 125 (44 L1 + 44 L2 + 37 L3).

| Nivel | Niveles | Especies total | mín / mediana / media / máx | Familias (suma) | Fam. mín/mediana/máx |
|---|---|---|---|---|---|
| 1 | 44 | 267 | 1 / 7 / 6,1 / 14 | 267 | 1 / 7 / 14 |
| 2 | 44 | 388 | 1 / 7 / 8,8 / **91** | 371 | 1 / 7 / **91** |
| 3 | 37 | 124 | 1 / 3 / 3,4 / 10 | 116 | 1 / 3 / 9 |

- Especies repetidas en varios niveles del mismo hábitat: **1 caso** (Pantano Ponzoña, hábitat 34), 17 especies únicas.
- Especies únicas por hábitat: mín 2, mediana 16,5, máx 97 (total 778).
- **Outlier que distorsiona todo**: hábitat 49 «Legendarios», L2 = 91 especies / 91 familias; el hábitat entero tiene 97 especies y 92 familias. Sin él, L2 baja de máx 91 a máx 19.
- 7 niveles tienen **una sola especie**; 9 niveles tienen **una sola familia**; 26 niveles tienen ≤2 familias; **70 de 125 niveles (56,0 %) tienen ≤5 familias**; 14 niveles ≥10 familias (13 si se excluye Legendarios).

### 2. Familias
- Relación especies/familia ≈ **1,03**: casi cada especie del pool es su propia familia. Una recompensa «por familia» dentro de un nivel apenas se diferencia de «por especie».
- Familias por hábitat: mín 1 (Lago Místico), mediana 8, máx 92 (Legendarios); total 399 asignaciones.

### 3. Pokédex
- `app/Jobs/ActualizarPokedexJob.php:33-56`: `visto = true` siempre; `atrapado` solo si estado `RECLUTADO`; no degrada un `atrapado` a visto. Clave `(user_id, pokemon_id)` — **nivel de forma, no de especie**.
- Emisiones: `src/CombateRuta/App/OtorgarRecompensasRuta.php:251`, `src/Exploraciones/App/Finalizacion/RepartidorRecompensasExploracion.php:257`, `src/CombateEntrenadores/App/OtorgarRecompensasEntrenador.php:116`, `src/Reclutamiento/App/ServicioCaptura.php:28` (al procesar derrotados), más `RECLUTADO` en `CrearReclutado.php:34`, `ReclutadoController.php:244`, `CrearEquipoInicial.php:135`.
- El pool de exploración sí filtra por nivel: `src/Exploraciones/App/Procesamiento/CargadorPoolHabitat.php:45` (`wherePivot('level', $exploracion->nivel)`).
- **Peso de aparición = `capture_rate / hatch`** (`PokemonDelPool::peso()`); `capture_rate <= 0` excluye. Verificado: **ningún Pokémon del pool tiene `capture_rate <= 0`**; mínimo 3 (69 filas con tasa 3). No hay especies imposibles por tasa cero en los datos.
- Reparto de rareza en el pool: 248 filas con tasa 45, 69 con tasa 3, 44 con 60, etc. Los de tasa 3 son ~15× más raros, lo que alarga mucho el «avistar todo».
- `RecompilarHabitatJsonJob` guarda **todo el hábitat, sin nivel** (`app/Jobs/RecompilarHabitatJsonJob.php:41-60`), pero `Habitat::pokemon()` expone el pivote `level`. La UI de hábitat ya tiene la estructura por niveles (`HabitatsController.php:67-69`).
- Riesgo detectado, sin confirmar contra BD: **15 filas apuntan a formas no regionales** (`10100 raichu`, `10164 slowpoke`, `10165 slowbro`, `10172 slowking`, `10174 zigzagoon`, `10175 linoone`, `10112 grimer`, `10113 muk`, `10161 meowth`, `10179 yamask`, `10162 ponyta`, `10163 rapidash`, `10169 articuno`, `10170 zapdos`, `10171 moltres`) cuyo `name` en el CSV no lleva sufijo de región; `PokemonSeeder::regionOf()` (`database/seeders/PokemonSeeder.php:239-247`) solo detecta sufijos `-alola/-galar/-hisui/-paldea`, y las 3 que sí lo llevan (`10241 sliggoo-hisui`, `10242 goodra-hisui`, `10253 wooper-paldea`) se insertan con cadena sintética `10000 + región*1000 + chain`. Esas 15 probablemente **no existen en la tabla `pokemon`** y, con FK `pokemon_id → pokemon` (`database/migrations/2026_02_24_201538_create_pokemon_habitat_table.php:17`), el seeder de hábitat fallaría o las filas se perderían. **Hay que verificar en la BD real antes de fijar el objetivo de «avistar todo un nivel».** Afecta a los hábitats 28 (L2), 33 (L1/L2), 34 (L1/L2/L3), 37 (L1/L3), 39 (L2/L3) y 49 (L2).
- `ESPECIES_NO_SALVAJES = [132, 201, 235]` (Ditto, Unown, Smeargle) se borran al sembrar hábitats (`PokemonHabitatSeeder.php:19,29-37`): **0 filas afectadas** en el CSV, pero conviene saberlo.
- Consecuencia: el objetivo «avistar todo un nivel» se mide en **IDs de Pokémon (formas incluidas)**, no en especies. Mediana 5-7 por nivel, pero con cola muy larga (hasta 91) y niveles triviales de 1 especie.

### 4. Caramelos
- Ya existe la clave canónica `familia:{evolutionChainId}` y el helper `ItemCatalogo::keyFamilia()`; se usa en `OtorgarRecompensasRuta::caramelosFamiliaDe()` (línea ~259) y en recompensas de exploración. También existen claves `ev:{stat}` y `tipo:{slug}`.
- No he podido verificar todavía el punto exacto de concesión/`player_inventory` (pendiente). Lo relevante: **la recompensa plana de 25/50/100 no puede repartirse «entre familias» de forma natural**, porque el caramelo de familia es un ítem por `chain_id`, no un saldo genérico. Opciones: (a) un caramelo de la familia dominante del nivel, (b) N caramelos repartidos entre las familias del nivel, (c) convertir a caramelos EV/tipo. Con 56 % de niveles ≤5 familias y mediana 7, repartir 25 entre familias da 1-4 caramelos por familia, cifra casi irrelevante.

### 5. Economía (cálculo directo, aún sin comparativa cerrada)
- Pagando 25/50/100 por **cada nivel existente**: 44×25 + 44×50 + 37×100 = 1.100 + 2.200 + 3.700 = **7.000 caramelos** por jugador (universo completo).
- Pagando **una sola vez por hábitat** (el nivel máximo alcanzado, o el nivel 3 si existe): 37×100 + 7×50 = **4.050**.
- Referencia de nidos 25/75/225/450/900 y refugios hasta 2.700: 7.000 es **~2,6× el techo de refugios** y ~7,8× el nido máximo; si se suma a todo lo demás, es claramente **inflacionario** salvo que se restrinja a un subconjunto de hábitats (p. ej. solo los de campaña/desbloqueo) o se pague una vez por hábitat. Con 56 hábitats y niveles triviales de 1 especie, pagar 7.000 sin fricción es demasiado.

## Pendiente (no completado)
1. Verificar en la **BD real** si las 15 formas huérfanas existen en `pokemon` y en `pokemon_habitat` (bloqueante para el objetivo).
2. Leer la concesión real de caramelos de familia (`player_inventory`, `ItemCatalogo`, `OtorgarRecompensas*`) y confirmar si admite reparto plano.
3. Cerrar la comparativa completa con Sendas y el resto de economía (solo tengo nidos/refugios de referencia del enunciado).
4. Calcular el **esfuerzo esperado** (colector de cupones ponderado por `capture_rate/hatch`) para avistar un nivel típico (7 especies) y uno extremo (91).
5. Redactar el informe final en `.opencode/work/TASK-046/analysis.md`.

## Recomendación inmediata
No fijar la recompensa 25/50/100 hasta resolver el punto 1. Si las formas huérfanas no existen en la BD, el nivel 2 de Legendarios (91 «especies») es inavistable y el objetivo debe contarse sobre `pokemon` realmente sembrados. Con los datos actuales, la propuesta es razonable **solo** si se paga una vez por hábitat y se limita a hábitats con ≥2 especies; en su forma literal (7.000) es inflacionaria.

Las 5 decisiones para el arquitecto quedan aplazadas hasta completar los puntos 1-3.

---

## Parte B — Población, eventos y ledger

**Nota de proceso:** se alcanzó el máximo de pasos del agente con la investigación ya completada. El informe siguiente resume lo verificado con evidencia; no se modificó ningún archivo.

```text
RESUMEN
No existe sistema de eventos, de población ni de misiones: los únicos artefactos son
dos diseños (TASK-043 misiones, TASK-044 facciones) sin migraciones ni código. El spawn
es 100 % estático: peso = capture_rate/hatch recalculado en cada llamada desde
pokemon_habitat, sin estado ni contadores. El único modificador de peso real es el
refugio de granja, y solo en exploraciones (no en ruta ni entrenadores). El cierre de
batalla sí centraliza recompensas en PresentadorResultadoBatalla, pero no emite ningún
evento de dominio ni persiste derrotados. La Pokédex sí registra avistamientos por
usuario y la vista de hábitat ya recibe los ids avistados, así que «12/18 especies»
es calculable hoy sin backend nuevo. Un evento de 3 días es viable sin cron usando
ventana on-read con Europe/Madrid (ya configurado), pero exige tabla de evento,
ventana y contadores por usuario. El hueco crítico del ledger es que mission_events
tiene unique (user_id,event_type,source_id): un combate debe aportar los conteos por
familia en payload, o emitir una fila por slot.
```

## REQUISITOS

- **Funcionales:** evento aleatorio con ventana de 3 días; objetivo «derrota X de la familia Y»; reducción real de la sobrerrepresentación; misiones que recompensen progreso (niveles de hábitat, familias, Pokédex, medallas, pisos, nidos, Sendas); recompensa 25/50/100 caramelos por completar nivel 1/2/3 de hábitat.
- **No funcionales:** idempotencia (un combate no cuenta dos veces), resolución on-read sin depender del scheduler (solo hay un comando programado), zona horaria `Europe/Madrid`, recompensas dentro de la economía existente, sin estado global por usuario bloqueante.
- **Criterios de aceptación (derivados):** el peso de la familia objetivo es verificable y reversible; el contador baja al derrotar y no por capturar; la ventana caduca sin castigo; la recompensa se paga una sola vez; el panel muestra tiempo restante correcto en Madrid; un jugador ausente 3 días no pierde nada permanente.
- **Ambigüedades:** si «derrota» cuenta solo victorias de ruta/entrenador o también exploración y mazmorra; si la población baja por derrotar o por capturar; si el evento es global (mismo para todos) o por usuario; si «avistar nivel completo» exige `visto` o `atrapado`; si los 25/50/100 son por nivel y una vez, o repetibles.

## ALCANCE AFECTADO

| Ámbito | Elementos |
|---|---|
| Módulos | `CombateRuta`, `CombateEntrenadores`, `Exploraciones`, `Granjas`, `Shared`, `Habitats`; módulos nuevos propuestos `Misiones` y (TASK-044) `Facciones` |
| Archivos núcleo | `PresentadorResultadoBatalla.php`, `GeneradorEquipoRuta.php`, `GeneradorEquipoEntrenador.php`, `CargadorPoolHabitat.php`, `ResolverBonosFamilia.php`, `OtorgarRecompensasRuta.php`, `EscritorEventosResultado.php`, `HabitatsController.php` |
| Tablas/contratos | `pokemon_habitat` (level 1-3), `pokedex` (visto/atrapado por usuario), `gym_progress`, `dungeon_progress`, `exploraciones_activas.eventos` (jsonb con `derrotados`), `habitats.pokemons` (json cacheado); tablas nuevas: `mission_events`, `mission_event_applications`, evento/ventana/contadores |
| Vistas/endpoints | `habitats/partials/columna-derecha.blade.php` (niveles y especies), `habitats/show.blade.php` (isSighted), `pokedex/index.blade.php` (counts), `misiones/index.blade.php` (stub Alpine sin backend), nav de misiones comentado |

## EVIDENCIA

| Archivo:línea | Hallazgo |
|---|---|
| `src/Exploraciones/Domain/ValueObjects/PokemonDelPool.php:56-69` | `peso()` = `capture_rate/hatch`; `pesoOverride` tiene prioridad; `capture_rate<=0` excluye |
| `src/Exploraciones/Domain/ValueObjects/PoolHabitat.php:63-83` | `ponderado()` reconstruye la lista desde el pool; sin estado |
| `src/Exploraciones/Domain/ValueObjects/ColeccionPoolPonderado.php:55-78` | `elegirConAleatorio()`: única selección del negocio |
| `src/CombateRuta/App/GeneradorEquipoRuta.php:64-91` | 3 tiradas **con reemplazo**, azar real por slot, sin semilla |
| `src/CombateRuta/App/GeneradorEquipoRuta.php:138-165` | `especiesPonderadas()`: añadido local sin commit (no es código base) |
| `src/CombateRuta/App/GeneradorEquipoRuta.php:170-213` | Pool por `pivot level`; pondera solo capture_rate/hatch: **ignora refugio y Senda** |
| `src/CombateEntrenadores/App/GeneradorEquipoEntrenador.php:61-111` | Semilla `crc32(habitat\|nivel\|entrenador\|fecha)`; estable por día |
| `src/CombateEntrenadores/App/GeneradorEquipoEntrenador.php:150-175` | ≥3 especies: 3 únicas barajadas; <3: ponderado con reemplazo |
| `src/Exploraciones/App/Procesamiento/CargadorPoolHabitat.php:31-68` | Único punto que inyecta `peso` externo: `factorAparicion` (l. 63) |
| `src/Granjas/App/ResolverBonosFamilia.php:83-101` | Refugio exclusivo → 1.0/0.0 por cadena; insalubres → 0.0 |
| `src/Granjas/Domain/CalculadoraBonosGranja.php:87-108,180-189` | Multiplicadores 1.25/1.5/2.0/2.5; nivel 5 exclusivo |
| `src/Exploraciones/Domain/SimuladorEncuentros.php:33-47` | Probabilidades de evento (45/20/15/10/10) y subtipos |
| `app/Livewire/Presenters/PresentadorResultadoBatalla.php:58-63` | `match` por meta: gimnasio/mazmorra/ruta/entrenador |
| `.../PresentadorResultadoBatalla.php:246-286` | Ruta: mapea `speciesId()` (l. 253) y delega en `RegistrarResultadoRuta` (l. 261) |
| `src/CombateRuta/App/RegistrarResultadoRuta.php:40-42` | Derrota → `null`; no se emite ni persiste nada |
| `src/CombateRuta/App/OtorgarRecompensasRuta.php:149,248-253` | Avistados por `ActualizarPokedexJob` × especie única derrotada |
| `app/Jobs/ActualizarPokedexJob.php:35-62` | `updateOrCreate` por (user,pokemon); no degrada atrapado; recompila hábitat |
| `app/Jobs/RecompilarHabitatJsonJob.php:42` | Consulta `Pokedex` sin `user_id` explícito: en cola (sin Auth) el scope está inactivo |
| `src/Exploraciones/App/Finalizacion/EscritorEventosResultado.php:62` | Único registro de derrotados: `eventos['derrotados']` al cerrar expedición |
| `routes/console.php:13` | Único job programado: `exploraciones:procesar` cada 5 min |
| `config/app.php:70` | `timezone = Europe/Madrid` |
| `src/Habitats/Presentation/DTOHabitatDetalle.php:22-24` | `levels` agrupados 1-3 + `min_lvl_1/2/3` |
| `resources/views/habitats/partials/columna-derecha.blade.php:258-272` | «N pokémon» por nivel y sprites ocultos si no avistado |
| `resources/views/habitats/show.blade.php:50,1206-1208` | `sightedPokemonIds` en cliente + `isSighted()` |
| `app/Http/Controllers/HabitatsController.php:64-79` | Construye `sightedPokemonIds` desde `Pokedex` del usuario |
| `resources/views/pokedex/index.blade.php:12` | «X vistos · Y atrapados · Z totales» |
| `app/Providers/DatagridServiceProvider.php:229-246` | Agregados de Pokédex por usuario |
| `app/Models/DungeonProgress.php:49-66` | **No usa `BelongsToUser`** (a diferencia de `GymProgress`) |
| `app/Models/Concerns/BelongsToUser.php:23-30` | Scope por usuario; inactivo sin Auth (CLI/jobs) |
| `.opencode/work/TASK-043/architecture.md:428-436` | `mission_events` unique `(user_id,event_type,source_id)`; ledger vs contadores; aplicación única |
| `.opencode/work/TASK-043/architecture.md:461` | Punto de emisión único propuesto: `PresentadorResultadoBatalla::procesar()` |
| `.opencode/work/TASK-044/architecture.md:224-226` | Mismo ledger; `faccion_combate`/`faccion_piso`; `source_id = battleId` |

## DEPENDENCIAS

- `PoolHabitat`/`PokemonDelPool`/`ColeccionPoolPonderado` son compartidos por ruta, entrenador y exploración: cualquier ponderador nuevo debe entrar por ahí o por el `peso` inyectado en `CargadorPoolHabitat`.
- `ResolverBonosFamilia` (Granjas) acopla spawn de exploración a refugios; ya existe el patrón «factor por cadena evolutiva».
- `Pokedex` + `pokemon_habitat.level` son la fuente para «nivel completo».
- `PresentadorResultadoBatalla` es el embudo real, pero no conoce familias ni hábitat en el caso ruta/gimnasio (solo `habitat_id` en meta).
- `ActualizarPokedexJob` es asíncrono: cualquier misión basada en «avistar» hereda el desfase de cola.
- TASK-043/044 asumen `Reloj`, `InventarioJugador` y ledger; ninguno existe aún.

## DUPLICIDADES Y DEUDA TÉCNICA

- Cuatro rutas de otorgamiento de recompensas y captura (`OtorgarRecompensasRuta`, `OtorgarRecompensasEntrenador`, mazmorra, exploración): emitir eventos en cada una duplicaría lógica; debe centralizarse.
- `CapturarPokemonJob`, `ServicioCaptura` y `ReclutadoController` actualizan Pokédex por caminos distintos.
- `RecompilarHabitatJsonJob` mezcla usuarios potencialmente y cachea información per-usuario en una tabla global (`habitats.pokemons`): el dato ya no es fiable para UI.
- `DungeonProgress` sin scope de usuario: consultas de progreso deben filtrar a mano.
- `resources/views/misiones/index.blade.php` es un stub Alpine sin backend: reescribir, no parchear (ya advertido en TASK-043).
- Cambios locales sin commit en `GeneradorEquipoRuta` (método `especiesPonderadas`) y en 28 archivos más: no interpretarlos como base.

## RIESGOS

| Sev. | Riesgo | Impacto | Mitigación |
|---|---|---|---|
| alto | `unique (user_id,event_type,source_id)` con un solo `battleId` para 3 derrotados | Solo cuenta 1 de 3, o pierde familias distintas | Payload con conteo por familia, o `source_id = battleId:slot` |
| alto | Doble emisión (Livewire re-render / reintento de job) | Progreso y recompensa duplicados | Ledger primero + transacción + `lockForUpdate`; nunca contar en la vista |
| alto | Peso del evento aplicado solo en exploración | El objetivo se percibe roto en ruta/entrenador | Definir explícitamente dónde aplica y mostrarlo en UI |
| alto | Farmeo: derrotar en ruta es infinito y gratis | Contador trivial → recompensa inflada | Tope por día por familia, o exigir hábitat concreto |
| medio | Ventana de 3 días con `Europe/Madrid` y jugadores en otra zona | Caducidad percibida injusta | Resolver corte on-read con `Reloj`; mostrar cierre absoluto |
| medio | Evento global vs por usuario | Global: carrera de conteo compartido; individual: difícil de comunicar | Global con contadores por usuario, o por usuario con semilla |
| medio | Evento caduca a mitad de exploración (tick cada 5 min) | Progreso perdido o recompensa huérfana | Contar por timestamp del evento, no por estado del panel |
| medio | Jugador ausente 3 días | Sensación de pérdida | Sin castigo, sin rachas; recompensa solo al completar |
| medio | Inflación de caramelos (25/50/100 × 56 hábitats × 3 niveles) | Rompe curva de nidos/Sendas | Pago único por (user,habitat,nivel) con ledger; tope semanal |
| bajo | `mission_events` sin retención | Crecimiento | Índice `(user_id, occurred_at)` y purga por periodo en v2 |
| bajo | `ActualizarPokedexJob` en cola | Misión «avistar» con retraso | Aceptar desfase o contar al despachar |

## OPCIONES

1. **Solo contadores (sin tocar spawn).** El evento define familia Y y objetivo X; se persiste `(user, event, family, kills, target)`. El peso no cambia. Barato, idempotente, pero la «sobrerrepresentación» es narrativa, no real.
2. **Peso dinámico por evento (recomendada a valorar).** Un `factorPoblacion(user, event, chain)` se multiplica en el único punto que ya acepta override (`CargadorPoolHabitat:63`) y, si se quiere ruta, se añade el mismo factor a `GeneradorEquipoRuta::ponderar`. Estado: contadores + factor derivado (p. ej. `1 + k·(kills/target)` acotado), sin simulación ecológica. Reversible y auditable.
3. **Población persistida con regeneración.** Tabla `habitat_population(user, habitat, chain, nivel, last_seen)` que decae/recupera con el tiempo. Más realista, exige job o cálculo on-read y multiplica el estado; solo justificable si el evento se repite.

## SUPOSICIONES

- «Derrota X de la familia Y» significa ganar combates contra esa familia, no capturarla.
- El evento es de calendario (3 días consecutivos), no 72 h desde la primera conexión.
- Los 25/50/100 son pago único por nivel de hábitat completado.
- «Avistar nivel completo» usa `pokedex.visto`, no `atrapado`.
- No se requiere que el evento sea simultáneo para todos los usuarios.

## PREGUNTAS BLOQUEANTES

- ¿El evento es global o por usuario? Determina tabla, concurrencia y UI.
- ¿«Derrota» cuenta en ruta, entrenador, gimnasio, mazmorra y exploración, o solo ruta?
- ¿La población debe alterar el spawn real o basta el contador con narrativa?
- ¿La recompensa de nivel se paga una vez por siempre o por evento/temporada?
- ¿Se acepta desfase de cola en «avistar» o debe ser síncrono?

## RECOMENDACIÓN

No construir simulación ecológica: el juego no tiene estado de población y el spawn se recalcula por consulta. Un evento de 3 días debe ser **una fila de evento con ventana absoluta + contadores por usuario**, resuelto on-read con `Europe/Madrid` (ya configurado) y sin cron, coherente con la decisión de TASK-043. El peso, si se quiere real, entra por el override de `CargadorPoolHabitat` y, conscientemente, también en `GeneradorEquipoRuta`; documentar dónde aplica. El ledger de TASK-043/044 sirve, pero exige resolver el `unique` por `battleId` con conteos agregados por familia. La recompensa 25/50/100 necesita su propio ledger `(user, habitat, nivel)` para no pagarse dos veces. La UI de «12/18» es casi gratis: `sightedPokemonIds` ya llega al cliente.

**Decisiones para el arquitecto (máx. 5):**

1. Evento **global con contadores por usuario** vs evento por usuario.
2. Población **solo contador** vs **factor de peso aplicado** (y en qué modos: exploración, ruta, ambos).
3. Contrato del ledger: `event_type` + payload agregado por familia vs `source_id = battleId:slot`.
4. Alcance de «derrota» (qué modos cuentan) y tope antifarmeo.
5. Recompensa de nivel de hábitat: pago único permanente vs repetible por temporada, y su tabla de idempotencia.