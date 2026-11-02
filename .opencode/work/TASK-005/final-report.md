# TASK-005 — Informe final

- **ID:** TASK-005
- **Título:** Diario de Exploraciones: imágenes, tipo desconocido, nombre del explorador y explicación de derrota
- **Estado final:** `COMPLETED`
- **Fecha:** 2026-11-02
- **Plan aprobado:** v4 (v1 + pérdidas legibles + motivo + aviso solo en tarjeta)

## 1. Resumen

Se corrigió la presentación del diario de Exploraciones y se añadió el detalle de las pérdidas por derrota:

- Los caramelos de familia muestran el **sprite de especie** (como la emboscada), el nombre concreto y un fallback de imagen.
- Los caramelos de tipo resuelven `tipo_id` (9 → Acero) y muestran su imagen; si no se pueden resolver, el evento se **oculta** y no cuenta en el diario.
- Los caramelos EV de hallazgo resuelven `StatEnum` y usan los slugs reales (`atksp`, `defsp`, `spd`), sin depender de `statName()` en JS.
- El explorador usa su nombre o, si no tiene alias, el nombre de su Pokémon; la narrativa ya no dice «Sin reclutado».
- La derrota muestra los **PV de entrada**, las rondas y los PV restantes del rival, y el evento raíz usa un motivo legible.
- «⚠️ Perdiste objetos» muestra nombre e imagen de cada pérdida (familia 147 → Nosepass, familia 34 → Magnemite, EV, tipo y captura) en la tarjeta de resultados; el diario ya no lo duplica.
- El aviso explica el **motivo**: «Tu explorador cayó debilitado en combate y perdió parte del botín.»; si no hay motivo registrado, usa un texto genérico de derrota. No cambia la regla de pérdida.

## 2. Archivos modificados

### Producción

- `src/Exploraciones/Presentation/EventoExploracionVista.php`
- `src/Exploraciones/Presentation/DiarioExploracion.php`
- `src/Exploraciones/Presentation/PresentadorExploraciones.php`
- `src/Exploraciones/Presentation/ExploradorVista.php`
- `src/Exploraciones/Presentation/ResultadoExploracionVista.php`
- `src/Exploraciones/App/CombateExploracion.php`
- `src/Exploraciones/App/ProcesarExploracionHandler.php`
- `src/Exploraciones/Domain/ValueObjects/ResultadoBatallaExploracion.php`
- `src/Exploraciones/Domain/ValueObjects/EventoExploracion.php`

### Vistas

- `resources/views/exploraciones/_evento.blade.php`
- `resources/views/exploraciones/_perdidas.blade.php` (nuevo)
- `resources/views/exploraciones/index.blade.php`

### Tests

- `tests/Unit/Exploraciones/EventoExploracionVistaTest.php`
- `tests/Unit/Exploraciones/EventoExploracionTest.php` (nuevo)
- `tests/Unit/Exploraciones/DiarioExploracionTest.php`
- `tests/Unit/Exploraciones/ExploradorVistaTest.php`
- `tests/Unit/Exploraciones/CombateExploracionTest.php`
- `tests/Feature/Exploraciones/ProcesarExploracionCombateTest.php`
- `tests/Feature/ExploracionesPageTest.php`
- `tests/Feature/ExploracionesViewTest.php`

### Documentación

- `src/Exploraciones/context.md`
- `src/Exploraciones/README.md`

### Artefactos de coordinación

- `.opencode/work/TASK-005/request.md`
- `.opencode/work/TASK-005/analysis.md`
- `.opencode/work/TASK-005/architecture.md`
- `.opencode/work/TASK-005/plan.md`
- `.opencode/work/TASK-005/approval.md`
- `.opencode/work/TASK-005/tasks.md`
- `.opencode/work/TASK-005/execution.md`
- `.opencode/work/TASK-005/review.md`
- `.opencode/work/TASK-005/qa.md`
- `.opencode/work/TASK-005/security.md`
- `.opencode/work/TASK-005/final-report.md`

## 3. Decisiones principales

- **Imagen de familia:** `candy_imagen` = sprite de especie (`/images/iconos_webp/{pokemon_id}.webp`), con `candy_src` como fallback y `candy_pokemon/0.webp` como último recurso. Se mantiene `candy_src` por compatibilidad.
- **Tipo:** `TipoEnum::fromId()` + `SlugTipo::de()`; fallback legacy a `slug`/`tipo_nombre`/`tipo_label`; sin resolución, `oculto=true` y filtrado en `DiarioExploracion`/`activaToView`.
- **EV:** misma rama de hallazgo; `StatEnum::slug()` para los slugs reales; `candy_nombre_js` deja de ser necesario.
- **Nombre del explorador:** `reclutado->nombre` → `pokemon->name` (si la relación está cargada) → `'Sin reclutado'`. No se tocó el campo `equipo` del presentador.
- **Derrota:** claves aditivas `hp_inicial`, `hp_max`, `rival_hp_inicial`, `rival_hp_final`, `rival_hp_max`; `hp_inicial` es el PV del **sub-combate decisivo**, tras el descanso previo y `triggerBattleStartEffects()`. `detalle_derrota` usa `hp_inicial`, rondas y PV del rival. El evento raíz conserva `reason` y añade `reason_texto`.
- **Round-trip:** `EventoExploracion` extiende constructor, `desdeArray()` y `aArray()` para no perder las claves nuevas.
- **Pérdidas:** resolución solo de presentación; el contrato persistido sigue siendo `tipo`, `id`, `label`, `cantidad_perdida`. `CadenasEvolutivas` con caché de instancia por página; partial `_perdidas` usado **solo en la tarjeta** de resultados (v4 retiró la copia del diario).
- **Motivo de pérdida:** `motivo_perdida` se deriva de `eventos['derrota']['reason']`; `explorador_debilitado` → «Tu explorador cayó debilitado en combate y perdió parte del botín.». Solo se expone si hay pérdidas; `reason` vacío se normaliza. La regla `ceil(cantidad/2)` no cambia.

## 4. Pruebas y validación

- Suite `Exploraciones`: **416 tests / 2555 aserciones en verde** en la última corrida. El conteo incluye cambios concurrentes ajenos a TASK-005 detectados durante la sesión; los tests específicos de TASK-005 están en verde.
- Filtro de página/vista de TASK-005: **33 tests / 281 aserciones** (incluye v3+v4).
- Filtro unitario/feature de v1/v2: **89 tests / 496 aserciones** (QA).
- Regresión `ProcesarExploracionPoolTest`: OK (1 test / 4 aserciones).
- PHPStan: **8 errores preexistentes en `SendasController.php`** (WIP ajeno); 0 en TASK-005.
- Pint: **pass** en los archivos de TASK-005.
- Sin migraciones ni dependencias nuevas.
- Sin `markTestSkipped`/`markTestIncomplete` en Exploraciones.
- No se ejecutó PHPUnit en paralelo sobre `laravel_test`.

## 5. Revisión y seguridad

- Revisor independiente: **APPROVED** (`review.md`). Sin hallazgos CRITICAL/HIGH/MEDIUM abiertos.
- QA: **PASS WITH KNOWN ISSUES** (`qa.md`). El criterio 10 es parcial por aserciones de vista acotadas a subcadenas, justificadas; no ocultan fallos.
- Seguridad: **NO APLICA** (`security.md`). Cambio de presentación sin autenticación, autorización, SQL de usuario, secretos ni dependencias nuevas. Salidas Blade escapadas.

### v3 — Motivo de la pérdida

- El aviso «⚠️ Perdiste objetos» muestra una línea de motivo: «Tu explorador cayó debilitado en combate y perdió parte del botín.».
- Sin `eventos['derrota']`/`reason`, muestra «La expedición terminó en derrota y se perdió parte del botín.»; `reason` vacío se normaliza.
- Solo se expone `motivo_perdida` cuando hay pérdidas.
- Revisor: **APPROVED**. QA: **PASS**. Tests específicos: 33/281; suite global 416/2555 (con cambios concurrentes ajenos).

### v4 — Aviso solo en la tarjeta

- Se retiró el include de `_perdidas` del diario; el aviso y el motivo quedan solo en la tarjeta de resultados.
- El diario vuelve a mostrarse solo si `diario.total > 0`; `_perdidas` usa `role="alert"` fijo.
- Revisor: **APPROVED**. QA: **PASS**. Tests específicos: 33/281; suite global 416/2555.

## 6. Issues conocidos y deuda

- **Ajeno:** 8 errores de PHPStan en `app/Http/Controllers/SendasController.php` (WIP previo).
- **LOW:** nombres de familia en minúsculas (`nosepass`, `magnemite`) porque se usa `pokemon.name` de BD; capitalización pendiente de estilo.
- **LOW:** `PS (HP)` en el caramelo EV de HP frente a `PS` en otras vistas.
- **LOW:** sin test E2E de reclutado sin alias en tarjeta + diario; hay test unitario de `ExploradorVista`.
- **LOW:** el caché de cadenas no garantiza una query global única si cada tarjeta aporta cadenas nuevas; sí evita repetir cadenas ya vistas.
- **LOW:** algunas aserciones de `ExploracionesViewTest` se acotaron a subcadenas por el sujeto pendiente del Blade; están comentadas y no debilitan el comportamiento crítico.
- **LOW:** el campo `equipo` de `PresentadorExploraciones` sigue devolviendo `'Sin reclutado'` cuando no hay reclutado; no lo consume ninguna vista y quedó fuera de alcance.
- **NIT:** `assertDontSee('familia 51')` es una aserción débil, pero el test valida el payload enriquecido, el nombre y la imagen.

## 7. Riesgos residuales

- El fallback de nombre depende de `relationLoaded('pokemon')`; un futuro llamador que no eager-cargue mostrará «Sin reclutado».
- El `onerror` inline puede ser bloqueado por una CSP estricta; la imagen no se vería, pero el texto seguiría visible.
- Los nombres de familia dependen de que el catálogo `Pokemon` tenga la cadena; si falta, se muestra el tipo y el ID en el `title`.
- No se auditó seguridad porque el alcance no lo requiere; si se exponen estos datos por API pública, habrá que revisarlo.

## 8. Próximos pasos

1. Revisar la UI en navegador (`/exploraciones`) y, si no se ven cambios de assets, ejecutar `npm run build` o `npm run dev`.
2. Autorizar el commit cuando el desarrollador lo decida; **no se ha hecho commit ni `git push`**.
3. Abrir tarea separada para los 8 errores de PHPStan de `SendasController`.
4. Opcional: capitalizar nombres de familia y unificar `PS (HP)`/`PS`.
5. Opcional: test E2E de reclutado sin alias y de la cadena de fallback de imagen con CSP.
6. Mantener la deuda de Fase 2 de TASK-003 (god-handler, idempotencia fuerte, `withoutOverlapping()`, etc.) fuera de este alcance.
