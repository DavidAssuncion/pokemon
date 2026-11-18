# TASK-059 — Petición

- **ID:** TASK-059
- **Fecha:** 2026-11-18
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** corrección funcional + unificación de modales de inicio de combate.
- **Estado:** RECEIVED → ANALYZING.

## Texto literal

> En mazmorra no puedo elegir vanguardia retaguardia en el combate. Revisa estos modales, deberian ser unicos para todos los tipos de pelea

## Interpretación inicial

1. **Fallo en mazmorra:** el modal de mazmorra permite elegir equipo y muestra controles de
   formación (Vanguardia/Retaguardia), pero la elección no se aplica al combate o no se
   envía al backend.
2. **Unificación:** los modales de selección de equipo/formación de los distintos tipos de
   pelea (mazmorra, gimnasio, ruta, entrenadores, etc.) deberían ser un único componente
   reutilizable, con el mismo comportamiento y contrato.
3. **Formación:** todos los tipos de pelea deben enviar y respetar la formación elegida
   (vanguardia/retaguardia) al construir el equipo del jugador.

## Contexto conocido

- El modal de mazmorra está en
  `resources/views/habitats/partials/modal-mazmorra.blade.php`; contiene toggles
  `mazmorraFormacion` y el botón `confirmarPiso()`.
- El modal de formación de hábitats está en
  `resources/views/habitats/partials/modal-formacion.blade.php`.
- El modal de gimnasios está en `resources/views/gimnasios/show.blade.php`.
- `/equipos` tiene su propio modal de edición de equipo.
- El backend de mazmorra (`MazmorraController::combatir`) acepta `formacion` y lo pasa a
  `IniciarCombateMazmorra`; hay que comprobar si el frontend lo envía y si el estado de los
  toggles se actualiza.
- El árbol de trabajo está sucio con tareas concurrentes (TASK-049..058). No se deben
  sobrescribir cambios ajenos.

## Ambigüedades para `analyst`

- ¿Cuáles son todos los tipos de pelea y qué modal usa cada uno?
- ¿El fallo de mazmorra es de UI (estado/toggles), de payload (no se envía `formacion`) o
  de backend (se ignora)?
- ¿La unificación debe ser un componente Blade anónimo, un parcial Alpine, un componente
  Livewire o una combinación?
- ¿El alcance incluye cambiar el flujo de gimnasios/ruta/entrenadores o solo extraer el
  modal común sin alterar su comportamiento?
- ¿La formación debe poder cambiarse también dentro del combate o solo antes de iniciarlo?

## Restricciones conocidas

- Sin tests: no crear, modificar ni ejecutar la suite.
- Sin dependencias nuevas.
- No revertir ni sobrescribir cambios locales de otras tareas.
- No tocar archivos fuera del alcance que determine el análisis.
- No leer `.env` ni secretos.
- Mantener accesibilidad y el comportamiento existente de cada tipo de pelea.
