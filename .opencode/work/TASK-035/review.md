# TASK-035 — Revisión

- **Fecha:** 2026-11-09
- **Revisor:** `reviewer` (sesión `ses_e4042d5d5ffe5Bo1qVlRv3pbuy`)
- **Veredicto:** **APPROVED**
- **Alcance revisado:** implementación TASK-035 (backend de PC, endpoints, partial/mixin y disparadores en ambos modales). Cambios concurrentes de TASK-036 excluidos.

## Resumen

La implementación respeta el plan v1: CP propio por la ruta canónica (`CalculadorCP::desdeStats` + `BattleStats`), rival de ruta como media ponderada del pool × 3, rival de entrenador/gimnasio como suma exacta del generador real, y presentación sin fórmulas en JS. Capas correctas (dominio puro en `src/Shared/Domain/Pc`, seam en `App\Support/Pc`, caso de uso por contexto, controladores delgados), `team_id` validado por propietario en controlador y reaplicado en `ObtenerPcEquipoJugador`, fecha/etapa en servidor, y respuesta solo con agregados. Sin blockers, altos ni medios.

## Hallazgos

### [BAJO] Mapeo `DatosStats → StatsValue` duplicado

- **Archivos:** `src/CombateRuta/App/CalcularComparacionPcRuta.php:90-100`, `app/Support/Pc/CalculadoraPcRival.php:29-36`.
- **Evidencia:** ambos construyen `new StatsValue(hp: ..., attack: ..., ...)`. `StatsValue::desdeArray()` ya existe. `architecture.md` proponía usarlo.
- **Impacto:** duplicidad evitable; riesgo de divergencia futura.
- **Recomendación:** centralizar en un helper o usar `StatsValue::desdeArray($datos->toArray())`.

### [BAJO] `pc = 0` no se trata como `equipo_incompleto`

- **Archivos:** `app/Support/Pc/ObtenerPcEquipoJugador.php:48-54`, `src/Shared/Domain/Pc/ComparacionPc.php:80-99`.
- **Evidencia:** `completo = miembros === 3`; con `total = 0` se devuelve `estado: ok` con `ratio/veredicto = null`. `architecture.md` pedía tratarlo como incompleto.
- **Impacto:** la UI mostraría el bloque `ok` con veredicto `—`. En la práctica es inalcanzable porque `BattleStats` suma `+10` (HP) y `+5` por stat.
- **Recomendación:** blindar con `if ($total <= 0) ...` si se quiere cubrir el caso degenerado.

### [BAJO] Miembro sin filas de stats se excluye del conteo

- **Archivo:** `app/Support/Pc/ObtenerPcEquipoJugador.php:33-46`.
- **Evidencia:** `CalculadoraPcReclutado::calcular()` devuelve `null` si no hay stats y el miembro no se cuenta; `ConstruirEquipoJugador::desdeEquipo()` sí lo incluiría en combate con stats base 0.
- **Impacto:** un equipo con datos degenerados podría indicar `equipo_incompleto` aunque el combate se permita.
- **Recomendación:** documentar como caso límite o alinear ambos lados.

### [NIT] `no_disponible` del gimnasio no se muestra en el modal

- **Archivo:** `resources/views/gimnasios/show.blade.php:354-362`.
- **Evidencia:** `openCombatPopup()` retorna si `gym.estado !== 'disponible'`; el aviso del partial no se renderiza.
- **Impacto:** ninguno funcional; el modal no abre. El estado queda como defensa.

### [NIT] `selectTeam` del gimnasio recarga al pulsar el mismo equipo

- **Archivo:** `resources/views/gimnasios/show.blade.php:365-371`.
- **Evidencia:** cada click lanza `cargarComparacionPc(...)` sin comprobar si ya era el mismo equipo.
- **Impacto:** petición extra sin cambio de datos. Micro-optimización opcional.

## Cumplimiento de criterios de aceptación (plan §7)

1. **PC propio, rival y veredicto con 3 miembros** — ✅.
2. **Ruta con `≈` y nota; sin composición** — ✅.
3. **Entrenador/gimnasio solo agregado; sin especies/tipos/niveles/IV/EV/movimientos** — ✅.
4. **PC propio coincide con `/equipos`** — ✅ por uso compartido de `CalculadoraPcReclutado`/`CalculadorCP::desdeStats`; no comparado numéricamente en navegador.
5. **Cambiar formación no cambia PC ni repite petición** — ✅ `toggleFormacionSlot()` no dispara fetch.
6. **Estados explícitos y sin bloquear “Combatir”** — ✅.
7. **`team_id` ajeno → 422; sin auth → 401** — ✅ validación en controlador y caso de uso; rutas bajo `auth` verificadas con `route:list -v`; no ejecutado en runtime.
8. **Sin fórmulas de CP en JS/Blade** — ✅ `grep` sin resultados.
9. **Sin cambios de fórmula/balance/esquema/dependencias/tests** — ✅.
10. **Análisis estático sin errores nuevos** — ✅ con matiz: `composer analyse` global falla con 34 errores y `composer arch` con 100 violaciones preexistentes; filtrado por el alcance, 0 errores PHPStan y 0 violaciones deptrac nuevas. Pint OK.

## Verificación ejecutada por el revisor

- `git status`/`git branch` y diff acotado.
- `php -l` sobre 22 archivos nuevos/modificados.
- `php artisan route:list --path=pc -v` (3 rutas GET bajo `web` + `Authenticate`).
- `php artisan view:cache`.
- Pint y PHPStan acotados.
- `grep` de fórmulas CP en `resources/` y de `nivel_rival` en vistas nuevas.

## Riesgos residuales

- Ruta es una esperanza matemática, no el rival real; mitigado con `aproximado`, `≈` y nota.
- El término EV puede dominar el CP y producir veredictos contraintuitivos; umbrales 0.8/1.2 sin calibrar con datos reales.
- Preview de entrenador/gimnasio ejecuta el generador completo (incluye movimientos): latencia asumida, sin caché.
- `no_disponible` de gimnasio no visible por el flujo actual del modal.
- No se ejecutó la suite ni pruebas manuales en navegador.

## Archivos modificados por el revisor

Ninguno.
