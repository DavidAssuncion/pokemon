# TASK-007 — QA

- **QA:** `qa`
- **Fecha:** 2026-11-02
- **Plan:** `plan.md` v1
- **Veredicto:** `PASS WITH KNOWN ISSUES`

## Criterios

| # | Criterio | Estado | Evidencia |
|---|---|---|---|
| 1 | Pichu < Pikachu < Raichu | Cumple | BD real: etapas 1/2/3; normalizador + calculador 1/2/3 |
| 2 | Etapa desde `pokemon_evolution` | Cumple | `CadenasEvolutivas::etapasDe()` + test |
| 3 | Estimación previa coincide | Cumple | `cadenasDelPool()` + `Estimador::fase()` mapa/lista |
| 4 | Fallback sin aristas | Cumple | Cadenas sin aristas se omiten; normalizador conserva conteo legacy |
| 5 | Reclutamiento usa la misma corrección | Cumple | `ReclutamientoController::otorgarCaramelos()` + test |
| 6 | Sin fórmula/migraciones/dependencias | Cumple | `calcularCaramelosFamilia` sigue con `sum('fase')`; sin diffs de esquema/deps |

## Pruebas ejecutadas

- Filtro del plan (`CadenasEvolutivasEtapasTest|NormalizadorPokemonDerrotadoTest|CalculadorRecompensasTest|EstimadorRecompensasExploracionTest|ExploracionesTest|FinalizarExploracionPerdidasTest|ReclutamientoControllerTest`) → **OK (111 tests, 418 aserciones)**.
- Filtro ruta/entrenador (`OtorgarRecompensasRutaTest|MultiplicadorRecompensasTest`) → **OK (14 tests, 73 aserciones)**.
- PHPStan: 0 en TASK-007; 8 preexistentes en `SendasController` y 3 en untracked ajenos.
- Pint: pass.
- BD real: `etapasDe([10])` → `{172:1, 25:2, 26:3}`.

## Fallos/known issues

- Suite global roja por WIP ajeno (TASK-003/004): 1534 tests, 7 errores/16 fallos; ninguno atribuible a TASK-007.
- 9 skips del WIP (`SyncCandyRegionalesTest`, `CampanaDiagnosticoTest`).
- Falta test de integración end-to-end de recompensas reales; QA lo recomienda como seguimiento.
- `ExploracionesViewTest::test_exploraciones_view_layout_handles_empty_candy_sides` falla en aislamiento por la vista (`Agua`), no por la fase evolutiva.

## Riesgos

- Cadenas con datos incompletos siguen usando el fallback legacy.
- El formato legacy del estimador queda como deuda.

## Conclusión

`PASS WITH KNOWN ISSUES` para TASK-007. El bug de Pichu/Pikachu queda corregido en recompensas reales y estimación.
