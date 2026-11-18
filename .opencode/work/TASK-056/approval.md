# TASK-056 — Aprobación

- **ID de tarea:** TASK-056
- **Estado:** APROBADO v1 y v2
- **Plan v1:** aprobado el 2026-11-17
- **Texto literal de la aprobación v1:** `Aprobar plan v1`
- **Plan v2:** aprobado el 2026-11-18
- **Texto literal de la aprobación v2:** `Aprobar plan v2`

## Vigencia

La aprobación v1 sigue cubriendo **F2 y F3** tal como se describen en este archivo. El ajuste de
presentación solicitado el 2026-11-18 (una tarjeta por familia + modal de craft con pestañas) está
fuera de v1 y requiere la aprobación de v2 antes de modificar la vista.

## Alcance autorizado

1. **F1 — Forja:** dominio de piezas, `ClavePieza`, `CategoriaItem` (+3 casos), carve-out de
   whitelist del inventario, puerto `NidosConstruidos` + adaptador en Granjas, `ListarPiezasForja`,
   `FabricarPieza`, contrato aditivo de `GET /api/forja/recetas`, `POST /api/forja/fabricar` sin
   cambio de body, UI de `resources/views/forja/index.blade.php` y `src/Forja/context.md`.
2. **F2 — Equipamiento:** migración `recruit_gear`, modelo, VO `EquipamientoPiezas`, casos de uso
   `EquiparPieza`/`DesequiparPieza`/`ObtenerPiezas`, endpoints con anti-IDOR,
   `AplicadorBonusPiezas`, integración en `MapeadorPokemonBatalla`/`ConstruirEquipoJugador`/
   `CombateExploracion`, `piezas[]` en el serializer y CP con piezas.
3. **F3 — UI de equipos (condicionada):** partial `piezas-reclutado.blade.php`, un `@include` en
   `modal-detalle-pokemon.blade.php`, CP visible y assets opcionales. **No puede empezar** hasta que
   el desarrollador confirme que el WIP de `resources/views/equipos/*` y `resources/css/*` está
   consolidado o revertido.

## Decisiones incluidas

Las decisiones D1-D12 del `plan.md` v1 y `architecture.md` §10.6, con los valores recomendados.

## Exclusiones

- Tests: no se crean, modifican ni ejecutan.
- Dependencias nuevas.
- Unificar `CatalogoItems`/`CatalogoObjetos`/`ClaveItem`.
- Modelo de instancias o niveles de pieza más allá de la etapa.
- Modificar los cambios locales preexistentes de TASK-049 a TASK-055.
- Tocar `resources/views/equipos/index.blade.php`, `partials/panel-equipos.blade.php`,
  `partials/modal-editar-equipo.blade.php`, `src/Battle/Domain/CombatienteVista.php` o
  `resources/css/*` antes de la condición de F3.
