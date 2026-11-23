# TASK-071 — Análisis

- **Fecha:** 2026-11-23
- **Estado:** cerrado para diseño.
- **Base:** árbol de trabajo actual (`master` con cambios locales sin commitear).

## 1. Estado actual relevante

### 1.1 Torres (a retirar)

El módulo `src/Torres` y sus assets son trabajo reciente **no trackeado** (`git status`
los muestra como `??`), igual que sus dos migraciones y el seeder. Está integrado en:

- `routes/web.php` (rutas de vista + `require routes/torres.php`).
- `routes/torres.php` (API JSON).
- `app/Http/Controllers/TorresViewController.php` y `resources/views/torres/`.
- `app/Models/TorreEvento.php`, `app/Models/TorreProgreso.php`.
- `database/seeders/TorreEventoSeeder.php` y `CatalogoSeeder`.
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php` (rama `'torre'`).
- `resources/views/layouts/app.blade.php` (entrada «Torres»).
- Comentarios en `app/Models/Habitat.php` y `config/habitats.php`.
- Texto de `resources/views/habitats/partials/columna-derecha.blade.php`.
- Migraciones ya ejecutadas en la BD local (`torre_eventos`, `torre_progreso`).

No hay tests ni referencias en PHPStan/baseline.

### 1.2 Hábitats salvajes

- Tablas `provinces` y `habitats`; `habitats.province_id` obligatorio.
- 62 hábitats con imagen `resources/habitats_img/{id}.webp`, servida por
  `/habitats-img/{id}.webp`.
- `HabitatsController@index` usa `ObtenerHabitatsPorProvincia` y filtra la provincia
  «Vacio»; la vista `resources/views/habitats/index.blade.php` pinta pestañas y tarjetas
  3:2 con enlace a `/habitats/{id}`.
- `HabitatsController@show` asume detalle salvaje con niveles 1-3 y spawns. **No sirve
  para un hábitat sin Pokémon ni niveles.**
- `Habitat::sinSpawns()` excluye el hábitat 49 de spawns salvajes; no se reutilizará.

### 1.3 Gimnasios (referencia funcional)

- 18 gimnasios en `gyms`/`gym_stages` + catálogo en código.
- Progreso en `gym_progress.current_stage` (1-5); 5 = medalla completada.
- `IniciarCombateGimnasio` valida etapa actual, nivel mínimo y construye el rival con
  `GeneradorPokemonGimnasio` + `PoliticaIvsEvs::perfilGimnasio`.
- Perfiles IV/EV de gimnasio: gimnasios 1-6 `TopUno`, 7-12 `TopDos`, 13-18 `Completo`;
  el líder sube un tier. Es el criterio que reutiliza el hábitat temporal, con el jefe
  de cada oleada subiendo un tier.
- `RegistrarResultadoGimnasio` persiste solo victoria y usuario autenticado (anti-IDOR).
- `PresentadorResultadoBatalla::procesarGimnasio` otorga recompensas ×10 y añade la
  medalla al modal.
- `GymProgressRepositoryInterface` **no expone un contador de medallas**; la nueva
  funcionalidad necesita contar `gym_progress.current_stage = 5` por usuario.

### 1.4 Batalla

- `CreadorBatallaSesion::crearYGuardar()` centraliza la creación y guarda metadatos
  (`tipo`, `user_id`, `team_id`, `nivel_rival`, …).
- `PresentadorResultadoBatalla::procesar()` enruta por `meta['tipo']`.
- `OtorgarRecompensasEntrenador::otorgar()` acepta multiplicador y dificultad; genera
  caramelos de tipo a partir de la EXP tipada de los rivales derrotados.

### 1.5 Datos locales observados

- 18 gimnasios migrados.
- 1 usuario con progreso de gimnasio y **2 medallas completadas**.
- Todos los `species_id` propuestos para los rosters existen en `pokemon`
  (verificado con consulta de solo lectura).

## 2. Hallazgos que condicionan el diseño

1. **Hábitat virtual, no fila en `habitats`.** Crear filas falsas obligaría a añadir
   flags de tipo, a tocar el detalle salvaje, la gestión admin, los spawns y las
   exploraciones. Un hábitat de facción no tiene Pokémon ni niveles: se modela como
   contenido de catálogo + progreso por usuario.
2. **La provincia «Temporal» también es virtual.** No se persiste en `provinces`; se
   compone en lectura y se oculta si no hay facciones desbloqueadas pendientes.
3. **Una sola tabla nueva:** `habitat_temporal_progreso` (`user_id`, `faccion_slug`,
   `oleada_actual`, `combate_actual`, `completed_at`). El catálogo de facciones,
   oleadas y rosters vive en código, como el catálogo de gimnasios.
4. **No hay que tocar `HabitatsController@show`.** El detalle de facción tiene ruta y
   vista propias (`/habitats/temporales/{faccion}`), con UI tipo gimnasio.
5. **El contador de medallas se aísla en el módulo nuevo** mediante
   `MedallasRepositoryInterface` + implementación Eloquent sobre `gym_progress`. Así no
   se modifica la interfaz de Gimnasios (archivo con cambios locales).
6. **Giovanni ya es líder del gimnasio de Tierra.** Se reutiliza como capitán de Rocket;
   es el mismo personaje y no hay conflicto de datos.
7. **Faltan assets de capitanes.** Solo existe `giovanni.png`. Se definirán slugs
   canónicos (`magno`, `aquiles`, `ciro`, `guzman`) con el fallback actual
   `/images/misc/trainer.webp`; el desarrollador puede añadir los PNG después.
8. **Faltan las 5 imágenes de facción.** Se define la ruta
   `resources/habitats_img/facciones/{slug}.webp` y un fallback
   `/images/misc/raid.webp` para que la UI funcione antes de recibirlas.
9. **Archivos compartidos con cambios locales sin commitear.**
   `PresentadorResultadoBatalla.php`, `HabitatsController.php`, `routes/web.php`,
   `layouts/app.blade.php`, `CatalogoSeeder.php`, `Habitat.php`,
   `config/habitats.php` y `columna-derecha.blade.php` se editarán de forma aditiva,
   sin revertir ni sobrescribir el trabajo actual.
10. **Migraciones de torres no trackeadas pero ejecutadas.** Al borrar los archivos,
    las instalaciones nuevas no las crearán; la BD local conservará las tablas por
    decisión expresa del desarrollador. No se añade migración de limpieza.
11. **Cada oleada tiene 4 combates (3 reclutas + 1 jefe).** El progreso necesita dos
    contadores (`oleada_actual`, `combate_actual`) y el detalle muestra 12 combates por
    facción. Son 60 combates en total.
12. **Nivel exacto del jugador.** Se elimina el escalado `+0/+2/+4`; la dificultad se
    regula con el perfil IV/EV y la composición del equipo. El generador de gimnasios
    acepta el nivel rival sin cambios.
13. **Plantillas de recluta reutilizadas.** Para no inflar el catálogo con 60 rosters
    únicos, cada facción define 3 plantillas de recluta y 3 jefes (2 secundarios + 1
    capitán); las plantillas rotan entre oleadas y lo que escala es el perfil IV/EV.

## 3. Ambigüedades resueltas

| Duda | Decisión |
|---|---|
| ¿3 combates seguidos o 3 oleadas en la partida? | 3 oleadas: inicio, mitad y final. |
| ¿Cuántos combates tiene cada oleada? | 4: 3 reclutas + 1 jefe. |
| ¿Quién es el jefe? | Oleadas 1-2: jefe secundario; oleada 3: capitán principal. |
| ¿Qué medallas desbloquean? | Hitos más juntos desde la 2.ª: 2/3/4/5/6. |
| ¿Cuándo se abren las oleadas 2 y 3? | 9 y 15 medallas respectivamente. |
| ¿Nivel de los rivales? | Exactamente el nivel del jugador, sin bonus. |
| ¿IV/EV? | Oleada 1: TopUno (jefe TopDos); oleada 2: TopDos (jefe Completo); oleada 3: Completo. |
| ¿Recompensa? | EXP ×3 + caramelo de tipo. |
| ¿Se limpian las tablas de torres? | No; solo se elimina el código y las migraciones para instalaciones nuevas. |
| ¿Hábitat con niveles? | No; no hay niveles de hábitat ni requisito de nivel. |

## 4. Riesgos

- **Volumen de contenido:** 5 facciones × 3 oleadas × 4 combates = 60 combates 3v3. Es
  el mayor riesgo de alcance; se mitiga reutilizando plantillas de recluta y 3 jefes por
  facción, con escalado por IV/EV.
- **Balance:** todos los rivales tienen el nivel exacto del jugador; la dificultad viene
  del perfil IV/EV (TopUno → TopDos → Completo) y de la composición. Se validará en
  aceptación manual.
- **UI del detalle:** 12 tarjetas por facción; se resuelve con secciones por oleada en
  acordeón/columnas y un único botón en el combate actual.
- **Assets pendientes:** sin las imágenes, las tarjetas usan fallback; no bloquea.
- **Cambios locales:** cualquier edición de los archivos compartidos debe revisarse con
  `git diff` para no perder trabajo previo.
- **Sin tests:** la verificación es estática + criterios manuales, según la política del
  proyecto.

## 5. Impacto por módulo

| Módulo | Impacto |
|---|---|
| `Torres` | Se elimina por completo del código. |
| `HabitatsTemporales` | Nuevo módulo Domain/App/Infra/Presentation. |
| `Gimnasios` | Solo lectura de `gym_progress` desde el módulo nuevo; sin cambios de contrato. |
| `CombateEntrenadores` / `Battle` | Reutilización de generador, constructor de equipo y recompensas. |
| `Habitats` | `HabitatsController@index` añade la provincia virtual; índice con pestaña nueva. |
| `app` | Rutas, controlador de vista, binding de repositorios, presentador de batalla. |
| BD | Nueva tabla de progreso; sin tocar tablas existentes. |
