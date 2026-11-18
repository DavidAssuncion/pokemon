# TASK-056 — Plan v2: piezas de Forja por familia/nido y equipamiento de 3 slots

- **ID:** TASK-056
- **Versión:** v2
- **Fecha:** 2026-11-18 (v1: 2026-11-17)
- **Proyecto:** `/home/david/pokemon`
- **Estado:** AWAITING_APPROVAL
- **Diseño de referencia:** `.opencode/work/TASK-056/architecture.md`

> Este plan **no autoriza implementación** hasta que el desarrollador emita una aprobación explícita
> que mencione la versión. La ausencia de respuesta no es aprobación.

## 1. Objetivo

Añadir a la Forja una sección de **Armas, Armaduras y Amuletos** para las familias evolutivas con
nido construido (nivel ≥ 1), con desbloqueo por nivel de nido, stats que replican los stats base de
la especie, una pieza única que evoluciona consumiendo la anterior, hasta 3 piezas equipadas por
Pokémon reclutado y habilidades pasivas en algunas piezas.

El plan se ejecuta en tres fases. La fase 3 queda condicionada a que el desarrollador consolide o
revierta el trabajo local pendiente de `resources/views/equipos/*`.

## 2. Decisiones incluidas en esta versión

La aprobación de `plan v1` incluye las siguientes decisiones, detalladas en `architecture.md` §10.6.
Si alguna no encaja, hay que solicitar cambios **antes** de aprobar.

| # | Decisión | Valor propuesto |
|---|---|---|
| D1 | Composición del Arma | `Atk + SpA` (2 componentes), fiel al ejemplo de Gengar |
| D2 | Curva de stats por etapa | `{1: 0.50, 2: 0.75, 3+: 1.00}`, redondeo `floor` |
| D3 | Unicidad de pieza | 1 unidad por jugador y clave (inventario ∪ equipamiento) |
| D4 | Evolución del portador | La pieza permanece equipada; sin restricción especie↔pieza |
| D5 | Persistencia de los 3 slots | Tabla nueva `recruit_gear`; `obj_equipados` intacto |
| D6 | Ingredientes y cantidades | `bonguri` del hábitat ×(1+etapa) + `material:recipiente` ×1 + baya rara ×etapa + pieza anterior (etapa ≥ 2) |
| D7 | Pieza que otorga la pasiva | Amuleto de etapa 3; habilidad slot 1 de la especie |
| D8 | CP | Incluye las piezas (una consulta por listado) |
| D9 | Whitelist del inventario | Carve-out por prefijo en `InventarioJugadorEloquent`; no registrar ~450 claves en `CatalogoItems` |
| D10 | Slot ocupado al equipar | Swap: la pieza anterior vuelve al inventario |
| D11 | Varios nidos de la misma familia | Una receta por nido, **una tarjeta por familia**; modal de craft con pestañas por etapa/especie y selector de bioma |
| D12 | Familias de más de 3 etapas | Tope de nivel 5 para etapa ≥ 3 |

## 3. Alcance

### Fase F1 — Dominio, oferta, fabricación y UI de la Forja

- `CategoriaItem` gana `ARMA`, `ARMADURA` y `AMULETO`.
- `ClavePieza` valida `^(arma|armadura|amuleto):[1-9][0-9]*$`.
- Dominio `src/Forja/Domain/Piezas/**`: `TipoPieza`, `DefinicionPieza`, `RecetaPieza`,
  `PiezaDesbloqueada`, colecciones, `ReglasPiezas`, `CalculadoraStatsPieza`,
  `ResolvedorEtapasPiezas`, `CatalogoPiezas`.
- Regla de desbloqueo 1/3/5, 2/4, 3 con `ResolvedorFamiliaEvolutiva` (`multiRaiz: true`,
  `maxEtapas: null`).
- Puerto `NidosConstruidos` + adaptador `NidosConstruidosEloquent` en Granjas.
- `ListarPiezasForja` y `FabricarPieza`; contrato aditivo de `GET /api/forja/recetas` con
  `secciones`, `categoria` y campos de pieza; `POST /api/forja/fabricar` sin cambio de body.
- Carve-out de whitelist del inventario para las claves de pieza.
- UI de `resources/views/forja/index.blade.php`: sección nueva, selector de bioma/bonguri, motivos
  resueltos por backend, sin `FORJA_CATEGORIAS` ni `forjaCategoriaDeResultado`.
- `src/Forja/context.md` actualizado.

### Fase F2 — Persistencia, equipar/desequipar y batalla

- Migración `recruit_gear` (`reclutado_id`, `slot` 1-3, `item_key`; unique por slot y por pieza).
- `RecruitGear`, `EquipamientoPiezas`, `EquiparPieza`, `DesequiparPieza`, `ObtenerPiezas`.
- Endpoints `GET/PUT/DELETE /api/reclutados/{reclutado}/piezas[/{slot}]` con anti-IDOR.
- `AplicadorBonusPiezas` en `Pokemon/Domain/Stats`.
- `MapeadorPokemonBatalla`, `ConstruirEquipoJugador` y `CombateExploracion`: cargar piezas y
  colapsar bonos + `effectKeys` en el mapeo.
- `ReclutadoSerializer` expone `piezas[3]`; CP con piezas.
- Registro de pasivas en `BattleEffectServiceProvider` si aportan claves nuevas.
- `SESSION_VERSION` **no** cambia.

### Fase F3 — UI de equipamiento, CP visible y assets

- Partial nuevo `resources/views/equipos/partials/piezas-reclutado.blade.php` con 3 slots.
- Un `@include` en `modal-detalle-pokemon.blade.php` (limpio).
- Iconos propios opcionales con fallback al icono de especie.
- CP visible con piezas si no se hizo en F2.

**Condición de inicio F3:** el desarrollador debe confirmar que el WIP de
`resources/views/equipos/index.blade.php`, `partials/panel-equipos.blade.php`,
`partials/modal-editar-equipo.blade.php` y `resources/css/*` está consolidado o revertido. Hasta
entonces, F3 queda bloqueada; F1 y F2 pueden completarse.

## 4. Fuera de alcance

- Tests: no se crean, modifican ni ejecutan.
- Dependencias nuevas.
- Unificar `CatalogoItems`, `CatalogoObjetos` y `ClaveItem` (deuda registrada).
- Modelo de instancias de pieza con estado propio; la etapa es la clave.
- Niveles de pieza más allá de la etapa (1-5); se usa la etapa 1-3.
- PvP, nuevos modos de combate o cambios de balance ajenos a las piezas.
- Modificar `resources/views/equipos/index.blade.php`, `partials/panel-equipos.blade.php`,
  `partials/modal-editar-equipo.blade.php`, `src/Battle/Domain/CombatienteVista.php` o
  `resources/css/*` antes de la condición de F3.
- Modificar los cambios locales preexistentes de TASK-049 a TASK-055.

## 5. Arquitectura y contratos (resumen)

- **Dominio:** `src/Forja/Domain/Piezas/**` puro; sin Eloquent/HTTP/facades.
- **Identidad:** `tipo:{species_id}`; la evolución es una cadena de recetas que consume la clave
  anterior. Sin instancias.
- **Desbloqueo:** `ResolvedorFamiliaEvolutiva` con `multiRaiz: true`; niveles
  `3` (profundidad 1), `2×etapa` (profundidad 2), `min(5, 2×etapa−1)` (profundidad ≥ 3).
- **Stats:** Arma `Atk + SpA`; Armadura `Def + SpD + HP`; Amuleto `Speed + floor(0.5 ×
  max(Atk, Def, SpA, SpD))`; factor por etapa y `floor`.
- **Persistencia:** `player_inventory` para piezas; `recruit_gear` para los 3 slots; `obj_equipados`
  queda como objeto de combate único.
- **Batalla:** los bonos se suman al `StatsValue` base en el mapeo y las pasivas viajan en
  `effectKeys`; `DatosPokemonBatalla`, `ObjetoCombatiente`, `FabricaEfectos` y
  `ManejadorObjetosEquipados` no cambian; `SESSION_VERSION` no cambia.
- **Frontera Forja↔Granjas:** puerto `NidosConstruidos` en Forja, adaptador en Granjas, binding en
  `AppServiceProvider`.

Detalle completo, evidencia y alternativas en `architecture.md`.

## 6. Archivos y responsables

| Fase | Archivo | Acción | Responsable |
|---|---|---|---|
| F1 | `src/Shared/Domain/Items/CategoriaItem.php` | +3 casos | backend |
| F1 | `src/Shared/Domain/Items/ClavePieza.php` | crear | backend |
| F1 | `src/Shared/Infra/Inventario/InventarioJugadorEloquent.php` | carve-out de whitelist | backend |
| F1 | `src/Forja/Domain/Piezas/**` | crear dominio | backend |
| F1 | `src/Forja/Domain/Ports/NidosConstruidos.php` | crear puerto | backend |
| F1 | `src/Granjas/Infra/Nidos/NidosConstruidosEloquent.php` | crear adaptador | backend |
| F1 | `src/Forja/App/ListarPiezasForja.php`, `FabricarPieza.php` | crear casos de uso | backend |
| F1 | `src/Forja/Infra/Repositorios/LectorFamiliasPiezas.php` | crear read model | backend |
| F1 | `src/Forja/App/DataTransferObjects/RecetaDetalle.php`, `ResultadoFabricacion.php` | campos aditivos | backend |
| F1 | `src/Forja/Infra/Controllers/ForjaController.php` | enrutar fabricación de pieza | backend |
| F1 | `app/Providers/AppServiceProvider.php` | binding del puerto | backend |
| F1 | `resources/views/forja/index.blade.php` | sección nueva y contrato backend | frontend |
| F1 | `src/Forja/context.md` | documentar | documenter |
| F2 | `database/migrations/2026_11_17_000001_create_recruit_gear_table.php` | crear migración | database |
| F2 | `app/Models/RecruitGear.php` | crear modelo | backend |
| F2 | `src/Equipos/Domain/ValueObjects/EquipamientoPiezas.php` | crear VO | backend |
| F2 | `src/Equipos/App/Piezas/{EquiparPieza,DesequiparPieza,ObtenerPiezas}.php` | crear casos de uso | backend |
| F2 | `src/Equipos/Infra/Requests/EquiparPiezaRequest.php` | crear FormRequest | backend |
| F2 | `src/Equipos/Infra/Controllers/PiezasReclutadoController.php` | crear controlador | backend |
| F2 | `routes/player.php` | 3 rutas | backend |
| F2 | `src/Pokemon/Domain/Stats/AplicadorBonusPiezas.php` | crear | backend |
| F2 | `src/CombateEntrenadores/App/MapeadorPokemonBatalla.php`, `ConstruirEquipoJugador.php` | cargar y colapsar piezas | backend |
| F2 | `src/Exploraciones/App/CombateExploracion.php` | cargar y colapsar piezas | backend |
| F2 | `app/Support/ReclutadoSerializer.php`, `app/Support/Pc/CalculadoraPcReclutado.php` | `piezas[]` y CP | backend |
| F2 | `app/Providers/BattleEffectServiceProvider.php` | registrar pasivas si aplica | backend |
| F3 | `resources/views/equipos/partials/piezas-reclutado.blade.php` | crear partial | frontend |
| F3 | `resources/views/equipos/partials/modal-detalle-pokemon.blade.php` | 1 `@include` | frontend |
| F3 | `resources/css/components.css` | solo si falta un patrón visual | frontend |
| F3 | `public/images/items/pieza/**` | assets opcionales | frontend |

## 7. Orden de ejecución y dependencias

1. **F1.1** Dominio de piezas + `ClavePieza` + `CategoriaItem` + carve-out de inventario.
2. **F1.2** Puerto `NidosConstruidos` + adaptador + read model de familias.
3. **F1.3** `ListarPiezasForja`, `FabricarPieza`, contrato aditivo y controller.
4. **F1.4** UI de Forja + documentación.
5. **F1.5** Verificación estática, revisión y seguridad.
6. **F2.1** Migración `recruit_gear` + modelo + VO.
7. **F2.2** Equipar/desequipar + endpoints + serializer.
8. **F2.3** Batalla + CP + pasivas.
9. **F2.4** Verificación estática, revisión y seguridad.
10. **F3.1** (tras confirmación del WIP) Partial de equipamiento + include + CP visible/assets.
11. **F3.2** Verificación estática y revisión.

Dependencias: F2 depende de F1; F3 depende de F2 y de la confirmación del WIP. F1 no toca
`src/Battle` ni `resources/views/equipos/*`.

## 8. Criterios de aceptación

### F1

1. `GET /api/forja/recetas` devuelve `secciones[]` y las 46 recetas existentes sin cambios de
   campos/valores, más las piezas de familias con nido ≥ 1.
2. Un jugador sin nidos recibe `secciones` y cero recetas de pieza; la vista muestra el estado vacío.
3. Con nido de Gastly a nivel 1 aparecen las piezas de Gastly; las de Haunter/Gengar figuran con
   `desbloqueada: false` y `motivo` de nido insuficiente.
4. `POST /api/forja/fabricar` con una receta de pieza descuenta bonguri/recipiente e incrementa la
   clave en `player_inventory`.
5. Intentar la pieza de etapa 3 con nido 1 devuelve 422 y no modifica el inventario.
6. Intentar la pieza de etapa 3 con nido 5 y sin la anterior devuelve 422.
7. La vista no contiene `FORJA_CATEGORIAS` ni `forjaCategoriaDeResultado`; pinta la sección con datos
   del backend.
8. `php artisan route:list --path=forja` sigue mostrando 3 rutas.
9. La Forja no toca `src/Battle` ni `resources/views/equipos/*`.

### F2

1. `PUT /api/reclutados/{id}/piezas/1` con `arma:94` descuenta 1 del inventario y crea la fila;
   `DELETE` la devuelve.
2. Equipar la misma pieza en dos slots del mismo reclutado devuelve 422.
3. Equipar en un slot ocupado hace swap y devuelve la anterior al inventario.
4. Reclutado ajeno → 404; slot 0 o 4 → 422.
5. Un combate de ruta con `arma:94` equipada aplica los stats sumados sin cambiar
   `DatosPokemonBatalla`.
6. La pasiva del Amuleto de etapa 3 aparece en `effectKeys` y produce su efecto.
7. `SESSION_VERSION` sigue en 21; una batalla guardada antes del despliegue sigue cargando.
8. El objeto de combate (`obj_equipados[0]`) sigue funcionando en paralelo.
9. `Set` completo de Gengar: Arma 65 Atk + 130 SpA; Armadura 60 Def + 75 SpD + 60 HP; Amuleto 110
   Vel + 65 SpA.
10. El CP mostrado incluye las piezas.

### F3

1. El modal de detalle muestra 3 slots con nombre, tipo y stats.
2. Equipar/desequipar actualiza la tarjeta sin recargar y emite `inventario:actualizado`.
3. El CP mostrado incluye las piezas.
4. `resources/views/equipos/index.blade.php` y `partials/modal-editar-equipo.blade.php` no aparecen
   en el diff de la fase.
5. `php artisan view:cache` compila sin errores.

## 9. Verificación requerida

Sin tests. Verificación estática y comprobación manual de criterios:

```bash
php -l <archivos PHP tocados>
vendor/bin/pint --dirty --format agent
composer run analyse        # PHPStan L6; no añadir entradas al baseline
composer run analyse:hard   # PHPStan L8; no añadir entradas al baseline
composer run arch           # deptrac report-only; no aumentar violaciones
php artisan view:cache
npm run build
php artisan route:list --path=forja
php artisan route:list --path=piezas
git diff --check
```

Comprobación manual (sin mutar datos de producción): lectura del diff, revisión de contratos,
verificación de que las recetas existentes mantienen su shape, que las claves nuevas están en el
carve-out, que la UI no calcula reglas y que los archivos con WIP no aparecen en el diff de F1/F2.
La validación funcional con datos locales, si el desarrollador la autoriza, se hará sobre una base
de datos de desarrollo, nunca producción.

## 10. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Balance por apilado (Gengar +195 SpA base) | alto | `FACTOR_ETAPA` 0.5/0.75/1.0; medir con CP; D2 revisable |
| Armadura con doble efecto (barrera + fórmula) | alto | probar un combate real antes de cerrar D2; factor propio si desborda |
| Whitelist del inventario | alto | carve-out en el primer commit de F1, antes de exponer recetas |
| Conflicto con cambios locales | alto | F1/F2 solo archivos limpios; F3 bloqueada hasta consolidar WIP |
| Cadenas sin aristas → profundidad 1 | medio | comportamiento seguro (nivel 3); documentar y verificar datos |
| Volumen de recetas | medio | solo familias con nido ≥ 1; `saldos` en una consulta |
| Pasiva anulable por supresión de habilidad | bajo | aceptado como semántica de "pasiva"; no se registra en `ClaveItem` |
| Documentación desactualizada (coste de nido, SESSION_VERSION) | bajo | corregir en el cierre |

## 11. Rollback

- **F1:** revertir commits; no hay migración ni cambio de esquema. Las claves `arma:*` que hubieran
  quedado en inventario son inertes.
- **F2:** `migrate:rollback` de `create_recruit_gear`; revertir serializer y los 2 call sites. Las
  batallas en curso no se ven afectadas porque el payload no cambia.
- **F3:** revertir el partial y el `@include`; sin efecto en datos.

## 12. Aprobación

Estado: **PENDIENTE v2**.

Para autorizar la implementación, el desarrollador debe responder explícitamente con una fórmula
que incluya la versión, por ejemplo:

- `APROBADO plan v2`
- `Implementa el plan v2`
- `Apruebo la versión 2 del plan`

La aprobación se registrará en `approval.md` con el texto literal. La aprobación de v1 queda como
histórica y no cubre el cambio de UI descrito en §13.

## 13. Cambios v2 — presentación de piezas por familia

### 13.1 Origen

Petición del desarrollador (2026-11-18): «Solo mostramos uno de la familia, pulsar en el abre modal
de craft con las pestañas de la familia y la posibilidad de buildear los 3 tipos de item».

### 13.2 Comportamiento

1. La sección `piezas` de la Forja muestra **una tarjeta por familia evolutiva** (no una por tipo ni
   por etapa). La clave de agrupación es `familia.evolution_chain_id`; el nombre y el icono salen de
   `familia.nombre` y `familia.primer_integrante_id` (menor `species_id`).
2. La tarjeta resume: nombre de la familia, número de biomas/nidos de la familia, rango de etapas y
   cuántas recetas son fabricables en ese momento. Sin stats ni ingredientes en la tarjeta.
3. Pulsar la tarjeta (o Enter/Espacio) abre un **modal de craft** accesible (`role="dialog"`,
   `aria-modal="true"`, título asociado, cierre con Escape, foco inicial y retorno de foco).
4. El modal muestra:
   - Cabecera con la familia y un **selector de bioma** cuando la familia tiene más de un nido
     (opciones = `habitat.nombre` + `bonguri.nombre`); el seleccionado aplica a todas las pestañas.
   - **Pestañas por especie/etapa de la familia**, ordenadas por `especie.etapa` ascendente. Cada
     pestaña usa `role="tab"`/`role="tabpanel"` y es navegable con teclado.
   - Dentro de cada pestaña, **los 3 tipos de ítem** (Arma, Armadura, Amuleto) de esa especie y nido,
     cada uno con su nombre, icono, stats, habilidad (si aplica), pieza anterior, ingredientes con
     stock y el botón **Fabricar** habilitado solo si `puede_fabricar`; si no, el `motivo_texto`.
5. Fabricar usa `POST /api/forja/fabricar` con la `clave` de la receta concreta (tipo + especie +
   bioma). Tras el 200, la UI actualiza los saldos/estado de las recetas afectadas sin cerrar el
   modal, y refleja el cambio en la tarjeta de la familia al cerrar (o recarga la lista si el
   backend devuelve el estado completo).
6. Estados: sin nidos → estado vacío existente; familia con todas las etapas bloqueadas → tarjeta
   atenuada con el motivo agregado; error de carga → mensaje existente.

### 13.3 Contrato

**Sin cambios de backend.** `GET /api/forja/recetas` ya expone por receta:
`familia.evolution_chain_id`, `familia.nombre`, `familia.primer_integrante_id`, `especie.id`,
`especie.nombre`, `especie.etapa`, `tipo_pieza`, `nido`, `habitat`, `bonguri`, `stats`, `habilidad`,
`pieza_anterior`, `puede_fabricar`, `motivo`, `motivo_texto`, `ingredientes` y `etiqueta`.

### 13.4 Archivos y responsable

| Archivo | Cambio | Responsable |
|---|---|---|
| `resources/views/forja/index.blade.php` | agrupación por familia, tarjeta única y modal de craft con pestañas | `frontend` |

No se toca `resources/css/**` (se reutilizan los componentes y tokens existentes). Si falta un
patrón visual, se resuelve con utilidades y componentes ya presentes; cualquier necesidad de CSS se
elevará antes de F3.

### 13.5 Criterios de aceptación v2 (sustituyen a los de UI de F1)

1. Con una familia de 3 etapas y 1 nido se muestra **una sola tarjeta** de familia en `piezas`.
2. La tarjeta no contiene botones de fabricar; todo el craft vive en el modal.
3. El modal muestra 3 pestañas (Gastly/Haunter/Gengar en el ejemplo) y, en cada una, las 3 recetas
   Arma/Armadura/Amuleto.
4. Con dos nidos de la misma familia aparece el selector de bioma y cambia las recetas de todas las
   pestañas.
5. Una receta no fabricable muestra `motivo_texto` y el botón deshabilitado con `aria-disabled`.
6. Fabricar una receta actualiza su estado y sus saldos sin recargar la página completa.
7. El modal se cierra con Escape y devuelve el foco a la tarjeta que lo abrió.
8. La vista no calcula stats, ingredientes, gates ni motivos: solo presenta datos del backend.
9. `php artisan view:cache` y `node --check` del JS inline pasan; sin `x-html` ni estilos inline.
10. No se modifican `src/Forja/**` ni el contrato JSON en esta fase.

### 13.6 Riesgos

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Rehacer parte de la UI ya revisada de F1 | medio | cambio acotado a una vista; re-revisión de `frontend` + `reviewer` |
| Estado del modal y del selector de bioma se desincroniza tras fabricar | medio | recalcular grupos desde `recetas` en cada refresco; claves estables por familia |
| Accesibilidad de pestañas y modal | medio | roles ARIA, foco gestionado y navegación por teclado verificadas por lectura |
