# TASK-002 — Arquitectura

> Resultado del especialista `architect`. Fecha: 2026-11-01.

## Decisión

1. **Bug Dragoaliento**: el dominio pasa a informar el resultado de aplicar estado (`bool`), y la
   UI deja de decidir por el movimiento: **el log y el evento `status` se derivan del delta real de
   `capturarEstado()`**, no de `tieneStatus()` ni de `forzar`.
2. **No se toca `setEstado()`** en esta tarea (sobrescritura de estados): se registra como hallazgo
   separado.
3. **Arnés IA vs IA**: test de integración determinista en `tests/Feature/Battle/` con semilla
   explícita, IVs obligatorias y tope de rondas; campaña de diagnóstico autogateada por variable de
   entorno. **Sin comando artisan** en esta tarea.
4. **Tope de rondas**: parámetro opcional `?int $maxRondas = null` y excepción de dominio
   `LimiteRondasAlcanzado`; *backward-compatible*.
5. **Invariantes**: se comprueban en un observador de ejecución (puerto de dominio inyectado por
   parámetro) tras cada turno y cada ronda, y al final. Algunos invariantes propuestos se degradan a
   diagnóstico porque hoy producen falsos positivos por diseño.

Las decisiones marcadas con ⚠️ requieren aprobación explícita del desarrollador.

## Contexto

Resumen del análisis:

- `GestorEfectosMovimiento::aplicarEstado()` sortea probabilidad y retorna `void`; tiene 9 ramas de
  no aplicación silenciosas (muerto, protegido, tirada, tres velos, Safeguard, inmunidad de
  habilidad; Espejo Mágico en el servicio).
- `ServicioEjecucionBatalla::aplicarEstado()` retorna `void` y añade tres ramas propias:
  `cuerpo_aureo`, flags BALA/SONIDO/POLVO y Espejo Mágico (que aplica el estado **al atacante**).
- `app/Livewire/Combate.php` registra «sufre X» de forma incondicional en dos rutas (`:694-699`
  prioridad, `:930-936` resolución normal) y fuerza el evento `status` con
  `'forzar' => tieneStatus && vivo` (`:938-947`), creando un evento sin `cambios`.
- La Blade pinta la píldora con `evento.texto` y solo anima cuando
  `actual.status !== previo.status`: síntoma exacto del bug.
- El motor no tiene tope de rondas; una batalla sin daño no termina (verificado con `timeout 12`).
- El determinismo es alcanzable porque `mt_srand()` controla `mt_rand()` y `array_rand()` en PHP
  8.4, pero `GeneradorIvs::aleatorios()` usa `random_int()` no sembrable y `CalculadoraDanioIA`
  manipula el estado MT global.
- `FabricaEfectos` sin contenedor está vacía: el arnés debe resolverla con
  `app(FabricaEfectos::class)` bajo `Tests\TestCase`.
- WIP local en `Combatiente`, `DatosPokemonBatalla`, `EquipoBatalla`: **no se toca**.

## Opciones evaluadas

### A. Contrato de `aplicarEstado`

1. `void` + delta solo en UI — corrige el síntoma, pero deja el dominio mudo. Descartada.
2. **`bool`** — mínimo, sin nuevas clases, suficiente para tests. **Elegida.**
3. DTO `ResultadoAplicacionEstado` / enum de motivo — permite mensajes diferenciados, pero no es
   necesario para el bug y amplía superficie. Descartada ahora.

### B. Fuente de verdad del log/evento en Livewire

1. Basarse en el `bool` — falla con Espejo Mágico (cambia el atacante), Sincronía y estados ya
   presentes.
2. **Basarse en `capturarEstado()` (delta real)** — cubre todos los casos. **Elegida.**

### C. Arnés

1. Solo test de integración — rápido, cero producción, campañas largas incómodas.
2. Solo comando artisan — requiere código de diagnóstico en `src/`/`app/`.
3. **Test de integración CI + test de campaña autogateado** — **elegida**.

### D. Tope de rondas

1. Envolver con `pcntl_alarm`/timeout — no portable, no determinista.
2. **Parámetro opcional en `ejecutarBatalla` + excepción** — determinista y *backward-compatible*.
   **Elegida.**
3. Parámetro + `break` silencioso — una batalla truncada se confunde con victoria. Descartada.

### E. Guarda de estado previo

1. Incluirla ahora — cambia reglas de juego y amplía alcance.
2. **Excluirla y documentarla** — **elegida** (ver A.3).

## Diseño elegido

### A. Bugfix Dragoaliento

#### A.1 Contratos exactos

```php
// src/Battle/Domain/Danio/GestorEfectosMovimiento.php
public function aplicarEstado(
    Combatiente $objetivo,
    MovimientoBatalla $movimiento,
    ?AgregadoBatalla $battle = null,
    ?Combatiente $atacante = null,
): bool;
```

```php
// src/Battle/Domain/ServicioEjecucionBatalla.php
public function aplicarEstado(
    Combatiente $objetivo,
    MovimientoBatalla $movimiento,
    ?AgregadoBatalla $battle = null,
    ?Combatiente $atacante = null,
): bool;
```

Semántica: **`true` si y solo si se ejecutó la escritura efectiva del estado sobre el objetivo
declarado** (se alcanzó `setEstado()`/`setTurnosEstado()`). No significa «hubo delta visible» ni «el
mundo cambió». Es un booleano local al objetivo.

##### Matriz de retorno — `GestorEfectosMovimiento`

| Rama | Retorno | Notas |
|---|---|---|
| Movimiento sin `statusEffect` | `false` | |
| Objetivo muerto | `false` | |
| `ClaveCondicion::PROTEGIDO` | `false` | |
| Tirada fallida | `false` | Dragoaliento 0,7 |
| Velo Flor | `false` | |
| Velo Dulce | `false` | |
| Velo Pastel | `false` | |
| Safeguard | `false` | |
| `permiteEstado()` falso | `false` | inmunidad de habilidad |
| Estado mayor previo (si se aprobara guarda) | `false` | no en esta tarea |
| Aplicación correcta | `true` | incluye turnos, `madrugar`, `sincronia`, cargas |
| `CONFUSION` | `true` | condición volátil |
| `sincronia` | `true` | efecto lateral en el atacante no reflejado en el retorno |

##### Matriz de retorno — `ServicioEjecucionBatalla`

| Rama | Retorno | Log |
|---|---|---|
| `cuerpo_aureo` en movimiento de estado | `false` | — |
| Flag BALA/SONIDO/POLVO inmune | `false` | — |
| Espejo Mágico activo | `false` | ya existe `"¡Espejo Mágico devuelve …!"` |
| Delegación al gestor | retorno del gestor | — |

**Espejo Mágico**: devuelve `false` porque el objetivo declarado no recibió el estado; el estado se
aplica al atacante y ya hay log propio. El delta de la UI detecta el cambio del atacante. Quedan
como defectos preexistentes no corregidos: (a) el log de reflejo se emite aunque el atacante sea
inmune; (b) `cuerpo_aureo` solo se comprueba en movimientos de categoría estado, no en secundarios.

#### A.2 Cambios en `app/Livewire/Combate.php`

**Ruta prioridad (`resolverPrioridades`, actual `:693-699`)**

```php
$objetivos = $resultado->objetivos;
$antesEstado = $this->capturarEstado($battle);
foreach ($objetivos as $target) {
    $servicio->aplicarEstado($target, $movimiento, $battle, $actor);
}
$this->registrarCambiosDeEstadoEnLog($battle, $antesEstado, $isAi ? 'RIVAL: ' : '');
```

Esta ruta no registra eventos; el cambio es solo de log.

**Ruta normal (`resolverAccion`, actual `:928-947`)**

```php
$objetivos = $resultado->objetivos;
$antesEstado = $this->capturarEstado($battle);   // == $previo
foreach ($objetivos as $target) {
    $servicio->aplicarEstado($target, $movimiento, $battle, $actor);
}
$actual = $this->capturarEstado($battle);
$texto = $this->registrarCambiosDeEstadoEnLog($battle, $antesEstado, $prefix);

$this->registrarEvento('status', $antesEstado, $actual, [
    'actorId' => $actor->id(),
    'objetivoId' => $objetivo->id(),
    'texto' => $texto ?? '',
    // sin 'forzar'
]);
$previo = $actual;
```

**Helper nuevo (privado, presentación)**

```php
/**
 * @param array<string, array{hp: float, defHp: float, spDefHp: float, alive: bool, status: string, statusTurns: int}> $antes
 */
private function registrarCambiosDeEstadoEnLog(
    AgregadoBatalla $battle,
    array $antes,
    string $prefijo = '',
): ?string;
```

Comportamiento:
- Recorre `capturarEstado($battle)` y compara solo `status` (no `statusTurns`, para no generar
  mensajes por el simple avance del sueño).
- `NONE → X`: `"{nombre} sufre {label}!"`.
- `X → NONE`: `"{nombre} se recupera de {label}!"`.
- `X → Y`: `"{nombre} sufre {label-Y}!"` (sustitución visible; coherente con el estado real).
- Varios combatientes (Espejo Mágico, Sincronía, multiobjetivo): un mensaje por cada uno, unidos
  para el `texto` del evento.
- Devuelve `null` si no hay cambios; con `null` y sin `forzar`, `registrarEvento` no crea evento
  (`:1403-1408`).

**Respuesta a la pregunta 2**: sí, el evento debe basarse en `capturarEstado()` en vez de `forzar`.
Mantiene el evento cuando el estado cambió por otra vía y lo elimina cuando no hubo cambio. El
`bool` del dominio no se usa para decidir el log; se mantiene como contrato verificable y para
tests.

#### A.3 Guarda de estado previo

**Decisión: excluir de esta tarea.** ⚠️ REQUIERE APROBACIÓN

- **A favor**: `setEstado()` sobrescribe un estado mayor previo sin guarda; una quemadura puede
  convertirse en parálisis.
- **En contra**: cambia el comportamiento del juego, exige decidir excepciones (autoinfligido,
  `POISON → BAD_POISON`, Sincronía, `madrugar`, objetos que curan) y puede romper tests existentes.
  No es necesario para el síntoma: el delta ya evita mensajes sin cambio visible.
- **Regla propuesta para tarea separada** (no implementar ahora):
  - La guarda va en `GestorEfectosMovimiento::aplicarEstado()`, no en `setEstado()`.
  - Si `estadoMayor() !== NONE` y el nuevo estado es distinto → `false` sin aplicar.
  - Excepciones: mismo estado → refrescar turnos y `true`; `POISON → BAD_POISON` solo si producto
    lo autoriza; autoestados no pasan por esta ruta.
  - Impacto esperado: los tests actuales usan combatientes frescos; ejecutar suite completa.

### B. Arnés determinista IA vs IA

#### B.1 Ubicación y piezas

Todo en tests salvo dos adiciones de dominio (tope de rondas y observador).

```text
tests/Support/Battle/
  EscenarioBatalla.php          # DTO readonly: nombre, equipo1, equipo2, clima, terreno, semilla
  ConstructorBatallaHarness.php # construye AgregadoBatalla con FabricaEfectos del contenedor
  VerificadorInvariantes.php    # aserciones de invariantes
  ObservadorInvariantes.php     # implementa ObservadorEjecucionBatalla
  EjecutorBatallaHarness.php    # semilla + batalla + tope + captura de resultado
  ResultadoBatallaHarness.php   # DTO: log, rondas, estancada, violaciones
  CatalogoEscenarios.php        # matriz de efectos/pares de riesgo
tests/Feature/Battle/
  HarnessEfectosTest.php        # CI, permanente y rápido
  CampanaDiagnosticoTest.php    # @group diagnostic, autogateado por env
  EstadoAplicadoLivewireTest.php
```

`tests/Support` no existe hoy; el autoload `Tests\` ya cubre `tests/`, no hay cambio de
`composer.json`.

#### B.2 Construcción determinista

```php
$fabrica = app(\Src\Battle\Domain\Effects\FabricaEfectos::class); // singleton con 316 registros

$equipo1 = EquipoBatalla::fromData($datos1, 'A', $fabrica);
$equipo2 = EquipoBatalla::fromData($datos2, 'B', $fabrica);

$batalla = new AgregadoBatalla($equipo1, $equipo2);
$batalla->setDificultad(NivelDificultad::PERFECTA);

mt_srand($escenario->semilla);      // 3. semilla ANTES de efectos de inicio
$batalla->triggerBattleStartEffects(); // 4. registra efectos de equipo
if ($escenario->clima !== null)  { $batalla->setWeather($escenario->clima); }
if ($escenario->terreno !== null) { $batalla->setTerreno($escenario->terreno); }

$log = $batalla->ejecutarBatalla($maxRondas, $observador);
```

Reglas duras:

- **IVs obligatorias** en cada `DatosPokemonBatalla` (`StatsValue::ceros()` o
  `GeneradorIvs::maximos()`); nunca confiar en el default (`random_int`).
- **`mt_srand($semilla)` inmediatamente antes de `triggerBattleStartEffects()`**.
- `CalculadoraDanioIA` (`:52-55`) re-siembra MT pero es determinista para la misma secuencia; se
  documenta y el test de doble ejecución lo verifica.
- **Aislamiento**: sembrar por batalla; `mt_srand()` sin argumentos en `tearDown`; no usar
  `#[RunInSeparateProcess]` salvo flakiness.
- **`setDificultad` es global para ambos bandos** (`SelectorAccionIA` compartido): no se puede
  simular asimetría de IA en esta tarea.
- No se serializa ninguna batalla del arnés: el observador va por parámetro, nunca como propiedad.

#### B.3 Cobertura

Cada escenario declara en `DatosPokemonBatalla`:

- `effectKeys`: habilidad o composición (`sequia`, `llovizna`, `nevada`, `chorro_arena`,
  `velo_flor`, `velo_dulce`, `velo_pastel`, `espejo_magico`, `cuerpo_aureo`, `inmunidad`,
  `levitacion`, `pararrayos`, `madrugar`, `sincronia`, `dicha`, `polvo_escudo`, `intrepido`…).
- `item`: `ClaveItem` (`life_orb`, `leftovers`, `focus_sash`, `sitrus_berry`, `lum_berry`,
  `assault_vest`).
- Movimientos con `PerfilMovimiento(probabilidadEstado: 1.0|0.0)` para eliminar varianza en
  escenarios de estado; los «reales» conservan sus probabilidades.
- Clima/terreno forzados por escenario **después** de `triggerBattleStartEffects()`.

#### B.4 Presupuesto y campaña

Todos los topes son de rondas, no de reloj; el reloj es solo red de seguridad.

| Capa | Escenarios | Semillas | Batallas | Tope | Objetivo | Cuándo |
|---|---|---|---|---|---|---|
| Determinismo | 3 | 1 (doble ejecución) | 6 | 30 | < 3 s | CI |
| Humo de efectos | 10 | 2 | 20 | 30 | < 8 s | CI |
| Barrido 1×1 prioritario | ~60 | 2 | ~120 | 40 | < 90 s | diagnóstico |
| Pares de riesgo | ~24 | 2 | ~48 | 40 | < 60 s | diagnóstico |
| Barrido completo | 289 | 2 | 578 | 40 | < 5 min | diagnóstico |

El barrido completo se ejecuta con `timeout 300` externo y `BATTLE_HARNESS_CAMPAIGN=1`. Las
estimaciones deben validarse en implementación.

Categorías del barrido priorizado: estados/inmunidades; clima (6); terreno (4); stats; objetos (6);
multi-golpe/secundarios/control; KO/«al debilitar»/revivir; daño directo/recoil/drenaje;
protección/pantallas/trampas; invocaciones/sustituto.

Pares de riesgo: `orbe_vida` + `focus_sash`, `restos` + `danio_fin_turno`, `espejo_magico` +
estado, `velo_flor` + secundario, Safeguard + multiobjetivo, `cuerpo_aureo` + estado, `sincronia` +
`bad_poison`, clima + clima contrario, Trick Room + prioridad, `madrugar` + sueño, `polvo_escudo` +
secundarios, `banda_focus` + multi-golpe.

#### B.5 Tope de rondas y observador

```php
// src/Battle/Domain/AgregadoBatalla.php
public function ejecutarBatalla(
    ?int $maxRondas = null,
    ?ObservadorEjecucionBatalla $observador = null,
): BattleLog;
```

```php
// src/Battle/Domain/Exceptions/LimiteRondasAlcanzado.php
final class LimiteRondasAlcanzado extends \RuntimeException
{
    public function __construct(
        public readonly int $maxRondas,
        public readonly int $rondasEjecutadas,
    ) {
        parent::__construct("Límite de {$maxRondas} rondas alcanzado");
    }
}
```

```php
// src/Battle/Domain/Observer/ObservadorEjecucionBatalla.php
interface ObservadorEjecucionBatalla
{
    public function alFinalizarTurno(int $ronda, Combatiente $actor, AgregadoBatalla $batalla): void;
    public function alFinalizarRonda(int $ronda, AgregadoBatalla $batalla): void;
}
```

Comportamiento:
- `maxRondas === null` → comportamiento actual, sin excepción.
- `maxRondas < 1` → `InvalidArgumentException`.
- Antes de `startNewRound()`, si `turnManager()->round() >= $maxRondas` → lanzar
  `LimiteRondasAlcanzado`. No se escribe «gana la batalla».
- El observador se llama tras `procesarDebilitamientos()`/`consumeAction` de cada turno y al final
  de `triggerRoundEndEffects()`. **Se pasa por parámetro, nunca se guarda como propiedad**, para no
  alterar la serialización de `BattleSessionService` ni la versión de sesión (20).

Alternativa sin cambio de producción ⚠️: usar `SujetoBatalla::attach()` existente (`onEndTurn` por
combatiente vivo al final de ronda) y comprobar invariantes solo a fin de ronda y fin de batalla.
Cubre menos. **Recomendación: implementar el puerto.**

## Mapa de capas

- **Dominio** (`src/Battle/Domain`): contratos `bool`; `LimiteRondasAlcanzado`;
  `ObservadorEjecucionBatalla`; lógica de ronda en `AgregadoBatalla`. Sin Laravel/HTTP/Blade.
- **Aplicación**: sin capa nueva. `ServicioEjecucionBatalla` sigue siendo servicio de dominio.
- **Infraestructura**: sin cambios. `FabricaEfectos` y `BattleEffectServiceProvider` se consumen
  desde tests.
- **Presentación** (`app/Livewire/Combate.php`, Blade): log y eventos derivados de delta; sin
  reglas de juego nuevas. La Blade no cambia.

## Contratos

- **Interfaces**: `ObservadorEjecucionBatalla` (2 métodos). No se toca `ObservadorBatalla`.
- **DTOs**: `EscenarioBatalla`, `ResultadoBatallaHarness` (solo tests). No se introduce DTO de
  resultado de estado.
- **Value Objects**: sin cambios. Se reutilizan `EstadoPokemon`, `PerfilMovimiento`, `StatsValue`.
- **Excepciones**: `LimiteRondasAlcanzado` (dominio); `InvarianteViolada` (solo tests).

## Flujo de datos

1. Test de campaña construye `EscenarioBatalla` y `ConstructorBatallaHarness` crea `EquipoBatalla`
   con `FabricaEfectos` del contenedor e IVs explícitas.
2. Se crea `AgregadoBatalla`, se fija dificultad, se siembra MT, se disparan efectos de inicio y se
   fuerza clima/terreno.
3. `EjecutorBatallaHarness` invoca `ejecutarBatalla($maxRondas, $observador)`.
4. En cada turno/ronda, `ObservadorInvariantes` lee el estado por accesores públicos y lanza
   `InvarianteViolada` con ronda/turno.
5. Al terminar (o al lanzarse `LimiteRondasAlcanzado`) se devuelve `ResultadoBatallaHarness`; el
   test compara log, rondas y violaciones.
6. En producción, `Combate` aplica estado, captura delta, escribe log y emite `status` solo si hay
   delta.

## Plan de archivos

- **crear**:
  - `src/Battle/Domain/Exceptions/LimiteRondasAlcanzado.php`
  - `src/Battle/Domain/Observer/ObservadorEjecucionBatalla.php`
  - `tests/Support/Battle/{EscenarioBatalla,ConstructorBatallaHarness,VerificadorInvariantes,ObservadorInvariantes,EjecutorBatallaHarness,ResultadoBatallaHarness,CatalogoEscenarios}.php`
  - `tests/Feature/Battle/{HarnessEfectosTest,CampanaDiagnosticoTest,EstadoAplicadoLivewireTest}.php`
- **modificar**:
  - `src/Battle/Domain/Danio/GestorEfectosMovimiento.php` (retorno `bool`)
  - `src/Battle/Domain/ServicioEjecucionBatalla.php` (retorno `bool`)
  - `src/Battle/Domain/AgregadoBatalla.php` (`maxRondas` + observador)
  - `app/Livewire/Combate.php` (dos rutas + helper)
  - `tests/Unit/Battle/ServicioEjecucionBatallaTest.php`
  - `tests/Unit/Battle/MovimientoEfectosTest.php`
- **eliminar**: ninguno.
- **no tocar**: `src/Battle/Domain/{Combatiente,DatosPokemonBatalla,EquipoBatalla}.php` (WIP),
  `resources/views/**`, `composer.json`, `phpunit.xml`.

## Base de datos

- No aplica. La batalla persiste serializada en sesión (`BattleSessionService`,
  `SESSION_VERSION = 20`). No se añaden propiedades serializadas; por eso el observador va por
  parámetro. **No se incrementa la versión de sesión.**

## Pruebas

### C.1 Unitarias — `tests/Unit/Battle/ServicioEjecucionBatallaTest.php`

- `test_aplicar_estado_retorna_true_cuando_aplica_paralisis`
- `test_aplicar_estado_retorna_false_cuando_falla_la_probabilidad`
  (`PerfilMovimiento(probabilidadEstado: 0.0)`)
- `test_aplicar_estado_retorna_false_si_el_objetivo_esta_muerto`
- `test_aplicar_estado_retorna_false_si_el_objetivo_esta_protegido`
- `test_aplicar_estado_retorna_false_con_cuerpo_aureo_en_movimiento_de_estado`
- `test_aplicar_estado_retorna_false_si_la_habilidad_bloquea_el_estado`
- `test_espejo_magico_aplica_estado_al_atacante_y_retorna_false`

Las dependencias de equipo se construyen manualmente para no requerir contenedor en tests
unitarios.

### C.2 Unitarias — `tests/Unit/Battle/MovimientoEfectosTest.php`

- `test_aplicar_estado_velo_flor_retorna_false`
- `test_aplicar_estado_safeguard_retorna_false`
- `test_aplicar_estado_espejo_magico_no_marca_al_objetivo`

### C.3 Feature Livewire — `tests/Feature/Battle/EstadoAplicadoLivewireTest.php`

Helper: batalla 1v1 con atacante más rápido, guardada con
`app(BattleSessionService::class)->guardar($id, $batalla)`, montar
`Livewire::withQueryParams(['battle_id' => $id])->test(Combate::class)`, fijar `pendingAction` y
llamar `commitAction()`.

- `test_dragoaliento_que_falla_probabilidad_no_muestra_mensaje_de_paralisis`
- `test_dragoaliento_que_aplica_muestra_mensaje_de_paralisis`
- `test_evento_status_no_se_emite_sin_cambio_de_estado`
- `test_evento_status_se_emite_cuando_espejo_magico_paraliza_al_atacante`
- `test_mensaje_y_evento_coinciden_con_el_estado_final`

### C.4 Arnés CI — `tests/Feature/Battle/HarnessEfectosTest.php`

- `test_misma_semilla_produce_la_misma_batalla`
- `test_batalla_sin_dano_termina_con_tope_de_rondas`
- `test_matriz_humo_de_efectos_respeta_invariantes` (10 escenarios × 2 semillas)
- `test_ivs_explicitas_son_obligatorias_en_el_constructor`

### C.5 Campaña — `tests/Feature/Battle/CampanaDiagnosticoTest.php`

- `#[Group('diagnostic')]` + `markTestSkipped` si `BATTLE_HARNESS_CAMPAIGN !== '1'`.
- `test_campana_priorizada` y `test_barrido_completo_de_claves`.
- Salida: número de batallas, estancadas, violaciones agrupadas por invariante. No falla por
  invariantes marcados como diagnóstico; los escribe en el reporte.

### C.6 Invariantes

Se leen con accesores públicos existentes: `hpActual()`, `defensaHpActual()`,
`defensaEspHpActual()`, `estaVivo()`, `estado()`, `estadoMayor()`, `turnosEstado()`,
`cargasVeneno()`, `contadorVenenoGrave()`, `multiplicadores()->turnosTemporales()`, `condiciones()`,
`usosRestantes()`, `pokemon()->battleStats()`; agregado: `turnManager()->allCombatants()`,
`weather()`, `terreno()`, `turnosTerreno()`, `log()->entries()`. No hacen falta nuevos getters.

| # | Invariante | Comprobación | Cuándo | Clasificación |
|---|---|---|---|---|
| 1 | `0 <= hpActual <= battleStats()->hp` | aserción | turno, ronda, fin | segura |
| 2 | `0 <= defHp <= defenseHp` y `spDef` | aserción | turno, ronda, fin | segura |
| 3 | `hpActual == 0 ⟺ !estaVivo()` | aserción | turno, ronda, fin | segura (tautológica) |
| 3b | ningún debilitado recibe curación | `hp == 0` se mantiene | ronda | segura |
| 4 | ningún estado en debilitado | advertencia | ronda | diagnóstico |
| 5 | turnos/cargas/veneno/temporales >= 0 | aserción | turno, ronda | segura |
| 6 | `terreno == NONE ⟺ turnosTerreno == 0` | aserción | ronda | segura |
| 7 | `usosRestantes === -1 \|\| 0 <= usos <= max` | aserción | fin | segura |
| 8 | temporales no negativos; decremento exacto 1/ronda | aserción / advertencia | ronda | parcial |
| 9 | terminación en `<= N` rondas | `LimiteRondasAlcanzado` | fin | segura |
| 10 | cada vivo actúa máximo una vez por ronda (+ extras) | advertencia | ronda | diagnóstico |
| 11 | toda reducción de HP tiene entrada de log | advertencia | turno | diagnóstico |
| 12 | condiciones volátiles no persisten tras KO/cambio | advertencia | ronda | diagnóstico |

Los invariantes 4, 8b, 10, 11 y 12 se reportan pero no rompen CI: documentan deuda conocida.
Endurecerlos será tarea posterior.

## Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Cambiar `void → bool` rompe subclase/mock | Fatal en carga | `ServicioEjecucionBatalla` no tiene subclases/mocks (verificado por grep); revisar antes |
| `aplicarEstadoYStatChanges`/`SimuladorAccionIA` dependen del retorno | Bajo | Ambos ignoran el retorno; firmas no cambian |
| Quitar `forzar` oculta animaciones legítimas | Medio | `status` solo representa cambios de estado; «falló» se mantiene en `damage` |
| `statusTurns` provoca eventos extra | Bajo | El log ignora solo turnos; el evento incluye delta real |
| PHPStan/baseline se desajusta | Bajo | Ejecutar análisis y actualizar baseline solo si el proyecto lo exige |
| Arnés flaky por `random_int` en IVs | Alto | IVs obligatorias; test que lo verifica |
| `CalculadoraDanioIA` re-siembra MT | Medio | Determinista para la misma secuencia; test de doble ejecución |
| Campaña lenta o sin memoria | Medio | Tope de rondas + `timeout 300` + grupos |
| Observador serializado en sesión | Alto | Se pasa por parámetro, nunca propiedad |
| `LimiteRondasAlcanzado` en producción | Bajo | Solo se lanza si el llamador pasa `maxRondas` |
| Tocar el WIP local | Alto | Lista de archivos autorizados; WIP excluido |

**Rollback**: tres commits independientes — (1) contrato `bool` + Livewire, (2) `maxRondas` +
observer, (3) arnés y tests. Revertir (3) no afecta producción; revertir (2) deja el bugfix; revertir
(1) restaura el comportamiento actual sin tocar el arnés.

## Borrador de ADR

- **Título**: Contrato explícito de aplicación de estado y arnés determinista de batallas.
- **Estado**: Propuesto.
- **Contexto**: mensajes/animaciones de estado sin cambio real (Dragoaliento), ramas silenciosas en
  dominio, motor sin tope de rondas ni forma de observar invariantes.
- **Decisión**: `aplicarEstado(): bool`; log/evento `status` derivados de `capturarEstado()`;
  `ejecutarBatalla(?int $maxRondas, ?ObservadorEjecucionBatalla)`; arnés determinista; campaña
  autogateada; guarda de estado previo diferida.
- **Consecuencias**: la UI deja de duplicar la política de estado; el dominio informa sin cambiar el
  motor; el arnés añade un puerto de observación mínimo; la sobrescritura de estados sigue siendo
  deuda; la campaña completa queda fuera de CI por coste.

## Preguntas abiertas

1. ⚠️ ¿Se aprueba `bool` frente a un enum de motivos? Recomendado: `bool`.
2. ⚠️ ¿Se aprueba el puerto `ObservadorEjecucionBatalla` por parámetro o se prefiere el fallback sin
   cambio de producción con `SujetoBatalla::attach()`? Recomendado: puerto.
3. ⚠️ ¿Se confirma excluir la guarda de `setEstado()` y abrir tarea aparte? Recomendado: excluir.
4. ⚠️ ¿Se confirma no crear comando artisan y dejar la campaña como test autogateado? Recomendado:
   sí.
5. ¿Se aceptan los presupuestos (20 batallas CI, ~170 diagnóstico prioritario, 578 completo)?
6. ¿Se corrige en esta tarea el doble defecto de Espejo Mágico? Recomendado: tarea separada.
7. ¿Debe `CombateExploracion` pasar un tope de rondas? Recomendado: tarea separada.
