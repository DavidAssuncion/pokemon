# TASK-033 — Seguridad

- **Estado:** PASS_WITH_FINDINGS — no bloqueante
- **Auditor:** `security` (sesión `ses_e40b43bb1ffeWgraX2CcBspTvr`)
- **Nota:** resultado del auditor, sin alterar sus conclusiones.

---

# Auditoría de seguridad — TASK-033

## 1. VEREDICTO

**PASS_CON_HALLAZGOS** (equivalente a `PASS_WITH_FINDINGS`).

No se han identificado vulnerabilidades **CRITICAL** ni **HIGH introducidas por TASK-033**. Los hallazgos son de severidad baja/informativa y corresponden a deuda preexistente o a dependencias. **No bloquea la entrega.**

---

## 2. SUPERFICIE REVISADA

- `app/Livewire/Combate.php` (props nuevas `$prioridadesObjetivo`, `$coberturas` y docblock de `$prioridadesDisponibles`)
- `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php` (`abrir`, `cambiarPostura`, `aplicar`, validación de objetivo/cobertura, `limpiar`)
- `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` (`resolver`, `objetivoPara`, `objetivoForzadoDesafio`, `textoMapa`)
- `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` (eliminación de bloqueos de retaguardia aliada)
- `src/Battle/Domain/AgregadoBatalla.php` (`objetivosRivalesLegalesPara`)
- `app/Livewire/Presenters/BatallaVistaPresenter.php` (`refsAliado`, retirada de `tieneVanguardiaViva`)
- `resources/views/livewire/partials/preparacion-panel.blade.php`
- Contexto de dominio y flujo: `EquipoBatalla`, `Combatiente`, `Posicion`, `CombatientesCollection`, `Collection`, `GestorSeleccionBatalla`, `OrquestadorBatalla`, `ResolutorActorBatalla`, `ResolutorAccionBatalla`, `GestorLifecycleBatalla`, `BattleSessionService`, `SelectorAccionIA`, `EjecutorDesafio`, `routes/web.php`.
- No se leyó `.env`, credenciales ni claves.

---

## 3. HALLAZGOS

### 3.1. Introducidos por TASK-033

**Ninguno de severidad MEDIUM o superior.**

Verificaciones negativas (diseño correcto):
- `$prioridadesObjetivo` se revalida en `aplicar()` (`GestorPreparacionBatalla.php:238-266`) y en `resolver()` (`GestorPrioridadesBatalla.php:169-177`): debe existir en `equipoRivalDe`, estar vivo y pertenecer a `objetivosRivalesLegalesPara()` (regla de fila).
- `$coberturas` se valida en `aplicarCoberturaElegida()` (`GestorPreparacionBatalla.php:272-305`): solo `team1`, vivo y distinto de sí mismo. No permite auto-cobertura, rivales, muertos ni ids inexistentes.
- `DESAFIO` prevalece: `objetivoPara()` comprueba `objetivoForzadoDesafio()` **antes** del objetivo declarado (`GestorPrioridadesBatalla.php:163-167`). El forzado sale de la condición de dominio, no del cliente.
- El actor y el movimiento se toman del dominio (`turnManager()->combatientesVivos()` y `prioridadDeclarada()`), no de mapas públicos. La IA ignora `$prioridadesObjetivo` por el guard `team1->findCombatantById(...)`.
- `textoMapa()` normaliza valores no-string, evitando TypeErrors por manipulación de tipo.
- `refsAliado()` filtra `alive === true` y excluye al actor (`BatallaVistaPresenter.php:128-139`); el targeting rival sigue dependiendo de `canTarget` (sin cambios).
- Sin SQL nuevo, sin archivos, sin deserialización nueva, sin endpoints nuevos, sin cambios de CSRF/auth/sesiones/secretos.
- Salida Blade escapada (`{{ }}`); no hay `{!! !!}` en el parcial nuevo.

### 3.2. Preexistentes (no introducidos por TASK-033)

- **[INFO/LOW] Re-ejecución de la preparación y prioridades desde cliente.** `preparacionPendiente` es pública y `confirmarPreparacion()` solo comprueba ese flag (`GestorPreparacionBatalla.php:87-95`); un cliente podría fijarlo y volver a invocar `confirmarPreparacion()`/`cambiarPostura()` fuera del ciclo de ronda. `resolver()` no comprueba `rondaPreparada()` ni `tieneAccionPendienteEstaRonda()` del actor. Afecta solo a la sesión propia (integridad de partida), no a datos de terceros. **No lo introduce TASK-033** (los mapas ya eran públicos). Recomendación: añadir guard de `rondaPreparada() === round()` y verificar acción pendiente en `resolver()`.
- **[INFO] Propiedades públicas usadas para pintar.** `team1`, `team2`, `turnQueue`, `actingRefId` son públicas (preexistente); un cliente puede alterar su propia vista (valores escapados), pero toda decisión de servidor se recalcula sobre el agregado. `wire:change`/`wire:key` interpolan `refId` con `{{ }}`; los refIds se generan en servidor (`jugador_*`, `gimnasio_rival_*`, `player_*`, `enemy_*`), sin entrada de usuario.
- **[INFO] `battleId` público.** Indexa la sesión del propio usuario; manipulado puede limpiar claves de su sesión (p. ej. `_token`) y provocar 419 en su propia sesión. Preexistente, sin cruce entre usuarios.
- **[INFO] Carga por `wire:model.live`.** Incrementa peticiones (una por cambio de movimiento de prioridad) sin throttle específico; coste acotado a sesión autenticada y almacenamiento de batalla. Sin explotación demostrable.

---

## 4. CONTROLES VERIFICADOS

- Autorización: página `/combate` bajo middleware `auth`; el actor del jugador se resuelve en servidor (`jugadorAutorizado` → `team1`). Las props nuevas no alteran autorización.
- Validación de entrada: objetivo de prioridad y cobertura revalidados contra el agregado (equipo, vida, fila, self).
- Precedencia `DESAFIO` correcta (forzado antes que declarado); no se puede saltar eligiendo otro rival.
- Suplantación de actor/movimiento: no posible; actor y `prioridadDeclarada` proceden del dominio.
- Regla de fila rival intacta (`objetivosRivalesLegalesPara`, `SincronizadorVistaBatalla`, `canTarget`).
- IA intacta (`actorAdmiteSoporte`, `SelectorAccionIA` sin cambios); asimetría jugador/IA documentada.
- Sesiones: `SESSION_VERSION` sigue en 21; `BattleSessionService` y serialización de dominio sin cambios. Snapshots antiguos: claves nuevas con default `[]` y `??` defensivos en Blade. Los mapas no se persisten en la sesión de batalla.
- Logs/mensajes nuevos: sin datos sensibles ni internos.
- Sin secretos en el diff; `bugs.txt` solo contiene notas funcionales del desarrollador.

---

## 5. DEPENDENCIAS

- `composer audit`: **exit 10**, 17 advisories, 0 abandonadas. Versiones instaladas relevantes: `laravel/framework v12.67.0`, `livewire/livewire v4.4.2`, `symfony/* v7.4.17`, `league/commonmark 2.10.0`.
- Advisory de Livewire (`>=4.3.0,<4.4.8`, bypass de `#[Authorize]`/`#[Json]`): **no aplica al código actual**, no se usa `#[Authorize]` ni `#[Json]` en `app/`, `src/` ni `routes/`.
- Advisory de `laravel/framework` (TokenGuard): la app usa autenticación por sesión, no API tokens.
- Advisory XSS de página de debug: condicionado a `APP_DEBUG=true`; no verificado (no se lee `.env`).
- `vendor/bin/phpstan analyse` sobre los archivos modificados: **sin errores**.
- `php -l` sobre los 6 PHP modificados: **sin errores de sintaxis**.
- No se modificaron `composer.json`, `composer.lock`, migraciones ni tests.

---

## 6. RIESGOS RESIDUALES

- La corrección de revalidación protege el objetivo/cobertura, pero no añade guard de ronda a `confirmarPreparacion()`/`resolver()` (deuda preexistente).
- Persisten advisories de dependencias; su explotación depende de features no usadas (Livewire `#[Authorize]`, TokenGuard, debug page). No se actualizaron dependencias (fuera de alcance).
- El objetivo de prioridad no se persiste en dominio: si en el futuro la resolución se difiere a otra petición, habría que añadir revalidación/almacenamiento en `EstadoTurno`.
- Ausencia de tests en el flujo (política del proyecto); la verificación es estática y manual.

---

## 7. RECOMENDACIÓN FINAL

Aprobar TASK-033 desde el punto de vista de seguridad: la manipulación de `$prioridadesObjetivo` y `$coberturas` está contenida por validación y revalidación en servidor, `DESAFIO` tiene precedencia correcta y no hay regresiones de autenticación, autorización, sesión ni de la regla de fila.

Recomendaciones posteriores (tareas separadas, no bloqueantes):
1. Añadir guard de `rondaPreparada()`/acción pendiente en `confirmarPreparacion()` y `resolver()` para cerrar la re-ejecución de prioridades (deuda preexistente).
2. Planificar actualización de `livewire/livewire` a `>=4.4.8` y revisar advisories de Symfony/Laravel.
3. Considerar `#[Locked]` en `battleId` y validar que `battleId` tiene formato esperado para reducir manipulación de claves de sesión propias.
