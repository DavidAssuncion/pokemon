# TASK-033 — Informe final

- **ID:** TASK-033
- **Fecha:** 2026-11-09
- **Estado final:** `COMPLETED` — implementación, verificación estática, revisión y seguridad completadas. Smoke manual en navegador pendiente por el desarrollador.
- **Commit local:** `e5f6b7c` (`fix(task-033): corregir targeting de apoyos, prioridades y cobertura`), sin `git push`.
- **Plan:** v1 aprobado («Aprobar plan v1»).
- **Tests:** no ejecutados, no creados, no modificados.

## 1. Resumen

Se corrigieron los tres fallos de targeting reportados:

1. **Danza Espada y apoyos sobre aliado en retaguardia:** se eliminó el bloqueo de posición aliada solo en el camino del jugador (`BatallaVistaPresenter::refsAliado()`, `GestorSeleccionBatalla::previewTarget()` y `selectMove()`). El targeting rival y la IA no cambian.
2. **Objetivo de prioridades:** se añadió `$prioridadesObjetivo` y el selector de objetivo en la preparación, con opción “Automático”, validación en `aplicar()`, revalidación en `resolver()`, precedencia de `DESAFIO` y tratamiento de autodirigidos.
3. **A quién cubrir:** se añadió `$coberturas` y el selector de aliado cubierto cuando la postura es `Cubrir`, con validación de mismo equipo/vivo/no autoobjetivo y fallback a `normalizarCubridores()`.

Además se añadió `AgregadoBatalla::objetivosRivalesLegalesPara()` como lectura pura para centralizar la regla de fila.

## 2. Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Livewire/Combate.php` | Nuevas propiedades `$prioridadesObjetivo` y `$coberturas`; docblock ampliado. |
| `src/Battle/Domain/AgregadoBatalla.php` | Nuevo `objetivosRivalesLegalesPara()`. |
| `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php` | Inicialización/limpieza, opciones de prioridad, validación de objetivo y cobertura, sincronización al cambiar postura. |
| `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` | Objetivo declarado, `DESAFIO`, revalidación y fallback IA. |
| `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` | Eliminados los dos bloqueos de retaguardia aliada. |
| `app/Livewire/Presenters/BatallaVistaPresenter.php` | Eliminado el filtro de retaguardia en `refsAliado()` y `tieneVanguardiaViva()`. |
| `resources/views/livewire/partials/preparacion-panel.blade.php` | Selectores de objetivo de prioridad y de aliado cubierto, avisos, accesibilidad y `@selected`. |

## 3. Decisiones

- Solo el jugador puede apoyar a aliados en retaguardia; la IA conserva su restricción.
- `DESAFIO` prevalece sobre la elección de prioridad, con aviso en UI.
- La cobertura se elige solo en la preparación de ronda, sin persistencia en Equipo.
- La reasignación de Cubrir al morir el objetivo se mantiene en la siguiente ronda.
- El objetivo de prioridad vive en el componente Livewire; no se persiste en dominio ni cambia `SESSION_VERSION`.
- Sin métodos públicos nuevos: contrato de `Combate` en 34 propiedades/18 métodos.

## 4. Verificación ejecutada

- `php -l` sobre los 6 PHP modificados: sin errores.
- `vendor/bin/phpstan analyse --no-progress`: 34 errores preexistentes, ninguno en archivos de TASK-033.
- `vendor/bin/pint --dirty --format agent`: pass.
- `php artisan view:cache` y `view:clear`: correctos.
- `git diff --check` acotado: sin errores.
- Greps: sin `tieneVanguardiaViva` en el presenter, sin el log “no está en la vanguardia”, `SESSION_VERSION` en 21.
- `git status`: solo los 7 archivos de la tarea antes del commit; `bugs.txt` y directorios de tareas preexistentes intactos.

**Tests:** no se ejecutaron ni se modificaron.

## 5. Revisión y seguridad

- **Reviewer:** `APPROVED`. CA1–CA22 cumplidos por inspección estática. Hallazgos LOW/NIT: `array_values` (no aplicado; la premisa era incorrecta porque `Collection` reindexa), duplicación de helpers (diferida), comprobación redundante (NIT), `@selected` (aplicado).
- **Security:** `PASS_WITH_FINDINGS`, sin hallazgos MEDIUM+ introducidos. Deuda preexistente documentada: re-ejecución de preparación/prioridades, `battleId` público y advisories de dependencias. No bloquea.

## 6. Riesgos residuales

- **Smoke manual pendiente:** selección visual de aliado en retaguardia, comportamiento de `wire:model.live` dentro del modal, aviso de `DESAFIO` y reparto 40/40/20.
- Duplicación de helpers (`esAutodirigido`, `objetivoForzadoDesafio`, `textoMapa`) entre dos gestores; candidata a tarea de limpieza.
- El objetivo de prioridad y la cobertura no sobreviven a la ronda; se eligen de nuevo en la siguiente.
- Deuda preexistente de guard de ronda y normalización de tipos en mapas públicos.
- Advisories de dependencias conocidos (Livewire < 4.4.8, Symfony/Laravel); fuera de alcance.

## 7. Próximos pasos

1. Smoke manual del desarrollador en `/combate`: retaguardia aliada, prioridades con objetivo y Cubrir.
2. Validar el modal de preparación con `wire:model.live` y sesiones antiguas.
3. Programar la limpieza de helpers duplicados y el guard de ronda como tareas separadas.
4. No se ha hecho `git push`; el commit `e5f6b7c` queda local.
5. Los artefactos de `.opencode/work/TASK-033/` quedan sin seguimiento en Git.
