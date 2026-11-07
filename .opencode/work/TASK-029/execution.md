# TASK-029 — Ejecución

## Estado

`COMPLETED` — análisis-only completado. Sin diseño ni implementación.

## Decisiones del desarrollador

- **Alcance:** solo análisis; no se autoriza diseño ni implementación por ahora.
- **Torbellino sobre aliado:** `AQUA_RING` persistente (1/16 del PS por ronda), como Acua Aro.
- **Duración:** 5 **rondas** completas.
- **Aplicación:** también al modo propio actual, no solo al modo aliado.
- **Efectos afectados:** los decide el análisis (persistentes vs instantáneos).

## Petición adicional

El desarrollador pide, además del análisis, un **listado adicional de posibles movimientos** usables sobre aliados, basado en el catálogo real del proyecto.

**Resuelta:** `analysis.md` §4 incluye el listado adicional agrupado por curación, escudos, boosts de stats y estados.

## Resultado del análisis

- Catálogo runtime: `storage/data/moves.json` (673 entradas).
- No existe ningún movimiento que cure solo escudo físico o solo escudo especial; Bucle Arena/Giro Fuego son propuesta de diseño.
- `MovimientoBatalla::esAutodirigido()` fuerza `defender = actor` en Livewire e IA; los `self_effects` se aplican siempre al actor.
- `STATS_ALIADO` existe en el enum pero no tiene ejecutor; `EjecutorAquaRing` hardcodea al atacante y `Purificar` cura al atacante.
- Recomendación: MVP acotado, sin migraciones y sin cambiar `SESSION_VERSION`.

## Resultado del addendum de duración

- Los boosts de stats **ya duran 5 rondas** (`MultiplicadoresStats::TURNOS_TEMPORALES = 5` y descuento en `MotorRondas`). No requieren cambios.
- `AQUA_RING` es el único persistente que hoy no caduca: puede pasar a 5 rondas usando `CondicionVolatil::turnos` y añadiendo su descuento en `ProcesadorCondicionesRonda`.
- Curas de PS, curación de estados y restauración puntual de escudos son **instantáneas**; el modelo no soporta un «escudo temporal» sin crear una condición nueva.
- Los efectos de equipo (Pantalla 10, Tailwind 4, Velo Sagrado/Neblina 5) quedan fuera de la regla de 5 rondas.
- No hace falta migración ni subir `SESSION_VERSION`; `turnos` ya se serializa en `CondicionVolatil` y `MultiplicadoresStats`.

## Preflight

- Proyecto: `/home/david/pokemon`
- Rama: `task-011`
- HEAD al inicio: `8e60509` (TASK-028)
- Commit de registro de TASK-029: `51277ff`
- Working tree al iniciar el análisis: limpio
- Tests: no se ejecutan

## Siguiente paso

Análisis cerrado. Si en el futuro se autoriza diseño, decidir antes:

1. **Escudos:** restauración instantánea (recomendada) o escudo temporal de 5 rondas (requiere modelo nuevo).
2. **Objetivo aliado:** cualquier aliado vivo del equipo o solo adyacente/vanguardia.
3. **Catálogo:** mapa en dominio sin migración (recomendado) o campo nuevo en `moves` con migración.
4. **IA:** si debe usar estos movimientos sobre aliados o queda fuera de la primera fase.
5. **Pantallas/Tailwind:** confirmar que mantienen sus duraciones propias y no se fuerzan a 5.
