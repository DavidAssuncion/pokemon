# TASK-020 — Arquitectura de descomposición backend

- **Fecha:** 2026-11-05
- **Base:** `analysis.md` (mapa TASK-020), auditoría TASK-019 y diseño de `architect` (Battle y Exploraciones).
- **Alcance:** backend de dominio. Livewire se mantiene. Sin tests. Sin cambios de código en esta fase.
- **Estrategia:** fachada temporal + extracción incremental. Primero cálculo puro y puertos; después componentes de estado; al final serialización y limpieza.

---

## 1. Principios de la refactorización

1. **Comportamiento primero:** cada paso es behavior-preserving. No se reordena daño, RNG, persistencia ni eventos.
2. **Fachada antes que reescritura:** `Combatiente`, `AgregadoBatalla` y `ServicioEjecucionBatalla` conservan sus métodos públicos y delegan en componentes nuevos.
3. **Cálculo puro primero:** funciones/VOs sin Eloquent, HTTP, sesión ni contenedor. Esto es lo que hace el código testeable sin crear tests.
4. **Estado explícito:** el estado persistente sigue en las raíces actuales hasta que exista un serializador de sesión. Los componentes nuevos no guardan estado entre llamadas.
5. **Puertos solo con DIP/testabilidad real:** reloj, azar, persistencia y mapeo de presentación. No se crean interfaces para calculadores de una sola implementación.
6. **Sin sobreabstracción:** no se mueve todo a `Domain`; los handlers de `App` siguen orquestando y pueden usar Eloquent. Se elimina `app()` de los caminos de producción.
7. **Compatibilidad práctica:** `SESSION_VERSION` puede desaparecer en el futuro, pero ninguna fase rompe una batalla en curso sin un adaptador o una decisión explícita.

---

## 2. Diagnóstico SOLID resumido

| Clase | Viola | Evidencia | Consecuencia |
|---|---|---|---|
| `Combatiente` | SRP, OCP, ISP, DIP | 2.050 líneas, 38 propiedades, 189 métodos públicos; identidad, HP, estados, condiciones, movimientos, objetos, habilidades, stats, combos, invocaciones, jefe, tipos, serialización y vista | Cualquier cambio afecta a efectos, Livewire y sesión |
| `AgregadoBatalla` | SRP, OCP, DIP | 890 líneas, 19 propiedades; rondas, condiciones, clima, gas, invocaciones, daño diferido, log, IA y ejecución | El agregado es también motor y orquestador |
| `ServicioEjecucionBatalla` | SRP, OCP, DIP | `calcularYAplicarDanoContra()` 240 líneas; `mt_rand` directo; devuelve DTO de `Presentation` | Difícil aislar precisión, daño, efectos y log |
| `FinalizarExploracionHandler` | SRP, OCP, ISP, DIP | 890 líneas; recompensas, botín, pérdidas, incidentes, persistencia, Pokédex y resultado; `app()`, `now()`, Eloquent y `Presentation` | Un cambio de economía toca el mismo método |
| `ProcesarExploracionHandler` | SRP, OCP, LSP, ISP, DIP | 802 líneas; tick, eventos, encuentros, emboscadas, descanso, pool y persistencia; `now()`, Eloquent, `app()` | El flujo crítico no es aislable |

---

## 3. Arquitectura objetivo — Battle

### 3.1 `Combatiente` → fachada + componentes

La fachada conserva `pokemon`, `posicion`, `senda`, los 189 métodos públicos (delegando) y la orquestación entre componentes. Los componentes propuestos:

| Componente | Estado | Responsabilidad |
|---|---|---|
| `IdentidadCombatiente` | id, nombre, icono, especie, forma, shiny, posición | Identidad y presentación |
| `VitalidadCombatiente` | HP y barreras | Daño, curación, escudos, revivir |
| `EstadosCombatiente` | estado mayor, veneno, turnos | Estados y veredicto de acción |
| `CondicionesCombatiente` | colección de condiciones | Silencio, interrupción, aturdimiento, tipo, sustituto |
| `TurnoCombatiente` | `EstadoTurno` | Ronda, acciones extra, retraso, postura, prioridad, coste |
| `MovimientosCombatiente` | `EstadoMovimientos` | Cargas, usos, elegibilidad, recarga |
| `ObjetoCombatiente` | `EstadoObjeto` | Objeto, cargas, bloqueo, robo, intercambio, reciclaje |
| `HabilidadCombatiente` | `EstadoHabilidad` | Anulación, copia, intercambio, filtrado |
| `EfectosCombatiente` | `ColeccionEfectos` | Triggers y capacidades de efectos |
| `StatsCombatiente` | multiplicadores y resistencias | Stats efectivos y velocidad |
| `RegistroDanio` | 6 campos | Último daño, ronda, atacante, golpes |
| `RecursosCombatiente` | `EstadoRecursos` | Combo |
| `InvocacionCombatiente` | turnos e invocador | Criaturas temporales |
| `JefeCombatiente` | `?JefeEstado` | Fases y puntos débiles |
| `SerializadorCombatiente` | sin estado | Mapeo de estado a/desde sesión |

**Regla de serialización:** `Combatiente::__serialize()` sigue aplanando el estado a las mismas claves actuales; `__unserialize()` reconstruye los componentes. No se añaden claves nuevas mientras exista compatibilidad con sesiones.

### 3.2 `AgregadoBatalla` → fachada + procesadores sin estado

| Componente | Responsabilidad |
|---|---|
| `ProcesadorCondicionesRonda` | Condiciones volátiles y daño/curación por ronda |
| `GestorClimaTerreno` | Clima, terreno, Trick Room |
| `GestorGasReactivo` | Gas reactivo y sincronización |
| `GestorInvocaciones` | Crear, limitar y retirar invocaciones |
| `ProcesadorDanioDiferido` | Daño diferido y muertes |
| `MotorRondas` | Secuencia de ronda y efectos |
| `EjecutorAccion` | Acción, IA, servicio, log y observers |

**Fase inicial:** solo componentes sin estado. El estado del agregado no se mueve hasta que exista un serializador versionado (`FormatoSesionBatalla`).

### 3.3 `ServicioEjecucionBatalla` → fachada + piezas

| Componente | Responsabilidad |
|---|---|
| `EvaluadorPrecondicionesMovimiento` | Guardas de acción |
| `ResolutorPrecision` | Precisión y esquiva |
| `CalculadorGolpes` | Multi-golpe y encadenamiento |
| `ResolutorImpacto` | Daño y cadena |
| `AplicadorDrenaje` | Drenaje |
| `AplicadorRecoil` | Recoil |
| `AplicadorCosteVelocidad` | Coste de velocidad |
| `AplicadorCuracion` | Curación |
| `AplicadorEfectosPostImpacto` | Efectos posteriores |
| `AplicadorEstadoMovimiento` | Estado secundario |
| `GeneradorLogMovimiento` | Log |

La fachada conserva los 6 métodos públicos y la secuencia exacta.

---

## 4. Arquitectura objetivo — Exploraciones

### 4.1 `FinalizarExploracionHandler` → cálculo puro + orquestador delgado

| Componente | Responsabilidad | Capa |
|---|---|---|
| `VentanaExploracion` | Inicio/fin/ahora tipados | Domain |
| `CalculadoraDuracionExploracion` | Duración real y factor de tiempo | Domain |
| `CalculadoraIncidentes` | Encuentros, victorias, huidas, emboscadas | Domain |
| `CalculadoraPerdidas` | Objetos perdidos y resta de recompensas | Domain |
| `ClasificadorEventosExploracion` | Derrotados, avistados, hallazgos | Domain |
| `CalculadoraRecompensasExpedicion` | Base + hallazgos + botín + pérdidas | Domain |
| `MapeadorPokemonsDerrotados` | Carga y normalización | App |
| `EscritorEventosResultado` | Único escritor de `derrotados`, `resultado`, `objetos_perdidos` | App |
| `RepartidorRecompensasExploracion` | Orquesta usuario, bonos, senda, cálculo, persistencia, Pokédex y resultado | App |

El handler conserva `refresh()`, idempotencia, `regreso`, transacción y despacho.

### 4.2 `ProcesarExploracionHandler` → tick puro + orquestador delgado

| Componente | Responsabilidad | Capa |
|---|---|---|
| `MapeadorVentanaExploracion` | Modelo → ventana | App |
| `CalculadoraVentanaTick` | Inicio, límite, último procesado, cierre | Domain |
| `CargadorPoolHabitat` | Pool y bonos | App |
| `GestorBonosHabitat` | Persistir `bonos.del_habitat` una vez | App |
| `GeneradorEventosTick` | Cadencia, backfill, generación | Domain |
| `GestorDescansoExplorador` | 1 %/min y recuperación | Domain |
| `CalculadoraEvasionEmboscada` | Evasión automática y tirada | Domain |
| `ResolutorEncuentro` | Descanso, combate y resolución | App |
| `ResolutorEmboscada` | Sub-combates y derrota parcial | App |
| `MapeadorResolucionCombate` | Shape `resolucion` | App |
| `GestorTickExploracion` | Orquesta tick y devuelve `ResultadoTick` | App |

El handler conserva guardado, cierre y despacho; el gestor no persiste.

---

## 5. Puertos e interfaces

| Puerto | Métodos | Justificación | Fase |
|---|---|---|---|
| `GeneradorAleatorio` | `entero(int,int): int`, `flotante(): float` | Aísla `mt_rand` y permite resultados deterministas | 1 |
| `Reloj` | `ahora(): DateTimeImmutable` | Aísla `now()` en Exploraciones | 1 |
| `SerializadorBatalla` | `serializar`, `deserializar` | Único punto de sesión; permite v22 y lectura dual | 5 |
| `AlmacenBatalla` | `guardar`, `cargar`, `limpiar` | DIP de Livewire respecto a sesión | 5 |
| `PersistenciaRecompensas` | `persistir(...)` | DIP real del handler | 3 |
| `ConsultorPlantaciones` | `factoresPara(...)` | Ya existe; elimina `app()` | 2 |
| `ConsultorHabitat` | `esDelHabitat(...)` | Sustituye `EsDelHabitat` en handlers | 2 |
| `CargadorSenda` | `para(...)` | Elimina `app()` de handlers | 2 |
| `MapeadorResultadoExploracion` | `desde(...)` | Rompe `App → Presentation` | 3 |

No se crean interfaces para `ResolverBonosFamilia`, `CalculadorGolpes`, `RegistroDanio` ni `AptitudMovimiento`: no hay DIP real ni segunda implementación.

---

## 6. Estado, serialización y clonado

- **Battle:** `Combatiente` posee el estado y lo aplana en `__serialize()`; los componentes se reconstruyen en `__unserialize()`. `AgregadoBatalla` no mueve estado hasta la fase 5. `ServicioEjecucionBatalla` no se serializa.
- **Servicios y puertos:** nunca como propiedades de objetos persistidos. Se inyectan por constructor o se crean por uso.
- **Clonado:** `Combatiente::__clone()` clona cada componente mutable; `AgregadoBatalla::__clone()` clona equipos y anula el selector de IA.
- **Sesión:** en fases 1-4 se mantiene el formato actual `"21|payload"` y las claves actuales. La fase 5 introduce `FormatoSesionBatalla` y, si se decide, retira `SESSION_VERSION` con lectura dual.
- **Prohibido en estado serializado:** `Closure`, PDO, recursos, servicios de contenedor, relojes y generadores.

---

## 7. Testabilidad sin tests

| Seam | Uso |
|---|---|
| `GeneradorAleatorio` | Doble determinista en `Servicio` y `EstadoManager` |
| `Reloj` | Tick y duración deterministas en Exploraciones |
| Calculadores puros | Entrada/salida sin BD |
| `RegistroDanio` / VOs | Estado aislado |
| `SerializadorBatalla` | Ciclo guardar/cargar sin sesión real |
| `AlmacenBatalla` | Livewire contra interfaz |
| Separación cálculo/persistencia | Handlers delgados; puertos sustituibles |
| `ResultadoEvento`/`ResultadoTick` | Elimina paso por referencia |

La verificación de esta tarea es estática y manual. **No se crean ni ejecutan tests.**

---

## 8. Fases y dependencias

```text
Fase 1  Puertos + cálculo puro (Battle y Exploraciones)
Fase 2  Combatiente: componentes y fachada
Fase 3  ServicioEjecucionBatalla + handlers de Exploraciones
Fase 4  AgregadoBatalla: procesadores sin estado
Fase 5  Serialización v22 / retirada de SESSION_VERSION
Fase 6  Limpieza (CRUD legado, código muerto) y frontend
```

- Fase 1 no cambia APIs, serialización ni comportamiento.
- Fase 2 depende de Fase 1.
- Fase 3 puede solaparse con Fase 2 en Exploraciones (archivos disjuntos).
- Fase 4 depende de Fase 3 para el servicio dividido.
- Fase 5 depende de Fase 2/4.
- Fase 6 es independiente y puede ejecutarse en paralelo si hay capacidad.

---

## 9. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Cambiar orden de daño/RNG | Alto | Extraer sin reordenar; adaptador `mt_rand` idéntico |
| Romper serialización | Alto | No mover estado de `Agregado`; mismas claves en `Combatiente`; comparar `serialize()` manualmente |
| Romper API de 189 métodos | Alto | Fachada con delegación; no eliminar firmas |
| Divergencia Livewire/Agregado | Alto | Dividir `Servicio` antes de `Combatiente`; una sola ruta de daño |
| Cambiar shape de `eventos` | Alto | Un único escritor; captura previa del JSON |
| Tests acoplados a Reflection/constructores | Medio | Wrappers y constructores compatibles |
| N+1 o más queries | Medio | Reutilizar pool; no duplicar consultas |
| deptrac/PHPStan ya rotos | Medio | No empeorar; corregir lo tocado |
| Sobreabstracción | Medio | Puertos solo con DIP/testabilidad real |

---

## 10. Criterios de aceptación globales

- `Combatiente` ≤ 900 líneas tras Fase 2; `AgregadoBatalla` ≤ 500 tras Fase 4; `ServicioEjecucionBatalla` ≤ 250 tras Fase 3; ningún componente nuevo > 300 líneas.
- Los 189 métodos públicos de `Combatiente`, 39 de `Agregado` y 6 de `Servicio` siguen existiendo con las mismas firmas durante la migración.
- `FinalizarExploracionHandler` ≤ 180 líneas y `ProcesarExploracionHandler` ≤ 220 al final de Fase 3/4.
- Cero `app()`, `now()` o `mt_rand()` en los caminos de producción tocados; todo pasa por puertos.
- Cero imports de `Presentation` desde `Domain`/`App` tocados.
- Payload de sesión v21 sin cambios en Fases 1-4; Fase 5 con adaptador.
- `pint` pasa; PHPStan/deptrac no empeoran.
- Sin tests nuevos, sin migraciones, sin dependencias nuevas, sin `git push`.

---

## 11. Decisiones pendientes

1. ¿Se acepta invalidar batallas en curso al introducir el formato v22, o se exige lectura dual?
2. ¿`Combatiente` conserva los 189 métodos indefinidamente o se deprecan tras migrar efectos?
3. ¿La memoria de `SelectorAccionIA` debe sobrevivir a la sesión?
4. ¿El RNG debe ser reproducible por semilla?
5. ¿Se puede retirar `SESSION_VERSION` en Fase 5 o se mantiene como compatibilidad?
6. ¿La limpieza de `app/Crud`/`/cruds` se hace en Fase 6 o como tarea independiente?
