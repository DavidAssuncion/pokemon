# TASK-067 — Análisis

- **ID:** TASK-067
- **Estado:** COMPLETADO (con verificaciones pendientes)
- **Analista:** `analyst`
- **Fecha:** 2026-11-19

## 1. Resultado y causa raíz (confianza)

**Causa raíz (confianza alta):** el endpoint de la granja construye y devuelve **todas** las familias asignadas al hábitat sin consultar la Pokédex del jugador. La cadena es:

`GranjaController::show()` → `ObtenerGranja::handle($userId, $habitatId)` → `ObtenerFamiliasDisponibles::handle($habitatId)` → `HabitatLecturaRepository::getFamiliesByHabitat($habitatId)`.

`ObtenerFamiliasDisponibles` y `getFamiliesByHabitat` **solo reciben `habitatId`**, no `userId`, y no consultan `pokedex`. Después, `ObtenerGranja` añade cada `DTOFamiliaDisponible` a la respuesta con `base`, `evolutions`, `types`, `nido`, `refugio` y `complementos`. El modal pinta directamente `granja.familias`.

La única lógica de avistamiento existente en hábitats es **por Pokémon individual**, no por familia, y se aplica solo en la página (`HabitatsController::show` → `sightedPokemonIds`) y en la columna derecha (`columna-derecha.blade.php:262-273`). Nunca se aplica al path de la granja.

**Confianza:** alta en la causa raíz y en el flujo; media en la semántica de “familia avistada” porque el producto no define si basta con ver un miembro o si debe verse la base.

## 2. Evidencia (archivo:línea)

- `src/Granjas/App/ObtenerGranja.php:55-110` — `handle(int $userId, int $habitatId)` no usa `$userId` para filtrar familias.
- `src/Granjas/App/ObtenerGranja.php:80-94` — recorre `$this->obtenerFamilias->handle($habitatId)` y añade **todas** las familias a `FamiliasGranjaCollection`.
- `src/Granjas/App/ObtenerGranja.php:148-225` — construye `FamiliaGranja` con `familia`, `nido`, `refugio` y `complementos` sin ninguna comprobación de avistamiento.
- `src/Habitats/App/ObtenerFamiliasDisponibles.php:16-19` — el caso de uso solo delega en el repositorio con `habitatId`; no recibe usuario.
- `src/Habitats/Infra/HabitatLecturaRepository.php:125-156` — `getFamiliesByHabitat` obtiene todos los `evolution_chain_id` de `pokemon_habitat` y construye todas las familias; no consulta `pokedex`.
- `src/Habitats/Infra/HabitatLecturaRepository.php:158-190` — `getUnassignedFamilies` también devuelve todas las familias sin filtrar; es otra superficie.
- `src/Habitats/Infra/Concerns/CadenasFamiliaHabitatsTrait.php:40-67` — `buildAvailableFamilyFromChain` crea `DTOFamiliaDisponible` con `base`, `evolutions` y `types` sin visibilidad.
- `src/Habitats/Infra/Concerns/CadenasFamiliaHabitatsTrait.php:96-110` — `getChainTypes` une los tipos de **todos** los miembros de la cadena, incluidos no avistados.
- `src/Habitats/Infra/Concerns/CadenasFamiliaHabitatsTrait.php:116-148` — `cargarMiembrosFamilia` carga todos los Pokémon de la cadena por `evolution_chain_id`, sin `pokedex`.
- `src/Granjas/Domain/DataTransferObjects/FamiliaGranja.php` — `toArray()` expone `base`, `evolutions`, `types`, `nido`, `refugio`, `complementos` y `ev_total`.
- `src/Granjas/Domain/DataTransferObjects/GranjaDetalle.php` — `toArray()` incluye `familias` e `inventario`; el contrato no tiene campo de visibilidad.
- `src/Granjas/Infra/Controllers/GranjaController.php:31-34` — `show(int $habitat)` no recibe `Request`, por lo que no puede leer el flag QA.
- `src/Granjas/Infra/Controllers/GranjaController.php:99-104` — `respuesta()` llama a `ObtenerGranja` y devuelve `toArray()`.
- `routes/granjas.php` — `GET /api/habitats/{habitat}/farm` y las rutas POST de acciones; todas dentro del grupo `auth` de `routes/web.php:18-46`.
- `resources/views/habitats/show.blade.php:537-545` — el modal hace `fetch('/api/habitats/{id}/farm')` sin parámetro de unlock.
- `resources/views/habitats/partials/modal-granja.blade.php:42-61` — tabs `x-for="(familia, index) in (granja.familias || [])"` con `familia.base?.icon`.
- `resources/views/habitats/partials/modal-granja.blade.php:65-98` — panel con `familia.base?.name`, `familia.types` y `saldoFamilia`.
- `resources/views/habitats/partials/modal-granja.blade.php:102-158` — nido y refugio.
- `resources/views/habitats/partials/modal-granja.blade.php:162-247` — plantaciones.
- `resources/views/habitats/partials/modal-granja.blade.php:250-280` — complementos EV.
- `app/Http/Controllers/HabitatsController.php:50-94` — `show` calcula `$unlockAll` y `$sightedPokemonIds`.
- `app/Http/Controllers/HabitatsController.php:75-80` — `Pokedex::whereIn('pokemon_id', $pokemonIds)->where('visto', true)->pluck('pokemon_id')`; no filtra familias.
- `app/Http/Controllers/HabitatsController.php:103-108` — `/api/habitats/{id}/families` usa el mismo `ObtenerFamiliasDisponibles`, sin filtro.
- `app/Http/Controllers/HabitatsController.php:145-150` — `/api/habitats/unassigned-families` sin filtro.
- `app/Models/Pokedex.php` — tabla `pokedex`; `fillable` `user_id`, `pokemon_id`, `visto`, `atrapado`; `visto`/`atrapado` booleanos; `BelongsToUser`.
- `database/migrations/2026_08_26_000001_create_pokedex_table.php` — clave original `pokemon_id` único.
- `database/migrations/2026_08_29_000005_add_user_id_to_pokedex_table.php` — añade `user_id` no nulo, índice y `unique(user_id, pokemon_id)`; retira el único de `pokemon_id`.
- `app/Models/Concerns/BelongsToUser.php` — global scope por `Auth::id()`; sin usuario autenticado no filtra.
- `app/Jobs/ActualizarPokedexJob.php` — `updateOrCreate(['user_id' => ..., 'pokemon_id' => ...], ['visto' => true, 'atrapado' => ...])`; escribe por `pokemon_id`, no por `species_id`.
- `database/seeders/PokemonSeeder.php:134-142` — omite formas no regionales y calcula la cadena; `:161-168` documenta la invariante `id == species_id` en normales y `species_id = pokemon.id` en regionales; `:216-223` reparenta regionales; `:239` asigna `species_id`; `:283-292` crea cadenas propias por región.
- `src/Granjas/App/FamiliaEnHabitat.php` — `asegurar()` solo comprueba que la cadena esté en `pokemon_habitat`; **no** comprueba avistamiento.
- `src/Granjas/App/MejorarNido.php`, `MejorarRefugio.php`, `MejorarComplemento.php` — llaman a `familiaEnHabitat->asegurar()` y mutan; no validan `pokedex`.
- `src/Granjas/App/PlantarBaya.php` — igual: valida pertenencia al hábitat y nivel de nido, no avistamiento.
- `src/Granjas/App/AsegurarGranja.php` — crea la granja de forma perezosa; no crea nidos/refugios.
- `src/Habitats/Infra/Queries/DetallePokemonFamiliaQuery.php:26-50` — el detalle de especie devuelve datos completos con flags `visto`/`atrapado`, pero no bloquea especies no vistas.
- `src/Habitats/Infra/Controllers/DetallePokemonFamiliaController.php` — endpoint `GET /api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}`; el modal lo usa al seleccionar un Pokémon.
- `resources/views/habitats/partials/columna-derecha.blade.php:262-273` — único filtro visual existente: `unknown.webp` para no avistados; es por Pokémon, no por familia.
- `resources/views/habitats/partials/columna-izquierda.blade.php:113` — el botón “Admin - Gestion” está disponible en la vista del hábitat sin comprobación visible de rol.
- `resources/views/habitats/show.blade.php:1399-1446` — `openGestionModal`, `loadUnassignedFamilies` (`/api/habitats/unassigned-families`) y `loadAssignedFamilies` (`/api/habitats/{id}/families`).
- `resources/views/habitats/show.blade.php:1229-1230` — `isSighted()` usa `unlockAll` o `sightedPokemonIds`.
- `resources/views/habitats/show.blade.php:1474-1514` — `assignFamily` usa `POST /api/habitats/{id}/families`; el endpoint devuelve la familia completa.
- `app/Support/DesbloqueoPokedex.php` — activo por `config('pokedex.unlock_all')` o `?unlock=1` solo en `local`/`testing`.
- `config/pokedex.php` — `unlock_all` por `POKEDEX_UNLOCK_ALL`.
- `tests/Feature/Habitats/FamiliesTest.php:166-232` — test del contrato de `/families` (cadenas 51, 52, 53, 54, tipos, orden). No se localizó un test dedicado a `/api/habitats/{id}/farm`.

## 3. Flujo de datos actual

1. El jugador abre el modal de granjas (`openGranjaModal`) y el frontend hace `GET /api/habitats/{habitat}/farm` (`show.blade.php:537`).
2. La ruta está bajo `auth` (`routes/web.php:18-46`); `GranjaController::show()` llama a `respuesta()`.
3. `respuesta()` ejecuta `ObtenerGranja::handle($userId, $habitatId)`.
4. `ObtenerGranja` llama a `AsegurarGranja::para()` (crea/obtiene la fila de granja), carga nidos, refugios y plantaciones de esa granja, y prepara el contexto de inventario/bonos.
5. `ObtenerGranja` llama a `ObtenerFamiliasDisponibles::handle($habitatId)`, que delega en `HabitatLecturaRepository::getFamiliesByHabitat($habitatId)`:
   - consulta `pokemon_habitat` + `pokemon` para obtener todos los `evolution_chain_id`;
   - por cada cadena carga miembros (`Pokemon`), evoluciones (`PokemonEvolution`) y tipos (`PokemonType`);
   - construye `DTOFamiliaDisponible` con `base`, `evolutions`, `types`.
6. `ObtenerGranja` mapea cada `DTOFamiliaDisponible` a `FamiliaGranja`, añadiendo nido, refugio, complementos y saldo de caramelos de la familia.
7. `GranjaDetalle::toArray()` serializa `familias`, `inventario`, `bayas` y `bonguri`.
8. El modal recorre `granja.familias` y pinta tabs (icono de `base`), nombre de `base`, tipos, nido, refugio, plantaciones y complementos.
9. La Pokédex solo entra en juego en la página (`sightedPokemonIds`) y en el endpoint de detalle de especie (flags `visto`/`atrapado`). **No entra en el path de la granja.**

## 4. Superficies afectadas y no afectadas

### Afectadas / con la misma fuga

| Superficie | Evidencia | Estado |
|---|---|---|
| API `GET /api/habitats/{h}/farm` | `ObtenerGranja.php:80-94` | Devuelve todas las familias |
| Modal de granjas | `modal-granja.blade.php:42-98` | Pinta todas las familias |
| API `GET /api/habitats/{id}/families` (gestión admin “Ya asignados”) | `HabitatsController.php:103-108`; `HabitatLecturaRepository.php:125-156` | Devuelve todas; **comparte caso de uso con la granja** |
| API `GET /api/habitats/unassigned-families` | `HabitatsController.php:145-150`; `HabitatLecturaRepository.php:158-190` | Devuelve todas las no asignadas |
| API `GET /api/habitats/{h}/families/{chain}/pokemon/{pokemon}` | `DetallePokemonFamiliaQuery.php:26-50` | Devuelve detalle completo de especie no vista; solo añade flags |
| API `GET /api/habitats/{h}/pokemon` | `HabitatsController.php:96-101`; `HabitatLecturaRepository.php:59-76` | Devuelve id/nombre de todos los Pokémon del hábitat |
| HTML de la página de hábitat | `HabitatsController.php:50-94`; `columna-derecha.blade.php:262-273` | La columna derecha oculta visualmente, pero el HTML de la página contiene los `id`, `name` e `icon` de todos los niveles dentro de los `<template x-if>`; el código fuente los expone |
| Inventario de la granja | `GranjaDetalle.php` (`inventario`), `InventarioGranja::resumen()` | Incluye claves `familia:{chain}` de familias ocultas, con `chainId` y cantidad |
| Botón “Admin - Gestion” | `columna-izquierda.blade.php:113` | Visible en la página del jugador; no se ve comprobación de rol |
| Acciones POST de granja | `routes/granjas.php`; `FamiliaEnHabitat.php` | Mutan familias ocultas si se conoce el `chain` |

### No afectadas directamente (pero candidatas a “pistas”)

- `resources/views/habitats/partials/columna-derecha.blade.php:262-273`: ya oculta Pokémon individuales no vistos; no filtra familias.
- `resources/views/habitats/partials/columna-izquierda.blade.php`: solo abre el modal; no pinta familias.
- `resources/views/equipos/partials/panel-huevos.blade.php:104-117`: opciones de huevo por familia; pendiente verificar si filtra por avistamiento.
- `resources/views/forja/index.blade.php:123-152`: recetas agrupadas por familia con nombre/icono; pendiente verificar.
- `resources/views/exploraciones/_evento.blade.php:123,169`: eventos `caramelo_familia`; pendiente verificar si exponen nombre de familia.
- `src/Huevos`, `src/Forja`, `src/Exploraciones`: fuera del path de la granja; requieren decisión de alcance.

## 5. Opciones de solución (A/B/…) con tradeoffs

### Opción A — Filtrar en `ObtenerGranja` (recomendada)

Añadir un parámetro `bool $incluirNoAvistadas = false` a `ObtenerGranja::handle()` y filtrar la colección de familias según los `pokemon_id` vistos del usuario.

Flujo propuesto:
1. Obtener todas las familias con `ObtenerFamiliasDisponibles` (sin tocar el caso de uso compartido con admin).
2. Recoger los `id` de `base` y `evolutions` de todas las familias.
3. Hacer **una** consulta a `pokedex` con `user_id = $userId`, `visto = true`, `whereIn('pokemon_id', $ids)`.
4. Quedarse con las familias que tengan al menos un miembro visto (o la regla que se decida; ver pregunta abierta 1).
5. Construir `FamiliaGranja` solo para las visibles.

Ventajas:
- El backend sigue siendo dueño de la regla; la API no devuelve datos ocultos.
- No se toca `ObtenerFamiliasDisponibles`, por lo que admin sigue viendo todo.
- No requiere migración ni cambios de contrato.
- Una sola consulta extra evita N+1.
- Reversible: al ver un miembro, la familia reaparece con su nido/refugio/plantaciones intactos.

Riesgos:
- Se siguen construyendo DTOs de familias ocultas antes de filtrar (coste menor; los datos no salen).
- Si la familia se considera visible por cualquier miembro, `base` puede ser un Pokémon no visto; hay que decidir representante o filtrar miembros (ver abajo).
- No bloquea las acciones POST por `chain`; sigue existiendo bypass de escritura.
- Hay que propagar el flag QA al endpoint.

### Opción B — Filtrar en `HabitatLecturaRepository::getFamiliesByHabitat()` con usuario opcional

Añadir `?int $userId = null` al método y al caso de uso; si viene usuario, filtrar las cadenas con un `exists` sobre `pokedex`/`pokemon`. Admin pasaría `null`.

Ventajas:
- Filtra antes de construir DTOs; una consulta.
- Centraliza la consulta de visibilidad en el repositorio de lectura.

Riesgos:
- Mezcla una regla de jugador en un repositorio genérico usado por admin.
- Cambia la interfaz `HabitatLecturaRepositoryInterface` y obliga a revisar todos los llamadores.
- El problema del representante parcial y del bypass de escritura sigue igual.
- Menos alineado con “el caso de uso de granja decide qué es una familia visible”.

### Opción C — Filtrar en `GranjaController` después de `ObtenerGranja`

Ventajas: cambio localizado.

Riesgos:
- `GranjaDetalle` y `FamiliasGranjaCollection` son readonly/construidos; habría que reconstruir o mutar.
- Duplica la regla en cada acción que devuelve `respuesta()`.
- La lógica de negocio queda en el controlador; no recomendado.

### Opción D — Filtrar en Blade/JS con `sightedPokemonIds`

Ventajas: ningún cambio de backend.

Riesgos:
- La API seguiría devolviendo nombre, icono, tipos y evoluciones de familias no vistas: **no cumple “no dar pistas por API”**.
- El modal no tiene los ids de todos los miembros de forma directa; habría que replicar la regla de familia en JS, violando `frontend-minimal-logic`.
- Rechazada.

### Opción E — No ocultar la familia; ocultar solo miembros no vistos

Ventajas: conserva la familia y el acceso a nido/refugio si ya existe; es coherente con la columna derecha.

Riesgos:
- Sigue mostrando nombre/icono/tipos de la familia, que es exactamente lo que el usuario pide ocultar.
- No cumple la petición literal. Solo válida como complemento de A para familias parcialmente vistas.

### Opción F — Ocultar en lectura + bloquear escritura (A + guard)

Además de A, añadir una comprobación compartida `FamiliaAvistada` en `FamiliaEnHabitat` o en cada caso de uso de acción, con bypass QA.

Ventajas:
- Cierra el bypass de mutar familias ocultas por `chain`.
- Convierte el avistamiento en un requisito real de desbloqueo.

Riesgos:
- Si existe infraestructura de una familia no vista (por QA, datos antiguos o unlock temporal), el jugador pierde acceso a ella hasta verla.
- Hay que definir el error (403/422) y el bypass QA.
- Más superficie de cambio y más riesgo de regresión que A sola.

## 6. Recomendación y arquitectura de la corrección (sin código)

**Recomendación:** Opción A, con estas precisiones:

1. **No filtrar en `ObtenerFamiliasDisponibles` ni en `HabitatLecturaRepository`**, porque los usa la gestión admin. El filtro debe vivir en el caso de uso de jugador `ObtenerGranja`.
2. Introducir un **puerto de lectura de Pokédex** (por ejemplo `PokedexLecturaInterface` en `Src\Granjas\Domain\Repositories` con implementación Eloquent en `Src\Granjas\Infra`) que devuelva una colección tipada de `pokemon_id` vistos para un usuario, o reutilizar un read-model existente. Evita acoplar el caso de uso a Eloquent y respeta DDD.
3. Añadir a `DTOFamiliasDisponibles` un método tipado, por ejemplo `soloConAlgunMiembroVisto(IntCollection $vistos): static`, que devuelva una nueva colección. Nada de arrays asociativos como contrato.
4. En `ObtenerGranja::handle()`, añadir `bool $incluirNoAvistadas = false`:
   - si es `true` (QA), no consultar ni filtrar;
   - si es `false`, obtener los vistos una vez y filtrar antes del bucle de construcción.
5. **Contrato JSON:** mantener exactamente el mismo shape; simplemente habrá menos elementos en `familias`. No añadir `visible`, `oculta` ni conteos: eso daría pistas y obligaría al frontend a decidir.
6. **QA:** `GranjaController::show()` debe recibir `Request` y pasar `DesbloqueoPokedex::activo($request)` a `ObtenerGranja`. Para las acciones POST, o se inyecta `Request` en cada método, o `respuesta()` usa `DesbloqueoPokedex::activo(request())`. Si se quiere que `?unlock=1` funcione también en el modal, el frontend debe añadir `?unlock=1` a las URLs de granja cuando `unlockAll` sea true; es solo transporte de un flag QA, no lógica de negocio. `POKEDEX_UNLOCK_ALL=true` ya funcionaría sin propagación.
7. **Semántica de familia visible:** recomiendo “al menos un miembro visto”, pero con una condición: el representante visual (`base`) debe ser un Pokémon visto. Si el `base` no está visto pero sí una evolución, no se debe pintar el `base` no visto. Dos alternativas:
   - **A-estricta:** familia visible solo si `base` está visto. Simple y sin fugas, pero puede ocultar una familia que el jugador ya conoce por una evolución.
   - **A-representante:** familia visible si cualquier miembro está visto, y el backend calcula un representante visto (o filtra `evolutions` a los vistos). Requiere ampliar el DTO de presentación, pero evita fugas.
   Recomiendo A-representante si el producto acepta tocar el DTO; si no, A-estricta es la opción más segura para “no dar pistas”.
8. **Miembros parcialmente vistos:** si se muestra la familia, los tabs de evolución no vistos no deberían revelar icono/nombre. El modal actual solo pinta tabs de `base` y `evolutions`; si se muestran evoluciones no vistas, hay fuga. Recomiendo que el backend entregue solo los miembros vistos (o un `portada`/`miembros_visibles`) y que el modal pinte esos. Esto también afecta a `types`: `getChainTypes` une tipos de todos los miembros; si se muestran tipos de evoluciones no vistas, hay pista. Opción mínima: calcular tipos solo con miembros vistos para la respuesta de granja.
9. **Nido/refugio/complementos:** son datos de familia, no de Pokémon. Si la familia es visible (algún miembro visto), se pueden mostrar. Si se elige A-estricta y la base no está vista, se ocultan aunque existan; es coherente con “no dar pistas”, pero puede dejar infraestructura inaccesible.
10. **Escritura:** recomiendo, como fase 2 o dentro del mismo plan si el product owner lo aprueba, añadir un guard `FamiliaAvistada` en las acciones. Si no se añade, declarar explícitamente que el filtro es solo de lectura y que un jugador que conozca el `chain` puede seguir mutando. Para TASK-067, la opción A de lectura es el mínimo que corrige el modal; el guard es una decisión de producto/seguridad.

## 7. Impacto en acciones/API/datos existentes/admin/QA

### Acciones

- `FamiliaEnHabitat::asegurar()` solo valida pertenencia al hábitat (`pokemon_habitat` + `pokemon.evolution_chain_id`). **No valida avistamiento.**
- `MejorarNido`, `MejorarRefugio`, `MejorarComplemento`, `AlternarCondicionesInsalubres`, `PlantarBaya` y `CosecharPlantacion` usan esa validación. Un jugador que conozca el `chain` puede seguir mutando una familia oculta llamando directamente a la API.
- Si se aplica solo la Opción A, la respuesta de la acción seguirá ocultando la familia, pero la mutación se habrá ejecutado. Esto puede consumir caramelos y mejorar infraestructura “a ciegas”.
- `CosecharTodo` (`POST /api/farm/harvest-all`) no fue leído; hay que verificar si cosecha nidos de familias ocultas y si devuelve datos de familia. Queda como verificación pendiente.
- Si se decide bloquear, el guard debe aplicarse de forma compartida y con bypass QA; el error debería ser un `ViolacionReglaNegocio` (422) o un 403, consistente con el proyecto.

### API

- `GET /api/habitats/{h}/farm`: cambiará el número/orden de elementos de `familias`. El frontend ya tolera longitud variable (`ajustarFamiliaActiva`, `familias.length === 0`), pero el mensaje de vacío actual (“Este hábitat no tiene familias asignadas todavía”) será engañoso si todas están ocultas.
- `GET /api/habitats/{id}/families` y `/unassigned-families`: no deben cambiar; admin los necesita completos. Pero siguen siendo accesibles a cualquier usuario autenticado.
- `GET /api/habitats/{h}/pokemon`: no cambia, pero sigue devolviendo nombres de todos los Pokémon del hábitat.
- `GET /api/habitats/{h}/families/{chain}/pokemon/{pokemon}`: no cambia; sigue devolviendo detalle de especies no vistas si se conoce la cadena. Es una fuga directa si el objetivo es “no dar pistas”.
- `inventario.familia` en la respuesta de granja: incluye `familia:{chain}` de todas las familias con caramelos, incluidas las ocultas. Es una fuga de metadatos (chain id y cantidad), no de nombre/icono. Se puede filtrar por cadenas visibles o aceptar.

### Datos existentes

- `AsegurarGranja` solo crea la fila de granja; no hay seeders que creen `nests`, `shelters` ni `plantaciones` (grep en `database/seeders` sin resultados para esos modelos). Esos registros se crean al ejecutar acciones.
- `ActualizarPokedexJob` solo pone `visto = true`; nunca lo revierte. Por tanto, si un jugador construyó un nido, lo más probable es que ya hubiera visto algún miembro.
- Aun así, puede existir infraestructura de una familia no vista por:
  - QA con `POKEDEX_UNLOCK_ALL` o `?unlock=1`;
  - datos históricos/migraciones;
  - acciones directas por API.
- Si se oculta la familia, sus nidos/refugios/plantaciones **no se borran**; quedan en la granja y reaparecen cuando el jugador vea un miembro. No hace falta migración ni borrado.
- Decisión pendiente: ¿mostrar un aviso neutro (“tienes mejoras en una familia no avistada”) o silencio total? El aviso es una pista; recomiendo silencio total y conservar datos, salvo que el product owner prefiera un placeholder sin identidad.

### Admin

- `HabitatsController::families()` y `unassignedFamilies()` comparten `ObtenerFamiliasDisponibles`/`ObtenerFamiliasSinHabitat`; **no se deben filtrar**.
- El modal de gestión admin (`modal-gestion-admin.blade.php`) debe seguir viendo todas las familias, incluidos nombres, iconos y tipos.
- El botón “Admin - Gestion” está en la página del jugador (`columna-izquierda.blade.php:113`) y el endpoint solo tiene `auth`, sin policy/rol visible. Si el objetivo es “no dar pistas”, esto es un bypass: cualquier jugador puede abrir el modal admin y ver todo. Hay que decidir si se gatea por rol/policy o si se acepta como herramienta de desarrollo. **Es una pregunta bloqueante para el alcance estricto.**

### QA

- `POKEDEX_UNLOCK_ALL=true`: `DesbloqueoPokedex::activo()` devuelve true en cualquier request, así que el filtro de `ObtenerGranja` debe respetarlo y devolver todas.
- `?unlock=1`: solo funciona en `local`/`testing` y solo si el request lo lleva. La página `/habitats/{id}?unlock=1` lo tiene, pero el `fetch` del modal no lo envía. Opciones:
  - propagar `?unlock=1` desde `show.blade.php` a las URLs de granja cuando `unlockAll` sea true (recomendado, cambio mínimo);
  - persistir el flag en sesión desde `HabitatsController::show` y que `DesbloqueoPokedex` lo consulte (más invasivo);
  - documentar que `?unlock=1` no aplica al modal y usar solo `POKEDEX_UNLOCK_ALL` para QA de granja (menos cómodo).
- Las acciones POST también devuelven `respuesta()`; si no se propaga el flag, en QA la familia desaparecerá de la respuesta tras una acción aunque esté desbloqueada en la página.

## 8. Archivos a modificar por especialista

### Backend

- `src/Granjas/App/ObtenerGranja.php` — añadir filtro y parámetro `incluirNoAvistadas`; consultar vistos una vez; aplicar antes/durante la construcción.
- `src/Granjas/Infra/Controllers/GranjaController.php` — `show(Request $request, int $habitat)`; pasar el flag QA a `respuesta()` y a las acciones; no cambiar rutas.
- `src/Habitats/Presentation/DTOFamiliasDisponibles.php` — método tipado de filtrado (`soloConAlgunMiembroVisto`), si se opta por mantener la lógica en la colección.
- Nuevo (recomendado DDD): `src/Granjas/Domain/Repositories/PokedexLecturaInterface.php` y `src/Granjas/Infra/PokedexLectura.php` (o read-model equivalente) para obtener `pokemon_id` vistos por usuario.
- Si se decide representante/parcial: `src/Habitats/Presentation/DTOFamiliaDisponible.php`, `DTOPokemonFamilia.php` o un DTO de presentación de granja; evaluar no romper el contrato de admin.
- Si se decide bloquear escritura: `src/Granjas/App/FamiliaEnHabitat.php` o nuevo `src/Granjas/App/FamiliaAvistada.php`, y llamadas en `AccionesGranja`/acciones.
- `src/Habitats/App/ObtenerFamiliasDisponibles.php` — **no tocar** salvo que se elija la Opción B; si se toca, admin debe seguir recibiendo todo.

### Frontend

- `resources/views/habitats/partials/modal-granja.blade.php` — cambiar el mensaje de vacío (`líneas 37-39`) para no decir “no tiene familias asignadas” cuando en realidad están ocultas; si se decide representante/parcial, adaptar tabs y panel a los miembros visibles. No añadir filtros de negocio.
- `resources/views/habitats/show.blade.php` — solo si se propaga el flag QA (`?unlock=1`) en las URLs de granja; no añadir lógica de avistamiento.
- `resources/views/habitats/partials/columna-derecha.blade.php` — sin cambios para TASK-067; ya oculta Pokémon individuales.

### Base de datos

- **Ningún cambio.** No hay migración ni seeder. El filtro es de lectura y los datos existentes se conservan.

### Admin

- `app/Http/Controllers/HabitatsController.php` — no filtrar `families()`/`unassignedFamilies()`; si se gatea admin, añadir policy/middleware de rol (decisión aparte).
- `resources/views/habitats/partials/modal-gestion-admin.blade.php` — sin cambios funcionales; sigue mostrando todo.

### Especialistas sugeridos

- **Backend/Domain:** `ObtenerGranja`, controlador de granja, puerto de Pokédex, filtro tipado, guard de acciones si aplica.
- **Frontend:** mensaje de vacío y, si aplica, representación de miembros visibles/QA.
- **Database:** no aplica.
- **Reviewer/Security:** revisar que admin siga completo y que no quede un bypass por endpoints o por acciones POST.

## 9. Criterios de aceptación y verificación estática

Criterios verificables:

1. Para un usuario sin ningún `pokedex.visto = true` de los miembros de la cadena X, `GET /api/habitats/{h}/farm` **no incluye** `evolution_chain_id = X` en `familias`; tampoco aparecen `base`, `evolutions`, `types`, `nido`, `refugio` ni `complementos` de X.
2. Si el usuario tiene al menos un miembro visto de X (o la base, según la decisión), X aparece y el shape de cada familia es idéntico al actual.
3. `GET /api/habitats/{id}/families` y `GET /api/habitats/unassigned-families` siguen devolviendo todas las familias, con los mismos campos y orden; no se ha modificado el caso de uso compartido.
4. `POKEDEX_UNLOCK_ALL=true` hace que `/farm` devuelva todas las familias; el comportamiento de `?unlock=1` en local/testing está definido y es coherente entre página, modal y acciones.
5. No hay lógica de avistamiento nueva en Blade/JS: el modal no decide visibilidad; solo pinta lo que recibe.
6. La respuesta `/farm` no añade campos de visibilidad (`visible`, `oculta`, `total_ocultas`) ni datos de familias ocultas.
7. Se ejecuta **una sola** consulta a `pokedex` por request de granja (o un número acotado), no una por familia; se apoya en el índice `unique(user_id, pokemon_id)`.
8. La familia oculta con nido/refugio/plantación existente no se borra; al marcar `visto` un miembro, reaparece con su infraestructura.
9. El mensaje de vacío del modal no afirma que no hay familias asignadas cuando hay familias ocultas.
10. Si se implementa el guard de escritura: una acción POST sobre una cadena sin miembros vistos devuelve error de negocio (403/422) y no muta; con QA activo, funciona.
11. Si no se implementa el guard: queda documentado en el plan que el filtro es solo de lectura y que el bypass por API es aceptado.

Verificación estática propuesta (sin tests):

- `php artisan route:list --path=farm` para confirmar que las rutas y firmas siguen existiendo.
- `vendor/bin/phpstan analyse` (config del proyecto) sobre `src/Granjas`, `src/Habitats` y `app/Http/Controllers`.
- `vendor/bin/pint --dirty --format agent` tras la implementación (según AGENTS.md), sin ejecutar la suite.
- Revisión por grep: `grep -R "sighted\|unlock\|visto" resources/views/habitats/partials/modal-granja.blade.php` para confirmar que no se añadió lógica de negocio al Blade.
- Revisión de queries: inspeccionar `ObtenerGranja` y confirmar que la consulta a `pokedex` está fuera del bucle de familias.
- Revisión de contrato: comparar `FamiliaGranja::toArray()` antes/después; no debe cambiar.
- Revisión de admin: `HabitatsController::families` y `unassignedFamilies` sin cambios; `ObtenerFamiliasDisponibles` sin filtro.
- Revisión de seguridad: comprobar si el grupo `auth` de `/api/habitats/{id}/families` tiene policy/rol; si no, registrar el riesgo.

## 10. Riesgos y preguntas abiertas

### Riesgos

| Severidad | Riesgo | Impacto | Mitigación |
|---|---|---|---|
| **Alto** | El endpoint admin `/api/habitats/{id}/families` y `/unassigned-families` siguen abiertos a cualquier usuario autenticado, y el botón “Admin - Gestion” está en la página del jugador. | El filtro de la granja es trivialmente evitable; no se cumple “no dar pistas”. | Gatear por rol/policy o declarar la herramienta como solo-dev y aceptar el riesgo. Es decisión bloqueante. |
| **Alto/Medio** | Familia parcialmente vista: `base` no visto pero una evolución sí. El modal pinta `base.name`/`base.icon`, que es un Pokémon no visto. | Fuga de una especie no avistada. | Definir representante visto o aplicar A-estricta (solo si `base` visto). |
| **Medio** | `types` de la familia se calcula con todos los miembros, incluidas evoluciones no vistas. | Pista de tipos de especies no avistadas. | Calcular tipos solo con miembros vistos o aceptar como parte de “familia desbloqueada”. |
| **Medio** | Las acciones POST mutan familias ocultas por `chain`; `FamiliaEnHabitat` no valida avistamiento. | Bypass de progresión y acciones “a ciegas”. | Guard compartido `FamiliaAvistada` con bypass QA, o documentar que el filtro es solo de lectura. |
| **Medio** | Infraestructura existente de una familia no vista queda inaccesible al ocultarla. | Confusión/soporte; caramelos y mejoras “atrapados” hasta ver un miembro. | Conservar datos; decidir placeholder neutro o grandfather. No borrar. |
| **Medio** | `inventario.familia` del payload de granja incluye `chain` y cantidades de familias ocultas. | Fuga de metadatos; un atacante puede inferir cadenas. | Filtrar el inventario por cadenas visibles o aceptar el riesgo. |
| **Medio** | El HTML de la página de hábitat contiene los `id`, `name` e `icon` de todos los Pokémon de los niveles, aunque la columna derecha los oculte visualmente. | Fuga por “ver código fuente”; el objetivo global de no dar pistas no se cumple. | Tratar como tarea aparte: renderizar solo los avistados o mover el ocultamiento al backend. |
| **Bajo** | `?unlock=1` no se propaga al fetch del modal ni a las acciones. | QA inconsistente: la página muestra todo, la granja no. | Propagar el flag en las URLs de granja cuando `unlockAll` sea true, o usar solo `POKEDEX_UNLOCK_ALL`. |
| **Bajo** | N+1 si se consulta `pokedex` dentro del bucle de familias. | Latencia extra por familia. | Una consulta `whereIn` con todos los `pokemon_id`; índice `unique(user_id, pokemon_id)`. |
| **Bajo** | Mensaje de vacío engañoso (“no tiene familias asignadas”) cuando todas están ocultas. | UX confusa. | Cambiar el texto en `modal-granja.blade.php:37-39`. |
| **Bajo** | Posible caché de hábitat (`RecompilarHabitatJsonJob`) con datos de familia. | Datos obsoletos/visibilidad incorrecta si se cachea la granja. | Verificar el job; si cachea familias, invalidar al cambiar `pokedex`. Pendiente de lectura. |

### Preguntas abiertas

1. **¿Qué define “familia desbloqueada”?**
   - Opción 1: cualquier miembro visto.
   - Opción 2: la base (menor `species_id`) vista.
   - Recomendación: cualquier miembro, pero con representante visto; si no se quiere tocar el DTO, Opción 2 es la más segura.
2. **Si la familia está parcialmente vista, ¿se ocultan los miembros no vistos?**
   - Opción 1: mostrar la familia completa (más simple, pero revela nombres/iconos/tipos de evoluciones no vistas).
   - Opción 2: mostrar solo miembros vistos y tipos de vistos (más fiel a “no dar pistas”, requiere cambio de DTO).
   - Recomendación: Opción 2.
3. **¿Deben bloquearse las acciones de escritura sobre familias no vistas?**
   - Opción 1: solo ocultar en lectura; se acepta el bypass por API.
   - Opción 2: guard compartido que rechaza la acción; más coherente con un desbloqueo real.
   - Recomendación: Opción 2 si el avistamiento es un requisito de progresión; si no, documentar Opción 1.
4. **¿Qué hacer con nidos/refugios/plantaciones ya existentes de una familia no vista?**
   - Opción 1: ocultar y conservar; reaparecen al ver un miembro.
   - Opción 2: mostrar un placeholder neutro sin identidad.
   - Opción 3: grandfather (permitir gestionar solo lo ya existente).
   - Recomendación: Opción 1; no borrar datos.
5. **¿La gestión admin es solo para administradores o es una herramienta abierta?**
   - Si es solo admin: hay que añadir policy/rol; si no, el filtro de granja no evita que cualquier jugador vea todo.
   - Recomendación: gatear; es bloqueante para el alcance estricto.
6. **¿El alcance es solo el modal de granjas o todas las superficies con pistas?**
   - Superficies adicionales: HTML de la página, `/api/habitats/{h}/pokemon`, detalle de especie, admin, panel de huevos, forja, eventos de exploración.
   - Recomendación: TASK-067 solo modal/API de granja; abrir tareas separadas para el resto y declararlo en el plan.
7. **¿Se debe filtrar `inventario.familia` en la respuesta de granja?**
   - Recomendación: sí, si los `chain` y cantidades se consideran pista; es bajo esfuerzo.
8. **¿Cómo se quiere soportar el QA `?unlock=1` en el modal?**
   - Opción 1: propagar el flag en las URLs de granja desde `show.blade.php`.
   - Opción 2: usar solo `POKEDEX_UNLOCK_ALL`.
   - Recomendación: Opción 1, cambio mínimo y coherente con la página.

---

### Verificaciones pendientes por el límite de pasos

No pude completar, y deben hacerse antes de aprobar el plan:

- Leer `app/Jobs/RecompilarHabitatJsonJob.php` y comprobar si cachea datos de familias/granja y cómo se invalida.
- Leer `src/Granjas/App/CosecharTodo.php`, `CosecharPlantacion.php` y `AlternarCondicionesInsalubres.php` para confirmar el comportamiento con familias ocultas.
- Inventariar con precisión todos los tests que llaman a `/api/habitats/*/farm` o dependen del número/orden de `familias`; el grep realizado solo encontró `farm_id` en tests de Exploraciones y no un test dedicado al endpoint.
- Confirmar las líneas exactas de `routes/granjas.php`, `DTOFamiliaDisponible.php`, `DTOPokemonFamilia.php` y `FamiliaGranja.php` (se citaron por método/archivo cuando no había numeración).
- Revisar `Huevos`, `Forja` y eventos de exploración para decidir si sus nombres/iconos de familia entran en el alcance de “no dar pistas”.
- Revisar si existe un rol/admin policy en `App\Models\User` o middleware para gatear la gestión admin.
- Confirmar el comportamiento exacto de `?unlock=1` en las acciones POST y si conviene un middleware/sesión en lugar de propagar el query param.

---

## Addendum de verificación (segunda pasada)

### A. Mutaciones de granja: guardas actuales y retorno de datos

**No todas las mutaciones pasan por `FamiliaEnHabitat::asegurar()`**. Pasan cinco:

| Caso de uso | ¿Llama a `FamiliaEnHabitat`? | Evidencia |
|---|---:|---|
| `MejorarNido` | Sí | `src/Granjas/App/MejorarNido.php:25-27` |
| `MejorarRefugio` | Sí | `src/Granjas/App/MejorarRefugio.php:24-26` |
| `MejorarComplemento` | Sí | `src/Granjas/App/MejorarComplemento.php:28-30` |
| `AlternarCondicionesInsalubres` | Sí | `src/Granjas/App/AlternarCondicionesInsalubres.php:24-26` |
| `PlantarBaya` | Sí | `src/Granjas/App/PlantarBaya.php:36-37` |
| `CosecharPlantacion` | **No** | `src/Granjas/App/CosecharPlantacion.php:22-25` solo inyecta `AsegurarGranja` e `InventarioJugador`; no hay `FamiliaEnHabitat` |
| `CosecharTodo` | **No** | `src/Granjas/App/CosecharTodo.php:34-37` solo inyecta `InventarioJugador` |

`FamiliaEnHabitat::asegurar()` solo comprueba pertenencia al hábitat (`pokemon_habitat` + `pokemon.evolution_chain_id`), no avistamiento: `src/Granjas/App/FamiliaEnHabitat.php:15-26`.

`AccionesGranja` solo agrupa cuatro acciones: nido, refugio, condiciones y complemento; no incluye plantar ni cosechar: `src/Granjas/App/AccionesGranja.php:13-39`.

**`CosecharTodo`:**

- Selecciona **todas** las granjas del usuario: `src/Granjas/App/CosecharTodo.php:41-43`.
- Selecciona **todos** los nidos con `level >= 1` de esas granjas: `CosecharTodo.php:55-62`.
- Calcula candidatos por stock de plantaciones: `CosecharTodo.php:72-79`.
- Cosecha cada nido en transacción: `CosecharTodo.php:90-112`.
- **No** comprueba asignación actual de la familia al hábitat ni avistamiento. Por tanto, cosecha también nidos de familias que ya no están asignadas o que el jugador nunca vio.
- No devuelve nombres/iconos de familia en el camino feliz: `ResultadoCosechaGlobal::toArray()` solo devuelve `total_bayas`, `total_plantaciones`, `nidos`, `bayas` y `errores` (`src/Granjas/Domain/DataTransferObjects/ResultadoCosechaGlobal.php:45-53`).
- **Sí puede filtrar datos de familia en errores**: `ErrorCosecha::toArray()` incluye `farm_id`, `habitat_id`, `nido_id`, `evolution_chain_id` y `mensaje` (`src/Granjas/Domain/DataTransferObjects/ErrorCosecha.php:46-54`). Si el guard se implementa lanzando excepción dentro del `try` de `CosecharTodo` (`CosecharTodo.php:90-99`), el error parcial expondría el `evolution_chain_id` de una familia oculta. Por eso el guard de `CosecharTodo` debe ser un **prefiltro silencioso**, no una excepción por nido.

**`CosecharPlantacion`:**

- Solo obtiene/crea la granja y busca el nido por `farm_id` + `evolution_chain_id`: `CosecharPlantacion.php:37-44`.
- No valida que la familia siga asignada al hábitat ni que esté avistada.
- Devuelve un `int` con las unidades cosechadas (`CosecharPlantacion.php:81-86`), pero `GranjaController::cosechar()` ignora ese valor y responde con la granja completa: `src/Granjas/Infra/Controllers/GranjaController.php:87-92`.

**Retorno de datos al cliente:**

- `mejorarNido`, `mejorarRefugio`, `alternarCondiciones`, `mejorarComplemento`, `plantar` y `cosechar` devuelven `respuesta($habitat)`, que serializa `GranjaDetalle` completo, incluidas `familias`, `inventario`, `bayas` y `bonguri`: `GranjaController.php:43-92` y `:99-104`.
- `cosecharTodo` devuelve el agregado de `ResultadoCosechaGlobal` (`GranjaController.php:94-97`), con posible fuga de `evolution_chain_id` en `errores` como se indicó.
- `GET /api/habitats/{habitat}/farm/berries` devuelve solo catálogo de bayas, no familias: `GranjaController.php:36-41`.

**Conclusión A:** el guard único debe cubrir también `CosecharPlantacion` y `CosecharTodo`; el segundo requiere prefiltro por visibilidad para no filtrar `chain` en `errores` ni contar nidos ocultos en `nidos`.

### B. `RecompilarHabitatJsonJob` y caché de hábitat/granja

`app/Jobs/RecompilarHabitatJsonJob.php` **no cachea familias ni granja**. Solo escribe el JSON `habitats.pokemons`:

- Toma todos los Pokémon del hábitat: `RecompilarHabitatJsonJob.php:40`.
- Carga `Pokedex` y construye `pokemon_id`, `nombre`, `visto`, `atrapado`: `:41-53`.
- Persiste en `$habitat->update(['pokemons' => $pokemonData])`: `:55`.
- El campo está declarado como `array` en `App\Models\Habitat`: `app/Models/Habitat.php:19-33`; la migración lo crea como JSON nullable: `database/migrations/2026_08_26_000005_add_pokemons_json_to_habitats.php:15-17`.

Invalidación:

- `ActualizarPokedexJob` actualiza la Pokédex y, para cada hábitat del Pokémon avistado, despacha `RecompilarHabitatJsonJob`: `app/Jobs/ActualizarPokedexJob.php:50-60`.
- No hay caché de granja: `ObtenerGranja` consulta en cada request (`src/Granjas/App/ObtenerGranja.php:55-94`). No se encontró ningún `Cache::` ni lectura de `habitats.pokemons` en `app/`, `src/`, `resources/` o `routes/` con el grep realizado.

**Riesgo adicional detectado:** `RecompilarHabitatJsonJob` usa `Pokedex::whereIn('pokemon_id', ...)` (`:42`) sin `user_id`. El trait `BelongsToUser` solo aplica el scope si `Auth::id()` no es null (`app/Models/Concerns/BelongsToUser.php`), y en un job de cola no hay usuario autenticado. Por tanto, el job mezcla Pokédex de todos los usuarios y `keyBy('pokemon_id')` colapsa filas; `visto` puede quedar a `true` si **cualquier** usuario lo vio. No afecta a la granja (no lee ese JSON), pero es un defecto latente si ese campo se expone.

**Conclusión B:** no hay caché de familias/granja que invalidar. El único artefacto es `habitats.pokemons`, no usado por la granja, con datos globales/no por usuario.

### C. Tests que dependen de `/farm` o del número/orden de `familias`

Inventario realizado sin modificar nada:

- **No existe ningún test que llame literalmente a `/farm`**. `grep -rn -- "/farm" tests` no devuelve resultados.
- La única aparición de `farm` es `farm_id` en la creación de un nido: `tests/Feature/Exploraciones/FinalizarExploracionSendaTest.php:204`. No es el endpoint de granja.
- No hay tests de `ObtenerGranja`, `GranjaController`, `MejorarNido`, `MejorarRefugio`, `MejorarComplemento`, `PlantarBaya`, `CosecharPlantacion`, `CosecharTodo`, `AccionesGranja` ni `FamiliaEnHabitat` (`grep` sin resultados).
- Tests que sí dependen del contrato de `/families` (no de granja):
  - `tests/Feature/Habitats/FamiliesTest.php:166-193`: espera exactamente 1 familia asignada, `base.id = 1`, 2 evoluciones y tipos presentes.
  - `tests/Feature/Habitats/FamiliesTest.php:195-218`: espera 3 familias no asignadas y `base`/`evolutions`/iconos.
  - `tests/Feature/Habitats/FamiliesTest.php:221-233`: espera que `types` sea la **unión de todos los miembros**, no solo los vistos. Si se filtrara `/unassigned-families` por avistamiento, este test fallaría.
  - `tests/Feature/HabitatsControllerTest.php:219-224`: solo comprueba 200 en `/api/habitats/{id}/families`.
  - `tests/Feature/HabitatsControllerTest.php:186-217`: prueba `/api/habitats/{id}/pokemon`, no granja.
- Tests de vista que renderizan `habitats.show` con `sightedPokemonIds => []`:
  - `tests/Feature/HabitatsViewTest.php:24-39` y `tests/Feature/FrontendHabitatModalTest.php:12-30`. No prueban el modal de granja ni su contenido; si se cambia el texto de vacío, no hay aserción sobre él.

**Conclusión C:** riesgo de regresión en tests **bajo** si el filtro se aplica solo en `ObtenerGranja`/endpoint de granja y no se toca `ObtenerFamiliasDisponibles`, `HabitatLecturaRepository` ni `/families`. No hay cobertura de granja; la verificación será estática.

### D. Líneas exactas de rutas, DTOs y colecciones

**Rutas** (`routes/granjas.php`):

- `:9` `GET /api/habitats/{habitat}/farm`
- `:10` `GET /api/habitats/{habitat}/farm/berries`
- `:11-12` `POST /api/habitats/{habitat}/farm/nests/{chain}/upgrade`
- `:13-14` `POST /api/habitats/{habitat}/farm/shelters/{chain}/upgrade`
- `:15-16` `POST /api/habitats/{habitat}/farm/shelters/{chain}/toggle-unhealthy`
- `:17-18` `POST /api/habitats/{habitat}/farm/shelters/{chain}/complements/{stat}/upgrade`
- `:19-20` `POST /api/habitats/{habitat}/farm/nests/{chain}/plantings`
- `:21-22` `POST /api/habitats/{habitat}/farm/nests/{chain}/plantings/{slot}/harvest`
- `:23` `POST /api/farm/harvest-all`

Middleware: `routes/granjas.php` no declara middleware propio; se incluye dentro del grupo `Route::middleware('auth')` de `routes/web.php:18-49`, concretamente en `routes/web.php:46`. No hay policy ni middleware adicional.

**`DTOFamiliasDisponibles`** (`src/Habitats/Presentation/DTOFamiliasDisponibles.php`):

- `:12` extiende `Src\Shared\Domain\Collection`.
- `:14` `$type = DTOFamiliaDisponible::class`.
- `:17-20` `all()`.
- `:22-25` `get(int $index)`.
- `:27-30` `contieneSpecies(int $speciesId)`.
- `:36-42` `ordenadasPorMinSpeciesId(): static`.
- `:47-50` `toArray()`.

Hereda de `Collection` (`src/Shared/Domain/Collection.php`): `add()` `:36-40`, `filter()` `:59-62`, `first()` `:71-84`, `sort()` `:93-99`, `map()` `:112-115`, `pluck()` `:125-128`, `reduce()` `:139-142`, `toList()` `:151-154`, `contains()` `:159-165`, `every()`, `sum()`, `min()`, `max()`. `filter()` devuelve `new static(...)`, por lo que el constructor reindexa; no hay problema de huecos JSON.

**`ColeccionPokemonFamilia`** (`src/Habitats/Presentation/ColeccionPokemonFamilia.php`):

- `:13-15` extiende `Collection`, tipo `DTOPokemonFamilia`.
- `:20-23` `speciesIds(): IntCollection`.
- `:28-36` `primeraEvolucion(): ?DTOPokemonFamilia`.
- `:38-44` `porId(int $id): ?DTOPokemonFamilia`.
- `:51-54` `toArray()`.
- Hereda `filter()`, `map()`, `contains()`, etc. de `Collection`.

**`ColeccionTiposFamilia`** (`src/Habitats/Presentation/ColeccionTiposFamilia.php`):

- `:12-14` extiende `Collection`, tipo `DTOPokemonTipo`.
- `:21-24` `ids(): array`.
- `:31-34` `toArray()`.

**`DTOPokemonFamilia`** (`src/Habitats/Presentation/DTOPokemonFamilia.php`):

- `:18-26` campos: `id`, `name`, `icon`, `level` (`?int`), `speciesId`, `stage`, `parentId`.
- `:35-52` `toArray()`: `id`, `name`, `icon`, `level` solo si no es null, `stage`, `parent_id`, `species_id`.

**`FamiliasGranjaCollection`** (`src/Granjas/Domain/Collections/FamiliasGranjaCollection.php`):

- `:10-12` extiende `Collection`, tipo `FamiliaGranja`.
- `:17-22` solo define `toArray()`.
- **No tiene método propio de filtrado**; hereda `filter()` de `Collection:59-62`. Si se filtra, el constructor reindexa.

**`InventarioGranja`** (`src/Granjas/App/InventarioGranja.php`):

- `:37-71` `resumen(int $userId): InventarioCaramelosGranja`; lee `PlayerInventory` con claves `familia:%` y `ev:%`; construye `CarameloFamilia(chainId, cantidad)` y `CarameloEv`.
- `:132-135` `saldoFamilia(int $userId, int $evolutionChainId): int`.
- `:142-150` `consumirFamilia(...)`.
- `CarameloFamilia::toArray()` devuelve `['evolution_chain_id' => ..., 'cantidad' => ...]`: `src/Granjas/Domain/DataTransferObjects/CarameloFamilia.php:19-25`.
- `InventarioCaramelosGranja::toArray()` devuelve `['familia' => [...], 'ev' => [...]]`: `src/Granjas/Domain/DataTransferObjects/InventarioCaramelosGranja.php:22-28`.
- `GranjaDetalle::toArray()` incluye `'inventario' => $this->inventario->toArray()`: `src/Granjas/Domain/DataTransferObjects/GranjaDetalle.php:31-38`.

**Consecuencia:** aunque se filtren `familias`, la respuesta de granja seguirá incluyendo en `inventario.familia` los `evolution_chain_id` y cantidades de familias ocultas. Si el criterio “no dar pistas” incluye metadatos, hay que proyectar también el inventario a las cadenas visibles o declararlo fuera de alcance.

### E. ¿El modal renderiza `familia.evolutions`? ¿Es código muerto el JS?

**No.** El modal de granja renderiza solo:

- Tabs de familia con `familia.base?.icon`: `resources/views/habitats/partials/modal-granja.blade.php:42-61`.
- Panel con `familia.base?.name` y `familia.types`: `modal-granja.blade.php:65-98`.
- Nido, refugio, plantaciones y complementos: `:102-280`.

No hay ninguna referencia a `familia.evolutions`, `tabActivaFamilia`, `detalleActivoFamilia` ni `familia-tab-<chain>-<pokemon>` en `modal-granja.blade.php` (grep sin resultados).

El JS de `show.blade.php` que gestiona tabs por Pokémon está **muerto**:

- Estado `familiaTabActiva`, `detallePokemonCache`, `detallePokemonLoading`, `detallePokemonError`: `resources/views/habitats/show.blade.php:474-479`.
- Métodos `tabsFamilia` (`:567-599`), `tabActivaFamilia` (`:601-611`), `esTabActivaFamilia` (`:613-617`), `seleccionarPokemonFamilia` (`:619-665`), `navegarTabFamilia` (`:668-691`), `detalleDe` (`:730-736`), `cargandoDetalle` (`:738-740`), `errorDetalle` (`:742-744`), `detalleActivoFamilia` (`:746-750`).
- No hay llamadas desde markup: el único `familia-tab-...` del modal es `'familia-tab-' + familia.evolution_chain_id` (`modal-granja.blade.php:46`), no `...-<pokemon>`.

`familia.evolutions` sí se usa en el **modal admin**, no en el de granja:

- `resources/views/habitats/show.blade.php:1422`, `:1441`, `:1460`, `:1546`, `:1583` (funciones de gestión admin).
- `resources/views/habitats/partials/modal-gestion-admin.blade.php` pinta evoluciones por nivel.

**Implicación para el plan:** como el modal de granja solo pinta `base`, no basta con filtrar `evolutions`; si el `base` no fue visto pero una evolución sí, seguiría mostrándose el `base` no visto. El backend debe **proyectar un representante visto en `base`** (el miembro visto de menor `stage`, luego `species_id`, luego `id`) y dejar en `evolutions` solo los demás miembros vistos. Además, `types` debe recalcularse solo con los miembros vistos, porque el `types` actual es la unión de toda la familia (`CadenasFamiliaHabitatsTrait.php:96-110`) y no guarda el mapeo tipo→miembro. Esa proyección debe ser solo para la respuesta de granja; el DTO compartido de admin no se toca.

### F. Rol/admin policy/middleware para la gestión admin

No existe ningún rol, policy ni middleware de administración:

- `App\Models\User` solo tiene `name`, `email`, `password`, `experiencia`; no hay `is_admin`, `role` ni casts relacionados: `app/Models/User.php:24-29` y `:46-53`.
- No existe `app/Policies` (no se encontró el directorio).
- El único middleware propio es `SoloEnEntornoDePruebas`: `app/Http/Middleware/SoloEnEntornoDePruebas.php:16-23`; se registra como alias `solo-local` en `bootstrap/app.php:15-19`.
- Las rutas de gestión de familias están solo bajo `auth`: `routes/habitats.php:24-31`; se incluyen en `routes/web.php:18-49`.
- El botón “Admin - Gestion” se renderiza sin condición de rol: `resources/views/habitats/partials/columna-izquierda.blade.php:112-123`.
- Los endpoints admin de gimnasios tienen el mismo patrón abierto bajo `auth`: `routes/gimnasios.php:13-18`.

**Riesgo registrado (fuera de alcance):** cualquier jugador autenticado puede abrir el modal admin y consultar todas las familias, incluidas las no avistadas, vía `/api/habitats/{id}/families` y `/api/habitats/unassigned-families`. El filtro de la granja no evita esa vía. Si el producto exige “no dar pistas” de forma estricta, esto requiere una tarea aparte de autorización; no se implementará en TASK-067.

### G. Punto único de guard para acciones y excepción/HTTP

**Punto de guard propuesto:**

El choke point natural es `FamiliaEnHabitat`, pero hoy no es universal. Propuesta sin código:

1. Convertir `FamiliaEnHabitat::asegurar()` en la guarda única de **asignación + avistamiento**:
   - nueva firma `asegurar(int $userId, int $habitatId, int $evolutionChainId, bool $incluirNoAvistadas = false): void`;
   - primero mantiene la comprobación actual de `pokemon_habitat`;
   - después comprueba que exista al menos un `pokemon` de esa `evolution_chain_id` con `pokedex.visto = true` para `user_id`;
   - si no, lanza la excepción de dominio.
2. Actualizar los cinco llamadores actuales: `MejorarNido`, `MejorarRefugio`, `MejorarComplemento`, `AlternarCondicionesInsalubres`, `PlantarBaya`.
3. Añadir la guarda a `CosecharPlantacion` (hoy no la tiene), inyectando `FamiliaEnHabitat` y llamándola antes de la transacción.
4. En `CosecharTodo`, **no** llamar a la guarda dentro del `try` por nido. Hacer un prefiltro por lote:
   - obtener los `evolution_chain_id` de todos los nidos candidatos;
   - consultar una sola vez las cadenas con algún miembro visto para ese usuario;
   - descartar silenciosamente los nidos de cadenas no visibles antes de construir `$candidatos`.
   Así se evita que `ErrorCosecha` exponga `evolution_chain_id` y que el contador `nidos` incluya nidos ocultos.
5. Alternativa más estricta: extraer un servicio `FamiliasAvistadas`/`PoliticaFamiliaAvistada` y hacer que `FamiliaEnHabitat` lo componga. Así la consulta de visibilidad es reutilizable por lectura y escritura, y `CosecharTodo` puede usar el método batch.

**Excepción y estado HTTP:**

El proyecto ya tiene un patrón claro:

- `ViolacionReglaNegocio` extiende `DominioExcepcion` y fija código 422: `src/Shared/Domain/Exceptions/ViolacionReglaNegocio.php:7-12`.
- `RecursoNoExiste` fija 404: `src/Shared/Domain/Exceptions/RecursoNoExiste.php:7-12`.
- `bootstrap/app.php:20-29` renderiza: `ViolacionReglaNegocio` → JSON `{"message": ...}`, 422 (`:24-26`); `RecursoNoExiste` → 404 (`:21-23`); `DominioExcepcion` genérica → 400 (`:27-29`).
- `GranjaController` no captura excepciones; las deja subir al handler (`GranjaController.php:43-92`).

Recomendación:

- Si se prioriza **no confirmar la existencia** de la familia oculta, usar una excepción genérica tipo `FamiliaNoDisponible` que extienda `RecursoNoExiste` → **404** con mensaje neutro (“Familia no disponible en este hábitat”). Aplicarla también al caso “no asignada” para que un `chain` oculto y un `chain` inexistente sean indistinguibles.
- Si se prioriza consistencia con el contrato actual de granja, usar `ViolacionReglaNegocio` → **422** con el mismo mensaje neutro. Es el patrón ya usado por `FamiliaEnHabitat` para “no asignada” (`FamiliaEnHabitat.php:23-25`), pero un 422 distinto del 404 de “no existe” puede dar una pista.
- No hace falta tocar `bootstrap/app.php` si se reutiliza una de las dos excepciones existentes. Si se crea una nueva, debe extender una de ellas para heredar el render.

QA: la guarda debe aceptar `bool $incluirNoAvistadas` y el controlador pasarlo desde `DesbloqueoPokedex::activo($request)`; `POKEDEX_UNLOCK_ALL` ya funciona globalmente. Para `?unlock=1` en local/testing, hay que propagar el flag a las URLs de granja o usar solo la variable de entorno.

### Impacto en el plan

**Backend / dominio/aplicación:**

- `src/Granjas/App/ObtenerGranja.php`: filtrar familias por “al menos un miembro visto”; proyectar `base` = representante visto; dejar `evolutions` solo con miembros vistos; recalcular `types` solo con miembros vistos; decidir si se filtra `inventario.familia` a cadenas visibles.
- Nuevo puerto/lectura: `PokedexLecturaInterface` + implementación Eloquent (o `FamiliasAvistadas`) para obtener `pokemon_id` vistos y cadenas visibles por lote.
- `src/Granjas/App/FamiliaEnHabitat.php`: nueva firma con `userId` y comprobación de avistamiento.
- `src/Granjas/App/MejorarNido.php`, `MejorarRefugio.php`, `MejorarComplemento.php`, `AlternarCondicionesInsalubres.php`, `PlantarBaya.php`: actualizar llamadas a la guarda.
- `src/Granjas/App/CosecharPlantacion.php`: inyectar y llamar a la guarda; hoy no valida ni asignación ni avistamiento.
- `src/Granjas/App/CosecharTodo.php`: prefiltro batch de nidos por cadenas visibles; evitar excepción por nido y fuga en `ErrorCosecha`.
- `src/Granjas/Infra/Controllers/GranjaController.php`: recibir `Request` para el flag QA y pasarlo a lectura/acciones; el resto de firmas se mantiene.
- Posible DTO/proyección nueva para la familia visible de granja, sin tocar `DTOFamiliaDisponible` compartido con admin. Si se reutiliza `DTOFamiliaDisponible`, construir una instancia nueva solo para granja.

**Frontend:**

- `resources/views/habitats/partials/modal-granja.blade.php`: cambiar el mensaje de vacío (`:37-39`) para no decir “no tiene familias asignadas” cuando están ocultas. No hace falta renderizar `evolutions`; el modal ya solo usa `base`.
- `resources/views/habitats/show.blade.php`: opcional, eliminar el JS muerto de tabs por Pokémon (`:474-479`, `:567-750`) o dejarlo; no interviene en la granja. Si se soporta `?unlock=1`, propagar el flag a las URLs de granja.

**Base de datos:**

- Ningún cambio. No hay migración ni seeder. Los nidos/refugios/plantaciones existentes se conservan; al avistar un miembro, la familia reaparece con su infraestructura.

**Admin:**

- Sin cambios. `ObtenerFamiliasDisponibles`, `HabitatLecturaRepository::getFamiliesByHabitat` y `/api/habitats/{id}/families` deben seguir devolviendo todo. No aplicar el filtro en el caso de uso compartido.

**Decisiones que el plan debe fijar:**

1. **Representante de familia:** `base` será el miembro visto de menor `stage`, luego `species_id`, luego `id`; `evolutions` solo contendrá los demás vistos.
2. **Tipos:** recalcular `types` solo con los `pokemon_id` vistos; el `types` actual es unión de toda la familia y no se puede filtrar sin una consulta adicional o un DTO enriquecido.
3. **Inventario:** decidir si `inventario.familia` se filtra a cadenas visibles. Si no, la API sigue exponiendo `evolution_chain_id` y cantidades de familias ocultas.
4. **Guarda de escritura:** incluir `CosecharPlantacion` y `CosecharTodo`; en `CosecharTodo` usar prefiltro silencioso para no filtrar `chain` en `ErrorCosecha`.
5. **Estado HTTP:** 404 neutro (`RecursoNoExiste`) si se quiere no confirmar existencia; 422 (`ViolacionReglaNegocio`) si se prioriza el patrón actual. No mezclar estados que distingan “oculta” de “no asignada”.
6. **QA:** `POKEDEX_UNLOCK_ALL` funciona; `?unlock=1` requiere propagación explícita a las URLs de granja o queda limitado a la página.
7. **Riesgo admin fuera de alcance:** no hay rol/policy; el botón y los endpoints siguen abiertos a cualquier usuario autenticado. Registrar como deuda de seguridad separada.
8. **Tests:** no hay cobertura del endpoint de granja; no se modifica ni ejecuta la suite. Los tests de `/families` (`FamiliesTest`) no deben verse afectados si el filtro se limita a `ObtenerGranja`; el test de unión de tipos (`FamiliesTest:221-233`) seguirá pasando porque admin conserva la unión completa.
