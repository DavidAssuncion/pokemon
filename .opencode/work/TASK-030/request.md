# TASK-030 — Petición

- **Fecha:** 2026-11-07
- **Origen:** cierre de TASK-029 (análisis-only) y respuesta del desarrollador: «Adelante con los primeros».
- **Petición literal:** «Adelante con los primeros».
- **Interpretación:** diseñar e implementar el primer alcance del modo aliado, limitado a los **9 movimientos** solicitados originalmente.

## Alcance funcional

| Movimiento | slug | Uso sobre aliado | Uso propio actual |
|---|---|---|---|
| Bucle Arena | `sand-tomb` | Restaura escudo físico (`defensaHpActual`) | Daño + atrapado, sin cambio |
| Giro Fuego | `fire-spin` | Restaura escudo especial (`defensaEspHpActual`) | Daño + atrapado, sin cambio |
| Torbellino | `whirlpool` | Aplica `AQUA_RING` durante 5 rondas | Daño + atrapado, sin cambio |
| Maquinación | `nasty-plot` | +2 Ataque Especial al aliado | +2 Ataque Especial propio, sin cambio |
| Paz Mental | `paz_mental` | +1 Ataque Especial y +1 Defensa Especial al aliado | Buffs propios, sin cambio |
| Danza Espada | `swords-dance` | +2 Ataque al aliado | +2 Ataque propio, sin cambio |
| Danza Dragón | `dragon-dance` | +1 Ataque y +1 Velocidad al aliado | Buffs propios, sin cambio |
| Recuperación | `recover` | Cura 50% del PS máximo al aliado | Cura 50% propia, sin cambio |
| Corpulencia | `bulk-up` | +1 Ataque y +1 Defensa al aliado | Buffs propios, sin cambio |

## Reglas decididas

- **Duración:** 5 rondas completas para efectos persistentes (buffs de stats y `AQUA_RING`).
- **Curas y escudos:** instantáneos. No se crea un «escudo temporal» en este primer alcance.
- **Modo propio:** se mantiene el comportamiento actual; la duración de 5 rondas también aplica al modo propio.
- **Torbellino:** `AQUA_RING` persistente, 1/16 del PS por ronda, durante 5 rondas.
- **Alcance:** sin IA sobre aliados, sin curación de estados, sin curas de salud adicionales ni boosts adicionales.
- **Sin migraciones ni cambios de `SESSION_VERSION`** salvo que el diseño demuestre lo contrario y se autorice.
- **Contrato público de `Combate` congelado:** 32 propiedades y 18 métodos públicos.
- **Sin tests:** no se crean, modifican ni ejecutan.
- **Sin `git push`.**

## Decisiones abiertas para el diseño

1. **Porcentaje de restauración de escudo** para Bucle Arena/Giro Fuego: ¿50% del máximo, restauración completa u otro? El análisis de TASK-029 no fija un valor.
2. **Elegibilidad del aliado:** ¿cualquier aliado vivo del equipo, incluido retaguardia, o solo adyacente/vanguardia?
3. **Flujo UI:** cómo se elige al aliado después de seleccionar el movimiento sin romper el flujo actual de selección de rival.
4. **Catálogo:** mapa explícito `slug → comportamiento aliado` en dominio (sin migración) o campo nuevo en `moves` (con migración).
5. **Log/eventos:** cómo reflejar el uso sobre aliado con los eventos existentes.

## Estado

`RECEIVED` — pendiente de diseño.
