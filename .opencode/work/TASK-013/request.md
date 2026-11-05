# TASK-013 — Solicitud

## Petición literal

> Generamos otro modal de seleccion de baya, y se organiza por las categorias anteriormente dichas, mostrando primero las que tenemos disponibles y un buscador. Tambien, las granjas deben mostrar los pokemon de la familia como tabs, pinchar muestra toda su informacion

## Interpretación

Extensión de la UI de granja de TASK-012:

1. **Modal de selección de baya** independiente del modal de granja:
   - Categorías: Medicinas, Mejoras, Reductores y Resistencias (las mismas de la forja).
   - Primero las bayas con stock disponible; después las agotadas.
   - Buscador por nombre.
   - Al seleccionar una baya, se planta en el hueco elegido.
2. **Tabs de Pokémon por familia** en el modal de granja:
   - Cada familia muestra sus miembros (base + evoluciones) como tabs.
   - Al pulsar una tab se muestra la información completa del Pokémon: sprite, tipos, stats base, hábitat, familia evolutiva y estado de Pokédex.

## Contexto

- TASK-012 implementó F2 (plantación/cosecha), F4 (forja) y la UI de granja.
- `resources/views/habitats/show.blade.php` contiene el modal `habitatShow()`.
- `GranjaDetalle` expone `familias`, `bayas` y `bonguri`.
- `FamiliaGranja` expone `base`, `evolutions` y `types`; `evolutions` solo tiene `id`, `name`, `icon`, `level`.
- `PresentadorDetallePokemon::para(Pokemon)` ya devuelve tipos, stats, hábitats y familia.
- `GET /datagrid/pokemon/{id}/detalle` existe, pero bloquea especies no vistas salvo `unlock=1`; no sirve tal cual para la granja.

## Restricciones

- No tests; `tests/` fuera de alcance.
- No commit/push.
- No tocar F6/F7.
- No romper la plantación, cosecha, forja ni combate existentes.
- Toda clave de baya se valida contra el catálogo.
- El endpoint de detalle debe autorizar que el Pokémon pertenece a la familia del hábitat.

## Criterio de cierre

Modal de bayas con categorías, buscador y disponibles primero; tabs de familia con detalle completo; verificación estática, revisión y informe final. Sin tests.
