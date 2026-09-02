# ANÁLISIS FRONTEND — Modal de detalle con evolución y caramelos

## Fecha
2026-09-01

## Tarea
Implementar métodos Alpine faltantes en el modal de detalle de `/equipos`:
`cargarEvoluciones`, `opcionSeleccionada`, `puedeAlimentar`, `alimentarCaramelo`,
y actualizar `evolvePokemon` para enviar `evolved_species_id`.

## Estado actual del archivo
- `resources/views/equipos/index.blade.php` — la vista Blade y el componente Alpine
  ya tienen: el template de evolución completo (selector de destino, barras de exp,
  botón de caramelo, acciones) y los estados (`detailEvoluciones`,
  `detailEvolucionesLoading`, `detailEvolucionesError`, `selectedEvolucionId`).
- **Faltan** 4 métodos Alpine:
  - `cargarEvoluciones(pokemon)` — fetch a `GET /reclutado/{id}/evoluciones`
  - `opcionSeleccionada()` — getter de la opción seleccionada
  - `puedeAlimentar(requisito)` — guarda del botón de caramelo
  - `alimentarCaramelo(requisito)` — POST a `/reclutado/{id}/dar-caramelo`
- `evolvePokemon()` no envía `evolved_species_id` en el body.

## Contrato backend consumido (verificado en `ReclutadoOpcionesEvolucionTest.php`)

| Endpoint | Método | Body | Respuesta |
|---|---|---|---|
| `/reclutado/{id}/evoluciones` | GET | — | `{opciones: [{pokemon_id, nombre, imagen, requisitos: [{tipo, slug, necesario, actual, caramelosDisponibles}], puede_evolucionar}]}` |
| `/reclutado/{id}/dar-caramelo` | POST | `{tipo: string (label español), evolved_species_id?: int}` | `{success, actual, caramelos_disponibles, puede_evolucionar}` |
| `/reclutado/{id}/evolucionar` | POST | `{evolved_species_id?: int}` | `{success, pokemon_id}` |
| `/reclutado/{id}` | DELETE | — | `{success: true}` |

## Estados UI cubiertos

| Estado | Visual |
|---|---|
| Loading | `Cargando opciones de evolución…` |
| Error (fetch falló) | `No hay información de evolución disponible.` |
| Sin evolución (`opciones.length === 0`) | `Este Pokémon no tiene evolución.` |
| Con opciones, sin seleccionar | Selector de destino visible (si >1 opción) |
| Con opciones, seleccionada | Barras de exp + botón de caramelo + botón Evolucionar |
| Barra llena o caramelo 0 | Botón de caramelo deshabilitado |
| Pokémon en exploración | Aviso + todo deshabilitado |
| 422 en dar-caramelo | `alert(data.error)` |
| 422 en evolucionar (sin destino) | `alert('Selecciona a qué pokémon evolucionar')` |

## Riesgos accesibilidad/UX
- Botón de caramelo tiene `aria-label` en el template (ya cubierto).
- Selector de destino tiene `aria-pressed` y `aria-label`.
- Progressbar tiene `role="progressbar"` + `aria-valuenow/min/max`.
- Confirmación en liberar (heredado: `confirm()`).
- Sin `x-ref` — patrón Alpine puro con estado en `equiposApp()`.

## Tests
- Test existente: `tests/Feature/EquiposViewTest.php`
  - `test_equipos_renders_detail_modal_markers` — assertSee existentes OK.
  - Añadir asserts: `cargarEvoluciones`, `opcionSeleccionada`, `puedeAlimentar`,
    `alimentarCaramelo`, `'/reclutado/' + pokemon.id + '/evoluciones'`.
  - No romper asserts existentes: mantener `openDetail`, `showDetailModal`,
    `detail-modal-title`, `nivelDe`, `evolucionar`, `DELETE`, `pokemonEnExploracion`.

## Ajustes de tests
- El nuevo endpoint `GET /reclutado/{id}/evoluciones` aparece en el template como
  string literal. Añadir `assertSee` para él.
- Los métodos `opcionSeleccionada()`, `puedeAlimentar(req)`, `alimentarCaramelo(req)`
  aparecen en el template — añadir `assertSee` para cada uno.

---

## 2026-09-01 — Corrección bug: `${}` en comillas simples en barra de exp (línea 533)

### Causa raíz
Línea 533: `x-text="'${formatExp(req.actual)} / ${formatExp(req.necesario)}'"` usa comillas
simples encerrando interpolación `${}`. JS solo interpola en template literals (backticks).
El usuario veía el texto literal `${formatExp(req.actual)} / ${formatExp(req.necesario)}`.

### Scope de cambios
- `resources/views/equipos/index.blade.php`:
  - Línea 533: cambiar a concatenación normal (coherente con el resto del archivo).
  - Líneas 536-537: añadir helper `porcentajeExpRequisito(req)` defensivo (evita NaN/Infinity
    si `necesario` es 0 o no numérico) y usarlo en `:aria-valuenow` y `:style`.
  - Componente Alpine `equiposApp()`: añadir método `porcentajeExpRequisito(req)` junto a
    `formatExp`.
- `tests/Feature/EquiposViewTest.php`:
  - Línea 123: actualizar `assertSee` de `'req.actual / req.necesario'` (texto del bug)
    a `"formatExp(req.actual) + ' / ' + formatExp(req.necesario)"` (nuevo marcador correcto).

### Busqueda de otros casos del mismo bug
`grep -rn "x-text=\|x-html=\|:style=" resources/views | grep "'\${"` → SÓLO línea 533 en
todo el proyecto. No hay otros casos.

### Tests
- `EquiposViewTest::test_equipos_renders_detail_modal_markers` debe quedar verde.
- Verificación visual: texto "0 / X" (formateado con toLocaleString) en lugar de
  `${formatExp(0)} / ${formatExp(X)}`.