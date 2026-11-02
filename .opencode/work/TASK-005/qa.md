# TASK-005 — QA

- **QA:** `qa`
- **Fecha:** 2026-11-02
- **Plan:** `plan.md` v2
- **Veredicto:** `PASS WITH KNOWN ISSUES`

## Resumen

Los 10 criterios quedan verificados. El único criterio parcial es el 10: las aserciones de vista se acotaron a subcadenas por el sujeto pendiente del Blade; están justificadas y no ocultan fallos. No hay regresiones en la suite de Exploraciones. El rojo de PHPStan está confinado a `SendasController.php` (WIP ajeno).

**Nota de evidencia:** la QA ejecutó la suite antes del último ajuste del fallback de imagen (`403 tests / 2505 aserciones`). Tras ese ajuste, el `orchestrator` re-ejecutó `Exploraciones` → **403 tests / 2507 aserciones OK**. La QA no ejecutó esa última corrida; el veredicto se emite sobre la evidencia completa, marcando la procedencia.

## Criterios

| # | Criterio | Estado | Evidencia |
|---|---|---|---|
| 1 | Nombre del explorador con fallback al Pokémon | Cumple | `ExploradorVistaTest`; tinker con reclutado sin alias → `Magnemite`; sin alias en `equipo` (campo no consumido) |
| 2 | Caramelo de familia con sprite/nombre/fallback | Cumple | `candy_imagen` + `candy_src`; `_evento.blade.php` encadena `candy_imagen → candy_src → 0.webp`; tests de página/vista |
| 3 | Caramelo de tipo resuelto u oculto | Cumple | `tipo_id=9` → Acero/acero/src; `999` → `oculto=true`; `DiarioExploracionTest` de total/grupos |
| 4 | Caramelo EV de hallazgo | Cumple | `stat=4` → Ataque Especial/atksp; `candy_nombre_js=null`; asset `atksp.webp` |
| 5 | Derrota con PV de entrada/rondas/rival | Cumple | `detalle_derrota` «Entró con 30/39 PS tras 4 rondas; a bronzor le quedaban 12/34 PS»; emboscada multi → sub-combate decisivo; histórico sin bloque |
| 6 | `reason_texto` humano | Cumple | «se quedó sin PS»; `reason` conservado; sin `explorador_debilitado` en crudo |
| 7 | Round-trip de las 5 claves | Cumple | `EventoExploracionTest`; `0.0` preservado |
| 8 | Pérdidas con nombre/imagen en tarjeta + diario | Cumple | familia 147→nosepass/299 y 34→magnemite/81 en BD real; partial en tarjeta y diario; contrato persistido intacto |
| 9 | Sin migraciones/dependencias; WIP intacto | Cumple | `git status` vacío para `database/migrations`, `composer.json/lock`, `package.json/lock` |
| 10 | Tests deterministas y sin aserciones debilitadas | Parcial | 403/2507 en verde, sin skips; algunas aserciones de vista se acotaron a subcadenas, justificadas |

## Pruebas ejecutadas

- `vendor/bin/phpunit --filter 'Exploraciones' --no-coverage` → **OK (403 tests, 2507 aserciones)** [orquestador, tras el último ajuste].
- QA: `vendor/bin/phpunit --filter 'Exploraciones' --no-coverage` → OK (403 tests, 2505 aserciones) antes del ajuste del fallback.
- QA: filtro unitario/feature (`EventoExploracionVistaTest|EventoExploracionTest|DiarioExploracionTest|ExploradorVistaTest|CombateExploracionTest|ProcesarExploracionCombateTest|ExploracionesPageTest|ExploracionesViewTest`) → OK (89 tests, 496 aserciones).
- QA: `ProcesarExploracionPoolTest` (regresión) → OK (1 test, 4 aserciones).
- `vendor/bin/phpstan analyse` → 8 errores, todos en `SendasController.php` (preexistentes, ajenos); 0 en TASK-005.
- `vendor/bin/pint --test` sobre 18 archivos de TASK-005 → pass.
- Sin `markTestSkipped`/`markTestIncomplete` en Exploraciones.
- Sin migraciones ni dependencias nuevas.

## Fallos y regresiones

- Ningún test en rojo en el alcance.
- `SendasController.php`: 8 errores de PHPStan preexistentes, fuera de TASK-005.
- No se detectaron regresiones en el diario, la tarjeta ni el resumen.

## Huecos de cobertura

- Los slugs `defsp` y `spd` no se ejercitaron; solo `atksp`.
- La cadena `onerror` de la vista no fue verificada por QA; sí por el orquestador y los tests de vista.
- Sin test de página con reclutado sin alias (solo unitario).
- Fallback `label=null` con tipo desconocido no cubierto.
- No se ejecutó `--display-skipped`; la búsqueda estática no encontró skips.

## Riesgos residuales

- El fallback de nombre depende de `relationLoaded('pokemon')`; un futuro llamador que no eager-cargue mostrará «Sin reclutado».
- `0.0` se serializa como `0` en JSON; el cast `(float)` lo restaura.
- Los helpers de `ExploracionesViewTest` invocan presentadores reales: acoplamiento test↔producción.
- Nombres de familia en minúsculas por convención de BD.

## Conclusión

`PASS WITH KNOWN ISSUES` para TASK-005 v1+v2. Los issues restantes son LOW/NIT, deuda ajena (`SendasController`) o huecos de cobertura que no bloquean la entrega.

## v3 — Motivo de la pérdida

- **Veredicto:** `PASS`.
- **Criterios:** el motivo aparece en tarjeta y diario; el fallback genérico funciona; no cambia `ceil(cantidad/2)` ni el contrato persistido; el partial solo lo pinta si hay pérdidas y el motivo es string no vacío.
- **Pruebas:** `ExploracionesPageTest|ExploracionesViewTest` 33/283; suite `Exploraciones` 412/2550; PHPStan 0 fuera de `SendasController`; Pint pass.
- **Fallos:** ninguno.
- **Riesgos LOW:** el motivo depende del evento raíz `derrota`; sin él se usa el texto genérico (esperado).
- **Nota:** el conteo global incluye cambios concurrentes ajenos a TASK-005 detectados durante la sesión; los tests específicos de v3 están en verde.

## v4 — Aviso solo en la tarjeta

- **Veredicto:** `PASS`.
- **Criterios:** el aviso aparece exactamente una vez; el motivo también; el diario solo se muestra con `diario.total > 0` y no incluye `_perdidas`; el partial conserva `role="alert"`, imagen, tipo, nombre y cantidad; no cambia la regla de pérdida.
- **Pruebas:** `ExploracionesPageTest|ExploracionesViewTest` 33/281; suite `Exploraciones` 416/2555; PHPStan 0 fuera de `SendasController`; Pint pass.
- **Fallos:** ninguno.
- **Observación:** el presentador ya expone `motivo_perdida=null` cuando no hay pérdidas (corregido en v3), por lo que el payload no arrastra un motivo de derrota en éxitos.
- **Nota:** la v4 supersede la parte de v2/v3 que mostraba el aviso dentro del diario.
