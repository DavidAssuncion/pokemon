# TASK-004 — Plan v5

- **ID:** TASK-004
- **Versión:** v5
- **Sustituye a:** v4
- **Estado:** APROBADO
- **Fecha:** 2026-11-02

## 0. Cambios respecto a v2

La base económica, los perfiles de IV/EV, los precios de granja y la decisión de
no añadir límites nuevos se mantienen. v5 añade la tercera ampliación con el
feedback del desarrollador sobre el pool y el tamaño del moveset rival:

| # | Cambio | v2 | v5 |
|---|---|---|---|
| 1 | Niveles de Senda | 5 niveles, ataques en 1-5 | **Ataques visibles en 1-3; 4-5 pasivos** |
| 2 | Nodos de ataque | 187/114/160/104/74 | **187/114/338** visibles (los 178 de 4-5 se desbloquean en 3) |
| 3 | Coste de nodo | 2/5/10/25/60 por nivel | **2/5/10/25/60 por tramo**; el tramo 4-5 se conserva como coste de maestría |
| 4 | Total de nodos | 9.584 | **9.584** (sin cambio) |
| 5 | Pasivos | — | N4 **+10 %**, N5 **+20 %** en captura, daño infligido, daño recibido y caramelos de tipo |
| 6 | Captura | Cap 25 % | Cap 25 % base; **30 % con Senda 5** |
| 7 | Movesets rivales | Todos los movimientos de nivel | **6 movimientos** con STAB + cobertura/utilidad y tope por tier |
| 8 | Pool del rival | Solo learnset | **Nivel disponible ∪ (aprendible por MT/tutor/huevo ∩ desbloqueado por el jugador)** |
| 9 | Showdown | No se usa | **Artefacto JSON build-time** con roles y movepools; sin Node en runtime |
| 10 | Movimientos del jugador | 6 | **6** (sin cambio); el rival también usa 6 |
| 11 | Valores ajustables | Dispersos | **Constantes/config centralizadas** en `config/sendas.php` y `config/combate.php` |
| 12 | Fases | 1, 2A, 2B, 3, 4, 5 | **1, 2A, 2B (Senda), 2C (movesets), 2D (economía), 3 (UI), 4 (docs), 5 (validación)** |

## 1. Objetivo

Rebalancear la economía de objetos y la dificultad de los combates para que:

1. Los costes se expliquen en horas de juego.
2. Las Sendas tengan una progresión clara: **niveles 1-3 desbloquean ataques**;
   **niveles 4-5** otorgan maestrías pasivas de tipo.
3. Los rivales usen **6 movimientos** con STAB + cobertura/utilidad, puedan usar
   sus movimientos por nivel **y** los que el jugador haya desbloqueado **si su
   especie puede aprenderlos por MT/tutor/huevo**, y **no hereden** sus bonus.
4. Los niveles 2-3 de hábitat sigan siendo más rentables por minuto que el
   nivel 1 para quien puede ganarlos.
5. La economía quede preparada para ajustes futuros con tests deterministas.

**Resultado esperado (base, sin pasivos):** Senda N5 en ~9,9 h; 18 Sendas en
~306 h; granja completa en ~36,6 h; evolución N20 en ~1,75 h; tipo/h en nivel 2
~72,6. **Con Senda 5:** Sendas ~9,0 h; 18 Sendas ~292 h; granja ~33 h;
evolución ~1,33 h (sensibilidad, no objetivo base).

## 2. Decisiones que se aprueban con este plan

### 2.1 Base v2 que se conserva

| # | Decisión | Valor |
|---|---|---|
| D1 | Escala IV/EV | IV máximo **15**; EV máximo **255**; mitad **8 IV / 128 EV**. |
| D2 | Perfiles de rival | `Nulo` (0/0), `TopUno` (1 stat 15/255, resto 0), `TopDos` (2 stats 15/255, resto 8/128), `Completo` (6 stats 15/255). |
| D3 | Rutas | Nivel 1 → `Nulo`; nivel 2 → `TopDos`; nivel 3 → `Completo`. |
| D4 | Entrenadores | Nivel 1 → `TopUno`; nivel 2 → `TopDos`; nivel 3 → `Completo`. |
| D5 | Gimnasios | 1-6 → `TopUno`; 7-12 → `TopDos`; 13-18 → `Completo`; líder +1 tier con tope en `Completo`. |
| D6 | Shiny | En batalla usa el perfil de su tier; al capturarlo conserva IV 15. |
| D7 | Mazmorra IV/EV | Fuera de la política en v5. |
| D8 | Multiplicadores de dificultad | `Nulo`/`TopUno`: 1,00/1,00/1,00; `TopDos`: **3,30 familia / 1,85 EV / 1,90 tipo**; `Completo`: **3,35 / 2,80 / 2,40**. |
| D9 | Granja | Nido **150**, refugio **180**, complemento **250 familia + 255 EV** (51 EV/nivel). |
| D10 | Tope EV | **255**; se elimina el 512. Los EV >255 quedan como legado. |
| D11 | Caramelo de tipo | Divisor **500 → 250**; 1 caramelo = 100 EXP de tipo. |
| D12 | Captura | `p = min(0,25, (capture_rate/255) × factor)` con factor **1,0 / 0,5 / 0,3** para hábitat 1/2/3. |
| D13 | Descarte | `max(1, intdiv(fase, 2))` caramelos de familia; **sin tope diario**. |
| D14 | Límites | **No se añaden límites nuevos** a ruta, exploración ni descarte. Se mantiene la renovación diaria de entrenadores (9/hábitat/día, solo al ganar). |
| D15 | Exploración | Eventos por tiempo transcurrido; máximo 12 por procesamiento; hallazgos ≥1 caramelo; sin filas de cantidad 0; emboscada evitada sin recompensa doble; derrota pierde `intdiv(cantidad, 2)`. |
| D16 | Mazmorra | Caramelos **×70**, EXP **×10**. |
| D17 | Sumidero de familia | Fuera de v5. |
| D18 | Siniestro sin N5 | Se documenta como excepción. |
| D19 | Compensación refugio 2.700 | Fuera de v5; se documenta como riesgo. |

### 2.2 Nuevas decisiones de v5

| # | Decisión | Valor |
|---|---|---|
| D20 | Niveles visibles de Senda | **1-3** desbloquean ataques; **4-5** son maestrías pasivas. |
| D21 | Compresión de nodos | `nivelVisible = min(3, max(1, required_level))`; `required_level` 4-5 se conserva como **tramo de coste**. Sin migración. |
| D22 | Reparto visible | **187 / 114 / 338** en la BD actual (639 nodos); **186 / 114 / 332** en seeder limpio (632 nodos). La diferencia son 7 movimientos de formas no disponibles. |
| D23 | Coste de Senda | **2 / 5 / 10 / 25 / 60** por tramo 1-5. Total **9.584** en la BD actual; **9.432** en seeder limpio. La lógica es la misma; cambia el catálogo de datos. |
| D24 | Umbrales | **0 / 9.000 / 18.000 / 36.000 / 72.000**; `EXP_MAXIMA` **72.000**; N5 = **720 caramelos** a 100 EXP/caramelo. |
| D25 | Beneficios N4/N5 | **N4 +10 %**, **N5 +20 %** en captura, daño infligido, daño recibido (reducción) y caramelos de tipo. **Absolutos, no compuestos**. |
| D26 | Ámbito de los pasivos | Solo al jugador; los rivales tienen mapa vacío. Aplican a ruta, exploración, entrenador y gimnasio; **no a mazmorra** en v5. |
| D27 | Tipo doble | Captura: **max** de las dos Sendas. Caramelos: **por tipo**, sin suma. Daño/defensa: por tipo del movimiento. |
| D28 | Cap de captura | **30 %** con Senda 5; única excepción al cap-25. Fórmula en §4.3. |
| D29 | Movesets rivales | **6 movimientos**; el jugador también conserva **6**. |
| D30 | Pool del rival | **Nivel disponible** (`level-up` al nivel del rival) **∪** (`aprendible` por `level-up` de nivel superior, MT, tutor o huevo **∩** desbloqueado por el jugador). No depende del nivel de Senda; sí del progreso de desbloqueo. |
| D31 | Topes de potencia | Tabla §4.5; alineados con los tiers IV/EV de D1-D5. |
| D32 | Showdown | **Artefacto JSON build-time**; sin tabla ni Node en runtime; comando `showdown:build-movesets`; MIT. |
| D33 | Gimnasio | Los sets manuales tienen **prioridad**; el selector solo rellena los que no tienen set. |
| D34 | Determinismo | Semilla `crc32(contexto|nivel|fecha|slot)`; solo desempata, no altera la puntuación. |
| D35 | Objetivos económicos | **Base sin pasivos**; la sensibilidad con Senda 5 se mide aparte. |
| D36 | Jugador 6 / rival 6 | Simetría total; ambos usan 6 movimientos. |
| D37 | Valores ajustables | **Constantes/config centralizadas** en `config/sendas.php` y `config/combate.php`; el dominio recibe VOs y no lee `config()` directamente. |

> **Punto de interpretación D30:** el rival conserva sus movimientos por nivel.
> Además, si tú has desbloqueado un movimiento que **su especie puede aprender
> por MT, tutor o huevo** —o por nivel a una altura superior a la suya—, el rival
> puede usarlo si le encaja, aunque no lo tenga por nivel o le falte nivel. No
> puede usar un movimiento que su especie no puede aprender, aunque tú lo tengas
> desbloqueado. Ejemplo: si tienes Lanzallamas y el rival es una especie que lo
> aprende por MT, puede llevarlo; un Pidgey no puede, porque no lo aprende.
>
> **Punto de interpretación D37:** el tamaño del moveset (6), los topes de
> potencia, los máximos de utilidad/setup, los umbrales, los costes y los
> porcentajes de los pasivos viven en configuración/constantes. Puedes ajustarlos
> después sin tocar la lógica.

## 3. Alcance

### Incluido

- Corrección de la contabilidad de EXP y del reparto de recompensas.
- Política de IV/EV por tier y su integración en ruta, entrenador y gimnasio.
- **Estructura de Senda:** niveles visibles 1-3, maestrías 4-5, coste por tramo,
  umbrales, beneficios pasivos y su aplicación en captura, daño y caramelos.
- **Selector de 6 movimientos para rivales:** pool permitido + desbloqueos del
  jugador, preferencia Showdown, roles, topes por tier, determinismo y golden
  files.
- **Constantes/configuración ajustable** para tamaño de moveset, topes, umbrales,
  costes y porcentajes de pasivos.
- **Artefacto Showdown build-time** y su comando de regeneración.
- Re-precio de Sendas, nido, refugio y complemento; divisor de tipo 250.
- Ajuste de captura y descarte (sin topes nuevos).
- Corrección de la cadencia de exploración y de la persistencia de recompensas.
- Coherencia de preview/UI: coste real, EXP de Senda, tier del rival, moveset,
  coste por hábitat, aviso de EV parcial y de movimientos no desbloqueados.
- Tests unitarios, feature, simulación económica y golden files.
- Documentación de la economía, de las Sendas maestras y ADR.

### Fuera de alcance

- Rebalanceo de stats de combate, movimientos, habilidades o fórmula de daño
  (salvo los multiplicadores de Senda y la selección de movesets rivales).
- Política IV/EV de mazmorras.
- Pasivos de Senda en mazmorra.
- Nuevos recursos, monedas o energía.
- Conversión familia → tipo.
- Añadir nodos N5 a Siniestro.
- Compensación retroactiva por el refugio a 2.700.
- Cambiar la selección de especies por `capture_rate` (H4).
- Cualquier límite diario nuevo (ruta, exploración o descarte).
- Reducir el loadout del jugador por debajo de 6 movimientos.

## 4. Arquitectura y contratos

### 4.1 Nuevos conceptos

| Clase | Ubicación | Responsabilidad |
|---|---|---|
| `NivelSenda` (enum) | `src/Shared/Domain/Sendas` | N1-N5; `visible()` = `min(3, nivel)`; `tienePasivos()`. |
| `BeneficiosSenda` | `src/Shared/Domain/Sendas` | Captura, daño infligido, daño recibido, caramelos; 0 / 0,10 / 0,20. |
| `MapaSendaJugador` | `src/Shared/Domain/Sendas` | Mapa `tipo_id => nivel`; `bonoCaptura()`, `bonoDano()`, `bonoDefensa()`, `bonoCaramelo()`. |
| `PoliticaSenda` | `src/Shared/Domain/Sendas` | Factores 1,0/1,1/1,2 y 1,0/0,9/0,8; caramelos brutos sin floor. |
| `CurvaSenda` | `src/Shared/Domain/Sendas` | Umbrales 0/9k/18k/36k/72k; `nivel(exp)`; `EXP_MAXIMA`. |
| `CatalogoMovimientosSenda` | `src/Battle/Domain/Repositories` + infra | Puerto: movimientos de un tipo con tramo 1-5; implementación Eloquent. |
| `SelectorMovimientosRival` | `src/Battle/Domain/Selector` | Elige 6 movimientos deterministas. |
| `PuntuadorMovimiento` | `src/Battle/Domain/Selector` | Score STAB/cobertura/utilidad/rol/precisión. |
| `PerfilMovesetRival` | `src/Battle/Domain/Selector` | Tier, cap de potencia, rol, número de slots y máximos de utilidad/setup. |
| `FiltroMovimientosRival` | `src/Battle/Domain/Selector` | Excluye OHKO, fijo, precisión <70, recoil >33 %, autodestrucción, dos turnos, efectos no implementados. |
| `ManejadorSendaOfensiva` | `src/Battle/Domain/Chain` | Multiplica daño por `1 + bonoDano(tipo)`. |
| `ManejadorSendaDefensiva` | `src/Battle/Domain/Chain` | Multiplica daño por `1 − bonoDefensa(tipo)`. |
| `BuildShowdownMovesets` | `app/Console/Commands` | Genera `resources/data/showdown_movesets.json`. |
| `ConfiguracionSenda` / `ConfiguracionCombate` | `src/Shared/App` + `config/` | Lee `config/sendas.php` y `config/combate.php` y construye VOs para el dominio. |
| `MapaSendaJugador` en `Combatiente` | `src/Battle/Domain` | Campo serializable, default vacío. |

### 4.2 Contratos principales

```php
enum NivelSenda: int {
    case N1 = 1; case N2 = 2; case N3 = 3; case N4 = 4; case N5 = 5;

    public static function desdeInt(int $nivel): self; // clamp 1..5
    public function visible(): int;                     // min(3, nivel)
    public function tienePasivos(): bool;               // >= 4
}

final readonly class BeneficiosSenda {
    public function __construct(
        public float $captura,
        public float $danoInfligido,
        public float $danoRecibido, // reducción
        public float $carameloTipo,
    ) {}

    public static function sinBonus(): self;
    public static function para(NivelSenda $nivel): self;
}

final readonly class MapaSendaJugador {
    public static function vacio(): self;
    /** @param array<int,int> $niveles tipo_id => nivel */
    public static function desdeArray(array $niveles): self;

    public function nivel(TipoPokemon $tipo): NivelSenda;
    public function beneficios(TipoPokemon $tipo): BeneficiosSenda;
    public function bonoCaptura(array $tipos): float; // max de los tipos
    public function bonoDano(TipoPokemon $tipo): float;
    public function bonoDefensa(TipoPokemon $tipo): float;
    public function bonoCaramelo(TipoPokemon $tipo): float;
    /** @return array<int,int> */
    public function toArray(): array;
}

final class PoliticaSenda {
    public static function factorDanoInfligido(MapaSendaJugador $mapa, TipoPokemon $tipo): float; // 1.0|1.1|1.2
    public static function factorDanoRecibido(MapaSendaJugador $mapa, TipoPokemon $tipo): float;  // 1.0|0.9|0.8
    public static function factorCaptura(MapaSendaJugador $mapa, array $tipos): float;            // 1.0|1.1|1.2
    public static function factorCaramelo(MapaSendaJugador $mapa, TipoPokemon $tipo): float;     // 1.0|1.1|1.2
}

final class CurvaSenda {
    public const EXP_MAXIMA = 72_000;
    public const EXP_POR_CARAMELO = 100;
    public const NIVEL_MAXIMO = 5;
    public static function umbral(int $nivel): int; // 0|9.000|18.000|36.000|72.000
    public static function nivel(int $exp): int;
}

final class SelectorMovimientosRival {
    /**
     * @param list<string> $movimientosDesbloqueados slugs desbloqueados por el jugador
     * @param list<string>|null $movimientosManuales
     * @return MovimientoBatallaCollection // hasta 6; exactamente 6 si el pool lo permite
     */
    public function seleccionar(
        Pokemon $pokemon,
        int $nivel,
        PerfilMovesetRival $perfil,
        array $movimientosDesbloqueados = [],
        ?array $movimientosManuales = null,
        ?callable $aleatorio = null,
    ): MovimientoBatallaCollection;
}
```

### 4.3 Reglas de composición

**Senda visible y coste:**

```text
nivelVisible = min(3, max(1, required_level))
coste        = SendaCostes::COSTE[required_level]  // 2/5/10/25/60
```

- El nodo de tramo 4-5 se desbloquea en Senda 3 y se muestra como
  **“Maestría · tramo 4/5”**.
- No hay migración: el dato `required_level` no cambia.
- `ArbolSendas` expone `nivel_requerido` (visible), `tramo` y `coste`.

**Captura:**

```text
pBase = min(0.25, (capture_rate / 255) * factor_habitat)   // 1,0 / 0,5 / 0,3
p     = min(0.30, pBase * factor_refugio * (1 + bonoCaptura))
```

**Caramelos de tipo:**

```text
caramelos = floor(expTipo / 250 * M * m_tipo * (1 + bonoCaramelo))
```

**Daño:**

```text
dano = floor(base * STAB * efectividad * clima * ... * (1 + bonoDano) * (1 - bonoDefensa))
```

- Un solo `floor` al final de la cadena.
- El daño fijo (`formulaDano !== 'normal'`) no recibe Senda.
- STAB, habilidades, objetos y clima siguen siendo manejadores independientes.

**Beneficios por nivel:**

| Nivel | Captura | Daño infligido | Daño recibido | Caramelos de tipo |
|---:|---:|---:|---:|---:|
| 1-3 | 0 % | 0 % | 0 % | 0 % |
| 4 | +10 % | +10 % | −10 % | +10 % |
| 5 | +20 % | +20 % | −20 % | +20 % |

- Absolutos, no compuestos: N5 = 1,20, no 1,21.
- Solo al jugador; los rivales tienen `MapaSendaJugador::vacio()`.
- Captura de duales: `max` de las dos Sendas; caramelos: por tipo.
- Sin pasivos en mazmorra.

### 4.4 Puntos de aplicación

| Beneficio | Punto | Archivo |
|---|---|---|
| Construcción del equipo | `desdeEquipo()` recibe `MapaSendaJugador` | `src/CombateEntrenadores/App/ConstruirEquipoJugador.php` |
| Datos de batalla | campo opcional `senda` | `src/Battle/Domain/DatosPokemonBatalla.php` |
| Equipo | `setSenda()` en cada combatiente | `src/Battle/Domain/EquipoBatalla.php` |
| Serialización | `senda` → `array<int,int>`; default vacío | `src/Battle/Domain/Combatiente.php` |
| Daño ofensivo | `ManejadorSendaOfensiva` tras `ManejadorBonificadorDanio` | `src/Battle/Domain/Chain/CadenaDanio.php` |
| Daño defensivo | `ManejadorSendaDefensiva` antes de `ManejadorAguante` | `src/Battle/Domain/Chain/CadenaDanio.php` |
| Captura ruta | `rollCaptura()` | `src/CombateRuta/App/OtorgarRecompensasRuta.php` |
| Captura exploración | `tiradaCaptura()` | `src/Exploraciones/App/FinalizarExploracionHandler.php` |
| Caramelos de tipo | `calcularCaramelosTipo()` | `src/Exploraciones/Domain/CalculadorRecompensas.php` |
| Selector ruta | `GeneradorEquipoRuta::generar()` | `src/CombateRuta/App/GeneradorEquipoRuta.php` |
| Selector entrenador | `GeneradorEquipoEntrenador::generar()` | `src/CombateEntrenadores/App/GeneradorEquipoEntrenador.php` |
| Selector gimnasio | `GeneradorPokemonGimnasio::movimientosDe()` | `src/Gimnasios/App/GeneradorPokemonGimnasio.php` |
| Loadout jugador | máximo 6, sin cambio | `app/Http/Controllers/ReclutadoController.php` |

### 4.5 Selector de 6 movimientos

**Universo de candidatos (unión):**

```text
C = nivelDisponible(especie, nivel)
    ∪ (aprendible(especie) ∩ desbloqueados(jugador))

nivelDisponible = level-up con nivel_requerido <= nivel del rival
aprendible      = level-up (cualquier nivel) ∪ machine ∪ tutor ∪ egg
desbloqueados   = PlayerMoveUnlock del usuario
```

- `nivelDisponible` = movimientos que el rival ya tiene por nivel.
- `aprendible` = todos los movimientos que su especie puede aprender por
  cualquier vía (nivel, MT, tutor, huevo), filtrados a movimientos existentes y
  soportados por el motor.
- `desbloqueados` = movimientos que el jugador ha desbloqueado en sus Sendas
  (`PlayerMoveUnlock`). Se cargan en la capa de aplicación y se pasan como
  `list<string>`; el dominio no consulta la BD.
- La intersección `aprendible ∩ desbloqueados` es la clave: el rival solo usa un
  movimiento desbloqueado por ti si su especie puede aprenderlo. Así puede
  llevar un movimiento de MT que tú has desbloqueado, aunque no lo tenga por
  nivel o le falte nivel; pero no puede llevar un movimiento que su especie no
  aprende.
- **No se exige que el movimiento sea del tipo del rival.** Si tú has
  desbloqueado un movimiento que su especie aprende por MT/tutor/huevo, puede
  usarlo como cobertura.
- El pool no depende del nivel de Senda del jugador, solo de sus desbloqueos.
- No se inventan movimientos: solo los que existen en el catálogo y pasan el
  filtro de implementación.

**Puntuación:**

```text
score = potenciaEfectiva
      * factorSTAB          // 1.5 si tipo ∈ tipos de la especie; 1.0 si no
      * factorCobertura     // 1.0 / 1.15 / 1.25 según tipos superefectivos
      * factorRol           // tabla de roles
      * factorPrecision     // max(0.5, accuracy/100); null → 1.0
      * factorPenalizacion  // recoil 0.9; dos turnos 0.7; autodestrucción 0
```

**Slots (6):**

1. Mejor STAB físico.
2. Mejor STAB especial (si el pool lo permite; si no, mejor STAB restante).
3. Mejor cobertura de tipo distinto.
4. Segunda cobertura o STAB de otro tipo.
5. Mejor utilidad/setup permitido por el rol.
6. Mejor candidato restante.

El rol decide cuántos slots se reservan a utilidad/setup. Si el pool no tiene
suficientes movimientos distintos, los slots restantes se rellenan con los
mejores candidatos; nunca se duplica un movimiento.

**Topes de potencia:**

| Contenido | Tier IV/EV | Slots | Cap | Máx. utilidad | Máx. setup |
|---|---|---:|---:|---:|---:|
| Ruta 1 | T0 | 6 | 60 | 1 | 0 |
| Ruta 2 | T2 | 6 | 90 | 2 | 1 |
| Ruta 3 | T3 | 6 | 110 | 2 | 1 |
| Entrenador 1 | T1 | 6 | 70 | 1 | 0 |
| Entrenador 2 | T2 | 6 | 95 | 2 | 1 |
| Entrenador 3 | T3 | 6 | 120 | 2 | 1 |
| Gimnasio 1-6 | T1 | 6 | 80 | 2 | 0 |
| Gimnasio 7-12 | T2 | 6 | 100 | 2 | 1 |
| Gimnasio 13-18 | T3 | 6 | 120 | 2 | 1 |
| Líder 1-6 | T2 | 6 | 90 | 2 | 1 |
| Líder 7-12 | T3 | 6 | 110 | 2 | 1 |
| Líder 13-18 | T3 | 6 | 130 | 2 | 1 |

**Roles (plantillas de 6):**

| Rol Showdown | Plantilla | Pesos |
|---|---|---|
| `Fast Attacker` | 4 ataques + 1 utilidad + 1 relleno | potencia ×1,1; prioridad +10; estado ×0,7 |
| `Bulky Attacker` | 4 ataques + 1 recuperación/estado + 1 relleno | potencia ×1,0; recoil ×0,9; estado ×0,9 |
| `Bulky Support` | 3 ataques + 3 utilidades | utilidad ×1,2; recuperación ×1,3; potencia ×0,9 |
| `Setup Sweeper` | 1 setup + 4 ataques + 1 relleno | setup ×1,4; STAB ×1,1 |
| `Stall` | 2 ataques + 4 utilidades | estado ×1,4; recuperación ×1,3; potencia ×0,7 |
| `Lider` | 3 STAB + 2 cobertura + 1 utilidad | STAB ×1,1; cap +10 |
| Sin Showdown | Rol por stats | speed ≥100 → Fast; hp+def+spDef ≥250 → Bulky Support; resto → Bulky Attacker |

**Exclusiones:** OHKO, daño fijo, precisión <70, recoil >33 %, autodestrucción,
dos turnos, efectos no implementados, estado sin efecto. `DepuradorMovimientos`
se aplica antes de puntuar.

**Constantes y configuración ajustable:**

- `config/combate.php`:
  - `rival.slots` = **6**.
  - `rival.caps` = tabla de topes por contenido/tier.
  - `rival.max_utilidad` y `rival.max_setup` por tier.
  - `rival.precision_min` = 70; `rival.recoil_max` = 33.
  - `rival.roles` = plantillas y pesos.
- `config/sendas.php`:
  - `niveles.visibles` = 3; `niveles.maximo` = 5.
  - `umbrales` = 0/9.000/18.000/36.000/72.000.
  - `costes` = 2/5/10/25/60.
  - `beneficios` = N4 0,10; N5 0,20.
  - `captura` = cap base 0,25; cap Senda 0,30; factores 1,0/0,5/0,3.
- La capa de aplicación lee la config y construye `PerfilMovesetRival` y
  `ReglasSenda`; el dominio recibe VOs y no llama a `config()`. Las clases de
  dominio tienen constantes por defecto para tests. **Puedes ajustar estos
  valores después sin tocar la lógica.**

**Determinismo:**

```text
contexto = "ruta:{habitatId}" | "entrenador:{habitatId}:{index}"
         | "gimnasio:{slug}:{etapa}" | "lider:{slug}:{etapa}"
semilla  = crc32("{contexto}|{nivel}|{fecha}|{slot}")
```

- `fecha` = `Y-m-d`; el moveset es estable durante el día.
- `Random\Randomizer(new Mt19937($semilla))` solo para desempatar.
- Misma semilla + mismos candidatos → mismos 6 slugs.

**Gimnasio:** set manual primero; el selector solo rellena los que no tienen set.
El líder sin set manual usa rol `Lider`, cap +10 y plantilla 3 STAB + 2 cobertura
+ 1 utilidad.

**Jugador 6 / rival 6:** ambos usan 6 movimientos. La UI lo indica; no se migran
loadouts.

**Fallback:** si el pool no llega a 6, se usan todos los disponibles; si no llega
a 4, se añaden los movimientos sintéticos de `GeneradorMovimientosTipo`, se
depura y, si aún faltan, se relajan precisión/utilidad/MT en ese orden. Nunca se
duplica un movimiento.

### 4.6 Artefacto Showdown

- Comando: `php artisan showdown:build-movesets` y `--check`.
- Salida: `resources/data/showdown_movesets.json`.
- Entrada: `showdown/data/random-battles/gen9/sets.json` (509 especies, 348
  movimientos únicos), `bss-factory-sets.json` (114 especies con sets cerrados),
  `factory-sets.json` (6 tiers), `showdown/data/moves.ts` (953 movimientos, 902
  con `num`).
- Mapeo por `num` (no por nombre localizado): `moves.ts` nombre → `num`;
  `storage/data/moves.json` `num` → slug.
- Cobertura esperada: **300/348 movimientos (86,2 %)** y **435/509 especies
  (85,5 %)**. Las no mapeadas son Gen 9/DLC o formas regionales.
- Esquema: `version`, `generated_at`, `source` (repo, commit, licencia MIT),
  `coverage`, `moves`, `species` (roles, movepool, sets, moves_unmapped).
- Idempotencia: JSON canónico, escritura atómica, `--check` compara SHA-256.
- Sin Node en runtime; `showdown/LICENSE` se conserva; `THIRD_PARTY_NOTICES.md`
  con atribución MIT.
- Si la especie no tiene entrada o todos sus movimientos son no mapeados, se usa
  el rol por stats y el fallback de Senda + learnset.

## 5. Fases, archivos y responsables

### Fase 1 — Estabilización contable (backend)

**Objetivo:** dejar de crear EXP y de premiar dos veces; corregir la cadencia de
exploración y la persistencia.

| Tarea | Archivos principales |
|---|---|
| Invariante de reparto y `activaEquipo()` | `src/Exploraciones/Domain/EconomiaRecompensas.php`, `CalculadorRecompensas.php` |
| Separar EXP/caramelos en mazmorra | `src/Mazmorras/App/OtorgarRecompensasMazmorra.php`, `CalculadorRecompensas.php` |
| Hallazgo mínimo 1 y filtrado de ceros | `src/Exploraciones/Domain/CalculadorRecompensas.php`, `src/Exploraciones/App/PersistirRecompensas.php` |
| Emboscada evitada sin recompensa de combate | `src/Exploraciones/App/FinalizarExploracionHandler.php` |
| Derrota `intdiv(cantidad, 2)` | `src/Exploraciones/App/FinalizarExploracionHandler.php` |
| Tope EV único 255 | `src/Shared/Domain/ReglasEv.php` (nuevo), `src/Exploraciones/App/AplicarEvsFamilia.php`, `app/Http/Controllers/ReclutadoController.php` |
| Cadencia de eventos por tiempo | `src/Exploraciones/Domain/CadenciaEventos.php` (nuevo), `src/Exploraciones/App/ProcesarExploracionHandler.php`, `ReglasExploracion.php` |
| Captura unificada | `src/Shared/Domain/ProbabilidadCaptura.php`, `src/Exploraciones/App/FinalizarExploracionHandler.php` |
| Tests de invariante y valores canónicos | `tests/Unit/Exploraciones/`, `tests/Feature/` |

**Dependencias:** ninguna. **Cierre:** tests verdes y revisión de `reviewer`.

### Fase 2A — Política IV/EV y dificultad (backend)

**Objetivo:** implementar los perfiles y aplicarlos a ruta, entrenador y
gimnasio; recalibrar los multiplicadores de recompensa.

| Tarea | Archivos principales |
|---|---|
| `PerfilIvsEvs` y `PoliticaIvsEvs` | `src/Shared/Domain/PerfilIvsEvs.php`, `PoliticaIvsEvs.php` (nuevos) |
| Generalizar el distribuidor a top-N | `src/Shared/Domain/DistribuidorStatsValor.php` |
| Ruta: IV/EV por nivel | `src/CombateRuta/App/GeneradorEquipoRuta.php`, `IniciarCombateRuta.php` |
| Entrenador: IV/EV por nivel | `src/CombateEntrenadores/App/GeneradorEquipoEntrenador.php`, `IniciarCombateEntrenador.php` |
| Gimnasio: IV/EV por índice y líder | `src/Gimnasios/App/GeneradorPokemonGimnasio.php`, `IniciarCombateGimnasio.php`, `src/Gimnasios/Domain/CatalogoGimnasios.php` |
| Multiplicadores por perfil | `src/Shared/Domain/MultiplicadoresDificultad.php` (nuevo), `src/Exploraciones/Domain/DificultadHabitat.php` |
| Integrar en el calculador | `src/Exploraciones/Domain/CalculadorRecompensas.php`, `src/CombateRuta/App/OtorgarRecompensasRuta.php`, `src/CombateEntrenadores/App/OtorgarRecompensasEntrenador.php` |
| Shiny: perfil en batalla, IV 15 al capturar | `GeneradorEquipoRuta.php`, `OtorgarRecompensasRuta.php` |
| Deprecar constantes antiguas | `src/Shared/Domain/IvsRangoEntrenador.php`, `src/Gimnasios/Domain/EvsRangoEntrenador.php` |
| Tests de perfiles y stats | `tests/Unit/Shared/`, `tests/Unit/Battle/` |

**Dependencias:** fase 1. **Cierre:** tests de vectores exactos y simulación de
tiempos/ratios.

### Fase 2B — Estructura de Senda y maestrías (backend)

**Objetivo:** niveles visibles 1-3, pasivos 4-5, coste por tramo, umbrales y
aplicación de beneficios.

| Tarea | Archivos principales |
|---|---|
| `NivelSenda`, `BeneficiosSenda`, `MapaSendaJugador`, `PoliticaSenda`, `CurvaSenda` | `src/Shared/Domain/Sendas/` (nuevos) |
| `SendaProgreso` y `SendaCostes` como adaptadores | `app/Support/SendaProgreso.php`, `SendaCostes.php` |
| Clamp visible en árbol y controlador | `app/Support/ArbolSendas.php`, `app/Http/Controllers/SendasController.php` |
| Carga del mapa del jugador | `src/Shared/App/CargarMapaSendaJugador.php` (nuevo) o `app/Support/` |
| `senda` en `DatosPokemonBatalla`, `EquipoBatalla`, `Combatiente` | `src/Battle/Domain/` |
| `ManejadorSendaOfensiva` y `ManejadorSendaDefensiva` | `src/Battle/Domain/Chain/` (nuevos) |
| Captura con Senda | `src/Shared/Domain/ProbabilidadCaptura.php`, `OtorgarRecompensasRuta.php`, `FinalizarExploracionHandler.php` |
| Caramelos de tipo con Senda | `src/Exploraciones/Domain/CalculadorRecompensas.php` |
| Tests de VOs, cadena, captura y caramelos | `tests/Unit/Shared/`, `tests/Unit/Battle/`, `tests/Unit/Exploraciones/` |

**Dependencias:** fase 2A. **Cierre:** reparto 187/114/338; total 9.584;
vectores de beneficios y captura; sesión compatible.

### Fase 2C — Selector de movesets y Showdown (backend)

**Objetivo:** 6 movimientos deterministas para rivales, con STAB + cobertura +
utilidad y artefacto Showdown.

| Tarea | Archivos principales |
|---|---|
| `CatalogoMovimientosSenda` (puerto + Eloquent) | `src/Battle/Domain/Repositories/`, `app/Infrastructure/Battle/` |
| `FiltroMovimientosRival`, `PuntuadorMovimiento`, `PerfilMovesetRival` | `src/Battle/Domain/Selector/` (nuevos) |
| `SelectorMovimientosRival` | `src/Battle/Domain/Selector/SelectorMovimientosRival.php` |
| Carga de desbloqueos del jugador | `app/Support/CargarMovimientosDesbloqueados.php` (nuevo) |
| Configuración ajustable | `config/sendas.php`, `config/combate.php` (nuevos) |
| Integración ruta/entrenador/gimnasio | `GeneradorEquipoRuta.php`, `GeneradorEquipoEntrenador.php`, `GeneradorPokemonGimnasio.php` |
| Comando Showdown | `app/Console/Commands/BuildShowdownMovesets.php` |
| Artefacto | `resources/data/showdown_movesets.json` |
| Attribución | `THIRD_PARTY_NOTICES.md`, `showdown/LICENSE` |
| Golden files y tests | `tests/Fixtures/SelectorMovimientos/`, `tests/Unit/Battle/`, `tests/Feature/` |

**Dependencias:** fases 2A y 2B. **Cierre:** 6 movimientos exactos; caps;
determinismo; sets manuales intactos; cobertura 300/348 y 435/509; valores
ajustables desde config.

### Fase 2D — Recalibración económica (backend)

**Objetivo:** aplicar precios y topes con la nueva dificultad y medir los hitos.

| Tarea | Archivos principales |
|---|---|
| Costes y umbrales de Senda | `app/Support/SendaCostes.php`, `SendaProgreso.php` |
| Costes de nido, refugio y complemento | `src/Granjas/Domain/CalculadoraBonosGranja.php`, `src/Granjas/App/MejorarComplemento.php`, `MejorarNido.php`, `MejorarRefugio.php` |
| Delta EV 51 y tope 255 | `src/Granjas/Domain/CalculadoraBonosGranja.php`, `src/Exploraciones/App/AplicarEvsFamilia.php` |
| Divisor de tipo 250 | `src/Exploraciones/Domain/CalculadorRecompensas.php` |
| Captura 25/14,7/6,4 base y 30/17,6/7,7 con Senda 5 | `src/Shared/Domain/ProbabilidadCaptura.php` |
| Simulación económica determinista | `tests/Unit/Economia/`, `tests/Feature/` |

**Dependencias:** fases 2B y 2C. **Cierre:** hitos base dentro de ±25 %;
sensibilidad con Senda 5 documentada.

### Fase 3 — Preview y UI (backend + frontend)

**Objetivo:** que el jugador vea el coste real, el tier, el moveset y los
beneficios de Senda.

| Tarea | Archivos principales |
|---|---|
| Página de Senda: ataques 1-3, maestrías 4-5, coste real, beneficios | `resources/views/sendas/index.blade.php`, `show.blade.php`, `app/Support/ArbolSendas.php` |
| Selector de movimientos: nivel/coste/desbloqueo/beneficio | `resources/views/equipos/`, `app/Support/ReclutadoSerializer.php` |
| Preview de combate: tier IV/EV, moveset, aviso de no desbloqueados | `resources/views/livewire/combate.blade.php`, `PresentadorResultadoBatalla.php` |
| Coste por hábitat y saldo | `src/Granjas/App/ObtenerGranja.php`, `resources/views/habitats/show.blade.php` |
| Aviso EV parcial | `app/Http/Controllers/ReclutadoController.php`, vistas de reclutado |
| Accesibilidad y agrupación de 338 nodos | vistas de Sendas, componentes Blade |
| Tests de igualdad preview/ejecución | `tests/Unit/Exploraciones/`, `tests/Feature/` |

**Dependencias:** fases 2B, 2C y 2D. **Cierre:** preview y ejecución coinciden;
ningún coste oculto; la página de Senda no degrada con 338 nodos.

### Fase 4 — Documentación (documenter)

- ADR-004: economía única, IV/EV por tier, Sendas maestras y movesets rivales.
- Actualizar `docs/architecture.md`, `docs/conventions.md` y los `context.md` de
  Exploraciones, Granjas, CombateRuta, Gimnasios y Battle.
- Documentar la interpretación D30 (unión de permitidos + desbloqueados), la
  simetría 6/6 y la ausencia de pasivos en mazmorra.
- Atribución MIT de Showdown en `THIRD_PARTY_NOTICES.md`.

**Dependencias:** fases 1-3.

### Fase 5 — Validación independiente

- `reviewer`: revisión del diff, capas, duplicidades, contratos y rendimiento.
- `qa`: tests de aceptación, regresión, simulación económica y golden files.
- `security`: revisión de endpoints de inventario, recompensas, descarte y
  multiplicadores de Senda (sin entrada de usuario).
- `documenter`: informe final y ADR.

**Dependencias:** fases 1-4.

## 6. Orden de ejecución

```text
Fase 1 (estabilización)
  └─ Fase 2A (IV/EV + dificultad)
       └─ Fase 2B (Senda: 1-3 ataques, 4-5 pasivos)
            └─ Fase 2C (selector de 6 + Showdown + config)
                 └─ Fase 2D (recalibración económica)
                      └─ Fase 3 (UI/preview)
                           └─ Fase 4 (documentación)
                                └─ Fase 5 (review/QA/security)
```

- No se implementa ninguna fase sin `approval.md` en estado `APROBADO`.
- Los archivos compartidos se tocan en secuencia; no se lanzan dos
  implementadores sobre el mismo archivo.
- El WIP actual no se revierte ni se sobrescribe: se integra encima.

## 7. Criterios de aceptación

### Sendas

1. `nivelVisible(required_level 1..5)` = **1/2/3/3/3**.
2. Reparto visible: **187 / 114 / 338** en la BD actual (639 nodos);
   **186 / 114 / 332** en seeder limpio (632 nodos). Por categoría en la BD
   actual: N1 63/34/90, N2 46/29/39, N3 163/136/39. La diferencia son 7
   movimientos de formas no disponibles (`snap-trap` y 6 de tramo 4).
3. Coste por tramo: **2/5/10/25/60**; total **9.584** en la BD actual y
   **9.432** en seeder limpio. Nodo de tramo 4 visible en 3 → coste 25;
   tramo 5 → 60.
4. Umbrales **0/9.000/18.000/36.000/72.000**; `EXP_MAXIMA` 72.000; N5 = 720
   caramelos.
5. Los 8 estados de tramo 4-5 se conservan en N3 con coste 25/60 y se etiquetan
   “Maestría de utilidad”.

### Beneficios de Senda

6. N1-3 = 0; N4 = 0,10; N5 = 0,20; **no** 1,21.
7. Captura con `capture_rate=166`, hábitat 1: **25,0 / 27,5 / 30,0 %**;
   hábitat 2 (`74,8`): **14,65 / 16,12 / 17,58 %**; hábitat 3 (`54,7`):
   **6,44 / 7,08 / 7,73 %**. Ningún caso >30 %.
8. Daño: base 100, STAB 1,5, Senda 5 ofensiva 1,2, defensiva 0,8 → **144**;
   sin defensa → 180; sin Senda → 150. Daño fijo no cambia.
9. Caramelos: `expTipo=2.500`, `M=1`, `m_tipo=1` → **10 / 11 / 12**;
   `expTipo=1.000`, `m_tipo=1,9`, Senda 5 → **9** (un solo floor).
10. Dual: captura `max` de las dos Sendas; caramelos por tipo; daño/defensa por
    tipo del movimiento.
11. Rivales: mapa vacío; no heredan captura, daño, defensa ni caramelos.
12. Mazmorra: sin Senda; daño, captura y caramelos idénticos a antes.

### Selector de movesets

13. **Hasta 6** movimientos por rival; exactamente 6 si el pool lo permite; sin
    duplicados.
14. Pool = **nivel disponible ∪ (aprendible por MT/tutor/huevo/nivel superior ∩
    desbloqueado por el jugador)**; no depende del nivel de Senda, sí de
    `PlayerMoveUnlock`. Un movimiento desbloqueado que la especie no aprende no
    entra.
15. STAB + cobertura + utilidad según rol; caps de §4.5.
16. Determinismo: misma semilla + mismos candidatos → mismos 6 slugs; semilla
    distinta solo cambia empates.
17. Gimnasio con set manual → set manual intacto; sin set → selector; líder sin
    set → rol `Lider`, cap +10.
18. Fallback: si el pool no llega a 6, se usan todos; si no llega a 4, se añaden
    sintéticos; nunca 3 en producción.
19. Jugador conserva 6; 7 se rechaza. Rival también 6.
20. Los valores de tamaño, caps, umbrales, costes y pasivos se leen de
    `config/sendas.php` y `config/combate.php`; cambiarlos no exige tocar la
    lógica.

### Showdown

21. Artefacto `resources/data/showdown_movesets.json` válido; `coverage`
    `moves_mapped=300/348`, `species_mapped=435/509`.
22. Dos builds idénticos (SHA-256); `--check` falla si el artefacto está
    obsoleto.
23. Sin Node en runtime; `showdown/LICENSE` y `THIRD_PARTY_NOTICES.md`
    presentes.

### Economía

24. Hitos base (sin pasivos): Senda N5 **9,9 h ±25 %**; 18 Sendas **306 h
    ±25 %**; granja **36,6 h ±25 %**; evolución N20 **1,75 h ±25 %**; tipo/h
    nivel 2 **72,6 ±3**.
25. Sensibilidad con Senda 5: Senda N5 ~9,0 h; 18 Sendas ~292 h; granja ~33 h;
    evolución ~1,33 h (±15 % de las estimaciones).
26. Captura/h: nivel 1 ≥2× nivel 2 y ≥2× nivel 3; con Senda 5, niveles
    9,9/2,27/0,78 en pool puro.
27. Ningún límite nuevo; la renovación diaria de entrenadores se mantiene.

### UI y arquitectura

28. Ningún endpoint devuelve un coste sin mostrar el tramo, el acumulado del
    nivel y el coste real antes de pagar.
29. La página de Senda agrupa/filtra los 338 nodos de nivel 3 y no hace una
    consulta por nodo.
30. Preview y ejecución coinciden en ±1 caramelo por clase con pool y semilla
    fijos.
31. `src/Shared/Domain/Sendas` y `src/Battle/Domain/Selector` sin `App` ni
    `Illuminate`; sesión antigua sin `senda` se hidrata con mapa vacío.

## 8. Pruebas requeridas

> **Decisión del desarrollador (2026-11-02):** se desactiva la **generación y
> mantenimiento de tests** por coste de tokens. Se conservan los tests ya
> existentes y no se crearán ni corregirán nuevos. La validación se apoyará en
> revisión estática, smoke tests puntuales y comprobaciones manuales. Los
> criterios de aceptación de este plan se mantienen como referencia funcional,
> pero no se exige cobertura de test nueva.

- **Unitarias de dominio:** `NivelSenda`, `BeneficiosSenda`, `MapaSendaJugador`,
  `PoliticaSenda`, `CurvaSenda`, `PoliticaCosteSenda` (o `SendaCostes`),
  `PuntuadorMovimiento`, `SelectorMovimientosRival`, `FiltroMovimientosRival`,
  `EconomiaRecompensas`, `CalculadorRecompensas`, `CadenciaEventos`,
  `CalculadoraBonosGranja`, `ReglasEv`, `PerfilIvsEvs`, `PoliticaIvsEvs`,
  `DistribuidorStatsValor` (N=0..6 y empates), `BattleStats` con vectores fijos.
- **Integración:** `CatalogoMovimientosSenda` (`nivel disponible ∪ (aprendible ∩
  unlocks)`), `ShowdownArtefacto` (esquema, cobertura, idempotencia),
  `CalculadorRecompensasSenda` (un solo floor), `ProbabilidadCapturaSenda`
  (refugio × Senda una vez, cap 30), `CombatienteSerializacion` (payload antiguo
  y nuevo), `ConfiguracionSenda`/`ConfiguracionCombate` (valores por defecto y
  override).
- **Pool del rival:** un rival que aprende Lanzallamas por MT y lo tiene
  desbloqueado el jugador → disponible; un rival que no lo aprende → no
  disponible; un rival que lo aprende por nivel a nivel superior y el jugador lo
  tiene desbloqueado → disponible; un rival con el movimiento por nivel pero sin
  desbloqueo del jugador → solo si su nivel alcanza el requisito.
- **Feature:** ruta L1/L2/L3 con Senda 1/4/5; exploración con refugio + Senda;
  entrenador con selector; gimnasio con set manual y sin set; líder; mazmorra
  sin Senda; shiny; loadout 6/7.
- **Simulación económica determinista:** escenario fijo (10 combates de ruta +
  9 entrenadores + 8 h de exploración) con semilla inyectada; compara hitos base
  y sensibilidad con Senda 5.
- **Golden files:** `tests/Fixtures/SelectorMovimientos/ruta-1.json`,
  `ruta-2.json`, `ruta-3.json`, `entrenador-3.json`, `gimnasio-lider.json`; 3-4
  especies representativas por tier, 6 slugs exactos, semilla y fecha fijas.
- **Config:** cambiar `rival.slots` a 4 (como ejemplo de ajuste) debe producir 4
  movimientos sin tocar código; cambiar `beneficios[5]` a 0,15 debe reflejarse
  en captura, daño y caramelos.
- **Regresión:** tests existentes de entrenador/gimnasio que asumen IV 8/6,
  10/8 y 12/10; sets manuales de gimnasio; shiny; mazmorra; loadout.
- **Arquitectura:** `deptrac`/`phpstan` sin `App`/`Illuminate` en dominio; sin
  dependencias circulares.

## 9. Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| El selector de 6 con cobertura/utilidad hace los combates más duros | Medio | Caps por tier; factor de tiempo 1,00-1,12; simulación; ajustar caps, no la estructura. |
| El pool del rival crece con los desbloqueos del jugador y puede volverse demasiado fuerte | Medio | La intersección con `aprendible` limita el pool; caps por tier; scoring; límite de utilidad/setup; configurar `rival.slots` y caps sin tocar código. |
| Los pasivos de Senda aceleran la economía | Medio | Objetivos base sin pasivos; sensibilidad con Senda 5; si se desvía >15 %, ajustar umbrales o bono. |
| Cap de captura 30 % en N5 | Medio | Solo Pokémon del tipo; en pool mixto el efecto es menor; monitorizar. |
| 338 nodos en nivel 3 degradan la UI | Medio | Agrupar/filtrar/paginar; no consultas por nodo; lazy images. |
| La serialización de sesión se rompe al añadir `senda` | Alto | Campo opcional con default vacío; test de payload antiguo; no cambiar claves existentes. |
| El `floor` de caramelos oculta el +10 % | Medio | Tests sobre `caramelosTipoBrutos()` y vectores que cambian; no confiar solo en el resultado final. |
| El pool del rival se interpreta como “cualquier Pokémon con cualquier movimiento” | Alto | Intersección con `aprendible`; test de especie sin el movimiento y de movimiento desbloqueado por el jugador que la especie sí aprende. |
| Los sets manuales de gimnasio se pierden | Alto | Prioridad manual; test de regresión. |
| El artefacto Showdown queda obsoleto | Bajo | `--check`, versión, hash, test de cobertura. |
| La simetría 6/6 puede hacer al rival demasiado variado | Bajo | Caps y roles; configurar slots/caps; golden files. |
| El WIP local es extenso | Alto | Implementar sobre el árbol actual; no revertir; revisar `git diff` por fase; un responsable por archivo. |
| El nerf de la economía legada (−44 % EXP) se percibe como pérdida | Alto | Aplicar en fase 1 con hallazgos ≥1; comunicar en changelog; conservar inventario/progreso. |
| T1 (top-1 255 EV) hace gimnasios 1-6 “cañón de cristal” | Medio | Es la spec; monitorizar; si es excesivo, bajar a 128 EV en revisión posterior. |
| T2/T3 alargan los combates y aumentan derrotas | Alto | Modelo de tiempo declarado; simulación; vigilar tasa de derrota; castigo por derrota `floor`. |
| EV >255 por complementos antiguos | Medio | Legado intocable; bloquear nuevas ganancias; aviso en UI. |
| Jugadores que pagaron el refugio a 2.700 | Medio | Documentar; compensación opcional posterior. |

## 10. Rollback

- **Código:** revertir los commits de la fase correspondiente. Las constantes
  nuevas están centralizadas en `SendaCostes`, `SendaProgreso`,
  `CalculadoraBonosGranja`, `PoliticaIvsEvs`, `PoliticaSenda` y
  `PerfilMovesetRival`.
- **Base de datos:** no hay migraciones nuevas en v5. La compresión de Senda es
  de lectura; el artefacto Showdown es un fichero regenerable. No hay cambios
  destructivos ni migraciones de datos.
- **Compatibilidad:** inventarios, reclutados, progreso y batallas en curso se
  conservan; la sesión antigua sin `senda` se hidrata con mapa vacío; los EV
  >255 quedan como legado.
- **Si una fase falla:** no se avanza a la siguiente; se corrige dentro del
  alcance aprobado y se repiten review/QA.

## 11. Aprobación

### 11.1 Configuración de aprobación

| Clave | Valor | Efecto |
|---|---|---|
| `aprobacion.mostrar_plan_en_pantalla` | **true** | El plan completo se muestra en pantalla (chat) antes de lanzar la pregunta de aprobación. |
| `aprobacion.formato` | resumen estructurado + tablas + criterios | Debe incluir objetivo, decisiones, alcance, fases, criterios y riesgos. |
| `aprobacion.requiere_version_literal` | **true** | La respuesta debe incluir la versión (`v5`); una respuesta ambigua no aprueba. |
| `aprobacion.registrar_texto_literal` | **true** | El texto exacto se guarda en `approval.md`. |
| `aprobacion.sin_respuesta` | **no aprobado** | La ausencia de respuesta nunca equivale a aprobación. |

> **Requisito añadido por el desarrollador:** antes de pedir aprobación, el
> orchestrator debe mostrar el plan por pantalla. La pregunta de aprobación solo
> se lanza después de esa presentación.

### 11.2 Fórmulas válidas

Este plan requiere una respuesta explícita del desarrollador que incluya la
versión. Fórmulas válidas:

- `APROBADO plan v5`
- `Implementa el plan v5`
- `Apruebo la versión 5 del plan`

Si prefieres cambiar alguna decisión (por ejemplo, el pool del rival, el cap de
captura 30 %, los pasivos +10/+20, la simetría 6/6 o los topes de potencia), se
genera `v6` antes de implementar.
