# TASK-060 — Arquitectura

**No aplica.**

El análisis concluye que no hay decisiones arquitectónicas nuevas: se añade un método
local en `StatsCombatiente`/`Combatiente`, un campo derivado de vista (`statsJefe`) y se
ajustan tres fórmulas de `ManejadorDanioBase`. No cambian capas, contratos externos,
persistencia ni infraestructura. El diseño concreto se detalla en `plan.md` v1.
