# TASK-036 — Petición

- **Fecha:** 2026-11-09
- **Origen:** desarrollador, sesión de OpenCode.
- **Estado:** RECEIVED → ANALYZING

## Texto literal

> si pulso sobre mi deberia poder usar Bucle Arena y derivados para obtener el beneficio

## Interpretación inicial

En el combate, al seleccionar al propio Pokémon activo como objetivo (pulsar su carta), debe poder usarse **Bucle Arena** (`sand-tomb`) y sus movimientos derivados para obtener su beneficio defensivo/aliado, en lugar de impedir la selección del propio actor.

Contexto conocido del sistema:

- `CatalogoEfectosAliado` clasifica `sand-tomb` como `ESCUDO_FISICO`, `fire-spin` como `ESCUDO_ESPECIAL` y `whirlpool` como `AQUA_RING` (5 turnos). Son movimientos con `target: normal` en `storage/data/moves.json` (35 de potencia), pero el juego los trata como apoyos sobre aliado.
- Hoy el jugador puede usar esos movimientos sobre otro aliado vivo (incluida la retaguardia desde TASK-033), pero no sobre sí mismo:
  - `BatallaVistaPresenter::refsAliado()` excluye al actor de las cartas seleccionables.
  - `GestorSeleccionBatalla::selectMove()` rechaza el autoobjetivo salvo que el movimiento sea autodirigido.
  - `MovesPreviewPresenter::previewDe()` no considera aliado al propio actor y mostraría una previsualización de daño.
  - `GestorAccionBatalla::resolver()` calcula `$esAliado` excluyendo al actor, por lo que enrutaría el movimiento por la ruta de ataque normal.

## Preguntas para el análisis

1. ¿Qué movimientos entran exactamente en "Bucle Arena y derivados"? ¿Solo `sand-tomb`, `fire-spin` y `whirlpool` (los tres del catálogo aliado con `target: normal`), o cualquier movimiento del catálogo aliado?
2. ¿El efecto sobre uno mismo debe ser idéntico al que se aplica a un aliado (escudo, Aqua Ring, duración, logs)?
3. ¿Qué capas y validaciones hay que ajustar para permitir el autoobjetivo sin abrir la puerta a atacarse a sí mismo con movimientos normales?
4. ¿Afecta a la IA? ¿La IA debe poder autoaplicarse estos efectos o se mantiene intacta?
5. ¿Afecta a la previsualización de movimientos, a la animación y a los eventos de combate?
6. ¿Hay interacción con `DESAFIO`, posturas (`Cubrir`/`Guardia`) o preparación de ronda?
7. ¿Qué compatibilidad hay que preservar con sesiones antiguas y con el contrato de `Combate` (34 propiedades/18 métodos)?
8. ¿Qué verificación estática y manual se requiere, sin tests?

## Restricciones

- No crear, modificar ni ejecutar tests.
- No añadir ni actualizar dependencias.
- No hacer `git push` ni operaciones destructivas.
- No tocar `bugs.txt`, `.opencode/work/TASK-032/`, `TASK-033/`, `TASK-034/` ni `TASK-035/`.
- No cambiar `SESSION_VERSION` ni `BattleSessionService` sin decisión explícita.
- Sin migraciones ni cambios de esquema.
- Mantener el comportamiento de la IA y del resto de movimientos de apoyo.
- Conservar el contrato público de `Combate` salvo que el diseño justifique lo contrario y se apruebe.
