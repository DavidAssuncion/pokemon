# TASK-039 — Plan v1

- **ID:** TASK-039
- **Versión:** v1
- **Fecha:** 2026-11-09
- **Estado:** AWAITING_APPROVAL — no autoriza implementación
- **Base:** rama `task-011`
- **Análisis:** `analysis.md` (COMPLETED)
- **Diseño:** `architecture.md` (COMPLETED)

## 1. Objetivo

Eliminar el botón **Esperar** del combate y toda posibilidad de cerrar el turno sin ejecutar una acción. Si el jugador no tiene ningún movimiento disponible ni objeto aplicable, el turno se salta automáticamente con un aviso en el registro, sin soft-lock.

## 2. Alcance

### 2.1. Incluido

- Eliminar el bloque del botón «Esperar» de `turn-bar.blade.php`.
- Eliminar la clave `mostrarEsperar` de `BatallaVistaPresenter::turnBar()`.
- Eliminar por completo la cadena pública `pasarTurno`:
  - `Combate::pasarTurno()`;
  - `OrquestadorBatalla::pasarTurno()`;
  - `GestorSeleccionBatalla::pasarTurno()`.
- Añadir un fallback interno en `GestorCicloTurnosBatalla::avanzar`:
  - se ejecuta solo para el actor del jugador;
  - solo si no hay ningún movimiento disponible ni objeto con `aplicable === true`;
  - registra `"{nombre} no tiene ninguna acción disponible y pierde el turno."`;
  - consume la acción, guarda y pasa al siguiente actor;
  - no se dispara con preparación pendiente.
- Mantener `Retrasar turno`, `Atacar con X`, «Cambiar objetivo», objetos, preparación y `Guardia`/`Cubrir`.

### 2.2. Fuera de alcance

- Eliminar `Retrasar turno` o `Atacar con X`.
- Eliminar `Guardia`/`Cubrir` como acción defensiva válida.
- Añadir un aviso visible fuera del registro (banner/toast).
- Añadir un movimiento base garantizado tipo «Placaje».
- Mantener un puente de compatibilidad con pestañas abiertas.
- Cambios en IA, sesiones, serialización, `SESSION_VERSION`, migraciones, dependencias o tests.

## 3. Arquitectura y contratos

- **Contrato de `Combate`:** pasa de 18 a 17 métodos públicos; `pasarTurno` deja de existir.
- **Fallback:** método privado `sinAccionLegal(Combatiente $actor, array $objetosCombate): bool` en `GestorCicloTurnosBatalla`, basado en `movimientoDisponible()` y en `objetosCombate[].aplicable`.
- **Preparación pendiente:** el fallback no se ejecuta hasta que el jugador confirme u omita la preparación; así conserva posturas y prioridades.
- **Persistencia:** consumir → log → `processing=false` → guardar → `nextActor` → return. Sin doble consumo ni bucles.
- **Compatibilidad:** una pestaña con HTML antiguo que invoque `pasarTurno` recibirá `MethodNotFoundException` sin mutar estado; se resuelve refrescando.

## 4. Archivos y responsables

### 4.1. Backend (`backend`)

| Orden | Archivo | Cambio |
|---|---|---|
| B1 | `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php` | `use Combatiente`, helper `sinAccionLegal()` y fallback en la rama del jugador. |
| B2 | `app/Livewire/Combate.php` | Eliminar `pasarTurno()`. |
| B3 | `app/Livewire/Combate/Soporte/OrquestadorBatalla.php` | Eliminar `pasarTurno()`. |
| B4 | `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` | Eliminar `pasarTurno()` y ajustar docblock. |
| B5 | `app/Livewire/Presenters/BatallaVistaPresenter.php` | Eliminar `mostrarEsperar` de `turnBar()`. |

### 4.2. Frontend (`frontend`)

| Orden | Archivo | Cambio |
|---|---|---|
| F1 | `resources/views/livewire/partials/turn-bar.blade.php` | Eliminar el bloque del botón «Esperar». |

### 4.3. Sin cambios

- `Retrasar turno`, `Atacar con X`, «Cambiar objetivo», objetos y preparación.
- `Guardia`/`Cubrir`, estados que bloquean, prioridades.
- IA, sesiones, serialización, `SESSION_VERSION`, migraciones, dependencias, tests.
- `bugs.txt`, `TASK-032/` a `TASK-038/` y cambios de TASK-035.

## 5. Orden de ejecución y dependencias

1. **B1** — red de seguridad (fallback) antes de retirar la vía manual.
2. **B2 → B3 → B4** — eliminar la cadena pública de fuera hacia dentro.
3. **B5 + F1** — eliminar clave y botón en el mismo commit/despliegue.
4. Verificación estática y manual.
5. Revisión independiente y seguridad.
6. Informe final.

B5 y F1 son indivisibles. B1 es independiente. B2-B4 no deben desplegarse sin B5-F1.

## 6. Criterios de aceptación

- **CA1:** en ninguna fase se renderiza el botón «Esperar».
- **CA2:** `turnBar` no devuelve `mostrarEsperar` y ningún Blade la referencia.
- **CA3:** `Combate` expone 17 métodos públicos; `pasarTurno` no existe en `Combate`, `OrquestadorBatalla` ni `GestorSeleccionBatalla`.
- **CA4:** `$wire.pasarTurno()` produce error de método y no consume acción ni modifica la sesión.
- **CA5:** `Retrasar turno`, `Atacar con X`, «Cambiar objetivo», objetos y preparación siguen funcionando.
- **CA6:** con todos los movimientos no disponibles y sin objetos aplicables, el turno avanza automáticamente con log comprensible y sin bloqueo.
- **CA7:** con al menos un movimiento disponible o un objeto `aplicable`, el fallback no se dispara.
- **CA8:** con preparación pendiente, el fallback no se dispara hasta confirmar/omitir; `Guardia`/`Cubrir` y prioridades conservan su efecto.
- **CA9:** consumo único por actor y avance correcto de ronda; sin recursión infinita.
- **CA10:** `SESSION_VERSION` y la serialización del agregado no cambian.
- **CA11:** sin cambios en tests, dependencias, migraciones ni en los archivos ajenos.

## 7. Verificación requerida

### 7.1. Estática

```bash
grep -rn "pasarTurno" app resources src          # 0 coincidencias
grep -rn "mostrarEsperar" app resources src      # 0 coincidencias
grep -c "public function" app/Livewire/Combate.php   # 17

php -l app/Livewire/Combate.php
php -l app/Livewire/Combate/Soporte/OrquestadorBatalla.php
php -l app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php
php -l app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php
php -l app/Livewire/Presenters/BatallaVistaPresenter.php

vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=512M
php artisan view:cache
git diff --check
git status --short
```

### 7.2. Manual

1. Combate normal: sin «Esperar»; atacar, usar objeto, «Cambiar objetivo», «Retrasar turno», «Atacar con X», preparación.
2. Estado sin acción legal: silencio/Mofa con solo movimientos de estado, o carga no completada como única opción; comprobar salto automático y log en «Registro».
3. Preparación pendiente: el turno no se auto-salta hasta «Listo»/«Saltar»; `Guardia`/`Cubrir` y prioridades funcionan.
4. Pestaña abierta antes del despliegue: invocar «Esperar» produce error de método sin cambios de estado; refrescar.
5. Compañero con acción legal: el fallback no se dispara.
6. `Guardia`/`Cubrir` consumen la acción como antes.

**Tests:** no se crean, no se modifican y no se ejecutan.

## 8. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Soft-lock al quitar Esperar. | Fallback automático con log cuando no hay movimiento ni objeto aplicable. |
| Pestaña abierta con HTML antiguo. | Error de método no destructivo; despliegue conjunto y `view:clear`. |
| Fallback silencioso. | Log claro en el registro; sin banner por alcance mínimo. |
| Doble consumo o bucle. | Guardar antes de `nextActor`; `consumeAction` finito. |
| Preparación pendiente y pérdida de posturas. | El fallback se difiere hasta confirmar/omitir. |
| Acoplamiento al flag `aplicable`. | Contrato de vista documentado y lectura en método privado. |
| `nextActor` público preexistente. | Fuera de alcance; con acción legal no consume. |

## 9. Rollback

- Revertir el commit de implementación de TASK-039.
- No hay migraciones, cambios de esquema ni de sesión.
- Al revertir, el botón y la cadena `pasarTurno` vuelven a estar disponibles; no hay datos que deshacer.
- Desplegar/revertir con el backend y el Blade juntos para evitar la referencia a `mostrarEsperar` o al método eliminado.

## 10. Decisiones que autoriza la aprobación del plan v1

1. Eliminar por completo el método público `pasarTurno` (18 → 17 métodos).
2. Mantener `Retrasar turno`, `Atacar con X`, «Cambiar objetivo», objetos y preparación.
3. Considerar `Guardia`/`Cubrir` como acción válida.
4. Fallback automático con log solo cuando no haya movimiento ni objeto aplicable.
5. El fallback se difiere si la preparación está pendiente.
6. Sin aviso visible fuera del registro y sin puente de compatibilidad.
7. Sin cambios en IA, `SESSION_VERSION`, serialización, migraciones, dependencias ni tests.
