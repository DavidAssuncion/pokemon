# TASK-024 — Arquitectura

- **Estado:** COMPLETADO
- **Autor:** `architect` (`ses_e4e2f725affeuvY06H5cmYFYyP`)
- **Base:** `.opencode/work/TASK-024/analysis.md`, `dd83ca9`.
- **Decisión:** descomposición en olas, sin cambios de comportamiento, DOM, contrato Livewire ni sesión.

## 1. Principios

- **Copia literal** en las extracciones Blade: mismos ids, clases, `wire:*`, `x-*`, `@if`, `@foreach` y estructura DOM.
- **JS de combate como script clásico** en un parcial Blade incluido desde `combate_page` dentro de `@push('scripts')`. No módulo Vite.
- **Livewire:** colaboradores sin estado en `App\Livewire\Combate\Soporte`; propiedades públicas y métodos `wire` intactos; estado y sesión no se mueven.
- **No** se crean componentes compartidos de modal ni se unifica el modal de exploración (contratos divergentes, sobreabstracción).
- **No** se toca `SESSION_VERSION = 21`, `BattleSessionService`, rutas, controladores, endpoints ni `render()`.

## 2. Olas

| Ola | Trabajo | Archivos | Riesgo |
|---|---|---|---|
| 1A | Parciales de `equipos/index` | `resources/views/equipos/partials/*` | Bajo |
| 1B | Parciales de `habitats/show` | `resources/views/habitats/partials/*` | Bajo |
| 2 | JS de combate a parcial clásico | `resources/views/livewire/partials/combate-scripts.blade.php` | Bajo/medio |
| 3A | `GrabadorEventosBatalla` + snapshots | `app/Livewire/Combate/Soporte/*` | Alto |
| 3B | `SincronizadorVistaBatalla` + `VistaBatalla` | idem | Alto |
| 3C | `ColaTurnosBatalla` | idem | Medio |
| 3D | `EstadoAnimacionBatalla` + `AnimacionBatalla` | idem | Medio |
| 3E | `ResolutorAccionBatalla` | idem | Alto — **condicionada a aprobación separada** |
| 4 | Componentes compartidos | — | **Rechazada** |

## 3. Ola 1 — Parciales de vistas

### 1A `equipos/index` (3.284 líneas)

| Parcial | Rango origen |
|---|---:|
| `panel-equipos` | 18-244 |
| `panel-pokemon` | 274-469 |
| `panel-huevos` | 471-602 |
| `modal-exploracion` | 610-756 |
| `modal-eliminar-equipo` | 758-790 |
| `modal-reemplazo` | 792-846 |
| `modal-detalle-pokemon` | 848-1179 |

`panel-tabs` (249-272) se mantiene inline: valor marginal y evita un parcial de 24 líneas.

### 1B `habitats/show` (3.007 líneas)

| Parcial | Rango origen |
|---|---:|
| `columna-izquierda` | 9-127 |
| `columna-derecha` | 129-475 |
| `modal-exploracion` | 478-670 |
| `modal-gestion-favoritos` | 672-740 |
| `modal-formacion` | 742-803 |
| `modal-gestion-admin` | 805-983 |
| `modal-mazmorra` | 985-1124 |
| `modal-granja` | 1126-1414 |
| `modal-baya` | 1416-1518 |

### Reglas

- Extraer desde `git show dd83ca9:` para conservar los rangos originales.
- Los `@include` se colocan exactamente donde estaba el bloque.
- No se extraen `@push('scripts')`, datos `@json`, `@php` de presentación, raíces `x-data` ni cabeceras.
- No se crean `@push` en parciales.

### Criterios de aceptación

1. `diff` vacío entre el rango original y cada parcial.
2. `php artisan view:cache` correcto; después `view:clear`.
3. Conjunto de `id="..."` del original = conjunto agregado de parciales.
4. Sin cambios en controladores, rutas, JS, Livewire ni sesión.
5. Sin lógica nueva en parciales.
6. Revisión independiente APPROVED.

## 4. Ola 2 — JS de combate

- Nuevo `resources/views/livewire/partials/combate-scripts.blade.php` con las líneas 11-614 de `combate_page`.
- Inclusión única desde `combate_page`:

```blade
@push('scripts')
    @vite(['resources/js/combate.js'])
    @include('livewire.partials.combate-scripts')
@endpush
```

- Se conservan funciones globales, `const VITALES`/`ESTADOS`, las 22 funciones y el listener `shown.bs.modal`.
- **No** se incluye desde `livewire/combate.blade.php` (riesgo de doble inclusión y `const` duplicada).
- El `x-init` de `livewire/combate.blade.php` queda intacto.

### Criterios de aceptación

1. `diff` vacío entre líneas 11-614 originales y el parcial.
2. 22 declaraciones `function` y 1 `shown.bs.modal`.
3. `combate_page` conserva `@push`, `@vite` y `@include` en ese orden.
4. `livewire/combate.blade.php` sin cambios.
5. `view:cache` correcto.
6. Revisión manual: ataque, IA, daño/curación, estado, barrera, KO, ronda, log, modal de victoria.

## 5. Ola 3 — `Livewire/Combate.php`

### Estructura

`app/Livewire/Combate/Soporte/`:

- `DTO/SnapshotCombatiente` (VO inmutable).
- `DTO/SnapshotBatalla` (mapa tipado).
- `DTO/VistaBatalla` (proyección).
- `DTO/AnimacionBatalla` (VO inmutable).
- `DTO/ResultadoAccionBatalla`.
- `GrabadorEventosBatalla`.
- `SincronizadorVistaBatalla`.
- `ColaTurnosBatalla`.
- `EstadoAnimacionBatalla`.
- `ResolutorAccionBatalla` (3E, condicionada).

### Contratos clave

- `GrabadorEventosBatalla`: `capturar`, `registrar`, `eventoFin`, `eventoBatallaTerminada`, `cambiosDeEstadoEnLog`.
- `SincronizadorVistaBatalla::proyectar(battle, actor, actorParaObjetos, usuarioId): VistaBatalla`.
- `ColaTurnosBatalla::construir(battle, actorActualRefId, team1, team2): array`.
- `EstadoAnimacionBatalla`: `paraMovimiento`, `paraObjeto`, `reiniciada`.
- `ResolutorAccionBatalla`: `resolverMovimiento`, `resolverObjeto`, `defenderTeam`, `aplicarCambiosEstadistica`.

### Reglas

- Propiedades públicas y métodos `wire` intactos.
- Colaboradores privados resueltos en `boot()` con `??=` e inyección por constructor.
- `$eventos`, `$eventoTick` y `$grabandoEventos` siguen en el componente; la secuencia se calcula ahí.
- `syncViewData` sigue siendo el único punto que consume el log del agregado.
- No se toca `render()` ni el layout.
- Sin interfaces nuevas: no hay segunda implementación.
- DTOs efímeros; no se guardan en sesión.

## 6. Ola 4 — rechazada

- Shell de modal: 11 variantes con scrim, tamaños y contratos Alpine distintos.
- Modal de exploración: contratos divergentes entre Equipos y Hábitats.
- `onSpriteError`/`riesgoClass`: posible extracción JS futura, no componente Blade.

## 7. Orden, dependencias y rollback

- 1A y 1B son disjuntos y pueden ir en paralelo.
- Ola 2 es disjunta de 1A/1B.
- Ola 3 va después de Ola 2 para congelar el contrato JS.
- Cada ola con commit local atómico; rollback con `git revert`, sin operaciones destructivas.
- Sin migraciones ni datos que revertir.

## 8. Verificación global

- `php artisan view:cache` / `view:clear`.
- `composer analyse`, `composer arch`, `vendor/bin/pint --dirty --format agent`, `php -l`.
- `git diff --check`, contrato público de Livewire, ids de vistas.
- Revisión manual en navegador de `/combate`, `/equipos` y `/habitats/{id}`.
- Sin tests.
