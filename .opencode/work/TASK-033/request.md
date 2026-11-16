# TASK-033 — Petición

- **Fecha:** 2026-11-09
- **Origen:** desarrollador, sesión de OpenCode.
- **Estado:** RECEIVED → ANALYZING

## Texto literal

> No puedo usar danza espada con otro en retaguardia
>
> En la preparacion, ataques prioridad no puedo elegir contra quien, y tampoco a quien cubrir

## Interpretación inicial

Tres fallos de selección de objetivo en el combate:

1. **Movimientos de apoyo sobre aliado en retaguardia.** `swords-dance` (Danza Espada) está en `CatalogoEfectosAliado`, pero no se puede usar sobre un aliado vivo que está en retaguardia mientras su vanguardia sigue viva. Se espera que los movimientos de apoyo puedan dirigirse a cualquier aliado vivo, independientemente de su posición.
2. **Objetivo de los ataques de prioridad en la preparación de ronda.** El panel de preparación permite elegir el movimiento de prioridad por Pokémon, pero no el objetivo; la resolución lo autoselecciona con `AgregadoBatalla::elegirObjetivoPara()`. Se espera poder elegir contra quién se dirige.
3. **Objetivo de Cubrir en la preparación de ronda.** La postura `cubrir` se puede elegir, pero no a qué aliado cubre; `EquipoBatalla::normalizarCubridores()` asigna el primero disponible. Se espera poder elegir a quién cubrir.

## Contexto conocido

- Proyecto: `/home/david/pokemon` (Laravel 12 + Livewire + Blade/Bootstrap + Alpine; dominio de batalla en `src/Battle`).
- Rama actual: `task-011`.
- Cambio local preexistente: `bugs.txt` modificado por el desarrollador (no tocar). `TASK-032/` sin seguimiento.
- El modo aliado se introdujo en TASK-030/031 (`CatalogoEfectosAliado`, `EfectoAliado`, `EjecutorEfectoAliado`).
- La preparación de ronda vive en `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php`, `GestorPrioridadesBatalla.php` y `resources/views/livewire/partials/preparacion-panel.blade.php`.
- `Cubrir` es una postura (`PosturaCombate::CUBRIR`) que redirige el 40 % del daño de un aliado al portador; el objetivo se guarda en `Combatiente::objetivoCubiertoId()`.
- La selección manual de objetivo de ataques normales vive en `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php`.

## Restricciones

- No crear, modificar ni ejecutar tests.
- No añadir ni actualizar dependencias.
- No hacer `git push` ni operaciones destructivas.
- No tocar `bugs.txt` ni artefactos de tareas anteriores.
- Mantener el comportamiento de la IA, del modo propio y del modo rival salvo la corrección solicitada.
- Sin migraciones ni cambios de esquema salvo que el análisis demuestre que son imprescindibles (requiere aprobación).
- No cambiar `SESSION_VERSION` ni `BattleSessionService` sin decisión explícita.
- Preservar accesibilidad y el contrato Livewire existente en la medida de lo posible.

## Preguntas para el análisis

1. ¿Dónde exactamente se bloquea el uso de Danza Espada sobre un aliado en retaguardia? ¿Afecta a `previewTarget`, `selectMove`, a la vista o a varias capas? ¿Hay más movimientos de apoyo afectados?
2. ¿Cómo se declara, resuelve y consume hoy una prioridad? ¿Qué estructura de estado haría falta para guardar el objetivo elegido por ronda y por actor?
3. ¿Qué objetivos válidos debe admitir una prioridad (rivales vivos, propia retaguardia, autodirigidos, aliados para futuros movimientos de prioridad de apoyo)?
4. ¿Cómo se asigna hoy el objetivo de `Cubrir` y cómo debería persistirse/validarse la elección del jugador (no cubrirse a sí mismo, aliado vivo, reasignación si muere)?
5. ¿Qué cambios de contrato Livewire (propiedades, métodos, validación) y de vistas son necesarios para los tres casos?
6. ¿Cómo debe comportarse la IA rival con las prioridades y con Cubrir? ¿Se ve afectada?
7. ¿Qué riesgos hay de romper sesiones antiguas, el autoguardado o la resolución de la ronda?
8. ¿Qué verificación estática y manual se requiere?
