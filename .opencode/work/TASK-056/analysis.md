# TASK-056 — Análisis

- **Autores:** `analyst` A (Forja, ítems, inventario y UI) y `analyst` B (Granjas/nidos, Pokémon, equipamiento y combate), subagentes independientes.
- **Fecha:** 2026-11-17.
- **Alcance:** solo lectura; sin cambios de código, datos ni tests.
- **Nota de coordinación:** se reproducen los dos informes; no se alteran sus conclusiones.

---

# Parte A — Forja, catálogo de ítems, inventario y UI

## RESUMEN

La Forja es un módulo delgado y estático: `CatalogoRecetas` construye 46 recetas en memoria (sin BD), `ListarRecetas` lee saldos del inventario en una sola consulta y `Fabricar` hace una transacción única consumir→incrementar. El catálogo de ítems (`CatalogoItems`) es la **puerta obligatoria de cualquier clave de inventario**: si una clave no existe ahí, `InventarioJugadorEloquent` lanza `ItemDesconocido` en cualquier lectura/consumo/escritura. La UI de `/forja` es presentacional pero tiene tres puntos **estáticos** (lista de categorías, mapeo prefijo→categoría y `textoMotivo`) que descartarían silenciosamente cualquier receta nueva.

Ya existe la infraestructura que la petición asume inexistente en parte: `habitats.bonguri` (con backfill por provincia), `player_inventory` con `unique(user_id, item_key)`, `reclutados.obj_equipados` JSON y `ability_pokemon`. No existe categoría ni clave de Arma/Armadura/Amuleto, ni equipamiento de 3 slots, ni múltiples objetos por combatiente en el motor.

## REQUISITOS

- **Funcionales:**
  - Nueva sección/fila en `/forja` para familias con nido construido (nivel ≥ 1) en algún hábitat del jugador.
  - Piezas Arma/Armadura/Amuleto por especie de la familia, con desbloqueo por nivel de nido: cadena de 3 etapas → 1/3/5; cadena de 2 → 2/4; sin evolución → 3.
  - Pieza única que evoluciona consumiendo la anterior (Gastly → Haunter → Gengar).
  - Stats por pieza según el set de la especie (arma Atk/SpA; armadura Def/SpD/HP; amuleto Vel + 50 % del mayor entre Atk/SpA/Def/SpD).
  - Bonguri del hábitat como ingrediente/bono.
  - Hasta 3 objetos equipados por reclutado, repetibles por tipo, de especies distintas.
  - Algunas piezas otorgan habilidad pasiva extra acumulable.
- **No funcionales:**
  - Sin arrays como contrato: DTOs readonly + `Collection` tipada + `MapaConteo`/`StringCollection`.
  - Backend dueño de cálculo y orden (`frontend-minimal-logic`).
  - Transaccionalidad y locks en el consumo de stock.
  - Compatibilidad con `SESSION_VERSION` / serialización de batalla si cambia el modelo de objeto.
- **Criterios de aceptación:** los 9 del encargo; 1–8 quedan cubiertos por evidencia abajo, 9 se entrega como preguntas abiertas.
- **Ambigüedades:** «fila por nido/bioma» vs «fila por familia»; significado exacto de «spd/spD» en el texto original; si el nivel del nido es solo gate o también coste; qué ocurre al evolucionar el Pokémon que lleva la pieza; convivencia con los 8 objetos de combate actuales.

## ALCANCE AFECTADO

- **Módulos:** `src/Forja` (Domain/App/Infra), `src/Shared/Domain/Items`, `src/Shared/Domain/Ports`, `src/Shared/Infra/Inventario`, `src/Granjas` (solo lectura de nidos/bonguri), `src/Battle` (objetos equipados, si el equipamiento entra al motor).
- **Archivos:** `src/Forja/Domain/CatalogoRecetas.php`, `RecetaFabricacion.php`, `src/Forja/App/ListarRecetas.php`, `Fabricar.php`, `RecetaDetalle.php`, `IngredienteDetalle.php`, `src/Forja/Infra/Controllers/ForjaController.php`, `src/Forja/Infra/Requests/FabricarRequest.php`, `src/Shared/Domain/Items/*`, `src/Shared/Infra/Inventario/InventarioJugadorEloquent.php`, `resources/views/forja/index.blade.php`, `routes/forja.php`.
- **Tablas/contratos:** `player_inventory` (clave + cantidad, sin identidad por instancia), `nests`/`farms`, `habitats.bonguri`, `reclutados.obj_equipados`, `ability_pokemon`.
- **Vistas/endpoints:** `GET /api/forja/recetas`, `POST /api/forja/fabricar`, `GET /forja` (todas bajo `auth`).

## EVIDENCIA

### 1) Construcción del catálogo de recetas y cómo añadir una familia nueva

- `src/Forja/Domain/CatalogoRecetas.php:108-210` — `construir()`: primero 9 recetas de poción literales (líneas 110-165), después **bucles sobre enums**: `ClaveBonguri::cases()` (167-174) para `receta:recipiente:{color}`, `ClaveMejora::cases()` (176-185), `ClaveReductor::cases()` (187-196), `ClaveResistencia::cases()` (198-207). Total 9 + 7 + 7 + 5 + 18 = **46** (coincide con `docs/architecture.md:376`).
- `CatalogoRecetas.php:215-229` — `crear()` es el único punto de construcción: clave de receta, `resultadoClave`, `resultadoCantidad: 1`, `ingredientes` como `MapaConteo`, y `categoriaResultado` **derivada de `CatalogoItems::definicion($resultadoClave)->categoria`** (línea 226). Es decir, la categoría no se declara en la receta: se hereda del catálogo de ítems.
- `CatalogoRecetas.php:25-33, 82-103` — memoización estática (`$lista`, `$indice`) con índice por `clave`. Añadir recetas es aditivo; `existe()`, `receta()`, `todas()`, `paraResultado()` y `resultados()` no cambian.
- `CatalogoRecetas.php:59-64` — `paraResultado()` agrupa variantes por `resultadoClave` (usado por la UI para el selector, ver §2).
- `src/Forja/Domain/RecetaFabricacion.php:30-46` — invariantes duras: **`resultadoCantidad !== 1` lanza `InvalidArgumentException`** (línea 30-32) y todos los ingredientes y el resultado deben existir en `CatalogoItems` (34-46).
- Conclusión verificada: **añadir una familia nueva no rompe nada mecánicamente** (basta un bloque nuevo en `construir()`), pero exige (a) claves y `DefinicionItem` en `CatalogoItems`, (b) una `CategoriaItem` nueva si se quiere categoría propia, y (c) que la UI conozca la categoría (§2), porque `CatalogoRecetas` no filtra ni conoce UI.

### 2) Agrupación y pintado en la vista: estático vs dinámico

- `resources/views/forja/index.blade.php:306-311` — **`FORJA_CATEGORIAS` es un array literal de 4 categorías** (`medicinas`, `mejoras`, `reductores`, `resistencias`). Es la lista cerrada de secciones.
- `forja/index.blade.php:313-335` — **`forjaCategoriaDeResultado(clave)` mapea por prefijo**: `material:recipiente` o `pocion:*` → `medicinas`; `mejora:*`; `reductor:*`; `resistencia:*`; **cualquier otra clave → `null`**.
- `forja/index.blade.php:388-452` — `agrupar()`: agrupa por `resultado_clave` (392-403), construye las categorías desde `FORJA_CATEGORIAS` (409-414) y **descarta los grupos sin categoría** (`const if (categoria) { categoria.recetas.push(grupo) }`, 420-426). `totalRecetas` (429-435) también se calcula sobre lo agrupado.
- Consecuencia: una receta de arma/armadura/amuleto **se sirve por API pero no se pinta** si no se añade su categoría a `FORJA_CATEGORIAS` y su rama en `forjaCategoriaDeResultado`.
- `forja/index.blade.php:160-206` — rama especial hardcodeada `grupo.clave === 'material:recipiente'` (lista de 7 variantes con origen). Es el **precedente a replicar** si la nueva sección necesita render propio (p. ej. fila por familia con 3 piezas).
- `forja/index.blade.php:515-525` — `textoMotivo()` solo conoce `faltan_materiales`. Un `motivo` nuevo (nivel de nido insuficiente, pieza ya poseída…) se mostraría como texto crudo.
- `forja/index.blade.php:465-469, 479-495` — `puedeFabricarGrupo`/`varianteSeleccionada` dependen exclusivamente de los flags del backend (`puede_fabricar`, `motivo`), lo que respeta `frontend-minimal-logic`; el único cálculo real en JS es `variantesRecipiente()` (orden por stock, 498-513).
- `forja/index.blade.php:350-353` — refresco al evento `inventario:actualizado` y recarga completa tras fabricar (`565`).

### 3) Contratos de `GET /api/forja/recetas` y `POST /api/forja/fabricar`

- `routes/forja.php:9-11` — rutas registradas; `routes/web.php:47` las importa dentro del grupo `auth` (líneas 18-49).
- `src/Forja/Infra/Controllers/ForjaController.php:31-38` — `recetas()` responde `{"recetas": [...]}`, mapeando `RecetaDetalle::toArray()`.
- `RecetaDetalle::toArray()` (`src/Forja/App/DataTransferObjects/RecetaDetalle.php:42-57`): `clave`, `resultado_clave`, `resultado_nombre`, `resultado_icono`, `resultado_cantidad`, `etiqueta`, `ingredientes[]`, `puede_fabricar`, `motivo`.
- `IngredienteDetalle::toArray()` (`IngredienteDetalle.php:34-44`): `clave`, `nombre`, `icono`, `requerido`, `disponible`, `origen`.
- Stock y `puede_fabricar`: `src/Forja/App/ListarRecetas.php:31-56` (recolecta todas las claves de ingrediente + resultado y hace **una** llamada a `saldos`), `:75-77` (`every` disponible ≥ requerido) y `:90` (`motivo: $puedeFabricar ? null : 'faltan_materiales'`). **Único motivo existente.**
- `origen`: `ListarRecetas.php:97-106` — solo se rellena si la clave empieza por `CategoriaItem::BONGURI->prefijo()` y se resuelve con `ClaveBonguri::tryFrom(...)->origen()`; resto `null`.
- `POST /api/forja/fabricar`: `ForjaController.php:40-52` + `FabricarRequest.php:20-27` — `receta` requerida string max 64; `cantidad` `sometimes|integer|in:1` (el FormRequest ya rechaza ≠1 con 422 antes de llegar al caso de uso). Respuesta `ResultadoFabricacion::toArray()` (`ResultadoFabricacion.php:32-40`): `receta`, `resultado`, `cantidad`, `saldos`.
- Errores: `RecetaNoExiste` extiende `RecursoNoExiste` → 404 (`src/Forja/Domain/Exceptions/RecetaNoExiste.php:12-17`; mapa en `bootstrap/app.php:19-26`). `ItemDesconocido` y `StockInsuficiente` extienden `ViolacionReglaNegocio` → **422** (`ItemDesconocido.php:10-15`, `StockInsuficiente.php:11-22`).
- Riesgo de contrato: `FabricarRequest` **no valida el prefijo ni el formato de la clave**, solo longitud; la existencia la resuelve `CatalogoRecetas::receta()`.

### 4) Flujo transaccional de `Fabricar` y soporte de resultados únicos

- `src/Forja/App/Fabricar.php:29-65` — orden: `CatalogoRecetas::receta()` (404) → guard `cantidad !== 1` (422) → `DB::transaction` → `consumirVarios` (lock ordenado por `item_key`, `InventarioJugadorEloquent.php:104-149`) → `incrementar` (idempotente con `insertOrIgnore` + `increment`, `:152-176`) → `saldos` para la respuesta.
- **Sí soporta encadenar piezas**: ya hay recetas cuyo ingrediente es un resultado de forja (`receta:super` consume `pocion:pocion`, `CatalogoRecetas.php:123-128`). Consumir «la pieza anterior» es mecánicamente idéntico.
- **No soporta instancias**: el inventario es `(user_id, item_key) → cantidad` (`database/migrations/2026_08_29_000007_create_player_inventory_table.php:15-23`). No hay id de instancia, nivel por pieza, ni atributos por copia. La «evolución de la pieza» solo puede modelarse como **clave distinta por etapa** (p. ej. `arma:gastly` → `arma:haunter`), no como nivel de una misma pieza.
- **No puede devolver un objeto no apilable**: `resultadoCantidad` está fijado a 1 (`RecetaFabricacion.php:30-32`), y el consumo masivo es por cantidad, sin distinción de stack.
- El `userId` va explícito y el adaptador usa `withoutUserScope()` (`InventarioJugadorEloquent.php:18-24, 39-44`), sin depender de `Auth`.

### 5) Categorías/claves de ítems, iconos y encaje de Arma/Armadura/Amuleto

- `src/Shared/Domain/Items/CategoriaItem.php:11-19` — 7 valores: `baya`, `bonguri`, `pocion`, `material`, `mejora`, `reductor`, `resistencia`. `prefijo()` = `"{value}:"` (24-27). **No hay arma/armadura/amuleto.**
- `CatalogoItems.php:208-307` — `construir()` recorre los enums y crea 82 `DefinicionItem` (36 bayas + 7 bonguris + 8 pociones + 1 material + 7 mejoras + 5 reductores + 18 resistencias).
- `DefinicionItem.php:12-21` — campos: `clave`, `nombre`, `categoria`, `equipable`, `usableEnCombate`, `consumible`, `icono`, `descripcion`. `equipable` ya existe y está en `true` **solo para las 6 bayas base** (`CatalogoItems.php:213-224`). Arma/Armadura/Amuleto encajarían como `equipable: true, usableEnCombate: false, consumible: false`.
- `CatalogoItems::aPartirDeItemKey()` (`:136-195`) resuelve por **cadena de `if` por prefijo**; un prefijo nuevo sin rama devuelve `null` → `ItemCatalogo::resolve()` cae a «Desconocido» (`app/Support/ItemCatalogo.php:107-116, 184-191`).
- Iconos: patrón `archivoIcono()` por enum (`ClaveBonguri.php:63-66`, `ClaveMejora.php:63-66`) → `public/images/items/{categoria}/{slug}.webp`. Estado real de assets: `public/images/items/material/` 1, `.../mejora/` 7, `.../pocion/` 8; **no hay carpeta `public/images/items/{arma,armadura,amuleto}`**. Bonguris en `public/images/bonguri/*.png` (7). Iconos de pokémon en `public/images/iconos_webp/` (1201 archivos, contrato `/images/iconos_webp/{id}.webp`).
- `CatalogoObjetos` (`src/Battle/Domain/Catalog/CatalogoObjetos.php:17-29`) es un catálogo **separado** de 8 claves de combate (`life_orb`, `leftovers`, …) con `cargas` (-1 pasivo), espejo de `ClaveItem` (`src/Battle/Domain/Enums/ClaveItem.php:7-17`) y registro de efectos en `app/Providers/BattleEffectServiceProvider.php:118-124`. Es el segundo «catálogo» a integrar: `EquiposVistaPresenter.php:36-38` y `TeamController.php:225` validan contra él.

### 6) `habitats.bonguri` ↔ `ClaveBonguri` ↔ recetas

- `database/migrations/2026_11_04_000002_add_bonguri_to_habitats.php:20-28, 33-40, 57-79` — columna `bonguri` string(16) nullable **en `habitats`**, backfill **por provincia** con `whereNull('bonguri')`; 7 provincias con color, `Vacio` queda `NULL`.
- `database/seeders/BonguriHabitatSeeder.php:26-57` — misma tabla `BONGURI_POR_PROVINCIA`, idempotente. Consecuencia: **todos los hábitats de una provincia comparten el mismo bonguri**; «cada hábitat tiene un bonguri» se cumple por columna, no por valor único.
- `src/Shared/Domain/Items/ClaveBonguri.php:20-58` — `clave()` = `bonguri:{color}`; `nombre()`; `origen()` = provincia. **Inconsistencia detectada:** `origen()` devuelve `'Andalucía'` (con tilde, línea 56) mientras `ProvinceSeeder` (`database/seeders/ProvinceSeeder.php:33`) y el backfill usan `'Andalucia'` (sin tilde). Es solo texto de UI (`origen`), no afecta al backfill, pero rompería cualquier lookup por nombre de provincia.
- `ClaveBonguri::porDefecto()` = `BLANCO` (`:69-75`); usado en `src/Granjas/App/ObtenerGranja.php:96-98` con `tryFrom(...) ?? porDefecto()` — **ese es el patrón canónico de lectura del bonguri de una zona**.
- Recetas actuales: `CatalogoRecetas.php:167-174` genera `receta:recipiente:{color}` consumiendo 1 bonguri → `material:recipiente`. El bonguri ya es ingrediente de forja; añadirlo a las piezas es reutilizar el mismo patrón.
- Lectura de nidos disponible: `app/Models/Nido.php:15-29` (`farm_id`, `evolution_chain_id`, `level` 0-5); `app/Models/Granja.php:43` (`nidos(): HasMany`); `src/Granjas/App/AsegurarGranja.php:16-35` (crea granja por `user_id+habitat_id`); `src/Granjas/App/ObtenerGranja.php:62` (`nidos()->get()->keyBy(evolution_chain_id)`); `ObtenerGranja.php:80-94` recorre las familias del hábitat con `ObtenerFamiliasDisponibles` y asocia nido por `evolutionChainId`. **No existe hoy ninguna consulta que agregue nidos de todos los hábitats de un jugador** (todas son por `habitat_id`), que es justo lo que la nueva fila necesita.
- Límites/costes de nido: `MejorarNido.php:44-59` con `CalculadoraBonosGranja::MAX_NIDO` y `costeNido($nivel+1)` (no leído en detalle; el otro analista cubre Granjas).

### 7) Tests y archivos que referencian Forja/ítems (sin modificarlos)

- **No hay ningún test de la Forja**: `grep -rn "Forja" tests/` solo devuelve un comentario ajeno en `tests/Feature/Gimnasios/GimnasioLivewireTest.php:92`.
- **Ningún test referencia `CatalogoItems`, `CatalogoRecetas`, `CatalogoObjetos` ni `objetosDeCombate`** (grep vacío), luego ampliar categorías/recetas **no rompe asserts de catálogo**.
- Tests que sí tocan inventario y podrían verse afectados si cambia el puerto o las claves: `tests/Unit/PlayerInventoryTest.php`, `tests/Feature/InventarioTest.php`, `tests/Feature/UserScopeTest.php`, `tests/Feature/PersistirRecompensasTest.php`, `tests/Feature/TopeEvTest.php`.
- `tests/Unit/ItemCatalogoTest.php` prueba `App\Support\ItemCatalogo` (caramelos `familia:`/`ev:`/`tipo:`), no el catálogo de forja.
- No hay test de la vista `/forja` ni del componente Alpine.

### 8) Riesgos, duplicidades y conflictos con cambios locales

- `git status --short`: 40 archivos modificados + ~12 untracked (TASK-049…055). **Ninguno de Forja ni de `src/Shared/Domain/Items`**; `docs/conventions.md` está modificado (sección Frontend/tokens) y `resources/views/equipos/*`, `resources/css/*`, `src/Battle/Domain/CombatienteVista.php`, `src/Shared/Tipos/TipoPokemon.php` también. La implementación de TASK-056 tocará previsiblemente `resources/views/equipos/*` y `src/Battle` → **conflicto probable con trabajo local en curso**. `docs/context.md` no figura modificado.
- `phpstan-baseline`/`phpstan-hardener-baseline` (ver `docs/context.md` pendiente 17) congelan deuda; añadir archivos nuevos no debería sumar entradas, pero ampliar catálogos grandes puede disparar complejidad.
- Duplicidad ya vigente: tres representaciones de «objeto equipable» (`CatalogoItems::equipable`, `CatalogoObjetos::OBJETOS`+`ClaveItem`, y `reclutados.obj_equipados` JSON sin validación de dominio). Añadir armas sin unificar agravaría el problema.

## DEPENDENCIAS

- `InventarioJugador` ← `InventarioJugadorEloquent` ← `CatalogoItems::existe()` como **whitelist implícita** (`InventarioJugadorEloquent.php:181-188`). También admite `familia:*` y `ev:*` numéricos (`:33`, `:190-201`) — `tipo:*` **no** está en ese carve-out (deuda ya documentada en `docs/architecture.md`).
- `Forja.App` → `Shared.Domain.Items` + `Shared.Domain.Ports` (sin dependencia de Battle ni de Granjas; `docs/architecture.md:649`).
- `Forja` **no** conoce `Granjas`: para gatear por nido habrá que introducir un puerto/DTO (no una dependencia directa) o resolverlo en `App`.
- `habitats.bonguri` → `ClaveBonguri` (dominio) ← `ObtenerGranja` (patrón de lectura) y `Granjas` DTOs (`BonguriGranja::desde`, `src/Granjas/Domain/DataTransferObjects/BonguriGranja.php:22-27`).
- UI: `x-ui.alert`, `x-ui.button`, `x-ui.card` (componentes existentes) y tokens Tailwind del proyecto.
- `reclutados.obj_equipados` (JSON, cast `array`, `app/Models/Reclutado.php:23,29`) y su lectura **solo del índice 0**: `src/CombateEntrenadores/App/ConstruirEquipoJugador.php:86-88`, `src/Exploraciones/App/CombateExploracion.php:53`, `src/Equipos/App/ActualizarObjetosEquipo.php:39-45`, `resources/views/equipos/index.blade.php:1756-1761`, `app/Support/ReclutadoSerializer.php:61`.

## DUPLICIDADES Y DEUDA TÉCNICA

1. **Doble catálogo de objetos**: `Shared\Domain\Items\CatalogoItems` (inventario, 82 ítems) vs `Battle\Domain\Catalog\CatalogoObjetos` (8 objetos de combate con `cargas`), con `ClaveItem` como tercer espejo. Un objeto de forja (`mejora:ataque`) y uno de combate (`choice_band`) siguen canales distintos.
2. **`obj_equipados` sobredimensionado**: es una lista, pero todo el código lee `[0]`; la escritura fuerza `[$itemKey]` (`ActualizarObjetosEquipo.php:35`). El «hasta 3» ya está latente sin soporte.
3. **Un solo objeto por combatiente** en el motor: `Combatiente::setItem(string $item)` (`src/Battle/Domain/Combatiente.php:649-651`) y `ObjetoCombatiente` con `cargas` única.
4. **Lógica de categoría en la vista**: `forjaCategoriaDeResultado` duplica en JS el conocimiento de prefijos de `CategoriaItem`; `FORJA_CATEGORIAS` duplica el orden/nombres de categorías que el backend no expone. Viola el espíritu de `frontend-minimal-logic` (`.ai/rules/frontend-minimal-logic.md`).
5. **`motivo` limitado a un literal** en backend y en JS.
6. `RecetaFabricacion` exige `resultadoCantidad === 1` «en esta fase» (`:30-32`) — deuda declarada.
7. `ClaveBonguri::origen()` con `'Andalucía'` vs seeder `'Andalucia'` (desalineación de datos de presentación).
8. Assets de ítems sin carpeta para armas/armaduras/amuletos; `CatalogoObjetos::iconoDe()` devuelve `''` (sin iconos para los 8 objetos de combate).

## RIESGOS

- **[alto] Whitelist implícita de claves.** Cualquier clave nueva (`arma:gengar`, `armadura:gengar`, `amuleto:gengar`) debe existir en `CatalogoItems` o `InventarioJugadorEloquent` lanzará `ItemDesconocido` (422) en lectura y en `incrementar`. Impacto: la fabricación falla en el último paso de la transacción. Mitigación: registrar el catálogo antes de exponer recetas y añadir validación temprana.
- **[alto] Cardinalidad del inventario.** `player_inventory` es contador por clave: no hay instancias. Impacto: «una pieza que evoluciona» no puede llevar nivel ni dueño; dos copias de la misma pieza son indistinguibles; no se puede transferir «la» pieza concreta entre reclutados manteniendo estado. Mitigación: modelar la etapa en la clave y aceptar el conteo, o introducir tabla de instancias (decisión de arquitectura).
- **[alto] La nueva receta no se vería sin cambios de UI.** `FORJA_CATEGORIAS` + `forjaCategoriaDeResultado` descartan grupos no mapeados (`forja/index.blade.php:420-426`). Impacto: backend correcto, petición vacía visualmente. Mitigación: categoría nueva en ambos sitios o `categoria` enviada por el backend y consumida tal cual.
- **[alto] Volumen de recetas.** N piezas × 3 tipos × familias con nido; si se generan todas por `cases()`, `ListarRecetas` hará `saldos` sobre cientos de claves y la UI pintará cientos de tarjetas. Impacto: payload y render. Mitigación: subset por nidos del jugador (no catálogo global) y `origen`/gate calculados en backend.
- **[medio] Gate por nivel de nido sin puerto.** `Forja` no depende de `Granjas`; implementarlo con Eloquent directo en `App` rompería la separación. Impacto: deuda arquitectónica + tests diferidos. Mitigación: puerto en `Shared`/`Forja\Domain` implementado en `Granjas\Infra` (patrón `ConsultorPlantaciones`).
- **[medio] `motivo` nuevo rompe la UI a texto crudo.** Impacto: UX confusa (`textoMotivo` cae al literal). Mitigación: ampliar el mapa JS o enviar el texto ya resuelto desde backend.
- **[medio] Conflictos con cambios locales.** `resources/views/equipos/*`, `resources/css/*` y `src/Battle/Domain/CombatienteVista.php` están modificados sin commitear. Cualquier edición ahí choca. Mitigación: delimitar archivos en el plan y tocar `equipos`/`Battle` solo en secuencia y con aprobación.
- **[medio] Dos fuentes de «equipable».** `DefinicionItem::equipable` vs `CatalogoObjetos`/`ClaveItem`. Impacto: `PATCH /teams/{team}/items` valida contra `CatalogoObjetos` (`TeamController.php:225`), así que un arma fabricada **hoy sería rechazada** al equipar. Mitigación: unificar contrato de equipamiento.
- **[bajo] `origen` solo bonguri.** Si el bonguri pasa a ser ingrediente de armas, el `origen` seguirá funcionando (mismo prefijo), sin cambios.
- **[bajo] `FabricarRequest` no valida formato de clave.** Sin impacto de seguridad (la receta debe existir), pero da 404 en vez de 422 para claves basura.

## OPCIONES

1. **Sección estática nueva en la Forja (mínima)**: añadir `CategoriaItem::ARMA/ARMADURA/AMULETO`, claves en `CatalogoItems`, bloque en `CatalogoRecetas::construir()` con claves generadas desde un enumerado de piezas, y dos entradas en `FORJA_CATEGORIAS`/`forjaCategoriaDeResultado`. Menor diff, sigue sirviendo el catálogo global; no resuelve el gate por nido ni el volumen.
2. **Sección dinámica por nidos (backend-first)**: `ListarRecetas` pasa a aceptar un contexto de piezas desbloqueadas por los nidos del jugador (puerto de granjas) y la UI consume `categoria`/`motivo`/`etiqueta` ya resueltos, retirando `FORJA_CATEGORIAS` como fuente de verdad. Más fiel a la petición; más trabajo en `src/Forja` + puerto.
3. **Modelo de instancias de pieza** (tabla propia, pieza con nivel y dueño) frente al modelo de claves por etapa. Resuelve transferencia/nivel real, pero rompe el patrón actual de inventario y exige decidir equipamiento y combate.

## SUPOSICIONES

- «Biomas» = `habitats` actuales; «nido construido» = `nests.level >= 1`.
- La pieza evoluciona como claves distintas por etapa (no como nivel de una instancia), dado el modelo de inventario.
- El bonguri de la zona actúa como ingrediente de la receta, igual que en `receta:recipiente:*`.
- Las piezas se guardan en `player_inventory` como conteo y se equipan vía `obj_equipados` (que hoy solo usa el índice 0).
- El diseño de Granjas/combate/equipamiento lo cubre el otro analista; aquí solo se fija la frontera.

## PREGUNTAS BLOQUEANTES (para el arquitecto)

1. **Identidad de la pieza**: ¿clave por etapa (`arma:gastly`/`arma:haunter`/`arma:gengar`) o instancia persistida? Determina si `player_inventory` sirve o hace falta tabla nueva.
2. **Alcance del listado**: ¿la Forja sirve solo las piezas de los nidos del jugador (subset dinámico) o el catálogo completo con gateo? Define si `GET /api/forja/recetas` cambia de contrato (¿añade `categoria`, `familia`, `nivel_nido`, `motivo` nuevos?).
3. **Fila por nido/bioma o por familia**: si un jugador tiene la misma familia en 2 hábitats con bonguris distintos, ¿una fila por familia, por nido o por (familia, bonguri)?
4. **Delimitación del equipamiento**: ¿las armas/armaduras/amuletos son un catálogo separado de `CatalogoObjetos`/`ClaveItem`, o se unifican? ¿`PATCH /teams/{team}/items` pasa a aceptar 3 claves (cambio de contrato `items` = string → lista)?
5. **Convivencia con los 8 objetos de combate**: ¿los 3 slots admiten también `life_orb`/`leftovers`, o son exclusivos de piezas forjadas?
6. **Cómo se expone el set de stats**: ¿la pieza replica los stats base de la especie (constantes derivadas en seed/código) o se calculan con `BattleStats`? ¿El bonguri modula el valor (bono) o solo es coste?
7. **Coste y gate**: ¿el nivel de nido es solo gate o multiplica coste/valor? ¿El bonguri es ingrediente único por pieza o hay cantidades por tipo?
8. **Habilidades pasivas**: ¿cómo se elige la habilidad si la especie tiene varias (`ability_pokemon.slot`), cómo se acumula y cómo entra en `FabricaEfectos` sin duplicar `ClaveEfecto`?
9. **Qué ocurre al evolucionar el Pokémon reclutado** que lleva una pieza de la etapa anterior (¿se queda, se bloquea, se convierte?).
10. **Superficie de assets**: ¿se acepta fallback/placeholder mientras no existan `public/images/items/{arma,armadura,amuleto}/*.webp`, o se exige generarlos antes de implementar?

## VERIFICACIÓN EJECUTADA

- `git status --short`, `git branch --show-current`, `git diff --stat`, `git log --oneline -12`, `git log -- database/migrations/2026_11_04_000002_add_bonguri_to_habitats.php`.
- Lectura con `cat -n`/`sed` de: `src/Forja/**` (13 archivos), `src/Shared/Domain/Items/**` (11), `src/Shared/Domain/Ports/InventarioJugador.php`, `src/Shared/Domain/ValueObjects/MapaConteo.php`, `src/Shared/Collections/StringCollection.php`, `src/Shared/Infra/Inventario/InventarioJugadorEloquent.php`, `routes/forja.php`, `routes/web.php`, `resources/views/forja/index.blade.php`, `app/Support/ItemCatalogo.php`, `app/Support/EquiposVistaPresenter.php`, `src/Battle/Domain/Catalog/CatalogoObjetos.php`, `src/Battle/Domain/Enums/ClaveItem.php`, `src/Equipos/App/ActualizarObjetosEquipo.php`, `src/Equipos/Domain/ValueObjects/ObjetosEquipo.php`, `src/CombateEntrenadores/App/ConstruirEquipoJugador.php` (extracto), `app/Http/Controllers/TeamController.php` (extracto), migraciones/seeder de `bonguri`, `2026_10_26_000001_create_farm_tables.php`, `2026_08_29_000007_create_player_inventory_table.php`, `app/Models/{Pokemon,Habitat,Nido,Granja,Reclutado}.php`, `src/Granjas/App/{ObtenerGranja,MejorarNido,AsegurarGranja}.php`, `src/Granjas/Domain/DataTransferObjects/{FamiliaGranja,NidoGranja}.php`, `database/seeders/{ProvinceSeeder,BonguriHabitatSeeder}.php`, `docs/architecture.md` (extractos), `.ai/rules/{index,frontend-minimal-logic,arrays-dto-collections}.md`, `.opencode/work/TASK-056/{request,execution}.md`.
- Búsquedas `grep` de: `InventarioJugador`, `bonguri`, `obj_equipados`, `loadout`, `CatalogoObjetos`, `CatalogoItems|CatalogoRecetas` en `tests/`, `Forja` en `tests/`, `forja` en `resources/views`.
- `php artisan route:list --path=forja` (solo lectura; 3 rutas confirmadas).
- **No** se ejecutaron migraciones, seeders, tests ni escrituras. No se leyó `.env`.

## PROBLEMAS DETECTADOS (Parte A)

1. `CatalogoItems` es la whitelist real del inventario: sin registrar la clave nueva, todo falla con 422.
2. La UI descarta recetas sin categoría (`forja/index.blade.php:420-426`).
3. `reclutados.obj_equipados` es lista, pero solo se usa `[0]`; equipar 3 no está soportado.
4. `TeamController` valida contra `CatalogoObjetos` (8 claves), así que un arma forjada sería rechazada al equipar.
5. `player_inventory` no tiene instancias → la «pieza que evoluciona» solo puede ser clave por etapa.
6. No hay carpeta de assets para las 3 categorías nuevas.
7. `ClaveBonguri::origen()` con tilde vs seeder sin tilde.
8. No existe consulta de nidos agregados por jugador (todas las lecturas son por hábitat).

## RECOMENDACIÓN (Parte A)

Diseñar la nueva sección como **catálogo dinámico por nidos** (opción 2) con estas fronteras: (a) `CategoriaItem` + claves de pieza registradas en `CatalogoItems` con `equipable: true`; (b) generación de recetas por un enumerado de piezas (no listas literales) en `CatalogoRecetas`; (c) `ListarRecetas` recibe del backend las piezas desbloqueadas por los nidos del jugador vía un puerto de granjas, y devuelve ya resueltos `categoria`, `motivo` y el orden; (d) la UI deja de ser fuente de verdad de categorías (`FORJA_CATEGORIAS`/`forjaCategoriaDeResultado` se sustituyen por lo que envía la API, o se amplían ambos mientras dure la transición); (e) antes de implementar, cerrar las decisiones de identidad de pieza, slots de equipamiento y unificación con `CatalogoObjetos`. Cualquier edición en `resources/views/equipos/*` o `src/Battle` debe ir en secuencia y fuera de los cambios locales pendientes.

## TAREAS PENDIENTES (Parte A)

- No se consultó la BD real (PostgreSQL) para conteos de nidos/hábitats/bonguris efectivos.
- No se inspeccionó a fondo `src/Granjas/Domain/CalculadoraBonosGranja.php` ni `ResolverBonosFamilia`.
- No se verificó la existencia de capacidades/habilidades por especie con datos reales.

---

# Parte B — Granjas/nidos, Pokémon, equipamiento y combate

## 1. Lectura de nidos del jugador (nivel, familia, hábitat, bonguri) y filtro nivel ≥ 1

- `src/Granjas/App/AsegurarGranja.php:16-35` — `para(userId, habitatId)`: valida hábitat (`RecursoNoExiste`) y hace *get-or-create* de `farms` por `(user_id, habitat_id)`.
- `src/Granjas/App/ObtenerGranja.php:57-72` — carga la granja, `nidos()->get()->keyBy(evolution_chain_id)` y `refugios()->get()`; las plantaciones se agrupan por `nest_id`.
- `src/Granjas/App/ObtenerGranja.php:62,85,156` — el nivel sale de `$nido->level` (0 si no hay fila). **No existe hoy ninguna consulta "nidos con nivel ≥ 1"**: `ObtenerGranja` produce todas las familias del hábitat con `NidoGranja(nivel: 0)` cuando no hay nido.
- `src/Granjas/App/ObtenerGranja.php:96-98,108` — bonguri: `Habitat::bonguri` → `ClaveBonguri::tryFrom` → fallback `ClaveBonguri::porDefecto()` (blanco); expuesto como `BonguriGranja` (`clave`/`nombre`/`icono`).
- Esquema: `database/migrations/2026_10_26_000001_create_farm_tables.php:16-34` — `farms(user_id, habitat_id)` unique; `nests(farm_id, evolution_chain_id, level default 0)` unique `(farm_id, evolution_chain_id)` + índice por `evolution_chain_id`.
- `database/migrations/2026_11_04_000002_add_bonguri_to_habitats.php:33-40,57-79` — columna `habitats.bonguri` (string 16, nullable) con backfill por provincia; "Vacío" queda NULL.
- `app/Models/Nido.php:74-84` — fillable `farm_id`, `evolution_chain_id`, `level` + casts int.
- `src/Granjas/Domain/CalculadoraBonosGranja.php:21,91-113` — `MAX_NIDO=5`, costes 10/20/30/40/50.

**Para la Forja**: la consulta "nidos construidos" (`level >= 1`) del jugador, con su hábitat y bonguri, no existe; habría que añadirla (join `nests → farms → habitats`, filtrando `user_id` y `level >= 1`), o derivarla del `GranjaDetalle` (que ya expone `nivel`, `bonguri` y `familia.evolution_chain_id`).

## 2. Cadena evolutiva, etapa y casos 3/2/sin evolución y ramificadas

- Agrupación por columna `pokemon.evolution_chain_id` (sin tabla): `app/Models/Pokemon.php:20-30`; `PokemonEvolution` sólo modela aristas `(evolved_species_id, evolves_from_species_id, minimum_level)` (`app/Models/PokemonEvolution.php:9-24`).
- `src/Habitats/Domain/ResolvedorCadenasEvolutivas.php:55-104` — construye `PokemonFamilia(id, nombre, speciesId)` y `AristaEvolutiva` desde las filas de `pokemon_evolution`, delega en `ResolvedorFamiliaEvolutiva` con **`multiRaiz=false`, `maxEtapas=3`** (modo compatibilidad histórico) y devuelve `MiembroCadena(stage, parentId, speciesId)`. Miembros no alcanzados → `stage` fallback **3** (`:97`).
- `src/Pokemon/Domain/Evolution/ResolvedorFamiliaEvolutiva.php:23-57` — BFS por componentes conexas; `multiRaiz=true` soporta ramificaciones (Eevee) con `branchIndex` (`:130-156,331-343`); `maxEtapas` recorta profundidad; orden final por `(stage, parentId, branchIndex, speciesId, id)` (`:354-381`).
- `ResolvedorCadenasEvolutivas::totalStages()` (`:106-109`) y `levelForStage()` (`:111-118`) son el criterio existente para mapear etapa→nivel (¡ojo: devuelve `min(stage,3)` y `2` cuando hay 1 sola etapa!).
- Criterio de negocio "primer integrante = menor `species_id`": `src/Habitats/Presentation/DTOFamiliaDisponible.php:32-38` (`minSpeciesId`) y `ResolvedorCadenasEvolutivas::splitFamilyMembers()` (`:127-142`).
- Familias disponibles por hábitat: `src/Habitats/App/ObtenerFamiliasDisponibles.php:16-19` → `HabitatLecturaRepositoryInterface::getFamiliesByHabitat`.

**Observación clave para el diseño**: el `levelForStage()` existente **no coincide** con la regla pedida (1/3/5 · 2/4 · 3). Es una regla nueva de Forja, no reutilizable tal cual; y hay que decidir si se usa `stage` de `ResolvedorFamiliaEvolutiva` (soporta ramas) o la heurística de `ResolvedorCadenasEvolutivas` (una raíz, máx 3 etapas, fallback 3 — peligroso para Eevee y para familias con 1 miembro).

## 3. Stats base y habilidades por especie

- Stats base: tabla `pokemon_stats` → `app/Models/PokemonStat.php:10-24` (`stat` cast `StatEnum`, `base_stat`, `effort`); relación `Pokemon::stats()` en `app/Models/Pokemon.php:41-47`. No se persiste un total agregado: se agrega en lectura en `src/CombateEntrenadores/App/MapeadorPokemonBatalla.php:336-360` (`statsDe()` → `DatosStats`).
- Habilidades: `abilities` (col. `data` JSON con `effect_keys` y `effect` texto legible) `app/Models/Ability.php:15-40`; pivote `ability_pokemon(pokemon_id, ability_id, slot, source)` unique `(pokemon_id, slot)` en `database/migrations/2026_10_22_000004_create_ability_pokemon_table.php:68-79`. `Pokemon::abilities()` en `app/Models/Pokemon.php:71-77`.
- Resolución de claves de efecto: `MapeadorPokemonBatalla::efectosDe()` (`:307-334`) → `ability.data['effect_keys']` o `CatalogoHabilidades::clavesDe(name)`.
- Cálculo de stats de combate: `src/Pokemon/Domain/Stats/BattleStats.php:32-81` — fórmula `floor(((2*base + iv*5 + floor(ev/4)) * nivel/100) + nivel + 10)` para HP y `+5` para el resto; **`defenseHp` y `spDefenseHp` son la fórmula de HP aplicada a Def y SpD** (`:64-65`). `StatsValue` es un VO mutable con props nullable (`src/Pokemon/Domain/Stats/StatsValue.php:7-107`, `conStat()` inmutable, claves cortas `hp/atk/def/spAtk/spDef/speed`).
- IV/EV del reclutado: columnas JSON `ivs`/`evs` (`database/migrations/2026_09_15_000001_add_ivs_evs_to_reclutados_table.php:42-48`) con cast `App\Models\Casts\StatsReclutado`; se inyectan en `BattleStats` vía `DatosPokemonBatalla` → `EquipoBatalla::fromData()`.

**Implicación**: un "set que replica los stats de la especie" puede construirse sin tocar `BattleStats` si se modela como modificación de los **valores base** (`StatsValue`) antes de construir `BattleStats`, o bien como multiplicadores en `StatsCombatiente` (`:+5 %`/`+10 %`). Son dos estrategias distintas con impacto diferente (afecta/ no afecta a barreras — ver punto 6).

## 4. Esquema de `reclutados` y equipamiento actual (1 objeto)

- `database/migrations/2026_05_27_000001_create_reclutados_table.php:15-26` — ya existe **`obj_equipados` (json, nullable)** y `movimientos` (json).
- `app/Models/Reclutado.php:22-36` — fillable incluye `obj_equipados`, `movimientos`, `ivs`, `evs`, `habilidad_activa_id`; cast `obj_equipados => 'array'`.
- Estado actual del equipamiento: **lista de UNA sola clave**:
  - `src/Equipos/App/ActualizarObjetosEquipo.php:14,31-40` — `obj_equipados => [$itemKey]` o `[]`; lee `$objEquipados[0]`.
  - `app/Http/Controllers/TeamController.php:216-241` — `PATCH /teams/{team}/items`, valida `Rule::in([...CatalogoObjetos::claves(), ''])`.
  - Lectura en batalla: `src/CombateEntrenadores/App/ConstruirEquipoJugador.php:86-95` y `src/Exploraciones/App/CombateExploracion.php:53-55` → `array_values($items)[0] ?? null`.
  - Contrato de API: `app/Support/ReclutadoSerializer.php:61` → `'obj_equipados' => $reclutado->obj_equipados ?? []`.
- **No existe** tabla de objetos equipados ni catálogo persistido: el catálogo vive en código (`src/Battle/Domain/Catalog/CatalogoObjetos.php:20-29`: `life_orb`, `leftovers`, `focus_sash`, `sitrus_berry`, `lum_berry`, `choice_band`, `choice_specs`, `assault_vest`).
- Persistir 3 slots por reclutado:
  - Opción A: reutilizar `obj_equipados` como lista de hasta 3 claves (cambio de contrato: hoy es 1; hay tests y vistas que asumen `[clave]` en índice 0 — `resources/views/equipos/index.blade.php:1751-1760`, `_pokemon-card`, `items-panel`).
  - Opción B: nueva tabla `reclutado_equipamiento(reclutado_id, slot 1-3, item_key, nivel, evolution_chain_id/familia)` con unique `(reclutado_id, slot)`, que además permite piezas con estado propio (nivel de la pieza, familia de origen). El `obj_equipados` actual quedaría como legado/compatibilidad.
  - Nota de integridad: no hay FK posible al catálogo (es código), igual que ocurre con `complements.stat` o `nests.evolution_chain_id` (sin FK).

## 5. Construcción de `Combatiente`/`EquipoBatalla` por modo y punto de inyección de objeto/habilidad

- Punto único de mapeo: `src/CombateEntrenadores/App/MapeadorPokemonBatalla.php:50-105` — produce `DatosPokemonBatalla` con `stats` base, `tipos`, `moves`, **`effectKeys`**, **`item`**, `nivel`, `evs`, `ivs`, `postura`, `senda`, `peso`.
- DTO de entrada: `src/Battle/Domain/DatosPokemonBatalla.php:26-110` — **un solo `?string $item`**, `array $effectKeys`, `StatsValue $evs/$ivs`, `nivel`, `bonusInicialStats`, `esJefe`.
- Construcción del equipo: `src/Battle/Domain/EquipoBatalla.php:157-236` — crea `BattleStats` (`:163-177`), `PokemonEntity`, `Combatiente`, y luego:
  - habilidades → `fabricaEfectos->crearEfectos($clave)` (`:199-211`),
  - **objeto** → `setItem($member->item)` + `crearItem($member->item)` (`:213-220`).
- Orquestación: `app/Support/CreadorBatallaSesion.php:33-53` (ruta, entrenador, gimnasio, mazmorra) y `src/Exploraciones/App/CombateExploracion.php:99-100` (1v1).
- Constructores por modo:
  - Entrenador/Ruta/Gimnasio/Mazmorra → `ConstruirEquipoJugador::desdeEquipo()` (`src/CombateEntrenadores/App/ConstruirEquipoJugador.php:52-113`, usado en `src/CombateRuta/App/IniciarCombateRuta.php:53`, `src/Gimnasios/App/IniciarCombateGimnasio.php:74`, `src/Mazmorras/App/IniciarCombateMazmorra.php:71`, `src/CombateEntrenadores/App/IniciarCombateEntrenador.php:53`).
  - Exploraciones → mapeo directo (`CombateExploracion.php:60-82`).
  - Mock/legado → `src/Battle/Infrastructure/FabricaBatallaMock.php:186-197`.
- Aplicación de efectos:
  - `src/Battle/Domain/Effects/InterfazEfecto.php:170-209` — hooks `onBattleStart/onRoundStart/onRoundEnd/onTurnStart/onDamageDealt/...`; **una habilidad puede componer varios efectos** (ver `FabricaEfectos::crearEfectos` `:100-114`).
  - Registro: `app/Providers/BattleEffectServiceProvider.php:100-131` (habilidades y 6 items: restos, orbe vida, banda focus, baya sitrus, baya lum, chaleco asalto) + `registrarHabilidades()` (`:139+`).
  - `src/Battle/Domain/Effects/FabricaEfectos.php:90-143` — `itemsRegistrados` es **mapa clave→UN efecto** (`crearItem` devuelve un solo `InterfazEfecto`), frente a `efectosRegistrados` que sí es `list<>`.
  - Cadena de daño: `src/Battle/Domain/Chain/ManejadorObjetosEquipados.php:16-47` — mapa `['life_orb' => 1.30]` + casos *hardcodeados* de `choice_band`/`choice_specs`; se **salta si `itemActivo()` es false**.
  - `EfectoRestos` (`:223-247`) cura 1/16 HP y `EfectoOrbeVida` (`:264-288`) aplica 10 % recoil: ambos leen `$portador->pokemon()->battleStats()->hp`.
- Estado del objeto en combate: `src/Battle/Domain/Componentes/Combatiente/ObjetoCombatiente.php` + `DelegacionObjetoCombatiente.php` — un `EstadoObjeto` con `clave`, `cargas`, `consumido`, bloqueo y **robo/intercambio/destrucción** (`robarDe`, `intercambiarCon`, `reciclar`) y serialización `item/itemCargas/itemConsumido/...`.

**Conclusión operativa**: hoy el motor soporta **exactamente 1 objeto por combatiente** y asume que ese objeto puede robarse/intercambiarse/destruirse. Soportar 3 slots requiere (a) cambiar el DTO (`?string $item` → colección de items), (b) `ObjetoCombatiente` multi-slot o 3 componentes, (c) `FabricaEfectos::crearItem`→`crearItems` (varios efectos por item), (d) `ManejadorObjetosEquipados` sumando multiplicadores, y (e) decidir el impacto en robo/destrucción/reciclaje (una mecánica que hoy es de un único objeto). `SESSION_VERSION` en `app/Support/BattleSessionService.php` habría que subirla (hoy 11 según docs).

## 6. Barreras y qué stats las alimentan

- `src/Pokemon/Domain/Stats/BattleStats.php:63-70` — `hp` desde base HP; **`defenseHp` desde base Defensa**, **`spDefenseHp` desde base Def. Especial** (misma fórmula de HP, con IV/EV del stat correspondiente).
- `src/Battle/Domain/Componentes/Combatiente/VitalidadCombatiente.php:101-107` — inicializa `hpActual`, `defensaHpActual`, `defensaEspHpActual` desde `battleStats()`.
- `src/Battle/Domain/BarrerasVidaManager.php:30-49` — `desglosarDaño()` divide daño directo/barrera/excedente; `curarBarreras()` (`:59-76`).
- La Def/SpD también reducen daño en la fórmula base: `src/Battle/Domain/Chain/ManejadorDanioBase.php:55-83` (`obtenerStatEfectivo(DEFENSA/DEFENSA_ESPECIAL)`), con `StatsCombatiente::obtenerStatEfectivo()` (`:124-147`).
- Por tanto, **una Armadura que suba Def/SpD/HP afecta doblemente**: fortalece la barrera (máximo) y reduce el daño recibido. Es el punto de mayor sensibilidad de balance del diseño.

## 7. Habilidad activa y habilidad pasiva extra de objeto

- `reclutados.habilidad_activa_id` FK nullable a `abilities` (`database/migrations/2026_10_22_000005_add_loadout_to_reclutados_table.php:12-18`), relación `Reclutado::habilidadActiva()` (`app/Models/Reclutado.php:57-63`), serializada (`ReclutadoSerializer.php:69`) y aplicada en `MapeadorPokemonBatalla::efectosDe()` (`:311-319`): si el id no existe → fallback al conjunto completo de la especie.
- Selección por parte del jugador: `app/Http/Controllers/ReclutadoController.php:354,422`.
- Hoy, sin `habilidad_activa_id`, **se aplican TODAS las habilidades de la especie** (`efectosDe` con `null` → `$pokemon->abilities` completo). Es decir, el modelo ya permite "varias habilidades simultáneas"; el límite es conceptual, no técnico.
- Añadir una habilidad pasiva extra de objeto implicaría:
  - o claves de efecto adicionales en `DatosPokemonBatalla::$effectKeys` (mínimo cambio, encaja con `EquipoBatalla::fromData():199-211`),
  - o registrar un `InterfazEfecto` de item en `FabricaEfectos::registrarItem()` (necesita `crearItems()` plural si la pasiva trae varios efectos),
  - ojo con la anulación/copia/intercambio de habilidad (`HabilidadCombatiente::anular/restaurar/copiarDe/intercambiarCon`, `:280-372`) que separa "efectos de habilidad" de "efectos de objeto" vía `CatalogoObjetos::clavesDeEfecto()` (`:64-70` = `ClaveItem::cases()`). Una pasiva de forja **debe** entrar en `ClaveItem` o quedaría siendo "robable" como habilidad.

## 8. UI donde se mostrarían los 3 slots (con nota de cambios locales)

- `resources/views/equipos/index.blade.php` (**modificado localmente**, 2189 líneas): `itemsDraft` (`:92-94`), `nombreObjeto()` (`:1405-1407`), `itemDe()` (`:1751-1760`, lee `obj_equipados[0]`), `setItemSlot()` (`:1762-1764`), `itemsCompletosDe()` (`:1790-1794`), guardado secuencial formación→posturas→objetos (`:1850-1891`).
- `resources/views/equipos/partials/panel-equipos.blade.php` (**modificado**, 256 líneas): muestra `nombreObjeto(itemDe(team, miembro.slot))` en las tarjetas (`:187`, `:234`).
- `resources/views/equipos/partials/modal-editar-equipo.blade.php` (**nuevo, untracked**): `<select>` único por slot con `<option value="">Sin objeto</option>` y `@foreach($objetos as ...)` (`:78-95`).
- `app/Support/EquiposVistaPresenter.php:24-44` — entrega `objetos` (clave→nombre) desde `CatalogoObjetos::claves()`; `app/Http/Controllers/PlayerController.php:137-149` la inyecta en `equipos.index`.
- `resources/views/livewire/partials/items-panel.blade.php` (**modificado**): panel de objetos *usables en combate* (bayas/pociones/mejoras), no de equipamiento; categorías en `:4-10`.
- `resources/views/livewire/_pokemon-card.blade.php` (**modificado**, +1 línea) — tarjeta de combate.
- Forja (destino de la nueva fila de Pokémon con nidos): `resources/views/forja/index.blade.php` (577 líneas, limpio) — grid de 4 categorías + `FORJA_CATEGORIAS` (`:396-401`) y `forjaCategoriaDeResultado()` (`:403-424`); la nueva sección sería una 5.ª categoría o una sección previa.
- Modal de granja: `resources/views/habitats/show.blade.php` y `partials/columna-*.blade.php` (**modificados**) — ahí vive el listado de nidos/refugios por familia y el bonguri.

**Riesgo de conflicto**: casi toda la UI de equipamiento está en archivos con cambios locales preexistentes, y `modal-editar-equipo.blade.php` es untracked (trabajo en curso de otra tarea). Cualquier cambio de 1→3 slots toca exactamente esos archivos.

## 9. Tests y archivos que referencian estos módulos (sin modificar)

- `obj_equipados` en ~40 tests de `tests/Feature/**` (HabitatsController, Exploraciones, CombateRuta, Mazmorras, Equipos, Reclutado*, Inventario, TopeEv…), siempre con `'obj_equipados' => []`.
- Módulos de referencia: `tests/Feature/CombateEntrenadores/ConstruirEquipoJugadorTest.php`, `CombateRuta/*`, `Mazmorras/*`, `Exploraciones/*`, `EquiposControllerTest.php`, `EloquentTeamRepositoryTest.php`, `tests/Feature/Habitats/FamiliesTest.php`.
- No hay tests (ni deben crearse) para Forja/Granjas-forja: el diseño de Armas/Armaduras/Amuletos entra en "sin tests".

## 10. Riesgos, duplicidades y deuda

| Riesgo | Nivel | Detalle | Mitigación |
|---|---|---|---|
| Contrato `obj_equipados` de 1 elemento asumido en 3 sitios + ~40 tests | alto | `ConstruirEquipoJugador:87`, `CombateExploracion:54`, `equipos/index.blade.php:1757` | Cambio aditivo: mantener `obj_equipados` legado y añadir estructura nueva (tabla o clave nueva), o migración con compatibilidad de lectura `[0]`→`[0..2]` |
| Motor con 1 solo objeto (`ObjetoCombatiente`, `EstadoObjeto`) con robo/intercambio/reciclado | alto | `DelegacionObjetoCombatiente.php:200-213`, `HabilidadCombatiente::esEfectoDeObjeto` | Decidir si las piezas de forja son robeables; si no, separar "equipo de forja" de "objeto de combate" en `DatosPokemonBatalla` |
| `SESSION_VERSION` con payload cambiado | alto | `app/Support/BattleSessionService.php` (v11) | Subir versión y migración best-effort si cambia el shape del combatiente |
| Def/SpD alimentan barrera **y** fórmula de daño | alto | `BattleStats:64-65`, `ManejadorDanioBase:55-83` | Definir si Armadura suma base o multiplicador; medir con CP (`CalculadorCP`) |
| `ResolvedorCadenasEvolutivas` fallback `stage=3` y `maxEtapas=3` | medio | `:83-88,97`; `multiRaiz=false` rompe Eevee | Usar `ResolvedorFamiliaEvolutiva` con `multiRaiz=true` para la Forja, o regla explícita por nº de miembros |
| `levelForStage()` no coincide con 1/3/5 · 2/4 · 3 | medio | `:111-118` | Regla nueva en el dominio de Forja (no reutilizar) |
| `CatalogoRecetas` es estático y por item-clave | medio | `src/Forja/Domain/CatalogoRecetas.php:108-210` | Las piezas son dinámicas (familia × etapa × tipo); necesitan catálogo generativo, no lista estática |
| `FabricaEfectos::crearItem` devuelve 1 efecto | medio | `:131-143` | Añadir `crearItems()` plural |
| `CatalogoItems` no tiene categoría de arma/armadura/amuleto | medio | `CategoriaItem` (7 casos), `CatalogoItems::aPartirDeItemKey` (`:136-195`) | Nueva categoría + prefijo; afecta inventario (`InventarioJugadorEloquent`) |
| UI de equipamiento en archivos con cambios locales / untracked | medio | ver punto 8 | Coordinar secuencia; no tocar `modal-editar-equipo.blade.php` sin aprobación |
| Un nido por (farm, chain) — "1 nido por bioma" vs familias por hábitat | bajo | `nests` unique `(farm_id, evolution_chain_id)` | Ya soporta N nidos por granja (uno por familia); confirmar que "1 nido por bioma" significa 1 por familia |
| `nests` sin FK a la cadena | bajo | `evolution_chain_id` es entero suelto | Igual que el resto del proyecto; validar en dominio |

Duplicidades/deuda relevante: el despacho de items está repartido entre `ManejadorObjetosEquipados` (multiplicadores + hardcode de choice band/specs), `FabricaEfectos::crearItem` (efecto) y `CatalogoObjetos` (cargas/nombres) — tres fuentes para una misma pieza. `CatalogoObjetos::iconoDe()` devuelve `''` (`:41-44`): no hay iconos de objeto.

## 11. Preguntas abiertas para el arquitecto

1. **Persistencia**: ¿tabla nueva `reclutado_equipamiento` (3 filas/reclutado, con nivel de pieza y familia) o extender `obj_equipados` a lista de 3? ¿Se mantiene `obj_equipados` como fuente única para el motor?
2. **Propiedad del objeto**: ¿las piezas son por reclutado (equipadas) o existe inventario de piezas (fabricadas) + equipamiento desde inventario? ¿Se pueden desequipar/reaplicar en cualquier modo?
3. **Stats de la pieza**: ¿se suma a `StatsValue` base (afecta a fórmula y a barreras, como la especie) o como multiplicador en `StatsCombatiente` (solo fórmula)? ¿Redondeo y tope?
4. **Escalado por nivel de nido**: ¿el nivel de nido (1/3/5, 2/4, 3) es sólo requisito de fabricación o también magnitud de stats? Si es magnitud, ¿qué curva (lineal vs. % del stat base)?
5. **Evolución de la pieza**: la pieza "Gastly→Haunter→Gengar consume la anterior y sube de nivel". ¿El nivel de pieza es la etapa (1..3) o un nivel propio 1..5? ¿Qué pasa si el jugador tiene nido de esa familia a nivel 1 y quiere la pieza de etapa 3?
6. **Amuleto**: "Vel + 50 % del mayor entre Atk/SpA/Def/SpD (sin Vel/HP)" — ¿redondeo hacia abajo? ¿se recalcula al cambiar IV/EV/nivel o se fija en fabricación? ¿y si el reclutado que lo equipa tiene stats distintos a la especie de origen (mismo pokémon, distinto nivel)?
7. **3 slots**: ¿pueden repetirse exactamente la misma pieza (misma especie y tipo) o sólo "tipo repetido de especies distintas"? Hay que fijar la clave de unicidad.
8. **Habilidad pasiva extra**: ¿una sola por Pokémon o una por pieza (3 acumulables)? ¿Se pierde al anular habilidad / robar objeto (`HabilidadCombatiente::esEfectoDeObjeto`)? ¿Se registra en `ClaveItem` para que sea tratada como objeto?
9. **Requisito de nido**: el forjado exige nido nivel 1/3/5 según etapas. ¿Se permite fabricar la pieza de una etapa superior sin haber construido la inferior (pago directo) o es obligatorio evolucionar consumiendo la anterior?
10. **"Pokémon de los que el jugador tenga nidos construidos"**: ¿la sección de Forja lista familias (con su primer integrante) o especies? ¿Se filtra por hábitat actual, por todos los hábitats del jugador, o global? ¿Se muestra el bonguri del hábitat como material?
11. **Materiales**: ¿el bonguri del hábitat es ingrediente de la pieza, y con qué bonguri se fabrica una pieza de una familia presente en varios hábitats con bonguris distintos?
12. **Modos**: "3 slots persistentes en todos los modos" — ¿también en exploraciones 1v1 y en la batalla mock? ¿Y en rivales (entrenador/gimnasio/mazmorra) que no tienen reclutado?
13. **Sesión**: ¿sube `SESSION_VERSION` y se acepta que batallas en curso se descarten?

## 12. Recomendación (Parte B, sin implementar)

Diseñar primero el **contrato de dominio de la pieza** (familia/especie de origen, etapa, tipo arma/armadura/amuleto, nivel de pieza, stats derivados, habilidad pasiva) como DTO readonly + enum, y **no** extender `CatalogoRecetas` estático. En paralelo, decidir la persistencia (recomendación: tabla `reclutado_equipamiento` con slot 1-3 y snapshot de la pieza, dejando `obj_equipados` como legado de 1 objeto para no romper 40 tests ni la UI en curso), y definir el punto de inyección único: `DatosPokemonBatalla` ya es el embudo por el que pasan los 4 modos 3v3 y las exploraciones, de modo que ampliarlo (item → colección de piezas) es el menor cambio de superficie en el motor; a cambio obliga a revisar `ObjetoCombatiente`, `FabricaEfectos::crearItem`, `ManejadorObjetosEquipados`, `HabilidadCombatiente::esEfectoDeObjeto` y `SESSION_VERSION`.

## VERIFICACIÓN EJECUTADA (Parte B)

- `git status --short` → 40 archivos modificados + untracked (TASK-049..056, `app/Support/UI/`, `docs/adr/0004-*`, `resources/views/equipos/partials/modal-editar-equipo.blade.php`, `resources/views/components/trainer-sprite.blade.php`, `public/images/trainers/`, `resources/data/entrenadores_sprites.json`).
- `git branch --show-current` → `master`.
- `git diff --stat` → 40 archivos, +1301/−774 (incluye `src/Battle/Domain/CombatienteVista.php`, `src/Exploraciones/Presentation/*`, `resources/views/equipos/*`, `resources/css/*`).
- `grep` sobre `bonguri`, `obj_equipados`, `habilidad_activa_id`, `ConstruirEquipoJugador`, `EquipoBatalla::fromData`, `registrarItem`, `DatosPokemonBatalla`, `defenseHp/spDefenseHp`.
- Sin ejecución de app, tests, migrations ni seeders; sin lectura de `.env`.

## PROBLEMAS DETECTADOS (Parte B)

1. **No existe consulta de "nidos construidos"** (`level >= 1`) por jugador: `ObtenerGranja` es por hábitat y rellena nivel 0. La Forja necesita un lector nuevo (o agregación de `GranjaDetalle` por hábitat).
2. **Equipamiento = 1 objeto** en dominio, motor, API y UI. La ampliación a 3 slots no es aditiva en el motor: `ObjetoCombatiente`/`EstadoObjeto` y la mecánica de robo/intercambio asumen un único objeto.
3. **`ResolvedorCadenasEvolutivas` no sirve para la regla de etapas pedida**: `maxEtapas=3`, `multiRaiz=false`, fallback `stage=3`, y `levelForStage()` devuelve 2/`min(stage,3)`.
4. **`CatalogoRecetas` es estático por clave de item**; las piezas de forja son paramétricas (familia × etapa × tipo × nivel de nido).
5. **`CatalogoItems`/`CategoriaItem` no tienen arma/armadura/amuleto**; sin categoría no hay prefijo de inventario ni `aPartirDeItemKey`.
6. **`CatalogoObjetos::iconoDe()` vacío** y `CatalogoObjetos` desalineado con `CatalogoItems` (dos catálogos de objetos).
7. **UI de equipamiento completamente en zona de conflicto** (archivos modificados localmente + un partial untracked de otra tarea).
8. **Los docs están desactualizados respecto al código** en Battle: `src/Battle/context.md`/`docs/context.md` hablan de `Combatiente` 606 líneas, `SESSION_VERSION=11` y 7 manejadores en la cadena; el código actual tiene `Combatiente` 788 líneas, 20+ manejadores en `CadenaDanio`, componentes `Componentes/Combatiente/*` y ~100 efectos.

## TAREAS PENDIENTES (Parte B)

- Leer el resto de `resources/views/forja/index.blade.php` (400-577) y el JS de agrupación por categorías.
- Leer el modal de granja completo (`resources/views/habitats/show.blade.php` + `partials/columna-*`) para ubicar el listado de nidos en UI.
- Confirmar `SESSION_VERSION` real y el shape serializado (`app/Support/BattleSessionService.php` + `SerializacionCombatiente`).
- Revisar `src/Forja/App/DataTransferObjects/*` y `src/Shared/Domain/Ports/InventarioJugador` + `InventarioJugadorEloquent` (contrato de inventario que usaría la Forja).
- Verificar `CalculadorCP` / `CalculadoraPcReclutado` para saber si las piezas deben afectar al CP mostrado.
- Revisar `tests/` que toquen `CatalogoObjetos`/items para cuantificar el impacto exacto del cambio 1→3.

## RECOMENDACIONES SIGUIENTES (Parte B)

1. Cerrar con el arquitecto las preguntas 1, 3, 4, 6 y 8 antes de diseñar (persistencia, naturaleza de stats, curva por nido, cálculo del amuleto, semántica de la pasiva).
2. Diseñar el dominio de pieza (`PiezaForja` DTO readonly + `TipoPieza` enum + `ReglasForja` con la curva de etapas 1/3/5 · 2/4 · 3) **antes** de tocar `DatosPokemonBatalla`.
3. Extender el análisis en una segunda pasada sobre los 5 puntos pendientes de arriba, en particular `BattleSessionService` y el modal de granja.
4. No tocar `resources/views/equipos/partials/modal-editar-equipo.blade.php` (untracked de otra tarea) sin aprobación explícita del desarrollador.
