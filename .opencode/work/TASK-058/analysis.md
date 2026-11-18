# TASK-058 — Análisis

- **Autor:** `analyst` (subagente independiente).
- **Fecha:** 2026-11-17.
- **Alcance:** solo lectura; sin cambios de código, sin tests, sin escrituras en BD.
- **Nota de coordinación:** se reproduce el resultado del analista. El cierre de la
  causa raíz del fallo 1 se completó con verificación adicional del orquestador y
  consta en `execution.md` § «Verificación de causa raíz».

---

## 1. Resumen del analista

### Fallo 1 — Reclutamiento

El analista descartó las hipótesis iniciales con evidencia:

| Hipótesis | Resultado |
|---|---|
| Bundle desactualizado | **Descartada.** `public/build/assets/app-FHM2LMbe.js` contiene el store actual (`reclutamientoNavbar`, TTL `15e3`, porcentajes `[25,50,75,100]`, rutas `recruit`/`discard`/`discard-all`). |
| CSRF ausente | **Descartada.** `meta[name="csrf-token"]` en `layouts/app.blade.php:6` y `layouts/auth.blade.php:6`; `fetchJson` envía `X-CSRF-TOKEN` en POST (`stores/comun.js:59-62`). |
| Rutas inexistentes o sin auth | **Descartada.** `route:list` muestra los 5 endpoints bajo `web` + `Authenticate`. |
| Shape distinto entre API y frontend | **Descartada.** `ReclutableResumen::toArray()` devuelve `{id, pokemon_id, nombre, icono, cantidad}` y el grid usa exactamente `item.id`, `item.nombre`, `item.icono`, `item.cantidad`. |
| Firmas de dominio incompatibles | **Descartada.** `GeneradorIvs::reclutamiento(int)` y `ResolverBonosFamilia::paraFamilia(int,int)` coinciden con las llamadas del controlador. |
| Error de servidor en reclutamiento | **No encontrado.** `laravel.log` no contiene errores reales de `ReclutamientoController`; solo restos de tests del 2 de noviembre. |

Hipótesis que el analista dejó abiertas (sin confirmar):

1. **Doble arranque de Alpine / `$persist`.** `browser.log` registra
   `Uncaught TypeError: Cannot redefine property: $persist` en
   `app-FHM2LMbe.js:23` desde `/login` (2026-11-18 08:03:13) y desde `/pokedex`
   con un bundle anterior. No hay entradas de `/reclutamiento`.
2. **Fallo silencioso en la página.** `reclutamiento/index.blade.php` no muestra
   el bloque de error/mensaje que sí tiene `modal-reclutamiento.blade.php:30-53`;
   un 419/422 en la página no se vería.
3. **`accionEnCurso` atascado.** `fetchJson` no tiene timeout ni `AbortController`;
   `accionEnCurso` solo se limpia en `finally`.

### Fallo 2 — Expediciones

Traza confirmada:

- `GET /exploraciones` sí procesa: `ExploracionActivaController::index()` llama a
  `ProcesarExploracionesPendientes::paraUsuario()` (`:56`).
- Los GET del panel **no** procesan: `ExpedicionesPanelController` lo documenta
  explícitamente en su docblock (`:20-27`) y `index()` solo llama a
  `ConstructorExpedicionesResumen::panel()`.
- `CalculadorEstadoExpedicion` devuelve `PENDIENTE_RESOLUCION` cuando
  `fin <= ahora` y `regreso === null`; `TERMINADA` exige `regreso` marcado.
- `PresentadorExploraciones:786` traduce `PENDIENTE_RESOLUCION` a
  «Pendiente de procesar».
- **Agravante:** para una expedición no indefinida y sin `regreso`,
  `puede_recoger` es `false` (exige `indefinido`) y `puede_archivar` es `false`
  (exige `TERMINADA`). La fila queda sin acciones: callejón sin salida.

Opciones evaluadas:

- **(a) Procesar al abrir el panel/modal.** Recomendada. Usar
  `paraUsuario($userId)` en `index()` y `una($exploracion)` en `detalle()`.
  El throttle de 30 s y el lock de 60 s por expedición evitan duplicados con el
  comando programado y con varias pestañas.
- **(b) Detectar terminada sin procesar.** Rechazada: cambiar
  `CalculadorEstadoExpedicion` para devolver `TERMINADA` sin `regreso` rompería
  `recoger` (necesita `PENDIENTE_RESOLUCION` para despachar el tick y las
  recompensas) y permitiría archivar/cerrar sin resolver.

---

## 2. Evidencia del analista

- `resources/js/bootstrap.js` registra los stores antes de `Livewire.start()`.
- `resources/js/app.js` importa `./bootstrap`.
- `resources/views/layouts/app.blade.php:9` carga `@vite([... 'resources/js/app.js'])`;
  `:175` solo emite `@livewireScriptConfig`.
- `resources/views/reclutamiento/index.blade.php:6` hidrata con
  `@js($reclutables ?? [])`; el grid se incluye en `:27`.
- `resources/views/layouts/partials/modal-reclutamiento.blade.php:64` incluye el
  mismo grid; el modal sí muestra `error`/`mensaje`.
- `resources/views/components/ui/modal.blade.php` crea un `x-data` anidado, pero
  `$store` es global: no es un bloqueo.
- `src/Exploraciones/App/ProcesarExploracionesPendientes.php`: `paraUsuario()`
  `:46-62`, `una()` `:91-104`, lock `:153-168`, throttle `:170-186`.
- `src/Exploraciones/Infra/Controllers/ExpedicionesPanelController.php` no inyecta
  `ProcesarExploracionesPendientes`.
- `database/migrations/2026_11_10_000001_add_archived_at_to_exploraciones_activas.php`
  existe; el error de `archived_at` en `laravel.log` es histórico (16-17 nov).

## 3. Comprobaciones no ejecutadas

- No se abrió un navegador: la reproducción visual queda pendiente.
- No se ejecutaron tests.
- No se escribió en la BD.
- No se leyó `.env` ni secretos.
