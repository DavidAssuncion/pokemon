# TASK-069 — Ejecución

- **Fecha:** 2026-11-19
- **Estado:** IMPLEMENTADO (con elementos diferidos)

## F1 — Bugs

### Gimnasio sin etapa
`src/Gimnasios/App/IniciarCombateGimnasio.php`: el `else` devuelve
`new DatosPokemonBatallaCollection()` en vez de `[]`, por lo que
`$datosRival->isEmpty()` ya no revienta y se lanza el
`ViolacionReglaNegocio` previsto.

### Caramelos de tipo
`app/Http/Controllers/ReclutadoController.php::darCaramelo`:
- `lockForUpdate()` sobre el reclutado dentro de la transacción.
- La EXP se aplica y persiste dentro de la misma transacción que el
  `decrement` del caramelo.
- Se sustituye el literal `100` por `Src\Shared\Domain\ReglasCaramelo::EXP_POR_CARAMELO`.

### Caramelos EV
`darEvs`: se elimina `max(1, $maxPorEv)`; si el cálculo da 0 se devuelve 422.

### Evolución
`evolucionar`: lock del reclutado + revalidación de `pokemon_id` y
`puedeEvolucionarPara()` dentro de la transacción. Dos peticiones concurrentes
no pueden consumir la EXP dos veces.

### Favoritos
`toggleFavorito`: mutex con `User::whereKey($userId)->lockForUpdate()` y conteo
+ toggle dentro de la transacción. `Favorito::toggle()` usa `insertOrIgnore`
(evita excepciones de unique y rellena `created_at`).

### Soft-lock IA
`GestorAccionBatalla::prepararAnimacionIa()` devuelve `bool` y cierra el turno
con `cancelarTurnoIa()` (consume acción, limpia pendiente, sincroniza, guarda y
avanza) cuando no hay acción, objetivo o movimiento. El llamador
(`GestorCicloTurnosBatalla`) retorna sin reconstruir la cola si no hay
animación.

### Evolución nullable
`ServicioEvolucion::opcionesEvolucion()`: el `map` devuelve `?Pokemon` y el
`filter()` ya no es inalcanzable. `idsEvolucion()` tipado.

### Capacidades
`FabricaCapacidadesStats::desdeReclutado()` acepta `?User` y tolera
`pokemon === null` (stats a 0).

### Admin de gimnasios
- Migración `2026_11_19_000001_add_is_admin_to_users_table.php`.
- `User::isAdmin()` + cast `is_admin`.
- Gate `admin` en `AppServiceProvider`.
- Rutas `/api/admin/gyms` bajo `can:admin` (verificado con `route:list`: 403
  para no admin).

### Invariantes de BD
Migración `2026_11_19_000002_add_unique_active_invariants.php`:
- Limpia duplicados previos.
- Índice parcial `exploraciones_activas (reclutado_id) WHERE regreso IS NULL`.
- Índices parciales de `favoritos` para `habitat_id IS NULL` / `IS NOT NULL`.
- `validarYNuevaExploracion()` traduce la violación de unique a 422.

### Otros
- `AppServiceProvider`: `Schema::hasTable('users')` perezoso y cacheado por
  petición.
- `PresentadorResultadoBatalla::procesarGimnasio`: `Log::error` + mensaje en el
  log si el gimnasio no existe, en vez de perder la medalla en silencio.

## F2 — Código muerto

Eliminados:
- `src/Shared/Infrastructure/Random/GeneradorAleatorioPhp.php`
- `src/Shared/Infrastructure/Time/RelojSistema.php`
- `src/Battle/Domain/Effects/EfectoMoody.php`
- `src/Battle/Domain/Enums/EstadoPendiente.php`
- `src/Shared/Domain/Ports/FactorAparicion.php`
- `src/CombateRuta/App/SelectorMovimientosMeta.php`
- `PresentadorExploraciones::{terminadaToView, transformarEvento, finExploracion}`

No eliminado: `src/Avistamientos/**` (módulo sin cablear; requiere decisión).

## F3 — Duplicación

- `StatEnum::key()` en `PlayerController`, `EstadisticasBasePokemon`,
  `FabricaCapacidadesStats` y `MapeadorPokemonBatalla`.
- Trait `Src\Habitats\Presentation\Concerns\FamiliaEvolutivaComun` compartido
  por `DTOFamiliaDisponible` y `DTOFamiliaSinHabitat`.
- Trait `App\Livewire\Combate\Soporte\Concerns\ObjetivosBatalla`.
- `CambiosStatsCollection::desdeSerializado()` usada por `MovimientoBatalla` y
  `DTOMovimientoBatalla`.
- `ExploracionActivaController::horaDeVueltaValida()`.
- `Rule::enum(RolExploracion::class)` en los tres validadores.
- `PresentadorExploraciones::exploradorYDiario()`.

## F4 — Encapsulamiento

- 10 controladores de módulo dejan de extender `App\Http\Controllers\Controller`
  (Deptrac: 128 → 118 violaciones).

## F5 — Clases extensas

- `ColeccionEfectos::multiplicarPorInterfaz()` centraliza 8 multiplicadores.
- `PresentadorExploraciones` reduce repeticiones con `exploradorYDiario()`.

## F6 — Verificación

- `php -l` de todos los archivos modificados: OK.
- `vendor/bin/pint` sobre los archivos tocados: OK.
- `vendor/bin/phpstan analyse` (nivel 6): **0 errores**.
- Baseline actualizada: 201 → 199 entradas (2 obsoletas eliminadas, 4 conteos
  corregidos); `phpstan.neon` documenta las excepciones de Larastan/JSON.
- `vendor/bin/deptrac analyse`: **118 violaciones** (antes 128).
