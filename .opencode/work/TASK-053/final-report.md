# TASK-053 — Informe final

## Resumen

Se implementó la fase 1 de sprites de entrenador:

- 18 gimnasios con líder canónico y nombre propio, y 3 clases genéricas por gimnasio.
- 56 hábitats con una clase fija coherente con su pool y 2 variantes por índice.
- Panel de ruta y botón lateral con la clase base del hábitat.
- 74 PNG curados a 80 px, manifiesto, componente y atribución documentada.

## Ajuste v2 (2026-11-17)

- Instrucción literal: `Ponlos a 80x80, lo permiten tanto en gym como en rutas`.
- Gimnasios, encabezado de ruta y tarjetas de entrenador a 80×80; botón lateral
  «Entrenadores» a 40×40.
- El contenedor del sprite de gimnasio deja de recortar con `rounded-full`;
  usa `rounded-card` y muestra el PNG completo.
- «Entrenador N» con `truncate` para no partirse a 640 px.
- Manifiesto `tamano_render_px = 80`; componente con default y fallback 80.
- Revisión v2: **APPROVED**; queda verificación visual a 1024 px.

## Estado final

**COMPLETED** — revisión independiente **APPROVED**, sin BLOCKER/HIGH/MEDIUM.
La verificación visual en navegador queda pendiente de la comprobación del
desarrollador.

## Cambios

### Dominio (commit local `ffa6cc7`)

| Archivo | Cambio |
|---|---|
| `src/Gimnasios/Domain/CatalogoLideres.php` | Nuevo catálogo de 18 líderes + fallback `Líder` |
| `src/Gimnasios/Domain/Gimnasio.php` | `nombreLider` obligatorio; `nombreEtapa(4)` |
| `src/Gimnasios/Domain/CatalogoGimnasios.php` | 18 × `nombreLider` |
| `src/Gimnasios/Infra/EloquentGymCatalogoRepository.php` | `nombreLider` desde el slug de la BD |

`EtapaGimnasio`, `DetalleGimnasio`, `GimnasioResumen` y las colecciones no cambian de
forma. El contrato JSON sigue siendo `{etapa, nombre}`; solo cambia el valor de
`nombre` en la etapa 4.

### Presentación

| Archivo | Cambio |
|---|---|
| `resources/data/entrenadores_sprites.json` | 18 gimnasios, 56 hábitats, 74 clases y metadatos |
| `public/images/trainers/*.png` | 74 PNG de 80×80 (~57 KB) |
| `app/Support/UI/SpritesEntrenador.php` | Manifiesto cacheado y `url()` con fallback |
| `resources/views/components/trainer-sprite.blade.php` | Componente 80 px |
| `app/Http/Controllers/GimnasiosViewController.php` | Pasa `sprites` |
| `app/Http/Controllers/HabitatsController.php` | Pasa `sprites` |
| `resources/views/habitats/show.blade.php` | `sprites` + helpers |
| `resources/views/habitats/partials/columna-derecha.blade.php` | Sprites de entrenador y ruta |
| `resources/views/habitats/partials/columna-izquierda.blade.php` | Sprite en «Entrenadores» |
| `resources/views/gimnasios/show.blade.php` | Sprites de las 4 etapas y nombre del líder |

Se conservan la rejilla de 4 columnas, el líder a la derecha, el modal `max-w-6xl` y
la leyenda colapsable de TASK-050.

### Documentación

- `THIRD_PARTY_NOTICES.md`: sección «Sprites de entrenador» con repo, commit,
  Showdown, ausencia de LICENSE y uso privado no comercial.
- `docs/gimnasios.md`, `src/Gimnasios/context.md`, `src/Gimnasios/README.md`:
  etapa 4 = nombre canónico; tabla de líderes y resolución del sprite.

## Decisiones

1. **Líderes:** Kanto + Johto cubren 16 tipos; Piers (Siniestro) y Valerie (Hada)
   cubren los 2 restantes. Son líderes de gimnasio reales, no Alto Mando.
2. **Etapas 1-3:** tres clases genéricas por gimnasio, coherentes con el tipo.
3. **Hábitats:** una clase base + 2 variantes fijas por índice, resueltas de forma
   determinista; el hábitat 49 usa `veteran`.
4. **Tamaño:** 80 px CSS, `image-rendering: pixelated`; render 1:1 del PNG de 80×80.
5. **Sprite del líder:** presentación, no dominio; el nombre sí es dominio.
6. **Licencia:** proyecto privado, arte de fans/Showdown con atribución; riesgo
   aceptado y documentado.

## Verificación

- `php -l` de 7 PHP: OK.
- `vendor/bin/pint --dirty --format agent`: pass.
- `php artisan view:cache`: OK; `view:clear`: OK.
- `npm run build`: OK (`app-tpYuJOLa.css`).
- `git diff --check`: limpio.
- Manifiesto: 18 gimnasios, 56 hábitats, 74 clases; 74 PNG referenciados; 0 ausencias;
  0 huérfanos.
- Assets: 74 PNG de 80×80, 58 136 B; MD5 de muestra idéntico al clon.
- Runtime: `nombreEtapa(4)` devuelve los 18 nombres canónicos; el shape JSON no
  cambia.
- Sin tests.

## Revisión

`reviewer`: **APPROVED**. Hallazgos menores, ninguno bloqueante:

- `aria-hidden` dinámico en el sprite de etapa.
- Fallback `onerror` duplicado en 4 vistas.
- `x-trainer-sprite` sin consumidor (preparado para vistas servidor).
- `aria-hidden="false"` redundante en etapa 4.

## Riesgos residuales

- **Verificación visual pendiente:** 80 px en 1×/2×, teclado, móvil y los 18
  gimnasios/56 hábitats.
- **Deuda menor:** componente sin consumidor y fallback duplicado.
- **Licencia:** arte sin LICENSE en el repo de origen; uso privado y atribución
  documentada. No es asesoría legal.
- **Layout a 1024 px:** la fila sprite de 80 px + botón «¡Combatir!» queda ajustada en la tarjeta de gimnasio; verificar visualmente.
- **Mezcla regional:** Brock cae en el gimnasio de Roca (Nv 36), no en el primero;
  es consecuencia del orden por tipo/dificultad.
- **Commit local:** `ffa6cc7` no se ha publicado (`git push` no ejecutado).

## Próximos pasos

1. Abrir `/gimnasios/bug` y otro gimnasio: comprobar 4 sprites, nombre del líder y
   layout.
2. Abrir un hábitat de agua, uno de bosque y el 49: comprobar base/variantes.
3. Revisar el panel de ruta y el botón «Entrenadores».
4. Probar el fallback renombrando temporalmente un PNG.
5. Si se quiere, unificar el componente `x-trainer-sprite` y el fallback en una
   corrección menor.
