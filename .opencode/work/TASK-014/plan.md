# TASK-014 — Plan v1 — Cosecha global, bonus, iconos y Recipiente

- **ID:** TASK-014
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-04
- **Proyecto:** `/home/david/pokemon` — Laravel 12 / PHP 8.4 / Alpine
- **Base:** `request.md`, `analysis.md`, `architecture.md`.

---

## 1. Objetivo

1. **Cosecha global desde el menú:** sustituir el botón `?` por `<i class="fa-solid fa-seedling"></i>`; al pulsarlo, cosechar todas las plantaciones listas del usuario en todos sus hábitats y nidos.
2. **Bonus de plantaciones:** confirmar que las bayas plantadas aumentan la probabilidad de obtener esas bayas en exploración (ya implementado) y mostrarlo en la UI.
3. **Iconos de items:** convertir los 16 PNG de `public/images/items` a WebP con nombres limpios y mapearlos a pociones, mejoras y recipiente.
4. **Recipiente:** mostrar en la forja los 7 bonguris con tus existencias y su origen (Galicia, Euskadi, Barcelona, Extremadura, Nova Alacant, Mutxamel, Andalucía), disponibles primero.

## 2. Alcance

### Incluido

- Caso de uso `CosecharTodo`, DTOs/colecciones, endpoint `POST /api/farm/harvest-all`.
- Botón de menú con Font Awesome local, estados de carga/éxito/error y evento `inventario:actualizado`.
- Listeners de refresco en granja y forja.
- Nota estática del bonus de plantaciones.
- Conversión PNG → WebP, movimiento de originales y actualización de `ClavePocion`, `ClaveMejora`, `ClaveMaterial`, `CatalogoItems`.
- `ClaveBonguri::origen()`, `IngredienteDetalle::$origen`, `ListarRecetas` y UI de Recipiente.
- Dependencia npm `@fortawesome/fontawesome-free`.
- Documentación: ADR-0003, `docs/architecture.md`, `src/Granjas/context.md`.
- Verificación estática, revisión y seguridad.

### Fuera de alcance

- F6/F7 y cambios de balance de exploración.
- Modificar `ReglasPlantacion` o `CalculadorBotinExploracion`.
- Cosecha automática por cron.
- Tests: no se crean, actualizan ni ejecutan.
- Commit/push, migraciones, despliegue.
- Iconos propios para reductores/resistencias (no existen en `public/images/items`).

## 3. Decisiones

| ID | Decisión |
|---|---|
| D1 | Cosecha global con una transacción por nido, errores parciales y respuesta agregada. |
| D2 | Ruta `POST /api/farm/harvest-all` bajo `auth`; sin body. |
| D3 | El botón de menú usa Font Awesome 6 Free local vía npm; se elimina el popup de ayuda. |
| D4 | Evento `inventario:actualizado` para refrescar granja/forja. |
| D5 | Bonus actual intacto; solo nota UI. |
| D6 | WebP con `cwebp -q 85 -m 6 -alpha_q 100 -metadata none`; PNG originales a `storage/app/imports/items-png/`. |
| D7 | `material:recipiente` se muestra como **Éter** y usa `material/recipiente.webp`. |
| D8 | Recipiente en forja: lista de 7 bonguris con existencia y origen; disponibles primero. |
| D9 | El botón de cosecha se oculta en `/combate*` para no alterar inventario en batalla. |

## 4. Diseño backend

### 4.1 Cosecha global

- `CosecharTodo::handle(int $userId): ResultadoCosechaGlobal`.
- 3 queries de selección: granjas, nidos nivel ≥1, plantaciones candidatas.
- Por nido: `DB::transaction(..., 3)` con lock de nido y plantaciones por id; recálculo de stock; `harvest_count`; inventario agregado por baya ordenado por `item_key`.
- Errores por nido capturados y reportados; el resto continúa.
- DTOs: `ResultadoCosechaGlobal`, `BayaCosechada`, `ErrorCosecha`, `CosechaNido`; colecciones tipadas.
- `GranjaController::cosecharTodo()` + ruta.

### 4.2 Recipiente

- `ClaveBonguri::origen(): string` con el mapping color → origen.
- `IngredienteDetalle`: `?string $origen = null`; `toArray()` lo incluye.
- `ListarRecetas`: rellena `origen` para ingredientes `bonguri:*`.
- `forjaPage`: `variantesRecipiente(grupo)` ordena disponibles primero.
- UI: bloque especial para `material:recipiente`; lista de 7 bonguris con icono, nombre, `disponible/requerido`, origen y botón Fabricar.

## 5. Diseño UI

### 5.1 Menú

- Eliminar `?`, popup y `x-data` de ayuda.
- Botón con `<i class="fa-solid" :class="cosechando ? 'fa-spinner fa-spin' : 'fa-seedling'"></i>`.
- `cosecharTodo()` con fetch POST, CSRF, timeout 15 s.
- Mensajes `+N bayas`, `Nada que cosechar`, `Error`; `role="status"`/`aria-live`.
- Oculto en `/combate*`.

### 5.2 Granja y forja

- `habitatShow`: listener `inventario:actualizado`; `loadGranja` con opciones `preservarFamilia` y `silenciosa`; sincroniza catálogo de bayas.
- `forjaPage`: listener y `cargar(false)`.
- Nota de bonus en plantaciones.

### 5.3 Forja Recipiente

- Tarjeta “Recipiente (Éter) ×1 por fabricación”.
- Lista de 7 bonguris; cada fila: icono, `Bonguri X`, `Tienes N`, `Origen: ...`, botón Fabricar.
- Botón deshabilitado si no hay bonguri o mientras se fabrica.
- El resto de grupos mantiene el selector actual.

## 6. Archivos

### Crear

| Archivo | Responsable |
|---|---|
| `src/Granjas/App/CosecharTodo.php` | backend |
| `src/Granjas/Domain/DataTransferObjects/{ResultadoCosechaGlobal,BayaCosechada,ErrorCosecha,CosechaNido}.php` | backend |
| `src/Granjas/Domain/Collections/{BayasCosechadasCollection,ErroresCosechaCollection}.php` | backend |
| `public/images/items/pocion/*.webp` (8) | frontend |
| `public/images/items/mejora/*.webp` (7) | frontend |
| `public/images/items/material/recipiente.webp` | frontend |
| `storage/app/imports/items-png/*.png` (16 movidos) | frontend |
| `docs/adr/0003-cosecha-global-e-iconos-items.md` | documenter |

### Modificar

| Archivo | Responsable |
|---|---|
| `routes/granjas.php` | backend |
| `src/Granjas/Infra/Controllers/GranjaController.php` | backend |
| `src/Shared/Domain/Items/{ClavePocion,ClaveMejora,ClaveMaterial,ClaveBonguri,CatalogoItems}.php` | backend/frontend |
| `src/Forja/App/DataTransferObjects/IngredienteDetalle.php` | backend |
| `src/Forja/App/ListarRecetas.php` | backend |
| `resources/views/layouts/app.blade.php` | frontend |
| `resources/views/habitats/show.blade.php` | frontend |
| `resources/views/forja/index.blade.php` | frontend |
| `resources/css/app.css` | frontend |
| `package.json`, `package-lock.json` | frontend |
| `docs/architecture.md` | documenter |
| `src/Granjas/context.md` | documenter |

## 7. Orden de ejecución

1. Backend cosecha global (DTOs/colecciones → `CosecharTodo` → controlador/ruta).
2. Assets: conversión/movimiento y enums.
3. Font Awesome: npm + imports + build.
4. Menú y listeners.
5. Recipiente: `ClaveBonguri::origen`, `IngredienteDetalle`, `ListarRecetas`, UI.
6. Documentación.
7. Verificación estática, revisión y seguridad.
8. Informe final.

Dependencias: 1 independiente; 2 y 3 independientes; 4 depende de 1 y 3; 5 depende de 2 (iconos) y del backend de forja; 6 al final.

## 8. Criterios de aceptación

1. `POST /api/farm/harvest-all` bajo `auth` devuelve `total_bayas`, `total_plantaciones`, `nidos`, `bayas`, `errores`.
2. Dos peticiones inmediatas: la segunda devuelve 0 y no altera inventario.
3. Un fallo en un nido no impide cosechar los demás.
4. Selección con 3 queries; locks nido → plantaciones por id; inventario por `item_key`.
5. El botón `?` y su popup ya no existen; el botón muestra `fa-seedling`, se deshabilita durante la petición y muestra el resultado.
6. Al cosechar con la granja abierta, se refresca sin cerrar ni resetear la pestaña; la forja recarga.
7. Los 16 WebP existen; no quedan PNG en `public/images/items`; originales en `storage/app/imports/items-png`.
8. `material:recipiente` muestra **Éter**, icono `material/recipiente.webp`, descripción “Recipiente para fabricar objetos”.
9. Nota de bonus visible; `ReglasPlantacion`/`CalculadorBotinExploracion` intactos.
10. Forja Recipiente lista los 7 bonguris con existencia y origen, disponibles primero; fabrica la variante correcta y deshabilita sin stock.
11. `IngredienteDetalle::toArray()` incluye `origen` para bonguris y no rompe otros ingredientes.
12. `php -l`, Pint, PHPStan y deptrac sin errores nuevos; `npm run build` OK; `tests/` sin cambios.

## 9. Verificación

```bash
php -l <archivos nuevos/modificados>
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
vendor/bin/deptrac analyse --no-progress
vendor/bin/pint --test --dirty --format agent
php artisan route:list --path=api/farm
php artisan view:cache && php artisan view:clear
npm run build
find public/images/items -name '*.png'          # vacío
ls storage/app/imports/items-png/*.png | wc -l  # 16
grep -n "unknown" src/Shared/Domain/Items/ClavePocion.php src/Shared/Domain/Items/ClaveMaterial.php
grep -n "fa-seedling\|inventario:actualizado" resources/views/layouts/app.blade.php resources/views/habitats/show.blade.php resources/views/forja/index.blade.php
```

Comprobaciones manuales: dos cosechas seguidas; cosecha con granja abierta; forja Recipiente con 0/1/varios bonguris; iconos en inventario/forja/combate; búsqueda de bonguris por origen. Sin tests.

## 10. Riesgos y mitigaciones

| Nivel | Riesgo | Mitigación |
|---|---|---|
| Alto | Doble cosecha | Locks + `harvest_count` + botón deshabilitado. |
| Alto | Deadlock | Orden nido → plantación, inventario por `item_key`, reintento 3. |
| Medio | Datos obsoletos en granja/forja | Evento `inventario:actualizado`. |
| Medio | Font Awesome no resuelve | npm local + build; fallback SVG si falla. |
| Medio | Cosecha en combate | Ocultar botón en `/combate*`. |
| Bajo | Origen duplicado | `ClaveBonguri::origen()` como fuente única. |
| Bajo | PNG originales | Mover a `storage/app/imports/items-png/`; no borrar. |
| Bajo | `IngredienteDetalle` aditivo | Campo `origen` opcional. |

## 11. Rollback

- Revertir archivos nuevos/modificados de TASK-014.
- Restaurar los PNG desde `storage/app/imports/items-png/` si se revierte la conversión.
- Revertir `package.json`/`package-lock.json` y eliminar la dependencia si se revierte Font Awesome.
- No hay migraciones.
- No se hace `git reset --hard`, `git clean` ni `git checkout --` sin aprobación.

## 12. Aprobación

Este plan no autoriza implementación hasta una aprobación explícita que mencione la versión, por ejemplo:

```text
Aprobar plan v1 (TASK-014)
```

La aprobación se registrará en `approval.md` con el texto literal, la fecha y el alcance autorizado. Incluye autorizar la instalación local de `@fortawesome/fontawesome-free` y el movimiento de los PNG originales a `storage/app/imports/items-png/`.
