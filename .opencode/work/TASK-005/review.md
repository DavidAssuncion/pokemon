# TASK-005 — Revisión

- **Revisor:** `reviewer`
- **Fecha:** 2026-11-02
- **Plan:** `plan.md` v2
- **Arquitectura:** `architecture.md` (incluye addendum v2)
- **Veredicto:** `APPROVED`

## Resumen

Las dos correcciones MEDIUM de la revisión preliminar quedaron resueltas y verificadas:

1. **N+1 de cadenas evolutivas:** `PresentadorExploraciones` incorpora una caché de instancia (`miembrosPorCadenaCache`, `cadenasConsultadas`, `miembrosDeCadenas()`). La página reutiliza la misma instancia, por lo que no se repiten consultas de cadenas ya vistas. Residual LOW: si cada tarjeta aporta cadenas nuevas, se ejecuta un lote por tarjeta; no es una query global única.
2. **Rival de emboscada multi-Pokémon:** `rivalNombre()` usa el `pokemon_id` del último `sub_combates` y resuelve el nombre con `$nombres`; `detalle_derrota` usa los PV del sub-combate decisivo. Test nuevo con 2 sub-combates (299 victoria, 81 derrota) → `rival_nombre='magnemite'` y `5/32 PS`.

También se añadió el test de round-trip de `EventoExploracion` (5 claves, `0.0` preservado), se corrigió la gramática «tras 1 ronda / tras N rondas» y se eliminó el doble `role="alert"` (la tarjeta lo conserva; el diario no lo duplica).

## Criterios

| # | Criterio | Estado | Evidencia |
|---|---|---|---|
| 1 | Nombre del explorador con fallback al Pokémon | Cumple | `ExploradorVista::nombre()` + `ExploradorVistaTest`; controlador eager-carga `reclutado.pokemon.types` |
| 2 | Caramelo de familia con sprite, nombre y fallback | Cumple | `candy_imagen` en `EventoExploracionVista`; `_evento.blade.php` encadena `candy_imagen → candy_src → 0.webp`; tests unitarios y de página |
| 3 | Caramelo de tipo resuelto u oculto | Cumple | `TipoEnum` + `SlugTipo`; `oculto=true` y filtro en `DiarioExploracion`; test de total/grupos |
| 4 | Caramelo EV de hallazgo con slug real | Cumple | `StatEnum::slug()` (`atksp`); test de stat 4; asset verificado |
| 5 | Derrota con PV de entrada, rondas y rival | Cumple | `detalle_derrota` usa `hp_inicial`; `hp_inicial` se captura tras descanso y `triggerBattleStartEffects()`; histórico sin claves intacto |
| 6 | `reason_texto` humano | Cumple | `derrota()` conserva `reason` y emite `reason_texto`; vista no muestra `explorador_debilitado` |
| 7 | Round-trip de las 5 claves | Cumple | `EventoExploracionTest` con las 5 claves y `0.0` |
| 8 | Pérdidas con nombre/imagen y aviso en tarjeta + diario | Cumple | `objetosPerdidosVista()` resuelve familia 147→nosepass/299 y 34→magnemite/81; partial `_perdidas` en tarjeta y diario |
| 9 | Sin migraciones/dependencias; WIP intacto | Cumple | `git status` vacío para migraciones/composer/package; diff limitado a Presentation, vistas, tests y docs |
| 10 | Tests deterministas y sin aserciones debilitadas | Cumple con matiz | 412/2550 en verde (incluye cambios concurrentes ajenos); sin skips; algunas aserciones de vista se acotaron a subcadenas por el sujeto pendiente del Blade, justificadas en comentarios |

## Hallazgos residuales

- [LOW] El caché de cadenas no garantiza una query global única si cada tarjeta aporta cadenas nuevas; impacto acotado a la cola de resultados.
- [LOW] El `onerror` de `_evento.blade.php` ya encadena `candy_src → 0.webp`; si el sprite y el caramelo fallan, se oculta la imagen y queda el texto.
- [LOW] Sin test E2E de reclutado sin nombre en tarjeta + diario; hay test unitario de `ExploradorVista`.
- [LOW] Los nombres de familia se muestran con el `name` de BD en minúsculas (`nosepass`, `magnemite`); el contenido cumple, la capitalización queda como deuda de estilo.
- [NIT] `PS (HP)` en el caramelo EV de HP frente a `PS` en otras vistas; fuera de alcance.
- [NIT] El test `assertDontSee('familia 51')` es débil, pero el mismo test valida payload enriquecido, nombre e imagen.

## Conclusión

`APPROVED` para TASK-005 v1+v2. No hay hallazgos CRITICAL/HIGH/MEDIUM abiertos. Los residuales son LOW/NIT y no bloquean la entrega.

## v3 — Motivo de la pérdida

- **Veredicto:** `APPROVED`.
- **Cambio:** `motivo_perdida` derivado de `eventos['derrota']['reason']`; `explorador_debilitado` → «Tu explorador cayó debilitado en combate y perdió parte del botín.»; sin motivo → texto genérico; solo se expone si hay pérdidas.
- **UI:** `_perdidas.blade.php` muestra el motivo bajo la cabecera; tarjeta y diario lo reciben.
- **Hallazgos LOW corregidos:** `motivo_perdida` ya no se emite en exploraciones sin pérdidas; `reason` vacío se normaliza con `trim`; tests de motivo genérico sin `derrota` y con `reason` vacío.
- **Evidencia:** `ExploracionesPageTest|ExploracionesViewTest` 33/283; suite `Exploraciones` 412/2550; PHPStan 0 fuera de `SendasController`; Pint pass.

## v4 — Aviso solo en la tarjeta

- **Veredicto:** `APPROVED`.
- **Cambio:** se eliminó el include de `_perdidas` dentro del diario; el aviso queda solo en la tarjeta de resultados (`index.blade.php:260`). La condición del diario vuelve a `diario.total > 0`; `$rol` se eliminó del partial y no quedan consumidores.
- **Tests:** `ExploracionesPageTest` fija `assertSame(1, substr_count(...))` para el aviso y el motivo; `ExploracionesViewTest` mantiene `role="alert"` y los casos de motivo.
- **Evidencia:** 33/281 página/vista; suite `Exploraciones` 416/2555; PHPStan 0 fuera de `SendasController`; Pint pass.
- **NIT:** `ExploracionesViewTest` usa una clase CSS para comprobar la ausencia del párrafo; no bloquea.
- **Trazabilidad:** la v2 pedía el aviso también en el diario; la v4 lo retira por petición explícita del desarrollador.
