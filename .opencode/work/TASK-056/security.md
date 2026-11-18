# TASK-056 — Seguridad

- **Estado final:** APPROVED (revalidación 2026-11-18)
- **Estado 1ª auditoría:** CHANGES_REQUESTED
- **Auditor:** `security` (subagente independiente)
- **Fecha:** 2026-11-18
- **Alcance:** F1 — listado y fabricación de piezas de Forja, inventario, nidos y contrato API.
- **Excluido:** WIP ajeno (`docs/context.md`, `src/Battle/**` de TASK-055, `equipos/**`, `css/**`, tests).

## Veredicto

CHANGES_REQUESTED — sin CRITICAL ni HIGH. 1 MEDIUM y 3 LOW; el resto INFO.

## Hallazgos

### MEDIUM

1. `src/Forja/App/FabricarPieza.php:51` — la unicidad se comprueba con `saldo()` sin bloquear el
   par `(user_id, item_key)` del resultado; `bloquearNido():114-120` solo bloquea el nido concreto.
   Dos POST concurrentes de la misma pieza por dos nidos de la misma familia (`…:h12` y `…:h30`)
   usan locks distintos, ambos pasan el chequeo y dejan `cantidad=2` (materiales pagados dos veces).
   Remediación: bloquear la fila/clave resultado dentro de la transacción y revalidar tras el lock.

### LOW

2. `src/Shared/Domain/Items/ClavePieza.php:20` — el ancla `$` acepta `"arma:94\n"`. Hoy no hay
   ruta de escritura que lo explote, pero F2 persistiría la cadena cruda. Remediación: `\z` o `/D`.
3. `routes/forja.php:10` — sin `throttle` en el POST de fabricación (ni en el resto del proyecto).
   Un usuario autenticado puede martillear el endpoint y forzar el read model completo en cada
   petición. Remediación: `throttle` por usuario (fuera del alcance de F1; registrar como pendiente).
4. `src/Forja/Domain/Exceptions/RecetaNoExiste.php:16` — el mensaje refleja la entrada del cliente
   (`Receta desconocida: <input>`). No es XSS (JSON, render con `x-text`), pero es eco innecesario.
   Remediación: mensaje genérico sin la clave.

### INFO

5. `src/Shared/Domain/Items/ClavePieza.php:31-38` — valida estructura, no existencia de la pieza;
   hoy `arma:*` solo lo escribe `FabricarPieza` (4 call sites de `incrementar`, ninguno con clave de
   usuario). F2 debe mantener la comprobación de propiedad por consumo de inventario.
6. `src/Forja/Domain/Piezas/ReglasPiezas.php:31` — `MOTIVO_PIEZA_ANTERIOR_EQUIPADA` declarado y sin
   usar; en F2, fabricar la etapa N+1 con la anterior equipada dará `StockInsuficiente` en vez del
   motivo previsto (fallo cerrado, no explotable). Añadir el chequeo sobre `recruit_gear` en F2.
7. `src/Forja/Domain/Piezas/ReglasPiezas.php:107` — `textoMotivo()` devuelve el motivo crudo por
   defecto; hoy solo recibe constantes internas. No mapear nunca entrada de request a `motivo`.
8. `composer audit` — advisories preexistentes (`laravel/framework`, `symfony/*`,
   `livewire/livewire` con `#[Authorize]` no usado, `league/commonmark`), sin dependencias nuevas en
   F1. Plan de actualización fuera de TASK-056.

## Comprobaciones

| # | Punto | Resultado |
|---|---|---|
| 1 | Autenticación/autorización | OK — 3 rutas con `web`+`Authenticate`, `userId` solo desde `Guard`, todo filtra `farms.user_id`, 404 uniforme |
| 2 | Inventario | OK — carve-out de una condición (`InventarioJugadorEloquent.php:184`), prefijos de caramelo intactos, 422 mapeados; LOW en ancla del regex |
| 3 | Fabricación | HALLAZGO — gate bajo `lockForUpdate`, `cantidad=1` y consumo atómico correctos; unicidad no serializada entre nidos (MEDIUM) |
| 4 | SQL/consultas | OK — bindings, sin concatenación; `species_id`/`evolution_chain_id` nunca vienen del cliente |
| 5 | XSS/CSRF | OK — solo `x-text`/`x-bind`, sin `x-html` ni `{!! !!}`, POST con `X-CSRF-TOKEN` |
| 6 | Secretos/dependencias | OK — sin secretos ni dependencias nuevas |
| 7 | DoS/abuso | HALLAZGO — volumen acotado, pero sin rate limiting |
| 8 | Integridad | OK — `unique(user_id,item_key)`, `insertOrIgnore`+increment, fallback de bonguri, cadenas sin aristas → gate conservador |
| 9 | Regresión | OK — el carve-out no relaja claves no-pieza ni afecta a otras rutas; sin cambios en Battle, equipos, CSS ni tests |

## Riesgos residuales

- Duplicación de piezas únicas por concurrencia entre nidos de la misma familia hasta corregir el
  lock (impacto en F2 y balance del set).
- Whitelist estructural (no de existencia): si en el futuro se acepta una clave por body, se podrían
  introducir claves `arma:*` con formato válido.
- Cadenas evolutivas con aristas incompletas regalan la pieza final a etapa 1/factor 0.5 con gate 3.
- Sin rate limiting: coste del read model por petición y spam de fabricación autenticado.
- Advisories de terceros sin ventana de parcheo planificada en este cambio.

## Correcciones delegadas

| ID | Hallazgo | Responsable | Estado |
|---|---|---|---|
| C1 | Lock de unicidad (MEDIUM) | `backend` | PENDIENTE |
| C5 | Anclaje `ClavePieza` (LOW) | `backend` | PENDIENTE |
| C6 | Mensaje genérico `RecetaNoExiste` (LOW) | `backend` | PENDIENTE |
| C8 | `throttle` en fabricación (LOW) | — | ACEPTADO COMO PENDIENTE (fuera de alcance F1) |

---

# Revalidación F2 (2026-11-18)

- **Veredicto:** APPROVED. Sin CRITICAL/HIGH.
- C9/C10/C11/C12/C14: RESUELTOS (locks ascendentes con reintentos, D3 con `PiezasEquipadas`,
  catch acotado a unicidad, `forceCreate` sin mass assignment, equipadas fuera del stock).
- F2.3a/b: sin vectores nuevos; `species_id` no forzable por request, contrato enriquecido solo
  con catálogo global, `piezas`/CP filtrados por `user_id`, `effectKeys` desde BD.
- LOW aceptado: `23000` en SQLite puede mapear una FK a 422 de "ya equipada" (fail-closed);
  ausencia de `throttle`; claves corruptas en `recruit_gear` solo por escritura directa.

# Revalidación F1 (2026-11-18)

- **Veredicto final:** APPROVED — sin CRITICAL/HIGH y sin vector nuevo explotable.

| ID | Resultado | Evidencia |
|---|---|---|
| C1 | RESUELTO | `FabricarPieza.php:54` + `InventarioJugadorEloquent.php:187-200`; el segundo POST espera el lock, ve `cantidad=1` y recibe 422; `insertOrIgnore` no sobrescribe fila existente; rollback sin residuos |
| C5 | RESUELTO | `ClavePieza.php:20` `\z`: acepta `arma:94`, `armadura:150`, `amuleto:1`; rechaza `arma:94\n`, `arma:94 `, `arma:094`, `arma:0`, `Arma:94`, `arma:+94`, `arma:94;DROP` |
| C6 | RESUELTO | `RecetaNoExiste.php:14-17` con mensaje fijo; 3 call sites; render con `x-text` |
| Regresión | OK | `NidosConstruidosEloquent.php:58-68` filtra `farms.user_id`; nido ajeno → mismo 422 que inexistente; carve-out de inventario sin relajar claves no-pieza |

## Hallazgos nuevos

- LOW — `InventarioJugador.php:68`: puerto público con escritura y whitelist estructural; si F2 le
  pasa una clave derivada de request permitiría filas `cantidad=0` de especies inexistentes (acotado
  por el unique, hasta ~453/usuario). Hoy no explotable: único caller `FabricarPieza.php:54`.
  Remediación: validar existencia o restringir el puerto a Forja en F2.
- INFO — amplificación de escritura (+1 INSERT por intento) bajo la ausencia de `throttle` (C8).
- INFO — `ItemDesconocido::paraClave` sigue ecoando la clave; en Forja solo recibe claves internas.

## Riesgo residual aceptado

- Sin `throttle` (C8); advisories de terceros preexistentes; duplicado por concurrencia cerrado solo
  para piezas (los ítems no únicos no requieren unicidad).

