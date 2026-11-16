# TASK-048 — Arquitectura y diseño

- **Estado:** PROPUESTO (diseño; no autoriza implementación)
- **Arquitecto:** `architect`, sesión `ses_e1c8ad95dffewXh1nI8MuUnAO0`.
- **Base:** `.opencode/work/TASK-048/analysis.md`.
- **Nota:** se reproduce el diseño del arquitecto sin alterar sus conclusiones.

---

# TASK-048 — Diseño: icono de avistamientos, página `/avistamientos` y evento global de 3 días

> Diseño únicamente. No se ha modificado ningún archivo, no se han ejecutado migraciones, seeders ni tests.

## 1. Recomendación

Se construye un módulo nuevo `src/Avistamientos/` (Domain puro, App con casos de uso, Infra con Eloquent/controlador) que resuelve **ventanas consecutivas de 3 días naturales** ancladas a `2026-01-01 00:00 Europe/Madrid`. En cada ventana se elige, de forma determinista con `crc32` sobre listas ordenadas por id, **un hábitat elegible** (43 candidatos: los 44 con pool menos el 49 «Legendarios») y **una familia** (`evolution_chain_id`) con ≥2 especies en ese hábitat. La selección se **persiste** en `avistamiento_eventos` con `unique(window_index)` e `insertOrIgnore`, de modo que un cambio del pool a mitad de ventana no altera el evento. El factor único es **×5** y se aplica **una sola vez** en `PokemonDelPool::peso()`, mediante un campo opcional `factor` que multiplica el peso ya calculado: en ruta respeta `capture_rate/hatch`; en exploración multiplica el peso de refugio (uniforme por defecto). Se integra solo en **ruta y exploración**, a través de un puerto en `Shared` (`FactorAparicion`) implementado en `Src\Avistamientos\Infra`, evitando que `CombateRuta` y `Exploraciones` dependan del módulo nuevo. La UI es un `<a>` con `fa-binoculars` en `navbar-acciones.blade.php` (sin 7.º ítem de navegación, sin tocar `app.blade.php`) y una página Blade de solo lectura. Es seguro con el árbol sucio porque el único archivo con cambios locales que se toca es `GeneradorEquipoRuta.php`, y solo se añade una dependencia opcional y dos llamadas; `app.blade.php` no se modifica. No hay cron, notificaciones, moneda, misiones ni tests.

## 2. Decisiones cerradas

| # | Decisión | Elección | Motivo |
|---|---|---|---|
| 1 | Ámbito | **Global** | Mismo estado de mundo para todos; sin tabla por usuario ni IDOR; la página solo usa `Pokedex` del autenticado para «visto». Sin impedimento encontrado. |
| 2 | Modos | **Ruta + exploración** | Son los dos spawns salvajes desde `pokemon_habitat`. Entrenadores/gimnasios/mazmorras usan equipos curados; además el factor es *opt-in* por construcción de pool: quien no lo inyecta obtiene 1.0. |
| 3 | Persistencia | **Tabla `avistamiento_eventos`** | El pool es editable por cualquier usuario autenticado (`routes/habitats.php:24-29`); persistir congela la ventana y evita que la página y el spawn discrepen. |
| 4 | Factor | **×5 único, multiplicativo** | Un solo valor (`ReglasAvistamiento::FACTOR_APARICION`) y un solo punto de aplicación. En ruta conserva la rareza; en exploración es fuerte pero aceptable (ver §6). |
| 5 | Elegibilidad | **Hábitat ≠49, pool no vacío, cadena no nula y familia con ≥2 especies en el hábitat** (fallback ≥1) | 101 de 122 pools de nivel solo tienen familias de 1 especie; exigir 2 mejora la perceptibilidad. Los 43 hábitats candidatos tienen al menos una familia de ≥2, así que no se pierde ninguno. |
| 6 | Ubicación | **`src/Avistamientos/` completo**, controlador en `Infra/Controllers`, ruta en `routes/avistamientos.php`, URL `/avistamientos` | Regla `ddd-modules.md` e `infra-controllers.md`; `app/` queda para legado. |

## 3. Producto

**Qué ve el jugador en `/avistamientos`** (solo lectura):

- Tarjeta principal «Avistamiento activo» con:
  - Hábitat destacado: imagen 3:2, nombre y enlace a `/habitats/{id}`.
  - Familia: nombre visible derivado del miembro con menor `species_id` (p. ej. «petilil»).
  - Multiplicador como badge «×5» y texto «Aparición aumentada».
  - Tiempo restante ya calculado en backend («Termina en 1 d 12 h») y `<time datetime>` con la fecha absoluta y offset.
  - Especies afectadas agrupadas por nivel (1/2/3), cada una con `x-sprite`, nombre, badge «Nivel N» y estado **Visto / No visto** (texto, no solo color).
  - Aviso si la familia ya no está en el hábitat: «Este avistamiento ya no tiene efecto en el hábitat».
- Si no hubiera selección (BD sin candidatos): estado vacío «No hay ningún avistamiento activo».
- Sin botones de acción, sin recompensas, sin notificaciones.

**Cómo se enteran:** el icono de prismáticos está siempre en la navbar. Un **punto** pequeño aparece cuando la ventana actual no coincide con la última que el jugador abrió; se guarda en `localStorage` al pulsar el enlace. Es estado **cosmético**, no de negocio, y no requiere backend ni promete notificaciones. Si no hay JS, el punto no se muestra y el icono sigue funcionando.

**Al caducar:** el spawn vuelve al estado previo sin job ni limpieza: el factor deja de coincidir con la ventana vigente y pasa a 1.0. La página muestra automáticamente la nueva ventana; la fila anterior permanece como histórico auditable. No se reutilizan filas ni se actualizan.

## 4. Regla del evento

**Ancla y ventana.** `ANCLA = 2026-01-01T00:00:00` en `Europe/Madrid` (decisión de producto; fecha limpia y anterior a la vida útil esperada). Índice:

```text
indice = floor(diasNaturalesEntre(ancla, fechaLocal(ahora)) / 3)
inicio = ancla + 3*indice días (calendario local, medianoche)
fin    = inicio + 3 días (calendario local)
```

`VentanaAvistamiento` expone `indice()`, `inicio()`, `fin()`, `contiene(DateTimeImmutable): bool` y `segundosRestantes(DateTimeImmutable): int`. Los días son **naturales locales**, no 72 h absolutas: una ventana que cruza un cambio de hora dura 71 h o 73 h reales (ver §12). En BD se guardan `starts_at`/`ends_at` en UTC y se muestran con offset.

**Selección determinista.** Listas siempre ordenadas ascendente por id; sal versionada para que un cambio de algoritmo no altere una ventana en curso:

```text
salHabitat = "avistamientos:v1:habitat:{indice}"
indiceHabitat = crc32(salHabitat) % totalHabitats

salFamilia = "avistamientos:v1:familia:{indice}:{habitatId}"
indiceFamilia = crc32(salFamilia) % totalFamilias
```

En PHP, `crc32()` puede devolver entero con signo en plataformas de 32 bits: usar `sprintf('%u', crc32($sal))` antes del módulo. El cálculo vive en `SelectorAvistamiento` (Domain puro, sin framework).

**Candidatos.**
- Hábitats: `pokemon_habitat` con al menos una familia de ≥2 especies, excluyendo el id 49 por constante `ReglasAvistamiento::HABITATS_EXCLUIDOS = [49]`. Resultado con el CSV del análisis: 43 ids `[1,2,3,4,5,6,7,8,9,10,11,13,…,48]`.
- Familias del hábitat: `evolution_chain_id` no nulo, agrupadas, con `count(*) >= 2`; si el pool editado dejara la lista vacía, se reintenta con `>= 1` (degradado, se registra igual).
- Se descartan los 12 hábitats sin pool (`[12,17,30,50,51,52,53,54,55,56,57,58]`) por la propia consulta.

**Ejemplo reproducible** (asumiendo que `pokemon_habitat` coincide con `storage/data/pokemon_habitat.csv`; hoy 2026-11-16):

| Campo | Valor |
|---|---|
| Días desde ancla | 319 → `indice = 106` |
| Ventana | 2026-11-15 00:00 +01:00 → 2026-11-18 00:00 +01:00 |
| `crc32("avistamientos:v1:habitat:106")` | 1 897 854 752 → `% 43 = 1` → hábitat **2, Bosque Brumoso** |
| Familias ≥2 ordenadas | `[18,19,142,217,276,278,279,337]` (8) |
| `crc32("avistamientos:v1:familia:106:2")` | 2 197 757 894 → `% 8 = 6` → cadena **279** |
| Familia | **petilil** (548, nivel 1) y **lilligant** (549, nivel 2) |
| Factor | ×5 |

La siguiente ventana (107) daría hábitat 9 «Caverna Gélida» y familia 59 (smoochum/jynx), lo que confirma rotación. Con la regla ≥2 se descarta, por ejemplo, la familia 463 (una sola especie, dracovish) que saldría sin filtro.

**Si el pool cambia a mitad de ventana:** la fila persistida no se toca. El factor solo afecta a especies que sigan en el pivote del hábitat; si la familia se elimina, ningún peso coincide y el evento queda sin efecto, sin re-selección y sin incoherencia entre usuarios. La página muestra el aviso de «sin efecto» y conserva hábitat, familia y fechas originales.

## 5. Factor de aparición

**Fórmula única** en `PokemonDelPool::peso()`:

```text
base  = pesoOverride ?? (captureRate / max(1, hatch))
peso  = max(0, base * factor)
```

- **Ruta:** `factor = 5` si `(habitatId, evolutionChainId)` coincide; si no, 1. El contrato de `ponderar()` no envía `peso`, así que `base = capture_rate/hatch` y el ×5 **conserva la rareza relativa**.
- **Exploración:** `CargadorPoolHabitat` envía `peso = factorRefugio` (1.0 por defecto) y `factor = 5`; `base × 5`.
- **Orden:** base (refugio o rareza) → × factor evento → `max(0)` → `PoolHabitat::ponderado()` descarta ≤0 → `ColeccionPoolPonderado` normaliza por suma. **Senda** solo modifica captura (`OtorgarRecompensasRuta:201`, `GeneradorTiradaCapturaExpedicion:55`), no aparición: no interactúa.
- **Una sola vez:** el factor es una propiedad del contrato, se inyecta al construir el pool y `peso()` se invoca exactamente una vez por entrada desde `ponderado()`. No hay ninguna otra multiplicación; `especiesPonderadas()` reutiliza `ponderar()` en una lectura independiente, no suma.

**Ejemplo numérico real (ventana 106, hábitat 2):**

| Modo | Especie | Peso antes | Peso después | Cuota antes → después |
|---|---|---|---|---|
| Ruta nivel 1 | petilil (190/20) | 9.5 | **47.5** | 10.9 % → **38.0 %** (total 87 → 125) |
| Ruta nivel 2 | lilligant (75/20) | 3.75 | **18.75** | 10.1 % → **35.9 %** (total 37.25 → 52.25) |
| Ruta nivel 3 | — | — | — | La familia no aparece: 0 % |
| Exploración nivel 1 | petilil | 1.0 | **5.0** | 12.5 % → **41.7 %** (8 especies: 5/12) |

En ruta la relación interna petilil:lilligant se mantiene (9.5:3.75 = 47.5:18.75). En exploración el salto es fuerte porque el pool es uniforme; se acepta: solo afecta a 1 de 43 hábitats y a 2 especies, y el valor queda en una constante para ajustarlo a ×3 (46.2 %) sin tocar código. **No se corrige** el override de rareza de exploración en esta tarea.

## 6. Persistencia y concurrencia

Migración `2026_11_16_000001_create_avistamiento_eventos_table.php`:

| Columna | Tipo | Nulo | Notas |
|---|---|---|---|
| `id` | `bigIncrements` | no | PK |
| `window_index` | `unsignedInteger` | no | **unique**; clave de lectura |
| `starts_at` | `timestamp` | no | UTC en BD |
| `ends_at` | `timestamp` | no | UTC en BD |
| `habitat_id` | `foreignId` | no | FK `habitats`, `restrictOnDelete` |
| `evolution_chain_id` | `unsignedBigInteger` | no | Sin FK: las cadenas son una columna, no una tabla |
| `factor` | `decimal(6,2)` | no | `5.00` |
| `created_at` / `updated_at` | `timestamps` | sí | Estándar |

Casts del modelo `AvistamientoEvento`: `window_index` int, `starts_at`/`ends_at` datetime, `factor` float, `habitat_id` int, `evolution_chain_id` int. Índices: solo `unique(window_index)`; la lectura es una fila por índice único y no se justifica otro índice (no hay listados ni borrados por fecha).

**Escritura idempotente:**

1. `buscarPorVentana(indice)`; si existe, se usa.
2. Si no, se calcula la selección y se ejecuta `insertOrIgnore` con `created_at`/`updated_at` del `Reloj` (PostgreSQL `ON CONFLICT DO NOTHING`).
3. Se vuelve a leer por `window_index`; gana la fila que insertó primero. Nunca se actualiza.

**Concurrencia:** dos primeras lecturas simultáneas (dos usuarios, o tick + página) pueden calcular a la vez; ambas calculan lo mismo por ser deterministas, una inserta y la otra es ignorada por el unique. No hay excepción ni bloqueo largo. `insertOrIgnore` silencia otras violaciones: la selección se valida antes (hábitat/cadena existentes) y la FK protege la integridad. Si la transacción del tick revierte, la fila también revierte; la siguiente llamada la recalcula igual.

**Lectura sin N+1:** spawn = 1 fila por unique (y 1-2 solo la primera vez en 3 días); página = evento (1), hábitat (1), especies de la familia en el hábitat (1), `Pokedex` visto del usuario (1). Si la familia ya no está, una consulta extra para el nombre global de la cadena.

## 7. Backend

```text
src/Avistamientos/
├── Domain/
│   ├── ReglasAvistamiento.php                 # ANCLA, DURACION_DIAS=3, FACTOR=5.0, HABITATS_EXCLUIDOS=[49], MIN_ESPECIES_FAMILIA=2
│   ├── Services/
│   │   ├── CalculadoraVentanaAvistamiento.php # ahora → VentanaAvistamiento
│   │   └── SelectorAvistamiento.php           # crc32 sobre colecciones ordenadas
│   ├── ValueObjects/
│   │   ├── VentanaAvistamiento.php
│   │   ├── SeleccionAvistamiento.php          # habitatId, evolutionChainId, factor
│   │   ├── HabitatCandidato.php + HabitatsCandidatosCollection.php
│   │   └── FamiliaCandidata.php + FamiliasCandidatasCollection.php
│   ├── DataTransferObjects/
│   │   ├── AvistamientoRegistrado.php         # fila persistida
│   │   ├── SeleccionVigente.php               # ventana + fila + factor
│   │   ├── HabitatAvistamiento.php
│   │   ├── EspecieEnHabitat.php + EspeciesEnHabitatCollection.php
│   │   └── AvistamientoPagina.php, NivelAvistamiento.php, EspecieAvistada.php (+ colecciones)
│   └── Repositories/AvistamientoRepositorioInterface.php
├── App/
│   ├── ObtenerSeleccionVigente.php            # ventana → fila o crear; fuente única
│   └── ObtenerAvistamientoPagina.php          # detalle + Pokedex + textos derivados
└── Infra/
    ├── Models/AvistamientoEvento.php
    ├── EloquentAvistamientoRepositorio.php
    ├── FactorAparicionAvistamientos.php       # implementa el puerto Shared
    └── Controllers/AvistamientosController.php
```

**Contratos principales** (firmas, sin implementación):

```php
final class CalculadoraVentanaAvistamiento {
    public function actual(DateTimeImmutable $ahora): VentanaAvistamiento;
    public function en(DateTimeImmutable $instante): VentanaAvistamiento;
}

final class SelectorAvistamiento {
    public function habitat(HabitatsCandidatosCollection $c, VentanaAvistamiento $v): HabitatCandidato;
    public function familia(FamiliasCandidatasCollection $c, VentanaAvistamiento $v, int $habitatId): FamiliaCandidata;
}

interface AvistamientoRepositorioInterface {
    public function buscarPorVentana(int $indice): ?AvistamientoRegistrado;
    public function registrarSiNoExiste(AvistamientoRegistrado $registro): void;
    public function habitatsCandidatos(): HabitatsCandidatosCollection;
    public function familiasCandidatas(int $habitatId): FamiliasCandidatasCollection;
    public function habitat(int $habitatId): ?HabitatAvistamiento;
    public function especiesDeFamilia(int $habitatId, int $evolutionChainId): EspeciesEnHabitatCollection;
    public function nombreGlobalDeFamilia(int $evolutionChainId): ?string;
    public function vistosDelJugador(EspeciesEnHabitatCollection $especies): EspeciesVistasCollection;
}

final class ObtenerSeleccionVigente {
    public function handle(): ?SeleccionVigente;
}

final class ObtenerAvistamientoPagina {
    public function handle(bool $desbloqueoTodo = false): AvistamientoPagina;
}
```

`ObtenerSeleccionVigente` recibe `CalculadoraVentanaAvistamiento`, `SelectorAvistamiento`, `AvistamientoRepositorioInterface` y `Reloj` (puerto existente). Si no hay candidatos devuelve `null`; no inventa excepciones. `ObtenerAvistamientoPagina` compone el DTO con `niveles` ya agrupados y ordenados (nivel asc, luego `species_id`), `nombreFamilia`, `restanteTexto`, `terminaEn` ISO-8601 y `sinEfecto`. Así Blade no calcula nada.

**Controlador y ruta:**

```php
// routes/avistamientos.php
Route::get('/avistamientos', AvistamientosController::class)->name('avistamientos.index');
```

Se añade `require __DIR__.'/avistamientos.php';` dentro del grupo `auth` de `routes/web.php` (junto a las líneas 38-48). El controlador (`final`, `__invoke(Request): View`) inyecta `ObtenerAvistamientoPagina`, lee `DesbloqueoPokedex::activo($request)` y devuelve `view('avistamientos.index', ['avistamiento' => $dto])`. Sin try-catch y sin lógica.

**Bindings** en `AppServiceProvider::register()`:

```php
$this->app->bind(AvistamientoRepositorioInterface::class, EloquentAvistamientoRepositorio::class);
$this->app->scoped(FactorAparicion::class, FactorAparicionAvistamientos::class);
// opcional, recomendado: $this->app->bind(Reloj::class, RelojPredeterminado::class);
```

`scoped` memoiza la selección dentro de request/job y se reinicia entre jobs, evitando repetir la consulta sin riesgo de quedarse con una ventana vieja en un worker.

## 8. Integración en el spawn

Puerto nuevo en `src/Shared/Domain/Ports/FactorAparicion.php`:

```php
interface FactorAparicion {
    public function factor(int $habitatId, ?int $evolutionChainId): float;
}
```

`FactorAparicionAvistamientos` resuelve `ObtenerSeleccionVigente`, memoiza y devuelve `5.0` solo si hábitat y cadena coinciden; si no, 1.0. `Shared` no depende de módulos: solo el puerto.

**Cambios exactos:**

| Archivo | Cambio |
|---|---|
| `src/Exploraciones/Domain/ValueObjects/PokemonDelPool.php` | Nueva propiedad `public readonly float $factor = 1.0` (constructor, `desdeArray` leyendo `'factor'`, `aArray` emitiéndolo si ≠1.0) y `peso()` → `max(0, base * $this->factor)`. |
| `src/Exploraciones/App/Procesamiento/CargadorPoolHabitat.php` | Constructor acepta `?FactorAparicion $factorAparicion = null`; en el contrato (líneas 62-63) se añade `'factor' => $this->factorAparicion?->factor($exploracion->habitat_id, $pokemon->evolution_chain_id) ?? 1.0`. `peso` sigue siendo el refugio. |
| `src/Exploraciones/App/ProcesarExploracionHandler.php` | Constructor añade `?FactorAparicion $factorAparicion = null`; línea 79 → `new CargadorPoolHabitat($bonosFamilia, $factorAparicion)`. El bus ya resuelve el handler por contenedor (`LaravelCommandBus:41`). |
| `src/CombateRuta/App/GeneradorEquipoRuta.php` | Constructor añade `?FactorAparicion $factorAparicion = null`; `generar()` línea 77 y `especiesPonderadas()` línea 145 pasan `$habitatId`; `ponderar(array $pool, int $habitatId)` añade `'factor' => ... ?? 1.0` al contrato (líneas 202-209). **No se toca `especiesPonderadas` más allá de la llamada**, ni `especiesPonderadas` se reescribe: es código local sin commit y se respeta. |

**DI:** `GeneradorEquipoRuta` y `ProcesarExploracionHandler` se resuelven por contenedor y autowiran el puerto; `CargadorPoolHabitat` se instancia con `new`, así que recibe la dependencia explícita desde el handler. Sin `app()` ni service locator.

**Acoplamiento:** `CombateRuta` y `Exploraciones` importan únicamente `Src\Shared\Domain\Ports\FactorAparicion`; `Src\Avistamientos` no aparece en sus imports. Opciones descartadas: post-procesar pesos en cada módulo (duplica la fórmula y arriesga doble aplicación) y un singleton global leído por helper estático (acoplamiento oculto e imposible de testear). La opción elegida es la menos invasiva que mantiene un único punto matemático.

## 9. Navbar y UI

`resources/views/layouts/partials/navbar-acciones.blade.php` está limpio. Se añade `$avistamientosActivo = request()->is('avistamientos*');` al bloque `@php` (líneas 8-13) y este `<a>` dentro del grupo `relative` (líneas 15-40), antes del cierre:

```blade
<a
    href="{{ route('avistamientos.index') }}"
    aria-label="Avistamientos"
    title="Avistamientos"
    @if($avistamientosActivo) aria-current="page" @endif
    x-data="{ pendiente: true }"
    x-init="pendiente = localStorage.getItem('avistamiento-visto') !== '{{ $ventanaAvistamiento?->indice() }}'"
    @click="localStorage.setItem('avistamiento-visto', '{{ $ventanaAvistamiento?->indice() }}')"
    class="relative {{ $claseBoton }} {{ $avistamientosActivo ? 'bg-primary-soft text-primary-ink' : $claseInactivo }}"
>
    <i class="fa-solid fa-binoculars" aria-hidden="true"></i>
    <span x-show="pendiente" x-cloak aria-hidden="true"
          class="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full bg-primary ring-2 ring-surface"></span>
</a>
```

- `fa-binoculars` está verificado en el build de Font Awesome 6 Free (`public/build/assets/app-S-0YB6LG.css`).
- Estado activo con el mismo patrón que Reclutamiento/Expediciones; `aria-current="page"` para lectores de pantalla.
- El punto es «ventana no vista», con `x-cloak` para evitar parpadeo y degradación limpia sin JS.
- Para el índice de ventana se añade un **composer ligero sin BD** en `AppServiceProvider::boot()` sobre `layouts.partials.navbar-acciones` que pasa `$ventanaAvistamiento` usando `CalculadoraVentanaAvistamiento` + `RelojPredeterminado`. Si se prefiere cero cambios en el provider, se elimina el punto y queda solo el icono.
- **No se toca `app.blade.php`**: el `@include` ya existe en la línea 68, fuera del contenedor `overflow-x-auto`, que es justo donde debe seguir el grupo.

**Página `resources/views/avistamientos/index.blade.php`:**

- `@extends('layouts.app')`, `@section('title', 'Avistamientos')`, `h1` y párrafo introductorio.
- `x-ui.card` con `title="Avistamiento activo"` y `subtitle` con el multiplicador; dentro, grid `grid-cols-1 md:grid-cols-[minmax(0,16rem)_1fr] gap-6`: figura del hábitat enlazada a `/habitats/{id}` y bloque de datos.
- Especies: un `<ul>` por nivel (`aria-label="Especies afectadas en nivel N"`), cada `<li>` con `x-sprite` (`size="64"`), nombre, badge de nivel y texto Visto/No visto.
- Footer del card: `<time datetime="{{ $avistamiento->terminaEn }}">` y enlace «Ver hábitat».
- Aviso `x-ui.alert variant="warning"` cuando `sinEfecto`.
- Accesibilidad: jerarquía `h1`/`h2`/`h3`, `alt` real en sprites, estado por texto además de color, foco visible con `focus-ring`.
- Responsive: una columna en móvil, imagen primero; dos columnas desde `md`; sin JS propio (el estado visto ya viene del backend).

## 10. Plan de implementación por archivos

| Orden | Archivo | Responsable | Depende de |
|---|---|---|---|
| 1 | `database/migrations/2026_11_16_000001_create_avistamiento_eventos_table.php` | database | — |
| 2 | `src/Shared/Domain/Ports/FactorAparicion.php` | backend | — |
| 3 | `src/Avistamientos/Domain/**` (reglas, VOs, DTOs, servicios, interfaz) | backend | 2 |
| 4 | `src/Avistamientos/Infra/Models/AvistamientoEvento.php` | backend | 1 |
| 5 | `src/Avistamientos/Infra/EloquentAvistamientoRepositorio.php` | backend | 1, 3, 4 |
| 6 | `src/Avistamientos/App/ObtenerSeleccionVigente.php` | backend | 3, 5 |
| 7 | `src/Avistamientos/Infra/FactorAparicionAvistamientos.php` | backend | 2, 6 |
| 8 | `src/Avistamientos/App/ObtenerAvistamientoPagina.php` y DTOs de página | backend | 5, 6 |
| 9 | `src/Avistamientos/Infra/Controllers/AvistamientosController.php` | backend | 8 |
| 10 | `routes/avistamientos.php` + `require` en `routes/web.php` | backend | 9 |
| 11 | `app/Providers/AppServiceProvider.php` (2-3 bindings + composer opcional) | backend | 5, 7 |
| 12 | `src/Exploraciones/Domain/ValueObjects/PokemonDelPool.php` | backend | — |
| 13 | `src/Exploraciones/App/Procesamiento/CargadorPoolHabitat.php` | backend | 2, 12 |
| 14 | `src/Exploraciones/App/ProcesarExploracionHandler.php` | backend | 13 |
| 15 | `src/CombateRuta/App/GeneradorEquipoRuta.php` | backend | 2, 12 |
| 16 | `resources/views/layouts/partials/navbar-acciones.blade.php` | frontend | 10, 11 |
| 17 | `resources/views/avistamientos/index.blade.php` | frontend | 8, 10 |

Orden ejecutable sin tests. El punto 15 exige revisar el diff local de `GeneradorEquipoRuta.php` antes de editar y no revertir `especiesPonderadas`.

## 11. Verificación

**Estática:** `php -l` sobre todos los PHP nuevos/modificados; `vendor/bin/pint --dirty --format agent`; `php artisan route:list --path=avistamientos --except-vendor` (debe mostrar `GET /avistamientos` con nombre `avistamientos.index`). No se ejecuta la suite.

**Manual (con la BD sembrada y el CSV como referencia):**
1. Entrar y abrir `/avistamientos` el 2026-11-16: hábitat «Bosque Brumoso», familia «petilil», especies petilil (nivel 1) y lilligant (nivel 2), «×5», fin `2026-11-18T00:00:00+01:00`, restante «1 d 12 h».
2. `select * from avistamiento_eventos where window_index = 106` → exactamente 1 fila; repetir la visita no crea otra.
3. `app(FactorAparicion::class)->factor(2, 279)` → `5.0`; `factor(2, 278)` y `factor(1, 279)` → `1.0`.
4. `GeneradorEquipoRuta::especiesPonderadas(2, 1)` → petilil con peso 47.5 y el resto sin cambio (total 125).
5. Pool de exploración del hábitat 2 nivel 1 → petilil 5.0, resto 1.0; el tick no aplica el factor dos veces (la suma no se multiplica por 25).
6. Navbar: icono visible en todas las páginas, activo en `/avistamientos`, punto presente hasta abrir la página y ausente después de visitarla.
7. DST: `CalculadoraVentanaAvistamiento` para 2026-10-25 devuelve fin `2026-10-28 00:00+01:00` (73 h reales) y para 2026-03-29 fin `2026-04-01 00:00+02:00` (71 h).
8. Degradado: quitar la familia 279 del hábitat 2 en una BD de desarrollo → página con aviso «sin efecto», `factor(2,279)` = 1.0 y sin fila nueva; restaurar después.

## 12. Riesgos y decisiones abiertas

| Riesgo | Mitigación |
|---|---|
| **Árbol sucio** (`app.blade.php`, `GeneradorEquipoRuta.php`) | No tocar `app.blade.php`; en `GeneradorEquipoRuta` solo constructor y dos llamadas; revisar `git diff` antes/después; nunca `reset`/`checkout`. |
| **Doble aplicación del ×5** | Un único campo `factor` aplicado en `peso()`; `ponderado()` llama una vez; prohibido multiplicar en repos o vistas. Verificación numérica 47.5, no 237.5. |
| **Concurrencia y cambio de pool** | `unique(window_index)` + `insertOrIgnore` + relectura; no se actualiza la fila; el cambio de familia se muestra como «sin efecto». |
| **Familia de 1 especie / evento invisible** | Regla ≥2 especies en el hábitat; fallback ≥1; el factor es constante ajustable (×3) si el producto lo pide. |
| **Rendimiento y caché** | Lectura por índice único; `scoped` memoiza por request/job; escritura una vez cada 3 días; la página son 4 consultas y el punto del navbar no consulta BD. |
| **DST** | Ventanas de días naturales locales; `ends_at` absoluto con offset en la UI; se aceptan 71/73 h reales. En 2026, ventanas 29 (71 h) y 99 (73 h). |

**Decisiones abiertas (producto):**
- Confirmar ×5 frente a ×3 (una constante).
- Punto del navbar con `localStorage` frente a indicador permanente o sin punto.
- ¿Debe el preview de recompensas (`ExploracionActivaController::poolParaEstimador`) reflejar el evento? Hoy ya diverge por refugio; se mantiene fuera de alcance.
- Confirmar el ancla `2026-01-01` (hoy es una decisión de producto, no derivada de datos).
- ¿Se quiere histórico visible de ventanas pasadas? La tabla lo permite, pero la página actual solo muestra la vigente.

## 13. Borrador de ADR

- **Título:** Evento global rotativo de avistamientos con factor de aparición ×5.
- **Estado:** Propuesto.
- **Contexto:** No existe sistema de eventos; ruta pondera por `capture_rate/hatch` y exploración es uniforme por el override de refugio. El pool (`pokemon_habitat`) es editable por cualquier usuario autenticado y el árbol tiene cambios locales en dos archivos clave. Se necesita un evento de 3 días determinista, sin cron, coherente entre página y spawn.
- **Decisión:** Ventanas de 3 días naturales en `Europe/Madrid` ancladas a 2026-01-01; selección determinista con `crc32` sobre hábitats (excluido 49) y familias con ≥2 especies; persistencia en `avistamiento_eventos` con `unique(window_index)`; factor único ×5 aplicado una sola vez en `PokemonDelPool::peso()` mediante un campo `factor`; puerto `FactorAparicion` en `Shared` implementado en `Avistamientos`; integración solo en ruta y exploración; UI en `navbar-acciones` y página Blade de solo lectura.
- **Consecuencias:** Evento estable y auditable, sin job ni notificaciones; una fila y una consulta por ventana; factor ajustable en una constante. Coste: una migración, un write excepcional en la primera lectura, y un evento por ventana que solo afecta a 1 de 43 hábitats. La exploración sigue siendo uniforme (deuda consciente); el preview de recompensas no refleja el evento; un refugio exclusivo de otra familia anula el efecto para ese jugador.
