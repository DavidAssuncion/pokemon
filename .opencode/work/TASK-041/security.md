# TASK-041 — Seguridad

- **Estado:** PASS_WITH_FINDINGS — no bloqueante, con condición operativa
- **Auditor:** `security` (sesión `ses_e3f743221ffecWAlBKuQIzt72i`)
- **Nota:** resultado del auditor, sin alterar sus conclusiones.

---

# Auditoría de seguridad — TASK-041

## 1. Veredicto

**PASS_WITH_FINDINGS** — no hay hallazgos CRITICAL ni HIGH. No bloquea la entrega, pero hay un riesgo residual MEDIUM condicionado a `APP_DEBUG=true` y deuda de dependencias preexistente.

## 2. Superficie revisada

- `app/Livewire/Presenters/DebugCombatientePresenter.php`
- `app/Livewire/Combate/Soporte/SincronizadorVistaBatalla.php`
- `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php`
- `resources/views/livewire/_pokemon-card.blade.php`
- `resources/css/combate.css`
- Contexto: `GestorPreparacionBatalla`, `GestorCicloTurnosBatalla`, `GestorSeleccionBatalla`, `GestorVistaBatalla`, `VistaBatalla`, `AgregadoBatalla`, `SelectorAccionIA`, `CalculadoraDanioIA`, `CombatienteVista`, `BattleSessionService`, `BatallaVistaPresenter`, `battle-field`, `battle-log`, `config/app.php`, `docker-compose.yml`.
- Artefactos: `plan.md`, `architecture.md`, `analysis.md`, `approval.md`, `execution.md`.
- Verificación: `php -l` 3/3, PHPStan sin errores en los 3 PHP, `composer audit` (17 advisories / 10 paquetes), `git diff --check` limpio, `SESSION_VERSION` 21, greps de `config('app.debug')` y `{!!`.

## 3. Hallazgos

### [MEDIUM] Divulgación de la build completa del rival si `APP_DEBUG=true` (condicional)

- **Ubicación:** `SincronizadorVistaBatalla.php:34,45-47`; `_pokemon-card.blade.php:1-71`; `docker-compose.yml:40,80,110`; `config/app.php:44`.
- **Vector:** cualquier jugador de la batalla cuando el servidor arranca con `APP_DEBUG=true`. El `docker-compose.yml` usa `${APP_DEBUG:-true}` por defecto en tres servicios.
- **Evidencia:** con debug activo se añade `debug` a cada rival y viaja en `team2` (payload Livewire) y HTML. Contiene movimientos completos, `attack`/`spAtk`, habilidad base, objeto y motivos de indisponibilidad. El tooltip se oculta solo por CSS; los datos ya están en el payload.
- **Impacto:** ventaja competitiva y detalle interno de la IA. No expone secretos, credenciales ni datos personales.
- **Condición:** `APP_DEBUG=true` accesible a jugadores. Con `APP_DEBUG=false` el array no se construye y el bloque no se renderiza.
- **Remediación:** (1) `APP_DEBUG=false` en entornos compartidos/producción y corregir el default del compose; (2) preferible flag dedicado `battle.debug_tooltip`, deshabilitado por defecto, y/o `app()->environment(['local','testing'])` o autorización; (3) documentar que activar `APP_DEBUG` expone esta información.
- **Nota:** el plan v1 aprobó “debug solo con `APP_DEBUG=true`”; el gate está bien implementado. El hallazgo es la condición operativa.

### [LOW] Dependencias con advisories de seguridad (preexistente)

- **Ubicación:** `composer.lock` (sin cambios).
- **Evidencia:** 17 advisories en 10 paquetes. Relevantes:
  - `laravel/framework v12.67.0`: TokenGuard auth bypass, destrucción por inclusión de relaciones, XSS en debug.
  - `livewire/livewire v4.4.2`: bypass de `#[Authorize]`.
  - `league/commonmark 2.10.0`, `symfony/* 7.4.x`.
- **Impacto:** no hay rutas `api`/TokenGuard ni `#[Authorize]`; los CVEs de auth no son explotables por la superficie actual. El XSS de debug agrava el MEDIUM si `APP_DEBUG=true`.
- **Remediación:** tarea separada de actualización (Laravel ≥12.69, Livewire ≥4.4.8, Symfony parcheado). No silenciar.

### [INFO] Doble evaluación de `SelectorAccionIA` y perturbación del RNG global

- **Ubicación:** `GestorPrioridadesBatalla.php:46`, `GestorPreparacionBatalla.php:229`, `CalculadoraDanioIA.php:52-55`.
- **Evidencia:** `CalculadoraDanioIA::estimar()` usa `mt_srand(42)/mt_srand($seed)`, desplazando la secuencia global; `SelectorAccionIA.php:431` usa `array_rand`. La evaluación se repite en preparación y turno normal cuando el rival no declara prioridad.
- **Impacto:** balance/fairness, no seguridad. Sin DoS medible: coste acotado y una vez por ronda, protegido por `preparacionPendiente`.
- **Remediación opcional:** caché por ronda o RNG aislado, fuera de alcance.

### [INFO] Exposición parcial del rival ya existente en producción

- **Ubicación:** `CombatienteVista.php:68-69,81,108,118-123,146-147`.
- **Evidencia:** incluso con `APP_DEBUG=false`, `team2` ya incluye `speed`, `effectiveSpeed`, `prioridadDeclarada`, claves de habilidades, objeto/cargas/estado y `usosMovimiento`/`cargasMovimiento`.
- **Impacto:** el límite “el rival no expone moveset ni stats ofensivas” es parcial; TASK-041 solo añade el detalle completo. Deuda preexistente.
- **Remediación:** filtrar esos campos para `team2` en una tarea específica si el diseño lo exige.

### [INFO] `team1`/`team2` son propiedades públicas Livewire sin `#[Locked]`

- **Ubicación:** `Combate.php:14-15`; `_pokemon-card.blade.php:1-5`.
- **Evidencia:** el cliente recibe `team2` y lo rehidrata; manipularlo requiere romper el checksum de Livewire. Todos los valores se imprimen con `{{ }}` y `team === 1` impide pintar el tooltip en `team1`.
- **Impacto:** auto-manipulación de visualización propia; no permite leer datos del servidor ni ejecutar código.
- **Remediación:** `#[Locked]` o ignorar claves desconocidas en la rehidratación.

## 4. Controles verificados

- **Gate server-side real:** `$debug = (bool) config('app.debug')` en `SincronizadorVistaBatalla.php:34`; no hay `env('APP_DEBUG')` en runtime. Con debug apagado no se construye el array y el HTML no contiene `.debug-tooltip`.
- **Solo `team2`:** `team1` se mapea sin `debug`; `debug` solo se añade en `team2`. La vista exige `team === 1` (defensa en profundidad).
- **No persistencia:** el payload `debug` vive solo en `VistaBatalla`; `BattleSessionService` serializa solo `AgregadoBatalla`. No se escribe en sesión ni caché.
- **Sesión:** `SESSION_VERSION` 21 sin cambios; sesiones antiguas cargan.
- **XSS:** sin `{!! !!}` en la carta/tooltip; todos los campos con `{{ }}`; `implode` escapado; no se generan atributos/estilos desde `debug`. El log sigue preescapado con `e()`.
- **CSS hover-only/overlay:** `display:none` por defecto; visible solo en `@media (hover: hover) and (pointer: fine)`; `pointer-events: none`; `role="tooltip"` y `aria-hidden="true"`; sin `tabindex`/foco/JS; `wire:click` recibe el clic.
- **IA:** sin endpoints ni métodos Livewire nuevos; `declararIa` solo se invoca desde `aplicar()`, protegido por preparación/processing/animación. Bucle finito, limpieza, pre-chequeo, filtro de prioridad y apoyo, revalidación. `DESAFIO` sigue en el selector. Sin entrada de usuario.
- **Sin cambios en auth/CSRF/SQL/archivos/secretos.**
- **Estático:** `php -l` 3/3, PHPStan sin errores en los 3 PHP, `git diff --check` limpio.

## 5. Riesgos residuales

- `APP_DEBUG=true` en cualquier entorno accesible a jugadores expone la build rival y la superficie de la página de debug (CVE-2026-102279). El default del `docker-compose.yml` (`${APP_DEBUG:-true}`) es inseguro para entornos compartidos.
- Configuración cacheada/trabajadores de larga vida: cambiar `APP_DEBUG` exige `config:cache` y reinicio.
- Perturbación del RNG global y cambio de balance de la IA: aceptados, requieren verificación manual.
- Exposición parcial preexistente de datos del rival en `CombatienteVista`.
- Deuda de dependencias (17 advisories).

## 6. Recomendación final

Aprobar TASK-041 con condiciones operativas:

1. Forzar `APP_DEBUG=false` en entornos con jugadores y corregir el default de `docker-compose.yml`.
2. Considerar un flag dedicado `battle.debug_tooltip` (default `false`) o restringir a entorno local/usuario autorizado.
3. Abrir tarea separada de actualización de dependencias.
4. Mantener la verificación manual de balance/RNG antes de producción.

No hay CRITICAL/HIGH que bloqueen. Si el despliegue usa el `docker-compose.yml` con su default actual, tratar la condición 1 como bloqueante de release hasta confirmar `APP_DEBUG=false`.
