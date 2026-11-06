# TASK-019 — Plan de mejoras propuesto

- **ID:** TASK-019
- **Versión:** `v0-propuesta` (no aprobada)
- **Estado:** PROPUESTA — **no autoriza implementación**
- **Fecha:** 2026-11-05
- **Base:** `analysis.md` y `security.md`
- **Alcance:** roadmap priorizado para el MVP. Cada fase requiere su propio plan aprobado antes de tocar código.

---

## 1. Objetivo

Reducir primero los riesgos funcionales y de seguridad, recuperar la línea base de calidad, cerrar la documentación de módulos y después abordar la deuda estructural. El orden es deliberado: **bugs/seguridad → CI → documentación → rendimiento → refactor**.

## 2. Fuera de alcance

- Implementar cambios sin aprobación explícita de la fase.
- Crear, modificar o ejecutar tests (`tests/` fuera de alcance).
- `git push`.
- Cambiar dependencias mayores sin autorización.
- Refactors masivos de Battle, Habitats o vistas gigantes dentro de las fases 0–2.

## 3. Fases

### Fase 0 — Bugs y seguridad bloqueantes

| # | Cambio | Archivos previstos | Responsable | Criterio de aceptación |
|---:|---|---|---|---|
| 0.1 | Lock + revalidación en reclutamiento | `app/Http/Controllers/ReclutamientoController.php` | backend | Dos peticiones concurrentes no duplican reclutado |
| 0.2 | Caramelos + EXP en una transacción con lock | `app/Http/Controllers/ReclutadoController.php` | backend | No se pierde EXP; no se duplica consumo |
| 0.3 | Evolución y favoritos atómicos | `app/Http/Controllers/ReclutadoController.php` | backend | Revalidación bajo lock; límite de 6 respetado |
| 0.4 | Exploración única por reclutado | `app/Http/Controllers/ExploracionActivaController.php`, migración | backend/database | Índice único parcial o lock; dos POST simultáneos → una sola activa |
| 0.5 | Finalización idempotente | `src/Exploraciones/App/FinalizarExploracionHandler.php` | backend | `lockForUpdate`; doble dispatch no reparte dos veces |
| 0.6 | Motivo de retirada correcto | `src/Exploraciones/App/ProcesarExploracionHandler.php` | backend | Vuelta normal ≠ `grupo_enemigo`; derrota ≠ retirada |
| 0.7 | Granja segura ante concurrencia | `src/Granjas/App/AsegurarGranja.php` | backend | Primera visita concurrente no lanza 500 |
| 0.8 | Rol admin + policies | `routes/gimnasios.php`, `routes/habitats.php`, `AdminGymController`, `HabitatsController`, migración de rol | backend/database | Jugador → 403; admin → 200 |
| 0.9 | Rate limiting | `bootstrap/app.php`, `routes/web.php`, `AuthController` | backend | 429 tras N intentos; login/registro y escrituras cubiertos |
| 0.10 | Contrato de eclosión | `resources/views/equipos/index.blade.php` | frontend | Lee `data.reclutado` y muestra el nombre |
| 0.11 | Dropdown Pokédex | `resources/views/pokedex/index.blade.php` | frontend | Cierra al hacer clic fuera |
| 0.12 | Fetch con feedback | `resources/views/equipos/index.blade.php` | frontend | Error visible en crear/eliminar/añadir/quitar/alimentar/evolucionar/liberar |
| 0.13 | Slots ordenados y `sort` no mutante | `app/Models/Team.php` o repositorio, `resources/views/habitats/show.blade.php` | backend/frontend | Slots 1–3 estables; sin mutación reactiva |
| 0.14 | Eliminar no-op de favoritos de hábitat | `routes/habitats.php`, `HabitatsController`, `habitats/show.blade.php` | backend/frontend | Sin botón muerto ni endpoint zombi |

**Verificación:** análisis estático, `route:list` y comprobación manual de los flujos. Sin tests. `git diff --check`.

### Fase 1 — Línea base de calidad

| # | Cambio | Criterio de aceptación |
|---:|---|---|
| 1.1 | Resolver los 34 errores PHPStan (baseline/config y 1 `argument.type`) | `composer analyse` exit 0 |
| 1.2 | Decidir deptrac: refactor o baseline explícito | `composer arch` exit 0 con reglas justificadas |
| 1.3 | Incluir `arch` en `composer check` | `composer check` verde |
| 1.4 | Limpiar imports y casts inútiles | Sin `cast.useless` nuevos |

**Restricción:** no mezclar el refactor de Habitats con esta fase.

### Fase 2 — Documentación de módulos

| # | Cambio | Criterio de aceptación |
|---:|---|---|
| 2.1 | README + context de Forja | Responsabilidad, entradas, contratos, dependencias y deuda |
| 2.2 | README de Granjas + completar plantaciones/bayas | Context alineado con rutas y DTOs |
| 2.3 | Actualizar `docs/modulos`, `src/README`, `app/README` | Forja/Granjas incluidos; enlaces válidos |
| 2.4 | Reescribir README raíz | Stack, conteos y agentes correctos |
| 2.5 | Corregir `docs/context`, `docs/architecture` y `SESSION_VERSION` | Sin cifras contradictorias |
| 2.6 | Corregir endpoints de CombateRuta/Mazmorras/CombateEntrenadores | Ejemplos coinciden con `route:list` |
| 2.7 | Marcar spec de exploraciones como superseded | Sin `team_id` como contrato vigente |
| 2.8 | ADR de exploración individual, Forja, Shared y gobierno documental | Decisiones trazables |

**Verificación:** comprobación de enlaces relativos y contraste con `route:list`/código.

### Fase 3 — Rendimiento y limpieza

| # | Cambio | Criterio de aceptación |
|---:|---|---|
| 3.1 | Serializer de reclutados sin N+1 | Consultas constantes por colección |
| 3.2 | Eager loading de complementos de granja | Sin consultas por refugio |
| 3.3 | Eliminar código muerto verificado | Sin referencias; Pint/PHPStan verdes |
| 3.4 | Extraer constantes de dominio (EXP/caramelo, favoritos, EV) | Sin números mágicos duplicados |

### Fase 4 — Refactor estructural y UX

| # | Cambio | Criterio de aceptación |
|---:|---|---|
| 4.1 | Revertir Dominio→Presentación en Habitats | deptrac sin violaciones de Habitats |
| 4.2 | Reducir Battle/Exploraciones | Clases < ~500 líneas o responsabilidades separadas |
| 4.3 | Dividir vistas `equipos`/`habitats` | Partials/componentes; sin JS inline nuevo |
| 4.4 | Accesibilidad: focus trap, labels, tabs | Navegación por teclado verificada manualmente |
| 4.5 | Endurecer sesión, `unserialize`, `fillable` y cabeceras | Hallazgos S-07..S-10 cerrados |

## 4. Orden y dependencias

```text
Fase 0 (bugs/seguridad)
  └─ Fase 1 (CI)
       └─ Fase 2 (docs)
            └─ Fase 3 (rendimiento/limpieza)
                 └─ Fase 4 (refactor/UX)
```

- 0.4 y 0.8 requieren migración y decisión de producto (una exploración activa; rol admin).
- 0.10–0.13 no dependen del backend salvo 0.13 (orden de slots).
- Fase 1.2 depende de decidir si se refactoriza o se acepta baseline.
- Fase 4.1 depende de Fase 1.2.

## 5. Criterios de aceptación globales

- Los bugs de Fase 0 tienen reproducción y verificación manual documentadas.
- `composer check` y `composer arch` verdes al cierre de Fase 1.
- Documentación de Forja/Granjas creada y enlaces válidos al cierre de Fase 2.
- Sin regresiones en rutas: `route:list` estable.
- Sin tests nuevos ni modificados.
- Sin secretos en commits, planes ni informes.
- Sin `git push` automático.

## 6. Verificación requerida

- Análisis estático: PHPStan, Pint, deptrac.
- Comprobación manual de criterios funcionales (concurrencia, autorización, contratos).
- `git diff --check` y revisión del diff por fase.
- **No tests.**

## 7. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Los fixes de concurrencia requieren migración y pueden fallar en datos existentes | Migración idempotente + verificación previa de duplicados |
| El rol admin no existe en el producto | Decisión explícita antes de Fase 0.8 |
| El refactor de Habitats rompe contratos | Fase 4 separada, con plan específico y revisión |
| La documentación vuelve a desfasarse | ADR de gobierno documental + verificación de enlaces |
| Los cambios de sesión invalidan batallas activas | Versionado y periodo de compatibilidad |

## 8. Rollback

- Cada fase se implementa en commits separados y reversibles.
- Migraciones con `down()` probado en local.
- Si una fase falla, revertir el commit de la fase, no el checkpoint `0317179`.
- No se hace `git push` hasta validación del desarrollador.

---

**Nota:** este documento es una propuesta de roadmap. No ha sido aprobado y no autoriza implementación. Para ejecutar cualquier fase se requiere un plan `v1` con alcance cerrado y aprobación explícita.
