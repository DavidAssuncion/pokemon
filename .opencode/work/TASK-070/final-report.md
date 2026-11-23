# TASK-070 — Informe final

- **Fecha:** 2026-11-19
- **Estado:** COMPLETADO
- **Plan:** v1

## Resultado

El evento de avistamientos queda operativo como evento global temporal de 3
días y se presenta en un modal sencillo de navbar que aparece y se oculta,
sin página propia.

- La primera lectura de cada ventana persiste hábitat/familia (determinista,
  una sola fila por `window_index`).
- El factor ×5 se aplica una sola vez en ruta (sobre `capture_rate/hatch`) y en
  exploración (sobre el factor de refugio).
- El modal muestra hábitat, familia, ×5, tiempo restante y especies por nivel
  con estado Visto/No visto; se cierra con X, Escape o clic fuera.
- Si no hay evento o la familia ya no está en el hábitat, el modal lo indica.

## Verificación

- Ventana 107: hábitat 23 «Vieja Central», familia Abra (cadena 27), factor 5,
  1 fila, 3 niveles.
- Puerto: `(23,27)=5`, `(23,28)=1`, `(24,27)=1`.
- Peso: `100/10 × 5 = 50`; con override no se duplica.
- PHPStan 0 errores; Pint OK; Deptrac 118 (sin cambios); Blade compilado;
  Vite build OK.

## Notas

- No hay migración nueva: la tabla `avistamiento_eventos` ya existe desde
  TASK-048. Si el entorno no la tiene, ejecutar `php artisan migrate`.
- Sin cron: la ventana se resuelve on-read; al cambiar de ventana el factor
  deja de aplicar automáticamente.
- Entrenadores, gimnasios y mazmorras no se ven afectados.
- Sin tests (política del proyecto).
