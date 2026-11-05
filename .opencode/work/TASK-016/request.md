# TASK-016 — Solicitud

## Petición literal

> He reducido el numero de columnas y quitado el favorito, no funcionaba, marca deprecated el endpoint y vamos a coregir los div del grid, muestran mucho espacio vacio arriba y abajo todos como norma general, debemos adaptarlo a que sea mas rectangular

## Interpretación

1. **Favoritos de hábitat:** el desarrollador ya ha reducido las columnas (`xl` de 8 a 6, `gap` de 6 a 3) y ha quitado el botón de favorito de las tarjetas de `/habitats`. El endpoint `POST /api/habitats/{habitat}/toggle-favorito` es un no-op legacy y debe quedar marcado como **deprecated**.
2. **Limpieza del favorito:** quedan restos en la vista que ya no tienen sentido porque el controlador ya no envía `favoritosIds`:
   - `:class="{ 'ring-2 ring-warning': isFavorito(...) }"` en la tarjeta.
   - `favoritosIds`, `togglingHabitat`, `isFavorito()` y `toggleFavoritoHabitat()` en `habitatsApp()`.
   - Comentario vacío `<!-- Favorite toggle button (corner) -->`.
3. **Grid de hábitats:** las tarjetas usan un contenedor `aspect-square`; con imágenes apaisadas y `object-contain` quedan bandas vacías arriba y abajo. Hay que hacer el marco de imagen **más rectangular** (3:2) y asegurar que la imagen llene el marco con `w-full h-full object-contain`, conservando la imagen completa.
4. **Columnas:** se mantienen las que ha elegido el desarrollador (`grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3`).

## Contexto

- Proyecto `/home/david/pokemon`, rama `task-011`.
- TASK-015 se completó y commiteó (`0ec0f7e`). Después, el desarrollador ha modificado localmente `resources/views/habitats/index.blade.php`:
  - `xl:grid-cols-8` → `xl:grid-cols-6`; `gap-6` → `gap-3`.
  - Eliminado el `<button>` de favorito.
  - La imagen perdió `w-full h-full` (queda `class=" object-contain p-2 ..."`).
- El endpoint `HabitatsController::toggleFavorito()` devuelve un JSON fijo `{favorito: false, count: 0}` y el comentario de ruta ya lo describe como “Legacy no-op”.
- `HabitatsController::index()` ya no pasa `favoritosIds` a la vista; el estado Alpine siempre queda vacío.
- Imágenes reales de `resources/habitats_img/`: mayoría apaisadas ~1,43–1,54:1; algunas 2:1 y unas pocas verticales.

## Restricciones

- No revertir los cambios locales del desarrollador; se conservan las 6 columnas y el `gap-3`.
- No tocar `/habitats/{id}`, `/pokedex`, `/equipos`, rutas, migraciones ni CSS compartido.
- No eliminar la ruta del endpoint: solo marcarla como deprecated y limpiar el consumo muerto del frontend.
- Sin tests; `tests/` fuera de alcance.
- Commit scoped en `task-011`, sin push.
- Continuidad de TASK-015: no se lanzan `reviewer` ni `security` salvo indicación contraria del desarrollador.

## Criterio de cierre

- Endpoint `toggleFavorito` marcado `@deprecated` con motivo y alternativa.
- Sin restos de favoritos en `/habitats` (ni ring, ni estado, ni fetch).
- Tarjetas de hábitat con marco de imagen 3:2, imagen completa y sin bandas verticales grandes.
- Columnas y gap del desarrollador intactos.
- Verificación estática y commit en `task-011`.
