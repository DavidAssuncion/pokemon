# TASK-056 — Arquitectura

- **Autor:** `architect` (subagente independiente), por delegación del orchestrator.
- **Fecha:** 2026-11-17.
- **Alcance:** solo diseño; sin cambios de código.
- **Nota de coordinación:** se reproduce el informe del arquitecto; no se alteran sus conclusiones.

---

# TASK-056 — Informe final de arquitectura
## Piezas fabricables en la Forja (Armas / Armaduras / Amuletos) y equipamiento de 3 slots

> **Nota de evidencia:** las referencias `archivo:línea` proceden de lectura directa del árbol actual. Las marcadas con **(A)** provienen de los informes de `.opencode/work/TASK-056/analysis.md` y no han sido re-verificadas línea a línea. Ninguna decisión de este informe se ha implementado: no se ha modificado ningún archivo.
>
> **Marcas:** `[APROBACIÓN]` = decisión de producto o de arquitectura que requiere aprobación explícita del desarrollador. `[Dn]` = fila de la tabla de decisiones (§10.6).

---

## 1. Resumen ejecutivo y decisiones

### 1.1 Qué se construye

Una sección nueva en `/forja` que ofrece, **solo para las familias evolutivas en las que el jugador tiene un nido construido (nivel ≥ 1)**, un juego de tres piezas por especie —**Arma, Armadura y Amuleto**—, con:

- desbloqueo por **nivel de nido** según la profundidad de la familia (1/3/5 · 2/4 · 3);
- **stats que replican los stats base de la especie** de origen (con bono de Amuleto al stat máximo);
- **una pieza única por etapa** que evoluciona consumiendo la anterior;
- **hasta 3 piezas equipadas por reclutado**, persistentes y aplicadas en los 5 modos de combate;
- **habilidades pasivas** en algunas piezas, acumulables con las de especie.

### 1.2 Las cinco decisiones estructurales

1. **Identidad por etapa, no por instancia.** La pieza es una clave de inventario `tipo:{species_id}`. `player_inventory` es un contador `unique(user_id, item_key)` (`database/migrations/2026_08_29_000007_create_player_inventory_table.php:15-24`) sin identidad por instancia; introducir instancias exigiría sustituir el puerto `InventarioJugador` y migrar caramelo, granja y forja. La "pieza que evoluciona" se modela como **cadena de recetas que consume la clave anterior**, patrón ya existente (`receta:super` consume `pocion:pocion`, `src/Forja/Domain/CatalogoRecetas.php:123-128`). → **§5**

2. **Catálogo de piezas propio y generativo, no `CatalogoRecetas` estático.** `CatalogoRecetas` construye 46 recetas en memoria y su `crear()` deriva la categoría de `CatalogoItems::definicion()` (`src/Forja/Domain/CatalogoRecetas.php:215-229`), que es Domain puro sin BD. Las piezas dependen de datos de especie (stats, familia, etapa) y del nido del jugador: no caben en ese catálogo ni en `CatalogoItems`. Se añade un pipeline paralelo en `src/Forja/Domain/Piezas/` y se deja el catálogo existente **intacto**. → **§2, §6**

3. **Bono plano sobre los stats base (`StatsValue`) antes de `BattleStats`.** Es la única naturaleza coherente con "un set que replica sus stats": escala con el nivel como los stats de la especie, entra en barreras (`BattleStats::defenseHp`/`spDefenseHp`, `src/Pokemon/Domain/Stats/BattleStats.php:63-70`) y en la fórmula de daño, y entra en CP. → **§4**

4. **El equipamiento se colapsa en el mapeo, no en el motor.** `MapeadorPokemonBatalla::desdePokemon()` (`src/CombateEntrenadores/App/MapeadorPokemonBatalla.php:50-105`) suma los bonos al `StatsValue` base y añade las pasivas a `effectKeys`. `DatosPokemonBatalla` **no cambia de forma** (`src/Battle/Domain/DatosPokemonBatalla.php:26-73`), así que **no se tocan** `ObjetoCombatiente`, `FabricaEfectos::crearItem`, `ManejadorObjetosEquipados` ni `HabilidadCombatiente`, y **`SESSION_VERSION` no se incrementa** (sigue en 21, `app/Support/BattleSessionService.php:50`), porque el `PokemonEntity` ya se serializa con sus stats finales (`SerializadorCombatiente::serializarPokemon` usa `serialize($pokemon)`, `.../SerializadorCombatiente.php:104-107`). → **§8**

5. **Los 3 slots van a tabla nueva; `obj_equipados` no se toca.** `obj_equipados` es leído en 62 puntos de `tests/` y en 3 sitios de producción siempre por `[0]` (`ConstruirEquipoJugador.php:86-88`, `CombateExploracion.php:53-55`, `ActualizarObjetosEquipo.php:31-45`). Una tabla `recruit_gear` con `unique(reclutado_id, slot)` + `unique(reclutado_id, item_key)` es **puramente aditiva**, da integridad real y expresa la regla "3 del mismo tipo y de diferente pokemon" sin lógica. → **§5**

### 1.3 Encaje en la arquitectura del proyecto

| Capa | Qué aporta TASK-056 | Regla respetada |
|---|---|---|
| `Shared/Domain/Items` | `CategoriaItem` (+3 casos), `ClavePieza` (VO de clave) | Lenguaje de claves de inventario único |
| `Shared/Infra/Inventario` | carve-out de whitelist para las 3 categorías | Sin dependencia Shared → Forja |
| `Forja/Domain/Piezas` | catálogo y reglas puras (etapas, stats, gates) | Domain sin Eloquent/HTTP/facades |
| `Forja/Domain/Ports` | `NidosConstruidos` (puerto de lectura de granjas) | Mismo patrón que `ConsultorPlantaciones` |
| `Granjas/Infra` | adaptador `NidosConstruidosEloquent` | Infra → Domain cruzado, precedente aceptado |
| `Forja/App` | `ListarPiezasForja`, `FabricarPieza` | Casos de uso, transacción y locks |
| `Equipos/App/Piezas` | `EquiparPieza`, `DesequiparPieza`, `ObtenerPiezas` | Reutiliza el módulo dueño de `obj_equipados` |
| `Pokemon/Domain/Stats` | `AplicadorBonusPiezas` (puro) | Sin depender de Forja |
| `CombateEntrenadores/App`, `Exploraciones/App` | 2 puntos de inyección del equipamiento | Embudo único de los 5 modos |

---

## 2. Modelo de dominio — `src/Forja/Domain/Piezas/**`

### 2.1 Árbol de archivos

```
src/Forja/Domain/Piezas/
├── Enums/
│   └── TipoPieza.php
├── DataTransferObjects/
│   ├── DefinicionPieza.php
│   ├── PiezaDesbloqueada.php
│   └── RecetaPieza.php
├── Collections/
│   ├── DefinicionesPiezaCollection.php
│   └── RecetasPiezaCollection.php
├── ReglasPiezas.php
├── CalculadoraStatsPieza.php
└── ResolvedorEtapasPiezas.php

src/Forja/Domain/Ports/
└── NidosConstruidos.php

src/Shared/Domain/Items/
├── CategoriaItem.php            (modificado: +3 casos)
└── ClavePieza.php               (nuevo VO)
```

### 2.2 `TipoPieza` (enum string)

| Caso | Valor | `nombre()` | `categoria()` | Grupo de stats |
|---|---|---|---|---|
| `ARMA` | `arma` | Arma | `CategoriaItem::ARMA` | Ofensivo: `attack`, `spAtk` |
| `ARMADURA` | `armadura` | Armadura | `CategoriaItem::ARMADURA` | Defensivo: `defense`, `spDef`, `hp` |
| `AMULETO` | `amuleto` | Amuleto | `CategoriaItem::AMULETO` | Velocidad + 50 % del máximo |

Métodos: `clave(int $speciesId): string` → `"{$this->value}:{$speciesId}"`; `prefijo(): string` (delega en `CategoriaItem::prefijo()`); `slugIcono(): string`.
Responsabilidad: **vocabulario cerrado**. Es la única fuente del prefijo de inventario, del orden de presentación y del mapeo a `CategoriaItem`.

### 2.3 `ClavePieza` (VO readonly, `src/Shared/Domain/Items/ClavePieza.php`)

Vive en **Shared** porque la consumen tres módulos y Shared no depende de ninguno:

| Consumidor | Uso |
|---|---|
| `Forja` | generar `resultado_clave` de las recetas y validarlas |
| `Equipos` | validar la clave recibida por `PUT /api/reclutados/{id}/piezas/{slot}` |
| `Shared/Infra/Inventario` | validar estructura de claves en `asegurarClaveValida()` |

Contrato:
```php
final readonly class ClavePieza
{
    public function __construct(public TipoPieza $tipo, public int $speciesId) {}
    public static function desde(string $clave): self;   // lanza ItemDesconocido si no encaja
    public static function esClaveValida(string $clave): bool;
    public function clave(): string;                     // "arma:94"
}
```
Formato: `^(arma|armadura|amuleto):[1-9][0-9]*$`. Se usa **`species_id`** y no slug porque la tabla `pokemon` **no tiene columna `slug`** (`database/migrations/2026_02_24_171456_create_pokemon_table.php:14-27`) y porque `species_id` ya es la clave de negocio del proyecto ("primer integrante = menor `species_id`", `src/Habitats/Presentation/DTOFamiliaDisponible.php:32-38`). Coherente además con `familia:{id}` y `ev:{id}` del inventario (`InventarioJugadorEloquent.php:33`).

### 2.4 `DefinicionPieza` (DTO readonly)

| Campo | Tipo | Origen | Nota |
|---|---|---|---|
| `clave` | `string` | `TipoPieza::clave(speciesId)` | id de inventario |
| `tipo` | `TipoPieza` | — | |
| `speciesId` | `int` | `pokemon.species_id` | |
| `speciesNombre` | `string` | `pokemon.name` | nombre visible de la especie |
| `evolutionChainId` | `int` | `pokemon.evolution_chain_id` | familia (`nests.evolution_chain_id`) |
| `etapa` | `int` | `ResolvedorEtapasPiezas` | profundidad BFS (raíz = 1) |
| `profundidadFamilia` | `int` | `ResolvedorEtapasPiezas` | `max(etapa)` de la familia |
| `nivelNidoRequerido` | `int` | `ReglasPiezas::nivelNidoPara()` | 1..5 |
| `bonos` | `StatsValue` | `CalculadoraStatsPieza` | solo los 2-3 stats del tipo |
| `habilidadPasiva` | `?string` | `abilities` slot 1 | clave de efecto de `CatalogoHabilidades` |
| `icono` | `string` | especie | `/images/iconos_webp/{id}.webp` |
| `descripcion` | `string` | generada | texto ya resuelto (el frontend no compone) |

`toArray()` es el contrato de frontera; no se expone `DefinicionPieza` fuera de Forja.

### 2.5 `ReglasPiezas` (constantes y reglas puras)

| Constante / método | Valor / firma | Justificación |
|---|---|---|
| `MAX_NIDO` | `5` | techo de `nests.level` (mismo valor que `CalculadoraBonosGranja::MAX_NIDO`, `src/Granjas/Domain/CalculadoraBonosGranja.php:21`) |
| `SLOTS` | `3` | petición del cliente |
| `BONO_AMULETO` | `0.5` | decisión cerrada del cliente (request.md §3) |
| `FACTOR_ETAPA` | `[1 => 0.5, 2 => 0.75, 3 => 1.0]` | `[D2]` |
| `FACTOR_ETAPA_MAX` | `1.0` (etapa ≥ 3) | tope de etapa |
| `nivelNidoPara(int $profundidad, int $etapa): int` | ver §3 | regla 1/3/5 · 2/4 · 3 |
| `factorEtapa(int $etapa): float` | `FACTOR_ETAPA[min(3, etapa)]` | |
| `prioridadStatMaximo()` | `['atk','def','spAtk','spDef']` | desempate determinista del Amuleto |

Todas las reglas viven aquí y **ninguna en Blade ni en `app/`** (regla `frontend-minimal-logic`).

### 2.6 `CalculadoraStatsPieza` (servicio puro)

```php
final class CalculadoraStatsPieza
{
    public function calcular(StatsValue $base, TipoPieza $tipo, int $etapa): StatsValue;
}
```
Sin estado, sin dependencias. Recibe los stats base (los mismos que `EstadisticasBasePokemon::desdePokemon()`, `app/Support/Pc/EstadisticasBasePokemon.php:16-46`) y devuelve un `StatsValue` **con solo los componentes del tipo** (los demás a 0). Detalle de fórmulas en **§4**.

### 2.7 `ResolvedorEtapasPiezas` (servicio puro)

```php
final class ResolvedorEtapasPiezas
{
    /**
     * @param list<PokemonFamilia> $nodos       especies de la familia
     * @param list<AristaEvolutiva> $aristas    aristas de pokemon_evolution del chain
     */
    public function resolver(array $nodos, array $aristas): EtapasFamilia;
}
```
Delega en `ResolvedorFamiliaEvolutiva::resolver($nodos, $aristas, multiRaiz: true, maxEtapas: null)` (`src/Pokemon/Domain/Evolution/ResolvedorFamiliaEvolutiva.php:23-57`) y devuelve, por `speciesId`: `etapa` y la `profundidad` de la familia. **No reutiliza** `ResolvedorCadenasEvolutivas` (§3.2).

### 2.8 `PiezaDesbloqueada` y `RecetaPieza`

- **`PiezaDesbloqueada`** (DTO readonly): `definicion`, `habitat{id,nombre}`, `nidoNivel`, `bonguri{clave,nombre,icono,origen}`, `desbloqueada: bool`, `piezaAnterior: ?ClavePieza`, `ingredientes: MapaConteo`, `puedeFabricar: bool`, `motivo: ?string`. Es la fila que consume `ListarPiezasForja`.
- **`RecetaPieza`** (DTO readonly): `clave` (`receta:pieza:{tipo}:{speciesId}:h{habitatId}`), `resultado` (`ClavePieza`), `habitatId`, `farmId`, `nivelRequerido`, `piezaAnterior: ?ClavePieza`, `ingredientes: MapaConteo`, `etiqueta`. Es el equivalente generativo de `RecetaFabricacion`, pero **no** depende de `CatalogoItems` para validar el resultado.

### 2.9 Qué NO se crea (deliberadamente)

- **No** hay entidad de instancia (`PiezaInstancia`) ni repositorio de piezas: la pieza es una clave.
- **No** hay `PiezaRepository`: la lectura de datos de especie es un *read model* en Infra (§6.2).
- **No** se modifica `RecetaFabricacion` ni `CatalogoRecetas`: 46 recetas y su contrato quedan intactos.
- **No** se introduce `src/Equipamiento/`: 3 casos de uso no justifican módulo nuevo; van a `src/Equipos` (§8.1). `[D-nombrado]`

---

## 3. Regla de desbloqueo por nido

### 3.1 Fórmula

Sea `profundidad` = máxima etapa BFS de la familia (`evolution_chain_id`) y `etapa` la profundidad de la especie:

| Profundidad de la familia | Nivel de nido requerido | Ejemplo |
|---|---|---|
| `1` (sin evolución) | **3** para todas | Pinsir → 3 |
| `2` | `2 × etapa` → **2, 4** | Eevee → 2; Vaporeon/Jolteon/… → 4 |
| `≥ 3` | `min(5, 2 × etapa − 1)` → **1, 3, 5**, y ≥ 4ª etapa → **5** | Gastly → 1; Haunter → 3; Gengar → 5 |

La regla se implementa en `ReglasPiezas::nivelNidoPara(int $profundidad, int $etapa): int` con un `match (true)` de tres ramas; sin `if` anidados y sin estado.

### 3.2 Resolvedor elegido y por qué

**Elegido: `Src\Pokemon\Domain\Evolution\ResolvedorFamiliaEvolutiva` con `multiRaiz: true` y `maxEtapas: null`.**

**Descartado: `Src\Habitats\Domain\ResolvedorCadenasEvolutivas`.** Tres motivos, todos verificados:

1. Fuerza `multiRaiz: false` (`src/Habitats/Domain/ResolvedorCadenasEvolutivas.php:83-88`): selecciona **una sola componente conexa**, así que **Eevee pierde las 8 eeveeluciones** y solo resuelve una rama.
2. Fuerza `maxEtapas: 3` (`:87`): una familia de 4 etapas queda truncada y sus miembros profundos no reciben etapa.
3. Aplica **fallback `stage = 3`** a los miembros no alcanzados (`:97`): una especie no resuelta se trataría como etapa final y se desbloquearía con nido 5 — exactamente lo contrario de lo prudente.

Además su `levelForStage()` **no implementa la regla pedida**: devuelve `2` cuando la familia tiene una sola etapa y `min($stage, 3)` en el resto (`:111-118`). No es reutilizable.

`ResolvedorFamiliaEvolutiva` con `multiRaiz: true` recorre **todas** las componentes conexas, calcula profundidad BFS real desde la raíz (`stage` 1-based), asigna `branchIndex` estable para hermanos y ordena por `(stage, parentId, branchIndex, speciesId, id)` (`:23-57` y comparador en `:54`). **Todo nodo recibe etapa**: no hay huérfanos ni fallback.

### 3.3 Casos límite resueltos

| Caso | Comportamiento | Razón |
|---|---|---|
| **Ramificadas (Eevee)**: 1 raíz + N hijas | Eevee etapa 1 → nivel 2; **cada** eeveelución etapa 2 → nivel 4 | `multiRaiz: true` procesa la componente completa; `branchIndex` da orden estable |
| **Familia de 1 miembro** (Pinsir) | etapa 1, profundidad 1 → **nivel 3** | rama `profundidad === 1` |
| **Etapa no alcanzada por falta de aristas** (`pokemon_evolution` incompleto) | la familia degenera a profundidad 1 → **nivel 3** | comportamiento **seguro**: no regala la pieza final. Riesgo de datos documentado en §10.4 |
| **Familia con bebé** (Pichu→Pikachu→Raichu) | profundidad 3 → niveles 1 / 3 / 5 | BFS desde la raíz sin arista entrante |
| **Familia de 4+ etapas** | etapa ≥ 3 → **tope 5** | `min(5, …)`; `[D12]` |
| **Fallback `stage = 3`** del resolvedor antiguo | **deja de usarse** | eliminado por diseño |
| **Nido inexistente o nivel 0** | la familia no aparece en la Forja | filtro `level >= 1` (§6.3) |

### 3.4 Discrepancia a documentar: `etapa` ≠ "primer integrante"

El proyecto tiene dos criterios distintos y conviene no confundirlos:

- **"Primer integrante" = menor `species_id`** (regla de negocio ratificada; `DTOFamiliaDisponible::minSpeciesId()`, `src/Habitats/Presentation/DTOFamiliaDisponible.php:32-38`). Se usa para **nombrar** la familia y para `caramelos_familia[].pokemon_id`.
- **`etapa` = profundidad BFS desde la raíz evolutiva** (este diseño). Se usa para **desbloquear piezas y calcular stats**.

En Pichu→Pikachu→Raichu, el nombre de la familia es el de **Pikachu** (`species_id` 25 < 172) mientras que la **etapa 1** es **Pichu**. Debe quedar escrito en `src/Forja/context.md` y en el ADR para que nadie "arregle" uno con el otro.

### 3.5 Dependencia de dominio aceptada

`ReglasPiezas`/`ResolvedorEtapasPiezas` (`Forja/Domain`) importan `Src\Pokemon\Domain\Evolution\*`. Es una dependencia **Domain → Domain** entre módulos, ya existente en el proyecto (`src/Battle/Domain` depende de `src/Pokemon/Domain`, `docs/conventions.md` §Dependencias). Se registra en la tabla de módulos de `docs/ddd.md`, no se duplica el BFS.

---

## 4. Fórmulas de stats

### 4.1 Regla única

> **Cada pieza replica los stats base de su especie en el grupo de su tipo. El Amuleto añade, además de la Velocidad, la mitad del mayor stat de combate de la especie.**

| Tipo | Componentes | Fórmula (por componente) |
|---|---|---|
| **Arma** | `attack`, `spAtk` | `floor(baseStat × 1.0 × factorEtapa)` |
| **Armadura** | `defense`, `spDef`, `hp` | `floor(baseStat × 1.0 × factorEtapa)` |
| **Amuleto** | `speed` + `X` | `speed = floor(baseSpeed × factorEtapa)`; `X = floor(baseX × 0.5 × factorEtapa)` donde `X = argmax(baseAtk, baseDef, baseSpA, baseSpD)` con desempate `atk → def → spAtk → spDef` |

**Contradicción resuelta** — «Armas requieren ataque/spA/spd» frente a *«Arma gengar 130 atksp + 65 atk»*: se da prioridad al **ejemplo numérico**, porque (a) es un dato cerrado del cliente, (b) el cliente **sí** marcó como errata el `75` del Amuleto, y **no** marcó el Arma, y (c) un Arma con Velocidad más un Amuleto con Velocidad duplicaría el stat más decisivo del motor (la Velocidad ordena los turnos, `GestorTurnos`). La `spd` de la frase se interpreta como la **Velocidad del Amuleto**.
`[APROBACIÓN] [D1]` — alternativa descartada: "Arma = 100 % del ofensivo dominante + 50 % de Velocidad" (explicaría la frase, contradice el ejemplo).

### 4.2 Naturaleza del bono, redondeo y topes

- **Bono plano sobre `StatsValue` base**, aplicado **antes** de construir `BattleStats`. Unidad: puntos de stat base. A nivel `L` el efecto real es `≈ bono × L/100` porque la fórmula multiplica por `nivel/100` (`src/Pokemon/Domain/Stats/BattleStats.php:73-81`).
- **Redondeo:** `floor` en cada componente, tras aplicar `FACTOR_ETAPA`.
- **Topes:** ninguno duro en F1. El único mando de balance es `FACTOR_ETAPA`.
- **No** se usa `multiplicador` sobre el stat calculado (a nivel 50 duplicaría el stat) ni modificadores en `StatsCombatiente` (no afectan a las barreras, incoherente con el objetivo "replicar stats").

### 4.3 `FACTOR_ETAPA` `[APROBACIÓN] [D2]`

| Etapa | Factor | Efecto de producto |
|---|---|---|
| 1 | `0.50` | la pieza de nido 1/2 es una mejora modesta |
| 2 | `0.75` | intermedia |
| 3 (y ≥3) | `1.00` | **coincide con los ejemplos del cliente** y con "1-3-5 desbloquea los artículos buenos" |

Sin `FACTOR_ETAPA` (todo 1.0), equipar el set completo de Gengar sobre Gengar **duplica** su Ataque Especial; con la curva, el set completo sigue siendo fuerte pero no rompe el motor. Alternativas ofrecidas al desarrollador: `{1.0, 1.0, 1.0}` (fiel literal, riesgo alto de balance) y `{0.4, 0.7, 1.0}` (más conservadora).

### 4.4 Ejemplos numéricos (etapa 3, factor 1.0, salvo Gastly)

| Especie | Base HP/Atk/Def/SpA/SpD/Vel | **Arma** | **Armadura** | **Amuleto** |
|---|---|---|---|---|
| **Gengar** (mixto) | 60 / 65 / 60 / 130 / 75 / 110 | `atk 65`, `spAtk 130` | `def 60`, `spDef 75`, `hp 60` | `speed 110`, `spAtk 65` (`130 × 0.5`) |
| **Machamp** (físico) | 90 / 130 / 80 / 65 / 85 / 55 | `atk 130`, `spAtk 65` | `def 80`, `spDef 85`, `hp 90` | `speed 55`, `atk 65` |
| **Alakazam** (especial frágil) | 55 / 50 / 45 / 135 / 95 / 120 | `atk 50`, `spAtk 135` | `def 45`, `spDef 95`, `hp 55` | `speed 120`, `spAtk 67` (`floor(67.5)`) |
| **Pinsir** (sin evolución) | 65 / 125 / 100 / 55 / 70 / 85 | `atk 125`, `spAtk 55` | `def 100`, `spDef 70`, `hp 65` | `speed 85`, `atk 62` (`floor(62.5)`) |
| **Gastly** (etapa 1, **factor 0.5**) | 30 / 35 / 30 / 100 / 35 / 80 | `atk 17`, `spAtk 50` | `def 15`, `spDef 17`, `hp 15` | `speed 40`, `spAtk 25` |

Los tres conjuntos de Gengar reproducen **exactamente** los números del cliente (130+65, 60+75+60, 110), y el Amuleto aplica la corrección de errata ya ratificada (65 en lugar de 75).

### 4.5 Impacto en barreras, fórmula de daño y CP

| Destino | Cómo entra | Efecto |
|---|---|---|
| **Barreras** | `defense`/`spDef` base alimentan `defenseHp`/`spDefenseHp` con la fórmula de HP aplicada a Def y SpD (`BattleStats.php:63-70`) | la Armadura sube el **techo** de la barrera **y** reduce el daño (doble efecto, riesgo §10.4) |
| **Fórmula de daño** | `attack`/`spAtk` base → stat efectivo usado por el manejador base de la cadena | escalado coherente con la especie |
| **Turnos** | `speed` | el Amuleto puede alterar el orden de actuación; es su función |
| **CP** | `CalculadorCP` suma los seis stats de `BattleStats` (`src/Pokemon/Domain/Stats/CalculadorCP.php:26-45`) | el CP mostrado **debe** incluir las piezas o `/equipos` mentiría `[D8]` |

El Amuleto usa **stats base de especie**, nunca los del reclutado: es determinista, cacheable y no depende de IV/EV/nivel del portador (cierra la pregunta abierta del análisis).

---

## 5. Identidad y persistencia

### 5.1 Piezas: claves por etapa en `player_inventory` `[D3]`

| Aspecto | Decisión |
|---|---|
| Clave | `arma:{species_id}`, `armadura:{species_id}`, `amuleto:{species_id}` |
| Cantidad | `0` o `1` (**unicidad por jugador y clave**) |
| "Nivel de la pieza" | **no existe**: el nivel es la etapa, y la etapa es la clave |
| "Evolucionar la pieza" | receta de etapa N+1 que **consume** la clave de etapa N y produce la N+1 |
| Copias | **no** en F1 `[D3]` |

**Por qué no instancias:** `player_inventory` es `(user_id, item_key) → cantidad` (`.../create_player_inventory_table.php:15-24`); una tabla de instancias obligaría a sustituir el puerto `InventarioJugador` (`src/Shared/Domain/Ports/InventarioJugador.php:19-58`), su adaptador (`src/Shared/Infra/Inventario/InventarioJugadorEloquent.php`) y todos sus consumidores (Granjas, Forja, Recompensas), además de migrar los contadores existentes. Coste alto, beneficio nulo para el caso de uso (una pieza única por etapa).

**Whitelist del inventario** `[APROBACIÓN] [D9]`: `InventarioJugadorEloquent::asegurarClaveValida()` solo acepta `CatalogoItems::existe()` o el carve-out de caramelo (`:181-201`). `CatalogoItems` **no puede** alojar las piezas: es Domain puro sin BD (`src/Shared/Domain/Items/CatalogoItems.php:208-307`) y las claves dependen de `species_id` (≈150+ especies × 3 tipos).

Recomendación: **carve-out estructural por prefijo**, calcado del que ya existe para caramelo:
```php
private function esClavePieza(string $clave): bool
{
    return ClavePieza::esClaveValida($clave);   // ^(arma|armadura|amuleto):[1-9][0-9]*$
}
```
Sin dependencia Shared → Forja, sin enumerar 450 claves, sin tocar `CatalogoItems`. La seguridad no se degrada: **escribir** una pieza solo ocurre en `FabricarPieza`, que resuelve la receta antes (404 si no existe); **leer** una clave basura produce un saldo 0, y el nombre/icono se resuelven en Forja.
Alternativa descartada: registrar las ~450 claves en `CatalogoItems` (exigiría generar y mantener un snapshot de datos de BD dentro de Domain, y quedaría obsoleto en cuanto cambie un stat).

`CategoriaItem` **sí** gana tres casos (`ARMA`, `ARMADURA`, `AMULETO`), porque es el vocabulario canónico de prefijos (`src/Shared/Domain/Items/CategoriaItem.php:11-27`) y lo consumen el catálogo, la UI y las secciones de la Forja.

### 5.2 Equipamiento: tabla `recruit_gear`

```text
recruit_gear
├── id                bigint PK
├── reclutado_id      foreignId → reclutados.id, onDelete: cascade
├── slot              unsignedTinyInteger              -- 1..3
├── item_key          string(64)                        -- "arma:94"
├── timestamps
├── UNIQUE (reclutado_id, slot)
├── UNIQUE (reclutado_id, item_key)
└── INDEX  (item_key)
```

| Constraint / índice | Por qué |
|---|---|
| `unique(reclutado_id, slot)` | un solo objeto por slot; 3 slots por reclutado |
| `unique(reclutado_id, item_key)` | implementa literalmente "3 del mismo tipo y de **diferente pokemon**": no se apila la misma pieza dos veces |
| `index(item_key)` | comprobación de "pieza ya poseída" (inventario ∪ equipamiento) y desequipado masivo |
| FK `reclutado_id → reclutados.id` cascade | evita piezas huérfanas al borrar un reclutado |
| Sin FK a catálogo | el catálogo es código, igual que `complements.stat` o `nests.evolution_chain_id` |

**Por qué tabla y no `obj_equipados`** `[D5-bis]`:

| Evidencia | Consecuencia |
|---|---|
| 62 ocurrencias de `obj_equipados` en `tests/` (todas `=> []`) | extenderlo a lista de 3 arriesga 62 asserts |
| `ConstruirEquipoJugador.php:86-88` lee `array_values($items)[0]` | semántica de "un objeto" incrustada en batalla |
| `CombateExploracion.php:53-55` idéntico | idem en exploraciones |
| `ActualizarObjetosEquipo.php:31-45` escribe `[$itemKey]` | el contrato de `PATCH /teams/{team}/items` es 1 string por slot |
| `app/Models/Reclutado.php:23,29` cast `array` | JSON sin integridad ni unicidad |

Una tabla nueva es **aditiva y reversible** (un `dropIfExists`), y separa limpiamente **objeto de combate** (`obj_equipados[0]`, robable/intercambiable) de **pieza forjada** (`recruit_gear`, no robable).

`[APROBACIÓN] [D-nombrado]` — nombre de tabla: recomendado `recruit_gear` (regla "tablas en inglés", `docs/conventions.md`); alternativa `reclutado_equipamiento` si el equipo prefiere proximidad con `reclutados`. Modelo: `app/Models/RecruitGear.php` (junto a `Reclutado`/`Team`), con `// atajo: modelo en app/Models, mover a src/Equipos/Infra/Models al migrar el módulo` según la convención de atajos.

### 5.3 Migración

| Archivo (no escrito) | Contenido |
|---|---|
| `database/migrations/2026_11_17_000001_create_recruit_gear_table.php` | `Schema::create('recruit_gear', …)` con las constraints de §5.2; `down()` = `dropIfExists` |
| — | **no** hay cambios en `nests`, `player_inventory`, `reclutados` ni `pokemon` |

Índices existentes suficientes para la consulta de la Forja: `farms` tiene `unique(user_id, habitat_id)` y `nests` tiene `unique(farm_id, evolution_chain_id)` + `index(evolution_chain_id)` (`database/migrations/2026_10_26_000001_create_farm_tables.php:16-34`). No se añade índice nuevo hasta que un `EXPLAIN` lo justifique.

### 5.4 Equipar y desequipar

```text
EquiparPieza::handle(userId, reclutadoId, slot, clave)
  DB::transaction:
    reclutado = lock reclutados (where id + user_id)     // 404 si ajeno/inexistente
    pieza    = ClavePieza::desde(clave)                  // 422 si formato inválido
    if slot < 1 || slot > 3                              // 422
    // swap: devolver la pieza anterior al inventario
    if existe fila (reclutado, slot):
        inventario.incrementar(userId, anterior.item_key, 1); delete fila
    inventario.consumir(userId, clave, 1, 'No tienes esa pieza')  // 422 StockInsuficiente
    insert (reclutado_id, slot, item_key)                 // unique → 422 si ya equipada en otro slot
    return equipamiento completo (3 slots)

DesequiparPieza::handle(userId, reclutadoId, slot)
  DB::transaction:
    reclutado = lock (id + user_id)                       // 404 si ajeno
    fila = select for update (reclutado, slot)            // 404 si vacío
    inventario.incrementar(userId, fila.item_key, 1)
    delete fila
```

- **IDs por URL**, nunca por body (`docs/conventions.md`, regla `infra-controllers`).
- **Anti-IDOR**: todo `where('user_id', $userId)` + 404, igual que `ActualizarObjetosEquipo.php:29` (`abort_unless($reclutado?->user_id === $userId, 404)`).
- **Swap en slot ocupado** `[D10]`: devuelve la anterior al inventario en la misma transacción. Alternativa (422 "slot ocupado") es más simple pero peor UX.
- **Atomicidad**: el orden es siempre *leer/escribir inventario primero, fila de equipamiento después* (o al revés, pero **siempre el mismo**), para evitar deadlocks con `FabricarPieza`, que bloquea inventario ordenado por `item_key` (`InventarioJugadorEloquent.php:104-149`).

### 5.5 Evolución del Pokémon que lleva la pieza `[APROBACIÓN] [D4]`

- La pieza **permanece equipada y sigue funcionando**. No se auto-consume, no se bloquea, no se transforma.
- **No hay restricción especie↔pieza al equipar.** El cliente pide explícitamente poder llevar "arma gengar, arma alakazam…" en el mismo Pokémon; con equipamiento libre, la evolución del portador es irrelevante.
- Motivo de diseño: destruir propiedad del jugador sin consentimiento es peor que permitir una pieza "no óptima". La UI puede mostrar una etiqueta informativa ("pieza de otra especie") sin bloquear.

---

## 6. Oferta de Forja

### 6.1 Frontera Forja ↔ Granjas: puerto `NidosConstruidos`

```php
namespace Src\Forja\Domain\Ports;

interface NidosConstruidos
{
    /** @return NidosConstruidosCollection  solo nidos con level >= 1 del jugador */
    public function paraJugador(int $userId): NidosConstruidosCollection;
}
```

DTO de frontera `NidoConstruido` (en `src/Forja/Domain/DataTransferObjects/`): `farmId`, `habitatId`, `habitatNombre`, `evolutionChainId`, `nivelNido`, `bonguriClave`.

**Ubicación del puerto:** en el **módulo consumidor** (`Forja/Domain/Ports`), no en Shared. Es exactamente el patrón ya validado en el proyecto: `Src\Exploraciones\Domain\Ports\ConsultorPlantaciones` (consumidor Exploraciones) implementado por `Src\Granjas\Infra\Plantaciones\ConsultorPlantacionesEloquent` y enlazado en `app/Providers/AppServiceProvider.php:61`.

**Adaptador:** `Src\Granjas\Infra\Nidos\NidosConstruidosEloquent` (`implements NidosConstruidos`). Infra de Granjas depende del Domain de Forja: dirección aceptada (Infra → Domain), sin ciclo, mismo precedente.

**Binding:** una línea en `AppServiceProvider::register()`, junto a `ConsultorPlantaciones` (`app/Providers/AppServiceProvider.php:61`).

### 6.2 Lectura de datos de especie (read model)

`ListarPiezasForja` necesita, por familia con nido: especies del chain, stats base, habilidades y aristas de evolución. Es una **lectura**, no un repositorio de escritura:

- `src/Forja/Infra/Repositorios/LectorFamiliasPiezas.php` (nombre propuesto): 3 consultas acotadas a los `evolution_chain_id` con nido — `pokemon` (id, name, species_id, evolution_chain_id), `pokemon_stats`, `ability_pokemon`/`abilities`, `pokemon_evolution`.
- Reutiliza `EstadisticasBasePokemon::desdePokemon()` (Infra de `app/Support`) o un mapeo equivalente para `StatsValue`.
- **Sin N+1**: una consulta por tabla con `whereIn`.

### 6.3 Consulta de nidos construidos

```sql
SELECT n.id, n.farm_id, n.evolution_chain_id, n.level,
       f.habitat_id, h.name AS habitat_nombre, h.bonguri
FROM nests n
JOIN farms f   ON f.id = n.farm_id
JOIN habitats h ON h.id = f.habitat_id
WHERE f.user_id = :userId
  AND n.level >= 1
ORDER BY n.evolution_chain_id, n.level DESC, f.habitat_id
```

Precedente idéntico en `src/Granjas/App/CosecharTodo.php:41-62` (granjas del jugador + `nidos where level >= 1`). `habitats.bonguri` es `string(16)` nullable con backfill por provincia (`database/migrations/2026_11_04_000002_add_bonguri_to_habitats.php:20-40,57-79`); el fallback canónico es `ClaveBonguri::tryFrom(...) ?? ClaveBonguri::porDefecto()` (`src/Granjas/App/ObtenerGranja.php:96-98`), con `[D-nombrado]` fallback blanco ya usado por Granjas.

### 6.4 Varios nidos de la misma familia: una receta por nido, una fila por familia `[APROBACIÓN] [D11]`

| Nivel | Decisión |
|---|---|
| **Backend** | emite **una receta por (pieza, nido)** con el **mismo `resultado_clave`** y `receta` distinta: `receta:pieza:arma:94:h{habitatId}` |
| **UI** | agrupa por `resultado_clave` → **una fila por familia**, con **selector de bioma/bonguri** dentro |
| **Gate** | el nivel exigido se evalúa contra el nido **de ese hábitat**; cada variante tiene su propio `desbloqueada` |

Esto **reutiliza el mecanismo de variantes ya existente**: la vista ya agrupa por `resultado_clave` (`resources/views/forja/index.blade.php:388-403`), ya tiene selector para el recipiente (7 variantes por bonguri, `:160-206`) y ya ordena las variantes poniendo primero las fabricables (`variantesRecipiente`, `:498-513`). **Cero lógica nueva de agrupación en el frontend.**

### 6.5 Contrato de `GET /api/forja/recetas` (aditivo)

Respuesta actual: `{"recetas":[…]}` (`src/Forja/Infra/Controllers/ForjaController.php:31-38`, shape en `src/Forja/App/DataTransferObjects/RecetaDetalle.php:42-57`). Se **añade** sin romper:

```jsonc
{
  "secciones": [                                   // NUEVO top-level
    { "clave": "piezas",   "nombre": "Piezas de tus nidos", "orden": 0 },
    { "clave": "medicinas","nombre": "Medicinas",           "orden": 1 },
    { "clave": "mejoras",  "nombre": "Mejoras",             "orden": 2 },
    { "clave": "reductores","nombre": "Reductores",         "orden": 3 },
    { "clave": "resistencias","nombre": "Resistencias",     "orden": 4 }
  ],
  "recetas": [
    {
      "clave": "receta:pocion_oran",               // existente, sin cambios
      "resultado_clave": "pocion:pocion",
      "resultado_nombre": "Poción",
      "resultado_icono": "/images/items/pocion/pocion.webp",
      "resultado_cantidad": 1,
      "etiqueta": "Desde Baya Aranja",
      "ingredientes": [ /* existente */ ],
      "puede_fabricar": true,
      "motivo": null,
      "categoria": "pocion",                       // NUEVO (CategoriaItem->value)

      // --- solo presentes cuando tipo_pieza !== null ---
      "tipo_pieza": "arma",
      "familia": { "evolution_chain_id": 40, "nombre": "Gastly", "primer_integrante_id": 92 },
      "especie": { "id": 92, "nombre": "Gastly", "etapa": 1, "profundidad_familia": 3 },
      "nivel_nido_requerido": 1,
      "nivel_nido_actual": 3,
      "desbloqueada": true,
      "habitat": { "id": 12, "nombre": "Torre Pokémon" },
      "bonguri": { "clave": "bonguri:negro", "nombre": "Negro", "icono": "/images/bonguri/…", "origen": "Barcelona" },
      "stats": { "hp": 0, "atk": 65, "def": 0, "spAtk": 130, "spDef": 0, "speed": 0 },
      "habilidad": { "clave": "levitacion", "nombre": "Levitación", "descripcion": "Inmune a movimientos de tipo Tierra." },
      "pieza_anterior": { "clave": "arma:93", "nombre": "Arma de Haunter" }
    }
  ]
}
```

Reglas del contrato:
- **`categoria`** es lo que permite retirar `FORJA_CATEGORIAS`/`forjaCategoriaDeResultado` del Blade (`resources/views/forja/index.blade.php:306-335`), eliminando la duplicación de conocimiento de prefijos en JS (regla `frontend-minimal-logic`).
- **`secciones`** es la fuente de verdad del orden y los nombres: el frontend solo pinta.
- **`stats`** ya calculados: el frontend **no** multiplica ni redondea.
- **`etiqueta`** y **`descripcion`** ya redactadas en español.
- **`origen`** del bonguri** reutiliza `ListarRecetas::origenIngrediente()` (`src/Forja/App/ListarRecetas.php:97-106`).

**Compatibilidad:** las recetas existentes no cambian de campos ni de valores; solo ganan `categoria`. Los consumidores actuales (una sola vista) siguen funcionando mientras no se despliegue el Blade nuevo.

### 6.6 Motivos (vocabulario y precedencia)

`puede_fabricar` es la conjunción de todas las condiciones; `motivo` informa de la **primera** que falla, en este orden determinista (backend):

| # | `motivo` | Condición | Texto sugerido (backend) |
|---|---|---|---|
| 1 | `nido_insuficiente` | `nidoNivel < nivelRequerido` | "Requiere nido nivel {n}" |
| 2 | `pieza_ya_poseida` | saldo en inventario o equipada `>= 1` | "Ya tienes esta pieza" |
| 3 | `pieza_anterior_equipada` | la pieza anterior existe pero está en `recruit_gear` | "Desequipa la pieza anterior para mejorarla" |
| 4 | `requiere_pieza_anterior` | etapa ≥ 2 y no hay pieza anterior en inventario | "Requiere {Arma de Haunter}" |
| 5 | `faltan_materiales` | algún ingrediente insuficiente | "Faltan materiales" (existente) |

`desbloqueada` (nido suficiente) se emite **independientemente** de `puede_fabricar`: la UI puede mostrar en gris las no desbloqueadas y resaltar las listas.

Hoy `motivo` solo conoce `'faltan_materiales'` en backend (`src/Forja/App/ListarRecetas.php:90`) y en JS (`textoMotivo`, `resources/views/forja/index.blade.php:515-525`). **El texto pasa a venir resuelto del backend** para no ampliar el mapa JS.

### 6.7 `POST /api/forja/fabricar`

- **Ruta y body sin cambios**: `{ "receta": "receta:pieza:arma:94:h12", "cantidad": 1 }` (`routes/forja.php:9-11`; `FabricarRequest` ya valida `string|max:64`, y la clave propuesta mide 33 caracteres).
- **Respuesta:** `ResultadoFabricacion` actual (`receta`, `resultado`, `cantidad`, `saldos`) + **`pieza`** opcional (`{clave, stats, habilidad}`) para que la UI confirme qué se ha forjado sin recalcular.
- **Errores:** 404 `RecetaNoExiste`; 422 `ViolacionReglaNegocio` (`nido_insuficiente`, `pieza_ya_poseida`, `pieza_anterior_equipada`) y `StockInsuficiente`; todo vía el mapa global de `bootstrap/app.php` (404/422/400), sin `try/catch` en el controlador.

---

## 7. Fabricación

### 7.1 Recetas

Clave: `receta:pieza:{tipo}:{speciesId}:h{habitatId}` (una por pieza y nido).
Resultado: `{tipo}:{speciesId}`.

### 7.2 Ingredientes y cantidades `[APROBACIÓN] [D6]`

| Tipo | Etapa 1 | Etapa 2 | Etapa 3 |
|---|---|---|---|
| `bonguri:{color del hábitat}` | ×2 | ×3 | ×4 |
| `material:recipiente` | ×1 | ×1 | ×1 |
| Baya rara del tipo | ×1 | ×2 | ×3 |
| **Pieza anterior** | — | ×1 | ×1 |

Baya rara por tipo (ya existen en `ClaveBaya` como `BAYAS_RARAS`, `src/Shared/Domain/Items/CatalogoItems.php:48-56`): **Arma → `baya:salac`**, **Armadura → `baya:ganlon`**, **Amuleto → `baya:petaya`**.

**Sin caramelos de familia**: `familia:{chain}` es la moneda de la mejora de nido (coste 10/20/30/40/50 según `CalculadorBonosGranja::costeNido()`, `src/Granjas/Domain/CalculadoraBonosGranja.php:91-101`) y usarla también en la Forja duplicaría la presión sobre el mismo recurso. El nido es **gate**, no coste (responde a la pregunta abierta del cliente).

### 7.3 Caso de uso

```text
FabricarPieza::handle(int $userId, string $recetaClave): ResultadoFabricacion
  receta = CatalogoPiezas::receta($recetaClave)          // 404 RecetaNoExiste

  DB::transaction:
    nido = Nido::query()->getQuery()
              ->join('farms','farms.id','=','nests.farm_id')
              ->where('nests.farm_id', receta.farmId)
              ->where('nests.evolution_chain_id', receta.evolutionChainId)
              ->where('farms.user_id', $userId)
              ->lockForUpdate()->first()                 // 404 si no es del jugador
    if nido === null || nido.level < receta.nivelRequerido
        -> 422 ViolacionReglaNegocio('Requiere nido nivel N')

    if piezaYaPoseida(userId, receta.resultado)
        -> 422 'Ya tienes esta pieza'                    // inventario ∪ equipamiento

    if receta.piezaAnterior !== null:
        if equipada(userId, receta.piezaAnterior) -> 422 'Desequipa la pieza anterior'
        // si no está en inventario, consumirVarios lanzará StockInsuficiente (422)

    inventario.consumirVarios(userId, receta.ingredientes, 'Faltan materiales para fabricar')
    inventario.incrementar(userId, receta.resultado->clave(), 1)
    return ResultadoFabricacion(…, saldos)
```

### 7.4 Cómo se evita fabricar la etapa 3 sin cadena o sin nido 5

Cuatro barreras independientes:

1. **Catálogo**: `CatalogoPiezas` solo genera recetas para etapas ≤ profundidad de la familia; una etapa 4 no existe en el catálogo → 404.
2. **Gate de nido**: `nido.level >= nivelRequerido` bajo `lockForUpdate`; etapa 3 en profundidad 3 exige nivel 5.
3. **Consumo de la anterior**: la receta de etapa ≥ 2 **incluye** la pieza anterior en `ingredientes`; sin ella, `consumirVarios` lanza `StockInsuficiente` (422) *antes* de escribir nada.
4. **Anti-duplicado**: `pieza_ya_poseida` impide acumular dos veces la misma etapa.

Además la UI muestra `requiere_pieza_anterior` **antes** de pulsar, para que el 422 no sea la primera información.

### 7.5 Transacción, locks e idempotencia

| Aspecto | Decisión |
|---|---|
| Transacción | `DB::transaction` única para gate + consumo + producción (patrón de `Fabricar.php:37-64`) |
| Lock del nido | `lockForUpdate` sobre `nests` con join a `farms.user_id`, para que una mejora concurrente no permita saltarse el gate |
| Lock del inventario | `consumirVarios` bloquea en orden de `item_key` (`InventarioJugadorEloquent.php:104-149`) |
| Idempotencia | **no hay clave de idempotencia** (coherente con la Forja actual); la protección es `pieza_ya_poseida` + el stock, que se revalida bajo lock |
| Concurrencia doble-submit | el segundo intento falla con `pieza_ya_poseida` (inventario ya incrementado) o `StockInsuficiente` |
| Orden de bloqueo | nido → inventario (ordenado por clave) → fila de producción. `EquiparPieza` bloquea reclutado → inventario. Sin ciclos entre ambos |

### 7.6 Errores

| Situación | Excepción | HTTP |
|---|---|---|
| Clave de receta inexistente o pieza no generada | `RecetaNoExiste` (`src/Forja/Domain/Exceptions/RecetaNoExiste.php`) | 404 |
| Nido ausente / ajeno / nivel insuficiente | `ViolacionReglaNegocio` | 422 |
| Pieza ya poseída / anterior equipada | `ViolacionReglaNegocio` | 422 |
| Materiales o pieza anterior insuficientes | `StockInsuficiente` | 422 |
| Clave de pieza con formato inválido | `ItemDesconocido` | 422 |

---

## 8. Equipamiento y batalla

### 8.1 Dónde vive la gestión

`src/Equipos/App/Piezas/`:

| Clase | Responsabilidad |
|---|---|
| `EquiparPieza` | consume del inventario, escribe/swap en `recruit_gear` |
| `DesequiparPieza` | devuelve al inventario, borra fila |
| `ObtenerPiezas` | lee los 3 slots de N reclutados en **una** consulta (`whereIn`) |
| `src/Equipos/Domain/ValueObjects/EquipamientoPiezas.php` | VO readonly `slot → clave` (3 slots), `toArray()` de frontera |

Se reutiliza `src/Equipos` (ya dueño de `obj_equipados` y del `PATCH /teams/{team}/items`, `app/Http/Controllers/TeamController.php:216-241`) en lugar de crear `src/Equipamiento/`: 4 clases no justifican módulo (regla de cambio mínimo).

### 8.2 El colapso: dos puntos de inyección

**Punto 1 — mapeo compartido.** `MapeadorPokemonBatalla::desdePokemon()` (`src/CombateEntrenadores/App/MapeadorPokemonBatalla.php:50-105`) gana un parámetro:

```php
?EquipamientoPiezas $piezas = null,   // claves equipadas
?StatsValue $bonosPiezas = null,      // bonos ya agregados (Forja → Pokemon.Domain)
array $effectKeysPiezas = [],         // pasivas ya resueltas
```

Y en el cuerpo:

```php
$stats = $this->statsDe($pokemon);                          // base actual
$stats = AplicadorBonusPiezas::aplicar($stats, $bonosPiezas); // + bonos (si hay)
…
effectKeys: $this->efectosDe($pokemon, $habilidadActivaId)
                ->concat($effectKeysPiezas)->toList(),       // + pasivas
item: $item,                                                 // objeto de combate intacto
```

**`AplicadorBonusPiezas`** (`src/Pokemon/Domain/Stats/AplicadorBonusPiezas.php`) es puro y **no depende de Forja**: recibe `DatosStats` + `StatsValue` de bonos y devuelve `DatosStats` sumado. La agregación de definiciones → `StatsValue` la hace Forja (`CalculadoraStatsPieza`), de modo que `Pokemon.Domain` no conoce el módulo Forja.

**Punto 2 — los dos call sites.**

| Modo | Call site | Cambio |
|---|---|---|
| Ruta, Entrenador, Gimnasio, Mazmorra | `ConstruirEquipoJugador::desdeEquipo()` (`src/CombateEntrenadores/App/ConstruirEquipoJugador.php:52-113`) | carga `recruit_gear` de los 3 reclutados en **una** consulta y pasa bonos + pasivas al mapeador |
| Exploración (1v1) | `CombateExploracion::combatir()` (`src/Exploraciones/App/CombateExploracion.php:42-85`) | idem para el explorador |

`ConstruirEquipoJugador` es el embudo de los 4 modos 3v3 (`src/CombateRuta/App/IniciarCombateRuta.php`, `src/Gimnasios/App/IniciarCombateGimnasio.php`, `src/Mazmorras/App/IniciarCombateMazmorra.php`, `src/CombateEntrenadores/App/IniciarCombateEntrenador.php`), y `CreadorBatallaSesion` (`app/Support/CreadorBatallaSesion.php`) los orquesta. **Solo 2 archivos de producción** cambian para cubrir los 5 modos.

Los rivales (entrenador/gimnasio/mazmorra/ruta salvaje) **no** llevan piezas: no tienen reclutado. `[D-nombrado]` Sin cambios en `GeneradorEquipo*`.

### 8.3 Pasivas `[APROBACIÓN] [D7]`

- La pasiva viaja como **clave de efecto** en `effectKeys`, que es exactamente el canal de las habilidades de especie (`EquipoBatalla::fromData` procesa `effectKeys` con `FabricaEfectos::crearEfectos`, `src/Battle/Domain/EquipoBatalla.php:199-211`).
- Se registra en `BattleEffectServiceProvider::registrarHabilidades()` (A) con las claves que ya usa `CatalogoHabilidades` (`src/Battle/Domain/Catalog/CatalogoHabilidades.php`), reutilizando efectos existentes. **No se crea un canal nuevo.**
- **Consecuencia**: la pasiva es **anulable** por supresión de habilidad, porque `HabilidadCombatiente::esEfectoDeObjeto()` solo protege las claves de `ClaveItem` (`src/Battle/Domain/Componentes/Combatiente/HabilidadCombatiente.php:160-163`). Es el comportamiento deseado ("habilidad pasiva", no "objeto").
- **No** se registra en `ClaveItem`: si se hiciera, el motor la trataría como objeto robable/intercambiable, incoherente con que las piezas **no** son robables.
- **Asignación recomendada:** solo el **Amuleto de etapa 3** otorga la habilidad de la especie (slot 1 de `ability_pokemon`, `unique(pokemon_id, slot)` en `database/migrations/2026_10_22_000004_create_ability_pokemon_table.php:12-23`). Acumulación máxima: 1 pasiva de pieza + las de especie. Alternativas: 1 por pieza (hasta 3, riesgo de apilado) o solo el Arma.

### 8.4 Separación del objeto de combate

| Concepto | Dónde vive | ¿Robable / intercambiable? | Efecto en combate |
|---|---|---|---|
| **Objeto de combate** (`life_orb`, `leftovers`, …) | `reclutados.obj_equipados[0]` → `Combatiente::setItem()` (`src/Battle/Domain/Combatiente.php:649-651`) | **Sí** (`ObjetoCombatiente::robarDe/intercambiarCon/reciclar`, `.../ObjetoCombatiente.php:120-138`) | multiplicador de daño, curación, cargas |
| **Pieza de forja** | `recruit_gear` (máx. 3) → colapsada en stats base + `effectKeys` | **No** (no entra en `EstadoObjeto`) | stats fijos + pasiva |

`ManejadorObjetosEquipados` sigue leyendo `$action->attacker->item()` y su mapa `['life_orb' => 1.30]` (`src/Battle/Domain/Chain/ManejadorObjetosEquipados.php:24-47`): **no se toca**.

### 8.5 `SESSION_VERSION` y compatibilidad

**No se incrementa** (se mantiene `21`, mínimo compatible `9`, `app/Support/BattleSessionService.php:50,64`).

Justificación verificada:
- `DatosPokemonBatalla` **no cambia su constructor** (`src/Battle/Domain/DatosPokemonBatalla.php:26-73`): los bonos ya están dentro de `hp/atk/…` y las pasivas dentro de `effectKeys`.
- `EquipoBatalla::fromData` (`:157-236`) no cambia.
- `Combatiente::__serialize/__unserialize` serializa el `PokemonEntity` completo con `serialize($pokemon)` (`SerializacionCombatiente.php:28-56`, `SerializadorCombatiente.php:104-107,150-163`): los stats ya sumados se persisten tal cual, y al rehidratar no se recalcula nada desde `recruit_gear`.
- Si el jugador cambia de piezas **durante** una batalla, la batalla en curso conserva las stats con las que empezó (comportamiento correcto y predecible).

### 8.6 CP `[APROBACIÓN] [D8]`

`CalculadoraPcReclutado::calcular()` usa `EstadisticasBasePokemon::desdePokemon()` + `CalculadorCP::desdeStats()` (`app/Support/Pc/CalculadoraPcReclutado.php:17-28`). Para que `/equipos` no muestre un CP distinto al que el Pokémon tiene en combate, se le pasan los bonos de piezas:

```php
$base  = EstadisticasBasePokemon::desdePokemon($reclutado->pokemon);
$base  = AplicadorBonusPiezas::aplicar($base, $bonosPiezas);
return CalculadorCP::desdeStats($base, $reclutado->evs, $reclutado->ivs, $nivel);
```

Coste: **una** consulta a `recruit_gear` para todos los reclutados del listado (`ObtenerPiezas::paraReclutados(array $ids)`), cargada en `PlayerController` y pasada a `ReclutadoSerializer::serializarColeccion`. Sin N+1.
Alternativa si el desarrollador prefiere no tocar `/equipos` en F2: mostrar CP sin piezas y un badge "+N CP con equipo".

### 8.7 Contrato del serializer

`ReclutadoSerializer` (`app/Support/ReclutadoSerializer.php:61` expone `obj_equipados`) gana:

```jsonc
"piezas": [
  { "slot": 1, "clave": "arma:94", "tipo": "arma", "nombre": "Arma de Gengar",
    "icono": "/images/iconos_webp/94.webp", "stats": { "atk": 65, "spAtk": 130 } },
  { "slot": 2, "clave": null, … },
  { "slot": 3, "clave": null, … }
]
```
Siempre 3 entradas (los slots vacíos con `clave: null`) para que el frontend no rellene huecos.

### 8.8 Endpoints

| Método | Ruta | Cuerpo | Respuesta |
|---|---|---|---|
| `GET` | `/api/reclutados/{reclutado}/piezas` | — | `{piezas:[3]}` (404 si ajeno) |
| `PUT` | `/api/reclutados/{reclutado}/piezas/{slot}` | `{clave}` | `{piezas:[3]}` |
| `DELETE` | `/api/reclutados/{reclutado}/piezas/{slot}` | — | `{piezas:[3]}` |

Fichero de rutas: `routes/player.php` (donde ya viven las de reclutados) o `src/Equipos/Infra/routes.php` si se crea en F2. **IDs por URL**, FormRequest propio (`EquiparPiezaRequest`: `clave` required string max 64 + `ClavePieza::esClaveValida`).

---

## 9. UI

### 9.1 Forja — nueva sección

**Archivo:** `resources/views/forja/index.blade.php` (**limpio, sin cambios locales**: no figura en `git status`).

| Elemento | Implementación |
|---|---|
| Sección | nueva entrada al principio, renderizada desde `secciones[]` (el backend manda nombre y orden) |
| Agrupación | se mantiene la existente por `resultado_clave` (`:388-403`); **una fila por familia** |
| Selector de bioma/bonguri | reutiliza el patrón de variantes del recipiente (`:160-206`, `variantesRecipiente` `:498-513`) |
| Categoría | `categoria` del backend; **se eliminan** `FORJA_CATEGORIAS` (`:306-311`) y `forjaCategoriaDeResultado` (`:313-335`) |
| Motivos | texto resuelto por el backend; `textoMotivo` (`:515-525`) se sustituye por `receta.motivo_texto` |
| Stats de la pieza | píldoras con los valores de `receta.stats` (sin cálculo en JS) |
| Estado vacío | "Aún no tienes nidos construidos. Mejora un nido en cualquier hábitat para desbloquear sus piezas." (solo si `secciones` existe y no hay recetas de piezas) |
| Cargando | reutiliza el spinner existente (`:45-56`) |
| Error | reutiliza el bloque de reintento (`:33-43`) |
| Accesibilidad | mismo patrón `<button aria-expanded aria-controls>` de las categorías (`:79-103`); `aria-label` en el selector de bioma con el nombre del hábitat; `aria-live="polite"` ya presente (`:23`) |
| Refresco | `inventario:actualizado` (`:350-353`) y recarga tras fabricar (`:565`) ya funcionan |

### 9.2 Iconos y fallback `[APROBACIÓN] [D-assets]`

- **F1 (sin assets nuevos):** `resultado_icono` = `/images/iconos_webp/{species_id}.webp` (siempre existe; contrato del proyecto). El tipo se comunica con una etiqueta/badge ("Arma"/"Armadura"/"Amuleto") y el color del token del tipo, no con un icono propio.
- **F3 (assets propios, opcional):** `/images/items/pieza/{tipo}/{species_id}.webp` con cadena de fallback **resuelta en backend** (`icono_pieza` → `icono`), de modo que el `<img>` nunca apunta a un 404. Se conserva el `onerror` → placeholder como red de seguridad, igual que en la Pokédex.

### 9.3 Equipos — 3 slots

**Ubicación:** `resources/views/equipos/partials/modal-detalle-pokemon.blade.php` (**limpio**) + **partial nuevo** `resources/views/equipos/partials/piezas-reclutado.blade.php`.

```
modal-detalle-pokemon.blade.php
  └── @include('equipos.partials.piezas-reclutado')      // 1 línea añadida
        ├── x-data="piezasReclutado()"                    // componente Alpine autocontenido
        ├── data-reclutado-id="{{ … }}"
        ├── data-piezas='@json(…)'
        └── @push('scripts') @once <script> … </script> @endonce
```

**Por qué esta ubicación y esta forma:**
- `modal-detalle-pokemon.blade.php` **no está modificado** localmente; `modal-editar-equipo.blade.php` está **untracked (otra tarea)** y **no se toca**.
- El componente Alpine vive **dentro del partial nuevo** (`@once @push('scripts')`), así que **`resources/views/equipos/index.blade.php` (modificado) no se edita**. Cero conflicto.
- El partial lee `data-piezas` y llama a `PUT/DELETE /api/reclutados/{id}/piezas/{slot}`; al terminar emite `inventario:actualizado` y refresca los datos locales sin recargar la página.
- 3 `<select>`/botones por slot, con `aria-label` por slot, y una lista de piezas disponibles del inventario (`GET /api/reclutados/{id}/piezas` incluye `disponibles[]`) para no teclear claves.

### 9.4 Archivos en conflicto y estrategia

| Archivo | Estado | Estrategia |
|---|---|---|
| `resources/views/equipos/index.blade.php` | **modificado** | **no se edita** (el componente va en el partial) |
| `resources/views/equipos/partials/panel-equipos.blade.php` | **modificado** | **no se edita** en F1/F2; opcional en F3 (mostrar las 3 piezas en la tarjeta) |
| `resources/views/equipos/partials/modal-editar-equipo.blade.php` | **untracked (otra tarea)** | **prohibido tocar**; si en el futuro debe mostrar piezas, se hace tras aprobación explícita |
| `src/Battle/Domain/CombatienteVista.php` | **modificado** | **no se toca** (el colapso no lo requiere) |
| `resources/css/{app,components,combate,tokens}.css` | **modificados** | F1 usa solo clases existentes; si hace falta un patrón nuevo, va a `components.css` en **F3** y con coordinación |
| `docs/conventions.md` | **modificado** | no se toca; la documentación de TASK-056 va a `src/Forja/context.md` + ADR nuevo |
| `src/Forja/**`, `resources/views/forja/index.blade.php`, `src/Shared/**` | **limpios** | campo de trabajo de F1 sin conflictos |
| `src/CombateEntrenadores/App/*`, `src/Exploraciones/App/*`, `src/Equipos/**`, `app/Support/ReclutadoSerializer.php` | **limpios** | campo de trabajo de F2 |

---

## 10. Fases, decisiones, riesgos y verificación

### 10.1 F1 — Dominio + oferta + fabricación + Forja (end-to-end, **sin tocar `src/Battle` ni `equipos/*`**)

**Archivos**

*Crear*
- `src/Shared/Domain/Items/ClavePieza.php`
- `src/Forja/Domain/Piezas/Enums/TipoPieza.php`
- `src/Forja/Domain/Piezas/DataTransferObjects/{DefinicionPieza,PiezaDesbloqueada,RecetaPieza,NidoConstruido}.php`
- `src/Forja/Domain/Piezas/Collections/{DefinicionesPiezaCollection,RecetasPiezaCollection,NidosConstruidosCollection}.php`
- `src/Forja/Domain/Piezas/{ReglasPiezas,CalculadoraStatsPieza,ResolvedorEtapasPiezas,CatalogoPiezas}.php`
- `src/Forja/Domain/Ports/NidosConstruidos.php`
- `src/Forja/App/{ListarPiezasForja,FabricarPieza}.php`
- `src/Forja/Infra/Repositorios/LectorFamiliasPiezas.php`
- `src/Granjas/Infra/Nidos/NidosConstruidosEloquent.php`
- `src/Forja/Infra/Requests/FabricarPiezaRequest.php` (o reutilizar `FabricarRequest`)

*Modificar*
- `src/Shared/Domain/Items/CategoriaItem.php` (+3 casos)
- `src/Shared/Infra/Inventario/InventarioJugadorEloquent.php` (`esClavePieza()` en `asegurarClaveValida`)
- `src/Forja/App/ListarRecetas.php` (merge con piezas + `secciones` + `categoria`)
- `src/Forja/App/DataTransferObjects/RecetaDetalle.php` (campos aditivos)
- `src/Forja/App/DataTransferObjects/ResultadoFabricacion.php` (`pieza` opcional)
- `src/Forja/Infra/Controllers/ForjaController.php` (`fabricar` enruta a `Fabricar` o `FabricarPieza`)
- `app/Providers/AppServiceProvider.php` (binding del puerto)
- `resources/views/forja/index.blade.php` (sección + `secciones` + `categoria` + motivos)
- `src/Forja/context.md` (nuevo si no existe)

**Criterios de aceptación**
1. `/api/forja/recetas` devuelve `secciones[]` y las 46 recetas existentes **sin cambios de campos ni valores**, más las piezas de familias con nido ≥ 1.
2. Un jugador **sin nidos** recibe `secciones` y **cero** recetas de pieza; la vista muestra el estado vacío.
3. Con nido de la familia Gastly a nivel 1 aparecen `arma:92` y `armadura:92`/`amuleto:92`; la etapa 2/3 figura con `desbloqueada: false` y `motivo: nido_insuficiente`.
4. `POST /api/forja/fabricar` con una receta de pieza descuenta bonguri y recipiente e incrementa `arma:92` en `player_inventory`.
5. Intentar `arma:94` con nido 1 devuelve **422** y no modifica el inventario (verificado por los `saldos` de la respuesta de error y por relectura).
6. Intentar `arma:94` con nido 5 y sin `arma:93` devuelve **422**.
7. La vista no contiene `FORJA_CATEGORIAS` ni `forjaCategoriaDeResultado`, y pinta la sección nueva exclusivamente con datos del backend.
8. `php artisan route:list --path=forja` sigue mostrando 3 rutas.

**Entregable mínimo end-to-end:** se pueden forjar y ver piezas; quedan en inventario a la espera de F2.

### 10.2 F2 — Persistencia, equipar/desequipar y aplicación en batalla (backend)

**Archivos**

*Crear*
- `database/migrations/2026_11_17_000001_create_recruit_gear_table.php`
- `app/Models/RecruitGear.php`
- `src/Equipos/Domain/ValueObjects/EquipamientoPiezas.php`
- `src/Equipos/App/Piezas/{EquiparPieza,DesequiparPieza,ObtenerPiezas}.php`
- `src/Equipos/Infra/Requests/EquiparPiezaRequest.php` (o `app/Http/Requests/`)
- `src/Equipos/Infra/Controllers/PiezasReclutadoController.php` (o en `ReclutadoController`)
- `src/Pokemon/Domain/Stats/AplicadorBonusPiezas.php`

*Modificar*
- `src/CombateEntrenadores/App/MapeadorPokemonBatalla.php` (bonos + `effectKeys`)
- `src/CombateEntrenadores/App/ConstruirEquipoJugador.php` (carga y pasa piezas)
- `src/Exploraciones/App/CombateExploracion.php` (idem)
- `app/Support/ReclutadoSerializer.php` (`piezas[]`)
- `app/Support/Pc/CalculadoraPcReclutado.php` + `PlayerController` (CP con piezas, una consulta) — `[D8]`
- `routes/player.php` (3 rutas nuevas)
- `app/Providers/BattleEffectServiceProvider.php` (registro de pasivas, si aportan claves nuevas)

**Criterios de aceptación**
1. `PUT /api/reclutados/{id}/piezas/1` con `arma:94` descuenta 1 de `player_inventory` y crea la fila; `DELETE` la devuelve.
2. Equipar la misma pieza en dos slots del mismo reclutado → **422** (unique `(reclutado_id, item_key)`).
3. Equipar en un slot ocupado devuelve la anterior al inventario (swap) en la misma transacción.
4. Equipar en un reclutado ajeno → **404**; slot 0 o 4 → **422**.
5. Un combate de ruta con `arma:94` equipada muestra stats base sumados (comprobable en el payload de la batalla y en el daño) **sin** cambios en `DatosPokemonBatalla`.
6. La pasiva del Amuleto de etapa 3 aparece en `effectKeys` y produce su efecto en combate.
7. `SESSION_VERSION` **sigue en 21** y una batalla guardada antes del despliegue sigue cargando.
8. El objeto de combate (`obj_equipados[0]`) sigue funcionando **en paralelo** a las piezas.

**Dependencia:** ninguna sobre F3.

### 10.3 F3 — UI de equipos, CP visible y assets

**Archivos**

*Crear*
- `resources/views/equipos/partials/piezas-reclutado.blade.php`

*Modificar*
- `resources/views/equipos/partials/modal-detalle-pokemon.blade.php` (1 `@include`)
- `resources/css/components.css` (solo si hace falta un patrón visual nuevo)
- `public/images/items/pieza/**` (assets) — opcional

**Criterios de aceptación**
1. El modal de detalle muestra 3 slots con nombre, tipo y stats de la pieza.
2. Equipar/desequipar desde la UI actualiza la tarjeta sin recargar y emite `inventario:actualizado`.
3. El CP mostrado en `/equipos` incluye las piezas (`[D8]`).
4. `resources/views/equipos/index.blade.php` y `partials/modal-editar-equipo.blade.php` **no aparecen** en el diff de la fase.
5. `php artisan view:cache` compila sin errores.

**Dependencia dura:** el desarrollador debe **consolidar o revertir** el WIP de `resources/views/equipos/*` antes de iniciar F3.

### 10.4 Riesgos y mitigaciones

| Riesgo | Nivel | Detalle | Mitigación |
|---|---|---|---|
| **Balance por apilado** | **alto** | Gengar con las 3 piezas suma +195 SpA base (130 → 325); a nivel 50 el stat pasa de ~135 a ~330 | `FACTOR_ETAPA` (0.5/0.75/1.0) + medir con CP antes de aprobar D2; `ReglasPiezas` como único mando |
| **Armadura con doble efecto** | **alto** | Def/SpD suben el **techo** de la barrera y **reducen** el daño (`BattleStats.php:63-70`, `ManejadorDanioBase`) | probar un combate real con Armadura de etapa 3 antes de cerrar D2; considerar factor propio para Armadura si desborda |
| **Whitelist del inventario** | **alto** | sin carve-out, `incrementar('arma:94')` lanza `ItemDesconocido` al final de la transacción (422) | implementar el carve-out **antes** de exponer recetas; F1 lo pone en el primer commit |
| **Conflicto con cambios locales** | **alto** | 40 archivos modificados + untracked de TASK-049…055 | F1 no toca ninguno en conflicto; F2 toca solo archivos limpios; F3 bloqueada hasta consolidar `equipos/*` |
| **Datos: cadenas sin aristas** | **medio** | familia degenera a profundidad 1 → todo a nivel 3 | documentar; si se detecta, corregir `pokemon_evolution` (fuera de alcance) |
| **Volumen de recetas** | **medio** | N familias × 3 tipos × M hábitats puede inflar el payload | solo familias con nido ≥ 1 (subset real del jugador); `saldos` sigue siendo **una** consulta |
| **`ItemCatalogo` desconoce piezas** | **bajo** | listados genéricos de inventario mostrarían "Desconocido" | la Forja y `/equipos` usan su catálogo; si se quiere resolver en genérico, upgrade a puerto `CatalogoPiezas` para el inventario |
| **Pasiva anulable** | **bajo** | supresión de habilidad elimina también la pasiva de pieza | aceptado como semántica "pasiva"; alternativa (registrar en `ClaveItem`) la haría robable e incoherente |
| **Documentación desactualizada** | **bajo** | `src/Granjas/context.md` dice coste de nido 25/75/225/450/900 y el código dice 10/20/30/40/50 (`CalculadoraBonosGranja.php:91-101`) | corregir en el cierre de TASK-056 (Bibliotecario) |

### 10.5 Rollback

| Fase | Rollback |
|---|---|
| F1 | revertir commits: no hay migración ni cambio de esquema → **rollback limpio**; las claves `arma:*` que hubieran quedado en `player_inventory` son inertes y no rompen nada |
| F2 | `php artisan migrate:rollback` de `create_recruit_gear` (drop table); revertir el serializer (el campo `piezas` es aditivo, su ausencia no rompe a nadie) y los 2 call sites. Las batallas en curso **no** se ven afectadas (payload sin cambios) |
| F3 | revertir el partial y el `@include`; sin efecto en datos |

### 10.6 Tabla de decisiones

| # | Decisión | Recomendación | Requiere aprobación |
|---|---|---|---|
| **D1** | Composición del Arma: `{Atk, SpA}` vs `{Atk, SpA, Speed}` | **2 componentes** (fiel al ejemplo Gengar) | **Sí** |
| **D2** | Curva de stats por etapa | **`{1: 0.5, 2: 0.75, 3: 1.0}`** | **Sí** |
| **D3** | Unicidad de pieza | **1 por jugador y clave** (inventario ∪ equipamiento) | **Sí** |
| **D4** | Evolución del portador / restricción especie↔pieza | **La pieza permanece; equipamiento libre sin restricción de especie** | **Sí** |
| **D5** | Persistencia de los 3 slots | **Tabla nueva `recruit_gear`**; `obj_equipados` intacto | **Sí** |
| **D6** | Ingredientes y cantidades | **bonguri ×(1+etapa) + recipiente ×1 + baya rara ×etapa + pieza anterior (etapa ≥ 2)**; sin caramelos de familia | **Sí** |
| **D7** | Qué pieza otorga la pasiva | **Amuleto de etapa 3**, habilidad slot 1 de la especie | **Sí** |
| **D8** | CP con o sin piezas | **Con piezas** (una consulta por listado) | **Sí** |
| **D9** | Whitelist del inventario | **Carve-out por prefijo** en `InventarioJugadorEloquent`, no registro en `CatalogoItems` | **Sí** |
| **D10** | Slot ocupado al equipar | **Swap** (la anterior vuelve al inventario) | **Sí** |
| **D11** | Fila de Forja con varios nidos | **Una receta por nido, una fila por familia**, selector de bioma | **Sí** |
| **D12** | Familias de >3 etapas | **Tope nivel 5** para etapa ≥ 3 | **Sí** |

*Notas no bloqueantes:* nombre de tabla `recruit_gear` vs `reclutado_equipamiento`; `app/Models/RecruitGear.php` vs `src/Equipos/Infra/Models/` (atajo marcado); assets propios de icono en F3 con fallback al icono de especie.

### 10.7 Verificación estática (sin tests)

```bash
# sintaxis de los archivos tocados
find src app -name '*.php' -newer composer.json -print0 | xargs -0 -n1 php -l

# estilo
vendor/bin/pint --dirty --format agent

# análisis estático (no añadir entradas a los baselines)
composer run analyse        # PHPStan L6 + phpstan-baseline
composer run analyse:hard   # PHPStan L8 + phpstan-hardener-baseline

# arquitectura (report-only: no debe aumentar el recuento de violaciones)
composer run arch

# vistas y assets
php artisan view:cache
npm run build

# rutas
php artisan route:list --path=forja
php artisan route:list --path=piezas
```

Precauciones específicas:
- Los baselines (`phpstan-baseline.php` L6, `phpstan-hardener-baseline.php` L8) están congelados: **no se añaden entradas nuevas**; si aparece un error en código nuevo, se corrige, no se baselinea.
- `deptrac` es **report-only** con ~112 violaciones preexistentes: el criterio es "no aumentar", no "cero". El nuevo adaptador `Granjas/Infra → Forja/Domain/Ports` es una arista Infra→Domain admitida (mismo caso que `ConsultorPlantaciones`); si deptrac la marca, se añade al colector correspondiente.
- **No** se ejecutan tests (política del proyecto) ni migraciones ni seeders.

### 10.8 Borrador de ADR

```markdown
# ADR-0005 — Piezas de forja por familia y nido

- Estado: propuesto
- Fecha: 2026-11-17
- Tarea: TASK-056
- Relacionado: ADR-0004 (tema visual), `src/Forja/context.md`, `src/Granjas/context.md`

## Contexto

La Forja es estática: `CatalogoRecetas` genera 46 recetas en memoria y
`CatalogoItems` es la whitelist del inventario (`player_inventory`, contador por
`item_key`). El cliente pide Armas/Armaduras/Amuletos para las familias con nido
construido, desbloqueadas por nivel de nido (1/3/5 · 2/4 · 3), con una pieza única
que evoluciona consumiendo la anterior, hasta 3 equipadas por Pokémon, con stats
que replican la especie y habilidades pasivas.

Las restricciones del código actual son: (a) el inventario no tiene instancias;
(b) `reclutados.obj_equipados` es una lista de la que **todo** el código lee el
índice 0, con 62 referencias en tests; (c) el motor soporta **un** objeto por
combatiente (`ObjetoCombatiente`, robo/intercambio/reciclado) y `FabricaEfectos::crearItem`
devuelve un solo efecto; (d) `ResolvedorCadenasEvolutivas` fuerza una sola raíz,
máximo 3 etapas y fallback `stage = 3`, incompatible con la regla pedida y con
familias ramificadas.

## Decisión

1. **Identidad**: la pieza es una clave de inventario `tipo:{species_id}`. La
   evolución es una cadena de recetas que consume la clave anterior. Sin
   instancias, sin nivel de pieza: la etapa es la clave.
2. **Dominio**: `src/Forja/Domain/Piezas/` con `TipoPieza`, `DefinicionPieza`,
   `ReglasPiezas`, `CalculadoraStatsPieza`, `ResolvedorEtapasPiezas` y
   `CatalogoPiezas` generativo. `CatalogoRecetas` y `CatalogoItems` no se modifican
   salvo `CategoriaItem` (+3 casos) y el carve-out de whitelist del inventario.
3. **Desbloqueo**: `ResolvedorFamiliaEvolutiva` con `multiRaiz: true` y
   `maxEtapas: null`; niveles `3` (profundidad 1), `2×etapa` (profundidad 2),
   `min(5, 2×etapa−1)` (profundidad ≥ 3).
4. **Stats**: bono plano sobre `StatsValue` base antes de `BattleStats`, con
   `FACTOR_ETAPA = {1: 0.5, 2: 0.75, 3: 1.0}`. Arma = Atk + SpA; Armadura = Def +
   SpD + HP; Amuleto = Speed + 50 % del mayor de Atk/Def/SpA/SpD, redondeo `floor`.
5. **Equipamiento**: tabla `recruit_gear(reclutado_id, slot 1-3, item_key)` con
   `unique(reclutado_id, slot)` y `unique(reclutado_id, item_key)`; equipar consume
   del inventario, desequipar lo devuelve, slot ocupado hace swap.
   `obj_equipados` queda como objeto de combate único, sin cambios.
6. **Batalla**: el equipamiento se **colapsa en el mapeo**
   (`MapeadorPokemonBatalla::desdePokemon` + `AplicadorBonusPiezas` + `effectKeys`).
   `DatosPokemonBatalla`, `ObjetoCombatiente`, `FabricaEfectos` y
   `ManejadorObjetosEquipados` no cambian; `SESSION_VERSION` no se incrementa.
7. **Frontera Forja↔Granjas**: puerto `NidosConstruidos` en `Forja/Domain/Ports`,
   adaptador en `Granjas/Infra`, binding en `AppServiceProvider` (patrón
   `ConsultorPlantaciones`).

## Consecuencias

**Positivas**
- Sin migración de datos ni cambios en el motor de batalla (riesgo de regresión mínimo).
- Los ~62 usos de `obj_equipados` y los 5 modos de combate quedan intactos.
- Una sola fuente de verdad para stats (la especie) y para gates (el nido).
- El frontend deja de duplicar categorías y motivos.

**Negativas / costes**
- No hay instancias: no se puede rastrear "la" pieza concreta ni transferir estado propio.
- Los baselines de PHPStan quedan congelados: el código nuevo debe nacer limpio en L8.
- `Forja/Domain` pasa a depender de `Pokemon/Domain/Evolution` (Domain→Domain), deuda
  ya existente en Battle; se registra en `docs/ddd.md`.
- Una cadena sin aristas degrada a profundidad 1 (todo nivel 3): comportamiento seguro
  pero potencialmente confuso; requiere verificación de datos.

**Alternativas descartadas**
- Tabla de instancias de pieza (coste alto, beneficio nulo para el caso de uso).
- Extender `obj_equipados` a lista de 3 (rompe 62 referencias de test y 3 lecturas de `[0]`).
- `ResolvedorCadenasEvolutivas` (rompe Eevee, trunca a 3 etapas, fallback peligroso).
- Registrar las piezas en `CatalogoItems` (Domain puro sin BD; ~450 claves derivadas de datos).
- Multiplicadores en `StatsCombatiente` (no afectan barreras; incoherente con "replicar stats").
- Extender `FabricaEfectos::crearItem` a varios efectos y `ObjetoCombatiente` a 3 slots
  (toca el motor y la mecánica de robo para un caso que no lo necesita).

## Seguimiento

- `src/Forja/context.md` y `src/Granjas/context.md` actualizados al cierre.
- Coste real de nido documentado (10/20/30/40/50), corrigiendo el 25/75/225/450/900 de los contextos.
- Revisar el balance con sets completos antes de cerrar D1/D2.
- Migrar `RecruitGear` a `src/Equipos/Infra/Models/` cuando se migre el módulo (atajo registrado).
```

---

## Anexo — Evidencia consultada (sin modificaciones)

**Lectura directa:** `.opencode/work/TASK-056/{request,analysis}.md`; `src/Forja/{Domain/CatalogoRecetas.php, Domain/RecetaFabricacion.php, App/ListarRecetas.php, App/Fabricar.php, App/DataTransferObjects/RecetaDetalle.php, Infra/Controllers/ForjaController.php}`; `src/Shared/Domain/Items/{CatalogoItems,CategoriaItem,DefinicionItem,ClaveBonguri,ClaveMaterial,ClaveMejora}.php`; `src/Shared/Domain/Ports/InventarioJugador.php`; `src/Shared/Infra/Inventario/InventarioJugadorEloquent.php`; `src/Shared/{Domain/ValueObjects/MapaConteo.php, Collections/StringCollection.php}`; `src/Habitats/Domain/ResolvedorCadenasEvolutivas.php`; `src/Habitats/Presentation/DTOFamiliaDisponible.php`; `src/Pokemon/Domain/Evolution/{ResolvedorFamiliaEvolutiva,FamiliaEvolutiva,MiembroFamiliaEvolutiva}.php`; `src/Pokemon/Domain/Stats/{BattleStats,StatsValue,CalculadorCP,DatosStats}.php`; `src/Battle/Domain/{DatosPokemonBatalla,EquipoBatalla,Combatiente}.php`; `src/Battle/Domain/Componentes/Combatiente/{ObjetoCombatiente,DelegacionObjetoCombatiente,HabilidadCombatiente,StatsCombatiente,SerializacionCombatiente,SerializadorCombatiente,VitalidadCombatiente}.php`; `src/Battle/Domain/Effects/FabricaEfectos.php`; `src/Battle/Domain/Chain/ManejadorObjetosEquipados.php`; `src/Battle/Domain/Catalog/{CatalogoObjetos,CatalogoHabilidades}.php`; `src/Battle/Domain/Enums/ClaveItem.php`; `src/CombateEntrenadores/App/{ConstruirEquipoJugador,MapeadorPokemonBatalla}.php`; `src/Exploraciones/App/CombateExploracion.php`; `src/Exploraciones/Domain/Ports/ConsultorPlantaciones.php`; `src/Granjas/App/{ObtenerGranja,CosecharTodo}.php`; `src/Granjas/Domain/DataTransferObjects/{GranjaDetalle,NidoGranja,FamiliaGranja,BonguriGranja}.php`; `src/Granjas/Domain/CalculadoraBonosGranja.php`; `src/Granjas/Infra/Plantaciones/ConsultorPlantacionesEloquent.php`; `src/Granjas/context.md`; `src/Equipos/App/ActualizarObjetosEquipo.php`; `src/Equipos/Domain/ValueObjects/ObjetosEquipo.php`; `app/Models/{Nido,Granja,Reclutado,Ability,Pokemon}.php`; `app/Support/{ReclutadoSerializer,EquiposVistaPresenter,ItemCatalogo}.php`; `app/Support/Pc/{CalculadoraPcReclutado,EstadisticasBasePokemon}.php`; `app/Http/Controllers/{TeamController,PlayerController}.php`; `app/Enums/StatEnum.php`; `app/Providers/AppServiceProvider.php`; `bootstrap/app.php`; `routes/{web,forja}.php`; migraciones `2026_08_29_000007_create_player_inventory_table`, `2026_10_26_000001_create_farm_tables`, `2026_02_24_171456_create_pokemon_table`, `2026_02_24_171459_create_pokemon_stats_table`, `2026_10_22_000004_create_ability_pokemon_table`; `resources/views/forja/index.blade.php`; `resources/views/equipos/index.blade.php` (extracto); `resources/views/equipos/partials/{panel-equipos,modal-editar-equipo}.blade.php`; `.ai/rules/index.md`; `docs/{ddd,conventions}.md`.

**Comandos de solo lectura ejecutados:** `git status --short`, `git branch --show-current`, `git diff --stat`, `ls`, `grep`, `sed`, `cat`.

**No ejecutado:** tests, migraciones, seeders, `artisan` de escritura, `npm`, lectura de `.env`. **No modificado:** ningún archivo del proyecto.

**Problemas detectados durante la verificación:**
1. `src/Granjas/context.md` documenta coste de nido 25/75/225/450/900; el código devuelve 10/20/30/40/50 (`CalculadoraBonosGranja.php:91-101`).
2. `docs/context.md` afirma `SESSION_VERSION=11`; el código tiene 21 (`BattleSessionService.php:50`).
3. `ClaveBonguri::origen()` devuelve `'Andalucía'` con tilde frente a `'Andalucia'` del seeder (solo presentación).
4. `CatalogoItems` no es ampliable con claves derivadas de BD: obliga a D9.
5. `ResolvedorCadenasEvolutivas::levelForStage()` devuelve `2` para familias de 1 etapa y `min(stage,3)` en el resto: no reutilizable.

6. Coexisten tres representaciones de "objeto equipable" (`CatalogoItems::equipable`, `CatalogoObjetos` + `ClaveItem`, `obj_equipados` sin validación de dominio). TASK-056 **no** las unifica (fuera de alcance) y añade una cuarta noción acotada (`recruit_gear`); la deuda queda registrada para una tarea de unificación.
