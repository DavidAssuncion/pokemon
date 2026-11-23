# TASK-070 — Ejecución

## Backend

### Casos de uso

- `ObtenerSeleccionVigente`: calcula la ventana, reutiliza la fila persistida o
  elige hábitat/familia deterministas y registra con `insertOrIgnore`. Relee
  para resolver carreras.
- `ObtenerAvistamientoVigente`: compone `AvistamientoPagina` (hábitat, familia,
  factor, fin, restante, niveles/especies y visto). Marca `sinEfecto` si la
  familia ya no está en el hábitat.

### Puerto y factor

- Restaurado `Src\Shared\Domain\Ports\FactorAparicion`.
- `FactorAparicionAvistamientos` (binding `scoped`): resuelve la selección una
  vez por instancia y devuelve el factor solo para el par hábitat/cadena.
- `PokemonDelPool` gana `factor`; `peso()` lo aplica solo al peso base (si hay
  `pesoOverride`, este es el peso final y no se duplica).
- `CargadorPoolHabitat`: `peso = factorRefugio * factorEvento`.
- `ProcesarExploracionHandler`: inyecta el puerto en el cargador.
- `GeneradorEquipoRuta`: `factor` por especie en `ponderar()`; ruta multiplica
  `capture_rate/hatch`. Entrenadores/gimnasios/mazmorras intactos.

### API y bindings

- `GET /api/avistamientos/vigente` (`auth`, nombre `avistamientos.vigente`).
- `AppServiceProvider`: binding del repositorio, `FactorAparicion` (scoped) y
  `Reloj` → `RelojPredeterminado`.

## Frontend

- Botón `fa-binoculars` en `navbar-acciones.blade.php`.
- `modal-avistamiento.blade.php`: overlay centrado, `role=dialog`, cierre por
  X/Escape/clic fuera. Estados cargando, error, sin evento, activo y
  `sin_efecto`. Especies por nivel con sprite y badge Visto/No visto.
- Store `resources/js/stores/avistamientos.js` registrado en `bootstrap.js`.
  Fetch bajo demanda (`/api/avistamientos/vigente`), TTL de 60 s.
- Vite build ejecutado.

## Verificación

- Ventana actual persistida: índice 107, hábitat 23 «Vieja Central», familia
  Abra (cadena 27), factor 5, 1 fila para la ventana, 3 niveles.
- `FactorAparicion`: `(23,27)=5`, `(23,28)=1`, `(24,27)=1`.
- `PoolHabitat`: base `100/10 × 5 = 50`; con override `2` → `2` (sin doble
  aplicación).
- `php artisan route:list --path=api/avistamientos -v`: auth presente.
- `php artisan view:cache`: OK.
- `npm run build`: OK.
- PHPStan nivel 6: 0 errores.
- Deptrac: 118 violaciones (sin cambios).
- Pint: OK.
