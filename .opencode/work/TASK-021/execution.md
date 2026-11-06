# TASK-021 — Ejecución

## Estado

`COMPLETED` — Ola 1 implementada, verificada y aprobada por los tres revisores. Cambios consolidados en un commit local; sin `git push`.

## Preflight

- Proyecto: `/home/david/pokemon`
- Rama: `task-011`
- HEAD de partida: `fc7fecf` (Fase 1 de TASK-020)
- Tests: no se ejecutan.
- Sin cambios en migraciones, dependencias ni `SESSION_VERSION`.

## Plan

`plan.md` v1 — Ola 1 con tres frentes paralelos:

- A: Fase 2 (`Combatiente`).
- B: Fase 3B (handlers de Exploraciones), dividida en B1 (`Finalizar`) y B2 (`Procesar`).
- C: Fase 6A (CRUD legado), con C1 (código/rutas/baselines) y C2 (documentación).

## Delegaciones

| Frente | Agente | Resultado | Revisión |
|---|---|---|---|
| A | `backend` | `Combatiente` 776 líneas; 15 componentes + 5 traits; 189 métodos; serialización 54 claves | APPROVED |
| B1 | `backend` | `FinalizarExploracionHandler` 162 líneas; `poolHabitat` no aplica | APPROVED |
| B2 | `backend` | `ProcesarExploracionHandler` 214 líneas; `poolHabitat` restaurado | APPROVED |
| C1 | `backend` | 12 archivos CRUD eliminados; `/cruds` fuera; baselines podados | APPROVED |
| C2 | `documenter` | 3 docs sin referencias a `app/Crud` | Completado |

## Correcciones relevantes

- **A:** `BarrerasVidaManager::desglosarDaño()` corregido; PHPDoc restaurados; PHPStan 34; serialización/clonado verificados.
- **B1:** import de `CalculadoraRecompensasExpedicion` corregido; `ConsultorPlantaciones` obligatorio; composition root `App\Support\ConsultorPlantacionesFactory` para evitar App→Infra; sin `app()`/`resolve()`.
- **B2:** `ResolutorEmboscada::combatir` 41→27 líneas; `poolHabitat` restaurado para `ReflectionMethod`.
- **C1:** baselines podados solo para archivos borrados; sin ignores nuevos.

## Verificación global final

| Comprobación | Resultado |
|---|---|
| Pint `--dirty --test` | pass |
| `php -l` archivos existentes | OK |
| PHPStan L6 | **34 errores** (baseline 34) |
| Deptrac | **105 violaciones** (baseline 105) |
| `git diff --check` | OK |
| `route:list` | 111 → 110; solo desaparece `cruds` |
| `Combatiente` | 776 líneas; 189 métodos públicos; serialización 54 claves |
| Handlers | `Finalizar` 162; `Procesar` 214 |
| `poolHabitat` | privado, 1 parámetro, delegación a `CargadorPoolHabitat` |
| `ConsultorPlantaciones` | no nullable en `ResolutorBotinExploracion`; factory en `App\Support` |
| Tests | **No ejecutados** |

## Hallazgos no bloqueantes

- A: `EstadosCombatiente::puedeActuar()` 84 líneas; reducir en Ola 2. `SerializadorCombatiente::serializarEfectos()` sin uso.
- A: traits con reglas de fachada; aceptables, revisar en fase posterior.
- B: `CalculadoraRecompensasExpedicion` usa `Illuminate\Support\Collection` en Domain; coherente con el `CalculadorRecompensas` preexistente.
- B: trait `MetodosCompatibilidadFinalizacion` como shim del handler; duplicidad menor de ventana temporal.
- C: baselines podados manualmente; `ignore.unmatched` preexistente ajeno.
- Docs: quedan menciones históricas a `src/Crud` en `README.md`, `docs/architecture.md` y `docs/context.md`; tarea aparte.

## Riesgos residuales

- Sin tests ni smoke en entorno levantado; la equivalencia se sostiene por comparación, revisión y análisis estático.
- `SerializadorCombatiente` debería validar estrictamente las claves en la Fase 5.
- La factory de `ConsultorPlantaciones` puede divergir si cambia el binding del contenedor.
- Deuda App→Presentation en `EscritorEventosResultado` y en el handler de finalización.
- Enlaces externos/bookmarks a `/cruds` devolverán 404; consecuencia esperada.

## Próximo paso

1. Commit local de la Ola 1; sin `git push`.
2. Plan v2 para la Ola 2 (Fase 3A `ServicioEjecucionBatalla`), con aprobación explícita.
3. Después: Fase 4 (`AgregadoBatalla`), Fase 5 (serialización) y Fase 6B (código muerto/frontend).
