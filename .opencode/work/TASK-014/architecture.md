# TASK-014 — Diseño técnico

> Diseño del `architect` para cosecha global, menú, bonus, assets de items y selector de bonguris del Recipiente.

## 1. Decisiones

1. **Cosecha global:** `POST /api/farm/harvest-all` bajo `auth`; `CosecharTodo` con **una transacción por nido**, locks nido → plantaciones, recálculo de stock, inventario agregado por baya y errores parciales.
2. **Menú:** sustituir el botón `?` y su popup por un botón con `<i class="fa-solid fa-seedling"></i>`. Font Awesome 6 Free **local vía npm**, importado en `resources/css/app.css`. Estados de carga/éxito/error y evento `inventario:actualizado`.
3. **Bonus:** mantener la fórmula actual (`min(2.0, 1 + 0.20 * plantas)`), que ya multiplica el peso de las bayas plantadas en la exploración. Añadir solo una nota estática en la UI.
4. **Assets:** convertir los 16 PNG a WebP con `cwebp -q 85 -m 6 -alpha_q 100 -metadata none`; estructura `public/images/items/{pocion,mejora,material}/`; mover los PNG a `storage/app/imports/items-png/`.
5. **Recipiente:** `material:recipiente` se muestra como **Éter**, con icono `material/recipiente.webp`. En la forja se listan los 7 bonguris con existencia y origen, disponibles primero.
6. **Sin tests, sin migraciones, sin commit/push.**

## 2. Cosecha global

### Contrato HTTP

```http
POST /api/farm/harvest-all
Accept: application/json
X-CSRF-TOKEN: ...
```

Respuesta `200`:

```json
{
  "total_bayas": 12,
  "total_plantaciones": 5,
  "nidos": 3,
  "bayas": [
    {"clave":"baya:liechi","nombre":"Lichi","icono":"/images/berry/liechi-berry.webp","cantidad":6}
  ],
  "errores": [
    {"farm_id":4,"habitat_id":2,"nido_id":42,"evolution_chain_id":7,"mensaje":"..."}
  ]
}
```

### `CosecharTodo`

Dependencia: `InventarioJugador`. No crea granjas.

1. Una query de granjas del usuario.
2. Una query de nidos con `level >= 1`.
3. Una query de plantaciones candidatas; calcular stock con `ReglasPlantacion::stockDe()` y agrupar por nido.
4. Ordenar nidos por `id`.
5. Por cada nido candidato, `DB::transaction(..., 3)`:
   - Lock del nido (`whereKey` + `whereIn farm_id`).
   - Lock de plantaciones del nido por `id` ascendente.
   - Recalcular stock tras el lock; si `<= 0`, saltar.
   - Acumular por `berry_key`; `increment('harvest_count', stock)`.
   - Incrementar inventario por baya ordenada por `item_key`.
6. Agregar totales; capturar errores por nido (`DominioExcepcion` con mensaje; otras con `report()` y mensaje genérico).
7. Devolver `ResultadoCosechaGlobal`.

**Idempotencia:** dos peticiones simultáneas no duplican porque `harvest_count` se actualiza bajo lock y el stock se recalcula. El botón se deshabilita durante la petición.

**Deadlocks:** orden nido → plantaciones por id; inventario por `item_key`; reintento 3.

### DTOs/colecciones

- `ResultadoCosechaGlobal` (readonly): `totalBayas`, `totalPlantaciones`, `nidos`, `BayasCosechadasCollection`, `ErroresCosechaCollection`; `toArray()`.
- `BayaCosechada`: `clave`, `nombre`, `icono`, `cantidad`.
- `ErrorCosecha`: `farmId`, `habitatId`, `nidoId`, `evolutionChainId`, `mensaje`.
- `CosechaNido`: `MapaConteo $bayas`, `int $plantaciones`.
- `BayasCosechadasCollection`, `ErroresCosechaCollection` extends `Collection`.

### Ruta/controlador

- `GranjaController::cosecharTodo(): JsonResponse` inyecta `CosecharTodo`.
- `routes/granjas.php`: `Route::post('/api/farm/harvest-all', [GranjaController::class, 'cosecharTodo']);`

## 3. Menú y feedback

- Eliminar el bloque `?`/popup y su `x-data` de ayuda en `layouts/app.blade.php`.
- Añadir `x-data` con `cosechando`, `cosechaEstado`, `cosechaMensaje`, `cosechaDetalle`, `cosechaTimer`.
- Botón: `<i class="fa-solid" :class="cosechando ? 'fa-spinner fa-spin' : 'fa-seedling'"></i>`; `:disabled="cosechando"`; `aria-label`/`title` dinámicos.
- `cosecharTodo()`: fetch POST con CSRF y `AbortController` 15 s; parsea total/errores; muestra `+N bayas`, `Nada que cosechar` o `Error`; emite `inventario:actualizado`.
- Ocultar/deshabilitar en rutas de combate para no alterar inventario a mitad de batalla (decisión de seguridad; se implementa ocultando si `request()->is('combate*')`).
- `config/ayuda.php` queda sin uso; no se borra.

## 4. Listeners

- `habitatShow`:
  - `init()` registra `window.addEventListener('inventario:actualizado', ...)`.
  - Si `showGranjaModal && !granjaAccionando`, `loadGranja({ preservarFamilia: true, silenciosa: true })`.
  - `loadGranja` acepta opciones: no resetear `familiaActiva`, no mostrar spinner y conservar datos si falla; tras asignar `granja`, `sincronizarCatalogoBayas()`.
- `forjaPage`: registra el evento y llama `cargar(false)`.

## 5. Bonus de plantaciones

- No cambiar `ReglasPlantacion` ni `CalculadorBotinExploracion`.
- Añadir nota en la sección de plantaciones:
  “Cada planta +20% de peso, tope ×2.0; solo bayas de esa granja/hábitat.”
- El factor ya se aplica en `CalculadorBotinExploracion::pesosBayas()`; no afecta a bonguris.

## 6. Assets e iconos

### Conversión

```bash
mkdir -p public/images/items/pocion public/images/items/mejora public/images/items/material
mkdir -p storage/app/imports/items-png

cwebp -q 85 -m 6 -alpha_q 100 -metadata none "Poción_(Dream_World).png" -o "public/images/items/pocion/pocion.webp"
# ... 15 restantes
mv public/images/items/*.png storage/app/imports/items-png/
```

### Mapeo

| Origen | Destino | Enum |
|---|---|---|
| Poción | `pocion/pocion.webp` | `ClavePocion::POCION` |
| Superpoción | `pocion/super.webp` | `SUPER` |
| Hiperpoción | `pocion/hiper.webp` | `HIPER` |
| Poción máxima | `pocion/maxima.webp` | `MAXIMA` |
| Cura total | `pocion/curatotal.webp` | `CURATOTAL` |
| Restaurar todo | `pocion/restaurar_todo.webp` | `RESTAURAR_TODO` |
| Revivir | `pocion/revivir.webp` | `REVIVIR` |
| Revivir máximo | `pocion/max_revivir.webp` | `MAX_REVIVIR` |
| Ataque X | `mejora/ataque.webp` | `ClaveMejora::ATAQUE` |
| Defensa X | `mejora/defensa.webp` | `DEFENSA` |
| Ataque Especial X | `mejora/ataque_especial.webp` | `ATAQUE_ESPECIAL` |
| Defensa especial X | `mejora/defensa_especial.webp` | `DEFENSA_ESPECIAL` |
| Velocidad X | `mejora/velocidad.webp` | `VELOCIDAD` |
| Precisión X | `mejora/precision.webp` | `PRECISION` |
| Crítico X | `mejora/critico.webp` | `CRITICO` |
| Éter | `material/recipiente.webp` | `ClaveMaterial::RECIPIENTE` |

### Enums

- `ClavePocion::archivoIcono()` → `/images/items/pocion/{value}.webp`.
- `ClaveMejora::archivoIcono()` → `/images/items/mejora/{value}.webp`.
- `ClaveMaterial::archivoIcono()` → `/images/items/material/{value}.webp`.
- `ClaveMaterial::nombre()` → `Éter`.
- `CatalogoItems` descripción material → `Recipiente para fabricar objetos`.
- Reductores/resistencias no cambian.

### Font Awesome

- `npm install @fortawesome/fontawesome-free@^6.7.2`.
- En `resources/css/app.css`:
  ```css
  @import "@fortawesome/fontawesome-free/css/fontawesome.css";
  @import "@fortawesome/fontawesome-free/css/solid.css";
  ```
- Verificar que `npm run build` emite las fuentes `fa-solid-900.woff2`.

## 7. Recipiente: bonguris y origen

### Origen por color

`ClaveBonguri::origen(): string`:

| Color | Origen |
|---|---|
| Blanco | Galicia |
| Amarillo | Euskadi |
| Negro | Barcelona |
| Verde | Extremadura |
| Rosa | Nova Alacant |
| Azul | Mutxamel |
| Rojo | Andalucía |

Los nombres coinciden con `ProvinceSeeder`/`BonguriHabitatSeeder`; “Andalucía” se muestra con tilde.

### `IngredienteDetalle`

Añadir `?string $origen = null`; `toArray()` incluye `origen` (null si no aplica). `ListarRecetas` lo rellena para ingredientes `bonguri:*` con `ClaveBonguri::tryFrom(...)->origen()`.

### Forja UI

- Para el grupo `material:recipiente`, sustituir el `<select>` + ingrediente único por una lista de los 7 bonguris:
  - icono, nombre, `disponible/requerido`, `origen`, botón “Fabricar”.
  - Orden: disponibles primero (`disponible > 0`), luego orden de `ClaveBonguri`.
  - Botón deshabilitado si `puede_fabricar` es false; `@click="seleccion[grupo.clave] = variante.clave; fabricar(grupo)"`.
  - La tarjeta sigue mostrando “Recipiente (Éter) ×1 por fabricación”.
- Para el resto de grupos, mantener el `<select>` y la lista de ingredientes actual.
- Añadir `variantesRecipiente(grupo)` en `forjaPage`.

## 8. Archivos

### Crear

- `src/Granjas/App/CosecharTodo.php`
- `src/Granjas/Domain/DataTransferObjects/{ResultadoCosechaGlobal,BayaCosechada,ErrorCosecha,CosechaNido}.php`
- `src/Granjas/Domain/Collections/{BayasCosechadasCollection,ErroresCosechaCollection}.php`
- `public/images/items/pocion/*.webp` (8)
- `public/images/items/mejora/*.webp` (7)
- `public/images/items/material/recipiente.webp`
- `storage/app/imports/items-png/*.png` (16 movidos)

### Modificar

- `routes/granjas.php`
- `src/Granjas/Infra/Controllers/GranjaController.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/habitats/show.blade.php`
- `resources/views/forja/index.blade.php`
- `resources/css/app.css`
- `package.json`, `package-lock.json`
- `src/Shared/Domain/Items/{ClavePocion,ClaveMejora,ClaveMaterial,ClaveBonguri,CatalogoItems}.php`
- `src/Forja/App/DataTransferObjects/IngredienteDetalle.php`
- `src/Forja/App/ListarRecetas.php`
- `src/Granjas/context.md` (documentación)
- `docs/adr/0003-cosecha-global-e-iconos-items.md` (ADR propuesto)
- `docs/architecture.md`

### Eliminar

- Ninguno; los PNG se mueven a `storage/app/imports/items-png/`.

## 9. Orden de implementación

1. Backend cosecha global: DTOs/colecciones → `CosecharTodo` → controlador/ruta.
2. Assets: conversión, movimiento, enums, descripción.
3. Font Awesome: npm + imports + build.
4. Menú y listeners.
5. Recipiente: `ClaveBonguri::origen`, `IngredienteDetalle`, `ListarRecetas`, UI de forja.
6. Documentación y verificación.

## 10. Criterios de aceptación

1. `POST /api/farm/harvest-all` bajo `auth`, sin body, devuelve `total_bayas`, `total_plantaciones`, `nidos`, `bayas`, `errores`.
2. Dos peticiones inmediatas: la segunda devuelve `total_bayas=0` y no altera inventario.
3. Un fallo en un nido no impide cosechar los demás; se reporta en `errores`.
4. La selección inicial usa 3 queries; los locks siguen el orden nido → plantaciones por id; inventario por `item_key`.
5. El botón `?` y su popup ya no existen; el botón muestra `fa-seedling`, se deshabilita durante la petición y muestra `+N bayas`/`Nada que cosechar`/`Error`.
6. Al cosechar con la granja abierta, el modal se refresca sin cerrar ni resetear la pestaña; la forja recarga recetas.
7. Los 16 WebP existen en las rutas de los enums; no quedan PNG en `public/images/items`; los originales están en `storage/app/imports/items-png`.
8. `material:recipiente` muestra **Éter**, icono `material/recipiente.webp` y descripción “Recipiente para fabricar objetos”.
9. La nota de bonus aparece en la UI; `ReglasPlantacion`/`CalculadorBotinExploracion` no cambian.
10. En la forja, el grupo Recipiente lista los 7 bonguris con existencia y origen, disponibles primero; el botón fabrica la variante correcta y se deshabilita sin stock.
11. `IngredienteDetalle::toArray()` incluye `origen` para bonguris y no rompe los demás ingredientes.
12. Sin tests ni migraciones; `php -l`, Pint, PHPStan y deptrac sin errores nuevos; `npm run build` OK.

## 11. Riesgos

| Nivel | Riesgo | Mitigación |
|---|---|---|
| Alto | Doble cosecha | Locks + `harvest_count` + botón deshabilitado. |
| Alto | Deadlock | Orden nido → plantación, inventario por `item_key`, reintento 3. |
| Medio | Datos obsoletos en granja/forja | Evento `inventario:actualizado` y recarga silenciosa. |
| Medio | Font Awesome no resuelve | npm local + `npm run build`; fallback SVG si falla. |
| Medio | Cosecha durante combate | Ocultar el botón en `/combate*`. |
| Bajo | Nombres de origen duplicados | `ClaveBonguri::origen()` como fuente única; seeder mantiene su mapa. |
| Bajo | PNG originales | Mover a `storage/app/imports/items-png/`, no borrar. |
| Bajo | `IngredienteDetalle` aditivo | Campo `origen` opcional; UI tolera null. |
