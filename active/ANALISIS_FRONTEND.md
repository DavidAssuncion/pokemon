# ANÁLISIS FRONTEND — Favoritos, hábitats favoritos, exploración individual, pérdidas en resultados

## Fecha
2026-09-02

## Tarea
Implementar frontend Blade/Alpine.js/Tailwind para el sistema de favoritos:
1. Vista "Favoritos" (reescribir `/equipos`)
2. Modal de gestión de favoritos
3. Favoritos en hábitats (corazón en tarjetas)
4. Modal de envío a exploración individual
5. Pérdidas en rojo en resultados
6. Nav: "Equipos" → "Favoritos"

## Archivos a modificar

### 1. `resources/views/equipos/index.blade.php` — REESCRITURA COMPLETA
- Cambiar título a "Pokémon Favoritos"
- Eliminar toda lógica de equipos/teams/slots/roles
- Panel izquierdo (1/3): lista de favoritos con hover "Enviar a explorar"
- Panel derecho (2/3): grid de no-favoritos con toggle favorito + búsqueda/filtro
- Mantener modal de detalle (reutilizado)
- Eliminar modal de eliminar equipo
- Añadir modal de gestión de favoritos (nuevo)
- Alpine -> `favoritosApp()`

### 2. `resources/views/habitats/index.blade.php` — MODIFICACIÓN
- Añadir `x-data="habitatsApp()"` con estado `favoritosIds`
- Añadir icono corazón/estrella en cada tarjeta de hábitat
- Llamar a `POST /api/habitats/{id}/toggle-favorito` con CSRF
- Filtrar favoritos primero (client-side)

### 3. `resources/views/habitats/show.blade.php` — MODIFICACIÓN
- Adaptar modal de exploración para individual (reemplazar equipo por favorito)
- Añadir selección de favorito + botón "Enviar a explorar"
- Preview adaptado para individual (capacidades si llegan)
- Eliminar envío de bayas (sin campo)
- POST a `/api/exploraciones/store-individual`

### 4. `resources/views/exploraciones/index.blade.php` — MODIFICACIÓN
- Añadir bloque de pérdidas (rojo) en resultados terminados
- Contrato: `objetos_perdidos: [{tipo, id/label, cantidad_perdida}]` en resultado

### 5. `resources/views/exploraciones/_evento.blade.php` — MODIFICACIÓN MÍNIMA
- Añadir tipos `combate`, `descanso` si el backend los añade
- Mostrar `hp_final`, `combate_log` de forma legible

### 6. `resources/views/layouts/app.blade.php` — MODIFICACIÓN MÍNIMA
- Cambiar "Equipos" por "Favoritos" en el nav

### 7. `tests/Feature/EquiposViewTest.php` — ACTUALIZAR
- Cambiar asserts de `equiposApp` a `favoritosApp`
- Añadir asserts para favoritos toggle, gestión modal, exploración individual
- Mantener asserts de detalle (evolución, release)

### 8. `tests/Feature/HabitatsViewTest.php` — ACTUALIZAR (opcional)
- Si hay asserts que se rompan por cambios en el show

## Contratos backend asumidos

### Ya existentes:
- `POST /api/reclutados/{reclutado}/toggle-favorito` → `{favorito: bool}` (ReclutadoController::toggleFavorito)
- `GET /api/reclutados/favoritos` → list serializados (ReclutadoController::favoritos)
- `POST /api/habitats/{habitat}/toggle-favorito` → `{favorito: bool, count: int}` / 422 con message (HabitatsController::toggleFavorito)
- `POST /api/exploraciones/store-individual` → 201 `{ok: true, id}` / 422 `{message}` (ExploracionActivaController::storeIndividual)
- `GET /exploraciones/preview` → `{capacidades, nivel_jugador, nivel_pokemon, min_lvl, peligro, riesgo}` (nuevo contrato individual)

### Datos que llegan del controlador:
- `PlayerController::equipos()`: `$teams`, `$reclutados` (ya incluyen `favorito` de la BD), `$teamIds`, `$equiposEnExploracion`
- `HabitatsController::index()`: `$provincias`
- `HabitatsController::show()`: `$habitat`, `$teams`, `$exploracionesActivas`, `$equiposEnExploracion`, `$sightedPokemonIds`
- `ExploracionActivaController::index()`: `$activas`, `$terminadas`

### Tolerancia:
- `favorito` puede faltar en reclutados (fallback false)
- `$favoritosIds` (habitats) inicializado desde controlador, tolera vacío
- `objetos_perdidos` puede faltar en resultado (no mostrar nada)
- Preview individual puede fallar (mostrar error "Función en preparación")
- `capacidades` puede venir o no

## Estados UI cubiertos

### Vista Favoritos:
- Loading: renderizado server-side, sin spinner
- Empty favoritos: mensaje + botón "Gestionar favoritos"
- Empty no-favoritos: mensaje vacío
- Error toggle favorito: console.error, no romper página
- Error 422 toggle (limite hábitats): alert con mensaje
- Favorito en exploración: bloqueado para enviar a explorar

### Modal gestión favoritos:
- Loading: n/a (datos ya cargados)
- Empty: mensaje si no hay reclutados
- Toggle: mutación local sin recarga
- Error: console.error + no romper

### Hábitats favoritos:
- Sin favoritos: orden normal
- Con favoritos: favoritos primero
- Error 422 toggle: alert máximo 6
- Error genérico: console.error

### Exploración individual:
- Sin favoritos: mensaje "No tienes favoritos"
- Preview falla: mostrar error
- Envío exitoso: location.reload() o redirect
- Error 422 envío: alert mensaje
- Favorito en exploración: bloqueado

### Pérdidas en resultados:
- Con pérdidas: panel rojo con detalle
- Sin pérdidas: no mostrar nada
- Sin objetos_perdidos: no mostrar nada

## Riesgos accesibilidad/UX
- Iconos WebP con onerror hide (cubierto)
- Modal con @keydown.escape y backdrop click (cubierto por patrón existente)
- Botones con aria-label
- Toggle favorito con feedback visual inmediato

## Tests
- EquiposViewTest: actualizar para nuevos asserts
- HabitatsViewTest: mantener asserts existentes, añadir favoritos
- Sin Dusk tests existentes para crear