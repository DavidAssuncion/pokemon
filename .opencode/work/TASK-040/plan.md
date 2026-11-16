# TASK-040 — Plan v1

- **ID:** TASK-040
- **Versión:** v1
- **Fecha:** 2026-11-09
- **Estado:** AWAITING_APPROVAL — no autoriza implementación
- **Base:** rama `task-011`
- **Análisis:** `analysis.md` (COMPLETED)
- **Diseño:** `architecture.md` (COMPLETED)

## 1. Objetivo

Corregir el bloqueo perpetuo de **Aguante**, **Protección** y **Detección**:

- La IA no debe declarar estos movimientos si se usaron en la ronda anterior o en la misma ronda.
- Un intento bloqueado no debe actualizar `ultimaRondaProteccion`; solo los usos válidos.
- El jugador conserva el aviso y el coste de acción/PP si reintenta en una ronda restringida, pero puede volver a usarlo en la ronda siguiente a la última válida.

## 2. Alcance

### 2.1. Incluido

- Predicado de dominio `puedeUsarProteccion(int $ronda)` en `RegistroDanio` y delegación en `Combatiente`.
- Clasificación `MovimientoBatalla::esProteccion()` para `PROTECCION` y `AGUANTE`.
- Corrección de `EjecutorAguante` y `EjecutorProteccion`: usar el predicado y actualizar el marcador solo en la rama válida.
- Filtro en `GestorPrioridadesBatalla::declararIa()` para no declarar protección restringida y elegir la siguiente prioridad o la acción normal.
- Filtro en `SelectorAccionIA::generarAccionesCandidatas()` para no generar candidatos de protección restringidos.
- Sin cambios en la preparación del jugador: sigue viendo el movimiento; el ejecutor es la barrera.

### 2.2. Fuera de alcance

- Filtrar la preparación del jugador (`GestorPreparacionBatalla`).
- Fallback por `slug` para sesiones antiguas sin `perfil`.
- Rediseñar la política de prioridades de la IA (seguirá usando una prioridad si existe).
- Cambiar el fallback “Espera” de la IA cuando solo tiene protección restringida.
- `SelectorAccionIA::elegirMejorMovimiento()` legacy.
- Logs cosméticos (nombre en minúscula).
- Cambios en `SESSION_VERSION`, serialización, migraciones, dependencias, Blade o tests.

## 3. Arquitectura y contratos

- **Predicado:** `ultimaRondaProteccion === 0` o `<= ronda - 2` → permitido; en otro caso, bloqueado. `>=` cubre también el doble uso en la misma ronda con acción extra.
- **Clasificación:** `perfil->claveEfecto` en `{PROTECCION, AGUANTE}`.
- **Ejecutores:** la rama bloqueada solo registra el log y retorna; la válida actualiza el marcador, aplica la condición y registra el log.
- **IA:** `declararIa()` filtra con `continue`; `SelectorAccionIA` filtra antes de la rama de autodirigidos. Sin lógica nueva de fallback.
- **Compatibilidad:** `ultimaRondaProteccion` ya se serializa; `SESSION_VERSION` sigue en 21.

## 4. Archivos y responsables

### 4.1. Backend (`backend`)

| Orden | Archivo | Cambio |
|---|---|---|
| B1 | `src/Battle/Domain/Componentes/Combatiente/RegistroDanio.php` | `puedeUsarProteccion(int $ronda): bool`. |
| B2 | `src/Battle/Domain/Combatiente.php` | Delegación `puedeUsarProteccion()`. |
| B3 | `src/Battle/Domain/MovimientoBatalla.php` | `esProteccion(): bool` + import de `ClaveEfectoMovimiento`. |
| B4 | `src/Battle/Domain/Effects/Movimiento/EjecutorAguante.php` | Predicado; sin actualización en la rama bloqueada. |
| B5 | `src/Battle/Domain/Effects/Movimiento/EjecutorProteccion.php` | Ídem. |
| B6 | `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` | Filtro en `declararIa()`. |
| B7 | `src/Battle/Domain/AI/SelectorAccionIA.php` | Filtro en `generarAccionesCandidatas()`. |

### 4.2. Frontend

`no aplica`.

### 4.3. Sin cambios

- `SESSION_VERSION`, `BattleSessionService`, `SerializadorCombatiente`.
- `MovimientosCombatiente::motivoMovimientoNoDisponible()`.
- `GestorPreparacionBatalla`, Blade, JS, tests, migraciones, dependencias.
- `bugs.txt`, `TASK-032/` a `TASK-039/` y cambios de TASK-035.

## 5. Orden de ejecución y dependencias

1. **B1 → B2** — estado y API del agregado.
2. **B3** — clasificación del movimiento.
3. **B4 + B5** — ejecutores.
4. **B6 + B7** — filtros de IA.
5. Verificación estática y manual.
6. Revisión independiente y seguridad.
7. Informe final.

B4/B5 dependen de B1/B2. B6/B7 dependen de B2/B3. No hay escritura paralela sobre el mismo archivo.

## 6. Criterios de aceptación

- **CA1:** `puedeUsarProteccion()` existe en `RegistroDanio` y `Combatiente` delega.
- **CA2:** `esProteccion()` devuelve `true` para Protección/Detección/Aguante y `false` para el resto.
- **CA3:** en ambos ejecutores `setUltimaRondaProteccion()` aparece solo en la rama válida.
- **CA4:** un intento bloqueado no modifica `ultimaRondaProteccion`.
- **CA5:** `declararIa()` omite protección restringida y elige la siguiente prioridad o deja la acción normal.
- **CA6:** `SelectorAccionIA` no genera candidatos de protección restringidos.
- **CA7:** el jugador reintenta en N+1, recibe el log y pierde acción/PP; en N+2 vuelve a funcionar.
- **CA8:** Protección y Detección se comportan igual que Aguante.
- **CA9:** `movimientoDisponible()` conserva su semántica.
- **CA10:** `SESSION_VERSION` sigue en 21; sin cambios de serialización, migraciones ni tests.
- **CA11:** solo se modifican los 7 archivos autorizados.

## 7. Verificación requerida

### 7.1. Estática

```bash
php -l src/Battle/Domain/Componentes/Combatiente/RegistroDanio.php
php -l src/Battle/Domain/Combatiente.php
php -l src/Battle/Domain/MovimientoBatalla.php
php -l src/Battle/Domain/Effects/Movimiento/EjecutorAguante.php
php -l src/Battle/Domain/Effects/Movimiento/EjecutorProteccion.php
php -l app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php
php -l src/Battle/Domain/AI/SelectorAccionIA.php

vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=512M
vendor/bin/pint --dirty --format agent
php artisan view:cache
```

Greps:

```bash
grep -n "setUltimaRondaProteccion" src/Battle/Domain/Effects/Movimiento/EjecutorAguante.php src/Battle/Domain/Effects/Movimiento/EjecutorProteccion.php
grep -rn "puedeUsarProteccion\|esProteccion" src/Battle app/Livewire
grep -n "SESSION_VERSION" app/Support/BattleSessionService.php
git diff --check
git status --short
```

Resultados esperados: `setUltimaRondaProteccion` solo en la rama válida de cada ejecutor; predicado y clasificación presentes; `SESSION_VERSION` en 21; solo los 7 archivos.

### 7.2. Manual

1. Riolu IA usa Aguante en N; en N+1 no hay log de bloqueo y usa Ataque Rápido/Onda Vacío o acción normal.
2. En N+2, Aguante vuelve a ser válido.
3. Repetir con Protección/Detección.
4. Jugador: Aguante en N, reintento en N+1 con log y coste; N+2 válido.
5. Sesión antigua atascada: se recupera como máximo una ronda después.
6. Acción extra por KO: la IA no elige protección restringida; el jugador recibe el log si la declara.
7. `movimientoDisponible()` sigue mostrando el movimiento en la lista del jugador.

**Tests:** no se crean, no se modifican y no se ejecutan.

## 8. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Corregir solo `declararIa` y dejar al jugador bloqueado. | Se corrigen ambos ejecutores. |
| Corregir solo los ejecutores y mantener el desperdicio de la IA. | Se filtra en `declararIa` y `SelectorAccionIA`. |
| Ruta normal de `SelectorAccionIA` sin filtro. | Filtro en `generarAccionesCandidatas()`. |
| Doble uso en la misma ronda con acción extra. | Predicado con `>=`. |
| Sesiones antiguas atascadas. | Se desbloquean como máximo una ronda después. |
| IA con solo protección restringida. | Cae en “Espera”; aceptado, sin bucle. |
| `elegirMejorMovimiento()` legacy sin filtro. | Sin llamadas de producción; documentado. |
| `perfil` ausente en sesiones muy antiguas. | El ejecutor evita el bucle; riesgo bajo documentado. |

## 9. Rollback

- Revertir el commit de implementación de TASK-040.
- No hay migraciones ni cambios de esquema ni de sesión.
- Los contadores `ultimaRondaProteccion` vuelven al comportamiento anterior; no hay datos que deshacer.

## 10. Decisiones que autoriza la aprobación del plan v1

1. Usar `>= $ronda - 1` (bloquea ronda anterior y doble uso en la misma ronda).
2. No filtrar la preparación del jugador en esta tarea.
3. No añadir fallback por `slug` para sesiones antiguas sin `perfil`.
4. Aceptar el fallback “Espera” para una IA con solo protección restringida.
5. No tocar `SelectorAccionIA::elegirMejorMovimiento()` legacy.
6. Mantener el coste de acción/PP en un intento fallido de protección.
7. Sin cambios en `SESSION_VERSION`, serialización, migraciones, dependencias, Blade ni tests.
