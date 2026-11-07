# TASK-031 — Revisión independiente

- **Estado:** COMPLETADA
- **Veredicto:** `APPROVED`
- **Fecha:** 2026-11-07
- **Revisor:** `reviewer` (independiente)
- **Alcance:** F1/F2 (dominio y preview) + F3/F4 (IA B0/B1) + correcciones post-revisión.
- **Tests:** no ejecutados, no modificados.

## 1. Veredicto

`APPROVED` — sin bloqueantes ni hallazgos altos. Los dos hallazgos medios del informe parcial quedaron cerrados:

- **Confusión/Alivio:** el desarrollador decidió mantener el comportamiento actual; el criterio 4 y `architecture.md` se actualizaron. No es un incumplimiento.
- **Crítico repetido:** `EvaluadorSoporteIA::valorCriticoMejorado()` devuelve 0 si la condición ya existe; la IA no refresca Foco Energía cada ronda.

Queda un hallazgo **medio** de calidad de decisión de IA (relevancia de buffs y sesgo al primer aliado), explícitamente no bloqueante y diferido a smoke/calibración.

## 2. Puntos de cierre verificados

1. **`DTOMovimientoBatalla` conserva `slug`:** `desdeDominio`, `toLivewire`, `fromLivewire` y `toDomain` lo preservan; la ruta IA llega a `CatalogoEfectosAliado::para($movimiento->slug)` sin degradarse.
2. **`DESAFIO`/`CUBRIR`:** `DESAFIO` solo fuerza objetivo en acciones no aliadas; `CUBRIR` solo actúa en la ruta de daño, que `aplicarMovimientoAliado` no invoca. Sin redirecciones indebidas.
3. **Regresión TASK-030:** catálogo verificado (46 slugs, 0 discrepancias); los 9 movimientos previos conservan tipo y resolución; `recover` sigue al 50 %; los 5 buffs previos y los escudos no cambian; `EjecutorRefresh` mantiene las mismas tres operaciones.
4. **`selfStatChanges`/`factorTemporal` vs `aplicarTemporal`:** el delta del evaluador coincide con el aplicado; `inversion` no se considera (hallazgo bajo).
5. **Correcciones post-revisión:** `golpeMaximo` solo se calcula para cura/escudos/Aqua Ring; guarda de aliado muerto; crítico solo si no está activo. Sin regresiones nuevas.

## 3. Hallazgos

### Medio

**M1 — Valoración de buffs sin relevancia de rol y sesgo al primer aliado**
- Evidencia: `EvaluadorSoporteIA.php:187-206` valora solo la magnitud del delta; `SelectorAccionIA.php:319-339` añade variantes en orden de aliados; el desempate no distingue soporte y `usort` es estable.
- Impacto: la IA puede subir Ataque a un aliado especial o buffar siempre al primer aliado.
- Estado: **no bloqueante**; dentro del alcance aprobado y diferido a smoke/calibración.
- Mitigación futura: penalizar stats que el aliado no usa o limitar el soporte ofensivo a DEF/DEF.ESP/VEL.

### Bajo

**L1 — `incluirSoporte = true` por defecto (opt-out)**
- `SelectorAccionIA.php:65`. Las APIs legacy pasan `false`, pero un llamador futuro podría obtener objetivos aliados sin saberlo.
- Mitigación futura: default `false` y `incluirSoporte: true` explícito en `elegirAccionPara`.

**L2 — Fracciones de curación/Aqua Ring duplicadas**
- `EjecutorEfectoAliado.php:23-25`, `MovesPreviewPresenter.php:34`, `EvaluadorSoporteIA.php:27-29`. `CalculadoraCuracionAliada` no expone esas constantes.
- Mitigación futura: constantes públicas en la calculadora.

**L3 — Aqua Ring refrescado se valora como efecto completo**
- `EvaluadorSoporteIA.php:208-230`: con `turnos = 1` se valora `porRonda * 5`, no los turnos restantes.
- Mitigación futura: valorar solo si la condición está ausente o descontar turnos activos.

**L4 — Habilidad `inversion` no considerada**
- `EvaluadorSoporteIA.php:187-206` asume delta positivo; `GestorEfectosMovimiento.php:325-327` invierte las etapas. Caso raro.
- Mitigación futura: devolver 0 si el aliado tiene `inversion`.

### NIT

- `architecture.md` tenía una fila antigua “crítico a 5” y el docblock de `EvaluadorSoporteIA` dice “refrescable”; actualizar en la próxima pasada.
- `elegirAccionPara(): ?AccionBatalla` nunca devuelve null; el chequeo en `GestorAccionBatalla` es código muerto.
- La rama de soporte no revalida `movimientoDisponible`; el selector lo garantiza y `resolver()` lo revalida.

## 4. Criterios de aceptación

| # | Estado | Evidencia |
|---|---|---|
| 1 | Cumplido | Catálogo 46 slugs, 0 discrepancias |
| 2 | Cumplido | Buffs al aliado; sin doble aplicación |
| 3 | Cumplido | Cura desde `perfil->curacionFraccion`; sin desborde a escudo |
| 4 | Cumplido (criterio actualizado) | Alivio deja confusión a 0 turnos; se elimina en la siguiente acción |
| 5 | Cumplido | `reemplazarCondicion` a 5 turnos |
| 6 | Cumplido | Preview de `CURAR_ESTADO`/`CRITICO_MEJORADO`; sin fila de daño |
| 7 | Cumplido | Generación solo con aliados vivos y movimientos del catálogo |
| 8 | Cumplido | Ruta de soporte con `defender` real; forzado solo en legacy |
| 9 | Cumplido (matiz) | Sin `estimar()` sobre el aliado; `koValue`/`damageValue` 0 |
| 10 | Cumplido | `hayKOEn` filtra todo el soporte |
| 11 | Cumplido | Compuertas de PS/escudo/buff/estado/crítico/Aqua Ring |
| 12 | Cumplido (criterio actualizado) | Umbrales y crítico solo si no está activo |
| 13 | Cumplido | Lookahead desactivado para soporte |
| 14 | Cumplido | `EjecutorAccion`/APIs legacy con `incluirSoporte:false` |
| 15 | Cumplido | Rama legacy intacta |
| 16 | Cumplido (estático) | Sin cambios funcionales en los 9 previos; smoke pendiente |
| 17 | Cumplido | `Combate` sin diff; 32/18 |
| 18 | Cumplido | `SESSION_VERSION` 21; sin migraciones |
| 19 | Cumplido | Sin cambios en Blade/JS/`moves.json`/`DepuradorMovimientos` |
| 20 | Cumplido | `esAutodirigido()` sin cambios |

La única verificación pendiente es el **smoke manual**, fuera del veredicto de código.

## 5. Verificación estática

- `php -l`: OK.
- Pint: pass.
- PHPStan: 34 errores preexistentes; 0 en archivos tocados.
- Deptrac: 100 violaciones preexistentes; 0 nuevas.
- `git diff --check`: OK.
- `view:cache`: OK.
- `app/Livewire/Combate.php`: 32 propiedades/18 métodos.
- `SESSION_VERSION`: 21.

## 6. Riesgos residuales

- Smoke manual pendiente.
- Calidad de buffs de IA: relevancia de stat y sesgo al primer aliado (M1).
- Apilamiento de buffs hasta 4.0 permitido por diseño.
- Coste de `mejorEstimacionContra` por candidata de cura/escudo/Aqua Ring.
- `incluirSoporte = true` por defecto (L1).
- `inversion` mal valorado (L4).
- Divergencia documentada `CRITICO_MEJORADO` propio/aliado.

## 7. Limitaciones

- Revisión estática; no se ejecutó la suite ni smoke en navegador.
- No se revisó seguridad (corresponde a `security`).
- `view:cache` no fue re-ejecutado por el revisor; sí por el orchestrator.
