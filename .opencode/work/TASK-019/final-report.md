# TASK-019 — Informe final

- **ID:** TASK-019
- **Estado:** `COMPLETED` (auditoría e informe)
- **Fecha:** 2026-11-05
- **Proyecto:** `/home/david/pokemon`
- **Base:** rama `task-011`, commit `0317179`
- **Alcance:** auditoría de solo lectura de backend, frontend, documentación y seguridad. Sin tests, sin `git push`, sin cambios de código de aplicación.

---

## 1. Resumen

Se auditó el proyecto completo sobre el checkpoint `0317179` (244 archivos: 52 modificados + trabajo nuevo de Forja, Granjas, ítems, exploraciones, ADR, migraciones y assets). Se delegó en `analyst` (backend y frontend), `documenter` y `security`, y se contrastaron los hallazgos de mayor impacto con evidencia directa (`route:list`, migraciones, configs, `grep` y herramientas estáticas).

**Resultado:** no hay bugs críticos de pérdida masiva de datos ni bypass de autenticación, pero sí **4 riesgos altos de concurrencia/datos**, **1 riesgo alto de autorización**, **3 hallazgos altos de seguridad**, **`composer check` y `composer arch` rotos**, **deuda estructural alta** y **documentación desfasada**. El proyecto es mantenible por módulos y tiene buenas bases (DTOs, Collections, scope de usuario, CSRF, datagrid con whitelists), pero el MVP necesita una fase de estabilización antes de seguir añadiendo funcionalidad.

## 2. Hallazgos principales

### 2.1 Bugs y datos (alto)

1. **Reclutamiento con carrera:** `ReclutamientoController.php:36` no bloquea el reclutable; `discard()` sí lo hace en `:80`.
2. **Pérdida de EXP por lost update:** `ReclutadoController.php:81-108` guarda `exp` fuera de la transacción que consume caramelos.
3. **Doble exploración activa:** `ExploracionActivaController.php:331-355` hace check-then-create sin lock ni índice único parcial.
4. **Doble reparto de recompensas:** `FinalizarExploracionHandler.php:91-94` usa `refresh()` + `regreso !== null` sin `lockForUpdate`.
5. **Granja con carrera:** `AsegurarGranja.php:20-36` hace `first()` + `save()` sin lock; el unique puede provocar 500.
6. **Motivo de retirada incorrecto:** `ProcesarExploracionHandler.php:107-116` etiqueta una vuelta normal como `retirada/grupo_enemigo`.
7. **Contrato de eclosión roto:** el backend devuelve `{reclutado: ...}` y la vista lee `data.pokemon/nombre/pokemon_name`.

### 2.2 Seguridad (alto)

1. **CRUD de gimnasios sin admin:** `/api/admin/gyms` y `/gimnasios/admin` solo exigen `auth`; cualquier jugador puede crear/editar gimnasios.
2. **Mutaciones globales de hábitats sin admin:** asignar/quitar familias y mover Pokémon de nivel solo exigen `auth`.
3. **Sin rate limiting:** login, registro y acciones de juego sin `throttle`/`RateLimiter`.
4. **Medios:** 17 avisos de `composer audit`, `users.name` sin `unique`, scope `BelongsToUser` fail-open, `unserialize` sin `allowed_classes`, sesión de un año sin cifrar y mensajes internos expuestos.

### 2.3 Frontend (alto)

1. **Dropdown de Pokédex:** `pokedex/index.blade.php:748` usa `closest('[x-data]')` y el `<body>` tiene `x-data`; nunca se cierra al hacer clic fuera.
2. **Fetch silencioso en `/equipos`:** al menos 9 acciones solo hacen `console.error`; el usuario no ve el fallo.
3. **Modales sin focus trap:** no hay `x-trap`, foco inicial, retorno ni scroll lock.
4. **Feature inalcanzable:** `openExploracionModal()` solo existe como definición.
5. **Slots sin ordenar:** `$team->members[$i]` sin `orderBy('slot')`; `.sort()` muta estado reactivo en getters de `habitats/show`.

### 2.4 Mantenibilidad

- **PHPStan L6:** 34 errores; `composer check` falla.
- **deptrac:** 113 violaciones; 78 Habitats, 16 Battle, 12 Exploraciones y 7 controladores en `Infra/`.
- **Archivos gigantes:** `Combatiente.php` (~65 KB), `app/Livewire/Combate.php` (~67 KB), `equipos/index.blade.php` (~194 KB), `habitats/show.blade.php` (~180 KB), handlers de exploración de 29–33 KB.
- **`Shared` depende de Battle/Pokemon**, contradiciendo la regla documentada.
- **`src/Crud` no existe**, pero se documenta y los generadores escriben ahí.
- **N+1** en `ReclutadoSerializer` y complementos de granja.
- **Código muerto:** `toggleFavorito` no-op, `poolHabitat`, vistas `misiones`/`reclutados`, `openExploracionModal`, métodos Alpine sin uso, CSS huérfano.

### 2.5 Documentación

- **Forja** no tiene README ni context; **Granjas** no tiene README y su context está incompleto.
- `docs/modulos/README.md`, `src/README.md` y `app/README.md` omiten Forja/Granjas y afirman que todos los módulos tienen README.
- `SESSION_VERSION` real = 21; los docs dicen 10, 11 o 20 en siete sitios.
- Conteos desfasados: 76 migraciones vs 60; 32 modelos vs 14; 13+base controladores vs 10; 24 archivos Chain vs 7.
- Endpoints erróneos en READMEs de CombateRuta, Mazmorras y CombateEntrenadores.
- `docs/spec_evolucion_exploraciones.md` usa `team_id`; el código usa `reclutado_id`.
- `docs/gimnasios.md` dice 5 gimnasios; hay 18.
- `docs/architecture.md` cita `DTOEquipoBatalla` inexistente y «4 providers» cuando hay 3.
- **Positivo:** 0 enlaces relativos rotos; ADR 0001–0003 bien estructurados.

## 3. Archivos creados/modificados

### Coordinación (esta tarea)

- `.opencode/work/TASK-019/request.md`
- `.opencode/work/TASK-019/analysis.md`
- `.opencode/work/TASK-019/architecture.md`
- `.opencode/work/TASK-019/plan.md`
- `.opencode/work/TASK-019/approval.md`
- `.opencode/work/TASK-019/tasks.md`
- `.opencode/work/TASK-019/execution.md`
- `.opencode/work/TASK-019/review.md`
- `.opencode/work/TASK-019/security.md`
- `.opencode/work/TASK-019/final-report.md`
- `.opencode/work/TASK-019/phpstan.log`
- `.opencode/work/TASK-019/deptrac.log`
- `.opencode/work/TASK-019/routes.json`
- `.opencode/work/TASK-019/verification-*.log`

### Código de aplicación

**Ninguno.** No se modificaron `src/`, `app/`, `resources/`, `routes/`, `database/`, `config/` ni `tests/`.

### Git

- Checkpoint creado antes de auditar: `0317179 chore: checkpoint de trabajo en curso antes de auditoría TASK-019` (244 archivos).
- **No se hizo `git push`.**
- El commit incluye el trabajo preexistente del desarrollador; no incluye `.opencode/work/TASK-019/`.

## 4. Verificación ejecutada

| Comprobación | Resultado |
|---|---|
| Pint | **pass** |
| PHPStan L6 | **34 errores** (exit 1) |
| deptrac | **113 violaciones** (exit 1) |
| `php artisan route:list` | 111 rutas |
| `composer audit --locked` | 17 avisos, 0 abandonados |
| Middleware `/api/admin/gyms` | solo `web + Authenticate` |
| Middleware mutaciones de hábitats | solo `web + Authenticate` |
| `users.name` unique | **no** |
| Índice único parcial de exploraciones | **no** |
| `SESSION_VERSION` | 21 |
| `SESSION_LIFETIME` | 525600 |
| `SESSION_ENCRYPT` | false |
| Enlaces relativos de documentación | 0 rotos |
| `git status` | solo `.opencode/work/TASK-019/` |
| Tests | **no ejecutados** |

## 5. Decisiones

- **Solo auditoría:** no se implementan correcciones ni se actualizan README; el desarrollador eligió «Solo auditoría e informe».
- **Commit previo:** se creó el checkpoint `0317179` para auditar el working tree real; sin push.
- **Prioridad:** bugs → mantenibilidad → documentación → limpieza.
- **Sin ADR nuevo:** las decisiones propuestas (exploración individual, Forja, Shared, gobierno documental) están en `plan.md` como candidatas.
- **Sin tests:** fuera de alcance por reglas del proyecto.

## 6. Riesgos residuales

- Los bugs de concurrencia no se reprodujeron con carga real; son riesgos basados en el código y el esquema.
- El rol de administrador no existe en el producto; H-01/H-02 requieren una decisión de producto.
- El `APP_KEY` de `.env.example` fue reportado por seguridad, pero no se reprodujo el valor por política de secretos; debe revisarse y rotarse si aplica.
- Los avisos de dependencias requieren actualización y reverificación; no se instalaron dependencias.
- La documentación desfasada puede inducir a implementar endpoints o estructuras inexistentes.
- El refactor de Habitats y la división de vistas/classes gigantes son cambios grandes con riesgo de regresión.

## 7. Próximos pasos recomendados

1. **Fase 0 — estabilización:** corregir concurrencia, autorización admin, rate limiting, motivo de retirada, contrato de eclosión y bugs de UI de alto impacto.
2. **Fase 1 — CI:** resolver PHPStan 34 y deptrac 113; incluir `arch` en `composer check`.
3. **Fase 2 — documentación:** README de Forja/Granjas, índices, conteos, endpoints y ADRs.
4. **Fase 3 — rendimiento/limpieza:** N+1, código muerto y constantes.
5. **Fase 4 — refactor/UX:** Habitats, Battle/Exploraciones, vistas gigantes y accesibilidad.

Para ejecutar cualquier fase se requiere un plan `v1` con alcance cerrado y aprobación explícita. El roadmap está en `plan.md`; **no autoriza implementación**.
