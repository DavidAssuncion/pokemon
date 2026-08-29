# Análisis Backend — POST /api/habitats/{id}/families devuelve la familia completa con niveles reales

## Fecha
2026-08-29

## Contexto verificado

- Problema: al asignar una familia ramificada (ej. Eevee), el frontend reconstruía la familia
  client-side con `buildAssignedFamilyFromUnassigned`, que falla en cadenas ramificadas
  (infiere niveles 2,3,3; el backend asigna 2,2 vía `levelForStage`). El POST actual devuelve
  `DTOFamiliaAsignada` (`habitat_id, evolution_chain_id, assigned_count`), insuficiente.
- Solución acordada (documentada en `active/ANALISIS_FRONTEND_HABITAT_ASSIGNFAMILY.md`): el POST
  devuelve 201 con la familia COMPLETA `{evolution_chain_id, base:{id,name,icon,level},
  evolutions:[{id,name,icon,level}]}` — exactamente el shape de `DTOFamiliaDisponible`, que el
  frontend ya consume en GET `/api/habitats/{id}/families` (pestaña "Ya Asignados") y en el
  Livewire `FamilyModal`.
- `HabitatRepository::buildAvailableFamilyFromChain(int $chainId, array $members)` ya produce
  `DTOFamiliaDisponible` con `level` real por miembro (`levelForStage($member['stage'], $totalStages)`),
  idéntico al `level` que el upsert de `assignFamily` persiste. Es LA fuente correcta de niveles.
- `DTOFamiliaAsignada` solo se referencia desde `HabitatRepository`, `AsignarFamiliaAHabitat`,
  `HabitatRepositoryInterface` (grep verificado). Tras el cambio queda sin referencias → se elimina
  (regla "sin código muerto").
- `app/Livewire/Habitats/FamilyModal.php::assign()` llama a `asignarFamiliaAHabitat->handle(...)`
  e IGNORA el retorno → no se rompe al cambiar el tipo de retorno.
- Tests existentes que assert `assigned_count`/`habitat_id` del POST: 3 (3/2/1 etapas). Se actualizan
  al nuevo shape (se deriva el total de miembros como `1 + count(evolutions)` y se conservan los
  asserts de BD). `test_obtener_familias_sin_habitat_solo_cadenas_vacias` assert `count(3)` → la
  cadena ramificada nueva se crea DENTRO del test nuevo (no en `setUp`) para no alterar ese conteo.

## Decisión de diseño

**Opción 1 (mínima, recomendada)**: `assignFamily` retorna `DTOFamiliaDisponible` reutilizando
`buildAvailableFamilyFromChain`. El controlador sigue haciendo `response()->json($result->toArray(), 201)`
sin cambios. No se añade `family` anidado ni se mantienen claves viejas.

## Qué voy a tocar

| Archivo | Acción | Propósito |
|---|---|---|
| `src/Habitats/Presentation/DTOFamiliaAsignada.php` | Eliminar | Queda sin referencias tras el cambio (código muerto). |
| `src/Habitats/Infra/HabitatRepository.php` | Modificar | `assignFamily(): DTOFamiliaDisponible`; tras upsert+cache, construir y devolver la familia vía `buildAvailableFamilyFromChain` (`?? throw \LogicException` porque la base siempre existe). Se elimina la variable `$assignedCount`. |
| `src/Habitats/Domain/Repositories/HabitatRepositoryInterface.php` | Modificar | Firma `assignFamily(...): DTOFamiliaDisponible` (import nuevo, quitar import de DTOFamiliaAsignada). |
| `src/Habitats/App/AsignarFamiliaAHabitat.php` | Modificar | `handle(...): DTOFamiliaDisponible` (import nuevo, quitar import de DTOFamiliaAsignada). |
| `app/Http/Controllers/HabitatsController.php` | Sin cambios | `$result->toArray()` + 201 ya producen el nuevo shape (verificado, sin PHPDoc que ajustar). |
| `tests/Feature/Habitats/FamiliesTest.php` | Modificar | Actualizar 3 tests POST al nuevo shape + NUEVO test de cadena ramificada (Eevee: base nivel 1, TODAS las evoluciones nivel 2). |

## Tests (TDD: rojo → verde)

1. `test_asignar_familia_3_etapas_inserta_levels_1_2_3` — 201; `evolution_chain_id`,
   `base.{id,level}=1`, `evolutions[0].level=2`, `evolutions[1].level=3`; sin `assigned_count`.
2. `test_asignar_familia_2_etapas_rattata_inserta_levels_1_2` — 201; base id 19 level 1,
   evolutions[0] id 20 level 2.
3. `test_asignar_familia_1_etapa_inserta_level_2` — 201; base id 151 level 2, evolutions vacío.
4. `test_asignar_familia_ramificada_asigna_nivel_2_a_todas_las_evoluciones` — NUEVO: cadena
   Eevee→Vaporeon/Jolteon; 201; base level 1, AMBAS evoluciones level 2 (no 2,3); BD con level 2
   en ambas. La cadena se crea dentro del test (no en setUp) para no romper
   `test_obtener_familias_sin_habitat_solo_cadenas_vacias` (assertCount 3).

## Shape exacto del JSON de respuesta (entregable para frontend)

```json
{
  "evolution_chain_id": 22,
  "base": { "id": 133, "name": "Eevee", "icon": "/images/iconos_webp/133.webp", "level": 1 },
  "evolutions": [
    { "id": 134, "name": "Vaporeon", "icon": "/images/iconos_webp/134.webp", "level": 2 },
    { "id": 135, "name": "Jolteon",  "icon": "/images/iconos_webp/135.webp", "level": 2 }
  ]
}
```
Status 201. Sin `habitat_id` ni `assigned_count` (se deriva: `1 + evolutions.length`).

## Riesgos

- **Contrato de otros endpoints intacto**: GET families, GET unassigned-families, DELETE, PATCH
  no cambian (no toco `buildAvailableFamilyFromChain`, `buildUnassignedFamilyFromChain`,
  `removeFamily`, `movePokemonToLevel`).
- **PHPStan**: `buildAvailableFamilyFromChain` devuelve `?DTOFamiliaDisponible`; tras validar
  `$members` no vacío la base siempre existe, pero para satisfacer el static analysis se usa
  `$family ?? throw new \LogicException(...)`.
- **No tocar vistas/Blade ni Livewire**: el frontend consume el body directo; `FamilyModal`
  ignora el retorno → sin cambios.
- **BD local**: no ejecutar `php artisan test` (sin BD desde host); tests listos para CI
  (RefreshDatabase sqlite :memory:).

## Entorno

- SÍ ejecutar `vendor/bin/pint --dirty --format agent` y `php -l` de cada archivo tocado.
- NO ejecutar `php artisan test`.