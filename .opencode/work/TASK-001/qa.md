# QA — TASK-001

Resultado del `qa` (sesión `ses_e6beca5b8ffe7gdn2wb8i3Yr4J`), modelo `opencode-go/deepseek-v4.1-flash`. Solo lectura; no modificó archivos.

## Veredicto

**PASS** — revisión estática. PHPUnit diferido según `AGENTS.md`; no se ejecutó PHPUnit, migraciones, seeders, builds ni servidores.

## Entorno

- PHP 8.4.18, Laravel 12.67.0.
- BD no usada para pruebas; el boot de Artisan resolvió la conexión sin error.
- Validación con `php -l`, `route:list`, `view:cache/clear`, `node --check` y `grep`.

## Criterios de aceptación

1. **Cumple** — `umbral()`: 0→0, 1→0, 2→15.625, 3→31.250, 4→62.500, 5→125.000, 6→500.000, 99→500.000 (`SendaProgreso.php:55-62`).
2. **Cumple** — `nivel()`: 15.624→1, 15.625→2, 31.249→2, 31.250→3, 62.499→3, 62.500→4, 124.999→4, 125.000→5, `PHP_INT_MAX`→5 (`SendaProgreso.php:71-80`).
3. **Cumple** — `resumen()`: 8 claves exactas, todas int; `exp` clampeada a 500.000; porcentajes 15.624→99, 93.750→50, 312.500→50, 500.000→100, 600.000→100 con exp 500.000 y faltante 0 (`SendaProgreso.php:101-120`).
4. **Cumple (estático)** — `POST /sendas/experiencia`: validación `cantidad in:1,10,100`; tipo inválido/sin stock → 422; `min(cantidad, stock)`; `exp += 100 × consumidos`; `level` recalculado; árbol fuera de la transacción; aislamiento por `user_id`; CSRF (`SendasController.php:131-194`).
5. **Cumple** — `desbloquear` sin regresión; el diff solo propaga la eliminación de `$nivelCuenta` (`SendasController.php:48-129`).
6. **Cumple** — `show.blade.php`: sin `aside`, `bloqueado_por_cuenta`, `progreso_vertical`, `nivel_por_exp`, `cap`, "Nivel máximo" ni `exp_siguiente === null`; `Nivel X / 5`; filtro superior; grid de 3 columnas; grupos colapsables; contadores; autoexpansión; x1/x10/x100; feedback; estados vacíos.
7. **Cumple** — `index.blade.php`: sin cap; siempre `exp / exp_siguiente`; conserva los totales calculados en el controlador.
8. **Cumple** — sin lógica de negocio en Blade/Alpine; el backend entrega nivel, exp, porcentaje, flags y coste.
9. **Cumple** — accesibilidad: `aria-expanded`, `aria-controls`, `role="progressbar"`, `role="status|alert"`, label del filtro.
10. **Cumple** — cambios locales preexistentes preservados.

## Fallos

Ninguno bloqueante, alto ni medio.

## Hallazgos LOW/NIT

- **[LOW] `SendasController.php:159`** — cast redundante `(int) $caramelo->cantidad`; `PlayerInventory` ya castea a integer.
- **[LOW] `show.blade.php:285-317 y 319-348`** — guardas separadas `savingExp`/`saving`; consumir y desbloquear casi a la vez puede dejar una foto obsoleta de `arbol` hasta recargar. Estado de servidor correcto.
- **[NIT] `show.blade.php:106-110`** — "Tope de Senda" aparece al entrar en nivel 5 (125.000 exp, 0 %) y puede leerse como "ya en el tope".
- **[NIT] `show.blade.php:69-75`** — `type="search"` sin `@search`; el botón nativo de limpiar de Safari puede no sincronizar `x-model`.
- **[NIT] `show.blade.php:182,188-191`** — doble estado vacío por categoría y global.
- **[NIT] `SendasController.php:174-178,189`** — dos fuentes de caramelos en la respuesta (`caramelos` y `arbol.caramelos`); el frontend usa la segunda.
- **[INFO] `architecture.md`** — el texto v1 dice quitar `/5` en index; el plan v2 y la revisión v2 dicen `Nivel X / 5`. La implementación sigue v2; solo hay inconsistencia documental en el texto antiguo.

## Comandos ejecutados

- `php -v`, `php artisan --version` → OK.
- `php -l` sobre los 4 PHP del alcance → sin errores.
- `php artisan route:list --path=sendas -v` → 4 rutas; `sendas.experiencia` bajo `web` + `Authenticate`.
- `php artisan view:cache` / `view:clear` → OK.
- `node --check` del bloque `sendasApp` → OK.
- `grep` de claves antiguas/umbrales en vistas/JS → sin coincidencias.
- `git status --short` de los 6 archivos → solo los cambios esperados.
- No se ejecutó PHPUnit (diferido).

## Cobertura observada

No existen tests de Sendas. Cobertura únicamente estática: sin render autenticado, sin peticiones HTTP reales y sin verificación de BD.

## Riesgos residuales

- **Reset de progresión (alto):** con U(2)=15.625, un jugador con 9.050 exp pasa de nivel 4 a 1; conserva desbloqueos comprados, pero pierde acceso a nodos no comprados de niveles 2–4. Falta decisión de backfill/compensación.
- **Economía (alto):** nivel 5 exige 125.000 exp = 1.250 caramelos; el caramelo compite con el desbloqueo (10–400).
- **Tests diferidos (alto):** sin `SendaProgresoTest` ni `SendasTest`; programar al cierre del módulo.
- **PHPStan baseline (medio):** baseline obsoleto; el análisis puede seguir en rojo por conteos ajenos.
- **Concurrencia UI (bajo):** guardas separadas (LOW).
- **Exp > 500.000 no visible:** se acumula en BD y se muestra clampeada; aceptado.
- **Caché `level`:** filas existentes pueden estar desincronizadas hasta la próxima escritura.
- **`required_level`/`SendaCostes` acotados a 5:** deuda latente.

## Recomendación

Aceptar la implementación v2: los criterios 1–10 se cumplen en revisión estática. Corregir LOW/NIT en una iteración menor, definir backfill/economía antes de producción y programar los tests de Sendas al cierre del módulo.

## Re-validación final (2026-10-31)

Veredicto: **PASS**.

- `php -l` del controlador y de los PHP del alcance: sin errores.
- `php artisan view:cache` / `view:clear`: OK; vista compilada lintada con `php -l`.
- `node --check` del bloque JS: OK.
- Grep de claves antiguas: sin coincidencias.
- Correcciones confirmadas: cast eliminado, `@search="alBuscar($event)"`, `alBuscar` con guarda de valor vacío, estado vacío por categoría con `!hayFiltro()`, estado global solo con filtro.
- Sin regresiones en `Nivel X / 5`, filtro, grupos, contadores, x1/x10/x100, ARIA ni contrato backend.
- No se ejecutó PHPUnit (diferido). Cobertura exclusivamente estática.

