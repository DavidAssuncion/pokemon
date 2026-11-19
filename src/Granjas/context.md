# Contexto del módulo `Granjas`

> Fuente de conocimiento del módulo. Si cambias una regla de producto, un contrato o una decisión
> de diseño, actualiza este archivo en el mismo cambio. Las búsquedas guiadas están al final.

## Propósito

Granjas por hábitat: el jugador construye y mejora **nidos**, **refugios** y **complementos EV**
para las familias evolutivas (`evolution_chain_id`) presentes en un hábitat. Los bonos se consumen
en Huevos (tiempo/IV) y en los modos de combate (aparición/captura), y los complementos EV se
aplican a todos los reclutados de la familia.

## Estructura y responsabilidades

| Ruta | Responsabilidad |
|---|---|
| `Domain/CalculadoraBonosGranja.php` | Cálculos puros: rango de IVs, factor de tiempo, multiplicadores de refugio, costes y EV de complementos. |
| `Domain/DataTransferObjects/` | `GranjaDetalle`, `NidoGranja`, `RefugioGranja`, `ComplementoGranja`, `BonosFamilia`, `CosteCaramelos`, `ResultadoCosechaGlobal`, `BayaCosechada`, `ErrorCosecha`, `CosechaNido`, etc. |
| `Domain/Collections/` | `FamiliasGranjaCollection`, `NivelesComplementoCollection`, `RefugiosHabitatCollection`, `CaramelosFamiliaCollection`, `CaramelosEvCollection`, `BayasCosechadasCollection`, `ErroresCosechaCollection`. |
| `Domain/Collections/TiposPokemonCollection.php` | Unión deduplicada de tipos de los miembros vistos, sin volver a consultar la Pokédex. |
| `Domain/ProyectorFamiliasVisibles.php` | Política pura: conserva familias con algún miembro visto y proyecta `base`, `evolutions` y `types`. |
| `Domain/Ports/PokedexLecturaInterface.php` | Puerto de lectura de avistamientos, cadenas visibles y tipos vistos por usuario. |
| `App/AsegurarGranja.php` | Garantiza una granja por `user_id + habitat_id`. |
| `App/FamiliaEnHabitat.php` | Valida que la familia pertenezca al hábitat **y** que el jugador la haya avistado; 404 neutro. |
| `App/InventarioGranja.php` | Lee/consume `player_inventory` (`familia:{chain}`, `ev:{stat}`); filtra familias a cadenas visibles. |
| `App/MejorarNido.php`, `MejorarRefugio.php`, `MejorarComplemento.php` | Compras/mejoras con transacción, `lockForUpdate` y coste. |
| `App/AlternarCondicionesInsalubres.php` | Activa/desactiva condiciones insalubres. |
| `App/ResolverBonosFamilia.php` | Resuelve los bonos vigentes de una familia (nido, refugio, EV). |
| `App/CosecharTodo.php` | Cosecha global por nido con transacción, locks, prefiltro de cadenas visibles y errores parciales. |
| `App/ObtenerGranja.php`, `AccionesGranja.php` | Fachada de lectura (aplica visibilidad) y acciones para el controlador. |
| `Infra/PokedexLectura.php` | Implementación con `DB::table` y `user_id` explícito; no usa el global scope `BelongsToUser`. |
| `Infra/Controllers/GranjaController.php` | HTTP JSON; resuelve el flag QA y devuelve `GranjaDetalle::toArray()`. |

## Decisiones de producto y funcionamiento

### Granja y familia

1. **Una granja por `user_id + habitat_id`** (`AsegurarGranja`). No hay granja global.
2. **La familia debe pertenecer al hábitat y estar avistada** (`FamiliaEnHabitat`); se identifica
   por `evolution_chain_id`, no por especie. La regla de visibilidad se detalla abajo.
3. **La familia puede estar en varios hábitats**. `ResolverBonosFamilia::habitatDeFamilia()` toma
   el primer `pokemon_habitat` sin orden explícito; si una familia vive en varios hábitats, el bono
   aplicado puede ser no determinista. **Decisión pendiente**: definir el hábitat canónico o
   resolver por hábitat de la operación.

### Visibilidad de familias (TASK-067)

- **Regla:** una familia es visible para el jugador si existe al menos un `pokedex.visto = true` de
  un miembro de su `evolution_chain_id`. Sin ese avistamiento no aparece en
  `GET /api/habitats/{habitat}/farm` ni en `inventario.familia`.
- **Proyección:** `base` es el miembro visto con menor `stage`, luego `species_id`, luego `id`;
  `evolutions` solo contiene miembros vistos y `types` es la unión de los tipos de esos miembros.
  Si el `base` original no está visto pero una evolución sí, la evolución pasa a ser `base`. Se
  conserva el orden de familias del repositorio (`minSpeciesId` de la cadena completa) para que no
  salten al avistar. No se añaden campos de visibilidad: `FamiliaGranja::toArray()` y
  `GranjaDetalle::toArray()` no cambian.
- **Inventario:** `InventarioGranja::resumen()` filtra solo las claves `familia:{chain}` a las
  cadenas visibles; `ev:{stat}` queda intacto. Así no se exponen `evolution_chain_id` ni cantidades
  de familias ocultas.
- **Guard de escritura:** `FamiliaEnHabitat::asegurar()` comprueba avistamiento y asignación al
  hábitat. Se aplica a nido, refugio, condiciones, complementos, plantar y cosechar.
- **404 neutro:** una familia no avistada, una no asignada y una inexistente lanzan el mismo
  `RecursoNoExiste` con `Familia no disponible en este hábitat` (404). El cliente no puede
  distinguirlas y el mensaje no revela datos.
- **Cosecha global:** `CosecharTodo` prefiltra los nidos por cadenas visibles antes de construir
  candidatos; el descarte es silencioso y no genera `ErrorCosecha`, de modo que `nidos`, `bayas` y
  `errores` no exponen cadenas ocultas.
- **QA:** `GranjaController` evalúa `App\Support\DesbloqueoPokedex::activo($request)` y pasa
  `$incluirNoAvistadas` a lectura y acciones. `POKEDEX_UNLOCK_ALL=true` mantiene el comportamiento
  completo; `?unlock=1` solo en `local`/`testing` se propaga desde `show.blade.php` con
  `urlGranja()` a las URLs de granja. `POST /api/farm/harvest-all` no recibe el flag de query: en QA
  se usa la variable de entorno.
- **Límites:** la gestión admin (`ObtenerFamiliasDisponibles`, `HabitatLecturaRepository`,
  `/api/habitats/{id}/families` y `/unassigned-families`) sigue mostrando todas las familias. El
  HTML de hábitat, `/api/habitats/{h}/pokemon`, Huevos, Forja y Exploración quedan fuera del
  alcance de esta regla, igual que el gateo por rol de los endpoints admin (ver deuda).
- **Persistencia:** no hay migraciones ni cambios de esquema. Nidos, refugios y plantaciones de una
  familia oculta se conservan y reaparecen con su infraestructura al avistar un miembro.

### Nidos (0–5)

4. Coste de subida (caramelos de familia): **25 / 75 / 225 / 450 / 900**.
5. Cada nivel reduce el tiempo de huevo un **10 %** hasta un máximo del **50 %**
   (`1.0 - min(0.5, 0.10 × nivel)`).
6. Rango de IVs por nivel de nido: **0–3 / 4–7 / 8–9 / 10–11 / 12–14 / 15** (nivel 5 = 15).

### Refugios (1–5)

7. Coste: **25 / 75 / 225 / 450 / 2700**.
8. Multiplicador de aparición y captura (condiciones salubres): **×1.25 / ×1.5 / ×2.0 / ×2.5**.
9. **Nivel 5 es exclusivo**: solo una familia puede tener refugio nivel 5 en el hábitat; aparición
   ×1.0 para esa familia y ×0 para el resto; captura ×3.0.
10. **Condiciones insalubres** invierten el efecto: multiplicadores **×0.8 / ×0.6667 / ×0.5 / ×0.4**;
    en nivel 5 la familia queda excluida (aparición 0, captura ×0.2) y no se pueden mejorar
    complementos.
11. El refugio debe existir para mejorar complementos (nivel ≥ 1) y las condiciones deben ser
    salubres.

### Complementos EV (1–5)

12. EV por nivel: **64 / 128 / 256 / 384 / 512**.
13. Coste de subida: **3 × EV + 1 × caramelo de familia** (`CosteCaramelos`).
14. Se aplican **retroactivamente** a todos los reclutados de la familia (delta = EV nuevo − EV
    anterior), con **cap 512 por stat**; los nuevos reclutados reciben los EV vigentes al crearse.
15. Los complementos se guardan por refugio + stat (`Complemento`).

### Inventario y UI

16. Inventario unificado en `player_inventory`: `familia:{chain}` y `ev:{stat}`.
17. UI: modal **Granjas** dentro de `resources/views/habitats/show.blade.php`; las acciones mutan
    estado local tras 200 OK (sin refresco pesado). El modal solo pinta las familias que devuelve
    `/farm` (ya filtradas por avistamiento); el vacío se muestra como
    `Todavía no hay familias para mostrar en este hábitat.` y **no** implica que no haya familias
    asignadas.
18. `GranjaController` devuelve el detalle completo (`GranjaDetalle`) tras cada acción.

### Cosecha global (TASK-014)

19. `CosecharTodo::handle(int $userId, bool $incluirNoAvistadas = false)` cosecha **todas** las
    plantaciones listas del jugador en todos sus hábitats y nidos. Antes de construir candidatos
    descarta en silencio los nidos de cadenas sin ningún miembro avistado (salvo
    `$incluirNoAvistadas`); esos nidos no aparecen en `nidos`, `bayas` ni `errores`. No crea granjas
    ni nidos; si no hay candidatos devuelve un resultado vacío.
20. Selección en **3 queries** (granjas, nidos con `level >= 1`, plantaciones candidatas) y
    **una transacción por nido**:
    - lock del nido (`whereKey` + `where farm_id`);
    - lock de las plantaciones del nido por `id` ascendente;
    - recálculo de stock con `ReglasPlantacion::stockDe()` tras el lock; si es `<= 0`, se omite;
    - `increment('harvest_count', stock)` y acumulación por `berry_key`;
    - incremento del inventario agregado por baya, ordenado por `item_key`.
21. **Errores parciales:** un fallo en un nido se captura como `ErrorCosecha` y el resto continúa.
    Las excepciones de dominio conservan su mensaje; el resto se registra con `report()` y se
    devuelve un mensaje genérico.
22. **Idempotencia:** el stock se recalcula bajo lock y `harvest_count` se actualiza en la misma
    transacción; dos peticiones inmediatas no duplican bayas. El botón también se deshabilita
    mientras hay una petición en curso.
23. El botón del menú (`fa-solid fa-seedling`) emite `inventario:actualizado` al terminar. La
    granja recarga en silencio si el modal está abierto; la forja relee recetas con `cargar(false)`.
    El botón se oculta en `/combate*`.
24. El bonus de plantaciones no cambia: sigue siendo `min(2.0, 1 + 0.20 × plantas)` y solo se
    añadió una nota estática en la UI.

## Contratos y puntos de entrada

Rutas (`routes/granjas.php`):

```text
GET  /api/habitats/{habitat}/farm
POST /api/habitats/{habitat}/farm/nests/{chain}/upgrade
POST /api/habitats/{habitat}/farm/shelters/{chain}/upgrade
POST /api/habitats/{habitat}/farm/shelters/{chain}/toggle-unhealthy
POST /api/habitats/{habitat}/farm/shelters/{chain}/complements/{stat}/upgrade
POST /api/farm/harvest-all
```

- `GranjaController` delega en `AccionesGranja`, `ObtenerGranja` y `CosecharTodo`.
- `GET /api/habitats/{habitat}/farm` devuelve solo familias visibles, con el mismo shape por
  familia; `inventario.familia` solo contiene cadenas visibles. No añade `visible`, `oculta` ni
  contadores de ocultas.
- Las acciones de granja (nido, refugio, condiciones, complementos, plantar y cosechar) responden
  `404 {"message":"Familia no disponible en este hábitat"}` si la cadena no está avistada, no
  pertenece al hábitat o no existe; no mutan datos.
- `GET /api/habitats/{id}/families` y `GET /api/habitats/unassigned-families` no cambian: son
  gestión admin y no aplican el filtro de avistamiento.
- `POST /api/farm/harvest-all` no lleva body y responde
  `{total_bayas, total_plantaciones, nidos, bayas[], errores[]}`; `200` también con errores
  parciales. `bayas[]` = `{clave, nombre, icono, cantidad}`; `errores[]` =
  `{farm_id, habitat_id, nido_id, evolution_chain_id, mensaje}`. En QA no acepta `?unlock=1`: usar
  `POKEDEX_UNLOCK_ALL=true`.
- Tablas: `granjas` (user+habitat), `nidos`, `refugios`, `complementos`, `player_inventory`.
- `ResolverBonosFamilia` es el punto de lectura para Huevos y modos de combate.

## Dependencias

- `Src\Pokemon\Domain\Stats\GeneradorIvs`/`RangoIvs` — rangos de IV.
- `Src\Huevos\App\CrearHuevo` — consume nido (tiempo/IV) vía `ResolverBonosFamilia`.
- `Src\Exploraciones` / `CombateRuta` / `CombateEntrenadores` — consumen factores de aparición y
  captura.
- `App\Models\Granja`, `Nido`, `Refugio`, `Complemento`, `PlayerInventory`.
- `App\Support\ItemCatalogo` — claves de inventario.

## Deuda y mejoras priorizadas (auditoría 2026-10-31)

- **[Seguridad, fuera de alcance TASK-067] Endpoints admin de familias sin rol/policy**:
  `/api/habitats/{id}/families` y `/api/habitats/unassigned-families` (y el modal de gestión) son
  accesibles a cualquier usuario autenticado. Añadir autorización por rol/policy cuando exista el
  modelo de permisos.
- **[Alta] `habitatDeFamilia()` no determinista** si la familia está en varios hábitats: definir
  hábitat canónico o pasar el hábitat de la operación. Evidencia:
  `ResolverBonosFamilia::habitatDeFamilia()` usa `value('habitat_id')` sin `orderBy`.
- **[Media] Costes y multiplicadores viven en `match`** dentro de `CalculadoraBonosGranja`.
  Extraer constantes nombradas (`COSTE_NIDO_*`, `COSTE_REFUGIO_*`, `FACTOR_REFUGIO_*`) o una tabla
  de configuración tipada; mantener una sola fuente.
- **[Media] `MejorarNido`/`MejorarRefugio`/`MejorarComplemento` repiten el patrón**
  transacción → asegurar granja → buscar/crear → `lockForUpdate` → validar máximo → consumir →
  incrementar. Extraer un servicio de mejora genérico o un `GranjaUpgradeService`.
- **[Media] `ResolverBonosFamilia` mezcla lectura Eloquent, reglas y fallback neutro**. Separar el
  repositorio de bonos y el cálculo puro; el dominio ya tiene `CalculadoraBonosGranja`.
- **[Baja] `GranjaController::respuesta()` devuelve `toArray()`**; correcto como frontera, pero
  mantener el DTO como contrato y no añadir arrays ad hoc.
- **Norma**: no crear arrays nuevos para bonos/costes; usar los DTOs existentes (`NidoBonos`,
  `RefugioBonos`, `CosteCaramelos`, `BonosFamilia`).

## Guía de búsqueda (grep)

- Clases: `CalculadoraBonosGranja`, `ResolverBonosFamilia`, `AsegurarGranja`, `FamiliaEnHabitat`,
  `InventarioGranja`, `MejorarNido`, `MejorarRefugio`, `MejorarComplemento`, `GranjaDetalle`,
  `NidoBonos`, `RefugioBonos`, `BonosFamilia`, `CosteCaramelos`, `PokedexLecturaInterface`,
  `PokedexLectura`, `ProyectorFamiliasVisibles`, `TiposPokemon`, `TiposPokemonCollection`.
- Constantes: `CalculadoraBonosGranja::MAX_NIDO`, `MAX_REFUGIO`, `MAX_COMPLEMENTO`,
  `REDUCCION_TIEMPO_POR_NIVEL`, `REDUCCION_TIEMPO_MAXIMA`.
- Rutas/términos: `/api/habitats/{habitat}/farm`, `nests`, `shelters`, `complements`,
  `toggle-unhealthy`, `plantings`, `harvest-all`, `nido`, `refugio`, `insalubres`, `avistad`,
  `visible`, `unlock`, `Familia no disponible en este hábitat`.
- Tablas: `granjas`, `nidos`, `refugios`, `complementos`, `player_inventory`.
- Tests: `tests/Feature/Granjas/`, `tests/Unit/Granjas/` (si existen), y tests de Huevos que usan
  `ResolverBonosFamilia`.
