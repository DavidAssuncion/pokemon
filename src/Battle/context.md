# Battle — Contexto del módulo

## Resumen

El módulo Battle implementa el sistema de combate por turnos 3v3 del juego Pokemon Battle Game. Soporta batalla manual (Livewire) y automática (IA vs IA). Usa datos mock (`FabricaBatallaMock`) con 6 pokémon predefinidos (Gengar, Giratina, Tyranitar vs Aggron, Deoxys, Mewtwo). Está diseñado para integrarse en el futuro con datos reales de reclutamiento y para soportar PvP.

## Arquitectura

```
src/Battle/
├── Domain/              (lógica de dominio pura — sin dependencias de Laravel)
│   ├── AccionBatalla.php
│   ├── AgregadoBatalla.php
│   ├── CalculadorDañoClima.php
│   ├── Combatiente.php
│   ├── DatosPokemonBatalla.php
│   ├── EquipoBatalla.php
│   ├── FabricaBatallaInterface.php
│   ├── GestorTurnos.php
│   ├── MovimientoBatalla.php
│   ├── Posicion.php
│   ├── SelectorAccionIA.php
│   ├── ServicioEjecucionBatalla.php
│   ├── Chain/             (Chain of Responsibility para cálculo de daño)
│   │   ├── CadenaDanio.php
│   │   ├── ManejadorDanio.php               (interface)
│   │   ├── ManejadorDanioAbstracto.php      (base abstracta con setNext)
│   │   ├── ManejadorDanioBase.php
│   │   ├── ManejadorEfectividadTipo.php
│   │   ├── ManejadorSTAB.php
│   │   ├── ManejadorCritico.php
│   │   ├── ManejadorPosicion.php
│   │   ├── ManejadorClima.php
│   │   └── ManejadorObjetosEquipados.php
│   ├── Effects/           (Strategy Pattern para efectos de habilidad/objeto)
│   │   ├── InterfazEfecto.php
│   │   ├── ComportamientosPorDefecto.php     (trait con defaults vacíos)
│   │   ├── ColeccionEfectos.php
│   │   ├── FabricaEfectos.php
│   │   ├── EfectoPerforacionArmadura.php
│   │   ├── EfectoRegeneracionDefensa.php
│   │   ├── EfectoInvocadorClima.php
│   │   ├── EfectoRestos.php
│   │   └── EfectoOrbeVida.php
│   ├── Enums/
│   │   ├── CategoriaMovimiento.php
│   │   ├── EstadoPokemon.php
│   │   └── TipoClima.php
│   ├── Observer/          (Observer Pattern para eventos de batalla)
│   │   ├── ObservadorBatalla.php
│   │   └── SujetoBatalla.php
│   └── ValueObjects/
│       ├── ColeccionMovimientos.php
│       └── EtapasStats.php
├── Infrastructure/       (adaptadores, factorías de datos mock)
│   └── FabricaBatallaMock.php
└── Presentation/         (DTOs para la vista, Livewire Wireable)
    ├── DTOAccionBatalla.php
    ├── DTOEquipoBatalla.php
    ├── DTOMovimientoBatalla.php
    └── DTOResultadoDanio.php
```

## Clases y su responsabilidad

### Domain — Agregado raíz y entidades principales

---

#### `AgregadoBatalla` (`src/Battle/Domain/AgregadoBatalla.php`)

**Responsabilidad:** Agregado raíz que orquesta una batalla 3v3. Contiene los dos equipos, el gestor de turnos, la cadena de daño, el clima, el log y el pendingAction. Expone métodos para ciclo de vida completo (batalla automática) y para integración con Livewire (batalla manual). **~255 líneas** (refactor 2026: la lógica de clima y de IA se extrajo a servicios).

**Mecánica principal:**
- `ejecutarBatalla(): array` — Bucle completo de batalla automática (IA vs IA). Itera rondas, turnos, aplica daño, estados, efectos de fin de ronda y determina ganador.
- `triggerBattleStartEffects()` — Dispara `onBattleStart` en todos los efectos de todos los combatientes (usado por invocadores de clima).
- `triggerRoundStartEffects()` — Dispara `onRoundStart` en combatientes vivos.
- `triggerRoundEndEffects()` — Dispara `onRoundEnd` + daño por estado (quemadura/veneno) + daño por clima (granizo/tormenta arena). El daño por clima **delega en `CalculadorDañoClima`** (servicio extraído).
- `elegirObjetivoPara(Combatiente $actor): ?Combatiente` — Público para IA desde Livewire. **Delega en `SelectorAccionIA`**. Si el actor está en vanguardia, ataca a vanguardia enemiga viva (aleatorio); si no hay, a retaguardia. Si está en retaguardia, a cualquier enemigo vivo. Fallback: cualquier enemigo vivo.
- `elegirMejorMovimiento(Combatiente $attacker, Combatiente $defender): ?MovimientoBatalla` — Público. **Delega en `SelectorAccionIA`**. Selecciona el movimiento con mayor `efectividad × potencia`. Si no hay movimientos, fallback a Placaje (40, NORMAL, FÍSICO).
- `setPendingAction(?DTOAccionBatalla)` — Almacena la acción pendiente para el ciclo manual (Livewire).
- `agregarLog(string)` / `limpiarLog()` / `log(): array` — Gestión del log de batalla.
- `setWeather(TipoClima)` / `weather(): TipoClima` — Clima actual (default NONE).

**Propiedades clave:** `team1`, `team2` (públicas readonly), `turnManager`, `damageChain`, `subject` (SujetoBatalla), `weather`, `pendingAction`, `log`.

**Lazy getters nullable (patrón `??=`):** los servicios `CalculadorDañoClima` y `SelectorAccionIA` se guardan como propiedades privadas **nullable** (`private ?CalculadorDañoClima $calculadorClima = null;`) y se crean bajo demanda con `$this->calculadorClima ??= new CalculadorDañoClima();`. Motivo: PHP serializa `null` sin problemas, por lo que las batallas guardadas en sesión (v3) se deserializan sin rotura y el getter reconstruye el servicio al usarlo. Verificado con round-trip serialize/unserialize.

**Relaciones:** Inyecta `GestorTurnos`, `CadenaDanio`, `SujetoBatalla`. Usa `ServicioEjecucionBatalla` internamente en `ejecutarAccion`. Importa `DTOAccionBatalla` de Presentation (inversión de dependencia Domain→Presentation, deuda documentada).

---

#### `CalculadorDañoClima` (`src/Battle/Domain/CalculadorDañoClima.php`)

**Responsabilidad:** Servicio extraído del método privado `AgregadoBatalla::calcularDañoClima` (eliminado en el refactor 2026). Calcula el daño por clima de fin de ronda para un combatiente. Stateless (sin propiedades).

**Método:** `calcular(Combatiente $c, TipoClima $weather): float` —
- `GRANIZO`: 6.25% HP a los que NO son tipo HIELO (`max(1, hp * 0.0625)`).
- `TORMENTA_ARENA`: 6.25% HP a los que NO son ROCA/TIERRA/ACERO.
- Muerto u otros climas (`default`): 0.

Inmunes declarados como constantes privadas (`INMUNES_GRANIZO`, `INMUNES_TORMENTA_ARENA`). Usado por `AgregadoBatalla::triggerRoundEndEffects()` vía lazy getter (patrón `??=`, ver AgregadoBatalla).

---

#### `SelectorAccionIA` (`src/Battle/Domain/SelectorAccionIA.php`)

**Responsabilidad:** Servicio con la lógica de IA para batalla (objetivo + mejor movimiento), extraída de `AgregadoBatalla` en el refactor 2026 para mantener el agregado orquestando solo el ciclo. Stateless.

**Métodos:**
- `elegirObjetivoPara(AgregadoBatalla $battle, Combatiente $actor): ?Combatiente` — Vanguardia → vanguardia enemiga viva (aleatorio); si no hay, retaguardia. Retaguardia → cualquier enemigo vivo. Fallback: cualquier enemigo vivo; `null` si no hay vivos.
- `elegirMejorMovimiento(Combatiente $attacker, Combatiente $defender): ?MovimientoBatalla` — Mayor `efectividad × potencia`; fallback Placaje (40, NORMAL, FÍSICO) si no hay movimientos.

`AgregadoBatalla` conserva la API pública `elegirObjetivoPara`/`elegirMejorMovimiento` como **delegación** a este servicio (para `Combate::prepareAiAnimation`). Se instancia vía lazy getter en el agregado (patrón `??=`).

---

#### `Combatiente` (`src/Battle/Domain/Combatiente.php`)

**Responsabilidad:** Representa un pokémon en batalla con su estado mutable: HP actual, barreras duales (defensa física/especial), velocidad acumulada, estado alterado, etapas de stats, objeto equipado, efectos. Es serializable a sesión.

**Mecánica principal:**
- `recibirDaño(float $daño, bool $isSpecial, float $directPct = 0.0): float` — Aplica daño desglosado: primero `directPct`% va directo a HP (ignora barreras), el resto se descuenta de la barrera correspondiente (defensa física o especial). El excedente de barrera pasa a HP. Mecánica de barreras propia del juego (no estándar Pokémon).
- `obtenerPorcentajeDanioDirecto(): float` — Suma el `obtenerPorcentajeDanioDirecto()` de todos los efectos del combatiente, capado a 1.0 (100%).
- `puedeActuar(): array{canAct, reason, selfDamage}` — Evalúa si el combatiente puede actuar según su estado:
  - SLEEP: turnos decrecientes, se despierta al llegar a 0.
  - FREEZE: 20% de descongelarse por turno.
  - PARALYSIS: 25% de quedar bloqueado.
  - CONFUSION: 33% de auto-daño (fórmula nivel 50, 40 de poder, stat ataque/defensa); turnos 2-4.
  - NONE/BURN/POISON/etc: pueden actuar.
- `aplicarDañoStatus(): float` — Daño por estado al final de ronda: BURN 6.25%, POISON 12.5%, BAD_POISON creciente (contador/16 del HP máx).
- `obtenerStatEfectivo(string $stat): float` — Stat base × multiplicador de etapas. Parálisis reduce velocidad a la mitad.
- `aplicarCambioEtapa(string $stat, int $change)` — Modifica etapa (clamp -6..+6).
- `aArrayVista(int $teamIdx): array` — Serializa a array para la vista (refId, nombre, icon, hp, maxHp, defHp, spDefHp, posicion, alive, speed, accumulatedSpeed, status, statusTurns, stages, team, item).
- `curarHp(float $porcentaje)` — Cura porcentaje del HP máximo.
- `curarBarreras(float $porcentaje)` — Cura porcentaje de ambas barreras.
- `__serialize()` / `__unserialize()` — Serialización a sesión con prefijo de versión. Incluye `effects` y `pokemon` serializados anidados.

**Propiedades clave:** `hpActual`, `defensaHpActual`, `defensaEspHpActual`, `velocidadAcumulada`, `estado` (EstadoPokemon), `contadorVenenoGrave`, `turnosEstado`, `etapas` (EtapasStats), `id`, `nombre`, `iconName`, `shiny`, `item`, `effects` (ColeccionEfectos), `pokemon` (PokemonEntity), `posicion` (Posicion).

**Relaciones:** Contiene `PokemonEntity` (src/Pokemon/Domain), `ColeccionEfectos`, `EtapasStats`. Usado por `AgregadoBatalla`, `EquipoBatalla`, `GestorTurnos`, `ServicioEjecucionBatalla`.

---

#### `EquipoBatalla` (`src/Battle/Domain/EquipoBatalla.php`)

**Responsabilidad:** Colección de 3 `Combatiente` con nombre de equipo. Maneja consultas de estado (vivos, vanguardia, retaguardia, todos debilitados).

**Mecánica principal:**
- `fromData(array $members, string $name, ?FabricaEfectos $fabricaEfectos = null): self` — Factory method que construye un equipo completo desde `DatosPokemonBatalla[]`. Crea `StatsValue`, `TiposCollection`, `PokemonEntity` y `Combatiente` para cada miembro. Procesa efectos vía `FabricaEfectos::crearEfecto()` y objetos vía `FabricaEfectos::crearItem()`. La `FabricaEfectos` es inyectable (default: new instance).
- `combatientesVivos(): array`, `vanguardiaAlive(): array`, `retaguardiaAlive(): array` — Filtros.
- `tieneVanguardiaViva(): bool`, `todosDebilitados(): bool`.
- `findCombatant(Combatiente $target): ?Combatiente` — Búsqueda por identidad de objeto.
- `findCombatantById(string $id): ?Combatiente` — Búsqueda por id string.

**Propiedades:** `name` (público readonly), `combatants` (Combatiente[]).

**Relaciones:** Usa `FabricaEfectos` (inyectable), `PokemonEntity`, `StatsValue`, `TiposCollection`. Usado por `AgregadoBatalla`, `GestorTurnos`.

---

#### `GestorTurnos` (`src/Battle/Domain/GestorTurnos.php`)

**Responsabilidad:** Gestiona el ciclo de rondas y turnos basado en velocidad acumulada.

**Mecánica principal:**
- `startNewRound()` — Incrementa ronda, acumula velocidad a todos los vivos (`agregarVelocidad()` = stat efectivo speed), resetea `vecesActuadoEstaRonda` a 0.
- `getNextActor(): ?Combatiente` — Retorna el combatiente vivo con mayor velocidad acumulada. Si todos tienen velocidad ≤ 0 o no hay vivos, retorna null.
- `consumeAction(Combatiente $actor)` — Resta la menor velocidad entre vivos (o 1 si ≤ 0) al actor. Incrementa `vecesActuadoEstaRonda`.
- `hayAlgunoConAccionPendiente(): bool` — true si algún vivo tiene velocidad acumulada > 0.
- `bothTeamsAlive(): bool` — true si ambos equipos tienen al menos un vivo.
- `allCombatants(): array`, `combatientesVivos(): array`, `menorVelocidadEntreVivos(): float`.

**Propiedades:** `round`, `teamA`, `teamB` (referencias a `EquipoBatalla::combatants()`).

**Relaciones:** Usa `EquipoBatalla`, `Combatiente`. Usado por `AgregadoBatalla`.

---

#### `ServicioEjecucionBatalla` (`src/Battle/Domain/ServicioEjecucionBatalla.php`)

**Responsabilidad:** Servicio compartido que ejecuta el cálculo y aplicación de un movimiento. Unifica la lógica entre batalla manual (Livewire) y automática (AgregadoBatalla).

**Mecánica principal:**
- `calcularYAplicarDano(AccionBatalla $accion): DTOResultadoDanio` — Obtiene `directPct` del atacante, calcula daño via `CadenaDanio::calculate()`, aplica `recibirDaño()` al defensor.
- `aplicarEstado(Combatiente $objetivo, MovimientoBatalla $movimiento)` — Aplica estado secundario al objetivo. SLEEP/CONFUSION: turnos aleatorios 2-4.
- `aplicarStatChanges(Combatiente $actor, Combatiente $objetivo, MovimientoBatalla $movimiento)` — Aplica selfStatChanges al actor y targetStatChanges al objetivo.
- `generarLogMovimiento(...): string` — Genera mensaje descriptivo con daño, penalización de retaguardia y porcentaje directo.

**Relaciones:** Inyecta `CadenaDanio`. Usa `AccionBatalla`, `Combatiente`, `MovimientoBatalla`, `DTOResultadoDanio`.

---

#### `MovimientoBatalla` (`src/Battle/Domain/MovimientoBatalla.php`)

**Responsabilidad:** Value Object inmutable que representa un movimiento con nombre, potencia, tipo, categoría, efecto secundario, prioridad y cambios de stats.

**Propiedades:** `nombre`, `potencia`, `tipo` (TipoPokemon), `categoria` (CategoriaMovimiento), `statusEffect` (EstadoPokemon, default NONE), `priority` (int, default 0), `selfStatChanges` (array de `{stat, stages}`), `targetStatChanges` (array de `{stat, stages}`).

**Métodos:** `esEspecial()`, `esFisico()`, `esEstado()`, `tieneStatus()`, `tieneSelfStatChanges()`, `tieneTargetStatChanges()`.

---

#### `AccionBatalla` (`src/Battle/Domain/AccionBatalla.php`)

**Responsabilidad:** DTO inmutable que encapsula una acción de ataque completa: atacante, defensor, movimiento, posición de origen, si el equipo defensor tiene vanguardia viva, y clima actual.

**Propiedades:** `attacker`, `defender`, `move`, `fromPosition`, `defenderTeamHasVanguard`, `weather`.

---

#### `DatosPokemonBatalla` (`src/Battle/Domain/DatosPokemonBatalla.php`)

**Responsabilidad:** DTO de entrada para construir combatientes desde datos mock. Contiene stats, tipos, movimientos, efectos, objeto, shiny, iconName. Valida que `moves` sean `MovimientoBatalla` y `tipos` sean `TipoPokemon`.

**Propiedades:** `id`, `nombre`, `hp`, `atk`, `def`, `spAtk`, `spDef`, `speed`, `tipos` (TipoPokemon[]), `posicion` (Posicion), `moves` (MovimientoBatalla[]), `shiny`, `iconName`, `effectKeys` (string[]), `item` (?string).

---

#### `Posicion` (`src/Battle/Domain/Posicion.php`)

**Responsabilidad:** Enum de dos valores: `VANGUARDIA` ('vanguardia') y `RETAGUARDIA` ('retaguardia'). Método `opuesta()` retorna la posición contraria.

---

#### `FabricaBatallaInterface` (`src/Battle/Domain/FabricaBatallaInterface.php`)

**Responsabilidad:** Interfaz para la fábrica de datos de batalla. Permite inyectar distintas implementaciones (mock → real).

**Métodos:** `createBattle(): AgregadoBatalla`, `crearEquiposMock(): EquipoBatalla`.

---

### Domain — Chain of Responsibility (Cadena de daño)

---

#### `CadenaDanio` (`src/Battle/Domain/Chain/CadenaDanio.php`)

**Responsabilidad:** Compone la cadena de 7 manejadores en orden fijo y calcula el daño final.

**Orden de la cadena:**
1. `ManejadorDanioBase` — Fórmula nivel 50
2. `ManejadorEfectividadTipo` — × efectividad
3. `ManejadorSTAB` — ×1.5 si coincide tipo
4. `ManejadorCritico` — ×1.5 si 6.25%
5. `ManejadorPosicion` — ×0.5 si retaguardia con vanguardia enemiga viva
6. `ManejadorClima` — ×0.75–1.25 según clima
7. `ManejadorObjetosEquipados` — mapa multiplicadores (life_orb → 1.30)

**Método:** `calculate(AccionBatalla $action): float` — Retorna `max(1, floor(daño))`. Nota: `max(1,...)` anula inmunidad de tipos (×0 → mínimo 1). Deuda documentada.

---

#### `ManejadorDanio` (interface) + `ManejadorDanioAbstracto` (base)

**Responsabilidad:** Interface con `setNext(ManejadorDanio)` y `handle(AccionBatalla, float): float`. `ManejadorDanioAbstracto` implementa el patrón Template Method: `handle()` llama a `process()` y luego al siguiente. Los manejadores concretos extienden `ManejadorDanioAbstracto` y solo implementan `process()`.

---

#### `ManejadorDanioBase` (`ManejadorDanioBase.php`)

**Fórmula:** `(((2 * 50 / 5 + 2) * potencia * atk / max(def, 1)) / 50) + 2` donde:
- `atk` = `obtenerStatEfectivo('spAtk')` si especial, `obtenerStatEfectivo('attack')` si físico.
- `def` = `obtenerStatEfectivo('spDef')` si especial, `obtenerStatEfectivo('defense')` si físico.
- `nivel = 50` fijo.

---

#### `ManejadorEfectividadTipo`

**Fórmula:** `daño × efectividad` donde efectividad = `TipoPokemon::effectiveness()` (multiplica sobre todos los tipos del defensor via TypeChart 18×18). Valores: 2.0 (súper eficaz), 1.0 (neutral), 0.5 (poco eficaz), 0.0 (inmune).

---

#### `ManejadorSTAB`

**Multiplicador:** `×1.5` si algún tipo del atacante coincide con el tipo del movimiento.

---

#### `ManejadorCritico`

**Probabilidad:** `6.25%` (0.0625). **Multiplicador:** `×1.5`. Usa `mt_rand()` / `mt_getrandmax()`.

---

#### `ManejadorPosicion`

**Multiplicador:** `×0.5` si el defensor está en retaguardia Y el equipo defensor tiene vanguardia viva.

---

#### `ManejadorClima`

**Multiplicadores según clima:**

| Clima | Tipos potenciados (×1.25) | Tipos reducidos (×0.75) |
|-------|--------------------------|------------------------|
| SEQUIA (`sequia`) | FUEGO | AGUA |
| DILUVIO (`diluvio`) | AGUA | FUEGO |
| NIEBLA (`niebla`) | SINIESTRO, FANTASMA, PSIQUICO | — |
| GRANIZO (`granizo`) | — (HIELO gana +25% SpDef → ataque especial -20% = ×0.80) | — |
| TORMENTA_ARENA (`tormenta_arena`) | — (ROCA/TIERRA/ACERO ganan +25% Def → ataque físico -20% = ×0.80) | — |
| TURBULENCIAS (`turbulencias`) | DRAGON, VOLADOR | — |

---

#### `ManejadorObjetosEquipados`

**Responsabilidad:** Aplica el multiplicador de daño del objeto equipado por el atacante. Manejador **genérico** (reemplaza a `ManejadorOrbeVida`, eliminado en el refactor 2026): consulta un mapa centralizado `objeto → multiplicador` en el constructor. Si el atacante no lleva objeto (o uno sin multiplicador de daño) devuelve ×1.0. Extensible: añadir claves al mapa.

**Multiplicador:** `['life_orb' => 1.30]` (default) si el atacante tiene `item()` con clave en el mapa y está vivo. El recoil del Orbe Vida (10% HP máx) NO está aquí: sigue en `EfectoOrbeVida` (`onDamageDealt`, ver Effects).

---

### Domain — Effects (Strategy Pattern)

---

#### `InterfazEfecto` (`src/Battle/Domain/Effects/InterfazEfecto.php`)

**Responsabilidad:** Interface que define hooks del ciclo de vida de un efecto: `obtenerClave()`, `esUnico()`, `obtenerPorcentajeDanioDirecto()`, `onBattleStart()`, `onRoundStart()`, `onRoundEnd()`, `onDamageDealt()`, `onDamageReceived()`, `onHealed()`, `onFainted()`, `onTurnStart()`, `onTurnEnd()`.

---

#### `ComportamientosPorDefecto` (trait)

**Responsabilidad:** Implementaciones vacías por defecto para todos los hooks. Los efectos concretos usan `use ComportamientosPorDefecto;` y sobrescriben solo lo necesario.

---

#### `ColeccionEfectos` (`src/Battle/Domain/Effects/ColeccionEfectos.php`)

**Responsabilidad:** Colección de `InterfazEfecto` con métodos de dispatch: `triggerBattleStart()`, `triggerRoundStart()`, `triggerRoundEnd()`, `dispararDanioInfligido()`, `dispararDanioRecibido()`, `triggerHealed()`, `dispararDebilitado()`, `dispararInicioTurno()`, `dispararFinTurno()`. También `find(string $clave)`, `unicos()`, `all()`.

---

#### `FabricaEfectos` (`src/Battle/Domain/Effects/FabricaEfectos.php`)

**Responsabilidad:** Fábrica que registra y crea efectos (habilidades e items) por clave. Ahora es una instancia inyectable (no estática). Se registra como singleton en `BattleEffectServiceProvider`.

**Métodos:** `registrarEfecto(clave, clase, ...args)`, `registrarItem(clave, clase)`, `crearEfecto(clave): ?InterfazEfecto`, `crearItem(clave): ?InterfazEfecto`, `clavesEfectos()`, `clavesItems()`.

**Efectos registrados (en `BattleEffectServiceProvider`):**

| Clave | Clase | Args |
|-------|-------|------|
| `armor_pierce` | `EfectoPerforacionArmadura` | 0.10 (10% directo) |
| `regen_def` | `EfectoRegeneracionDefensa` | 10.0 (10% cura barrera) |
| `sandstorm_summoner` | `EfectoInvocadorClima` | `TipoClima::TORMENTA_ARENA` |
| `sequia_summoner` | `EfectoInvocadorClima` | `TipoClima::SEQUIA` |
| `diluvio_summoner` | `EfectoInvocadorClima` | `TipoClima::DILUVIO` |
| `niebla_summoner` | `EfectoInvocadorClima` | `TipoClima::NIEBLA` |
| `granizo_summoner` | `EfectoInvocadorClima` | `TipoClima::GRANIZO` |
| `turbulencias_summoner` | `EfectoInvocadorClima` | `TipoClima::TURBULENCIAS` |

**Items registrados:**

| Clave | Clase |
|-------|-------|
| `leftovers` | `EfectoRestos` |
| `life_orb` | `EfectoOrbeVida` |

---

#### `EfectoPerforacionArmadura` (`EfectoPerforacionArmadura.php`)

**Comportamiento:** `obtenerPorcentajeDanioDirecto()` retorna 0.10 (10% del daño ignora barreras).

---

#### `EfectoRegeneracionDefensa` (`EfectoRegeneracionDefensa.php`)

**Comportamiento:** `onRoundEnd()` cura 10% de `defenseHp` (barrera física) cada ronda.

---

#### `EfectoInvocadorClima` (`EfectoInvocadorClima.php`)

**Comportamiento:** `onBattleStart()` establece el clima en la batalla y agrega mensaje al log. Soporta 6 climas con mensajes en español.

---

#### `EfectoRestos` (`EfectoRestos.php`)

**Comportamiento:** `onRoundEnd()` cura 1/16 (6.25%) del HP máximo, mínimo 1. Agrega log.

---

#### `EfectoOrbeVida` (`EfectoOrbeVida.php`)

**Comportamiento:** `onDamageDealt()` inflige recoil de 10% del HP máximo al portador (mínimo 1). El bonus de daño ×1.3 se aplica en `ManejadorObjetosEquipados` de la cadena (mapa `['life_orb' => 1.30]`).

---

### Domain — Observer (Observer Pattern)

---

#### `SujetoBatalla` (`src/Battle/Domain/Observer/SujetoBatalla.php`)

**Responsabilidad:** Sujeto notificador. Permite adjuntar observadores y notifica 3 eventos: `notifyEndTurn()`, `notifyDamaged()`, `notifyFainted()`.

---

#### `ObservadorBatalla` (`src/Battle/Domain/Observer/ObservadorBatalla.php`)

**Responsabilidad:** Interface de observador con 3 métodos: `onEndTurn()`, `onDamaged()`, `onFainted()`.

---

### Domain — Enums

---

#### `TipoClima` (`src/Battle/Domain/Enums/TipoClima.php`)

**Valores (7):** `NONE`, `SEQUIA`, `DILUVIO`, `NIEBLA`, `GRANIZO`, `TORMENTA_ARENA`, `TURBULENCIAS`.

**Métodos:** `label(): string` (retorna etiqueta en español: 'sequía', 'diluvio', etc.), `esClimaActivo(): bool` (true si no es NONE).

---

#### `EstadoPokemon` (`src/Battle/Domain/Enums/EstadoPokemon.php`)

**Valores (8):** `NONE`, `BURN`, `POISON`, `BAD_POISON`, `PARALYSIS`, `SLEEP`, `FREEZE`, `CONFUSION`.

**Métodos:** `label(): string` (español: 'quemadura', 'envenenamiento', etc.), `causaDanoPorRonda(): bool` (true para BURN, POISON, BAD_POISON).

---

#### `CategoriaMovimiento` (`src/Battle/Domain/Enums/CategoriaMovimiento.php`)

**Valores (3):** `FISICO`, `ESPECIAL`, `ESTADO`.

---

### Domain — Value Objects

---

#### `EtapasStats` (`src/Battle/Domain/ValueObjects/EtapasStats.php`)

**Responsabilidad:** Value Object inmutable que encapsula las etapas de estadísticas (-6 a +6).

**Mecánica:**
- `aplicarCambio(string $stat, int $cambio): self` — Retorna nueva instancia con cambio clamp -6..+6.
- `obtenerMultiplicador(string $stat): float` — Fórmula: `stage >= 0 → (2 + stage) / 2` (ej: +2 → ×2.0); `stage < 0 → 2 / (2 - stage)` (ej: -2 → ×0.5).
- `obtenerNoNeutras(): array` — Solo stages distintos de 0 (para UI).
- Validación en constructor: cada valor debe ser entero entre -6 y +6.

---

#### `ColeccionMovimientos` (`src/Battle/Domain/ValueObjects/ColeccionMovimientos.php`)

**Responsabilidad:** Colección tipada de `MovimientoBatalla` con `ArrayAccess`, `Countable`, `IteratorAggregate`. Sin dependencia de Illuminate (implementación nativa). Métodos: `add()`, `get()`, `all()`, `count()`, `isEmpty()`.

---

### Infrastructure

---

#### `FabricaBatallaMock` (`src/Battle/Infrastructure/FabricaBatallaMock.php`)

**Responsabilidad:** Implementación temporal de `FabricaBatallaInterface` con datos mock. Define 6 pokémon con stats, movimientos, efectos, items y shiny.

**Pokémon del equipo 1 ('Tú'):**
| id | nombre | rol | posicion | shiny | efectos | item |
|----|--------|-----|----------|-------|---------|------|
| player_1 | Gengar | SpA | RETAGUARDIA | no | armor_pierce | life_orb |
| player_2 | Giratina | Mixto | VANGUARDIA | sí | — | — |
| player_3 | Tyranitar | Físico | VANGUARDIA | sí | sandstorm_summoner | leftovers |

> **Tyranitar es ROCA/SINIESTRO** (corregido en el refactor 2026; antes solo SINIESTRO). Al ser tipo ROCA es inmune a su propia tormenta arena (`sandstorm_summoner`): el `CalculadorDañoClima` no le aplica el 6.25% al cierre de ronda.

**Pokémon del equipo 2 ('Rival'):**
| id | nombre | rol | posicion | shiny | efectos | item |
|----|--------|-----|----------|-------|---------|------|
| enemy_1 | Aggron | Físico | VANGUARDIA | no | regen_def | — |
| enemy_2 | Deoxys | SpA/Def | VANGUARDIA | no | niebla_summoner | — |
| enemy_3 | Mewtwo | SpA | RETAGUARDIA | no | — | life_orb |

**Métodos:** `createBattle(): AgregadoBatalla` (crea ambos equipos + triggerBattleStartEffects), `crearEquiposMock(): EquipoBatalla`, `generateTeam1()`/`generateTeam2()`.

**Inyección:** Recibe `?FabricaEfectos` en constructor (resuelto desde contenedor Laravel como singleton).

---

### Presentation (DTOs)

---

#### `DTOAccionBatalla` (`src/Battle/Presentation/DTOAccionBatalla.php`)

**Responsabilidad:** DTO de presentación Livewire Wireable para una acción de batalla pendiente. Reemplaza el array asociativo `pendingAction`.

**Propiedades:** `type`, `actorId`, `defenderId`, `attackerNombre`, `move` (DTOMovimientoBatalla — tipado).

**Nota:** `$move` ahora es `DTOMovimientoBatalla` (tipado, no `array`). Cambio del Cleaner de esta iteración.

---

#### `DTOEquipoBatalla` (`src/Battle/Presentation/DTOEquipoBatalla.php`)

**Responsabilidad:** DTO para datos de equipo de batalla. **No se usa actualmente** (deuda: eliminar).

**Propiedades:** `miembros` (array de `{pokemon, posicion}`).

---

#### `DTOMovimientoBatalla` (`src/Battle/Presentation/DTOMovimientoBatalla.php`)

**Responsabilidad:** DTO de presentación Livewire Wireable para `MovimientoBatalla`. Desacopla la entidad de dominio de Livewire.

**Métodos:** `desdeDominio(MovimientoBatalla): self` (Domain → DTO), `toDomain(): MovimientoBatalla` (DTO → Domain), `toLivewire(): array`, `fromLivewire($value): self`.

---

#### `DTOResultadoDanio` (`src/Battle/Presentation/DTOResultadoDanio.php`)

**Responsabilidad:** DTO que encapsula el resultado de calcular y aplicar daño.

**Propiedades:** `dano` (float), `directPct` (float).

---

### app/Livewire/Combate.php

**Responsabilidad:** Componente Livewire que orquesta la batalla manual (jugador vs IA). 653 líneas.

**Ciclo de turno (jugador):**
1. `nextActor()` → obtiene el siguiente actor del `GestorTurnos`.
2. Verifica estado (sueño, parálisis, etc.) → si no puede actuar, consume acción y avanza.
3. Si es jugador → `phase = 'player_target'`, muestra targets seleccionables.
4. `previewTarget(teamIdx, pokemonIdx)` → calcula preview de daño para cada movimiento, cambia a `phase = 'player_move'`.
5. `selectMove(index)` → establece `pendingAction` con `DTOAccionBatalla` (move tipado como `DTOMovimientoBatalla`), setea animación, guarda en sesión.
6. Alpine.js timeout 700ms → `commitAction()`.

**Ciclo de turno (IA):**
1. `nextActor()` detecta que es IA → `prepareAiAnimation()`.
2. Elige objetivo + mejor movimiento, setea `pendingAction` y animación.
3. Alpine.js timeout 700ms → `commitAction()`.

**commitAction():**
1. Recupera `pendingAction` de la sesión.
2. Convierte `DTOMovimientoBatalla` a `MovimientoBatalla` via `toDomain()`.
3. Construye `AccionBatalla` y llama a `ServicioEjecucionBatalla::calcularYAplicarDano()`.
4. Genera log, aplica estado, aplica stat changes, dispara eventos de efectos.
5. Consume acción, notifica debilitamiento, limpia pending, avanza al siguiente actor.

**Persistencia en sesión:**
- `SESSION_VERSION = 4` (prefijo `v{version}|{serialized}`).
- `getBattle()`: deserializa con control de versiones (tolera cambios de estructura).
- `saveBattle()`: serializa con versión.

**Propiedades públicas (vista):** `battleId`, `team1`, `team2`, `turnQueue`, `currentMoves`, `selectedMoveIdx`, `phase`, `round`, `log`, `actingRefId`, `processing`, `animAttackerId`, `animDefenderId`, `animAttackerNombre`, `animDefenderNombre`, `animMoveNombre`, `animTick`, `weather`, `selectedTargetTeam`, `selectedTargetIdx`, `selectedTargetRefId`.

**Fases (phase):** `init`, `player_target`, `player_move`, `animating`, `battle_over`.

---

### app/Providers

#### `BattleEffectServiceProvider` (`app/Providers/BattleEffectServiceProvider.php`)

**Responsabilidad:** Registra `FabricaEfectos` como singleton en el contenedor Laravel y registra todos los efectos (habilidades: armor_pierce, regen_def, sandstorm_summoner, sequia_summoner, diluvio_summoner, niebla_summoner, granizo_summoner, turbulencias_summoner) e items (leftovers → EfectoRestos, life_orb → EfectoOrbeVida).

#### `AppServiceProvider` (`app/Providers/AppServiceProvider.php`)

**Responsabilidad:** Registra `FabricaBatallaInterface::class → FabricaBatallaMock::class` (a través del contenedor Laravel). `FabricaBatallaMock` recibe automáticamente el singleton `FabricaEfectos` del container.

---

## Mecánicas de combate

| Mecánica | Implementación | Detalle |
|----------|---------------|---------|
| **Daño base** | Fórmula nivel 50 | `(((2*50/5+2) * power * atk / max(def,1)) / 50) + 2` |
| **Efectividad** | TypeChart 18×18 | 2.0 / 1.0 / 0.5 / 0.0 |
| **STAB** | ×1.5 | Si tipo atacante = tipo movimiento |
| **Crítico** | 6.25% ×1.5 | `mt_rand() / mt_getrandmax() < 0.0625` |
| **Posición** | -50% retaguardia | Si vanguardia enemiga viva |
| **Clima** | ±25% | 6 climas, efectos específicos por tipo |
| **Daño por clima (fin de ronda)** | 6.25% HP | Granizo a no-HIELO; tormenta arena a no-ROCA/TIERRA/ACERO (`CalculadorDañoClima`) |
| **Objetos equipados** | Mapa multiplicadores | `ManejadorObjetosEquipados`: life_orb → ×1.3 (recoil 10% en `EfectoOrbeVida`) |
| **Restos** | 1/16 HP/ronda | `onRoundEnd`, mínimo 1 |
| **Barreras duales** | Defensa Física / Especial | Daño descuenta de barrera antes de HP |
| **Perforación armadura** | 10% directo | Ignora barreras, va directo a HP |
| **Quemadura** | 6.25% HP/ronda | Daño por estado |
| **Veneno** | 12.5% HP/ronda | Daño por estado |
| **Veneno grave** | Contador/16 HP/ronda | Creciente cada ronda |
| **Parálisis** | 25% bloqueo + 50% velocidad | Probabilidad de no actuar; velocidad a la mitad |
| **Sueño** | 2-4 turnos | No puede actuar; se despierta al llegar a 0 |
| **Congelación** | 20% descongelar/turno | No puede actuar hasta descongelarse |
| **Confusión** | 33% auto-daño | 2-4 turnos; auto-daño con fórmula nivel 50 |
| **Etapas** | -6..+6 | Fórmula `(2+stage)/2` si ≥0, `2/(2-stage)` si <0 |
| **Velocidad acumulada** | Suma por ronda | `getNextActor()` = mayor velocidad acumulada |

---

## Contrato de datos de vista

### Por combatiente (`aArrayVista()`)

```php
[
    'refId' => string,
    'nombre' => string,
    'icon' => string,          // "/iconos/{iconName}.png" o "/iconos/shiny/{iconName}.png"
    'hp' => float,
    'maxHp' => float,
    'defHp' => float,
    'maxDefHp' => float,
    'spDefHp' => float,
    'maxSpDefHp' => float,
    'posicion' => 'vanguardia'|'retaguardia',
    'alive' => bool,
    'speed' => float,
    'accumulatedSpeed' => float,
    'status' => string,        // 'none'|'burn'|'poison'|'bad_poison'|'paralysis'|'sleep'|'freeze'|'confusion'
    'statusTurns' => int,
    'stages' => array<string, int>,  // ['attack' => 0, 'defense' => 0, ...]
    'team' => 0|1,
    'item' => string,
]
```

### Por movimiento en preview (`currentMoves`)

```php
[
    'nombre' => string,
    'tipo' => string,              // valor de TipoPokemon (int como string)
    'potencia' => int,
    'categoria' => string,         // 'fisico'|'especial'|'estado'
    'daño' => float,               // daño calculado
    'efectividad' => float,        // 2.0|1.0|0.5|0.0
    'stab' => bool,
    'directo' => bool,
    'statusEffect' => string,      // valor de EstadoPokemon
    'selfStatChanges' => array,    // [{stat, stages}]
    'targetStatChanges' => array,  // [{stat, stages}]
]
```

---

## Flujo de datos

### Batalla manual (Livewire)

```
Usuario click → Combate::mount() → nuevaBatalla() → FabricaBatallaMock::createBattle()
  → triggerBattleStartEffects() → syncViewData() → saveBattle() → nextActor()

nextActor() → getBattle() → turnManager.getNextActor()
  → Si IA: prepareAiAnimation() → setPendingAction() → setAnimState()
    → Alpine setTimeout 700ms → commitAction()
  → Si jugador: phase='player_target' → espera click

previewTarget(team, idx) → calcula daño preview → phase='player_move'
selectMove(index) → setPendingAction() → setAnimState()
  → Alpine setTimeout 700ms → commitAction()

commitAction() → getBattle() → pendingAction.toDomain()
  → AccionBatalla → ServicioEjecucionBatalla.calcularYAplicarDano()
    → CadenaDanio.calculate() (7 manejadores)
    → Combatiente.recibirDaño()
  → aplicarEstado() / aplicarStatChanges()
  → SujetoBatalla.notifyDamaged() / notifyFainted()
  → Efectos onDamageDealt / onDamageReceived
  → turnManager.consumeAction()
  → syncViewData() → saveBattle() → nextActor()
```

### Batalla automática (IA vs IA)

```
AgregadoBatalla::ejecutarBatalla()
  → loop while bothTeamsAlive():
    → startNewRound() (acumular velocidad)
    → loop while hayAlgunoConAccionPendiente():
      → getNextActor() → SelectorAccionIA.elegirObjetivoPara() → SelectorAccionIA.elegirMejorMovimiento()
      → ServicioEjecucionBatalla::calcularYAplicarDano()
      → aplicarEstado() / aplicarStatChanges()
      → SujetoBatalla.notifyDamaged() / notifyFainted()
      → Efectos onDamageDealt / onDamageReceived
      → consumeAction()
    → triggerRoundEndEffects() (efectos + daño estado + daño clima vía CalculadorDañoClima)
    → SujetoBatalla.notifyEndTurn()
  → determina ganador → retorna log[]
```

---

## Estado de la iteración (2026-08-30)

### Bugs corregidos (6)
Accesos a propiedades privadas de `PokemonEntity` (`$moves`, `$tiposCollection`) reemplazados por getters `moves()`, `tiposCollection()`:
1. `AgregadoBatalla.php:306` — `->moves->isEmpty()` → `->moves()->isEmpty()`
2. `AgregadoBatalla.php:313` — `->moves as $move` → `->moves() as $move`
3. `Combate.php:228` — `->moves->all()` → `->moves()->all()`
4. `Combate.php:427` — `->moves as $move` → `->moves() as $move`
5. `Combate.php:476` — `->moves->get($index)` → `->moves()->get($index)`
6. `Combate.php:533` — `->tiposCollection as $tipo` → `->tiposCollection() as $tipo`

> Nota: las líneas de `AgregadoBatalla` (306/313) corresponden al estado **pre-refactor**; tras extraer los servicios, el archivo quedó en ~255 líneas (la referencia histórica se conserva como registro del fix).

### Weather banner corregido
El `@switch($weather)` en `battle-field.blade.php` comparaba con valores ingleses ('sandstorm', 'sun', ...) pero `TipoClima` produce valores españoles. Corregido usando `TipoClima::tryFrom()?->label()` para texto y `match` con valores reales del enum para iconos.

### Ruta /combate reactivada
`Route::get('/combate', \App\Livewire\Combate::class)` descomentada dentro del grupo `middleware('auth')` en `routes/web.php`. Enlace "Combate" añadido al nav.

### Cambios Cleaner (refactor)
- `BattleAggregate` eliminado (era duplicado de `AgregadoBatalla`, marcado `@deprecated`).
- `EfectoInvocadorTormentaArena` eliminado (reemplazado por `EfectoInvocadorClima` genérico con `TipoClima::TORMENTA_ARENA`).
- `FabricaEfectos` encapsulada como instancia inyectable (no estática). Registrada como singleton en `BattleEffectServiceProvider`.
- `DTOAccionBatalla::$move` tipado como `DTOMovimientoBatalla` (antes `array`).
- `src/Battle/App/` eliminado (contenía `IniciarBatalla` y `BattleSrv` — ambos deprecados).

### Tests
- **140 tests de Battle** verdes (20 unit files + 1 trait helper + 1 feature file = 140 tests, 362 assertions).
- Suite completa: **533 passed**, 1 failed pre-existente (`ServicioCapturaTest` ajeno a Battle).
- QA PASS. Arquitecto APROBADO con deuda documentada.

---

## Iteración refactor (2026-08-30)

Refactor de diseño del módulo que resolvió 3 problemas (commits `f9f2b19`, `8ae08ed`, `105bed9` + cleaner `81dd62d`, `60d486d`, `1af0dd8` + docs `c33f785`/`d4e4d6a`):

1. **`ManejadorOrbeVida` → `ManejadorObjetosEquipados`** (`f9f2b19`): manejador genérico en la cadena de daño que consulta el objeto del atacante y aplica un multiplicador de un mapa centralizado `['life_orb' => 1.30]` (extensible). `CadenaDanio` usa el nuevo manejador como último eslabón (misma posición). `ManejadorOrbeVida.php` eliminado; `EfectoOrbeVida` NO se tocó (el recoil 10% sigue en `onDamageDealt`).
2. **Tyranitar corregido a ROCA/SINIESTRO + `CalculadorDañoClima` extraído** (`8ae08ed`): Tyranitar ya no sufre su propia tormenta arena (inmune por tipo ROCA). El método privado `AgregadoBatalla::calcularDañoClima` se extrajo al servicio `CalculadorDañoClima` (`calcular(Combatiente, TipoClima): float`, granizo 6.25% a no-HIELO, tormenta arena 6.25% a no-ROCA/TIERRA/ACERO, muerto/otros → 0). `SESSION_VERSION` 3 → 4.
3. **`SelectorAccionIA` extraído** (`105bed9`): la lógica de elegir objetivo + mejor movimiento salió de `AgregadoBatalla` al servicio `SelectorAccionIA`. `AgregadoBatalla` conserva la API pública `elegirObjetivoPara`/`elegirMejorMovimiento` como delegación (usada por `Combate::prepareAiAnimation`).

**Impacto:** `AgregadoBatalla` pasó de 327 → ~255 líneas. MSI de Infection en `src/Battle` subió al **~80%** de código cubierto (Covered Code MSI 80%, ver Testing). Los 3 servicios se instancian en el agregado con lazy getters nullable (`??=`) para no romper la serialización de sesión (PHP serializa `null`).

### Pendientes / Deuda (del Arquitecto)
1. **Ciclo Pokemon↔Battle**: `MovimientoBatalla` debería moverse a `src/Pokemon/Domain/Movement/` (dependencia cruzada).
2. **DTOEquipoBatalla no usado**: debería eliminarse.
3. **AgregadoBatalla importa DTOAccionBatalla** (Presentation): inversión de dependencia Domain→Presentation.
4. **Strings mágicos de stats sin enum**: 26 ocurrencias de strings como 'attack', 'defense', 'speed', etc. sin enum tipado.
5. **`max(1,...)` en CadenaDanio**: anula inmunidad de tipos (×0 → mínimo 1).
6. **StatsValue nullable**: `?float` en propiedades (deuda por diseño).
7. **Combate.php god-component**: 653 líneas, responsabilidades mezcladas (ciclo de turno, IA, persistencia, animación, sincronización de vista).
8. **Combatiente/AgregadoBatalla god-classes**: 606 y ~255 líneas respectivamente.
9. **Infection MSI ~80%**: cobertura del módulo en Covered Code MSI 80% (mutantes escapados de `SelectorAccionIA` son equivalentes/neutros); pendiente cerrar la cobertura del 20% restante (código muerto/no cubierto).

### Integración futura
- **Datos reales**: reemplazar `FabricaBatallaMock` con datos de reclutamiento (equipos reales del jugador).
- **PvP**: extender `AgregadoBatalla` para dos jugadores humanos (propuesta: sincronización vía WebSockets/Laravel Reverb o polling).

---

## Dependencias del módulo

```
src/Battle/Domain → src/Pokemon/Domain (PokemonEntity, StatsValue, BattleStats)
                  → src/Shared/Tipos (TipoPokemon, TiposCollection, TypeChart)

app/Livewire/Combate.php → src/Battle/Domain/ (AgregadoBatalla, Combatiente, etc.)
                         → src/Battle/Presentation/ (DTOAccionBatalla, DTOMovimientoBatalla)

app/Providers/AppServiceProvider.php → src/Battle (FabricaBatallaInterface, FabricaBatallaMock)
app/Providers/BattleEffectServiceProvider.php → src/Battle/Domain/Effects/ (FabricaEfectos, efectos)
```

---

## Decisiones arquitectónicas clave

1. **Código en español**: nombres de dominio en español (AgregadoBatalla, Combatiente, ServicioEjecucionBatalla, etc.). Los valores de enums (TipoClima, EstadoPokemon) están en inglés para consistencia con el sistema de tipos.
2. **Mecánica de barreras propia**: no es estándar Pokémon. Cada combatiente tiene barreras de defensa física y especial que absorben daño antes que el HP. Esto es una mecánica propia del juego.
3. **Chain of Responsibility**: 7 manejadores en orden fijo. Fácil de extender añadiendo nuevos manejadores al final de la cadena en `CadenaDanio::__construct()`.
4. **Efectos como Strategy**: `InterfazEfecto` con 11 hooks de ciclo de vida. Los efectos se registran por clave en `FabricaEfectos`. Para añadir un nuevo efecto, solo se necesita la clase + registro en `BattleEffectServiceProvider`.
5. **Observer para eventos**: `SujetoBatalla` notifica daño, debilitamiento y fin de turno. Los efectos se suscriben indirectamente a través de `ColeccionEfectos`.
6. **Serialización en sesión con versionado**: `v{version}|{serialized}`. `SESSION_VERSION=4` en `Combate.php`. Permite migrar datos entre cambios estructurales. Los servicios del agregado se guardan como propiedades nullable + lazy getter (`??=`) para que la deserialización de versiones antiguas no falle.
7. **FabricaEfectos inyectable**: no es estática. Se pasa como dependencia a `EquipoBatalla::fromData()` y `FabricaBatallaMock`. Registrada como singleton en Laravel.
8. **Dominio sin dependencias de Laravel**: `src/Battle/Domain/` no importa nada de `app/` ni de Illuminate. Las colecciones usan implementaciones nativas (`ArrayIterator`), no `Illuminate\Support\Collection`.

---

## Testing

- **Ubicación**: `tests/Unit/Battle/` (21 archivos: 20 tests + trait `ConstruyeCombatientes`) + `tests/Feature/PokemonBattleTest.php`. Total: **140 tests** (362 assertions).
- **Estrategia de construcción de combatientes (unit tests, sin boot de Laravel ni BD)**: dos enfoques:
  1. **Build directo**: `new Combatiente(new PokemonEntity(...), Posicion::VANGUARDIA)` con setters para id/nombre/item/effects.
  2. **EquipoBatalla::fromData()**: `EquipoBatalla::fromData([$dato], 'Equipo')` con `DatosPokemonBatalla` mínimo.
  Para efectos (Orbe Vida, Restos, Invocador Clima) se construyen las instancias directamente y se añaden vía `$combatant->effects()->add($efecto)`, evitando dependencia de `FabricaEfectos` (requiere app boot). Helper compartido: trait `ConstruyeCombatientes` (`combatiente()`, `batallaMinima()`).
- **RNG determinista**: `ManejadorCritico` y `procesarParalysis` usan `mt_rand`. Semillas verificadas: `mt_srand(1)` → no crit (0.417 > 0.0625) y parálisis permite actuar (40 > 25); `mt_srand(100)` → parálisis bloquea (13 ≤ 25).
- **Feature test**: `PokemonBattleTest` migrado de `BattleAggregate` (@deprecated) a `AgregadoBatalla` + `FabricaBatallaMock::createBattle()` (requiere boot de Laravel + BD).
- **Cobertura**: Infection `src/Battle` con **Covered Code MSI 80%** (656/816 mutantes matados; el MSI bruto incluye código sin cobertura). Se ejecuta con `--filter=src/Battle --only-covering-test-cases --test-framework-extra-args='--filter=Battle'`. Los mutantes escapados de `SelectorAccionIA` son equivalentes/neutros (rama retaguardia coincide con fallback; score inicial -1 no altera resultado con movimientos). Pendiente cerrar el 20% restante.

---

## Referencias

| Archivo | Propósito |
|---------|-----------|
| `docs/analysis/pokemon-showdown/02-formulas-constantes.md` | Fórmulas oficiales de daño, stats, STAB, crítico, efectividad — referencia para `CadenaDanio` |
| `docs/analysis/pokemon-showdown/03-mecanicas-batalla.md` | Flujo de turnos, cola de acciones, targeting, estados — referencia para `GestorTurnos` y estados |
| `docs/analysis/pokemon-showdown/06-recomendaciones-migracion.md` | Recomendaciones concretas para evolución del motor de batalla |
| `docs/analysis/pokemon-showdown/07-indice-cruzado.md` | Índice cruzado PS↔proyecto: navegación rápida por concepto, guía "quiero X", tabla de tipos, mapeo de archivos y glosario |
| `src/Battle/Domain/AgregadoBatalla.php` | Agregado raíz, ciclo de batalla (~255 líneas) |
| `src/Battle/Domain/CalculadorDañoClima.php` | Daño por clima (granizo/tormenta arena) |
| `src/Battle/Domain/Combatiente.php` | Entidad de combatiente |
| `src/Battle/Domain/EquipoBatalla.php` | Colección de combatientes |
| `src/Battle/Domain/GestorTurnos.php` | Gestión de rondas y turnos |
| `src/Battle/Domain/SelectorAccionIA.php` | IA de selección de objetivo/movimiento |
| `src/Battle/Domain/ServicioEjecucionBatalla.php` | Servicio de ejecución de movimientos |
| `src/Battle/Domain/Chain/CadenaDanio.php` | Cadena de daño (7 manejadores, último `ManejadorObjetosEquipados`) |
| `src/Battle/Domain/Chain/ManejadorObjetosEquipados.php` | Multiplicador de objeto equipado (life_orb → 1.30) |
| `src/Battle/Domain/Effects/FabricaEfectos.php` | Fábrica de efectos |
| `src/Battle/Infrastructure/FabricaBatallaMock.php` | Datos mock de batalla |
| `src/Battle/Presentation/DTOAccionBatalla.php` | DTO de acción pendiente |
| `src/Battle/Presentation/DTOMovimientoBatalla.php` | DTO de movimiento (Wireable) |
| `app/Livewire/Combate.php` | Componente Livewire de batalla manual (SESSION_VERSION=4) |
| `app/Providers/BattleEffectServiceProvider.php` | Registro de efectos e items |
| `app/Providers/AppServiceProvider.php` | Binding FabricaBatallaInterface |
| `resources/views/livewire/combate.blade.php` | Vista principal de combate |
| `resources/views/livewire/partials/battle-field.blade.php` | Campo de combate + weather banner |
| `resources/views/livewire/partials/moves-panel.blade.php` | Panel de movimientos |
| `resources/views/livewire/partials/turn-bar.blade.php` | Barra de turnos |
| `resources/views/livewire/_pokemon-card.blade.php` | Card de pokémon en batalla |
| `tests/Unit/Battle/` (21 archivos: 20 tests + trait) | Tests unitarios de mecánicas |
| `tests/Feature/PokemonBattleTest.php` | Test de integración de batalla |