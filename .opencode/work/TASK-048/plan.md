# TASK-048 — Plan

- **ID:** TASK-048
- **Versión:** v1
- **Estado:** PROPUESTO — no autoriza implementación hasta aprobación explícita
- **Fecha:** 2026-11-16
- **Base:** `.opencode/work/TASK-048/analysis.md` y `.opencode/work/TASK-048/architecture.md`
- **Entregable:** icono de prismáticos en la navbar, página `/avistamientos` y evento global de 3 días con factor ×5 de aparición para una familia en un hábitat.

## 1. Objetivo

Añadir al juego un evento de mundo rotativo y visible:

1. Cada **3 días naturales** (`Europe/Madrid`) se elige de forma determinista **un hábitat** y **una familia evolutiva** de ese hábitat.
2. Durante la ventana, esa familia aparece **×5** en los spawns salvajes de **ruta** y **exploración** de ese hábitat.
3. La navbar muestra un icono `fa-binoculars` que lleva a `/avistamientos`, donde se ve el evento activo, las especies afectadas, su nivel, si el jugador las ha visto y el tiempo restante.
4. La selección se persiste por ventana para que un cambio del pool no altere el evento a mitad de ventana.

## 2. Alcance

### Incluido

- Migración `avistamiento_eventos` con `unique(window_index)`.
- Módulo `src/Avistamientos/` (Domain, App, Infra) y puerto `FactorAparicion` en `Shared`.
- Cálculo de ventana de 3 días y selección determinista con `crc32`.
- Página Blade `/avistamientos`, controlador, ruta y bindings.
- Icono de prismáticos en `navbar-acciones.blade.php`, con estado activo y punto de «ventana no vista».
- Factor ×5 aplicado una sola vez en `PokemonDelPool::peso()`, integrado en ruta y exploración.
- ADR `docs/adr/0004-avistamientos-evento-3-dias.md`.

### Fuera de alcance

- Cron, jobs programados, notificaciones push o correo.
- Eventos por usuario; el evento es **global**.
- Entrenadores, gimnasios, mazmorras y equipos curados.
- Cambiar la rareza base de la exploración (su pool sigue siendo uniforme salvo refugio).
- Reflejar el evento en el preview/estimador de recompensas de exploración.
- Recompensas, moneda, misiones, facciones o capture rate.
- Histórico visible de ventanas pasadas en la UI (la tabla lo permite, pero no se muestra).
- Tests. No se crean, modifican ni ejecutan.

## 3. Comportamiento de producto

### 3.1 Página `/avistamientos`

- Tarjeta «Avistamiento activo»:
  - Imagen 3:2 del hábitat, nombre y enlace a `/habitats/{id}`.
  - Nombre visible de la familia (miembro con menor `species_id`).
  - Badge «×5» y texto «Aparición aumentada».
  - Especies afectadas agrupadas por nivel 1/2/3, con `x-sprite`, nombre, badge de nivel y estado **Visto / No visto**.
  - `<time datetime>` con la fecha de fin y texto restante calculado en backend («Termina en 1 d 12 h»).
  - Enlace «Ver hábitat».
- Si la familia ya no está en el hábitat: aviso «Este avistamiento ya no tiene efecto en el hábitat» y se mantienen hábitat/familia/fechas originales.
- Si no hay candidatos: estado vacío «No hay ningún avistamiento activo».
- Solo lectura; sin botones de acción.

### 3.2 Navbar

- `<a>` en `resources/views/layouts/partials/navbar-acciones.blade.php` con `fa-solid fa-binoculars`, `aria-label="Avistamientos"`, `title="Avistamientos"` y `aria-current="page"` cuando la ruta está activa.
- Punto pequeño cuando la ventana actual no coincide con la última abierta, guardada en `localStorage`. Es cosmético y no bloquea nada si no hay JS.
- No se añade un séptimo ítem al array `$navItems` de `app.blade.php`.

### 3.3 Regla del evento

- **Ancla:** `2026-01-01 00:00:00` en `Europe/Madrid`.
- **Ventana:** 3 días naturales locales; `indice = floor(diasEntre(ancla, hoy) / 3)`.
- **Hábitats candidatos:** los 44 con pool menos el 49 «Legendarios»; se excluyen los 12 vacíos por la propia consulta.
- **Familias candidatas:** `evolution_chain_id` no nulo, con ≥2 especies en el hábitat; fallback a ≥1 si el pool editado no deja ninguna de ≥2.
- **Selección:** `crc32` con sal versionada `avistamientos:v1:...` sobre listas ordenadas por id. `crc32` se normaliza con `sprintf('%u', ...)`.
- **Persistencia:** `insertOrIgnore` por `window_index`; la fila no se actualiza. Si dos requests compiten, gana la primera y la segunda relee.
- **Caducidad:** no hay job; al cambiar la ventana, el factor deja de coincidir y vuelve a 1.0.

### 3.4 Factor de aparición

- `PokemonDelPool` gana `public readonly float $factor = 1.0`.
- `peso()`: `base = pesoOverride ?? (captureRate / max(1, hatch))`; `peso = max(0, base * factor)`.
- En ruta, `base` es `capture_rate/hatch` y el ×5 conserva la rareza relativa.
- En exploración, `base` es el factor de refugio (1.0 por defecto); el ×5 multiplica ese peso.
- Se aplica exactamente una vez, al construir el pool; `PoolHabitat::ponderado()` no vuelve a multiplicar.
- Entrenadores, gimnasios y mazmorras no inyectan el factor y quedan en 1.0.

## 4. Arquitectura y contratos

Se sigue el diseño de `.opencode/work/TASK-048/architecture.md`. Resumen de contratos:

```php
interface FactorAparicion
{
    public function factor(int $habitatId, ?int $evolutionChainId): float;
}
```

- `Src\Avistamientos\Domain\ReglasAvistamiento`: ancla, duración, factor y exclusiones.
- `CalculadoraVentanaAvistamiento`: `actual(DateTimeImmutable): VentanaAvistamiento`, `en(DateTimeImmutable): VentanaAvistamiento`.
- `SelectorAvistamiento`: elige hábitat y familia con `crc32` sobre colecciones ordenadas.
- `AvistamientoRepositorioInterface`: lectura/escritura de la ventana, candidatos, especies y vistos.
- `ObtenerSeleccionVigente`: devuelve `?SeleccionVigente`; crea la fila si no existe.
- `ObtenerAvistamientoPagina`: compone el DTO de página; no calcula en Blade.
- `FactorAparicionAvistamientos`: implementa el puerto; devuelve 5.0 solo si coinciden hábitat y cadena; memoiza con `scoped`.

**DTO de página** (contrato para el frontend):

```text
AvistamientoPagina
  bool activo
  ?int indice
  ?int habitatId
  ?string habitatNombre
  ?string familiaNombre
  ?int evolutionChainId
  float factor
  ?string terminaEn        // ISO-8601 con offset
  ?string restanteTexto    // ya formateado
  bool sinEfecto
  NivelesAvistamientoCollection niveles

NivelAvistamiento
  int nivel
  EspeciesAvistadasCollection especies

EspecieAvistada
  int pokemonId
  string nombre
  bool visto
```

## 5. Archivos y responsables

### 5.1 Crear

| Archivo | Responsable |
|---|---|
| `database/migrations/2026_11_16_000001_create_avistamiento_eventos_table.php` | database |
| `src/Shared/Domain/Ports/FactorAparicion.php` | backend |
| `src/Avistamientos/Domain/**` (reglas, VOs, DTOs, servicios, repositorio) | backend |
| `src/Avistamientos/App/**` (casos de uso) | backend |
| `src/Avistamientos/Infra/Models/AvistamientoEvento.php` | backend |
| `src/Avistamientos/Infra/EloquentAvistamientoRepositorio.php` | backend |
| `src/Avistamientos/Infra/FactorAparicionAvistamientos.php` | backend |
| `src/Avistamientos/Infra/Controllers/AvistamientosController.php` | backend |
| `routes/avistamientos.php` | backend |
| `resources/views/avistamientos/index.blade.php` | frontend |
| `docs/adr/0004-avistamientos-evento-3-dias.md` | documenter |

### 5.2 Modificar

| Archivo | Responsable | Precaución |
|---|---|---|
| `routes/web.php` | backend | añadir `require __DIR__.'/avistamientos.php';` dentro de `auth` |
| `app/Providers/AppServiceProvider.php` | backend | bindings + composer ligero del navbar |
| `src/Exploraciones/Domain/ValueObjects/PokemonDelPool.php` | backend | propiedad `factor` y `peso()` |
| `src/Exploraciones/App/Procesamiento/CargadorPoolHabitat.php` | backend | factor por familia |
| `src/Exploraciones/App/ProcesarExploracionHandler.php` | backend | pasar el puerto al cargador |
| `src/CombateRuta/App/GeneradorEquipoRuta.php` | backend | **tiene cambios locales**; solo constructor y llamadas; no revertir `especiesPonderadas` |
| `resources/views/layouts/partials/navbar-acciones.blade.php` | frontend | archivo **no trackeado**; no borrar ni reemplazar su contenido |

### 5.3 No se toca

- `resources/views/layouts/app.blade.php` (tiene cambios locales; el `@include` ya existe).
- `resources/views/misiones/` ni ningún artefacto de TASK-043/044/046/047.
- Tests, seeders, CSV de datos, `.env`.

## 6. Orden de ejecución

1. **database** — migración y modelo Eloquent (schema fijo).
2. **backend A** — puerto `FactorAparicion`, módulo `Avistamientos` (Domain/App/Infra), página, controlador, ruta y bindings.
3. **backend B** — integración en `PokemonDelPool`, `CargadorPoolHabitat`, `ProcesarExploracionHandler` y `GeneradorEquipoRuta`.
4. **frontend** — navbar y `resources/views/avistamientos/index.blade.php`.
5. **documenter** — ADR.
6. **reviewer** — revisión independiente del diff y de los criterios.
7. **orchestrator** — informe final.

Los pasos 2 y 3 no se paralelizan porque 3 depende del puerto y del binding de 2. El paso 4 depende de la ruta y del DTO de 2.

## 7. Criterios de aceptación

1. `GET /avistamientos` responde 200 para un usuario autenticado y muestra el evento activo con hábitat, familia, ×5, especies por nivel, visto/no visto, fin y restante.
2. Un usuario no autenticado no accede (redirección a login).
3. La misma ventana devuelve el mismo hábitat y la misma familia para todos los usuarios; la ventana cambia cada 3 días naturales a medianoche `Europe/Madrid`.
4. El hábitat 49 «Legendarios» y los hábitats sin pool nunca se seleccionan; la familia elegida tiene al menos 2 especies en el hábitat (o 1 en el fallback documentado).
5. Solo existe una fila por `window_index`; repetir la visita o dos requests simultáneos no duplican.
6. `FactorAparicion::factor()` devuelve 5.0 para el par hábitat/familia de la ventana y 1.0 para cualquier otro par.
7. En ruta, la especie de la familia elegida multiplica su peso por 5 y el resto no cambia; la suma de pesos es la esperada, sin doble multiplicación.
8. En exploración, el peso de la familia elegida se multiplica por 5 sobre el peso de refugio; el resto no cambia.
9. Entrenadores, gimnasios y mazmorras no se ven afectados.
10. La navbar muestra `fa-binoculars`, enlaza a `/avistamientos`, marca el estado activo y no añade un ítem nuevo a `$navItems`.
11. El punto de «ventana no vista» aparece y desaparece al abrir la página; sin JS, el icono sigue funcionando.
12. La página es responsive y accesible: un `h1`, estados por texto, `aria-label` en el icono y foco visible.
13. `php -l` no reporta errores en los PHP nuevos/modificados.
14. `vendor/bin/pint --dirty --format agent` no deja cambios pendientes.
15. `php artisan route:list --path=avistamientos --except-vendor` muestra la ruta con nombre `avistamientos.index`.
16. No se ha creado, modificado ni ejecutado ningún test.

## 8. Verificación

### 8.1 Estática

```bash
php -l <cada PHP nuevo o modificado>
vendor/bin/pint --dirty --format agent
php artisan route:list --path=avistamientos --except-vendor
```

### 8.2 Manual

- Abrir `/avistamientos` con un usuario de desarrollo y comprobar la tarjeta, las especies y el tiempo restante.
- Consultar `avistamiento_eventos` por `window_index` y confirmar una sola fila.
- Usar `php artisan tinker --execute` (solo lectura) para comprobar `FactorAparicion` en el par elegido y en un par ajeno.
- Comprobar el pool de ruta y el de exploración del hábitat elegido; verificar que el peso se multiplica una sola vez.
- Navegar a un hábitat, a un entrenador y a un gimnasio para confirmar que no cambian.
- Comprobar la navbar en escritorio y móvil, el estado activo y el punto.
- Comprobar el cambio de ventana simulando fechas con el `Reloj` o `Carbon::setTestNow` en tinker (sin escribir datos).

**Nota:** el ejemplo del arquitectura (ventana 106 → Bosque Brumoso / petilil) es ilustrativo y depende de que la BD coincida con `storage/data/pokemon_habitat.csv`. La verificación comprobará la coherencia y el determinismo, no una especie concreta.

### 8.3 Sin tests

No se crean, actualizan ni ejecutan tests. La suite queda fuera de alcance.

## 9. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| **Árbol sucio** | `app.blade.php` no se toca; en `GeneradorEquipoRuta.php` solo se añade el constructor y dos llamadas; revisar `git diff` antes y después; nunca `reset`, `checkout`, `clean` ni `push`. |
| **Doble aplicación del ×5** | Un único campo `factor` en `PokemonDelPool`; `peso()` es el único punto de multiplicación; verificar numéricamente que el peso es 47.5 y no 237.5 en el ejemplo. |
| **Concurrencia** | `unique(window_index)` + `insertOrIgnore` + relectura; la fila no se actualiza. |
| **Cambio del pool a mitad de ventana** | La selección persistida no se recalcula; si la familia desaparece, la página avisa «sin efecto» y el factor vuelve a 1.0. |
| **Familia de 1 especie / evento invisible** | Regla ≥2 especies; fallback ≥1; factor ajustable en una constante. |
| **Rendimiento** | Una fila por ventana; `scoped` memoiza por request/job; la página son 4 consultas; el navbar no consulta BD. |
| **DST** | Ventanas de días naturales locales; `ends_at` absoluto con offset; se aceptan 71/73 h reales. |
| **Modelo/DB desincronizados** | La migración y el modelo se escriben en el mismo paso; `php artisan migrate` solo en desarrollo y con aprobación del desarrollador. |

## 10. Rollback

- Revertir los archivos nuevos y modificados de esta tarea, respetando los cambios locales preexistentes.
- La migración se revierte con `php artisan migrate:rollback --path=database/migrations/2026_11_16_000001_create_avistamiento_eventos_table.php` solo si el desarrollador lo autoriza.
- No usar `git reset --hard`, `git checkout --`, `git clean` ni `git push`.

## 11. Aprobación

Este plan **no autoriza implementación**. Se requiere una respuesta explícita que mencione la versión:

- `Aprobar plan v1`
- `Solicitar cambios`
- `Cancelar tarea`
