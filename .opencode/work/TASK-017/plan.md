# TASK-017 — Plan v1 — Imágenes estiradas y tarjetas uniformes

- **ID:** TASK-017
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-04
- **Proyecto:** `/home/david/pokemon` — Laravel 12 / Blade + Alpine
- **Base:** `request.md`, `analysis.md`
- **Rama:** `task-011`

---

## 1. Objetivo

1. **Estirar** las imágenes de las tarjetas de `/habitats` para que ocupen todo el marco, aunque se deformen (`object-fill`, sin padding).
2. **Uniformar** todas las tarjetas: misma zona de imagen y misma altura de tarjeta en todos los breakpoints y provincias, fijando la banda del nombre.
3. **Mantener** el grid actual de 6 columnas y `gap-3`.

## 2. Alcance

### Incluido

- `resources/views/habitats/index.blade.php`:
  - imagen `block w-full h-full object-fill` (sin `p-2`);
  - contenedor de imagen `w-full aspect-[3/2] bg-surface-sunken overflow-hidden` (sin flex de centrado);
  - banda del nombre `h-11 px-4 ... flex items-center justify-center` + `line-clamp-2`;
  - eliminar `group` y el zoom `group-hover:scale-[1.03]` (recortaría la imagen ya estirada);
  - comentario del marco actualizado.
- Verificación estática, build y commit scoped en `task-011`.

### Fuera de alcance

- Cambiar columnas, `gap-3`, enlace, colores, sombra o borde de la tarjeta.
- `/habitats/{id}`, `/pokedex`, `/equipos`, CSS compartido, rutas, controladores, migraciones.
- Tests: no se crean, actualizan ni ejecutan.
- `reviewer` y `security`: no se lanzan por continuidad.

## 3. Decisiones

| ID | Decisión |
|---|---|
| D1 | `object-fill` + `w-full h-full` + `block`, sin `p-2`: la imagen ocupa el 100 % del marco y se deforma lo necesario. |
| D2 | Contenedor `aspect-[3/2]` sin flex: el marco ya es uniforme; el flex no aporta y puede encoger el ítem. |
| D3 | Banda del nombre `h-11` (44 px) + `line-clamp-2`: todas las tarjetas miden lo mismo; los nombres de 21 caracteres caben en dos líneas. |
| D4 | Se elimina el zoom de hover y la clase `group`: con `object-fill`, el zoom recortaría los bordes. Queda el feedback de sombra/borde. |
| D5 | Grid intacto: `grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3`. |
| D6 | Un único archivo modificado y commit scoped en `task-011`, sin push. |

## 4. Archivo

| Archivo | Responsable | Cambio |
|---|---|---|
| `resources/views/habitats/index.blade.php` | frontend | Imagen estirada, marco sin flex, banda del nombre fija y limpieza de hover. |

No se modifica ningún otro archivo.

## 5. Orden de ejecución

1. Aplicar las clases exactas del análisis.
2. `php artisan view:cache` / `view:clear`.
3. `npm run build` y comprobar `.object-fill` / `.line-clamp-2` en el CSS compilado.
4. Greps de verificación.
5. Commit scoped en `task-011`.

## 6. Criterios de aceptación

1. La imagen usa `block w-full h-full object-fill` y no tiene padding.
2. El marco usa `aspect-[3/2]` y no tiene `flex items-center justify-center`.
3. La banda del nombre usa `h-11`, `flex items-center justify-center` y `line-clamp-2`.
4. No quedan `group`, `group-hover:scale-[1.03]`, `object-contain` ni `p-2` en la tarjeta.
5. Se conserva `xl:grid-cols-6` y `gap-3`.
6. Todas las tarjetas tienen la misma altura en todos los breakpoints y provincias.
7. `/habitats/{id}`, `/pokedex` y `/equipos` no cambian.

## 7. Verificación

```bash
php artisan view:cache && php artisan view:clear
npm run build
grep -n "object-fill\|h-11\|line-clamp-2" resources/views/habitats/index.blade.php
grep -n "object-contain\|p-2\|scale-\[1.03\]\|group-hover" resources/views/habitats/index.blade.php
git diff --check
```

- Comprobar en el CSS compilado la presencia de `.object-fill{object-fit:fill}` y `.line-clamp-2`.
- Revisión visual recomendada: 375, 640, 768, 1024 y 1280 px; cambiar de provincia y verificar altura uniforme.
- Sin tests. Sin `reviewer` ni `security`.

## 8. Riesgos y rollback

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Deformación visible de imágenes (hasta ~12–25 % en casos extremos) | Alto | Es el requisito explícito; se documenta. |
| Nombres muy largos con elipsis | Medio | `line-clamp-2` mantiene los nombres actuales; `alt` conserva el nombre completo. |
| `line-clamp` en hijo de flex | Bajo | Probado en navegadores actuales; alternativa `grid place-items-center`. |
| Caché de build/vista | Bajo | `npm run build` + `view:clear`. |
| Pérdida del zoom de hover | Bajo | Decisión justificada; reversible en una línea. |

**Rollback:** el archivo está limpio en HEAD; se puede revertir con un parche inverso de las líneas modificadas. No usar `git checkout --` si hay cambios locales del desarrollador (ahora no los hay en esta vista).

## 9. Commit

- Rama: `task-011`.
- Archivo: `resources/views/habitats/index.blade.php`.
- No se stagean `routes/habitats.php`, `resources/css/app.css`, `package.json`, `show.blade.php` ni ningún otro cambio preexistente.
- Mensaje propuesto: `fix(habitats): estirar imágenes y uniformar tarjetas`.
- Sin push.

## 10. Aprobación

Este plan **no autoriza implementación** hasta que `approval.md` registre una aprobación explícita de la **v1** con el texto literal del desarrollador.
