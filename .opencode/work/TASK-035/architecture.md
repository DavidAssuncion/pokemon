# TASK-035 — Arquitectura: comparación aproximada de PC en "Configurar Formación"

- **Fecha:** 2026-11-09
- **Rama:** `task-011`
- **Estado:** DESIGN (sin implementación)
- **Alcance:** ruta 3v3, entrenadores de hábitat y gimnasios
- **Restricciones:** sin migraciones, sin dependencias nuevas, sin cambios de fórmula/balance, sin tests, sin duplicar CP en JS

## DECISIÓN

Implementar la comparación de PC **íntegramente en backend**, con un cálculo compartido de PC
reutilizando `CalculadorCP` + `BattleStats`, y **tres casos de uso de lectura** (uno por contexto)
que reutilizan los generadores existentes. El frontend solo presenta el resultado.

- **PC propio:** suma del CP de los 3 miembros, calculado al nivel del usuario con sus EVs/IVs reales.
  Se reutiliza exactamente la misma ruta de código que `/equipos`.
- **PC rival ruta:** media ponderada del pool (`capture_rate/hatch`) por slot × 3, etiquetada `aproximado`.
- **PC rival entrenador:** suma exacta del equipo determinista que producirá el combate.
- **PC rival gimnasio:** suma exacta del roster de la etapa actual; se devuelve **solo el agregado**.
- **Veredicto:** `ratio = pc_rival / pc_equipo`; `< 0.8` favorable, `0.8–1.2` parejo, `> 1.2` desfavorable.
  Umbrales en un único Value Object (`UmbralesVeredictoPc`).
- **Contrato:** GET de solo lectura por contexto, `team_id` validado por propietario, respuesta con
  `estado` explícito (`ok`, `equipo_incompleto`, `sin_rivales`, `no_disponible`) y **nunca** composición,
  especies, niveles individuales, tipos, IV/EV ni movimientos del rival.

## CONTEXTO

Hechos verificados en el análisis y en el código:

- El CP canónico ya existe: `src/Pokemon/Domain/Stats/CalculadorCP.php:28-40`; los stats de combate en
  `src/Pokemon/Domain/Stats/BattleStats.php:54-81`. `/equipos` ya lo calcula en
  `app/Support/ReclutadoSerializer.php:359-377`.
- Los modales no reciben `stats`, nivel calculado ni CP: `resources/views/habitats/partials/modal-formacion.blade.php:16-39`
  y `resources/views/gimnasios/show.blade.php:188-218`. El estado Alpine recibe `teams: @json($teams)`
  (`habitats/show.blade.php:50`, `gimnasios/show.blade.php:259`).
- Ruta: rival aleatorio en cada inicio; el generador documenta selección ponderada con reemplazo
  (`src/CombateRuta/App/GeneradorEquipoRuta.php:23-38,80-95,133-176`). Solo cabe esperanza matemática.
- Entrenador: semilla `crc32("{$habitatId}|{$nivel}|{$entrenadorIndex}|{$fecha}")`
  (`src/CombateEntrenadores/App/GeneradorEquipoEntrenador.php:76-78,142-175`). Rival reproducible.
- Gimnasio: catálogo `gyms/gym_stages`, nivel escalado (`EscaladorNivelRival`) y perfil IV/EV
  (`PoliticaIvsEvs::perfilGimnasio`). El detalle oculta expresamente equipo y nivel rival
  (`src/Gimnasios/App/ObtenerGimnasioDetalle.php:14-17`).
- En los tres `IniciarCombate*` el jugador combate al nivel de `User::nivel()`; el rival de ruta y
  entrenador también usa ese nivel; el de gimnasio usa `EscaladorNivelRival`.
- La formación vanguardia/retaguardia no altera stats: solo posición/targeting
  (`ConstruirEquipoJugador.php:79,154-167`). El PC no debe variar al cambiar formación.

## OPCIONES EVALUADAS

1. **Backend con primitivas compartidas + caso de uso por contexto (elegida).**
   - Una sola fórmula, tres orquestadores que reutilizan su generador.
   - Evita un servicio “Dios” que conozca los tres contextos.
   - Permite evolucionar ruta, entrenador y gimnasio por separado.
2. **Un único `App\Support\CalculadoraPcFormacion` con métodos `ruta()`, `entrenador()`, `gimnasio()`.**
   - Menos cableado, pero acopla el seam compartido a los tres generadores y a tres modelos de
     disponibilidad distintos. Difícil de razonar y de cambiar sin efectos cruzados. Descartada.
3. **Cálculo completo por módulo, sin primitivas compartidas.**
   - Duplica la suma de EVs, la conversión `DatosStats→StatsValue` y el comparador. Riesgo alto de
     divergencia entre contextos y con `/equipos`. Descartada.
4. **Cálculo en frontend con `@json($teams)`.**
   - Faltan `pokemon.stats` y nivel; obligaría a duplicar `BattleStats`/`CalculadorCP` en JS.
     Descartada por seguridad y doble fuente de verdad.
5. **Guardar el rival de ruta antes de confirmar para dar PC exacto.**
   - Cambia el flujo de batalla/sesión y contradice “el rival se decide al combatir”. Descartada para MVP.
6. **Mostrar solo bandas cualitativas sin números.**
   - Menos informativo; el producto ya aprobó totales + veredicto. Descartada.

## DISEÑO ELEGIDO

### 1. Principios de ubicación

- **Dominio puro** (`Src\Shared\Domain\Pc`, `Src\Pokemon\Domain\Stats`): enums, umbrales, comparador,
  DTO de comparación y la extensión de `CalculadorCP`. Sin Eloquent, HTTP ni framework.
- **Seam compartido** (`App\Support\Pc`): carga del equipo propio (Eloquent), mapeo de stats base,
  cálculo de CP de reclutados y de rivales. Es el punto único para que `/equipos` y el modal coincidan.
- **Aplicación por contexto** (`src/*/App`): un caso de uso de lectura por contexto que reutiliza su
  generador y compone el resultado.
- **Infraestructura** (`src/*/Infra/Controllers`, `routes/*.php`): validación HTTP y serialización.
- **Presentación** (`resources/views`): partial Blade + mixin Alpine; sin fórmulas ni decisiones.
- **No** se toca `TeamAggregate`, ni `$teams`, ni los contratos de inicio de combate.

Justificación del seam en `App\Support` y no en un módulo: el cálculo del equipo propio cruza los tres
contextos y necesita Eloquent. Ponerlo en `src/Equipos/App` forzaría dependencias
Aplicación→Aplicación entre contextos (deptrac solo permite Aplicación→Dominio). `App\Support` es
infraestructura de aplicación y ya es el patrón usado por `CreadorBatallaSesion`,
`CargarMovimientosDesbloqueados` y `ReclutadoSerializer`.

### 2. Algoritmo

#### 2.1 Extensión de dominio (una sola fórmula)

En `CalculadorCP` se añade un método que centraliza la construcción de `BattleStats` y la suma de EVs:

```php
public static function desdeStats(
    StatsValue $base,
    StatsValue $evs,
    ?StatsValue $ivs,
    int $nivel,
): int {
    $stats = new BattleStats($base, $evs, $nivel, $ivs);
    $evTotal = (int) array_sum($evs->toArray());

    return self::calcular($stats, $evTotal);
}
```

No se modifica `calcular()`. `ReclutadoSerializer` pasa a delegar aquí para que `/equipos` y el modal
sean inseparables.

#### 2.2 PC del equipo propio

```text
fn pcEquipoJugador(teamId, userId, nivelJugador):
    team = Team::with('members.reclutado.pokemon.stats')
                ->where('user_id', userId)->find(teamId)
    si team == null → {miembros: 0, pc: null, completo: false}

    miembros = 0; total = 0
    para cada member ordenado por slot:
        r = member.reclutado; p = r?.pokemon
        si r == null o p == null → continuar
        base = EstadisticasBasePokemon::desdePokemon(p)   // null si no hay stats
        si base == null → continuar
        total += CalculadorCP::desdeStats(base, r.evs, r.ivs, nivelJugador)
        miembros++

    completo = (miembros === ReglasEquipo::MIEMBROS)   // 3
    return {miembros, pc: completo ? total : null, completo}
```

Casos límite:
- Equipo inexistente o de otro usuario → `miembros=0`, `pc=null`; el controlador ya devuelve 422 por
  `Rule::exists(...)->where('user_id', Auth::id())`, así que esto es defensa en profundidad.
- 2 miembros, 4 miembros o un miembro sin `pokemon/stats` → `completo=false`, `pc=null`.
- `pc=0` con 3 miembros (datos degenerados) → se trata como `equipo_incompleto` para no dividir por cero.

#### 2.3 PC rival exacto (entrenador y gimnasio)

Se reutiliza el generador tal cual y se calcula el CP desde `DatosPokemonBatallaCollection`. Sus campos
`hp/atk/def/spAtk/spDef/speed` son stats base y `nivel/evs/ivs` son los del combate real.

```text
fn pcRival(datos, nivelFallback):
    si datos.isEmpty() → null
    total = 0
    para cada d en datos:
        base = StatsValue(hp: d.hp, attack: d.atk, defense: d.def,
                          spAtk: d.spAtk, spDef: d.spDef, speed: d.speed)
        total += CalculadorCP::desdeStats(base, d.evs, d.ivs, d.nivel ?? nivelFallback)
    return total
```

- **Entrenador:** `perfil = PoliticaIvsEvs::perfilEntrenador(nivel)`;
  `generar(habitatId, nivel, trainerIndex, fecha, nivelJugador, perfil: perfil, movimientosDesbloqueados: [])`.
  `fecha` se calcula en servidor (`today()->toDateString()`), nunca se acepta del cliente. No pasar
  movimientos no altera la selección de especies; evita trabajo innecesario.
- **Gimnasio:** `etapa = progreso ?? 1` (servidor), `nivelRival = escalador->escalar(nivelMinimo, nivelJugador)`,
  `indice = catalogo->indiceDe(slug)`, `perfil = perfilGimnasio(indice, etapa === 4)`,
  `generar(equipoEtapa, nivelRival, perfil, indice, esLider: etapa===4, movimientosDesbloqueados: [])`.
  Si `equipoEtapa` es null o el resultado está vacío → `sin_rivales`.

#### 2.4 PC rival aproximado (ruta)

El generador expone de forma aditiva las especies del pool con su peso, reutilizando sus métodos
privados `poolDelHabitat()` y `ponderar()` (sin duplicar la fórmula de pesos):

```php
/** @return list<EspeciePonderadaRuta> */
public function especiesPonderadas(int $habitatId, int $nivel): array
```

`EspeciePonderadaRuta` es un DTO de dominio con `DatosStats $stats` y `float $peso` (sin Eloquent).

```text
fn pcRuta(habitatId, nivel, nivelJugador):
    perfil = PoliticaIvsEvs::perfilRuta(nivel)
    muestras = []
    para cada especie en generador.especiesPonderadas(habitatId, nivel):
        base = StatsValue::desdeArray(especie.stats.toArray())
        ivs  = PoliticaIvsEvs::ivs(perfil, especie.stats)
        evs  = PoliticaIvsEvs::evs(perfil, especie.stats)
        muestras.add(MuestraPcRival(base, evs, ivs, nivelJugador, especie.peso))

    si muestras vacío o sumaPesos <= 0 → null
    esperadoPorSlot = Σ (cp_i * peso_i / sumaPesos)
    return round(esperadoPorSlot * ReglasEquipo::MIEMBROS)
```

Justificación: la selección es con reemplazo e independiente por slot, por lo que el valor esperado del
equipo es `3 × E[CP]`. No se usa `GET /ruta/rivales` (deprecated y revela composición).

#### 2.5 Veredicto

```text
fn comparar(pcEquipo, pcRival, umbrales):
    si pcEquipo == null o pcRival == null o pcEquipo <= 0 → null
    ratio = round(pcRival / pcEquipo, 2)
    si ratio < umbrales.favorableMaximo (0.8)   → FAVORABLE
    si ratio > umbrales.desfavorableMinimo (1.2) → DESFAVORABLE
    return PAREJO
```

### 3. Contratos HTTP

Todos bajo el grupo `auth` de `routes/web.php` (los `routes/*.php` se incluyen dentro). GET de solo
lectura, sin CSRF.

#### 3.1 Ruta

```http
GET /api/habitats/{habitat}/ruta/pc?team_id={int}&nivel={1|2|3}
```

Validación: `team_id` requerido, entero y `Rule::exists('teams','id')->where('user_id', Auth::id())`;
`nivel` opcional 1–3 (por defecto 1); `habitat` entero y existente en `habitats`.

Respuesta `200`:

```json
{
  "estado": "ok",
  "equipo": { "miembros": 3, "completo": true, "pc": 1240 },
  "rival": { "pc": 1050, "aproximado": true, "metodo": "promedio_pool" },
  "comparacion": { "ratio": 0.85, "veredicto": "parejo" },
  "contexto": { "tipo": "ruta", "habitat_id": 12, "nivel": 2 }
}
```

#### 3.2 Entrenador de hábitat

```http
GET /api/habitats/{habitat}/entrenadores/{nivel}/{trainer}/pc?team_id={int}
```

Validación: `nivel` y `trainer` enteros 1–3; `team_id` como arriba. `fecha` se resuelve en servidor.

Respuesta `200`:

```json
{
  "estado": "ok",
  "equipo": { "miembros": 3, "completo": true, "pc": 1240 },
  "rival": { "pc": 1320, "aproximado": false, "metodo": "generador_determinista" },
  "comparacion": { "ratio": 1.06, "veredicto": "parejo" },
  "contexto": { "tipo": "entrenador", "habitat_id": 12, "nivel": 2, "entrenador_indice": 1 }
}
```

#### 3.3 Gimnasio

```http
GET /api/gimnasios/{gym}/pc?team_id={int}
```

La etapa se calcula siempre en servidor (`GymProgressRepositoryInterface::obtenerProgreso() ?? 1`).
No se acepta `etapa` del cliente.

Respuesta `200`:

```json
{
  "estado": "ok",
  "equipo": { "miembros": 3, "completo": true, "pc": 1240 },
  "rival": { "pc": 1600, "aproximado": false, "metodo": "catalogo_etapa" },
  "comparacion": { "ratio": 1.29, "veredicto": "desfavorable" },
  "contexto": { "tipo": "gimnasio", "gym": "brock" }
}
```

No se devuelve `nivel_rival`, ni etapa, ni número de rivales, ni especies.

#### 3.4 Estados y códigos

| Situación | HTTP | Cuerpo / `estado` |
|---|---|---|
| No autenticado | 401 | Laravel |
| `team_id` ausente, no entero, ajeno o inexistente | 422 | `{message, errors}` |
| `nivel`/`trainer` fuera de 1–3 | 422 | `{message, errors}` |
| `habitat` inexistente | 422 | `{message, errors}` |
| `gym` inexistente | 404 | `GimnasioNoExiste` (render global) |
| Equipo con ≠3 miembros válidos | 200 | `estado: "equipo_incompleto"`, `equipo.pc: null` |
| Pool/rival vacío o catálogo sin etapa | 200 | `estado: "sin_rivales"`, `rival.pc: null` |
| Entrenador ya derrotado hoy | 200 | `estado: "no_disponible"` |
| Gimnasio completado o bloqueado | 200 | `estado: "no_disponible"` |
| Error interno | 500 | Laravel |

Todos los estados de dominio se devuelven como `200` con `estado` para que el frontend no bloquee el
combate por un fallo del indicador. Los 422/404 quedan reservados a peticiones mal formadas o recursos
inexistentes.

### 4. Contratos de clases

```php
// Src\Shared\Domain\Pc
enum EstadoComparacionPc: string { case OK='ok'; case EQUIPO_INCOMPLETO='equipo_incompleto'; case SIN_RIVALES='sin_rivales'; case NO_DISPONIBLE='no_disponible'; }
enum MetodoPcRival: string { case PROMEDIO_POOL='promedio_pool'; case GENERADOR_DETERMINISTA='generador_determinista'; case CATALOGO_ETAPA='catalogo_etapa'; }
enum VeredictoPc: string { case FAVORABLE='favorable'; case PAREJO='parejo'; case DESFAVORABLE='desfavorable'; }

final readonly class UmbralesVeredictoPc
{
    public const FAVORABLE_MAXIMO = 0.8;
    public const DESFAVORABLE_MINIMO = 1.2;

    public function __construct(
        public float $favorableMaximo = self::FAVORABLE_MAXIMO,
        public float $desfavorableMinimo = self::DESFAVORABLE_MINIMO,
    ) {}
}

final class ComparadorPc
{
    public function comparar(?int $pcEquipo, ?int $pcRival, ?UmbralesVeredictoPc $umbrales = null): ?VeredictoPc;
    public function ratio(?int $pcEquipo, ?int $pcRival): ?float;
}

final readonly class ComparacionPc
{
    public function __construct(
        public EstadoComparacionPc $estado,
        public int $miembrosEquipo,
        public ?int $pcEquipo,
        public bool $equipoCompleto,
        public ?int $pcRival,
        public bool $rivalAproximado,
        public ?MetodoPcRival $metodoRival,
        public ?float $ratio,
        public ?VeredictoPc $veredicto,
    ) {}

    public static function equipoIncompleto(int $miembros, MetodoPcRival $metodo, bool $aproximado): self;
    public static function sinRival(int $pcEquipo, int $miembros, MetodoPcRival $metodo, bool $aproximado): self;
    public static function noDisponible(MetodoPcRival $metodo): self;
    public static function ok(int $miembros, int $pcEquipo, int $pcRival, MetodoPcRival $metodo, bool $aproximado, ?float $ratio, ?VeredictoPc $veredicto): self;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
```

```php
// App\Support\Pc
final class EstadisticasBasePokemon
{
    public static function desdePokemon(?Pokemon $pokemon): ?StatsValue;
}

final class CalculadoraPcReclutado
{
    public static function calcular(Reclutado $reclutado, int $nivel): ?int;
}

final class ObtenerPcEquipoJugador
{
    public function obtener(int $teamId, int $userId, int $nivelJugador): PcEquipoJugador;
}

final readonly class PcEquipoJugador
{
    public function __construct(public int $miembros, public ?int $pc, public bool $completo) {}
}

final readonly class MuestraPcRival
{
    public function __construct(
        public StatsValue $base,
        public StatsValue $evs,
        public StatsValue $ivs,
        public int $nivel,
        public float $peso,
    ) {}
}

final class CalculadoraPcRival
{
    public function desdeDatosBatalla(DatosPokemonBatallaCollection $equipo, int $nivelFallback): ?int;
    /** @param list<MuestraPcRival> $muestras */
    public function promedioPonderado(array $muestras, int $slots): ?int;
}
```

Casos de uso:

```php
Src\CombateRuta\App\CalcularComparacionPcRuta::calcular(
    int $habitatId, int $nivel, int $teamId, int $userId, int $nivelJugador
): ComparacionPc;

Src\CombateEntrenadores\App\CalcularComparacionPcEntrenador::calcular(
    int $habitatId, int $nivel, int $trainerIndex, int $teamId,
    int $userId, int $nivelJugador, string $fecha
): ComparacionPc;

Src\Gimnasios\App\CalcularComparacionPcGimnasio::calcular(
    string $slug, int $teamId, int $userId, int $nivelJugador
): ComparacionPc;
```

El DTO `EspeciePonderadaRuta` (`DatosStats $stats`, `float $peso`) vive en
`Src\CombateRuta\Domain\DataTransferObjects` y no depende de Eloquent.

Excepciones: no se crean nuevas. Se reutilizan las globales:
`GimnasioNoExiste` (404), `RecursoNoExiste` (404) y `ViolacionReglaNegocio` (422) si hiciera falta.
Los estados de disponibilidad no lanzan excepción: se modelan con `EstadoComparacionPc`.

### 5. Integración Blade/Alpine

**Punto de inserción**
- `resources/views/habitats/partials/modal-formacion.blade.php`: después del bloque de miembros
  (línea 39), antes de `<!-- Buttons -->` (línea 41): `@include('partials.comparacion-pc')`.
- `resources/views/gimnasios/show.blade.php`: después del bloque de formación (línea 218), antes de
  `{{-- Botones --}}` (línea 220): `@include('partials.comparacion-pc')`.

**Partial compartido** `resources/views/partials/comparacion-pc.blade.php`
- `<section x-show="selectedTeamId" role="group" aria-labelledby="pc-comparacion-titulo">`.
- Contenedor con `aria-live="polite"` y `aria-atomic="true"`, `:aria-busy="pcCargando"`.
- Cargador con `role="status"` y texto “Calculando PC...”.
- Resultado en `<dl><dt>PC de tu equipo</dt><dd>…</dd>…</dl>`.
- Veredicto con **texto + icono** (`▲`/`＝`/`▼`, `aria-hidden="true"`), nunca solo color.
- `≈` acompañado de `<span class="sr-only">Aproximadamente </span>`.
- Spinner con `motion-reduce:animate-none`; sin animaciones de barras.
- Copy por estado:
  - `ok` ruta: “PC de tu equipo: 1.240”, “PC estimado del rival: ≈ 1.050”, nota
    “Estimación basada en los Pokémon del hábitat; el rival se decide al combatir.”
  - `ok` entrenador: “PC del rival: 1.320”.
  - `ok` gimnasio: “PC estimado del rival: 1.600” + “Cálculo aproximado de la etapa actual. No se muestra su equipo.”
  - `equipo_incompleto`: “El equipo necesita 3 miembros para combatir.”
  - `sin_rivales`: “No hay rival disponible en este nivel.”
  - `no_disponible`: “Este combate ya no está disponible.”
  - error: “No se pudo calcular la comparación. Puedes combatir igualmente.”
  - sin equipo: “Selecciona un equipo para ver la comparación.”

**Mixin Alpine compartido** `resources/views/partials/comparacion-pc-script.blade.php`
Define una sola vez `function comparadorPcState()` y se incluye en `@push('scripts')` antes del script
de la página. Ambos `x-data` lo incorporan con spread: `...comparadorPcState(),`.

Estado y métodos:
- `pcComparacion`, `pcCargando`, `pcError`, `pcSolicitud`.
- `cargarComparacionPc(url)`: `fetch` GET con `Accept: application/json`; token incremental para
  descartar respuestas obsoletas; no lanza errores al usuario; no bloquea el botón de combate.
- `limpiarComparacionPc()`.
- `formatoPc(valor)` con `Intl.NumberFormat('es-ES')`.
- `textoVeredicto`, `iconoVeredicto`, `claseVeredicto`, `etiquetaPcRival`.

Disparadores:
- Hábitats: `openRutaFormacionPopup()`, `openFormacionPopup()`, `selectTrainer()`, `selectTeam()` si el
  popup está abierto, y `selectRutaNivel()` si el popup está abierto en contexto ruta.
- Gimnasio: `selectTeam()` tras fijar la formación; `openCombatPopup()` limpia el estado.
- `closeFormacionPopup()` limpia en ambos.
- `toggleFormacionSlot()` **no** refetch: la formación no altera el PC.
- `confirmarCombate()` no cambia: un fallo del indicador nunca impide combatir.

URLs:
- Ruta: `/api/habitats/{id}/ruta/pc?team_id=…&nivel=…`
- Entrenador: `/api/habitats/{id}/entrenadores/{nivel}/{indice}/pc?team_id=…`
- Gimnasio: `/api/gimnasios/{slug}/pc?team_id=…`

### 6. Seguridad

- `team_id` validado en el controlador con `Rule::exists('teams','id')->where('user_id', Auth::id())`
  y revalidado en `ObtenerPcEquipoJugador` con `where('user_id', $userId)`.
- Parámetros de ruta numéricos y rangos 1–3; `habitat` existente; `gym` resuelto por repositorio (404).
- Etapa de gimnasio y `fecha` de entrenador siempre en servidor.
- La respuesta solo contiene enteros agregados y metadatos de contexto ya visibles. Nunca especies,
  tipos, niveles individuales, IV/EV, movimientos, formación rival ni `nivel_rival`.
- GET de solo lectura; no requiere CSRF. Sin escrituras ni transacciones.
- No se registran datos sensibles en logs.
- El endpoint no se cachea en caché compartida. Si se añade caché, clave por
  `user_id + team_id + nivel_jugador + contexto + fecha` con TTL corto.

### 7. Rendimiento

- Una petición por apertura de modal o cambio de equipo/nivel.
- Ruta: carga el pool y calcula CP por especie; no genera movimientos.
- Entrenador/gimnasio: reutilizan el generador completo, incluida la selección de movimientos. Es la
  opción de menor riesgo y garantiza que el rival previsualizado sea exactamente el del combate.
  Si la latencia es alta, la mitigación es cachear o extraer en otra tarea una selección de especies
  sin movimientos; no se hace en este MVP.

## MAPA DE CAPAS

- **Dominio:**
  - `Src\Shared\Domain\Pc\*` (enums, umbrales, comparador, DTO).
  - `Src\Pokemon\Domain\Stats\CalculadorCP::desdeStats`.
  - `Src\CombateRuta\Domain\DataTransferObjects\EspeciePonderadaRuta`.
- **Aplicación:**
  - `Src\CombateRuta\App\CalcularComparacionPcRuta`.
  - `Src\CombateEntrenadores\App\CalcularComparacionPcEntrenador`.
  - `Src\Gimnasios\App\CalcularComparacionPcGimnasio`.
  - `Src\CombateRuta\App\GeneradorEquipoRuta::especiesPonderadas` (aditivo).
- **Infraestructura / seam de aplicación:**
  - `App\Support\Pc\EstadisticasBasePokemon`, `CalculadoraPcReclutado`, `ObtenerPcEquipoJugador`,
    `PcEquipoJugador`, `CalculadoraPcRival`, `MuestraPcRival`.
  - Controladores `CombateRutaController`, `EntrenadorController`, `GimnasioController`.
- **Presentación:**
  - `resources/views/partials/comparacion-pc.blade.php`,
    `resources/views/partials/comparacion-pc-script.blade.php`.
  - Estados Alpine en `resources/views/habitats/show.blade.php` y `resources/views/gimnasios/show.blade.php`.
  - Inclusión en `resources/views/habitats/partials/modal-formacion.blade.php` y
    `resources/views/gimnasios/show.blade.php`.

Dirección de dependencias: Presentación → Aplicación/Infraestructura → Dominio. El dominio nuevo no
importa Eloquent, HTTP, Blade ni framework. `App\Support` no es dominio.

## FLUJO DE DATOS

1. El jugador abre “Configurar Formación” con un equipo seleccionado (hábitats) o selecciona equipo
   dentro del modal (gimnasio).
2. Alpine construye la URL del contexto y llama a `cargarComparacionPc(url)`; muestra “Calculando PC...”.
3. El controlador valida `team_id`, parámetros y existencia del recurso; obtiene `Auth::user()->nivel()`.
4. El caso de uso carga el equipo propio por `user_id` y calcula su PC con `CalculadorCP::desdeStats`.
5. Según contexto:
   - Ruta: `GeneradorEquipoRuta::especiesPonderadas()` → perfil de ruta → media ponderada × 3.
   - Entrenador: `GeneradorEquipoEntrenador::generar()` con fecha del servidor → suma de CP.
   - Gimnasio: etapa del servidor → `GeneradorPokemonGimnasio::generar()` → suma de CP.
6. Se compone `ComparacionPc` (estado, totales, ratio, veredicto) y se serializa a JSON.
7. Alpine recibe el JSON, descarta respuestas obsoletas y actualiza `aria-live`.
8. El botón “Combatir” sigue habilitado según las reglas existentes; el indicador nunca lo bloquea.

## PLAN DE ARCHIVOS

| Orden | Acción | Archivo | Responsable |
|---|---|---|---|
| 1 | Crear | `src/Shared/Domain/Pc/EstadoComparacionPc.php` | backend |
| 2 | Crear | `src/Shared/Domain/Pc/MetodoPcRival.php` | backend |
| 3 | Crear | `src/Shared/Domain/Pc/VeredictoPc.php` | backend |
| 4 | Crear | `src/Shared/Domain/Pc/UmbralesVeredictoPc.php` | backend |
| 5 | Crear | `src/Shared/Domain/Pc/ComparadorPc.php` | backend |
| 6 | Crear | `src/Shared/Domain/Pc/ComparacionPc.php` | backend |
| 7 | Modificar | `src/Pokemon/Domain/Stats/CalculadorCP.php` (añadir `desdeStats`) | backend |
| 8 | Crear | `app/Support/Pc/EstadisticasBasePokemon.php` | backend |
| 9 | Crear | `app/Support/Pc/CalculadoraPcReclutado.php` | backend |
| 10 | Crear | `app/Support/Pc/ObtenerPcEquipoJugador.php` | backend |
| 11 | Crear | `app/Support/Pc/PcEquipoJugador.php` | backend |
| 12 | Crear | `app/Support/Pc/MuestraPcRival.php` | backend |
| 13 | Crear | `app/Support/Pc/CalculadoraPcRival.php` | backend |
| 14 | Modificar | `app/Support/ReclutadoSerializer.php` (delegar `cpDe` y `statsBaseValue`, sin cambiar contrato) | backend |
| 15 | Crear | `src/CombateRuta/Domain/DataTransferObjects/EspeciePonderadaRuta.php` | backend |
| 16 | Modificar | `src/CombateRuta/App/GeneradorEquipoRuta.php` (añadir `especiesPonderadas`) | backend |
| 17 | Crear | `src/CombateRuta/App/CalcularComparacionPcRuta.php` | backend |
| 18 | Crear | `src/CombateEntrenadores/App/CalcularComparacionPcEntrenador.php` | backend |
| 19 | Crear | `src/Gimnasios/App/CalcularComparacionPcGimnasio.php` | backend |
| 20 | Modificar | `src/CombateRuta/Infra/Controllers/CombateRutaController.php` (método `pc`) | backend |
| 21 | Modificar | `src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php` (método `pc`) | backend |
| 22 | Modificar | `src/Gimnasios/Infra/Controllers/GimnasioController.php` (método `pc`) | backend |
| 23 | Modificar | `routes/ruta.php` | backend |
| 24 | Modificar | `routes/entrenadores.php` | backend |
| 25 | Modificar | `routes/gimnasios.php` | backend |
| 26 | Crear | `resources/views/partials/comparacion-pc.blade.php` | frontend |
| 27 | Crear | `resources/views/partials/comparacion-pc-script.blade.php` | frontend |
| 28 | Modificar | `resources/views/habitats/partials/modal-formacion.blade.php` (incluir partial) | frontend |
| 29 | Modificar | `resources/views/habitats/show.blade.php` (mixin, URLs, disparadores) | frontend |
| 30 | Modificar | `resources/views/gimnasios/show.blade.php` (partial, mixin, URLs, disparadores) | frontend |

No se crean migraciones, seeders, factories ni tests. No se modifican formularios de combate ni
`$teams`.

**Secuenciación con TASK-034/TASK-033:** los pasos 1–25 no tocan vistas de combate y pueden ejecutarse
primero. Los pasos 26–30 deben hacerse después de integrar TASK-034 y comprobar `git status`/`git diff`
de `habitats/show.blade.php` y `gimnasios/show.blade.php`; si TASK-033/034 tienen cambios sin confirmar
en esos archivos, se coordina o se trabaja en worktree separado. No se tocan `app/Livewire/Presenters/*`.

## BASE DE DATOS

- **Tablas:** solo lectura de `teams`, `team_members`, `reclutados`, `pokemon`, `pokemon_stats`,
  `habitats`, pivote hábitat-pokémon, `gyms`, `gym_stages` y el log de entrenadores.
- **Constraints:** no se modifican. Se asume la integridad referencial existente.
- **Índices:** no se crean. Si la latencia del pool o del catálogo es alta, se valorará en otra tarea
  un índice `habitat_pokemon(habitat_id, level)` y cache del catálogo; no en este alcance.
- **Migraciones:** ninguna.
- **Transacciones:** ninguna; todas las consultas son de lectura y no requieren atomicidad.

## VERIFICACIÓN

### Análisis estático

```bash
php -l <cada archivo PHP nuevo/modificado>
composer analyse
composer arch
vendor/bin/pint --test
php artisan route:list --path=pc
```

- `composer analyse`: Larastan/PHPStan sin errores nuevos.
- `composer arch`: deptrac; comprobar que no aparecen violaciones nuevas. `App\Support` no está
  clasificado como capa, por lo que el diseño no introduce Aplicación→Aplicación.
- `vendor/bin/pint --test`: estilo; en implementación se corrige con `--dirty --format agent`.
- `php artisan route:list --path=pc`: confirmar las tres rutas GET y que están bajo `auth`.

### Revisión manual

- `grep -R "CalculadorCP\|BattleStats" resources/` no debe devolver nada: no hay fórmula en JS.
- Inspeccionar un JSON de cada contexto y comprobar que no contiene `nombre`, `species_id`, `tipos`,
  `nivel`, `ivs`, `evs`, `moves` del rival.
- Comparar el PC de un equipo en `/equipos` y en el nuevo endpoint con el mismo usuario/nivel; deben
  coincidir exactamente.
- Cambiar vanguardia/retaguardia y verificar que el PC no cambia.
- Probar estados: equipo de 2, equipo de 3, sin rival, error de red, gimnasio completado, entrenador
  derrotado.
- Revisar accesibilidad: foco, `aria-live`, `role=status`, texto del veredicto sin depender del color,
  `motion-reduce`.
- `git diff --stat` limitado a los archivos del plan.

### Criterios de aceptación

- Al abrir el modal con 3 miembros se muestran ambos totales y el veredicto.
- Ruta muestra `≈` y la nota de estimación; nunca composición.
- Entrenador y gimnasio muestran solo el agregado; gimnasio no revela nivel ni equipo.
- Equipo incompleto, sin rival, cargando y error tienen estado explícito y no bloquean el combate.
- El PC propio coincide con `/equipos`.
- La formación no altera el PC.
- Sin migraciones, dependencias, cambios de fórmula ni tests.

## RIESGOS Y MITIGACIONES

| Severidad | Riesgo | Mitigación |
|---|---|---|
| Alto | Ruta no determinista: el número puede leerse como rival real | `aproximado: true`, `≈`, nota “el rival se decide al combatir”; no usar `ruta/rivales` |
| Alto | El término EV domina el CP y produce comparaciones contraintuitivas | No cambiar la fórmula; veredicto + copy; calibrar umbrales con datos reales |
| Alto | Gimnasio oculta deliberadamente equipo/nivel | Solo agregado; sin `nivel_rival` ni composición; copy “No se muestra su equipo” |
| Medio | Duplicar el mapeo de stats base | Extraer `EstadisticasBasePokemon` y hacer que `ReclutadoSerializer` delegue |
| Medio | Divergencia entre el rival previsualizado y el combate | Reutilizar los generadores reales; no reimplementar selección |
| Medio | Coste de generar movimientos en la preview | Una petición por apertura; cache opcional; futura extracción de selección de especies |
| Medio | `GymCatalogoRepository::indiceDe()` carga todos los gyms | Aceptable en MVP; cache del catálogo si aparece latencia |
| Medio | Cambio de etapa/nivel entre preview y combate | Recalcular siempre en servidor al abrir y al combatir; no confiar en el cliente |
| Medio | Conflicto con TASK-034/TASK-033 en vistas | Backend primero; frontend al final; revisar `git status`/worktree |
| Bajo | `ReclutadoSerializer` cambia y altera `/equipos` | Delegación behavior-preserving; comparar muestras manualmente |
| Bajo | Formato/localización | `Intl.NumberFormat('es-ES')` |
| Bajo | Accesibilidad | `aria-live`, `role=status`, texto + icono, `motion-reduce` |

## BORRADOR DE ADR

- **Título:** Comparación de PC previa al combate en el modal “Configurar Formación”.
- **Estado:** Propuesto.
- **Contexto:** El jugador elige equipo y formación antes de combatir en ruta 3v3, entrenadores de
  hábitat y gimnasios. El CP canónico ya existe y los modales no reciben stats ni nivel. El rival de
  ruta es aleatorio; el de entrenador es determinista por fecha; el de gimnasio sale del catálogo y su
  composición está oculta por diseño.
- **Decisión:** Calcular en backend con `CalculadorCP` + `BattleStats`. Un seam compartido en
  `App\Support\Pc` para el equipo propio y los rivales; tres casos de uso de lectura que reutilizan los
  generadores. Ruta devuelve media ponderada del pool; entrenador y gimnasio, suma exacta. El payload
  solo expone agregados y un veredicto con umbrales en un único Value Object. El frontend solo presenta.
- **Consecuencias:** Una sola fuente de verdad para el CP; no se revela composición; el indicador no
  bloquea el combate. La preview de entrenador/gimnasio reutiliza el generador completo (incluye
  movimientos), lo que añade coste; se mitiga con cache o una extracción futura. Los umbrales podrán
  calibrarse sin tocar los tres contextos. No hay cambios de esquema, balance ni dependencias.

## PREGUNTAS ABIERTAS

1. **Gimnasio y `≈`:** se propone “PC estimado del rival” sin `≈`, con nota “No se muestra su equipo”.
   Confirmar si producto prefiere `≈`.
2. **Redondeo:** se propone entero más cercano y agrupación visual con `Intl`. Confirmar si se prefiere
   redondear a decenas/centenas.
3. **Entrenador derrotado:** se propone `200` con `estado: "no_disponible"` en lugar de `422`.
4. **Equipo en exploración:** la preview no valida exploraciones activas; el combate sí las rechaza.
   ¿Debe la preview reflejar ese estado o basta con el aviso del combate?
5. **PC por miembro:** se muestra solo el total. Confirmar que no se quiere desglose.
6. **Caché:** no se incluye en el MVP. Confirmar si se acepta añadirla si aparece latencia.
7. **Calibración de umbrales:** 0.8/1.2 es una propuesta inicial; validar con datos reales.
