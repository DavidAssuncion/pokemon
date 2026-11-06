# TASK-021 — Informe final

- **Estado:** `COMPLETED`
- **Fecha:** 2026-11-06
- **Plan:** v1 — Ola 1 (aprobado en `approval.md`)
- **Alcance:** Fase 2 (`Combatiente`), Fase 3B (handlers de Exploraciones) y Fase 6A (CRUD legado).
- **Restricciones:** sin tests, sin migraciones, sin dependencias nuevas, sin `git push`.

---

## 1. Resumen

La Ola 1 redujo la deuda estructural de tres áreas en paralelo, con archivos disjuntos:

1. **`Combatiente`** pasó de 2.050 a 776 líneas como fachada que delega en 15 componentes y 5 traits, conservando 189 métodos públicos, la serialización de 54 claves y el clonado.
2. **Exploraciones** quedó con `FinalizarExploracionHandler` de 162 líneas y `ProcesarExploracionHandler` de 214, con cálculo puro separado de persistencia y el contrato `poolHabitat` restaurado.
3. **CRUD legado** eliminado: 12 archivos, la ruta `/cruds`, el controlador y 40 bloques de baseline; documentación directa actualizada.

Las tres revisiones independientes emitieron **APPROVED**.

---

## 2. Archivos creados

### Battle — componentes de `Combatiente`

- `src/Battle/Domain/Componentes/Combatiente/` — 20 archivos:
  - 15 componentes de estado (`Identidad`, `Vitalidad`, `Estados`, `Condiciones`, `Turno`, `Movimientos`, `Objeto`, `Habilidad`, `Efectos`, `Stats`, `RegistroDanio`, `Recursos`, `Invocacion`, `Jefe`, `Serializador`).
  - 5 traits auxiliares (`DelegacionIdentidad`, `DelegacionObjeto`, `DelegacionTurno`, `DelegacionEfectos`, `Serializacion`).

### Exploraciones — finalización

- `src/Exploraciones/App/Finalizacion/` — 8 archivos:
  - `CalculadoraTiemposExpedicion`, `DatosDerrotadosExpedicion`, `EscritorEventosResultado`, `GeneradorTiradaCapturaExpedicion`, `MapeadorPokemonsDerrotados`, `MetodosCompatibilidadFinalizacion`, `RepartidorRecompensasExploracion`, `ResolutorBotinExploracion`.
- `src/Exploraciones/Domain/Recompensas/` — 2 archivos nuevos:
  - `CalculadoraRecompensasExpedicion`, `ClasificadorEventosExploracion`.

### Exploraciones — tick

- `src/Exploraciones/App/Procesamiento/` — 13 archivos:
  - `AplicadorDescansoExplorador`, `CargadorPoolHabitat`, `ContextoTickExploracion`, `EjecutorCombateExplorador`, `GestorBonosHabitat`, `GestorTickExploracion`, `MapeadorResolucionCombate`, `MapeadorVentanaExploracion`, `ParametrosGeneracionTick`, `ResolutorEmboscada`, `ResolutorEncuentro`, `ResolutorEventoExploracion`, `ResultadoTick`.
- `src/Exploraciones/Domain/Tick/` — 6 archivos:
  - `CalculadoraEvasionEmboscada`, `CalculadoraVentanaTick`, `DescansoAplicado`, `GeneradorEventosTick`, `GestorDescansoExplorador`, `VentanaTick`.

### Composition root

- `app/Support/ConsultorPlantacionesFactory.php` — resuelve `ConsultorPlantaciones` para construcciones manuales sin `app()` y sin App→Infra.

## 3. Archivos modificados

- `src/Battle/Domain/Combatiente.php` — fachada de 776 líneas.
- `src/Exploraciones/App/FinalizarExploracionHandler.php` — 162 líneas.
- `src/Exploraciones/App/ProcesarExploracionHandler.php` — 214 líneas; `poolHabitat` restaurado.
- `routes/web.php` — eliminada la ruta `/cruds`, su import y el comentario de `src/Crud`.
- `phpstan-baseline.php` — 38 bloques eliminados de archivos borrados.
- `phpstan-hardener-baseline.php` — 2 bloques eliminados.
- `app/README.md`, `docs/conventions.md`, `docs/modulos/README.md` — referencias directas al CRUD eliminadas.

## 4. Archivos eliminados

- `app/Crud/Base/BaseCrudService.php`
- `app/Crud/Base/BaseDTO.php`
- `app/Crud/Base/CrudGenerator.php`
- `app/Crud/Base/PostgreSqlSchemaReader.php`
- `app/Crud/Base/Generators/ControllerGenerator.php`
- `app/Crud/Base/Generators/DtoGenerator.php`
- `app/Crud/Base/Generators/ModelGenerator.php`
- `app/Crud/Base/Generators/RequestGenerator.php`
- `app/Crud/Base/Generators/RoutesGenerator.php`
- `app/Crud/Base/Generators/ViewGenerator.php`
- `app/Console/Commands/CrudGenerateCommand.php`
- `app/Http/Controllers/DashboardController.php`

---

## 5. Decisiones

1. **Fachada + componentes:** `Combatiente` conserva todas las firmas públicas; los traits solo contienen delegación o reglas de fachada, sin estado propio.
2. **Serialización estable:** `__serialize()` mantiene las 54 claves y su orden; `__unserialize()` reconstruye los componentes; `__clone()` no comparte estado mutable.
3. **Exploraciones:** cálculo puro en Domain, orquestación en App; el gestor de tick no persiste; el handler conserva `save()`, cierre y despacho.
4. **Compatibility seams:** se restauraron `poolHabitat` y los métodos privados de `Finalizar` usados por Reflection, para no romper tests existentes sin ejecutarlos.
5. **`ConsultorPlantaciones`:** dependencia obligatoria en `ResolutorBotinExploracion`; composición por defecto en `App\Support\ConsultorPlantacionesFactory`, sin `app()` ni App→Infra.
6. **CRUD:** eliminación quirúrgica; baselines podados solo para archivos borrados; sin ignores nuevos.
7. **Sin tests:** no se crearon, modificaron ni ejecutaron.

---

## 6. Verificación

| Comprobación | Resultado |
|---|---|
| Pint `--dirty --test` | pass |
| `php -l` archivos existentes | OK |
| PHPStan L6 | **34 errores** (baseline 34) |
| Deptrac | **105 violaciones** (baseline 105) |
| `git diff --check` | OK |
| `route:list` | 111 → 110; solo desaparece `GET|HEAD /cruds` |
| `Combatiente` | 776 líneas; 189 métodos públicos; 54 claves de serialización |
| `FinalizarExploracionHandler` | 162 líneas; métodos ≤40 |
| `ProcesarExploracionHandler` | 214 líneas; `poolHabitat` privado, 1 parámetro |
| Componentes nuevos | máximo 285 líneas |
| `app()`/`resolve()` en archivos B | 0 |
| Tests | **No ejecutados** |

---

## 7. Revisión y seguridad

- **Frente A:** APPROVED. Sin hallazgos críticos/altos/medios. Seguimientos LOW/NIT: `puedeActuar` 84 líneas, helper de serialización sin uso, traits con reglas.
- **Frente B:** APPROVED tras corregir `poolHabitat` y `ConsultorPlantaciones`. Hallazgos LOW: Illuminate en `CalculadoraRecompensasExpedicion`, trait shim, duplicidad de ventana temporal.
- **Frente C1:** APPROVED. Sin referencias activas; ruta eliminada; baselines coherentes. NIT: poda manual de baseline e ignore preexistente ajeno.
- **C2:** documentación directa actualizada; deuda histórica en README raíz, `docs/architecture.md` y `docs/context.md` queda para tarea aparte.
- **Seguridad:** `NO APLICA`. No se tocaron autenticación, autorización, entradas, SQL, sesiones, cookies, secretos ni endpoints públicos; solo se eliminó una ruta muerta.

---

## 8. Riesgos residuales

- No se ejecutaron tests ni smoke en entorno levantado; la equivalencia se sostiene por extracción literal, comparación y revisión.
- `EstadosCombatiente::puedeActuar()` concentra 84 líneas; reducir en Ola 2.
- `SerializadorCombatiente` debería validar estrictamente las claves del payload en la Fase 5.
- `CalculadoraRecompensasExpedicion` mantiene `Illuminate\Support\Collection` por herencia de `CalculadorRecompensas`.
- `EscritorEventosResultado` mantiene deuda App→Presentation preexistente.
- La factory de `ConsultorPlantaciones` puede divergir si cambia el binding del contenedor.
- Enlaces externos/bookmarks a `/cruds` devolverán 404; consecuencia esperada.
- Documentación histórica del CRUD en README raíz, `docs/architecture.md` y `docs/context.md`.

---

## 9. Próximos pasos

1. Commit local de la Ola 1; **sin `git push`**.
2. Plan v2 y aprobación para la **Ola 2 — Fase 3A**: dividir `ServicioEjecucionBatalla`.
3. Después: Fase 4 (`AgregadoBatalla`), Fase 5 (serialización v22 / `SESSION_VERSION`) y Fase 6B (código muerto/frontend).
4. Abrir tarea de documentación para la deuda histórica del CRUD.
5. Seguimiento A: `puedeActuar` ≤40 líneas y limpiar `serializarEfectos`.

---

## 10. Artefactos

- `request.md`
- `analysis.md` (referencia a TASK-020)
- `architecture.md` (referencia a TASK-020)
- `plan.md` (v1)
- `approval.md`
- `tasks.md`
- `execution.md`
- `review.md`
- `security.md`
- `final-report.md` (este documento)
