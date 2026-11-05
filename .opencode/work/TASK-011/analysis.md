# TASK-011 — Análisis del sistema de bonguris, bayas, fabricación y uso de objetos en combate

> **Estado:** `ANALYZING` · **Rama:** `master` · **Fecha:** 2026-11-04  
> **Alcance:** solo análisis. No se ha modificado ningún archivo, no se han ejecutado tests ni migraciones.  
> **Convención:** **HECHO** = verificado en código con `archivo:línea`; **INFERENCIA** = deducción razonada; **PROPUESTA** = diseño recomendado, no implementado.

---

## 1) Resumen ejecutivo

El proyecto ya tiene tres piezas reutilizables:

1. **Inventario unificado** `player_inventory` con claves de texto y unique `(user_id, item_key)` — HECHO: `database/migrations/2026_08_29_000007_create_player_inventory_table.php:15-23`, `app/Models/PlayerInventory.php:19-29`.
2. **Motor de combate con objetos equipados**: catálogo de 8 objetos, 2 bayas (`sitrus_berry`, `lum_berry`), efectos automáticos, cargas y consumo — HECHO: `src/Battle/Domain/Catalog/CatalogoObjetos.php:20-29`, `src/Battle/Domain/Enums/ClaveItem.php:9-16`, `app/Providers/BattleEffectServiceProvider.php:117-128`.
3. **Exploraciones con recompensas y persistencia** de caramelos/capturas/EXP — HECHO: `src/Exploraciones/App/PersistirRecompensas.php:34-48`, `src/Exploraciones/App/FinalizarExploracionHandler.php:136-200`.

Lo que **no existe**:

- No existe una **acción de «usar objeto»** en combate. `AccionBatalla` solo modela ataque + movimiento (`src/Battle/Domain/AccionBatalla.php:12-21`) y `DTOAccionBatalla` se crea siempre con `type: 'attack'` (`app/Livewire/Combate.php:809-815`, `:1094-1100`).
- No se puede **seleccionar un aliado** como objetivo: la vista solo marca seleccionables los rivales (`$teamIdx === 1`) — HECHO: `app/Livewire/Presenters/BatallaVistaPresenter.php:112-121`.
- No existe **forja, recetas, cola de fabricación ni consumo de materiales** más allá de las mejoras de granja — HECHO: `src/Granjas/` no contiene recetas; `src/Granjas/context.md:6-11` describe solo nidos/refugios/complementos.
- No hay **iconos de pociones/revivires** en `public/images`.
- No hay **control de propiedad** al equipar objetos: el selector usa todo el catálogo y no consulta inventario — HECHO: `app/Support/EquiposVistaPresenter.php:35-42`, `app/Http/Controllers/TeamController.php:225`, `src/Equipos/App/ActualizarObjetosEquipo.php:34-36`.

La especificación es viable, pero exige tocar el núcleo del combate (nuevo tipo de acción), el contrato de recompensas de exploración (aditivo), el inventario (nuevas familias de claves), la UI de equipos (propiedad + consumo) y crear el subdominio de fabricación. El mayor riesgo no es técnico sino de **balance y de propiedad de objetos**: hoy equipar un objeto no descuenta nada del inventario.

---

## 2) Estado actual por área

### 2.1 Inventario

**HECHO**
- Tabla `player_inventory`: `id`, `user_id` FK cascade, `item_key` varchar(64), `cantidad` unsigned int default 0, timestamps, unique `(user_id, item_key)` — `database/migrations/2026_08_29_000007_create_player_inventory_table.php:15-23`.
- Modelo con `BelongsToUser`, `$fillable = [user_id, item_key, cantidad]`, cast `cantidad => integer` — `app/Models/PlayerInventory.php:15-29`.
- Claves canónicas actuales: `familia:{chain_id}`, `ev:{stat}`, `tipo:{slug}` — `app/Support/ItemCatalogo.php:27-40`.
- Resolución de presentación sin tabla: `ItemCatalogo::resolve()` resuelve prefijos y cae a `Desconocido` para cualquier clave no reconocida — `app/Support/ItemCatalogo.php:84-107`, `:174-181`.
- Persistencia de recompensas: `firstOrCreate(...)->increment('cantidad', $cantidad)` — `src/Exploraciones/App/PersistirRecompensas.php:80-90`.
- Granjas lee/consume `familia:*` y `ev:*` con `lockForUpdate` — `src/Granjas/App/InventarioGranja.php:23-57`, `:99-116`.

**INFERENCIA**
- El inventario soporta cualquier clave nueva sin migración, siempre que respete el límite de 64 caracteres y el prefijo. Es el punto natural para `baya:*`, `bonguri:*`, `pocion:*`, `mejora:*`, `resistencia:*`.
- No hay tabla de catálogo: el «catálogo» es código (`ItemCatalogo` + `CatalogoObjetos`). Añadir familias nuevas implica ampliar ambos, no crear tablas.

### 2.2 Catálogo de objetos y equipamiento

**HECHO**
- `CatalogoObjetos` define 8 objetos con `nombre` y `cargas` (`-1` pasivo, `>0` consumible): `life_orb`, `leftovers`, `focus_sash`, `sitrus_berry`, `lum_berry`, `choice_band`, `choice_specs`, `assault_vest` — `src/Battle/Domain/Catalog/CatalogoObjetos.php:20-29`.
- `iconoDe()` devuelve cadena vacía para todos — `src/Battle/Domain/Catalog/CatalogoObjetos.php:41-44`; por eso la vista muestra solo el nombre (`resources/views/livewire/_pokemon-card.blade.php:16-19`).
- `ClaveItem` solo tiene 8 casos, 2 de baya — `src/Battle/Domain/Enums/ClaveItem.php:9-16`.
- `EstadoObjeto` encapsula clave, cargas, consumido y bloqueo; `consumir()` descuenta y limpia la clave al llegar a 0; `robarDe`/`intercambiarCon`/`reciclar` existen — `src/Battle/Domain/ValueObjects/EstadoObjeto.php:59-66`, `:97-112`, `:133-179`, `:181-194`.
- `Combatiente` delega: `item()`, `itemCargas()`, `itemActivo()`, `consumirCargaObjeto()`, `destruirObjeto()`, `robarObjetoDe()`, `intercambiarObjetoCon()`, `reciclarObjeto()` — `src/Battle/Domain/Combatiente.php:816-879`; `setItem()` en `:1382-1385`.
- Equipamiento persistido en `reclutados.obj_equipados` (json nullable) — migración `2026_05_27_000001_create_reclutados_table.php:21`; se escribe como lista de una sola clave — `src/Equipos/App/ActualizarObjetosEquipo.php:34-36`.
- El selector de equipos usa **todo** el catálogo, sin filtrar por inventario — `app/Support/EquiposVistaPresenter.php:35-42`; la validación HTTP solo comprueba que la clave pertenezca al catálogo — `app/Http/Controllers/TeamController.php:221-230`.
- El objeto equipado se aplica al construir la batalla: `setItem()` + `crearItem()` + alta del efecto — `src/Battle/Domain/EquipoBatalla.php:213-219`. También en exploración 1v1 — `src/Exploraciones/App/CombateExploracion.php:53-65`.

**INFERENCIA**
- Hoy **equipar no consume inventario**. Un jugador puede poner `life_orb` a los 3 miembros sin tener ninguno. Es un agujero funcional y de balance, no solo de la nueva feature.
- El objeto equipado es «infinito» entre batallas: las cargas viven en `EstadoObjeto` serializado en sesión; al terminar la batalla no se escribe nada en `player_inventory`.

### 2.3 Combate

**HECHO — turnos**
- Una acción por combatiente vivo y ronda; `getNextActor()` elige el vivo con acción pendiente de mayor velocidad efectiva; empates por orden de inserción — `src/Battle/Domain/GestorTurnos.php:81-129`.
- `startNewRound()` incrementa ronda, resetea contadores y avanza cargas de movimiento — `src/Battle/Domain/GestorTurnos.php:55-73`.
- `consumeAction()` incrementa el contador (o consume acción extra) — `src/Battle/Domain/GestorTurnos.php:159-170`.
- Fases de UI: `init`, `preparacion`, `player_target`, `player_move`, `battle_over` — `src/Battle/Domain/Enums/FaseCombate.php:31-38`.

**HECHO — acciones**
- `AccionBatalla` solo contiene atacante, defensor, movimiento, clima, terreno, previsualización y equipo defensor — `src/Battle/Domain/AccionBatalla.php:12-21`.
- `ServicioEjecucionBatalla::calcularYAplicarDano()` ejecuta exclusivamente movimientos (daño, multiobjetivo, drenaje, recoil, autodestrucción) — `src/Battle/Domain/ServicioEjecucionBatalla.php:45-127`.
- `AgregadoBatalla::ejecutarAccion()` (modo automático) también ejecuta solo movimientos — `src/Battle/Domain/AgregadoBatalla.php:791-830`.
- El flujo manual resuelve `pendingAction` y llama a `calcularYAplicarDano`; después consume acción y movimiento — `app/Livewire/Combate.php:843-980`.

**HECHO — objetivos**
- La selección de objetivo del jugador solo permite rivales: `selectable` exige `$teamIdx === 1` — `app/Livewire/Presenters/BatallaVistaPresenter.php:112-121`.
- `previewTarget()`/`getTargetFromSelection()` aceptan team 0/1, pero la UI nunca marca aliados como seleccionables — `app/Livewire/Combate.php:1001-1025`, `:1247-1256`.
- Existen objetivos aliados en el dominio de movimientos (`ObjetivoMovimiento::ADJACENT_ALLY`, `ALLIES`, etc.) — `src/Battle/Domain/Enums/ObjetivoMovimiento.php:26-29`, pero no se usan para objetos.

**HECHO — objetos equipados en batalla**
- Al inicio de batalla, `EquipoBatalla::fromData()` aplica `item` y registra el efecto correspondiente — `src/Battle/Domain/EquipoBatalla.php:213-219`.
- `EfectoBayaSitrus` cura 25 % del HP máximo al bajar del 50 %, consume carga y loguea — `src/Battle/Domain/Effects/EfectoBayaSitrus.php:30-52`.
- `EfectoBayaLum` cura el estado mayor al inicio de ronda, consume carga y loguea — `src/Battle/Domain/Effects/EfectoBayaLum.php:28-43`.
- `ManejadorObjetosEquipados` aplica multiplicadores de daño (orbe vida ×1,3; cinta/gafas ×1,3) — `src/Battle/Domain/Chain/ManejadorObjetosEquipados.php:24-46`.

**HECHO — soporte de efectos temporales**
- `MultiplicadoresStats` tiene dos capas: base y temporal; `TURNOS_TEMPORALES = 5` — `src/Battle/Domain/ValueObjects/MultiplicadoresStats.php:10-25`.
- `aplicarTemporal(StatClave, factor)` multiplica el stack y reinicia la duración a 5 turnos — `:166-181`.
- `avanzarTemporales()` descuenta y limpia al llegar a 0 — `:189-205`.
- `AgregadoBatalla` avanza los temporales al final de cada ronda — `src/Battle/Domain/AgregadoBatalla.php:466-468`.
- **Esto ya implementa exactamente «+30 % durante 5 turnos»** para stats que existan en `StatClave` (ataque, defensa, at. esp., def. esp., velocidad, precisión, evasión) — `src/Battle/Domain/Enums/StatClave.php:7-15`.
- No existe `StatClave::CRITICO`; el crítico se modela con `EfectoAjustaCritico` (multiplicador) y `ManejadorCritico` — `app/Providers/BattleEffectServiceProvider.php:372`, `src/Battle/Domain/Chain/ManejadorCritico.php` (no leído en detalle).

**HECHO — curación/revivir**
- `revivir(float $fraccion)` revive a un debilitado, resetea estado/condiciones/multiplicadores y devuelve false si ya estaba vivo — `src/Battle/Domain/Combatiente.php:574-596`.
- `curarHpCantidad(float)` cura HP sin excedente — `:1730-1740`; `curarConExcedente()` convierte excedente en escudo — `:1747-1765`.
- No hay método para restaurar PP/usos: `EstadoMovimientos` solo tiene `restaurar(MapaConteo, MapaConteo)` y `reiniciar()` — `src/Battle/Domain/ValueObjects/EstadoMovimientos.php:79-89`.

**HECHO — persistencia de batalla**
- La batalla se serializa en sesión con `SESSION_VERSION = 20` y mínimo compatible 9 — `app/Support/BattleSessionService.php:47-60`, `:67-70`.
- Cualquier cambio de estructura de `Combatiente`, `AgregadoBatalla` o `DTOAccionBatalla` obliga a revisar/bump de versión y compatibilidad de payloads.

### 2.4 Exploraciones

**HECHO**
- `ResultadoRecompensas` contiene capturas + caramelos familia/EV/tipo + EXP; no hay colección de objetos — `src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php:44-58`.
- `CalculadorRecompensas::calcular()` produce ese resultado; los hallazgos se suman después con `sumarHallazgos()` — `src/Exploraciones/Domain/CalculadorRecompensas.php:51-85`; `ResultadoRecompensas::sumarHallazgos()` en `:69-84`.
- `PersistirRecompensas::persistir()` guarda caramelos, capturas y EXP; **no hay punto de entrada para objetos** — `src/Exploraciones/App/PersistirRecompensas.php:34-48`.
- `FinalizarExploracionHandler` calcula recompensas, hallazgos, pérdidas por derrota, persiste y marca regreso; es idempotente por `regreso !== null` — `src/Exploraciones/App/FinalizarExploracionHandler.php:72-120`, `:136-217`.
- Generación de eventos: 45 % encuentro, 20 % hallazgo, 15 % emboscada, 10 % contratiempo, 10 % neutral — `src/Exploraciones/Domain/SimuladorEncuentros.php:25-40`.
- Los hallazgos actuales son `caramelo_familia` (<33,33), `caramelo_ev` (<66,66) y `caramelo_tipo` (resto) — `src/Exploraciones/Domain/SimuladorEncuentros.php:262-302`.
- Contrato de resultado persistido: `capturados`, `caramelos_familia`, `caramelos_ev`, `caramelos_tipo`, `exp`, `exp_reclutado`, `resultado`, `duration_real`, `tiempo_perdido`, `incidentes` — `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php:28-39`, `:50-61`.
- `PresentadorExploraciones::toTerminada()` reconstruye ese contrato y lo pasa a la vista; también resuelve `objetos_perdidos` — `src/Exploraciones/Presentation/PresentadorExploraciones.php:238-330`, `:345-430`.
- La UI de eventos tiene una rama `hallazgo` que espera campos `candy_*`; un subtipo desconocido cae a `'Hallazgo registrado'` — `src/Exploraciones/Presentation/EventoExploracionVista.php:211-232`; vista `resources/views/exploraciones/_evento.blade.php:103+`.
- Hay TODO explícito: `app/Models/ExploracionActiva.php:11`, `app/Http/Controllers/ExploracionActivaController.php:254`, `routes/exploraciones.php:15`.

**INFERENCIA**
- Añadir bayas/bonguris como **nuevo subtipo de hallazgo** exige tocar `EventoExploracion` (añadir clave de item), `SimuladorEncuentros`, `EventoExploracionVista` y la vista.
- Añadirlos como **botín calculado al finalizar** (sin evento por hallazgo) solo exige `ResultadoRecompensas`, `PersistirRecompensas`, `Transformador` y `Presentador`. Es la vía MVP de menor riesgo.

### 2.5 Granja / Forja

**HECHO**
- `src/Granjas/` contiene: `AsegurarGranja`, `MejorarNido`, `MejorarRefugio`, `MejorarComplemento`, `AlternarCondicionesInsalubres`, `ResolverBonosFamilia`, `InventarioGranja`, `ObtenerGranja`, `AccionesGranja`, DTOs y colecciones — `src/Granjas/context.md:15-27`.
- Tablas: `farms`, `nests`, `shelters`, `complements` — `database/migrations/2026_10_26_000001_create_farm_tables.php:16-56`.
- `Granja` tiene `nidos()` y `refugios()`; no tiene complementos directos — `app/Models/Granja.php:43-54`.
- `Nido`: `farm_id`, `evolution_chain_id`, `level` 0–5 — `app/Models/Nido.php:14-36`.
- `Refugio`: `farm_id`, `evolution_chain_id`, `level`, `unhealthy_conditions`; `complementos()` hasMany — `app/Models/Refugio.php:15-47`.
- `Complemento`: `shelter_id`, `stat` (cast `StatEnum`), `level` 0–5 — `app/Models/Complemento.php:15-37`.
- Rutas: solo `GET /api/habitats/{habitat}/farm` y POST de mejoras — `routes/granjas.php:9-17`.
- **No existe ninguna entidad, tabla, ruta ni caso de uso de recetas/fabricación.**

**INFERENCIA**
- La forja no encaja en `MejorarNido`/`MejorarRefugio`: esas acciones consumen caramelos y suben nivel de una estructura. La fabricación consume ingredientes heterogéneos y produce items de inventario. Es un caso de uso distinto.
- Puede vivir en `src/Granjas/App` (mínimo cambio) o en un módulo `src/Forja` (mejor frontera). Ver sección 4.

### 2.6 UI

**HECHO**
- Combate: `resources/views/livewire/combate.blade.php` (255 líneas) y partials. No hay menú de objetos; el único rastro de objeto es el badge de `_pokemon-card.blade.php:16-19`.
- El presentador entrega `item`, `itemNombre`, `itemIcono`, `itemCargas`, `itemActivo`, `itemBloqueado` — `src/Battle/Domain/CombatienteVista.php:118-124`.
- Equipos: selector `<select>` por slot con `@foreach($objetos as $clave => $nombre)` — `resources/views/equipos/index.blade.php:180-190`; JS `itemDe()`/`guardarItemSlot()` hace PATCH a `/teams/{id}/items` — `:2883-2928`.
- Exploraciones: el modal de resultados consume `resultado.caramelos_*`, `capturados` y `objetos_perdidos`; el partial `_caramelo.blade.php` pinta imagen + cantidad — `resources/views/exploraciones/_caramelo.blade.php:1-26`.
- La vista de combate se sirve en `/combate` con `view('combate_page')` — `routes/web.php:30-33`.

### 2.7 Assets

**HECHO**
- `public/images/berry/`: 65 `.webp` con nombres ingleses (`oran-berry.webp`, `sitrus-berry.webp`, `liechi-berry.webp`, `occa-berry.webp`…).
- `public/images/bonguri/`: 7 PNG con prefijo `80px-Bonguri_<color>_EP.png` (amarillo, azul, blanco, negro, rojo, rosa, verde).
- No existen iconos de poción, revivir, restaurar, recipiente ni de los objetos fabricados.
- `public/images/` solo tiene: `berry`, `bonguri`, `candy_ev`, `candy_pokemon`, `candy_type`, `gem`, `iconos_webp`, `medallas`, `misc`, `moves`, `stone`, `type`.
- `public/images/misc/` contiene 6 archivos genéricos (`coin`, `farm`, `raid`, `sparkles`, `trainer`, `unknown`), ninguno de poción.

---

## 3) Gaps concretos

| # | Gap | Evidencia | Impacto |
|---|---|---|---|
| G1 | No existe acción de usar objeto en combate | `AccionBatalla.php:12-21`; `DTOAccionBatalla.php:17-23`; `ServicioEjecucionBatalla.php:45-127` | Bloquea «consumir en combate» y «dar a un aliado» |
| G2 | La UI solo permite elegir rivales | `BatallaVistaPresenter.php:112-121` | Bloquea curar/dar objeto a aliados |
| G3 | No hay fases de UI para objeto ni para objetivo aliado | `FaseCombate.php:31-38` | Hay que añadir estados y botones |
| G4 | Equipar no consume inventario ni valida propiedad | `TeamController.php:225`; `ActualizarObjetosEquipo.php:34-36`; `EquiposVistaPresenter.php:35-42` | Objetos infinitos y gratis; rompe la economía |
| G5 | El objeto equipado no se descuenta al consumirse en batalla | `EstadoObjeto.php:97-112`; `EfectoBayaSitrus.php:50` | Igual que G4, pero en consumo |
| G6 | No hay recompensas de objetos en exploración | `PersistirRecompensas.php:34-48`; `ResultadoRecompensas.php:44-58` | No hay fuente de bayas/bonguris |
| G7 | El contrato de resultado no contempla items | `TransformadorResultadoExploracion.php:50-61`; `PresentadorExploraciones.php:238-330` | La UI no puede mostrarlos |
| G8 | Un hallazgo nuevo sin rama de vista se muestra como «Hallazgo registrado» | `EventoExploracionVista.php:211-232` | Mala UX si se añade sin tocar presentación |
| G9 | No existe forja/recetas | `src/Granjas/` completo; `routes/granjas.php:9-17` | Toda la fabricación está por hacer |
| G10 | No hay iconos de pociones/revivires/recipiente/objetos fabricados | `public/images/` (listado) | Fase de iconos bloqueada hasta decidir fuente |
| G11 | Falta `roseli-berry.webp` (Baya Hibis/Hada) | `ls public/images/berry` no lo contiene | 1 de 36 bayas sin icono |
| G12 | `CatalogoObjetos::iconoDe()` devuelve vacío | `CatalogoObjetos.php:41-44` | La UI de combate no puede pintar iconos |
| G13 | No hay restauración de PP/usos | `EstadoMovimientos.php:79-89` | «Restaurar todo» y «Max Revivir» no pueden curar PP sin método nuevo |
| G14 | El crítico no está en `StatClave` | `StatClave.php:7-15` | «Crítico X» requiere efecto/condición aparte |
| G15 | No hay resistencia temporal por tipo | `EfectoModificadorDanio.php:27-38` es permanente; `CondicionDanio::TIPO_MOVIMIENTO` sin duración | «−30 % 5 turnos» requiere efecto/condición nueva |
| G16 | El contrato de sesión de batalla está versionado | `BattleSessionService.php:47-60` | Cambios de DTO/propiedades exigen bump y compatibilidad |
| G17 | La pérdida por derrota solo contempla caramelos/capturas | `FinalizarExploracionHandler.php:190-197`; `DTOObjetoPerdido.php:15-36` | Las bayas/bonguris no se perderían al ser derrotado |
| G18 | No hay límite ni contador de objetos por combate | No existe en `GestorTurnos` ni `Combatiente` | Riesgo de spam de pociones si no se limita por acción/inventario |

---

## 4) Modelo propuesto (dominio y datos)

### 4.1 Principios

1. **No crear una tabla de catálogo en MVP.** El catálogo es fijo y vive en código (`CatalogoObjetos` + `ItemCatalogo`), como ya ocurre con los 8 objetos actuales. Crear tablas exige seeders, migraciones y sincronización; no aporta valor hasta que exista administración de recetas.
2. **Reutilizar `player_inventory`.** Las claves nuevas son aditivas y no rompen las existentes.
3. **Mantener `reclutados.obj_equipados`** como almacenamiento del objeto equipado (1 por Pokémon), pero añadir validación de propiedad y consumo.
4. **La fabricación es un caso de uso transaccional**, no una entidad persistente, mientras sea instantánea.
5. **El dominio no conoce Eloquent.** La forja define puertos (`InventarioJugadorInterface`) e implementaciones en `Infra`.

### 4.2 Claves de inventario propuestas

| Familia | Patrón | Ejemplos | Nº |
|---|---|---|---|
| Baya | `baya:{slug_inglés}` | `baya:oran`, `baya:sitrus`, `baya:kelpsy`, `baya:roseli` | 36 |
| Bongurí | `bonguri:{color}` | `bonguri:rojo`, `bonguri:azul` | 7 |
| Poción/restaurador | `pocion:{slug}` | `pocion:pocion`, `pocion:super`, `pocion:maxima`, `pocion:curatotal`, `pocion:restaurar_todo`, `pocion:revivir`, `pocion:max_revivir` | 8 |
| Material | `material:recipiente` | `material:recipiente` | 1 |
| Mejora de stat | `mejora:{stat}` | `mejora:ataque`, `mejora:defensa_especial`, `mejora:precision`, `mejora:critico` | 7 |
| Reductor de stat | `reductor:{stat}` | `reductor:ataque`, `reductor:velocidad` | 5 |
| Resistencia de tipo | `resistencia:{slug_tipo}` | `resistencia:fuego`, `resistencia:defensa...` (no aplica), `resistencia:hada` | 18 |

- Longitud máxima: `resistencia:defensa_especial` no existe; el más largo real es `resistencia:defensa_especial` si se añadiera, 31 caracteres. `mejora:ataque_especial` = 23. Todos caben en varchar(64).
- `ItemCatalogo::resolve()` debe ampliarse con ramas para estos prefijos y un fallback por prefijo (`baya`, `bonguri`, `pocion`, `material`, `mejora`, `reductor`, `resistencia`) — HECHO del patrón actual: `app/Support/ItemCatalogo.php:84-107`.

### 4.3 Dominio propuesto

**Value Objects / Enums**
- `ClaveBaya` (enum string) con 36 casos y `archivoIcono(): string` → `/images/berry/{slug}-berry.webp`.
- `ClaveBonguri` (enum string) con 7 casos y `archivoIcono(): string`.
- `ClavePocion` (enum string) con 8 casos.
- `ClaveObjetoCombate` (enum string) con 30 casos (7 mejoras + 5 reductores + 18 resistencias).
- `CategoriaItem` (enum): `baya`, `bonguri`, `pocion`, `material`, `mejora`, `reductor`, `resistencia`.
- `DefinicionItem` (readonly): `clave`, `nombre`, `categoria`, `equipable`, `usableEnCombate`, `consumible`, `icono`.
- `RecetaFabricacion` (readonly): `clave`, `resultadoClave`, `resultadoCantidad`, `ingredientes` (`MapaConteo` o colección tipada), `categoria`.
- `CatalogoRecetas` (Domain): lista inmutable de las 46 recetas; métodos `obtener(string): RecetaFabricacion`, `todas(): RecetaFabricacionCollection`, `paraResultado(string)`.

**Entidades / agregados**
- No hace falta una entidad `Item` persistente. El inventario es un saldo por clave.
- `InventarioJugador` (servicio de dominio/aplicación): `saldo(userId, clave): int`, `consumir(userId, clave, cantidad): void`, `incrementar(userId, clave, cantidad): void`, `consumirVarios(userId, MapaConteo): void` con validación atómica.

**Servicios de aplicación**
- `ListarRecetas` → DTO con receta + ingredientes + saldo actual + `puedeFabricar`.
- `Fabricar` → Command/Handler o servicio; transacción + `lockForUpdate` + validación + descuento + incremento; devuelve `ResultadoFabricacion` (clave, cantidad, saldos).
- `OtorgarBotinExploracion` → incrementa items desde recompensas.

**Combate**
- `AccionObjeto` (readonly): `actorId`, `objetivoId`, `claveItem`, `tipoAccion` (`curar`, `curarEstado`, `revivir`, `potenciar`, `debilitar`, `resistir`).
- `ServicioUsoObjetos` (Domain): aplica el efecto sobre `Combatiente`/`AgregadoBatalla`, loguea y devuelve `ResultadoUsoObjeto` (curado, revivido, efecto aplicado, consumido).
- `ResultadoUsoObjeto` (readonly): `exito`, `motivo`, `curado`, `revivido`, `objetivo`, `clave`.
- `EfectoResistenciaTipoTemporal` o `CondicionVolatil` con `tipo` + `factor 0.70` + `turnos 5` (ver 5.4).
- `EfectoTemporalCritico` o condición equivalente (ver 5.4).

### 4.4 Datos: alternativa MVP vs modelo completo

**Alternativa MVP (recomendada)**
- Sin migraciones nuevas.
- Solo claves nuevas en `player_inventory`.
- Catálogo y recetas en código (`src/Forja/Domain/CatalogoRecetas.php`).
- Fabricación instantánea, transaccional, sin historial.
- Objeto equipado en `reclutados.obj_equipados` con validación de propiedad y descuento al equipar.
- Recompensas de exploración como botín calculado al finalizar (sin evento por hallazgo).

**Modelo completo (fase posterior, si se necesita administración)**
```text
item_catalog (id, key, nombre, categoria, equipable, usable_combate, icono, activo)
recipes (id, key, result_item_key, result_qty, categoria, activo)
recipe_ingredients (id, recipe_id, item_key, qty)
craft_jobs (id, user_id, recipe_key, qty, finish_at, claimed_at)   -- solo si hay tiempos
item_transactions (id, user_id, item_key, delta, reason, ref_type, ref_id, created_at) -- auditoría
player_inventory (item_key → FK item_catalog.id)                  -- migración destructiva, no MVP
```
- `player_inventory` ya tiene unique `(user_id, item_key)`; mantiene integridad sin FK.
- Si se añade `craft_jobs`, índice `(user_id, finish_at)` y unique opcional por job.
- Si se añade `item_transactions`, índice `(user_id, created_at)`.

### 4.5 Contratos de persistencia

- `player_inventory` sigue siendo la fuente de verdad de cantidades.
- `reclutados.obj_equipados` sigue siendo la fuente de verdad del equipado (una clave).
- Las recetas no se persisten en MVP.
- Las batallas no persisten consumo de inventario; el descuento ocurre en el momento de la acción (o al equipar), no al finalizar la batalla.
- **Decisión pendiente:** si se descuenta al equipar o al consumir. Recomendación: descontar al equipar (simplica y evita objetos gratis); si se quiere «devolver si no se usó», añadir devolución al cerrar batalla, pero eso exige persistir el `battleId` y el objeto reservado.

---

## 5) Flujo de combate propuesto

### 5.1 Modelo de turno

- Se mantiene **una acción por Pokémon vivo y ronda** (`GestorTurnos`).
- «En el turno de un aliado» = **la acción del Pokémon aliado se consume para usar el objeto**. No se añade una acción extra de entrenador.
- El actor debe pasar `puedeActuar()` (dormido, congelado, paralizado, confuso, etc. pueden impedirlo), igual que un movimiento — HECHO del patrón en `app/Livewire/Combate.php:224-260`.
- El uso de objeto **no consume movimiento** ni cargas de movimiento; solo consume la acción de ronda y el item del inventario.

### 5.2 Nuevas fases de UI

PROPUESTA:
```php
enum FaseCombate: string {
    case INICIO = 'init';
    case PREPARACION = 'preparacion';
    case SELECCION_OBJETIVO = 'player_target';
    case SELECCION_MOVIMIENTO = 'player_move';
    case SELECCION_OBJETO = 'player_item';          // elegir item del inventario
    case SELECCION_OBJETIVO_OBJETO = 'player_item_target'; // elegir aliado/rival
    case BATALLA_TERMINADA = 'battle_over';
}
```
- `player_item`: lista de items usables en combate con cantidad y si son aplicables al estado actual (p. ej. revivir solo si hay aliado debilitado).
- `player_item_target`: marca aliados y/o rivales según el item. Requiere ampliar `BatallaVistaPresenter::marcarInteraccion()` para aceptar `teamIdx` permitidos, en lugar de fijar `$teamIdx === 1`.
- Alternativa mínima: reutilizar `player_move` con un panel/tab de objetos. Menos estados, pero mezcla dos conceptos y complica el contrato de `currentMoves`. Recomendación: fases nuevas.

### 5.3 Acción y resolución

PROPUESTA:
1. El jugador pulsa «Objeto» → `phase = player_item`.
2. Selecciona item → `phase = player_item_target`.
3. Selecciona objetivo válido → se crea `AccionObjeto` y se guarda como `pendingAction`.
4. `commitAction()` detecta `pendingAction->tipo === 'item'` y llama a `ServicioUsoObjetos::usar()`.
5. El servicio:
   - valida que el item sea `usableEnCombate`;
   - valida objetivo (vivo/muerto según efecto);
   - aplica curación/estado/revivir/buff/debuff/resistencia;
   - registra log y evento de vista;
   - descuenta 1 del inventario (vía puerto/transacción);
   - consume la acción del actor (`turnManager->consumeAction`);
   - no consume movimiento.
6. `nextActor()` continúa.

**Cambio de contrato mínimo** (si no se quiere un DTO nuevo):
- Extender `DTOAccionBatalla` con `?string $itemKey = null` y hacer `move` nullable o añadir un `DTOMovimientoBatalla` vacío. Esto afecta a `Wireable` y a la sesión.
- **Recomendación:** crear `DTOAccionObjeto` separado y guardar en `AgregadoBatalla` un `pendingAction` que pueda ser `DTOAccionBatalla|DTOAccionObjeto`. Requiere bump de `SESSION_VERSION` (20 → 21) y compatibilidad de payloads antiguos (`BattleSessionService.php:47-60`).
- Alternativa más simple pero menos limpia: mantener `DTOAccionBatalla` y añadir `type='item'`, `itemKey`, reutilizando `defenderId` como objetivo y `move` con un movimiento ficticio. No recomendada por deuda.

### 5.4 Efectos por familia

| Familia | Efecto propuesto | Soporte actual | Trabajo |
|---|---|---|---|
| Poción / Súper / Hiper / Máxima | Curar HP (20/40/60/100 % del máximo, a decidir) | `curarHpCantidad()` / `curarConExcedente()` — `Combatiente.php:1730-1765` | Ninguno de dominio; solo servicio |
| Cura Total | Curar estado mayor + confusión | `setEstado(NONE)`, `quitarCondicion(CONFUSION)` — patrón en `EfectoBayaLum.php:38-41` | Ninguno |
| Restaurar Todo | HP completo + estado + PP | HP/estado sí; PP no | Añadir `restaurarUsos()`/`reiniciar()` en `EstadoMovimientos` y `Combatiente` |
| Revivir | Revivir aliado debilitado al 50 % | `revivir(0.5)` — `Combatiente.php:574-596` | Ninguno |
| Max Revivir | Revivir al 100 % + estado | `revivir(1.0)` | Ninguno |
| Mejora de stat | `+30 %` 5 turnos sobre aliado | `aplicarTemporal(stat, 1.30)` + avance en `AgregadoBatalla.php:466-468` | Ninguno para stats existentes |
| Mejora de crítico | `+1` nivel de crítico 5 turnos | No hay `StatClave::CRITICO` | Nuevo efecto/condición; revisar `ManejadorCritico` |
| Mejora de precisión/evasión | `+1` nivel (= ×1,3 temporal) | `StatClave::PRECISION`/`EVASION` + `aplicarTemporal` | Ninguno |
| Reductor de stat | `−30 %` 5 turnos sobre rival | `aplicarTemporal(stat, 0.70)` | Ninguno; ojo a stacks |
| Resistencia de tipo | `−30 %` daño recibido de ese tipo, 5 turnos | `EfectoModificadorDanio` existe pero es permanente; `CondicionDanio::TIPO_MOVIMIENTO` | Efecto/condición temporal nueva |
| Recuperar PP | +10 PP o todos | No existe | Nuevo método |

**Nota de balance:** `aplicarTemporal` multiplica stacks. Dos usos de «Ataque X» = ×1,69; tres = ×2,197. Dos reductores = ×0,49; tres = ×0,343. El clamp global es 0,25–4,0 (`MultiplicadoresStats.php:20-23`). Hay que decidir si los objetos de la misma stat **stackean** o **refrescan** (recomendación: refrescar duración sin multiplicar, para evitar combos degenerados).

### 5.5 Objetivos permitidos

PROPUESTA:
| Item | Objetivo |
|---|---|
| Poción/Súper/Hiper/Máxima/Cura Total/Restaurar Todo | Aliado vivo (incluido el actor) |
| Revivir/Max Revivir | Aliado debilitado |
| Mejora de stat | Aliado vivo |
| Reductor de stat | Rival vivo |
| Resistencia de tipo | Aliado vivo (incluido el actor) |

- La UI debe permitir **aliados** en `player_item_target`. Eso contradice el comportamiento actual (`BatallaVistaPresenter.php:112-121`), que solo marca rivales.
- El actor puede ser objetivo de sus propios objetos (curarse a sí mismo) sin gastar una acción extra.

### 5.6 Límites

- 1 objeto por acción de Pokémon.
- Sin límite global por combate en MVP; el límite real es el inventario.
- No se puede usar objeto si el actor no puede actuar.
- No se puede usar revivir si no hay aliado debilitado.
- No se puede usar curación si el objetivo está a HP máximo (o se permite y no consume; decisión).
- Los objetos equipados se activan automáticamente por sus hooks (`EfectoBayaSitrus`, `EfectoBayaLum`); no requieren acción.

### 5.7 Modo automático / IA

- `AgregadoBatalla::ejecutarAccion()` no tiene inventario ni noción de objeto. En MVP, **la IA no usa objetos**; los combates automáticos de exploración siguen igual.
- Si se quiere IA con objetos, haría falta inyectar un `InventarioIA` y ampliar `SelectorAccionIA`. Fuera de alcance MVP.

---

## 6) Exploración y fabricación

### 6.1 Obtención de bayas y bonguris

**Opción A (MVP recomendada): botín calculado al finalizar**
- Nuevo `CalculadorBotinExploracion` (Domain puro) que recibe: nivel del hábitat, duración, categoría/resultado, tipo de hábitat y un `callable` aleatorio.
- Devuelve `RecompensaItemCollection` (clave + cantidad) con:
  - 0–3 bayas ponderadas (tabla de pesos por rareza).
  - 0–1 bongurí (probabilidad fija o por hábitat).
- `FinalizarExploracionHandler` lo llama y lo añade a `ResultadoRecompensas`.
- `PersistirRecompensas` incrementa `player_inventory`.
- Ventaja: no toca `EventoExploracion`, `SimuladorEncuentros` ni la UI de diario. Menos granularidad, pero mucho menos riesgo.
- Desventaja: el jugador no ve «encontraste una baya X» en el diario; solo en el resumen final.

**Opción B (fase 2): hallazgo por evento**
- Nuevo subtipo `recoleccion` en `SimuladorEncuentros` con `item_key` y `cantidad`.
- Requiere añadir `itemKey` a `EventoExploracion` (`desdeArray`/`aArray`) — hoy no existe (`EventoExploracion.php:27-59`).
- Requiere rama nueva en `EventoExploracionVista::hallazgo()` (`:211-232`) y en `_evento.blade.php`.
- Permite diario rico y animaciones, pero toca más contratos.

**Recomendación:** Opción A para MVP; Opción B cuando exista UI de diario de objetos.

### 6.2 Ajuste de probabilidades

- Hoy el 20 % de eventos son hallazgo y se reparten 33,33 / 33,33 / 33,34 entre familia/EV/tipo (`SimuladorEncuentros.php:262-302`).
- Si se añade botín al final, no hay que tocar probabilidades de eventos; solo el cálculo final.
- Si se añade por evento, hay que decidir si se reduce el peso de caramelos o se añade un 5–10 % adicional. Recomendación: no reducir caramelos en MVP; añadir botín aparte.

### 6.3 Pérdida por derrota

- Hoy `objetosPerdidos()` calcula pérdidas de caramelos/capturas y `restarPerdidas()` las resta (`FinalizarExploracionHandler.php:190-197`).
- Las bayas/bonguris deben incluirse: `intdiv(cantidad, 2)` por grupo, y `DTOObjetoPerdido` debe aceptar `tipo = 'baya'|'bonguri'|'pocion'` con `label` e `imagen`.
- `PresentadorExploraciones::objetosPerdidosVista()` hoy resuelve solo `familia`, `ev`, `tipo`, `captura` (`:345-430`); hay que añadir ramas para las claves nuevas.
- Alternativa MVP: no perder bayas/bonguris en la primera iteración (solo caramelos). Menos realista, pero reduce alcance. Recomendación: incluirlos desde el inicio porque el sistema de pérdidas ya existe y el coste es bajo.

### 6.4 Fabricación

**Ubicación recomendada:** módulo nuevo `src/Forja/` con:
```text
src/Forja/
  Domain/
    CatalogoRecetas.php
    RecetaFabricacion.php
    DefinicionItem.php
    CategoriaItem.php
    Collections/RecetaFabricacionCollection.php
    DataTransferObjects/ResultadoFabricacion.php
    DataTransferObjects/RecetaDetalle.php
  App/
    ListarRecetas.php
    Fabricar.php
  Infra/
    Controllers/ForjaController.php
    Repositories/EloquentInventarioJugador.php
    routes.php
```
- `Fabricar` usa `UnitOfWork` (`app/Bus/DatabaseUnitOfWork`) y `lockForUpdate` sobre las filas de `player_inventory` ordenadas por clave para evitar deadlocks.
- Valida saldo de todos los ingredientes antes de descontar; si falta uno → `ViolacionReglaNegocio` 422 con mensaje claro.
- Descuenta ingredientes e incrementa resultado en la misma transacción.
- Devuelve `ResultadoFabricacion` con saldos nuevos y `puedeFabricar` recalculado.
- Rutas propuestas:
```text
GET  /api/forja/recetas
POST /api/forja/fabricar   body: { receta: string, cantidad?: int }
```
- UI: modal «Forja» en `resources/views/habitats/show.blade.php` (junto a Granjas) o página `/forja`. Recomendación: modal dentro de Granjas para MVP; página propia si crece.

**Alternativa mínima:** añadir `Fabricar` y `ListarRecetas` a `src/Granjas/App`, rutas bajo `/api/habitats/{habitat}/farm/craft`. Menos módulos, pero mezcla dos responsabilidades y hace más difícil extraer la forja después.

**Ubicación del catálogo de recetas:** `src/Forja/Domain/CatalogoRecetas.php`, con las 46 recetas como constantes/objetos. No tabla en MVP.

**Idempotencia:** la fabricación no es idempotente por naturaleza; cada POST descuenta. Para evitar dobles clics, el frontend debe deshabilitar el botón mientras hay request en vuelo; el backend puede aceptar un `Idempotency-Key` opcional (fuera de MVP).

---

## 7) Catálogo de recetas/efectos y ambigüedades

### 7.1 Recetas de pociones/restauradores

| # | Receta | Resultado | Ambigüedad |
|---|---|---|---|
| R1 | 3× Baya Aranja (Oran) | 1 Poción | — |
| R2 | 1× Baya Zidra (Sitrus) | 1 Poción | Compite con R1: ¿misma potencia? |
| R3 | 2× Poción | 1 Súper Poción | — |
| R4 | 3× Súper Poción | 1 Hiper Poción | — |
| R5 | 2× Hiper Poción | 1 Poción Máxima | — |
| R6 | 1× Poción Máxima + 1× Cura Total | 1 Restaurar Todo | ¿Restaura PP? |
| R7 | 1× Zreza + 1× Meloc + 1× Safre + 1× Perasi | 1 Cura Total | Cura los 4 estados clásicos; ¿incluye confusión? |
| R8 | 1× Cura Total + 1× Súper Poción | 1 Revivir | Revivir sin ingrediente «revivir»; es una decisión de diseño |
| R9 | 1× Revivir + 1× Restaurar Todo | 1 Max Revivir | ¿Diferencia real con Restaurar Todo? |

### 7.2 Bayas reductoras de stats + Recipiente

| Baya | Stat que reduce | Item resultante | Efecto propuesto |
|---|---|---|---|
| Algama (Kelpsy) | Ataque | `reductor:ataque` | −30 % 5 turnos al rival |
| Ispero (Qualot) | Defensa | `reductor:defensa` | −30 % 5 turnos |
| Meluce (Hondew) | Ataque Especial | `reductor:ataque_especial` | −30 % 5 turnos |
| Uvav (Grepa) | Defensa Especial | `reductor:defensa_especial` | −30 % 5 turnos |
| Tamate (Tamato) | Velocidad | `reductor:velocidad` | −30 % 5 turnos |

- **Ambigüedad fuerte:** en los juegos oficiales estas bayas reducen EVs fuera de combate, no stats en combate. La especificación las convierte en objetos de combate. Hay que confirmar magnitud, duración y objetivo.
- **Contradicción:** la especificación dice que estas bayas «no serán usables en batalla, solo sus fabricaciones». Por tanto, la baya en sí no se puede consumir ni equipar; solo el objeto fabricado.

### 7.3 Bayas potenciadoras + Recipiente

| Baya | Stat | Item resultante | Efecto propuesto |
|---|---|---|---|
| Lichi (Liechi) | Ataque | `mejora:ataque` | +30 % 5 turnos |
| Gonlan (Ganlon) | Defensa | `mejora:defensa` | +30 % 5 turnos |
| Aslac (Salac) | Velocidad | `mejora:velocidad` | +30 % 5 turnos |
| Yapati (Petaya) | Ataque Especial | `mejora:ataque_especial` | +30 % 5 turnos |
| Aricoc (Apicot) | Defensa Especial | `mejora:defensa_especial` | +30 % 5 turnos |
| Zonlan (Lansat) | Crítico +1 | `mejora:critico` | +1 nivel de crítico 5 turnos |
| Lagro (Micle) | Precisión y Evasión +1 | `mejora:precision` | +1 nivel en ambas 5 turnos |

- **Ambigüedad:** la especificación dice «Precisión X, Crítico X» como dos objetos, pero luego asigna a Lagro «precisión y evasión». ¿Se crean dos objetos (`mejora:precision`, `mejora:evasion`) o uno solo que sube ambas? Recomendación: un solo objeto `mejora:precision` que aplica +1 a precisión y +1 a evasión, como dice el texto.
- **Ambigüedad:** Zonlan en los juegos sube +2 niveles de crítico; la especificación dice +1. Prevalece la especificación, pero confirmar.
- **Ambigüedad:** el disparador oficial es «al bajar del 25 % de HP»; la especificación no lo menciona. Aquí se propone uso activo, no automático.

### 7.4 Bayas de tipo + Recipiente

18 recetas, una por tipo:

| Baya | Tipo | Item resultante | Efecto propuesto |
|---|---|---|---|
| Caoca (Occa) | Fuego | `resistencia:fuego` | −30 % daño recibido de Fuego, 5 turnos |
| Pasio (Passho) | Agua | `resistencia:agua` | ídem |
| Gualot (Wacan) | Eléctrico | `resistencia:electrico` | ídem |
| Tamar (Rindo) | Planta | `resistencia:planta` | ídem |
| Rimoya (Yache) | Hielo | `resistencia:hielo` | ídem |
| Pomaro (Chople) | Lucha | `resistencia:lucha` | ídem |
| Kebia (Kebia) | Veneno | `resistencia:veneno` | ídem |
| Acardo (Shuca) | Tierra | `resistencia:tierra` | ídem |
| Kouba (Coba) | Volador | `resistencia:volador` | ídem |
| Payapa (Payapa) | Psíquico | `resistencia:psiquico` | ídem |
| Yecana (Tanga) | Bicho | `resistencia:bicho` | ídem |
| Alcho (Charti) | Roca | `resistencia:roca` | ídem |
| Drasi (Kasib) | Fantasma | `resistencia:fantasma` | ídem |
| Anjiro (Haban) | Dragón | `resistencia:dragon` | ídem |
| Dillo (Colbur) | Siniestro | `resistencia:siniestro` | ídem |
| Baribá (Babiri) | Acero | `resistencia:acero` | ídem |
| Chilan (Chilan) | Normal | `resistencia:normal` | ídem |
| Hibis (Roseli) | Hada | `resistencia:hada` | ídem |

- **Ambigüedad:** «reduce mi daño recibido 30 % 5 turnos» no dice si es solo de ataques supereficaces (efecto oficial) o de cualquier ataque de ese tipo. Recomendación: cualquier ataque de ese tipo, porque el texto no restringe.
- **Ambigüedad:** si el objeto se usa sobre un aliado, ¿la resistencia es para ese aliado o para todo el equipo? El texto dice «mi daño recibido»; recomendación: solo el objetivo.

### 7.5 Recipiente

| Receta | Resultado |
|---|---|
| Cualquier bongurí (7 colores) | 1 Recipiente |

- **Ambigüedad:** ¿1 bongurí → 1 recipiente? ¿Todos los colores producen el mismo recipiente o hay 7 recipientes distintos? Recomendación: 1 bongurí → 1 `material:recipiente` genérico; el color es solo sabor/colección.
- **Ambigüedad:** ¿se necesita un recipiente por cada baya o se reutiliza? El texto «Baya + Recipiente» sugiere consumir el recipiente. Recomendación: consumir 1 recipiente por fabricación.

### 7.6 Efectos de las pociones (valores pendientes)

| Item | Efecto propuesto | Valor |
|---|---|---|
| Poción | Cura HP | 20 % del máximo (o 20 HP) |
| Súper Poción | Cura HP | 40 % |
| Hiper Poción | Cura HP | 60 % |
| Poción Máxima | Cura HP | 100 % |
| Cura Total | Cura estado | Todos los estados mayores + confusión |
| Restaurar Todo | HP + estado + PP | 100 % + todos + todos los usos |
| Revivir | Revive | 50 % HP |
| Max Revivir | Revive | 100 % HP + estado limpio |

- **Ambigüedad:** la especificación no da magnitudes. Hay que decidir % vs cantidad fija y si las pociones curan escudos/barreras o solo HP. Recomendación: solo HP; los escudos se curan con movimientos/efectos existentes.

---

## 8) Assets e iconos

### 8.1 Mapeo confirmado de bayas

Fuente de verificación: WikiDex (Baya Zreza = Cheri; Baya Lagro = Micle; Baya Algama = Kelpsy) y tabla de Pokéxperto usada en la búsqueda. Los archivos se comprobaron con `ls`/test de existencia en `public/images/berry/`.

| Nombre en la especificación | Inglés | Archivo | Estado |
|---|---|---|---|
| Aranja | Oran | `oran-berry.webp` | OK |
| Zidra | Sitrus | `sitrus-berry.webp` | OK |
| Zreza | Cheri | `cheri-berry.webp` | OK — **no es Leppa** |
| Meloc | Pecha | `pecha-berry.webp` | OK |
| Safre | Rawst | `rawst-berry.webp` | OK |
| Perasi | Aspear | `aspear-berry.webp` | OK |
| Algama | Kelpsy | `kelpsy-berry.webp` | OK |
| Ispero | Qualot | `qualot-berry.webp` | OK |
| Meluce | Hondew | `hondew-berry.webp` | OK |
| Uvav | Grepa | `grepa-berry.webp` | OK |
| Tamate | Tamato | `tamato-berry.webp` | OK |
| Lichi | Liechi | `liechi-berry.webp` | OK |
| Gonlan | Ganlon | `ganlon-berry.webp` | OK |
| Aslac | Salac | `salac-berry.webp` | OK |
| Yapati | Petaya | `petaya-berry.webp` | OK |
| Aricoc | Apicot | `apicot-berry.webp` | OK |
| Zonlan | Lansat | `lansat-berry.webp` | OK |
| Lagro | Micle | `micle-berry.webp` | OK — **no es Starf** |
| Caoca | Occa | `occa-berry.webp` | OK |
| Pasio | Passho | `passho-berry.webp` | OK |
| Gualot | Wacan | `wacan-berry.webp` | OK |
| Tamar | Rindo | `rindo-berry.webp` | OK |
| Rimoya | Yache | `yache-berry.webp` | OK |
| Pomaro | Chople | `chople-berry.webp` | OK |
| Kebia | Kebia | `kebia-berry.webp` | OK |
| Acardo | Shuca | `shuca-berry.webp` | OK |
| Kouba | Coba | `coba-berry.webp` | OK |
| Payapa | Payapa | `payapa-berry.webp` | OK |
| Yecana | Tanga | `tanga-berry.webp` | OK |
| Alcho | Charti | `charti-berry.webp` | OK |
| Drasi | Kasib | `kasib-berry.webp` | OK |
| Anjiro | Haban | `haban-berry.webp` | OK |
| Dillo | Colbur | `colbur-berry.webp` | OK |
| Baribá | Babiri | `babiri-berry.webp` | OK |
| Chilan | Chilan | `chilan-berry.webp` | OK |
| Hibis | Roseli | `roseli-berry.webp` | **FALTA** |

- Total: 36 bayas de la especificación; 35 con archivo; 1 faltante (`roseli`).
- Sobran 30 archivos de baya no usados por la especificación (p. ej. `leppa-berry.webp`, `starf-berry.webp`, `chesto-berry.webp`, `figy-berry.webp`). No hay que borrarlos; pueden usarse en el futuro.
- **Corrección a las sospechas:** `Zreza` es `cheri` y `Lagro` es `micle`; `leppa` y `starf` corresponden a `Zanama` y `Arabol`, que no aparecen en la especificación.

### 8.2 Bonguris

| Color | Archivo actual | Estado |
|---|---|---|
| Amarillo | `80px-Bonguri_amarillo_EP.png` | OK |
| Azul | `80px-Bonguri_azul_EP.png` | OK |
| Blanco | `80px-Bonguri_blanco_EP.png` | OK |
| Negro | `80px-Bonguri_negro_EP.png` | OK |
| Rojo | `80px-Bonguri_rojo_EP.png` | OK |
| Rosa | `80px-Bonguri_rosa_EP.png` | OK |
| Verde | `80px-Bonguri_verde_EP.png` | OK |

- Son PNG, no WebP. Para homogeneizar, la fase de iconos puede convertirlos con `app/Support/WebpConverter` / comando `iconos:optimize-webp` (HECHO de existencia en `docs/architecture.md`, sección «Infraestructura transversal»), pero no es bloqueante: el navegador sirve PNG.
- **Decisión pendiente:** mantener nombres con prefijo `80px-` o normalizar a `bonguri/{color}.webp`. Recomendación: normalizar en la fase de iconos y dejar el mapping en el catálogo.

### 8.3 Iconos faltantes

No existe ningún icono para:

- 8 pociones/restauradores (`pocion`, `super`, `hiper`, `maxima`, `curatotal`, `restaurar_todo`, `revivir`, `max_revivir`).
- 1 recipiente.
- 7 mejoras de stat.
- 5 reductores de stat.
- 18 resistencias de tipo.

Total: **39 iconos nuevos** (8 + 1 + 7 + 5 + 18). Opciones:
1. Reutilizar el icono de la baya de origen para el objeto fabricado (p. ej. `mejora:ataque` usa `liechi-berry.webp`). Barato, pero confunde baya con objeto.
2. Reutilizar iconos de tipo (`public/images/type/{slug}.webp`) para las resistencias. Barato y semánticamente aceptable.
3. Buscar/generar iconos propios de poción/recipiente. Necesario para pociones.
- Recomendación MVP: opción 2 para resistencias + opción 1 para mejoras/reductores + iconos propios solo para las 9 pociones/recipiente. La fase de iconos puede sustituirlos después.

### 8.4 Convención de rutas de iconos propuesta

- Bayas: `/images/berry/{slug}-berry.webp` (ya existe).
- Bonguris: `/images/bonguri/{color}.webp` (normalizado) o el PNG actual.
- Pociones/objetos: `/images/items/{slug}.webp` (nueva carpeta, a decidir).
- `CatalogoObjetos::iconoDe()` y `ItemCatalogo::resolve()` deben devolver la ruta; hoy el primero devuelve vacío (`CatalogoObjetos.php:41-44`) y el segundo no conoce las claves nuevas (`ItemCatalogo.php:84-107`).

---

## 9) Riesgos

| Nivel | Riesgo | Impacto | Mitigación |
|---|---|---|---|
| **Alto** | Equipar sin propiedad ni coste | Objetos infinitos; economía rota | Validar inventario y descontar al equipar; test de aceptación obligatorio |
| **Alto** | Cambio del núcleo de combate (`DTOAccionBatalla`/`AccionBatalla`) | Regresiones en 5 modos (manual, ruta, entrenador, gimnasio, mazmorra, exploración) | DTO nuevo + servicio aislado + bump de `SESSION_VERSION`; no tocar la cadena de daño |
| **Alto** | Balance de revivir/pociones | Combates triviales; bosses rotos | Definir magnitudes, límite por acción, coste de recetas y drop rates |
| **Alto** | Stacks de buffs/debuffs | ×1,69/×2,19 y ×0,49/×0,34; clamp 0,25–4,0 | Decidir refresco vs stack; limitar a 1 buff por stat |
| **Medio** | Contrato de exploración | La UI de resultados puede romperse si `items` no es aditivo | Añadir clave opcional `items` con default `[]`; tolerar ausencia |
| **Medio** | `EventoExploracion` sin `itemKey` | Si se elige opción B, hay que migrar payloads persistidos | Empezar con opción A (botín final) |
| **Medio** | Concurrencia en fabricación | Saldos negativos o dobles descuentos | Transacción + `lockForUpdate` ordenado + validación previa |
| **Medio** | Claves de item desde el cliente | Inyección de claves arbitrarias en inventario | Validar contra catálogo; nunca aceptar `item_key` libre |
| **Medio** | N+1 en resolución de items | Lentitud en modales con muchos items | Catálogo en código; resolver sin BD para bayas/pociones |
| **Medio** | Iconos faltantes | UI incompleta | Fase de iconos separada; fallback a `unknown.webp` |
| **Bajo** | PNG de bonguris | Peso/formato inconsistente | Convertir a WebP en fase de iconos |
| **Bajo** | `player_inventory` sin FK a catálogo | Claves huérfanas si se renombra | Catálogo estable; script de migración si se renombra |
| **Bajo** | `obj_equipados` legacy con claves no inventariadas | Jugadores con objetos «regalo» | Backfill o tolerancia; no borrar datos |

**Riesgo de seguridad específico:** el endpoint `PATCH /teams/{team}/items` acepta cualquier clave del catálogo y no comprueba que el reclutado tenga el objeto. Un usuario puede equipar `life_orb` a los 3 sin tenerlo. Esto ya existe hoy; la nueva feature lo amplifica porque las bayas serán más valiosas.

**Riesgo de rendimiento:** `ItemCatalogo::resolverFamilia()` hace una consulta por item de familia (`ItemCatalogo.php:119-123`). Para bayas/pociones no debe replicarse: usar mapas estáticos en código.

---

## 10) Fases y alcance

### Fase 0 — Cierre de decisiones (esta tarea)
- Responder las preguntas de la sección 11.
- Confirmar si «Items equipables: Postergado» (`MVP.txt:64`) aplica a las bayas nuevas o solo a un sistema futuro. **INFERENCIA:** el equipamiento actual de 8 objetos ya existe; lo postergado debe ser el sistema completo de equipables de bayas.

### Fase 1 — Botín e inventario (MVP-1)
- Claves `baya:*` y `bonguri:*` en `ItemCatalogo`.
- Catálogo de 36 bayas + 7 bonguris con nombre e icono.
- `CalculadorBotinExploracion` + `ResultadoRecompensas.items` + `PersistirRecompensas`.
- Mostrar botín en el resumen de exploración.
- Pérdida por derrota incluye bayas/bonguris.
- **No** incluye fabricación ni uso en combate.
- Criterio: una exploración incrementa el inventario exactamente una vez (idempotencia por `regreso`).

### Fase 2 — Forja de pociones (MVP-2)
- Módulo `src/Forja` + catálogo de recetas R1–R9.
- `Fabricar` transaccional + `ListarRecetas`.
- UI de forja (modal o página).
- Iconos de pociones (al menos temporales).
- **No** incluye objetos de stat/tipo.

### Fase 3 — Uso de pociones/revivir en combate (MVP-3)
- `AccionObjeto` + `ServicioUsoObjetos`.
- Fases `player_item` / `player_item_target`.
- Selección de aliados en la UI.
- Descuento de inventario al usar.
- Curación, estado, revivir, restaurar todo.
- **No** incluye buffs/debuffs/resistencias.

### Fase 4 — Objetos de stat y tipo (MVP-4)
- 30 recetas restantes (5 reductoras + 7 potenciadoras + 18 resistencias).
- Efectos temporales: `aplicarTemporal` para stats; efecto/condición nueva para crítico y resistencia de tipo.
- UI de selección de objetivo rival/aliado según item.
- Balance inicial y límites.

### Fase 5 — Equipables e iconos (post-MVP)
- Equipar bayas base con consumo de inventario.
- Activación automática de bayas equipadas.
- Iconos definitivos (bayas, bonguris, pociones, recipiente, objetos).
- Animaciones de uso de objeto.
- **Diferido:** administración de recetas, tiempos de fabricación, IA con objetos, historial/auditoría.

---

## 11) Preguntas para el desarrollador

Cada pregunta incluye opciones y recomendación por defecto.

1. **¿Qué bayas se pueden equipar?**
   - A) Solo las 6 base (Aranja, Zidra, Zreza, Meloc, Safre, Perasi).
   - B) Las 6 base + las 30 de stat/tipo.
   - C) Ninguna; todas se consumen desde el menú.
   - **Recomendación: A.** Las de stat/tipo son materiales según la especificación.

2. **¿Las bayas de stat/tipo se pueden consumir directamente en combate?**
   - A) No, solo fabricar.
   - B) Sí, con su efecto crudo.
   - **Recomendación: A.** Coherente con «no serán usables en batalla».

3. **¿«Usar contra el enemigo» aplica un debuff o hace daño?**
   - A) Solo debuff (reductor de stat).
   - B) Debuff + daño.
   - **Recomendación: A.** La especificación no describe objetos de daño.

4. **¿El uso de objeto consume la acción del Pokémon aliado o es una acción extra del entrenador?**
   - A) Consume la acción del Pokémon.
   - B) Acción extra gratuita por ronda.
   - **Recomendación: A.** «En turno de un aliado» implica que ese aliado actúa.

5. **¿Cuántos objetos equipados por Pokémon?**
   - A) 1 (estado actual).
   - B) 2+.
   - **Recomendación: A.** `obj_equipados` ya es lista de una clave.

6. **¿El objeto equipado se descuenta del inventario?**
   - A) Sí, al equipar.
   - B) Solo al consumirse en batalla.
   - C) No se descuenta.
   - **Recomendación: A** (simple y sin objetos gratis). Si se elige B, hay que persistir la reserva y el `battleId`.

7. **¿Las pociones curan porcentaje o cantidad fija?**
   - A) % del HP máximo (20/40/60/100).
   - B) Cantidad fija (20/50/100/200).
   - **Recomendación: A.** Escala con el nivel y evita trivializar a niveles altos.

8. **¿«Restaurar Todo» y «Max Revivir» restauran PP?**
   - A) Sí, todos los usos.
   - B) No, solo HP/estado.
   - **Recomendación: A.** Es lo que sugiere «restaurar todo»; requiere método nuevo en `EstadoMovimientos`.

9. **¿Revivir puede usarse sobre un aliado debilitado en combate 3v3?**
   - A) Sí, solo aliado debilitado.
   - B) No, solo fuera de combate.
   - **Recomendación: A.** `Combatiente::revivir()` ya lo soporta.

10. **¿La fabricación es instantánea o con tiempo?**
    - A) Instantánea.
    - B) Con temporizador/cola.
    - **Recomendación: A** para MVP; B exige `craft_jobs` y una UI de cola.

11. **¿Dónde vive la forja?**
    - A) Módulo nuevo `src/Forja`.
    - B) Dentro de `src/Granjas`.
    - **Recomendación: A.** Mejor frontera; B si se prioriza velocidad.

12. **¿Cantidades de drop de exploración?**
    - A) 0–3 bayas + 0–1 bongurí por expedición.
    - B) 1 baya fija + 1 bongurí cada N expediciones.
    - **Recomendación: A** con pesos por rareza; ajustar tras telemetría.

13. **¿Se pueden fabricar varias unidades a la vez?**
    - A) Sí, con cantidad limitada por materiales.
    - B) Solo de una en una.
    - **Recomendación: A**, pero el MVP puede empezar con 1 y añadir cantidad después.

14. **¿Los objetos fabricados son equipables?**
    - A) No, solo consumibles.
    - B) Sí, algunos.
    - **Recomendación: A.** Mantener el equipamiento para las 6 bayas base y los 8 objetos actuales.

15. **¿Se puede usar objeto si el Pokémon está dormido/paralizado/confuso?**
    - A) No, debe pasar `puedeActuar()`.
    - B) Sí, el entrenador actúa.
    - **Recomendación: A.** Consistente con el motor actual.

16. **¿Límite de objetos por combate?**
    - A) Sin límite global; solo inventario.
    - B) Máximo N por combate.
    - **Recomendación: A** para MVP; B si el balance lo exige.

17. **¿La baya equipada se activa automáticamente?**
    - A) Sí, como Sitrus/Lum.
    - B) Solo manual.
    - **Recomendación: A.** Reutiliza los hooks existentes.

18. **¿Nombres de claves en inglés o español?**
    - A) Inglés (`baya:oran`) para alinear con assets.
    - B) Español (`baya:aranja`).
    - **Recomendación: A** para las claves; nombre visible en español.

19. **¿Se debe migrar `player_inventory` a una tabla de catálogo con FK?**
    - A) No en MVP.
    - B) Sí, ahora.
    - **Recomendación: A.** El catálogo en código es suficiente y evita migraciones/seeders.

20. **¿Compatibilidad con batallas en curso al cambiar el DTO?**
    - A) Bump de `SESSION_VERSION` y descartar payloads antiguos.
    - B) Compatibilidad best-effort.
    - **Recomendación: A** (o B si se puede mantener `type='attack'` por defecto y añadir campos opcionales).

---

## 12) Criterios de aceptación propuestos

### 12.1 Inventario y catálogo
- [ ] Existen 36 claves `baya:*`, 7 `bonguri:*`, 8 `pocion:*`, 1 `material:recipiente`, 7 `mejora:*`, 5 `reductor:*` y 18 `resistencia:*`.
- [ ] `ItemCatalogo::resolve()` devuelve nombre + icono para todas; una clave desconocida sigue cayendo a `Desconocido` sin lanzar.
- [ ] La longitud de todas las claves es ≤ 64.
- [ ] No se ha creado ninguna migración nueva (si se elige alternativa MVP).

### 12.2 Exploración
- [ ] Al finalizar una exploración, el inventario del dueño incrementa exactamente las cantidades del botín.
- [ ] Repetir la finalización no duplica el botín (idempotencia por `regreso`).
- [ ] El resumen de exploración muestra las bayas/bonguris obtenidos con nombre e icono.
- [ ] En derrota, se pierde `intdiv(cantidad, 2)` de cada grupo y se muestra en `objetos_perdidos`.
- [ ] Si el usuario no existe, no se escribe inventario y no se lanza excepción.

### 12.3 Fabricación
- [ ] Cada receta consume exactamente sus ingredientes y produce exactamente su resultado.
- [ ] Si falta un ingrediente, la operación falla con 422 y **no** descuenta nada (atomicidad).
- [ ] Dos peticiones concurrentes no dejan saldos negativos.
- [ ] `ListarRecetas` devuelve `puedeFabricar` correcto según saldo.
- [ ] El resultado de fabricación devuelve los saldos nuevos.
- [ ] No se puede fabricar una receta inexistente (404/422).

### 12.4 Combate
- [ ] Usar un objeto consume la acción del Pokémon y no consume movimiento.
- [ ] Usar un objeto descuenta 1 del inventario; sin stock, la acción no se ofrece/no se ejecuta.
- [ ] Poción cura solo a aliados vivos; revivir solo a aliados debilitados; reductor solo a rivales vivos.
- [ ] Un buff de stat aplica +30 % durante 5 rondas y se retira al agotarse.
- [ ] Un debuff aplica −30 % durante 5 rondas y se retira al agotarse.
- [ ] Una resistencia de tipo reduce el daño de ese tipo un 30 % durante 5 rondas.
- [ ] La UI permite seleccionar aliados y rivales según el item, no solo rivales.
- [ ] El log registra el uso y el efecto.
- [ ] No hay regresiones en los combates de ruta/entrenador/gimnasio/mazmorra/exploración.

### 12.5 Equipamiento
- [ ] Solo se puede equipar un item que el jugador posee y que sea `equipable`.
- [ ] Equipar descuenta 1 del inventario (si se elige esa opción).
- [ ] `obj_equipados` sigue siendo una lista de una clave.
- [ ] Un item no equipable es rechazado con 422.

### 12.6 UI y arquitectura
- [ ] Blade no calcula reglas de negocio ni consulta BD; recibe DTOs/colecciones.
- [ ] El dominio de `Forja` no importa Eloquent/HTTP.
- [ ] Los nuevos servicios respetan `.ai/rules/arrays-dto-collections.md` (sin arrays como contrato).
- [ ] `phpstan`/`pint` pasan (verificación estática; sin tests).
- [ ] No se han modificado tests.

---

## Archivos inspeccionados

**Petición y reglas**
- `.opencode/work/TASK-011/request.md`
- `MVP.txt` (sección «Modulo Forja+Granja»; «Items equipables: Postergado»)
- `.ai/rules/index.md`, `ddd-modules.md`, `model-policy.md`, `domain-repositories.md`, `infra-controllers.md`, `arrays-dto-collections.md`
- `docs/ddd.md`, `docs/architecture.md`

**Battle**
- `src/Battle/Domain/Catalog/CatalogoObjetos.php`
- `src/Battle/Domain/Enums/ClaveItem.php`, `FaseCombate.php`, `ObjetivoMovimiento.php`, `AmbitoDanio.php`, `StatClave.php`, `CondicionDanio.php`
- `src/Battle/Domain/ValueObjects/EstadoObjeto.php`, `MultiplicadoresStats.php`, `CambioStat.php`, `EstadoMovimientos.php`
- `src/Battle/Domain/Effects/InterfazEfecto.php`, `FabricaEfectos.php`, `EfectoBayaSitrus.php`, `EfectoBayaLum.php`, `EfectoModificadorDanio.php`, `ComportamientosPorDefecto.php`, `EfectoRefuerzos.php`
- `src/Battle/Domain/Chain/ManejadorObjetosEquipados.php`
- `src/Battle/Domain/AccionBatalla.php`, `GestorTurnos.php`, `ServicioEjecucionBatalla.php`, `AgregadoBatalla.php`, `EquipoBatalla.php`, `Combatiente.php`, `CombatienteVista.php`, `DatosPokemonBatalla.php`, `ReglasBatalla.php`
- `src/Battle/Presentation/DTOAccionBatalla.php`
- `app/Providers/BattleEffectServiceProvider.php`
- `app/Support/BattleSessionService.php`, `CreadorBatallaSesion.php`
- `app/Livewire/Combate.php`
- `app/Livewire/Presenters/BatallaVistaPresenter.php`
- `resources/views/livewire/combate.blade.php`, `_pokemon-card.blade.php`, `partials/battle-field.blade.php`

**Inventario y equipos**
- `app/Models/PlayerInventory.php`, `Reclutado.php` (referencia), `Team.php` (referencia)
- `database/migrations/2026_08_29_000007_create_player_inventory_table.php`, `2026_05_27_000001_create_reclutados_table.php`
- `app/Support/ItemCatalogo.php`, `EquiposVistaPresenter.php`
- `app/Http/Controllers/TeamController.php`
- `src/Equipos/App/ActualizarObjetosEquipo.php`
- `resources/views/equipos/index.blade.php`

**Exploraciones**
- `src/Exploraciones/App/PersistirRecompensas.php`, `FinalizarExploracionHandler.php`, `CombateExploracion.php`
- `src/Exploraciones/Domain/CalculadorRecompensas.php`, `EconomiaRecompensas.php`, `SimuladorEncuentros.php`
- `src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php`
- `src/Exploraciones/Domain/ValueObjects/EventoExploracion.php`
- `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php`, `PresentadorExploraciones.php`, `EventoExploracionVista.php`, `DTOObjetoPerdido.php`
- `app/Models/ExploracionActiva.php`
- `app/Http/Controllers/ExploracionActivaController.php`
- `routes/exploraciones.php`
- `resources/views/exploraciones/_evento.blade.php`, `_caramelo.blade.php`

**Granjas**
- `src/Granjas/context.md`
- `src/Granjas/App/InventarioGranja.php`, `AccionesGranja.php`
- `app/Models/Granja.php`, `Nido.php`, `Refugio.php`, `Complemento.php`
- `database/migrations/2026_10_26_000001_create_farm_tables.php`
- `routes/granjas.php`

**Combate PvE**
- `src/CombateEntrenadores/App/MapeadorPokemonBatalla.php`, `ConstruirEquipoJugador.php`

**Assets**
- `public/images/berry/` (listado completo)
- `public/images/bonguri/` (listado completo)
- `public/images/` (listado de carpetas)

**Fuentes externas**
- WikiDex: Baya Zreza (Cheri), Baya Lagro (Micle), Baya Algama (Kelpsy) — consultadas para corregir el mapeo.
- Pokéxperto: tabla de bayas español→inglés usada para el resto del mapeo.

---

## Decisiones tomadas en este análisis

1. Se mantiene `player_inventory` como inventario único; no se propone migración en MVP.
2. Se mantiene `reclutados.obj_equipados` con 1 objeto por Pokémon.
3. La fabricación se propone como módulo `src/Forja` (alternativa: dentro de `src/Granjas`).
4. El botín de exploración se propone calculado al finalizar (alternativa: evento por hallazgo).
5. El uso de objeto consume la acción del Pokémon aliado, no una acción extra.
6. Los buffs de stat se apoyan en `MultiplicadoresStats::aplicarTemporal` (5 turnos, ×1,30).
7. Las resistencias de tipo requieren un efecto/condición temporal nuevo; no se reutiliza `EfectoModificadorDanio` permanente.
8. El crítico requiere un efecto/condición aparte porque no está en `StatClave`.
9. Las claves usan slug inglés para alinear con assets; el nombre visible es español.
10. `Zreza = cheri` y `Lagro = micle`; se descartan `leppa` y `starf` para esos nombres.

---

## Verificación ejecutada

- Lectura estática de los archivos listados.
- `git status --short`, `git branch --show-current`, `git log --oneline -10` (solo lectura).
- `grep`/`rg` sobre objetos, items, bayas, inventario, granja, recetas y UI.
- Comprobación de existencia de los 36 archivos de baya con `test -f` (35 OK, 1 MISS: `roseli`).
- Listado de `public/images/berry`, `public/images/bonguri` y carpetas de `public/images`.
- Consulta web a WikiDex para Zreza/Lagro/Algama y tabla de Pokéxperto para el mapeo.
- **No** se ejecutaron tests, migraciones, seeders ni comandos de escritura.
- **No** se modificó ningún archivo del repositorio.

---

## Problemas encontrados

1. **TODO de bayas** en `app/Models/ExploracionActiva.php:11`, `app/Http/Controllers/ExploracionActivaController.php:254`, `routes/exploraciones.php:15`.
2. **No existe acción de objeto** en el motor de combate.
3. **La UI solo permite objetivos rivales**, no aliados.
4. **Equipar no consume inventario ni valida propiedad** (agujero previo, amplificado por la feature).
5. **No hay forja ni recetas**.
6. **No hay iconos de pociones/recipiente/objetos fabricados**.
7. **Falta `roseli-berry.webp`**.
8. **`CatalogoObjetos::iconoDe()` vacío**.
9. **No hay restauración de PP**.
10. **No hay resistencia de tipo temporal**.
11. **El crítico no está en `StatClave`**.
12. **Contradicciones de la especificación** (bayas usables vs solo materiales; recetas de revivir; Lagro/Zonlan; «restaurar todo» vs «max revivir»).
13. **`MVP.txt` marca «Items equipables: Postergado»**, pero ya existe equipamiento de 8 objetos; hay que aclarar el alcance.

---

## Riesgos pendientes

- **Balance:** sin magnitudes de pociones, drop rates y stacks, el sistema puede romper el combate.
- **Propiedad de objetos:** hay que decidir y aplicar el descuento al equipar/consumir.
- **Compatibilidad de sesión:** cualquier cambio en `DTOAccionBatalla`/`Combatiente` exige `SESSION_VERSION` y pruebas manuales de payloads antiguos.
- **Contrato de exploración:** la UI debe tolerar la ausencia de `items` hasta que se despliegue.
- **Iconos:** 39 iconos nuevos; la fase de iconos necesita una decisión de estilo (reutilizar vs crear).
- **Forja:** si se añaden tiempos/cola, el modelo MVP (sin tablas) se queda corto.
- **Datos legacy:** `obj_equipados` puede contener claves que el jugador no tiene; hay que decidir backfill o tolerancia.
- **Tests:** no se han ejecutado por restricción; la verificación futura debe ser estática + manual hasta que el flujo lo permita.
</subagent>

---

> **Nota de proceso:** se ha alcanzado el máximo de pasos permitidos para este agente durante la fase de inspección. No se ha modificado ningún archivo. El documento siguiente se apoya solo en lo leído; los puntos que quedaron sin verificación directa se marcan como `INFERENCIA` o `PROPUESTA` y se listan al final.

# TASK-011 — Análisis complementario A2
## Exploración por modos, bonguri por hábitat, bonus de hábitat y plantación/cosecha en nidos

> **Convención:** **HECHO** = verificado en código con `archivo:línea`; **INFERENCIA** = deducción razonada; **PROPUESTA** = diseño recomendado, no implementado.
> Este documento **no repite** el análisis previo (`.opencode/work/TASK-011/analysis.md`); lo referencia y amplía.

---

## A2.1 Resumen ejecutivo

El sistema actual de exploración ya es **individual y por reclutado**, guarda `habitat_id` y `nivel` en `exploraciones_activas` y calcula recompensas en dos momentos: eventos durante el tick (`ProcesarExploracionHandler`) y agregación final (`FinalizarExploracionHandler`). **No existe noción de modo** ni de botín de objetos; el contrato `ResultadoRecompensas` solo transporta capturas, caramelos y EXP (`src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php:44-58`).

La granja ya tiene **nidos por familia con nivel 0–5** (`nests`, `Nido`), pero **no tiene ninguna entidad de plantación ni de bayas**. El inventario unificado `player_inventory` permite claves nuevas sin migración, y el catálogo de presentación solo conoce `familia:*`, `ev:*` y `tipo:*` (`app/Support/ItemCatalogo.php:84-107`).

Hay una **pista fuerte sobre el bonguri**: `ProvinceSeeder` asocia cada provincia a un color de bonguri en comentarios (`database/seeders/ProvinceSeeder.php:18-33`). Con 56 hábitats y 7 colores, “un único bonguri por hábitat” no puede significar exclusividad global: significa **exactamente uno por hábitat**, reutilizable entre hábitats.

La propuesta MVP es:
1. `exploraciones_activas.modo` nullable (`experiencia`/`reclutamiento`/`botin`; `null` = comportamiento actual).
2. `habitats.bonguri` (slug de color) con backfill desde la provincia.
3. Botín **calculado al finalizar** (opción A del análisis previo), añadido de forma aditiva a `ResultadoRecompensas`.
4. Tabla nueva `nest_plantings` (una fila por hueco plantado) con crecimiento calculado por tiempo, sin cron.
5. Cosecha manual desde la granja en MVP; auto-cosecha al finalizar exploración de un `RECOLECTOR` en fase 2.
6. Bonus “del hábitat” MVP por **especie nativa en `pokemon_habitat`**; campo de origen como mejora posterior.

---

## A2.2 Estado actual de exploración y granja (evidencia)

### Exploración

**HECHO — inicio y persistencia**
- Rutas: `GET /exploraciones`, `GET /exploraciones/preview`, `POST /exploraciones`, `POST /api/exploraciones/store-individual`, recoger/cancelar/cerrar — `routes/exploraciones.php:8-16`.
- Validación del inicio: `reclutado_id` obligatorio y **propiedad del usuario** (`Rule::exists(...)->where('user_id', Auth::id())`), `habitat_id` existente, `level` 1–3, `duracion_horas` 1–72, `return_time` o `indefinido` — `app/Http/Controllers/ExploracionActivaController.php:295-303`.
- Creación: guarda `user_id`, `reclutado_id`, `habitat_id`, `nivel`, `duracion_horas`, `hora_limite`, `indefinido`, `inicio_exploracion`, `llegada_destino`, `regreso` — `ExploracionActivaController.php:332-343`; `$fillable` en `app/Models/ExploracionActiva.php:19-31`.
- Columnas reales: `user_id` (`2026_08_29_000006_add_user_id_to_exploraciones_activas_table.php:17-34`), `habitat_id`/`nivel`/`duracion_horas`/`hora_limite`/`indefinido` (`2026_08_26_000004_add_habitat_id_and_level_to_exploraciones_activas.php:15-21`), `reclutado_id` (`2026_09_02_000003_change_equipo_id_to_reclutado_id_in_exploraciones.php`), `eventos` jsonb (`2026_08_30_000003_change_eventos_to_jsonb_in_exploraciones_activas_table.php:12-17`).
- La UI envía `reclutado_id`, `level`, `habitat_id` y duración/hora — `resources/views/habitats/show.blade.php:2045-2055`. El preview se pide con los mismos parámetros — `:1960-1997`.

**HECHO — procesamiento y recompensas**
- Cadencia: 1 evento cada 30 min, máximo 12 por procesamiento, recompensas al 50 % y EXP 50/50 — `src/Exploraciones/Domain/ReglasExploracion.php:20-31`.
- Probabilidades de evento: 45 % encuentro, 20 % hallazgo, 15 % encuentro especial, 10 % contratiempo, 10 % neutral; subtipos 80/10/7/3 — `src/Exploraciones/Domain/SimuladorEncuentros.php:33-42`.
- Hallazgo: 33,33 % caramelo familia, 33,33 % EV, resto tipo — `SimuladorEncuentros.php:46-47`, `:263-303`.
- El tick genera eventos y los resuelve con combate 1v1 real; emboscada secuencial; descanso 1 %/min — `src/Exploraciones/App/ProcesarExploracionHandler.php:129-256`, `:351-461`, `:637-662`.
- El pool del hábitat se filtra por `pokemon_habitat.level` — `ProcesarExploracionHandler.php:727-764`; el pivote tiene unique `(pokemon_id, habitat_id)` y `level` 1–3 — `database/migrations/2026_02_24_201538_create_pokemon_habitat_table.php:15-21`.
- La finalización es idempotente por `regreso` — `src/Exploraciones/App/FinalizarExploracionHandler.php:78-83`; calcula derrotados, categoría, recompensas, hallazgos, pérdidas, persiste y marca regreso — `:91-119`, `:158-217`.
- La economía individual aplica factor 0,5 y reparte EXP 50/50 — `src/Exploraciones/Domain/EconomiaRecompensas.php:75-84`.
- Los caramelos de tipo salen de `floor(exp_tipo / 250 × multiplicadores)` — `src/Exploraciones/Domain/CalculadorRecompensas.php:330-369`.
- El contrato persistido de resultado es `capturados`, `caramelos_familia`, `caramelos_ev`, `caramelos_tipo`, `exp`, `exp_reclutado`, `resultado`, `duration_real`, `tiempo_perdido`, `incidentes` — `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php:50-61`.
- La vista de resultados solo pinta capturas y caramelos — `resources/views/exploraciones/index.blade.php:305-362`.
- El evento `hallazgo` sin subtipo conocido cae a “Hallazgo registrado” — `src/Exploraciones/Presentation/EventoExploracionVista.php:211-232`.
- `EventoExploracion` **no tiene clave de item** — `src/Exploraciones/Domain/ValueObjects/EventoExploracion.php:27-59`, `:119-248`.

**HECHO — huecos ya documentados**
- `TODO(futuro): bayas` en `app/Models/ExploracionActiva.php:11`, `ExploracionActivaController.php:254` y `routes/exploraciones.php:15`.

### Granja

**HECHO**
- Tablas: `farms` unique `(user_id, habitat_id)`; `nests` unique `(farm_id, evolution_chain_id)` con `level` 0–5; `shelters`; `complements` — `database/migrations/2026_10_26_000001_create_farm_tables.php:16-56`.
- `Nido` solo tiene `farm_id`, `evolution_chain_id`, `level` — `app/Models/Nido.php:18-36`; `Granja` tiene `nidos()` y `refugios()` — `app/Models/Granja.php:43-54`.
- Reglas de nido: nivel 0–5, reduce tiempo de huevo 10 %/nivel hasta 50 %, IV por nivel — `src/Granjas/context.md` (secciones 4–6).
- Rutas: solo `GET /api/habitats/{habitat}/farm` y POST de mejoras — `routes/granjas.php:9-17`.
- `GranjaController` resuelve el usuario por `Guard` y devuelve siempre el `GranjaDetalle` completo — `src/Granjas/Infra/Controllers/GranjaController.php:22-65`.
- `ObtenerGranja` compone familias, nidos, refugios, complementos e inventario — `src/Granjas/App/ObtenerGranja.php:47-85`; `NidoGranja` expone nivel, rango IV, factor tiempo y mejora — `src/Granjas/Domain/DataTransferObjects/NidoGranja.php:30-39`.
- `MejorarNido` usa transacción + `lockForUpdate` + consumo de `familia:{chain}` — `src/Granjas/App/MejorarNido.php:29-60`.
- La UI de granja es un modal en `resources/views/habitats/show.blade.php:1088-1245`; el bloque de nido está en `:1152-1170`; las acciones llaman a `/api/habitats/{id}/farm/...` — `:1704-1717`.

**HECHO — inventario**
- Claves canónicas actuales: `familia:{chain}`, `ev:{stat}`, `tipo:{slug}` — `app/Support/ItemCatalogo.php:27-40`; `resolve()` cae a “Desconocido” para claves no reconocidas — `:84-107`.
- `PersistirRecompensas` incrementa `player_inventory` con `firstOrCreate(...)->increment()` — `src/Exploraciones/App/PersistirRecompensas.php:80-90`.

### “Pokémon del hábitat”: no existe origen

**HECHO**
- `reclutados` no tiene `habitat_id` ni origen — `database/migrations/2026_05_27_000001_create_reclutados_table.php:15-28`, `app/Models/Reclutado.php:22-36`.
- `CrearReclutado` no recibe hábitat — `app/Support/CrearReclutado.php:20-37`.
- `ReclutamientoController::recruit` crea el reclutado sin hábitat — `app/Http/Controllers/ReclutamientoController.php:50-57`.
- `reclutables` tampoco guarda hábitat — `database/migrations/2026_08_26_000002_create_reclutables_table.php:15-21`.
- `favoritos` sí tiene `habitat_id` nullable, pero es una lista de favoritos, no un origen — `database/migrations/2026_09_02_000001_add_favorito_to_reclutados_table.php:30-39`.
- La única relación especie↔hábitat es `pokemon_habitat` — `app/Models/Habitat.php:45-49`.

**INFERENCIA:** hoy no se puede saber si un reclutado fue capturado/reclutado en un hábitat concreto. Solo se puede comprobar si **su especie es nativa** de ese hábitat.

### Bonguri: pista en el seeder de provincias

**HECHO**
- `ProvinceSeeder` crea 8 provincias y comenta el color de bonguri de cada una: Galicia (blanco, repetido), Euskadi (amarillo), Barcelona (negro), Extremadura (verde), Nova Alacant (rosa), Mutxamel (azul), Andalucía (rojo), Vacío (sin color) — `database/seeders/ProvinceSeeder.php:18-33`.
- Hay 56 hábitats en 8 provincias (7 con color, 1 “Vacío” con 1 hábitat) — `storage/data/habitats.csv` (conteo por `province_id`).

**INFERENCIA:** el diseño previo era **bonguri por provincia**, heredado por el hábitat. Un hábitat de la provincia “Vacío” necesita fallback explícito.

---

## A2.3 Gaps para los nuevos requisitos

| # | Gap | Evidencia | Impacto |
|---|---|---|---|
| N1 | No existe `modo` de exploración | `ExploracionActiva.php:19-31`; `ExploracionActivaController.php:295-343` | No se puede configurar pelear/buscar/explorar |
| N2 | `SimuladorEncuentros` tiene probabilidades fijas | `SimuladorEncuentros.php:33-42` | El modo no puede alterar la mezcla de eventos |
| N3 | `ResultadoRecompensas` no transporta objetos | `ResultadoRecompensas.php:44-58` | El botín no llega a inventario ni a la vista |
| N4 | `EventoExploracion` no tiene `item_key` | `EventoExploracion.php:27-59` | Un hallazgo de baya no puede representarse por evento |
| N5 | `ItemCatalogo` no conoce `baya:*`/`bonguri:*` | `ItemCatalogo.php:84-107` | La UI mostraría “Desconocido” |
| N6 | No hay bonguri asignado a hábitat | `habitats` solo tiene name/min_lvl/peligro/pokemons/mazmorra (`Habitat.php:19-32`) | No se puede entregar el bonguri correcto |
| N7 | No hay origen de hábitat en reclutados | `Reclutado.php:22-36`; `CrearReclutado.php:20-37` | El bonus “del hábitat” no es fiable |
| N8 | No existe plantación ni bayas en granja | `Nido.php:18-36`; `2026_10_26_000001_create_farm_tables.php:25-34` | Toda la feature de nidos está por hacer |
| N9 | No hay endpoints de plantar/cosechar | `routes/granjas.php:9-17` | Sin API ni UI |
| N10 | La granja no devuelve plantaciones | `ObtenerGranja.php:47-85`; `NidoGranja.php:30-39` | La UI no puede pintar huecos |
| N11 | La pérdida por derrota solo cubre caramelos/capturas | `FinalizarExploracionHandler.php:615-676` | Bayas/bonguris no se perderían |
| N12 | La vista de resultados no pinta objetos | `ResultadoExploracionVista.php:57-85`; `index.blade.php:305-362` | El botín sería invisible |
| N13 | No hay tabla catálogo de bayas por hábitat | No existe en `database/migrations` | La “baya salvaje del lugar” no está definida |
| N14 | El botín de combate no tiene hook | `OtorgarRecompensasRuta.php:138-147` | Fase futura sin punto de extensión |

---

## A2.4 Modelo de datos propuesto y alternativa MVP

### A2.4.1 Modo de exploración

**PROPUESTA**
- Columna `exploraciones_activas.modo VARCHAR(20) NULL` (consistente con las columnas en español de esa tabla: `duracion_horas`, `hora_limite`, `inicio_exploracion`).
- Enum de dominio `ModoExploracion: string`:
  - `EXPERIENCIA = 'experiencia'`
  - `RECLUTAMIENTO = 'reclutamiento'`
  - `BOTIN = 'botin'`
  - `MIXTO = 'mixto'` (solo para `null`/legacy; no se ofrece en UI).
- `null` en BD = `MIXTO` = comportamiento actual exacto. Así una exploración en curso durante el despliegue no cambia de comportamiento.
- Validación nueva: `modo` `nullable|in:experiencia,reclutamiento,botin`. La UI siempre lo envía; la API antigua sigue funcionando sin él.
- **Sin índice**: se lee con la fila.

**Alternativa:** `modo` NOT NULL DEFAULT `'mixto'`. Más simple, pero obliga a un valor “mixto” persistido y no distingue legacy de elección explícita. Recomendada la nullable.

### A2.4.2 Bonguri por hábitat

**PROPUESTA (recomendada)**
- Columna `habitats.bonguri VARCHAR(16) NULL` con enum `ClaveBonguri`: `blanco`, `amarillo`, `negro`, `verde`, `rosa`, `azul`, `rojo`.
- Backfill determinista desde `province_id` según `ProvinceSeeder.php:18-33`; provincia 8 (“Vacío”) → `NULL` y fallback en dominio `ClaveBonguri::porDefecto()` (p. ej. `blanco`), o asignación manual.
- Una sola fuente de nombre/icono en el enum (`/images/bonguri/{color}.webp` o el PNG actual `80px-Bonguri_{color}_EP.png`).
- No crear tabla de mapping: 7 valores cerrados, sin administración.

**Alternativa A:** derivar el bonguri de `province_id` en dominio, sin columna. Menos migración, pero no permite excepciones por hábitat ni resolver la provincia “Vacío” sin código especial.
**Alternativa B:** tabla `habitat_bonguri` (habitat_id, color). Permite historial/multiplicidad, pero contradice “un único tipo por hábitat” y añade join. Rechazada para MVP.

### A2.4.3 Plantaciones de nido

**PROPUESTA (recomendada): una tabla, una fila por hueco plantado**

```text
nest_plantings
  id                bigserial PK
  nest_id           bigint FK -> nests(id) ON DELETE CASCADE
  slot_index        unsignedSmallInteger          -- 1..10
  berry_key         varchar(32)                   -- 'baya:oran' (clave de inventario)
  planted_at        timestamp NOT NULL            -- inicio del crecimiento
  harvest_count     unsignedSmallInteger default 0 -- cosechas ya recogidas
  created_at, updated_at

  UNIQUE (nest_id, slot_index)
  INDEX  (nest_id)
  INDEX  (berry_key)                              -- para agregar por tipo en la granja
```

Reglas:
- `slot_index` válido: `1 <= slot_index <= nests.level * 2`. Nivel 0 → 0 huecos. Nivel 5 → 10.
- Los huecos vacíos **no se persisten**: se calculan como `level*2 - count(plantings)`. Evita filas nulas y sincronización al subir nivel.
- Se permite repetir `berry_key` en distintos `slot_index` (requisito explícito).
- `berry_key` debe pertenecer al catálogo cerrado de bayas; nunca una cadena libre del cliente.
- Crecimiento y stock **calculados**, no almacenados:
  - `dias = floor((now - planted_at) / 24 h)`
  - `stock = min(3, dias - harvest_count)`
  - `puede_cosechar = stock > 0`
- Cosechar: en transacción, `lockForUpdate` de la fila, recalcular `stock`, sumar `stock` al inventario, `harvest_count += stock`.
- Arrancar/replantar: elimina la fila y no devuelve la baya (decisión de producto). Si se permite replantar sin cosechar, se pierde lo acumulado.
- Si se decide que una plantación muere tras 3 cosechas totales: añadir `harvest_count < 3` a la condición de stock y borrar la fila al llegar a 3.

**Alternativa MVP descartada:** `nests.plantings JSON` con un array de huecos. Es la vía más rápida, pero impide `UNIQUE`, complica la concurrencia y obliga a agregar el JSON para el bonus de bayas. No recomendada.

**Alternativa de dos tablas (`nest_slots` + `plantings`):** modela explícitamente los huecos vacíos, pero duplica estado y exige crear/borrar filas al mejorar el nido. Solo si la UI necesita “hueco bloqueado” como entidad.

### A2.4.4 Origen de hábitat del reclutado (fase 2)

**PROPUESTA**
- `reclutados.habitat_origen_id BIGINT NULL FK -> habitats(id) ON DELETE SET NULL`, index.
- Se rellena al reclutar/capturar si el flujo conoce el hábitat. Hoy no lo conoce: `CrearReclutado` no recibe hábitat y `reclutables` no lo guarda (`CrearReclutado.php:20-37`, `2026_08_26_000002_create_reclutables_table.php:15-21`).
- Mientras no exista, el MVP usa la comprobación de especie nativa.

### A2.4.5 Claves de inventario

**PROPUESTA**
- `baya:{slug_inglés}`: `baya:oran`, `baya:sitrus`, `baya:liechi`, etc.
- `bonguri:{color}`: `bonguri:blanco`, `bonguri:rojo`, etc.
- Longitud máxima: `bonguri:amarillo` = 17; `baya:roseli` = 12; caben en `varchar(64)`.
- **Conflicto a resolver:** el catálogo de combate actual usa `sitrus_berry` y `lum_berry` (análisis previo §2.2, `src/Battle/Domain/Catalog/CatalogoObjetos.php:20-29`). La misma baya no debe tener dos claves. Opciones:
  1. `baya:*` es la clave de material y `sitrus_berry` sigue siendo la clave del objeto equipable, con un mapping explícito `ClaveBaya -> ClaveItem`.
  2. Unificar a `baya:*` y migrar `obj_equipados`/`ClaveItem`.
  - **Recomendación MVP:** opción 1 con mapping en código; no tocar `obj_equipados` en esta fase.

### A2.4.6 Resumen de migraciones nuevas

```text
2026_11_xx_000001_add_modo_to_exploraciones_activas.php
2026_11_xx_000002_add_bonguri_to_habitats.php
2026_11_xx_000003_create_nest_plantings_table.php
-- fase 2 opcional:
2026_11_xx_000004_add_habitat_origen_id_to_reclutados.php
```

Todas aditivas y nullable salvo `nest_plantings`, que es tabla nueva. Sin cambios destructivos.

---

## A2.5 Flujos

### A2.5.1 Inicio de exploración con modo

**PROPUESTA**
1. La UI del modal de exploración (`habitats/show.blade.php:478-630`) añade un selector de modo (3 tarjetas/radios) antes de la duración.
2. `loadPreview()` incluye `modo` en el query string (`:1970-1979`). `preview()` valida `modo` y lo pasa al estimador.
3. `confirmExploration()` incluye `modo` en el body (`:2045-2055`).
4. `validarYNuevaExploracion()` valida `modo` y lo guarda en `ExploracionActiva::create(...)` (`ExploracionActivaController.php:295-343`).
5. `storeIndividual()` devuelve `modo` en el contrato JSON (`:277-283`), de forma aditiva.
6. `ProcesarExploracionHandler` lee `$exploracion->modo` y construye un `PerfilExploracion`:
   - `MIXTO`/`null` → constantes actuales.
   - `EXPERIENCIA`/`RECLUTAMIENTO`/`BOTIN` → pesos y multiplicadores de A2.6.
7. El perfil se pasa a `SimuladorEncuentros::generarEventosDesdePool(...)` y a `FinalizarExploracionHandler` para el botín.

**Compatibilidad:** una exploración antigua con `modo = null` sigue exactamente igual. No hay cambio de contrato obligatorio.

### A2.5.2 Generación de eventos por modo

**PROPUESTA**
- No crear un tipo de evento nuevo en MVP. El modo se implementa como **perfil de pesos + multiplicadores**:
  - `PerfilExploracion` (VO readonly) con:
    - `pesosEvento`: encuentro, hallazgo, especial, contratiempo, neutral (suman 100).
    - `multiplicadorExp`: sobre EXP.
    - `multiplicadorCaramelos`: sobre caramelos.
    - `factorCaptura`: sobre la probabilidad de captura.
    - `multiplicadorBotin`: sobre el botín final.
- `SimuladorEncuentros::generarEventosDesdePool` recibe `?PerfilExploracion $perfil = null`; si es `null`, usa las constantes actuales. Es un cambio **aditivo** y conserva la firma antigua para tests/BC.
- `generarEventoDesde` sustituye `self::PROBABILIDAD_*` por los pesos del perfil. La lógica de subtipos y hallazgos no cambia.
- El modo `BOTIN` no necesita “evento de objeto” si el botín se calcula al finalizar (A2.5.4). Si se quiere diario rico, fase 2: nuevo subtipo `recoleccion` con `item_key` en `EventoExploracion` (hoy no existe, `EventoExploracion.php:27-59`).

**INFERENCIA:** parametrizar `SimuladorEncuentros` es suficiente para el MVP; no hace falta un nuevo tipo de evento. Un evento nuevo solo aporta valor visual, no funcional.

### A2.5.3 Bonus por Pokémon del hábitat

**PROPUESTA MVP**
- `EsDelHabitat` (servicio App puro o método de dominio) comprueba:
  - `pokemon_habitat` contiene `(reclutado.pokemon_id, exploracion.habitat_id)` para el nivel de la exploración o cualquier nivel.
  - Alternativa de fase 2: `reclutado.habitat_origen_id === exploracion.habitat_id`.
- Se calcula una vez en `ProcesarExploracionHandler` y en `FinalizarExploracionHandler` (o se persiste en `eventos['bonos']` para no repetir la consulta).
- Efectos:
  - `ProcesarExploracionHandler`: `+10 %` de cadencia (reduce intervalo) o `+1` evento por tick; **no** aumentar la dificultad.
  - `FinalizarExploracionHandler`: `×1,25` sobre cantidad de botín y `+10 pp` en la probabilidad de bonguri; `×1,10` sobre EXP/caramelos como máximo.
- Se aplica **una sola vez** y se documenta en el resultado (`eventos['bonos']['del_habitat'] = true`) para que la UI lo muestre.

**Por qué no solo `habitat_origen_id`:** no existe y no se puede rellenar retroactivamente con fiabilidad. La comprobación de especie es verificable hoy (`pokemon_habitat`).

### A2.5.4 Cálculo del botín

**PROPUESTA (opción A del análisis previo, ampliada)**
1. `CalculadorBotinExploracion` (Domain puro) recibe un DTO `ContextoBotin`:
   - `modo: ModoExploracion`
   - `bonguriHabitat: ?ClaveBonguri`
   - `nivelHabitat: int` (1–3)
   - `duracionHoras: int`
   - `categoria: string` (éxito/parcial/fracaso/retirada)
   - `delHabitat: bool`
   - `pesosBayasPlantadas: MapaFactores` (tipo → multiplicador)
   - `aleatorio: callable`
2. Devuelve `RecompensaItemCollection` de `RecompensaItem { clave: string, cantidad: int }`.
3. Se llama en `FinalizarExploracionHandler` **después** de `restarPerdidas` y **antes** de `persistir`:
   ```
   $botin = $calculadorBotin->calcular($contexto);
   $recompensas = $recompensas->sumarBotin($botin);
   ```
4. `ResultadoRecompensas` gana `items` con default `[]` para no romper constructores existentes (`ResultadoRecompensas.php:44-58`).
5. `PersistirRecompensas::persistir` recorre `items` e incrementa `player_inventory` (`PersistirRecompensas.php:34-48`, `:80-90`).
6. `TransformadorResultadoExploracion` añade `items` al contrato (`:50-61`).
7. `ResultadoExploracionVista` y `index.blade.php` pintan el botín.
8. Pérdida por derrota: incluir `items` en `objetosPerdidos()`/`restarPerdidas()` (`FinalizarExploracionHandler.php:615-728`) con `tipo = 'baya'|'bonguri'`.

**Idempotencia:** el handler ya es idempotente por `regreso` (`:78-83`) y el `CommandBus` declara que todo comando se ejecuta en transacción (`src/Shared/Bus/CommandBus.php:6-8`). El botín debe ir dentro de esa misma transacción.

**Por evento vs al final:** el botín al final es el MVP. El botín por evento exige `item_key` en `EventoExploracion` y una rama nueva en `EventoExploracionVista` (`:211-232`), además de migrar payloads persistidos. Se deja para fase 2.

### A2.5.5 Plantación

**PROPUESTA**
1. `POST /api/habitats/{habitat}/farm/nests/{chain}/plantings` body `{ slot: int, berry: string }`.
2. Caso de uso `PlantarBaya`:
   - `AsegurarGranja::para(userId, habitatId)` (`AsegurarGranja.php:16-35`).
   - `FamiliaEnHabitat::asegurar(habitatId, chain)` (patrón de `MejorarNido.php:27`).
   - Busca `Nido` por `farm_id + evolution_chain_id`; debe existir y `level >= 1`.
   - Valida `1 <= slot <= level*2` y que no exista ya una fila `(nest_id, slot)`.
   - Valida que `berry` sea una `ClaveBaya` del catálogo.
   - Consume 1 `baya:{slug}` del inventario con `lockForUpdate`.
   - Inserta `nest_plantings` con `planted_at = now()`, `harvest_count = 0`.
   - Todo en `DB::transaction`.
3. Respuesta: `GranjaDetalle` completo (patrón actual de `GranjaController::respuesta()`, `:55-60`).

### A2.5.6 Crecimiento y cosecha

**PROPUESTA**
1. `POST /api/habitats/{habitat}/farm/nests/{chain}/plantings/{slot}/harvest`.
2. Caso de uso `CosecharPlantacion`:
   - Carga la fila con `lockForUpdate` verificando que pertenece a una granja del usuario y al hábitat.
   - `dias = floor(now - planted_at / 24h)`; `stock = min(3, dias - harvest_count)`.
   - Si `stock <= 0` → `ViolacionReglaNegocio` 422.
   - Incrementa `baya:{berry}` en `stock`.
   - `harvest_count += stock`.
   - Si la política es “3 cosechas totales”, borra la fila al llegar a 3; si es “stock máximo 3”, conserva la planta.
3. No hay cron ni job: el estado se calcula al leer. Esto elimina la necesidad de un planificador y evita carreras de “maduración”.

**Auto-cosecha (fase 2):** al finalizar una exploración en el hábitat, si el reclutado tiene rol `RECOLECTOR` (`src/Exploraciones/Domain/RolExploracion.php:20`) y el modo es `BOTIN`, recorrer las plantaciones de la granja del usuario en ese hábitat y cosechar todas las que tengan stock. Reutiliza `CosecharPlantacion`. Añadir el botín cosechado al resumen.

### A2.5.7 Influencia de la plantación en bayas salvajes

**PROPUESTA**
- Solo la granja del **mismo usuario y mismo hábitat** (`farms.user_id`, `farms.habitat_id`).
- Se agregan las plantaciones por `berry_key` de todos los nidos de esa granja.
- Cada tipo plantado modifica su peso en la tabla de botín de bayas:
  - `factor(tipo) = 1 + 0,20 × min(slots(tipo), 5)` → máximo `×2,0`.
  - Varios tipos se aplican de forma independiente y se renormaliza el total.
- No afecta a la cosecha (la cosecha devuelve el tipo plantado), ni a otros hábitats, ni al botín de bonguri.
- La tabla base de bayas es global en MVP (todas las 36 con pesos por rareza). No existe hoy una tabla `habitat_berries`; crearla es fase 2 si se quiere que cada hábitat tenga bayas nativas.

---

## A2.6 Fórmulas y valores por defecto propuestos

> Todos los valores son **PROPUESTA**, ajustables tras telemetría. Deben vivir en constantes/enums (`ReglasExploracion`, `PerfilExploracion`, `ReglasPlantacion`), nunca como literales dispersos (`.ai/rules/arrays-dto-collections.md`).

### A2.6.1 Pesos de evento por modo (suman 100)

| Modo | Encuentro | Hallazgo | Especial | Contratiempo | Neutral |
|---|---:|---:|---:|---:|---:|
| `mixto` (actual) | 45 | 20 | 15 | 10 | 10 |
| `experiencia` | 65 | 5 | 15 | 10 | 5 |
| `reclutamiento` | 55 | 10 | 15 | 10 | 10 |
| `botin` | 20 | 45 | 10 | 15 | 10 |

### A2.6.2 Multiplicadores por modo

| Modo | EXP | Caramelos | Captura | Botín |
|---|---:|---:|---:|---:|
| `mixto` | ×1,00 | ×1,00 | ×1,00 | ×1,00 |
| `experiencia` | ×1,25 | ×0,75 | ×0,50 | ×0,50 |
| `reclutamiento` | ×0,75 | ×0,75 | ×2,00 | ×0,50 |
| `botin` | ×0,50 | ×0,50 | ×0,50 | ×1,50 |

Notas:
- Los multiplicadores de EXP/caramelos se aplican con los parámetros existentes `multiplicadorExp`/`multiplicador` de `CalculadorRecompensas::calcular` (`:51-85`).
- El factor de captura debe aplicarse en `FinalizarExploracionHandler::rollAleatorio` (`:491-525`) multiplicando la probabilidad final. Requiere verificar la firma de `ProbabilidadCaptura::intentarConFactores` antes de implementar; hoy recibe `captureRate`, factor hábitat, factor refugio, aleatorio y factor Senda (`:517-523`).
- `BOTIN` no debe dar EXP alta; `EXPERIENCIA` no debe dar botín alto.

### A2.6.3 Bonus por Pokémon del hábitat

| Efecto | Valor |
|---|---:|
| Reducción de intervalo entre eventos | −10 % |
| Multiplicador de botín (cantidad) | ×1,25 |
| Probabilidad de bonguri | +10 pp |
| Multiplicador de EXP/caramelos | ×1,10 (opcional) |

### A2.6.4 Botín de bayas por expedición

- Tirada base por expedición finalizada, no por evento:
  - `n = 0..3` bayas según modo y duración.
  - `botin`: 1–3 bayas (media 2).
  - `experiencia`: 0–1.
  - `reclutamiento`: 0–1.
- Selección ponderada por rareza (común → rara). La tabla exacta de 36 bayas y pesos queda para el plan de implementación; no se inventa aquí.
- Con bonus de hábitat: `ceil(n × 1,25)` y se permite un tipo de mayor rareza con probabilidad +10 %.

### A2.6.5 Botín de bonguri

- Un único tipo por expedición: el del hábitat.
- Probabilidad base por modo:
  - `botin`: 60 %.
  - `experiencia`: 25 %.
  - `reclutamiento`: 25 %.
  - `mixto`: 30 %.
- Con bonus de hábitat: `+10 pp`, tope 85 %.
- Cantidad: 1; con tirada < 0,25 y bonus de hábitat → 2.
- Tope por expedición: 2 bonguris (evita explotación de 72 h).

### A2.6.6 Plantación y cosecha

| Parámetro | Valor |
|---|---|
| Huecos por nivel de nido | 2 |
| Huecos nivel 0 | 0 |
| Crecimiento | 24 h desde `planted_at` |
| Cosechas acumulables | 3 |
| Coste de plantar | 1 baya |
| Rendimiento por cosecha | 1 baya; +1 con 35 % |
| Replantar sin cosechar | Permitido; pierde stock |
| Cosecha automática | Solo fase 2 (`RECOLECTOR` + `BOTIN`) |

### A2.6.7 Influencia de plantaciones

| Parámetro | Valor |
|---|---|
| Bonus por hueco del mismo tipo | +20 % de peso |
| Tope por tipo | ×2,0 (5 huecos) |
| Ámbito | Misma granja (usuario + hábitat) |
| Afecta a bonguri | No |
| Afecta a otros hábitats | No |

---

## A2.7 UI necesaria

### Exploraciones

**HECHO:** el modal actual no tiene modo (`habitats/show.blade.php:478-630`) y el resumen no pinta objetos (`index.blade.php:305-362`).

**PROPUESTA**
1. **Selector de modo** en el modal de exploración, antes de la duración:
   - “Pelear” (experiencia), “Buscar” (reclutamiento), “Explorar” (botín).
   - Cada opción con descripción corta y aviso de qué prioriza.
   - Persistir en estado Alpine `modo` y enviarlo en preview/store.
2. **Preview**:
   - Mostrar el modo y las recompensas esperadas del modo (reutilizar `recompensas_esperadas.items`, `:538-571`).
   - Si el Pokémon es del hábitat, mostrar badge “Conoce el terreno” y el bonus.
3. **Tarjeta activa** (`index.blade.php:38-160`): badge de modo junto al hábitat/nivel.
4. **Resumen de resultados**:
   - Nueva sección “Objetos encontrados” con icono + nombre + cantidad.
   - Incluir bayas y bonguris en “Objetos perdidos” (`_perdidas.blade.php`).
   - No calcular nada en Blade: `ResultadoExploracionVista` debe entregar `items` ya resueltos (nombre, icono, cantidad).

### Granja

**PROPUESTA**
1. En el bloque de cada familia del modal de granja (`habitats/show.blade.php:1126-1245`), añadir “Plantaciones”:
   - Grid de `nivel*2` huecos.
   - Hueco vacío: botón “Plantar” → selector de bayas del inventario (`baya:*`) con cantidad disponible.
   - Hueco plantado: icono de baya, barra/estado de crecimiento, stock acumulado (0–3), botón “Cosechar” habilitado solo si `stock > 0`.
   - Huecos bloqueados por nivel: mostrar candado y texto “Mejora el nido”.
2. Reutilizar `accionGranja()` (`:1661-1717`) para POST y refresco del `GranjaDetalle`.
3. Mostrar el bonguri del hábitat en la cabecera de la granja o del hábitat (icono + nombre), como información de botín.
4. Contrato backend: ampliar `NidoGranja` con `plantaciones` y `huecos_totales`; nuevo DTO `PlantacionGranja` con `slot`, `berry_key`, `berry_nombre`, `berry_icono`, `planted_at`, `stock`, `puede_cosechar`, `progreso_pct`, `estado`.
5. No introducir cálculos de crecimiento en JS: el backend entrega `stock`, `progreso_pct` y `puede_cosechar`.

---

## A2.8 Riesgos

| Nivel | Riesgo | Impacto | Mitigación |
|---|---|---|---|
| **Alto** | Cambiar `ResultadoRecompensas` rompe finalización, pérdidas y presentación | Regresión en todas las exploraciones | Añadir `items` con default `[]`; no reordenar parámetros; actualizar `sumarHallazgos`, `restarPerdidas` y transformador en el mismo cambio |
| **Alto** | Botín no idempotente o fuera de transacción | Duplicación de inventario | Calcular y persistir dentro del `CommandBus` transaccional; mantener idempotencia por `regreso` (`FinalizarExploracionHandler.php:78-83`) |
| **Alto** | Explotación de `BOTIN` con 72 h | Inflación de bayas/bonguris | Topes por expedición (máx. 3 bayas + 2 bonguris), multiplicador de duración con rendimientos decrecientes, telemetría |
| **Alto** | Concurrencia al plantar/cosechar | Doble cosecha, stock negativo, huecos duplicados | `UNIQUE(nest_id, slot_index)`, `lockForUpdate`, recalcular stock dentro de la transacción |
| **Medio** | “Del hábitat” por especie es ambiguo | Bonus percibido como injusto o explotable | Documentar la regla en UI; fase 2 con `habitat_origen_id` |
| **Medio** | Bonguri mal asignado o nulo en provincia “Vacío” | Botín incoherente | Backfill determinista + fallback explícito + validación en dominio |
| **Medio** | Claves de baya enviadas por el cliente | Inyección de items o claves huérfanas | Validar contra enum `ClaveBaya`; nunca aceptar `item_key` libre |
| **Medio** | Duplicidad `baya:sitrus` vs `sitrus_berry` | Dos inventarios para la misma baya | Mapping explícito; no unificar en esta fase |
| **Medio** | Autorización de nidos/plantaciones | IDOR entre usuarios/hábitats | Resolver siempre `farm` por `(userId, habitatId)`; verificar `nest.farm_id` y `chain` en hábitat; `BelongsToUser` no cubre `Nido` (no usa el trait) |
| **Medio** | Sin tabla de bayas nativas por hábitat | “Baya salvaje del lugar” queda indefinida | MVP: tabla global ponderada + bonus por plantación; fase 2: `habitat_berries` |
| **Bajo** | Cálculo de stock con zonas horarias | Cosechas adelantadas/atrasadas | Usar timestamps UTC y diferencias absolutas; no usar “día natural” |
| **Bajo** | Respuesta completa de granja tras cada acción | Payload grande con muchas familias | Ya ocurre hoy (`GranjaController.php:55-60`); aceptar en MVP |
| **Bajo** | Iconos de bayas/bonguris incompletos | UI con placeholders | `roseli-berry.webp` falta (análisis previo §8.1); fallback `unknown.webp` |
| **Bajo** | La vista de resultados no tolera `items` ausente | Error en exploraciones antiguas | Default `[]` en presentador y `@if(!empty(...))` en Blade |

**Riesgo específico de autorización:** `Nido`, `Refugio` y `Complemento` no usan `BelongsToUser` (`app/Models/Nido.php:14-36`). La autorización depende de resolver la granja por `user_id`. Cualquier endpoint nuevo debe repetir ese patrón y no confiar en el `nest_id` de la URL.

---

## A2.9 Preguntas abiertas

1. **¿“3 cosechas acumulables” significa stock máximo 3 o 3 cosechas totales por planta?**
   - Recomendación: **stock máximo 3, planta persistente**. Es lo que sugiere “acumulable” y evita que la granja muera sin intervención. Si se prefiere total, la fila se borra a las 3.
2. **¿Plantar consume una baya del inventario?**
   - Recomendación: **sí, 1 baya por hueco**. Sin coste, la plantación no compite con la forja.
3. **¿La cosecha es manual, automática al explorar, o ambas?**
   - Recomendación MVP: **manual desde la granja** (determinista y sin acoplar exploración). Fase 2: auto-cosecha al finalizar una expedición `BOTIN` con rol `RECOLECTOR`.
4. **¿Qué define “Pokémon del hábitat”?**
   - Recomendación MVP: **especie presente en `pokemon_habitat`**. Fase 2: `reclutados.habitat_origen_id`.
5. **¿El bonguri se asigna por hábitat o por provincia?**
   - Recomendación: **columna en `habitats` con backfill desde la provincia** (evidencia `ProvinceSeeder.php:18-33`). Permite excepciones y resuelve “Vacío”.
6. **¿El bonguri cae siempre o con probabilidad?**
   - Recomendación: **probabilidad por modo** (A2.6.5), no drop garantizado.
7. **¿Las bayas salvajes son universales o nativas de cada hábitat?**
   - Recomendación MVP: **tabla global ponderada**, con bonus por plantación. Fase 2: `habitat_berries`.
8. **¿El modo afecta también a la captura o solo a los eventos?**
   - Recomendación: **sí, la captura**, con `factorCaptura` por modo (A2.6.2). Si no, “buscar para reclutar” no se diferencia de “pelear”.
9. **¿Se puede replantar un hueco antes de cosechar?**
   - Recomendación: **sí, pero se pierde el stock**. Documentarlo en la UI.
10. **¿Las plantas se marchitan?**
    - Recomendación MVP: **no**. El stock se queda en 3 hasta que se cosecha.
11. **¿La cosecha recoge todo el stock de golpe o de una en una?**
    - Recomendación: **todo el stock** en una acción.
12. **¿El crecimiento es 24 h exactas o “día natural”?**
    - Recomendación: **24 h exactas desde `planted_at`**; evita problemas de zona horaria.
13. **¿Un nido de nivel 0 tiene huecos?**
    - Recomendación: **no, 0 huecos**. Hay que construir el nido primero.
14. **¿Las bayas de la forja y las de plantar son el mismo inventario?**
    - Recomendación: **sí**, `player_inventory` con `baya:*`. La baya se consume al plantar o al fabricar.
15. **¿El botín de combate futuro comparte el mismo `CalculadorBotinExploracion`?**
    - Recomendación: **sí, extraer un `OtorgarBotinLugar` reutilizable** y llamarlo desde exploración y desde ruta/entrenador.

---

## A2.10 Criterios de aceptación propuestos

### Modo de exploración
- [ ] `exploraciones_activas.modo` existe, es nullable y no rompe filas antiguas.
- [ ] La UI envía `modo`; el backend lo valida contra el enum.
- [ ] Una exploración `modo = null` se comporta exactamente como antes (mismos pesos 45/20/15/10/10).
- [ ] `experiencia`, `reclutamiento` y `botin` producen mezclas de eventos distintas y verificables.
- [ ] El preview y el resumen muestran el modo elegido.

### Bonguri
- [ ] Todos los hábitats tienen un `bonguri` válido o fallback documentado.
- [ ] Una expedición en un hábitat solo puede devolver el bonguri de ese hábitat.
- [ ] No se entrega bonguri si el hábitat no tiene color y el fallback está deshabilitado.
- [ ] El bonguri se muestra con nombre e icono en el resumen.

### Botín
- [ ] Finalizar una exploración incrementa el inventario exactamente con las cantidades del botín.
- [ ] Repetir la finalización no duplica el botín (idempotencia por `regreso`).
- [ ] El resumen muestra bayas y bonguris con nombre e icono.
- [ ] En derrota se pierde `intdiv(cantidad, 2)` de cada grupo y aparece en `objetos_perdidos`.
- [ ] Una clave desconocida nunca llega a `player_inventory`.
- [ ] El botín está topado por expedición (máx. 3 bayas + 2 bonguris).

### Bonus de hábitat
- [ ] Un Pokémon de especie nativa del hábitat recibe el bonus; uno no nativo no.
- [ ] El bonus se aplica una sola vez y se refleja en el resultado.
- [ ] El bonus no altera la dificultad ni la probabilidad de derrota de forma desproporcionada.

### Plantación
- [ ] Un nido de nivel N ofrece exactamente `N*2` huecos.
- [ ] No se puede plantar en un `slot_index` fuera de rango ni duplicar `(nest_id, slot_index)`.
- [ ] Plantar consume 1 baya y falla con 422 sin saldo.
- [ ] Se pueden repetir bayas en huecos distintos.
- [ ] El stock se calcula a las 24 h, 48 h y 72 h y se topa en 3.
- [ ] Cosechar dos veces seguidas no duplica la baya.
- [ ] La granja devuelve el estado de cada plantación y la UI lo pinta sin cálculos propios.
- [ ] Un usuario no puede plantar ni cosechar en nidos de otro usuario.
- [ ] Una baya fuera del catálogo se rechaza con 422.

### Integración y arquitectura
- [ ] El dominio nuevo (`CalculadorBotinExploracion`, `PerfilExploracion`, `ReglasPlantacion`) no importa Eloquent/HTTP.
- [ ] Los nuevos contratos usan DTOs/colecciones, no arrays asociativos (`.ai/rules/arrays-dto-collections.md`).
- [ ] No se modifican tests.
- [ ] No se rompe el contrato actual de `ResultadoRecompensas` para los flujos de ruta/entrenador.

---

## A2.11 Fases y dependencias

### Fase A2-1 — Modo + bonguri + botín (MVP)
Dependencias: ninguna externa; toca `Exploraciones` y `habitats`.
- Migraciones: `modo`, `bonguri`.
- `ModoExploracion`, `PerfilExploracion`, `ClaveBonguri`.
- `SimuladorEncuentros` parametrizado (default = actual).
- `CalculadorBotinExploracion` + `RecompensaItem` + `items` en `ResultadoRecompensas`.
- `PersistirRecompensas`, `Transformador`, `ResultadoExploracionVista` e `index.blade.php`.
- Pérdidas por derrota con bayas/bonguris.
- Catálogo `baya:*`/`bonguri:*` en `ItemCatalogo` y assets.
- UI: selector de modo, badge, resumen de objetos.

### Fase A2-2 — Plantación y cosecha
Dependencias: `player_inventory`, `Granjas`, `ItemCatalogo` de A2-1.
- Migración `nest_plantings`.
- `PlantarBaya`, `CosecharPlantacion`, `ReglasPlantacion`.
- Endpoints y DTOs (`PlantacionGranja`, ampliación de `NidoGranja`/`GranjaDetalle`).
- UI de huecos, plantar, cosechar.
- No requiere modo de exploración, pero comparte el catálogo de bayas.

### Fase A2-3 — Influencia de plantaciones y bonus de hábitat
Dependencias: A2-1 y A2-2.
- `EsDelHabitat` + bonus en procesamiento/finalización.
- `PesosBayasPlantadas` en el contexto de botín.
- Auto-cosecha con `RECOLECTOR` + `BOTIN` (opcional).
- Telemetría y ajuste de topes.

### Fase A2-4 — Forja
Dependencias: A2-1 (bayas/bonguris en inventario).
- Según análisis previo §6.4 y fases 2–3.
- No depende de plantaciones, pero se beneficia del suministro de bayas.

### Fase A2-5 — Uso de objetos en combate
Dependencias: A2-4.
- `AccionObjeto`, `ServicioUsoObjetos`, fases de UI, descuento de inventario.
- Según análisis previo §5.

### Fase A2-6 — Botín en combates (futuro)
Dependencias: A2-1 (`CalculadorBotinExploracion` reutilizable).
- Extraer `OtorgarBotinLugar` y llamarlo desde:
  - `OtorgarRecompensasRuta::otorgar` tras `persistir` (`src/CombateRuta/App/OtorgarRecompensasRuta.php:147`).
  - `OtorgarRecompensasEntrenador` (pendiente de inspección).
- Requiere pasar `habitat_id` a `otorgar`; hoy solo recibe `nivelHabitat` (`OtorgarRecompensasRuta.php:73-81`). `IniciarCombateRuta` ya guarda `habitat_id` (`src/CombateRuta/App/IniciarCombateRuta.php:69`) y `Combate` Livewire lo expone (`app/Livewire/Combate.php:67`, `:767`).
- Sin UI nueva en MVP de esta fase; solo punto de extensión y tope.

**Orden recomendado:** A2-1 → A2-2 → A2-3 → A2-4 → A2-5 → A2-6. Coincide con la decisión D-05 (exploración → forja → combate) e intercala la granja antes de la forja porque comparte inventario de bayas.

---

## Archivos inspeccionados

**Petición y análisis previo**
- `.opencode/work/TASK-011/request.md`
- `.opencode/work/TASK-011/analysis.md`
- `.ai/rules/index.md`, `arrays-dto-collections.md`, `ddd-modules.md`, `frontend-minimal-logic.md`

**Exploraciones**
- `app/Models/ExploracionActiva.php`, `app/Models/Habitat.php`, `app/Models/Reclutado.php`
- `app/Http/Controllers/ExploracionActivaController.php`
- `routes/exploraciones.php`, `routes/web.php`
- `src/Exploraciones/Domain/SimuladorEncuentros.php`, `CalculadorRecompensas.php`, `ReglasExploracion.php`, `EconomiaRecompensas.php`, `CalculadorVueltaExploracion.php`, `EvaluadorExploracion.php`, `RolExploracion.php`, `CadenciaEventos.php`, `EstimadorRecompensasExploracion.php`
- `src/Exploraciones/Domain/ValueObjects/EventoExploracion.php`, `PoolHabitat.php`, `PokemonDelPool.php`, `ResultadoBatallaExploracion.php`
- `src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php`
- `src/Exploraciones/App/ProcesarExploracionHandler.php`, `FinalizarExploracionHandler.php`, `PersistirRecompensas.php`, `ProcesarExploracionesPendientes.php`, `CombateExploracion.php`
- `src/Exploraciones/Presentation/PresentadorExploraciones.php`, `TransformadorResultadoExploracion.php`, `EventoExploracionVista.php`, `ResultadoExploracionVista.php`

**Granja**
- `src/Granjas/context.md`
- `src/Granjas/Infra/Controllers/GranjaController.php`
- `src/Granjas/App/ObtenerGranja.php`, `AccionesGranja.php`, `AsegurarGranja.php`, `InventarioGranja.php`, `MejorarNido.php`
- `src/Granjas/Domain/CalculadoraBonosGranja.php`, `DataTransferObjects/NidoGranja.php`, `GranjaDetalle.php`
- `app/Models/Granja.php`, `Nido.php`, `Province.php`
- `routes/granjas.php`
- `database/migrations/2026_10_26_000001_create_farm_tables.php`

**Hábitats y datos**
- `database/migrations/2026_02_24_171453_create_habitats_table.php`
- `2026_02_24_201538_create_pokemon_habitat_table.php`
- `2026_08_26_000005_add_pokemons_json_to_habitats.php`
- `2026_08_29_000010_add_min_lvl_columns_to_habitats_table.php`
- `2026_08_30_000001_add_peligro_to_habitats_table.php`
- `2026_09_02_000001_add_favorito_to_reclutados_table.php`
- `2026_08_26_000002_create_reclutables_table.php`
- `database/seeders/ProvinceSeeder.php`, `HabitatSeeder.php`
- `storage/data/habitats.csv`

**Inventario, reclutamiento y combate de ruta**
- `app/Support/ItemCatalogo.php`, `CrearReclutado.php`
- `app/Http/Controllers/ReclutamientoController.php`
- `app/Models/Concerns/BelongsToUser.php`
- `src/CombateRuta/App/OtorgarRecompensasRuta.php`, `Domain/DataTransferObjects/ResultadoRuta.php`
- `src/Shared/Bus/CommandBus.php`

**UI**
- `resources/views/habitats/show.blade.php` (modal de exploración, modal de granja, JS de preview/store/acciones)
- `resources/views/exploraciones/index.blade.php`
- `resources/views/exploraciones/_evento.blade.php` (referenciado por el análisis previo)

---

## Decisiones tomadas en este análisis

1. El modo se modela como **columna nullable + enum**, con `null` = comportamiento actual. No se rompe ninguna exploración en curso.
2. El modo se implementa **parametrizando `SimuladorEncuentros`**, no creando un tipo de evento nuevo en MVP.
3. El botín se calcula **al finalizar** (opción A), no por evento.
4. El bonguri se asigna **por hábitat** con backfill desde la provincia; no se crea tabla de mapping.
5. Las plantaciones viven en **una tabla nueva `nest_plantings`** con una fila por hueco plantado y stock calculado por tiempo.
6. La cosecha MVP es **manual desde la granja**; la auto-cosecha con `RECOLECTOR` queda para fase 2.
7. “Pokémon del hábitat” se resuelve por **especie en `pokemon_habitat`** en MVP; el campo de origen es fase 2.
8. La probabilidad de bayas salvajes se modifica solo por **plantaciones de la misma granja/hábitat**, con tope ×2,0.
9. Las claves nuevas son `baya:*` y `bonguri:*`; se mantiene el mapping con las claves de combate existentes.
10. El botín de combate futuro se centraliza en un **servicio reutilizable** llamado desde ruta/entrenador/exploración.

---

## Verificación ejecutada

- Lectura estática de los archivos listados.
- `git status --short`, `git branch --show-current` (solo lectura; rama `master`; cambios preexistentes en `.githooks/pre-commit` y `MVP.txt`).
- `grep`/`rg` sobre exploraciones, granja, nidos, bayas, bonguris, inventario, recompensas, autorización y UI.
- Conteo de hábitats y provincias en `storage/data/habitats.csv`.
- **No** se ejecutaron tests, migraciones, seeders ni comandos de escritura.
- **No** se modificó ningún archivo.
- **No** se leyó `.env` ni secretos.

---

## Problemas encontrados

1. **No existe modo de exploración** y el contrato actual no lo contempla (`ExploracionActivaController.php:295-343`).
2. **No existe botín de objetos** en `ResultadoRecompensas` (`ResultadoRecompensas.php:44-58`).
3. **No existe origen de hábitat** en reclutados (`Reclutado.php:22-36`; `CrearReclutado.php:20-37`).
4. **No existe plantación ni bayas en la granja** (`Nido.php:18-36`).
5. **`Nido`/`Refugio`/`Complemento` no usan `BelongsToUser`**; la autorización depende de resolver la granja (`app/Models/Concerns/BelongsToUser.php:17-30`).
6. **Duplicidad potencial de claves de baya** entre `sitrus_berry`/`lum_berry` y `baya:*`.
7. **La provincia “Vacío” no tiene bonguri** en el seeder (`ProvinceSeeder.php:33`).
8. **No hay tabla de bayas nativas por hábitat**, así que “baya salvaje del lugar” no está definida.
9. **La vista de resultados no pinta objetos** (`ResultadoExploracionVista.php:57-85`).
10. **El contrato de eventos no tiene `item_key`** (`EventoExploracion.php:27-59`), lo que bloquea el botín por evento.
11. **`OtorgarRecompensasRuta` no recibe `habitat_id`**, solo `nivelHabitat` (`OtorgarRecompensasRuta.php:73-81`), lo que limita el hook de botín de combate.

---

## Riesgos pendientes

- **Balance:** sin telemetría, los multiplicadores por modo y los topes de botín pueden inflar la economía. Hay que instrumentar y ajustar.
- **Semántica de “3 cosechas”:** pendiente de confirmar stock vs total; cambia el modelo de borrado.
- **Origen del Pokémon:** la comprobación por especie es una aproximación; si el producto exige “capturado allí”, habrá que añadir `habitat_origen_id` y backfill.
- **Concurrencia:** plantar/cosechar requiere `lockForUpdate` y `UNIQUE`; sin ello, doble cosecha es posible.
- **Autorización:** cualquier endpoint nuevo debe resolver la granja por `(userId, habitatId)` y verificar la cadena; no confiar en IDs de la URL.
- **Contrato de exploración:** `items` debe ser opcional y tolerante a ausencia para no romper exploraciones antiguas.
- **Duplicidad de claves:** decidir el mapping `baya:*` ↔ `sitrus_berry`/`lum_berry` antes de implementar la forja.
- **Iconos:** falta `roseli-berry.webp` y los bonguris son PNG; la fase de iconos sigue pendiente.
- **Testing:** no se ejecutó nada por restricción; la verificación futura debe ser estática + manual hasta que el flujo lo permita.

---

**Estado de la inspección:** se alcanzó el límite de pasos del agente antes de leer `app/Bus/LaravelCommandBus.php` y `src/CombateEntrenadores/App/OtorgarRecompensasEntrenador.php`. La transaccionalidad del `CommandBus` se ha citado desde su interfaz (`src/Shared/Bus/CommandBus.php:6-8`), no desde la implementación. El hook de botín de entrenador queda como punto a verificar en la fase A2-6.
