# TASK-064 — Plan

- **ID:** TASK-064
- **Versión:** v1
- **Estado:** AWAITING_APPROVAL
- **Fecha:** 2026-11-19
- **Proyecto:** `/home/david/pokemon`

## 1. Objetivo

Añadir un modo QA/debug seguro que permita:

1. Una vista `/debug`, fuera del menú, solo en `local`/`testing`, con:
   - experiencia de la cuenta y botones `±1.000`, `±10.000`, `±100.000`,
     `±1.000.000`;
   - 18 filas de caramelos de tipo y 6 de estadística, con botones
     `±100`, `±255`, `±3072` para añadir o retirar del inventario.
2. En `/pokedex`, cuando el modo QA esté activo (`$unlockAll` y entorno
   permitido):
   - reclutar directamente el Pokémon abierto, reutilizando el flujo normal de
     reclutamiento (bonos de familia, IVs, EVs, exp 1500, Pokédex actualizada);
   - otorgar caramelos de su familia evolutiva en cantidades 100/255/3072.

## 2. Alcance

- Nuevo middleware de entorno y rutas QA.
- Nuevos controladores, servicios y DTOs de QA en `app/Support/Qa`.
- Nueva vista `/debug` y partial de filas.
- Bloque de acciones QA en el modal de `/pokedex`.
- Sin migraciones, sin nuevas tablas, sin dependencias nuevas.

## 3. Fuera de alcance

- No se modifica `app/Http/Controllers/PlayerController.php`.
- No se modifica `routes/player.php`.
- No se añade `/debug` al menú de navegación.
- No se crean ni ejecutan tests.
- No se añaden topes de nivel ni cambios en la curva de experiencia.
- No se muestra stock inicial de caramelos de familia en el modal (se muestra el
  total devuelto tras la acción).
- No se actualizan contadores globales de Pokédex en el modal; solo
  `selectedPokemon.atrapado`.
- No se documenta con ADR (no solicitado; el informe final resume las decisiones).

## 4. Arquitectura y contratos

Diseño completo en `architecture.md`. Resumen:

### Rutas (`routes/debug.php`, cargado fuera del grupo `auth`)

| Método | URI | Nombre | Controlador |
|---|---|---|---|
| GET | `/debug` | `qa.debug.index` | `DebugController@index` |
| POST | `/debug/experiencia` | `qa.debug.experiencia` | `DebugController@ajustarExperiencia` |
| POST | `/debug/inventario` | `qa.debug.inventario` | `DebugController@ajustarInventario` |
| POST | `/pokedex/qa/reclutar` | `qa.pokedex.reclutar` | `PokedexQaController@reclutar` |
| POST | `/pokedex/qa/caramelo-familia` | `qa.pokedex.caramelo-familia` | `PokedexQaController@carameloFamilia` |

Grupo: `['solo-local', 'auth']`. `solo-local` se registra en `bootstrap/app.php`
y devuelve 404 fuera de `local`/`testing`; cada método repite la guarda con
`ModoQa::asegurarEntornoPermitido()`.

### Mutaciones

- Experiencia: `AjustesQa::ajustarExperiencia(userId, delta)` con transacción,
  `lockForUpdate` del usuario y `max(0, anterior + delta)`.
- Inventario: `AjustesQa::ajustarInventario(userId, clave, delta)` con
  transacción, `firstOrCreate`/`lockForUpdate`, clamp a 0 y consumo máximo del
  stock disponible.
- Reclutamiento: `ReclutarDesdePokedex::para(userId, pokemonId)` reutiliza
  `ResolverBonosFamilia`, `GeneradorIvs::reclutamiento` y `CrearReclutado`.
- Caramelo de familia: el servidor resuelve `evolution_chain_id` desde
  `pokemon_id`; nunca acepta la cadena del cliente. Cadena nula/0 → 422.

### Contratos de UI

- `/debug`: formularios POST con `@csrf` y redirect + flash; cada fila de caramelo
  tiene un formulario con botones `±100`, `±255`, `±3072`.
- `/pokedex`: fetch POST con `X-CSRF-TOKEN`, `Accept: application/json`, feedback
  inline `role="status"`, sin recargar ni perder el modal.
- El bloque QA solo se renderiza si `qaAcciones` es true
  (`$unlockAll && entornoPermitido()`).

## 5. Archivos y responsables

### Backend — `backend`

| Archivo | Acción |
|---|---|
| `app/Http/Middleware/SoloEnEntornoDePruebas.php` | crear |
| `app/Support/Qa/ModoQa.php` | crear |
| `app/Support/Qa/AjustesQa.php` | crear |
| `app/Support/Qa/ResultadoAjusteQa.php` | crear |
| `app/Support/Qa/PanelQa.php` | crear |
| `app/Support/Qa/ContenidoPanelQa.php` | crear |
| `app/Support/Qa/FilaAjusteQa.php` | crear |
| `app/Support/Qa/ReclutarDesdePokedex.php` | crear |
| `app/Http/Controllers/DebugController.php` | crear |
| `app/Http/Controllers/PokedexQaController.php` | crear |
| `routes/debug.php` | crear |
| `bootstrap/app.php` | modificar (alias `solo-local`) |
| `routes/web.php` | modificar (`require routes/debug.php`) |

### Frontend — `frontend`

| Archivo | Acción |
|---|---|
| `resources/views/debug/index.blade.php` | crear |
| `resources/views/debug/partials/_seccion-caramelos.blade.php` | crear |
| `resources/views/pokedex/index.blade.php` | modificar: bloque QA tras "Movimientos por nivel" y estado/métodos al final de `pokedexApp()` |

### Validación — `security`, `reviewer`

| Responsable | Tarea |
|---|---|
| `security` | Auditar gating, CSRF, autorización, IDOR, claves de inventario, cantidades y producción |
| `reviewer` | Revisar diff contra plan, capas, duplicidades y criterios de aceptación |

## 6. Orden de ejecución y dependencias

1. **Backend, base:** middleware `SoloEnEntornoDePruebas`, `ModoQa`, alias en
   `bootstrap/app.php`, `routes/debug.php`, `require` en `routes/web.php`.
2. **Backend, servicios:** `ResultadoAjusteQa`, `FilaAjusteQa`,
   `ContenidoPanelQa`, `AjustesQa`, `PanelQa`, `ReclutarDesdePokedex`.
3. **Backend, controladores:** `DebugController`, `PokedexQaController`.
4. **Frontend:** `debug/index.blade.php` + partial; bloque QA en el modal de
   Pokédex. Depende de las rutas y contratos del backend.
5. **Verificación estática.**
6. **Auditoría de seguridad.**
7. **Revisión independiente.**
8. **Correcciones** si hay hallazgos.
9. **Informe final.**

Restricción de concurrencia: `resources/views/pokedex/index.blade.php` está
modificado por TASK-061 (en `REVIEWING`). Se trabaja sobre el working tree actual,
sin revertir ni sobrescribir; las inserciones se marcan `TASK-064` y se anclan por
marcadores, no por número de línea. No se modifica `PlayerController.php`.

## 7. Criterios de aceptación verificables

1. `/debug` responde 200 en local/testing y 404 fuera de esos entornos, también
   para usuarios anónimos; no aparece en el menú.
2. `/debug` muestra la experiencia actual, el nivel y 24 filas (18 tipos + 6
   stats) con sus cantidades actuales.
3. Los botones de experiencia aplican exactamente `±1.000`, `±10.000`,
   `±100.000`, `±1.000.000`; la experiencia nunca queda negativa.
4. Los botones de caramelos aplican exactamente `±100`, `±255`, `±3072`; el stock
   nunca queda negativo; retirar más de lo disponible consume solo el stock y
   avisa; no se crean filas con stock 0.
5. `item_key` y `delta` manipulados fuera de las listas permitidas se rechazan sin
   mutación.
6. Todos los POST exigen CSRF y usan `auth()->id()`; no aceptan `user_id` del
   cliente.
7. En `/pokedex` con modo QA activo, el modal muestra "Reclutar" y los botones de
   familia solo si hay `evolution_chain_id`; sin él, el servidor devuelve 422.
8. Reclutar crea un `Reclutado` con `exp.total=1500`, IVs/EVs según bonos y deja
   la especie como atrapada para ese usuario; se permiten duplicados.
9. El caramelo de familia incrementa `familia:{chain_id}` en 100/255/3072 y
   devuelve el total actualizado.
10. `app/Http/Controllers/PlayerController.php` no tiene diff; no se rompen los
    filtros de TASK-061.
11. `pint`, `composer analyse` y `composer arch` no introducen errores nuevos.

## 8. Verificación requerida

Sin tests. Se ejecutará:

```bash
php -l <archivos PHP nuevos/modificados>
php artisan route:list --path=debug -v
php artisan route:list --path=pokedex/qa -v
php artisan route:list --except-vendor | tail -1
vendor/bin/pint --dirty --format agent
composer analyse
composer arch
grep -n "debug" resources/views/layouts/app.blade.php   # sin resultados
git diff -- app/Http/Controllers/PlayerController.php   # vacío
git status --short                                      # solo archivos de TASK-064
```

Comprobación manual de criterios:
- Navegar a `/debug` en local con sesión y comprobar filas y botones.
- Ejecutar sumas/restas y verificar valores y mensajes.
- Abrir un Pokémon en `/pokedex?unlock=1`, reclutar y dar caramelos de familia.
- Verificar 404 de `/debug` y de los POST en un contexto no local/testing.
- Comprobar que `/debug` no aparece en el menú.

## 9. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Exposición en producción | Middleware 404 + guarda en controlador + gate de UI por entorno |
| `POKEDEX_UNLOCK_ALL=true` en producción | `qaAcciones` exige entorno permitido; POST 404 |
| Orden de middleware | `route:list -v` + guarda redundante |
| Conflicto con TASK-061 | No tocar `PlayerController`; inserciones marcadas y ancladas por contexto |
| Valores negativos | Clamp en servidor con `lockForUpdate`; sin migración |
| Doble clic | `qaProcesando` en UI + transacción/lock en servidor |
| Job síncrono al reclutar | Solo QA/local; se documenta el coste |
| Claves arbitrarias de inventario | `item_key` validado contra 24 claves; familia resuelta en servidor |
| Imagen de familia inexistente | Fallback `candy_pokemon/0.webp` |

## 10. Rollback

1. Antes de implementar, guardar parche de los 3 archivos modificados:
   `git diff -- bootstrap/app.php routes/web.php resources/views/pokedex/index.blade.php > /tmp/opencode/task064-modificados.patch`.
2. Revertir con `git apply -R` y eliminar explícitamente los 13 archivos nuevos.
3. No usar `git checkout --`, `reset --hard`, `clean` ni `stash`.
4. El rollback no afecta a TASK-061 ni TASK-063.

## 11. Aprobación

Este plan requiere aprobación explícita de la versión v1 antes de implementar.
La tarea permanece en `AWAITING_APPROVAL` y `approval.md` en `Estado: PENDIENTE`.
