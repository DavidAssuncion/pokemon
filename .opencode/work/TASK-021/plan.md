# TASK-021 — Plan v1 — Ola 1: tres frentes paralelos

- **ID:** TASK-021
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-06
- **Base:** `TASK-020/architecture.md`, `TASK-020/analysis.md`, commit `fc7fecf`
- **Alcance autorizado por este plan:** solo la **Ola 1**. Las olas 2-4 requieren plan y aprobación propios.

---

## 1. Respuesta a la pregunta

**No se pueden paralelizar todos los cambios.** Hay dependencias reales:

- La Fase 3A (`ServicioEjecucionBatalla`) necesita la fachada de `Combatiente` estable.
- La Fase 4 (`AgregadoBatalla`) depende de la Fase 3A.
- La Fase 5 (serialización v22 / `SESSION_VERSION`) depende de las fases 2 y 4.
- La Fase 6B (código muerto/frontend) puede depender de referencias que cambian en las fases 2-3.

**Sí se pueden abrir tres frentes disjuntos ahora:**

| Frente | Fase | Archivos principales | Riesgo |
|---|---|---|---|
| A | Fase 2 — `Combatiente` en componentes | `src/Battle/Domain/Combatiente.php`, nuevo subdirectorio de componentes | Alto |
| B | Fase 3B — handlers de Exploraciones | `src/Exploraciones/App/FinalizarExploracionHandler.php`, `ProcesarExploracionHandler.php`, nuevos componentes de Dominio/App | Alto |
| C | Fase 6A — limpieza del CRUD legado | `app/Crud/Base`, `CrudGenerateCommand`, rutas/vistas/referencias de `/cruds` | Medio/bajo |

Los tres frentes no comparten archivos. Si aparece un archivo compartido, se detiene ese frente y se resuelve antes de continuar.

---

## 2. Objetivo de la Ola 1

Reducir la deuda estructural de las tres áreas sin cambiar comportamiento:

1. **Fase 2:** convertir `Combatiente` en una fachada que delega en componentes, conservando sus 189 métodos públicos, su serialización y su clonado.
2. **Fase 3B:** dividir los handlers de Exploraciones en cálculo puro y orquestación delgada, conservando idempotencia, timing de persistencia y shape de `eventos`.
3. **Fase 6A:** eliminar el CRUD propio en desuso (`app/Crud/Base`, `crud:generate`, `/cruds`) y sus referencias, sin tocar Battle ni Exploraciones.

---

## 3. Frente A — Fase 2: `Combatiente` en componentes

### 3.1 Componentes objetivo

Subdirectorio propuesto: `src/Battle/Domain/Componentes/Combatiente/` con namespace `Src\Battle\Domain\Componentes\Combatiente`.

| Componente | Estado que posee | Responsabilidad |
|---|---|---|
| `IdentidadCombatiente` | id, nombre, icono, especie, forma, shiny, posición | Identidad y presentación |
| `VitalidadCombatiente` | HP, barreras | Daño, curación, escudos, revivir |
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

### 3.2 Reglas

- `Combatiente` conserva **todos** los métodos públicos con las mismas firmas y delega en los componentes.
- `__serialize()` sigue aplanando el estado a las **mismas claves** actuales; `__unserialize()` reconstruye los componentes.
- `__clone()` clona cada componente mutable.
- No se mueve estado de `AgregadoBatalla`.
- No se toca `ServicioEjecucionBatalla`, `BattleSessionService` ni `SESSION_VERSION`.
- No se cambia el orden de daño, RNG, efectos ni log.
- Los componentes no importan `Illuminate`, `App\`, `Presentation`, Eloquent ni usan `app()`.

### 3.3 Criterios de aceptación

1. `Combatiente` ≤900 líneas; cada componente ≤300 líneas.
2. Los 189 métodos públicos siguen existiendo con las mismas firmas.
3. `serialize()`/`unserialize()` producen un payload equivalente y una batalla puede cargarse durante su ciclo de vida.
4. `__clone()` no comparte estado mutable entre original y copia.
5. Sin cambios en `AgregadoBatalla`, `ServicioEjecucionBatalla`, `BattleSessionService`, `SESSION_VERSION`, tests, rutas ni migraciones.
6. Pint pasa; PHPStan no añade errores sobre 34; deptrac no añade violaciones sobre 105.
7. Revisión independiente `APPROVED`.

---

## 4. Frente B — Fase 3B: handlers de Exploraciones

### 4.1 `FinalizarExploracionHandler`

Extraer a `Domain` el cálculo puro y dejar en `App` la orquestación:

- `VentanaExploracion`, `CalculadoraDuracionExploracion`, `CalculadoraIncidentes`, `CalculadoraPerdidas` ya existen de la Fase 1.
- Nuevos: `CalculadoraRecompensasExpedicion`, `ClasificadorEventosExploracion`, `MapeadorPokemonsDerrotados`, `EscritorEventosResultado`, `RepartidorRecompensasExploracion`.
- El handler conserva `refresh()`, idempotencia, `regreso`, transacción y despacho.

### 4.2 `ProcesarExploracionHandler`

Extraer:

- `MapeadorVentanaExploracion`, `CalculadoraVentanaTick`, `CargadorPoolHabitat`, `GestorBonosHabitat`, `GeneradorEventosTick`, `GestorDescansoExplorador`, `CalculadoraEvasionEmboscada`, `ResolutorEncuentro`, `ResolutorEmboscada`, `MapeadorResolucionCombate`, `GestorTickExploracion`.
- El handler conserva guardado, cierre y despacho; el gestor no persiste.

### 4.3 Reglas

- Mantener el shape de `eventos`, `resultado`, `derrotados`, `objetos_perdidos`, `bonos.del_habitat` y `regreso`.
- Mantener la idempotencia y el timing de `save()`/transacción.
- No tocar `Combatiente`, `AgregadoBatalla`, `ServicioEjecucionBatalla`, serialización ni `SESSION_VERSION`.
- No usar `app()`, `now()` ni `mt_rand()` en los caminos tocados; usar los puertos existentes.

### 4.4 Criterios de aceptación

1. `FinalizarExploracionHandler` ≤180 líneas; `ProcesarExploracionHandler` ≤220 líneas.
2. Cada componente nuevo ≤300 líneas; métodos ≤40 líneas.
3. `eventos` y `regreso` conservan claves, orden y valores.
4. La idempotencia y el timing de persistencia no cambian.
5. Sin cambios en Battle, serialización, tests, rutas ni migraciones.
6. Pint pasa; PHPStan ≤34; deptrac ≤105.
7. Revisión independiente `APPROVED`.

---

## 5. Frente C — Fase 6A: limpieza del CRUD legado

### 5.1 Alcance

- Inventariar referencias a `app/Crud`, `CrudGenerateCommand`, `crud:generate`, `/cruds` y `src/Crud`.
- Verificar que no existe `src/Crud`.
- Eliminar el CRUD propio y sus rutas/vistas/referencias.
- No eliminar rutas o vistas ajenas.
- No tocar Battle, Exploraciones, serialización ni frontend de juego.

### 5.2 Criterios de aceptación

1. `grep` de `app/Crud`, `CrudGenerateCommand`, `crud:generate` y `/cruds` sin referencias activas.
2. `php artisan route:list` no muestra rutas `/cruds` y no pierde rutas ajenas.
3. No se rompen vistas, menús ni enlaces que no sean del CRUD.
4. Sin cambios en Battle/Exploraciones.
5. Pint pasa; PHPStan ≤34; deptrac ≤105.
6. Revisión independiente `APPROVED` o justificación de que el cambio es trivial.

---

## 6. Archivos y propiedad

### Frente A

- `src/Battle/Domain/Combatiente.php`
- `src/Battle/Domain/Componentes/Combatiente/*.php` (nuevo)
- `src/Battle/Domain/CombatienteVista.php` solo si cambia una firma pública; no debería.

### Frente B

- `src/Exploraciones/App/FinalizarExploracionHandler.php`
- `src/Exploraciones/App/ProcesarExploracionHandler.php`
- Nuevos componentes en `src/Exploraciones/Domain/*` y `src/Exploraciones/App/*`.
- No tocar `PersistirRecompensas`, `CombateExploracion`, `Lote`/`UnitOfWork` salvo que el plan de detalle lo justifique.

### Frente C

- `app/Crud/Base/*`
- `app/Console/Commands/CrudGenerateCommand.php`
- Rutas, vistas y referencias de `/cruds`.
- Documentación que mencione el CRUD legado, si existe.

**Regla de concurrencia:** cada archivo tiene un único dueño. Si un frente necesita un archivo de otro, se detiene y se coordina.

---

## 7. Orden y dependencias

```text
Ola 1 (este plan)
  A: Fase 2  ─┐
  B: Fase 3B ─┼─ en paralelo
  C: Fase 6A ─┘

Ola 2 (plan v2)
  Fase 3A: ServicioEjecucionBatalla (después de A)
  + resto de B/C si no terminaron

Ola 3 (plan v3)
  Fase 4: AgregadoBatalla (después de 3A)
  + Fase 6B: código muerto/frontend (después de 3B)

Ola 4 (plan v4)
  Fase 5: serialización v22 / SESSION_VERSION (después de 2 y 4)
```

- La Ola 1 no toca `ServicioEjecucionBatalla` (eso es Ola 2).
- La Ola 1 no toca serialización (eso es Ola 4).
- La Ola 2 puede ejecutar Fase 3A y terminar B/C en paralelo.
- La Ola 3 puede ejecutar Fase 4 y 6B en paralelo si los archivos siguen disjuntos.
- La Ola 4 es serial y de riesgo alto.

---

## 8. Verificación

- **Estática:** Pint, PHPStan L6, deptrac, `php -l`, `git diff --check`.
- **Búsquedas:** `grep` de `mt_rand`, `now()`, `app()`, `Illuminate`, `SESSION_VERSION`, `__serialize`, referencias al CRUD.
- **Manual:** comparar payload serializado antes/después en Fase 2; revisar `route:list` antes/después en Fase 6A; diff de `eventos` en Fase 3B.
- **Sin tests:** no se crean, actualizan ni ejecutan.

---

## 9. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Romper serialización de `Combatiente` | Alto | Mismas claves; `__unserialize` reconstructor; comparar payload; no tocar `SESSION_VERSION` |
| Cambiar la API de 189 métodos | Alto | Fachada con delegación; no eliminar firmas |
| Romper orden RNG/daño | Alto | Extracción literal; sin reordenar |
| Cambiar shape de `eventos` | Alto | Un único escritor; comparar JSON |
| Conflictos entre frentes | Medio | Archivos disjuntos; regla de propiedad; detener si aparece shared |
| PHPStan/deptrac ya rotos | Medio | No empeorar baseline 34/105 |
| Eliminar una ruta/vista usada por el CRUD | Medio | Inventario y `route:list` antes/después |
| Sobreabstracción | Medio | Componentes solo donde hay estado o cálculo real |

---

## 10. Rollback

- Un commit local por frente, sin `git push`, para poder revertir de forma selectiva.
- Si el usuario prefiere un único commit al final de la ola, se consolida antes de cerrar.
- No hay migraciones ni datos que revertir.
- Si un frente falla la revisión, se corrige dentro del mismo frente; no se avanza a la Ola 2 hasta cerrar la Ola 1.

---

## 11. Fuera de alcance de este plan

- Fase 3A (`ServicioEjecucionBatalla`).
- Fase 4 (`AgregadoBatalla`).
- Fase 5 (serialización v22 / `SESSION_VERSION`).
- Fase 6B (código muerto/frontend), salvo las referencias estrictamente necesarias para 6A.
- Tests, migraciones, dependencias, `git push`.

---

## 12. Aprobación solicitada

Aprobar la **Ola 1** de TASK-021:

- Frente A: Fase 2 (`Combatiente`).
- Frente B: Fase 3B (handlers de Exploraciones).
- Frente C: Fase 6A (CRUD legado).

Las olas 2-4 se planificarán y aprobarán al cerrar la anterior.
