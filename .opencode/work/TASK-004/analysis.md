# TASK-004 — Análisis

> Resultados literales devueltos por los dos análisis de `analyst`. El
> orchestrator no altera conclusiones. La síntesis y el modelo de balance
> corresponden al `architect` y se registrarán en `architecture.md`.

---

# Parte A — Inventario de sumideros de la economía de objetos

> Análisis **read-only**. No se modificó ningún archivo ni se ejecutaron migraciones/seeders/escrituras. La única consulta ejecutada fue un `SELECT` agregado de solo lectura sobre `type_path_nodes` vía `php artisan tinker`. Los datos de nodos dependen del estado actual de la BD local (línea base con WIP sin commitear, rama `master`).

## A.1 Resumen ejecutivo

La economía de caramelos usa **tres claves canónicas** en `player_inventory` (`app/Support/ItemCatalogo.php:27-40`), con tabla única y `unique(user_id, item_key)` (`database/migrations/2026_08_29_000007_create_player_inventory_table.php:18-22`):

| Clave | Formato | Consumidores verificados |
|---|---|---|
| `familia:{evolution_chain_id}` | familia evolutiva | nido, refugio, complemento EV |
| `ev:{stat}` (1-6) | stat | complemento EV, caramelos EV a reclutado |
| `tipo:{slug}` | tipo elemental | desbloqueo de nodo de Senda, exp de Senda, exp de evolución de reclutado |

**Se localizan 5 sumideros** (7 acciones de gasto): nodo de Senda, exp de Senda, nido, refugio, complemento EV, caramelo de tipo a reclutado, caramelo EV a reclutado.

**Cifras clave del inventario actual:**

| Concepto | Valor | Fuente |
|---|---|---|
| Coste de nodo de Senda por nivel 1-5 | 10 / 25 / 50 / 150 / 400 | `app/Support/SendaCostes.php:14-20` |
| Nodos totales en BD | **639** | SELECT sobre `type_path_nodes` |
| Coste total de desbloquear todos los nodos | **57.920** caramelos de tipo | cálculo sobre datos BD |
| Exp por caramelo de tipo (Senda) | **100** | `app/Support/SendaProgreso.php:32` |
| Coste de llevar una Senda a nivel 5 (solo exp) | **1.250** caramelos de tipo | `SendaProgreso.php:39-45` |
| Nido a 5 | **1.675** caramelos de familia | `CalculadoraBonosGranja.php:91-101` |
| Refugio a 5 | **3.475** caramelos de familia | `CalculadoraBonosGranja.php:103-113` |
| Complemento a 5 (por stat) | **4.032** EV + **1.344** familia | `CalculadoraBonosGranja.php:115-132` |
| Caramelo EV a reclutado | **+10 EV**, tope **255** | `ReclutadoController.php:156,184,147-151` |
| Complemento EV de granja | hasta **512 EV**, retroactivo a la familia | `AplicarEvsFamilia.php:30` |

**Incoherencia principal:** conviven **dos topes de EV distintos** (255 para caramelos individuales, 512 para complementos de granja) sobre el mismo campo `reclutados.evs`. Con un complemento de nivel ≥3 (256 EV), el reclutado queda por encima del tope individual y **rechaza cualquier caramelo EV** (`ReclutadoController.php:147-151`).

## A.2 Mapa de sumideros

| ID | Acción | Caramelo consumido | Cantidad | Fuente de verdad | Persistencia | Endpoint |
|---|---|---|---|---|---|---|
| S1 | Desbloquear nodo de Senda | `tipo:{tipo}` | 10/25/50/150/400 según nivel del nodo | **Constante** `SendaCostes.php:14-20` | `player_move_unlocks` | `POST /sendas/desbloquear` (`routes/player.php:67`) |
| S2 | Convertir caramelos en exp de Senda | `tipo:{tipo}` | N unidades × 100 exp | **Constante** `SendaProgreso.php:32` | `player_type_paths.exp/level` | `POST /sendas/experiencia` (`routes/player.php:68`) |
| S3 | Mejorar nido | `familia:{chain}` | 25/75/225/450/900 | **Constante** `CalculadoraBonosGranja.php:91-101` | `nests.level` | `POST /api/habitats/{h}/farm/nests/{c}/upgrade` (`routes/granjas.php:9-10`) |
| S4 | Mejorar refugio | `familia:{chain}` | 25/75/225/450/2700 | **Constante** `CalculadoraBonosGranja.php:103-113` | `shelters.level` | `POST .../shelters/{c}/upgrade` (`routes/granjas.php:11-12`) |
| S5 | Mejorar complemento EV | `ev:{stat}` **+** `familia:{chain}` | `ev*3` y `ev` por nivel | **Constante** `CalculadoraBonosGranja.php:115-132` | `complements.level` | `POST .../complements/{stat}/upgrade` (`routes/granjas.php:15-16`) |
| S6 | Caramelos de tipo a reclutado (exp de evolución) | `tipo:{tipo}` | 1 caramelo = +100 exp de ese tipo | **Constante** `ReclutadoController.php:105,113` | `reclutados.exp` (JSON) | `POST /reclutado/{id}/dar-caramelo` (`routes/player.php:52`) |
| S7 | Caramelos EV a reclutado | `ev:{stat}` | 1 caramelo = +10 EV | **Constante** `ReclutadoController.php:184` | `reclutados.evs` (JSON) | `POST /reclutado/{id}/dar-evs` (`routes/player.php:53`) |

**No son sumideros (verificado):**
- Reclutamiento: no consume caramelos (`app/Http/Controllers/ReclutamientoController.php:27-67`).
- Descarte de reclutables: **genera** caramelos de familia (`ReclutamientoController.php:113-142`).
- Mazmorras: sin acceso a `PlayerInventory` (`grep` sobre `src/Mazmorras` sin resultados).
- Huevos: sin consumo de inventario (`src/Huevos/`, sin referencias a `PlayerInventory`).
- Objetos de equipo: `ObjetosEquipo` es un mapa `slot => item_key` de strings, sin inventario ni consumo (`src/Equipos/Domain/ValueObjects/ObjetosEquipo.php:10-18`).
- Batallas/exploraciones: generan caramelos (`src/Exploraciones/App/PersistirRecompensas.php:86-87`), no los gastan.

## A.3 Tablas de costes por sistema

### A.3.1 Sendas — coste por nodo

Constante de código, **no persistida**: el propio archivo lo declara (`SendaCostes.php:8-10`). El nivel del nodo sí está en BD (`type_path_nodes.required_level`, `database/migrations/2026_10_30_162640_create_type_path_nodes_table.php:22`), pero se **calcula por código** al sembrar (`SendaNiveles::para`, `app/Support/SendaNiveles.php:26-41`; inserción en `database/seeders/TypePathNodeSeeder.php:54-66`).

| Nivel requerido | Coste unitario | Acumulado 1 nodo por nivel |
|---|---|---|
| 1 | 10 | 10 |
| 2 | 25 | 35 |
| 3 | 50 | 85 |
| 4 | 150 | 235 |
| 5 | 400 | **635** |

`SendaCostes::coste()` **clampea** cualquier nivel fuera de 1-5 (`SendaCostes.php:22-27`); `ArbolSendas` también clampea antes (`ArbolSendas.php:123`).

**Regla de asignación de nivel** (`SendaNiveles.php`):

| Criterio | Regla | Línea |
|---|---|---|
| Ajustes manuales | `ember`=1, `flamethrower`=3, `fire-blast`=5, `will-o-wisp`=2 | `:19-24` |
| Dañino por potencia | ≤40→1, ≤60→2, ≤80→3, ≤100→4, >100→5 | `:43-52` |
| Estado / potencia 0 | `moves.nivel_requerido` ≤20→1, ≤35→2, ≤50→3, ≤70→4, >70→5 | `:54-63` |

> El nivel efectivo depende de `moves.base_power`, `moves.category` y `moves.nivel_requerido` (datos de BD), no solo de constantes.

### A.3.2 Sendas — distribución real de nodos y coste por tipo

Consulta de solo lectura sobre `type_path_nodes` (639 filas). Tipos según `App\Enums\TipoEnum` (`app/Enums/TipoEnum.php:9-27`).

| Tipo | Nodos | N1 | N2 | N3 | N4 | N5 | Coste nodos |
|---|---|---|---|---|---|---|---|
| 1 Normal | 122 | 57 | 19 | 28 | 9 | 9 | 7.395 |
| 2 Lucha | 43 | 13 | 6 | 10 | 9 | 5 | 4.130 |
| 3 Volador | 20 | 5 | 5 | 3 | 3 | 4 | 2.375 |
| 4 Veneno | 28 | 9 | 7 | 7 | 4 | 1 | 1.615 |
| 5 Tierra | 25 | 8 | 4 | 3 | 8 | 2 | 2.330 |
| 6 Roca | 19 | 6 | 4 | 3 | 3 | 3 | 1.960 |
| 7 Bicho | 21 | 6 | 5 | 6 | 3 | 1 | 1.335 |
| 8 Fantasma | 20 | 7 | 5 | 5 | 2 | 1 | 1.145 |
| 9 Acero | 29 | 5 | 8 | 6 | 5 | 5 | 3.300 |
| 10 Fuego | 38 | 3 | 5 | 11 | 8 | 11 | 6.305 |
| 11 Agua | 38 | 9 | 4 | 12 | 7 | 6 | 4.240 |
| 12 Planta | 48 | 13 | 10 | 13 | 3 | 9 | 5.080 |
| 13 Eléctrico | 34 | 8 | 4 | 9 | 7 | 6 | 4.080 |
| 14 Psíquico | 51 | 12 | 5 | 18 | 11 | 5 | 4.795 |
| 15 Hielo | 25 | 6 | 4 | 10 | 4 | 1 | 1.660 |
| 16 Dragón | 22 | 5 | 3 | 3 | 7 | 4 | 2.925 |
| 17 Siniestro | 34 | 6 | 13 | 9 | 6 | **0** | 1.735 |
| 18 Hada | 22 | 9 | 3 | 4 | 5 | 1 | 1.515 |
| **Total** | **639** | **187** | **114** | **160** | **104** | **74** | **57.920** |

### A.3.3 Nido — `CalculadoraBonosGranja::costeNido`

`src/Granjas/Domain/CalculadoraBonosGranja.php:91-101`. Consumo en `src/Granjas/App/MejorarNido.php:53-59`.

| Nivel destino | Coste | Acumulado |
|---|---|---|
| 1 | 25 | 25 |
| 2 | 75 | 100 |
| 3 | 225 | 325 |
| 4 | 450 | 775 |
| 5 | 900 | **1.675** |

### A.3.4 Refugio — `CalculadoraBonosGranja::costeRefugio`

`CalculadoraBonosGranja.php:103-113`. Consumo en `src/Granjas/App/MejorarRefugio.php:53-59`.

| Nivel destino | Coste | Acumulado |
|---|---|---|
| 1 | 25 | 25 |
| 2 | 75 | 100 |
| 3 | 225 | 325 |
| 4 | 450 | 775 |
| 5 | 2.700 | **3.475** |

### A.3.5 Complemento EV — `CalculadoraBonosGranja::costeComplemento`

`CalculadoraBonosGranja.php:115-120`: `CosteCaramelos(ev: evComplemento(nivel)*3, familia: evComplemento(nivel))`.
`evComplemento` es **valor absoluto acumulado** por nivel (`:122-132`); la mejora aplica solo el delta (`src/Granjas/App/MejorarComplemento.php:81-83`).

| Nivel | EV acumulado del complemento | Delta EV aplicado | Coste EV (caramelos) | Coste familia | Acum. EV | Acum. familia |
|---|---|---|---|---|---|---|
| 1 | 64 | 64 | 192 | 64 | 192 | 64 |
| 2 | 128 | +64 | 384 | 128 | 576 | 192 |
| 3 | 256 | +128 | 768 | 256 | 1.344 | 448 |
| 4 | 384 | +128 | 1.152 | 384 | 2.496 | 832 |
| 5 | 512 | +128 | 1.536 | 512 | **4.032** | **1.344** |

Fórmula: `coste_ev(n) = 3 · E(n)`, `coste_familia(n) = E(n)`, con `E(n) ∈ {64, 128, 256, 384, 512}`.
Suma de deltas = 512 = tope aplicado en `AplicarEvsFamilia` (`:30`).

### A.3.6 Exp de evolución de reclutado (caramelos de tipo)

- 1 caramelo de tipo = **100 exp de ese tipo** (`ReclutadoController.php:105,113`).
- Umbral por nivel: `10·(L+1)³ − 10·L³ = 30L² + 30L + 10` (`src/Reclutamiento/App/ServicioEvolucion.php:41-45` + `src/Shared/Domain/NivelHelper.php:47-50`).
- El umbral se exige **por cada tipo del Pokémon destino** (`ServicioEvolucion.php:100-112` y `:137-139`), y la exp es independiente por tipo (`expTipo($tipo)`).
- La evolución **consume** la exp de cada tipo requerido (`ServicioEvolucion.php:276-281`; `ReclutadoController.php:229-232`).

| Nivel del reclutado | Umbral exp (por tipo) | Caramelos necesarios (por tipo) |
|---|---|---|
| 1 | 70 | 1 |
| 2 | 190 | 2 |
| 3 | 370 | 4 |
| 4 | 610 | 7 |
| 5 | 910 | 10 |
| 10 | 3.310 | 34 |
| 15 | 7.210 | 73 |
| 20 | 12.610 | 127 |
| 25 | 19.510 | 196 |
| 30 | 27.910 | 280 |
| 40 | 49.210 | 493 |
| 50 | 76.510 | 766 |
| 70 | 149.110 | 1.492 |
| 100 | 303.010 | 3.031 |

`cantidad` máxima por petición: 100 caramelos (`ReclutadoController.php:40`). Se recorta a `ceil(faltante/100)` (`:76-77`), así que **no se puede sobrepasar el umbral por esta vía**.

### A.3.7 Caramelos EV a reclutado

- 1 caramelo = **+10 EV** al stat (`ReclutadoController.php:184`).
- Tope: **255** (`:147-151`), y `cantidad` se recorta a `max(1, ceil((255 − actual)/10))` (`:156-157`).
- Máximo 100 caramelos por petición (`:132`).

## A.4 Coste total de progresión

### A.4.1 Senda completa (por tipo)

Para desbloquear nodos de nivel N se exige `nivel_senda >= N` (`ArbolSendas.php:126`; `SendasController.php:80-84`). Umbrales de exp (`SendaProgreso.php:39-45`):

| Nivel Senda | Exp umbral | Caramelos de tipo equivalentes (a 100 exp) |
|---|---|---|
| 1 | 0 | 0 |
| 2 | 15.625 | 157 |
| 3 | 31.250 | 313 |
| 4 | 62.500 | 625 |
| 5 | 125.000 | **1.250** |

El nivel 5 cubre hasta 500.000 de presentación (`SendaProgreso.php:27`), pero el gasto **no tiene tope**: la exp real se sigue acumulando (`SendaProgreso.php:12-15`; `SendasController.php:168-170`).

| Tipo | Coste nodos | Exp a N5 | **Total** |
|---|---|---|---|
| 1 Normal | 7.395 | 1.250 | 8.645 |
| 2 Lucha | 4.130 | 1.250 | 5.380 |
| 3 Volador | 2.375 | 1.250 | 3.625 |
| 4 Veneno | 1.615 | 1.250 | 2.865 |
| 5 Tierra | 2.330 | 1.250 | 3.580 |
| 6 Roca | 1.960 | 1.250 | 3.210 |
| 7 Bicho | 1.335 | 1.250 | 2.585 |
| 8 Fantasma | 1.145 | 1.250 | 2.395 |
| 9 Acero | 3.300 | 1.250 | 4.550 |
| 10 Fuego | 6.305 | 1.250 | 7.555 |
| 11 Agua | 4.240 | 1.250 | 5.490 |
| 12 Planta | 5.080 | 1.250 | 5.330 |
| 13 Eléctrico | 4.080 | 1.250 | 5.330 |
| 14 Psíquico | 4.795 | 1.250 | 6.045 |
| 15 Hielo | 1.660 | 1.250 | 2.910 |
| 16 Dragón | 2.925 | 1.250 | 4.175 |
| 17 Siniestro | 1.735 | **625** (no hay nodos N5) | 2.360 |
| 18 Hada | 1.515 | 1.250 | 2.765 |
| **Total 18 Sendas** | **57.920** | **21.875** | **79.795** |

> Nota: el nivel de Senda es **compartido por toda la cuenta** (`player_type_paths` unique `(user_id, type_id)`, migración `2026_10_30_162641`), y el desbloqueo de nodo también es de cuenta (`ArbolSendas.php:20-24`; `player_move_unlocks` unique `(user_id, move_id)`, migración `2026_10_29_204322:22`). No se paga dos veces por reclutado.

### A.4.2 Granja — por familia y hábitat

La granja es **por jugador y hábitat** (`farms` unique `(user_id, habitat_id)`, migración `2026_10_26_000001_create_farm_tables.php:19-24`), y nido/refugio/complemento cuelgan de la granja (`:26-54`). Por tanto el coste se **repite por cada hábitat** donde se quiera desarrollar la misma familia.

| Concepto | Caramelos familia | Caramelos EV |
|---|---|---|
| Nido a 5 | 1.675 | — |
| Refugio a 5 | 3.475 | — |
| 1 complemento a 5 | 1.344 | 4.032 |
| **Familia completa (1 stat)** | **6.494** | **4.032** |
| **Familia completa (6 stats)** | **13.214** | **24.192** |

### A.4.3 Reclutado — coste de evolución

Coste por tipo requerido según nivel (tabla A.3.6), multiplicado por el número de tipos del destino (1 o 2). Ejemplo: evolución de doble tipo a nivel 5 = 10 caramelos de cada tipo = 20 caramelos.

## A.5 EV y topes

| Vía | Aporte | Tope | Evidencia |
|---|---|---|---|
| Caramelo EV individual | +10 EV por caramelo | **255** por stat; rechaza 422 al alcanzarlo | `ReclutadoController.php:156,184,147-151` |
| Complemento de granja | +64/+64/+128/+128/+128 (total 512) | **512** por stat, retroactivo a toda la familia | `CalculadoraBonosGranja.php:122-132`; `AplicarEvsFamilia.php:30` |

**¿Se desperdician caramelos al llegar al tope?** Sí, parcialmente:

1. **Redondeo `ceil`**: con 250 EV, `maxPorEv = ceil(5/10) = 1` (`ReclutadoController.php:156`), se consume 1 caramelo y se aplica `min(255, 250+10) = 255` (`:184`) → **5 EV perdidos**. El desperdicio es de hasta 9 EV por operación final, nunca más (el recorte impide pasarse de 255 con varios caramelos).
2. **Bloqueo por complementos**: si el reclutado ya tiene ≥255 EV (posible desde complemento nivel 3 = 256), `darEvs` devuelve 422 y **los caramelos EV no se pueden usar en ese reclutado** (`:147-151`). No hay conversión ni consumo alternativo.
3. **Complementos**: el delta nunca supera 512, y 512 es exactamente la suma de deltas, por lo que **no hay desperdicio** en esa vía (salvo que existiera un EV previo >512, que el clamp impediría).

**Observación de coste de oportunidad (dato, no propuesta):** un caramelo EV vale +10 EV sobre un reclutado (`:184`), pero en complementos se cobra **1 caramelo por 1 punto** del nivel EV (`CalculadoraBonosGranja.php:119`), es decir 192 caramelos por 64 EV familiares en el nivel 1. La unidad "EV" del coste de complemento y la unidad "EV" del aporte individual **no son equivalentes**.

## A.6 Requisitos y validaciones

### A.6.1 S1 — Desbloquear nodo (`SendasController.php:48-129`)
- Validación: `slug` string ≤100, `tipo` string ≤30 (`:50-53`).
- El movimiento debe existir y pertenecer al tipo (`:60-67`).
- Nodo debe existir en el árbol (`:70-74`).
- No debe estar ya desbloqueado (`:76-78`) y `firstOrCreate` protege la carrera (`:101-110`).
- `nivel_senda >= nivel_requerido` (`:80-84`).
- Stock suficiente, con `lockForUpdate` dentro de `DB::transaction` (`:89-115`).
- Sin descuentos ni bonificaciones.

### A.6.2 S2 — Exp de Senda (`SendasController.php:131-194`)
- `cantidad` restringida a `1, 10, 100` (`:135`).
- Consume `min(cantidad, stock)` (`:159`), por lo que **nunca cobra de más**.
- `lockForUpdate` + transacción (`:147-179`).
- Actualiza `exp` y recalcula `level` (`:168-170`).
- **Sin tope de gasto**: se puede seguir consumiendo por encima de 500.000 exp sin beneficio adicional (`SendaProgreso.php:57-59,103-116`).

### A.6.3 S3/S4 — Nido y refugio (`MejorarNido.php:25-61`; `MejorarRefugio.php:24-61`)
- La familia debe estar asignada al hábitat (`FamiliaEnHabitat.php:16-28`).
- Nivel máximo 5 → `ViolacionReglaNegocio` (`MejorarNido.php:49-51`; `MejorarRefugio.php:49-51`).
- Coste 0 (nivel fuera de rango) → excepción (`:53-56` en ambos).
- `lockForUpdate` sobre nido/refugio (`:44`) y sobre inventario (`InventarioGranja.php:105-109`).
- Todo dentro de `DB::transaction` (`:29`).
- Creación implícita a nivel 0 si no existe (`MejorarNido.php:36-42`; `MejorarRefugio.php:35-42`).

### A.6.4 S5 — Complemento (`MejorarComplemento.php:28-85`)
- Stat válido (`:32-35`).
- Refugio construido, nivel ≥1 (`:45-47`).
- Sin condiciones insalubres (`:49-51`).
- Nivel máximo 5 (`:70-72`).
- Consume **primero EV y después familia** (`:76-77`); la transacción revierte si falta la segunda (`:37`).
- Aplica delta EV a todos los reclutados de la familia (`:81-83`; `AplicarEvsFamilia.php:16-38`).
- Sin comprobación de coste ≤ 0 (a diferencia de nido/refugio); con nivel ≥5 ya se ha cortado antes.

### A.6.5 S6 — Caramelo de tipo a reclutado (`ReclutadoController.php:35-116`)
- Reclutado no en exploración (`:45-49`).
- Debe existir evolución (`:52-56`).
- El tipo debe ser uno de los requeridos por el destino (`:58-62`).
- No gastar si ya se alcanzó el umbral (`:68-74`).
- `cantidad` 1-100 (`:40`); recorte a `ceil(faltante/100)` (`:77`).
- `lockForUpdate` + transacción en el consumo (`:79-98`).

### A.6.6 S7 — Caramelo EV a reclutado (`ReclutadoController.php:128-197`)
- Stat en `hp,atk,def,spAtk,spDef,speed` (`:131`).
- No en exploración (`:135-139`).
- Rechazo si el stat ya está a 255 (`:147-151`).
- `cantidad` 1-100 (`:132`), recorte por tope (`:156-157`).
- `lockForUpdate` + transacción (`:159-176`).

### A.6.7 Topes de inventario
`player_inventory.cantidad` es `unsignedInteger` sin máximo (`migración ...000007:20`). **No hay tope de stock** ni coste de almacenamiento.

### A.6.8 Descuentos / bonificaciones
**No existen** en ninguna de las 7 acciones: los costes son `match` puros sin modificadores por nivel de jugador, eventos o mejoras. Los únicos modificadores de la granja (factor tiempo, factor refugio) afectan a **beneficios**, no a costes (`CalculadoraBonosGranja.php:36-70`).

## A.7 Inconsistencias y riesgos con evidencia

| Severidad | Hallazgo | Evidencia | Impacto |
|---|---|---|---|
| **Alto** | **Doble tope EV (255 vs 512)** sobre el mismo campo `reclutados.evs`. Un complemento nivel 3 ya deja al reclutado en 256 y bloquea los caramelos EV individuales. | `ReclutadoController.php:147-151,184` vs `AplicarEvsFamilia.php:30` | Caramelos EV inutilizables en reclutados mejorados por granja; regla de negocio ambigua. |
| **Medio** | **Desperdicio parcial de caramelos EV** por `ceil` y clamp final (hasta 9 EV por operación). | `ReclutadoController.php:156-157,184` | Pérdida silenciosa de recursos sin aviso en UI. |
| **Medio** | **Coste oculto de la exp de Senda**: la UI muestra "Nv Senda 5" y el coste del nodo, pero no que alcanzar ese nivel cuesta 1.250 caramelos del mismo tipo. | `resources/views/sendas/show.blade.php:160` (solo `nivel_requerido` y `coste`); `SendaProgreso.php:39-45` | El jugador percibe un coste de 400 cuando el coste real del nodo N5 es 400 + la exp acumulada. |
| **Medio** | **Exp de Senda sin tope de gasto**: se puede consumir indefinidamente por encima de 500.000 exp, que es el máximo de presentación. | `SendaProgreso.php:57-59,103-116`; `SendasController.php:168-170` | Sumidero infinito sin retorno; riesgo de frustración. |
| **Medio** | **Salto anómalo del refugio a nivel 5** (2.700 frente a la progresión ×3: 25/75/225/450). | `CalculadoraBonosGranja.php:105-112` | Desproporción frente a nido (900 en el mismo nivel). |
| **Medio** | **Coste repetido por hábitat**: nido/refugio/complemento se pagan por `(user, habitat, family)`. La UI de granja no lo indica. | migración `...create_farm_tables.php:19-24,26-54`; `AsegurarGranja.php:14-30` | El "coste de una familia" se multiplica por cada hábitat donde se despliegue. |
| **Medio** | **Unidad "EV" ambigua**: `coste_ev` son caramelos (1 caramelo = +10 EV individuales), pero se cobra 1:1 contra puntos de nivel EV. | `CalculadoraBonosGranja.php:119`; `ReclutadoController.php:184` | La UI muestra "192 EV 🍬" (`show.blade.php:1238`) sin aclarar la equivalencia. |
| **Bajo** | **`player_type_paths.level` es redundante**: la UI calcula el nivel desde `exp`, ignorando la columna persistida. | `ArbolSendas.php:66-71` (`SendaProgreso::resumen($exp)`) vs `SendasController.php:170` | Si la columna se desincroniza, no afecta a la UI, pero confunde en consultas. |
| **Bajo** | **Nivel de nodo depende del estado de BD**: si no se reejecuta `TypePathNodeSeeder` tras cambiar `SendaNiveles`, UI y cobro usan niveles obsoletos (el seeder borra y reinserta). | `TypePathNodeSeeder.php:68-74`; `type_path_nodes.required_level` | Deriva silenciosa entre código y datos. |
| **Bajo** | **Siniestro sin nodos de nivel 5** (0 de 34): única Senda cuya progresión máxima real es nivel 4. | SELECT `type_path_nodes`; `ArbolSendas.php:126` | Coste de Senda completa distinto (625 en vez de 1.250). |
| **Bajo** | **Carrera en creación de complemento**: `Complemento` se crea sin `lockForUpdate` y hay `unique(shelter_id, stat)`; dos peticiones simultáneas podrían chocar con error 500 en vez de regla de negocio. | `MejorarComplemento.php:53-65`; migración `...farm_tables.php:53` | Riesgo de error intermitente bajo concurrencia. |
| **Bajo** | **`darCaramelo` calcula `faltante` fuera de la transacción**; el consumo se hace con `min` sobre stock real, así que no sobrecobra, pero la respuesta `exp_ganada` puede no cubrir el umbral si el stock era menor. | `ReclutadoController.php:67-77,87-97,105` | Mensaje/estado confuso, sin pérdida de recursos. |
| **Bajo** | **`SendaProgreso::umbral(6)` = 500.000** se usa como "exp_siguiente" del nivel 5, lo que hace que `exp_faltante` a nivel 5 sea 0 y el porcentaje 100 aunque la barra real siga subiendo. | `SendaProgreso.php:55-62,101-120` | Presentación coherente con el diseño declarado, pero puede ocultar el exceso. |

## A.8 Preguntas abiertas

1. **¿El tope 512 de complementos es intencional?** El de 255 coincide con el clásico de Pokémon; 512 no. Confirmar cuál es la fuente de verdad cuando un reclutado tiene EV por ambas vías.
2. **¿Debe la exp de Senda tener tope de consumo?** Hoy es un sumidero sin retorno por encima de 500.000 (`SendasController.php:168-170`).
3. **¿El coste de granja debe ser por hábitat o por familia global?** La implementación actual lo replica por hábitat (migración `farms` unique `(user_id, habitat_id)`).
4. **¿Los complementos deben cobrar EV "de caramelo" a razón de 1:1 o de 10:1?** `coste_ev = 3·E(n)` frente a `+10 EV` por caramelo individual.
5. **¿El salto del refugio de 450 a 2.700 es deliberado?** Es el único tramo que rompe la progresión ×3.
6. **¿Se espera que Siniestro no tenga nodos de nivel 5?** Es el único tipo sin ellos (0 de 34 nodos).
7. **Fuentes de caramelos (para balance neto, fuera de alcance):** recompensas de exploración (`PersistirRecompensas.php:86-87`), descarte de reclutables (`ReclutamientoController.php:113-142`) y recompensas de batalla/mazmorra no se cuantificaron aquí.

### Nota de método y límites

- Datos de nodos obtenidos con `SELECT type_id, required_level, count(*) FROM type_path_nodes GROUP BY ...` (solo lectura). Reflejan la BD local actual, no una migración reproducible en frío.
- No se leyeron `.env` ni secretos.
- No se modificó ningún archivo ni se ejecutaron migraciones, seeders ni escrituras.

---

# Parte B — Inventario y cuantificación de fuentes de recompensas

> **Nota metodológica.** Análisis read-only sobre el árbol de trabajo actual (`master` con WIP sin commitear: `git status` marca como modificados `CalculadorRecompensas.php`, `FinalizarExploracionHandler.php`, `PersistirRecompensas.php`, `ProcesarExploracionHandler.php`, `Combate.php`, etc.). No se ha leído `.env`, no se ha ejecutado ninguna migración/seeder ni consulta a base de datos; los ejemplos numéricos son **cálculos analíticos con supuestos explícitos**, no muestras de datos reales.

## B.1 Resumen ejecutivo

1. **Todas las recompensas de caramelos y EXP del juego salen de un único calculador** (`CalculadorRecompensas`) y se diferencian solo por un multiplicador por actividad **M**: exploración 0,125–0,6; ruta 1,0; entrenador 2,0; gimnasio 10,0; mazmorra 70,0 (`src/Exploraciones/Domain/CalculadorRecompensas.php:57`, `EvaluadorExploracion.php:253-263`, `OtorgarRecompensasEntrenador.php:34-36`, `OtorgarRecompensasMazmorra.php:39-40`).
2. **Las tres clases de caramelo se derivan de atributos del Pokémon derrotado**, no de una tabla de botín: familia = fase evolutiva; EV = `effort` base; tipo = EXP tipada / 500 (`CalculadorRecompensas.php:258-287`, `:313-335`).
3. **La EXP de derrota usa el nivel del jugador como “nivel salvaje”**, no el nivel del rival: `intdiv(base_experience × nivel_jugador, 5)` (`NivelHelper.php:39-42`; `FinalizarExploracionHandler.php:154`; `OtorgarRecompensasRuta.php:126`; `OtorgarRecompensasEntrenador.php:81`).
4. **La generación de eventos de exploración no respeta el “1 evento / 30 min”**: el planificador corre cada 5 min y el cálculo es `intdiv(minutos, 30) + bonus`; con ticks de 5 min el término base es 0 y **todos los eventos provienen del bonus por tick (0–3)**, que no escala con el tiempo (`ReglasExploracion.php:19`; `routes/console.php:13`; `ProcesarExploracionHandler.php:411-420`). Con bonus 0 no hay eventos; refrescando la página cada ~1 min se pueden obtener hasta ~60 ticks/hora.
5. **La ruta es un grifo sin límite**: 3 derrotados por combate, reinicio libre y sin cooldown (`RegistrarResultadoRuta.php:10-13`), con economía **legada** (factor 1,0), mientras la exploración usa economía **individual** (factor 0,5 y reparto 50/50). Para los mismos 3 derrotados, la ruta da **4× la EXP de cuenta** y **2× los caramelos** de una exploración (`OtorgarRecompensasRuta.php:123-128`; `FinalizarExploracionHandler.php:149-157`; `EconomiaRecompensas.php:39-58`).
6. **Los hallazgos de exploración rinden 0 caramelos por defecto**: la cantidad base es 1 y el multiplicador `categoría × rol × 0,5` produce `floor(0,5)=0`; familia y EV **no filtran ceros**, por lo que se persisten y muestran caramelos de cantidad 0 (`CalculadorRecompensas.php:195-217` vs `:223-232`; `FinalizarExploracionHandler.php:167-172`).
7. **Mazmorra = 70×**: un jefe con 750 EXP base al nivel 25 genera ~52.500 EXP de cuenta, 210 caramelos de familia, 210 EV y 105 de tipo (`OtorgarRecompensasMazmorra.php:39-40`; `ReglasMazmorra.php:25`).
8. **Capturar y descartar es un grifo de caramelos de familia sin coste**: cada reclutable descartado da `fase × cantidad` caramelos; las capturas vienen de ruta/exploración (`ReclutamientoController.php:69-90`, `:113-142`).
9. **No existe recurso “dinero” ni moneda** en el código revisado; el único inventario es `player_inventory` con claves `familia:`, `ev:` y `tipo:` (`ItemCatalogo.php:27-40`).
10. **Los sumideros son asimétricos**: caramelos de tipo → EXP de tipo (evolución/Sendas); EV → +10 por caramelo con tope 255 por stat; familia → solo granja (nido/refugio/complemento). No hay uso de caramelo de familia sobre Pokémon (`ReclutadoController.php:35-197`; `MejorarNido.php:58`; `MejorarRefugio.php:58`; `MejorarComplemento.php:76-77`).

## B.2 Mapa de fuentes

### B.2.1 Qué actividad otorga qué

| Actividad | Familia | EV | Tipo | EXP | Capturas | Otros recursos | M efectivo | Límite | Evidencia |
|---|---|---|---|---|---|---|---|---|---|
| **Combate de ruta** (3v3 salvaje) | Sí | Sí | Sí | Sí (cuenta + 3 miembros) | Sí (cap-25; shiny 1/2048 garantizado) | Avistados Pokédex | **1,0** (legado) | **Ninguno**; reintento libre | `OtorgarRecompensasRuta.php:48`, `:123-128`, `:171-176`; `RegistrarResultadoRuta.php:10-13` |
| **Entrenador** (3v3) | Sí | Sí | Sí | Sí | No | Avistados | **2,0** | 3 entrenadores × 3 niveles = 9/hábitat/día | `OtorgarRecompensasEntrenador.php:34`, `:78-88`; `IniciarCombateEntrenador.php:44`; `ObtenerEntrenadoresHabitat.php:29-31` |
| **Gimnasio** (etapas 1–4) | Sí | Sí | Sí | Sí | No | Medalla al completar | **10,0** | 4 combates por gimnasio, una vez (bloqueo al completar) | `OtorgarRecompensasEntrenador.php:36`; `IniciarCombateGimnasio.php:51-61`; `EloquentGymProgressRepository.php:22-44` |
| **Mazmorra** (jefe por piso) | Sí | Sí | Sí | Sí | No | Progreso de piso | **70,0** (10×7) | Pisos secuenciales, no repetibles; derrota → 1 h cooldown | `OtorgarRecompensasMazmorra.php:13-16`, `:34-41`; `IniciarCombateMazmorra.php:51-56`, `:96-100` |
| **Exploración: derrotas** | Sí | Sí | Sí | Sí (50/50) | Sí (cap-25 × 0,5) | EXP de tipo para Sendas | **0,125–0,6** (categoría × 0,5) | 1 activa por reclutado; duración 1–72 h o indefinida | `FinalizarExploracionHandler.php:149-157`, `:190`; `ExploracionActivaController.php:296-300`, `:321-322` |
| **Exploración: hallazgos** (20 % eventos) | Sí (33,33 %) | Sí (33,33 %) | Sí (33,34 %) | No | No | — | `categoría × rol × 0,5` (0,5–0,9) × `(1+bonus)` | 1 caramelo base por hallazgo | `SimuladorEncuentros.php:33-37`, `:46-47`, `:263-303`; `CalculadorRecompensas.php:125-142` |
| **Emboscada evitada** | Sí (+1) | No | No | Sí (cuenta como victoria) | Sí | 1 evento extra | Igual que hallazgo | Solo si detección ≥ COMPETENTE (50 %) o MAESTRO (100 %) | `FinalizarExploracionHandler.php:322-354`; `EventoExploracion.php:222-234`; `ProcesarExploracionHandler.php:336-341` |
| **Descarte de reclutables** | Sí (`fase × cantidad`) | No | No | No | — | — | — | Sin coste ni cooldown | `ReclutamientoController.php:69-90`, `:113-142` |
| **Reclutamiento** (reclutable→Reclutado) | No | No | No | No | — | Crea reclutado con IV/EV de granja | — | — | `ReclutamientoController.php:27-67` |
| **Huevos** | **No** | **No** | **No** | **No** | — | Crea reclutado | — | — | `EclosionarHuevo.php:18-93`; `grep "caramelo" src/Huevos` sin resultados |
| **Granjas** | **No genera** | **No genera** | **No genera** | **No** | — | Modifica aparición/captura/IV/EV | — | — | `ResolverBonosFamilia.php:27-121` |
| **Entrenador/Gimnasio/Mazmorra** | — | — | — | — | **No capturan** | — | — | — | `OtorgarRecompensasEntrenador.php:78-79`; `PresentadorResultadoBatalla.php:251-269` |
| **Dinero / moneda** | **No existe** | — | — | — | — | — | — | — | Búsqueda en `app/`, `src/`, `database/migrations`: solo `saldo` de caramelos (`InventarioGranja.php:59-97`) |

### B.2.2 Multiplicadores y economías

| Actividad | Multiplicador de categoría/actividad | Factor económico | M final (caramelos y EXP) | Evidencia |
|---|---|---|---|---|
| Exploración, éxito excepcional | 1,2 | 0,5 (individual) | **0,60** | `EvaluadorExploracion.php:253-263`; `EconomiaRecompensas.php:50-58` |
| Exploración, éxito | 1,0 | 0,5 | **0,50** | ídem |
| Exploración, éxito parcial | 0,7 | 0,5 | **0,35** | ídem |
| Exploración, fracaso | 0,25 | 0,5 | **0,125** | ídem |
| Exploración, retirada | 1,0 | 0,5 | **0,50** | `EvaluadorExploracion.php:260` |
| Ruta | 1,0 | 1,0 (legado por defecto) | **1,00** | `OtorgarRecompensasRuta.php:48`, `:123-128`; `CalculadorRecompensas.php:53` |
| Entrenador | 2,0 | 1,0 (legado) | **2,00** | `OtorgarRecompensasEntrenador.php:34`, `:83-88` |
| Gimnasio | 10,0 | 1,0 (legado) | **10,00** | `OtorgarRecompensasEntrenador.php:36`; `PresentadorResultadoBatalla.php:126-132` |
| Mazmorra | 10,0 × 7 | 1,0 (legado) | **70,00** | `OtorgarRecompensasMazmorra.php:39-40`; `ReglasMazmorra.php:25` |

> El factor económico se aplica **una sola vez** en dominio (`CalculadorRecompensas.php:55-57`); persistencia y UI no vuelven a descontar (`PersistirRecompensas.php:109-117`).

## B.3 Fórmulas y tablas por actividad

### B.3.1 Fórmulas comunes (derivadas del código)

Sea:

- `L` = nivel del jugador (`User::nivel()`, `User.php:58-61`; curva `10×n³`, `NivelHelper.php:16-50`).
- `e_i = intdiv(base_experience_i × L, 5)` (`NivelHelper.php:39-42`).
- `f_i` = fase evolutiva = nº de miembros de la cadena con `species_id ≤ species_id_i` (`NormalizadorPokemonDerrotado.php:73-86`).
- `v_i` = suma de `effort` de los stats base del derrotado (`NormalizadorPokemonDerrotado.php:51-64`).
- `M` = multiplicador de la tabla B.2.2.

| Recurso | Fórmula exacta | Redondeo | Evidencia |
|---|---|---|---|
| **Caramelos de familia** | `floor( Σ_i f_i × M )` agrupado por `evolution_chain_id` | `floor`, mínimo 0 | `CalculadorRecompensas.php:258-268` |
| **Caramelos EV** | `floor( Σ_i v_i,stat × M )` agrupado por `stat` (solo `effort > 0`) | `floor`, mínimo 0 | `CalculadorRecompensas.php:276-287` |
| **EXP tipada por derrota** | `e_i` si 1 tipo; `intdiv(e_i, 2)` **para cada** tipo si 2 tipos | `intdiv` | `CalculadorRecompensas.php:293-303` |
| **Caramelos de tipo** | `floor( Σ_i e_i,t × M / 500 )` por tipo `t`; se filtran los 0 | `floor`; filtro `>0` | `CalculadorRecompensas.php:313-335` (`0.2/100 = 1/500`) |
| **EXP de cuenta (legado)** | `floor( Σ_i e_i × M )` | `floor` | `CalculadorRecompensas.php:342-349` |
| **EXP de cuenta (individual)** | `pool = floor(Σ_i e_i × M × 0,5)`; `cuenta = intdiv(pool,2)`; `destino = pool − cuenta` | `floor` + `intdiv`; el resto va al destino | `CalculadorRecompensas.php:408-420` |
| **EXP por miembro (legado)** | `floor( Σ_i floor(e_i × 0,8 / 3) × M )` | doble `floor` | `CalculadorRecompensas.php:357-367`; `ReglasExploracion.php:23` |
| **EXP tipada por miembro** | `floor( Σ_i floor(reparto_i × 0,8/3) × M )` por tipo | doble `floor` | `CalculadorRecompensas.php:378-397` |
| **Captura** | `p = min(max(capture_rate,25),25)/255`; exploración multiplica `p × factor_refugio × 0,5` | — | `ProbabilidadCaptura.php:16-25`, `:32-35`; `FinalizarExploracionHandler.php:153`, `:469-496` |
| **Caramelo de hallazgo** | `floor( cantidad_base × (1+bonus_recolección) × M_hallazgo )`, `cantidad_base = 1` | `floor` | `CalculadorRecompensas.php:125-142`, `:195-232` |
| **Caramelo de descarte** | `fase × cantidad_descartada` | entero exacto | `ReclutamientoController.php:126-131` |

### B.3.2 Tabla de ejemplo por actividad

Supuesto: **3 derrotados**, `base_experience = 80`, `L = 25` ⇒ `e_i = 400`; `fase = 1`, `effort = 1`, 1 tipo. (Los valores escalan linealmente con `base_experience`, `L`, `fase` y `effort`.)

| Actividad | M | Familia | EV | Tipo | EXP cuenta | EXP por miembro | Evidencia |
|---|---|---|---|---|---|---|---|
| Exploración fracaso | 0,125 | 0 | 0 | 0 | 37 | 38 | `EvaluadorExploracion.php:259`; `CalculadorRecompensas.php:418-420` |
| Exploración parcial | 0,35 | 1 | 1 | 0 | 105 | 105 | `EvaluadorExploracion.php:258` |
| Exploración éxito | 0,50 | 1 | 1 | 1 | 150 | 150 | `EvaluadorExploracion.php:257` |
| Exploración excepcional | 0,60 | 1 | 1 | 1 | 180 | 180 | `EvaluadorExploracion.php:256` |
| Ruta | 1,00 | 3 | 3 | 2 | 1.200 | 318 × 3 miembros = 954 | `OtorgarRecompensasRuta.php:123-128` |
| Entrenador | 2,00 | 6 | 6 | 4 | 2.400 | 636 × 3 | `OtorgarRecompensasEntrenador.php:83-88` |
| Gimnasio | 10,00 | 30 | 30 | 24 | 12.000 | 3.180 × 3 | ídem, `PresentadorResultadoBatalla.php:126-132` |
| Mazmorra (1 jefe, base 150, fase 3, effort 3 ⇒ e=750) | 70,00 | 210 | 210 | 105 | 52.500 | 14.000 × 3 | `OtorgarRecompensasMazmorra.php:39-40` |

> **Nota:** en mazmorra el cálculo se hace sobre `base_experience` de la especie, no sobre los stats ×10 del jefe (`GeneradorJefeMazmorra` no altera `base_experience`); el multiplicador 70 sí se aplica a la EXP (`CalculadorRecompensas.php:342-349`).

### B.3.3 Exploración: generación de eventos

- Probabilidades por evento: **45 % encuentro, 20 % hallazgo, 15 % encuentro especial (emboscada), 10 % contratiempo, 10 % neutral** (`SimuladorEncuentros.php:33-37`).
- Subtipos de encuentro: **80 % normal, 10 % grupo, 7 % emboscada, 3 % excepcional** (`SimuladorEncuentros.php:39-42`).
- Hallazgo: **33,33 % familia / 33,33 % EV / 33,34 % tipo**, 1 caramelo base (`SimuladorEncuentros.php:46-47`, `:263-303`).
- Emboscada: **2–3 Pokémon** (`SimuladorEncuentros.php:389-392`).
- Nº de eventos por tick: `intdiv(minutos, intervalo_efectivo) + bonus`, con `intervalo_efectivo = max(1, floor(30 × (1−r_movilidad)))` y `r ∈ {0; 0,10; 0,25; 0,40}` (`ProcesarExploracionHandler.php:411-420`; `CapacidadesStats.php:178-186`).
- **Problema estructural:** el scheduler corre cada 5 min (`routes/console.php:13`) y el throttle de procesamiento es 30 s (`ProcesarExploracionesPendientes.php:35`). Con ticks de 5 min, `intdiv(5,30)=0`; por tanto **el término base desaparece** y los eventos son solo `bonusEventosExploracion` (0–3) por tick, sin escalar por tiempo.
- Resolución: encuentro perdido → fin de expedición; emboscada perdida → fin; emboscada evitada → `resolucion='evitada'`, cuenta como victoria y añade un evento extra (`ProcesarExploracionHandler.php:186-199`, `:336-341`; `EventoExploracion.php:226-234`).
- Categoría final: retirada → 1,0; sin combates → éxito 1,0; ratio victorias/combates ≥0,85 + excepcional vencido → 1,2; ≥0,6 → 1,0; ≥0,3 → 0,7; resto → 0,25 (`EvaluadorExploracion.php:210-263`).
- Derrota: se pierde `ceil(cantidad/2)` de cada caramelo/captura; **la EXP no se pierde** (`FinalizarExploracionHandler.php:563-624`, `:670-673`).

### B.3.4 Ruta, entrenador, gimnasio y mazmorra

| Actividad | Composición | Fórmula aplicada | Capturas | Evidencia |
|---|---|---|---|---|
| Ruta | 3 rivales salvajes, ponderados por `capture_rate/hatch`, nivel = nivel del jugador | `calcular()` sin `EconomiaRecompensas` ⇒ **legado** (factor 1,0; 80 %/3) | `ProbabilidadCaptura::intentar` (cap 25/255); shiny 1/2048 → reclutado directo | `GeneradorEquipoRuta.php:68-99`, `:134-140`; `OtorgarRecompensasRuta.php:123-128`, `:134-151` |
| Entrenador | 3 rivales; equipo determinista por fecha/hábitat/nivel/índice | `calcular(..., multiplicador=2)` ⇒ legado | No | `GeneradorEquipoEntrenador.php:24-59`; `OtorgarRecompensasEntrenador.php:34`, `:78-88` |
| Gimnasio | Equipo de etapa (1–3 entrenadores; 4 líder) | `multiplicador=10` | No | `IniciarCombateGimnasio.php:59-90`; `OtorgarRecompensasEntrenador.php:36` |
| Mazmorra | 1 jefe con stats ×10 y HP ×7; +30 % inicial | `multiplicador=70` | No | `ReglasMazmorra.php:15-25`; `OtorgarRecompensasMazmorra.php:39-40` |

### B.3.5 Descarte de reclutables

- `discard` y `discardAll` otorgan **caramelos de familia** por cada reclutable descartado: `fase × cantidad` (`ReclutamientoController.php:69-90`, `:92-104`, `:113-142`).
- No hay coste, cooldown ni límite; la única entrada de reclutables son las capturas de ruta/exploración (`PersistirRecompensas.php:95-107`; `OtorgarRecompensasRuta.php:160`).
- `recruit` no otorga caramelos: convierte el reclutable en Pokémon (`ReclutamientoController.php:27-67`).

### B.3.6 Influencia de nivel, hábitat, tipo, familia, tiempo y riesgo

| Factor | Efecto exacto | Evidencia |
|---|---|---|
| **Nivel del jugador `L`** | Multiplica linealmente `e_i = base×L/5`; por tanto EXP, caramelos de tipo y (vía EXP) Sendas. No afecta a familia ni EV. | `NivelHelper.php:39-42`; `CalculadorRecompensas.php:300`, `:345`, `:415` |
| **Nivel del Pokémon explorador** | Solo afecta al **nivel del rival** (`EscaladorNivelRival`), no a la recompensa. | `ProcesarExploracionHandler.php:509-521`; `EscaladorNivelRival.php:18-23` |
| **Hábitat** | Define el pool (peso `capture_rate/hatch`), los tipos/stats elegibles en hallazgos y `peligro` (dificultad `30 + 5×peligro`). | `ProcesarExploracionHandler.php:655-692`; `PoolHabitat`; `EvaluadorExploracion.php:61-71`; `SimuladorEncuentros.php:311-372` |
| **Tipo** | Los caramelos de tipo se generan por los **tipos del derrotado** (no por el movimiento usado); en hallazgo, por un tipo aleatorio de un Pokémon del pool. | `CalculadorRecompensas.php:293-335`; `NormalizadorPokemonDerrotado.php:91-98`; `SimuladorEncuentros.php:345-372` |
| **Familia evolutiva** | `fase` (1–3+) multiplica los caramelos de familia; el `evolution_chain_id` fija la clave `familia:{chain}`. | `NormalizadorPokemonDerrotado.php:73-86`; `ItemCatalogo.php:27-30` |
| **Tiempo** | Más ticks ⇒ más eventos (pero el bonus es por tick, no por minuto). El descanso consume 1 % HP/min y adelanta `ultimo_procesado`, perdiendo ticks. | `ReglasExploracion.php:21`; `ProcesarExploracionHandler.php:565-590`, `:220` |
| **Riesgo/peligro** | Sube la dificultad; reduce el rango de exploración (menos bonus de eventos) y la categoría final (menos multiplicador). No altera las fórmulas base. | `EvaluadorExploracion.php:61-114`, `:210-263`; `CapacidadesStats.php:123-160` |

## B.4 Rendimiento esperado por hora/sesión

### B.4.1 Supuestos explícitos

- `L = 25`; `base_experience = 80` ⇒ `e = 400` por derrota.
- `fase` media = 1,5; `effort` medio = 1,5; 1 tipo por derrotado (los duales reparten `intdiv(e,2)` a cada tipo, total ≈ `e`).
- Exploración: 90 % de victoria en encuentros; emboscadas detectadas al 50 % (rango COMPETENTE); de las emboscadas combatidas, 81 % se superan (0,9²); se ignora el truncamiento por derrota.
- `bonusRecolección = 1` (rango COMPETENTE en recolección) salvo donde se indique; rol no-Recolector salvo donde se indique.
- No se modela el tiempo real de clic/combate: “hora de juego” = 60 min de actividad.

**Kills esperados por 100 eventos de exploración:**

| Tipo de evento | Eventos | Kills esperados | Cálculo |
|---|---|---|---|
| Encuentro | 45 | 40,5 | 45 × 0,9 |
| Emboscada evitada | 7,5 | 18,75 | 7,5 × 2,5 |
| Emboscada combatida y ganada | 6,1 | 15,2 | 7,5 × 0,81 × 2,5 |
| **Total** | — | **≈ 74,4** | — |

### B.4.2 Exploración: ingresos por 100 eventos (M = 0,5)

| Recurso | Cantidad | Cálculo |
|---|---|---|
| Familia | **≈ 55** | `floor(74,4 × 1,5 × 0,5)` |
| EV | **≈ 55** | `floor(74,4 × 1,5 × 0,5)` |
| Tipo | **≈ 29** | `floor(74,4 × 400 × 0,5 / 500)` |
| Hallazgos (20 + 7,5 evitadas) | **0 / 20 / 40** según `bonusRecolección` 0/1/3 (no-Recolector) | `27,5 × (1+b) × 0,5` |
| EXP pool | **≈ 7.444** | `74,4 × 400 × 0,25` |
| EXP cuenta / reclutado | **≈ 3.722 / 3.722** | `intdiv(pool,2)` |
| Capturas | **≈ 3,65** | `74,4 × 0,049` |
| Familia por descarte de esas capturas | **≈ 5,5** | `3,65 × 1,5` |

### B.4.3 Exploración: ingresos por hora según frecuencia de tick

`b` = `bonusEventosExploracion` (0–3). Eventos/hora = `ticks/hora × b`.

| Régimen de ticks | Ticks/h | Eventos/h (b=1) | Familia/h | EV/h | Tipo/h | EXP cuenta/h |
|---|---|---|---|---|---|---|
| Scheduler cada 5 min | 12 | 12 | ≈ 7 + 0,8 hallazgo ≈ **8** | ≈ **8** | ≈ **5** | **≈ 446** |
| Refresco de página ~1/min (throttle 30 s) | ≤ 60 | ≤ 60 | ≈ **40** | ≈ **40** | ≈ **22** | **≈ 2.230** |
| Sin scheduler, 8 h offline | 1 (burst) | `16 + b` | ≈ 9–12 | ≈ 9–12 | ≈ 5–7 | ≈ 500–700 |
| **b = 0 (rango NOVATO)** | 12 | **0** | **0** | **0** | **0** | **0** |

> El caso `b=0` es literal: si `intdiv(minutos,30)=0` y `bonus=0`, `numEncuentros()` devuelve 0 y no se genera ningún evento (`ProcesarExploracionHandler.php:411-420`). La expedición solo produce “éxito sin combates” (categoría 1,0) y cero recompensas.

### B.4.4 Ruta: ingresos por combate y por hora

| Concepto | Por combate (3 kills) | 12 combates/h (5 min/batalla) | 30 combates/h (2 min/batalla) |
|---|---|---|---|
| Familia | 3 | 36 | 90 |
| EV | 3 | 36 | 90 |
| Tipo | 2 | 24 | 60 |
| EXP cuenta | 1.200 | 14.400 | 36.000 |
| EXP equipo (3 miembros) | 954 | 11.448 | 28.620 |
| Capturas | 0,29 | 3,5 | 8,8 |
| Familia extra por descarte | 0,44 | 5,3 | 13,2 |

Con `fase` media 1,5, la familia por combate sube a ~4–5 y por hora a ~54–135.

### B.4.5 Entrenador, gimnasio y mazmorra

| Actividad | Familia | EV | Tipo | EXP cuenta | EXP/miembro | Frecuencia máxima |
|---|---|---|---|---|---|---|
| Entrenador (1 combate, M=2) | 6 | 6 | 4 | 2.400 | 636 | 9/hábitat/día |
| Entrenador (9/día, un hábitat) | 54 | 54 | 36 | 21.600 | 5.724 | diario |
| Gimnasio (1 etapa, M=10) | 30 | 30 | 24 | 12.000 | 3.180 | 4 etapas por gimnasio, una vez |
| Gimnasio completo (4 etapas) | 120 | 120 | 96 | 48.000 | 12.720 | una vez por gimnasio |
| Mazmorra (1 jefe, M=70) | 210 | 210 | 105 | 52.500 | 14.000 | 1 por piso, secuencial |

### B.4.6 Sesión típica de 1 hora (supuesto)

| Componente | Familia | EV | Tipo | EXP cuenta |
|---|---|---|---|---|
| 10 combates de ruta | 30–45 | 30–45 | 20–30 | 12.000–18.000 |
| 1 etapa de gimnasio o 1 piso de mazmorra | 30–210 | 30–210 | 24–105 | 12.000–52.500 |
| 1–2 exploraciones pasivas (b=1) | 8–16 | 8–16 | 5–10 | 450–900 |
| **Total típico** | **≈ 70–270** | **≈ 70–270** | **≈ 50–145** | **≈ 25.000–70.000** |

Rango optimista: ruta a 2 min/batalla + mazmorra + refresco de exploración ⇒ **>150 familia/h, >150 EV/h, >80 tipo/h, >100.000 EXP/h**. Rango pesimista: solo exploración con `b=0` ⇒ **0 de todo**.

### B.4.7 Comparación con el coste de las acciones

No hay coste monetario ni energía. El “coste” es tiempo/oportunidad:

| Sumidero | Coste | Equivalente en horas de ruta (12 combates/h) |
|---|---|---|
| Evolución nivel 20 (1 tipo) | 12.610 EXP de tipo ⇒ **127 caramelos** | ≈ 5–6 h de ruta (24 tipo/h) |
| Evolución nivel 30 (1 tipo) | 27.910 EXP ⇒ **280 caramelos** | ≈ 12 h |
| Evolución nivel 50 (1 tipo) | 76.510 EXP ⇒ **766 caramelos** | ≈ 32 h |
| Senda de tipo a nivel 5 | 500.000 EXP ⇒ **5.000 caramelos** | ≈ 208 h |
| Desbloqueo de movimiento de Senda | 10 / 25 / 50 / 150 / 400 caramelos | 0,4–17 h |
| EV de un Pokémon (6 stats a 255) | **156 caramelos EV** (26/stat) | ≈ 4 h (36 EV/h) |
| Granja completa de una familia | **13.214 familia + 24.192 EV** | ≈ 366 h de familia / 672 h de EV |
| Nido nivel 5 | 1.675 familia | ≈ 47 h |
| Refugio nivel 5 | 3.475 familia | ≈ 97 h |

> Cálculos con las fórmulas de `ServicioEvolucion.php:41-44` (`10[(N+1)³−N³]`), `SendaProgreso.php:22-27`, `SendaCostes.php:14-20`, `ReclutadoController.php:147-157`, `CalculadoraBonosGranja.php:91-132`.

## B.5 Topes, probabilidades y aleatoriedad

### B.5.1 Probabilidades

| Tirada | Valor | Evidencia |
|---|---|---|
| Tipo de evento de exploración | 45 % encuentro / 20 % hallazgo / 15 % especial / 10 % contratiempo / 10 % neutral | `SimuladorEncuentros.php:33-37` |
| Subtipo de encuentro | 80 % normal / 10 % grupo / 7 % emboscada / 3 % excepcional | `SimuladorEncuentros.php:39-42` |
| Subtipo de hallazgo | 33,33 % familia / 33,33 % EV / 33,34 % tipo | `SimuladorEncuentros.php:46-47`, `:271-302` |
| Nº de Pokémon en emboscada | 2 (50 %) / 3 (50 %) | `SimuladorEncuentros.php:389-392` |
| Evasión de emboscada | 50 % si detección ≥ COMPETENTE; **100 % si MAESTRO**; 0 % si < COMPETENTE | `ProcesarExploracionHandler.php:336-341`; `CapacidadesStats.php:204-215` |
| Retirada tras derrota de emboscada | 50 % | `ProcesarExploracionHandler.php:191` |
| Retirada por desventaja (modelo de capacidad, no usado en el pipeline individual) | 50 % | `EvaluadorExploracion.php:99-107` |
| Captura | `min(capture_rate,25)/255` ⇒ máximo **9,80 %**; exploración ×0,5 ⇒ máx. **4,90 %** | `ProbabilidadCaptura.php:16-25`; `FinalizarExploracionHandler.php:153`, `:484-494` |
| Shiny salvaje | **1/2048** por slot; captura garantizada y reclutado directo | `ProbabilidadesShiny.php:13`; `OtorgarRecompensasRuta.php:42-44`, `:134-151` |
| Shiny huevo | **1/512** | `ProbabilidadesShiny.php:14` |
| Éxito con coste (modelo de capacidad) | 5 o 10 min (50/50) | `EvaluadorExploracion.php:92-95` |

**No hay tablas de botín ni pity systems.** No se encontró ningún contador de “suerte acumulada”, “garantía tras N intentos” ni tabla de drops; las únicas probabilidades son las constantes anteriores.

### B.5.2 Topes y cooldowns

| Mecánica | Valor | Evidencia |
|---|---|---|
| Ruta | **Sin bloqueo, sin log diario, reintento libre** | `RegistrarResultadoRuta.php:10-13` |
| Entrenador | 3 por nivel × 3 niveles = **9/hábitat/día**; bloqueo si `haGanadoHoy` | `ObtenerEntrenadoresHabitat.php:29-31`; `IniciarCombateEntrenador.php:44` |
| Gimnasio | 4 etapas; bloqueo al completar (`current_stage=5`) | `IniciarCombateGimnasio.php:51-53`; `EloquentGymProgressRepository.php:22-44` |
| Mazmorra | Piso actual únicamente; no se repiten ganados; derrota → **1 h** cooldown | `IniciarCombateMazmorra.php:51-56`, `:96-100`; `ReglasMazmorra.php:27`; `EloquentDungeonProgresoRepository.php:51-60` |
| Exploración simultánea | **1 activa por reclutado**; sin tope por usuario | `ValidadorExploracion.php:18-31` |
| Duración de exploración | 1–72 h o **indefinida** | `ExploracionActivaController.php:296-300`, `:321-322`; `CalculadorFinExploracion.php:41` |
| Descanso | 1 % HP/min; consume tiempo real y adelanta `ultimo_procesado` | `ReglasExploracion.php:21`; `ProcesarExploracionHandler.php:565-590`, `:220` |
| Derrota en exploración | Pierde `ceil(cantidad/2)` de caramelos y capturas; **no pierde EXP** | `FinalizarExploracionHandler.php:571-621`, `:670-673` |
| EV por stat | Tope **255**; +10 por caramelo | `ReclutadoController.php:147-157` |
| Senda | Nivel máximo 5; tope de presentación 500.000 EXP | `SendaProgreso.php:22-27`; `SendaProgreso.php:33-38` |
| Granja | Nido/refugio/complemento máximo nivel 5 | `CalculadoraBonosGranja.php:21-25` |
| Inventario | Sin tope de cantidad | `PersistirRecompensas.php:86-89` |

**No existen topes diarios de caramelos, energía, bayas ni límite de exploraciones por usuario.** El único límite diario es el de entrenadores; gimnasio/mazmorra son límites de progresión, no temporales.

## B.6 Mapeo recompensa → sumidero

### B.6.1 Origen del caramelo

| Clase | Se genera por | ¿El jugador elige el subtipo? | Evidencia |
|---|---|---|---|
| **Familia** | `evolution_chain_id` del derrotado; hallazgo `caramelo_familia` de un Pokémon del pool; emboscada evitada (primer Pokémon); descarte de reclutables | **No**: lo fija la especie | `CalculadorRecompensas.php:258-268`; `SimuladorEncuentros.php:271-283`; `FinalizarExploracionHandler.php:345-354`; `ReclutamientoController.php:126-131` |
| **EV** | `effort` base del derrotado, por stat; hallazgo `caramelo_ev` que elige un Pokémon del pool y **un** stat con `effort>0` | **No**: lo fija la especie / el pool | `CalculadorRecompensas.php:276-287`; `SimuladorEncuentros.php:286-293`, `:311-339` |
| **Tipo** | Tipos del derrotado (EXP tipada); hallazgo `caramelo_tipo` que elige un Pokémon del pool y **uno** de sus tipos | **No**: lo fija la especie / el pool | `CalculadorRecompensas.php:313-335`; `SimuladorEncuentros.php:296-302`, `:345-372` |

> El tipo **no** se deriva del movimiento usado: `PokemonDerrotado::tipos` viene de `pokemon.types` (`NormalizadorPokemonDerrotado.php:91-98`), no del movimiento.

### B.6.2 Sumideros

| Clase | Uso | Coste | ¿Elección del jugador? | Evidencia |
|---|---|---|---|---|
| **Tipo** | Convertir en EXP de tipo del reclutado para evolucionar (100 EXP/caramelo) | `ceil(faltante/100)` caramelos; umbral `10[(N+1)³−N³]` por **cada** tipo de la evolución | Elige el reclutado y la evolución; **no** el tipo (debe coincidir con los tipos de la evolución) | `ReclutadoController.php:58-77`, `:105`; `ServicioEvolucion.php:41-44`, `:132-141` |
| **Tipo** | EXP de Senda (100/caramelo) y desbloqueo de movimientos | 1/10/100 caramelos; nodos 10/25/50/150/400 | Elige la Senda/nodo | `SendasController.php:90-125`, `:133-190`; `SendaProgreso.php:27`; `SendaCostes.php:14-20` |
| **EV** | +10 EV al stat del caramelo en un reclutado; tope 255 | 1 caramelo = +10; máx. 26/stat | Elige el reclutado; **no** el stat | `ReclutadoController.php:118-197` |
| **EV** | Complementos de granja | `ev × 3` = 192/384/768/1.152/1.536 por nivel, por stat | Elige familia/stat | `MejorarComplemento.php:74-77`; `CalculadoraBonosGranja.php:115-132` |
| **Familia** | Nido | 25/75/225/450/900 (total 1.675) | Elige familia/hábitat | `MejorarNido.php:53-58`; `CalculadoraBonosGranja.php:91-101` |
| **Familia** | Refugio | 25/75/225/450/2.700 (total 3.475) | Elige familia/hábitat | `MejorarRefugio.php:53-58`; `CalculadoraBonosGranja.php:103-113` |
| **Familia** | Complemento | 64/128/256/384/512 por nivel y stat (total 1.344/stat; 8.064/familia) | Elige familia/stat | `MejorarComplemento.php:74-77`; `CalculadoraBonosGranja.php:115-132` |
| **Familia** | **Ningún uso sobre Pokémon** | — | — | `grep consumirFamilia` solo devuelve `MejorarNido`, `MejorarRefugio`, `MejorarComplemento` |

**Totales por familia para maximizar la granja:** familia **13.214** (1.675 + 3.475 + 8.064) y EV **24.192** (3 × 1.344 × 6 stats).

### B.6.3 Economía de la evolución: caramelo vs EXP de tipo

- La evolución consume `exp.tipos` del reclutado, no “caramelos” directamente (`ServicioEvolucion.php:276-281`; `ReclutadoController.php:226-232`).
- Los combates **también** añaden EXP de tipo al reclutado: `PersistirRecompensas::sumarExp()` suma `expTipoPorMiembro` a `reclutados.exp.tipos` (`PersistirRecompensas.php:200-214`).
- El caramelo de tipo solo acelera esa EXP: 1 caramelo = 100 EXP (`ReclutadoController.php:76-77`, `:105`).
- **Consecuencia:** la evolución puede pagarse “gratis” con EXP de tipo de combates; el caramelo es un atajo, no la única vía. La cuenta también recibe EXP de tipo para Sendas (`PersistirRecompensas.php:137`, `:161-195`), pero **la UI del modal de batalla no la muestra**.

## B.7 Inconsistencias y riesgos con evidencia

### B.7.1 Inconsistencias de dominio/UI/persistencia

| # | Severidad | Hallazgo | Evidencia |
|---|---|---|---|
| **I1** | **Alta** | **El “1 evento / 30 min” no se cumple.** El scheduler corre cada 5 min; `intdiv(5,30)=0`; los eventos son solo el bonus por tick (0–3), que no escala con el tiempo. Con bonus 0 no hay eventos; con refresco de página (throttle 30 s) se puede multiplicar la tasa por ~5 respecto al scheduler. | `ReglasExploracion.php:19`; `routes/console.php:13`; `ProcesarExploracionHandler.php:411-420`; `ProcesarExploracionesPendientes.php:35`, `:46-66` |
| **I2** | **Alta** | **El preview de recompensas no coincide con el dominio.** El estimador usa `min_lvl`/dificultad como “nivel salvaje”, mientras el pipeline usa el nivel del jugador; ignora hallazgos, categoría, rol, bonus de recolección y capturas; calcula el caramelo de tipo como `e × floor(expProm×0,2/100) × 0,5` en vez de `floor(Σe×M/500)`. | `ExploracionActivaController.php:109-117`; `EstimadorRecompensasExploracion.php:78-94`, `:123`; `FinalizarExploracionHandler.php:154`; `CalculadorRecompensas.php:328-334` |
| **I3** | **Alta** | **Ruta da 4× la EXP de cuenta y 2× los caramelos de una exploración por los mismos derrotados.** Ruta/entrenador/gimnasio usan la economía legada por defecto (factor 1,0); exploración usa individual (factor 0,5 y 50/50). | `OtorgarRecompensasRuta.php:123-128`; `OtorgarRecompensasEntrenador.php:83-88`; `CalculadorRecompensas.php:53`; `FinalizarExploracionHandler.php:149-157`; `EconomiaRecompensas.php:39-58` |
| **I4** | **Alta** | **Los hallazgos rinden 0 caramelos por defecto.** `1 × (1+0) × (1,0×0,5) = 0,5 → floor 0`. Además, familia y EV **no filtran los ceros**: se persisten y se muestran caramelos con cantidad 0; tipo sí filtra. Solo se salva con `bonusRecolección ≥ 1` o rol Recolector + bonus. | `CalculadorRecompensas.php:125-142`, `:195-217` vs `:223-232`; `FinalizarExploracionHandler.php:167-172`, `:382-395` |
| **I5** | **Alta** | **La mazmorra aplica 70× también a la EXP**: un jefe de 750 EXP base al nivel 25 da 52.500 EXP de cuenta y 14.000 a cada miembro, además de 210/210/105 caramelos. Es el mayor grifo por acción del juego. | `OtorgarRecompensasMazmorra.php:39-40`; `CalculadorRecompensas.php:265`, `:284`, `:331`, `:348` |
| **I6** | **Alta** | **Grifo sin límite ruta + descarte.** La ruta no tiene cooldown ni tope; las capturas (hasta 9,8 % por derrotado) se convierten en caramelos de familia al descartar, sin coste. | `RegistrarResultadoRuta.php:10-13`; `OtorgarRecompensasRuta.php:171-176`; `ReclutamientoController.php:69-90`, `:113-142` |
| **I7** | **Alta** | **Emboscada evitada = doble premio.** `resolucion='evitada'` cuenta como victoria, por lo que los 2–3 Pokémon de la emboscada entran como “derrotados” (familia/EV/tipo/EXP) **sin combatir**, más 1 caramelo de familia y un evento extra. Con detección MAESTRO ocurre en el 100 % de las emboscadas. | `EventoExploracion.php:226-234`; `FinalizarExploracionHandler.php:216-227`, `:322-354`; `ProcesarExploracionHandler.php:336-341`, `:196-199` |
| **I8** | **Media** | **La derrota en exploración castiga con `ceil(cantidad/2)`**, lo que en pilas de 1 caramelo equivale a perder el 100 %; se aplica después de sumar hallazgos; la EXP no se pierde. | `FinalizarExploracionHandler.php:571-621`, `:670-673` |
| **I9** | **Media** | **El parámetro `nivelEntrenador` no se usa** en `OtorgarRecompensasEntrenador::otorgar()`: la EXP se calcula con `$usuario->nivel()`. El nivel mostrado/esperado del entrenador no influye. | `OtorgarRecompensasEntrenador.php:49`, `:81` |
| **I10** | **Media** | **La etiqueta del caramelo de tipo en el preview usa solo el tipo dominante del pool**, mientras el dominio reparte caramelos de **todos** los tipos de los derrotados. | `EstimadorRecompensasExploracion.php:90-94`, `:183-203`; `CalculadorRecompensas.php:328-334` |
| **I11** | **Media** | **La EXP de Senda no se muestra en ningún modal**: `PersistirRecompensas::otorgarExpSendas()` se ejecuta en todos los flujos, pero los modales solo muestran EXP de cuenta/miembro y caramelos. | `PersistirRecompensas.php:134-137`, `:161-195`; `OtorgarRecompensasEntrenador.php:110-117`; `PresentadorResultadoBatalla.php:181-196` |
| **I12** | **Media** | **Los caramelos de familia no tienen uso sobre Pokémon** y la granja es finita por familia (13.214). Cadenas sobrantes acumulan sin sumidero; no hay conversión entre familias. | `CalculadoraBonosGranja.php:21-25`, `:91-132`; `InventarioGranja.php:69-87` |
| **I13** | **Baja** | **Captura ligeramente distinta entre ruta y exploración**: ruta usa `Randomizer::getFloat(0,1)`; exploración usa `mt_rand(1,100)/100` (mínimo 0,01). Con `p=25/255`, la probabilidad efectiva es ~9,8 % vs ~9 %. | `OtorgarRecompensasRuta.php:181-186`; `FinalizarExploracionHandler.php:484`; `ProbabilidadCaptura.php:38-50` |
| **I14** | **Baja** | **`$userId ?? 1` en el descarte**: si no hay usuario autenticado, los caramelos van al usuario 1. La ruta está autenticada, pero es un fallback peligroso. | `ReclutamientoController.php:135-139` |
| **I15** | **Baja** | **Se crean filas de inventario con cantidad 0** por hallazgos familia/EV con multiplicador <1; la UI puede mostrar “0 caramelos”. | `CalculadorRecompensas.php:195-217`; `PersistirRecompensas.php:86-89` |

### B.7.2 Riesgos económicos (sin juicio de balance)

| # | Riesgo | Impacto | Mitigación posible (no propuesta de valores) |
|---|---|---|---|
| R1 | Tasa de eventos dependiente de la frecuencia de peticiones, no del tiempo | Exploraciones pasivas pueden rendir 0 o 5× según despliegue/refresco; el preview no lo refleja | Unificar la generación con un reloj monotónico y un acumulador por tiempo, no por llamada |
| R2 | Ruta/descarte sin coste | Faucet potencialmente infinito de familia/EV/tipo/EXP | Definir un coste/limitador explícito (no implementado hoy) |
| R3 | Mazmorra 70× sobre EXP | Saltos de nivel y de Sendas por una sola acción | Separar el multiplicador de EXP del de caramelos |
| R4 | Hallazgos a 0 | La mecánica de exploración “hallazgo” es funcionalmente inexistente en la economía individual | Aplicar el multiplicador después de un mínimo, o redondear hacia arriba |
| R5 | Familia sin sumidero tras maxear granja | Acumulación indefinida en cadenas sobrantes | Nuevo sumidero o conversión (decisión de diseño) |
| R6 | EV se satura por Pokémon en ~4 h de ruta | El caramelo EV pierde valor individual; solo sirve para granja | Revisar tope 255 / coste de complementos |

## B.8 Preguntas abiertas

1. **¿El planificador está realmente activo en producción?** Si `routes/console.php:13` no se ejecuta por cron, el término base `intdiv(minutos,30)` sí produce eventos al abrir la página tras horas, y la economía cambia por completo. No puedo verificarlo sin acceso al despliegue.
2. **¿Cuál es el número real de pisos por mazmorra y de hábitats con mazmorra?** Depende del JSON `habitats.mazmorra` (`ConfiguracionMazmorra::desdeJson`) y de los seeders; no se consultó la base de datos.
3. **¿Cuántos reclutados puede tener un jugador?** No se encontró tope; si es ilimitado, el número de exploraciones simultáneas (1 por reclutado) también lo es (`ValidadorExploracion.php:18-31`).
4. **¿El “nivel de exploración” 1–3 tiene datos de pool distintos?** `pokemon_habitat.level` filtra el pool (`ProcesarExploracionHandler.php:670`); sin datos no se puede decir cuántas especies hay por nivel.
5. **¿La economía individual (factor 0,5) es definitiva o un interruptor de fase?** `FinalizarExploracionHandler.php:149` la instancia siempre; no hay feature flag.
6. **¿El 70× de mazmorra es intencional también para EXP?** El comentario solo habla de “recompensa” (`OtorgarRecompensasMazmorra.php:13-16`), pero el código multiplica EXP (`CalculadorRecompensas.php:348`).
7. **¿Los caramelos de familia de hallazgos con cantidad 0 deben persistirse?** El dominio los genera y `PersistirRecompensas` los incrementa; no hay filtro (`CalculadorRecompensas.php:195-217`; `PersistirRecompensas.php:86-89`).
8. **¿Debe el descarte dar caramelos de familia?** Es la única conversión captura→caramelo y no tiene coste (`ReclutamientoController.php:69-90`); no hay documento de diseño que lo confirme.
9. **¿La EXP de tipo de Sendas debe otorgarse en ruta/entrenador/gimnasio/mazmorra?** `PersistirRecompensas` es compartido y la otorga en todos (`PersistirRecompensas.php:134-137`), pero la UI no la muestra.
10. **¿Existe un recurso “dinero” en otra rama o migración no presente en el árbol?** En el árbol actual no hay ninguna referencia; si el diseño lo prevé, no está implementado.

### Anexo A — Constantes citadas (resumen)

| Constante | Valor | Fuente |
|---|---|---|
| `MINUTOS_POR_ENCUENTRO` | 30 | `ReglasExploracion.php:19` |
| `HP_POR_MINUTO_DESCANSO` | 1 | `ReglasExploracion.php:21` |
| `FRACCION_EXP_REPARTO` | 0,8 | `ReglasExploracion.php:23` |
| `FACTOR_RECOMPENSAS` | 0,5 | `ReglasExploracion.php:26` |
| `FRACCION_EXP_INDIVIDUAL` | 0,5 | `ReglasExploracion.php:29` |
| `MIEMBROS_EQUIPO` | 3 | `CalculadorRecompensas.php:38`; `ReglasEquipo::MIEMBROS` |
| `PROBABILIDAD_ENCUENTRO/HALLAZGO/ESPECIAL/CONTRATIEMPO/NEUTRAL` | 45/20/15/10/10 | `SimuladorEncuentros.php:33-37` |
| `SUBTIPO_NORMAL/GRUPO/EMBOSCADA/EXCEPCIONAL` | 80/10/7/3 | `SimuladorEncuentros.php:39-42` |
| `HALLAZGO_FAMILIA/EV` | 33,33/66,66 | `SimuladorEncuentros.php:46-47` |
| `CAP` / `MAXIMO` captura | 25/255 | `ProbabilidadCaptura.php:16-18` |
| `MULTIPLICADOR_RUTA` | 1,0 | `OtorgarRecompensasRuta.php:48` |
| `MULTIPLICADOR_ENTRENADOR` | 2,0 | `OtorgarRecompensasEntrenador.php:34` |
| `MULTIPLICADOR_GIMNASIO` | 10,0 | `OtorgarRecompensasEntrenador.php:36` |
| `FACTOR_RECOMPENSA_RESPECTO_GIMNASIO` | 7 | `ReglasMazmorra.php:25` |
| `HORAS_COOLDOWN` mazmorra | 1 | `ReglasMazmorra.php:27` |
| `SALVAJE` / `HUEVO` shiny | 1/2048 y 1/512 | `ProbabilidadesShiny.php:13-14` |
| `EXP_POR_CARAMELO` Senda | 100 | `SendaProgreso.php:27` |
| Costes de Senda | 10/25/50/150/400 | `SendaCostes.php:14-20` |
| Coste nido | 25/75/225/450/900 | `CalculadoraBonosGranja.php:91-101` |
| Coste refugio | 25/75/225/450/2.700 | `CalculadoraBonosGranja.php:103-113` |
| EV complemento | 64/128/256/384/512; coste EV ×3 | `CalculadoraBonosGranja.php:115-132` |
| Tope EV por stat | 255 | `ReclutadoController.php:147-151` |
| Nivel máximo Senda | 5 | `SendaProgreso.php:22` |
| Nivel máximo granja | 5 | `CalculadoraBonosGranja.php:21-25` |

### Anexo B — Fórmulas en una línea

```text
e_i            = intdiv(base_experience_i × nivel_jugador, 5)
familia        = floor( Σ fase_i × M )
ev             = floor( Σ effort_i,stat × M )
tipo_t         = floor( Σ (e_i si 1 tipo; intdiv(e_i,2) si 2 tipos) × M / 500 )
exp_cuenta     = floor(Σ e_i × M)                      [legado]
exp_cuenta     = intdiv(floor(Σ e_i × M × 0,5), 2)     [individual]
exp_miembro    = floor( Σ floor(e_i × 0,8 / 3) × M )   [legado]
captura        = min(max(capture_rate,25),25) / 255    [× factor_refugio × 0,5 en exploración]
hallazgo       = floor( 1 × (1 + bonus_recolección) × M_hallazgo )
descarte       = fase × cantidad_descartada
```

**Conclusión de inventario:** el sistema no tiene tablas de botín ni pity; toda la economía es formulaica y depende de cuatro palancas (`base_experience`, nivel del jugador, `fase`/`effort` del derrotado y el multiplicador M de la actividad). Las mayores desviaciones entre lo mostrado y lo otorgado están en la generación de eventos (I1), el preview (I2) y los hallazgos a cero (I4).

---

## Síntesis de brechas (para el architect)

---

# Ampliación III — Sendas (ataques 1-3, pasivos 4-5) y movesets de rivales

> Inventario de solo lectura. No se ha modificado código ni ejecutado migraciones.
> La BD local sí estuvo accesible en esta pasada (`php artisan tinker`), así que
> las cifras de nodos y movimientos son de la BD real.

## 1. Estructura actual de Sendas

- 5 niveles con umbrales `0 / 15.625 / 31.250 / 62.500 / 125.000`
  (`app/Support/SendaProgreso.php:39-45`) y `EXP_MAXIMA = 500.000`
  (`SendaProgreso.php:22-28`).
- 639 nodos, uno por movimiento aprendible (`type_path_nodes`):

| Nivel | Físico | Especial | Estado | Total |
|---|---:|---:|---:|---:|
| 1 | 63 | 34 | 90 | **187** |
| 2 | 46 | 29 | 39 | **114** |
| 3 | 77 | 52 | 31 | **160** |
| 4 | 52 | 46 | 6 | **104** |
| 5 | 34 | 38 | 2 | **74** |
| **Total** | **272** | **199** | **168** | **639** |

- La asignación actual es por potencia (`app/Support/SendaNiveles.php:38-45`):
  `≤40 → 1`, `≤60 → 2`, `≤80 → 3`, `≤100 → 4`, `>100 → 5`. Los movimientos de
  estado usan `nivel_requerido` (`≤20 → 1`, `≤35 → 2`, `≤50 → 3`, `≤70 → 4`,
  `>70 → 5`; `SendaNiveles.php:47-56`). Hay ajustes manuales para
  `ember=1`, `flamethrower=3`, `fire-blast=5`, `will-o-wisp=2`
  (`SendaNiveles.php:14-19`).
- Los 178 nodos de niveles 4-5 son **170 de daño + 8 de estado**. Si se
  comprimen a 1-3, el nivel 3 pasa de 160 a 338 nodos.
- Coste actual por nivel (`app/Support/SendaCostes.php:10-20`):
  `10 / 25 / 50 / 150 / 400`. El coste total de los 639 nodos es **57.920**
  caramelos de tipo.
- El nivel de Senda se calcula desde `player_type_paths.exp`
  (`app/Models/PlayerTypePath.php:12-27`; `SendasController.php:160-177`).
  Cada caramelo de tipo da 100 EXP (`SendaProgreso.php:27`); el jugador puede
  convertir caramelos en EXP (`SendasController.php:103-179`).

## 2. Compresión a niveles 1-3 y coste

Propuesta de regla (sin implementar):

- Daño: `≤40 → 1`, `41-60 → 2`, `>60 → 3`.
- Estado: `nivel_requerido ≤20 → 1`, `21-35 → 2`, `>35 → 3`.
- Resultado: **187 / 114 / 338** nodos.
- El coste se desacopla del nivel de desbloqueo y se calcula por potencia:
  `nivel 1 → 2`, `nivel 2 → 5`, `nivel 3` con `≤80 → 10`, `≤100 → 25`,
  `>100 → 60`.
- Total: `187×2 + 114×5 + 160×10 + 104×25 + 74×60 = 9.584` caramelos, el mismo
  total que el plan v2. El desbloqueo queda en 1-3, pero el coste sigue
  escalando con la potencia.
- Alternativa descartada: conservar 5 niveles y llamar “pasivos” a 4-5 sin
  mover nodos; incumple “los ataques se desbloquean entre 1 y 3”.

**Riesgo de UI:** el nivel 3 concentra 338 nodos. Habrá que agrupar por
categoría/potencia y no renderizar 338 tarjetas de golpe.

## 3. Puntos de aplicación de los beneficios pasivos

### 3.1 Captura

- Regla unificada: `ProbabilidadCaptura::probabilidadConFactor()`
  (`src/Shared/Domain/ProbabilidadCaptura.php:33-45`). El factor se aplica
  **después** del cap-25, así que un +10 % relativo no queda bloqueado por el
  cap.
- Ruta: `OtorgarRecompensasRuta::rollCaptura()` recibe un `PokemonDerrotado` y
  llama a `ProbabilidadCaptura::intentar()` sin factor
  (`src/CombateRuta/App/OtorgarRecompensasRuta.php:166-176`). Aquí se puede
  inyectar el factor de Senda.
- Exploración: `FinalizarExploracionHandler::tiradaCaptura()` ya compone
  refugio × `factorCaptura` y llama a `intentarConFactor()`
  (`src/Exploraciones/App/FinalizarExploracionHandler.php:470-494`). El factor
  de Senda se multiplica ahí.
- `PokemonDerrotado` expone `tipos` como labels en español
  (`src/Exploraciones/Domain/Recompensas/PokemonDerrotado.php:14-27`), y
  `TipoPokemon::label()` devuelve exactamente esos labels
  (`src/Shared/Tipos/TipoPokemon.php:36-56`). El mapeo es directo.
- Reclutamiento (`ServicioCaptura`) usa `ProbabilidadCaptura::probabilidad()`
  sin factor (`src/Reclutamiento/App/ServicioCaptura.php:31`). No es un combate;
  decidir si la Senda aplica también ahí.

### 3.2 Daño infligido

- La cadena de daño está en `src/Battle/Domain/Chain/CadenaDanio.php:15-35`.
  El último multiplicador es `ManejadorBonificadorDanio`, que usa
  `Combatiente::bonificadorDanio()` (`ManejadorBonificadorDanio.php:15-25`).
- `Combatiente` ya serializa `bonificadorDanio` (`Combatiente.php:74,195,280,
  1156,1371`), inyectado por exploración (`CapacidadesStats::bonusDanoCombate`).
- Para la Senda hace falta un multiplicador **por tipo de movimiento**; un
  único float no basta. Opciones: un mapa `TipoPokemon => float` en
  `Combatiente`, o dos floats (`danioSenda`, `defensaSenda`) si se aplica solo
  al tipo del movimiento. El mapa es más general y testeable.

### 3.3 Daño recibido

- No existe hoy un multiplicador defensivo por tipo. Se puede añadir un
  `ManejadorSendaDefensa` en la cadena, antes de `ManejadorAguante`, que
  multiplique el daño por `0,90`/`0,80` cuando el tipo efectivo del movimiento
  coincida con una Senda del defensor.
- El defensor es el jugador; los rivales no tienen Sendas, así que su
  multiplicador es 1,0. La cadena es simétrica y no requiere ramas.
- Cuidado con movimientos de daño fijo: `CadenaDanio::calculate()` los excluye
  de la cadena (`CadenaDanio.php:38-45`); decidir si la Senda aplica a daño
  fijo (probablemente no).

### 3.4 Caramelos de tipo (recompensas)

- `CalculadorRecompensas::calcularCaramelosTipo()` calcula
  `floor(exp_tipo × 0,2 / 100 × multiplicador)` y filtra cantidades 0
  (`src/Exploraciones/Domain/CalculadorRecompensas.php:313-335`). El plan v2
  cambia el divisor 500 → 250.
- Para aplicar el +10/+20 % de Senda por tipo, el calculador necesita un mapa
  `label de tipo => multiplicador`. `PokemonDerrotado` ya trae los labels.
- Alternativa: aplicarlo en `PersistirRecompensas`, pero el calculador es el
  sitio testeable y puro.

## 4. Loadout del jugador

- `ReclutadoController` valida `movimientos` como array de slugs, máximo 6
  (`app/Http/Controllers/ReclutadoController.php:315-354`). El loadout se
  guarda en `reclutados.movimientos`.
- `ReclutadoSerializer::movimientosDisponibles()` construye el catálogo de la
  especie con `desbloqueado` a partir de `PlayerMoveUnlock` y de
  `TypePathNode` (`app/Support/ReclutadoSerializer.php:155-190`).
- `ConstruirEquipoJugador` pasa `movimientosSeleccionados` al mapper
  (`src/CombateEntrenadores/App/ConstruirEquipoJugador.php:91-99`).
- `MapeadorPokemonBatalla::movimientosDeSeleccion()` resuelve slugs sin
  filtrar nivel (el controlador ya validó) y `DepuradorMovimientos` elimina
  redundantes (`MapeadorPokemonBatalla.php:139-172`; `DepuradorMovimientos.php:
  11-50`). Los movimientos del jugador no se limitan a 4 en batalla; el
  límite de 6 es de loadout, no de combate. **Esto es una inconsistencia a
  decidir:** el usuario habla de “4 movimientos” para rivales, pero el jugador
  puede llevar 6.

## 5. Movesets de rivales: estado actual

- Ruta: `GeneradorEquipoRuta` llama a `MapeadorPokemonBatalla::desdePokemon()`
  sin `movimientosSeleccionados`; el mapper usa `movimientosDe()` (solo
  `level-up` al nivel del rival) y `DepuradorMovimientos`
  (`GeneradorEquipoRuta.php:88-98`; `MapeadorPokemonBatalla.php:107-216`).
- Entrenador: mismo patrón (`GeneradorEquipoEntrenador.php:70-80`).
- Gimnasio: `GeneradorPokemonGimnasio` usa sets manuales por especie si
  existen; si no, `movimientosDeConFallback()`
  (`GeneradorPokemonGimnasio.php:181-198`). El catálogo permite
  `movimientos` por especie (`EquipoEtapaGimnasio.php:13-37`).
- No hay límite de 4 movimientos ni selector STAB/cobertura/utilidad. El
  rival lleva **todos** los movimientos de nivel disponibles (el depurador
  quita redundantes).
- Los rivales **no** consultan las Sendas del jugador. El usuario quiere que
  puedan usar todos los movimientos de la Senda del tipo, aunque el jugador no
  los tenga desbloqueados; eso implica un pool nuevo independiente de
  `PlayerMoveUnlock`.

## 6. Pokémon Showdown: qué hay realmente

- `showdown/` es el repositorio oficial (MIT, `showdown/LICENSE:1-21`), ya
  vendorizado. No hay dependencia de Node en runtime.
- `showdown/data/random-battles/gen9/sets.json`: **509 especies**, 1-3 sets
  por especie, con `role`, `movepool` (5-7 movimientos), `abilities`,
  `teraTypes`. Son **pools de Random Battle**, no sets cerrados de 4.
- `showdown/data/random-battles/gen9/bss-factory-sets.json`: **114 especies**
  con sets cerrados de 4 movimientos, EVs, naturaleza, objeto y habilidad.
- `showdown/data/random-battles/gen9/factory-sets.json`: 6 tiers (Uber, OU,
  UU…), sets cerrados por especie.
- `showdown/data/moves.ts`: 953 movimientos, 902 con `num`.
- `showdown/data/random-battles/gen9/teams.ts`: lógica de generación, no datos
  de sets.

**Match con el juego (calculado sobre `storage/data/moves.json` y
`pokemon.csv`):**

- Movimientos únicos en los movepools de `sets.json`: **348**.
- Mapeables al catálogo del juego por `num` o slug normalizado: **300 (86,2 %)**.
- No mapeables: 48 (movimientos de Gen 9/DLC que no están en el catálogo).
- Especies de `sets.json` mapeables por nombre/slug: **435 / 509 (85,5 %)**.
  Las no mapeables son formas regionales (Alola, Galar, Paldea) que el juego
  no tiene como especie separada.

**Respuesta directa:** sí hay movesets, pero no son “recomendaciones Smogon”
curadas. Son pools de Random Battle (amplios, con rol) y sets Factory
(cerrados, para 114 especies). Sirven como **capa de preferencia** para un
selector heurístico; no como sets finales.

## 7. Opciones de integración de Showdown

1. **Artefacto JSON en build-time** (recomendada): un comando artisan lee
   `sets.json` + `moves.ts`, mapea por `num`/slug y escribe
   `resources/data/showdown-movesets.json` con `roles`, `movepool` y `source`.
   El runtime solo lee el JSON; sin Node, sin migración, actualizable.
2. **Tabla `pokemon_movesets` + seeder**: más flexible y editable desde admin,
   pero añade migración, modelo y mantenimiento.
3. **Solo referencia offline**: usar los datos para afinar el heurístico y no
   importar nada. Menos preciso, cero mantenimiento.

## 8. Riesgos y preguntas abiertas

- **Compresión:** 338 nodos en nivel 3; la UI necesita agrupación y filtros.
- **Coste:** si el coste se desacopla del nivel, hay que decidir si vive en
  `SendaCostes` (por potencia) o en una columna `coste` de `type_path_nodes`.
  La primera opción no requiere migración.
- **Pasivos:** ¿+10 % en nivel 4 y +20 % en nivel 5 es acumulativo absoluto o
  +10 % adicional? ¿Aplican a mazmorra? ¿A captura de Pokémon de doble tipo
  (máximo de las dos Sendas o tipo primario)?
- **Daño:** el motor no tiene hoy un multiplicador por tipo; hay que añadir
  handlers y serializar el mapa de Sendas en `Combatiente`. ¿Aplica a daño
  fijo?
- **Caramelos de tipo:** el +20 % de Senda 5 se suma al divisor 250 del plan
  v2; hay que recalcular los hitos (Senda N5, 18 Sendas, evolución).
- **Rivales:** el pool “todos los movimientos de la Senda” no depende del
  progreso del jugador; hay que decidir si el rival de un tipo usa solo esa
  Senda o la unión de sus dos tipos. ¿Y los movimientos de cobertura de otras
  Sendas?
- **Límite de 4:** el jugador puede llevar 6; los rivales llevarían 4. Hay que
  decidir si se iguala el jugador a 4 o se deja 6.
- **Showdown:** 48 movimientos no mapean; 74 especies no mapean. El selector
  debe tener fallback por learnset + pool de Senda.
- **Legalidad:** los movepools de Random Battle incluyen movimientos que la
  especie no aprende en el juego; el usuario quiere precisamente eso para los
  rivales, pero hay que acotarlo por tipo/tier para no romper la fantasía.
- **Implementación de efectos:** no todos los movimientos del catálogo tienen
  `effect_key` implementado; el selector debe puntuar solo movimientos
  soportados por el motor.

_(La síntesis de diseño la realizará el `architect` en el Addendum III.)_
