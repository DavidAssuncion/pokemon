# TASK-055 — Arquitectura

> Resultado de `architect` (sesión `ses_e14ac3f3effe5ma5KofWj1CFAm`). Se reproduce sin
> alterar sus conclusiones.

## DECISIÓN

Se introduce un **multiplicador de efecto permanente** separado del sistema de etapas:

- `BattleStats` acepta `multiplicadorStats` (por defecto `1.0`) y lo aplica **después** de
  calcular las estadísticas de combate, **solo** a ataque, defensa, ataque especial, defensa
  especial y velocidad, y también a `defenseHp`/`spDefenseHp`. **HP no se multiplica**.
- `DatosPokemonBatalla` transporta `multiplicadorStatsJefe` (por defecto `1.0`) como nuevo
  campo del contrato de entrada. `JefeMazmorra` envía `3.0`.
- `EquipoBatalla::fromData` conecta el campo del DTO con `BattleStats`.
- El generador deja de aplicar `×10` a los stats base y deja de enviar `bonusInicialStats`
  y `puntosDebiles`.
- `JefeEstado` deja de crear un Núcleo implícito cuando la lista de partes está vacía; la
  infraestructura de partes se conserva.
- La UI deriva `+200 %` de `BattleStats::multiplicadorStats()` y lo pinta como badge dentro
  del bloque de jefe.
- **No se cambia `SESSION_VERSION`**: el nuevo dato es opcional y tiene valor por defecto,
  por lo que los payloads antiguos siguen cargando.

La razón central: el ×3 debe ser un efecto sobre las **estadísticas efectivas**, no una
etapa. `MultiplicadoresStats` queda intacto y neutral para el jefe, así que
`bonificacionesPositivas()`, Inversión, Psicocarga, Castigo, Poder Reserva y Canto
Encantador no lo ven.

---

## CONTEXTO

Hallazgos verificados en el código:

- `src/Mazmorras/Domain/ReglasMazmorra.php:15-22`: `MULTIPLICADOR_STATS_JEFE=10`,
  `MULTIPLICADOR_HP_JEFE=7`, `BONUS_INICIAL_STATS_JEFE=30.0`.
- `src/Mazmorras/App/GeneradorJefeMazmorra.php:52-57`: HP ×7 y stats ×10 sobre base de
  especie.
- `src/Mazmorras/App/GeneradorJefeMazmorra.php:72`: `bonusInicialStats: 30.0`.
- `src/Mazmorras/App/GeneradorJefeMazmorra.php:78-81`: `puntosDebiles` con Núcleo/Coraza.
- `src/Battle/Domain/EquipoBatalla.php:175-177`: construye `BattleStats`; `:222-228`
  configura jefe y aplica `bonusInicialStats`.
- `src/Battle/Domain/Componentes/Combatiente/StatsCombatiente.php:113-122`:
  `aplicarBonusInicialStats` itera **todos** los `StatClave`, incluidos precisión y evasión.
- `src/Battle/Domain/ValueObjects/MultiplicadoresStats.php:23`: límite superior `4.0`;
  `:325-332` convierte factor `3.0` en +4 etapas.
- `src/Pokemon/Domain/Stats/BattleStats.php:54-71`: `defenseHp`/`spDefenseHp` se calculan
  con la defensa/defensa especial base, sin multiplicadores.
- `src/Battle/Domain/ValueObjects/JefeEstado.php:112-114`: fallback que crea `Núcleo` con
  `hpMax*0.2`.
- `src/Battle/Domain/CombatienteVista.php:82-84,127-129,205-226`: etapas, jefe y partes.
- `resources/views/livewire/_pokemon-card.blade.php:94-108`: badges de jefe/fases/partes;
  `:139-143` badges de etapas.
- `app/Support/BattleSessionService.php:50`: `SESSION_VERSION=21`; `:93-103` acepta
  payloads desde la versión 9.

---

## OPCIONES EVALUADAS

1. **Nuevo campo en `DatosPokemonBatalla` + multiplicador en `BattleStats` (elegida).**
   - Separa explícitamente “base de especie ×1” de “efecto permanente ×3”.
   - Las barreras se escalan con la defensa efectiva.
   - El valor sobrevive en sesión dentro de `BattleStats` y sirve para la UI sin plumbing
     extra.
   - Coste: un parámetro opcional más en `BattleStats` y un campo más en el DTO.

2. **Reutilizar `bonusInicialStats` cambiando su semántica (rechazada).**
   - El nombre mentiría: hoy significa “bonus porcentual aplicado como capa base de
     `MultiplicadoresStats`”.
   - Rompería el diseño previsto de otros modos (TASK-044 usa `bonusInicialStats +20 %`
     como bonus inicial).
   - Si se aplica por la vía de etapas, incumple el requisito de “no etapas”; si se cambia
     la vía, el campo queda con semántica ambigua.

3. **Hornear el ×3 en `GeneradorJefeMazmorra` y añadir un campo solo visual (rechazada).**
   - Las barreras se podrían calcular bien si se multiplica también `def`, pero el DTO
     dejaría de reflejar “base ×1”.
   - Habría que transportar el +200 % por otro canal (`JefeEstado` o `Combatiente`) y lidiar
     con su serialización.
   - Duplica la fuente de verdad: los stats ya vendrían ×3 y además existiría un indicador
     visual.

4. **Escalar `StatsValue` en `EquipoBatalla::fromData` antes de construir `BattleStats`
   (alternativa viable, no elegida).**
   - Funciona para stats y barreras, pero `PokemonEntity::stats()` dejaría de ser la base de
     especie ×1.
   - Para el indicador visual haría falta otro campo persistido, con el mismo problema de
     serialización que la opción 1 sin la ventaja de tener el multiplicador en
     `BattleStats`.

---

## DISEÑO ELEGIDO

### Semántica

- **Base de especie**: `DatosPokemonBatalla.hp/atk/def/spAtk/spDef/speed` siguen siendo los
  valores de especie, sin ×10 en los cinco stats.
- **Vida**: el generador sigue aplicando `hp * MULTIPLICADOR_HP_JEFE` (`10`).
- **Efecto permanente**: `multiplicadorStats = 3.0` sobre los cinco stats de combate y sobre
  las barreras.
- **No es etapa**: no se toca `MultiplicadoresStats`; el jefe conserva todos sus factores
  en `1.0` y `bonificacionesPositivas() === 0`.

### Pseudocódigo de `BattleStats`

```php
public function __construct(
    StatsValue $stats,
    StatsValue $evs,
    int $nivel = 100,
    ?StatsValue $ivs = null,
    public float $multiplicadorStats = 1.0,
) { ... }

private function calcularStats(StatsValue $stats, StatsValue $evs, int $nivel, StatsValue $ivs): void
{
    $escala = max(0.0, $this->multiplicadorStats);

    $this->hp = $this->calcularHp($stats->hp, $evs->hp, $nivel, $hpIv);

    $this->defenseHp = $this->calcularHp($stats->defense, $evs->defense, $nivel, $defIv) * $escala;
    $this->spDefenseHp = $this->calcularHp($stats->spDef, $evs->spDef, $nivel, $spDefIv) * $escala;

    $this->attack = $this->calcularStat($stats->attack, $evs->attack, $nivel, $atkIv) * $escala;
    $this->defense = $this->calcularStat($stats->defense, $evs->defense, $nivel, $defIv) * $escala;
    $this->spAtk = $this->calcularStat($stats->spAtk, $evs->spAtk, $nivel, $spAtkIv) * $escala;
    $this->spDef = $this->calcularStat($stats->spDef, $evs->spDef, $nivel, $spDefIv) * $escala;
    $this->speed = $this->calcularStat($stats->speed, $evs->speed, $nivel, $speedIv) * $escala;
}

public function multiplicadorStats(): float
{
    return $this->multiplicadorStats;
}
```

El multiplicador se aplica **después** de la fórmula de nivel para que el efecto sea
exactamente ×3 respecto a la misma especie sin efecto. Esto es lo que hace que “equivalente
a +200 %” sea verificable. `hp` queda fuera.

> Nota: si producto prefiere que la fórmula HP de la barrera reciba directamente la defensa
> ×3 (`calcularHp(3 * baseDef)`), el cambio es de una línea; el resultado difiere ligeramente
> por el término `+nivel+10`. Se deja como pregunta abierta.

---

## MAPA DE CAPAS

- **Dominio (`src/`)**:
  - `Src\Pokemon\Domain\Stats\BattleStats`: cálculo de stats efectivos y barreras; nuevo
    multiplicador opcional.
  - `Src\Battle\Domain\DatosPokemonBatalla`: contrato de entrada; nuevo valor
    `multiplicadorStatsJefe`.
  - `Src\Battle\Domain\ValueObjects\JefeEstado`: partes del jefe; se elimina el fallback.
  - `Src\Battle\Domain\CombatienteVista`: campo derivado para la vista.
  - `Src\Battle\Domain\ValueObjects\MultiplicadoresStats`: **sin cambios**.
- **Aplicación (`src/Mazmorras/App`, `src/Battle/Domain`)**:
  - `GeneradorJefeMazmorra`: traduce reglas de producto a `DatosPokemonBatalla`.
  - `EquipoBatalla::fromData`: composición de `BattleStats` a partir del DTO.
- **Infraestructura**:
  - Sin cambios. La persistencia es la sesión actual (`serialize`/`unserialize`).
- **Presentación**:
  - `CombatienteVista` (mapper de dominio existente) y `_pokemon-card.blade.php`.

---

## CONTRATOS

### Interfaces
- Ninguna nueva.

### DTOs
- `DatosPokemonBatalla`:
  - Nueva propiedad privada `float $multiplicadorStatsJefe = 1.0` y parámetro opcional al
    final del constructor.
  - Getter `multiplicadorStatsJefe(): float`.
  - `conNivel()` conserva el valor.
  - Se elige propiedad privada con getter (no `readonly` promocionado) para que un payload
    antiguo sin la clave cargue con `1.0` sin `Error` de propiedad no inicializada. La
    alternativa `readonly` + `__wakeup` también es válida, pero añade complejidad de
    serialización.
- `BattleStats`:
  - Parámetro promocionado `public float $multiplicadorStats = 1.0`.
  - Getter `multiplicadorStats(): float`.
  - Propiedad con valor por defecto: los `BattleStats` serializados antiguos cargan con
    `1.0`.

### Value Objects
- Ninguno nuevo.
- `MultiplicadoresStats` no se modifica: sigue siendo el contrato de etapas y temporales.

### Excepciones
- Ninguna nueva. El multiplicador se normaliza con `max(0.0, ...)`.

---

## FLUJO DE DATOS

1. `IniciarCombateMazmorra` solicita el jefe a `GeneradorJefeMazmorra`.
2. El generador lee los stats base de especie (`MapeadorPokemonBatalla::statsDe`).
3. Construye `DatosPokemonBatalla` con:
   - `hp = baseHp * 10`,
   - `atk/def/spAtk/spDef/speed = base` (×1),
   - `multiplicadorStatsJefe = 3.0`,
   - `esJefe = true`, fases intactas,
   - sin `bonusInicialStats`,
   - sin `puntosDebiles`.
4. `CreadorBatallaSesion` → `EquipoBatalla::fromData`.
5. `fromData` construye `StatsValue` base y `BattleStats(..., multiplicadorStats: 3.0)`.
6. `BattleStats` calcula HP sin escalar y aplica ×3 a los cinco stats y a las dos barreras.
7. `Combatiente` con `PokemonEntity`/`BattleStats`; `JefeEstado` con `partes = []`.
8. `CombatienteVista::para()` expone `bonusStatsJefe = 200`.
9. La tarjeta pinta el badge `+200 %` dentro del bloque `@if($p['esJefe'])`.
10. La batalla se serializa en sesión; `BattleStats` guarda `multiplicadorStats = 3.0`.
    Payloads antiguos lo reconstruyen a `1.0`.

---

## PLAN DE ARCHIVOS (cambios por archivo y orden)

### 1. `src/Pokemon/Domain/Stats/BattleStats.php`
- `BattleStats.php:32-42`: añadir `public float $multiplicadorStats = 1.0` al constructor.
- `BattleStats.php:54-71`: aplicar `$escala` a los cinco stats y a `defenseHp`/`spDefenseHp`
  después de las fórmulas; no aplicar a `hp`.
- `BattleStats.php:44-52`: añadir `multiplicadorStats(): float`.
- Docblock: explicar que es un efecto permanente, no una etapa, y que HP queda fuera.

### 2. `src/Battle/Domain/DatosPokemonBatalla.php`
- `DatosPokemonBatalla.php:26-59`: añadir parámetro `float $multiplicadorStatsJefe = 1.0`,
  propiedad privada y asignación.
- Añadir getter `multiplicadorStatsJefe(): float`.
- `DatosPokemonBatalla.php:79-110`: `conNivel()` debe propagar `multiplicadorStatsJefe`.
- Docblock del campo: “multiplicador permanente de combate; no es una etapa; no afecta HP”.

### 3. `src/Battle/Domain/EquipoBatalla.php`
- `EquipoBatalla.php:175-177`: construir siempre `BattleStats` con
  `nivel: $member->nivel ?? 100` y `multiplicadorStats: $member->multiplicadorStatsJefe()`.
- `EquipoBatalla.php:222-228`: mantener `configurarJefe(...)`; el bloque de
  `bonusInicialStats` se conserva para otros modos, pero el generador de mazmorra ya no lo
  envía.

### 4. `src/Mazmorras/Domain/ReglasMazmorra.php`
- `ReglasMazmorra.php:15-22`:
  - `MULTIPLICADOR_HP_JEFE = 10`.
  - Eliminar `MULTIPLICADOR_STATS_JEFE = 10`.
  - Añadir `MULTIPLICADOR_STATS_EFECTIVO_JEFE = 3.0` (efecto permanente ×3, no etapa).
  - Eliminar `BONUS_INICIAL_STATS_JEFE`.
- Actualizar comentarios.

### 5. `src/Mazmorras/App/GeneradorJefeMazmorra.php`
- `GeneradorJefeMazmorra.php:14-18`: actualizar docblock (“vida ×10, stats base ×1, efecto
  ×3 no-etapa”).
- `GeneradorJefeMazmorra.php:52-57`: HP ×10; el resto sin multiplicar.
- `GeneradorJefeMazmorra.php:72`: sustituir `bonusInicialStats` por
  `multiplicadorStatsJefe: ReglasMazmorra::MULTIPLICADOR_STATS_EFECTIVO_JEFE`.
- `GeneradorJefeMazmorra.php:78-81`: eliminar `puntosDebiles`.

### 6. `src/Battle/Domain/ValueObjects/JefeEstado.php`
- `JefeEstado.php:112-114`: eliminar el fallback que crea `ParteJefe('nucleo', 'Núcleo', ...)`.
- Docblock de `normalizarPartes`: lista vacía = jefe sin puntos débiles; no se crea ninguna
  parte implícita.
- No tocar `ParteJefe`, `PartesJefeCollection`, `JefeCombatiente` ni
  `daniarParte`/`tienePartesRotas`.

### 7. `src/Battle/Domain/CombatienteVista.php`
- `CombatienteVista.php:127-129`: añadir una clave derivada:
  ```php
  'bonusStatsJefe' => (int) round(($battleStats->multiplicadorStats() - 1.0) * 100),
  ```
- No tocar `stages`, `stagesDetalle`, `buffTurns` ni las líneas de postura/clases ajenas.

### 8. `resources/views/livewire/_pokemon-card.blade.php`
- Dentro de `@if($p['esJefe'])` (`:94-108`), añadir un badge de 3 líneas:
  ```blade
  @if(($p['bonusStatsJefe'] ?? 0) > 0)
      <span class="badge bg-warning-soft text-warning-ink"
            title="Estadísticas de combate mejoradas de forma permanente">
          +{{ $p['bonusStatsJefe'] }} %
      </span>
  @endif
  ```
- No tocar `wire:key` (`:3`), el bloque de `stagesDetalle` (`:139-143`) ni el resto de
  badges.

### 9. Documentación
- `src/Mazmorras/context.md:17,25,38-42,90-92,108-109`.
- `src/Mazmorras/README.md:11,19`.
- `src/Battle/context.md:1005-1010`.
- `docs/context.md:35,206,219-220`.
- `docs/testing.md:229`.
- Opcional: `src/Mazmorras/App/IniciarCombateMazmorra.php:26` (docblock “jefe ×10”).
- En todos: “vida ×10, stats base ×1, efecto permanente ×3 (+200 %), barreras sobre defensa
  ×3, sin Núcleo/Coraza; `bonusInicialStats` queda para otros modos”.

### Crear / eliminar
- **Crear**: ninguno.
- **Eliminar**: ninguno.

---

## CÁLCULO DE BARRERAS

`BattleStats` calcula actualmente:

```php
$this->defenseHp = $this->calcularHp($stats->defense, $evs->defense, $nivel, $defIv);
$this->spDefenseHp = $this->calcularHp($stats->spDef, $evs->spDef, $nivel, $spDefIv);
```

Con el cambio:

```php
$this->defenseHp = $this->calcularHp(...) * $escala;
$this->spDefenseHp = $this->calcularHp(...) * $escala;
```

Consecuencia:

- La barra física y la especial máximas son **3×** las de la misma especie sin efecto.
- `VitalidadCombatiente` inicializa `defensaHpActual`/`defensaEspHpActual` desde
  `battleStats()->defenseHp`, así que arrancan llenas sobre el nuevo máximo.
- `EfectoRegeneracionDefensa`, `EfectoDanioFinTurno`, `EfectoTrampa`,
  `ProcesadorCondicionesRonda` y la IA usan el mismo `defenseHp`, por lo que heredan el ×3
  sin cambios adicionales.
- HP no se toca: sigue siendo `baseHp × 10` antes de la fórmula.

**Alternativa**: calcular `defenseHp = calcularHp($stats->defense * $escala, ...)`. Es una
línea distinta y da un valor ligeramente inferior en niveles bajos por el término `+nivel+10`.
Se recomienda la versión post-fórmula porque hace que “×3” sea exacto y verificable; se deja
como pregunta de producto.

---

## PRESENTACIÓN UI

- `CombatienteVista` ya concentra los derivados de vista; se añade `bonusStatsJefe` sin tocar
  el contrato de etapas.
- El badge se pinta **solo** dentro de `@if($p['esJefe'])`, junto a `JEFE`, fase y `ENRAGE`.
- No se reutiliza `stagesDetalle`: el +200 % no debe aparecer como `+4 etapas` ni como badge
  de etapa.
- Se usan clases Bootstrap ya existentes (`bg-warning-soft text-warning-ink`), sin CSS nuevo.
- El diff en `CombatienteVista` y en el Blade queda limitado a las líneas nuevas; no se
  reformatean las líneas ajenas.

---

## IMPACTO EN SERIALIZACIÓN

- **Sesión de batalla (`AgregadoBatalla`)**: `BattleStats` gana una propiedad con valor por
  defecto `1.0`. Los payloads v9–v21 la reconstruyen a `1.0`; los nuevos guardan `3.0`.
  **No hace falta `SESSION_VERSION` 22 ni migración.**
- **`DatosPokemonBatalla`**: también se serializa en `AlmacenRivalRutaFijado`
  (`app/Support/CombateRuta/AlmacenRivalRutaFijado.php:64-76,104`) para previews de ruta.
  Por eso el campo se implementa como propiedad privada con default + getter, de modo que un
  payload antiguo sin la clave cargue con `1.0`. Si se optara por `readonly` promocionado,
  haría falta `__wakeup` o subir `AlmacenRivalRutaFijado::VERSION` a 2.
- **Sesiones vivas de mazmorra**: conservan el jefe antiguo (×7/×10/+30 %, con
  Núcleo/Coraza) porque sus `BattleStats`/`JefeEstado` ya están materializados. No se
  migran; el nuevo balance aplica al iniciar un combate nuevo. Se documenta como
  comportamiento esperado.
- **Payloads nuevos leídos por código viejo**: no es un escenario soportado; no se diseña
  compatibilidad hacia atrás de código.

---

## BASE DE DATOS

- **Tablas**: ninguna.
- **Constraints**: n/a.
- **Índices**: n/a.
- **Migraciones**: ninguna.
- **Transacciones**: n/a.

---

## VERIFICACIÓN

### Análisis estático
- `vendor/bin/phpstan analyse` (o `composer run analyse`).
- `vendor/bin/pint --dirty --format agent` en los PHP modificados.
- `php -l` sobre los archivos tocados si se quiere una comprobación rápida.
- **No ejecutar tests** (política del proyecto).

### Criterios manuales
1. **Vida ×10**: en una batalla nueva, `battleStats()->hp` coincide con `BattleStats` usando
   `baseHp * 10`.
2. **Base ×1**: `pokemon()->stats()` del jefe coincide con los `base_stat` de especie; no hay
   ×3 ni ×10 en el DTO.
3. **×3 efectivo**: `battleStats()->attack/defense/spAtk/spDef/speed` son exactamente `3 ×`
   los de un `BattleStats` equivalente con `multiplicadorStats: 1.0`.
4. **Barreras**: `defenseHp` y `spDefenseHp` son exactamente `3 ×` las de un `BattleStats`
   equivalente con multiplicador `1.0`; `hp` no cambia.
5. **Sin etapas**: `multiplicadores()->obtenerEtapas()` devuelve todo `0`;
   `bonificacionesPositivas() === 0`; `buffTurns === 0`; `stagesDetalle` no incluye al jefe.
6. **Sin interacciones**: tras usar Inversión/Psicocarga sobre el jefe, sus
   `MultiplicadoresStats` siguen neutrales; Castigo/Poder Reserva no reciben bonus por
   `objetivo_con_boost`; Canto Encantador no garantiza confusión.
7. **Sin Núcleo/Coraza**: `jefe()->partes()` está vacío; `CombatienteVista::para(...)['jefePartes'] === []`;
   la tarjeta no pinta badges de partes.
8. **Indicador**: `CombatienteVista::para(...)['bonusStatsJefe'] === 200`; la tarjeta muestra
   `+200 %`.
9. **Persistencia**: guardar y recargar la batalla conserva
   `BattleStats::multiplicadorStats() === 3.0`; un payload v21 antiguo carga sin error y con
   `1.0`.
10. **Regresión de otros modos**: un Pokémon normal (sin multiplicador) genera exactamente
    los mismos `BattleStats` que antes.

### Riesgos no cubiertos
- La fórmula exacta de barreras (post-fórmula vs. pre-fórmula) no está fijada por producto.
- El equilibrio de dificultad (HP ×10 + efecto ×3) no se valida con datos de juego.

---

## RIESGOS Y MITIGACIONES

| Riesgo | Mitigación |
|---|---|
| `tests/Feature/Mazmorras/MazmorraApiTest.php:120-135` hardcodea stats ×7/×10 y factor 1.3; fallará. | Fuera del alcance: no se modifica. Se registra como test preexistente a actualizar al cierre del módulo. |
| Sesiones vivas de mazmorra conservan el jefe antiguo. | Documentado; el nuevo balance aplica al iniciar combate. No se invalida la sesión. |
| Payloads antiguos de `DatosPokemonBatalla` (previews de ruta) sin el campo nuevo. | Propiedad privada con default `1.0` + getter; alternativa `readonly` + `__wakeup`. |
| Payloads antiguos de `BattleStats` sin `multiplicadorStats`. | Propiedad con default `1.0`; verificado que `unserialize` aplica el default. Sin bump de versión. |
| UI con cambios locales ajenos (`wire:key`, clases de postura). | Añadir solo líneas nuevas aisladas; no reformatear ni tocar líneas ajenas. |
| Doble aplicación del ×3. | El generador no multiplica stats; solo envía `multiplicadorStatsJefe = 3.0`. No se envía `bonusInicialStats`. |
| Interacción accidental con etapas. | `MultiplicadoresStats` no se toca; la UI no usa `stagesDetalle` para el ×3. |
| `bonusInicialStats` sigue aplicando precisión/evasión en otros modos. | Fuera de alcance; se documenta. Si se quiere, corregir en tarea aparte limitándolo a los cinco stats. |
| `JefeEstado` con cero partes. | `daniarParte` devuelve `false`, `tienePartesRotas` `false`, `partes()` vacío y el Blade itera vacío. Verificado por lectura. |
| Cambio de HP ×7→×10 altera tiempo de combate y recompensas. | Es la decisión de producto; no se toca la recompensa (sigue 7× gimnasio). |
| `BattleStats` es usado por CP y previews; el nuevo multiplicador podría afectarles. | Default `1.0`; solo el generador de mazmorra pasa `3.0`. |

---

## CRITERIOS DE ACEPTACIÓN

- [ ] Vida del jefe = base de especie ×10.
- [ ] Stats base del jefe = base de especie ×1 (sin ×10).
- [ ] Ataque, defensa, ataque especial, defensa especial y velocidad efectivos = ×3.
- [ ] `bonificacionesPositivas()` del jefe = 0; Inversión/Psicocarga/Castigo/Poder
      Reserva/Canto Encantador no interactúan con el ×3.
- [ ] `defenseHp` y `spDefenseHp` reflejan la defensa efectiva ×3.
- [ ] Cero Núcleo/Coraza en jefes de mazmorra; `JefeEstado` no crea partes implícitas.
- [ ] Infraestructura de partes (`JefeEstado`, `JefeCombatiente`, `ParteJefe`,
      `PartesJefeCollection`) intacta para la futura habilidad.
- [ ] La tarjeta del jefe muestra `+200 %` como indicador visual, no como etapa.
- [ ] No se crean ni modifican tests.
- [ ] No se añaden dependencias.

---

## ARCHIVOS QUE NO DEBEN TOCARSE

- `tests/**`, en especial `tests/Feature/Mazmorras/MazmorraApiTest.php`.
- `app/Support/BattleSessionService.php`: solo verificación; no bump de `SESSION_VERSION`.
- `src/Battle/Domain/ValueObjects/MultiplicadoresStats.php`: debe seguir siendo el sistema de
  etapas/temporales.
- `src/Battle/Domain/Componentes/Combatiente/StatsCombatiente.php`: no reutilizar ni cambiar
  `aplicarBonusInicialStats` en esta tarea.
- `src/Battle/Domain/Componentes/Combatiente/JefeCombatiente.php`, `ParteJefe.php`,
  `PartesJefeCollection.php`: conservar infraestructura.
- Líneas ajenas de `src/Battle/Domain/CombatienteVista.php` (postura/clases) y
  `resources/views/livewire/_pokemon-card.blade.php:3` (`wire:key`) y `:139-143` (etapas).
- Migraciones, `.env`, credenciales y configuración de producción.

---

## BORRADOR DE ADR

- **Título**: Multiplicador permanente del jefe de mazmorra fuera del sistema de etapas.
- **Estado**: Propuesto.
- **Contexto**: El jefe de mazmorra usaba stats base ×10 y un bonus inicial +30 % aplicado
  como capa base de `MultiplicadoresStats`. Eso hacía que el buff del jefe contara como
  etapas (+4), disparara Castigo/Poder Reserva/Canto Encantador y pudiera invertirse,
  copiarse o intercambiarse. Producto pide vida ×10, stats base ×1 y un efecto permanente ×3
  (+200 %) solo en cinco stats, con barreras sobre la defensa efectiva y sin Núcleo/Coraza.
- **Decisión**: Añadir `multiplicadorStats` a `BattleStats` (default `1.0`) aplicado después
  de la fórmula de nivel a los cinco stats y a `defenseHp`/`spDefenseHp`; transportarlo desde
  `DatosPokemonBatalla::$multiplicadorStatsJefe`; el generador envía `3.0` y deja de usar
  `bonusInicialStats` y `puntosDebiles`; eliminar el fallback de Núcleo en `JefeEstado`;
  exponer `+200 %` como campo derivado de vista.
- **Consecuencias**:
  - El ×3 no es una etapa y no interactúa con mecánicas de etapas.
  - Las barreras máximas se multiplican por 3.
  - `bonusInicialStats`/`aplicarBonusInicialStats` quedan disponibles para otros modos, pero
    no para mazmorra.
  - No hay migración ni bump de `SESSION_VERSION`; los payloads antiguos cargan con
    multiplicador `1.0`.
  - Las sesiones vivas conservan el jefe antiguo.
  - Se añade un campo opcional al contrato de entrada y una propiedad serializada en
    `BattleStats`.

---

## PREGUNTAS ABIERTAS

1. **Aritmética de barreras**: ¿se confirma que la barra máxima debe ser exactamente `3 ×` la
   barra base (post-fórmula), o producto espera `calcularHp(3 × defensaBase)`? La diferencia
   es el término `+nivel+10`.
2. **`bonusInicialStats`**: ¿se deja intacto para otros modos (recomendado), se marca
   `@deprecated` o se corrige ahora para limitarlo a los cinco stats?
3. **Sesiones vivas**: ¿se acepta que los combates de mazmorra ya iniciados mantengan el
   balance antiguo hasta terminarlos?
4. **Test preexistente**: ¿la actualización de `MazmorraApiTest` se difiere al cierre del
   módulo (recomendado) o se incluye en una tarea aparte?
5. **Nomenclatura**: ¿se prefiere `MULTIPLICADOR_STATS_EFECTIVO_JEFE` o se reutiliza el
   nombre `MULTIPLICADOR_STATS_JEFE` con el nuevo valor `3.0`?
6. **UI**: ¿el badge debe mostrar solo `+200 %` o también un tooltip con `×3`? Se propone
   `+200 %` con tooltip explicativo.
