# TASK-034 — Informe final

- **ID:** TASK-034
- **Fecha:** 2026-11-09
- **Estado final:** `COMPLETED` — implementación, verificación estática, revisión y seguridad completadas. Smoke manual en navegador pendiente por el desarrollador.
- **Commit local:** `9519c34` (`feat(task-034): mostrar y animar progreso de EXP tras combate`), sin `git push`.
- **Plan:** v1 aprobado («Aprobar plan v1»).
- **Tests:** no ejecutados, no creados, no modificados.

## 1. Resumen

El modal de victoria ahora muestra el progreso de EXP de forma visual:

- Barra de **cuenta** con nivel y porcentaje final.
- Una barra por cada **Senda de tipo** que gana EXP, con icono, nivel, delta y barra.
- Animación de llenado; al subir de nivel, la barra se llena, se vacía y se reajusta al nuevo porcentaje.
- Máximo 4 segmentos por barra y `niveles_omitidos` cuando se cruzan más niveles.
- Senda en nivel 5: 100 %, “Tope alcanzado”, sin simular un sexto nivel.
- `prefers-reduced-motion` y HTML final correcto sin JS.
- El resto del modal (capturas, caramelos, medalla) se conserva.

La EXP no se vuelve a conceder: los snapshots son de solo lectura alrededor de la concesión existente.

## 2. Archivos creados/modificados

### Backend

| Archivo | Cambio |
|---|---|
| `app/Support/SnapshotProgreso.php` | Nuevo DTO de instantánea. |
| `app/Support/ProgresoSegmentoVista.php` | Nuevo DTO de segmento. |
| `app/Support/ProgresoCuentaVista.php` | Nuevo DTO de barra de cuenta. |
| `app/Support/ProgresoSendaVista.php` | Nuevo DTO de barra de Senda. |
| `app/Support/ProgresoRecompensasVista.php` | Nuevo DTO raíz + `aArray()`. |
| `app/Support/ProgresoRecompensas.php` | Builder `capturar()`/`comparar()`, segmentos, orden, topes y resumen accesible. |
| `app/Livewire/Presenters/PresentadorResultadoBatalla.php` | `medirProgreso()`/`adjuntarProgreso()` y envoltura de los 4 flujos. |
| `app/Livewire/Presenters/BatallaVistaPresenter.php` | Reenvío tolerante de `progreso` en `rewardsVista()`. |

### Frontend

| Archivo | Cambio |
|---|---|
| `resources/views/livewire/partials/reward-progreso.blade.php` | Nuevo contenedor con `aria-live` y bucle de barras. |
| `resources/views/livewire/partials/_reward-progreso-barra.blade.php` | Nuevo markup de barra, nivel, delta y chips. |
| `resources/views/livewire/combate.blade.php` | Un `@include` por layout (ruta y entrenador/gimnasio). |
| `resources/views/livewire/partials/combate-scripts.blade.php` | `rewardProgreso()` y evento `reward-modal-visible`. |
| `resources/css/combate.css` | Estilos, keyframe y `prefers-reduced-motion`. |

## 3. Decisiones

- Snapshots en el presentador, no en `PersistirRecompensas`: no se tocan persistencia ni DTO de recompensa.
- La diferencia real de BD es la fuente de verdad; no se reconstruye la fórmula.
- DTOs readonly y array snake_case solo en la frontera de vista.
- Orden de Sendas en backend: EXP ganada ↓, EXP final ↓, `tipo_id` ↑.
- La cuenta no tiene tope; las Sendas sí (nivel 5).
- El header global no se actualiza en tiempo real; se actualiza al navegar.
- Exploración queda fuera de fase 1.

## 4. Verificación ejecutada

- `php -l` sobre los 8 PHP de la tarea: sin errores.
- `vendor/bin/phpstan analyse --no-progress`: 34 errores preexistentes, ninguno en archivos de TASK-034.
- `composer arch` (Deptrac): 100 violaciones preexistentes; ninguna nueva de TASK-034.
- `vendor/bin/pint --dirty --format agent`: pass.
- `php artisan view:cache` y `view:clear`: correctos.
- `npm run build`: correcto; CSS de combate compilado con las clases nuevas.
- `git diff --check`: sin errores en los archivos de la tarea.
- Pruebas funcionales del builder con datos representativos: orden de Sendas, 4 segmentos con `niveles_omitidos`, tope 71.999 → 72.100 y `type_id` fuera de catálogo.
- `capturar()` contra BD real: consulta de solo lectura válida.

**Tests:** no se ejecutaron ni se modificaron.

## 5. Revisión y seguridad

- **Reviewer:** primera pasada `CHANGES_REQUESTED` por un HIGH real: el partial se incluía dos veces en el layout de ruta. Se movió el segundo `@include` dentro de la rama `@else`; re-revisión `APPROVED`. Criterios 1-14 cumplidos.
- **Security:** `PASS_WITH_FINDINGS`, sin CRITICAL/HIGH. Deuda documentada: advisories de dependencias preexistentes, invariante `meta['user_id'] === Auth::id()` no asertada y forma de `progreso` no normalizada. No bloquea.

## 6. Riesgos residuales

- **Smoke manual pendiente:** animación real en navegador, cruce de 1/3/5 niveles, tope, reduced motion, muchas Sendas y lector de pantalla.
- **`aria-live` en modal oculto:** el resumen se inserta con `.modal-pendiente` (`display:none`); algunos lectores podrían no anunciarlo. Mitigación futura: escribirlo al recibir `reward-modal-visible`.
- **`x-init` redundante** con el `init()` de Alpine; inocuo por el guard `iniciado`.
- **Carrera entre snapshots:** una ganancia externa de EXP del mismo usuario entre las dos lecturas podría inflar la presentación; ventana mínima.
- **Advisories de dependencias** (Livewire < 4.4.8, Symfony/Laravel, CommonMark); fuera de alcance.

## 7. Próximos pasos

1. Smoke manual del desarrollador en `/combate`: victoria de ruta, entrenador, gimnasio y mazmorra; cruce de nivel y tope de Senda.
2. Verificar `prefers-reduced-motion` y el anuncio del resumen con lector de pantalla.
3. Si el navegador no refleja el CSS, ejecutar `npm run dev` o `npm run build` (ya se ejecutó build en esta tarea).
4. Programar la actualización de dependencias y las mejoras de endurecimiento en tareas separadas.
5. No se ha hecho `git push`; el commit `9519c34` queda local.
6. Los artefactos de `.opencode/work/TASK-034/` quedan sin seguimiento en Git.
