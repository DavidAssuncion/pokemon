# TASK-039 — Petición

- **Fecha:** 2026-11-09
- **Origen:** desarrollador, sesión de OpenCode.
- **Estado:** RECEIVED → ANALYZING

## Texto literal

> Elimina el boton de esperar, siempre se ejecutara una accion, no se puede cancela

## Interpretación

Eliminar el botón **Esperar** de la barra de turnos del combate y garantizar que el jugador no pueda saltarse la acción: siempre debe ejecutarse una acción (movimiento, objeto, etc.). No se debe poder cancelar el turno mediante “Esperar”.

## Contexto técnico conocido

- El botón está en `resources/views/livewire/partials/turn-bar.blade.php:33-40`, condicionado por `$turnBar['mostrarEsperar']`.
- `mostrarEsperar` se calcula en `BatallaVistaPresenter::turnBar()` (`app/Livewire/Presenters/BatallaVistaPresenter.php:301-310`) como `faseSeleccion && sinAnimacion`.
- El botón llama a `wire:click="pasarTurno"`.
- Cadena actual: `Combate::pasarTurno()` → `OrquestadorBatalla::pasarTurno()` → `GestorSeleccionBatalla::pasarTurno()`.
- `GestorSeleccionBatalla::pasarTurno()` consume la acción del actor y registra `"{nombre} espera."`.
- No se han encontrado usos de `pasarTurno` en tests.
- La barra también tiene **Retrasar turno** (no consume la acción; reordena) y **Atacar con X** (adelanta el turno). No son equivalentes a “Esperar”.

## Preguntas para el análisis

1. ¿Basta con eliminar el botón o hay que eliminar también `pasarTurno` de la cadena pública para impedir que un cliente lo invoque?
2. ¿Qué ocurre si el actor no tiene ningún movimiento disponible (sin PP, silencio, objeto de elección, etc.)? ¿Existe un fallback o se puede quedar bloqueado sin el botón Esperar?
3. ¿“Retrasar turno” debe mantenerse? No cancela la acción, solo la pospone.
4. ¿Hay otras vías de saltar la acción (objetos, preparación, `swapTurno`, auto-skip) que haya que revisar?
5. ¿El cambio afecta al contrato de `Combate` (métodos públicos) y a la compatibilidad de sesiones?
6. ¿Hay que ajustar `mostrarEsperar` y `turnBar` o basta con quitar el bloque Blade?
7. ¿Qué verificación estática y manual se requiere, sin tests?

## Restricciones

- No crear, modificar ni ejecutar tests.
- No añadir ni actualizar dependencias.
- No hacer `git push` ni operaciones destructivas.
- No tocar `bugs.txt`, `.opencode/work/TASK-032/` a `TASK-038/` ni los cambios de TASK-035.
- No cambiar `SESSION_VERSION` ni `BattleSessionService` sin decisión explícita.
- Sin migraciones ni cambios de esquema.
- Mantener el resto de la barra de turnos y la lógica de preparación.
