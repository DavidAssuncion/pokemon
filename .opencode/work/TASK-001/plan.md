# Plan v2 — TASK-001

Estado: `APROBADO v2`
Fecha: 2026-10-31
Sustituye al plan v1, aprobado antes del cambio de requisitos.

## Objetivo

Evolucionar la página de detalle de Sendas (`/sendas/{tipo}`) para:

1. Agrupar los movimientos por nivel requerido dentro de las tres columnas de categoría, con divisores colapsables.
2. Sustituir el aside de progreso vertical por un filtro de nombres en barra superior.
3. Fijar 5 niveles geométricos: entrada `0 / 15.625 / 31.250 / 62.500 / 125.000`, nivel 5 hasta `500.000` exp; la exp puede superar el tope y se acumula sin subir de nivel.
4. Permitir consumir caramelos de tipo para ganar 100 exp por caramelo, con botones x1/x10/x100.
5. Mantener coherente la portada `/sendas` (sin cap por cuenta; con `Nivel X / 5` y progreso al siguiente tramo).

## Alcance

- Backend: corregir `SendaProgreso` a la curva v2. `ArbolSendas`, `SendasController` y la ruta ya están implementados por el backend v1 y se conservan, salvo verificación.
- Frontend: refactor de `show.blade.php` y ajuste de `index.blade.php`.
- Validación estática y revisión independiente.
- Sin migraciones ni cambios de esquema.

## Fuera de alcance

- Rebalancear `required_level`, `SendaCostes` o el catálogo de movimientos.
- Backfill o compensación de exp para jugadores existentes (se acepta el reset de progresión salvo que el desarrollador pida lo contrario).
- Mostrar el excedente real por encima de 500.000 en la UI.
- Tests PHPUnit durante el desarrollo (diferidos según `AGENTS.md`).
- Documentación permanente nueva (no solicitada).

## Arquitectura y contratos

Se aplica `.opencode/work/TASK-001/architecture.md` y su revisión v2. Resumen vinculante:

- `SendaProgreso`:
  - `NIVEL_MAXIMO = 5`, `EXP_MAXIMA = 500_000`, `EXP_POR_CARAMELO = 100`.
  - `UMBRALES` privados: `1=>0, 2=>15_625, 3=>31_250, 4=>62_500, 5=>125_000`.
  - `umbral(int $nivel): int`: 1–5 desde la tabla; `n > 5` devuelve `500_000`.
  - `nivel(int $exp): int`: mayor nivel con umbral <= exp, acotado a 5.
  - `resumen(int $exp): array`: `exp` clampeada a `[0, 500_000]`, `exp_maxima`, `nivel`, `nivel_maximo`, `exp_siguiente` (500.000 en nivel 5), `exp_faltante`, `porcentaje` entero 0–100, `exp_por_caramelo`.
  - `porcentaje` con `intdiv((exp - inicio) * 100, rango)` para exactitud en los bordes.
- `ArbolSendas`: sin cambios; las claves nuevas viajan por `array_merge`. `ramas` sigue siendo listas planas; `nodo()` intacto.
- `SendasController::experiencia()`: sin cambios; ya valida `cantidad in:1,10,100`, bloquea inventario, consume `min(cantidad, stock)`, suma `100 * consumidos`, recalcula `level` y devuelve el árbol.
- `show.blade.php`: sin aside; filtro superior; 3 columnas con grupos colapsables; `Nivel X / 5`; tarjeta de progreso `exp / exp_siguiente`; nota de tope cuando `nivel >= nivel_maximo`; botones x1/x10/x100; feedback accesible.
- `index.blade.php`: `Nivel X / 5`; siempre `exp / exp_siguiente`; sin `bloqueado_por_cuenta`, `cap` ni "Nivel máximo".

## Archivos y responsables

| # | Archivo | Responsable | Acción |
|---|---|---|---|
| 1 | `app/Support/SendaProgreso.php` | backend | Reescribir a la curva v2 (5 niveles, tope 500.000, claves nuevas) |
| 2 | `resources/views/sendas/show.blade.php` | frontend | Refactor completo según v2 |
| 3 | `resources/views/sendas/index.blade.php` | frontend | Quitar textos de cap y adaptar progreso |
| — | `app/Support/ArbolSendas.php` | — | Sin cambios; verificar que `resumen()` propaga las claves nuevas |
| — | `app/Http/Controllers/SendasController.php` | — | Sin cambios; verificar endpoint y totales preexistentes |
| — | `routes/player.php` | — | Sin cambios; ruta `sendas.experiencia` ya presente |
| — | Migraciones | — | No aplica |

## Orden de ejecución y dependencias

1. Backend: corregir `SendaProgreso` (archivo 1). Verificar `ArbolSendas`/controlador/ruta.
2. Frontend: `show.blade.php` e `index.blade.php` (archivos 2–3), ya con el contrato v2.
3. Pint sobre PHP modificado (`vendor/bin/pint --dirty --format agent`).
4. Revisión independiente (`reviewer`).
5. Auditoría de seguridad (`security`).
6. QA de revisión estática (`qa`), sin PHPUnit.
7. Al cierre, preguntar si se desea testing específico.

No hay implementación paralela: el contrato backend debe quedar fijado antes de las vistas.

## Criterios de aceptación verificables

1. `SendaProgreso::umbral()`: `0→0`, `1→0`, `2→15.625`, `3→31.250`, `4→62.500`, `5→125.000`, `6→500.000`, `99→500.000`.
2. `SendaProgreso::nivel()`: `0..15.624→1`, `15.625..31.249→2`, `31.250..62.499→3`, `62.500..124.999→4`, `>=125.000→5` (incluido `PHP_INT_MAX`).
3. `resumen()`: claves exactas, `exp` clampeada a 500.000, `nivel_maximo=5`, `exp_maxima=500.000`, `exp_por_caramelo=100`.
4. Porcentajes: `15.624→99`, `93.750→50`, `312.500→50`, `500.000→100`, `600.000→100` con `exp` mostrada 500.000 y `faltante=0`.
5. `ArbolSendas` sigue funcionando con `ramas` planas y `nodo()` localiza nodos.
6. `GET /sendas` no muestra `cap`, "Cap de cuenta" ni "Nivel máximo"; muestra `Nivel X / 5` y `exp / exp_siguiente`.
7. `GET /sendas/{tipo}` no tiene aside; muestra filtro superior, 3 columnas, grupos colapsables, contadores filtrados, autoexpansión y estado vacío.
8. Botones x1/x10/x100 consumen como máximo el stock, actualizan caramelos, exp, nivel, porcentaje y árbol sin recarga, y muestran feedback.
9. `POST /sendas/experiencia` sigue validando, aislando por usuario, actualizando `level` y devolviendo el árbol.
10. `POST /sendas/desbloquear` no cambia de comportamiento.
11. Pint no deja cambios pendientes en los PHP modificados.
12. No se revierten los cambios locales preexistentes.

## Pruebas requeridas

- **Durante el desarrollo:** ninguna prueba PHPUnit, según `AGENTS.md` y `docs/workflow.md`.
- **Validación estática:** Pint sobre PHP modificado; `reviewer` puede ejecutar PHPStan/Deptrac.
- **QA:** revisión estática de edge cases, tipado, contratos y handoff; sin ejecutar tests.
- **Al cierre:** preguntar si se desea testing específico. Si se acepta:
  - Unit `SendaProgresoTest`: umbrales, bordes, porcentajes, claves, clamp.
  - Feature `SendasTest`: show 200/404, experiencia x1/x10/stock/validación/aislamiento, desbloquear sin regresión, exp >500.000 en BD con payload clampeado.
  - Verificación de `PersistirRecompensas` sobre `player_type_paths.level`.

## Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| **Reset de progresión**: con U(2)=15.625, un jugador con 9.050 exp pasa de nivel 4 a nivel 1; los nodos no comprados de niveles 2–4 dejan de estar disponibles | Alto | Aviso explícito en la aprobación; los desbloqueos comprados se conservan; se puede pedir backfill/compensación como cambio aparte |
| **Economía**: nivel 5 exige 125.000 exp = 1.250 caramelos (antes 150) | Alto | Aceptado por producto; `EXP_POR_CARAMELO` es una constante ajustable |
| **Ventana de contrato roto**: el backend v1 ya está en el árbol y las vistas leen claves eliminadas | Alto | Backend y vistas en el mismo cambio; grep de claves al final |
| **Caché `level` desincronizado** en filas existentes | Medio | Se resincroniza en la próxima escritura; no se lee en ningún sitio |
| **`exp` clampeada en `resumen()`** | Medio | Docblock: valor de presentación; el real vive en `player_type_paths.exp` |
| **`exp_siguiente` en nivel 5 significa tope, no siguiente nivel** | Bajo | Docblock y nota visual de tope |
| **Sin tests de Sendas** | Bajo | QA estático + ofrecer tests al cierre |

## Rollback

- Revertir `SendaProgreso` y las dos vistas. Sin migraciones ni transformación de datos.
- El endpoint `sendas.experiencia` puede deshabilitarse eliminando su ruta; no hay datos nuevos que limpiar.

## Aprobación

Este plan requiere aprobación explícita de la versión `v2` antes de implementar. La ausencia de respuesta no es aprobación.
