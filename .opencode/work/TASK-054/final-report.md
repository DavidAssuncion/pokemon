# TASK-054 — Informe final

## Resumen

Se aplicaron dos correcciones de presentación:

1. **`/equipos`:** la tarjeta de equipo desplegada muestra ahora **Retaguardia a la
   izquierda y Vanguardia a la derecha**, igual que el campo de combate. El cambio es un
   intercambio íntegro de bloques en el DOM, sin `order-*`, CSS nuevo ni cambios de
   contenido.
2. **`/habitats` en modo ruta:** se eliminó el bloque completo de clase base del hábitat
   (rótulo "Entrenador de ruta", sprite y nombre de clase). El panel de niveles queda
   como primer contenido. El modo `entrenadores` no se tocó. Se retiró también el helper
   Alpine `claseBaseHabitat()`, que quedó sin uso.

## Estado final

**COMPLETED**. Revisión independiente: **APPROVED** (10/10 criterios PASS).

## Archivos modificados

| Archivo | Acción |
|---|---|
| `resources/views/equipos/partials/panel-equipos.blade.php` | Retaguardia antes que Vanguardia dentro del grid de 2 columnas |
| `resources/views/habitats/partials/columna-derecha.blade.php` | Eliminado el bloque de clase base del hábitat en modo ruta |
| `resources/views/habitats/show.blade.php` | Eliminado el helper `claseBaseHabitat()` y su comentario |

Artefactos de coordinación: `.opencode/work/TASK-054/`.

## Decisiones

1. **Orden de columnas:** se corrigió el orden real del DOM, no con `order-*`, para que
   el orden visual y el de lectura (teclado y lectores de pantalla) coincidan con el
   campo de batalla.
2. **Alcance en hábitats:** el desarrollador eligió eliminar el **bloque completo**
   (rótulo, sprite y nombre de clase), no solo el rótulo.
3. **Limpieza:** se eliminó `claseBaseHabitat()` porque su único uso estaba en el bloque
   retirado. `spriteUrl()` y `spriteAlt()` se conservan por sus usos en el modo
   entrenadores y en otras vistas.
4. **Sin backend:** ambos cambios son 100 % presentación.

## Verificación ejecutada

- `php artisan view:cache`: OK.
- `php artisan view:clear`: OK.
- Vistas compiladas: sin restos de "Entrenador de ruta" ni `claseBaseHabitat`; orden
  Retaguardia→Vanguardia confirmado.
- `grep -rn "Entrenador de ruta" resources/views`: sin resultados.
- `grep -rn "claseBaseHabitat"`: sin resultados en código.
- `git diff --check`: limpio.
- `git status --short` / `git diff --stat`: solo los tres archivos autorizados dentro de
  la ventana de TASK-054; el resto de cambios son preexistentes de TASK-049 a TASK-053.
- Sin tests y sin `npm run build` (no hay clases nuevas).

## Hallazgos de revisión

- **LOW, fuera de alcance:** `resources/views/habitats/partials/columna-izquierda.blade.php:73`
  sigue mostrando el sprite de clase del hábitat en el botón del modo "Entrenadores"
  durante el modo ruta. Es una adición de TASK-053 y no forma parte del bloque de combate
  retirado. Si el desarrollador quiere eliminarlo también, requiere tarea y plan nuevos.
- **NIT preexistentes:** cabeceras de columna sin `role="group"`; evaluación repetida de
  `spriteAlt(spriteEntrenador(...))` en un `aria-label` de TASK-053.

## Hallazgos de seguridad

No aplica. El cambio es solo presentación: sin entradas de usuario, datos sensibles,
autenticación, autorización, SQL, archivos, sesiones, secretos, dependencias ni endpoints.

## Riesgos residuales

- **Trazabilidad del diff:** TASK-053 y TASK-054 están sin commitear. La retirada del
  bloque y del helper no aparece en `git diff` frente a HEAD porque cancela adiciones de
  TASK-053. Conviene commitear de forma secuencial (primero TASK-053 y después TASK-054)
  para que el efecto neto sea auditable.
- **Verificación manual:** pendiente de comprobación en navegador (`/equipos` con
  formaciones 3-0/2-1/1-2/0-3; `/habitats/{id}` en modo ruta y entrenadores; 320 px;
  teclado/foco).
- **Accesibilidad:** el orden de lectura cambia deliberadamente a Retaguardia →
  Vanguardia; coincide con el orden visual y con el campo de batalla.
- **Sprite de clase en la columna izquierda (LOW):** pendiente de decisión del
  desarrollador.

## Próximos pasos

1. Comprobar visualmente `/equipos` y `/habitats/{id}` en modo ruta y entrenadores.
2. Decidir si el sprite de clase del botón "Entrenadores" de la columna izquierda debe
   retirarse también (nueva tarea si procede).
3. Commit acotado a los archivos de TASK-054, teniendo en cuenta que
   `columna-derecha.blade.php` y `show.blade.php` también contienen cambios sin commitear
   de TASK-053.
