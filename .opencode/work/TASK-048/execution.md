# TASK-048 — Ejecución

- **Estado:** IMPLEMENTING (checkpoint git solicitado por el desarrollador)
- **Responsable de coordinación:** `orchestrator`

## Preflight (2026-11-16)

- **Directorio de trabajo:** `/home/david/pokemon`.
- **Rama:** `task-011`.
- **`git status`:** 29 archivos modificados y 260 entradas sin seguimiento, incluidas las carpetas `.opencode/work/TASK-032` a `TASK-048`, cambios de UI/expediciones/PC y el módulo `src/Avistamientos/`.
- **Archivos sensibles para esta tarea:**
  - `resources/views/layouts/app.blade.php` — modificado localmente. **No se ha tocado.**
  - `src/CombateRuta/App/GeneradorEquipoRuta.php` — modificado localmente (`especiesPonderadas`). **No se ha tocado.**
  - `resources/views/layouts/partials/navbar-acciones.blade.php` — **sin seguimiento**. No se ha tocado.
  - `app/Providers/AppServiceProvider.php`, `routes/web.php`, `src/Exploraciones/**` — limpios.
- **Master:** `master` es ancestro de `task-011` (28 commits por detrás). El merge será fast-forward.
- **Secretos:** se revisaron los 260 archivos sin seguimiento y el diff de los 29 modificados; no se detectaron claves AWS, claves privadas, tokens `sk-`, `ghp_` ni `xox`. `.env` y claves están en `.gitignore`.
- **PHPStan:** `src/Avistamientos/` queda limpio (solo el aviso de baseline no matcheado de `src/Granjas/App/*`). El proyecto completo tiene 34 errores preexistentes, ajenos a TASK-048; no se corrigen en este checkpoint.

## Registro

- 2026-11-16: petición recibida y transcrita en `request.md`.
- 2026-11-16: preflight ejecutado.
- 2026-11-16: análisis de integración delegado al `analyst` (sesión `ses_e1c8f4d9affe5o5kivz5Gk3mrW`); completado y guardado en `analysis.md`.
- 2026-11-16: diseño delegado al `architect` (sesión `ses_e1c8ad95dffewXh1nI8MuUnAO0`); completado y guardado en `architecture.md`.
- 2026-11-16: plan v1 redactado y aprobado con el texto `Aprobar plan v1`.
- 2026-11-16: migración `avistamiento_eventos` creada y verificada con `php -l`.
- 2026-11-16: dominio y puerto `FactorAparicion` creados; `php -l` y PHPStan limpios.
- 2026-11-16: Infra (`AvistamientoEvento` + `EloquentAvistamientoRepositorio`) creada; PHPStan limpio tras corregir 4 avisos.
- 2026-11-16: **autorización adicional del desarrollador:** «Commitea todo y mergealo contra master, tambien lo que no es tuyo». Se interpreta como autorización explícita para commit local de todo el árbol de trabajo y merge local fast-forward a `master`, sin `push`.
- 2026-11-16: commit y merge en curso.

## Estado de la implementación

- **Hecho:** migración, dominio, puerto compartido, modelo Eloquent y repositorio.
- **Pendiente:** casos de uso (`ObtenerSeleccionVigente`, `ObtenerAvistamientoPagina`), `FactorAparicionAvistamientos`, controlador, ruta, bindings, integración en ruta/exploración, vista `/avistamientos`, icono de navbar, ADR, revisión y verificación manual.
- **TASK-048 no está completada.** El commit es un checkpoint solicitado; no debe interpretarse como entrega final.

## Verificación

- `php -l` sobre todos los PHP de `src/Avistamientos/`: sin errores.
- `vendor/bin/pint --dirty --format agent`: `pass`.
- `vendor/bin/phpstan analyse src/Avistamientos`: sin errores del módulo; solo el aviso de baseline de `src/Granjas/App/*`.
- Sin tests ejecutados. Sin migraciones ejecutadas.
