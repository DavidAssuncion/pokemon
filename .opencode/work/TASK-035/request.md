# TASK-035 — Petición

- **Fecha:** 2026-11-09
- **Origen:** desarrollador, sesión de OpenCode.
- **Estado:** RECEIVED → ANALYZING

## Texto literal

> Antes de un combate, en Configurar Formación, se debe mostrar un aproximado de los PC entre nuestro equipo vs los del rival, para saber si mejor evitar el combate

## Interpretación inicial

Añadir al modal de **Configurar Formación**, antes de confirmar el combate, una comparación aproximada del **PC (Poder/Puntos de Combate)** del equipo seleccionado frente al del rival, de forma que el jugador pueda decidir no combatir.

## Decisiones de producto (aclaradas con el desarrollador)

1. **Alcance:** se muestra en todos los modales de formación:
   - Combate de ruta 3v3 (modal de hábitats).
   - Combates contra entrenadores de hábitat (mismo modal de hábitats).
   - Combates de gimnasio (modal de `gimnasios/show`).
2. **Fórmula:** el PC será una **propuesta basada en las stats reales** del juego. El `analyst` debe revisar las fórmulas y datos de combate existentes y proponer un cálculo aproximado y explicable; la fórmula definitiva se valida en el plan.

## Contexto conocido

- Proyecto: `/home/david/pokemon` (Laravel 12 + Livewire + Blade + Alpine; dominio en `src/`).
- Rama actual: `task-011`.
- Cambios locales preexistentes: `bugs.txt` modificado; `TASK-032/`, `TASK-033/` y `TASK-034/` sin seguimiento. No tocar.
- `TASK-034` está en curso (presentación de EXP post-combate) y puede tocar vistas de combate. Esta tarea es independiente, pero la implementación debe secuenciarse si comparten archivos.
- Modales existentes:
  - `resources/views/habitats/partials/modal-formacion.blade.php` (ruta 3v3 y entrenadores; estado Alpine en `resources/views/habitats/show.blade.php`).
  - `resources/views/gimnasios/show.blade.php` (modal de formación y estado Alpine propio).
- En el modal actual solo se muestran los miembros del equipo y el toggle Vanguardia/Retaguardia; no hay ningún concepto de PC ni comparación con el rival.
- En gimnasios el equipo rival se oculta deliberadamente en la UI de etapas ("sin revelar su equipo"), por lo que habrá que decidir qué agregado puede mostrarse sin filtrar la composición.

## Restricciones

- No crear, modificar ni ejecutar tests.
- No modificar archivos en esta fase de análisis; solo devolver el análisis.
- No añadir ni actualizar dependencias.
- No hacer `git push` ni operaciones destructivas.
- No tocar `bugs.txt` ni artefactos de otras tareas.
- No cambiar fórmulas de combate, balance, economía ni esquema sin decisión explícita aprobada.
- Evitar exponer la composición exacta del rival si el diseño actual la oculta; el indicador debe ser agregado/aproximado.
- Respetar accesibilidad y el patrón Blade/Alpine existente.

## Preguntas para el análisis

1. ¿Qué datos de nuestro equipo están disponibles en cada modal (stats, nivel, tipos, formación) y de dónde provienen?
2. ¿Qué información del rival existe **antes** de iniciar el combate en cada contexto (ruta 3v3, entrenador, gimnasio)? ¿Es composición real, niveles de referencia o generación al iniciar?
3. ¿Dónde se calculan las stats efectivas y las fórmulas de combate? ¿Qué campos (PS, ataque, defensa, velocidad, nivel) permiten un PC aproximado coherente?
4. ¿Conviene calcular el PC en backend (nuevo contrato/endpoint o datos ya enviados) o en frontend con datos existentes? Riesgos de duplicar fórmulas.
5. ¿La formación (vanguardia/retaguardia) debe influir en el PC mostrado, o el PC es previo a la formación?
6. ¿Cómo mostrar la comparación (totales, barras, veredicto) sin inducir a error y sin revelar el equipo rival?
7. ¿Qué ocurre si no hay equipo seleccionado o el rival aún no está definido?
8. ¿Qué archivos habría que tocar y qué verificación estática/manual se propone?
