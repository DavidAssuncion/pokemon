# TASK-060 — Seguridad

**No aplica.**

Cambios de presentación (chips de la tarjeta) y de cálculo de daño en batalla. No se toca
autenticación, autorización, entrada de usuario, SQL, archivos, sesiones, cookies, secretos,
dependencias ni datos personales. No se exponen datos nuevos: `statsJefe` deriva de
`BattleStats::multiplicadorStats()` del propio combatiente y sustituye a `bonusStatsJefe`.

El único efecto de seguridad potencial —manipulación de payload— no cambia: el multiplicador
del jefe se fija en servidor (`GeneradorJefeMazmorra`) y no proviene de la petición.
