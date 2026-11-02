# TASK-006 — Plan v1

- **ID:** TASK-006
- **Versión:** v1
- **Fecha:** 2026-11-02
- **Estado:** pendiente de aprobación
- **Basado en:** `analysis.md`

## 1. Objetivo

Corregir dos problemas de presentación:

1. La vista de hábitat muestra «Reclutado eliminado» cuando el reclutado existe pero no tiene alias; debe mostrar el nombre de su Pokémon.
2. El modal de recompensas de combate debe usar el mismo formato que el grid de Exploraciones: capturas a 1/3 y caramelos a 2/3 con tres filas, o caramelos a ancho completo si no hay capturas.

## 2. Alcance

### A. Nombre del reclutado en hábitat

- `app/Http/Controllers/HabitatsController.php`: eager-load `reclutado.pokemon` en la consulta de exploraciones activas.
- `resources/views/habitats/show.blade.php`: fallback `nombre` → `pokemon.name` → `'Reclutado eliminado'`, tratando cadenas vacías.
- Tests: caso con `nombre=null` y Pokémon existente → nombre del Pokémon; caso con relación nula → fallback.

### B. Recompensas de combate

- `resources/views/livewire/combate.blade.php` (rama `layout === 'ruta'`):
  - Grid `lg:grid-cols-3` con capturas `lg:col-span-1` y caramelos `lg:col-span-2` si hay capturas.
  - Caramelos en tres filas: familia, EV y tipo.
  - Sin capturas → caramelos `lg:col-span-3`.
  - Sin caramelos → capturas `lg:col-span-3`.
  - Mantener la rama `layout === 'entrenador'` intacta.
- Opcional: `BatallaVistaPresenter` expone `tieneCapturas` para que la vista no derive el booleano.
- Tests: render del modal con capturas + caramelos y sin capturas.

## 3. Fuera de alcance

- Cambiar la regla de recompensas, el cálculo de capturas/caramelos o el contrato persistido.
- Migraciones, dependencias o cambios de dominio.
- Rediseñar la rama de entrenador/gimnasio.

## 4. Archivos y responsables

| # | Archivo | Responsable |
|---|---|---|
| 1 | `app/Http/Controllers/HabitatsController.php` | `backend` |
| 2 | `resources/views/habitats/show.blade.php` | `frontend` |
| 3 | `resources/views/livewire/combate.blade.php` | `frontend` |
| 4 | `app/Livewire/Presenters/BatallaVistaPresenter.php` (opcional `tieneCapturas`) | `backend` |
| 5 | `tests/Feature/HabitatsControllerTest.php` / `HabitatsViewTest.php` | `backend`/`frontend` |
| 6 | `tests/Feature/CombateLivewireTest.php` (o nuevo test del modal) | `backend`/`frontend` |

## 5. Criterios de aceptación

1. Un reclutado con `nombre=null` y `pokemon.name='rhyperior'` muestra «rhyperior», no «Reclutado eliminado».
2. «Reclutado eliminado» solo aparece si la relación `reclutado` es realmente nula.
3. El modal de recompensas de ruta muestra capturas 1/3 + caramelos 2/3 cuando hay capturas.
4. Los caramelos se agrupan en 3 filas: familia, EV y tipo.
5. Sin capturas, los caramelos ocupan el ancho completo; sin caramelos, las capturas ocupan el ancho completo.
6. La rama de entrenador/gimnasio no cambia.
7. Tests de hábitat y combate en verde; PHPStan/Pint sin errores nuevos.
8. Sin migraciones ni dependencias.

## 6. Pruebas

```bash
vendor/bin/phpunit --filter 'HabitatsControllerTest|HabitatsViewTest|CombateLivewireTest' --no-coverage
vendor/bin/phpunit --filter 'Habitats|Combate' --no-coverage
```

Después, PHPStan y Pint sobre los archivos tocados.

## 7. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| N+1 al acceder a `reclutado.pokemon` | bajo | Eager-load `reclutado.pokemon` en el controlador |
| Fallback `??` no cubre cadena vacía | bajo | Usar `?:`/`trim` en la vista |
| Grid rompe en móvil | bajo | `grid-cols-1 lg:grid-cols-3` |
| Test del modal acoplado a Livewire | medio | Usar el helper `Livewire::test` y aserciones de clases/estructura, no de estilos completos |
| `layout='ruta'` también aplica a entrenadores con caramelos | bajo | Es intencional: el layout depende de los datos, no del tipo de combate |

## 8. Rollback

Revertir los hunks de los archivos de vista/controlador y tests. Sin migraciones ni datos.

## 9. Aprobación

Se solicita aprobación explícita de este plan v1. Texto esperado: `Aprobar plan TASK-006 v1`.
