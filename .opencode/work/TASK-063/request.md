# TASK-063 — Petición

- **ID:** TASK-063
- **Fecha:** 2026-11-19
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** corrección de bug en el motor de combate (turnos).
- **Estado:** RECEIVED.

## Texto literal

> todos los pokemon cuando matan en combate consiguen un turno extra, no deberia ser asi, solo los de algunas habilidades

Referencia previa en `bugs.txt` (segunda línea):

> Parece que todos al matar ganan un turno extra, esto no debe ser asi.

## Interpretación inicial

En el motor de combate, al debilitar (KO) a un rival, el atacante recibe un turno
extra de forma global. El comportamiento correcto es que ese turno extra solo lo
obtengan los Pokémon cuya habilidad lo conceda; el resto no debe repetir turno.

## Alcance previsto

- Localizar y corregir la condición que otorga el turno extra tras un KO.
- Identificar qué habilidad(es) deben conservar ese efecto y cómo están modeladas.
- Ajustar únicamente la lógica necesaria; sin cambios de UI salvo que el análisis
  demuestre que el mensaje o el flujo lo requieren.

## Restricciones conocidas

- No se crean, actualizan ni ejecutan tests.
- No se sobrescriben los cambios locales preexistentes (TASK-061 en curso y
  modificaciones sin commitear en `master`).
- Sin `git push` ni operaciones destructivas.
- Si el conjunto de habilidades que deben conservar el turno extra no está
  definido en código o documentación, se preguntará al desarrollador antes de
  implementar.
