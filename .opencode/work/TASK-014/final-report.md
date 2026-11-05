# TASK-014 — Informe final

- **ID:** TASK-014
- **Estado:** `COMPLETED` (sin revisión ni auditoría de seguridad por decisión del desarrollador)
- **Fecha:** 2026-11-04
- **Proyecto:** `/home/david/pokemon`
- **Rama:** `task-011`
- **Cambios:** working tree, **sin commit y sin push**.

---

## 1. Resultado

- **Cosecha global desde el menú:** el botón `?` se sustituyó por `<i class="fa-solid fa-seedling"></i>`. Al pulsarlo, `POST /api/farm/harvest-all` cosecha todas las plantaciones listas del usuario en todos sus hábitats/nidos.
- **Toast de cosecha:** azul con “Cosechando…” y spinner; verde con “Cosecha completada” y el desglose de todas las bayas, incluyendo **icono + nombre × cantidad**; rojo si hay error. Es `fixed`, `z-[70]`, no genera scrollbar.
- **Bonus de plantaciones:** se mantiene la fórmula existente (`min(2.0, 1 + 0.20 * plantas)`), que aumenta el peso relativo de las bayas plantadas en exploración. Se añadió una nota en la UI.
- **Iconos de items:** 16 PNG convertidos a WebP con nombres limpios; originales movidos a `storage/app/imports/items-png/`.
- **Recipiente:** conserva el nombre “Recipiente” y usa el icono de Éter (`material/recipiente.webp`). En la forja se listan los 7 bonguris con existencias y origen (Galicia, Euskadi, Barcelona, Extremadura, Nova Alacant, Mutxamel, Andalucía), disponibles primero.
- **Forja:** las cuatro categorías (Medicinas, Mejoras, Reductores, Resistencias) se muestran en 4 columnas en `xl` (2 en `md`, 1 en móvil), una por sección. Las secciones y las recetas empiezan **colapsadas** por defecto; las recetas se colocan a 2 columnas dentro de cada sección y se expanden a ancho completo al pulsarlas para ver los materiales. Si falta algún material, la tarjeta se ve gris (`opacity-60 grayscale`) y muestra “Faltan materiales”.
- **Toast:** formato compacto en 2 columnas, con icono + nombre + cantidad.
- **Tabs de granja:** no se deshicieron los ajustes del desarrollador. Solo se añadieron el listener de refresco, las opciones de `loadGranja` y la nota de bonus.

## 2. Archivos principales

### Backend

- `src/Granjas/App/CosecharTodo.php` (nuevo)
- `src/Granjas/Domain/DataTransferObjects/{ResultadoCosechaGlobal,BayaCosechada,ErrorCosecha,CosechaNido}.php` (nuevos)
- `src/Granjas/Domain/Collections/{BayasCosechadasCollection,ErroresCosechaCollection}.php` (nuevas)
- `src/Granjas/Infra/Controllers/GranjaController.php` (`cosecharTodo`)
- `routes/granjas.php` (`POST /api/farm/harvest-all`)
- `src/Shared/Domain/Items/ClaveBonguri.php` (`origen()`)
- `src/Forja/App/DataTransferObjects/IngredienteDetalle.php` (`origen`)
- `src/Forja/App/ListarRecetas.php` (origen para bonguris)
- `src/Shared/Domain/Items/{ClavePocion,ClaveMejora,ClaveMaterial,CatalogoItems}.php` (rutas WebP, Recipiente)

### Frontend

- `resources/views/layouts/app.blade.php` (botón seedling, toast, estados, evento)
- `resources/views/habitats/show.blade.php` (listener, `loadGranja` opciones, nota de bonus; tabs intactos)
- `resources/views/forja/index.blade.php` (listener, lista de bonguris del Recipiente)
- `resources/css/app.css` (Font Awesome)
- `package.json` / `package-lock.json` (`@fortawesome/fontawesome-free`)

### Assets

- `public/images/items/pocion/*.webp` (8)
- `public/images/items/mejora/*.webp` (7)
- `public/images/items/material/recipiente.webp`
- `storage/app/imports/items-png/*.png` (16 originales)

### Documentación

- `docs/adr/0003-cosecha-global-e-iconos-items.md`
- `docs/architecture.md`
- `src/Granjas/context.md` (parcial)

## 3. Verificación ejecutada

| Comprobación | Resultado |
|---|---|
| `php -l` de PHP nuevos/modificados | Sin errores. |
| `phpstan analyse` | 34 errores preexistentes; ninguno nuevo. |
| `deptrac analyse` | 113 violaciones preexistentes; ninguna nueva. |
| `pint --test --dirty` | `pass`. |
| `view:cache` / `view:clear` | OK. |
| `npm run build` | OK; fuentes Font Awesome emitidas. |
| `route:list --path=api/farm` | `POST /api/farm/harvest-all`. |
| Assets | 0 PNG en `public/images/items`; 16 PNG en `storage/app/imports/items-png`; 16 WebP. |
| `x-data` del menú | Validado con `node --check`. |
| `CosecharTodo` | Respuesta vacía correcta; prueba con plantación forzada y rollback: 3 bayas, 1 plantación, 1 nido. |
| Recipiente | 7 variantes con `origen`; nombre “Recipiente” e icono `material/recipiente.webp`. |
| Tabs de granja | Sin cambios en el bloque de tabs; solo listener/opciones/nota. |
| `tests/` | Sin cambios. No se ejecutaron tests. |

## 4. Revisión y seguridad

- **Revisión independiente:** omitida por indicación explícita del desarrollador («Sin reviewer ni security»).
- **Auditoría de seguridad:** omitida por la misma indicación.
- No se debe interpretar este cierre como una validación independiente. La verificación fue estática y manual por el orquestador.

## 5. Riesgos residuales

- **Sin revisión ni seguridad independientes:** no hay segunda opinión sobre concurrencia, autorización o regresiones.
- **Sin prueba en navegador:** no se validó visualmente el toast, los iconos WebP, el tema oscuro ni el modal con muchos datos.
- **Concurrencia:** la idempotencia depende de locks + `harvest_count`; no se ejecutó una prueba de doble petición real.
- **Rate limiting:** `POST /api/farm/harvest-all` no tiene throttle.
- **`config/ayuda.php`:** queda huérfano tras eliminar el popup.
- **PNG originales:** están en `storage/app/imports/items-png/`, que no se versiona; si se necesita persistencia, moverlos a un artefacto versionado.
- **Font Awesome:** nueva dependencia local; revisar advisories npm preexistentes.
- **`x-data` global en `<body>`:** el estado de cosecha vive en el ámbito global; evitar colisiones futuras.
- **Documentación:** `src/Granjas/context.md` quedó parcial (dependencias/deuda/guía de búsqueda pendientes).

## 6. Próximos pasos

1. Probar en navegador: botón seedling, toast azul/verde, cosecha con granja abierta, forja Recipiente con 0/1/varios bonguris, iconos en inventario/forja/combate.
2. Probar doble clic y dos pestañas para confirmar que no se duplica la cosecha.
3. Completar `src/Granjas/context.md` (dependencias, deuda y guía de búsqueda).
4. Si se desea, solicitar una revisión/auditoría posterior; no se hicieron en esta tarea por indicación del desarrollador.
5. Commit separado de TASK-011/012/013; no se hizo push.
