# TASK-006 — Revisión

- **Revisor:** `reviewer`
- **Fecha:** 2026-11-02
- **Plan:** `plan.md` v1
- **Veredicto:** `APPROVED`

## Resumen

Los dos cambios cumplen el plan:

- **Hábitat:** el fallback `nombre → pokemon.name → 'Reclutado eliminado'` usa `filled()` y navegación segura; el controlador eager-carga `reclutado.pokemon`. El test con `nombre=null` y Pokémon `rhyperior` muestra el nombre del Pokémon y no «Reclutado eliminado».
- **Combate:** el grid de `layout='ruta'` usa `lg:grid-cols-3`: capturas `lg:col-span-1` + caramelos `lg:col-span-2` con tres filas (familia/EV/tipo); sin capturas, caramelos `lg:col-span-3`; sin caramelos, capturas `lg:col-span-3`. La rama entrenador/gimnasio queda intacta.

El hallazgo MEDIUM de la revisión anterior (faltaba el test de capturas sin caramelos) quedó resuelto con un tercer test Livewire que aserta `lg:col-span-3`, ausencia de `lg:col-span-2` y «Capturados».

## Criterios

| # | Criterio | Estado |
|---|---|---|
| 1 | `nombre=null` + Pokémon existente → nombre del Pokémon | Cumple |
| 2 | «Reclutado eliminado» solo si la relación es nula | Cumple (por esquema/inspección) |
| 3 | Capturas 1/3 + caramelos 2/3 con 3 filas | Cumple |
| 4 | Sin capturas → caramelos full width; sin caramelos → capturas full width | Cumple |
| 5 | Rama entrenador/gimnasio intacta | Cumple |
| 6 | Sin cambios de recompensas, migraciones ni dependencias | Cumple |
| 7 | Tests deterministas y en verde | Cumple |

## Hallazgos residuales

- [LOW] No hay test del alias vacío/espacios (`filled()` lo cubre, pero no está ejercitado).
- [LOW] La regla de nombre visible está triplicada (`ExploradorVista`, `ReclutadoSerializer`, vista de hábitat); unificar en el futuro.
- [NIT] `assertSee('grid-cols-3')` es redundante con `lg:grid-cols-3`.
- [NIT] `tieneCapturas` se deriva en la vista; podría exponerse en `BatallaVistaPresenter`.
- **Ajeno:** `PokemonHabitatSeederTest` sigue en rojo por FK `province_id=8` (WIP de `ProvinceSeeder`/`habitats.csv`); no participa ningún archivo de TASK-006.

## Conclusión

`APPROVED` para TASK-006. Sin hallazgos CRITICAL/HIGH/MEDIUM abiertos.
