# TASK-016 — Plan v1 — Grid de hábitats y deprecación de favoritos

- **ID:** TASK-016
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-04
- **Proyecto:** `/home/david/pokemon` — Laravel 12 / Blade + Alpine
- **Base:** `request.md`, `analysis.md`
- **Rama:** `task-011`

---

## 1. Objetivo

1. **Deprecar** el endpoint legacy `POST /api/habitats/{habitat}/toggle-favorito` y su método `HabitatsController::toggleFavorito()` sin eliminarlos.
2. **Limpiar** el consumo muerto de favoritos en `/habitats`: ring, `favoritosIds`, `togglingHabitat`, `isFavorito()` y `toggleFavoritoHabitat()`.
3. **Corregir** las tarjetas del grid de hábitats: marco de imagen `aspect-[3/2]` y `w-full h-full object-contain p-2`, conservando las 6 columnas y el `gap-3` del desarrollador.

## 2. Alcance

### Incluido

- `resources/views/habitats/index.blade.php`:
  - quitar `relative` del wrapper y el comentario vacío del favorito;
  - quitar `:class` del ring de favorito;
  - `aspect-square` → `aspect-[3/2]`;
  - imagen con `w-full h-full object-contain p-2`;
  - reducir `habitatsApp()` a `activeTab`.
- `app/Http/Controllers/HabitatsController.php`:
  - `@deprecated` en el docblock de `toggleFavorito()`, con motivo y alternativa (`POST /api/reclutados/{reclutado}/toggle-favorito` con `habitat_id`).
- Verificación estática, build y commit scoped en `task-011`.

### Fuera de alcance

- Eliminar la ruta o el método legacy.
- Tocar `routes/habitats.php` (tiene cambios preexistentes sin commit).
- `/habitats/{id}`, `/pokedex`, `/equipos`, CSS compartido, migraciones.
- Cambiar el número de columnas o el `gap` elegidos por el desarrollador.
- Tests: no se crean, actualizan ni ejecutan.
- `reviewer` y `security`: no se lanzan por continuidad de la instrucción del desarrollador.

## 3. Decisiones

| ID | Decisión |
|---|---|
| D1 | Marco de imagen `aspect-[3/2]`: con las imágenes reales (mediana 1,46) elimina las bandas verticales en 48/56 y deja relleno medio ~91 %. |
| D2 | `object-contain` con `w-full h-full` y `p-2`: imagen completa; `object-cover` recortaría hasta 25 % y se descarta. |
| D3 | Se conservan `xl:grid-cols-6` y `gap-3` del desarrollador. |
| D4 | Se elimina todo el estado/fetch muerto de favoritos en la vista; `activeTab` se mantiene. |
| D5 | El endpoint se mantiene; solo se marca `@deprecated` en el controlador. No se toca `routes/habitats.php` para evitar mezclar hunks. |
| D6 | Commit scoped en `task-011`; sin push. |

## 4. Archivos

| Archivo | Responsable | Cambio |
|---|---|---|
| `resources/views/habitats/index.blade.php` | frontend | Limpieza de favoritos + marco 3:2 + imagen completa. |
| `app/Http/Controllers/HabitatsController.php` | backend | Docblock `@deprecated` en `toggleFavorito()`. |

No se modifica ningún otro archivo.

## 5. Orden de ejecución

1. Backend: docblock `@deprecated`.
2. Frontend: limpieza de favoritos y clases del grid.
3. `php -l`, `php artisan view:cache` / `view:clear`, `npm run build`, greps de verificación.
4. Commit scoped en `task-011`.

## 6. Criterios de aceptación

1. `HabitatsController::toggleFavorito()` tiene `@deprecated`, motivo y alternativa.
2. La ruta `POST /api/habitats/{habitat}/toggle-favorito` sigue existiendo.
3. `/habitats` no contiene `favoritosIds`, `togglingHabitat`, `isFavorito`, `toggleFavoritoHabitat` ni `ring-warning` en las tarjetas.
4. El grid conserva `xl:grid-cols-6` y `gap-3`.
5. El contenedor de imagen usa `aspect-[3/2]` y la imagen `w-full h-full object-contain p-2`.
6. La imagen se ve completa y sin bandas verticales grandes en la mayoría de hábitats.
7. `/habitats/{id}`, `/pokedex` y `/equipos` no cambian.

## 7. Verificación

```bash
php -l app/Http/Controllers/HabitatsController.php
php artisan view:cache && php artisan view:clear
npm run build
php artisan route:list --path=api/habitats --no-ansi
```

- `grep` en `resources/views/habitats/index.blade.php`: 0 coincidencias de `favoritosIds`, `togglingHabitat`, `isFavorito`, `toggleFavoritoHabitat`, `ring-warning`.
- `grep` de `aspect-[3/2]`, `w-full h-full object-contain p-2`, `xl:grid-cols-6`, `gap-3`.
- `git diff --check`.
- Sin tests. Sin `reviewer` ni `security`.

## 8. Riesgos y rollback

| Riesgo | Nivel | Mitigación |
|---|---|---|
| `object-contain` sin `h-full` recorta imágenes | Medio | Incluir `w-full h-full` explícitos. |
| Las 3 imágenes ~2:1 conservan banda vertical | Bajo | Aceptado; `object-cover` recortaría más. |
| Tocar `routes/habitats.php` contamina el commit | Bajo | No se toca. |
| Vista compilada obsoleta | Bajo | `php artisan view:clear`. |
| Pérdida de `activeTab` al limpiar JS | Bajo | Conservar `activeTab: 0`. |

**Rollback:** el controlador está limpio en HEAD; el diff de la vista incluye los cambios del desarrollador y los de esta tarea. Para revertir solo lo nuevo, aplicar parche inverso sobre las líneas modificadas; no usar `git checkout --` sobre la vista sin perder los cambios del desarrollador.

## 9. Commit

- Rama: `task-011`.
- Archivos: `resources/views/habitats/index.blade.php` y `app/Http/Controllers/HabitatsController.php`.
- No se stagea `routes/habitats.php` ni ningún otro archivo.
- Mensaje propuesto: `fix(habitats): tarjetas 3:2 y deprecación de favoritos`.
- Sin push.

## 10. Aprobación

Este plan **no autoriza implementación** hasta que `approval.md` registre una aprobación explícita de la **v1** con el texto literal del desarrollador.
