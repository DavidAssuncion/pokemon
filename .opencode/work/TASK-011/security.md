# TASK-011 — Auditoría de seguridad (F1)

- **Estado:** `COMPLETADA`
- **Veredicto final:** `APPROVED`
- **Auditor:** `security`
- **Fecha:** 2026-11-04
- **Alcance:** Fase 1 del plan v2 (modos de exploración, bonguri por hábitat, botín de bayas/bonguris).
- **Restricción:** no se modificó código ni se ejecutaron tests.

## 1. Resumen ejecutivo

No hay hallazgos `CRITICAL` ni `HIGH`. El vector de farmeo instantáneo detectado en la primera pasada (`POST /exploraciones/{id}/recoger` con `forzarRegreso: true`) quedó cerrado con `factorTiempo`. El bypass mediante `return_time` de ~1 minuto también quedó cerrado con la validación de un mínimo de 60 minutos en `preview()` y `validarYNuevaExploracion()`.

La validación de `modo`, la propiedad del reclutado, la persistencia del botín al usuario correcto, la validación de claves de item, la idempotencia por `regreso`, CSRF y XSS se mantienen correctos. No se añadieron endpoints, dependencias ni secretos.

## 2. Hallazgos y estado

| Severidad | Hallazgo | Estado | Evidencia / mitigación |
|---|---|---|---|
| Medio | Farmeo instantáneo con `recoger` forzado: 1–3 bayas + 60 % bonguri sin tiempo transcurrido. | **Resuelto** | `factorTiempo` reduce tiradas de bayas y probabilidad de bonguri; base `inicioVuelta`; una recogida inmediata en 1 h da ~0,022. |
| Medio | Bypass con `return_time` a ~1 min: la base de `factorTiempo` se hacía 1 min y el botín era completo tras ~60 s. | **Resuelto** | `ExploracionActivaController::preview()` y `validarYNuevaExploracion()` rechazan `return_time` con menos de 60 min. |
| Bajo | Idempotencia no atómica: el chequeo `regreso !== null` no bloquea la fila; la protección real es el lock de caché (TTL 60 s). | Aceptado / residual | Idempotencia por `regreso` + transacción del `CommandBus` + `Cache::lock` en `ProcesarExploracionesPendientes`. Añadir `lockForUpdate` si se quiere blindaje extra. |
| Info | El seeder `BonguriHabitatSeeder` reasigna `bonguri` en cada arranque con `RUN_SEEDERS=true`; podría sobrescribir ediciones manuales futuras. | Aceptado | Hoy no hay UI de edición; valores constantes, sin entrada de usuario. Añadir `whereNull` si F3 permite edición. |
| Info | Balance: `multiplicadorBotin` eleva el bonguri de `botin` del 60 % al 90 %; EXPERIENCIA/RECLUTAMIENTO bajan al 12,5 %. | Aceptado | Decisión de balance; validar con telemetría. No es seguridad. |
| Info | Filas legacy sin `inicio_exploracion` ni `created_at` → `factorTiempo = 0` y no reciben botín. | Aceptado | No explotable; fallback conservador. |
| Info | Dependencias con avisos preexistentes: Laravel 12.67.0, Livewire 4.4.2, Symfony 7.4.17 (ReDoS), `league/commonmark`. | Fuera de F1 | No introducidas por F1; planificar actualización (Laravel ≥12.69, Livewire ≥4.4.8, Symfony ≥7.4.22). |

## 3. Controles verificados

- **Validación de entrada:** `modo` solo admite `experiencia|reclutamiento|botin`; `null` se normaliza a `MIXTO`; `reclutado_id` exige propiedad del usuario; `habitat_id` existe; `return_time` exige ≥60 min.
- **Autorización / IDOR:** el botín se persiste siempre para `$exploracion->user`/`user_id`; las rutas `recoger`/`cancelar`/`cerrar` están protegidas por el global scope `BelongsToUser`; no se confía en IDs de la URL para el propietario.
- **Integridad de inventario:** `RecompensaItem` valida la clave contra `CatalogoItems`; no hay claves libres hacia `player_inventory`; las pérdidas usan `intdiv(cantidad,2)` y `max(0, ...)`; el botín se suma antes de las pérdidas y se persiste dentro de la transacción.
- **Idempotencia:** `regreso !== null` + transacción del `CommandBus` + lock de caché; sin doble incremento en condiciones normales.
- **XSS:** vistas nuevas con `{{ }}` y `x-text`; sin `{!! !!}` con datos de usuario; iconos del catálogo, sin interpolación de entrada; `onerror` estático.
- **CSRF / rutas:** sin endpoints nuevos; CSRF intacto; solo cambios de comentario en rutas.
- **Secretos:** no se leyó `.env` ni se introdujeron secretos, tokens o credenciales.
- **Dependencias:** sin cambios en `composer.json`, `composer.lock`, `package.json` ni `package-lock.json`.

## 4. Riesgos residuales

- **Bajo:** con varios reclutados se pueden mantener expediciones en paralelo; cada una respeta el mínimo de ~45–60 min. Se recomienda un límite de frecuencia por usuario en una fase posterior.
- **Bajo:** la idempotencia depende del lock de caché; un TTL expirado a mitad de proceso podría permitir una segunda finalización. Considerar `lockForUpdate` sobre `exploraciones_activas`.
- **Informativo:** dependencias con avisos de seguridad preexistentes; actualizar fuera de F1.
- **Informativo:** el seeder provincial sobrescribe `bonguri`; sin impacto actual.

## 5. Archivos inspeccionados

- `app/Http/Controllers/ExploracionActivaController.php`
- `src/Exploraciones/App/FinalizarExploracionHandler.php`, `PersistirRecompensas.php`, `ProcesarExploracionHandler.php`
- `src/Exploraciones/Domain/ContextoBotin.php`, `CalculadorBotinExploracion.php`, `ModoExploracion.php`, `PerfilExploracion.php`, `PesosEvento.php`, `Recompensas/RecompensaItem*.php`, `ResultadoRecompensas.php`
- `src/Shared/Domain/Items/*`, `src/Shared/Domain/Exceptions/ItemDesconocido.php`
- `database/seeders/BonguriHabitatSeeder.php`, `CatalogoSeeder.php`
- `resources/views/exploraciones/index.blade.php`, `resources/views/habitats/show.blade.php`, `resources/views/exploraciones/_perdidas.blade.php`
- `routes/exploraciones.php`, `composer.json`, `docker/entrypoint.sh`

## 6. Verificación ejecutada

- Inspección estática del diff completo de F1 y de los archivos nuevos.
- `php -l` sin errores; `git diff --check` limpio (excluyendo `MVP.txt`).
- `composer audit` (avisos preexistentes documentados).
- Pruebas manuales del backend: `return_time` a 1 min → 422; 2 h → OK; 40 min → 422.
- **No ejecutados:** tests, migraciones destructivas ni escrituras de datos.
- **No se leyó** `.env` ni secretos.
