# TASK-065 — Petición

- **ID:** TASK-065
- **Fecha:** 2026-11-19
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** cambio de mecánica de habilidad + marca visual en combate.
- **Estado:** RECEIVED.

## Texto literal

> Cambio en habiliad Ausente de slaking, marca visual de que estara ausente. Restaura 1/16 de escudos si no actua. Si recibe daño mientras iba a esta ausente, se enfada, cancela el efecto y aumenta 1 nivel su ataque. (La estrategia es esperar a que piedra el turno para atacarle)

## Interpretación inicial

Rediseño de la habilidad **Ausente** (Truant, Slaking):

1. **Marca visual** de que el Pokémon estará ausente en su siguiente turno
   (indicador en combate, no solo el log).
2. **Restauración:** si no actúa (turno ausente), recupera 1/16 de "escudos".
3. **Reacción al daño:** si recibe daño mientras iba a estar ausente, se enfada,
   cancela el efecto de ausencia y sube 1 nivel de Ataque.
4. **Intención de diseño:** el rival puede esperar a que pierda el turno para
   atacarle; recibir daño en ese momento le permite reaccionar.

## Dudas a resolver en el análisis

- Qué son exactamente los "escudos" en el modelo actual (PS, escudo de
  `Pantallista`, otro recurso) y cómo se redondea 1/16.
- Momento exacto de la restauración y del enfado (inicio/fin del turno ausente,
  antes/después del daño).
- Si al cancelar la ausencia el Pokémon puede actuar inmediatamente ese mismo
  turno o solo evita la pérdida y gana el Ataque.
- Formato de la marca visual existente para estados (badge, icono, tooltip) y
  dónde se renderiza.
- Interacción con `Protección`/`Aguante`, prioridad, multiobjetivo y efectos que
  dañan fuera del turno (clima, veneno, retroceso).

## Restricciones conocidas

- No se crean, actualizan ni ejecutan tests.
- No se sobrescriben los cambios locales preexistentes (TASK-061, TASK-063,
  TASK-064 y demás trabajo sin commitear).
- Sin `git push` ni operaciones destructivas.
- Si el diseño de la mecánica sigue ambiguo tras el análisis, se preguntará al
  desarrollador antes de implementar.
