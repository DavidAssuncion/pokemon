# Análisis Frontend — assignFamily consume familia completa real del backend

## Fecha
2026-08-29

## Contexto
El BACKEND está cambiando (en paralelo) `POST /api/habitats/{id}/families` para que, en lugar de
devolver solo counts (`DTOFamiliaAsignada`: habitat_id, evolution_chain_id, assigned_count),
devuelva 201 con la FAMILIA COMPLETA + niveles reales por miembro
(`evolution_chain_id`, `base:{id,name,icon,level}`, `evolutions:[{id,name,icon,level}]`).

Verificado en working tree: `HabitatsController@assignFamily` y `HabitatRepository@assignFamily`
aún están en el contrato VIEJO (DTOFamiliaAsignada con counts). El backend NO ha entregado aún el
nuevo shape → asumo el contrato pactado (documentado en la tarea) y dejo un fallback defensivo.

## Vista a tocar (única)
`resources/views/habitats/show.blade.php` — SOLO el flujo `assignFamily` + eliminación del helper
obsoleto `buildAssignedFamilyFromUnassigned`.

## Contrato consumido (pactado, backend en paralelo)
```
POST /api/habitats/{id}/families  body:{evolution_chain_id}  → 201
{
  "evolution_chain_id": 22,
  "base": {"id": 133, "name": "Eevee", "icon": "...", "level": 1},
  "evolutions": [ {"id": 134, "name": "Vaporeon", "icon": "...", "level": 2}, ... ]
}
```

## Cambios
1. `assignFamily`: tras `!response.ok`, leer el body una vez. Si trae `evolution_chain_id` + `base`:
   añadir a `assignedFamilies` con `total_stages: 1 + (data.evolutions?.length || 0)` (igual que los
   loaders ~664/683). Se conserva la mutación de `unassignedFamilies` (git lo sigue quitando).
2. **Decisión fallback defensivo**: si el body NO trae la estructura completa (backend no entregado
   aún, o body parcial), NO infiero niveles y NO añado a `assignedFamilies` (un objeto mínimo sin
   levels rompería visualmente la pestaña Ya Asignados y el getter). La familia ya se quitó de
   `unassignedFamilies`, así que no hay duplicados; se auto-corrige al reabrir el modal (que sí
   recarga listados — patrón self-heal ya existente).
3. Elimino `buildAssignedFamilyFromUnassigned` (definición + uso). Ya no se necesita: no inferimos
   niveles client-side.

## Coherencia verificada
- `assignedByLevel` (getter) sigue agrupando por `family.base.level` y `evolutions[i].level` reales;
  descarta entradas sin level 1-3 válido (defensa). Con el contrato nuevo los levels son reales
  (Eevee ramificada → base 1, evoluciones todas 2).
- Pestaña Asignar (`filteredUnassigned`, filtros, grid) intacta.
- No se toca `updatePokemonLevel`, `removeFamily`, loaders.
- Se conserva `if (this.gestionLoading) return; ... finally { this.gestionLoading = false; }`.

## Verificación
- Lectura cuidadosa: escapes Blade, `await response.json()`, sin nuevas dependencias JS.
- No ejecuto `npm run build`.
