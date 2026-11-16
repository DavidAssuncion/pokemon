# TASK-041 — Petición

- **Fecha:** 2026-11-09
- **Origen:** desarrollador, sesión de OpenCode.
- **Estado:** RECEIVED → CLARIFYING

## Texto literal

> Iba contra fantasma por eso lucha o normal era mala idea, debia usar garra metal pero no lo hacia. Hay que revisar mas a fondo esto. Poner el cursos sobre el pokemon rival debe decirme sus movimientos y stats para debuggearlo mas a fondo, debemos depurar el comportamiento de la IA

## Interpretación

Dos entregables relacionados con depurar la IA:

1. **Selección de movimiento contra Fantasma:** Riolu (IA) debía usar **Garra Metal** (Acero) contra un rival Fantasma, porque Lucha y Normal no son eficaces. No lo hacía: la política de prioridades de `declararIa()` elige cualquier movimiento con `priority > 0` sin evaluar tipo/daño/valor, por lo que Quick Attack (Normal) o Aguante siguen ganando a Garra Metal (Acero, prioridad 0). Tras TASK-040, Aguante se filtra en rondas consecutivas, pero Quick Attack puede seguir siendo elegido por su prioridad.
2. **Tooltip de debug del rival:** al pasar el cursor sobre un Pokémon rival, mostrar sus movimientos y stats para poder diagnosticar por qué la IA elige una acción.

## Contexto técnico conocido

- `GestorPrioridadesBatalla::declararIa()` recorre los movimientos con `priority > 0` y elige el de mayor prioridad; en empate, mayor potencia. No usa `SelectorAccionIA` ni evalúa efectividad de tipo.
- `SelectorAccionIA::elegirAccion()` ya construye candidatos, calcula daño, efectividad, KO, amenazas y valor de soporte. `AgregadoBatalla::elegirAccionPara()` lo usa para la acción normal.
- `TypeChart` marca Fantasma como inmune a Normal y Lucha (`TypeChart.php:41,56`).
- Riolu tiene Golpe Roca (Lucha), Garra Metal (Acero), Onda Vacío (Lucha), Ataque Rápido (Normal), Aguante y Avivar.
- TASK-040 corrigió el bloqueo perpetuo de Aguante/Protección/Detección, pero no la política de prioridades.
- La card rival ya recibe stats parciales en `CombatienteVista`; no recibe la lista de movimientos.

## Preguntas para el análisis

1. Confirmar que `declararIa()` es la causa de que Garra Metal no se use contra Fantasma y que, tras TASK-040, Quick Attack seguiría ganando por prioridad.
2. ¿Cómo debe integrarse `SelectorAccionIA` en `declararIa()` sin duplicar la evaluación ni romper el flujo normal? ¿Declarar prioridad solo si la mejor acción evaluada es un movimiento de prioridad? ¿O comparar el valor de la prioridad con la mejor acción normal?
3. ¿Qué pasa con `DESAFIO`, soporte, autodirigidos, acciones extra y prioridades forzadas?
4. Tooltip: qué datos exactos mostrar (movimientos con tipo/categoría/potencia/precisión/PP, stats actuales/máximas, tipos, habilidad, objeto, condiciones), de dónde obtenerlos y cómo enviarlos a la vista.
5. Visibilidad: ¿siempre, solo con `APP_DEBUG`, con un flag de configuración o con un toggle de debug? Implicaciones de revelar información del rival.
6. UI/UX: hover, focus de teclado, tap en móvil, Bootstrap tooltip/popover/Alpine, accesibilidad, escapado.
7. Archivos afectados por especialista (`backend`/`frontend`), orden, criterios de aceptación y verificación estática/manual sin tests.
8. Compatibilidad: `SESSION_VERSION`, serialización, contrato de `Combate`, sesiones antiguas.
9. Riesgos de balance/IA y de seguridad por revelar movimientos/stats del rival.

## Restricciones

- No crear, modificar ni ejecutar tests.
- No añadir ni actualizar dependencias.
- No hacer `git push` ni operaciones destructivas.
- No tocar `bugs.txt`, `.opencode/work/TASK-032/` a `TASK-040/` ni los cambios de TASK-035.
- No cambiar `SESSION_VERSION` ni `BattleSessionService` sin decisión explícita.
- Sin migraciones ni cambios de esquema.
- Mantener el comportamiento del jugador y del resto de la IA salvo la mejora solicitada.

## Decisiones aclaradas con el desarrollador (2026-11-09)

1. **Visibilidad del tooltip:** solo en **modo debug** (`APP_DEBUG=true`). En producción no se envía ni se muestra información del rival.
2. **Contenido:** movimientos, stats actuales/máximas, tipos, habilidad y objeto.
3. **Interacción:** **solo hover** (sin tap ni foco de teclado). Se acepta la limitación en móvil/teclado porque es una herramienta de debug.
4. **IA:** se debe corregir la política de prioridades para que no elija un movimiento inefectivo solo por tener prioridad; debe evaluar tipo/daño/valor como la acción normal.
