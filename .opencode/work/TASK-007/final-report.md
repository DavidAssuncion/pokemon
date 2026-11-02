# TASK-007 — Informe final

- **ID:** TASK-007
- **Estado final:** `COMPLETED`
- **Fecha:** 2026-11-02
- **Plan aprobado:** v1 («Si, adelante»)

## 1. Resumen

Se corrigió el cálculo de la fase evolutiva de los caramelos de familia. Antes se contaban miembros con `species_id <=` el actual, lo que invertía el orden en bebés con número de Pokédex alto:

- Pichu (172) → fase 3.
- Pikachu (25) → fase 1.
- Raichu (26) → fase 2.

Ahora se usa `pokemon_evolution` (`evolves_from_species_id`) con BFS:

- Pichu → etapa 1.
- Pikachu → etapa 2.
- Raichu → etapa 3.

Así los bebés dan menos caramelos y las evoluciones más.

## 2. Archivos modificados

- `app/Support/CadenasEvolutivas.php`
- `src/Exploraciones/App/NormalizadorPokemonDerrotado.php`
- `src/Exploraciones/App/FinalizarExploracionHandler.php`
- `src/CombateRuta/App/OtorgarRecompensasRuta.php`
- `src/CombateEntrenadores/App/OtorgarRecompensasEntrenador.php`
- `app/Http/Controllers/ReclutamientoController.php`
- `app/Http/Controllers/ExploracionActivaController.php`
- `src/Exploraciones/Domain/EstimadorRecompensasExploracion.php`
- Tests: `tests/Unit/Exploraciones/CadenasEvolutivasEtapasTest.php`, `NormalizadorPokemonDerrotadoTest.php`, `CalculadorRecompensasTest.php`, `EstimadorRecompensasExploracionTest.php`, `tests/Feature/ReclutamientoControllerTest.php`.

## 3. Decisiones

- `etapasDe()` omite cadenas sin aristas para preservar el fallback legacy y no romper datos incompletos.
- El normalizador recibe un tercer parámetro opcional; las 17 llamadas existentes no se rompen.
- La estimación previa admite el mapa real o la lista legacy.
- No se cambió la fórmula general (`sum('fase')`) ni la base visual de la familia (menor `species_id`).

## 4. Pruebas

- Filtro del plan → **OK (111 tests, 418 aserciones)**.
- Ruta/entrenador → **OK (14 tests, 73 aserciones)**.
- BD real: Pichu 1, Pikachu 2, Raichu 3.
- PHPStan: 0 en TASK-007; 8 preexistentes en `SendasController` y 3 en untracked ajenos.
- Pint: pass.
- Sin migraciones ni dependencias.

## 5. Revisión y seguridad

- Reviewer: **APPROVED**.
- QA: **PASS WITH KNOWN ISSUES**.
- Seguridad: no aplica (cálculo de catálogo).

## 6. Issues conocidos

- Suite global roja por WIP ajeno de TASK-003/004 (7 errores/16 fallos); ningún fallo atribuible a TASK-007.
- Falta test de integración end-to-end de recompensas reales; seguimiento recomendado.
- El formato legacy del estimador queda como deuda.
- La imagen/nombre base de la familia sigue usando el menor `species_id` (Pikachu en la cadena 10), no el bebé; es deuda de presentación separada.

## 7. Próximos pasos

- Revisar en la UI que Pichu da menos caramelos que Pikachu.
- Añadir el test de integración end-to-end de recompensas.
- Cerrar los fallos del WIP TASK-003/004 para dejar la suite global en verde.
- Autorizar commit cuando se decida; no se ha hecho commit ni `git push`.
