# TASK-044 — Plan

- **Estado:** PROPUESTO — no autoriza implementación
- **Versión:** v1 (MVP: expediente de Yell)
- **Base:** `analysis.md` (Partes A y B) + `architecture.md` (propuesta del `architect`, sesión `ses_e2f758411ffeaHnPLd7Ub77J3S`).
- **Nota:** esta tarea es de análisis y diseño. El plan queda como propuesta para una tarea de implementación futura. No se ha solicitado ni emitido aprobación.

## 1. Objetivo

Validar el bucle de facciones con el mínimo riesgo: un expediente autorado de 3 actos (Yell) en Mutxamel, sin sistema de legendarios, con 3 combates de grunts, un lugarteniente, una base de 3 pisos y un líder 3v3. Sirve para comprobar si el expediente, la presencia en el hábitat y el modal de victoria funcionan antes de escalar a las otras siete facciones.

## 2. Alcance

### Incluido

- Catálogo `Facciones` en código con la ficha de Yell (líder Púa, lugarteniente El Batería, equipos 3v3, emblema, paleta y textos de una línea).
- Módulo `src/Facciones/` con Domain/App/Infra.
- Tablas `faction_progress` y `faction_base_progress` (sin `legendary_ledger` en esta fase).
- Tres actos: patrullas → sabotaje/rescate → asalto y líder.
- Base de 3 pisos: 3v1, 3v1 y 3v3 contra el líder.
- Presencia de Yell en Calvari: emblema, una línea y patrulla opcional; el nivel 3 del hábitat queda tras el acto III (con válvula de nivel de TASK-043).
- Expediente en la pestaña de campaña de `/misiones` y tira de presencia en el hábitat.
- Modal de victoria con una clave `expediente` (emblema, acto, línea, enlace); sin usar el `log` de batalla.
- Flag `facciones.enabled` (por defecto `false`).
- Documentación: `src/Facciones/context.md` y ADR.

### Fuera de alcance

- Legendarios, anclas, `legendary_ledger` y Rayquaza.
- Las otras siete facciones.
- Diálogos ramificados, retratos, escenas estáticas o arte nuevo.
- Moneda nueva, tienda o recompensas repetibles con caramelos.
- Cambios en gimnasios, mazmorras existentes o motor 3v3.
- Poblar los 12 hábitats vacíos.
- Tests. No se crean, modifican ni ejecutan.

## 3. Arquitectura y contratos

- **Módulo:** `src/Facciones/` con Domain puro, App e Infra. `Facciones` emite eventos; `Misiones` los consume. Sin dependencia inversa.
- **Catálogo en código:** `CatalogoFacciones` y `CatalogoBases` son contenido autorado y versionado; la BD solo guarda progreso por usuario.
- **Estado:** `faction_progress` unique `(user_id, faction_slug)` con `act`, `status`, `choice`, `rewarded_at`; `faction_base_progress` unique `(user_id, base_slug)` con `current_floor` 1-3 y `completed_at`.
- **Combates:** nuevos `meta['tipo'] = 'faccion'` y `'faccion_base'`; ramas explícitas en `PresentadorResultadoBatalla::procesar()`; no dejar que caigan en `entrenador`.
- **Equipos:** `GeneradorEquipoFaccion` normaliza a 3 y reutiliza el mapeo de stats/movimientos existente. El líder no repite el trío del gimnasio de su provincia.
- **Base:** reutiliza el combate de mazmorra (3v1, jefe con fases) pero con persistencia propia; el piso 3 es 3v3 contra el líder. Cooldown 1 h tras derrota, sin repetir victorias.
- **Recompensas:** multiplicadores existentes (grunt ×2, lugarteniente/piso ×10, líder ×20 una vez) + emblema, nota y una recompensa única (en Yell: reclutado firma Toxtricity con IV 12). Sin caramelos repetibles.
- **UI:** expediente en `/misiones` (sin 7.º ítem de nav) y tira de presencia en el hábitat. Emblema CSS + sprite insignia; sin retratos.
- **Idempotencia:** eventos en `mission_events` con unique `(user_id, event_type, source_id)`; recompensa en la transacción que marca el acto, con `rewarded_at` y `lockForUpdate`.

## 4. Archivos previstos y responsable

| Archivo / grupo | Acción | Responsable |
|---|---|---|
| `database/migrations/*_create_faction_progress_table.php` | Crear | `database` |
| `database/migrations/*_create_faction_base_progress_table.php` | Crear | `database` |
| `src/Facciones/Domain/**` | Crear | `backend` |
| `src/Facciones/App/**` | Crear | `backend` |
| `src/Facciones/Infra/Models/**` | Crear | `backend` |
| `src/Facciones/Infra/Repositories/**` | Crear | `backend` |
| `src/Facciones/Infra/Controllers/FaccionController.php` | Crear | `backend` |
| `src/Facciones/Infra/routes.php` | Crear | `backend` |
| `src/Facciones/context.md` | Crear | `documenter` |
| `app/Providers/AppServiceProvider.php` | Modificar (bindings y flag) | `backend` |
| `app/Livewire/Presenters/PresentadorResultadoBatalla.php` | Modificar (ramas `faccion` y `faccion_base`) | `backend` |
| `app/Livewire/Presenters/BatallaVistaPresenter.php` | Modificar (rewards de expediente) | `backend` |
| `resources/views/misiones/index.blade.php` | Modificar (pestaña campaña) | `frontend` |
| `resources/views/facciones/**` | Crear (expediente y base) | `frontend` |
| `resources/views/habitats/partials/columna-derecha.blade.php` | Modificar (tira de presencia) | `frontend` |
| `resources/views/livewire/combate.blade.php` | Modificar (parcial de expediente) | `frontend` |
| `config/facciones.php` | Crear | `backend` |
| `docs/adr/0005-facciones-expedientes.md` | Crear | `documenter` |

**Nota:** los archivos compartidos (`PresentadorResultadoBatalla`, `BatallaVistaPresenter`, `combate.blade.php`) los modifica un único especialista por archivo y en secuencia.

## 5. Orden de ejecución y dependencias

1. **Datos:** migraciones, modelos y repositorios.
2. **Dominio:** catálogo, entidades, VOs y reglas de Yell.
3. **Aplicación:** casos de uso de expediente, combate, base y recompensas.
4. **Integración:** presentador de batalla, modal, flag y bindings.
5. **UI:** expediente, base y presencia en el hábitat.
6. **Documentación:** ADR y `context.md`.
7. **Verificación estática, revisión y seguridad.**

## 6. Criterios de aceptación

1. Con `facciones.enabled=false`, el juego se comporta exactamente igual que antes.
2. El expediente de Yell muestra los 3 actos, su estado y la recompensa; no hay más de un expediente abierto.
3. Los 3 combates de grunts, el lugarteniente, los 3 pisos y el líder se pueden completar en secuencia; cada victoria avanza el acto una sola vez.
4. La base conserva el progreso entre pisos; la derrota aplica 1 h de cooldown al piso actual y no pierde los anteriores.
5. El líder 3v3 no repite el trío exacto del gimnasio de su provincia.
6. El modal de victoria muestra emblema, acto y línea sin usar el `log` de batalla.
7. La presencia de Yell en Calvari se ve antes y después del arco; el nivel 3 se abre al cerrar el acto III o por la válvula de nivel.
8. Repetir el cierre de una batalla, recargar o reenviar la petición no duplica recompensas.
9. La recompensa única (Toxtricity IV 12) se concede una sola vez y queda registrada.
10. `composer run analyse`, `analyse:hard`, `lint` y `arch` no añaden errores nuevos.
11. `reviewer` y `security` no dejan hallazgos críticos abiertos.

## 7. Verificación requerida

- Análisis estático: `composer run analyse` (L6), `analyse:hard` (L8), `lint`, `arch` con colector `Facciones`.
- Revisión de arquitectura: Domain sin Eloquent/HTTP/facades; controladores delgados; DTOs readonly; contratos de repositorio en español.
- Comprobación manual de los 11 criterios.
- Revisión independiente (`reviewer`).
- Auditoría de seguridad (`security`): autorización por usuario, IDOR en `faction_slug`/`base_slug`, idempotencia de recompensas, manipulación del flag y del acto.
- **Sin tests.**

## 8. Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| El expediente se convierte en chore | Pérdida de diversión | 3 actos con verbos distintos; sin diarias de facción; cierre único |
| Recompensas duplicadas | Economía rota | `rewarded_at` + transacción + unique de eventos |
| Base reutiliza mal la mazmorra | Progreso compartido o bloqueos | Entidad y tablas propias; no tocar `habitats.mazmorra` |
| Líder repite el gimnasio | Sensación de refrito | Catálogo de equipos revisado; regla de no repetición |
| UI saturada | El jugador no encuentra el expediente | Vive en la pestaña de campaña; tira de presencia contextual |
| Autoría escasa | Villano plano | Presupuesto de 1 línea por acto y 1 nota; tono cómico de Yell |
| Árbol de trabajo sucio | Mezclar cambios ajenos | Delimitar archivos; worktree si hace falta |

## 9. Rollback

- Apagar `facciones.enabled` detiene el expediente, los combates y la UI; el juego base sigue igual.
- Las migraciones son aditivas; `down()` elimina solo las dos tablas nuevas.
- No se modifican columnas ni datos existentes; no hace falta restaurar datos.
- Los cambios en presentador y vistas quedan detrás del flag y son reversibles con un diff acotado.

## 10. Fases posteriores (no autorizadas)

- **v1:** Skull + Aqua + Magma, `legendary_ledger`, `ReglasAncla`, Rayquaza y encargos de facción.
- **v2:** Flare, Galaxia, Rocket, Plasma, Archivo Negro, notas y expedientes sin capítulo.
