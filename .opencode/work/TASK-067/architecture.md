# TASK-067 — Arquitectura

- **ID:** TASK-067
- **Estado:** COMPLETADO
- **Arquitecto:** `architect`
- **Fecha:** 2026-11-19

## 1. Decisión de diseño y principios

**Decisión:** la visibilidad de familia es una **política de dominio pura** (`familia visible ⇔ al menos un miembro con pokedex.visto = true` para el usuario) que se aplica **solo en el caso de uso de jugador** (`ObtenerGranja`) y en el **guard único de escritura** (`FamiliaEnHabitat`), apoyada en un **puerto de lectura del conocimiento del jugador** (Pokédex) implementado en infraestructura. No se toca el caso de uso compartido con admin ni el contrato de `/api/habitats/{id}/families` ni `/unassigned-families`.

Principios aplicados:

- **Regla de dependencia:** `Domain` no consulta nada; `App` orquesta puerto + proyector; `Infra` aísla Eloquent (`Pokedex`, `PokemonType`) y el controlador sigue sin lógica de negocio.
- **Un solo dueño de la regla:** el predicado "cadena avistada" vive en un único puerto (`cadenasConAlgunVisto`) y lo consumen lectura, guard de acciones y prefiltro de `CosecharTodo`. Nada de SQL de visibilidad duplicado.
- **Contratos tipados:** IDs vistos como `IntCollection`; tipos como `TiposPokemonCollection`/`TiposPokemon`; nada de arrays asociativos entre capas.
- **Proyección, no mutación:** `DTOFamiliaDisponible` (compartido con admin) **no se modifica**; el proyector construye **instancias nuevas** solo para la respuesta de granja.
- **Silencio ante lo no visto:** ni nombres, ni iconos, ni tipos, ni `evolution_chain_id` de familias no avistadas en el payload de granja; tampoco en `inventario.familia`.
- **Sin migraciones ni caché:** `RecompilarHabitatJsonJob` no interviene; los datos existentes se conservan.

**Descartado explícitamente:** filtrar en `HabitatLecturaRepository`/`ObtenerFamiliasDisponibles`, filtrar en el controlador, filtrar en Blade/JS, enriquecer `DTOPokemonFamilia` con tipos, usar sesión o middleware para el flag QA, crear un read-model específico de granja que sustituya al proyector.

### 1.1 Alternativas evaluadas y motivo de descarte

| Opción | Ventaja | Motivo de descarte |
|---|---|---|
| **B. Filtrar en `HabitatLecturaRepository::getFamiliesByHabitat(?userId)`** | Filtra antes de construir DTOs (menos trabajo) | Mete una regla de jugador en un repositorio compartido con admin; cambia la interfaz usada por 4 llamadores; el admin depende de recibir todo. |
| **C. Filtrar en `GranjaController` tras `ObtenerGranja`** | Cambio localizado | Lógica de negocio en controlador; obliga a reconstruir DTOs readonly en cada acción; prohíbe `infra-controllers.md`. |
| **D. Filtrar en Blade/JS con `sightedPokemonIds`** | Cero backend | La API seguiría sirviendo nombres/iconos/tipos; viola `frontend-minimal-logic.md` y no cumple "no dar pistas". |
| **E. Enriquecer `DTOPokemonFamilia` con `types`** | Una sola consulta en el repo de hábitats | Cambia `toArray()` de un DTO consumido por admin/`/families`; rompe contrato y tests de `FamiliesTest`. |
| **F. Read-model único de granja que devuelva familias ya proyectadas** | Menos clases | Duplica en infraestructura la regla de visibilidad que el guard necesita por cadena; el prefiltro de `CosecharTodo` seguiría necesitando el puerto. Dos fuentes de la misma regla. |
| **G. Persistir `desbloqueada` en `pokemon_habitat`/nueva columna** | Consulta trivial | Requiere migración, backfill y sincronización con `ActualizarPokedexJob`; datos derivados de `pokedex` (redundancia). Prohibido por alcance. |
| **H. Sesión/middleware para `?unlock=1`** | Evita propagar el parámetro | Estado implícito por usuario, más superficie y no cubre `harvest-all` (layout global sin request-derived flag). El flag QA es transporte, no negocio. |

## 2. Contratos nuevos/modificados (firmas exactas)

### 2.1 Puerto de lectura (nuevo)

`src/Granjas/Domain/Ports/PokedexLecturaInterface.php`

```php
<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Ports;

use Src\Granjas\Domain\Collections\TiposPokemonCollection;
use Src\Shared\Collections\IntCollection;

/**
 * Lectura del conocimiento del jugador sobre pokémon: qué ha avistado, qué
 * cadenas evolutivas tiene desbloqueadas y los tipos de esos pokémon.
 * No depende de Auth: el `userId` es explícito.
 */
interface PokedexLecturaInterface
{
    /**
     * Subconjunto de $candidatos con `pokedex.visto = true` para el usuario.
     */
    public function vistos(int $userId, IntCollection $candidatos): IntCollection;

    /**
     * Cadenas evolutivas (de $cadenas) con al menos un miembro visto.
     */
    public function cadenasConAlgunVisto(int $userId, IntCollection $cadenas): IntCollection;

    /**
     * Tipos de los pokémon vistos por el usuario, restringido a $pokemonIds.
     */
    public function tiposVistos(int $userId, IntCollection $pokemonIds): TiposPokemonCollection;
}
```

Implementación: `src/Granjas/Infra/PokedexLectura.php` (Eloquent/Query Builder). Se registra en `AppServiceProvider::register()`:

```php
$this->app->bind(
    \Src\Granjas\Domain\Ports\PokedexLecturaInterface::class,
    \Src\Granjas\Infra\PokedexLectura::class,
);
```

> `tiposVistos` vive en este puerto porque su resultado está acotado por `pokedex.visto` (es "lo que el jugador conoce"), no por el catálogo completo. Filtra por `user_id` **y** `visto`: aunque el llamador pase ids ya vistos, el puerto no puede filtrar tipos de especies no vistas.

### 2.2 DTOs/colecciones nuevos (Dominio de Granjas)

`src/Granjas/Domain/DataTransferObjects/TiposPokemon.php`

```php
final readonly class TiposPokemon
{
    public function __construct(
        public int $pokemonId,
        public ColeccionTiposFamilia $tipos, // reutiliza el DTO existente de Habitats
    ) {}
}
```

`src/Granjas/Domain/Collections/TiposPokemonCollection.php`

```php
final class TiposPokemonCollection extends Collection
{
    public string $type = TiposPokemon::class;

    /**
     * Unión de tipos de los pokémon indicados, deduplicada por id y ordenada por id.
     */
    public function tiposDe(IntCollection $pokemonIds): ColeccionTiposFamilia;
}
```

> Reutilizar `ColeccionTiposFamilia`/`DTOPokemonTipo` (Habitats/Presentation) evita duplicar el DTO y mantiene `Domain` libre de `App\Enums`: el mapeo `TipoEnum → id/name` ocurre en infraestructura. Es la misma dirección de dependencia que ya usa `FamiliaGranja` (Granjas/Domain → Habitats/Presentation).

### 2.3 Proyector de dominio (nuevo)

`src/Granjas/Domain/ProyectorFamiliasVisibles.php`

```php
final class ProyectorFamiliasVisibles
{
    /**
     * Filtra a familias con ≥1 miembro visto y proyecta representante,
     * evolutions y types solo con miembros vistos. Conserva el orden de entrada.
     */
    public function proyectar(
        DTOFamiliasDisponibles $familias,
        IntCollection $pokemonVistos,
        TiposPokemonCollection $tipos,
    ): DTOFamiliasDisponibles;
}
```

Algoritmo (§3.2) puro, sin I/O, determinista.

### 2.4 Accesores tipados en el DTO compartido (modificación aditiva, sin cambio de `toArray()`)

`src/Habitats/Presentation/DTOFamiliasDisponibles.php` — añadir:

```php
public function pokemonIds(): IntCollection;        // base + evolutions de todas las familias
public function evolutionChainIds(): IntCollection;  // cadenas de todas las familias
```

`toArray()` y `ordenadasPorMinSpeciesId()` intactos: **el contrato JSON de `/families` y `/unassigned-families` no cambia**.

### 2.5 Firmas modificadas de casos de uso (un único parámetro QA opcional)

```php
// src/Granjas/App/ObtenerGranja.php
public function handle(int $userId, int $habitatId, bool $incluirNoAvistadas = false): GranjaDetalle;

// src/Granjas/App/FamiliaEnHabitat.php
public function __construct(private readonly PokedexLecturaInterface $pokedex) {}
public function asegurar(
    int $userId,
    int $habitatId,
    int $evolutionChainId,
    bool $incluirNoAvistadas = false,
): void;

// src/Granjas/App/AccionesGranja.php
public function nido(int $userId, int $habitatId, int $evolutionChainId, bool $incluirNoAvistadas = false): void;
public function refugio(int $userId, int $habitatId, int $evolutionChainId, bool $incluirNoAvistadas = false): void;
public function condiciones(int $userId, int $habitatId, int $evolutionChainId, bool $incluirNoAvistadas = false): void;
public function complemento(int $userId, int $habitatId, int $evolutionChainId, string $stat, bool $incluirNoAvistadas = false): void;

// MejorarNido / MejorarRefugio / AlternarCondicionesInsalubres
public function handle(int $userId, int $habitatId, int $evolutionChainId, bool $incluirNoAvistadas = false): void;

// MejorarComplemento
public function handle(int $userId, int $habitatId, int $evolutionChainId, string $statKey, bool $incluirNoAvistadas = false): void;

// PlantarBaya
public function handle(int $userId, int $habitatId, int $evolutionChainId, int $slot, string $berryKey, bool $incluirNoAvistadas = false): void;

// CosecharPlantacion
public function __construct(
    private readonly AsegurarGranja $asegurarGranja,
    private readonly InventarioJugador $inventario,
    private readonly FamiliaEnHabitat $familiaEnHabitat,
) {}
public function handle(int $userId, int $habitatId, int $evolutionChainId, int $slot, bool $incluirNoAvistadas = false): int;

// CosecharTodo
public function __construct(
    private readonly InventarioJugador $inventario,
    private readonly PokedexLecturaInterface $pokedex,
) {}
public function handle(int $userId, bool $incluirNoAvistadas = false): ResultadoCosechaGlobal;

// InventarioGranja
public function resumen(int $userId, ?IntCollection $cadenasVisibles = null): InventarioCaramelosGranja;
```

`$cadenasVisibles = null` significa "sin filtro" (QA / otros usos). Se filtran **solo** las entradas `familia:{chain}`; `ev:{stat}` no se toca.

### 2.6 Sin cambios de contrato

- `FamiliaGranja::toArray()` y `GranjaDetalle::toArray()`: **idénticos**.
- `DTOFamiliaDisponible` / `DTOPokemonFamilia`: **intactos**.
- Rutas de `routes/granjas.php`: **idénticas** (solo cambian firmas de métodos del controlador).
- `bootstrap/app.php`: **intacto** (se reutiliza `RecursoNoExiste` → 404).
- Tablas: **sin cambios**.

## 3. Flujo de lectura (filtro + proyección + tipos + inventario)

### 3.1 `ObtenerGranja::handle`

```text
1. $granja   = AsegurarGranja::para($userId, $habitatId)
2. $familias = ObtenerFamiliasDisponibles::handle($habitatId)   // sin tocar
3. si (!$incluirNoAvistadas):
      $vistos = pokedex->vistos($userId, $familias->pokemonIds())
      $tipos  = pokedex->tiposVistos($userId, $vistos)
      $familias = proyector->proyectar($familias, $vistos, $tipos)
      $cadenasVisibles = $familias->evolutionChainIds()
   si ($incluirNoAvistadas): $cadenasVisibles = null
4. $inventario = inventario->resumen($userId, $cadenasVisibles)
5. resto igual: nidos, refugios, plantaciones, contexto, familiaGranja(...), GranjaDetalle
```

Notas:

- Se consulta Pokédex **una vez** con todos los `pokemon_id` del hábitat (`unique(user_id, pokemon_id)` + `whereIn`), y tipos **una vez** con el subconjunto visto. Cero N+1 por familia.
- `$inventario` se calcula **después** de la proyección porque necesita las cadenas visibles; `$contexto` y `GranjaDetalle` lo reciben igual.
- El `instanceof DTOFamiliaDisponible` del bucle actual se conserva por defensividad del tipo de colección.

### 3.2 Proyección (`ProyectorFamiliasVisibles`)

Por cada `DTOFamiliaDisponible`:

1. `miembros = [base] ∪ evolutions` (en `ColeccionPokemonFamilia`).
2. `vistosFamilia = miembros.filter(p => p.id ∈ $vistos)`.
3. Si vacío → **familia excluida** (no se añade a la salida).
4. Orden del representante: `stage ASC, speciesId ASC, id ASC` (mismo criterio que `tabsFamilia()` de la vista; regla de negocio, no de presentación).
5. `base = vistosFamilia[0]`; `evolutions = vistosFamilia[1..n]` (mismo comparador).
6. `types = $tipos->tiposDe(ids(vistosFamilia))` → unión deduplicada por `id`, ordenada por `id` (idéntico orden al `getChainTypes` actual).
7. `new DTOFamiliaDisponible($chainId, $base, $evolutions, $types)`.

Decisiones:

- **Orden de familias:** se conserva el orden devuelto por el repositorio (ordenado por `minSpeciesId` de la cadena completa). Es estable entre visitas y no aporta información nueva; reordenar por el mínimo de los vistos haría "saltar" las tabs al avistar evoluciones.
- **`base` puede no ser el primer integrante de la especie:** es el miembro visto de menor etapa. Es exactamente lo que exige el modal (§E del análisis): el modal solo pinta `base` y `types`.
- **Tipos sin mapeo tipo→miembro:** no se necesita; la unión de tipos de los miembros vistos es suficiente para el badge y no revela tipos de no vistos.
- **QA:** con `$incluirNoAvistadas = true` **no se proyecta**: se devuelve la familia completa y la unión de tipos de toda la cadena (comportamiento actual).

### 3.3 Puerto `PokedexLectura` (infraestructura)

```text
vistos(userId, candidatos)
  SELECT pokemon_id FROM pokedex
  WHERE user_id = ? AND visto = true AND pokemon_id IN (...)
  → IntCollection   // usar query builder / withoutUserScope para no depender de Auth

cadenasConAlgunVisto(userId, cadenas)
  SELECT DISTINCT p.evolution_chain_id
  FROM pokemon p JOIN pokedex px ON px.pokemon_id = p.id
  WHERE px.user_id = ? AND px.visto = true AND p.evolution_chain_id IN (...)

tiposVistos(userId, pokemonIds)
  SELECT pt.pokemon_id, pt.type
  FROM pokemon_type pt JOIN pokedex px ON px.pokemon_id = pt.pokemon_id
  WHERE px.user_id = ? AND px.visto = true AND pt.pokemon_id IN (...)
  → agrupar por pokemon_id, mapear TipoEnum → DTOPokemonTipo(id, name), ordenar por id
  → TiposPokemonCollection
```

Reglas de implementación:

- **Nunca** `Pokedex::query()` con el global scope `BelongsToUser`: en jobs/CLI `Auth::id()` es `null` y el scope queda inactivo. Usar `DB::table` o `withoutUserScope()` y `user_id` explícito.
- No se exponen modelos; solo `IntCollection` / `TiposPokemonCollection`.
- Sin caché: `pokedex` es la fuente; `ActualizarPokedexJob` ya es la escritura.

### 3.4 Inventario

`InventarioGranja::resumen($userId, $cadenasVisibles)` descarta `CarameloFamilia` cuya `chainId` no esté en `$cadenasVisibles` (cuando no es `null`). Motivo: hoy `inventario.familia` filtra `evolution_chain_id` y cantidades de familias no vistas (metadatos). Coste: una condición en el bucle existente. `saldoFamilia(chainId)` del modal sigue funcionando porque solo se consultan cadenas visibles; si faltara, devuelve 0.

## 4. Flujo de escritura (guard único, cosechas, error HTTP)

### 4.1 Guard único: `FamiliaEnHabitat`

```text
asegurar(userId, habitatId, chainId, incluirNoAvistadas = false):
  1. si (!incluirNoAvistadas):
        cadenas = pokedex->cadenasConAlgunVisto(userId, IntCollection([chainId]))
        si (!in_array(chainId, cadenas->all(), true)):
            throw new RecursoNoExiste('Familia no disponible en este hábitat')   // 404
  2. si (!existe pokemon_habitat(habitatId) con pokemon.evolution_chain_id = chainId):
        throw new RecursoNoExiste('Familia no disponible en este hábitat')       // 404 idéntico
```

- **Orden:** visibilidad primero, asignación después. Ambas ramas producen **la misma excepción, mismo mensaje y mismo status**; el cliente no puede distinguir "oculta" de "no asignada" ni de "no existe".
- **Estado HTTP: 404 `RecursoNoExiste`.** Justificación: 403 confirmaría que la familia existe (revela); 422 distinguiría "oculta" de "no existe" (revela). 404 es el patrón del proyecto (`bootstrap/app.php`) y el guard ya se renderiza sin tocar el handler. El frontend solo usa `response.ok` + `data.message`; no hay test ni consumidor que dependa del 422 anterior de "no asignada". **Trade-off aceptado y documentado:** una familia no asignada pero vista ahora responde 404 en vez de 422 (cambio de status, mensaje neutro).
- Sin transacción: la lectura de `pokedex` es previa a la mutación; `visto` es monótono (`ActualizarPokedexJob` nunca lo revierte), así que no hay TOCTOU relevante.

Llamadores actualizados (5 existentes): `MejorarNido`, `MejorarRefugio`, `MejorarComplemento`, `AlternarCondicionesInsalubres`, `PlantarBaya` → pasan `$userId` y `$incluirNoAvistadas`.

### 4.2 `CosecharPlantacion` (hoy sin guard)

Inyecta `FamiliaEnHabitat` y llama `asegurar($userId, $habitatId, $chainId, $incluirNoAvistadas)` **antes** de `DB::transaction`. Los errores posteriores ("nido no existe", "sin plantación", "sin stock") siguen siendo 422 y no revelan nada de familias ocultas: la familia ya es visible en ese punto.

### 4.3 `CosecharTodo` — prefiltro silencioso (sin excepciones por nido)

```text
handle(userId, incluirNoAvistadas = false):
  1. granjas del usuario (sin cambios)
  2. nidos level >= 1 (sin cambios)
  3. si (!incluirNoAvistadas):
        cadenas  = IntCollection(unique(nidos.evolution_chain_id))
        visibles = pokedex->cadenasConAlgunVisto(userId, cadenas)
        nidos    = nidos.filter(n => in_array(n.evolution_chain_id, visibles->all(), true))
        si (nidos->isEmpty()) return ResultadoCosechaGlobal::vacio()
  4. plantacionesPorNido / candidatos / transacciones (sin cambios)
```

- **No** se lanza excepción dentro del `try` por nido: `ErrorCosecha::toArray()` expone `evolution_chain_id`, y el contador `nidos` incluiría nidos ocultos. El prefiltro garantiza que `nidos`, `total_plantaciones`, `bayas` y `errores` solo describan familias visibles.
- Como el botón de `harvest-all` vive en `layouts/app.blade.php` (global), no recibe `?unlock=1`; en QA se usa `POKEDEX_UNLOCK_ALL=true`.

## 5. QA unlock

**Recomendación:** el controlador lee el flag con `DesbloqueoPokedex::activo($request)` y lo pasa como `bool` a lectura y acciones; el Blade **solo propaga el parámetro** en las URLs de granja cuando `unlockAll` es true.

- `GranjaController`: `show(Request $request, int $habitat)`, `mejorarNido(Request $request, ...)`, `mejorarRefugio`, `alternarCondiciones`, `mejorarComplemento`, `cosechar`, `cosecharTodo(Request $request)`; `plantar` reutiliza `PlantarBayaRequest` (ya es `Request`). `respuesta(int $habitat, bool $incluirNoAvistadas)`.
- Frontend (`show.blade.php`): helper mecánico

```js
urlGranja(ruta = '') {
    const base = '/api/habitats/{{ $habitat['id'] }}/farm' + ruta;
    return this.unlockAll ? base + '?unlock=1' : base;
},
```

y sustituir los literales de `loadGranja`, `mejorarNido`, `mejorarRefugio`, `alternarInsalubres`, `mejorarComplemento`, `cosecharBaya`, `replantarBaya`, `plantarBayaDesdeModal`. Es transporte de un flag QA, no lógica de negocio (no replica reglas).
- `POKEDEX_UNLOCK_ALL=true`: funciona sin propagación (config global).
- **Limitación aceptada:** `?unlock=1` no aplica a `POST /api/farm/harvest-all` (layout global sin flag). Para QA de cosecha global, usar la variable de entorno. Se documenta en `context.md`; no se toca `layouts/app.blade.php`.

## 6. Cambios por archivo y especialista

### Backend (dominio/aplicación/infraestructura)

| Archivo | Cambio |
|---|---|
| `src/Granjas/Domain/Ports/PokedexLecturaInterface.php` | **crear** puerto (§2.1) |
| `src/Granjas/Domain/DataTransferObjects/TiposPokemon.php` | **crear** DTO |
| `src/Granjas/Domain/Collections/TiposPokemonCollection.php` | **crear** colección + `tiposDe()` |
| `src/Granjas/Domain/ProyectorFamiliasVisibles.php` | **crear** proyector (§2.3, §3.2) |
| `src/Granjas/Infra/PokedexLectura.php` | **crear** implementación (§3.3) |
| `app/Providers/AppServiceProvider.php` | bind `PokedexLecturaInterface → PokedexLectura` |
| `src/Habitats/Presentation/DTOFamiliasDisponibles.php` | añadir `pokemonIds()` y `evolutionChainIds()`; **no** tocar `toArray()` |
| `src/Granjas/App/ObtenerGranja.php` | inyectar puerto + proyector; flujo §3.1 |
| `src/Granjas/App/InventarioGranja.php` | `resumen(..., ?IntCollection $cadenasVisibles = null)` |
| `src/Granjas/App/FamiliaEnHabitat.php` | inyectar puerto; guard §4.1 |
| `src/Granjas/App/AccionesGranja.php` | propagar `$incluirNoAvistadas` |
| `src/Granjas/App/MejorarNido.php`, `MejorarRefugio.php`, `MejorarComplemento.php`, `AlternarCondicionesInsalubres.php`, `PlantarBaya.php` | actualizar llamada al guard + parámetro |
| `src/Granjas/App/CosecharPlantacion.php` | inyectar guard, llamada previa a transacción |
| `src/Granjas/App/CosecharTodo.php` | inyectar puerto; prefiltro §4.3 |
| `src/Granjas/Infra/Controllers/GranjaController.php` | `Request` + flag QA en todas las respuestas/acciones |
| `src/Granjas/context.md` | documentar regla de visibilidad, guard, 404, QA y limitación de `harvest-all` |

### Frontend

| Archivo | Cambio |
|---|---|
| `resources/views/habitats/partials/modal-granja.blade.php` | solo el texto de vacío (líneas 37-39): `Todavía no hay familias para mostrar en este hábitat.` (neutro: no afirma "no asignadas" cuando están ocultas) |
| `resources/views/habitats/show.blade.php` | helper `urlGranja()` + sustitución de URLs `/farm` (§5). **No** se añade lógica de avistamiento; no se toca el JS muerto de tabs |

### Base de datos

**No aplica.** Sin migraciones, seeders ni índices nuevos (`pokedex` ya tiene `unique(user_id, pokemon_id)`; el join usa `pokemon.evolution_chain_id`). Los nidos/refugios/plantaciones de familias ocultas se conservan y reaparecen al avistar.

### Admin (fuera de alcance)

`HabitatsController::families()`, `unassignedFamilies()`, `ObtenerFamiliasDisponibles`, `HabitatLecturaRepository` y `modal-gestion-admin.blade.php`: **sin cambios**.

## 7. Orden de implementación y dependencias

1. **Dominio (backend):** `TiposPokemon` → `TiposPokemonCollection` → `PokedexLecturaInterface` → `ProyectorFamiliasVisibles`. Sin dependencias de infra.
2. **Infraestructura (backend):** `PokedexLectura` + bind en `AppServiceProvider`. Depende de 1.
3. **Contrato compartido (backend):** accesores en `DTOFamiliasDisponibles`. Independiente de 2.
4. **Aplicación lectura (backend):** `InventarioGranja::resumen` con filtro; `ObtenerGranja` (§3.1). Depende de 1, 2, 3.
5. **Aplicación escritura (backend):** `FamiliaEnHabitat` → 5 llamadores → `CosecharPlantacion` → `CosecharTodo` → `AccionesGranja`. Depende de 1, 2. **Puede ir en paralelo con 4** salvo `ObtenerGranja` (sin solape de archivos).
6. **Presentación (backend):** `GranjaController`. Depende de 4 y 5.
7. **Frontend:** `show.blade.php` (URLs) y `modal-granja.blade.php` (vacío). Depende de 6.
8. **Cierre:** `src/Granjas/context.md`, `pint`, `phpstan`, verificaciones.

Un solo especialista backend para 1-6 (secuencial, archivos compartidos como `AppServiceProvider`); frontend en 7; database no aplica; `reviewer`/`security` validan §8-§10.

## 8. Criterios de aceptación verificables

1. Usuario sin `pokedex.visto = true` de la cadena X: `GET /api/habitats/{h}/farm` **no contiene** `evolution_chain_id = X` en `familias`, ni `base`/`evolutions`/`types`/`nido`/`refugio`/`complementos` de X.
2. Con **al menos un** miembro visto de X: X aparece; `base.id` es un miembro visto; `evolutions[].id` ⊂ vistos; `types` = unión de tipos de los vistos (sin tipos exclusivos de no vistos); `familia.evolution_chain_id` correcto.
3. Caso `base` no visto y evolución sí: `base` es la evolución vista (menor `stage`, luego `species_id`, luego `id`); no aparece el `base` original no visto.
4. `GET /api/habitats/{id}/families` y `GET /api/habitats/unassigned-families`: mismo JSON que antes (mismos elementos, campos y orden; `types` sigue siendo la unión completa de la cadena).
5. Payload de `/farm`: **ningún** campo nuevo (`visible`, `oculta`, `total_ocultas` ausentes) y mismo shape por familia.
6. `inventario.familia` no incluye `evolution_chain_id` de cadenas no visibles; `inventario.ev` intacto.
7. POST de granja (nido/refugio/condiciones/complemento/plantar/cosechar) sobre cadena no vista → **404** `{"message":"Familia no disponible en este hábitat"}`, **sin mutación** (nido/refugio/complemento/plantación/inventario sin cambios).
8. Misma respuesta 404 idéntica para cadena inexistente, no asignada al hábitat o no vista (indistinguibles).
9. `POST /api/farm/harvest-all` no incluye en `nidos`, `total_plantaciones`, `bayas` ni `errores` ningún nido de cadena no vista.
10. `POKEDEX_UNLOCK_ALL=true` → `/farm` y acciones devuelven/operan sobre todas las familias (comportamiento previo). `?unlock=1` en `local`/`testing` → página, modal (`?unlock=1` propagado) y acciones coherentes.
11. Una sola consulta a `pokedex` en lectura + una de tipos (revisión de `ObtenerGranja`/`PokedexLectura` o query log); ninguna dentro del bucle de familias.
12. Familia oculta con nido/refugio/plantación existente: datos intactos en BD; al marcar `visto` un miembro, reaparece con su infraestructura y saldo de caramelos.
13. Sin lógica de avistamiento en `resources/**`: `grep -RniE "sighted|unlock|visto|pokedex" resources/views/habitats/partials/modal-granja.blade.php` solo devuelve `unlockAll` si se usara el helper (no en el modal).
14. `declare(strict_types=1);` en los 6 archivos nuevos; PHPStan nivel 6 sin errores nuevos; Pint limpio.

## 9. Verificación estática (sin tests)

```bash
# Contexto
git status --short && git diff --stat

# Estilo y análisis
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=1G        # incluye src/ y app/ (nivel 6)

# Rutas intactas
php artisan route:list --path=farm

# Contratos intactos (revisión de diff)
git diff -- src/Habitats/Presentation/DTOFamiliasDisponibles.php \
            src/Granjas/Domain/DataTransferObjects/FamiliaGranja.php \
            src/Granjas/Domain/DataTransferObjects/GranjaDetalle.php

# Admin sin tocar
git diff -- src/Habitats/App/ObtenerFamiliasDisponibles.php \
            src/Habitats/Infra/HabitatLecturaRepository.php \
            app/Http/Controllers/HabitatsController.php
```

Revisiones manuales obligatorias (sin ejecutar suite):

- `grep -RniE "sighted|visto|pokedex|unlock" resources/views/habitats/` → solo los usos preexistentes (columna derecha, `unlockAll`) y el helper `urlGranja`.
- `ObtenerGranja`: confirmar que `vistos` y `tiposVistos` están **fuera** del `foreach` de familias.
- `PokedexLectura`: confirmar `DB::table`/`withoutUserScope` (sin dependencia de `Auth`).
- `FamiliaEnHabitat`: confirmar que ambas ramas lanzan `RecursoNoExiste` con el **mismo** mensaje.
- `CosecharTodo`: confirmar que el prefiltro va **antes** de `$candidatos` y no hay `asegurar()` dentro del `try`.
- `grep -Rni "evolution_chain_id" src/Granjas/App/ObtenerGranja.php` → solo dentro de la construcción de familias visibles.
- Revisar `src/Granjas/context.md` actualizado.

## 10. Riesgos y mitigaciones

| Severidad | Riesgo | Mitigación |
|---|---|---|
| **Alto (fuera de alcance)** | `/api/habitats/{id}/families` y `/unassigned-families` siguen abiertos bajo `auth`; el modal admin muestra todo. El filtro de granja es evitable. | Deuda de seguridad separada (sin roles/policy hoy). Registrar en `context.md` y en el informe final. **No** se implementa aquí por decisión del desarrollador. |
| **Medio (fuera de alcance)** | `GET .../{chain}/pokemon/{pokemon}` devuelve detalle de especie no vista; HTML de la página contiene nombres/iconos de todos los niveles. | Tareas aparte; documentar. TASK-067 garantiza que el **payload de granja** no los contiene. |
| **Medio** | Infraestructura existente de familia no vista queda inaccesible (caramelos/mejoras "atrapados") hasta avistar. | No se borra nada; reaparece intacta. Decisión de producto ya tomada (bloquear escritura). |
| **Medio** | Cambio de 422 → 404 en "familia no asignada". | Sin tests de granja; el frontend usa `ok`/`message`. Documentado en `context.md` y en el ADR. |
| **Bajo** | Coste de construir DTOs de familias ocultas antes de filtrar (2-3 queries por cadena en el repo compartido). | Aceptado: no hay fuga y el admin necesita ese camino. Optimización futura: filtro por cadenas en el repo (opción B), hoy descartada por riesgo. |
| **Bajo** | `atrapado = true` con `visto = false` (anomalía de datos) no desbloquea. | `ActualizarPokedexJob` escribe ambos al avistar; el producto define "avistado" = `visto`. Documentar. |
| **Bajo** | `?unlock=1` no se propaga a `harvest-all` (layout global). | QA con `POKEDEX_UNLOCK_ALL=true`; documentado. |
| **Bajo** | `refugio.excluido` de una familia visible puede indicar que otra familia tiene refugio nivel 5. | No revela identidad ni datos de la oculta; aceptado. |
| **Bajo** | Orden de familias no se reordena tras la proyección. | Intencional y estable; no filtra información (las cadenas ocultas desaparecen, no se reordenan). |
| **Bajo** | PHPStan: `Collection::contains(callable(object))` con callbacks `int`. | Usar `in_array(..., true)` sobre `IntCollection::all()` en dominio/guard; no añadir API nueva a `Shared`. |

## 11. Decisiones abiertas (con recomendación)

1. **Estado HTTP del guard: 404 vs 422.**
   - A) `RecursoNoExiste` 404 con mensaje único para oculta/no asignada/no existe → **recomendada** (no confirma existencia).
   - B) `ViolacionReglaNegocio` 422 (patrón previo de "no asignada") → mantiene el status pero distingue de un 404 de recurso inexistente y confirma que la familia existe.
   - C) 403 → confirma existencia; descartada.
2. **Filtrado de `inventario.familia`.** Recomendado **sí** (bajo esfuerzo, cierra fuga de `chain` + cantidad). Alternativa: aceptar el riesgo y no tocar `InventarioGranja` (menos archivos).
3. **Texto de vacío del modal.** Propuesto: `Todavía no hay familias para mostrar en este hábitat.` Alternativas: "No hay familias disponibles en este hábitat." (más seco) o mantener el actual (engañoso). El texto es producto; el diseño solo exige que no afirme "no asignadas".
4. **QA de `harvest-all`.** Recomendado: aceptar `POKEDEX_UNLOCK_ALL` (env) y documentar que `?unlock=1` no aplica. Alternativa: compartir `unlockAll` globalmente vía `View::composer` y añadirlo al fetch del layout (más superficie en un componente global).
5. **Estabilidad del orden de familias.** Recomendado: conservar el orden del repositorio (orden por `minSpeciesId` de la cadena completa). Alternativa: reordenar por el `speciesId` del representante proyectado (las tabs cambiarían de posición al avistar evoluciones).
6. **Ubicación del proyector (`Domain` vs `App`).** Recomendado `Domain` por ser política pura y reutilizable; asume la dependencia ya existente `Granjas/Domain → Habitats/Presentation` (`FamiliaGranja`). Alternativa: moverlo a `App` si el equipo quiere evitar más acoplamiento entre módulos.

---

**Resumen de alcance:** 6 archivos nuevos, 16 modificados, 0 migraciones, 0 rutas nuevas, 0 cambios en el contrato JSON de hábitats/granja. La regla "familia visible = al menos un miembro visto" queda en un único puerto (`cadenasConAlgunVisto`) y en un proyector puro; lectura, guard y cosecha global la consumen sin duplicar SQL. Admin, `/families`, `/unassigned-families` y `ObtenerFamiliasDisponibles` permanecen intactos.
