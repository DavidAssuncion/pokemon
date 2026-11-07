# TASK-029 — Informe final

- **Estado:** `COMPLETED` (análisis-only)
- **Fecha:** 2026-11-07
- **Alcance:** análisis del requisito de movimientos usables sobre aliados y de la duración de 5 rondas. Sin código, tests, migraciones ni cambios de esquema.
- **Plan:** no aplica; el desarrollador eligió «Solo análisis».

## 1. Decisiones del desarrollador

- **Alcance:** solo análisis; no se autoriza diseño ni implementación por ahora.
- **Torbellino sobre aliado:** `AQUA_RING` persistente, 1/16 del PS por ronda, como Acua Aro.
- **Duración:** 5 **rondas** completas, no 5 turnos individuales.
- **Aplicación:** también al modo propio actual, no solo al modo aliado.
- **Efectos afectados:** los decide el análisis (persistentes vs instantáneos).

## 2. Resumen

Se inventariaron los 673 movimientos del catálogo runtime (`storage/data/moves.json`) y se analizó el modelo de objetivos, efectos, escudos, duraciones y serialización. El dominio ya declara objetivos de aliado, pero no existe hoy un buff o cura dirigido a un aliado concreto. La duración de 5 rondas ya está implementada para los boosts de stats; el único persistente que no caduca es `AQUA_RING`.

## 3. Hallazgos principales

- **Objetivos:** `ObjetivoMovimiento` incluye `ADJACENT_ALLY`, `ADJACENT_ALLY_OR_SELF`, `ALLIES`, `ALLY_SIDE` y `ALLY_TEAM`, pero `MovimientoBatalla::esAutodirigido()` y cuatro llamadores de Livewire/IA fuerzan `defender = actor`.
- **Buffs:** `GestorEfectosMovimiento::aplicarStatChangesAMuchos()` aplica los `self_effects` siempre al actor; `STATS_ALIADO` existe en `ClaveEfectoMovimiento` pero no tiene ejecutor registrado.
- **Escudos:** `defensaHpActual` es el escudo físico y `defensaEspHpActual` el especial. `curarBarreras()` cura ambos; no existe un primitivo para curar solo uno ni un «escudo temporal».
- **Catálogo:** Bucle Arena (`sand-tomb`), Giro Fuego (`fire-spin`) y Torbellino (`whirlpool`) son ataques de trampa con `partiallytrapped`; el catálogo no puede expresar dos efectos según el objetivo.
- **Duraciones:** `MultiplicadoresStats::TURNOS_TEMPORALES = 5` ya aplica 5 rondas a los boosts de stats. `AQUA_RING` se crea con `turnos=0` y no se descuenta, por lo que es permanente.
- **Serialización:** `CondicionVolatil::turnos` y `MultiplicadoresStats::turnos` ya se serializan. El cambio a 5 rondas no requiere migración ni subir `SESSION_VERSION`.
- **Efectos de equipo:** Pantalla/Reflejo duran 10 (20 con Pantallista), Tailwind 4, Velo Sagrado/Neblina 5. No deben forzarse a 5.

## 4. Listado de candidatos

El inventario completo está en `analysis.md` §4 y en el addendum §9. Resumen:

### Curación instantánea
Recuperación (`recover`), Respiro (`roost`), Sol Matinal (`morning-sun`), Fotosíntesis (`synthesis`), Luz Lunar (`moonlight`), Ovocuración (`soft-boiled`), Batido (`milk-drink`), Relajo (`slack-off`), Auxilio (`heal-order`) y Recogearena (`shore-up`). Descanso (`rest`) se excluye del MVP por añadir sueño.

### Persistente
- Torbellino (`whirlpool`) → `AQUA_RING` al aliado, 5 rondas.
- Acua Aro (`aqua-ring`) → referencia del efecto.

### Escudos
- Bucle Arena (`sand-tomb`) → restaurar `defensaHpActual`.
- Giro Fuego (`fire-spin`) → restaurar `defensaEspHpActual`.
- No existe movimiento que cure solo un escudo; la restauración es instantánea en el modelo actual.

### Boosts de stats (5 rondas ya vigentes)
- **Solicitados:** Maquinación (`nasty-plot`), Paz mental (`paz_mental`), Danza Espada (`swords-dance`), Danza Dragón (`dragon-dance`), Corpulencia (`bulk-up`).
- **Adicionales:** Desarrollo, Meditación, Agilidad, Pulimento, Aligerar, Fortaleza, Refugio, Rizo Defensa, Barrera, Armadura Ácida, Defensa Férrea, Retracción, Rizo Algodón, Amnesia, Carga, Luminicola, Masa Cósmica, A Defender, Aura Magnética, Afilar, Afilagarras, Avivar, Enrosque, Danza Aleteo, Cambio de Marcha, Geocontrol, Danza Triunfal, Bálsamo Osado, Bastión Final, Foco Energía y Aguzar.

### Soporte y estados
Alivio (`refresh`), Purificación (`purify`), Pulso Cura (`heal-pulse`), Cura Floral (`floral-healing`), Niebla Aromática (`aromatic-mist`), Piñón Auxiliar (`gear-up`), Motivación (`coaching`), Aullido (`howl`), Gota Vital (`life-dew`), Cura Selvática (`jungle-healing`), Plegaria Lunar (`lunar-blessing`), Bramido Dragón (`dragon-cheer`) y Acupresión (`acupressure`).

### Excluidos
Tambor, Deslome, Estruendo Escama, Rompecoraza, Doble Equipo, Reducción, Protección, Detección, Aguante, Sustituto, Levitón y los efectos de campo/clima/terreno con duración propia.

## 5. Duración de 5 rondas

- **Boosts de stats:** ya duran 5 rondas; no requieren cambios en modo propio ni en modo aliado.
- **`AQUA_RING`:** pasa de permanente a 5 rondas. Se reutiliza `CondicionVolatil::turnos` y se añade su descuento en `ProcesadorCondicionesRonda::aplicarContadores`, después de la cura para obtener 5 curas.
- **Curas de PS, curación de estados y restauración puntual de escudos:** instantáneas; no tienen duración en el modelo actual.
- **Escudo temporal:** no existe; si se quiere que el escudo restaurado caduque a las 5 rondas, hay que crear una condición de escudo bonus/regeneración. Es una decisión pendiente para un futuro diseño.
- **Efectos de equipo:** mantienen sus duraciones propias (10/4/5/5). No se fuerza la regla de 5.
- **Serialización:** sin migración ni `SESSION_VERSION`. Las sesiones antiguas con `AQUA_RING` y `turnos=0` seguirían siendo permanentes hasta reaplicar el movimiento; invalidarlas exigiría subir `MINIMUM_COMPATIBLE_SESSION_VERSION`, no solo `SESSION_VERSION`.

## 6. Impacto técnico

- `MovimientoBatalla::esAutodirigido()` es un interruptor global; cambiarlo afecta a buffs propios, curas y efectos de equipo. Para el modo aliado conviene un predicado separado.
- Los `self_effects` se aplican siempre al actor; buffear a un aliado requiere un ejecutor o parámetro dedicado.
- El catálogo no puede expresar dos efectos según el objetivo; la alternativa sin migración es un mapa `slug → comportamiento aliado` en dominio.
- `EjecutorAquaRing` hardcodea al atacante; para Torbellino sobre aliado debe recibir el objetivo.
- `Purificar` cura al atacante pese a su target `adjacentAlly`; es un defecto preexistente a corregir si se toca esa zona.
- `AplicadorCuracion` solo cura si `attacker === defender`; la cura a aliado por esa vía requiere cambios.
- La UI actual solo marca como seleccionables a los rivales (`teamIdx === 1`); permitir aliados exige cambios en `BatallaVistaPresenter` y en el JS/Blade.
- La IA solo genera acciones contra enemigos; el soporte a aliados queda fuera de la primera fase.
- `AQUA_RING` y los boosts ya exponen `turnos`/`buffTurns` en `CombatienteVista`; el badge de condiciones ya muestra los turnos, pero los buffs de stats no se pintan.

## 7. Decisiones pendientes para un futuro diseño

1. **Escudos:** restauración instantánea (recomendada) o escudo temporal de 5 rondas (requiere modelo nuevo).
2. **Objetivo aliado:** cualquier aliado vivo del equipo o solo adyacente/vanguardia.
3. **Catálogo:** mapa en dominio sin migración (recomendado) o campo nuevo en `moves` con migración.
4. **IA:** si debe usar estos movimientos sobre aliados o queda fuera de la primera fase.
5. **Pantallas/Tailwind:** confirmar que mantienen sus duraciones propias y no se fuerzan a 5.
6. **Sesiones antiguas:** decidir si se invalidan las que tengan `AQUA_RING` permanente.

## 8. Verificación

- **Análisis estático únicamente.** No se ejecutaron tests del proyecto.
- **Sin cambios de código de aplicación, vistas, JS, rutas, migraciones ni configuración.**
- **Sin migraciones ni cambios de esquema.**
- **Sin `git push`.**
- El working tree contiene solo artefactos de `.opencode/work/TASK-029/`.

## 9. Riesgos residuales

- `esAutodirigido()` como interruptor global.
- `self_effects` siempre aplicados al actor.
- Catálogo incapaz de expresar dos efectos por objetivo.
- `STATS_ALIADO` sin ejecutor y `Purificar` curando al atacante.
- `AQUA_RING` permanente en sesiones antiguas.
- Falta de log/evento de expiración de buffs y condiciones.
- Conflicto potencial con las duraciones propias de Pantalla, Reflejo, Tailwind, Velo Sagrado y Neblina.
- Sin verificación runtime; todo el análisis es estático.

## 10. Próximos pasos

1. Si se autoriza diseño, partir de `analysis.md` y resolver las decisiones pendientes.
2. Delegar en `architect` el diseño de dominio, UI/JS y serialización.
3. Redactar plan y solicitar aprobación explícita con versión.
4. No implementar hasta la aprobación.

## 11. Artefactos

- `request.md`
- `analysis.md` (incluye el addendum de duración de 5 rondas)
- `architecture.md` — no aplica
- `plan.md` — no aplica
- `approval.md` — no aplica
- `tasks.md`
- `execution.md`
- `review.md` — no aplica
- `security.md` — no aplica
- `final-report.md` (este documento)
