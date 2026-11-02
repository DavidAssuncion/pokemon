# TASK-002 — Aprobación

- **ID de tarea**: TASK-002
- **Versión aprobada**: plan v1
- **Estado**: `APROBADO`
- **Texto literal de la aprobación**: «Aprobar plan v1»
- **Fecha**: 2026-11-01
- **Alcance autorizado**: plan v1 completo — bugfix Dragoaliento (contrato `bool`, log/evento por
  delta real), tests de regresión, arnés IA vs IA determinista (`maxRondas` + observador), campaña
  de diagnóstico autogateada, revisión y QA. Quedan fuera: guarda de estado previo, correcciones
  automáticas de bugs encontrados, comando artisan, tope de rondas en producción y cualquier
  archivo del WIP local.

## Condiciones

- No tocar `src/Battle/Domain/{Combatiente,DatosPokemonBatalla,EquipoBatalla}.php` ni el WIP de
  Sendas/Equipos.
- No instalar dependencias, no tocar `.env`, no ejecutar operaciones git destructivas.
- Cualquier ampliación de alcance requiere plan v2 y nueva aprobación.
