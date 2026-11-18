# TASK-053 — Arquitectura

- **Autor:** `architect` (subagente independiente).
- **Fecha:** 2026-11-17.
- **Alcance:** solo diseño; sin cambios de código.
- **Nota de coordinación:** el resultado se reproduce íntegro; no se alteran sus conclusiones.

---

# TASK-053 — Diseño del nombre canónico de líder

## 0. Corrección de premisa (bloqueante para el diseño, no para el encargo)

El encargo afirma: *«No hay tabla de gimnasios ni de líderes»*. **Es falso en el árbol actual:**

| Evidencia | Qué demuestra |
|---|---|
| `database/migrations/2026_09_05_211421_create_gyms_table.php:19-25` | existe tabla `gyms` (`slug`, `medalla`, `tipo`, `nivel_minimo`) |
| `database/migrations/2026_09_05_211423_create_gym_stages_table.php:22-30` | existe tabla `gym_stages` (`gym_id`, `etapa`, `vanguardia`, `retaguardia`) |
| `app/Providers/AppServiceProvider.php:57` | el contenedor enlaza `GymCatalogoRepositoryInterface` → `EloquentGymCatalogoRepository` |
| `src/Gimnasios/Infra/EloquentGymCatalogoRepository.php:134-153` | **la fuente en runtime es la BD**: `desdeModelo()` construye `Gimnasio` desde filas |
| `src/Gimnasios/Domain/Repositories/GymCatalogoRepositoryInterface.php:12-16` | el docblock dice literalmente «Sustituye a `CatalogoGimnasios` como fuente para los casos de uso» |
| `database/seeders/GymSeeder.php:19-29` | `CatalogoGimnasios` solo se usa para **sembrar** la BD |

**Consecuencia directa:** un parámetro obligatorio `nombreLider` en `Gimnasio` no puede alimentarse solo desde `CatalogoGimnasios`; el adaptador Eloquent (`desdeModelo`) **debe** aportarlo también. Cualquier diseño que ignore esto rompe en runtime con `ArgumentCountError` o, peor, con un `''` silencioso.

No devuelvo `BLOQUEADO` porque el diseño puede cerrarse con esta corrección; queda registrada como decisión a confirmar con el desarrollador (§9, P1).

---

## 1. Decisión de diseño

### D1 — El nombre canónico vive en un catálogo de dominio por `slug`, no en BD

Nuevo `src/Gimnasios/Domain/CatalogoLideres.php`: `final class`, `private const NOMBRES` (`array<string,string>`), `nombreDe(string $slug): string` con fallback `'Líder'`.

**Por qué no BD:** el desarrollador decidió «canónico» (TASK-053 `request.md:23`), es decir, **no editable**. Meterlo en `gyms` obliga a migración + `$fillable` + `desdeModelo` + `insertar` + formulario admin (`resources/views/gimnasios/admin.blade.php:138-155`), y expone el dato a que el CRUD admin lo sobrescriba. Coste alto, riesgo de datos en producción, cero beneficio.

**Por qué no 18 literales dentro de `CatalogoGimnasios`:** el adaptador Eloquent también necesita el valor; sin un mapa común habría dos fuentes (catálogo de código y adaptador) y podrían divergir. El mapa es la única fuente; `CatalogoGimnasios` y el repositorio lo leen.

**Por qué no un DTO aparte:** un DTO transporta datos entre capas; aquí no hay transporte, hay un dato canónico que la entidad ya posee. Sería una abstracción sin función.

### D2 — `Gimnasio` expone `public readonly string $nombreLider` (parámetro obligatorio)

La convención del proyecto para entidades es explícita: «propiedades `public`» (`/.ai/rules/ddd-modules.md:10`), igual que `medalla`, `tipo` y `nivelMinimo`. La entidad sigue siendo un contenedor de datos puro y sin dependencias ocultas.

**Alternativa descartada:** que `Gimnasio::nombreLider()` delegue en `CatalogoLideres` de forma estática. Sería el cambio más pequeño (0 archivos fuera de `Domain`), pero introduce una dependencia estática oculta en la entidad, la hace no testeable de forma aislada y rompe la consistencia con el resto de propiedades. No se descarta por elegancia: se descarta porque el repositorio tendría que seguir leyendo el mapa de todos modos si el dato se persiste o se expone en admin, y entonces tendríamos dos caminos de acceso al mismo dato.

### D3 — `nombreEtapa(4)` devuelve el nombre del líder (opción **a**)

| Opción | Veredicto |
|---|---|
| (a) etapa 4 → `$this->nombreLider` | **Elegida.** Un único campo alimenta vista (`etapa.nombre`) y combate (`nombreRival`). Sin ramas en Blade ni en JS. |
| (b) `nombreEtapa(4)` sigue en `'Líder'` + campo nuevo en el DTO | Rechazada: obliga a la vista a ramificar `etapa === 4 ? nombreLider : nombre`, duplica la etiqueta y deja dos verdades para el mismo concepto. |
| (c) `'Líder · Brock'` | Rechazada: mete formato de presentación en el dominio y contamina `nombreRival` del combate (`IniciarCombateGimnasio.php:97`), que debe ser el nombre del rival, no una etiqueta compuesta. |

### D4 — `EtapaGimnasio` **no cambia de forma**

Se mantiene `{etapa: int, nombre: string}`. Se rechaza añadir `esLider: bool`: la etapa 4 ya es identificable por `etapa === 4` (contrato documentado en `docs/gimnasios.md:113`), y `frontend-minimal-logic.md` prohíbe replicar reglas, no leer un identificador. Se rechaza añadir `nombreLider`: duplicaría `nombre` en la misma fila.

### D5 — `DetalleGimnasio` **no cambia**

La cabecera (`show.blade.php:44-47`) pinta `gym.tipo_medalla` y `gym.tipo_nombre`; el modal de formación (`show.blade.php:156`) pinta `gym.tipo_medalla`. **Ninguno consume el nombre del líder.** El único canal es `etapas[].nombre`. Añadir `nombreLider` al detalle sería un segundo camino para el mismo dato y ampliaría el contrato JSON sin consumidor.

### D6 — El sprite del líder **no es dominio**

Se resuelve en presentación por `gym.slug` (convención `public/images/trainers/…` o manifiesto en `src/Shared/UI/`, precedente `TipoBadges.php`). Razones: el dominio no conoce rutas de assets (`ddd-modules.md:8`), hoy **no existe ningún sprite de líder** (`public/images/` solo tiene `misc/trainer.webp`; no hay `public/images/trainers/`), y un `spriteLider` en la entidad sería un campo muerto hasta que TASK-053 cree los assets. La clave es `slug`, ya presente en el DTO: cero cambios de contrato.

### D7 — Sin Value Object y sin guard en runtime

Un VO para un `string` de presentación no aporta comportamiento ni invariantes propias: viola «no introducir abstracciones sin necesidad demostrable». La validación es **estructural**: el único escritor es `private const NOMBRES` y el fallback `'Líder'`. Se documenta el invariante con `@param non-empty-string $nombreLider` para análisis estático, sin coste en runtime. Si en el futuro el dato pasa a BD, el guard (y la validación en `AdminGymController`) se añaden entonces, no antes.

---

## 2. Contrato de dominio y DTOs

### 2.1 `Gimnasio` (modificado)

```php
final class Gimnasio
{
    public function __construct(
        public readonly string $slug,
        public readonly string $medalla,
        public readonly TipoPokemon $tipo,
        public readonly int $nivelMinimo,
        public readonly string $nombreLider,   // NUEVO · @param non-empty-string
        public readonly EquipoEtapaGimnasioCollection $equipos,
    ) {}

    public function equipoEtapa(int $etapa): ?EquipoEtapaGimnasio; // sin cambios

    public function nombreEtapa(int $etapa): string;
    // 1 => 'Entrenador 1' | 2 => 'Entrenador 2' | 3 => 'Entrenador 3'
    // 4 => $this->nombreLider        <-- único cambio
    // default => 'Desconocido'
}
```

**Compatibilidad de llamadas:** las dos únicas construcciones usan argumentos con nombre — `CatalogoGimnasios.php:27-38` (×18) y `EloquentGymCatalogoRepository.php:146-152`. Insertar `nombreLider` entre `nivelMinimo` y `equipos` **no rompe ninguna llamada posicional** (no existe ninguna: `grep "new Gimnasio("` solo devuelve esas 19).

### 2.2 `CatalogoLideres` (nuevo)

```php
namespace Src\Gimnasios\Domain;

final class CatalogoLideres
{
    /** @var array<string, non-empty-string> */
    private const NOMBRES = [
        'bug' => '…', 'poison' => '…', /* … 18 entradas … */
    ];

    public const POR_DEFECTO = 'Líder';

    /** Fallback para gimnasios creados por admin con slug fuera del catálogo canónico. */
    public static function nombreDe(string $slug): string;
    // return self::NOMBRES[$slug] ?? self::POR_DEFECTO;
}
```

Semántica: **clave = `slug` de gimnasio** (nunca el nombre); **valor = nombre visible del líder**. `POR_DEFECTO` preserva el comportamiento actual para slugs no canónicos.

### 2.3 `EtapaGimnasio` (sin cambios de forma)

| Campo | Tipo | Semántica (precisada) |
|---|---|---|
| `etapa` | `int` | 1-4, orden de progresión |
| `nombre` | `string` | **Nombre visible del rival de la etapa.** 1-3: `Entrenador N`. 4: nombre canónico del líder. |

`toArray()` devuelve `array{etapa: int, nombre: string}` — **misma clave, mismo tipo**. Solo cambia el *valor* de `nombre` en etapa 4.

### 2.4 DTOs y colecciones sin cambios

`DetalleGimnasio` (`:19-32`), `GimnasioResumen` (`:18-30`), `EtapaGimnasioCollection` (`:13-27`) y `EquipoEtapaGimnasio` quedan intactos.

---

## 3. Compatibilidad y consumidores

### 3.1 Usos reales de `nombreEtapa`

| Archivo:línea | Consumo | Efecto |
|---|---|---|
| `src/Gimnasios/App/ObtenerGimnasioDetalle.php:42` | `nombre:` del DTO | cambia el valor mostrado en la ficha (deseado) |
| `src/Gimnasios/App/IniciarCombateGimnasio.php:97` | `nombreRival:` de `CreadorBatallaSesion` | cambia el nombre del rival guardado en sesión |

Sobre el segundo: `app/Support/CreadorBatallaSesion.php:42` lo pasa a `EquipoBatalla::fromData`. **La vista de combate no pinta nombres de equipo** (`resources/views/livewire/partials/battle-field.blade.php:46,57` solo itera Pokémon), así que no hay impacto visual inmediato; el dato cambia en la sesión de batalla y en cualquier futuro consumidor del nombre del equipo rival.

### 3.2 Cadena de `toArray()` (usos reales, verificados)

```
EtapaGimnasio::toArray()            src/Gimnasios/Domain/DataTransferObjects/EtapaGimnasio.php:25-31
  └─ EtapaGimnasioCollection::toArray()   src/Gimnasios/Domain/Collections/EtapaGimnasioCollection.php:24-27 (llamada en :26)
       └─ DetalleGimnasio::toArray()      src/Gimnasios/Domain/DataTransferObjects/DetalleGimnasio.php:56
            └─ GimnasioController::show() src/Gimnasios/Infra/Controllers/GimnasioController.php:73
                 └─ JSON GET /api/gimnasios/{gym}
                      └─ resources/views/gimnasios/show.blade.php:337-344 (fetch + asignación)
                         ├─ :88   x-for="(etapa, idx) in gym.etapas"
                         ├─ :115  x-text="etapa.nombre"
                         ├─ :123  :title="'Entrenador: ' + etapa.nombre"
                         └─ :299  gym.etapas.find(...)?.nombre  (nombre de la etapa anterior)
```

No existe ningún otro consumidor: `grep -rn "api/gimnasios"` solo devuelve `resources/views/gimnasios/index.blade.php:159` (listado, que no usa `etapas`) y las llamadas de `show.blade.php:337,362,370,400`.

**Veredicto: retrocompatible.** Mismas claves y tipos; no hace falta clave nueva ni versión de API. `@deprecated` y los docblocks de shape (`EtapaGimnasio.php:21`, `DetalleGimnasio.php:38`) siguen siendo válidos sin edición.

**Deuda de documentación (no de contrato):** `docs/gimnasios.md:223` muestra `{ "etapa": 4, "nombre": "Líder" }` y `docs/gimnasios.md:113` / `src/Gimnasios/context.md:54` fijan «4=Líder». Deben actualizarse al cerrar la implementación.

### 3.3 Nota sobre `:123`

`show.blade.php:123` usa `:title="'Entrenador: ' + etapa.nombre"`. Con el cambio, la etapa 4 mostrará `Entrenador: Brock`. Es un detalle de copy que TASK-050 debe decidir (quizá `Rival:`); **no es un cambio de contrato** y no debe resolverse en dominio.

---

## 4. Archivos y orden de implementación

| # | Acción | Archivo | Cambio |
|---|---|---|---|
| 1 | crear | `src/Gimnasios/Domain/CatalogoLideres.php` | `final class`, `NOMBRES`, `POR_DEFECTO`, `nombreDe()` |
| 2 | modificar | `src/Gimnasios/Domain/Gimnasio.php` | `+ public readonly string $nombreLider`; `nombreEtapa(4)` |
| 3 | modificar | `src/Gimnasios/Domain/CatalogoGimnasios.php` | `use`; 18 × `nombreLider: CatalogoLideres::nombreDe('<slug>')` |
| 4 | modificar | `src/Gimnasios/Infra/EloquentGymCatalogoRepository.php` | `use`; en `desdeModelo()` `nombreLider: CatalogoLideres::nombreDe($model->slug)` |
| 5 | modificar (solo tras cerrar TASK-050) | `resources/views/gimnasios/show.blade.php` | sprite de líder y, si se acuerda, copy de `:123`. **No tocar `etapa.nombre`** |
| 6 | modificar (doc) | `docs/gimnasios.md`, `src/Gimnasios/context.md`, `src/Gimnasios/README.md` | «4=Líder» → nombre canónico; ejemplo JSON |

**Sin cambios (verificado, no tocar):** `app/Models/Gym.php`, `app/Models/GymStage.php`, migraciones, `database/seeders/GymSeeder.php` (la firma de `insertar()` no cambia), `AdminGymController`, `DetalleGimnasio`, `EtapaGimnasio`, `EtapaGimnasioCollection`, `GimnasioResumen`, `ObtenerGimnasioDetalle`.

**Orden obligatorio:** 1 → 2 → 3 y 4 en el **mismo commit** (2 rompe 3 y 4 hasta que existan). Los pasos 5 y 6 van después, en commits separados. El paso 5 **no puede empezar** hasta que TASK-050 esté en `COMPLETED`.

---

## 5. Impacto en TASK-050 y en la vista de hábitats

### 5.1 Estado real de TASK-050

`git status` muestra `M resources/views/gimnasios/show.blade.php` y TASK-050 sigue en `AWAITING_APPROVAL` con cambios ya aplicados en el árbol. **Toda la fase de vista de TASK-053 debe esperar a que TASK-050 cierre**, o habrá conflicto en el mismo archivo.

### 5.2 Qué debe evitar el implementador

1. **No tocar `show.blade.php`** hasta que TASK-050 cierre (conflicto garantizado en el bloque de etapas y en el modal).
2. **No hardcodear `'Líder'`** en Blade ni en JS. El texto sale de `etapa.nombre` (`:115`, `:123`, `:299`).
3. **No crear un mapa de nombres en JS/Alpine.** `frontend-minimal-logic.md:5-7`: el backend es dueño del dato.
4. **No usar `x-html`** para el nombre. Los nombres llevan tildes y apóstrofos (p. ej. «Lt. Surge»); `x-text` escapa por defecto. Un `x-html` abriría un vector de inyección si algún día el nombre viene de BD.
5. **No añadir `esLider` al DTO** para decidir la columna del líder: la posición ya se deriva de `etapa === 4` (orden natural 1→4, que es justo lo que TASK-050 implementa en `:87-88`).
6. **No mover el sprite al dominio** ni concatenar rutas en el JS de la vista. Usar el componente Blade de TASK-053 con el `slug` del gimnasio y fallback a `misc/trainer.webp`.

### 5.3 Hábitats: sin impacto

Los entrenadores de hábitat son otro bounded context de presentación: su nombre se genera en `src/CombateEntrenadores/App/IniciarCombateEntrenador.php:79` (`"Entrenador Nivel {$nivel}"`) y su UI vive en `resources/views/habitats/show.blade.php:74-102,145-218`. **No comparten** `Gimnasio`, `EtapaGimnasio` ni `nombreEtapa`. El implementador no debe «unificar» nombres de entrenador entre gimnasios y hábitats: son reglas de producto distintas (decisión 4 vs 5 de TASK-053).

---

## 6. Riesgos

| # | Riesgo | Evidencia / mitigación |
|---|---|---|
| R1 | **Romper runtime** por no actualizar el adaptador Eloquent (premisa errónea del encargo) | `EloquentGymCatalogoRepository.php:146-152` es la construcción real. Los pasos 3 y 4 van en el mismo commit que 2; verificación con `php artisan tinker` (§7) |
| R2 | Cambio de contrato en JSON | Shape intacto (§3.2). Solo cambia el valor de `nombre` en etapa 4; `docs/gimnasios.md:223` se actualiza |
| R3 | Nombres canónicos repetidos | No es un error de datos: el nombre es etiqueta, nunca clave. Prohibido indexar por nombre; la clave es `slug` |
| R4 | Escape en Blade | Nombres con tilde/apóstrofo. `x-text` escapa; prohibido `x-html` (§5.2.4) |
| R5 | Gimnasio sin líder (slug creado por admin) | `CatalogoLideres::nombreDe()` → `POR_DEFECTO = 'Líder'`, idéntico al comportamiento actual. Documentado en el método |
| R6 | Duplicación slug entre `NOMBRES` y catálogo/BD | El slug es la única clave en los tres sitios; un typo en el mapa produce «Líder». Mitigación: verificación manual de las 18 entradas (§7) y revisión de que las claves del mapa coinciden con `CatalogoGimnasios` |
| R7 | Conflicto con TASK-050 | No tocar `show.blade.php` hasta su cierre (§5.1) |
| R8 | Copy incoherente `'Entrenador: Brock'` | `show.blade.php:123`. Se decide en la fase de vista, no en dominio |
| R9 | Nombres de producto inventados | Los 18 nombres son decisión de producto (§9, P2). El diseño no los fija |
| R10 | Regresión en el nombre del rival de combate | `IniciarCombateGimnasio.php:97`. Hoy no se pinta (`battle-field.blade.php:46,57`), pero queda en la sesión de batalla; verificar manualmente un combate de etapa 4 |

---

## 7. Verificación

**Estática**
```bash
php -l src/Gimnasios/Domain/CatalogoLideres.php
php -l src/Gimnasios/Domain/Gimnasio.php
php -l src/Gimnasios/Domain/CatalogoGimnasios.php
php -l src/Gimnasios/Infra/EloquentGymCatalogoRepository.php
vendor/bin/pint --dirty --format agent
php artisan view:cache          # solo si se tocó Blade
```

**Búsquedas de control**
```bash
grep -rn "new Gimnasio(" src app database        # deben aparecer 19 y todas con nombreLider
grep -rn "nombreEtapa" src app                   # 3 apariciones (definición + 2 consumidores)
grep -rn "'Líder'" src app resources             # 0 tras el cambio (salvo CatalogoLideres::POR_DEFECTO)
grep -rn "nombreLider" src app resources         # nuevo contrato
```

**Manual (sin tests)**
```bash
php artisan tinker --execute 'foreach (app(Src\Gimnasios\Domain\CatalogoGimnasios::class)->todos() as $g) { echo $g->slug." => ".$g->nombreLider.PHP_EOL; }'
```
Debe imprimir 18 líneas, todas con nombre distinto de `Líder`. Después: abrir `/gimnasios/bug` y comprobar la 4.ª tarjeta; y comprobar el JSON de `GET /api/gimnasios/bug` (`etapa: 4` con el nombre canónico).

---

## 8. Archivos consultados

**Reglas:** `.ai/rules/index.md`, `.ai/rules/ddd-modules.md`, `.ai/rules/arrays-dto-collections.md`, `.ai/rules/frontend-minimal-logic.md`, `/home/david/pokemon/AGENTS.md`.

**Dominio Gimnasios:** `src/Gimnasios/Domain/Gimnasio.php`, `CatalogoGimnasios.php`, `Collections/EtapaGimnasioCollection.php`, `DataTransferObjects/{EtapaGimnasio,DetalleGimnasio,GimnasioResumen,EquipoEtapaGimnasio}.php`, `Repositories/GymCatalogoRepositoryInterface.php`, `Exceptions/`.

**Aplicación/Infra:** `App/ObtenerGimnasioDetalle.php`, `App/ObtenerGimnasios.php`, `App/IniciarCombateGimnasio.php`, `Infra/Controllers/{GimnasioController,AdminGymController}.php`, `Infra/EloquentGymCatalogoRepository.php`, `app/Providers/AppServiceProvider.php:57`, `app/Http/Controllers/GimnasiosViewController.php`, `app/Support/CreadorBatallaSesion.php`.

**Persistencia:** `database/migrations/2026_09_05_211421_create_gyms_table.php`, `…211423_create_gym_stages_table.php`, `database/seeders/GymSeeder.php`, `app/Models/{Gym,GymStage}.php`.

**Presentación:** `resources/views/gimnasios/{show,index,admin}.blade.php`, `resources/views/components/sprite.blade.php`, `resources/views/livewire/partials/battle-field.blade.php`, `resources/views/habitats/show.blade.php`, `src/Shared/UI/TipoBadges.php`.

**Compartido/Docs:** `src/Shared/Domain/Collection.php`, `src/CombateEntrenadores/App/IniciarCombateEntrenador.php`, `docs/gimnasios.md`, `src/Gimnasios/{context.md,README.md}`, `.opencode/work/TASK-050/{request,plan}.md`, `.opencode/work/TASK-053/request.md`.

**Assets:** `public/images/` (existe `misc/trainer.webp`; **no** existe `trainers/`).

---

## 9. Preguntas abiertas

- **P1 (confirmación de premisa):** ¿Se acepta que la fuente en runtime es la tabla `gyms` y que el adaptador Eloquent debe aportar `nombreLider` desde `CatalogoLideres`? Sin esto, el paso 4 es obligatorio igualmente; solo cambia quién es la fuente.
- **P2 (producto, no lo decido):** los 18 nombres canónicos. Borrador posible: Kanto (Brock, Misty, Lt. Surge, Erika, Koga, Sabrina, Blaine, Giovanni) + Johto (Falkner, Bugsy, Whitney, Morty, Chuck, Jasmine, Pryce, Clair) + 2 extra para `fairy` y `steel`. **Requiere aprobación explícita** antes de escribir el mapa.
- **P3:** ¿el `:title` de `show.blade.php:123` pasa a `Rival:` en la etapa 4, o se deja `Entrenador:`? Es copy de TASK-050, no de dominio.
- **P4:** ¿TASK-053 quiere exponer `nombre_lider` también en el payload de admin (`AdminGymController::serializar:170-180`)? Recomiendo **no** hacerlo ahora (campo no editable y sin consumidor).
