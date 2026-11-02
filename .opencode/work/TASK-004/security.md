# TASK-004 — Seguridad

- **Estado:** **OMITIDA por decisión del desarrollador** (2026-11-02).
- **Auditoría:** parcial, estática, sin modificar código.
- **Veredicto provisional:** `PASS_CON_HALLAZGOS` (sin critical/high remotos con la configuración por defecto).

## Controles verificados

- Endpoints de Senda, recompensas, `dar-evs` y descarte bajo `auth`.
- Scopes `BelongsToUser` en `Reclutado`, `Reclutable`, `Team`, `PlayerInventory`, `PlayerMoveUnlock`, `PlayerTypePath`.
- `team_id` validado contra el usuario autenticado.
- `RegistrarResultadoGimnasio`/`Mazmorra` comprueban `userId === authUserId`.
- Índices únicos por usuario (`item_key`, `type_id`, `move_id`).
- Topes EV 255, IV 0-15, captura máxima 30 %.
- `MapaSendaJugador` normaliza tipos/niveles y no acepta datos de cliente.
- Showdown es MIT y no se usa Node en runtime.

## Hallazgos

| Severidad | Hallazgo | Estado |
|---|---|---|
| Medium | Carrera en `ReclutamientoController::discard`/`discardAll`: lectura de `cantidad` sin lock | **Corregido** con `DB::transaction` + `lockForUpdate` |
| Medium | Carrera en `ReclutadoController::darEvs`: `evs` leído fuera de la transacción | **Corregido** con lock del reclutado y del inventario; recálculo con `ReglasEv` |
| Low | `player_type_paths.exp` no se satura a 72.000 en recompensas | Pendiente |
| Low | Configuración de Senda sin validación (`por_caramelo=0`, costes negativos) | Pendiente |
| Low | `--out` de `showdown:build-movesets` acepta ruta arbitraria | Pendiente (CLI local) |
| Low | `unserialize()` de sesión sin `allowed_classes` | Pendiente (driver database por defecto) |
| Info | `MapaSendaJugador` es snapshot de inicio de combate | Aceptado |

## Decisión del desarrollador

Se **omite la fase de seguridad** para cerrar el desarrollo. Los hallazgos Medium se corrigieron antes de la omisión; los Low quedan documentados como riesgos residuales en `final-report.md`.

No se completaron: revisión de XSS de las vistas nuevas, rate limiting de endpoints de economía, `composer audit`, revisión completa de `BuildShowdownMovesets` y de `THIRD_PARTY_NOTICES.md`.
