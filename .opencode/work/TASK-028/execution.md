# TASK-028 — Ejecución

## Estado

`COMPLETED` — verificación diferencial 3F ejecutada. **11/11 escenarios con diff vacío** entre `fad7c65` y `8cc7fc0`. Sin cambios de código de aplicación.

## Preflight

- Proyecto: `/home/david/pokemon`
- Rama: `task-011`
- HEAD: `8cc7fc0`
- Base: `fad7c65`
- Worktree base: `/tmp/opencode/task-028/base`
- Arnés: `/tmp/opencode/task-028/harness.php`
- Tests del proyecto: no ejecutados.

## Resultado

| Escenario | Base | Actual | Diff |
|---|---|---|---|
| 1 — nueva batalla | OK | OK | vacío |
| 2 — mount con battle_id | OK | OK | vacío |
| 3 — previewTarget + selectMove | OK | OK | vacío |
| 4 — commitAction jugador | OK | OK | vacío |
| 5 — turno IA | OK | OK | vacío |
| 6 — ronda/preparación | OK | OK | vacío |
| 7 — postura + confirmar | OK | OK | vacío |
| 8 — saltar/auto-salto | OK | OK | vacío |
| 9 — objetos | OK | OK | vacío |
| 10 — swap/retrasar/pasar | OK | OK | vacío |
| 11 — fin de batalla | OK | OK | vacío |

- Repetición del árbol actual: 11/11 idénticos (determinismo).
- Se compararon: propiedades públicas, `log`, `eventos`, `phase`, `round`, `processing`, `actingRefId`, `turnQueue`, `session_version`, `session_hash`, `session_meta`, `rewards` y `habitatId`.

## Evidencia de que los escenarios no son no-ops

- 3: `previewTarget` cambia hash; `selectMove` fija pending/anim.
- 4: `commitAction`: log 6→8, eventos 0→3, fase.
- 5: IA pendiente → commit → log 3→5, eventos 0→3.
- 6: round 1→2, log 3→18.
- 7: `preparacionPendiente` true→false, log 5→7.
- 8: salto manual y auto-salto; `anim=player_1`.
- 9: `baya:oran` → fase item; cancelar; `mejora:critico` → pending/anim.
- 10: swap/retrasar/pasar; log 6→7→8.

## Limitaciones

- `GeneradorIvs` usa `random_int`; se sustituyó por un stub de IVs fijos, aplicado por igual a ambos árboles.
- Sin `pdo_sqlite`; se usó PostgreSQL `laravel_test` solo para `Schema::hasTable('users')`. Inventario en memoria.
- Escenario 2: `round` público 0 con sesión en ronda 1; idéntico en ambos árboles.
- Escenario 11: sin meta, `rewards=[]` y `habitatId=0`; la ruta de recompensas con BD no está cubierta.
- No se cubren JS/DOM/animaciones; las segundas llamadas a `commitAction` se simulan.

## Estado del repo

- `git status --short`: solo `.opencode/work/TASK-028/`.
- Sin cambios de código.
- Tests no ejecutados.
