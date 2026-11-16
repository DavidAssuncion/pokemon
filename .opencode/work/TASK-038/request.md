# TASK-038 — Petición

- **Fecha:** 2026-11-09
- **Origen:** desarrollador, sesión de OpenCode.
- **Estado:** RECEIVED → CLARIFYING

## Texto literal

> --- Ronda 7 ---
> riolu pierde escudo por presion
> machoke pierde escudo por presion
> giratina-altered sufre 10.6875 de daño por tormenta de arena
> riolu pierde escudo por presion
> machoke pierde escudo por presion
> mewtwo Restos recuperan 6.4375 PS
> mewtwo recupera 6 de DEF por Bucle Arena
> mewtwo sufre 6.4375 de daño por tormenta de arena
> riolu sufre 5.3125 de daño por tormenta de arena
> machoke sufre 8.0625 de daño por tormenta de arena
>
> porque riolu o machoke no atacan?

## Interpretación inicial

El jugador observa que Riolu y Machoke pierden escudo por la habilidad Presión y sufren daño de tormenta de arena, pero no ve líneas de ataque suyas en el fragmento de log. Pregunta por qué no atacan.

## Contexto técnico conocido

- `presion` está registrada como `EfectoDanioFinTurno` con `ObjetivoEfecto::ESCUDOS_ENEMIGOS`, 12,5 % y `afectaEscudos=true` (`app/Providers/BattleEffectServiceProvider.php:196`).
- Ese efecto se ejecuta en `onRoundEnd`, no al inicio del turno.
- `GestorCicloTurnosBatalla::avanzarRonda()` ejecuta `triggerRoundEndEffects()`, inicia la ronda y añade `--- Ronda N ---` al log; los efectos de fin de ronda pueden aparecer después del encabezado al volcarse el log.
- Las acciones de la ronda deberían aparecer después de las líneas de transición (Presión/tormenta).
- `GestorCicloTurnosBatalla` puede saltar un actor por estado (`puedeActuar()`), postura (`Cubrir`/`Guardia`) o porque no esté en la cola; en esos casos hay logs específicos (“no puede actuar”, “mantiene la postura…”).
- `GestorAccionBatalla::prepararAnimacionIa()` puede retornar sin log si `elegirAccionPara()` o `elegirObjetivoPara()` devuelven `null`; es una vía a revisar.
- `TASK-033` añadió soporte a aliados en retaguardia; `TASK-036` añadió autoobjetivo de Bucle Arena; `TASK-037` añadió su regeneración por ronda.

## Preguntas para aclarar

1. ¿Riolu y Machoke son del equipo del jugador o del rival?
2. ¿Qué líneas aparecen **después** de la tormenta de arena en la ronda 7? ¿Hay “usa …”, “no puede actuar”, “mantiene la postura…”, “pasa turno” o similares?
3. ¿La ronda 7 estaba esperando la preparación/acción del jugador o ya había terminado?
4. ¿El combate es manual, automático o IA vs IA?
5. ¿Ocurre en todas las rondas o solo en algunas? ¿Afecta a todos los Pokémon o solo a Riolu/Machoke?
6. ¿Riolu/Machoke están en vanguardia o retaguardia? ¿Tienen movimientos con PP? ¿Están bajo algún estado o postura?

## Restricciones

- No crear, modificar ni ejecutar tests.
- No añadir ni actualizar dependencias.
- No hacer `git push` ni operaciones destructivas.
- No tocar `bugs.txt`, `.opencode/work/TASK-032/` a `TASK-037/` ni los cambios de TASK-035.
- No cambiar `SESSION_VERSION` ni `BattleSessionService` sin decisión explícita.
- Sin migraciones ni cambios de esquema.
