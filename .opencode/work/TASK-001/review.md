# Revisión — TASK-001

Resultado del `reviewer` (sesión `ses_e6bf11b14ffekApzcl4JPLwKGE`), modelo `opencode-go/deepseek-v4.1-flash`. Solo lectura; no modificó archivos.

## Veredicto

**APPROVED**

La implementación del plan v2 cumple el contrato en los seis archivos del alcance. La curva, `umbral()`, `nivel()` y `resumen()` coinciden con la tabla y los bordes aprobados. El endpoint valida, serializa por lock de inventario, consume `min(cantidad, stock)`, acumula `100 × consumidos`, recalcula `level` y devuelve el árbol fuera de la transacción. Las vistas no usan `aside`, `cap`, `bloqueado_por_cuenta`, `progreso_vertical` ni `nivel_por_exp`; filtro, grupos colapsables, contadores y botones x1/x10/x100 son funcionales por lectura.

## Hallazgos

- **[LOW] `app/Http/Controllers/SendasController.php:159` — cast redundante.**
  `min($cantidad, (int) $caramelo->cantidad)`; `PlayerInventory` ya castea `cantidad` a integer. PHPStan lo marca como `cast.useless`. Recomendación: quitar `(int)`.

- **[LOW] `resources/views/sendas/show.blade.php:285-348` — guardas de concurrencia separadas (`savingExp` vs `saving`).**
  `consumir()` solo bloquea con `savingExp` y `desbloquear()` solo con `saving`; ambos reemplazan `this.arbol`. Si se ejecutan casi a la vez, la última respuesta puede dejar una foto anterior (nodo recién desbloqueado mostrado como bloqueado o caramelos desfasados) hasta recargar. El estado en servidor es correcto. Recomendación: guarda única (`saving || savingExp`) o refrescar el árbol al terminar cualquiera.

- **[NIT] `resources/views/sendas/show.blade.php:182,188-191` — doble estado vacío.**
  Mensaje por categoría y mensaje global; con una Senda vacía se muestran cuatro mensajes. Recomendación: global solo con filtro activo.

- **[NIT] `resources/views/sendas/show.blade.php:106-110` — “Tope de Senda” visible al entrar al nivel 5.**
  La condición `nivel >= nivel_maximo` se cumple con 125.000 exp (barra al 0 %) y el texto puede leerse como “ya estás en el tope”. Recomendación: condicionar el texto de tope alcanzado a `exp >= exp_maxima` o reformular como “Tramo final hasta 500.000 exp”.

- **[NIT] `resources/views/sendas/show.blade.php:67-75` — `type="search"` sin `@search`.**
  En Safari el botón nativo de limpiar dispara `search`, no `input`. Recomendación: añadir `@search="limpiarFiltro()"` o usar `type="text"`.

- **[NIT] `app/Http/Controllers/SendasController.php:174-178,189` — dos fuentes para los caramelos en la misma respuesta.**
  `resultado['caramelos']` y `arbol.caramelos`; el frontend solo usa el segundo. Campo redundante, posible discrepancia bajo concurrencia. El contrato lo incluye; documentar o eliminar en revisión futura.

No hay hallazgos BLOCKER, HIGH ni MEDIUM atribuibles a los seis archivos.

## Fortalezas

- Curva exacta y determinista: `UMBRALES` privados 0/15.625/31.250/62.500/125.000, `umbral(>5)=500.000`, `nivel()` acotado, porcentaje con `intdiv` y clamp 0–100.
- `resumen()` expone las 8 claves acordadas, todas int, con `exp` clampeada y constantes `EXP_MAXIMA`/`NIVEL_MAXIMO`.
- Transacción correcta con lock de inventario, `min(cantidad, stock)`, `increment` atómico, `refresh` + recálculo de `level`, árbol tras el commit. `decrement` actualiza el atributo en memoria, por lo que `caramelos` es correcto.
- Aislamiento por usuario explícito; sin IDOR; CSRF del grupo `web` + token en `fetch`; sin mass assignment.
- Vistas sin `x-html`, `@js` escapado, `x-cloak` con CSS en el layout, `:key` estables, ARIA correcta, autoexpansión y contadores filtrados.
- Contrato limpio: sin claves antiguas; `PersistirRecompensas` sigue usando `nivel(int)`.
- `index.blade.php` conserva el cambio local de totales.

## Pruebas y análisis ejecutados

- `vendor/bin/pint --test` sobre los cuatro PHP del alcance → pass.
- `php -r` con vectores de aceptación de `umbral`, `nivel` y `resumen` → OK.
- Compilación Blade de `show` e `index`, `php -l` del PHP compilado y render real de `sendas.show` → OK.
- `node --check` del bloque `sendasApp` → OK.
- `php artisan route:list --path=sendas` → rutas correctas bajo `auth`.
- `vendor/bin/phpstan analyse` → 9 errores, todos por conteos obsoletos del `phpstan-baseline.php` (archivo generado, fuera del alcance y ya modificado localmente). No hay CI; `pre-commit` solo ejecuta Pint.
- Greps de regresión: sin `bloqueado_por_cuenta`, `progreso_vertical`, `nivel_por_exp`, `Nivel máximo`, `Cap de cuenta`, `aside` en las vistas de Sendas.
- No se ejecutó PHPUnit (diferido).

## Riesgos residuales

- Gate de PHPStan en rojo por baseline obsoleto; regenerarlo ahora absorbería conteos de 78 archivos locales ajenos.
- Reset de progresión y economía de caramelos aceptados en el plan (U(2)=15.625; nivel 5 = 1.250 caramelos).
- La exp real por encima de 500.000 no se muestra; solo se indica que sigue acumulándose.
- Inconsistencia visual transitoria si se consume y desbloquea en paralelo (LOW).
- Sin pruebas automatizadas de Sendas (diferido).
- `player_type_paths.level` de filas existentes puede estar desincronizado; se corrige en la próxima escritura.
- `required_level` y `SendaCostes` siguen acotados a 5 (deuda aceptada).
- `ArbolSendas::para()` se calcula varias veces por respuesta en `desbloquear` (preexistente).

## Re-revisión final (2026-10-31)

Veredicto: **APPROVED**.

Se verificaron las correcciones posteriores:

- `SendasController.php:159` y `:175` sin casts redundantes; el tipo JSON sigue siendo numérico por el cast del modelo y `decrement`.
- `show.blade.php`: guardas `saving || savingExp` en `consumir`/`desbloquear`, `:disabled` coherente en botones de caramelos y desbloqueo.
- Texto “Tramo final: hasta 500.000 exp…”, sin ambigüedad de “Tope”.
- Estado vacío global solo con filtro (`totalVisible() === 0 && hayFiltro()`) y estado por categoría con `!hayFiltro()`.
- `@search="alBuscar($event)"` con guarda `event.target.value.trim() === ''`, por lo que Enter conserva la búsqueda y el botón nativo de limpiar sigue funcionando.
- Sin claves antiguas, sin regresiones de ARIA, `x-cloak`, filtro, grupos, contadores ni x1/x10/x100.

NITs residuales (no bloqueantes):

- `toggleGrupo` sigue activo durante el filtro, aunque `estaAbierto()` fuerza la apertura; el clic no produce cambio visible.
- La respuesta de `experiencia()` mantiene dos fuentes de caramelos (`caramelos` y `arbol.caramelos`); el frontend usa la segunda.
- `changes-final.diff` termina en el último hunk modificado de `desbloquear`; el resto del método no cambia, por lo que el artefacto es suficiente para el cambio, aunque no es un diff completo del archivo.
- PHPStan sigue en rojo por el baseline obsoleto, fuera del alcance.

