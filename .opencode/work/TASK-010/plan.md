# TASK-010 — Plan v2

- **Estado:** `AWAITING_APPROVAL`
- **Fecha:** 2026-11-04
- **Proyecto:** `/home/david/pokemon`
- **Base:** rama `refactor/ui-tokens`, HEAD `f56f25e`, tras cerrar y commitear los cambios pendientes de TASK-009.
- **Rama propuesta:** `task-010` desde `refactor/ui-tokens`, sin `push`.
- **Cambio respecto a v1:** el desarrollador indicó que, una vez cambiado el seeder, no se aplica más corrección sobre nombres existentes. Se elimina el backfill/migración. La generación de los 59 assets shiny queda **fuera de v2** hasta que el desarrollador defina esa lista; se entrega `missing-shiny.md` para esa definición.

## 1. Objetivo

1. Guardar los nombres de Pokémon con la primera letra en mayúscula al insertarlos desde los seeders. **No se modifican las filas existentes ni se re-ejecuta el seeder como backfill.**
2. Mantener la sesión iniciada hasta 1 año de inactividad, efectivo en local y Docker.
3. Corregir la lógica shiny que aún descarta el flag en exploraciones y en la evolución, manteniendo `x-sprite` como único componente. Los 59 assets shiny faltantes se documentan y se dejan para una decisión posterior.

## 2. Decisiones incluidas en el plan

| ID | Decisión | Estado |
|----|----------|--------|
| D1 | «1 año» = `SESSION_LIFETIME=525600` minutos de inactividad deslizante, no caducidad absoluta. | Asumida |
| D2 | Solo se cambia el seeder; **sin migración ni backfill** de los nombres existentes. | Indicada por el desarrollador |
| D3 | No se toca el CSV ni se re-ejecuta `PokemonSeeder`; las 38 formas Alola/Galar que el seeder omite quedan fuera. | Asumida |
| D4 | Los 59 assets shiny reales **no se generan en v2**; se entrega la lista y el análisis de mapeo en `missing-shiny.md`. | Indicada por el desarrollador |
| D5 | Eventos de exploración y generación de shiny salvaje en exploraciones quedan fuera de alcance. | Asumida |
| D6 | `x-sprite` sigue siendo el único componente de sprite. | Aceptada |
| D7 | Seguridad obligatoria por el cambio de sesión; revisión independiente solo si el desarrollador la solicita (preferencia registrada en TASK-009). | Aceptada |
| D8 | Sin tests: no se crean, actualizan, ejecutan ni reparan. | Aceptada |
| D9 | La regresión `size="48"` de `turn-bar` se reporta, no se incluye. | Aceptada |
| D10 | No se modifica `SESSION_SECURE_COOKIE` en local; se recomienda `true` en producción y seguridad lo valida. | Asumida |

## 3. Alcance

### A. Nombres capitalizados (solo seeder)

1. `database/seeders/PokemonSeeder.php:116`: `'name' => Str::ucfirst($row['name'])`; importar `Illuminate\Support\Str`.
2. No se crea migración de datos.
3. No se re-ejecuta el seeder contra la base de datos.
4. Las filas existentes quedan como están. Si el desarrollador quiere capitalizarlas, tendrá que hacerlo por su cuenta; este plan no lo cubre.
5. Verificación estática: inspección del punto único y comprobación de `Str::ucfirst` sobre muestras (`bulbasaur` → `Bulbasaur`, `mr-mime` → `Mr-mime`, `ho-oh` → `Ho-oh`, `nidoran-f` → `Nidoran-f`).

### B. Sesión de 1 año

1. `config/session.php:37`: fallback `525600`.
2. `.env.example:31`: `SESSION_LIFETIME=525600`.
3. `docker-compose.yml`: `SESSION_LIFETIME: ${SESSION_LIFETIME:-525600}` en `app`, `worker` y `scheduler`.
4. Mantener `expire_on_close=false`.
5. `.env` local: si define `SESSION_LIFETIME`, el desarrollador debe cambiarlo a `525600`; el agente no lee `.env`. Se verificará con `config:show`. Si el permiso lo permite, el especialista hará un reemplazo ciego de la línea sin imprimir el resto del archivo.
6. Limpiar caché de configuración tras el cambio (`config:clear`; en producción reiniciar/re-cachear).
7. No se añade «recordarme» nuevo.

### C. Shiny consistente (sin generar assets)

1. **Exploraciones — capturados:**
   - `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php:76-80`: añadir `'es_shiny' => $captura->esShiny`.
   - `src/Exploraciones/Presentation/PresentadorExploraciones.php:244-251`: conservar `es_shiny`.
   - `src/Exploraciones/Presentation/ResultadoExploracionVista.php:167-179`: conservar el flag; la vista usará `:id` + `:shiny`.
   - `src/Exploraciones/App/FinalizarExploracionHandler.php:697-704`: preservar `esShiny` al recrear `RecompensaCaptura` en `restarPerdidas`.
   - `resources/views/exploraciones/index.blade.php:307-312`: `<x-sprite :id="$capturado['pokemon_id']" :shiny="$capturado['es_shiny'] ?? false" ...>`.
2. **Evolución:**
   - `app/Support/ReclutadoVistaPresenter.php:67-74`: añadir `'es_shiny' => (bool) $reclutado->es_shiny` a `siguiente`.
   - `resources/views/reclutado/show.blade.php:28`: `:shiny="$siguiente['es_shiny'] ?? false"`.
3. **Sin cambios** en `x-sprite`, dominio de batalla, `CombatienteVista`, `BatallaVistaPresenter`, `_pokemon-card`, `equipos/index` (ya usan shiny correctamente).
4. **Assets:** no se generan. Se entrega `missing-shiny.md` con los 59 Pokémon reales, los 4 legacy y el placeholder; la decisión de generarlos queda para una tarea posterior.

## 4. Fuera de alcance

- Backfill/migración de nombres existentes.
- Corrección del CSV de las 38 formas Alola/Galar.
- Generación/remapeo de los 59 assets shiny (pendiente de definición del desarrollador).
- Generación de shiny salvaje en exploraciones (`CalculadorRecompensas`, `PokemonDerrotado`): no existe hoy.
- Shiny en el diario de eventos de exploración: requiere ampliar el contrato persistido.
- Regresión `size="48"` de `turn-bar`: se reporta.
- Limpieza de campos muertos `src`/`onerror` en `BatallaVistaPresenter::capturas` y duplicidad `iconoDe`/`iconoPrincipalDe`.
- `SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT`, «recordarme».
- Tests, dependencias, `composer.json`, `package.json`, `push`.
- Cualquier archivo de TASK-009.

## 5. Arquitectura y contratos

- **Nombres:** cambio en el punto único del seeder. Sin mutadores en el modelo, sin migración.
- **Sesión:** configuración; sin migración ni código de autenticación.
- **Shiny:** `x-sprite` con `:id` + `:shiny`; los presenters de exploraciones conservan `es_shiny` en el shape de serialización existente; `siguiente` de evolución expone el flag. No se crea DTO nuevo para un bool; se documenta como deuda.
- **Assets:** no se tocan en v2. `missing-shiny.md` define el universo y las ambigüedades.
- Detalle en `architecture.md` y `missing-shiny.md`.

## 6. Archivos que se modificarán

| Archivo | Acción | Responsable |
|---|---|---|
| `database/seeders/PokemonSeeder.php` | Modificar `:116` + import | database |
| `config/session.php` | Modificar fallback | backend |
| `.env.example` | Modificar `:31` | backend |
| `docker-compose.yml` | Añadir `SESSION_LIFETIME` | backend |
| `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php` | Modificar | backend |
| `src/Exploraciones/Presentation/PresentadorExploraciones.php` | Modificar | backend |
| `src/Exploraciones/Presentation/ResultadoExploracionVista.php` | Modificar | backend |
| `src/Exploraciones/App/FinalizarExploracionHandler.php` | Modificar | backend |
| `resources/views/exploraciones/index.blade.php` | Modificar | frontend |
| `app/Support/ReclutadoVistaPresenter.php` | Modificar | backend |
| `resources/views/reclutado/show.blade.php` | Modificar | frontend |
| `.env` local | Actualizar solo `SESSION_LIFETIME` (no versionado) | backend/desarrollador |

## 7. Orden de ejecución y dependencias

1. **F0 — Base limpia:** TASK-009 commitea sus cambios pendientes; `git status` limpio; crear rama `task-010` desde `refactor/ui-tokens`. Sin este paso, TASK-010 queda `BLOCKED`.
2. **F1 — Nombres:** cambio de seeder (`database`).
3. **F2 — Sesión:** config + `.env.example` + compose (`backend`).
4. **F3 — Shiny backend:** `restarPerdidas` → transformador → presentador → resultado → `ReclutadoVistaPresenter` (`backend`).
5. **F4 — Shiny vistas:** `exploraciones/index`, `reclutado/show` (`frontend`), después de F3.
6. **F5 — Verificación estática y manual** (`orchestrator` + especialistas).
7. **F6 — Seguridad** (`security`): obligatoria por la sesión.
8. **F7 — Revisión** (`reviewer`): solo si el desarrollador la solicita.
9. **F8 — Informe final** (`orchestrator`).

Dependencias: F4 depende de F3; F5 depende de F1–F4; F6 depende de F2; F7 depende de F5.

## 8. Criterios de aceptación verificables

- [ ] `PokemonSeeder` aplica `Str::ucfirst` en el punto único; una inserción nueva guardaría `Bulbasaur`, no `bulbasaur`.
- [ ] No se ha creado migración ni se ha modificado ningún nombre existente.
- [ ] `php artisan config:show session` muestra `lifetime=525600` en local y en Docker.
- [ ] La sesión sobrevive al cierre del navegador (`expire_on_close=false`) y no se pierde por inactividad antes de 1 año.
- [ ] Un shiny con asset disponible se ve shiny en slots/listado/detalle de equipo, combate (campo, log, captura), cabecera de explorador, resumen de capturados y ficha de reclutado/evolución.
- [ ] Un no shiny se ve normal.
- [ ] Fallback shiny → normal → `0.webp` funciona sin imagen rota.
- [ ] `missing-shiny.md` contiene la lista completa y verificable de los 59 Pokémon reales.
- [ ] No se ha modificado ningún archivo de `tests/` ni dependencias.
- [ ] `git diff --check` sin errores; Pint aplicado a PHP modificado.

## 9. Verificación requerida

**Análisis estático**
- `php -l` sobre cada PHP modificado.
- `vendor/bin/pint --dirty --format agent`.
- `vendor/bin/phpstan analyse --no-progress` según `phpstan.neon`.
- `vendor/bin/phpcs` según `phpcs.xml`, si aplica.
- `php artisan config:show session`.
- `grep` de `es_shiny` en `src/Exploraciones` y `app/Support`.
- `grep` de `iconos_webp/shiny` en vistas para detectar URLs sin `_s`.
- `git diff --check`.

**Comprobación manual**
- Equipo: slot, listado, modal de detalle y reemplazo con un shiny y un no shiny.
- Combate: campo, registro y modal de captura con shiny.
- Exploraciones: cabecera del explorador y resumen de capturados.
- Ficha de reclutado: sprite principal y siguiente evolución.
- Sesión: login, cierre de navegador, reapertura y `config:show`.

**Sin tests.** No se ejecuta PHPUnit, no se crean ni reparan tests.

## 10. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| TASK-009 deja cambios sin commitear y bloquea la base | Alto | F0 obligatorio; no iniciar hasta `git status` limpio |
| Las filas existentes siguen en minúsculas | Aceptado | Decisión del desarrollador: no hay backfill |
| Re-ejecutar el seeder no capitaliza 38 formas Alola/Galar | Medio | No se re-ejecuta; documentado |
| Consumidores case-sensitive de `name` | Medio | Grep final; `PreferenciasShowdown` normaliza |
| Exploraciones no generan shiny salvaje | Alto (producto) | Documentado; si se requiere, tarea de dominio aparte |
| 59 formas sin asset shiny seguirán viéndose normales | Aceptado | Decisión pendiente; `missing-shiny.md` |
| Sesión de 1 año robada | Alto | HTTPS + `SESSION_SECURE_COOKIE=true` en producción, HttpOnly, SameSite, logout visible, limpieza de `sessions` |
| `config:cache` conserva 120 | Medio | `config:clear`/`config:cache`; verificar con `config:show` |
| Tests existentes esperan nombres en minúsculas | Bajo | Fuera de alcance; no se ejecutan ni modifican |

## 11. Rollback

- **Código:** `git revert` de los commits de TASK-010; no se hace `push`.
- **Sesión:** revertir `config/session.php`, `.env.example` y `docker-compose.yml`; limpiar caché de configuración.
- **Nombres:** revertir el cambio del seeder. No hay migración ni datos que restaurar.
- **Vistas/presenters:** revertir commits; no hay migración de esquema.

## 12. Solicitud de aprobación

Se solicita aprobación explícita de **TASK-010 plan v2**. La aprobación debe mencionar la versión. Sin ella no se inicia `IMPLEMENTING`.

Decisiones que el desarrollador puede ajustar:

1. **Sesión:** 1 año de inactividad (D1) vs 1 año absoluto o «recordarme».
2. **Assets shiny:** se dejan fuera de v2; se definirán después con `missing-shiny.md`.
3. **Exploraciones:** se acepta que no se generen shiny salvajes (D5).
4. **Revisión:** aceptar que `reviewer` no se lance salvo petición (D7).
5. **Base:** TASK-009 debe commitearse antes de crear `task-010`.
