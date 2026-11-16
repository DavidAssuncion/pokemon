# TASK-040 — Petición

- **Fecha:** 2026-11-09
- **Origen:** desarrollador, sesión de OpenCode.
- **Estado:** RECEIVED → ANALYZING

## Texto literal

> solo tiene aguante riolu?
>
> [log de la ronda 8-9: Riolu usa Aguante como prioridad y el sistema responde “riolu no puede volver a aguantar en rondas consecutivas” en rondas consecutivas; Machoke usa Ultrapuño y luego queda paralizado; Mewtwo/Giratina retrasan turno; Tyranitar usa Bucle Arena sobre Mewtwo; Mewtwo usa Recuperación sobre Tyranitar; Giratina usa Sombra Vil sobre Machoke]

## Interpretación

Riolu no se limita a Aguante: tiene Golpe Roca, Garra Metal, Onda Vacío, Ataque Rápido, Aguante y Avivar. La IA rival declara Aguante como movimiento de prioridad cada ronda; el ejecutor de Aguante bloquea el uso consecutivo y, al hacerlo, actualiza `ultimaRondaProteccion` con la ronda actual, de modo que el siguiente intento vuelve a considerarse consecutivo y Riolu queda atrapado intentando Aguante sin usar sus otros movimientos.

## Hipótesis a verificar

1. `GestorPrioridadesBatalla::declararIa()` elige el movimiento disponible con mayor `priority` sin comprobar la restricción de rondas consecutivas de Aguante/Protección.
2. `EjecutorAguante::ejecutar()` actualiza `ultimaRondaProteccion` **antes** de comprobar si el intento es válido; en el camino bloqueado también la actualiza, perpetuando el bloqueo.
3. `EjecutorProteccion::ejecutar()` tiene el mismo patrón para Protección/Detección.
4. La IA no tiene un fallback que elija el siguiente movimiento de prioridad (p. ej. Ataque Rápido) cuando Aguante está restringido.

## Preguntas para el análisis

1. ¿Se confirma la causa raíz en `GestorPrioridadesBatalla::declararIa()`, `EjecutorAguante` y `EjecutorProteccion`?
2. ¿El alcance debe incluir Aguante y Protección/Detección, o solo Aguante?
3. ¿La IA debe saltar Aguante/Protección si `ultimaRondaProteccion === ronda - 1` y elegir el siguiente movimiento de prioridad o su acción normal?
4. ¿El ejecutor debe actualizar `ultimaRondaProteccion` solo cuando el uso es válido, o centralizar la comprobación en un predicado de dominio (`puedeUsarProteccion()`)?
5. ¿Debe mantenerse el log “no puede volver a aguantar en rondas consecutivas” cuando se intente? ¿O la IA debe evitar el intento y no generar ese log?
6. ¿Afecta a la jugabilidad del jugador (puede declarar Aguante manualmente en preparación)? ¿La validación de preparación debe impedir declarar Aguante si está restringido?
7. ¿Hay que tocar serialización/`SESSION_VERSION`? `ultimaRondaProteccion` ya se serializa.
8. ¿Qué casos límite hay: uso en rondas alternas, Protected/Detect, muerte del portador, cambio de ronda, IA vs jugador, sesión antigua?
9. ¿Qué verificación estática y manual se requiere, sin tests?

## Restricciones

- No crear, modificar ni ejecutar tests.
- No añadir ni actualizar dependencias.
- No hacer `git push` ni operaciones destructivas.
- No tocar `bugs.txt`, `.opencode/work/TASK-032/` a `TASK-039/` ni los cambios de TASK-035.
- No cambiar `SESSION_VERSION` ni `BattleSessionService` sin decisión explícita.
- Sin migraciones ni cambios de esquema.
- Mantener el resto de la IA y de las prioridades.
