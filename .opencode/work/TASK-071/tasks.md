# TASK-071 — Tareas

Plan v3. Estado global: **implementado**.

## Fase 0 — Retirada de torres

| # | Tarea | Estado |
|---|---|---|
| 1 | Borrar `src/Torres/**`, controlador de vista, modelos, seeder, migraciones y vistas | Hecha |
| 2 | Quitar referencias de `routes/web.php`, navbar, presentador, seeder y comentarios | Hecha |
| 3 | Verificar con `grep` que no quedan referencias de producción | Hecha |

## Fase 1 — Dominio

| # | Tarea | Estado |
|---|---|---|
| 4 | Entidades `FaccionRival`, `OleadaRival` y `CombateRival` + colecciones | Hecha |
| 5 | `CatalogoFaccionesRivales` con 5 facciones, 3 oleadas, 4 combates por oleada | Hecha |
| 6 | 3 plantillas de recluta + 2 jefes secundarios + 1 capitán por facción | Hecha |
| 7 | `ReglasHabitatTemporal` (umbrales 2/3/4/5/6, oleadas 9/15, ×3, nivel exacto) | Hecha |
| 8 | `perfilIvsEvs()`: oleada 1 TopUno/jefe TopDos; oleada 2 TopDos/jefe Completo; oleada 3 Completo | Hecha |
| 9 | DTOs (`ProvinciaTemporal`, `FaccionResumen`, `FaccionDetalle`, `OleadaVista`, `CombateVista`, `ProgresoHabitatTemporal`, `ResultadoHabitatTemporal`) | Hecha |
| 10 | Enums y excepciones de dominio | Hecha |
| 11 | Interfaces de repositorio (`Progreso...`, `Medallas...`) | Hecha |

## Fase 2 — Persistencia

| # | Tarea | Estado |
|---|---|---|
| 12 | Migración `create_habitat_temporal_progreso_table` (`oleada_actual`, `combate_actual`) | Hecha |
| 13 | Modelo `HabitatTemporalProgreso` | Hecha |
| 14 | Repositorio Eloquent de progreso | Hecha |
| 15 | Repositorio Eloquent de medallas | Hecha |
| 16 | Bindings en `AppServiceProvider` | Hecha |

## Fase 3 — Casos de uso

| # | Tarea | Estado |
|---|---|---|
| 17 | `ObtenerProvinciaTemporal` | Hecha |
| 18 | `ObtenerDetalleFaccion` (3 oleadas × 4 combates) | Hecha |
| 19 | `IniciarCombateHabitatTemporal` (nivel exacto, perfil por oleada/jefe) | Hecha |
| 20 | `RegistrarResultadoHabitatTemporal` (avance combate/oleada) | Hecha |

## Fase 4 — API y rutas

| # | Tarea | Estado |
|---|---|---|
| 21 | `HabitatTemporalController` | Hecha |
| 22 | `routes/habitats_temporales.php` + carga en `routes/web.php` | Hecha |
| 23 | Imágenes servidas desde `public/images/rivales` (sin ruta propia) | Hecha |
| 24 | Ruta y controlador de vista del detalle | Hecha |
| 25 | `whereNumber('id')` en `/habitats/{id}` | Hecha |

## Fase 5 — Presentación

| # | Tarea | Estado |
|---|---|---|
| 26 | `HabitatsController@index` inyecta la provincia temporal | Hecha |
| 27 | Pestaña «Temporal» + tarjetas con borde/imagen/progreso | Hecha |
| 28 | Vista de detalle con 3 secciones de oleada y 4 combates por oleada | Hecha |
| 29 | Badge de jefe y estados por combate | Hecha |
| 30 | Quitar «Torres» de la navbar | Hecha |

## Fase 6 — Batalla

| # | Tarea | Estado |
|---|---|---|
| 31 | Rama `habitat_temporal` en `PresentadorResultadoBatalla` | Hecha |
| 32 | Quitar rama y método de torre | Hecha |
| 33 | Recompensas ×3 + logs de combate/oleada/completado | Hecha |
| 34 | Verificar meta de sesión, avance y anti-IDOR | Hecha |

## Fase 7 — Verificación

| # | Tarea | Estado |
|---|---|---|
| 35 | `php -l` de los PHP tocados | Hecha |
| 36 | `vendor/bin/pint` de los archivos tocados | Hecha |
| 37 | `vendor/bin/phpstan analyse` | Hecha (0 errores) |
| 38 | `php artisan route:list` de habitats-temporales y torres | Hecha (sin rutas de torres) |
| 39 | `php artisan migrate` (solo migración nueva) | Hecha |
| 40 | Criterios de aceptación manuales | Hecha |
| 41 | Tests | No aplica (política del proyecto) |

## Corrección posterior — formación compartida

| # | Tarea | Estado |
|---|---|---|
| 42 | Extraer modal de formación a `partials/formacion-combate` y estado a `formacion-combate-script` | Hecha |
| 43 | Endpoint `GET /api/habitats-temporales/{faccion}/pc` + `CalcularComparacionPcHabitatTemporal` | Hecha |
| 44 | Reutilizar el modal en gimnasios y hábitats temporales; retirar el selector simple | Hecha |
| 45 | Mostrar 4 entrenadores de la oleada actual con estilos de gimnasio | Hecha |
| 46 | Retorno de victoria al hábitat o a `/habitats` al completar | Hecha |
