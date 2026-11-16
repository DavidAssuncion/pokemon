# TASK-043 — Plan

- **Estado:** PROPUESTO — no autoriza implementación
- **Versión:** v1 (MVP de validación)
- **Base:** `analysis.md` (Partes A y B) + `architecture.md` (propuesta del `architect`, sesión `ses_e3a54706dffeePekePdlEh2r33`).
- **Nota:** esta tarea es de análisis y diseño. El plan queda como propuesta para una tarea de implementación futura. No se ha solicitado ni emitido aprobación.

## 1. Objetivo

Validar el bucle de misiones con el mínimo riesgo: una parte diaria y tres encargos de campo por jugador, generados de forma determinista, con progreso pasivo, recompensa automática y un ledger de eventos idempotente. Sin campaña, sin semanales y sin desbloqueo de hábitats en esta fase.

## 2. Alcance

### Incluido

- Módulo `src/Misiones/` con capas Domain/App/Infra.
- Tres tablas nuevas: `mission_instances`, `mission_events`, `mission_event_applications`.
- Generación determinista por usuario + periodo + ámbito + slot.
- Cuatro tarjetas: 1 parte diaria + 3 encargos.
- Seis arquetipos:
  1. Derrotar N Pokémon de un tipo en un hábitat.
  2. Derrotar N Pokémon de una especie/familia.
  3. Derrotar N Pokémon en un hábitat (cualquier especie).
  4. Vencer N entrenadores de un nivel/índice.
  5. Completar una exploración de duración mínima.
  6. Eclosionar N huevos.
- Aceptación pasiva y recompensa automática al completar.
- Recompensas en las monedas existentes: EXP de cuenta, caramelos de familia/EV/tipo, objetos de inventario y bayas/bonguris. Sin moneda nueva.
- Página `/misiones` reescrita y tarjeta contextual en `/habitats/{id}`.
- Enlace en el nav con badge de misiones completadas sin ver.
- Flag de configuración `misiones.enabled` (por defecto `false`).
- Documentación: ADR y `src/Misiones/context.md`.

### Fuera de alcance

- Campaña narrativa, capítulos e hitos.
- Misiones semanales y elección 1 de 3.
- Desbloqueo de hábitats y modificación de `min_lvl_1/2/3`.
- Notas de campo, hallazgos, zona del día y contratos de entrenador.
- Notificaciones push/email, ranking, logros y streaks.
- Cambios en las reglas de combate, recompensas base o economía.
- Tests. No se crean, modifican ni ejecutan.

## 3. Arquitectura y contratos

- **Bounded context:** `src/Misiones/`.
- **Domain puro:** entidades, Value Objects, colecciones, DTOs readonly y puertos. Sin Eloquent, HTTP, facades ni `Request`.
- **App:** casos de uso `ObtenerCuaderno`, `ObtenerEncargosHabitat`, `RegistrarEventoJuego`, `DescartarEncargo`.
- **Infra:** modelos Eloquent, repositorios, controlador, rutas y migraciones.
- **Punto único de emisión:** `PresentadorResultadoBatalla::procesar()` emite un `EventoJuego` por batalla con `battleId`, tipo, resultado, hábitat, nivel, especies rivales (id/tipo/familia), composición del equipo y capturas. Exploraciones, huevos, forja y granjas emiten su evento al cerrar la operación.
- **Idempotencia:** `mission_events` unique `(user_id, event_type, source_id)`; `mission_event_applications` unique `(mission_instance_id, mission_event_id)`; la recompensa se concede en la misma transacción que marca la misión como completada.
- **Periodo:** `ResolutorPeriodo` con reloj de dominio; diaria = `Y-m-d` en `Europe/Madrid`; sin cron, resolución on-read.
- **Generación:** semilla `crc32(user_id|periodo|ámbito|slot|versión_catálogo)`; cantidades por presupuesto de tiempo (4-8 min por encargo, 8-12 min la parte); validación de elegibilidad antes de persistir; `insertOrIgnore` por unique para concurrencia.
- **Recompensas:** `CalculadorRecompensaMision` reutiliza `InventarioJugador`, `OtorgadorExperiencia` y las claves de caramelo existentes (`familia:{id}`, `ev:{stat}`, `tipo:{slug}`). Sin ledger económico nuevo.
- **Rutas:** `src/Misiones/Infra/routes.php`, dentro de `auth`:
  - `GET /misiones`
  - `GET /misiones/habitat/{habitat}`
  - `POST /misiones/{mision}/descartar`
  - `GET /misiones/contador`
- **Flag:** `config/misiones.php` con `enabled`; si está apagado, no se generan misiones, no se emiten eventos y el nav no muestra el enlace.

## 4. Archivos previstos y responsable

| Archivo / grupo | Acción | Responsable |
|---|---|---|
| `database/migrations/*_create_mission_instances_table.php` | Crear | `database` |
| `database/migrations/*_create_mission_events_table.php` | Crear | `database` |
| `database/migrations/*_create_mission_event_applications_table.php` | Crear | `database` |
| `src/Misiones/Domain/**` (entidades, VOs, colecciones, DTOs, puertos, servicios) | Crear | `backend` |
| `src/Misiones/App/**` (casos de uso) | Crear | `backend` |
| `src/Misiones/Infra/Models/**` | Crear | `backend` |
| `src/Misiones/Infra/Repositories/**` | Crear | `backend` |
| `src/Misiones/Infra/Controllers/MisionesController.php` | Crear | `backend` |
| `src/Misiones/Infra/routes.php` | Crear | `backend` |
| `src/Misiones/context.md` | Crear | `documenter` |
| `app/Providers/AppServiceProvider.php` | Modificar (bindings y flag) | `backend` |
| `app/Livewire/Presenters/PresentadorResultadoBatalla.php` | Modificar (emitir evento) | `backend` |
| `src/Exploraciones/App/FinalizarExploracionHandler.php` | Modificar (emitir evento) | `backend` |
| `src/Huevos/App/EclosionarHuevo.php` | Modificar (emitir evento) | `backend` |
| `resources/views/misiones/index.blade.php` | Reescribir | `frontend` |
| `resources/views/habitats/partials/columna-derecha.blade.php` | Modificar (tarjeta de encargos) | `frontend` |
| `resources/views/layouts/app.blade.php` | Modificar (enlace y badge) | `frontend` |
| `config/misiones.php` | Crear | `backend` |
| `docs/adr/0004-sistema-misiones-mvp.md` | Crear | `documenter` |

**Nota:** los archivos compartidos (`PresentadorResultadoBatalla`, `FinalizarExploracionHandler`, `EclosionarHuevo`) los modifica un único especialista (`backend`) en secuencia; `frontend` no toca lógica de dominio.

## 5. Orden de ejecución y dependencias

1. **Datos:** migraciones, modelos y repositorios. Depende de las decisiones de unique/índices.
2. **Dominio:** VOs, entidades, generador, evaluador y calculador de recompensa.
3. **Aplicación:** casos de uso y contratos de evento.
4. **Integración:** emisión en batalla, exploración y huevo; bindings y flag.
5. **UI:** `/misiones`, tarjeta de hábitat, nav y badge.
6. **Documentación:** ADR y `context.md`.
7. **Verificación estática, revisión y seguridad.**

No hay paralelismo entre 1-3 y 4-5 hasta que los contratos de evento estén cerrados.

## 6. Criterios de aceptación

1. Con `misiones.enabled=false`, el juego se comporta exactamente igual que antes (rutas, nav y respuestas).
2. Un usuario con el flag activo recibe exactamente 1 parte diaria y 3 encargos al abrir `/misiones`; recargar no duplica ni cambia la selección.
3. Al día siguiente (medianoche `Europe/Madrid`), las misiones anteriores caducan sin penalización y se genera un conjunto nuevo.
4. Las misiones se aceptan de forma pasiva; no hay paso obligatorio de aceptación.
5. Completar un objetivo actualiza el progreso y concede la recompensa una sola vez; repetir el mismo `battleId`, recargar o reenviar la petición no duplica la recompensa.
6. Los seis arquetipos del alcance funcionan con datos reales: tipo, especie/familia, hábitat, entrenador, exploración y huevo.
7. Una misión con objetivo no elegible (hábitat bloqueado, especie fuera del pool, sin huevo posible) no se genera.
8. La tarjeta muestra título, objetivo, progreso `actual/total`, estado y recompensa; es legible en móvil y accesible con teclado y lector de pantalla.
9. El enlace del nav solo aparece con el flag activo; el badge refleja misiones completadas sin ver.
10. No se modifican las reglas de combate, las recompensas base ni la economía existente.
11. `composer run analyse`, `composer run analyse:hard`, `composer run lint` y `composer run arch` no añaden errores nuevos a los baselines.
12. La revisión independiente (`reviewer`) y la auditoría de seguridad (`security`) no dejan hallazgos críticos abiertos.

## 7. Verificación requerida

- Análisis estático: `composer run analyse` (L6), `composer run analyse:hard` (L8), `composer run lint`, `composer run arch` (report-only; añadir colector de `Misiones`).
- Revisión de arquitectura: `Domain` sin imports de Eloquent/HTTP/facades; controladores delgados; DTOs readonly; contratos de repositorio en español.
- Comprobación manual de los 12 criterios de aceptación.
- Revisión independiente con `reviewer`.
- Auditoría de seguridad con `security` por endpoints autenticados, autorización por usuario, integridad de recompensas e idempotencia.
- **Sin tests**: no se crean, modifican ni ejecutan.

## 8. Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| No hay log de derrotados en ruta/entrenador/gimnasio/mazmorra | Misiones de derrota no progresan o progresan mal | Emitir el evento en el cierre de batalla con las especies de `team2`; no depender de logs históricos |
| Doble cierre de batalla o doble clic | Recompensas duplicadas | Ledger `mission_events` + `mission_event_applications` + transacción y unique |
| Zona horaria/DST | Corte diario incorrecto | `ResolutorPeriodo` con `Europe/Madrid` y clave de fecha persistida; pruebas manuales de cambio de día |
| Corte por concurrencia | Dos conjuntos de misiones para el mismo día | `insertOrIgnore` sobre unique `(user_id, period_type, period_key, scope_key, slot)` |
| Inflación de caramelos | Rompe la curva de nidos/Sendas | Presupuesto semanal duro, pago en la moneda del sistema, sin EXP fuerte, sin moneda |
| Misiones triviales o imposibles | Sensación de relleno o bloqueo | Elegibilidad previa, presupuesto de tiempo, mínimos por arquetipo, invalidación sin castigo |
| Stub de misiones incompatible | Contrato confuso y deuda | Reescribir la vista; conservar solo tarjeta, barra y estados útiles |
| Árbol de trabajo sucio | Mezclar cambios ajenos | Delimitar archivos; no revertir cambios preexistentes; considerar worktree |
| Flag mal aplicado | Cambios visibles sin querer | Flag por defecto `false`; verificar rutas/nav con el flag apagado |

## 9. Rollback

- Apagar `misiones.enabled` detiene generación, eventos y UI; el juego base sigue igual.
- Las migraciones son aditivas (tablas nuevas); `down()` elimina solo esas tablas. Si ya hay datos que se quieran conservar, no ejecutar `down()`: basta con el flag.
- No se modifican columnas ni datos de tablas existentes en este MVP, por lo que el rollback no requiere restauración de datos.
- Los cambios de integración en `PresentadorResultadoBatalla`, `FinalizarExploracionHandler` y `EclosionarHuevo` deben quedar detrás del flag y ser reversibles con un diff acotado.

## 10. Fases posteriores (no autorizadas)

- **v1:** campaña de 8 capítulos, hitos, desbloqueo blando de hábitats (campaña o nivel), semanal 1 de 3, arquetipos de granja/forja/evolución, notas de campo, job de reconciliación.
- **v2:** hallazgos, zona del día, contratos de entrenador, historial, analítica de completado.

Cada fase requiere su propio plan, aprobación explícita y revisión.
