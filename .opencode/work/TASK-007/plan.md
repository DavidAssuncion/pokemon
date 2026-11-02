# TASK-007 — Plan v1

- **ID:** TASK-007
- **Versión:** v1
- **Fecha:** 2026-11-02
- **Estado:** aprobado con «Si, adelante»
- **Basado en:** `analysis.md`

## 1. Objetivo

Calcular la etapa evolutiva real de cada Pokémon con `pokemon_evolution` (`evolves_from_species_id`) para que los caramelos de familia sean coherentes: bebés (Pichu) menos que sus evoluciones (Pikachu/Raichu). Aplicarlo a recompensas reales y a la estimación previa.

## 2. Alcance

- `app/Support/CadenasEvolutivas.php`: nuevo `etapasDe()`.
- `src/Exploraciones/App/NormalizadorPokemonDerrotado.php`: tercer parámetro opcional `$etapasPorCadena`; fallback actual si la cadena no tiene aristas.
- `src/Exploraciones/App/FinalizarExploracionHandler.php`, `src/CombateRuta/App/OtorgarRecompensasRuta.php`, `src/CombateEntrenadores/App/OtorgarRecompensasEntrenador.php`: calcular y pasar las etapas.
- `app/Http/Controllers/ReclutamientoController.php`: usar `etapasDe()` en `otorgarCaramelos()`.
- `app/Http/Controllers/ExploracionActivaController.php`: `cadenasDelPool()` ordena por etapa (o devuelve el mapa) para que la estimación coincida.
- `src/Exploraciones/Domain/EstimadorRecompensasExploracion.php`: admitir el mapa de etapas y mantener compatibilidad con listas.
- Tests: helper de etapas, normalizador, estimador, reclutamiento y un caso de recompensa con Pichu/Pikachu/Raichu.

## 3. Fuera de alcance

- Cambiar la fórmula general de recompensas, EXP, EV o tipo.
- Cambiar la base visual de la familia (menor `species_id`); es deuda de presentación separada.
- Migraciones, dependencias o cambios de esquema.

## 4. Criterios de aceptación

1. Pichu da menos caramelos de familia que Pikachu; Pikachu menos que Raichu.
2. La etapa se calcula con `pokemon_evolution`, no con `species_id`.
3. La estimación previa usa el mismo orden que la recompensa real.
4. Familias sin aristas mantienen el fallback actual (no se rompen tests/datos incompletos).
5. `ReclutamientoController` usa la misma corrección.
6. Tests relevantes en verde; PHPStan/Pint sin errores nuevos.
7. Sin migraciones ni dependencias.

## 5. Pruebas

```bash
vendor/bin/phpunit --filter 'CadenasEvolutivas|NormalizadorPokemonDerrotadoTest|EstimadorRecompensasExploracionTest|ExploracionesTest|FinalizarExploracionPerdidasTest|ReclutamientoControllerTest' --no-coverage
```

Después, PHPStan y Pint sobre los archivos tocados.

## 6. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| WIP TASK-004 en los mismos archivos | alto | Cambios aditivos; no revertir; leer el estado actual antes de editar |
| Firma del normalizador | alto | Tercer parámetro opcional; fallback actual |
| Familias sin aristas | medio | Omitir la cadena en `etapasDe` y usar el cálculo actual |
| Ramas/regionales | medio | BFS por `evolves_from_species_id`; padre fuera de la familia = raíz |
| Tests con cadenas artificiales | medio | Mantener fallback; añadir casos con aristas reales |

## 7. Rollback

Revertir los hunks de los archivos tocados. Sin migraciones ni datos.

## 8. Aprobación

El desarrollador aprobó la corrección con «Si, adelante» tras la explicación del bug y la propuesta de usar `pokemon_evolution`.
