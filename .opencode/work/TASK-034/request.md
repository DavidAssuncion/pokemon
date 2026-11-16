# TASK-034 — Petición

- **Fecha:** 2026-11-09
- **Origen:** desarrollador, sesión de OpenCode.
- **Estado:** RECEIVED → ANALYZING

## Texto literal

> Conseguir experiencia debe sentirse y verse mejor. Tras un combate, debe mostrarse el nivel de la cuenta y las sendas para el tipo y recibir la experiencia, ver como sube la barra y producir una animacion si sube de nivel, se avacia y reajusta

## Interpretación inicial

Mejorar la presentación de la experiencia tras un combate:

1. Mostrar el **nivel de la cuenta** antes/después y la barra de progreso de experiencia.
2. Mostrar las **Sendas de tipo** que reciben experiencia, con su nivel y barra de progreso.
3. Animar la subida de cada barra al recibir la EXP.
4. Si se sube de nivel: animación de subida, vaciado de la barra y reajuste/continuación hasta el nuevo porcentaje.
5. Mantener el resto de recompensas (capturas, caramelos, medalla) que ya muestra el modal.

## Contexto conocido

- Proyecto: `/home/david/pokemon` (Laravel 12 + Livewire + Blade/Bootstrap + Alpine; dominio en `src/`).
- Rama actual: `task-011`.
- Cambios locales preexistentes: `bugs.txt` modificado y `TASK-032/` sin seguimiento. No tocar.
- `TASK-033` está en curso y afecta a targeting/preparación de combate. Esta tarea es independiente, pero comparte potencialmente `resources/views/livewire/combate.blade.php` y `app/Livewire/Combate.php`; la implementación deberá secuenciarse.
- La EXP ya se persiste en `src/Exploraciones/App/PersistirRecompensas.php`: cuenta (`users.experiencia`), Sendas (`player_type_paths.exp/level`) y miembros/reclutados.
- La curva de nivel de cuenta vive en `src/Shared/Domain/NivelHelper.php`; la curva de Sendas en `app/Support/SendaProgreso.php` / `src/Shared/Domain/Sendas/CurvaSenda.php`.
- El modal de victoria actual está en `resources/views/livewire/combate.blade.php` (líneas ~123-260) y muestra `exp_total`, `exp_miembro`, capturas, caramelos y medalla, pero no nivel ni barras.
- `BatallaVistaPresenter::rewardsVista()` normaliza las recompensas para la vista; `PresentadorResultadoBatalla` otorga las recompensas por tipo de combate.

## Restricciones

- No crear, modificar ni ejecutar tests.
- No añadir ni actualizar dependencias.
- No hacer `git push` ni operaciones destructivas.
- No tocar `bugs.txt`, `TASK-032/` ni artefactos de otras tareas.
- No cambiar `SESSION_VERSION` ni `BattleSessionService` sin decisión explícita.
- Sin migraciones ni cambios de esquema salvo que el análisis demuestre que son imprescindibles (requiere aprobación).
- No duplicar la concesión de EXP: la animación debe ser solo presentación; la persistencia ya existe y no debe alterarse sin justificación.
- Respetar accesibilidad: `prefers-reduced-motion`, textos legibles, sin depender solo del color.
- Mantener el contrato de recompensas existente y la compatibilidad con sesiones/partidas antiguas.

## Preguntas para el análisis

1. ¿Dónde y cuándo se otorga exactamente la EXP en cada tipo de combate (ruta, entrenador, gimnasio, mazmorra) y qué DTO llega a la vista?
2. ¿Cómo capturar el estado antes/después de cuenta y Sendas sin duplicar escrituras ni introducir inconsistencias (transacción, snapshots, recarga)?
3. ¿Qué estructura de presentación necesita la vista para animar: EXP antes/después, nivel antes/después, umbral inicial/siguiente, porcentaje antes/después, tipos con EXP?
4. ¿Qué Sendas deben mostrarse: solo las que reciben EXP, todas las del jugador o las de los tipos del equipo/rivales?
5. ¿Cómo encajar la animación con Blade/Alpine/CSS existente, sin añadir dependencias, y cómo manejar varios niveles en una sola ganancia?
6. ¿Cómo comportarse si la EXP supera el tope de la Senda o el nivel máximo de cuenta?
7. ¿Afecta a la pantalla de recompensas de exploración, o solo al modal de combate?
8. ¿Qué verificación estática y manual se requiere?

## Nota de alcance

Esta tarea no incluye cambiar fórmulas de EXP, economía, curvas de nivel ni recompensas. Solo la captura de estado y la presentación/animation posterior al combate.
