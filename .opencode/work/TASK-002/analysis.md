# TASK-002 — Análisis

> Resultado de los especialistas `analyst`. No contiene conclusiones propias del orquestador.
> Fecha: 2026-10-31.

## 1. Arnés IA vs IA y ejecución de batallas (analista A)

### Camino existente

- Sí existe un camino completo sin UI: `AgregadoBatalla::ejecutarBatalla()`
  (`src/Battle/Domain/AgregadoBatalla.php:719-761`). Ambos bandos usan el mismo
  `SelectorAccionIA` compartido (`:260-268`, `:765`, `:772`).
- Se puede variar la dificultad con `setDificultad()` (`:265-268`), pero es global para ambos
  bandos.
- La composición se puede variar construyendo `EquipoBatalla`/`DatosPokemonBatalla` y pasándolos
  al constructor público del agregado (`:60-68`).
- No hay API de semilla. El motor usa `mt_rand()` global en ~20 puntos. `mt_srand()` controla
  también `array_rand()` en PHP 8.4.
- **Bloqueador de determinismo**: si no se pasan IVs, `GeneradorIvs::aleatorios()` usa
  `random_int()` (`src/Pokemon/Domain/Stats/GeneradorIvs.php:82`), que no es sembrable.
- **Riesgo**: `CalculadoraDanioIA` re-siembra el MT global (`mt_srand(42)` y luego `mt_srand($seed)`)
  sin restaurar el estado (`src/Battle/Domain/AI/CalculadoraDanioIA.php:52-55`).

### `FabricaBatallaMock`

- Composición fija de 3v3 (`src/Battle/Infrastructure/FabricaBatallaMock.php:41-179`).
- No es determinista si no se fijan IVs.
- Fuera del contenedor crea una `FabricaEfectos` vacía (`EquipoBatalla.php:156-158`) y descarta
  `effectKeys`/`item` (`:197-218`): **ejecuta batallas sin efectos reales**. No sirve para cazar
  bugs de efectos.

### Arnés mínimo viable (recomendado por el analista)

```php
$fabrica = app(\Src\Battle\Domain\Effects\FabricaEfectos::class); // requiere Tests\TestCase
$t1 = EquipoBatalla::fromData($datos1, 'A', $fabrica);
$t2 = EquipoBatalla::fromData($datos2, 'B', $fabrica);
$b  = new AgregadoBatalla($t1, $t2);
$b->triggerBattleStartEffects();
$b->setDificultad(NivelDificultad::PERFECTA);
mt_srand($semilla);
$log = $b->ejecutarBatalla();
```

Requisitos: `DatosPokemonBatalla` con `ivs: StatsValue::ceros()` (o `GeneradorIvs::maximos()`),
`effectKeys`/`item` por escenario, y wrapper con timeout/límite de rondas.

### Pruebas verificadas

- `vendor/bin/phpunit tests/Unit/Battle --no-coverage` → **264 tests, 661 assertions, 2 fallos,
  1,226 s**.
  - `CombatienteAvanzadoTest.php:176` espera `speed 100` y recibe `205`.
  - `ManejadorObjetosEquipadosTest.php:137` espera `150` y recibe `195`.
  - Ambos son preexistentes y ajenos al WIP.
- `vendor/bin/phpunit tests/Feature/PokemonBattleTest.php --no-coverage` → 2 tests OK, 0,24 s.
- No usar `composer test`: ejecuta `artisan config:clear` y escribe caché.

### Sin tope de rondas

- `AgregadoBatalla::ejecutarBatalla()` usa `while (bothTeamsAlive())` (`:723`) sin límite.
- `GestorTurnos::bothTeamsAlive()` (`:180-183`) solo comprueba debilitamiento.
- Verificado empíricamente: batalla 1v1 con solo `Paz Mental` en ambos bandos no retorna
  (`timeout 12` → código 124).
- `procesarDebilitamientos()` es idempotente por `debilidadNotificada` (`Combatiente.php:1880-1892`),
  pero no evita estancamientos por curación ≥ daño.

### WIP local relevante

- `src/Battle/Domain/Combatiente.php` (+17 `aplicarBonusInicialStats`).
- `src/Battle/Domain/DatosPokemonBatalla.php` (+2 `bonusInicialStats`).
- `src/Battle/Domain/EquipoBatalla.php` (+4 aplica el bonus).
- `src/Battle/context.md`.
- `tests/Unit/Battle` y `tests/Unit/Battle/AI` sin cambios.
- El WIP no toca serialización ni el flujo de `ejecutarBatalla`.

### Huecos de infraestructura

- No hay comando de simulación. `Tareas_pdte/especificacion_ia_batalla.md:1829-1846` especifica un
  `BattleAISimulator` de 10.000 batallas, pero no está implementado.
- No hay inyección de `SelectorAccionIA` ni de semilla en el agregado.

## 2. Cobertura de efectos (analista B)

> El analista B alcanzó el máximo de pasos. El inventario es parcial pero cuantificado; los
> pendientes se listan al final.

### Inventario

- `BattleEffectServiceProvider`: **316 registros** `registrarEfecto`, **289 claves únicas**.
- **92 clases de efecto distintas registradas** de 102 archivos en `src/Battle/Domain/Effects/`.
- Enums: `ClaveEfecto` 8; `ClaveEfectoMovimiento` 86; `EstadoPokemon` 8 (7 + NONE); `TipoClima` 7
  (6 + NONE); `TipoTerreno` 5 (4 + NONE); `ClaveItem` 8; `ClaveCondicion` 26.
- Ejecutores de movimiento: 79 clases `Ejecutor*`, **76 registrados** en
  `ColeccionEjecutoresMovimiento` (`:24-102`).
- 7 claves del enum sin ejecutor: `FOCO_RESPLANDOR`, `PICO_TALADRO`, `PODER_PASADO`, `PUNO_METEORO`,
  `PUNO_SOMBRA`, `STATS_ALIADO`, `VELOCIDAD_EXTREMA`.
- Objetos: 6 registros de 8 `ClaveItem`; faltan `CINTA_ELECCION` y `GAFAS_ELECCION`.
- Capacidades: 26 interfaces; `ReaccionaAlDanioRecibido` la más usada (16 referencias).

### Cobertura de tests

- `tests/Unit/Battle`: 5.256 líneas en 24 archivos; `MovimientoEfectosTest.php` concentra 1.568
  líneas y ~70 tests.
- De las 289 claves de habilidad, **270 no aparecen mencionadas** en ningún test de
  `tests/Unit/Battle`.
- Solo ~19 tienen alguna referencia.
- `FabricaEfectosTest.php` prueba la mecánica de la fábrica, no el catálogo real del provider: no
  valida que las 289 claves se creen sin excepción.

### Efectos difíciles de ejercitar en IA vs IA

- Dependientes de decisión/UI: `EfectoPantallista`, `EfectoRefuerzos`, `EfectoHospitality`,
  `EfectoExpulsarArena`, `EfectoEspejoMagico`, `EjecutorInteraccionObjeto`,
  `EjecutorInteraccionHabilidad`, `EfectoDormilon`, `EfectoFlexibilidad`, `EfectoMofa`,
  `EfectoDesafio`.
- Dependientes de aliados/posiciones: `EfectoGuardiaAmiga`, `EfectoRedireccionTipo`,
  `EfectoVinculoDano`, `EfectoAuraAliada`, `EfectoEquipo*`.
- Dependientes de clima/terreno concretos: invocadores.
- Dependientes de KO/orden exacto: `EfectoAlDebilitar`, `EfectoComealmas`, `EfectoPerishBody`,
  `EfectoUltraimpulso`, `EfectoMoxie`.

### Invariantes propuestos

1. `0 <= hpActual <= hpMax` en todo combatiente tras cada turno/ronda.
2. `0 <= defensaHpActual <= defenseHpMax` y equivalente especial.
3. `hpActual == 0` ⟺ `!estaVivo()`; ningún debilitado recibe curación.
4. Ningún estado distinto de `NONE` en un combatiente debilitado.
5. `turnosEstado >= 0`; `contadorVenenoGrave >= 0`; `cargasVeneno >= 0`.
6. Clima/terreno coherentes: `NONE` ⟺ `turnosTerreno == 0`; el estado del agregado coincide con el
   de los combatientes.
7. PP/usos: `0 <= usosRestantes <= usosMax`; consumir solo descuenta si se usó.
8. Stats: temporales se decrementan exactamente 1 por ronda; no quedan temporales con `turnos < 0`.
9. Terminación: la batalla acaba en `<= N` rondas (hoy no hay tope).
10. Unicidad de acciones: cada vivo actúa como máximo una vez por ronda (+ acciones extra
    justificadas).
11. Log: toda reducción de HP tiene entrada de log asociada.
12. Condiciones volátiles no persisten tras KO o cambio de combatiente.

### Patrones de riesgo con evidencia

- **Sin tope de rondas / bucle infinito**: `AgregadoBatalla.php:719-735`,
  `GestorTurnos::bothTeamsAlive()` (`:180`). Alta prioridad.
- **Daño negativo no validado**: `BarrerasVidaManager::desglosarDaño` (`:30-49`) no hace
  `max(0, ...)`; `Combatiente::recibirDaño` (`:1629`) restaría un negativo → curaría HP/barreras.
- **Doble aplicación en multiobjetivo**: `ServicioEjecucionBatalla::calcularYAplicarDanoContra`
  ejecuta ejecutores por cada defensor (`:324`) y secundarios/control por cada uno (`:346-348`).
- **Orden de resolución**: daño de estado/clima en `triggerRoundEndEffects` (`:432`) después de
  `onRoundEnd` y antes de `procesarDanioDiferido`; `procesarDebilitamientos` es idempotente
  (`:499`), pero las curas al debilitarse pueden alterar el orden.
- **Persistencia tras KO/cambio**: `procesarCondicionesVolatiles` (`:545`) solo recorre vivos; no
  hay limpieza explícita al cambiar de combatiente.
- **Inmunidades**: `ignoraHabilidadRival` anula la inmunidad (`:249`); verificar orden
  inmunidad/override.
- **Orbe Vida** resta HP directo sin pasar por barreras ni `recibirDaño`
  (`EfectoOrbeVida.php:77`) → no dispara `dispararDanioRecibido` ni notifica KO en el mismo punto.
- **Restos** usa `setHpActual` directo (`EfectoRestos.php:36`), saltándose `onHealed`.
- **Dos convenciones de porcentaje**: `curarHp(float $porcentaje)` divide por 100
  (`BarrerasVidaManager::curarHp:53`) mientras `EfectoCuracionFinTurno` usa fracción directa
  (`:144`).
- **Simulador IA ignora efectos**: `SimuladorAccionIA::simular` llama `calcularYAplicarDano` sin
  `$battle` y `aplicarEstadoYStatChanges(..., null, ...)`; no ejecuta ejecutores ni secundarios.
  La IA evalúa un mundo distinto del real.
- **Clonado de estado mutable**: `ColeccionEfectos::__clone` (`:68`) clona efectos; cualquier
  efecto nuevo con estado mutable no clonado contaminaría la batalla original.

### Huecos/duplicados/huérfanos

- **Huérfano confirmado**: `EfectoMoody` existe y no se referencia en ningún sitio.
- **Claves del enum sin ejecutor**: las 7 listadas.
- **Items sin efecto**: `CINTA_ELECCION`, `GAFAS_ELECCION`.
- **Alias duplicados intencionales**: `hipnotizador`/`hypnotizador`, `neutralizing_gas`/`gas_reactivo`,
  `robustez`/`sturdy`/`undying`, `mold_breaker`/`rompemoldes`, `actualizacion_software`/`veleta`,
  `ira_gyarados`/`ira`.
- **Posibles no-op**: ~28 claves registradas con `EfectoMarcador` (polvo_escudo, sincronia, dicha,
  madrugar, lodo_liquido, impasible, indefenso, metal_pesado, metal_liviano, allanamiento, mutatipo,
  libero, rompeaura, ensañamiento, remoto, corrosion, letargo_perenne, reves, guardia_metalica,
  coraza_reflejo, mimetismo, energia_eolica, dinamo, oportunista, ojo_mental, cadena_toxica,
  titere_toxico) → candidatos a «registrado sin comportamiento».

### Pendientes del analista B

- Leer `ComportamientosPorDefecto`, `EfectoModificadorDanio`, `EfectoInmuneEstado`,
  `EfectoInmunidadTipo` para confirmar orden/inmunidades.
- Verificar numéricamente cuántos `EfectoMarcador` hay y si alguno tiene lógica.
- Cruzar `CatalogoHabilidades` con las 289 claves para detectar habilidades del CSV sin registro.
- Confirmar si `aplicarEstadoYStatChanges` puede duplicar estado con `aplicarSecundarios`.
- Revisar `tests/Feature` por cobertura de batalla no contada.
- Verificar si `EfectoMoody` es deuda declarada.

## 3. Bug Dragoaliento — causa raíz (analista C)

### Confirmación

- `app/Livewire/Combate.php:694-699` (ruta por prioridad) y `:930-936` (ruta `resolverAccion`)
  llaman a `$servicio->aplicarEstado(...)` y después registran
  `"{$target->nombre()} sufre {$movimiento->statusEffect->label()}!"` de forma **incondicional**
  si el movimiento tiene estado y el objetivo vive.
- `app/Livewire/Combate.php:938-947` registra el evento `status` con
  `'forzar' => $movimiento->tieneStatus() && $objetivo->estaVivo()`. `registrarEvento()`
  (`:1403-1408`) solo descarta el evento si `cambios === [] && ! $forzar`; con `forzar = true`
  crea el evento aunque no haya delta.
- `resources/views/combate_page.blade.php:352-359` pinta la píldora de texto con `evento.texto`;
  `:397-401` solo anima el estado si `actual.status !== previo.status`. Encaja exactamente con el
  síntoma: se lee «sufre parálisis» pero el estado no cambia.
- `GestorEfectosMovimiento::aplicarEstado()` (`src/Battle/Domain/Danio/GestorEfectosMovimiento.php:27-90`)
  sortea `probabilidadEstado` (`:41-43`) y retorna `void`; no comunica el resultado.

### Condiciones de no aplicación

| Condición | Evidencia |
|---|---|
| Movimiento sin estado u objetivo muerto | `GestorEfectosMovimiento.php:33-35` |
| Objetivo protegido | `:37-39` |
| Tirada de probabilidad fallida (Dragoaliento: 70 %) | `:41-43` |
| Velo Flor | `:48-51` |
| Velo Dulce | `:52-55` |
| Velo Pastel | `:56-59` |
| Safeguard | `:60-62` |
| `effects()->permiteEstado()` falso | `:66-68` |
| `cuerpo_aureo` en movimiento de estado | `ServicioEjecucionBatalla.php:379-381` |
| Flags BALA/SONIDO/POLVO inmunes | `:383-387` |
| Espejo Mágico | `:389-398` |

Ninguna rama informa al llamador.

### `setEstado()` sobrescribe estados

- `Combatiente::setEstado()` (`:1288-1307`) asigna `$this->estado = $estado` sin guarda de estado
  previo. `CONFUSION` va por condiciones volátiles.
- Consecuencia: una quemadura puede quedar sustituida por parálisis si la tirada pasa. Es un
  defecto real e independiente del síntoma (el síntoma es mensaje sin efecto; esto es efecto con
  mensaje). No verificado ejecutando tests.

### Propuesta mínima del analista

1. `GestorEfectosMovimiento::aplicarEstado(): bool` — `false` en todas las ramas de no aplicación;
   `true` tras aplicar.
2. `ServicioEjecucionBatalla::aplicarEstado(): bool` — `false` en cuerpo áureo, flags, espejo
   mágico; delega el resto.
3. `app/Livewire/Combate.php` — usar el booleano para el log y para `'forzar'`.
4. `aplicarEstadoYStatChanges()` se mantiene `void`; ni `AgregadoBatalla` ni `SimuladorAccionIA`
   necesitan el resultado.
5. Compatibilidad: añadir tipo de retorno a un método antes `void` no rompe llamadas que lo
   ignoran. Los 5 tests existentes de `ServicioEjecucionBatallaTest` y el de
   `MovimientoEfectosTest` siguen siendo válidos.

### Tests propuestos

- `tests/Unit/Battle/ServicioEjecucionBatallaTest.php`: `true` cuando aplica; `false` si falla
  probabilidad; `false` con objetivo muerto/protegido/inmunidad/cuerpo áureo; `false`/no
  sustitución con estado previo (requiere guarda aparte).
- `tests/Unit/Battle/MovimientoEfectosTest.php`: Safeguard, Velo Flor, Espejo Mágico.
- `tests/Feature/CombateLivewireTest.php`: Dragoaliento que falla el estado no muestra mensaje.
- Determinismo: inyectar `probabilidadEstado` 0.0/1.0 vía `PerfilMovimiento`; no depender de
  `mt_srand()` global.

## 4. Bloqueos y decisiones pendientes

- El alcance incluye corregir el bug reportado, no solo testearlo.
- Decidir si se corrige también la sobrescritura de estados (cambio de comportamiento) o se deja
  como hallazgo separado.
- Decidir si el arnés IA vs IA será un test permanente, un comando de diagnóstico o ambos.
- El motor no tiene tope de rondas: el arnés debe imponerlo externamente para no colgarse.
- Los 2 tests preexistentes en rojo no deben confundirse con regresiones de esta tarea.
