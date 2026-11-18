# TASK-056 — Revisión

- **Estado final:** APPROVED (2ª revisión, 2026-11-18)
- **Estado 1ª revisión:** CHANGES_REQUESTED
- **Revisor:** `reviewer` (subagente independiente)
- **Fecha:** 2026-11-18
- **Alcance revisado:** F1 (Forja de piezas: dominio, puertos, casos de uso, adaptadores, contrato API y UI).
- **Excluido:** `docs/context.md`, `src/Battle/**`, `resources/views/equipos/**`, `resources/css/**`, `tests/**` y artefactos de TASK-049…055.

## Veredicto

CHANGES_REQUESTED — sin BLOCKER; 2 HIGH y 3 MEDIUM. La funcionalidad es correcta en fórmulas,
gates, ingredientes, contrato y aislamiento de alcance. Fórmulas verificadas numéricamente:
Gengar Arma 65/130, Armadura 60/60/75, Amuleto 110+65; gates 1/3/5; ingredientes ×(1+etapa),
recipiente ×1, baya ×etapa, pieza anterior desde etapa 2.

## Criterios de aceptación (F1)

| Criterio | Resultado |
|---|---|
| Recetas existentes intactas (46) | PASS |
| `secciones` y `categoria` coherentes | PASS (tras corrección del 2026-11-18) |
| Una receta por nido, una fila por familia con selector de bioma | PASS |
| Gate por nivel de nido (1/3/5, 2/4, 3) | PASS |
| Fórmulas de stats por tipo y etapa | PASS |
| Ingredientes (bonguri, recipiente, baya, pieza anterior) | PASS |
| Fabricación atómica, `cantidad=1`, 404/422 | PASS, con hallazgo HIGH de concurrencia |
| Rutas y aislamiento de alcance | PASS |
| Sin tests creados/modificados/ejecutados | PASS |

## Hallazgos

### HIGH

1. `src/Forja/App/FabricarPieza.php:46-68` — la unicidad de la pieza no está serializada entre
   nidos de la misma familia: el lock solo cubre la fila del nido concreto; dos POST concurrentes
   sobre nidos distintos de la misma familia pasan ambos el chequeo y dejan `cantidad=2` de una
   pieza declarada única. Corrección: bloquear la clave resultado en la transacción y revalidar
   después del lock.
2. `src/Forja/Domain/Piezas/CatalogoPiezas.php:131-138` — una receta de etapa ≥2 sin padre
   resuelto se emite con `piezaAnterior = null`, permitiendo saltarse la progresión.
   Corrección: omitir esas definiciones del catálogo.

### MEDIUM

3. `src/Forja/App/ListarPiezasForja.php:10` y `src/Forja/App/FabricarPieza.php:13` — `App` depende
   de `Infra` (`LectorFamiliasPiezas`), única ocurrencia en `src/`. Corrección: puerto
   `DefinicionesPieza` en `Domain/Ports`, implementado en Infra y enlazado en `AppServiceProvider`.
4. `src/Forja/App/FabricarPieza.php:112-129` — `App` consulta `nests`/`farms` con `DB::table` y
   duplica el join de `NidosConstruidosEloquent.php:24-40`. Corrección: exponer `bloquear()` en el
   puerto `NidosConstruidos` y ejecutarlo en el adaptador.
5. `src/Forja/App/ListarRecetas.php:113,139,188-198` — `categoria` emite la clave de sección, no
   `CategoriaItem->value` como fija `architecture.md` §6.5. Corrección: registrar la decisión en el
   diseño (se mantiene la clave de sección porque la UI agrupa por ella).

## Comprobaciones pendientes

- `composer run analyse:hard` (PHPStan L8) sobre los archivos de F1.
- `composer run arch` (deptrac, report-only) filtrando `Forja`.
- Verificación funcional de `effect_keys` reales y de familias con padre ausente en la BD local.

## Riesgos residuales

- Duplicado de pieza por doble envío concurrente entre dos nidos de la misma familia hasta corregir
  el lock.
- Recetas de etapa ≥2 fabricables sin pieza anterior si los datos de evolución están incompletos.
- `categoria` con semántica distinta a la documentada puede confundir a consumidores de F2/F3.
- La pasiva del Amuleto etapa 3 puede exponer una `clave` sin efecto registrado hasta F2.
- `src/Forja/context.md` inexistente: F1 no cerrada documentalmente.
- F3 sigue bloqueada por el WIP de `resources/views/equipos/*`.

## Correcciones delegadas

| ID | Hallazgo | Responsable | Estado |
|---|---|---|---|
| C1 | Lock de unicidad de la clave resultado | `backend` | PENDIENTE |
| C2 | Omitir etapas ≥2 sin padre | `backend` | PENDIENTE |
| C3 | Puerto `DefinicionesPieza` | `backend` | PENDIENTE |
| C4 | `bloquear()` en puerto `NidosConstruidos` | `backend` | PENDIENTE |
| C5 | Anclaje de `ClavePieza` (`\z`) | `backend` | PENDIENTE |
| C6 | Mensaje genérico de `RecetaNoExiste` | `backend` | PENDIENTE |
| C7 | Registrar `categoria` = sección en el diseño | `documenter` | BLOQUEADO (permisos) |

---

# Re-revisión (2026-11-18)

- **Veredicto final:** APPROVED (tras C17/C18).

## Cierre final F2

- C17.1 (piezas vacías en `evolucionar`/`actualizarLoadout`): PASS.
- C17.2 (deadlock Fabricar↔Equipar, orden global ascendente + `attempts=3`): PASS.
- C17.3 (422 de pieza equipada en otro reclutado, `pieza_anterior_equipada`, claves corruptas): PASS.
- C18 (baseline de `Guard::id()` en `ReclutadoController`): PASS; `composer run analyse` sin
  `ignore.count` nuevo de TASK-056.
- Deuda aceptada: `recruit_gear.slot` sin CHECK, `piezas` sin consumidor hasta F3, coste de
  `bloquearClave` en fabricación.
- **Alcance:** solo C1-C6 y regresiones. Sin tests.

| ID | Resultado | Evidencia |
|---|---|---|
| C1 | PASS | `FabricarPieza.php:54` bloquea la clave resultado antes de revalidar; `InventarioJugadorEloquent.php:187-200` (`insertOrIgnore` + `SELECT ... FOR UPDATE`); UNIQUE `(user_id,item_key)`; sin residuos por rollback |
| C2 | PASS | `CatalogoPiezas.php:60-62` omite etapa ≥2 sin padre; listado y fabricación comparten el mismo catálogo; sin ruta alternativa |
| C3 | PASS | Cero imports `Infra\` en `src/Forja/App`; puertos enlazados en `AppServiceProvider.php:66-67` |
| C4 | PASS | `NidosConstruidos.php:25` + `NidosConstruidosEloquent.php:58-68`; nido ajeno/inexistente → 422, nunca 500 |
| C5 | PASS | `ClavePieza.php:20` con `\z`; acepta `arma:25`, rechaza `arma:25\n` |
| C6 | PASS | `RecetaNoExiste.php:12-15` sin parámetro; 3 call sites actualizados; sin eco en 4xx |
| Regresiones | PASS | JSON de 46 recetas estáticas intacto; flujo exitoso sin cambios |

## Hallazgos nuevos (LOW/NIT)

- `CatalogoPiezas.php:60-62` — descendientes de una etapa omitida (etapa 3 con padre etapa 2
  omitido) se emiten y quedan visiblemente bloqueados por `requiere_pieza_anterior`. No hay salto
  de progresión ni corrupción; deuda aceptada para F2.
- `NidosConstruidosEloquent.php:60-66` — el join bloquea también la fila de `farms`; amplía el
  lock sin ciclo detectado (orden nido → clave → ingredientes).
- `InventarioJugador.php:60-68` — `bloquearClave` documenta «exige transacción activa» pero no lo
  verifica; su único llamador está dentro de `DB::transaction`.

## Deuda aceptada

- Cascada de omisión C2 → F2 o cuando el diseño unifique el criterio.
- READ COMMITTED implícito de PostgreSQL: el `FOR UPDATE` daría `40001` bajo `REPEATABLE READ`.
- C7 (`categoria` = sección) sin registrar en documentación por el bloqueo de permisos.
- `src/Forja/context.md` pendiente.

