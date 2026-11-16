# TASK-047 — Arquitectura

No aplica: la implementación es un seeder de datos sobre una columna y un contrato
ya existentes. No hay cambios de arquitectura, contratos ni capas. El diseño técnico
y el algoritmo están en `plan.md` v1.

Se descarta delegar en `architect` porque no se introduce ningún componente nuevo:
el seeder consume `pokemon_habitat`, `pokemon` y `pokemon_stats`, y escribe el JSON
que `ConfiguracionMazmorra` ya sabe leer.
