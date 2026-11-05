# TASK-013 — Auditoría de seguridad

- **Fecha:** 2026-11-04
- **Auditor:** `security`
- **Veredicto:** `APPROVED`

## Resumen

No se identificaron hallazgos `CRITICAL` ni `HIGH` en los nuevos endpoints ni en la UI. El detalle de especie está autorizado por cadena asignada al hábitat y `evolution_chain_id`; el catálogo de bayas solo lee el inventario del usuario autenticado. No hay IDOR entre usuarios.

## Hallazgos

### LOW

- `GET /api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}` no tiene rate limiting y carga habilidades/movimientos; un usuario autenticado podría generar carga repetida. Recomendación: `throttle` y/o caché de `pokemon_evolution`.
- `GET /api/habitats/{habitat}/farm/berries` usa `AsegurarGranja`, que puede crear una fila `farms` en un GET. Efecto idempotente y acotado al propio usuario; recomendación opcional: separar lectura de creación.

### INFO

- Especies no vistas devuelven detalle completo (`visto=false`), por decisión D8. No revela datos de cuenta; los nombres/iconos ya estaban en `/families`.
- Los 404 distinguen “familia no encontrada” y “Pokémon no encontrado”; información ya pública en `/families`.
- `POKEDEX_UNLOCK_ALL` preexistente; garantizar `false` en producción.
- Advisories de dependencias preexistentes (Laravel 12.67.0, Livewire 4.4.2, Symfony 7.4.17, CommonMark 2.10.0); actualizar en tarea separada.

## Controles verificados

- Ambas rutas nuevas bajo `Route::middleware('auth')`; `whereNumber` en parámetros.
- Detalle: cadena asignada al hábitat (`pokemon_habitat` + `pokemon.evolution_chain_id`) y Pokémon de esa cadena; 404 sin filtrar.
- `user_id` desde `Guard::id()`, nunca del cliente; `Pokedex` por usuario; inventario por `user_id` explícito.
- SQL con bindings; sin interpolación nueva.
- CSRF intacto; no se desactiva middleware.
- XSS: `x-text`/`:attr`; sin `x-html`/`{!! !!}` nuevos con datos de usuario.
- `PresentadorDetallePokemon` conserva su contrato; `DatagridServiceProvider` intacto.
- Sin secretos leídos ni dependencias añadidas.

## Riesgos residuales

- Rate limiting ausente en detalle.
- Efecto de escritura en GET de catálogo.
- Advisories de dependencias preexistentes.
- Endurecer cookies de sesión en producción (`SESSION_SECURE_COOKIE=true`).

## Archivos inspeccionados

Los detallados en el informe del `security`: rutas, `DetallePokemonFamiliaQuery`, controladores, `InventarioGranja`, `ObtenerCatalogoBayas`, catálogo de items, presentadores, modelos, configuración de sesión y vistas.
