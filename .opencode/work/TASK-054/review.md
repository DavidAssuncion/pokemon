# TASK-054 — Revisión

- **Revisor:** `reviewer` independiente (sesión `ses_e14af6cd9ffeIWckDDOxq6ZMrA`).
- **Fecha:** 2026-11-17.
- **Veredicto:** **APPROVED**.
- **Estado:** revisión cerrada.

## Criterios de aceptación

| # | Criterio | Resultado |
|---|---|---|
| 1 | Retaguardia a la izquierda y Vanguardia a la derecha en `/equipos` | PASS |
| 2 | Contenido y comportamiento de ambas columnas sin cambios | PASS |
| 3 | Cabecera, colapso y modal de edición intactos | PASS |
| 4 | Modo ruta sin "Entrenador de ruta", sprite ni nombre de clase; niveles primero | PASS |
| 5 | Modo `entrenadores` sin cambios | PASS |
| 6 | Sin referencias a `claseBaseHabitat`; `spriteUrl`/`spriteAlt` conservan usos | PASS |
| 7 | Solo los tres archivos autorizados | PASS |
| 8 | `git diff --check` limpio | PASS |
| 9 | `view:cache`/`view:clear` OK | PASS |
| 10 | Sin tests | PASS |

## Evidencia destacada

- El DOM actual tiene Retaguardia en `panel-equipos.blade.php:148-193` y Vanguardia en
  `195-240`; `diff -u` entre ambos bloques solo muestra las diferencias esperadas
  (comentario, colores, formación y prefijo de `:key`).
- El orden coincide con `battle-field.blade.php:21-41` (columna 1 Retaguardia, columna 2
  Vanguardia).
- `grep "Entrenador de ruta"` y `grep "claseBaseHabitat"` sin resultados en código.
- Las vistas compiladas confirman el orden Retaguardia→Vanguardia y la ausencia del
  bloque y del helper.
- Sin `order-*`, sin CSS nuevo y sin archivos nuevos de TASK-054.

## Hallazgos

### LOW (fuera del alcance autorizado)

- **`resources/views/habitats/partials/columna-izquierda.blade.php:73` — sprite de clase
  del hábitat visible en el botón de modo "Entrenadores" durante modo ruta.** Es una
  adición de TASK-053 fuera de los tres archivos autorizados. No afecta al bloque de
  combate de ruta retirado. Requiere decisión y plan nuevos si se quiere eliminar.

### NIT (no bloqueantes, preexistentes)

- `panel-equipos.blade.php:150,197` — cabeceras de columna sin `role="group"`/heading.
- `columna-derecha.blade.php:306` — `spriteAlt(spriteEntrenador(...))` evaluado tres
  veces en el mismo `aria-label` (código de TASK-053).

## Verificación ejecutada

- `git status --short`, `git diff --stat`, `git diff --check`.
- Lectura completa de los tres archivos en su estado actual y `diff -u` entre los bloques
  de la tarjeta.
- `grep` de "Entrenador de ruta", `claseBaseHabitat`, `spriteUrl`, `spriteAlt` y
  `spriteEntrenador`.
- Conteo de `<div>`/`</div>` en los archivos afectados.
- `php artisan view:cache` y `view:clear`; inspección de vistas compiladas.
- `ls -lt` para aislar la ventana de edición de TASK-054.
- Sin tests y sin `npm run build`.

## Riesgos residuales

- **Trazabilidad:** TASK-053 y TASK-054 están sin commitear; la retirada del bloque y del
  helper no aparece en `git diff` frente a HEAD porque cancela adiciones de TASK-053.
  Conviene commitear de forma secuencial para que el efecto neto sea auditable.
- **Accesibilidad:** el orden de lectura cambia deliberadamente a Retaguardia →
  Vanguardia, coincidiendo con el orden visual y el campo de batalla.
- **320 px:** sin riesgo nuevo; ambas columnas son estructuralmente idénticas.
- **Verificación en navegador:** no ejecutada por el revisor; queda para comprobación
  manual del desarrollador.
- **Sprite de clase en la columna izquierda (LOW):** pendiente de decisión del
  desarrollador; fuera del alcance de TASK-054.
