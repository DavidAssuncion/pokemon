# TASK-007 — Análisis

- **Analista:** `analyst`
- **Fecha:** 2026-11-02
- **Estado:** completado

## 1. Bug confirmado

`NormalizadorPokemonDerrotado::fase()` calcula la etapa contando miembros de la cadena con `species_id <= species_id actual` (`src/Exploraciones/App/NormalizadorPokemonDerrotado.php:73-88`). Ese criterio asume que el número de Pokédex crece con la evolución, pero los bebés suelen tener un número mayor:

- Pichu: `species_id 172` → cuenta Pichu, Pikachu y Raichu → **fase 3**.
- Pikachu: `species_id 25` → solo Pikachu → **fase 1**.
- Raichu: `species_id 26` → Pikachu y Raichu → **fase 2**.

`CalculadorRecompensas::calcularCaramelosFamilia()` suma `fase` por cadena (`src/Exploraciones/Domain/CalculadorRecompensas.php:264-276`), así que Pichu da más caramelos que Pikachu. El resultado correcto, usando `pokemon_evolution` (`25 ← 172`, `26 ← 25`), es **Pichu 1, Pikachu 2, Raichu 3**.

## 2. Alcance del defecto

Afecta a:

- Recompensas reales de exploración (`FinalizarExploracionHandler`).
- Recompensas de ruta (`OtorgarRecompensasRuta`).
- Recompensas de entrenador (`OtorgarRecompensasEntrenador`).
- Estimación previa (`ExploracionActivaController::cadenasDelPool()` + `EstimadorRecompensasExploracion::fase()`).
- Caramelos por descarte en reclutamiento (`ReclutamientoController::otorgarCaramelos()`), que duplica el mismo cálculo.

## 3. Cambios mínimos

1. `app/Support/CadenasEvolutivas.php`: añadir `etapasDe(iterable $chainIds): array<int, array<int,int>>` que lea `pokemon_evolution.evolves_from_species_id` y devuelva `chain_id → [species_id → etapa]` por BFS. Si una cadena no tiene aristas, omitirla para que los llamadores usen el fallback actual.
2. `src/Exploraciones/App/NormalizadorPokemonDerrotado.php`: añadir un tercer parámetro opcional `?array $etapasPorCadena = null`; usar `etapasPorCadena[$chain][$species]` si existe; si no, mantener el cálculo actual por `species_id`.
3. Callers: `FinalizarExploracionHandler`, `OtorgarRecompensasRuta`, `OtorgarRecompensasEntrenador` y `ReclutamientoController` calculan y pasan `CadenasEvolutivas::etapasDe(...)`.
4. `ExploracionActivaController::cadenasDelPool()`: usar `etapasDe()` y devolver la lista de especies ordenada por etapa (o un mapa de etapas); `Estimador::fase()` debe consumir el mapa o el orden. Mantener compatibilidad con listas legacy.
5. No cambiar `CalculadorRecompensas` ni la fórmula general.

## 4. Tests

- Nuevo test de `CadenasEvolutivas::etapasDe()` con Pichu/Pikachu/Raichu → 1/2/3 y con una familia normal → 1/2/3.
- `NormalizadorPokemonDerrotadoTest`: caso Pichu/Pikachu/Raichu con aristas de `pokemon_evolution` y `etapasPorCadena`; los tests existentes sin aristas siguen usando el fallback.
- `ExploracionesTest`/`FinalizarExploracionPerdidasTest`: los fixtures sin aristas mantienen el comportamiento actual; añadir un caso con aristas para Pichu/Pikachu/Raichu.
- `EstimadorRecompensasExploracionTest`: caso con mapa de etapas/orden correcto.
- `ReclutamientoControllerTest`: si se corrige el descarte, ajustar el caso que espera el orden por `species_id` (10 → 6) o añadir un caso con aristas.

## 5. Riesgos

- [alto] WIP de TASK-004 en los mismos archivos: cambios aditivos y sin revertir.
- [alto] Cambiar la firma del normalizador: usar un tercer parámetro opcional evita romper las 17 llamadas existentes.
- [medio] Familias sin datos de evolución: se mantiene el fallback actual para no alterar tests ni datos incompletos.
- [medio] Ramas/regionales: BFS por `evolves_from_species_id`; las aristas con padre fuera de la familia se tratan como raíz.
- [bajo] La base visual de la familia sigue siendo el menor `species_id` (Pikachu en la cadena 10); es un defecto de presentación separado.

**Confirmación:** no se modificó ningún archivo.
