# TASK-011 — Revisión independiente (F1)

- **Estado:** `COMPLETADA`
- **Veredicto final:** `APPROVED`
- **Revisor:** `reviewer`
- **Fecha:** 2026-11-04
- **Alcance:** Fase 1 del plan v2 (modos de exploración, bonguri por hábitat, botín de bayas/bonguris).
- **Restricción:** no se ejecutaron tests; la revisión se basa en diff, análisis estático y criterios de aceptación.

## 1. Resumen

La implementación de F1 cumple los criterios de aceptación aprobados. No se han encontrado bloqueantes nuevos. Los dos hallazgos altos de la primera pasada quedaron resueltos:

1. **Backfill provincial en instalaciones nuevas:** se añadió `BonguriHabitatSeeder`, idempotente, registrado en `CatalogoSeeder` después de `HabitatSeeder`. Verificado en un esquema PostgreSQL temporal limpio: 56 hábitats, 55 con bonguri y “Vacio” NULL.
2. **`multiplicadorBotin` sin uso:** ahora se aplica a la probabilidad de bonguri en `CalculadorBotinExploracion::caeBonguri()`. Además, `factorTiempo` reduce el botín de las recogidas forzadas antes de tiempo.

## 2. Hallazgos nuevos (no bloqueantes)

| Severidad | Hallazgo | Evidencia | Recomendación |
|---|---|---|---|
| Medio (proceso) | `factorTiempo` es una regla de balance nueva que no figuraba en el plan v2; `multiplicadorBotin` se aplica como probabilidad y no como cantidad. | `CalculadorBotinExploracion.php:92-118,211-223`; `FinalizarExploracionHandler.php:670-690` | Registrado en el addendum del plan. Validar balance con telemetría. |
| Bajo | El seeder sobrescribe `bonguri` de todos los hábitats de la provincia, mientras la migración usaba `whereNull`. | `BonguriHabitatSeeder.php:54-56`; migración `2026_11_04_000002` | Añadir `whereNull` si en F3 se permiten colores manuales por hábitat; hoy no hay edición. |
| Bajo | Doble escalado de la probabilidad de bonguri: base por modo × `multiplicadorBotin` (BOTIN 60 % → 90 %; EXPERIENCIA/RECLUTAMIENTO 25 % → 12,5 %). | `CalculadorBotinExploracion.php:37-40,211-220` | Validar el balance y documentarlo. |
| Bajo | `composer setup` ejecuta `migrate` pero no seeders; sin `CatalogoSeeder` el mapping provincial no se materializa y todo cae al fallback blanco. | `composer.json:45-54`; `docker/entrypoint.sh:56-57` | Añadir `db:seed --class=CatalogoSeeder` al setup o documentar la dependencia. |
| NIT | `factorTiempo` usa el reloj de pared (`now()`) en la finalización. | `FinalizarExploracionHandler.php:672-692` | Aceptable: la idempotencia se mantiene por `regreso`, lock y transacción. |

## 3. Hallazgos de la primera pasada

| Hallazgo | Estado | Evidencia de cierre |
|---|---|---|
| Backfill inefectivo en `migrate:fresh` | **Resuelto** | `BonguriHabitatSeeder` + registro en `CatalogoSeeder`; prueba en esquema limpio. |
| `multiplicadorBotin` código muerto | **Resuelto** | `caeBonguri()` multiplica la probabilidad; `botin()` pasa el valor del perfil. |
| Preview no aplica multiplicadores del modo | **Abierto, no bloqueante** | `preview()` devuelve `modo` pero `EstimadorRecompensasExploracion` no recibe perfil. El botín real sí aplica el modo. Limitación documentada de F1. |
| `modo = null` ahora genera botín | **Abierto, no bloqueante** | La mezcla de eventos se mantiene (45/20/15/10/10), pero la feature de botín aplica a todas las exploraciones. Documentado. |
| Comentario “Sin bayas (TODO)” | **Resuelto** | Controlador, ruta y modelo actualizados. |
| Duplicidad de resolución de items | **Abierto, no bloqueante** | `PresentadorExploraciones::itemVista()` y `ResultadoExploracionVista::items()` resuelven de forma parecida; se acepta por seguridad de fallback. |

## 4. Criterios de aceptación

| # | Criterio | Estado | Evidencia |
|---|---|---|---|
| 1 | `modo` nullable; `null` = 45/20/15/10/10; validación `experiencia|reclutamiento|botin`; mezcla cambiable | Cumplido | `PesosEvento::para`, `SimuladorEncuentros::generarEventoDesde`, `ExploracionActivaController:87,306` |
| 2 | Bonguri válido o NULL con fallback; solo el del hábitat; backfill provincial | Cumplido con condición | `BonguriHabitatSeeder` + `ClaveBonguri::porDefecto`; requiere ejecutar `CatalogoSeeder` |
| 3 | Botín una sola vez; resumen con nombre/icono; derrota `intdiv(,2)`; claves validadas; sin tope duro | Cumplido | `FinalizarExploracionHandler`; `RecompensaItem`; `PersistirRecompensas::guardarItems`; idempotencia por `regreso` |
| 4 | `ResultadoRecompensas` compatible; `items` opcional al final | Cumplido | Constructor con `?items = null` último; flujos de ruta/entrenador intactos |
| 5 | `sitrus_berry`/`lum_berry` funcionan; `tests/` intacto | Cumplido | `ClaveItem`/`CatalogoObjetos` sin cambios; `tests/` fuera del diff |
| 6 | DDD: dominio puro, DTOs/colecciones tipadas, sin arrays como contrato | Cumplido | `CalculadorBotinExploracion` sin Eloquent/HTTP; `RecompensaItemCollection`; arrays solo en frontera |
| 7 | Compatibilidad de resultados antiguos sin `items` y payloads sin `modo` | Cumplido | Fallbacks en `PresentadorExploraciones`, `ResultadoExploracionVista` y `ModoExploracion::desde(null)` |

## 5. Riesgos residuales

- Entornos sin `RUN_SEEDERS`/`db:seed` no aplican el mapping provincial; el fallback garantiza un bonguri válido (blanco).
- Balance del bonguri en modo `botin`: ~90 % de probabilidad; revisar con telemetría.
- El preview de recompensas esperadas no refleja los multiplicadores del modo.
- `modo = null` mantiene los pesos históricos pero también recibe botín.
- `phpstan` (34 errores) y `deptrac` (113 violaciones) son deuda preexistente; no hay errores nuevos en archivos de F1.
- Dependencias con avisos de seguridad preexistentes (Laravel, Livewire, Symfony); actualizar fuera de F1.

## 6. Verificación ejecutada

- `git status` y `git diff` de los archivos de F1 y del seeder nuevo.
- `php -l` de los archivos nuevos/modificados: sin errores.
- `phpstan` (nivel 6): 34 errores preexistentes; ninguno en archivos de F1.
- `deptrac`: 113 violaciones preexistentes; ninguna en las clases nuevas.
- `php artisan view:cache` / `view:clear`: correctos.
- `php artisan route:list --path=exploraciones`: 7 rutas esperadas.
- `git diff --check` sobre `src`, `app`, `database`, `routes`, `resources`: limpio (solo avisos ajenos en `MVP.txt`).
- **No ejecutados:** tests, migraciones destructivas ni escrituras de datos.

## 7. Archivos inspeccionados

- Plan, arquitectura, análisis A1/A2, aprobación y artefactos de TASK-011.
- Migraciones `2026_11_04_000001_add_modo_to_exploraciones_activas.php` y `2026_11_04_000002_add_bonguri_to_habitats.php`.
- `database/seeders/BonguriHabitatSeeder.php`, `CatalogoSeeder.php`.
- `src/Shared/Domain/Items/*`, `ItemDesconocido.php`.
- `src/Exploraciones/Domain/ModoExploracion.php`, `PesosEvento.php`, `PerfilExploracion.php`, `ContextoBotin.php`, `CalculadorBotinExploracion.php`, `Recompensas/RecompensaItem*.php`, `ResultadoRecompensas.php`, `SimuladorEncuentros.php`.
- `src/Exploraciones/App/FinalizarExploracionHandler.php`, `PersistirRecompensas.php`, `ProcesarExploracionHandler.php`.
- `app/Http/Controllers/ExploracionActivaController.php`, `app/Models/ExploracionActiva.php`, `Habitat.php`, `app/Support/ItemCatalogo.php`.
- `src/Exploraciones/Presentation/*`, `resources/views/exploraciones/index.blade.php`, `resources/views/habitats/show.blade.php`, `_perdidas.blade.php`.
- `composer.json`, `docker/entrypoint.sh` (contexto de despliegue).
