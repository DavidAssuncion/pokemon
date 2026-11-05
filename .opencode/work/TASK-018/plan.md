# TASK-018 — Plan v2 — Miniatura de ubicación en cabecera y modal ancho

- **ID:** TASK-018
- **Versión:** v2 (sustituye a v1; el desarrollador rectificó la decisión de no incluir miniatura)
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-05
- **Proyecto:** `/home/david/pokemon` — Laravel 12 / Blade + Alpine
- **Base:** `request.md`, `analysis.md`
- **Rama:** `task-011`

---

## 1. Objetivo

1. **Ubicación en la cabecera:** tarjeta compacta a la derecha en escritorio, con **miniatura del hábitat (máx. 128 px)**, provincia, nombre y **botón “Ir al hábitat”**.
2. **Responsive:** en móvil la tarjeta se apila bajo la identidad para no comprimir sprite/nombre/tipos.
3. **Sin duplicación:** retirar el bloque “Ubicación” del cuerpo; el estado vacío “Sin hábitat asignado.” pasa a la cabecera.
4. **Ancho tipo `/equipos`:** `w-[90vw] max-w-[90vw] max-h-[94vh]`, con cabecera fija y cuerpo con scroll.
5. **Solo lectura:** sin quality/EV/IV ni acciones de escritura.

## 2. Alcance

### Incluido

- `resources/views/pokedex/index.blade.php`:
  - contenedor del modal con `w-[90vw] max-w-[90vw] max-h-[94vh]` y sin `max-w-3xl`/`lg:max-w-4xl`;
  - cabecera `flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6`;
  - tarjeta de ubicación a la derecha en `lg` con miniatura `w-24 max-w-32 sm:w-28 lg:w-32`, `aspect-[4/3]`, `object-cover`;
  - provincia + nombre + botón “Ir al hábitat”; rutas adicionales compactas;
  - getter Alpine `ubicacionesDetalle` con fallback a `location`;
  - eliminación del bloque “Ubicación” del cuerpo y actualización del comentario de la columna derecha.
- Verificación estática, build y commit scoped en `task-011`.

### Fuera de alcance

- Backend, contrato, rutas, migraciones.
- `/equipos`, `/habitats`, CSS compartido.
- Imagen 16:9 grande en el cuerpo (se elimina).
- Tests: no se crean, actualizan ni ejecutan.
- `reviewer` y `security`: no se lanzan por continuidad.

## 3. Decisiones

| ID | Decisión |
|---|---|
| D1 | La ubicación va en una tarjeta a la derecha de la cabecera en `lg` (≥1024 px); en móvil se apila debajo de la identidad. |
| D2 | Miniatura `w-24 max-w-32 sm:w-28 lg:w-32` (máx. 128 px), `aspect-[4/3]`, `object-cover`, `alt=""` por decorativa. |
| D3 | Provincia + nombre + botón primario “Ir al hábitat”. La primera ubicación lleva miniatura y botón; las adicionales, enlace de texto compacto. |
| D4 | Se elimina el bloque “Ubicación” del cuerpo; el estado vacío se muestra en la cabecera solo con detalle cargado y sin rutas. |
| D5 | Getter `ubicacionesDetalle` para no triplicar `locations || location`. |
| D6 | Modal `w-[90vw] max-w-[90vw] max-h-[94vh]`; se conserva `flex flex-col overflow-hidden` con cabecera fija y cuerpo con scroll. |
| D7 | Grid interno intacto: `md:grid-cols-5` con 3/5 + 2/5; movimientos a ancho completo. |
| D8 | No se toca el cambio local `gap-6` de `/habitats` ni `routes/habitats.php`. |

## 4. Archivo

| Archivo | Responsable | Cambio |
|---|---|---|
| `resources/views/pokedex/index.blade.php` | frontend | Ancho del modal, cabecera responsive con miniatura/botón, getter y limpieza del bloque del cuerpo. |

No se modifica ningún otro archivo.

## 5. Orden de ejecución

1. Aplicar el ancho del modal.
2. Añadir el getter `ubicacionesDetalle`.
3. Reestructurar la cabecera con la tarjeta de ubicación.
4. Eliminar el bloque “Ubicación” del cuerpo y actualizar el comentario.
5. `php artisan view:cache` / `view:clear`, `npm run build`, greps y `git diff --check`.
6. Commit scoped en `task-011`.

## 6. Criterios de aceptación

1. En `lg`, la cabecera muestra a la derecha la miniatura del hábitat (máx. 128 px), provincia, nombre y botón “Ir al hábitat”.
2. En móvil, la tarjeta se apila bajo la identidad sin comprimir el nombre ni los tipos.
3. El botón/enlace navega a `loc.url` (`/habitats/{id}`).
4. El cuerpo ya no contiene el bloque “Ubicación”.
5. “Sin hábitat asignado.” aparece en la cabecera solo cuando el detalle cargó y `locations` está vacío.
6. Con Pokémon no avistado sin `unlockAll`, la tarjeta de ubicación no aparece y se mantiene el mensaje actual.
7. El modal usa `w-[90vw] max-w-[90vw] max-h-[94vh]`, sin `max-w-3xl` ni `lg:max-w-4xl`.
8. La cabecera permanece fija y el cuerpo scrollea.
9. No hay quality/EV/IV ni acciones de escritura.
10. `/equipos`, `/habitats` y el backend no cambian.

## 7. Verificación

```bash
php artisan view:cache && php artisan view:clear
npm run build
grep -n "w-\[90vw\]\|max-w-\[90vw\]\|max-h-\[94vh\]\|ubicacionesDetalle\|Ir al hábitat\|max-w-32" resources/views/pokedex/index.blade.php
grep -n "max-w-3xl\|lg:max-w-4xl" resources/views/pokedex/index.blade.php
git diff --check
```

- Confirmar que el bloque `<!-- Ubicación -->` solo existe una vez, en la cabecera.
- Confirmar que el cuerpo conserva `min-h-0 flex-1 overflow-y-auto`.
- Revisión visual recomendada: 360, 375, 390, 768, 1024, 1280, 1440, 1920 px; estados con 1 ubicación, sin ubicación, no avistado y error.
- Sin tests. Sin `reviewer` ni `security`.

## 8. Riesgos y rollback

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Crecimiento de cabecera en móvil (~84 px) | Medio | Miniatura de 96 px y textos compactos; el cuerpo mantiene scroll. |
| Salto de altura al aparecer la tarjeta tras el fetch | Medio | Aceptado; se puede reservar placeholder en una iteración futura. |
| `object-cover` recorta imágenes retrato/cuadradas o muy panorámicas | Bajo | Aceptado para miniatura; el enlace lleva a la ficha con imagen completa. |
| Nombres largos en móvil | Bajo | `truncate` + `aria-label`/`title`. |
| Solape con el botón cerrar | Bajo | `pr-12` en cabecera + `lg:mr-1`; miniatura a la izquierda de la tarjeta. |
| Muchas ubicaciones | Bajo | Solo la primera lleva miniatura y botón; las extra son filas compactas. |
| Ancho limitado por clases antiguas | Bajo | Eliminar `max-w-3xl` y `lg:max-w-4xl`. |

**Rollback:** la vista está limpia en HEAD; se puede revertir con un parche inverso. No usar `git checkout --` si hay cambios locales ajenos (no los hay en esta vista).

## 9. Commit

- Rama: `task-011`.
- Archivo: `resources/views/pokedex/index.blade.php`.
- No se stagean `resources/views/habitats/index.blade.php` (cambio local del desarrollador, `gap-6`) ni `routes/habitats.php` (preexistente).
- Mensaje propuesto: `feat(pokedex): miniatura de ubicación en cabecera y modal ancho`.
- Sin push.

## 10. Aprobación

Este plan **no autoriza implementación** hasta que `approval.md` registre una aprobación explícita de la **v2** con el texto literal del desarrollador.
