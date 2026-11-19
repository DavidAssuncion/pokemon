# TASK-067 — Plan

- **ID:** TASK-067
- **Versión:** v1
- **Estado:** AWAITING_APPROVAL
- **Fecha:** 2026-11-19
- **Proyecto:** `/home/david/pokemon`

## 1. Objetivo

Impedir que el modal de granjas de un hábitat muestre familias de Pokémon que el
jugador no ha avistado, sin revelar nombres, iconos, tipos, evoluciones ni
metadatos de esas familias. La regla se aplica en backend y también bloquea las
acciones de granja sobre familias no avistadas.

## 2. Alcance

- Filtrar las familias devueltas por `GET /api/habitats/{habitat}/farm` a
  aquellas con **al menos un miembro avistado** (`pokedex.visto = true`).
- Proyectar la familia visible para que:
  - `base` sea el miembro avistado de menor `stage`, luego `species_id`, luego `id`;
  - `evolutions` contenga solo miembros avistados;
  - `types` sea la unión de tipos de los miembros avistados.
- Filtrar `inventario.familia` a las cadenas visibles, para no exponer
  `evolution_chain_id` ni cantidades de familias ocultas.
- Bloquear en backend las acciones de granja (nido, refugio, condiciones,
  complementos, plantar, cosechar y cosecha global) sobre cadenas sin ningún
  miembro avistado, con respuesta neutra **404**.
- Mantener intactos el contrato de `/api/habitats/{id}/families`,
  `/api/habitats/unassigned-families`, la gestión admin y el JSON de familias
  de la granja (mismos campos, sin flags de visibilidad).
- Mantener el comportamiento QA:
  - `POKEDEX_UNLOCK_ALL=true` sigue mostrando y permitiendo todo;
  - `?unlock=1` (local/testing) se propaga a las URLs de granja del modal.
- Ajustar el mensaje de vacío del modal para no afirmar que no hay familias
  asignadas cuando en realidad están ocultas.
- Actualizar la documentación del módulo `src/Granjas/context.md`.

## 3. Fuera de alcance

- Gatear por rol/policy el botón y los endpoints de gestión admin
  (`/api/habitats/{id}/families`, `/unassigned-families`). Queda como deuda de
  seguridad documentada.
- Ocultar nombres/iconos no avistados del HTML de la página de hábitat
  (`columna-derecha`), del endpoint `/api/habitats/{h}/pokemon` o del detalle de
  especie.
- Reglas equivalentes en Huevos, Forja o eventos de Exploración.
- Eliminar el JS muerto de tabs por Pokémon en `show.blade.php`.
- Reordenar las familias tras la proyección.
- Migraciones, seeders, índices o cambios de esquema.
- Tests: no se crean, no se modifican y no se ejecuta la suite.
- Nuevas dependencias, rutas o campos en el contrato JSON.

## 4. Decisiones de producto y arquitectura (v1)

1. **Familia visible:** existe al menos un `pokedex.visto = true` de la cadena
   para el usuario. Es la decisión confirmada por el desarrollador.
2. **Proyección:** el representante se calcula con orden `stage ASC`,
   `species_id ASC`, `id ASC`; `evolutions` y `types` solo usan miembros vistos.
3. **Guard de escritura:** `FamiliaEnHabitat::asegurar()` valida avistamiento y
   asignación. Ambas ramas lanzan `RecursoNoExiste` con el mismo mensaje y 404,
   de modo que una familia oculta, no asignada o inexistente sea indistinguible.
4. **Cosecha global:** `CosecharTodo` prefiltra nidos por cadenas visibles antes
   de construir candidatos; no lanza excepciones por nido para no exponer
   `evolution_chain_id` en `ErrorCosecha`.
5. **Inventario:** `inventario.familia` se filtra a cadenas visibles;
   `inventario.ev` no cambia.
6. **QA:** el controlador lee `DesbloqueoPokedex::activo($request)` y lo pasa a
   lectura y acciones. El Blade solo propaga `?unlock=1` en las URLs de granja;
   no decide visibilidad. `harvest-all` no recibe el flag de query: en QA se usa
   `POKEDEX_UNLOCK_ALL=true`.
7. **Orden de familias:** se conserva el orden actual del repositorio
   (`minSpeciesId` de la cadena completa) para evitar saltos al avistar.
8. **Arquitectura:** puerto `PokedexLecturaInterface` + proyector puro de
   dominio. El filtro se aplica en `ObtenerGranja`; no se toca el caso de uso
   compartido con admin.

## 5. Contratos y cambios técnicos

- Nuevo puerto `Src\Granjas\Domain\Ports\PokedexLecturaInterface` con:
  - `vistos(int $userId, IntCollection $candidatos): IntCollection`
  - `cadenasConAlgunVisto(int $userId, IntCollection $cadenas): IntCollection`
  - `tiposVistos(int $userId, IntCollection $pokemonIds): TiposPokemonCollection`
- Nueva implementación Eloquent `Src\Granjas\Infra\PokedexLectura`, con consultas
  explícitas por `user_id` (sin depender del global scope `BelongsToUser`).
- Nuevos `TiposPokemon`, `TiposPokemonCollection` y
  `ProyectorFamiliasVisibles` en el dominio de Granjas.
- `DTOFamiliasDisponibles` añade `pokemonIds()` y `evolutionChainIds()` sin
  cambiar `toArray()`.
- `ObtenerGranja::handle(..., bool $incluirNoAvistadas = false)`.
- `FamiliaEnHabitat::asegurar(int $userId, ..., bool $incluirNoAvistadas = false)`.
- `InventarioGranja::resumen(int $userId, ?IntCollection $cadenasVisibles = null)`.
- Acciones y casos de uso de granja propagan `$incluirNoAvistadas`.
- `GranjaController` recibe `Request` para el flag QA y lo pasa a `respuesta()`.
- `FamiliaGranja::toArray()` y `GranjaDetalle::toArray()` no cambian.
- Rutas, middleware y esquema de BD no cambian.

## 6. Archivos a crear o modificar y responsable

### Backend (`backend`)

**Crear:**

| Archivo |
|---|
| `src/Granjas/Domain/Ports/PokedexLecturaInterface.php` |
| `src/Granjas/Domain/DataTransferObjects/TiposPokemon.php` |
| `src/Granjas/Domain/Collections/TiposPokemonCollection.php` |
| `src/Granjas/Domain/ProyectorFamiliasVisibles.php` |
| `src/Granjas/Infra/PokedexLectura.php` |

**Modificar:**

| Archivo |
|---|
| `app/Providers/AppServiceProvider.php` |
| `src/Habitats/Presentation/DTOFamiliasDisponibles.php` |
| `src/Granjas/App/ObtenerGranja.php` |
| `src/Granjas/App/InventarioGranja.php` |
| `src/Granjas/App/FamiliaEnHabitat.php` |
| `src/Granjas/App/AccionesGranja.php` |
| `src/Granjas/App/MejorarNido.php` |
| `src/Granjas/App/MejorarRefugio.php` |
| `src/Granjas/App/MejorarComplemento.php` |
| `src/Granjas/App/AlternarCondicionesInsalubres.php` |
| `src/Granjas/App/PlantarBaya.php` |
| `src/Granjas/App/CosecharPlantacion.php` |
| `src/Granjas/App/CosecharTodo.php` |
| `src/Granjas/Infra/Controllers/GranjaController.php` |

### Frontend (`frontend`)

| Archivo | Cambio |
|---|---|
| `resources/views/habitats/partials/modal-granja.blade.php` | Texto de vacío neutro: `Todavía no hay familias para mostrar en este hábitat.` (líneas 37-39). Sin lógica de negocio. |
| `resources/views/habitats/show.blade.php` | Helper `urlGranja()` y sustitución de las URLs `/farm` del modal para propagar `?unlock=1` cuando `unlockAll` sea true. Sin filtros de avistamiento. |

### Documentación (`documenter`)

| Archivo | Cambio |
|---|---|
| `src/Granjas/context.md` | Documentar la regla de visibilidad, el guard 404, el filtrado de inventario, el comportamiento QA y la limitación de `harvest-all`. |

### Base de datos (`database`)

No aplica: sin migraciones, seeders ni índices.

## 7. Orden de ejecución y dependencias

1. **Backend — dominio e infraestructura:** DTOs/colecciones, puerto, proyector,
   implementación `PokedexLectura` y bind en `AppServiceProvider`.
2. **Backend — lectura:** accesores en `DTOFamiliasDisponibles`,
   `InventarioGranja`, `ObtenerGranja`.
3. **Backend — escritura:** `FamiliaEnHabitat`, cinco llamadores,
   `CosecharPlantacion`, `CosecharTodo`, `AccionesGranja`.
4. **Backend — presentación:** `GranjaController` con `Request` y flag QA.
5. **Frontend:** mensaje de vacío y URLs de granja con `?unlock=1`.
6. **Documentación:** `src/Granjas/context.md`.
7. **Revisión y seguridad:** `reviewer` y `security` sobre el diff completo.

Los pasos 2 y 3 pueden ejecutarse en secuencia dentro de la misma sesión de
`backend` para evitar solapes en `ObtenerGranja`, `AppServiceProvider` y los
casos de uso. El frontend depende de la firma del controlador del paso 4.

## 8. Criterios de aceptación verificables

1. Usuario sin ningún `pokedex.visto = true` de la cadena X:
   `GET /api/habitats/{h}/farm` no contiene `evolution_chain_id = X`, ni
   `base`/`evolutions`/`types`/`nido`/`refugio`/`complementos` de X.
2. Con al menos un miembro visto de X: X aparece; `base.id` es un miembro visto;
   `evolutions[].id` solo contiene vistos; `types` es la unión de tipos de los
   vistos; `evolution_chain_id` es correcto.
3. Si `base` no está visto pero una evolución sí: `base` es la evolución vista
   (menor `stage`, luego `species_id`, luego `id`); el `base` original no visto
   no aparece.
4. `GET /api/habitats/{id}/families` y
   `GET /api/habitats/unassigned-families` devuelven el mismo JSON que antes
   (mismos elementos, campos y orden; `types` sigue siendo la unión completa).
5. El payload de `/farm` no añade campos (`visible`, `oculta`,
   `total_ocultas` ausentes) y mantiene el shape por familia.
6. `inventario.familia` no incluye `evolution_chain_id` de cadenas no visibles;
   `inventario.ev` no cambia.
7. POST de granja (nido/refugio/condiciones/complemento/plantar/cosechar) sobre
   cadena no vista devuelve 404 con
   `{"message":"Familia no disponible en este hábitat"}` y no muta datos.
8. La respuesta 404 es idéntica para cadena inexistente, no asignada al hábitat
   o no vista.
9. `POST /api/farm/harvest-all` no incluye en `nidos`, `total_plantaciones`,
   `bayas` ni `errores` ningún nido de cadena no vista.
10. `POKEDEX_UNLOCK_ALL=true` mantiene el comportamiento previo en `/farm` y
    acciones; `?unlock=1` en local/testing funciona de forma coherente en
    página, modal y acciones.
11. La lectura usa una consulta a `pokedex` y una de tipos, fuera del bucle de
    familias; no hay N+1.
12. Una familia oculta con nido/refugio/plantación existente conserva los datos
    en BD y reaparece con su infraestructura al avistar un miembro.
13. El modal no implementa lógica de avistamiento; solo el texto de vacío y la
    propagación del flag QA en las URLs.
14. Los archivos nuevos usan `declare(strict_types=1);`; PHPStan no añade
    errores y Pint queda limpio.

## 9. Verificación requerida (análisis estático, sin tests)

```bash
git status --short
git diff --stat

vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=1G
php artisan route:list --path=farm
```

Revisiones manuales obligatorias:

- `ObtenerGranja`: `vistos` y `tiposVistos` fuera del `foreach` de familias.
- `PokedexLectura`: consultas por `user_id` explícito, sin depender de `Auth`.
- `FamiliaEnHabitat`: ambas ramas lanzan `RecursoNoExiste` con el mismo mensaje.
- `CosecharTodo`: prefiltro antes de candidatos y sin `asegurar()` dentro del
  `try` por nido.
- `grep -RniE "sighted|visto|pokedex|unlock" resources/views/habitats/`:
  solo usos preexistentes y el helper `urlGranja`.
- `git diff` de `ObtenerFamiliasDisponibles`, `HabitatLecturaRepository`,
  `HabitatsController`, `FamiliaGranja` y `GranjaDetalle`: sin cambios de
  contrato.
- `php artisan route:list --path=farm`: mismas rutas y métodos.

No se ejecuta la suite de tests ni se crean/modifican archivos en `tests/`.

## 10. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Endpoints admin abiertos a cualquier usuario autenticado (fuera de alcance). | Deuda de seguridad documentada; no se implementa gateo en TASK-067. |
| HTML de la página y endpoints de hábitat siguen exponiendo datos no vistos (fuera de alcance). | Documentar; TASK-067 garantiza que el payload de granja no los contiene. |
| Cambio de 422 a 404 para familia no asignada. | Mensaje neutro; el frontend usa `response.ok` y `message`; documentado. |
| Infraestructura de una familia oculta queda inaccesible hasta avistar. | No se borra nada; reaparece intacta. Decisión de producto confirmada. |
| Coste de construir DTOs de familias ocultas antes de filtrar. | Aceptado; sin fuga. Optimización futura fuera de alcance. |
| `?unlock=1` no aplica a `harvest-all`. | QA con `POKEDEX_UNLOCK_ALL=true`; documentado. |
| Sin cobertura de tests del endpoint de granja. | Verificación estática y manual; no se ejecuta la suite. |
| Regresión en el orden de familias. | Se conserva el orden actual; no se reordena. |

## 11. Rollback

- No hay migraciones ni cambios de datos: el rollback es revertir los archivos
  de código y la documentación.
- Los nidos, refugios y plantaciones existentes no se tocan; al revertir el
  filtro, las familias vuelven a mostrarse con su infraestructura.
- No se ejecutarán `git reset --hard`, `git clean` ni `git checkout --` sin
  aprobación explícita.

## 12. Aprobación

Este plan requiere aprobación explícita de la versión **v1** antes de
implementar. Sin aprobación, la tarea permanece en `AWAITING_APPROVAL`.
