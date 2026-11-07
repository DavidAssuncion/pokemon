# TASK-031 — Petición

- **Fecha:** 2026-11-07
- **Origen:** respuesta del desarrollador a la pregunta de siguiente paso: «2-3 y 4».
- **Interpretación:** analizar y planificar tres frentes de la siguiente ola del modo aliado:
  1. **Curas y buffs adicionales.**
  2. **Curación de estados, efectos de equipo y `STATS_ALIADO`.**
  3. **IA y prioridades con objetivo aliado.**
- **No implementar todavía:** primero análisis, diseño, plan y aprobación explícita.

## Frente 2 — Curas y buffs adicionales

### Curas de PS
Respiro (`roost`), Sol Matinal (`morning-sun`), Fotosíntesis (`synthesis`), Luz Lunar (`moonlight`), Ovocuración (`soft-boiled`), Batido (`milk-drink`), Relajo (`slack-off`), Auxilio (`heal-order`), Recogearena (`shore-up`).

### Buffs de stats
Agilidad (`agility`), Pulimento (`rock-polish`), Aligerar (`autotomize`), Fortaleza (`harden`), Refugio (`withdraw`), Rizo Defensa (`rizo_defensa`), Barrera (`barrier`), Armadura Ácida (`acid-armor`), Defensa Férrea (`iron-defense`), Retracción (`shelter`), Rizo Algodón (`cotton-guard`), Amnesia (`amnesia`), Carga (`charge`), Luminicola (`tail-glow`), Masa Cósmica (`cosmic-power`), A Defender (`defend-order`), Aura Magnética (`magnetic-flux`), Afilar (`sharpen`), Afilagarras (`hone-claws`), Avivar (`work-up`), Enrosque (`coil`), Danza Aleteo (`quiver-dance`), Cambio de Marcha (`shift-gear`), Geocontrol (`geomancy`), Danza Triunfal (`victory-dance`), Bálsamo Osado (`take-heart`), Bastión Final (`no-retreat`).

### Crítico
Foco Energía (`focus-energy`), Aguzar (`laser-focus`).

### Casos especiales
- Bálsamo Osado cura estado además de buffear.
- Bastión Final incluye `SIN_RETIRADA`, hoy permanente.
- Algunos movimientos tienen efectos adicionales o restricciones que deben revisarse.

## Frente 3 — Estados, equipo y `STATS_ALIADO`

- Alivio (`refresh`): cura estado propio; propuesto para aliado.
- Purificación (`purify`): ya declara `adjacentAlly`, pero el ejecutor cura al atacante; corregir.
- Aromaterapia (`aromatherapy`) y Cascabel Cura (`heal-bell`): ya son de equipo.
- Gota Vital (`life-dew`), Cura Selvática (`jungle-healing`), Plegaria Lunar (`lunar-blessing`), Bramido Dragón (`dragon-cheer`): efectos de equipo.
- Aullido (`howl`), Piñón Auxiliar (`gear-up`), Motivación (`coaching`), Niebla Aromática (`aromatic-mist`): `allies`/`adjacentAlly` con `STATS_ALIADO` sin ejecutor.
- Acupresión (`acupressure`): aliado o self, boost aleatorio.
- `ClaveEfectoMovimiento::STATS_ALIADO` existe pero no está registrado en `ColeccionEjecutoresMovimiento`.

## Frente 4 — IA y prioridades

- `SelectorAccionIA`: hoy genera acciones solo contra rivales.
- `EvaluadorAccionIA`/`CalculadoraDanioIA`: valorar acciones de soporte sobre aliados.
- `GestorPrioridadesBatalla`: hoy no permite seleccionar aliado; revisar si debe hacerlo.
- Contrato de `prioridades` y `Combate`: impacto y compatibilidad.
- Evitar que la IA desperdicie curas/buffs o que los use con objetivos inválidos.

## Restricciones

- No implementar sin plan aprobado con versión.
- No crear, modificar ni ejecutar tests.
- No `git push`.
- No cambiar dependencias, migraciones, rutas ni esquema sin autorización.
- No tocar `SESSION_VERSION` ni `BattleSessionService` sin decisión explícita.
- Mantener el contrato público de `Combate` (32 propiedades/18 métodos).
- No romper el modo propio ni el modo rival.

## Estado

`RECEIVED` — pendiente de análisis.
