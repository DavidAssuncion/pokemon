# TASK-066 — Plan

- **ID:** TASK-066
- **Versión:** v1
- **Estado:** AWAITING_APPROVAL
- **Fecha:** 2026-11-19
- **Proyecto:** `/home/david/pokemon`

## 1. Objetivo

Corregir el filtro por nombre de la Pokédex para que no distinga mayúsculas y
minúsculas, manteniendo la búsqueda parcial y el resto de filtros sin cambios.
Ajustar el placeholder para que no prometa búsqueda por número, ya que no se
implementa en esta tarea.

## 2. Alcance

- Cambiar `LIKE` por `orWhereLike()` en `DatagridService::applySearch()`.
- Actualizar el placeholder y el `aria-label` del buscador de Pokédex a
  "Buscar por nombre...".
- Verificar que la búsqueda sigue respetando la pestaña activa
  (Vistos / Todos / Atrapados) y el resto de filtros.

## 3. Fuera de alcance

- Búsqueda por número/id.
- Búsqueda que ignore la pestaña activa.
- Normalización de grafías canónicas (`Mr. Mime`, `Farfetch'd`, `Nidoran♀`).
- Escapado de comodines `%`/`_`.
- Cambios en índices o migraciones.
- Cambios en TASK-061/TASK-064.
- Tests.

## 4. Causa raíz y corrección

`DatagridService::applySearch()` aplica `orWhere(..., 'like', '%term%')`. En
PostgreSQL `LIKE` distingue mayúsculas y los nombres están capitalizados, así que
`pikachu` y `PIKACHU` devuelven 0 mientras `Pikachu` devuelve 1.

Corrección en `app/Datagrid/DatagridService.php`:

```php
// antes
$q->orWhere($this->qualifyColumn($table, $column), 'like', $term);
// después
$q->orWhereLike($this->qualifyColumn($table, $column), $term);
```

`orWhereLike()` usa `caseSensitive: false` por defecto: compila a `ilike` en
PostgreSQL y a `like` en SQLite. No requiere migración ni dependencia nueva.

Vista `resources/views/pokedex/index.blade.php`:

```text
placeholder="Buscar por nombre..."
aria-label="Buscar por nombre"
```

## 5. Archivos y responsables

| Archivo | Cambio | Responsable |
|---|---|---|
| `app/Datagrid/DatagridService.php` | `orWhere('like')` → `orWhereLike()` | `backend` |
| `resources/views/pokedex/index.blade.php` | placeholder y aria-label | `frontend` |

Sin cambios en `PlayerController.php`, `DatagridServiceProvider.php`, rutas,
migraciones ni configuración.

## 6. Orden de ejecución

1. `backend`: corregir `DatagridService::applySearch()`.
2. `frontend`: ajustar placeholder/aria-label.
3. Verificación estática y funcional de solo lectura.
4. Revisión independiente.

Los dos archivos son disjuntos; pueden implementarse en paralelo.

## 7. Criterios de aceptación

1. `search=pikachu`, `search=Pikachu` y `search=PIKACHU` devuelven el mismo
   `meta.total` (en la BD actual, 1 resultado: Pikachu).
2. `search=Pika` sigue devolviendo 1 (búsqueda parcial).
3. `search=` vacío devuelve el listado completo sin `where` de nombre.
4. `search=pikachu&filter[visto]=1` respeta la pestaña: devuelve 1 solo si el
   usuario tiene Pikachu visto; con `filter[visto]=0`, 0.
5. Los filtros de tipo, esfuerzo, habilidad, movimiento, visto/atrapado,
   paginación y orden mantienen su comportamiento.
6. En PostgreSQL el SQL generado usa `ilike`; en SQLite el resultado sigue siendo
   insensible a mayúsculas en ASCII.
7. El placeholder y el `aria-label` ya no prometen búsqueda por número.
8. No se modifica `PlayerController.php` ni las líneas de TASK-061/TASK-064.
9. `vendor/bin/pint --dirty --format agent` sin cambios pendientes.

## 8. Verificación requerida

Sin tests. Se ejecutará:

```bash
php -l app/Datagrid/DatagridService.php
vendor/bin/pint --dirty --format agent
php artisan tinker --execute '...'   # consultas de solo lectura para pikachu/Pikachu/PIKACHU, Pika, vacío
git diff -- app/Datagrid/DatagridService.php resources/views/pokedex/index.blade.php
git status --short
```

Comprobación manual:
- Abrir `/pokedex`, escribir `pikachu`, `Pikachu` y `PIKACHU` y comprobar que
  devuelven el mismo resultado.
- Probar con la pestaña "Vistos" y "Todos".
- Probar un filtro de tipo junto con la búsqueda.

`composer analyse` puede reportar deuda preexistente; se comprobará que no haya
errores nuevos en `DatagridService.php`.

## 9. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Cambio global de semántica en todos los datagrids con `search` | Es una mejora coherente; verificar `team`, `habitat`, `gym`, `reclutado` con términos existentes |
| `ILIKE` no usa índice btree | 1083 filas; irrelevante. Si crece, índice GIN `pg_trgm` |
| Comodines `%`/`_` del usuario | Comportamiento preexistente; fuera de alcance |
| Búsqueda por número sigue sin funcionar | Se retira la promesa del placeholder; si se quiere, tarea aparte |
| Confusión con la pestaña "Vistos" | Se mantiene el comportamiento actual y se documenta en el informe |

## 10. Rollback

Revertir el operador a `orWhere(..., 'like', $term)` y restaurar el placeholder
anterior. Sin migraciones ni datos que deshacer.

## 11. Aprobación

Requiere aprobación explícita de la versión v1 antes de implementar.
`approval.md` permanece en `Estado: PENDIENTE`.
