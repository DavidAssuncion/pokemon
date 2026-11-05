# TASK-014 — Análisis

> Resultado del `analyst` para TASK-014.

## Resumen

- La cosecha global no existe; hay que crear un caso de uso sin hábitat, con transacciones por nido y locks ordenados.
- El botón `?` y su popup solo viven en `resources/views/layouts/app.blade.php:25-66`; `config/ayuda.php` no tiene otras referencias.
- No hay Font Awesome cargado; el proyecto usa SVGs inline.
- Los 16 PNG de `public/images/items` son 8 pociones, 7 mejoras X y Éter. No hay assets para reductores ni resistencias.
- El bonus de plantaciones ya existe: `ReglasPlantacion::factorPlantacion()` = min(2.0, 1 + 0.20 * plantas); `CalculadorBotinExploracion::pesosBayas()` multiplica el peso de cada baya. No toca bonguris.
- `ClaveMaterial::nombre()` = "Recipiente"; el asset de Éter debe representar el recipiente.

## Evidencia

- `resources/views/layouts/app.blade.php:25-66` — estado `ayudaAbierta`, botón `?` y popup; el `x-data` del `<nav>` solo sirve a la ayuda.
- `src/Granjas/App/CosecharPlantacion.php:39-91` — transacción, lock de nido, lock de fila, recálculo de stock e incremento atómico.
- `src/Granjas/App/PlantarBaya.php:39-88` — mismo orden de locks (nido → plantación).
- `src/Granjas/Domain/ReglasPlantacion.php:15-19,66-78` — 24 h, stock 3, +20% por planta, tope ×2.0.
- `src/Exploraciones/Domain/CalculadorBotinExploracion.php:94,163-183` — el factor multiplica el peso de cada baya antes de la selección ponderada; no toca bonguris.
- `src/Granjas/Infra/Plantaciones/ConsultorPlantacionesEloquent.php:22-38` — cuenta plantas por `(user_id, habitat_id)` y baya.
- `src/Exploraciones/App/FinalizarExploracionHandler.php:665-682` — inyecta `factoresPlantacion`.
- `database/migrations/2026_11_04_000003_create_nest_plantings_table.php:16-29` — unique `(nest_id, slot_index)` e índice de `berry_key`.
- `src/Shared/Domain/Items/ClavePocion.php:49-52` y `ClaveMaterial.php:35-38` — devuelven `unknown.webp`.
- `src/Shared/Domain/Items/ClaveMejora.php:64-67` y `ClaveReductor.php:58-61` — reutilizan la baya de origen.
- `src/Shared/Domain/Items/ClaveResistencia.php:52-55` — usa `/images/type/{slug}.webp`.
- `src/Shared/Domain/Items/CatalogoItems.php:240-266` — descripción del material hardcodea “Recipiente”.
- `resources/views/habitats/show.blade.php:2157-2188` — `accionGranja` maneja CSRF/errores y recarga la granja.
- `public/images/items` — 16 PNG, 112 KB; `cwebp` disponible; prueba de conversión: 72 KB con `-q 85 -m 6 -alpha_q 100`.
- `database/seeders/ProvinceSeeder.php:18-33` — Galicia, Euskadi, Barcelona, Extremadura, Nova Alacant, Mutxamel, Andalucia.
- `database/seeders/BonguriHabitatSeeder.php:23-31` — mapping provincia → color.

## Recomendaciones

1. **Cosecha global:** `CosecharTodo` en `Src\Granjas\App`, transacción por nido, locks nido → plantaciones, inventario agregado por baya, errores parciales. Ruta `POST /api/farm/harvest-all`.
2. **Menú:** sustituir el bloque de ayuda por un botón `<i class="fa-solid fa-seedling"></i>` con estados de carga/éxito/error y evento `inventario:actualizado`. Font Awesome local vía npm (decisión del plan) o SVG si no se autoriza.
3. **Bonus:** la fórmula actual cumple “más plantas = más probabilidad relativa de esa baya”; no cambiar el cálculo. Añadir una nota estática en la UI.
4. **Assets:** convertir a WebP con `cwebp -q 85 -m 6 -alpha_q 100 -metadata none`; estructura `public/images/items/{pocion,mejora,material}/`; mover PNG originales a `storage/app/imports/items-png/`; actualizar `ClavePocion`, `ClaveMejora` y `ClaveMaterial`.
5. **Recipiente:** `material:recipiente` usará el icono de Éter y el nombre visible **Éter**; descripción “Recipiente para fabricar objetos”. En la forja, mostrar los 7 bonguris con existencia y origen (`Galicia`, `Euskadi`, `Barcelona`, `Extremadura`, `Nova Alacant`, `Mutxamel`, `Andalucía`), disponibles primero.
6. **Reductores/resistencias:** conservan iconos de baya/tipo; no hay assets propios.

## Riesgos

- [alto] Doble cosecha por doble clic/reintento → locks + recálculo de stock + botón deshabilitado.
- [alto] Deadlock → locks ordenados nido → plantación, reintento 3.
- [medio] Rendimiento con muchos nidos → 3 queries de selección y una transacción corta por nido.
- [medio] Datos obsoletos en granja/forja tras cosechar → evento global y recarga silenciosa.
- [medio] Font Awesome por CDN → usar npm local; alternativa SVG si no se autoriza.
- [bajo] Caché de imágenes → rutas nuevas WebP, `loading="lazy"`.
- [bajo] Regresión de cosecha individual → reutilizar `ReglasPlantacion`, no tocar `CosecharPlantacion`.

## Preguntas abiertas

- ¿Cosecha global todo-o-nada o por nido con errores parciales? Recomendación: por nido.
- ¿`material:recipiente` se llama “Recipiente” o “Éter”? El usuario pidió Éter; se usará Éter.
- ¿Font Awesome npm local o SVG inline? El usuario pidió `fa-solid fa-seedling`; se usará npm local.
- ¿Se autoriza mover los PNG originales a `storage/app/imports/items-png/`? Recomendación: sí, para conservarlos fuera de `public`.
