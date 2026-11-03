# TASK-009 — Plan v1

- **Estado:** `AWAITING_APPROVAL`
- **Fecha:** 2026-11-02
- **Proyecto:** `/home/david/pokemon`
- **Baseline:** `5917dc5 updates`, `master`, árbol limpio.
- **Rama propuesta:** `refactor/ui-tokens` desde `master`, sin `push`.

## 1. Objetivo

Unificar la presentación visual de toda la aplicación y la escala de imágenes de Pokémon en **128px / 96px / 64px**, mediante una capa única de tokens, componentes Blade reutilizables y un componente `sprite`. Bootstrap quedará confinado al combate. El resultado debe permitir cambiar el theme futuro editando solo `resources/css/tokens.css`, sin tocar vistas.

## 2. Alcance

1. **Tokens de tema:** colores semánticos, tipografía, radios, sombras, espaciado, foco, colores de tipo y calidad IV en `resources/css/tokens.css`; utilidades y componentes en `resources/css/components.css`.
2. **Modo oscuro por variables:** `.dark` redefine tokens, no utilidades; se eliminan los `dark:` por archivo migrado.
3. **Componentes base:** `ui.button`, `ui.input`, `ui.select`, `ui.card`, `ui.badge`, `ui.alert`, `ui.modal`, `ui.empty-state`, `tipo-badge` y `sprite`.
4. **Sprites Pokémon:** todos los renders migrados a `<x-sprite>` con escala 128/96/64, `object-contain`, dimensiones intrínsecas, lazy/eager, alt y fallback único.
5. **Migración de vistas:** las 44 vistas Blade (layout, auth, onboarding, sendas, misiones, gimnasios, reclutamiento, Pokédex, exploraciones, hábitats, equipos, combate), excluyendo la vista legacy no enrutada.
6. **Bootstrap:** CSS en `@layer bootstrap`; JS de Bootstrap solo en combate; puente `--bs-* → var(--color-*)`; eliminación de parches `!important` y de la mezcla de escalas en combate.
7. **Colores de tipo:** `TipoPokemon::color()` como fuente única de hex, emitido a variables CSS; `TipoBadges` mantiene compatibilidad durante la migración.
8. **Limpieza:** retirar componentes muertos y duplicaciones cuando dejen de usarse; dejar la base lista para el theme.

## 3. Fuera de alcance

- Lógica de negocio, dominio, DTOs, controladores, Livewire backend, consultas y migraciones.
- Tests: no se crean, actualizan, ejecutan ni reparan; `tests/` no se toca.
- Redimensionado u optimización de archivos de imagen: los assets actuales se mantienen.
- Cambio de theme definitivo: solo se deja la base y una prueba de que un token cambia la UI sin tocar vistas.
- Sustitución de Bootstrap por Tailwind o eliminación de Bootstrap (se aísla, no se elimina).
- Mover Chart.js fuera de `app.js`.
- Refactor de clases de estilo emitidas por PHP (`hpClass`, `posturaClase`, `claseCarta`) a estados; queda como deuda posterior.
- Eliminación de `reclutados/index.blade.php` y `public/css/app.css` (legacy), salvo que el desarrollador lo autorice expresamente.
- Documentación en `docs/`: no se solicita. El ADR queda en `architecture.md` de esta tarea.

## 4. Decisiones incluidas en el plan

| ID | Decisión | Estado |
|----|----------|--------|
| D1 | Tokens en `resources/css/tokens.css` + `components.css`; `app.css` solo importa y conserva calidad IV. | Aceptada |
| D2 | Color de tipos desde `TipoPokemon::color()` emitido a `--type-*`; sin duplicar hex en CSS/Blade. | Aceptada |
| D3 | Bootstrap CSS en `@layer bootstrap`; si `@tailwindcss/vite` no preserva la capa, plan B: sin capa + parches explícitos documentados. | Aceptada con fallback |
| D4 | Quitar `bootstrap.bundle` de `resources/js/app.js`; JS de Bootstrap solo en combate. | Aceptada |
| D5 | Namespace `resources/views/components/ui/` para los componentes base. | Aceptada |
| D6 | Reescribir los 8 componentes muertos sobre tokens y eliminar los duplicados al final. | Aceptada |
| D7 | `ui.modal` con `<dialog>` nativo; si Livewire morph falla en el piloto, fallback a `div` + Alpine con foco gestionado. | Aceptada con fallback |
| D8 | Sprite de combate a 128px (`--sprite-combat`); cambiar a 96 es una línea en `tokens.css`. | Aceptada |
| D9 | No eliminar la vista legacy ni `public/css/app.css` en esta tarea. | Aceptada |
| D10 | No mover Chart.js. | Aceptada |
| D11 | Theme futuro claro/oscuro; los tokens permiten añadir paletas alternativas después. | Aceptada |
| D12 | No refactorizar las clases de estilo emitidas por PHP. | Aceptada |

## 5. Contratos clave

### 5.1 Sprite

- `<x-sprite>` con `id|src`, `name`, `shiny`, `suffix`, `size` (`128|96|64|inline|combat`), `loading`, `priority`, `decorative`.
- Contenedor `.sprite` con `aspect-ratio:1`, `width:var(--sprite-size)`, `max-width:100%`, `flex-shrink:0`.
- Imagen interna `width="128" height="128"` intrínsecos, `object-contain`, `decoding="async"`.
- Fallback único: `shiny_s → {id}.webp → 0.webp`; prohibido `/images/iconos/*.png`.
- `loading="eager"` + `fetchpriority="high"` en vanguardia de combate y retrato principal de detalle; `lazy` en el resto.
- `alt` con nombre; `decorative` solo si el nombre ya está visible adyacente.

### 5.2 Mapeo de tamaños

| Contexto | Tamaño |
|---|---|
| Combate (tarjeta), detalle de equipo/Pokédex/reclutado | **128** |
| Rejillas y listas de varios: Pokédex, equipos, hábitats, reclutamiento, gimnasios admin, evolución, huevos disponibles | **96** |
| Miniaturas: exploraciones, huevos incubando, formación de gimnasio, mazmorra, modal de reemplazo, familia evolutiva | **64** |
| Micro-iconos inline (turno, postura, estado) | **inline (32)** |
| Pila de salvajes | **inline (32)** con `.sprite-stack` |

La rejilla Pokédex de 9 columnas usa `max-width:100%` para reducirse a ~81px sin desbordar. El `w-96` (384px) de hábitats desaparece: favoritos y formación a 96/64, hero de granja a 128.

### 5.3 Tokens

- Semánticos: `canvas`, `surface`, `surface-raised`, `surface-sunken`, `line`, `line-strong`, `ink`, `ink-muted`, `ink-subtle`, `ink-inverse`, `primary` (+ hover/soft/ink/on), `success`, `warning`, `danger`, `info` (+ soft/ink), `focus`.
- Tipografía: `text-2xs` (10px), `text-3xs` (11px puntual).
- Radios: `control`, `card`, `panel`, `full`; sombras: `card`, `modal`.
- Espaciado: `gutter`, `section` (aditivos, sin alterar la escala numérica de Tailwind).
- Sprites: `--sprite-128`, `--sprite-96`, `--sprite-64`, `--sprite-inline`, `--sprite-combat`.
- Tipos: `--type-{slug}` emitidos desde PHP; `--color-type-{slug}` mapeados en `@theme`.
- Calidad IV: `--quality-*` existentes, expuestos también como tokens de tema.

## 6. Fases, archivos y responsables

| Fase | Archivos | Responsable | Depende de |
|---|---|---|---|
| **F0 · Preparación** | Crear rama `refactor/ui-tokens` desde `master`; confirmar `git status` limpio; guardar baseline `5917dc5` en `execution.md`. | `orchestrator` | — |
| **F1 · Tokens** | Crear `resources/css/tokens.css`, `resources/css/components.css`, `resources/views/partials/tipos-tokens.blade.php`; modificar `resources/css/app.css`, `resources/views/layouts/app.blade.php`, `resources/views/layouts/auth.blade.php`, `resources/views/partials/tipos-badge-script.blade.php`. | `frontend` | F0 |
| **F2 · Bootstrap** | Modificar `resources/js/app.js`, `resources/css/combate.css` (import en capa + eliminar 3 parches), `resources/views/combate_page.blade.php` solo si es necesario. Verificar `resources/js/combate.js` y `resources/js/bootstrap.js`. | `frontend` | F1 |
| **F3 · Componentes** | Crear `resources/views/components/ui/{button,input,select,card,badge,alert,modal,empty-state}.blade.php`, `resources/views/components/sprite.blade.php`; modificar `resources/views/components/tipo-badge.blade.php`, `resources/css/components.css`; piloto en `welcome`, `auth/login` y modal de Pokédex. | `frontend` | F1 |
| **F4a · Layout/auth** | `layouts/app`, `layouts/auth`, `auth/login`, `auth/register`, `welcome`, `onboarding/equipo-inicial`. | `frontend` | F3 |
| **F4b · Módulos medios** | `sendas/index`, `sendas/show`, `misiones/index`, `gimnasios/index`, `reclutamiento/index`, `reclutado/show`, `habitats/index`. | `frontend` (secuencial o owners disjuntos) | F3, F4a |
| **F4c · Pokédex/exploraciones/gimnasios** | `pokedex/index`; `exploraciones/index`, `_evento`, `_explorador`, `_perdidas`, `_diario`, `_caramelo`; `gimnasios/admin`, `gimnasios/show`. | `frontend` (owners disjuntos) | F3, F4a |
| **F4d · Hábitats** | `habitats/show` + partials nuevos por sección (cabecera, equipos, especies, granja, modales). | `frontend` exclusivo | F4c |
| **F4e · Equipos** | `equipos/index` + partials nuevos por sección (inventario, huevos, detalle, evolución, modales); `equipos/partials/movimiento-card`. | `frontend` exclusivo | F4d |
| **F5 · Combate** | `resources/css/combate.css`, `livewire/combate`, `livewire/_pokemon-card`, `livewire/partials/{moves-panel,preparacion-panel,turn-bar,battle-field,battle-log,battle-log-modal}`, `combate_page`. | `frontend` | F1, F2, F3 |
| **F6 · Limpieza y prueba de theme** | `tokens.css` (prueba de cambio de token y reversión), retirar componentes muertos solo si `grep` confirma 0 usos; retirar `TipoBadges::CLASES` solo si no hay usos; no tocar legacy. | `frontend` | F4a–F4e, F5 |
| **Revisión** | Diff completo, criterios de aceptación, accesibilidad, contratos y regresiones. | `reviewer` | F6 |
| **Seguridad** | `NO APLICA`: no se modifican autenticación, autorización, consultas, entrada de usuario ni endpoints. Solo presentación. | — | — |
| **Documentación** | `NO APLICA`: no solicitada; el ADR queda en `architecture.md`. | — | — |

## 7. Orden y dependencias

1. **F0 → F1 → F2 → F3** son la base. F1 y F2 pueden solaparse solo si F2 no toca `tokens.css`; F3 depende de F1.
2. **F4a** cierra `layouts/app` antes de abrir módulos; nadie más lo toca después.
3. **F4b** y **F4c** pueden ejecutarse en paralelo con archivos disjuntos.
4. **F4d → F4e** son estrictamente secuenciales: ambas redefinen patrones de card/modal y son las dos vistas monolíticas.
5. **F5** va al final porque es la única superficie con animaciones y Bootstrap.
6. **F6** solo cuando todas las vistas estén migradas; después, revisión independiente.

## 8. Criterios de aceptación

### Globales

1. **Sprites:** los 34 renders de Pokémon usan `<x-sprite>` o el patrón `.sprite`; no queda ningún `w-96` en imágenes Pokémon; los tamaños corresponden a la tabla §5.2; todas las imágenes tienen `width`/`height` intrínsecos y `object-contain`.
2. **Tokens:** todo color de la app sale de `tokens.css`; `grep -nE '#[0-9a-fA-F]{6}' resources/views/**` no encuentra valores en archivos migrados; `grep -c 'dark:'` = 0 en cada archivo migrado (salvo excepción documentada); `grep -c 'text-\[10px\]'` = 0.
3. **Componentes:** los 9 componentes existen, tienen props documentadas en el propio archivo y se usan en al menos una vista piloto; no emiten clases Bootstrap.
4. **Bootstrap:** `resources/js/app.js` no importa `bootstrap.bundle`; `data-bs-*` solo aparece en `livewire/**` y `combate_page`; `combate.css` es el único CSS que importa Bootstrap; los 3 parches `!important` desaparecen.
5. **Theme:** cambiar un valor de `tokens.css` altera la UI sin modificar ningún archivo de `resources/views/**`; `git diff --stat resources/views` queda vacío tras la prueba.
6. **Accesibilidad:** foco visible en interactivos; modales con `role="dialog"`/`aria-modal`/`aria-labelledby`; iconos decorativos con `alt=""`; contraste AA en texto esencial.
7. **Sin regresiones funcionales:** no se modifican controladores, dominio, Livewire backend, Chart.js ni consultas; las rutas y flujos existentes siguen igual.
8. **Sin tests:** no se crea, modifica ni ejecuta ningún test.

### Por fase

- **F1:** `tokens.css` compila; `app.css` importa tokens/componentes; no cambia ninguna clase de vista salvo los includes de layouts; `.dark body !important` desaparece.
- **F2:** Bootstrap JS no se carga en páginas no-combate; el modal de combate sigue funcionando; Bootstrap CSS solo se importa en `combate.css`.
- **F3:** cada componente tiene 0 hex, 0 `text-[10px]` y 0 valores arbitrarios; `sprite` se prueba en una vista piloto con 128/96/64.
- **F4a:** 6 archivos sin `dark:` ni `text-[10px]`; alertas de sesión usan `ui.alert`.
- **F4b:** cada archivo sin `dark:`, `text-[10px]`, `$tipo['color']` inline ni `/images/iconos/`; usa `x-sprite` donde corresponde.
- **F4c:** Pokédex/exploraciones sin `iconUrl()` con PNG roto; `x-sprite` en los renders de Pokémon; sin `dark:`/`text-[10px]`.
- **F4d:** `habitats/show` sin `w-96`, `$cardPanelClass`, `dark:` ni `text-[10px]`; los 16 `<img>` de Pokémon usan `.sprite`; partials extraídos.
- **F4e:** `equipos/index` sin `text-[10px]`, `dark:`, `$cardPanelClass`, hex en `calidadIvs()`; Chart.js lee tokens resueltos; modales con `ui.modal`.
- **F5:** `combate.css` sin hex propios (salvo animaciones sin color); `bg-hp-*` con tokens; `prefers-reduced-motion`; `data-bs-*` intacto; animaciones conservadas.
- **F6:** `grep` de componentes muertos = 0 usos; `TipoBadges::CLASES` retirado solo con 0 referencias; prueba de theme satisfactoria.

## 9. Verificación requerida

- **Estática:** `grep`/`rg` por fase según §8 y los comandos del `architecture.md` §7.
- **Compilación:** `npm run build` al final de F1, F3 y F6 (Vite compila Tailwind/CSS); no es una suite de tests.
- **Blade:** opcional `php artisan view:cache` seguido de `php artisan view:clear` para detectar errores de compilación; no renderiza datos.
- **Manual:** el desarrollador revisa en `npm run dev` las páginas principales en claro/oscuro: `/`, `/login`, `/pokedex`, `/equipos`, `/reclutamiento`, `/habitats/{id}`, `/exploraciones`, `/gimnasios`, `/sendas`, `/misiones`, `/combate`.
- **Revisión independiente:** `reviewer` valida diff, contratos, accesibilidad, 0 hex, Bootstrap aislado y ausencia de cambios de lógica.
- **Seguridad:** `NO APLICA` (sin cambios de autenticación, autorización, entrada, SQL, sesiones, cookies, secretos, dependencias ni endpoints).
- **Tests:** `NO APLICA`; no se ejecutan.

## 10. Riesgos y mitigaciones

| Riesgo | Severidad | Mitigación |
|---|---|---|
| `@layer bootstrap` invierte Reboot/Preflight en combate | Alto | F2 aislada; comparación visual; plan B sin capa y parches explícitos. |
| Vistas monolíticas (`equipos`, `habitats`) y conflictos | Alto | Owners exclusivos, extracción de partials al tocar, commits por sección. |
| Regresiones visuales por dark mode | Medio | Tokens semánticos, revisión en claro/oscuro por módulo, `dark:` = 0. |
| Livewire morph con `<dialog>` | Medio | Piloto en modal de Pokédex; fallback a `div` + Alpine. |
| Chart.js no acepta `var()` | Bajo | Leer tokens con `getComputedStyle`; mantener hex temporal si falla. |
| Pérdida de animaciones de combate | Medio | F5 al final; mismas clases de animación; `prefers-reduced-motion`. |
| Build/CSS no compila por sintaxis `@theme` | Medio | `npm run build` en F1 y F6; corregir antes de seguir. |
| `TipoBadges::CLASES` retirado antes de tiempo | Medio | Retirar solo en F6 con `grep` de 0 usos; conservar `@source` hasta entonces. |
| Cambio de tamaño de slots de equipo (64→96) | Bajo | Aceptado en §5.2; revisión visual. |

## 11. Rollback

- Rama `refactor/ui-tokens` y **un commit por fase/módulo**; sin `push`.
- F1–F3 son aditivas: revertir el commit deja la app como `5917dc5`.
- F4a–F4e y F5 se revierten por fase/módulo.
- No se usa `reset --hard`, `clean` ni `checkout --` sobre cambios del desarrollador.
- Si el theme futuro no funciona, `git revert` de F1 deja las vistas intactas.

## 12. Aprobación solicitada

Se solicita aprobación explícita del **plan v1** para ejecutar F0–F6, revisión y cierre. La aprobación debe indicar la versión (`v1`).

Opciones: `Aprobar plan v1`, `Solicitar cambios`, `Cancelar tarea`.
