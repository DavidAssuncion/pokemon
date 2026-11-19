# TASK-061 — Plan v1

- **ID:** TASK-061
- **Versión:** v1
- **Fecha:** 2026-11-18
- **Proyecto:** `/home/david/pokemon`
- **Estado:** APROBADO v1 (2026-11-18)

## 1. Objetivo

1. **Descripciones de habilidades:** completar `abilities.effect` para las 228 habilidades
   asignadas que hoy están vacías, con textos fieles al comportamiento implementado en
   `BattleEffectServiceProvider` y en `src/Battle/Domain/Effects/**`. Las 23 habilidades con
   efecto personalizado se describen desde el código, no desde el texto oficial.
2. **Filtros de Pokédex:** añadir en `/pokedex` dos filtros independientes de selección
   única, **Habilidad** y **Movimiento**, con buscador, combinables entre sí y con los
   filtros actuales (visto, tipo, esfuerzo, búsqueda). El filtro de movimiento cubre todo el
   learnset (`pokemon_moves`, fuentes `level-up`, `machine`, `egg`, `tutor`, `custom`); el de
   habilidad incluye todas las habilidades del pivote.

## 2. Alcance

### Dentro del alcance

- Catálogo de descripciones por `effect_key` en `storage/data/abilities_descripciones.json`
  (227 claves) y seeder idempotente de una sola pasada.
- Dos `RelationFilter` nuevos en el registro `pokemon` del datagrid (`ability`, `move`) por
  id, con columna cualificada.
- Catálogos de habilidades y movimientos asignados, embebidos en la vista.
- Componente Blade reutilizable de combobox con buscador y factoría Alpine.
- Integración de los dos comboboxes en la Pokédex.
- ADR de la decisión.

### Fuera del alcance

- Motor de combate, `FabricaEfectos`, clases de `src/Battle/**`, `Ability`, `Move`,
  `PresentadorDetalleEspecie`, `DatagridService`, `RelationFilter`.
- Migraciones e índices: el diseño concluye que los índices actuales cubren el acceso; queda
  una contingencia documentada si `EXPLAIN` supera 20 ms.
- Los 3 JSON de lotes y el CSV de habilidades no se reescriben.
- Persistencia de filtros en la URL y arreglo del bug preexistente de cierre de los
  dropdowns de tipo/esfuerzo.
- Tests: no se crean, modifican ni ejecutan.

## 3. Arquitectura y contratos

Se adopta el diseño de `architecture.md` con dos ajustes de cumplimiento:

1. **DTO de catálogo:** `OpcionFiltro` (`final readonly class`, `JsonSerializable`) evita
   devolver `list<array{id,name}>` desde un método público, conforme a
   `.ai/rules/arrays-dto-collections.md`. `CatalogoFiltrosPokedex` devuelve una colección
   tipada de `OpcionFiltro`; el array solo aparece en la serialización de vista (`@js`).
2. **Propiedad de las descripciones:** las escribe `backend` por lotes secuenciales; el
   `documenter` solo redacta el ADR. El protocolo de fidelidad y la tabla de cobertura se
   registran en la coordinación, no en `docs/`.

### Contrato de filtros

- `filter[ability]`: id numérico de `abilities`; `filter[move]`: id numérico de `moves`.
- `RelationFilter('abilities', 'abilities.id')` y `RelationFilter('moves', 'moves.id')`.
- Valor no numérico o ≤ 0 ⇒ filtro ignorado; id inexistente ⇒ 0 resultados.
- Cada filtro es un `whereHas` independiente ⇒ AND con el resto.
- `meta.total`/`meta.last_page` reflejan los filtros; `meta.counts` sigue siendo global.

### Contrato de catálogo y UI

- `OpcionFiltro { id: int, name: string }`, serializado como `{id,name}`.
- Solo se ofrecen habilidades/movimientos asignados a alguna especie (278 / 639).
- Evento del combobox: `combobox-seleccionado` con
  `{ name: 'ability'|'move', id: int|null, label: string|null }`.
- Los ids viven en `abilityFilterId`/`moveFilterId` del estado Alpine y se añaden a
  `buildParams`.
- El combobox es accesible: `aria-haspopup`, `aria-expanded`, `role="combobox"`,
  `role="listbox"`, `aria-activedescendant`, teclado (↑/↓/Enter/Esc/Tab/Home/End),
  `@click.outside`.

### Contrato de datos

- `storage/data/abilities_descripciones.json`:
  `{version:1, fuente:"BattleEffectServiceProvider::registrarHabilidades", descripciones:{effect_key: texto}}`.
- El seeder fusiona CSV + asignaciones + lotes antes de escribir; los lotes solo aportan
  `effect_keys`; el texto se resuelve por `effect_key` para habilidades de una sola clave y
  por CSV para las compuestas.
- Nunca se escribe `effect` vacío sobre un texto existente. Segunda ejecución: 0 cambios.

## 4. Archivos y responsables

| # | Archivo | Acción | Responsable |
|---|---|---|---|
| 1 | `app/Providers/DatagridServiceProvider.php` | añadir `ability`, `move` e `idPositivo` | backend |
| 2 | `app/Support/Pokedex/OpcionFiltro.php` | crear DTO readonly `JsonSerializable` | backend |
| 3 | `app/Support/Pokedex/CatalogoFiltrosPokedex.php` | crear consulta de catálogos asignados | backend |
| 4 | `app/Http/Controllers/PlayerController.php` | pasar `habilidades` y `movimientos` a la vista | backend |
| 5 | `resources/views/components/ui/combobox.blade.php` | crear combobox accesible con buscador | frontend |
| 6 | `resources/views/partials/combobox-filtro-script.blade.php` | crear factoría Alpine `comboboxFiltro` | frontend |
| 7 | `resources/views/pokedex/index.blade.php` | insertar los dos comboboxes, estado, handler y `buildParams` | frontend |
| 8 | `database/seeders/AbilitySeeder.php` | reescribir a una pasada idempotente | backend |
| 9 | `storage/data/abilities_descripciones.json` | crear 227 descripciones por `effect_key` | backend (lotes) |
| 10 | `docs/adr/0005-filtros-pokedex-y-descripciones-habilidades.md` | crear ADR | documenter |

Sin cambios: `app/Datagrid/DatagridService.php`, `app/Datagrid/RelationFilter.php`,
`app/Models/Ability.php`, `app/Models/Move.php`, `app/Support/PresentadorDetalleEspecie.php`,
`src/Battle/**`, los 3 JSON de lotes, el CSV, `tests/**`, migraciones.

## 5. Orden de ejecución y dependencias

1. **B1 — Filtros y catálogo (backend):** archivos 1-4. Verificación con
   `DatagridService::list('pokemon', ...)` en tinker para ids conocidos.
2. **F1 — Componente (frontend):** archivos 5-6, sin dependencia de B1.
3. **F2 — Integración en Pokédex (frontend):** archivo 7, depende de F1 y de los nombres de
   props de B1. Archivo compartido: se ejecuta solo, sin paralelismo.
4. **D1 — Seeder (backend):** archivo 8. Independiente de B1/F1; puede ir en paralelo con
   ellos por archivos disjuntos, pero se ejecuta antes de las descripciones.
5. **D2-D7 — Descripciones (backend):** archivo 9 en 6 lotes secuenciales (≈40-45 claves):
   1. Personalizadas, escudos y STAB (incluye las 23 prioritarias).
   2. Inmunidades, absorciones y redirecciones.
   3. Clima, multiplicadores de estadística e invocadores.
   4. Curación, daño de fin de turno y drenaje.
   5. Contacto y estados.
   6. Pasivas, reacciones, marcadores y resto.
   Cada lote: leer provider/clases, escribir entradas, validar JSON, ejecutar el seeder y
   comprobar cobertura. El mismo archivo se edita en secuencia, nunca en paralelo.
6. **DOC — ADR (documenter):** archivo 10, en paralelo con D2-D7 (archivo disjunto).
7. **R1 — Revisión (reviewer):** diff completo, criterios de aceptación y muestreo de al
   menos 10 descripciones, incluidas las 23 personalizadas.
8. **S1 — Seguridad (security):** parámetros de filtro, SQL, escape en JS/Blade y exposición
   de datos.
9. **Cierre:** corregir hallazgos, informe final y estado.

## 6. Criterios de aceptación

1. **Cobertura de descripciones:** las 278 habilidades asignadas tienen `effect` no vacío;
   las 227 claves de las 228 habilidades vacías están en el JSON; 0 claves sin descripción.
2. **Fidelidad:** las 23 habilidades personalizadas describen el comportamiento real del
   provider/clases (multiplicadores, condiciones y límites), no el texto oficial.
3. **Seeder:** dos ejecuciones consecutivas dejan el mismo `md5` del conjunto
   `id||effect`; la segunda reporta 0 actualizadas; no se crean ni eliminan habilidades.
4. **Filtro de habilidad:** `filter[ability]=<id>` devuelve solo especies con esa habilidad,
   incluidas las ocultas; se combina con `filter[move]`, `types`, `effort`, `visto` y
   `search`.
5. **Filtro de movimiento:** `filter[move]=<id>` devuelve especies con ese movimiento en
   cualquier fuente del learnset.
6. **Valores inválidos:** no numéricos se ignoran; ids inexistentes devuelven `total: 0`.
7. **Contadores:** `meta.counts` no cambia al aplicar los filtros nuevos.
8. **UI:** dos comboboxes con buscador insensible a acentos, teclado y ARIA; limpiar con ✕;
   clic fuera cierra; el mensaje de vacío menciona los filtros activos.
9. **Sin regresiones:** los filtros de tipo/esfuerzo/búsqueda/visto siguen funcionando.
10. **Estática:** `pint`, `php -l`, `phpstan` y `php artisan view:cache` pasan sin errores
    nuevos.
11. **Tests:** `tests/**` sin cambios y suite no ejecutada.
12. **ADR:** existe y refleja las decisiones de filtros y descripciones.

## 7. Verificación requerida (sin tests)

### Análisis estático

- `vendor/bin/pint --dirty --format agent`.
- `php -l` sobre los PHP nuevos/modificados.
- `composer analyse` (PHPStan con baseline existente).
- `php artisan view:cache` y `php artisan view:clear`.
- `composer arch` si el cambio de `app/Support` lo justifica.
- `git diff --stat` limitado a los archivos de la tabla.

### Comprobación manual

- Tinker: `DatagridService::list('pokemon', ['filter' => ['ability' => <id>]])` y equivalentes
  con `move`, combinaciones, ids inválidos y conteos; comparar con `abilities[].id` del
  detalle.
- SQL de cobertura: 0 habilidades asignadas con `effect` vacío; 0 claves sin descripción.
- Seeder dos veces y comparación de huella.
- Revisión visual/manual de `/pokedex` por el desarrollador: buscador, selección, limpiar,
  combinación y estado vacío.
- `EXPLAIN (ANALYZE, BUFFERS)` de las consultas de filtro y catálogo; umbral 20 ms.

## 8. Riesgos y mitigaciones

| Riesgo | Severidad | Mitigación |
|---|---|---|
| `id` ambiguo en el `EXISTS` de Postgres | alto | Columna cualificada `abilities.id`/`moves.id` y prueba manual |
| Reintroducir el vaciado de `effect` | alto | Seeder de una pasada; validación de cobertura tras cada lote |
| Descripciones no fieles en las 23 personalizadas | alto | Protocolo desde provider/clases; muestreo del reviewer |
| Volumen de 227 descripciones agota el contexto de un especialista | alto | 6 lotes secuenciales con cobertura verificable |
| Filtro de movimiento incoherente con el modal (solo level-up) | medio | `title`/`aria-description` explicativo y nota en el informe |
| Payload de catálogos (+31 KB) | bajo | Gzip (~7 KB); medir y cachear solo si supera 50 ms |
| Conflicto al editar el JSON por lotes | medio | Secuencial, orden alfabético, una clave por línea |
| Regresión de `counts` o de filtros existentes | bajo | No se toca `pokemonCounts`; criterios 7 y 9 |
| Bug preexistente de cierre de dropdowns | bajo | Los comboboxes usan `@click.outside`; no se modifica el handler antiguo |
| XSS al inyectar nombres en JS | bajo | `@js()` en todos los payloads; sin `{{ }}` dentro de JS |
| Índices insuficientes | bajo | Decisión de no migrar; contingencia con umbral 20 ms |

## 9. Rollback

- No hay migraciones ni cambios de esquema: el rollback es de código y datos.
- Revertir los archivos de la tabla en orden inverso; el repositorio conserva el estado
  anterior en `fbe370b`.
- Restaurar `storage/data/abilities_descripciones.json` y `AbilitySeeder.php`, y re-ejecutar
  el seeder anterior para devolver los textos del CSV. No se usan `git reset --hard` ni
  `git clean`.
- Los filtros nuevos son aditivos: desregistrarlos en `DatagridServiceProvider` y retirar la
  UI deja la Pokédex como estaba.
