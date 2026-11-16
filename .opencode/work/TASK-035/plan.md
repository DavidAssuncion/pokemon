# TASK-035 — Plan v1

- **ID:** TASK-035
- **Versión:** v1
- **Fecha:** 2026-11-09
- **Estado:** AWAITING_APPROVAL — no autoriza implementación
- **Base:** rama `task-011`
- **Análisis:** `analysis.md` (COMPLETED)
- **Diseño:** `architecture.md` (COMPLETED)

## 1. Objetivo

Mostrar en el modal **Configurar Formación**, antes de confirmar el combate, una comparación aproximada de **PC (Poder/Puntos de Combate)** entre el equipo seleccionado y el rival, con un veredicto que ayude al jugador a decidir si evita el combate.

Aplica a:

- Combate de ruta 3v3 (modal de hábitats).
- Combate contra entrenador de hábitat (mismo modal).
- Combate de gimnasio (modal de `gimnasios/show`).

## 2. Alcance

### 2.1. Incluido

- Cálculo backend del PC del equipo propio reutilizando el CP canónico existente (`CalculadorCP` + `BattleStats`).
- Cálculo del PC rival:
  - Ruta 3v3: media ponderada del pool del hábitat/nivel, marcada como aproximada.
  - Entrenador: suma exacta del equipo determinista que se combatirá.
  - Gimnasio: suma exacta del roster de la etapa actual, sin revelar composición ni nivel rival.
- Tres endpoints GET de solo lectura bajo `auth`, con validación de propiedad del equipo.
- Bloque de comparación en ambos modales con totales y veredicto.
- Estados: sin equipo, equipo incompleto, cargando, error, sin rival, no disponible.
- Accesibilidad: `aria-live`, `role="status"`, texto + icono (no solo color), `prefers-reduced-motion`.
- Reutilización del mapeo de stats base para que el PC de `/equipos` y el del modal coincidan.

### 2.2. Fuera de alcance

- Cambiar fórmulas de combate, balance, stats o CP.
- Cambiar `$teams`, `TeamAggregate`, contratos de inicio de combate o sesiones de batalla.
- Simular combates o mostrar probabilidad de victoria.
- Mostrar composición, especies, tipos, niveles individuales, IV/EV o movimientos del rival.
- Mostrar desglose de PC por miembro (solo total).
- Guardar el rival de ruta antes de combatir para dar un PC exacto.
- Caché del cálculo (MVP sin caché).
- Migraciones, seeders, factories, dependencias nuevas.
- Tests: no se crean, modifican ni ejecutan.
- Documentación nueva: no se solicita; el ADR queda como borrador en `architecture.md`.

## 3. Decisiones de producto y diseño

1. **Fórmula única:** se reutiliza `CalculadorCP` y `BattleStats`; no se crea un segundo CP ni se duplica en JS.
2. **PC propio:** suma de los 3 miembros al nivel actual del usuario, con sus EVs/IVs reales.
3. **PC ruta:** `3 ×` media ponderada del pool (`capture_rate/hatch`), mostrado con `≈` y nota de estimación.
4. **PC entrenador/gimnasio:** suma exacta del rival que se combatirá; solo se expone el agregado.
5. **Veredicto:** `ratio = pc_rival / pc_equipo`; `< 0.8` favorable, `0.8–1.2` parejo, `> 1.2` desfavorable. Umbrales en `UmbralesVeredictoPc`.
6. **Presentación:** totales + veredicto. Gimnasio se muestra como “PC del rival” de la etapa actual, con nota “No se muestra su equipo”; sin `≈` porque el cálculo de la etapa es determinista. Ruta sí usa `≈`.
7. **Redondeo:** el backend devuelve enteros; la UI formatea con `Intl.NumberFormat('es-ES')`. Sin redondeo adicional.
8. **Equipo incompleto:** si no hay 3 miembros válidos, no se muestran números; mensaje “El equipo necesita 3 miembros para combatir.”
9. **Entrenador derrotado / gimnasio completado:** `estado: no_disponible` con HTTP 200; el frontend muestra el aviso y no bloquea el combate por el indicador.
10. **Formación:** vanguardia/retaguardia no altera el PC; no se refetch al cambiar posición.
11. **Cálculo backend:** el frontend solo presenta; no hay fórmulas en Blade/Alpine.
12. **Sin caché en MVP:** una petición por apertura de modal o cambio de equipo/nivel.
13. **Errores del indicador:** nunca bloquean el botón “Combatir”; las validaciones de combate existentes se mantienen intactas.

## 4. Arquitectura y contratos

Se sigue `architecture.md`:

- **Dominio puro** en `src/Shared/Domain/Pc`: enums `EstadoComparacionPc`, `MetodoPcRival`, `VeredictoPc`; `UmbralesVeredictoPc`; `ComparadorPc`; DTO `ComparacionPc`.
- **Extensión de dominio:** `CalculadorCP::desdeStats(StatsValue $base, StatsValue $evs, ?StatsValue $ivs, int $nivel): int`, sin modificar `calcular()`.
- **Seam de aplicación** en `app/Support/Pc`: `EstadisticasBasePokemon`, `CalculadoraPcReclutado`, `ObtenerPcEquipoJugador`, `PcEquipoJugador`, `MuestraPcRival`, `CalculadoraPcRival`.
- **Aplicación por contexto:** `CalcularComparacionPcRuta`, `CalcularComparacionPcEntrenador`, `CalcularComparacionPcGimnasio`.
- **Generador de ruta:** método aditivo `GeneradorEquipoRuta::especiesPonderadas(int $habitatId, int $nivel): array` y DTO `EspeciePonderadaRuta` (`DatosStats`, `float $peso`). No se usa `GET /ruta/rivales`.
- **Presentación:** partial `resources/views/partials/comparacion-pc.blade.php` y mixin `comparacion-pc-script.blade.php`; estados Alpine en las dos vistas existentes.

### 4.1. Endpoints

```http
GET /api/habitats/{habitat}/ruta/pc?team_id={int}&nivel={1|2|3}
GET /api/habitats/{habitat}/entrenadores/{nivel}/{trainer}/pc?team_id={int}
GET /api/gimnasios/{gym}/pc?team_id={int}
```

- Bajo el grupo `auth`; GET de solo lectura.
- `team_id` validado con `Rule::exists('teams','id')->where('user_id', Auth::id())` y revalidado en `ObtenerPcEquipoJugador`.
- `fecha` de entrenador y etapa de gimnasio se resuelven en servidor; nunca se aceptan del cliente.
- Respuesta con `estado`, `equipo`, `rival` y `comparacion`; nunca composición ni datos individuales del rival.
- Estados de dominio con HTTP 200; 422/404 solo para peticiones o recursos inválidos.

### 4.2. Contrato de respuesta

```json
{
  "estado": "ok",
  "equipo": { "miembros": 3, "completo": true, "pc": 1240 },
  "rival": { "pc": 1050, "aproximado": true, "metodo": "promedio_pool" },
  "comparacion": { "ratio": 0.85, "veredicto": "parejo" },
  "contexto": { "tipo": "ruta", "habitat_id": 12, "nivel": 2 }
}
```

## 5. Archivos y responsables

### 5.1. Backend — dominio y soporte

| # | Acción | Archivo | Responsable |
|---|---|---|---|
| 1 | Crear | `src/Shared/Domain/Pc/EstadoComparacionPc.php` | backend |
| 2 | Crear | `src/Shared/Domain/Pc/MetodoPcRival.php` | backend |
| 3 | Crear | `src/Shared/Domain/Pc/VeredictoPc.php` | backend |
| 4 | Crear | `src/Shared/Domain/Pc/UmbralesVeredictoPc.php` | backend |
| 5 | Crear | `src/Shared/Domain/Pc/ComparadorPc.php` | backend |
| 6 | Crear | `src/Shared/Domain/Pc/ComparacionPc.php` | backend |
| 7 | Modificar | `src/Pokemon/Domain/Stats/CalculadorCP.php` (añadir `desdeStats`) | backend |
| 8 | Crear | `app/Support/Pc/EstadisticasBasePokemon.php` | backend |
| 9 | Crear | `app/Support/Pc/CalculadoraPcReclutado.php` | backend |
| 10 | Crear | `app/Support/Pc/ObtenerPcEquipoJugador.php` | backend |
| 11 | Crear | `app/Support/Pc/PcEquipoJugador.php` | backend |
| 12 | Crear | `app/Support/Pc/MuestraPcRival.php` | backend |
| 13 | Crear | `app/Support/Pc/CalculadoraPcRival.php` | backend |
| 14 | Modificar | `app/Support/ReclutadoSerializer.php` (delegar `cpDe` y `statsBaseValue`, sin cambiar contrato) | backend |
| 15 | Crear | `src/CombateRuta/Domain/DataTransferObjects/EspeciePonderadaRuta.php` | backend |
| 16 | Modificar | `src/CombateRuta/App/GeneradorEquipoRuta.php` (añadir `especiesPonderadas`) | backend |

### 5.2. Backend — casos de uso, controladores y rutas

| # | Acción | Archivo | Responsable |
|---|---|---|---|
| 17 | Crear | `src/CombateRuta/App/CalcularComparacionPcRuta.php` | backend |
| 18 | Crear | `src/CombateEntrenadores/App/CalcularComparacionPcEntrenador.php` | backend |
| 19 | Crear | `src/Gimnasios/App/CalcularComparacionPcGimnasio.php` | backend |
| 20 | Modificar | `src/CombateRuta/Infra/Controllers/CombateRutaController.php` (método `pc`) | backend |
| 21 | Modificar | `src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php` (método `pc`) | backend |
| 22 | Modificar | `src/Gimnasios/Infra/Controllers/GimnasioController.php` (método `pc`) | backend |
| 23 | Modificar | `routes/ruta.php` | backend |
| 24 | Modificar | `routes/entrenadores.php` | backend |
| 25 | Modificar | `routes/gimnasios.php` | backend |

### 5.3. Frontend

| # | Acción | Archivo | Responsable |
|---|---|---|---|
| 26 | Crear | `resources/views/partials/comparacion-pc.blade.php` | frontend |
| 27 | Crear | `resources/views/partials/comparacion-pc-script.blade.php` | frontend |
| 28 | Modificar | `resources/views/habitats/partials/modal-formacion.blade.php` (incluir partial) | frontend |
| 29 | Modificar | `resources/views/habitats/show.blade.php` (mixin, URLs, disparadores) | frontend |
| 30 | Modificar | `resources/views/gimnasios/show.blade.php` (partial, mixin, URLs, disparadores) | frontend |

### 5.4. No aplica

- **Base de datos:** sin migraciones, constraints, índices ni transacciones nuevas. Solo lecturas.
- **Tests:** fuera de alcance; no se crean, modifican ni ejecutan.
- **Documentación:** no se solicita; el ADR queda como borrador en `architecture.md`.

## 6. Orden de ejecución y dependencias

1. **Fase A — Backend base (pasos 1–16):** dominio, CP compartido y soporte. No toca vistas.
2. **Fase B — Backend por contexto (pasos 17–25):** casos de uso, controladores y rutas. No toca vistas.
3. **Fase C — Frontend (pasos 26–30):** partial, mixin y disparadores. Debe ejecutarse al final.
4. **Revisión independiente** (`reviewer`) y **auditoría de seguridad** (`security`) por los nuevos endpoints y la validación de propiedad.
5. **Informe final** y cierre.

Dependencias y concurrencia:

- `TASK-034` (EXP post-combate) y `TASK-036` (autoobjetivo de Bucle Arena) están en curso. No se tocan sus archivos ni `app/Livewire/Presenters/*`.
- Antes de la Fase C, comprobar `git status` y `git diff` de `resources/views/habitats/show.blade.php` y `resources/views/gimnasios/show.blade.php`. Si hay cambios concurrentes sin integrar, coordinar o trabajar en worktree separado.
- Los cambios locales preexistentes (`bugs.txt`, carpetas de tareas) no se tocan.
- No se hace `git push` ni ninguna operación destructiva.

## 7. Criterios de aceptación verificables

1. Con un equipo de 3 miembros, al abrir Configurar Formación en ruta, entrenador o gimnasio se muestran el PC del equipo, el PC rival y el veredicto.
2. En ruta, el PC rival se muestra con `≈` y la nota de que el rival se decide al combatir; no se revela composición.
3. En entrenador y gimnasio se muestra solo el agregado; no se revelan especies, tipos, niveles individuales, IV/EV ni movimientos del rival.
4. El PC del equipo propio coincide exactamente con el CP que muestra `/equipos` para los mismos reclutados y nivel de usuario.
5. Cambiar vanguardia/retaguardia no cambia el PC mostrado ni provoca una nueva petición.
6. Equipo incompleto, sin rival, cargando, error de red y combate no disponible muestran un estado explícito y **no bloquean** el botón “Combatir”.
7. `team_id` de otro usuario devuelve 422; sin autenticación, 401.
8. No hay fórmulas de CP en JS/Blade.
9. No se modifican fórmulas de combate, balance, esquema, dependencias ni tests.
10. El análisis estático (`composer analyse`, `composer arch`, Pint) no introduce errores nuevos.

## 8. Verificación requerida

### 8.1. Análisis estático

```bash
php -l <archivos PHP nuevos/modificados>
composer analyse
composer arch
vendor/bin/pint --dirty --format agent
php artisan route:list --path=pc
```

- `composer analyse`: sin errores nuevos.
- `composer arch`: sin violaciones de capas nuevas.
- Pint: aplicar formato y dejar el diff limpio.
- `route:list`: confirmar las tres rutas GET bajo `auth`.

### 8.2. Comprobación manual

- Abrir el modal en los tres contextos con un equipo de 3 y comprobar totales, `≈`, veredicto y copy.
- Comparar el PC propio con `/equipos` para el mismo usuario/nivel.
- Cambiar formación y confirmar que el PC no cambia.
- Probar equipo de 2, sin rival, gimnasio completado, entrenador derrotado y error de red simulado.
- Inspeccionar el JSON de cada endpoint y verificar que no incluye datos individuales del rival.
- Revisar accesibilidad: `aria-live`, `role="status"`, foco, texto del veredicto y `motion-reduce`.
- `git diff --stat` limitado a los archivos del plan.

### 8.3. Sin tests

No se ejecuta la suite ni se crean/modifican archivos en `tests/`.

## 9. Riesgos y mitigaciones

| Severidad | Riesgo | Mitigación |
|---|---|---|
| Alto | Ruta no determinista: el número puede leerse como rival real | `aproximado: true`, `≈`, nota explícita; no usar `GET /ruta/rivales` |
| Alto | El término EV domina el CP y produce comparaciones contraintuitivas | No cambiar la fórmula; veredicto + copy; calibrar umbrales en otra iteración si hace falta |
| Alto | Gimnasio oculta deliberadamente equipo/nivel | Solo agregado; sin `nivel_rival` ni composición; nota “No se muestra su equipo” |
| Medio | Divergencia entre rival previsualizado y combate | Reutilizar los generadores reales; fecha/etapa en servidor |
| Medio | IDOR en `team_id` | Validación en controlador y en `ObtenerPcEquipoJugador` |
| Medio | Latencia al generar movimientos en la preview | Una petición por apertura; cache futura fuera de alcance |
| Medio | Conflicto con TASK-034/TASK-036 en vistas | Backend primero; frontend al final; revisar `git status`/worktree |
| Medio | Divergencia con `/equipos` al refactorizar `ReclutadoSerializer` | Delegación behavior-preserving; comparación manual de muestras |
| Bajo | Formato/localización de números | `Intl.NumberFormat('es-ES')` |
| Bajo | Accesibilidad | `aria-live`, `role="status"`, texto + icono, `motion-reduce` |

## 10. Rollback

- Sin migraciones ni cambios de datos: el rollback es exclusivamente de código.
- Revertir los archivos del plan en orden inverso (frontend → controladores/rutas → aplicación → dominio), o usar un worktree/rama de trabajo.
- No se ejecutan `git reset --hard`, `git clean` ni `git checkout --` sin aprobación explícita.
- Si el frontend causa problemas, puede desactivarse temporalmente el partial/mixin sin afectar a los endpoints ni al combate.

## 11. Aprobación

Este plan **no autoriza implementación** hasta recibir una aprobación explícita que mencione la versión:

- `Aprobar plan v1`
- `Implementa el plan v1`
- `Apruebo la versión 1 del plan`

La aprobación se registrará en `approval.md` con el texto literal, la fecha y el alcance autorizado.
