# TASK-053 — Revisión

- **Revisor:** `reviewer` independiente.
- **Fecha:** 2026-11-17.
- **Veredicto:** **APPROVED**.
- **Alcance revisado:** commit `ffa6cc7` (dominio) + presentación en el árbol de trabajo.
- **Excluido:** cambios preexistentes de TASK-049, TASK-050 y TASK-051.

## Criterios de aceptación

| # | Criterio | Resultado | Evidencia |
|---|---|---|---|
| 1 | 18 gimnasios con 4 sprites distintos; etapa 4 = líder; 1-3 = `Entrenador N` | PASS | Manifiesto 18 gimnasios, 4 slugs por gimnasio sin duplicados; runtime `Bugsy`…`Clair` |
| 2 | JSON `{etapa, nombre}`; etapa 4 = líder; sin `Líder` literal | PASS | `EtapaGimnasio::toArray()` intacto; `ObtenerGimnasioDetalle("bug")` → `etapa 4 = Bugsy` |
| 3 | 56 hábitats con base + 2 variantes; panel de ruta y botón lateral con base | PASS | Manifiesto 56 ids reales; `spriteEntrenador()` 1→clase, 2-3→variantes; `claseBaseHabitat()` |
| 4 | 40×40 CSS, `width`/`height`, `lazy`, `async`, `pixelated` | PASS | 4 `<img>` de TASK-053 y componente |
| 5 | Decorativos `alt=""` + `aria-hidden`; líder con `alt` de nombre | PASS | Hábitats y etapas 1-3 decorativos; etapa 4 con nombre |
| 6 | Manifiesto válido: slugs↔PNG, gyms↔BD, hábitats↔BD, `clases` completa | PASS | 74 clases = 74 PNG = 74 usados; 18 gyms = 18 BD; 56 hábitats = 56 BD |
| 7 | `THIRD_PARTY_NOTICES.md` documenta repo, commit, Showdown, LICENSE y uso privado | PASS | `THIRD_PARTY_NOTICES.md:36-58` |
| 8 | Sin tocar CSS, tests, migraciones ni contratos; TASK-050/051 intactas | PASS | `git diff resources/css` sin `trainer`/`sprite`; sin cambios en `tests/`; rejilla y modal de TASK-050 conservados |
| 9 | `php -l`, Pint, `npm run build`, `view:cache`, `git diff --check` | PASS | 7 `php -l` OK; Pint pass; build OK; `view:cache` OK; diff check limpio |
| 10 | Verificación manual de 18 gimnasios/56 hábitats/ruta/fallback/teclado/1×-2× | PASS (estático) | Datos y fallback verificados; navegador no ejecutado |

## Hallazgos

### LOW 1 — `aria-hidden` dinámico en el sprite de etapa

`resources/views/gimnasios/show.blade.php:123-126` usa `alt` vacío + `aria-hidden`
dinámico. Funciona, pero es más frágil que un `aria-hidden="true"` fijo; el nombre
del líder ya está en el texto adyacente. Sin impacto funcional.

### LOW 2 — Fallback `onerror` duplicado

El fallback a `/images/misc/trainer.webp` está repetido en 4 vistas y con ligeras
diferencias (el componente usa `Js::from`; gimnasios no tiene guarda anti-bucle).
Hoy no falla porque `trainer.webp` existe. Deuda de mantenimiento.

### LOW 3 — Componente `x-trainer-sprite` sin consumidor

El componente está creado y validado por `view:cache`, pero las vistas de gimnasios y
hábitats resuelven en Alpine. El plan lo preveía para el panel de ruta y el botón
lateral; el implementador usó Alpine. No rompe nada; queda como componente preparado
para vistas servidor o para una futura unificación.

### NIT 4 — `aria-hidden="false"` redundante

`resources/views/gimnasios/show.blade.php:126` emite `aria-hidden="false"` en etapa 4;
es válido pero redundante.

### NIT 5 — Artefactos de coordinación

`tasks.md`, `execution.md`, `review.md`, `security.md` y `final-report.md` estaban
pendientes de actualizar. Se actualizan en el cierre.

## Evidencia

- `git show --stat ffa6cc7`: 4 archivos de dominio, +71/−3.
- Manifiesto: 18 gimnasios, 56 hábitats, 74 clases; 0 huérfanos; 0 ausencias.
- Assets: 74 PNG de 80×80, 58 136 B, 0 duplicados; MD5 idéntico al clon.
- Runtime: 18 `nombreEtapa(4)` correctos; shape JSON intacto.
- `php artisan view:cache`: OK.
- `npm run build`: OK (`app-CzvR81Xf.css`).
- `git diff --check`: limpio.
- Sin tests.

## Riesgos residuales

- Verificación visual real pendiente (1×/2×, teclado, móvil).
- Componente sin consumidor y fallback duplicado: deuda menor.
- Licencia del arte: aceptada y documentada; no verificable técnicamente.
- El `onerror` de gimnasios no tiene guarda anti-bucle; hoy irrelevante.

## Recomendación

Aprobar la fase 1. No hay BLOCKER/HIGH/MEDIUM.


## Ajuste v2 — 80×80 (2026-11-17)

- **Veredicto:** **APPROVED**.
- Gimnasios, encabezado de ruta y tarjetas de entrenador a 80×80; botón lateral
  «Entrenadores» a 40×40.
- Manifiesto `tamano_render_px = 80`; componente con default y fallback 80.
- Correcciones visuales tras revisión: el contenedor del gimnasio usa `rounded-card`
  (sin recorte circular) y «Entrenador N» usa `truncate`.
- Sin hallazgos BLOCKER/HIGH/MEDIUM.
- Riesgos bajos: fila sprite + botón ajustada a 1024 px; verificación visual pendiente
  a 1024/640-767 px y 1×/2×.
