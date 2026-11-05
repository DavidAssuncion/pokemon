# TASK-012 — Plan v1 — Fases F2–F5

- **ID:** TASK-012
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-04
- **Proyecto:** `/home/david/pokemon` — Laravel 12 / PHP 8.4 / Livewire 4
- **Rama:** `task-011` (F1 sin commitear)
- **Base documental:** `request.md`, `analysis.md`, `architecture.md` de TASK-012; `TASK-011/architecture.md`; `TASK-011/analysis.md`.

---

## 1. Objetivo

Implementar F2, F3, F4 y F5 del sistema de bayas, bonguris, plantaciones, forja y uso de objetos en combate, sobre la F1 ya implementada:

| Fase | Resultado esperado |
|---|---|
| **F2** | Plantar y cosechar bayas en los huecos de los nidos, con crecimiento diario, stock máximo 3 y persistencia de la planta. |
| **F3** | Bonus de botín por explorar con un Pokémon nativo del hábitat y por las plantaciones de la granja; visible en el resultado. |
| **F4** | Forja de pociones, restauradores, revivir, mejoras, reductores y resistencias a partir de bayas, bonguris y recipientes. |
| **F5** | Uso manual de objetos en combate: curar, revivir, buff/debuff, resistencia de tipo y bayas básicas; consume la acción, no el movimiento. |

---

## 2. Alcance

### Incluido

- Inventario compartido con operaciones atómicas (`saldo`, `saldos`, `consumir`, `consumirVarios`, `incrementar`).
- Migración `nest_plantings` y modelo `PlantacionNido`.
- Dominio, aplicación, endpoints y UI de plantación/cosecha.
- Cálculo del flag “Pokémon nativo del hábitat” y persistencia en `eventos['bonos']['del_habitat']`.
- Factores de plantación por `(user_id, habitat_id)` aplicados solo al peso de bayas salvajes.
- Catálogo de 39 claves nuevas de objetos (8 pociones, 1 recipiente, 7 mejoras, 5 reductores, 18 resistencias) y 46 recetas.
- Módulo `src/Forja` con `ListarRecetas`, `Fabricar`, endpoints y página `/forja`.
- Dominio de combate para `AccionObjeto`, efectos, `ServicioUsoObjetos`, resistencias temporales y `restaurarUsos()`.
- Integración Livewire: fases `player_item_target`, panel de 44 objetos, selección de aliados/rivales, incluido revivir debilitados.
- Bump de `SESSION_VERSION` a 21 manteniendo mínimo compatible 9.
- Verificación estática, revisión independiente, auditoría de seguridad e informe final.
- ADR/documentación de las decisiones nuevas.

### Fuera de alcance

- F6: equipables con coste, descuento al equipar y activación automática de bayas equipadas.
- F7: botín de bayas/bonguris en combates de ruta/entrenador/gimnasio/mazmorra.
- Auto-cosecha (`RECOLECTOR` + `BOTIN`): se difiere.
- Iconos definitivos de pociones, recipiente, mejoras, reductores y resistencias; se usa fallback.
- Bayas `chesto`, `persim`, `lum` y `leppa` como objetos directos: no existen en `ClaveBaya`; se difieren.
- Ampliación del catálogo de 36 bayas.
- Tests: no se crean, actualizan ni ejecutan; `tests/` queda fuera de alcance.
- Commit, push, despliegue, migración en producción, instalación/actualización de dependencias.
- Cambios de balance fuera de los valores v1 documentados.

---

## 3. Decisiones y contratos

### 3.1 Decisiones estructurales

| ID | Decisión |
|---|---|
| D1 | Puerto `InventarioJugador` en `src/Shared/Domain/Ports` + adaptador único `InventarioJugadorEloquent` en `src/Shared/Infra/Inventario`. |
| D2 | `nest_plantings` con una fila por hueco ocupado; stock calculado al leer; unique `(nest_id, slot_index)`. |
| D3 | `ConsultorPlantaciones` como puerto de `Exploraciones\Domain` implementado en `Granjas\Infra`; `EsDelHabitat` en `Exploraciones\App`; flag persistido en `eventos['bonos']['del_habitat']`. |
| D4 | 39 claves nuevas en `src/Shared/Domain/Items`; 46 recetas estáticas en `src/Forja`; sin migración. |
| D5 | `DTOAccionObjeto` separado; `pendingAction` como unión `DTOAccionBatalla|DTOAccionObjeto|null`; fase `player_item_target`; `ResistenciasTemporales` con manejador de cadena propio. |
| D6 | `SESSION_VERSION = 21`, `MINIMUM_COMPATIBLE_SESSION_VERSION = 9`; v20 sigue cargando con defaults. |
| D7 | Orden de ejecución secuencial: Bloque 0 → F2 → F3 → F4 → F5. |

### 3.2 Decisiones de producto v1

| Tema | Decisión |
|---|---|
| Huecos | `nivel * 2`; nivel 0 no permite plantar. |
| Plantar | Consume 1 baya; clave validada contra `ClaveBaya`. |
| Crecimiento | 1 cosecha cada 24 h; stock máximo 3; planta persistente. |
| Cosecha | Manual; sin auto-cosecha. |
| Replantar | Permitido; pierde stock pendiente; la UI advierte. |
| Bonus de hábitat | +25 % tiradas de baya y +10 pp de bonguri, tope 85 % de bonguri. |
| Factores de plantación | Solo bayas salvajes de la misma granja/hábitat; `min(2.0, 1 + 0.20 * plantas)`; no afectan bonguris. |
| Bonguri sin color | Se mantiene el fallback blanco de F1. |
| Recetas | Árbol R1–R9 + recipiente (7 variantes) + mejoras/reductores/resistencias; 46 recetas, 39 resultados. |
| Fabricación | 1 unidad por acción; sin `Idempotency-Key`; UI deshabilita doble clic. |
| Objetos de combate | 44: 6 bayas básicas + 8 pociones + 7 mejoras + 5 reductores + 18 resistencias. |
| Buff/debuff | ±30 % durante 5 rondas; reaplicar refresca sin stackear. |
| Resistencia de tipo | −30 % a cualquier ataque de ese tipo durante 5 rondas. |
| Revivir | Aliado debilitado; 50 % HP; Max Revivir 100 % + usos. |
| Restaurar Todo | HP 100 %, estado limpio y usos restaurados. |
| Sesión | v21; mínimo 9; no se destruyen batallas vivas. |
| Iconos | Fallback `unknown.webp` y reutilización de iconos de tipo/baya. |

### 3.3 Contratos principales

```php
// Inventario compartido
interface InventarioJugador {
    public function saldo(int $userId, string $clave): int;
    public function saldos(int $userId, StringCollection $claves): MapaConteo;
    public function consumir(int $userId, string $clave, int $cantidad, ?string $mensajeError = null): void;
    public function consumirVarios(int $userId, MapaConteo $claves, ?string $mensajeError = null): void;
    public function incrementar(int $userId, string $clave, int $cantidad): void;
}

// F2
PlantarBaya::handle(int $userId, int $habitatId, int $evolutionChainId, int $slot, string $berryKey): void
CosecharPlantacion::handle(int $userId, int $habitatId, int $evolutionChainId, int $slot): int

// F3
ConsultorPlantaciones::factoresPara(int $userId, int $habitatId): MapaFactores
EsDelHabitat::handle(int $pokemonId, int $habitatId): bool

// F4
CatalogoRecetas::receta(string $clave): RecetaFabricacion
CatalogoRecetas::todas(): RecetaFabricacionCollection
CatalogoRecetas::paraResultado(string $resultadoClave): RecetaFabricacionCollection
ListarRecetas::handle(int $userId): RecetaDetalleCollection
Fabricar::handle(int $userId, string $recetaClave, int $cantidad = 1): ResultadoFabricacion

// F5
ServicioUsoObjetos::validar(AccionObjeto $accion, AgregadoBatalla $battle): ?string
ServicioUsoObjetos::aplicar(AccionObjeto $accion, AgregadoBatalla $battle): ResultadoUsoObjeto
UsarObjetoEnCombate::handle(int $userId, AgregadoBatalla $battle, AccionObjeto $accion): ResultadoUsoObjeto
ObtenerOpcionesObjeto::handle(int $userId, AgregadoBatalla $battle, Combatiente $actor): OpcionObjetoCollection
```

Los contratos completos, firmas y modelos están en `architecture.md` §3–§5 y Anexo C.

---

## 4. Arquitectura y datos

### 4.1 Inventario compartido (Bloque 0)

- `src/Shared/Domain/Ports/InventarioJugador.php`: contrato.
- `src/Shared/Domain/Exceptions/StockInsuficiente.php`: 422 con factory `para(...)`.
- `src/Shared/Infra/Inventario/InventarioJugadorEloquent.php`: implementación con `PlayerInventory::withoutUserScope()`, `lockForUpdate`, validación de saldo y `firstOrCreate()->increment()`.
- `app/Providers/AppServiceProvider.php`: binding interfaz → implementación.
- `src/Granjas/App/InventarioGranja.php`: delega `consumirFamilia`/`consumirEv` en el puerto; añade `resumenBayas()`.
- Toda clave pasa por `CatalogoItems::existe()`; una clave desconocida lanza `ItemDesconocido` (422).

### 4.2 F2 — Datos

Migración `database/migrations/2026_11_04_000003_create_nest_plantings_table.php`:

```text
nest_plantings
  id                bigserial PK
  nest_id           bigint FK → nests(id) ON DELETE CASCADE
  slot_index        unsignedSmallInteger
  berry_key         varchar(32)
  planted_at        timestamp
  harvest_count     unsignedSmallInteger default 0
  created_at, updated_at

  UNIQUE (nest_id, slot_index)
  INDEX (berry_key)
```

Sin FK a catálogo, igual que `player_inventory`. `slot_index` se valida en aplicación contra `nests.level`.

### 4.3 F2 — Cálculo sin cron

```text
segundos       = max(0, ahora - planted_at)
transcurridas  = intdiv(segundos, 86400)
stock          = max(0, min(3, transcurridas - harvest_count))
progreso       = stock > 0 ? 100 : floor((segundos % 86400) / 864)
estado         = stock > 0 ? LISTA : CRECIENDO
```

`harvest_count` es acumulado histórico; la planta sigue produciendo tras cosechar.

### 4.4 F3 — Flujo de bonus

1. `ProcesarExploracionHandler` calcula una vez `eventos['bonos']['del_habitat']` con `EsDelHabitat` y lo persiste.
2. `FinalizarExploracionHandler::botin()` construye `ContextoBotin` con:
   - `factoresPlantacion` desde `ConsultorPlantaciones::factoresPara(userId, habitatId)`.
   - `delHabitat` desde el flag persistido o, si falta, desde `EsDelHabitat`.
3. `CalculadorBotinExploracion` aplica los factores solo en `pesosBayas()`.
4. `caeBonguri()` recibe `delHabitat` para el +10 pp, sin factores de plantación.
5. El resultado persistido incluye `bonos.del_habitat`; la UI muestra el hito “Conoce el terreno”.

### 4.5 F4 — Catálogo y recetas

- 39 claves nuevas: `pocion:*` (8), `material:recipiente` (1), `mejora:*` (7), `reductor:*` (5), `resistencia:*` (18).
- `CatalogoItems::construir()` y `aPartirDeItemKey()` ampliados; `objetosDeCombate()` devuelve los 44 usables.
- 46 recetas: 9 de pociones (R1–R9), 7 de recipiente (una por color), 7 mejoras, 5 reductores, 18 resistencias.
- `RecetaFabricacion::$clave` es el id de receta; `$resultadoClave` puede repetirse (poción desde Oran/Zidra, recipiente desde 7 bonguris).
- `CatalogoRecetas::paraResultado()` devuelve variantes; `resultados()` devuelve 39 claves únicas.
- `Fabricar` consume todos los ingredientes en una transacción con lock ordenado y produce 1 unidad.
- Sin migración.

### 4.6 F5 — Combate

- `AccionObjeto` + `DTOAccionObjeto`; `pendingAction` acepta `DTOAccionBatalla|DTOAccionObjeto|null`.
- `FaseCombate::SELECCION_OBJETIVO_ITEM = 'player_item_target'`.
- `Combate.php`:
  - `seleccionarObjeto()`, `cancelarObjeto()`, `confirmarObjetivoItem()`.
  - `previewTarget()` bifurca por fase.
  - `resolverAccion()` deriva a `resolverAccionObjeto()` con `instanceof`.
  - `resolverAccionObjeto()` consume solo la acción (`consumeAction`), no el movimiento.
- `ServicioUsoObjetos` valida catálogo, actor, objetivo, pertenencia y estado; `UsarObjetoEnCombate` consume inventario antes de aplicar.
- `CatalogoEfectosItem`: 44 efectos (6 bayas + 38 objetos).
- `ResistenciasTemporales` + `ManejadorResistenciaTemporal` en `CadenaDanio`.
- `Combatiente::restaurarUsos()`, `aplicarResistenciaTemporal()`, `multiplicadorResistenciaTemporal()`; serialización con default.
- `MultiplicadoresStats::refrescarTemporal()` para no stackear.
- `SESSION_VERSION = 21`, mínimo 9.

---

## 5. Archivos que se crearán o modificarán

### Bloque 0 — Inventario compartido

| Archivo | Acción | Responsable |
|---|---|---|
| `src/Shared/Domain/Ports/InventarioJugador.php` | crear | backend |
| `src/Shared/Domain/Exceptions/StockInsuficiente.php` | crear | backend |
| `src/Shared/Infra/Inventario/InventarioJugadorEloquent.php` | crear | backend |
| `app/Providers/AppServiceProvider.php` | modificar (binding) | backend |
| `src/Granjas/App/InventarioGranja.php` | modificar (delegación + `resumenBayas`) | backend |

### F2 — Plantación y cosecha

| Archivo | Acción | Responsable |
|---|---|---|
| `database/migrations/2026_11_04_000003_create_nest_plantings_table.php` | crear | database |
| `app/Models/PlantacionNido.php` | crear | backend |
| `app/Models/Nido.php` | modificar (relación `plantaciones`) | backend |
| `src/Granjas/Domain/Entities/Plantacion.php` | crear | backend |
| `src/Granjas/Domain/Enums/EstadoPlantacion.php` | crear | backend |
| `src/Granjas/Domain/ReglasPlantacion.php` | crear | backend |
| `src/Granjas/Domain/DataTransferObjects/PlantacionGranja.php` | crear | backend |
| `src/Granjas/Domain/DataTransferObjects/BayaGranja.php` | crear | backend |
| `src/Granjas/Domain/DataTransferObjects/BonguriGranja.php` | crear | backend |
| `src/Granjas/Domain/Collections/PlantacionesGranjaCollection.php` | crear | backend |
| `src/Granjas/Domain/Collections/BayasGranjaCollection.php` | crear | backend |
| `src/Granjas/Domain/DataTransferObjects/NidoGranja.php` | modificar (huecos/plantaciones) | backend |
| `src/Granjas/Domain/DataTransferObjects/GranjaDetalle.php` | modificar (bayas/bonguri) | backend |
| `src/Granjas/App/PlantarBaya.php` | crear | backend |
| `src/Granjas/App/CosecharPlantacion.php` | crear | backend |
| `src/Granjas/App/ObtenerGranja.php` | modificar (plantaciones/bayas/bonguri) | backend |
| `src/Granjas/Infra/Controllers/GranjaController.php` | modificar (`plantar`, `cosechar`) | backend |
| `src/Granjas/Infra/Requests/PlantarBayaRequest.php` | crear | backend |
| `routes/granjas.php` | modificar (2 rutas) | backend |
| `resources/views/habitats/show.blade.php` | modificar (UI de huecos) | frontend |

### F3 — Influencia y bonus

| Archivo | Acción | Responsable |
|---|---|---|
| `src/Exploraciones/Domain/Ports/ConsultorPlantaciones.php` | crear | backend |
| `src/Exploraciones/App/EsDelHabitat.php` | crear | backend |
| `src/Granjas/Infra/Plantaciones/ConsultorPlantacionesEloquent.php` | crear | backend |
| `src/Exploraciones/Domain/ContextoBotin.php` | modificar (`factoresPlantacion`) | backend |
| `src/Exploraciones/Domain/CalculadorBotinExploracion.php` | modificar (factores + `delHabitat`) | backend |
| `src/Exploraciones/App/ProcesarExploracionHandler.php` | modificar (flag de hábitat) | backend |
| `src/Exploraciones/App/FinalizarExploracionHandler.php` | modificar (contexto y `bonos`) | backend |
| `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php` | modificar (`bonos`) | backend |
| `src/Exploraciones/Presentation/PresentadorExploraciones.php` | modificar (`bonos`) | backend |
| `src/Exploraciones/Presentation/ResultadoExploracionVista.php` | modificar (`bonus_habitat`) | backend |
| `src/Exploraciones/Presentation/HitoExploracion.php` | modificar (hito “Conoce el terreno”) | backend |
| `app/Providers/AppServiceProvider.php` | modificar (binding `ConsultorPlantaciones`) | backend |
| `resources/views/exploraciones/index.blade.php` | modificar (badge/hito) | frontend |

### F4 — Forja

| Archivo | Acción | Responsable |
|---|---|---|
| `src/Shared/Domain/Items/ClavePocion.php` | crear | backend |
| `src/Shared/Domain/Items/ClaveMaterial.php` | crear | backend |
| `src/Shared/Domain/Items/ClaveMejora.php` | crear | backend |
| `src/Shared/Domain/Items/ClaveReductor.php` | crear | backend |
| `src/Shared/Domain/Items/ClaveResistencia.php` | crear | backend |
| `src/Shared/Domain/Items/CatalogoItems.php` | modificar (39 claves + `objetosDeCombate`) | backend |
| `src/Forja/Domain/RecetaFabricacion.php` | crear | backend |
| `src/Forja/Domain/RecetaFabricacionCollection.php` | crear | backend |
| `src/Forja/Domain/CatalogoRecetas.php` | crear (46 recetas) | backend |
| `src/Forja/Domain/Exceptions/RecetaNoExiste.php` | crear | backend |
| `src/Forja/App/ListarRecetas.php` | crear | backend |
| `src/Forja/App/Fabricar.php` | crear | backend |
| `src/Forja/App/DataTransferObjects/IngredienteDetalle.php` | crear | backend |
| `src/Forja/App/DataTransferObjects/RecetaDetalle.php` | crear | backend |
| `src/Forja/App/DataTransferObjects/ResultadoFabricacion.php` | crear | backend |
| `src/Forja/App/Collections/IngredientesDetalleCollection.php` | crear | backend |
| `src/Forja/App/Collections/RecetaDetalleCollection.php` | crear | backend |
| `src/Forja/Infra/Controllers/ForjaController.php` | crear | backend |
| `src/Forja/Infra/Requests/FabricarRequest.php` | crear | backend |
| `routes/forja.php` | crear | backend |
| `routes/web.php` | modificar (`require forja`) | backend |
| `resources/views/layouts/app.blade.php` | modificar (enlace “Forja”) | frontend |
| `resources/views/forja/index.blade.php` | crear | frontend |

### F5 — Combate

| Archivo | Acción | Responsable |
|---|---|---|
| `src/Battle/Domain/AccionObjeto.php` | crear | backend |
| `src/Battle/Domain/Enums/ObjetivosItem.php` | crear | backend |
| `src/Battle/Domain/Enums/FaseCombate.php` | modificar (`player_item_target`) | backend |
| `src/Battle/Domain/UsoObjetos/EfectoItem.php` | crear | backend |
| `src/Battle/Domain/UsoObjetos/ResultadoEfectoItem.php` | crear | backend |
| `src/Battle/Domain/UsoObjetos/ResultadoUsoObjeto.php` | crear | backend |
| `src/Battle/Domain/UsoObjetos/CatalogoEfectosItem.php` | crear (44 efectos) | backend |
| `src/Battle/Domain/UsoObjetos/ServicioUsoObjetos.php` | crear | backend |
| `src/Battle/Domain/UsoObjetos/Efectos/EfectoCurarHp.php` | crear | backend |
| `src/Battle/Domain/UsoObjetos/Efectos/EfectoCurarEstado.php` | crear | backend |
| `src/Battle/Domain/UsoObjetos/Efectos/EfectoRevivir.php` | crear | backend |
| `src/Battle/Domain/UsoObjetos/Efectos/EfectoRestaurarTodo.php` | crear | backend |
| `src/Battle/Domain/UsoObjetos/Efectos/EfectoModificarStat.php` | crear | backend |
| `src/Battle/Domain/UsoObjetos/Efectos/EfectoMejorarPrecision.php` | crear | backend |
| `src/Battle/Domain/UsoObjetos/Efectos/EfectoMejorarCritico.php` | crear | backend |
| `src/Battle/Domain/UsoObjetos/Efectos/EfectoResistenciaTipo.php` | crear | backend |
| `src/Battle/Domain/ValueObjects/ResistenciasTemporales.php` | crear | backend |
| `src/Battle/Domain/ValueObjects/MultiplicadoresStats.php` | modificar (`refrescarTemporal`) | backend |
| `src/Battle/Domain/Combatiente.php` | modificar (resistencias, `restaurarUsos`, serialización) | backend |
| `src/Battle/Domain/Chain/ManejadorResistenciaTemporal.php` | crear | backend |
| `src/Battle/Domain/Chain/CadenaDanio.php` | modificar (insertar manejador) | backend |
| `src/Battle/Domain/AgregadoBatalla.php` | modificar (unión `pendingAction`) | backend |
| `src/Battle/Domain/DataTransferObjects/OpcionObjeto.php` | crear | backend |
| `src/Battle/Domain/Collections/OpcionObjetoCollection.php` | crear | backend |
| `src/Battle/App/UsarObjetoEnCombate.php` | crear | backend |
| `src/Battle/App/ObtenerOpcionesObjeto.php` | crear | backend |
| `src/Battle/Presentation/DTOAccionObjeto.php` | crear | backend |
| `app/Livewire/Combate.php` | modificar (fases/acciones) | backend |
| `app/Livewire/Presenters/BatallaVistaPresenter.php` | modificar (objetivos) | backend |
| `resources/views/livewire/partials/items-panel.blade.php` | crear | frontend |
| `resources/views/livewire/partials/moves-panel.blade.php` | modificar (incluir panel) | frontend |
| `app/Support/BattleSessionService.php` | modificar (`SESSION_VERSION` 21) | backend |

### Documentación

| Archivo | Acción | Responsable |
|---|---|---|
| `docs/adr/0001-inventario-compartido-f2-f5.md` | crear (ADR) | documenter |
| `docs/architecture.md` | modificar (módulos/forja/combate) | documenter |

---

## 6. Orden de ejecución y dependencias

### Secuencia

1. **Bloque 0 — Inventario compartido** (backend). Sin él no se implementa F2/F4/F5.
2. **F2 — Plantación/cosecha** (database → backend → frontend). Depende del Bloque 0.
3. **F3 — Bonus de hábitat y plantaciones** (backend → frontend). Depende de F2 (tabla `nest_plantings`) y del Bloque 0.
4. **F4 — Forja** (backend → frontend). Depende del Bloque 0; puede adelantarse en paralelo con F2/F3 si se aíslan archivos, pero en esta ejecución se hace después de F3 para evitar conflictos en `app/Providers/AppServiceProvider.php` y en el catálogo compartido.
5. **F5 — Combate** (backend → frontend). Depende de F4 (catálogo/efectos) y del Bloque 0.
6. **Documentación** (documenter) tras F5.
7. **Verificación estática** (orchestrator + especialistas).
8. **Revisión independiente** (reviewer).
9. **Auditoría de seguridad** (security).
10. **Informe final** (orchestrator).

### Reglas de concurrencia

- No se ejecutan dos especialistas sobre el mismo archivo a la vez.
- `app/Providers/AppServiceProvider.php` se toca en Bloque 0 y F3; secuencial.
- `src/Shared/Domain/Items/CatalogoItems.php` solo lo toca F4.
- `resources/views/habitats/show.blade.php` solo lo toca F2 (frontend).
- `app/Livewire/Combate.php`, `AgregadoBatalla.php`, `Combatiente.php`, `BattleSessionService.php` solo los toca F5.
- `routes/web.php` solo lo toca F4.
- Si F4 se paraleliza con F2/F3, el frontend de forja y el de granja son tareas separadas y no comparten archivos.

---

## 7. Criterios de aceptación

### Bloque 0 — Inventario

- [ ] `InventarioJugador` tiene una única implementación y está bindeada.
- [ ] `consumir` y `consumirVarios` fallan con 422 si falta stock y no dejan saldos negativos.
- [ ] Toda clave se valida contra `CatalogoItems`; una clave desconocida lanza `ItemDesconocido`.
- [ ] `InventarioGranja` sigue funcionando para caramelos y añade `resumenBayas()`.

### F2 — Plantación y cosecha

- [ ] Un nido de nivel N ofrece exactamente `N*2` huecos; nivel 0 no permite plantar.
- [ ] Plantar fuera de rango o sin nivel ≥ 1 devuelve 422 sin cambios.
- [ ] Plantar consume exactamente 1 baya; sin saldo devuelve 422 y no crea/actualiza la plantación.
- [ ] La clave de baya se valida contra `ClaveBaya`; una clave desconocida nunca llega a `player_inventory`.
- [ ] Dos peticiones concurrentes al mismo `(nest_id, slot)` no crean dos filas.
- [ ] Cosechar a 24/48/72 h devuelve 1/2/3 unidades, con tope 3.
- [ ] Cosechar dos veces seguidas no duplica: la segunda devuelve 422.
- [ ] La planta persiste tras cosechar y vuelve a producir al día siguiente.
- [ ] Replantar sustituye la baya, reinicia `planted_at`/`harvest_count` y la UI avisa.
- [ ] La granja devuelve `huecos_totales`, `huecos_libres`, `plantaciones`, `bayas` y `bonguri`; la UI no calcula.
- [ ] Un usuario no puede plantar/cosechar en nidos de otra granja.
- [ ] `GranjaDetalle` conserva `id`, `habitat`, `familias`, `inventario`.

### F3 — Influencia y bonus

- [ ] Un explorador cuya especie está en `pokemon_habitat` recibe el bonus; uno no nativo no.
- [ ] El flag `eventos['bonos']['del_habitat']` se calcula una vez y se reutiliza.
- [ ] Si se finaliza sin tick previo, el fallback lo calcula una vez.
- [ ] Los factores de plantación solo modifican pesos de bayas de `(user_id, habitat_id)`; no afectan a bonguris ni a otras granjas/hábitats.
- [ ] El factor por baya es `min(2.0, 1 + 0.20 * plantas)`; sin plantaciones, botín idéntico a F1.
- [ ] El resultado persistido incluye `bonos.del_habitat` y la UI muestra el hito/badge.
- [ ] No se añade auto-cosecha.

### F4 — Forja

- [ ] Existen exactamente 46 recetas y 39 resultados; `todas()` devuelve 46.
- [ ] R1 y R2 producen `pocion:pocion`; `paraResultado('pocion:pocion')` devuelve 2.
- [ ] `paraResultado('material:recipiente')` devuelve 7.
- [ ] R3–R9 consumen exactamente sus ingredientes y producen 1 unidad.
- [ ] Cada mejora/reductor/resistencia consume 1 baya + 1 recipiente.
- [ ] Si falta un ingrediente: 422 y no se descuenta nada.
- [ ] Dos peticiones concurrentes no dejan saldos negativos.
- [ ] `ListarRecetas` devuelve `puede_fabricar` correcto y una sola consulta de saldos.
- [ ] Receta inexistente → 404; `cantidad !== 1` → 422.
- [ ] `ItemCatalogo::resolve()` no devuelve “Desconocido” para las claves nuevas.
- [ ] `src/Forja/Domain` no importa Eloquent/HTTP/Blade.
- [ ] Los iconos faltantes usan fallback y no bloquean la fabricación.

### F5 — Combate

- [ ] Usar un objeto consume la acción y **no** consume movimiento.
- [ ] Usar un objeto descuenta 1 del inventario; sin stock no se ofrece y, si se fuerza, no se ejecuta ni consume acción.
- [ ] Poción/curar solo a aliados vivos (incluido el actor); revivir solo a debilitados; reductor solo a rivales vivos.
- [ ] La UI permite seleccionar aliados y rivales según el objeto, incluidos los debilitados para revivir.
- [ ] Buff +30 % dura 5 rondas y se retira; debuff −30 % ídem; reaplicar refresca sin stackear.
- [ ] Resistencia de tipo −30 % durante 5 rondas, aplicada a cualquier ataque de ese tipo.
- [ ] Crítico +1 nivel durante 5 rondas (`CRITICO_MEJORADO`).
- [ ] Restaurar Todo y Max Revivir reinician usos.
- [ ] El panel incluye 44 objetos: 6 bayas básicas + 38 fabricados.
- [ ] Oran cura 10 % y Zidra 25 %; Cheri/Peque/Meloc/Safre/Perasi curan su estado.
- [ ] `baya:sitrus` y `sitrus_berry` se distinguen y coexisten.
- [ ] El log registra “X usó Item en Y” y el efecto.
- [ ] No hay regresiones en ruta/entrenador/gimnasio/mazmorra/exploración.
- [ ] Payloads v20 siguen cargando y `resistencias` se inicializa vacío.

### Documentación

- [ ] ADR creado con contexto, decisión y consecuencias.
- [ ] `docs/architecture.md` actualizado con `src/Forja`, inventario compartido y flujo de objetos en combate.

---

## 8. Verificación requerida

Sin tests. Se ejecutará:

```bash
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --dirty --format agent
vendor/bin/deptrac analyse
php -l <cada archivo PHP nuevo/modificado>
php artisan route:list --path=forja
php artisan route:list --path=farm
php artisan view:cache && php artisan view:clear
```

Comprobaciones adicionales:

- `git diff --stat` y `git status` para confirmar que `tests/` no se toca y que solo se modifican archivos autorizados.
- `grep` de `delHabitat: false` para confirmar que no queda en el flujo final.
- `grep` de `DTOAccionBatalla`/`DTOAccionObjeto` para confirmar la unión y el `instanceof`.
- `grep` de `SESSION_VERSION`/`MINIMUM_COMPATIBLE` para confirmar 21/9.
- Conteo de `CatalogoItems::definiciones()` = 82 claves y `CatalogoRecetas::todas()` = 46.
- Revisión manual de contratos: `GranjaDetalle`, `NidoGranja`, `ContextoBotin`, `DTOAccionBatalla`/`DTOAccionObjeto`, `ResultadoRecompensas`.
- Comprobación manual de los criterios de aceptación con datos locales.

No se ejecutarán migraciones ni seeders en producción. La migración de F2 se ejecutará solo en local para verificar que aplica y revierte; si el desarrollador no lo autoriza, se validará con `migrate --pretend`.

---

## 9. Riesgos y mitigaciones

| Nivel | Riesgo | Mitigación |
|---|---|---|
| **Alto** | F5 toca el núcleo del turno | DTO separado + `instanceof` + rama aislada; no se modifica la cadena de daño salvo el manejador de resistencia; revisión manual de los 4 contextos. |
| **Alto** | Consumo de inventario en combate no transaccional con la sesión | Orden “validar → consumir → aplicar → guardar”; decremento condicional; deuda documentada. |
| **Alto** | F4/F5 introducen claves que la UI no resuelve | `CatalogoItems` como única fuente; validación en `RecetaFabricacion`; `ItemCatalogo` delega; conteo 82. |
| **Alto** | Doble plantación/cosecha | Unique `(nest_id, slot_index)`, `lockForUpdate`, recálculo tras el lock. |
| **Medio** | F3 aplica factores a bonguris u otras granjas | Factores solo en `pesosBayas()`; consulta filtrada por `(user_id, habitat_id)`; `caeBonguri()` no recibe factores. |
| **Medio** | `delHabitat` ambiguo | Se fija al explorador (`reclutado->pokemon_id`), se persiste una vez y se muestra como “Conoce el terreno”. |
| **Medio** | Buffs acumulables | `refrescarTemporal()` reemplaza el factor temporal. |
| **Medio** | `SESSION_VERSION` no invalida v20 | Decisión explícita: mínimo 9 y defaults; no se destruyen batallas. |
| **Medio** | Replantar pierde stock | Decisión de producto; la UI muestra `confirm()` y el backend reinicia. |
| **Medio** | Doble fabricación por doble clic | Locks evitan saldos negativos; UI deshabilita; sin `Idempotency-Key` (deuda). |
| **Medio** | Varias recetas comparten resultado | Agrupación por `resultado_clave` y selector de variantes con `etiqueta`. |
| **Bajo** | Iconos nuevos ausentes | Fallback `unknown.webp` y reutilización de iconos de tipo/baya. |
| **Bajo** | Payload de granja grande | Ya ocurre hoy; máximo 10 plantaciones por familia. |
| **Bajo** | Rollback con payloads v21 que contengan `DTOAccionObjeto` | Despliegue hacia delante o mantener la clase durante la ventana de rollback. |

---

## 10. Rollback

- F2: `php artisan migrate:rollback --step=1` para `nest_plantings`; revertir los archivos de F2.
- F3: revertir los cambios de `ContextoBotin`, `CalculadorBotinExploracion`, handlers y presentación; no hay migración.
- F4: revertir `src/Forja`, los enums nuevos y los cambios de `CatalogoItems`; no hay migración.
- F5: revertir los archivos de combate y `SESSION_VERSION` a 20. Si hay batallas con `DTOAccionObjeto` en sesión, se descartan o se mantiene la clase durante la ventana de rollback.
- Orden de rollback inverso al de implementación: F5 → F4 → F3 → F2 → Bloque 0.
- No se hace `git reset --hard`, `git clean` ni `git checkout --` sin aprobación.

---

## 11. Aprobación

Este plan no autoriza implementación hasta que el desarrollador emita una aprobación explícita que mencione la versión, por ejemplo:

```text
Aprobar plan v1 (F2–F5)
```

La aprobación se registrará en `approval.md` con el texto literal, la fecha y el alcance autorizado. Sin ella, la tarea permanece en `AWAITING_APPROVAL`.
