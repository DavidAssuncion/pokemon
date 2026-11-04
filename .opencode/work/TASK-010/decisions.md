# TASK-010 — Decisiones

## Plan v2 (pendiente de aprobación)

| ID | Decisión | Estado |
|----|----------|--------|
| D1 | «1 año» = `SESSION_LIFETIME=525600` minutos de inactividad deslizante, no caducidad absoluta. | Asumida |
| D2 | Solo se cambia el seeder; sin migración ni backfill de nombres existentes. | Indicada por el desarrollador |
| D3 | No se toca el CSV ni se re-ejecuta el seeder; las 38 formas Alola/Galar quedan fuera. | Asumida |
| D4 | Los 59 assets shiny no se generan en v2; se entrega `missing-shiny.md` para definirlos. | Indicada por el desarrollador |
| D5 | Eventos y generación de shiny salvaje en exploraciones fuera de alcance. | Asumida |
| D6 | `x-sprite` es el único componente de sprite. | Aceptada |
| D7 | Seguridad obligatoria; reviewer solo si se solicita. | Aceptada |
| D8 | Sin tests. | Aceptada |
| D9 | Regresión `turn-bar` reportada, no incluida. | Aceptada |
| D10 | No se cambia `SESSION_SECURE_COOKIE` en local; recomendación para producción. | Asumida |

## Historial

- **2026-11-04:** el desarrollador indicó «Una vez cambiado en el seeder no aplica mas correccion» → se elimina el backfill de v1.
- **2026-11-04:** el desarrollador pidió «indícame que shiny faltan» y aclaró que quiere definir más esa parte → se entrega `missing-shiny.md` y se deja la generación de assets fuera de v2.
