# TASK-021 — Revisión independiente

- **Estado global:** COMPLETED — A, B y C1 APPROVED; C2 completado
- **Alcance:** Ola 1 de TASK-021
- **Fecha:** 2026-11-06

## Frente A — `Combatiente`

- **Estado:** APPROVED
- **Revisor:** `ses_e4f5396a9ffegFaxyxWgEWLIMS`

### Confirmaciones

- `Combatiente` 2.050 → 776 líneas; 15 componentes + 5 traits; 189 métodos públicos idénticos.
- `__serialize()` conserva las 54 claves y su orden; `__unserialize()` reconstruye los 14 componentes; `__clone()` no comparte estado mutable.
- Rutas complejas verificadas contra `fc7fecf`: `puedeActuar`, `prepararComoInvocacion`, `revivir`, habilidades, jefe, `recibirDaño`, condiciones y efectos.
- Sin cambios en `AgregadoBatalla`, `ServicioEjecucionBatalla`, `BattleSessionService`, `SESSION_VERSION`, tests, rutas ni migraciones.
- Componentes sin `Illuminate`, `App\`, `Presentation`, Eloquent, `app()`, `now()`, `mt_rand()` ni Livewire.
- Pint pass; PHPStan 34 sin errores del Frente A; deptrac 105 sin violaciones nuevas.

### Hallazgos

- **LOW:** `EstadosCombatiente::puedeActuar()` mide 84 líneas. Es extracción literal; se acepta en Ola 1 y se reduce en Ola 2.
- **LOW:** tres traits contienen reglas de fachada, no delegación pura (`setPostura`, `bloquearObjeto`, `notificarDebilitamiento`). Aceptable; mover en fase posterior si se busca fachada más delgada.
- **NIT:** `SerializadorCombatiente::serializarEfectos()` no se usa; eliminar o unificar.
- **NIT:** diferencias cosméticas de firma/import y serialización mixta entre componentes.

### Riesgos residuales

- `SerializadorCombatiente::serializar()` podría aceptar una clave inesperada al final del payload; validar estrictamente en Fase 5.
- `puedeActuar` concentra 84 líneas y 4 colaboradores; deuda de diseño para Ola 2.
- La comparación de roundtrip/clonado se apoya en el script del implementador, contrastado estáticamente.

### Seguimiento no bloqueante

1. Reducir `EstadosCombatiente::puedeActuar()` ≤40 líneas en Ola 2.
2. Eliminar o usar `SerializadorCombatiente::serializarEfectos()`.
3. Validación estricta de claves del serializador en Fase 5.

## Frente B — Exploraciones

- **Estado:** APPROVED
- **Revisor:** `ses_e4f5396a7ffeIYvFQlAaHh4ntq`

### Confirmaciones

- `FinalizarExploracionHandler` 162 líneas; `ProcesarExploracionHandler` 214; componentes ≤285; método máximo 38.
- Métodos Reflection de B1 (`duracionReal`, `factorTiempo`, `incidentes`, `objetosPerdidos`, `restarPerdidas`, `idsDerrotados`) conservan firma y semántica.
- `poolHabitat` restaurado: privado, 1 parámetro, delega en `CargadorPoolHabitat`; el test de Reflection vuelve a encontrarlo.
- `ConsultorPlantaciones` obligatorio en `ResolutorBotinExploracion`; el handler usa `ConsultorPlantacionesFactory` como composition root y mantiene el binding de producción.
- Shape de `eventos`, idempotencia, timing de persistencia, tick y RNG conservados.
- Sin `app()`/`resolve()`/`now()`/`mt_rand()` en el camino tocado.
- Pint pass; PHPStan 34 sin errores de B; deptrac 105.

### Hallazgos

- **HIGH:** `poolHabitat` eliminado y usado por `ReflectionMethod`. **Resuelto.**
- **MEDIUM:** `ConsultorPlantaciones` nullable y sin resolución de contenedor en construcciones manuales. **Resuelto** con dependencia obligatoria + composition root `App\Support\ConsultorPlantacionesFactory`.
- **LOW:** `CalculadoraRecompensasExpedicion` usa `Illuminate\Support\Collection` en Domain; coherente con el `CalculadorRecompensas` preexistente, no bloqueante.
- **LOW:** trait `MetodosCompatibilidadFinalizacion` como partición artificial del handler; aceptable como shim.
- **LOW:** duplicidad de construcción de ventana temporal entre B1 y B2; no bloqueante.
- **NIT:** docblock de `poolHabitat` menciona `poolDeHabitat()`, método que ya no existe; actualizar el comentario.

### Riesgos residuales

- La factoría manual de `ConsultorPlantaciones` puede divergir si cambia el binding del contenedor.
- `EscritorEventosResultado` mantiene dependencia App → Presentation, deuda preexistente movida.
- `CalculadoraRecompensasExpedicion` sigue acoplada a `Illuminate\Support\Collection` por herencia de la API de `CalculadorRecompensas`.

### Cierre

- Re-revisión final: **APPROVED**. Sin bloqueos pendientes.

## Frente C1 — CRUD legado

- **Estado:** APPROVED
- **Revisor:** `ses_e4f5396a4ffeo5eo1nzg8JRHnM`

### Confirmaciones

- 12 archivos eliminados (`app/Crud/Base/**`, `CrudGenerateCommand.php`, `DashboardController.php`).
- Ruta `GET|HEAD /cruds` eliminada; `route:list` pasa de 111 a 110 rutas y solo desaparece `cruds`.
- Todas las acciones restantes resuelven clase y método.
- 40 bloques de baseline eliminados (38 + 2), 0 añadidos; solo apuntan a archivos borrados.
- Sin referencias activas a `App\Crud`, `app/Crud`, `CrudGenerateCommand`, `crud:generate`, `/cruds`, `src/Crud` ni `DashboardController` en código/rutas/config.
- Pint pass; PHPStan 34; deptrac 105; `php -l` y `git diff --check` OK.
- Sin tests.

### Hallazgos

- **NIT:** poda manual de baselines en lugar de regeneración; aceptada por diff mínimo y coherencia.
- **NIT:** `ignore.unmatched` preexistente de `app/Support/DesbloqueoPokedex.php`; ajeno a C1, no corregir aquí.

### Riesgos residuales

- Documentación histórica (`README.md` raíz, `docs/architecture.md`, `docs/context.md`) aún menciona el CRUD; C2 cubre solo referencias directas a `app/Crud`.
- Enlaces externos/bookmarks a `/cruds` devolverán 404; consecuencia esperada.
- Cambios concurrentes de A/B/C2 no revisados en este veredicto.

## C2 — Documentación

- **Estado:** Completado; no requiere revisión de código.
- `app/README.md`, `docs/conventions.md` y `docs/modulos/README.md` sin referencias a `app/Crud`, `crud:generate` ni `/cruds`.
- Queda deuda documental histórica en el README raíz, `docs/architecture.md` y `docs/context.md`; se recomienda tarea aparte.

## Pendiente

- Informe final y commit local de la Ola 1; sin `git push`.
- Ola 2 (Fase 3A `ServicioEjecucionBatalla`) requiere plan y aprobación propios.
