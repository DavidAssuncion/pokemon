# TASK-006 — Análisis

- **Analista:** `analyst`
- **Fecha:** 2026-11-02
- **Estado:** completado

## 1. «Reclutado eliminado» en el hábitat

**Causa raíz:** el reclutado **no se ha borrado**. El registro 59 existe, pertenece al usuario 2 y su `pokemon.name` es `rhyperior`; lo que ocurre es que `reclutados.nombre` es `NULL` por diseño (`app/Support/CrearReclutado.php:25`; columna nullable en `database/migrations/2026_05_27_000001_create_reclutados_table.php:17`). La vista usa el operador `??`, que también cae al fallback cuando el nombre existe pero es `null`:

- `resources/views/habitats/show.blade.php:160` → `{{ $exp->reclutado->nombre ?? 'Reclutado eliminado' }}`.

Además, `HabitatsController::show` solo eager-carga `reclutado` (`app/Http/Controllers/HabitatsController.php:55`), no `reclutado.pokemon`, por lo que el fallback al nombre del Pokémon necesitaría lazy loading o un `with('reclutado.pokemon')`.

**Fallback correcto:** `nombre` no vacío → `pokemon.name` no vacío → `'Reclutado eliminado'`. El patrón ya existe en `src/Exploraciones/Presentation/ExploradorVista.php:101-116` y en `app/Support/ReclutadoSerializer.php:57`. Conviene usar `?:`/`trim` en lugar de `??` para cubrir también cadenas vacías.

**Tests:** `tests/Feature/HabitatsControllerTest.php:85,101` solo comprueban que se pasa el nombre; `tests/Feature/HabitatsViewTest.php:28` renderiza la vista con `exploracionesActivas` vacía, así que el bloque de exploraciones activas no se ejercita. Caso nuevo: reclutado con `nombre=null` y `pokemon.name='rhyperior'` en exploración activa → `assertSee('rhyperior')` y `assertDontSee('Reclutado eliminado')`; caso con relación realmente nula → mantiene el fallback.

## 2. Layout actual de recompensas de combate

La rama `layout === 'ruta'` de `resources/views/livewire/combate.blade.php:131-196` apila caramelos y capturas dentro de una única tarjeta `bg-gray-50 ... rounded-lg p-3`:

- Caramelos: familia (`:146`), EV (`:156`) y tipo (`:166`), separados por `<hr>`.
- Capturas: bloque «Capturados» (`:181-196`) con imágenes 16×16 y badge verde.
- `BatallaVistaPresenter::rewardsVista()` expone `tieneCaramelos`, `capturas`, `caramelos_familia/ev/tipo` y asigna `layout='ruta'` si hay caramelos **o** capturas (`app/Livewire/Presenters/BatallaVistaPresenter.php:347`). No expone `tieneCapturas`.

**Propuesta:** convertir el contenedor en un grid de 3 columnas:

- Si hay capturas y caramelos: capturas `lg:col-span-1`, caramelos `lg:col-span-2` con tres filas (familia/EV/tipo).
- Si no hay capturas: caramelos `lg:col-span-3` (full width).
- Si hay capturas pero no caramelos: capturas `lg:col-span-3`.
- Solo afecta a `layout === 'ruta'`; el layout `entrenador`/gimnasio (lista plana) no se toca.
- Matiz: `layout='ruta'` se asigna por datos, no por tipo de combate; un combate de entrenador con caramelos también usará el grid. Es coherente con el formato de recompensas.

**Tests:** no hay test que renderice el modal de victoria. `tests/Feature/CombateLivewireTest.php:13/19/25` cubre mount/log/creación; `tests/Feature/CombateRuta/PresentadorRutaTest.php:40/54` cubre el payload. Caso nuevo: montar `App\Livewire\Combate`, fijar `phase='battle_over'` y `rewards` con un caramelo de familia y una captura, y asertar `grid-cols-3`, `col-span-1`, `col-span-2` y «Capturados»; variante sin capturas → `col-span-3`.

## 3. Riesgos y dudas

- El fallback de nombre debe usar `?:` o `trim`, no `??`, para cubrir `''` además de `null`.
- `reclutado.pokemon` debe eager-cargarse para no introducir N+1 en el listado de exploraciones activas.
- El grid del modal debe ser responsive: en móvil una columna; el `lg:grid-cols-3` solo aplica en pantallas grandes.
- No se cambia la regla de recompensas ni el contrato persistido.

**Confirmación:** no se modificó ningún archivo.
