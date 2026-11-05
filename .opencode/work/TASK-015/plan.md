# TASK-015 — Plan v1 — Pokédex, detalle read-only e imágenes de hábitat

- **ID:** TASK-015
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-04
- **Proyecto:** `/home/david/pokemon` — Laravel 12 / PHP 8.4 / Blade + Alpine
- **Base:** `request.md`, `analysis.md`, `architecture.md`
- **Rama:** `task-011` (la rama ya en curso, por indicación del desarrollador)

---

## 1. Objetivo

1. **Imágenes de hábitat:** mantener la tarjeta cuadrada de `/habitats` pero mostrar la imagen completa (`object-contain`), sin recortar los laterales de las imágenes apaisadas.
2. **Listado de la Pokédex:** eliminar el espacio muerto de las tarjetas ajustando densidad del grid, tamaño/centrado del sprite y altura mínima de la información.
3. **Detalle de la Pokédex:** ampliar el modal a un diseño read-only inspirado en `/equipos` (cabecera horizontal, familia, habilidades, movimientos por nivel, stats base, datos de especie y rutas), **sin quality, sin dar EV y sin mostrar EV/IV**.
4. **Navegación:** cada ruta del Pokémon tendrá un enlace operativo “Ir al hábitat” a `/habitats/{id}`.
5. **Cierre:** verificación estática, comprobación manual de criterios y commit scoped en `task-011`.

## 2. Alcance

### Incluido

- `PresentadorDetallePokemon`: `locations[]` aditivo, orden determinista por `habitat_id` y `location` derivado de `locations[0]`.
- `PresentadorDetalleEspecie`: nuevo `paraPokedex()` con habilidades, movimientos **solo `level-up`**, metadatos de especie y derivados `height_m`/`weight_kg`. El método actual `para()` conserva el learnset completo para el endpoint de hábitats.
- `DatagridServiceProvider`: el detalle del datagrid `pokemon` usa `PresentadorDetalleEspecie::paraPokedex()`.
- `resources/views/pokedex/index.blade.php`: grid 3/5/6/8/10/12, sprite 128 centrado por flex, `min-h-[3.5rem]` en la info y rediseño del modal (`max-w-3xl lg:max-w-4xl`, cabecera fija, cuerpo con scroll, 3/5 + 2/5 y movimientos a ancho completo).
- `resources/views/habitats/index.blade.php`: `object-cover` → `object-contain`, `p-2`, hover 1.03.
- Verificación estática, comprobación manual y commit en `task-011`.

### Fuera de alcance

- `/equipos`, `ReclutadoSerializer`, datagrid `reclutado`, `pokemon-modal-quality`, quality/EV/IV, Chart.js, loadout, shiny y combate.
- `resources/views/habitats/show.blade.php`: ya respeta la relación de aspecto; no se toca.
- Rutas nuevas, migraciones, dependencias, CSS compartido o tokens nuevos.
- `per_page`, paginación y scroll infinito.
- Focus trap y `aria-describedby` del modal.
- Tests: no se crean, actualizan ni ejecutan.
- `reviewer` y `security`: **no se lanzan** por indicación explícita del desarrollador.
- Documentación: `docs/architecture.md` y `docs/adr/0002...` contienen cambios preexistentes y afirmaciones desactualizadas sobre este contrato. No se modifican en este commit para no mezclar trabajo ajeno; se registra como deuda en el informe final.

## 3. Decisiones

| ID | Decisión |
|---|---|
| D1 | Grid de Pokédex `grid-cols-3 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 2xl:grid-cols-12`, `gap-2 sm:gap-3`. |
| D2 | Sprite `size="128"` dentro de caja `aspect-square ... flex items-center justify-center`; se elimina `wrapper-class="mx-auto"` (inerte sobre `inline-block`). |
| D3 | `/habitats`: `object-contain p-2 group-hover:scale-[1.03]`; fondo `bg-surface-sunken` sin cambios. |
| D4 | `locations[]` aditivo; `location` es exactamente `locations[0] ?? null`; orden por `habitat_id`. |
| D5 | El detalle de Pokédex usa `PresentadorDetalleEspecie::paraPokedex()` con `moves` filtrados a `level-up` en backend. |
| D6 | Modal `max-w-3xl lg:max-w-4xl`, cabecera fija, cuerpo con scroll, rejilla `md:grid-cols-5` (3/5 + 2/5) y movimientos a ancho completo. |
| D7 | Lista blanca de secciones: cabecera, familia, habilidades, movimientos, stats base, datos de especie y ubicación. Prohibido cualquier quality/EV/IV o acción de escritura. |
| D8 | Se mantiene `location` y todos los campos actuales del contrato; solo se añaden claves. |
| D9 | Sin cambios en `pokedexApp()`: el modal solo pinta datos; los únicos elementos accionables son cerrar y los enlaces de ruta. |
| D10 | Commit scoped en `task-011`; sin push. |

## 4. Contrato

El contrato completo está en `architecture.md` (§ Contrato JSON). Resumen:

- Se mantienen `id`, `name`, `visto`, `atrapado`, `types`, `type_details`, `stats`, `habitat_name`, `habitat_id`, `habitats`, `location`, `family`.
- Se añaden:
  - `locations[]`: mismo shape que `location`, ordenado por `habitat_id`, `[]` si no hay hábitat.
  - `abilities[]`: `id`, `name`, `effect`, `slot`, `source`, ordenado por `slot`.
  - `moves[]`: shape actual, **solo `source = level-up`**, ordenado por nivel y nombre.
  - `species`: claves opcionales actuales más `height_m` y `weight_kg`.
- `location === locations[0]` cuando hay hábitats; `null`/`[]` cuando no hay.
- Autorización intacta: `visto` o `unlock`; sin cambios en la ruta `/datagrid/pokemon/{id}/detalle`.

## 5. Archivos

### Modificar

| Archivo | Responsable | Cambio |
|---|---|---|
| `app/Support/PresentadorDetallePokemon.php` | backend | `locations[]`, orden determinista, `location` derivado. Conservar el diff preexistente de `visto`/`atrapado`. |
| `app/Support/PresentadorDetalleEspecie.php` | backend | `paraPokedex()`, `componer()`, `moves(..., bool)`, `FUENTE_NIVEL`, `height_m`/`weight_kg`. |
| `app/Providers/DatagridServiceProvider.php` | backend | Closure `detail` → `PresentadorDetalleEspecie::paraPokedex()` + `use`. |
| `resources/views/pokedex/index.blade.php` | frontend | Grid, sprite, `min-h`, modal completo, secciones y ubicaciones. |
| `resources/views/habitats/index.blade.php` | frontend | `object-contain`, `p-2`, hover suave. |

### No se modifica

- `resources/views/equipos/index.blade.php`
- `resources/views/habitats/show.blade.php`
- `routes/*`, migraciones, `resources/css/*`, `tests/*`
- `docs/architecture.md` y `docs/adr/*` (deuda documentada)

## 6. Orden de ejecución

1. **B1** `PresentadorDetallePokemon` — `locations[]` y orden determinista.
2. **B2** `PresentadorDetalleEspecie` — `paraPokedex()` y derivados.
3. **B3** `DatagridServiceProvider` — conexión del detalle.
4. **F0** `resources/views/habitats/index.blade.php` — imágenes completas.
5. **F1** `resources/views/pokedex/index.blade.php` — grid, sprite y altura de info.
6. **F2** `resources/views/pokedex/index.blade.php` — modal (después de F1, mismo archivo).
7. Verificación estática y comprobación manual.
8. Commit scoped en `task-011`.

Backend y frontend comparten contrato ya cerrado; se ejecutan en secuencia para evitar depurar dos variables a la vez. F0 puede ir en paralelo con B1–B3, pero no es necesario.

## 7. Criterios de aceptación

1. A 1920 px la tarjeta de Pokédex mide ~145 px y el sprite ocupa ~129 px, sin hueco asimétrico.
2. A 640/768/1024/1280/1536 las columnas son 5/6/8/10/12 y la tarjeta no baja de ~108 px.
3. Las tarjetas sin hábitat tienen la misma altura que las que lo tienen.
4. `/habitats` muestra la imagen completa; las bandas usan `bg-surface-sunken` y el hover no recorta.
5. El detalle devuelve `locations`, `abilities`, `moves` (solo `level-up`) y `species`; `location === locations[0]`.
6. El modal mide 768/896 px, tiene cabecera fija y cuerpo con scroll; el botón cerrar no desaparece.
7. No aparece ningún dato de quality/EV/IV ni acción de escritura.
8. El enlace “Ir al hábitat” navega a `/habitats/{id}`.
9. Con `visto=false` y sin `unlock`, el endpoint responde 404 y el modal muestra “aún no avistado” (sin cambios).
10. `/equipos` y `/habitats/{id}` no cambian.

## 8. Verificación

### Análisis estático

```bash
vendor/bin/phpstan analyse app/Support/PresentadorDetallePokemon.php \
  app/Support/PresentadorDetalleEspecie.php app/Providers/DatagridServiceProvider.php
vendor/bin/pint --dirty --format agent
php artisan view:cache && php artisan view:clear
php artisan route:list --path=datagrid
```

### Comprobaciones dirigidas

- `grep` sobre el bloque del modal: sin `quality`, `calidad`, `pokemon-modal-quality`, `--quality-`, `chart`, `iv`, `effort`, `cp`, `loadout`, `Liberar`.
- Únicos accionables: `@click="closeDetail()"`, backdrop y `<a :href="loc.url">`.
- `detail.stats` sigue usando `stat.value` (base), nunca un cálculo.
- Fallback de ubicación: `detail.locations || (detail.location ? [detail.location] : [])`.

### Comprobación manual

- Revisar a 360, 768, 1024 y 1920 px: grid, tarjetas de hábitat y modal.
- Abrir el detalle de un Pokémon con muchos movimientos (id 151), uno con 5 habilidades (id 645), uno sin hábitat y uno sin movimientos de nivel.
- Verificar que el enlace “Ir al hábitat” navega correctamente.
- Verificar que `/equipos` no ha cambiado.

**No se ejecutan tests.** `tests/` queda fuera de alcance.
**No se lanzan `reviewer` ni `security`** por indicación del desarrollador; se marcan como no aplica.

## 9. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| `PresentadorDetallePokemon.php` tiene cambios preexistentes sin commit | Alto | Guardar `git diff` antes de editar; no usar `git checkout --`; commit scoped. |
| `PresentadorDetalleEspecie.php` está untracked | Medio | Se añadirá al commit; rollback manual de los métodos nuevos. |
| Dos ediciones en `pokedex/index.blade.php` | Medio | F1 y F2 en secuencia. |
| Payload del learnset | Bajo | Solo `level-up` (media 13, máx 26). |
| Cambio de orden de hábitats | Bajo | Corrección de determinismo; hoy 0 Pokémon con >1 hábitat. |
| Modal sin focus trap | Bajo | Fuera de alcance; escape y backdrop funcionan. |
| Sin navegador real | Medio | Comprobación manual en los breakpoints indicados. |
| Deuda documental | Bajo | Se registra en el informe final; no se mezcla con el commit. |

## 10. Rollback

- `resources/views/pokedex/index.blade.php`, `resources/views/habitats/index.blade.php` y `app/Providers/DatagridServiceProvider.php` están limpios en HEAD: `git checkout -- <archivo>` es seguro.
- `PresentadorDetallePokemon.php` y `PresentadorDetalleEspecie.php` requieren reversión manual de los métodos añadidos; no usar `git checkout --`.
- El cambio de backend es aditivo: revertir el closure devuelve el modal al contrato antiguo y el markup nuevo degrada con los fallbacks.

## 11. Commit

- Rama: `task-011`.
- Archivos a stagear: los cinco de la tabla §5.
- No se stagean ni commitean archivos ajenos a TASK-015.
- No se hace `git push`.
- Mensaje propuesto: `feat(pokedex): detalle de especie read-only y rutas de hábitat`.
- Nota: `app/Support/PresentadorDetallePokemon.php` ya tenía cambios previos sin commit (overrides `visto`/`atrapado`), y `PresentadorDetalleEspecie.php` es untracked. Se conservarán y entrarán en el commit como parte del archivo; se documentará en `execution.md`.

## 12. Aprobación

Este plan **no autoriza implementación** hasta que `approval.md` registre una aprobación explícita de la **v1** con el texto literal del desarrollador.
