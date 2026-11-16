# TASK-037 — Arquitectura: stats e IV/EV del rival en Configurar Formación

- **Fecha:** 2026-11-09
- **Rama:** `task-011`
- **Estado:** DESIGN (sin implementación)
- **Base:** TASK-035 en working tree
- **Restricciones:** sin migraciones, dependencias nuevas, cambios de fórmulas/balance, ni tests

## Decisión

Implementar la leyenda **íntegramente en backend** y fijar el rival de ruta 3v3 mediante un **snapshot serializado en sesión**, identificado por un `preview_id`.

- El rival de ruta se genera **una vez al abrir el modal**, con el mismo `GeneradorEquipoRuta` y los mismos movimientos desbloqueados que usaría el combate.
- El snapshot se guarda en sesión con TTL de 1 hora y se referencia por `preview_id`.
- `IniciarCombateRuta` consume ese snapshot; si no existe o caducó, cae al generador actual.
- La leyenda viaja como bloque aditivo `perfil_ivs_evs` en la misma respuesta GET de preview por contexto, junto al bloque de comparación de PC de TASK-035.
- El frontend solo presenta: no calcula stats, IV/EV, niveles ni CP.
- La leyenda está siempre visible (`<section>`, nunca `<details>`), con tablas compactas, scroll accesible y el perfil actual resaltado con texto además de color.

### Por qué snapshot y no semilla

| Criterio | Snapshot serializado (elegido) | Semilla + regeneración |
|---|---|---|
| Exactitud de especie/shiny/movimientos | Total: se guarda el resultado | Depende del pool, nivel y movimientos desbloqueados en el momento del combate |
| Cambios de nivel del jugador | Se conserva composición y se recalculan stats con `conNivel` | Puede cambiar la selección de movimientos |
| Cambios de hábitat/nivel | El `preview_id` queda ligado a hábitat+nivel; no se reutiliza | Habría que versionar también las entradas |
| Tamaño | 3 Pokémon con movimientos; sesión en BD, aceptable | Muy pequeño |
| Robustez ante cambios de código | Etiqueta de versión + TTL + comprobación de instancia | Alta, pero depende de la estabilidad del generador |
| Múltiples pestañas | Cada `preview_id` es independiente | También, con una semilla por pestaña |
| Limpieza | Borrado explícito + TTL | Igual |

La semilla se descarta porque no garantiza “exactamente ese rival” si cambia el pool, los movimientos desbloqueados o el nivel entre preview y combate.

## Contexto verificado

- `GeneradorEquipoRuta::generar()` selecciona 3 especies con reemplazo ponderado y azar real por defecto (`src/CombateRuta/App/GeneradorEquipoRuta.php:82-127`). El shiny también usa azar real.
- `GeneradorEquipoRuta::especiesPonderadas()` existe solo para la aproximación de PC de TASK-035 (`:138-165`).
- `IniciarCombateRuta` genera un rival **nuevo** en cada combate (`src/CombateRuta/App/IniciarCombateRuta.php:51-56`).
- `DatosPokemonBatalla` transporta stats base, IV, EV, nivel, movimientos, tipos, posición, shiny, etc. (`src/Battle/Domain/DatosPokemonBatalla.php:26-59`).
- `EquipoBatalla::fromData()` precalcula `BattleStats` si `nivel !== null` (`src/Battle/Domain/EquipoBatalla.php:175-177`).
- La fuente única de IV/EV es `PoliticaIvsEvs` + `PerfilIvsEvs` + `DistribuidorStatsValor` (`src/Shared/Domain/*`). `BattleStats` contiene las fórmulas (`src/Pokemon/Domain/Stats/BattleStats.php:73-81`).
- El rival de entrenador es determinista por fecha (`crc32("{$habitatId}|{$nivel}|{$entrenadorIndex}|{$fecha}")`); el de gimnasio por catálogo, etapa e índice.
- `CreadorBatallaSesion` persiste la batalla y su meta en sesión (`app/Support/CreadorBatallaSesion.php:33-53`); `BattleSessionService` usa un formato versionado (`app/Support/BattleSessionService.php:50,71-73`).
- La sesión usa driver `database` y lifetime largo (`config/session.php:23,37`), por lo que el tamaño del snapshot no es un problema.
- TASK-035 ya expone los endpoints GET de PC, partials `comparacion-pc.blade.php` y `comparacion-pc-script.blade.php`.
- En gimnasios el modal se abre sin equipo y el rival se calcula al nivel escalado (`src/Gimnasios/App/CalcularComparacionPcGimnasio.php:52-87`).

## Fijación del rival de ruta

### DTO de aplicación

`App\Support\CombateRuta\RivalRutaFijado`:

```php
final readonly class RivalRutaFijado
{
    public function __construct(
        public string $previewId,
        public int $habitatId,
        public int $nivel,
        public int $nivelJugador,
        public int $creadoEn,
        public DatosPokemonBatallaCollection $equipo,
    ) {}
}
```

### Almacén de sesión

`App\Support\CombateRuta\AlmacenRivalRutaFijado`:

```php
final class AlmacenRivalRutaFijado
{
    private const PREFIJO = 'ruta_preview';
    private const VERSION = 1;
    private const TTL_SEGUNDOS = 3600;

    public function obtener(int $userId, string $previewId, int $habitatId, int $nivel): ?RivalRutaFijado;
    public function fijar(int $userId, int $habitatId, int $nivel, int $nivelJugador, DatosPokemonBatallaCollection $equipo): RivalRutaFijado;
    public function limpiar(int $userId, string $previewId): void;
}
```

- Clave: `ruta_preview:{userId}:{previewId}`.
- `previewId`: `bin2hex(random_bytes(16))` → 32 caracteres.
- Payload: `v1|habitatId|nivel|nivelJugador|creadoEn|serialize($equipo)`.
- Al leer: validar versión, `habitatId`, `nivel`, TTL, `unserialize` y `instanceof DatosPokemonBatallaCollection`. Cualquier fallo → `session()->forget()` y `null`.
- El `userId` va en la clave y el snapshot no se comparte entre usuarios.
- El snapshot se guarda en sesión, nunca en base de datos.

### Ciclo de vida

```text
CREAR
  GET /api/habitats/{h}/ruta/pc?team_id={t}&nivel={n}
  → no hay preview_id
  → generar rival con GeneradorEquipoRuta (mismos movimientos desbloqueados que el combate)
  → almacen.fijar(user, h, n, nivelJugador, equipo)
  → response.preview_id = nuevo id

REUTILIZAR
  GET .../pc?team_id={t}&nivel={n}&preview_id={id}
  → almacen.obtener(user, id, h, n)
  → si válido: mismo equipo; stats ajustados con conNivel(nivelJugador)
  → si inválido/caducado/contexto distinto: se crea uno nuevo y se devuelve su id

INVALIDAR
  - nivel distinto: el frontend limpia el id al cambiar de nivel; si no lo hace,
    obtener() falla por nivel y crea uno nuevo.
  - hábitat distinto: obtener() falla por hábitat.
  - TTL superado o sesión nueva: obtener() devuelve null; se crea uno nuevo.

LIMPIAR
  - al iniciar combate: IniciarCombateRuta llama almacen.limpiar() tras crear la batalla.
  - al cancelar el modal: DELETE /api/habitats/{h}/ruta/preview/{previewId}.
  - al caducar: TTL de 1 hora.
```

- **Múltiples pestañas:** cada apertura sin `preview_id` crea uno distinto; dos pestañas pueden tener rivales distintos y cada combate usa el suyo.
- **Caducidad de sesión:** si Laravel invalida la sesión, el snapshot desaparece; el combate cae al generador actual.

## Integración con el combate

`IniciarCombateRuta` conserva su flujo y añade un parámetro opcional:

```php
public function iniciar(
    int $habitatId,
    int $nivel,
    int $teamId,
    int $userId,
    int $nivelJugador,
    array $formacion = [],
    ?string $previewId = null,
): string;
```

Pseudocódigo:

```text
equipoJugador = construirEquipoJugador(...)

fijado = previewId ? almacen.obtener(user, previewId, habitatId, nivel) : null

si fijado != null:
    equipoRival = fijado.nivelJugador === nivelJugador
        ? fijado.equipo
        : fijado.equipo.conNivel(nivelJugador)
sino:
    movimientosDesbloqueados = cargarMovimientos.para(user)
    equipoRival = generador.generar(habitatId, nivel, nivelJugador,
                                    movimientosDesbloqueados: movimientosDesbloqueados)

si equipoRival.isEmpty():
    throw ViolacionReglaNegocio('No hay Pokémon salvajes disponibles...')

battleId = creadorBatalla.crearYGuardar(...)

si previewId != null:
    almacen.limpiar(user, previewId)

return battleId
```

Reglas:

- **Con rival fijado:** no se llama al generador; se reutiliza el snapshot. No se recargan movimientos desbloqueados.
- **Sin rival fijado o `preview_id` inválido:** se mantiene el comportamiento actual.
- **Jugador sube de nivel entre preview y combate:** no se regenera. `conNivel($nivelJugador)` conserva especie, shiny, movimientos, IV/EV y posición, y recalcula los stats de combate al nivel actual.
- **Error al crear la batalla:** el snapshot no se limpia; el jugador puede corregir el equipo y reintentar con el mismo rival.
- **Meta de batalla:** se puede añadir `'preview_id' => $previewId` para trazabilidad, sin efecto en presentadores. Opcional.

Métodos aditivos de dominio:

```php
// Src\Battle\Domain\DatosPokemonBatalla
public function conNivel(int $nivel): self;

// Src\Battle\Domain\Collections\DatosPokemonBatallaCollection
public function conNivel(int $nivel): self;
```

No cambian fórmulas ni propiedades serializadas; no se modifica `SESSION_VERSION`.

## Contrato de preview

Se amplían los endpoints existentes de TASK-035. No se crea un endpoint de preview nuevo.

| Contexto | Endpoint | `team_id` | `preview_id` |
|---|---|---|---|
| Ruta | `GET /api/habitats/{habitat}/ruta/pc?nivel={1\|2\|3}` | Requerido (el modal no se abre sin equipo) | Opcional |
| Entrenador | `GET /api/habitats/{habitat}/entrenadores/{nivel}/{trainer}/pc` | Requerido | No aplica |
| Gimnasio | `GET /api/gimnasios/{gym}/pc` | **Opcional** (la leyenda debe verse antes de elegir equipo) | No aplica |
| Limpieza ruta | `DELETE /api/habitats/{habitat}/ruta/preview/{previewId}` | No | Sí, en la URL |

Validación:

- `team_id`: `sometimes|integer` + `Rule::exists('teams','id')->where('user_id', Auth::id())` en gimnasio; `required` en ruta/entrenador.
- `nivel`: `sometimes|integer|min:1|max:3` en ruta; `required` en entrenador.
- `trainer`: `required|integer|min:1|max:3`.
- `habitat`: `required|integer` + `Rule::exists('habitats','id')`.
- `preview_id`: `sometimes|string|max:64`.
- `gym`: resuelto por repositorio; 404 si no existe.
- La etapa de gimnasio y la fecha de entrenador se resuelven siempre en servidor.

Respuesta `200` unificada:

```json
{
  "estado": "ok",
  "preview_id": "a1b2c3d4e5f60718293a4b5c6d7e8f90",
  "equipo": { "miembros": 3, "completo": true, "pc": 1240 },
  "rival": { "pc": 1180, "aproximado": false, "metodo": "rival_fijado" },
  "comparacion": { "ratio": 0.95, "veredicto": "parejo" },
  "perfil_ivs_evs": {
    "actual": { "clave": "top_dos", "etiqueta": "Top 2" },
    "perfiles": [
      {
        "clave": "nulo",
        "etiqueta": "Sin IV/EV",
        "descripcion": "Ningún stat recibe el máximo; todos llevan IV 0 y EV 0.",
        "stats_al_maximo": 0,
        "iv_maximo": 15,
        "iv_resto": 0,
        "ev_maximo": 255,
        "ev_resto": 0,
        "actual": false
      }
    ],
    "pokemons": [
      {
        "etiqueta": "Rival 1",
        "stats": [
          { "clave": "hp",    "etiqueta": "PS",        "base": 70,  "iv": 8,  "ev": 128, "calculado": 166 },
          { "clave": "atk",   "etiqueta": "Ataque",    "base": 110, "iv": 15, "ev": 255, "calculado": 184 },
          { "clave": "def",   "etiqueta": "Defensa",   "base": 80,  "iv": 8,  "ev": 128, "calculado": 121 },
          { "clave": "spAtk", "etiqueta": "At. Esp.",  "base": 65,  "iv": 8,  "ev": 128, "calculado": 106 },
          { "clave": "spDef", "etiqueta": "Def. Esp.", "base": 75,  "iv": 8,  "ev": 128, "calculado": 116 },
          { "clave": "speed", "etiqueta": "Velocidad", "base": 95,  "iv": 15, "ev": 255, "calculado": 169 }
        ]
      }
    ]
  },
  "contexto": { "tipo": "ruta", "habitat_id": 12, "nivel": 2 }
}
```

Cifras ilustrativas. Los valores reales salen de `PoliticaIvsEvs` y `BattleStats`; no se codifican en Blade ni en JS.

Notas del contrato:

- `preview_id` solo se devuelve en ruta; en entrenador/gimnasio es `null`.
- `rival.aproximado` pasa a `false` en ruta porque el rival ya no es una media del pool: es el rival fijado. `metodo` pasa a `rival_fijado`.
- No se expone `nivel_stats`, `nombre`, `species_id`, `tipos`, `moves`, `posicion`, `peso`, `iconName`, `effectKeys` ni `item` del rival.
- Gimnasio sin `team_id`: `estado: "equipo_incompleto"`, `equipo.miembros: 0`, `equipo.pc: null`, leyenda completa. El frontend muestra “Selecciona un equipo” en la sección de PC y la leyenda en su sección independiente.
- Si el rival no está disponible (`sin_rivales` o `no_disponible`), `perfil_ivs_evs.perfiles` sigue mostrando los cuatro perfiles; `pokemons` queda vacío y `actual` puede ser `null`.
- Si `preview_id` no existe o caducó, se crea uno nuevo y se devuelve en la respuesta. No es un error.

Códigos:

| Situación | HTTP | Cuerpo |
|---|---|---|
| No autenticado | 401 | Laravel |
| `team_id` ausente/ajeno/inexistente en ruta/entrenador | 422 | `{message, errors}` |
| `team_id` ajeno/inexistente en gimnasio (si se envía) | 422 | `{message, errors}` |
| `nivel`/`trainer` fuera de rango | 422 | `{message, errors}` |
| `habitat` inexistente | 422 | `{message, errors}` |
| `gym` inexistente | 404 | `GimnasioNoExiste` |
| Equipo incompleto o sin equipo | 200 | `estado: "equipo_incompleto"` |
| Rival vacío | 200 | `estado: "sin_rivales"` |
| Entrenador ya derrotado o gimnasio no disponible | 200 | `estado: "no_disponible"` |
| `preview_id` inválido o caducado | 200 | Se devuelve uno nuevo |
| Limpieza correcta o idempotente | 204 | Sin cuerpo |
| Error interno | 500 | Laravel |

## DTOs y capas

| DTO | Ubicación | Contenido | Serialización |
|---|---|---|---|
| `PerfilRivalAplicado` | `App\Support\Pc` | `clave`, `etiqueta`, `nivelStats` (interno) | `toArray()` solo `clave`/`etiqueta`; no expone nivel |
| `PerfilIvsEvsOpcion` | `App\Support\Pc` | `clave`, `etiqueta`, `descripcion`, `statsAlMaximo`, `ivMaximo`, `ivResto`, `evMaximo`, `evResto`, `actual` | `toArray()` |
| `StatPreview` | `App\Support\Pc` | `clave`, `etiqueta`, `base`, `iv`, `ev`, `calculado` | `toArray()` |
| `StatsPreview` | `App\Support\Pc` | `list<StatPreview>` ordenada HP→Velocidad | `toArray()` lista |
| `PokemonRivalPreview` | `App\Support\Pc` | `etiqueta` (“Rival 1”), `StatsPreview` | `toArray()` |
| `LeyendaIvsEvs` | `App\Support\Pc` | `?PerfilRivalAplicado`, `list<PerfilIvsEvsOpcion>`, `list<PokemonRivalPreview>` | `toArray()` |
| `VistaPreviaRival` | `App\Support\Pc` | `ComparacionPc`, `LeyendaIvsEvs`, `?string $previewId` | `array_merge(...)` |
| `RivalRutaFijado` | `App\Support\CombateRuta` | Snapshot y metadatos de sesión | No se serializa al cliente |
| `MetodoPcRival::RIVAL_FIJADO` | `Src\Shared\Domain\Pc` | Nuevo caso del enum | `value` en JSON |

Decisiones:

- Los DTOs de la leyenda viven en `App\Support\Pc` y no en dominio: son read models de presentación/serialización y dependen de `DatosPokemonBatallaCollection`/`BattleStats`.
- `MetodoPcRival` sigue en dominio porque describe cómo se obtuvo el PC rival.
- No se usan arrays como contrato interno. `toArray()` aparece solo en la frontera JSON.
- Las etiquetas (“Rival 1”, “PS”, “Top 2”, descripciones) las genera el backend para no duplicar copy ni valores en Blade.
- La descripción de cada perfil se construye con `cuantosMaximos()`, `ivMaximo()`, `ivResto()`, `evMaximo()` y `evResto()`; no se hardcodean 15/255/8/128 en la vista.

## UI: modal Configurar Formación

**Partial nuevo** `resources/views/partials/leyenda-ivs-evs.blade.php`, incluido en hábitats y gimnasios. Siempre visible, sin `<details>`.

Estructura:

- Título “IV/EV del rival”.
- Nota: “Cada Pokémon del rival usa uno de estos perfiles. Los stats se calculan al nivel del combate.”
- Estados de carga/error.
- Resumen del perfil aplicado (“Perfil aplicado: Top 2”).
- Tabla de los cuatro perfiles: Perfil, Stats al máximo, IV (máx./resto), EV (máx./resto); el actual con `aria-current` y texto “Este combate”.
- Tablas por Pokémon anónimo (`Rival 1`, `Rival 2`, `Rival 3`) con columnas Stat, Base, IV, EV, Calculado.
- Nota final: “Base = stats base; IV/EV = perfil aplicado; Calculado = stat al nivel del combate. El orden de los rivales no indica su posición en combate.”

Integración:

- Hábitats: después de `@include('partials.comparacion-pc')` en `modal-formacion.blade.php`.
- Gimnasios: después de `@include('partials.comparacion-pc')` en `show.blade.php`.
- El partial no está dentro de `x-show="selectedTeamId"`; la leyenda se ve aunque no haya equipo en gimnasio.
- El modal se convierte en columna con cuerpo desplazable y botones fijos:
  - `max-h-[70vh] overflow-y-auto overscroll-contain pr-1` para el contenido.
  - Botones fuera del área desplazable.

Copy:

- Ruta: “Rival fijado para este combate. Si cierras y vuelves a abrir el modal, se elegirá otro.”
- Gimnasio: “Rival de la etapa actual. No se muestran especies, tipos ni movimientos del rival.”

Cambios en `comparacion-pc-script.blade.php`:

- Añadir `pcPreviewId: null`.
- En `cargarComparacionPc`, tras `this.pcComparacion = data`, `this.pcPreviewId = data.preview_id || null;`.
- En `limpiarComparacionPc`, resetear `pcPreviewId = null`.
- Añadir `limpiarPreviewRuta(url)` que capture el id y haga `DELETE` con CSRF.
- El `confirmarCombateRuta` debe enviar `preview_id: this.pcPreviewId` en el body.
- `selectRutaNivel` debe limpiar el `preview_id` antes de pedir uno nuevo.
- `closeFormacionPopup` debe llamar a `limpiarPreviewRuta` antes de `limpiarComparacionPc`.

Accesibilidad:

- `caption` sr-only en ambas tablas.
- `scope="col"` y `scope="row"`.
- Perfil actual con `aria-current="true"` y texto “Este combate”, no solo color.
- Contenedores con `role="region"`, `aria-label` y `tabindex="0"` para scroll por teclado.
- Cargando con `role="status"`; error con `role="alert"`.
- `tabular-nums` para columnas numéricas.
- Sin animaciones; no se necesita `motion-reduce`.
- La leyenda está siempre expandida; no hay `<details>`.

## Reutilización de TASK-035

| Elemento | Decisión |
|---|---|
| `ComparacionPc`, `ComparadorPc`, `UmbralesVeredictoPc`, `EstadoComparacionPc`, `VeredictoPc` | Se conservan sin cambios |
| `MetodoPcRival` | Se añade `RIVAL_FIJADO`; `PROMEDIO_POOL` deja de usarse y se elimina |
| `CalculadoraPcReclutado`, `ObtenerPcEquipoJugador` | Se conservan; `ObtenerPcEquipoJugador` acepta `?int $teamId` |
| `CalculadoraPcRival::desdeDatosBatalla` | Se conserva; es la base del PC exacto de ruta |
| `CalculadoraPcRival::promedioPonderado` | Se elimina (sin uso) |
| `MuestraPcRival` | Se elimina |
| `GeneradorEquipoRuta::especiesPonderadas` | Se elimina (sin uso) |
| `EspeciePonderadaRuta` | Se elimina |
| `CalcularComparacionPcRuta` | Se reescribe: fija/reutiliza rival y calcula PC exacto |
| `CalcularComparacionPcEntrenador` | Se amplía: genera antes de validar equipo y construye leyenda |
| `CalcularComparacionPcGimnasio` | Se amplía: genera antes de validar equipo, `team_id` opcional y leyenda |
| `comparacion-pc.blade.php` | Se conserva; cambian notas de ruta/gimnasio |
| `comparacion-pc-script.blade.php` | Se conserva; se añade `pcPreviewId` y limpieza |
| Partials de modal | Se conserva la comparación; se añade la leyenda y el cuerpo desplazable |

La leyenda se construye una sola vez en `ConstruirLeyendaIvsEvs` y se pinta una sola vez en `leyenda-ivs-evs.blade.php`. No se duplica copy ni tabla entre hábitats y gimnasios.

## Seguridad y privacidad

- **Sesión y propiedad:** la clave incluye `userId`; el `preview_id` es aleatorio de 128 bits. Un `preview_id` de otro usuario no existe en la sesión del atacante. No hay IDOR.
- **Validación de contexto:** al recuperar se comprueban `habitatId` y `nivel`; un `preview_id` de otro hábitat/nivel se descarta y se crea uno nuevo.
- **TTL:** 1 hora. El snapshot se borra al combatir y al cancelar. No se persiste en base de datos.
- **Datos expuestos:** solo stats base, IV, EV y calculados, etiquetas “Rival 1..3”, perfiles y PC agregado. Nunca nombres, especies, `species_id`, tipos, movimientos, formación, posición, peso, habilidad, objeto ni `nivel_stats`.
- **Riesgo aceptado de fingerprinting:** los stats base permiten inferir especies del pool. Producto ya aprobó mostrarlos. Se documenta.
- **Riesgo aceptado en gimnasio:** los stats calculados permiten inferir el nivel del rival aunque no se exponga `nivel_stats`. Es consecuencia directa de mostrar base/IV/EV/calculado. Se documenta.
- **GET con efecto de sesión:** la primera llamada a `/pc` de ruta crea el snapshot. Es un efecto de sesión idempotente por `preview_id`, no una escritura de negocio. Se documenta; la alternativa `POST /preview` se descarta por simplicidad y porque TASK-035 ya usa GET.
- **`team_id`:** validado por propietario en el controlador y revalidado en `ObtenerPcEquipoJugador` con `where('user_id', $userId)`.
- **Sin logs:** no se registra el contenido del rival ni el `preview_id`.
- **Sin caché compartida:** si se añadiera, la clave debe incluir `user_id`, `preview_id`, contexto y nivel.

## Secuenciación

- **TASK-035** es la base y está sin commit. TASK-037 modifica sus archivos; no se debe revertir ni commitear sin aprobación.
- **TASK-036** toca `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php`, `app/Livewire/Presenters/*`, `app/Livewire/Combate/Soporte/*` y `moves-panel.blade.php`. TASK-037 no toca esos archivos, salvo `DatosPokemonBatalla`/`DatosPokemonBatallaCollection` para `conNivel`, que TASK-036 no modifica. No hay solapamiento directo.
- **Archivos compartidos con TASK-035:** `GeneradorEquipoRuta.php`, `CalcularComparacionPc*`, `CombateRutaController`, `EntrenadorController`, `GimnasioController`, `routes/ruta.php`, `comparacion-pc.blade.php`, `comparacion-pc-script.blade.php`, `modal-formacion.blade.php`, `habitats/show.blade.php`, `gimnasios/show.blade.php`.
- **Orden seguro:**
  1. Backend de dominio/support (DTOs, almacén, builder, `conNivel`).
  2. Casos de uso y `IniciarCombateRuta`.
  3. Controladores y rutas.
  4. Frontend (partial, script, modales, vistas).
  5. Revisión de `git status`/`git diff` limitada al alcance.
- **No** hacer commit, push, reset ni clean sin autorización.
- Antes de tocar vistas compartidas, comprobar que TASK-036 no las ha modificado; si hay cambios, coordinar o usar worktree separado.

## Mapa de capas

- **Dominio:**
  - `Src\Shared\Domain\Pc\MetodoPcRival` (nuevo caso `RIVAL_FIJADO`).
  - `Src\Battle\Domain\DatosPokemonBatalla::conNivel`.
  - `Src\Battle\Domain\Collections\DatosPokemonBatallaCollection::conNivel`.
  - `Src\Shared\Domain\PoliticaIvsEvs`, `PerfilIvsEvs`, `DistribuidorStatsValor` (solo lectura).
  - `Src\Pokemon\Domain\Stats\BattleStats`, `CalculadorCP`, `StatsValue`, `DatosStats` (solo lectura).
  - `Src\Shared\Domain\Pc\ComparacionPc` (sin cambios).
- **Aplicación:**
  - `Src\CombateRuta\App\CalcularComparacionPcRuta`.
  - `Src\CombateEntrenadores\App\CalcularComparacionPcEntrenador`.
  - `Src\Gimnasios\App\CalcularComparacionPcGimnasio`.
  - `Src\CombateRuta\App\IniciarCombateRuta`.
  - `App\Support\Pc\ConstruirLeyendaIvsEvs`.
- **Infraestructura / seam de aplicación:**
  - `App\Support\CombateRuta\AlmacenRivalRutaFijado`.
  - `App\Support\CombateRuta\RivalRutaFijado`.
  - `App\Support\Pc\*` (DTOs de leyenda y `VistaPreviaRival`).
  - `App\Support\Pc\ObtenerPcEquipoJugador` (team nullable).
  - Controladores `CombateRutaController`, `EntrenadorController`, `GimnasioController`.
  - Rutas `routes/ruta.php`, `routes/entrenadores.php`, `routes/gimnasios.php`.
- **Presentación:**
  - `resources/views/partials/leyenda-ivs-evs.blade.php`.
  - `resources/views/partials/comparacion-pc.blade.php`.
  - `resources/views/partials/comparacion-pc-script.blade.php`.
  - `resources/views/habitats/partials/modal-formacion.blade.php`.
  - `resources/views/habitats/show.blade.php`.
  - `resources/views/gimnasios/show.blade.php`.

Dirección de dependencias: Presentación → Aplicación/Infraestructura → Dominio. `App\Support` no es dominio. El dominio nuevo (`conNivel`, enum) no importa Eloquent, HTTP ni Blade.

## Contratos de clases

```php
// App\Support\Pc
final class ConstruirLeyendaIvsEvs
{
    public function construir(
        ?PerfilIvsEvs $perfilActual,
        int $nivelStats,
        DatosPokemonBatallaCollection $equipoRival,
    ): LeyendaIvsEvs;
}

final readonly class VistaPreviaRival
{
    public function __construct(
        public ComparacionPc $comparacion,
        public LeyendaIvsEvs $leyenda,
        public ?string $previewId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array;
}

final class ObtenerPcEquipoJugador
{
    public function obtener(?int $teamId, int $userId, int $nivelJugador): PcEquipoJugador;
}

// Casos de uso
Src\CombateRuta\App\CalcularComparacionPcRuta::calcular(
    int $habitatId,
    int $nivel,
    int $teamId,
    int $userId,
    int $nivelJugador,
    ?string $previewId = null,
): VistaPreviaRival;

Src\CombateEntrenadores\App\CalcularComparacionPcEntrenador::calcular(
    int $habitatId,
    int $nivel,
    int $trainerIndex,
    int $teamId,
    int $userId,
    int $nivelJugador,
    string $fecha,
): VistaPreviaRival;

Src\Gimnasios\App\CalcularComparacionPcGimnasio::calcular(
    string $slug,
    ?int $teamId,
    int $userId,
    int $nivelJugador,
): VistaPreviaRival;

// Combate
Src\CombateRuta\App\IniciarCombateRuta::iniciar(
    int $habitatId,
    int $nivel,
    int $teamId,
    int $userId,
    int $nivelJugador,
    array $formacion = [],
    ?string $previewId = null,
): string;
```

## Flujo de datos

1. El jugador abre “Configurar Formación”.
   - Ruta: hay equipo seleccionado y se llama `GET /pc` con `nivel`, `team_id` y, si existe, `preview_id`.
   - Entrenador: hay equipo y entrenador; se llama `GET /pc`.
   - Gimnasio: puede no haber equipo; se llama `GET /pc` sin `team_id` para cargar la leyenda.
2. El controlador valida parámetros y propiedad del equipo.
3. El caso de uso:
   - Ruta: busca `preview_id`; si no existe, genera el rival con `GeneradorEquipoRuta` y lo fija; si existe, lo reutiliza y ajusta stats con `conNivel`.
   - Entrenador: genera el rival determinista con la fecha del servidor.
   - Gimnasio: resuelve etapa, nivel escalado e índice; genera el rival del catálogo.
4. Se construye `LeyendaIvsEvs` con el perfil aplicado, los cuatro perfiles y los stats anónimos por Pokémon.
5. Se obtiene el PC del equipo propio (o `miembros=0` si no hay equipo).
6. Se calcula el PC rival con `CalculadoraPcRival::desdeDatosBatalla` y se compone `ComparacionPc`.
7. El controlador serializa `VistaPreviaRival` + `contexto`.
8. Alpine guarda `preview_id`, descarta respuestas obsoletas y pinta la leyenda y la comparación.
9. Al cambiar de equipo, se vuelve a llamar con el mismo `preview_id` en ruta; el rival no cambia.
10. Al cambiar de nivel de ruta, se limpia el `preview_id` y se pide uno nuevo.
11. Al confirmar combate de ruta, se envía `preview_id`; `IniciarCombateRuta` reutiliza el snapshot, ajusta nivel si hace falta, crea la batalla y limpia el snapshot.
12. Al cancelar, el frontend llama a `DELETE /api/habitats/{h}/ruta/preview/{previewId}` y limpia el estado.
13. Si el indicador falla, el combate sigue disponible; el backend cae al generador actual si no hay snapshot.

## Plan de archivos

| Orden | Acción | Archivo | Responsable |
|---|---|---|---|
| 1 | Modificar | `src/Shared/Domain/Pc/MetodoPcRival.php` (añadir `RIVAL_FIJADO`; retirar `PROMEDIO_POOL`) | backend |
| 2 | Modificar | `src/Battle/Domain/DatosPokemonBatalla.php` (`conNivel`) | backend |
| 3 | Modificar | `src/Battle/Domain/Collections/DatosPokemonBatallaCollection.php` (`conNivel`) | backend |
| 4 | Crear | `app/Support/CombateRuta/RivalRutaFijado.php` | backend |
| 5 | Crear | `app/Support/CombateRuta/AlmacenRivalRutaFijado.php` | backend |
| 6 | Crear | `app/Support/Pc/PerfilRivalAplicado.php` | backend |
| 7 | Crear | `app/Support/Pc/PerfilIvsEvsOpcion.php` | backend |
| 8 | Crear | `app/Support/Pc/StatPreview.php` | backend |
| 9 | Crear | `app/Support/Pc/StatsPreview.php` | backend |
| 10 | Crear | `app/Support/Pc/PokemonRivalPreview.php` | backend |
| 11 | Crear | `app/Support/Pc/LeyendaIvsEvs.php` | backend |
| 12 | Crear | `app/Support/Pc/ConstruirLeyendaIvsEvs.php` | backend |
| 13 | Crear | `app/Support/Pc/VistaPreviaRival.php` | backend |
| 14 | Modificar | `app/Support/Pc/ObtenerPcEquipoJugador.php` (`?int $teamId`) | backend |
| 15 | Modificar | `src/CombateRuta/App/CalcularComparacionPcRuta.php` | backend |
| 16 | Modificar | `src/CombateEntrenadores/App/CalcularComparacionPcEntrenador.php` | backend |
| 17 | Modificar | `src/Gimnasios/App/CalcularComparacionPcGimnasio.php` | backend |
| 18 | Modificar | `src/CombateRuta/App/IniciarCombateRuta.php` | backend |
| 19 | Modificar | `src/CombateRuta/Infra/Controllers/CombateRutaController.php` | backend |
| 20 | Modificar | `src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php` | backend |
| 21 | Modificar | `src/Gimnasios/Infra/Controllers/GimnasioController.php` | backend |
| 22 | Modificar | `routes/ruta.php` (DELETE preview) | backend |
| 23 | Eliminar | `app/Support/Pc/MuestraPcRival.php` | backend |
| 24 | Eliminar | `src/CombateRuta/Domain/DataTransferObjects/EspeciePonderadaRuta.php` | backend |
| 25 | Crear | `resources/views/partials/leyenda-ivs-evs.blade.php` | frontend |
| 26 | Modificar | `resources/views/partials/comparacion-pc.blade.php` | frontend |
| 27 | Modificar | `resources/views/partials/comparacion-pc-script.blade.php` | frontend |
| 28 | Modificar | `resources/views/habitats/partials/modal-formacion.blade.php` | frontend |
| 29 | Modificar | `resources/views/habitats/show.blade.php` | frontend |
| 30 | Modificar | `resources/views/gimnasios/show.blade.php` | frontend |

No se crean migraciones, seeders, factories ni tests. No se añaden dependencias.

## Base de datos

- **Tablas de lectura:** `teams`, `team_members`, `reclutados`, `pokemon`, `pokemon_stats`, `habitats`, pivote hábitat-pokémon, `gyms`, `gym_stages`, log de entrenadores.
- **Sesión:** tabla de sesiones de Laravel (driver `database`) para el snapshot y la batalla. Sin cambios de esquema.
- **Constraints/índices:** ninguno nuevo.
- **Migraciones:** ninguna.
- **Transacciones:** ninguna escritura de negocio; solo sesión.

## Verificación

### Análisis estático

```bash
php -l <cada archivo PHP nuevo/modificado>
composer analyse
composer arch
vendor/bin/pint --dirty --format agent
php artisan route:list --path=ruta
php artisan route:list --path=pc
```

- `composer analyse`: sin errores nuevos.
- `composer arch`: sin violaciones nuevas.
- Pint: estilo.
- `route:list`: confirmar `GET /pc` en los tres contextos y `DELETE /ruta/preview/{previewId}` bajo `auth`.

### Comprobaciones de alcance

```bash
grep -R "BattleStats\|PoliticaIvsEvs\|CalculadorCP\|DistribuidorStatsValor" resources/
grep -R "speciesId\|species_id\|nombre\|tipos\|moves\|nivel_stats" app/Support/Pc/
```

La primera no debe contener fórmulas; la segunda no debe aparecer en DTOs serializados.

### Revisión manual (sin tests)

1. Ruta con equipo de 3: leyenda siempre visible, cuatro perfiles, perfil actual con “Este combate”.
2. HP calculado de cada “Rival N” coincide con la barra de HP máxima del rival en el combate iniciado con el mismo `preview_id`.
3. Cambiar de equipo: el rival de ruta no cambia.
4. Cambiar de nivel: se fija un rival nuevo y la leyenda se actualiza.
5. Cerrar y reabrir: rival nuevo; el anterior se limpia.
6. Cancelar: `preview_id` borrado; un combate posterior sin id genera rival nuevo.
7. Gimnasio sin equipo: leyenda visible; al seleccionar equipo aparece la comparación.
8. Entrenador: leyenda y PC coinciden con el rival determinista del día.
9. JSON sin nombres, especies, tipos, movimientos, formación ni `nivel_stats`.
10. `preview_id` ajeno o de otro hábitat/nivel: se ignora y se devuelve uno nuevo.
11. Equipo incompleto y sin rival: leyenda visible; comparación con su estado.
12. Accesibilidad: foco en regiones desplazables, `caption`, `scope`, `aria-current`, texto además de color.
13. Scroll vertical del modal y botones visibles.
14. Sin cambios de `SESSION_VERSION` ni esquema.

## Riesgos y mitigaciones

| Severidad | Riesgo | Mitigación |
|---|---|---|
| Alto | Snapshot corrupto o cambio de formato entre despliegues | Versión, TTL, `instanceof`, `try/catch`, borrado del inválido |
| Alto | Subida de nivel entre preview y combate | `conNivel` conserva composición y recalcula stats |
| Alto | El rival se regenera si el `preview_id` no llega al combate | El frontend lo envía; si falta, fallback al generador |
| Medio | Sesión caduca con el modal abierto | TTL 1 h; refresco al cambiar equipo/nivel; fallback |
| Medio | Múltiples pestañas | Cada `preview_id` es independiente |
| Medio | Fingerprinting de especies por stats base | Producto lo aprobó; no se añaden nombres/tipos/movimientos |
| Medio | Inferencia del nivel de gimnasio por stats calculados | No se expone `nivel_stats`; se documenta |
| Medio | GET `/pc` con efecto de sesión | Efecto idempotente por `preview_id`, solo sesión, TTL y limpieza |
| Medio | Tablas en `max-w-md` | `text-xs`, `tabular-nums`, scroll horizontal accesible y vertical del modal |
| Bajo | Copy duplicado | El builder genera etiquetas y descripciones |
| Bajo | Código muerto de la aproximación | Eliminar `MuestraPcRival`, `promedioPonderado`, `especiesPonderadas`, `EspeciePonderadaRuta` |
| Bajo | Confusión Nulo vs Top 1 | Tabla con “Stats al máximo” y descripción explícita |
| Bajo | Confusión de orden con formación | Nota “El orden de los rivales no indica su posición en combate” |

## ADR borrador

- **Título:** Fijación y previsualización anónima del rival de ruta 3v3 en “Configurar Formación”.
- **Estado:** Propuesto.
- **Contexto:** El rival de ruta se generaba de nuevo al combatir y la preview de PC era una media del pool. Producto exige mostrar los stats anónimos del rival exacto y reutilizarlo en el combate. La leyenda debe estar siempre visible, también en gimnasios sin equipo seleccionado. No se pueden cambiar fórmulas, balance, IV/EV ni esquema.
- **Decisión:** Generar el rival de ruta una vez al abrir el modal y guardarlo como snapshot serializado en sesión bajo un `preview_id` con TTL. Ampliar los endpoints GET de preview con el bloque aditivo `perfil_ivs_evs`; en gimnasio, `team_id` es opcional. `IniciarCombateRuta` consume el snapshot; si no existe, cae al generador actual. Los stats se recalculan con `conNivel` si cambia el nivel del jugador. La leyenda se construye en backend con `PoliticaIvsEvs`/`BattleStats` y se pinta en un partial compartido. Se limpia al combatir y al cancelar.
- **Consecuencias:** El rival de ruta preview y el combate coinciden en especie, shiny, movimientos e IV/EV. Aparece estado de sesión con TTL y un endpoint DELETE de limpieza. El PC de ruta deja de ser aproximado. La leyenda revela stats base y permite inferir especies/nivel de gimnasio; producto lo acepta. Se elimina código muerto de la aproximación. No hay cambios de esquema, dependencias, fórmulas ni tests.

## Preguntas abiertas (a resolver en el plan/aprobación)

1. Confirmar que el PC de ruta deja de ser una media del pool y pasa a ser el del rival fijado (`metodo: rival_fijado`, `aproximado: false`).
2. Confirmar que `GET /pc` de ruta puede crear el snapshot de sesión en la primera llamada, o si se prefiere `POST /preview`.
3. Aprobar la eliminación de `MuestraPcRival`, `CalculadoraPcRival::promedioPonderado`, `GeneradorEquipoRuta::especiesPonderadas`, `EspeciePonderadaRuta` y `MetodoPcRival::PROMEDIO_POOL`.
4. Confirmar el copy definitivo: “Sin IV/EV”, “Top 1”, “Top 2”, “Todo al máximo”, “Rival 1..3”, “Este combate”.
5. Confirmar que, si el jugador sube de nivel, se conserva la composición y se recalculan stats (`conNivel`).
6. Confirmar que no se expone `nivel_stats` y se acepta la inferencia del nivel de gimnasio.
7. Confirmar que mostrar stats base del rival es aceptable pese al fingerprinting.
8. Confirmar TTL de 1 hora.
9. Confirmar que la leyenda va después de la comparación de PC, dentro del cuerpo desplazable.
10. Decidir si se añade `preview_id` a la meta de batalla para trazabilidad.
