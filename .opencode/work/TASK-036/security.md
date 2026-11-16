# TASK-036 — Seguridad

- **Estado:** PASS_WITH_FINDINGS — no bloqueante
- **Auditor:** `security` (sesión `ses_e40418f6cffeEj1Y0BmJnNefVY`)
- **Nota:** resultado del auditor, sin alterar sus conclusiones.

---

# Auditoría de seguridad — TASK-036

## 1. Veredicto

**PASS_CON_HALLAZGOS** (`PASS_WITH_FINDINGS`) — no hay hallazgo CRITICAL ni HIGH explotable introducido por TASK-036. No bloquea la entrega. Los hallazgos son deuda preexistente (dependencias) y brechas de defensa en profundidad no alcanzables desde el cliente.

## 2. Superficie analizada

- `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php` — `AUTOOBJETIVO`, `permiteAutoobjetivo()`.
- `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` — `previewTarget()`, `selectMove()`, `actorAdmiteAutoobjetivo()`, `DESAFIO`.
- `app/Livewire/Combate/Soporte/GestorAccionBatalla.php` — `resolver()`, `$autoobjetivo`, `$efectoAliado`, rechazo de autoataque.
- `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php` — log de `aplicarMovimientoAliado()`.
- `app/Livewire/Presenters/MovesPreviewPresenter.php` — rama de autoobjetivo, `apoyoEtiqueta`, no-usable.
- `app/Livewire/Presenters/BatallaVistaPresenter.php` y `PresentadorVistaCombate.php` — flag de carta seleccionable y fases.
- `resources/views/livewire/partials/moves-panel.blade.php` — badge `apoyoEtiqueta`.
- Contexto de confianza: `Combate.php` (propiedades públicas Livewire), `ResolutorActorBatalla`, `BattleSessionService`, `EjecutorEfectoAliado`, `DTOMovimientoBatalla`, `EjecutorDesafio`, rutas de `/combate`.
- Verificado el perímetro: `git diff --name-only -- tests composer.json composer.lock database/migrations` → vacío. `SESSION_VERSION` sigue en 21.

## 3. Hallazgos

### [MEDIUM] Dependencias con advisories aplicables (preexistente, no introducido por TASK-036)

- **Ubicación:** `composer.lock` (sin cambios en esta tarea).
- **Vector:** `composer audit` reporta advisories que afectan a las versiones instaladas.
- **Evidencia:**
  - `livewire/livewire v4.4.2` (<4.4.8): bypass de autorización en `#[Authorize]` vía reordenación de claves y orden de `#[Json]`. **No explotable hoy**: `grep -rn "Authorize" app src` no encuentra usos del atributo.
  - `laravel/framework v12.67.0` (<12.68.0): bypass de `TokenGuard` con credenciales no string (la app solo usa guard `session`, sin `token`); “relationship inclusion” destructivo (requiere endpoints con inclusión de relaciones); XSS en página de debug (solo con `APP_DEBUG=true`).
  - `symfony/http-foundation v7.4.17`: ReDoS en `HeaderUtils::split()` vía cabecera `Cache-Control`; además bypass de `security.access_control` con HEAD (la app usa middleware de rutas, no `access_control`), host en URLs y proxies.
  - `symfony/routing v7.4.17`, `symfony/mime v7.4.17`, `symfony/config v7.4.17`, `symfony/filesystem v7.4.21`, `symfony/http-kernel v7.4.17`, `symfony/yaml v8.1.9`, `league/commonmark 2.10.0`.
- **Impacto:** condicional y de infraestructura; el más relevante es un posible DoS por ReDoS en endpoints públicos y una escalada futura si se adopta `#[Authorize]`.
- **Remediación:** tarea aparte de actualización de dependencias (`composer update` acotado) y revisión de `APP_DEBUG` en producción. No es alcance de TASK-036.

### [LOW] El rechazo de autoataque en `resolver()` no revalida el actor contra el jugador autorizado

- **Ubicación:** `app/Livewire/Combate/Soporte/GestorAccionBatalla.php:136-271` (rama de ataque) frente a `:293-300` (`resolverObjeto()` sí llama a `jugadorAutorizado`).
- **Vector:** `pendingAction` con `actorId` distinto del actor actual de la ronda. Preexistente y documentado en `architecture.md:359`.
- **Evidencia:** la guarda nueva (`:212-227`) sí impide el autoataque, pero no comprueba `jugadorAutorizado` antes de ejecutar la acción.
- **Impacto:** si se pudiera escribir el `pendingAction` de sesión (no es una propiedad pública Livewire; verificado en `Combate.php:13-56`), se ejecutaría una acción no autorizada. No es alcanzable desde el cliente con el flujo actual: el pendiente se crea en servidor (`selectMove`, `prepararAnimacionIa`) y la sesión es server-side (`BattleSessionService.php:71-78`).
- **Remediación:** añadir la comprobación de `jugadorAutorizado` en la rama de ataque como endurecimiento; no requerido para cerrar TASK-036.

### [LOW] Un `pendingAction` forjado con `objetivo=self` en el DTO podría eludir la guarda

- **Ubicación:** `GestorAccionBatalla.php:189-195` y `DTOMovimientoBatalla::toDomain()` (`src/Battle/Presentation/DTOMovimientoBatalla.php:137-155`).
- **Vector:** `$movimiento->esAutodirigido()` se calcula desde el DTO (`objetivo`, `selfStatChanges`). Un DTO con un ataque dañino marcado `objetivo=self` haría `$autoobjetivo=false`, `$efectoAliado=null`, y la guarda `! $movimiento->esAutodirigido()` sería falsa, llegando a `aplicarMovimiento()` con `attacker === defender`.
- **Impacto:** autoataque con daño. **No alcanzable** desde el cliente: `pendingAction` vive en el agregado serializado en sesión y no es propiedad pública Livewire; requiere escritura en sesión (compromiso de servidor).
- **Remediación (endurecimiento):** validar el DTO del pendiente contra el moveset real del actor (slug, objetivo, potencia) antes de construir la acción, o guardar solo `slug`+índice y reconstruir desde el dominio.

### [INFO] El flag de presentación es manipulable pero solo afecta a la UI

- **Ubicación:** `PresentadorVistaCombate.php:22,64-78` y `BatallaVistaPresenter.php:59-64,190-193`.
- **Vector:** `currentMoves` y `actingRefId` son propiedades públicas Livewire y el cliente puede alterarlas.
- **Evidencia:** el flag solo pinta `selectable`/`wire:click`; `previewTarget()` (`GestorSeleccionBatalla.php:59-67`), `selectMove()` (`:127-150`) y `resolver()` (`GestorAccionBatalla.php:212-227`) revalidan contra el estado del servidor.
- **Impacto:** ninguno sobre el estado de batalla; a lo sumo, una carta aparece clicable y la acción se rechaza.
- **Remediación:** ninguna.

### [INFO] `selectMove()` no comprueba `phase`, `processing` ni `animAttackerId`

- **Ubicación:** `GestorSeleccionBatalla.php:78-209`.
- **Vector:** un cliente puede invocar `selectMove()` fuera de fase o durante la animación y sobrescribir el `pendingAction` antes del `commit`.
- **Impacto:** sin ganancia de privilegios; todos los objetivos y movimientos se revalidan en servidor y el resolver vuelve a validar. Preexistente.
- **Remediación:** opcional, guardas de fase/estado al inicio de `selectMove()`.

## 4. Controles correctos

- Predicado único y explícito: solo `sand-tomb`, `fire-spin`, `whirlpool` (`CatalogoEfectosAliado.php:29,89-92`). El rechazo es *fail-safe*: si `AUTOOBJETIVO` y `para()` divergieran, `previewAliadoNoUsable` y la guarda de `resolver()` rechazan la acción.
- `selectMove()` rechaza autoobjetivo para todo movimiento no autodirigido fuera del catálogo, antes de crear el `pendingAction` (`GestorSeleccionBatalla.php:141-150`). Verificado que un ataque normal con `defenderId===actorId` se corta.
- `resolver()` aplica defensa en profundidad antes de `aplicarMovimiento()`: `objetivo === actor && ! esAutodirigido && efectoAliado === null` → limpia pendiente, consume acción y pasa turno (`GestorAccionBatalla.php:212-227`). La rama de apoyo (`:229-239`) no pasa por daño, precisión ni secundarios (`ResolutorAccionBatalla.php:155-204`), por lo que no hay daño ni `partiallytrapped` sobre uno mismo.
- El efecto de autoobjetivo usa el catálogo (`ESCUDO_FISICO`, `ESCUDO_ESPECIAL`, `AQUA_RING`) y `EjecutorEfectoAliado` comprueba `estaVivo()` (`EjecutorEfectoAliado.php:35-40`), con tope de escudo (`:69`) y refresco no acumulativo de Acua Aro (`:119-124`).
- `DESAFIO` queda excluido solo cuando `$esAutoobjetivo` es verdadero (`GestorSeleccionBatalla.php:181-187`); contra rival el mismo movimiento sigue redirigiéndose. `EjecutorDesafio` fija `objetivoForzadoId` al atacante del efecto, no al propio actor (`src/Battle/Domain/Effects/Movimiento/EjecutorDesafio.php:27-30`), por lo que no puede forzar un autoataque.
- Objetivos inválidos: `objetivoPorSeleccion()` usa `?? null` (`CombatientesCollection.php:35-40`), muerto/no existente se corta en `previewTarget` (`:55-57`), `selectMove` (`:143-195`) y `resolver` (`:161-169`); el soporte sobre muerto se neutraliza en el ejecutor.
- Presentación: la previsualización de autoobjetivo nunca calcula daño. `previewAliadoBase` fija `daño=0`, `potencia=0`, `modoAliado=true` (`MovesPreviewPresenter.php:285-336`) y los no usables se marcan `disponible=false` sin fila de daño.
- Salida escapada: `apoyoEtiqueta` se pinta con `{{ }}` (`moves-panel.blade.php:60`), igual que `efectoAliado['texto']` y `motivoTexto`. No hay `{!! !!}` nuevo. El único `{!! !!}` del registro (`battle-log.blade.php:5`) recibe HTML construido tras `e($entry)` (`BatallaVistaPresenter.php:384-397`), preexistente y sin datos nuevos controlados por el jugador.
- Autenticación/autorización: `/combate` sigue bajo middleware `auth` (`routes/web.php:22-29`); no hay endpoints, rutas ni métodos públicos nuevos. `battleId` es una clave de la sesión del propio usuario (`BattleSessionService.php:71-78`), sin IDOR entre usuarios.
- Sesión/contrato: sin cambios en serialización, `DTOAccionBatalla` ni `SESSION_VERSION = 21` (`BattleSessionService.php:50`); las sesiones v9+ siguen cargando y las acciones pendientes antiguas no se reinterpretan como autoobjetivo.
- Sin secretos, SQL, manejo de archivos, deserialización de entrada cliente, CSRF nuevo ni rate limiting nuevo en el cambio.

## 5. Dependencias

- `composer audit --no-interaction` → advisories en `laravel/framework v12.67.0`, `livewire/livewire v4.4.2`, `league/commonmark 2.10.0`, `symfony/{http-foundation,routing,mime,config,filesystem,http-kernel,yaml}`. Ninguna introducida ni modificada por TASK-036 (`composer.json`/`composer.lock` sin diff). 0 paquetes abandonados.
- `php -l` sobre los 7 PHP modificados → sin errores de sintaxis.
- `phpstan -c phpstan.neon` (level 6) sobre los archivos del cambio → `[OK] No errors`.
- `phpstan -c phpstan-hardener.neon` sobre los archivos del cambio → solo 4 errores ya registrados en `phpstan-hardener-baseline.php:215-236` (tipos `list` en `BatallaVistaPresenter::toView`), preexistentes; la ejecución parcial no aplicó el baseline por ruta.
- `deptrac analyse` → exit 1 con 100 violaciones preexistentes (`AgregadoBatalla`, `ServicioEjecucionBatalla`, DTOs de `Habitats`/`Granjas`), ninguna en los archivos de TASK-036.

## 6. Riesgo residual

- Dependencias vulnerables (ReDoS y bypasses condicionales) hasta que se actualicen; no explotables por el flujo de TASK-036, pero sí superficie general de la app.
- La rama de ataque de `resolver()` no reautoriza al actor del pendiente (deuda preexistente). Mitigado por el hecho de que `pendingAction` es server-side y no una propiedad Livewire; no se puede forjar desde el cliente.
- La lista `AUTOOBJETIVO` debe mantenerse si el catálogo gana movimientos con `target: normal`; hoy el fallo es seguro (rechazo), no silencioso.
- Un `pendingAction` corrupto/antiguo con `defenderId===actorId` y movimiento normal pierde la acción del turno (comportamiento aprobado), sin daño ni estado.

## 7. Recomendación

Aceptar TASK-036: los tres vectores críticos del encargo (autoataque con movimiento normal, aplicación de efectos no catalogados sobre uno mismo y redirección por `DESAFIO`) están bloqueados en servidor en `selectMove()` y en `resolver()`, y la presentación no puede provocar daño contra uno mismo. Abrir una tarea separada para actualizar dependencias (`livewire/livewire ≥4.4.8`, `laravel/framework ≥12.69.0`, `symfony/*` y `league/commonmark`), y, como endurecimiento opcional, añadir la reautorización del actor y la validación del DTO pendiente contra el moveset real.
