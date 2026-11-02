# TASK-005 — Plan v4

- **ID:** TASK-005
- **Versión:** v4
- **Fecha:** 2026-11-02
- **Estado:** v1, v2, v3 y v4 implementados y validados
- **Basado en:** `analysis.md` y `architecture.md`

> **v1 (aprobado e implementado):** corrección del diario (imagen de caramelo de familia, tipo resuelto/oculto, nombre del explorador, PV de derrota y caramelo EV). Backend y vista implementados.
>
> **v2 (aprobado e implementado):** nombres e imágenes en «⚠️ Perdiste objetos» y aviso dentro del diario. La v4 retira la copia del diario. Se detalla en la sección 11.
>
> **v3 (aprobado e implementado):** motivo de la pérdida en el aviso. Se detalla en la sección 12.
>
> **v4 (aprobado e implementado):** el aviso se muestra solo en la tarjeta de resultados; se retira del diario. Se detalla en la sección 13.

## 1. Objetivo

Corregir la presentación del diario de Exploraciones para que:

1. Los caramelos de familia muestren una imagen reconocible (sprite de especie, como la emboscada) y un nombre concreto.
2. Los caramelos de tipo resuelvan `tipo_id` y muestren su tipo/imagen; si no se pueden resolver, no aparezcan.
3. Ninguna narrativa use «Sin reclutado»: se usará el nombre del reclutado o el de su Pokémon.
4. La derrota se explique con los PV de entrada al combate y el estado del rival, en lugar del motivo técnico `explorador_debilitado`.

## 2. Alcance

### Incluido

- `caramelo_familia`: `candy_imagen` con sprite de especie (`/images/iconos_webp/{id}.webp`), fallback a `candy_src` y a `candy_pokemon/0.webp`; nombre resuelto escaneando también la bitácora de exploraciones terminadas.
- `caramelo_tipo`: resolución `tipo_id` → `TipoEnum::label()` + `SlugTipo::de()`; fallback legacy; `oculto=true` y filtrado en `DiarioExploracion`/`activaToView` si no se resuelve.
- `caramelo_ev` de hallazgo: completar `stat_nombre`/`stat_slug` y `candy_src` con los slugs reales (`atksp`, `defsp`, `spd`), eliminando la dependencia de `statName()` en JS.
- `ExploradorVista::nombre()`: fallback al nombre del Pokémon si el reclutado no tiene nombre.
- Combate: persistir `hp_inicial`, `hp_max`, `rival_hp_inicial`, `rival_hp_final`, `rival_hp_max`; el presentador calcula `rondas`, `rival_nombre` y `detalle_derrota`.
- `derrota` raíz: `reason_texto` humano, conservando `reason`.
- Extensión de `ResultadoBatallaExploracion` y `EventoExploracion` para que el round-trip no pierda las claves nuevas.
- Vista `_evento.blade.php`: consumir `candy_imagen`, `detalle_derrota` y `reason_texto`.
- Tests unitarios y feature de los casos nuevos.
- Actualización de `src/Exploraciones/context.md`.

### Fuera de alcance

- `_caramelo.blade.php` y el resumen de recompensas de `ResultadoExploracionVista` (comparten partial con Livewire de combate; deuda documentada).
- El campo `equipo` de `PresentadorExploraciones` (185/275): no lo consume ninguna vista y los tests lo asertan.
- Enriquecer el evento raíz `derrota` con PV: basta el evento de combate previo y el texto humano.
- Cambios de reglas de combate, economía, constantes de balance, migraciones o dependencias.
- Refactor estructural de `ProcesarExploracionHandler` (Fase 2 de TASK-003).

## 3. Arquitectura y contratos

Se aplica `architecture.md`:

- Nuevas claves de combate: `hp_inicial`, `hp_max`, `rival_hp_inicial`, `rival_hp_final`, `rival_hp_max` (float, aditivas).
- `hp_inicial` = PV al inicio del sub-combate decisivo, tras el descanso previo y `triggerBattleStartEffects()`.
- `candy_imagen` para familia; `candy_slug`/`candy_nombre`/`candy_src` resueltos para tipo; `stat_nombre`/`stat_slug`/`candy_src` para EV.
- `oculto` en `EventoExploracionVista`; el filtrado de colección vive en `DiarioExploracion` y `activaToView`.
- `EventoExploracion` extiende constructor, `desdeArray()` y `aArray()` para conservar las claves nuevas.
- Compatibilidad: eventos históricos sin claves nuevas se renderizan igual; no se reescriben bitácoras.

## 4. Archivos y responsables

### Backend (en orden)

| # | Archivo | Cambio |
|---|---|---|
| 1 | `src/Exploraciones/Presentation/EventoExploracionVista.php` | `candy_imagen`, `tipo_id`→tipo, `oculto`, EV, slugs, `rondas`/`rival_nombre`/`detalle_derrota`, `reason_texto` |
| 2 | `src/Exploraciones/Presentation/DiarioExploracion.php` | filtrar `oculto` antes de total/grupos |
| 3 | `src/Exploraciones/Presentation/PresentadorExploraciones.php` | `nombresPokemon()` escanea bitácora de terminadas; filtro en `activaToView` |
| 4 | `src/Exploraciones/Presentation/ExploradorVista.php` | fallback de nombre al Pokémon |
| 5 | `src/Exploraciones/App/CombateExploracion.php` | capturar HP propio/rival |
| 6 | `src/Exploraciones/Domain/ValueObjects/ResultadoBatallaExploracion.php` | 4 props opcionales; `toArray()` no-null |
| 7 | `src/Exploraciones/App/ProcesarExploracionHandler.php` | `resolucionCombate()` persiste las 5 claves |
| 8 | `src/Exploraciones/Domain/ValueObjects/EventoExploracion.php` | constructor/`desdeArray`/`aArray` |

### Frontend (después de backend)

| # | Archivo | Cambio |
|---|---|---|
| 9 | `resources/views/exploraciones/_evento.blade.php` | `candy_imagen`, `detalle_derrota`, `reason_texto` |

### Tests

| # | Archivo | Caso |
|---|---|---|
| 10 | `tests/Unit/Exploraciones/EventoExploracionVistaTest.php` | familia, tipo, EV, combate histórico/nuevo |
| 11 | `tests/Unit/Exploraciones/DiarioExploracionTest.php` | oculto no cuenta; familia resuelta |
| 12 | `tests/Unit/Exploraciones/ExploradorVistaTest.php` | fallback de nombre |
| 13 | `tests/Unit/Exploraciones/CombateExploracionTest.php` | HP inicial/rival |
| 14 | `tests/Feature/Exploraciones/ProcesarExploracionCombateTest.php` | persistencia de las 5 claves |
| 15 | `tests/Feature/ExploracionesPageTest.php` | HTML de familia/tipo/nombre |
| 16 | `tests/Feature/ExploracionesViewTest.php` | sin «Sin reclutado»/«Desconocido» |

### Documentación

| # | Archivo | Cambio |
|---|---|---|
| 17 | `src/Exploraciones/context.md` | contrato del diario, claves nuevas y comportamiento de ocultación |

## 5. Orden de ejecución y dependencias

1. Backend 1→8 (secuencial; el contrato debe existir antes de la vista).
2. Frontend 9.
3. Tests 10→16.
4. Documentación 17.
5. Revisión (`reviewer`), QA (`qa`) y cierre.

No hay archivos compartidos entre `backend` y `frontend`. Los tests pueden escribirse en paralelo con la implementación, pero se ejecutan al final.

## 6. Criterios de aceptación

1. En una exploración terminada con reclutado sin nombre, la tarjeta y el diario muestran el nombre del Pokémon; no aparece «Sin reclutado».
2. Un `caramelo_familia` con `pokemon_id=81` muestra el sprite de Magnemite y «Caramelos de Magnemite»; si falta el sprite, cae a `candy_src` y luego a `0.webp`.
3. Un `caramelo_tipo` con `tipo_id=9` muestra «Caramelo de tipo Acero» y `/images/candy_type/acero.webp`; un tipo irresoluble no aparece en `diario.eventos`, `diario.total` ni `diario.grupos`.
4. Un `hallazgo/caramelo_ev` con `stat=4` muestra «Ataque Especial» y `/images/candy_ev/atksp.webp`; no depende de `statName()`.
5. Un combate perdido muestra `detalle_derrota` con PV de entrada/máximos y PV restantes del rival; los eventos históricos sin claves nuevas se renderizan sin bloque de PV.
6. El evento raíz `derrota` muestra un texto humano; `reason` se conserva como dato.
7. `EventoExploracion::desdeArray()->aArray()` conserva las 5 claves nuevas.
8. La suite relevante pasa; PHPStan y Pint no añaden errores en los archivos tocados.
9. Sin migraciones ni dependencias nuevas.
10. El WIP ajeno no se revierte ni se sobrescribe.

## 7. Pruebas requeridas

```bash
vendor/bin/phpunit --filter 'EventoExploracionVistaTest|DiarioExploracionTest|ExploradorVistaTest|CombateExploracionTest|ProcesarExploracionCombateTest|ExploracionesPageTest|ExploracionesViewTest' --no-coverage
```

Después, suite relevante del módulo:

```bash
vendor/bin/phpunit --filter 'Exploraciones' --no-coverage
```

Calidad:

```bash
vendor/bin/phpstan analyse --no-progress --memory-limit=2G
vendor/bin/pint --dirty --format agent
```

No ejecutar PHPUnit en paralelo sobre `laravel_test`.

## 8. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| `oculto` reduce `total` y rompe tests que cuentan eventos | medio | Fixtures válidos y caso explícito en `DiarioExploracionTest` |
| Cambio de `stat_nombre`/desaparición de `statName()` en EV | medio | Test de stat 4 y revisión de asserts de vista |
| `relationLoaded('pokemon')` no cargado en un futuro llamador | bajo | Documentar el requisito en el PHPDoc de `ExploradorVista`; el controlador ya eager-carga |
| Sprite de especie ausente | bajo | `onerror` cae a `candy_src` y luego a `0.webp` |
| Ambigüedad de `hp_inicial` en emboscada múltiple | bajo | Se define como sub-combate decisivo; `sub_combates` conserva la traza |
| Claves nuevas perdidas por `EventoExploracion::aArray()` | medio | Extender el VO en el mismo cambio |

## 9. Rollback

Revertir los hunks de los 9 archivos de producción y los tests/documentación. No hay migraciones, datos ni dependencias que deshacer. Las claves nuevas son aditivas y los eventos históricos no se reescriben.

## 10. Aprobación

Se solicita aprobación explícita de este plan v1 antes de implementar. Texto esperado: `Aprobar plan v1`.

## 11. v2 — Pérdidas legibles (2026-11-02)

### Objetivo

Que la sección «⚠️ Perdiste objetos» muestre el nombre y la imagen de cada objeto perdido, en lugar de IDs crudos como `familia 147 ×2`, y que el aviso aparezca también dentro del diario de la expedición.

### Alcance

- Enriquecer en presentación los `objetos_perdidos` históricos y nuevos con:
  - `tipo_label`: `Familia`, `EV`, `Tipo`, `Captura`.
  - `label`: nombre legible (p. ej. `Nosepass`, `Magnemite`, `Defensa`, `Roca`).
  - `imagen`: ruta del recurso correspondiente.
  - `id` se conserva para trazabilidad e inventario.
- Resolución:
  - **Familia** (`tipo=familia`, `id=evolution_chain_id`): `CadenasEvolutivas::miembrosDe()` una sola vez para todas las cadenas perdidas; se elige el miembro base por menor `species_id` (mismo criterio que `TransformadorResultadoExploracion::pokemonBaseDeCadena()`); `label = name`, `imagen = /images/candy_pokemon/{baseId}.webp`.
  - **EV** (`tipo=ev`, `id=stat`): `StatEnum::fromId()`; `label`, `imagen = /images/candy_ev/{slug}.webp`.
  - **Tipo** (`tipo=tipo`, `id=slug`): `label` ya persistido; `imagen = /images/candy_type/{slug}.webp`.
  - **Captura** (`tipo=captura`, `id=pokemon_id`): `$nombres[$id]` o `Pokémon #id`; `imagen = /images/iconos_webp/{id}.webp`.
  - Sin resolución: `label = null`, `tipo_label` + `#id` como fallback visual; nunca el ID como único texto.
- Crear `resources/views/exploraciones/_perdidas.blade.php` con el bloque de aviso y la lista (imagen, tipo, nombre, cantidad). Reutilizarlo en:
  - La tarjeta de resultados (`index.blade.php`, bloque actual inline).
  - El contenido del diario (`index.blade.php`, dentro del desplegable), para que el aviso también se vea allí.
- El aviso mantiene `role="alert"`, el icono de advertencia y el texto «⚠️ Perdiste objetos».
- No se modifica el contrato persistido: `eventos['objetos_perdidos']` sigue guardando `tipo`, `id`, `label`, `cantidad_perdida`. La resolución es de presentación y cubre datos históricos.

### Archivos y responsables (v2)

| # | Archivo | Responsable | Cambio |
|---|---|---|---|
| 1 | `src/Exploraciones/Presentation/PresentadorExploraciones.php` | `backend` | `objetosPerdidosVista()`: resuelve `tipo_label`, `label`, `imagen`; `toTerminada()` lo usa |
| 2 | `src/Exploraciones/Presentation/ResultadoExploracionVista.php` | `backend` | `objetosPerdidos()` conserva los campos enriquecidos; elimina el fallback `label => id` |
| 3 | `resources/views/exploraciones/_perdidas.blade.php` | `frontend` | Nuevo partial del aviso y la lista |
| 4 | `resources/views/exploraciones/index.blade.php` | `frontend` | Usa el partial en la tarjeta y lo incluye dentro del diario |
| 5 | `tests/Feature/ExploracionesPageTest.php` | `backend`/`frontend` | Nuevas expectativas y caso con `label=null` |
| 6 | `tests/Feature/ExploracionesViewTest.php` | `frontend` | Render del partial dentro del diario |
| 7 | `tests/Feature/Exploraciones/FinalizarExploracionPerdidasTest.php` | `backend` | Sin cambios de contrato; opcional: assert de que el presentador resuelve las pérdidas |

### Criterios de aceptación v2

1. Una exploración con `objetos_perdidos = [{tipo: familia, id: 147, cantidad_perdida: 2}, {tipo: familia, id: 34, cantidad_perdida: 2}]` muestra «Familia · Nosepass ×2» y «Familia · Magnemite ×2» (o los nombres base reales de las cadenas), con su imagen, y no `familia 147 ×2`.
2. Los objetos de tipo EV, tipo y captura también muestran nombre e imagen.
3. El aviso «⚠️ Perdiste objetos» aparece en la tarjeta de resultados y dentro del diario.
4. Los datos históricos sin `label` se resuelven al renderizar; no se reescribe la BD.
5. La suite de Exploraciones sigue en verde; PHPStan/Pint sin errores nuevos.
6. No hay migraciones ni dependencias nuevas.

### Pruebas v2

```bash
vendor/bin/phpunit --filter 'ExploracionesPageTest|ExploracionesViewTest|FinalizarExploracionPerdidasTest' --no-coverage
```

Después, `vendor/bin/phpunit --filter 'Exploraciones' --no-coverage`, PHPStan y Pint.

### Riesgos v2

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Una consulta extra para resolver cadenas perdidas | bajo | `CadenasEvolutivas::miembrosDe()` agrupa todas las cadenas en una sola query por página |
| Cambian las expectativas de `ExploracionesPageTest` | medio | Actualizar el test con los campos enriquecidos y un caso `label=null` |
| Duplicidad visual del aviso (tarjeta + diario) | bajo | Es intencional: el diario es un desplegable y el usuario puede no abrir la tarjeta |
| Imagen de familia inexistente | bajo | `onerror` oculta la imagen; el nombre sigue visible |

### Aprobación v2

Se solicita aprobación explícita de la v2 antes de implementarla. Texto esperado: `Aprobar plan v2`.

## 12. v3 — Motivo de la pérdida (2026-11-02)

### Objetivo

Mostrar en el bloque «⚠️ Perdiste objetos» el motivo por el que se perdieron: la expedición terminó en derrota al caer debilitado el explorador.

### Alcance

- `PresentadorExploraciones::toTerminada()` calcula `motivo_perdida` a partir del evento raíz `derrota`:
  - `explorador_debilitado` → «Tu explorador cayó debilitado en combate y perdió parte del botín.»
  - Sin motivo → «La expedición terminó en derrota y se perdió parte del botín.»
  - Otro motivo → texto humanizado con el motivo.
- `ResultadoExploracionVista::toView()` expone `motivo_perdida` en `vista`.
- `resources/views/exploraciones/_perdidas.blade.php` acepta `$motivo` opcional y lo muestra como línea explicativa bajo el título.
- `resources/views/exploraciones/index.blade.php` pasa `motivo` en las dos inclusiones (tarjeta y diario).
- No se modifica la regla `ceil(cantidad/2)`, el contrato persistido ni los cálculos.

### Archivos y responsables

| # | Archivo | Responsable |
|---|---|---|
| 1 | `src/Exploraciones/Presentation/PresentadorExploraciones.php` | `backend` |
| 2 | `src/Exploraciones/Presentation/ResultadoExploracionVista.php` | `backend` |
| 3 | `resources/views/exploraciones/_perdidas.blade.php` | `frontend` |
| 4 | `resources/views/exploraciones/index.blade.php` | `frontend` |
| 5 | `tests/Feature/ExploracionesPageTest.php` | `backend`/`frontend` |
| 6 | `tests/Feature/ExploracionesViewTest.php` | `frontend` |

### Criterios de aceptación

1. Una exploración con derrota `explorador_debilitado` y `objetos_perdidos` muestra el motivo en la tarjeta y en el diario.
2. Sin `derrota`/`reason`, muestra el texto genérico de derrota; no inventa una causa.
3. El motivo no duplica el listado ni cambia las cantidades.
4. La suite de Exploraciones sigue en verde; PHPStan/Pint sin errores nuevos.
5. Sin migraciones ni dependencias.

### Pruebas

```bash
vendor/bin/phpunit --filter 'ExploracionesPageTest|ExploracionesViewTest' --no-coverage
vendor/bin/phpunit --filter 'Exploraciones' --no-coverage
```

### Aprobación

Se solicita aprobación explícita de la v3. Texto esperado: `Aprobar plan v3`.

## 13. v4 — Quitar el aviso de pérdidas del diario (2026-11-02)

### Objetivo

Evitar la duplicidad del aviso «⚠️ Perdiste objetos»: se mantiene en la tarjeta de resultados (arriba) y se elimina de dentro del diario.

### Alcance

- `resources/views/exploraciones/index.blade.php`:
  - Eliminar el `@include('exploraciones._perdidas', ...)` del bloque del diario.
  - Volver la condición del diario a `@if(($terminada['diario']['total'] ?? 0) > 0)`.
  - Mantener el include de la tarjeta de resultados.
- `resources/views/exploraciones/_perdidas.blade.php`:
  - Simplificar el `role`: el partial solo se usa en la tarjeta, así que `role="alert"` fijo; eliminar `$rol` si no tiene otros consumidores.
- Tests:
  - `ExploracionesPageTest`: el aviso y el motivo aparecen **una vez** (tarjeta), no dos; el diario no repite el bloque.
  - `ExploracionesViewTest`: el partial sigue renderizando `role="alert"`, imagen, nombre y motivo.
- Documentación: `context.md`/`README.md` indican que el aviso se muestra solo en la tarjeta.

### Criterios de aceptación

1. «⚠️ Perdiste objetos» aparece una sola vez en la página (tarjeta de resultados).
2. El diario no incluye el bloque de pérdidas y sigue mostrando sus eventos.
3. El motivo y los objetos perdidos siguen visibles en la tarjeta.
4. La suite de Exploraciones sigue en verde; PHPStan/Pint sin errores nuevos.
5. Sin migraciones ni dependencias.

### Aprobación

Se solicita aprobación explícita de la v4. Texto esperado: `Aprobar plan v4`.

## 14. v5 — Pérdidas como columna del grid (2026-11-02)

### Objetivo

Sustituir el bloque de aviso «⚠️ Perdiste objetos» por una columna más del grid de resultados, con el mismo lenguaje visual que las recompensas pero en rojo y con las imágenes en gris. El grid pasa a tres columnas: **Derrotados**, **Recompensas perdidas** y **Recompensas obtenidas**.

### Alcance

- `ResultadoExploracionVista`:
  - Añadir `tiene_perdidas` y `tiene_obtenidas` (capturados o caramelos).
  - Adaptar `spans()` a tres columnas: derrotados, perdidas, obtenidas.
  - Mantener `tiene_capturados`/`tiene_recompensas` por compatibilidad.
- `resources/views/exploraciones/_perdidas.blade.php`:
  - Convertirlo en la columna «Recompensas perdidas»: fondo rojo, borde rojo, imágenes `grayscale opacity-50`, badge rojo, motivo y lista.
  - Aceptar `$span` opcional para el `col-span`.
  - Sin `role="alert"` (es una sección estática, no un aviso emergente).
- `resources/views/exploraciones/index.blade.php`:
  - Grid `lg:grid-cols-3`.
  - Columna 1: Derrotados (sin cambios visuales).
  - Columna 2: Recompensas perdidas (`_perdidas`).
  - Columna 3: Recompensas obtenidas: capturados (badge verde) + caramelos; renombrar el título.
  - Eliminar el include full-width de `_perdidas` de encima del grid.
- Tests:
  - `ExploracionesPageTest`: el título «Recompensas perdidas» aparece una vez; «Recompensas obtenidas» una vez; las imágenes perdidas llevan `grayscale`; el motivo sigue visible.
  - `ExploracionesViewTest`: el partial de pérdidas renderiza la columna, el motivo y las imágenes grises; sin pérdidas no renderiza.
- Documentación: `context.md`/`README.md` describen la columna y el grid de tres columnas.

### Criterios de aceptación

1. El grid muestra tres columnas: Derrotados, Recompensas perdidas, Recompensas obtenidas.
2. Las pérdidas usan fondo rojo, badge rojo e imágenes en gris; el motivo se mantiene.
3. Los capturados aparecen dentro de «Recompensas obtenidas» con su badge verde.
4. Los caramelos obtenidos siguen mostrándose con sus imágenes y cantidades.
5. No se duplica el bloque de pérdidas fuera del grid.
6. La suite de Exploraciones sigue en verde; PHPStan/Pint sin errores nuevos.
7. Sin migraciones ni dependencias.

### Archivos y responsables

| # | Archivo | Responsable |
|---|---|---|
| 1 | `src/Exploraciones/Presentation/ResultadoExploracionVista.php` | `backend` |
| 2 | `resources/views/exploraciones/_perdidas.blade.php` | `frontend` |
| 3 | `resources/views/exploraciones/index.blade.php` | `frontend` |
| 4 | `tests/Feature/ExploracionesPageTest.php` | `backend`/`frontend` |
| 5 | `tests/Feature/ExploracionesViewTest.php` | `frontend` |

### Aprobación

Se solicita aprobación explícita de la v5. Texto esperado: `Aprobar plan v5`.
