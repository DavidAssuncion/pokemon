# TASK-010 — Arquitectura

- **Estado:** `COMPLETADO` (diseño parcial, con limitaciones declaradas)
- **Responsable:** `architect`
- **Fecha:** 2026-11-04
- **Base:** `analysis.md` (A, B, D, E) y estado actual de TASK-009
- **Alcance:** nombres en seeders + backfill, sesión de 1 año, shiny consistente con `x-sprite`

> El arquitecto no modificó archivos ni ejecutó migraciones, seeders, builds o tests.
>
> **Actualización del desarrollador (2026-11-04):** se descarta el backfill/migración de nombres; solo se cambia el seeder. La generación de los 59 assets shiny queda fuera de v2 hasta que el desarrollador defina esa lista. El resto del diseño se mantiene.

## Decisión

1. **Nombres:** `Str::ucfirst()` en el punto único de construcción de `PokemonSeeder` (`database/seeders/PokemonSeeder.php:116`) + **migración de backfill** portable e idempotente para las 1.083 filas existentes. No usar `initcap`. No re-ejecutar el seeder como backfill. No tocar el CSV en esta tarea.
2. **Sesión:** `SESSION_LIFETIME=525600` (365 × 24 × 60 minutos) en `.env.example`, fallback de `config/session.php` y `docker-compose.yml` (app/worker/scheduler). Mantener `expire_on_close=false`. Sin migración ni «recordarme» nuevo.
3. **Shiny:** mantener `x-sprite` como único componente; propagar `es_shiny` en las cuatro capas de exploraciones y en `siguiente` de evolución; **generar 59 assets `{id}_s.webp`** remapeando los `{baseId}_f{formId}_s.webp` existentes. `905 incarnate` queda con fallback documentado. Los eventos de exploración y la generación de shiny salvaje en exploraciones quedan fuera de alcance.

## Contexto verificado

| Hecho | Evidencia |
|---|---|
| `PokemonSeeder` construye la fila en `:114-127` y hace `upsert` con `name` en `:188-198` | lectura directa |
| El seeder procesa 1.045 filas; la BD tiene 1.083; 38 formas Alola/Galar del CSV tienen nombre base y `regionOf()` las omite | `PokemonSeeder.php:104-111,238-247`, CSV |
| `config/session.php:37` usa `env('SESSION_LIFETIME', 120)`; `:39` `expire_on_close=false`; `config:show` confirma `lifetime=120` | `php artisan config:show session` |
| `x-sprite` resuelve normal/shiny y fallback shiny → normal → `0.webp` | `resources/views/components/sprite.blade.php:40-47,54-68` |
| `es_shiny` se pierde en 4 puntos de exploraciones | `TransformadorResultadoExploracion.php:76-80`, `PresentadorExploraciones.php:244-251`, `ResultadoExploracionVista.php:167-179`, `FinalizarExploracionHandler.php:697-704` |
| Exploraciones no generan shiny salvaje | `CalculadorRecompensas::calcularCapturas` (`:255-266`) crea `RecompensaCaptura` sin `esShiny`; `PokemonDerrotado` no tiene flag |
| 64 stems normales sin `_s`: 59 reales + 4 legacy + `0` | `comm` de assets |
| De los 59 reales, 51 tienen un único candidato `_f`; 8 son ambiguos; 905 no tiene fuente local | inventario de assets |
| TASK-009 creó `x-sprite` y migró las vistas; hay cambios sin commitear | `git status`, `resources/views/components/sprite.blade.php` |

## Contratos

### Exploraciones (capturados)

Shape persistido en `eventos['resultado']['capturados']`:

```text
list<array{pokemon_id:int, nombre:string, cantidad:int, es_shiny:bool}>
```

Cambios:

1. `TransformadorResultadoExploracion::capturados()` añade `'es_shiny' => $captura->esShiny` (`:76-80`) y actualiza PHPDoc (`:28-39,64-67`).
2. `PresentadorExploraciones::toTerminada()` conserva `'es_shiny' => (bool) ($capturado['es_shiny'] ?? false)` (`:244-251`).
3. `ResultadoExploracionVista::capturados()` conserva el flag y deja de construir `icono` normal; la vista usará `:id` + `:shiny` de `x-sprite`.
4. `FinalizarExploracionHandler::restarPerdidas()` preserva `esShiny: $c->esShiny` al recrear `RecompensaCaptura` (`:697-704`).
5. `resources/views/exploraciones/index.blade.php:307-312` usa `<x-sprite :id="$capturado['pokemon_id']" :shiny="$capturado['es_shiny'] ?? false" ...>`.

**No se crea un DTO nuevo.** El pipeline es array-based en la frontera de serialización/vista; añadir un DTO solo para un bool sería inconsistente. Se documenta como deuda migrar el bloque a DTO readonly en una tarea de saneamiento.

### Evolución

`ReclutadoVistaPresenter::toView()` expone en `siguiente`:

```text
array{pokemon_id:int, nombre:string, nombre_formateado:string, es_shiny:bool, imagen:string}
```

`resources/views/reclutado/show.blade.php:28` pasa `:shiny="$siguiente['es_shiny'] ?? false"`. El shiny es del individuo y sobrevive a la evolución: `ReclutadoController.php:241` solo actualiza `pokemon_id`.

### Eventos de exploración

Sin cambios. No hay fuente shiny en el dominio y ampliar el contrato obliga a tocar bitácora persistida y eventos antiguos. Se documenta como limitación. Si en el futuro se genera shiny salvaje, habrá que añadir `es_shiny` al evento de encuentro y a `EventoExploracionVista.php:60-79`.

### `x-sprite`

- Se mantiene el contrato actual: `id`, `src`, `fallback`, `name`, `shiny`, `suffix`, `size`, `loading`, `priority`, `decorative`, `wrapperClass`.
- `:src` explícito gana sobre `:shiny` (`sprite.blade.php:44-47`); los consumidores deben usar `:id` + `:shiny` cuando el individuo pueda ser shiny.
- Fallback shiny → normal → `0.webp` (`sprite.blade.php:54-68`).
- No se crea un componente paralelo.

### Nombres

- Seeder: `'name' => Str::ucfirst($row['name'])` con `Illuminate\Support\Str`.
- Migración de backfill:

```sql
UPDATE pokemon
SET name = UPPER(SUBSTR(name, 1, 1)) || SUBSTR(name, 2)
WHERE name <> UPPER(SUBSTR(name, 1, 1)) || SUBSTR(name, 2)
```

Portable PostgreSQL/SQLite, idempotente. `down()` no-op documentado: no se puede recuperar el casing original.

### Sesión

- `SESSION_LIFETIME=525600` en `.env.example` y fallback de `config/session.php`.
- `docker-compose.yml`: `SESSION_LIFETIME: ${SESSION_LIFETIME:-525600}` en `app`, `worker` y `scheduler`.
- Mantener `expire_on_close=false`.
- Verificación: `php artisan config:show session` → `lifetime=525600`.
- Si hay `bootstrap/cache/config.php`, ejecutar `config:clear`/`config:cache`.
- No se implementa «recordarme»; la duración es de inactividad deslizante, no absoluta desde el login.

## Archivos a crear/modificar por responsable

**Crear**
- `database/migrations/2026_11_04_000001_capitalize_pokemon_names.php` — `database`.
- 59 `public/images/iconos_webp/shiny/{id}_s.webp` — `frontend`/assets, con tabla de mapeo validada por `database`.
- Opcional `public/images/iconos_webp/shiny/0_s.webp` — `frontend`/assets.

**Modificar**
- `database/seeders/PokemonSeeder.php:116` — `database`.
- `config/session.php:37` — `backend`.
- `.env.example:31` — `backend`.
- `docker-compose.yml` — `backend`.
- `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php:76-80` — `backend`.
- `src/Exploraciones/Presentation/PresentadorExploraciones.php:244-251` — `backend`.
- `src/Exploraciones/Presentation/ResultadoExploracionVista.php:167-179` — `backend`.
- `src/Exploraciones/App/FinalizarExploracionHandler.php:697-704` — `backend`.
- `resources/views/exploraciones/index.blade.php:307-312` — `frontend`.
- `app/Support/ReclutadoVistaPresenter.php:67-74` — `backend`.
- `resources/views/reclutado/show.blade.php:28` — `frontend`.
- Opcional: `resources/views/livewire/partials/turn-bar.blade.php:7` (`size="48"` → `inline`) — `frontend`, regresión de TASK-009, **fuera del alcance shiny**.

## Orden de ejecución y dependencias

1. **Cerrar TASK-009**: commitear los cambios actuales en `refactor/ui-tokens`; no mezclar con TASK-010.
2. Crear rama `task-010` desde `refactor/ui-tokens` (recomendado) o worktree separado si se paraleliza. No trabajar sobre cambios sin commitear.
3. Nombres: migración de backfill + cambio de seeder (independientes; la migración cubre datos existentes).
4. Sesión: config + `.env.example` + compose.
5. Shiny código: `restarPerdidas` → transformador → presentador → resultado → Blade → evolución.
6. Assets: generar/validar 59 + `0_s.webp`.
7. Verificación estática y seguridad.
8. Revisión independiente solo si el desarrollador la solicita (preferencia registrada en TASK-009); seguridad sí es obligatoria por el cambio de sesión.

## Alternativas descartadas

1. **Solo re-ejecutar el seeder** para capitalizar: no cubre las 38 formas Alola/Galar que el seeder omite; no es fiable en despliegues existentes.
2. **`initcap`**: altera el resto de la cadena y no es portable.
3. **Mutador en `App\Models\Pokemon`**: no reescribe datos existentes y mezcla presentación en el modelo.
4. **Resolver el asset shiny alternativo en `x-sprite`/backend**: duplica la regla, exige `src` + `fallback` en cada consumidor y complica el fallback.
5. **DTO nuevo para capturas**: inconsistente con el pipeline actual; coste alto para un bool.
6. **Shiny en diario de exploración**: requiere cambiar el dominio de eventos y la fuente no existe.
7. **Sesión con «recordarme» real**: mejor seguridad, pero no es la petición literal y añade UI/código.
8. **Worktree separado**: válido si hay paralelismo, pero TASK-009 ya está cerrado y toca las mismas vistas; rama secuencial es más simple.

## Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Migración de nombres en BD equivocada | Medio | Ejecutar `migrate` en cada entorno; verificar `db:show` antes/después |
| `down()` no restaura casing | Bajo | Documentar no-op; backup previo |
| Consumidores case-sensitive de `name` | Medio | Grep final; `PreferenciasShowdown` normaliza |
| Las 38 formas Alola/Galar siguen sin seed reproducible | Medio | Tarea de seguimiento para corregir CSV/seeder |
| Exploraciones nunca generan shiny salvaje | Alto (producto) | Decidir si se implementa roll shiny; fuera de alcance |
| 905 incarnate sin shiny local | Bajo | Fallback documentado o asset externo autorizado |
| Mapeo erróneo de los 8 ambiguos | Alto | Validar con `pokemon_forms.csv` e inspección visual |
| Sesión de 1 año robada | Alto | HTTPS + `secure=true`, `HttpOnly`, `SameSite=Lax`, logout, limpieza de `sessions` |
| `config:cache` con valor antiguo | Medio | `config:clear`/`config:cache`; verificar con `config:show` |
| Regresión `size="48"` de TASK-009 | Bajo | Cambiar a `inline` o añadir token 48 |
| Cambios sin commitear de TASK-009 | Alto | Commit antes de crear `task-010` |

## Verificación y criterios de aceptación

**Análisis estático**
- `vendor/bin/pint --dirty --format agent`.
- `vendor/bin/phpstan analyse --no-progress` (según `phpstan.neon`).
- `vendor/bin/phpcs` (según `phpcs.xml`), si aplica.
- `php artisan config:show session` → `lifetime=525600`.
- `php artisan migrate:status` → migración aplicada.
- Consulta read-only: `SELECT count(*) FROM pokemon WHERE name <> UPPER(SUBSTR(name,1,1)) || SUBSTR(name,2)` → `0`.
- `grep -rn "es_shiny" src/Exploraciones app/Support` para confirmar propagación.
- `grep -rn "iconos_webp/shiny" resources/views` para confirmar que no quedan URLs shiny mal formadas.
- Verificar que cada `{id}_s.webp` de los 59 existe y pesa > 0.

**Criterios de aceptación**
- [ ] Los 1.083 nombres empiezan por mayúscula; `mr-mime` → `Mr-mime` (primera letra, resto igual).
- [ ] `PokemonSeeder` capitaliza en el punto único de construcción.
- [ ] Migración idempotente y portable; re-ejecutarla no cambia nada.
- [ ] `SESSION_LIFETIME=525600` efectivo en local y Docker.
- [ ] `x-sprite` sigue siendo el único componente de sprite.
- [ ] Capturados de exploración propuestos con `es_shiny` en las cuatro capas; sin pérdida en `restarPerdidas`.
- [ ] `siguiente` de evolución expone `es_shiny` y la vista lo usa.
- [ ] Los 59 assets shiny existen o hay fallback documentado para 905.
- [ ] Sin cambios en tests ni dependencias.

**Riesgos no cubiertos**
- Serialización de `/api/huevos` y `PokemonResumen` no verificadas.
- Lista exacta de consumidores case-sensitive fuera de los patrones buscados.
- Confirmación de los 8 mapeos ambiguos (requiere inspección visual).
- Comportamiento de `sessions` a largo plazo (crecimiento/GC).

## Preguntas abiertas / bloqueos

1. ¿Se acepta que las capturas de exploración nunca serán shiny? Si el requisito implica que sí, hace falta una tarea de dominio (roll shiny en generación de salvajes + flag en `PokemonDerrotado` + eventos).
2. ¿Se autoriza copiar 59 assets desde `{baseId}_f{formId}_s.webp`? ¿Y obtener 905 de una fuente externa?
3. ¿Se corrige el CSV/seeder para las 38 formas Alola/Galar en esta tarea o en seguimiento?
4. ¿Producción usa HTTPS? Para fijar `SESSION_SECURE_COOKIE=true`.
5. ¿La sesión de 1 año es de inactividad (comportamiento real de `lifetime`) o máxima desde login?
6. ¿Se acepta commitear TASK-009 antes de `task-010`?
