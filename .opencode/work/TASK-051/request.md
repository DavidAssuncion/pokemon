# TASK-051 — Petición

- **Fecha:** 2026-11-17
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** rediseño de tema visual (tokens CSS, Tailwind v4 y vistas Blade).
- **Estado:** IMPLEMENTING (plan v1 aprobado)

## Texto literal

> Vamos a usar este theme para light 03 — Stone & ink
>
> Inspiración: diseño editorial, lujo discreto y minimalismo.
>
> Background · Surface · Border · Muted · Text · Accent
>
> #F2EFE9 · #E9E4DA · #D8D0C3 · #A69B8C · #302E2A · #A47750
>
> Para black se usara  // Lavender Grey — Dark
>
> $primary: #A855F7;
> $secondary: #8B88A3;
>
> $success: #7CA982;
> $info: #60A5FA;
> $warning: #FBBF24;
> $danger: #F87171;
>
> // Background
> $body-bg: #1E1B24;
> $body-color: #EAE6F8;
>
> // Surfaces
> $card-bg: #2A2733;
> $modal-content-bg: #2A2733;
> $dropdown-bg: #2A2733;
>
> // Borders
> $border-color: #4A4458;
>
> // Typography
> $headings-color: #EAE6F8;
> $text-muted: #8B88A3;
>
> // Forms
> $input-bg: #2A2733;
> $input-color: #EAE6F8;
> $input-border-color: #4A4458;
>
> // Radius
> $border-radius: .5rem;
> $border-radius-lg: .75rem;
> $border-radius-xl: 1rem;

## Decisiones del desarrollador

- **Proyecto:** `/home/david/pokemon`.
- **Alcance confirmado:** sustituir el tema claro actual por «Stone & ink».
- **Modo oscuro:** sustituir el tema oscuro actual por «Lavender Grey — Dark».
- La nomenclatura `light 03` se interpreta como el tema claro vigente que se reemplaza,
  no como una variante adicional conmutable.
- **Acento:** `#A47750` actúa como primario interactivo (botones, enlaces, foco, nav),
  con derivados accesibles AA (`on-primary`, `accent-ink`, hover).
- **Calidad IV:** se conserva la paleta neón actual y su fondo `#1e293b`; no se retematiza.
- **Limpieza de hardcodes:** se autoriza modificar `src/.../Presentation`,
  `src/Battle/Domain/CombatienteVista.php` y `app/Livewire/Presenters` para sustituir
  clases Tailwind/Bootstrap fijas por tokens del tema.
- **Combate:** se analiza primero su comportamiento extraño. El desarrollador reporta
  enlaces con subrayado e icono de plantas ausente, y sospecha de Livewire. La
  investigación y la unificación de CSS/JS de combate forman parte del alcance.
- **Continuidad:** el desarrollador pide avanzar el cambio de estilos mientras se
  investiga la unificación de combate y CSS/JS. La implementación queda supeditada a la
  aprobación explícita del plan.

## Contexto conocido

- El proyecto es una aplicación Laravel 12 + Blade + Tailwind CSS v4.
- Los tokens de tema viven en `resources/css/tokens.css` (`@theme static` para claro y
  `.dark` para oscuro) según el encabezado de TASK-009.
- `resources/css/app.css` importa los tokens y define utilidades visuales adicionales.
- Existe un modo oscuro basado en la clase `.dark`.
- Hay cambios locales ajenos sin commitear:
  - `resources/views/habitats/partials/modal-formacion.blade.php`
  - `resources/views/partials/leyenda-ivs-evs.blade.php`
  - Carpetas `.opencode/work/TASK-049/` y `.opencode/work/TASK-050/`

## Interpretación de trabajo (pendiente de análisis)

1. Inventariar todos los puntos donde se define o consume color (tokens, utilidades,
   clases Tailwind hardcodeadas, JS, gráficos, correos, PDFs si aplica).
2. Definir el mapeo completo de la paleta Stone & ink (6 colores base) sobre los tokens
   semánticos existentes, incluidas las variantes derivadas (`hover`, `soft`, `ink`,
   `subtle`, `raised`, `sunken`, estados y foco).
3. Definir el mapeo completo de Lavender Grey — Dark sobre los mismos tokens.
4. Detectar clases de color hardcodeadas que impedirían que el cambio de tema se vea
   de forma consistente y proponer su sustitución por tokens.
5. Verificar contraste y accesibilidad de las combinaciones texto/fondo resultantes.

## Restricciones

- No implementar antes de la aprobación explícita del plan.
- Solo se modifica el tema visual; no se cambia lógica de negocio ni backend.
- No crear, modificar ni ejecutar tests. `tests/` queda fuera de alcance.
- No tocar los cambios locales de TASK-049/TASK-050 ni las vistas ya modificadas sin
  autorización.
- No leer `.env` ni secretos.
- No añadir dependencias.
- No ejecutar `git push` ni operaciones destructivas.
- Respetar `.ai/rules` del proyecto.
