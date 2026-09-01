# ANÁLISIS_BACKEND — Detalle de reclutados en /equipos + endpoint de liberar

## Objetivo

Preparar el backend para que el frontend muestre el detalle de un pokémon reclutado en
`/equipos` y realice las acciones de "evolucionar" y "liberar":

1. Ampliar el payload de `PlayerController::equipos()` para que cada `reclutado` serializado
   incluya `nivel`, `exp_total`, `base_experience`, `es_shiny` y `stats` (`{name, value}`
   con label español de `StatEnum` y `base_stat`, ordenadas por stat 1-6), cargando
   `pokemon.stats` para evitar N+1. Preservar el contrato existente
   (`pokemon.nombre`, `pokemon.pokemon_id`, `pokemon.types[].tipo_nombre`, `teamIds`,
   `equiposEnExploracion`).
2. Nuevo endpoint `DELETE /reclutado/{reclutado}` (`ReclutadoController::destroy`) que
   elimina un `Reclutado` del usuario: anti-IDOR vía global scope `BelongsToUser` (mismo
   patrón que `show`), bloqueo 422 si el `TeamMember` está en un equipo en exploración
   (`Team::isExploring()`), borrado del `TeamMember` primero y luego del `Reclutado` en
   transacción. Respuesta `{success: true}`.

## Archivos afectados

### Modificar
- `app/Http/Controllers/PlayerController.php` — eager load `pokemon.stats`, serialización
  aditiva de cada reclutado (método privado `serializarReclutado`).
- `app/Http/Controllers/ReclutadoController.php` — método `destroy(Reclutado): JsonResponse`
  (anti-IDOR por route-model binding + global scope, 422 en exploración, transacción
  member→reclutado).
- `routes/player.php` — `Route::delete('/reclutado/{reclutado}', [ReclutadoController::class, 'destroy']);`
- `active/ANALISIS_BACKEND.md` — este documento.

### Crear (tests)
- `tests/Feature/EquiposPayloadTest.php` — payload de `/equipos`.
- `tests/Feature/ReclutadoLiberarTest.php` — endpoint de liberar.

### Sin cambios
- Vistas Blade / JS Alpine (lo hará el agente Frontend).
- Modelos Eloquent (no se añaden `$appends` globales: la serialización es local al
  controlador para no alterar Datagrid/otros consumos).
- `src/Reclutamiento/App/ServicioEvolucion.php` (ya expone `siguienteEvolucion`,
  `puedeEvolucionar`, `requisitos`, `nivelDe`).
- Factories: NO se crean. Los tests existentes de los mismos dominios
  (`EquiposControllerTest`, `ReclutadoEvolucionTest`) crean modelos directamente con
  `::create()` vía helpers; no hay factories de Reclutado/Team/TeamMember/Pokemon y crear
  una infraestructura nueva sería deuda (regla "no crear abstracciones preventivas").

## Tests

Todos Feature (controllers + persistencia):

### `EquiposPayloadTest`
- `test_equipos_reclutados_incluyen_nivel_exp_total_base_experience_es_shiny_y_stats`
  — stats `{name, value}` con labels en español ordenadas por stat 1-6 aunque se inserten
  desordenadas; `pokemon.types[].tipo_nombre` preservado; contrato base intacto.
- `test_equipos_sin_stats_devuelve_lista_vacia` — reclutado sin filas de stats → `stats: []`
  (el tooltip del frontend ya trata listas vacías).

### `ReclutadoLiberarTest`
- `test_liberar_reclutado_sin_equipo_devuelve_success` — 200 `{success:true}`, fila borrada.
- `test_liberar_reclutado_asignado_a_equipo_inactivo_borra_member_y_reclutado` — 200,
  `team_members` y `reclutados` borrados.
- `test_liberar_reclutado_en_equipo_en_exploracion_devuelve_422` — 422, nada se borra.
- `test_liberar_reclutado_de_otro_usuario_devuelve_404` — anti-IDOR → 404 (global scope),
  la fila ajena permanece.

## Diseño

- Serialización local en `PlayerController` (no `$appends` en modelos): cada item =
  `$reclutado->toArray()` + claves aditivas `nivel`, `exp_total`, `base_experience`,
  `es_shiny`, `stats`; `pokemon.types` se re-serializa añadiendo `tipo_nombre` (accesor que
  no se incluye en `toArray()` por defecto y que el frontend ya consume).
- `destroy`: route-model binding `Reclutado $reclutado`; el global scope `BelongsToUser`
  hace que un reclutado ajeno resuelva 404 (idéntico a `show`). `$reclutado->teamMember`
  (HasOne) → si existe y `$member->team?->isExploring()` → 422; si no, `DB::transaction`
  borra el member y el reclutado. El FK `team_members.pokemon_id → reclutados.id` es
  `ON DELETE CASCADE`, pero el borrado explícito del member primero documenta la intención
  y evita depender del cascade.
- Nombre del método: `destroy` (convención REST Laravel en `app/`, igual que
  `TeamController::destroy`).

## Riesgos

- El contrato previo de `@json($reclutados)` era la serialización directa de los modelos
  (`exp` como `{total, tipos}` vía cast, `user_id`, timestamps). Mantener `toArray()` como
  base y añadir claves preserva el contrato; ninguna clave existente se elimina.
- `base_experience`/`stats` pueden ser `null`/`[]` si el `Pokemon` no existe o no tiene
  stats (pokemon_id required; casos legacy): el frontend (tooltip) ya tolera listas vacías.
- `tipo_nombre` en `pokemon.types` es aditivo: donde el frontend ya lo esperaba ahora
  funciona, y donde antes no estaba (types del payload actual) no rompe nada.

---

## Iteración anterior — Escalado de stats de combate en entrenadores de ruta + nivel real en la fórmula de daño

## Objetivo

Corregir el desequilibrio de daño en combates de entrenadores de ruta:

1. `BattleStats` debe conocer su nivel (propiedad `nivel` + getter), para que la fórmula de daño pueda usarlo.
2. `ManejadorDanioBase` debe usar el nivel real del atacante (`battleStats()->nivel`) en lugar del 50 hardcodeado.
3. `IniciarCombateEntrenador` debe escalar al jugador (nivel del usuario) y al rival (fórmula de gimnasios con `min_lvl_{1|2|3}` del hábitat).
4. `GeneradorEquipoEntrenador` debe aceptar `nivelRival` opcional y pasarlo a `MapeadorPokemonBatalla::desdePokemon()`.
5. `EntrenadorController::combatir()` debe pasar `nivelJugador` a `iniciar()`.

## Archivos afectados

### Modificar (código)
- `src/Pokemon/Domain/Stats/BattleStats.php` — añadir `public readonly int $nivel`, asignarlo en `calcularStats()`, getter `nivel()`.
- `src/Battle/Domain/Chain/ManejadorDanioBase.php` — `$nivel = $action->attacker->pokemon()->battleStats()->nivel ?? 50;`.
- `src/CombateEntrenadores/App/IniciarCombateEntrenador.php` — nuevo parámetro `int $nivelJugador`, escalar jugador, calcular y pasar `$nivelRival`.
- `src/CombateEntrenadores/App/GeneradorEquipoEntrenador.php` — `?int $nivelRival = null` en `generar()`, pasarlo a `desdePokemon()`.
- `src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php` — pasar `nivelJugador`.

### Modificar (tests — expects de daño a nivel 100)
El mock (`ConstruyeCombatientes` / `FabricaBatallaMock`) no pasa nivel → `BattleStats` usa default 100. Los asserts de daño que asumían nivel 50 deben reflejar el nivel 100:
- `tests/Unit/Battle/CadenaDanioTest.php` (24→44, 36/24→66/44; clamp sin cambio)
- `tests/Unit/Battle/ManejadorPosicionTest.php` (12→22, 24→44)
- `tests/Unit/Battle/ManejadorClimaTest.php` (24→44, 30→55, 18→33, 19→35; 44·1.25=55, 44·0.75=33, floor(44·0.8)=35)
- `tests/Unit/Battle/EfectoOrbeVidaTest.php` (31→57 = floor(44·1.3))

### Sin cambios
- `src/CombateEntrenadores/App/ObtenerEntrenadoresHabitat.php` — sigue llamando a `generar()` sin `nivelRival` (param opcional null → stats/default actuales; preview sin escalado, ok).

## Tests

- Unit (Battle): asserts de daño actualizados a nivel 100 en los 4 archivos citados.
- Feature: `tests/Feature/CombateLivewireTest.php` y suite `--filter=Gimnasio` (sin expects de daño; verificación de que nada se rompe).

Comandos de validación final:
- `php artisan test --compact --filter=Gimnasio`
- `php artisan test --compact tests/Feature/CombateLivewireTest.php`
- `php artisan test --compact tests/Unit/Battle/`

## Diseño

- `BattleStats::$nivel` — propiedad pública readonly `int`, asignada una sola vez en `calcularStats()` (llamado desde el constructor). Getter `nivel(): int` por coherencia.
- Serialización: `BattleStats` no define `__serialize/__unserialize`; la propiedad nueva se serializa con el mecanismo por defecto (PHP ≥ 8.1 soporta readonly en unserialize por defecto). Sesiones antiguas sin la propiedad: el `?? 50` de `ManejadorDanioBase` evita error (null-coalescing no lanza con propiedad no inicializada/inexistente). `SESSION_VERSION` ya está en 6 (bump de la tarea previa).
- `IniciarCombateEntrenador::iniciar()`: nueva firma `(habitatId, nivel, trainerIndex, teamId, userId, nivelJugador, fecha, formacion = [])`. Rival: `$habitat = Habitat::query()->find($habitatId)`, `$minLvl = $habitat?->getAttribute('min_lvl_'.$nivel) ?? 1`, `$nivelRival = $minLvl + intdiv($nivelJugador - $minLvl, 2)` (fórmula literal del brief).

## Riesgos

- Cambio de valores de daño en tests de Battle: corrección legítima de balance (nivel real vs 50).
- `Auth::user()` nullable en `EntrenadorController`: se sigue el patrón de `GimnasioController` (`$user = Auth::user(); $user->nivel()`), no `Auth::user()->nivel()` inline, para no romper PHPStan 6.
- Sin clamp `max(0, ...)` en la fórmula de entrenadores: el brief especifica la fórmula literal; a diferencia de gimnasios no hay bloqueo por nivel mínimo.