# TASK-014 — Ejecución

## Estado

`COMPLETED`

## Preflight

- Rama: `task-011`.
- Working tree con TASK-011/012/013 sin commitear; `.githooks/pre-commit` y `MVP.txt` preexistentes fuera de alcance.
- `public/images/items`: 16 PNG, 112 KB.
- `cwebp` disponible.
- No hay Font Awesome cargado.
- `tests/` sin cambios.

## Historial

- 2026-11-04: tarea creada a partir de la petición de cosecha global, bonus de plantaciones e iconos de items.

- 2026-11-04: `analyst` y `architect` entregan análisis/diseño. Plan v1 redactado; incluye cosecha global, Font Awesome local, iconos WebP y bonguris del Recipiente con origen.

- 2026-11-04: plan v1 aprobado con «Aprobar plan v1 (TASK-014)». Inicia implementación.

## Implementación

- Backend cosecha global: `CosecharTodo`, DTOs/colecciones, `POST /api/farm/harvest-all`.
- Menú: botón `fa-solid fa-seedling`, estados de carga/éxito/error, evento `inventario:actualizado`, oculto en combate.
- Granja: listener de refresco silencioso; nota de bonus; tabs del desarrollador intactos.
- Forja: lista de 7 bonguris del Recipiente con existencias y origen; listener de refresco.
- Assets: 16 PNG convertidos a WebP, originales movidos a `storage/app/imports/items-png`; enums actualizados; `Recipiente` conserva el nombre y usa el icono de Éter.
- Font Awesome 6.7.2 local e importado; build con fuentes.

## Verificación estática

- `php -l`: sin errores.
- `phpstan`: 34 errores preexistentes, sin nuevos.
- `deptrac`: 113 preexistentes, sin nuevas.
- `pint --test --dirty`: pass.
- `view:cache`/`view:clear`: OK.
- `npm run build`: OK; fuentes FA emitidas.
- `POST /api/farm/harvest-all` registrada.
- 16 WebP, 0 PNG en `public/images/items`, 16 PNG originales en `storage/app/imports/items-png`.
- 7 variantes de Recipiente con origen; `Recipiente` + icono `recipiente.webp`.
- `x-data` del menú validado con `node --check`.
- `tests/`: sin cambios.

- 2026-11-04: revisión y seguridad omitidas por indicación explícita del desarrollador. Verificación estática y manual completada. Informe final en `final-report.md`.

## Ajuste posterior del toast

- El toast verde de cosecha muestra ahora el icono de cada baya junto al nombre y la cantidad (`<img :src="item.icono">`).
- `view:cache`/`view:clear` y `npm run build` OK tras el ajuste.

## Ajustes de formato

- Toast de cosecha compactado: total en el título y desglose en 2 columnas con icono/nombre/cantidad.
- Forja: 4 categorías en 4 columnas (xl), recetas apiladas; categorías colapsadas por defecto.
- `view:cache`/`view:clear` y `npm run build` OK tras los ajustes.

## Ajuste de recetas en forja

- Recetas colapsadas por defecto; 2 columnas por sección; expansión a ancho completo.
- Tarjeta gris + badge “Faltan materiales” cuando la variante seleccionada no es fabricable.
- `view:cache`/`view:clear` y `npm run build` OK.
