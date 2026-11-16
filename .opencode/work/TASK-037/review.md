# TASK-037 — Revisión

- **Fecha:** 2026-11-09
- **Revisor:** `reviewer` (sesión `ses_e3fb1d19bffePcYS9MhLxQUkJA`)
- **Veredicto:** **APPROVED**
- **Base:** TASK-035 en working tree
- **Alcance:** fijación del rival de ruta, leyenda IV/EV y stats anónimos, integración en modales

## Resumen

El cambio implementa lo aprobado en el plan v1:

- Rival de ruta fijado al abrir el modal mediante snapshot en sesión (`RivalRutaFijado` + `AlmacenRivalRutaFijado`), con `preview_id` de 128 bits, clave por `userId`, validación de hábitat/nivel, TTL de 1 h, `instanceof` y limpieza.
- `IniciarCombateRuta` reutiliza el snapshot, aplica `conNivel` si cambia el nivel del jugador y limpia tras crear la batalla; si el id no es válido, cae al generador actual.
- La leyenda se construye en backend (`ConstruirLeyendaIvsEvs`) con la fuente única `PoliticaIvsEvs`/`BattleStats`, expone los cuatro perfiles, el perfil actual y stats anónimos `Rival 1..3` con base/IV/EV/calculado, sin nombres, especies, tipos, movimientos, posición ni `nivel_stats`.
- El contrato TASK-035 se mantiene aditivo (`preview_id`, `perfil_ivs_evs`); el código muerto de la aproximación se conserva, como exige el plan §2.2.
- Frontend: leyenda siempre visible sin `<details>`, modal con cuerpo desplazable y botones fijos, token anti-respuestas-obsoletas, `preview_id` en URL/confirmación/limpieza, y gimnasio sin equipo con leyenda y “Selecciona un equipo”.
- No se han modificado tests, migraciones, dependencias, fórmulas ni esquema.

## Hallazgos

### LOW — `unserialize` sin allow-list (residual aceptado)

- **Archivo:** `app/Support/CombateRuta/AlmacenRivalRutaFijado.php:62-68`.
- **Evidencia:** `unserialize($serializado)` dentro de `try/catch`, con validación posterior `instanceof DatosPokemonBatallaCollection`. El payload lo genera y escribe solo el servidor; el cliente solo controla la clave `preview_id`.
- **Impacto:** defensa en profundidad ante manipulación del almacén de sesión. No hay vector de inyección controlable por el cliente en el flujo actual.
- **Recomendación:** valorar `allowed_classes` con el grafo completo en una tarea de endurecimiento. Se mantiene como residual documentado.

### LOW — claves de stat no canónicas (corregido)

- **Archivo:** `app/Support/Pc/ConstruirLeyendaIvsEvs.php:104-109`.
- **Evidencia:** la leyenda emitía `attack`/`defense`; `StatsValue::toArray()` usa `atk`/`def`.
- **Estado:** corregido a `hp/atk/def/spAtk/spDef/speed`; el frontend solo usa `clave` como `:key`.

### LOW — docblocks obsoletos (pendiente menor)

- **Archivos:** `src/Shared/Domain/Pc/MetodoPcRival.php:7-10` y `app/Support/Pc/CalculadoraPcRival.php:12-15`.
- **Evidencia:** siguen describiendo la ruta como media ponderada; ahora es `rival_fijado`. `PROMEDIO_POOL` se conserva por decisión del plan §2.2.
- **Impacto:** documentación engañosa; sin efecto funcional.

### LOW — snapshots sin tope (corregido)

- **Archivo:** `app/Support/CombateRuta/AlmacenRivalRutaFijado.php`.
- **Estado:** `fijar()` poda snapshots caducados/corruptos y limita a 5 vigentes por usuario; los más antiguos se eliminan.

### LOW — orden del roster en gimnasio (corregido)

- **Archivo:** `app/Support/Pc/ConstruirLeyendaIvsEvs.php`.
- **Estado:** el orden de `Rival 1..3` se basa en un hash determinista de `nombre|speciesId|id`, no en la posición; las etiquetas ya no revelan vanguardia/retaguardia.

### NIT

- `MAXIMO_POKEMON = 3` podría usar `ReglasEquipo::MIEMBROS`.
- `preview_id` en GET/POST es `sometimes|string|max:64`; el DELETE exige `[a-f0-9]{32}`. No explotable; conviene unificar.
- `PerfilRivalAplicado::nivelStats` no se lee; propiedad muerta.
- `aria-live` envuelve tablas completas; puede ser verboso para lectores de pantalla.

## Cumplimiento de criterios de aceptación (plan §7)

1. **Rival fijado y reutilizado** — ✅; runtime verificó que el HP máximo de los 3 rivales del combate coincide con la leyenda.
2. **Leyenda siempre visible, cuatro perfiles, “Este combate” y `aria-current`** — ✅; sin `<details>`.
3. **Stats anónimos `Rival 1..3`, 6 stats, base/IV/EV/calculado** — ✅.
4. **PC de ruta exacto (`rival_fijado`, `aproximado: false`)** — ✅.
5. **Gimnasio sin equipo con leyenda; PC con “Selecciona un equipo”** — ✅.
6. **Cambio de equipo no cambia rival; cambio de nivel fija uno nuevo; cierre limpia** — ✅.
7. **Subida de nivel conserva composición y recalcula** — ✅; runtime verificó HP 70 → 72 al subir de nivel.
8. **JSON sin nombres/especies/tipos/movimientos/formación/posición/`nivel_stats`** — ✅; grep sin claves prohibidas.
9. **Sin fórmulas en JS/Blade** — ✅.
10. **Sin cambios de fórmulas, balance, esquema, dependencias ni tests** — ✅.
11. **Análisis estático sin errores nuevos** — ✅; PHPStan scoped sin errores, Pint pass, `php -l` OK, Blade compila.
12. **TASK-035 sigue funcionando y TASK-036 no se ve afectado** — ✅; contrato de `ComparacionPc` sin cambios y archivos de TASK-036 no tocados.

## Verificación ejecutada por el revisor

- `git status`, `git branch`, `git diff` acotado.
- `php -l` sobre 21 archivos del alcance.
- PHPStan scoped, Pint y `route:list` (`/pc` y `/preview`).
- `view:cache`/`view:clear`.
- Lectura estática de los generadores, `PoliticaIvsEvs`, `PerfilIvsEvs`, `BattleStats`, `EquipoBatalla`, rutas y vistas.
- Sin tests.

## Riesgos residuales

- Confirmación antes de que resuelva el preview: el combate cae al generador (fallback previsto).
- Snapshot huérfano si falla el DELETE o se abandona la página: TTL 1 h y tope de 5.
- `unserialize` sin allow-list: residual de defensa en profundidad.
- Fingerprinting por stats base e inferencia del nivel de gimnasio: aceptado por producto.
- Sin compuerta de nivel en servidor para rutas/entrenadores: preexistente.
- Código muerto de TASK-035 conservado por decisión del plan.

## Archivos modificados por el revisor

Ninguno.
