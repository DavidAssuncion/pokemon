# TASK-034 — Plan v1

- **ID:** TASK-034
- **Versión:** v1
- **Fecha:** 2026-11-09
- **Estado:** AWAITING_APPROVAL — no autoriza implementación
- **Base:** rama `task-011`
- **Análisis:** `analysis.md` (COMPLETED)
- **Diseño:** `architecture.md` (COMPLETED)

## 1. Objetivo

Mejorar la experiencia visual de la EXP tras un combate:

- Mostrar el nivel de la cuenta y su barra de progreso.
- Mostrar las Sendas de tipo que reciben EXP, con nivel y barra.
- Animar la subida de cada barra.
- Al subir de nivel, llenar la barra, vaciarla y reajustarla al nuevo porcentaje.
- Mantener el resto de recompensas del modal (capturas, caramelos, medalla).

## 2. Alcance

### 2.1. Incluido

- Modal de victoria de combate: ruta, entrenador, gimnasio y mazmorra.
- Cuenta: nivel, porcentaje y EXP antes/después.
- Sendas: todas las que ganan EXP, ordenadas por EXP ganada descendente.
- Animación por segmentos con máximo 4 segmentos por barra y `niveles_omitidos`.
- Senda en nivel 5: 100 %, “Tope alcanzado”, sin simular un sexto nivel.
- `prefers-reduced-motion`: valores finales sin animación.
- Sin JS: el HTML muestra el resultado final correcto.
- Accesibilidad: `role="progressbar"`, `aria-valuenow`, `aria-valuetext` y `aria-live` con resumen final.
- Snapshots de solo lectura antes/después de la concesión; sin duplicar la EXP.

### 2.2. Fuera de alcance

- Pantalla de recompensas de exploraciones (fase 2).
- Actualización en tiempo real de la barra del header.
- Cambios en fórmulas de EXP, economía, curvas, `PersistirRecompensas`, DTOs de recompensa, `Combate.php`, `SESSION_VERSION`, `BattleSessionService`, migraciones, dependencias o tests.
- Corrección del texto preexistente “Has derrotado al entrenador” en ruta/mazmorra sin botín.
- Gestión de foco del modal (deuda previa).
- Persistir el progreso de animación entre recargas.

## 3. Arquitectura y contratos

- **Clave aditiva `rewards['progreso']`**: solo se añade si hay ganancia real de cuenta o Sendas. Si falta, el modal se comporta como hoy.
- **Backend**: builder `App\Support\ProgresoRecompensas` + DTOs readonly:
  - `SnapshotProgreso`
  - `ProgresoSegmentoVista`
  - `ProgresoCuentaVista`
  - `ProgresoSendaVista`
  - `ProgresoRecompensasVista`
- **Snapshot**: `capturar(int $userId)` lee `users.experiencia` y `player_type_paths.exp`; `comparar()` devuelve `?ProgresoRecompensasVista`.
- **Persistencia**: se envuelve la llamada existente con `medirProgreso()`; la lectura “después” se hace inmediatamente tras persistir. No se toca `PersistirRecompensas`.
- **Presentador**: `PresentadorResultadoBatalla` adjunta `progreso` en los cuatro flujos, después de `conMedalla()` en gimnasio.
- **Vista**: `BatallaVistaPresenter::rewardsVista()` reenvía `progreso` de forma tolerante (`is_array(...) ? ... : null`). No toca `refsAliado()` (TASK-033).
- **Blade**: partials nuevos `reward-progreso.blade.php` y `_reward-progreso-barra.blade.php`; sin lógica de negocio.
- **Animación**: componente Alpine `rewardProgreso()` + transiciones CSS; disparo con `reward-modal-visible` desde `mostrarModalVictoria()`.
- **Contrato `progreso`**: el definido en `architecture.md` §2.1 (cuenta y sendas con EXP antes/después, niveles, porcentajes, segmentos, topes y resumen accesible).

## 4. Archivos y responsables

### 4.1. Backend (`backend`)

| Orden | Archivo | Cambio |
|---|---|---|
| B1 | `app/Support/SnapshotProgreso.php` | Nuevo DTO de instantánea. |
| B2 | `app/Support/ProgresoSegmentoVista.php` | Nuevo DTO de segmento. |
| B3 | `app/Support/ProgresoCuentaVista.php` | Nuevo DTO de cuenta + `aArray()`. |
| B4 | `app/Support/ProgresoSendaVista.php` | Nuevo DTO de Senda + `aArray()`. |
| B5 | `app/Support/ProgresoRecompensasVista.php` | Nuevo DTO raíz + `aArray()`. |
| B6 | `app/Support/ProgresoRecompensas.php` | Builder: `capturar()`, `comparar()`, segmentos, orden y resumen. |
| B7 | `app/Livewire/Presenters/PresentadorResultadoBatalla.php` | Envolver las 4 concesiones y adjuntar `progreso`. |
| B8 | `app/Livewire/Presenters/BatallaVistaPresenter.php` | Reenviar `progreso` en `rewardsVista()`; no tocar `refsAliado()`. |

### 4.2. Frontend (`frontend`)

| Orden | Archivo | Cambio |
|---|---|---|
| F1 | `resources/views/livewire/partials/_reward-progreso-barra.blade.php` | Nuevo markup de una barra. |
| F2 | `resources/views/livewire/partials/reward-progreso.blade.php` | Nuevo contenedor con `x-data` y `aria-live`. |
| F3 | `resources/views/livewire/combate.blade.php` | Dos `@include` en los layouts de ruta y entrenador/gimnasio. |
| F4 | `resources/views/livewire/partials/combate-scripts.blade.php` | `rewardProgreso()` y evento `reward-modal-visible`. |
| F5 | `resources/css/combate.css` | Estilos, keyframe y `prefers-reduced-motion`. |

### 4.3. Sin cambios

- `src/Exploraciones/App/PersistirRecompensas.php`.
- `src/CombateEntrenadores/Domain/DataTransferObjects/DatosModalVictoria.php`.
- `src/CombateRuta/Domain/DataTransferObjects/ResultadoRuta.php`.
- `app/Livewire/Combate.php`, `GestorAccionBatalla.php`, gestores de preparación y `BatallaVistaPresenter::refsAliado()` (TASK-033).
- `SESSION_VERSION`, `BattleSessionService`, migraciones, dependencias, tests, `bugs.txt`, `TASK-032/`, `TASK-033/`.

## 5. Orden de ejecución y dependencias

1. **B1 → B6** — DTOs y builder.
2. **B7 + B8** — presentadores.
3. **F1 + F2** — partials (pueden empezar con el contrato congelado).
4. **F3 + F4 + F5** — integración, animación y estilos.
5. **Verificación estática** — §7.1.
6. **Revisión** — `reviewer`; `security` no aplica previsiblemente.
7. **Informe final**.

**Secuenciación con TASK-033:** el backend de TASK-034 puede empezar tras la aprobación porque no comparte archivos. El frontend de TASK-034 debe esperar a que TASK-033 esté cerrado (o al menos a que su frontend esté commiteado) para evitar hunks simultáneos en `combate.blade.php` y `combate-scripts.blade.php`.

## 6. Criterios de aceptación

1. Tras una victoria, el modal muestra la barra de cuenta con nivel final y porcentaje final.
2. Se muestra una barra por cada Senda que recibe EXP, con icono, nombre, nivel, barra y delta.
3. Las Sendas se ordenan por EXP ganada descendente en backend.
4. Al cruzar un nivel, la barra se llena, se vacía y se reajusta al porcentaje del nuevo nivel.
5. Con varios niveles se animan como máximo 4 segmentos y se muestra `niveles_omitidos`.
6. Una Senda en nivel 5 muestra 100 %, “Tope alcanzado” y la EXP ganada, sin sexto nivel.
7. La animación no vuelve a conceder EXP ni recalcula fórmulas.
8. Sin JS y con `prefers-reduced-motion: reduce`, el HTML final es correcto.
9. Recompensas sin `progreso` renderizan el modal actual sin errores.
10. La medalla de gimnasio sigue apareciendo y `conMedalla()` no pierde datos.
11. Los layouts de ruta y entrenador/gimnasio siguen funcionando.
12. No se modifican `PersistirRecompensas`, DTOs de recompensa, `Combate.php`, `SESSION_VERSION`, `BattleSessionService`, migraciones ni dependencias.
13. El modal hace scroll con muchas Sendas sin desbordar.
14. `aria-live` anuncia el resumen final una vez; cada barra expone `role="progressbar"` y valores finales.

## 7. Verificación requerida

### 7.1. Estática

```bash
php -l app/Support/SnapshotProgreso.php
php -l app/Support/ProgresoSegmentoVista.php
php -l app/Support/ProgresoCuentaVista.php
php -l app/Support/ProgresoSendaVista.php
php -l app/Support/ProgresoRecompensasVista.php
php -l app/Support/ProgresoRecompensas.php
php -l app/Livewire/Presenters/PresentadorResultadoBatalla.php
php -l app/Livewire/Presenters/BatallaVistaPresenter.php

composer analyse
composer arch
vendor/bin/pint --dirty --format agent
php artisan view:cache
npm run build
```

Comprobaciones específicas: PHPDoc de listas tipadas, tipos de retorno explícitos, `array<string,mixed>` en helpers que reciben `rewards`, y que `rewardsVista()` no cambia su contrato previo.

### 7.2. Manual

1. Ruta con un tipo: cuenta + una Senda, con EXP cerca del umbral 9.000.
2. Ruta con doble tipo: dos barras de Senda.
3. Entrenador: barra junto a `exp_total`/`exp_miembro`.
4. Gimnasio: medalla visible y barra presente.
5. Mazmorra: barra con el layout actual.
6. Cuenta cruza 1, 3 y 5 niveles: límite de 4 segmentos y `niveles_omitidos`.
7. Senda 71.999 → 72.100: nivel 5, “Tope alcanzado”, sin vaciado.
8. Senda ya en 72.000+: barra al 100 %, delta visible.
9. Recompensa sin `progreso`: modal intacto.
10. `prefers-reduced-motion: reduce`: valores finales inmediatos.
11. Lector de pantalla: resumen `aria-live` una vez y barras con `aria-valuetext`.
12. Muchas Sendas: scroll sin desbordar.
13. Sin JS: anchos y niveles finales visibles.

**Tests:** no se crean, no se modifican y no se ejecutan.

## 8. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Cambio concurrente de EXP entre capturas. | Ventana mínima; captura “después” inmediatamente tras persistir. |
| Flash del ancho final antes de animar. | El modal suele estar oculto por `modal-pendiente`; ajuste síncrono antes de animar. |
| Animación repetida en morphs. | `iniciado` + `wire:key` por hash del payload. |
| Modal revelado sin animación si no se emite el evento. | HTML final correcto; evento en `mostrarModalVictoria()`. |
| Muchas Sendas alargan el modal. | Orden backend, barras compactas, scroll y escalonado acotado. |
| Datos legados con `exp = 0` y `level > 1`. | Se deriva el nivel de `exp`, igual que `CargarMapaSendaJugador`; sin regresión. |
| Texto “Has derrotado al entrenador” en ruta/mazmorra sin botín. | Fuera de alcance; documentado. |
| Conflicto con TASK-033 en Blade/scripts. | Backend en paralelo; frontend después de TASK-033. |
| Payload grande en Livewire. | Máximo 18 Sendas; escalares y arrays. |
| `@js($progreso)` con datos no serializables. | El DTO `aArray()` garantiza escalares/arrays. |

## 9. Rollback

- Revertir el commit de implementación de TASK-034.
- No hay migraciones, cambios de esquema ni datos persistidos que deshacer.
- Las recompensas antiguas siguen funcionando sin `progreso`.

## 10. Decisiones que autoriza la aprobación del plan v1

1. Fase 1 solo modal de combate; exploración fuera de alcance.
2. Mostrar todas las Sendas que ganan EXP, ordenadas por EXP ganada.
3. `MAX_SEGMENTOS = 4` con `niveles_omitidos`.
4. Duración objetivo de ~2,2 s en el peor caso (escalonado 70 ms, segmento 140-400 ms, pausa 70 ms).
5. Mostrar totales exactos (`exp_mostrada_despues / exp_siguiente`) además de nivel y delta.
6. Crear 6 archivos nuevos en `app/Support` (builder + 5 DTOs readonly).
7. El header no se actualiza en tiempo real; se actualiza al navegar.
8. La deuda del texto “Has derrotado al entrenador” queda para otra tarea.
9. Backend de TASK-034 puede empezar tras la aprobación; frontend espera al cierre de TASK-033.
10. Sin cambios en `PersistirRecompensas`, DTOs de recompensa, `Combate.php`, `SESSION_VERSION`, `BattleSessionService`, migraciones, tests ni dependencias.
