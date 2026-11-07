# TASK-031 — Arquitectura

- **Estado:** COMPLETADO (diseño)
- **Fecha:** 2026-11-07
- **Base:** `task-011`, HEAD `c020b6c`
- **Fuente:** resultado de `architect`; sin implementación.
- **Alcance aprobado:** Frente A base (9 curas, 25 buffs, Alivio, crítico) + IA B0/B1 con compuerta conservadora.
- **Fuera de alcance:** Purificar, Bálsamo Osado, Bastión Final, Acupresión, `STATS_ALIADO`/equipo, prioridades con objetivo aliado, cambios de datos.

## 1. Decisiones de diseño

1. **`TipoEfectoAliado` añade dos casos:** `CURAR_ESTADO` y `CRITICO_MEJORADO`. `EfectoAliado` no cambia: sigue siendo `tipo + turnos`.
   - `CURAR_ESTADO`: instantáneo, `turnos: 0`.
   - `CRITICO_MEJORADO`: `turnos: 5`, equivalente a `EjecutorFocoEnergia`.
2. **Curación de estado mediante método de dominio** `Combatiente::curarEstadoMayor(): void`, no un helper privado del ejecutor. Evita una décima copia del bloque `setEstado(NONE) + setTurnosEstado(0) + setContadorVenenoGrave(0)`.
3. **`CRITICO_MEJORADO` sobre aliado usa `reemplazarCondicion`**, no `agregarCondicion`: si el aliado ya tiene la condición, se refresca a 5 turnos. Se documenta la divergencia con el modo propio, que no se toca.
4. **IA B0 con ruta condicionada, no decisión única global.** `prepararAnimacionIa()` usa una sola decisión solo si el actor admite soporte (aliado vivo alcanzable + movimiento del catálogo disponible). El resto conserva el camino legacy; así no cambia la IA de atacantes sin soporte.
5. **`resolver()` no se toca.** Con el `defenderId` correcto, la rama aliada existente aplica el efecto y mantiene la defensa en profundidad para slugs no admitidos.
6. **Evaluación de soporte separada:** `ClasificadorAccionIA::esApoyo()` + `EvaluadorSoporteIA`. `EvaluacionAccion` añade `supportValue` al final con default `0.0`; `PesosAmenaza` añade pesos de soporte al final con defaults.
7. **Compuerta conservadora uniforme** para `NORMAL`, `DIFÍCIL` y `PERFECTA`: si hay un KO disponible, el soporte no compite; el soporte sin efecto se descarta; el desempate no cambia.
8. **Lookahead desactivado solo para acciones de apoyo.** Los ataques conservan el comportamiento actual.
9. **`EjecutorAccion`/Exploraciones:** `incluirSoporte: false`; el motor headless sigue siendo 1v1/enemigo. No se implementa rama aliada en `EjecutorAccion`.

## 2. Frente A — Dominio

### 2.1 Enum y VO

`src/Battle/Domain/Enums/TipoEfectoAliado.php`:

```php
enum TipoEfectoAliado: string
{
    case ESCUDO_FISICO = 'escudo_fisico';
    case ESCUDO_ESPECIAL = 'escudo_especial';
    case AQUA_RING = 'aqua_ring';
    case CURAR_PS = 'curar_ps';
    case BUFF_STATS = 'buff_stats';
    case CURAR_ESTADO = 'curar_estado';
    case CRITICO_MEJORADO = 'critico_mejorado';
}
```

`src/Battle/Domain/ValueObjects/EfectoAliado.php` no cambia.

### 2.2 Catálogo

`src/Battle/Domain/Catalog/CatalogoEfectosAliado.php`: 37 entradas nuevas; total 46 slugs (9 existentes + 37).

| Tipo | Slugs | Efecto |
|---|---|---|
| `CURAR_PS` (9) | `roost`, `morning-sun`, `synthesis`, `moonlight`, `soft-boiled`, `milk-drink`, `slack-off`, `heal-order`, `shore-up` | `new EfectoAliado(CURAR_PS)` |
| `BUFF_STATS` (25) | `agility`, `rock-polish`, `autotomize`, `harden`, `withdraw`, `rizo_defensa`, `barrier`, `acid-armor`, `iron-defense`, `shelter`, `cotton-guard`, `amnesia`, `charge`, `tail-glow`, `cosmic-power`, `defend-order`, `magnetic-flux`, `sharpen`, `hone-claws`, `work-up`, `coil`, `quiver-dance`, `shift-gear`, `geomancy`, `victory-dance` | `new EfectoAliado(BUFF_STATS)` |
| `CURAR_ESTADO` (1) | `refresh` | `new EfectoAliado(CURAR_ESTADO)` |
| `CRITICO_MEJORADO` (2) | `focus-energy`, `laser-focus` | `new EfectoAliado(CRITICO_MEJORADO, turnos: 5)` |

Los 9 slugs de TASK-030 conservan su tipo. `take-heart`, `no-retreat`, `acupressure`, `purify`, `howl`, `gear-up`, `coaching`, `aromatic-mist` y `rest` no se añaden.

### 2.3 `EjecutorEfectoAliado`

- Ampliar el `match` exhaustivo con `CURAR_ESTADO` y `CRITICO_MEJORADO`.
- `aplicarCuracionPs()` usa `perfil->curacionFraccion` con fallback a `FRACCION_RECUPERACION = 0.5`; los 9 slugs tienen 50 %, así que el resultado no cambia.
- Nuevo `aplicarCuracionEstado()`: comprueba estado, llama `curarEstadoMayor()`, registra log y devuelve texto.
- Nuevo `aplicarCriticoMejorado()`: `turnos = max(1, $efecto->turnos)`; `reemplazarCondicion(new CondicionVolatil(CRITICO_MEJORADO, $turnos, $attacker->id()))`; log y texto.
- `Combatiente::curarEstadoMayor()`: `setEstado(NONE)`, `setTurnosEstado(0)`, `setContadorVenenoGrave(0)`.
- **Confusión:** el helper no elimina la condición inmediatamente; `setTurnosEstado(0)` deja sus turnos a 0 y se elimina en la siguiente acción. Es el comportamiento actual de Alivio y se conserva por decisión del desarrollador (2026-11-07). Si producto quiere impedir esa cura indirecta, se cambia después el manejo de estados.
- `EjecutorRefresh` puede delegar en `curarEstadoMayor()` (mismo comportamiento, opcional).

### 2.4 Preview

`app/Livewire/Presenters/MovesPreviewPresenter.php`:

- `textoEfectoAliado()` añade:
  - `CURAR_ESTADO` → “Cura el estado alterado del aliado”.
  - `CRITICO_MEJORADO` → “Mejora la probabilidad de crítico del aliado durante N rondas”.
- `CURAR_PS` usa `perfil->curacionFraccion` con fallback a la constante existente.
- Sin cambios en `previewAliadoBase()` ni en `previewDe()`.

### 2.5 Sin cambios

- **Blade:** no aplica; el badge `SOBRE ALIADO`, el texto y el ocultado de daño son data-driven.
- **JS:** no aplica; se reutiliza el evento `effect`.
- **Presentadores de selección:** `actorAdmiteAliado()` y `refsAliado()` se amplían solos con el catálogo.
- **Sesión:** no aplica; no hay estado persistido nuevo; `SESSION_VERSION` sigue en 21.
- **Datos:** `storage/data/moves.json` no cambia.
- **`DepuradorMovimientos`:** no cambia.

## 3. Frente B — IA

### 3.1 B0 — Decisión única y autoobjetivo

Problema: `prepararAnimacionIa()` obtiene objetivo y movimiento con dos llamadas independientes a `elegirAccion()` y después fuerza `defenderId = actor` si `esAutodirigido()`. Cualquier soporte sobre aliado se destruye.

Diseño:

- `AgregadoBatalla` añade:
  - `actorAdmiteSoporte(Combatiente $actor): bool`: hay aliado vivo alcanzable (vanguardia si corresponde) y movimiento disponible del catálogo.
  - `elegirAccionPara(Combatiente $actor): ?AccionBatalla`: llama una sola vez a `SelectorAccionIA::elegirAccion(...)` y devuelve `accion`.
- `prepararAnimacionIa()`:
  - Si `actorAdmiteSoporte($actor)`: `$accion = elegirAccionPara($actor)`, `$objetivo = $accion->defender`, `$movimiento = $accion->move`.
  - Si no: camino legacy `elegirObjetivoPara()` + `elegirMejorMovimiento()`.
  - **Se elimina el forzado** `defenderId = actor`; el selector ya devuelve `defender = actor` para autodirigidos.
- `resolver()` no cambia.
- `SelectorAccionIA::elegirAccion()` gana `bool $incluirSoporte = true` al final.
  - `elegirObjetivoPara()`, `AgregadoBatalla::elegirObjetivoPara()/elegirMejorMovimiento()` y `EjecutorAccion::elegirMovimiento()` pasan `incluirSoporte: false`.

**Por qué ruta condicionada:** actores sin aliados o sin movimientos del catálogo conservan el camino legacy y cero cambio de comportamiento. Actores con soporte obtienen una decisión coherente (objetivo + movimiento de la misma evaluación).

### 3.2 B1 — Generación

`SelectorAccionIA::generarAccionesCandidatas()` mantiene intacto el bloque enemigo y añade `agregarAccionesSoporte()` cuando `incluirSoporte` es verdadero.

Condiciones:

- `$contexto->aliados` no vacío.
- Actor con al menos un movimiento disponible admitido por `CatalogoEfectosAliado`.
- Aliado vivo, distinto del actor, y no en retaguardia si el equipo tiene vanguardia viva.
- `AccionBatalla(attacker: actor, defender: aliado, move: movimiento, defenderTeamHasVanguard: $vanguardiaViva, weather: $battle->weather(), terreno: $battle->terreno(), equipoDefensor: $equipoActor)`.
- Las variantes autodirigidas siguen generándose apuntando al actor; el bloque de soporte añade la variante sobre aliado. Ambas compiten.

### 3.3 Clasificador

Nuevo `src/Battle/Domain/AI/ClasificadorAccionIA.php`:

```php
public static function esApoyo(AgregadoBatalla $battle, AccionBatalla $accion): bool
{
    $equipoDefensor = $battle->equipoDe($accion->defender);

    return $accion->defender->id() !== $accion->attacker->id()
        && $equipoDefensor !== null
        && $equipoDefensor === $battle->equipoDe($accion->attacker)
        && CatalogoEfectosAliado::admite($accion->move->slug);
}
```

Usos: evaluación, `hayKOEn()`, `debeAplicarLookahead()` y guardas.

### 3.4 Evaluador de soporte

Nuevo `src/Battle/Domain/AI/EvaluadorSoporteIA.php`:

```php
public function valor(ContextoDecisionIA $contexto, AccionBatalla $accion): float;
```

Definiciones:

- `hpMax`, `hp`, `faltante = max(0, hpMax - hp)`, `ratio = hp / hpMax`.
- `golpeMax = max` de `CalculadoraDanioIA::mejorEstimacionContra($enemigo, $aliado, $battle)->esperado` sobre enemigos vivos; 0 si no hay.
- `enRiesgoKO = golpeMax >= hp` (solo PS; aproximación conservadora).
- `valor = 0` si `< puntosMinimosSoporte` (5.0).

| Tipo | Fórmula | Compuerta |
|---|---|---|
| `CURAR_PS` | `curado = min(hpMax * fraccion, faltante)`; `base = curado / hpMax * puntosCuracion`; `valor = base + (evitaKO ? puntosCuracionCritica : 0)`; `evitaKO = enRiesgoKO && golpeMax < hp + curado`; `fraccion = perfil->curacionFraccion` (fallback 0.5) | `faltante > 0` y (`ratio <= 0.5` o `enRiesgoKO`) |
| `ESCUDO_FISICO`/`ESPECIAL` | `deficit = max(0, maxEscudo - actual)`; `incremento = CalculadoraCuracionAliada::incrementoEscudo(maxEscudo, stat efectivo del actor)`; `efectivo = min(incremento, deficit)`; `base = efectivo / maxEscudo * puntosEscudo`; `enRiesgoKO = golpeMax >= hp + actual`; `evitaKO = enRiesgoKO && golpeMax < hp + actual + efectivo`; `valor = base + (evitaKO ? puntosCuracionCritica : 0)` | `deficit > 0` y (`deficit / maxEscudo >= 0.25` o `enRiesgoKO`) |
| `BUFF_STATS` | Por cada `CambioStat`: `actual = multiplicadores()->obtenerMultiplicador(stat)`; `nuevo = min(4.0, actual * cambio->factorTemporal())`; `delta += max(0, nuevo - actual)`; `valor = delta * puntosBuffPorMultiplicador` | `delta >= 0.3` |
| `AQUA_RING` | `porRonda = CalculadoraCuracionAliada::curacionConAporteFijo(hpMax, hpMaxLanzador * 0.05)`; `curadoEsperado = min(faltante, porRonda * efecto->turnos)`; `valor = curadoEsperado / hpMax * puntosAquaRing` | `faltante > 0` y (`ratio <= 0.5` o `enRiesgoKO`) y (`AQUA_RING` ausente o `turnos <= 1`) |
| `CURAR_ESTADO` | `valor = puntosCuracionEstado` | `estadoMayor() !== NONE` |
| `CRITICO_MEJORADO` | `valor = puntosCriticoMejorado` | condición ausente (no se reutiliza si ya está activa) |

Pesos iniciales en `PesosAmenaza` (al final, con defaults):

```php
puntosCuracion = 60.0
puntosCuracionCritica = 100.0
puntosCuracionEstado = 45.0
puntosEscudo = 40.0
puntosBuffPorMultiplicador = 40.0
puntosCriticoMejorado = 25.0
puntosAquaRing = 30.0
puntosMinimosSoporte = 5.0
umbralHpSoporte = 0.5
umbralDeficitEscudo = 0.25
```

No se modifican `puntosKO`, `multiplicadorDanio`, `puntosSupervivencia` ni `puntosRiesgo`.

### 3.5 `EvaluadorAccionIAImpl`

- Constructor gana `?EvaluadorSoporteIA $evaluadorSoporte = null` al final; si es null, se construye con `CalculadoraCuracionAliada` y los pesos.
- `evaluar()` empieza con:

```php
if (ClasificadorAccionIA::esApoyo($contexto->battle, $accion)) {
    return $this->evaluarApoyo($contexto, $accion);
}
```

- `evaluarApoyo()` devuelve `EvaluacionAccion` con `supportValue`, `score = supportValue + survivalValue - risk`, `koValue = 0`, `damageValue = 0`, `threatReduction = 0`.
- `debeAplicarLookahead(ContextoDecisionIA $contexto, AccionBatalla $accion)` devuelve `false` si `esApoyo`; si no, mantiene `PERFECTA`/`DIFÍCIL` con lookahead y `NORMAL` sin él.
- `SimuladorAccionIA` no se modifica.

### 3.6 Selección y compuerta

`SelectorAccionIA::elegirAccion()`:

1. Evalúa todas las candidatas.
2. Descarta apoyo con `supportValue <= 0`.
3. Si `hayKOEn($evaluaciones)` (alguna evaluación no-apoyo con `koValue > 0`), filtra todas las de apoyo.
4. `seleccionarSegunDificultad()` sin cambios: ordena por `score`, desempata por `damageValue` y `potencia`.
5. Si el pool queda vacío, `accionFallback()`.

La compuerta aplica igual a las tres dificultades. No hay dos pasadas ni flag en contexto.

### 3.7 Filtros anti-inútiles

| Filtro | Dónde | Regla | Resultado |
|---|---|---|---|
| Aliado muerto | Generación + resolución | `vivos()` y `estaVivo()` | Bloqueado |
| Autoobjetivo | Generación | `id() !== actor->id()` | Bloqueado |
| Retaguardia con vanguardia viva | Generación | `estaEnRetaguardia() && tieneVanguardiaViva()` | Bloqueado |
| PS llenos | Evaluación | `faltante <= 0` | Bloqueado |
| PS > 50 % sin riesgo KO | Evaluación | `ratio > 0.5 && !enRiesgoKO` | Bloqueado |
| Escudo lleno | Evaluación | `deficit <= 0` | Bloqueado |
| Escudo con déficit < 25 % sin riesgo KO | Evaluación | `deficit/maxEscudo < 0.25 && !enRiesgoKO` | Bloqueado |
| Buff saturado | Evaluación | `Σ delta <= 0` | Bloqueado |
| Buff marginal | Evaluación | `Σ delta < 0.3` | Bloqueado |
| Estado ausente | Evaluación | `estadoMayor() === NONE` | Bloqueado |
| Crítico ya activo a 5 | Evaluación | `turnos >= 5` | Bloqueado |
| Aqua Ring activo | Evaluación | `turnos > 1` | Bloqueado (se permite refrescar a ≤ 1) |
| Valor mínimo | Evaluación | `valor < 5.0` | Bloqueado |
| `DESAFIO` | Generación | No bloquea soporte; replica la UI (`!$esAliado`) | Permitido |
| `CUBRIR` | — | `aplicarMovimientoAliado` no dispara redirecciones | Permitido |
| Doble buff | Evaluación | Delta neto y mínimo evitan repetir buffs saturados; se permite apilar si aún hay delta | Permitido con límite |

### 3.8 `EjecutorAccion` / Exploraciones

- `EjecutorAccion::elegirMovimiento()` pasa `incluirSoporte: false`.
- No se implementa rama aliada en `EjecutorAccion`.
- `CombateExploracion` es 1v1, así que `contexto->aliados` está vacío; la guarda es suficiente.
- Si en el futuro se usa `ejecutarBatalla()` con aliados, hará falta una rama explícita; queda documentado.

## 4. Contratos y compatibilidad

- `app/Livewire/Combate.php`: **sin cambios** (32 propiedades/18 métodos).
- `SESSION_VERSION`: **sin cambios** (21).
- `EvaluacionAccion`: `supportValue = 0.0` al final; solo dos construcciones conocidas (`EvaluadorAccionIAImpl` y fallback del selector), el fallback puede omitirlo.
- `PesosAmenaza`: nuevos parámetros al final con defaults; `porDefecto()` y `normal()` usan named args y no necesitan cambios.
- `SelectorAccionIA::elegirAccion()`: parámetro booleano al final con default `true`; las APIs legacy pasan `false`.
- `AgregadoBatalla::elegirObjetivoPara()/elegirMejorMovimiento()`: pasan `incluirSoporte: false` para conservar su semántica enemiga.
- No hay migraciones, datos, serialización ni estado persistido nuevo.

## 5. Archivos y responsabilidades

| Archivo | Cambio | Responsable |
|---|---|---|
| `src/Battle/Domain/Enums/TipoEfectoAliado.php` | `CURAR_ESTADO`, `CRITICO_MEJORADO` | backend |
| `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php` | 37 slugs | backend |
| `src/Battle/Domain/Ejecucion/EjecutorEfectoAliado.php` | 2 ramas, 2 métodos, fracción desde perfil | backend |
| `src/Battle/Domain/Combatiente.php` | `curarEstadoMayor()` | backend |
| `src/Battle/Domain/Effects/Movimiento/EjecutorRefresh.php` | Delegación opcional | backend |
| `app/Livewire/Presenters/MovesPreviewPresenter.php` | 2 ramas y textos | backend |
| `src/Battle/Domain/AI/ClasificadorAccionIA.php` | **Nuevo** | backend |
| `src/Battle/Domain/AI/EvaluadorSoporteIA.php` | **Nuevo** | backend |
| `src/Battle/Domain/AI/SelectorAccionIA.php` | `incluirSoporte`, generación, filtros, compuerta | backend |
| `src/Battle/Domain/AI/EvaluadorAccionIAImpl.php` | Rama de soporte, lookahead | backend |
| `src/Battle/Domain/AI/ValueObjects/EvaluacionAccion.php` | `supportValue` | backend |
| `src/Battle/Domain/AI/PesosAmenaza.php` | Pesos de soporte | backend |
| `src/Battle/Domain/AgregadoBatalla.php` | `actorAdmiteSoporte()`, `elegirAccionPara()`, legacy `false` | backend |
| `app/Livewire/Combate/Soporte/GestorAccionBatalla.php` | Ruta condicionada, sin forzado | backend |
| `src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php` | `incluirSoporte: false` | backend |
| `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php` | Sin cambios | — |
| `app/Livewire/Combate.php` | Sin cambios | — |
| `storage/data/moves.json` | Sin cambios | — |
| `resources/views/...` / `resources/js/...` | Sin cambios | — |

## 6. Fases

| Fase | Contenido | Depende de | Riesgo |
|---|---|---|---|
| **F1 — Dominio base** | Catálogo con 9 `CURAR_PS` + 25 `BUFF_STATS`; fracción desde `perfil` en ejecutor y preview | — | Bajo |
| **F2 — Dominio estados/crítico** | Enum, catálogo (`refresh`, `focus-energy`, `laser-focus`), ejecutor, `curarEstadoMayor`, preview | F1 | Medio |
| **F3 — IA B0** | `actorAdmiteSoporte`, `elegirAccionPara`, ruta condicionada, eliminación del forzado, legacy `false` | — | Medio |
| **F4 — IA B1** | Clasificador, evaluador de soporte, generación, evaluación, `supportValue`, pesos, compuerta, lookahead | F1, F2, F3 | Medio-alto |
| **F5 — Verificación** | `php -l`, Pint, PHPStan, deptrac, `view:cache`, `git diff`, smoke manual | F1-F4 | — |

Orden: F1 → F2 → F3 → F4 → F5. B1 no debe desplegarse sin B0.

## 7. Riesgos y mitigaciones

| Riesgo | Severidad | Mitigación |
|---|---|---|
| `UnhandledMatchError` por enum nuevo sin rama | Alta | F2 atómica: enum + ejecutor + preview en el mismo cambio; PHPStan y `php -l` |
| La IA se autoaplica el soporte | Alta | B0 elimina el forzado; `elegirAccionPara` devuelve el aliado; `resolver()` ya enruta |
| La IA daña a un aliado con escudos/Aqua Ring | Alta | `esApoyo()` antes de `estimar()`; `incluirSoporte:false` en APIs legacy y `EjecutorAccion` |
| Cambio de comportamiento de la IA | Media | Ruta condicionada; legacy intacto para actores sin soporte |
| Bucles/pasividad | Media | Compuerta de HP/riesgo KO, delta de buff, mínimo 5, no-KO |
| Coste de `mejorEstimacionContra` | Media | Listas acotadas (≤ 4 movimientos × ≤ 3 aliados); cachear después si hace falta |
| `CRITICO_MEJORADO`: propio usa agregar, aliado reemplazar | Baja | Divergencia documentada; no se toca modo propio |
| Alivio no cura confusión | Baja | Comportamiento actual preservado; decisión de producto pendiente |
| `geomancy` con carga en modo aliado | Baja | Reutiliza `cargaMaxima` y `consumirMovimiento`; smoke de dos rondas |
| Pesos mal calibrados | Media | Valores conservadores ajustables en `PesosAmenaza`; smoke manual |
| `PesosAmenaza`/`EvaluacionAccion` rompen construcciones | Baja | Parámetros al final con default; solo dos construcciones |

**Rollback:** todo es código; sin migraciones, datos ni sesión. Revertir F4 si B1 produce pasividad, conservando F3; revertir F2 completo si hay `UnhandledMatchError`; revertir F3 si la IA cambia demasiado. Usar `git revert`, no `reset --hard`, `clean` ni `checkout --` sin aprobación. Sin `git push`.

## 8. Preguntas abiertas / hipótesis

1. **Resuelto (2026-11-07):** Alivio conserva el comportamiento actual (todos los estados mayores).
2. **Resuelto (2026-11-07):** la confusión se deja a 0 turnos y se elimina en la siguiente acción; no se cambia el manejo de estados.
3. `CRITICO_MEJORADO`: el jugador refresca a 5 con `reemplazarCondicion`; la IA no reutiliza el movimiento si la condición ya está activa. La divergencia con modo propio (`agregarCondicion`) se mantiene documentada.
4. `DESAFIO`: no bloquea soporte a aliados; confirmar.
5. Compuerta: igual para las tres dificultades; confirmar.
6. Pesos iniciales: valores de arranque; ajustar tras smoke.
7. `enRiesgoKO` usa solo PS, ignorando escudos; aproximación conservadora.
8. `geomancy`: la revisión detectó que `MovimientoFactory` asigna `cargaMaxima=3` por el flag `charge`, no 1; se asume que `consumirMovimiento()` funciona igual. Verificar con smoke.
9. `magnetic-flux`/`shelter`/`autotomize`/`charge`: datos simplificados; se aceptan tal cual.
10. `aplicarStatChangesAMuchos()` puede aplicar varias veces si hay multiplicador de efecto; la valoración de la IA asume una aplicación. Aproximación.
11. `mejorEstimacionContra()` no comprueba PP ni disponibilidad; comportamiento preexistente.
12. El contrato 32/18 de `Combate` se toma de TASK-030 y no se toca.
