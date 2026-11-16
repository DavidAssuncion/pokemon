# TASK-042 — Análisis

- **Estado:** COMPLETED
- **Analista:** `analyst` (sesión `ses_e3a64bba2ffedS5vs4dk7ZZqCL`)
- **Nota:** resultado del analista, sin alterar sus conclusiones. El analista alcanzó el límite de pasos durante la investigación; las afirmaciones negativas se limitan al código revisado.

---

**Nota de alcance:** se alcanzó el límite de pasos del agente durante la investigación. El análisis se basa exclusivamente en los archivos leídos hasta ese punto; no se modificó nada. Las afirmaciones negativas (“no existe”, “no se localizó”) se limitan al código revisado.

# 1. Resumen ejecutivo

La navbar actual es una lista de enlaces de texto construida con `$navItems` en `resources/views/layouts/app.blade.php:44-65`, dentro de un contenedor con `overflow-x-auto` (`:27`). “Reclutamiento” y “Exploraciones” son dos entradas más; no hay iconos, contadores ni paneles.

“Expedición” es un sinónimo de “exploración” en este repositorio. No existe un modelo, clase ni ruta `Expedicion`: el término aparece en comentarios y documentación. Por tanto, “expediciones” debe interpretarse como `exploraciones_activas` (activas y terminadas pendientes de revisar).

Los datos actuales no están pensados para consumo incremental:

- Reclutamiento se renderiza con `Reclutable::with('pokemon')->orderBy('updated_at','desc')->get()` y se inyecta como JSON en la vista (`app/Http/Controllers/PlayerController.php:63-78`). No hay endpoint JSON de listado.
- Exploraciones se renderiza con el presentador completo en `GET /exploraciones`, que además **procesa pendientes como efecto secundario** (`app/Http/Controllers/ExploracionActivaController.php:46-70`). No hay endpoint JSON de listado de activas/terminadas.
- Los POST de reclutamiento devuelven solo `{"success":true}` (`ReclutamientoController.php:65,93,109`). El POST de nueva exploración devuelve un contrato mínimo `{ok,id,inicio,fin,modo,estado}` (`ExploracionActivaController.php:288-295`).
- Los nuevos reclutables no nacen de la UI de reclutamiento: los crean `CapturarPokemonJob` y `PersistirRecompensas` de forma asíncrona (`app/Jobs/CapturarPokemonJob.php:37-41`, `src/Exploraciones/App/PersistirRecompensas.php:125-130`). No hay evento de navegador, SSE, broadcast ni polling; solo el comando programado `exploraciones:procesar` cada cinco minutos (`routes/console.php:13`).
- “Ver historial” no puede mostrar un histórico real: `cerrar` **elimina** la fila (`ExploracionActivaController.php:436-447`), y “terminadas” solo contiene resultados pendientes de revisar.

La conclusión es que la petición requiere, como mínimo: decidir qué significa “historial”, añadir endpoints de lectura o embeber datos, extraer parciales reutilizables y mover el estado Alpine desde scripts de página a un componente/estado compartido. La navbar, tal como está, no puede alojar un panel dentro del contenedor con `overflow-x-auto` sin que se recorte.

---

# 2. Hechos verificados con evidencia

## 2.1 Requisitos y alcance

- **Funcionales**:
  - Sustituir las entradas de texto de Reclutamiento y Exploraciones por iconos FontAwesome.
  - Al pulsar Exploraciones, mostrar un panel de tres columnas: Pokémon, progreso (barra horizontal) y tiempo restante, con flecha de detalle (`request.md`).
  - Enlace “Ver historial” abajo a la derecha para cargar todas y abrir un modal con la misma información que la página.
  - Al pulsar Reclutamiento, abrir directamente un modal.
  - Cachear la colección cliente y reindexarla cuando se añada un recluta o una exploración.
- **No funcionales implícitos**: sin recarga completa, responsive, accesible, sin lógica de negocio en frontend, datos acotados al usuario, rendimiento aceptable en todas las páginas.
- **Criterios de aceptación**: no están definidos en la petición; deben fijarse antes de implementar (ver §6).
- **Ambigüedades**: “historial”, “cachear”, “se añade a la colección”, comportamiento hover/click, contenido exacto del detalle y del modal de reclutamiento.

## 2.2 Navbar

- `resources/views/layouts/app.blade.php:21` — `<header class="bg-surface sticky top-0 z-30 relative">`.
- `:24` — `nav` con `flex items-center justify-center h-14`.
- `:27` — contenedor de enlaces con `overflow-x-auto`; **recorta cualquier popover absoluto que se coloque dentro**.
- `:44-55` — `$navItems` incluye `/reclutamiento` y `/exploraciones` como texto.
- `:57-65` — bucle de enlaces con estado activo por `request()->is(...)`.
- `:68` — bloque de usuario/nivel/tema posicionado `absolute right-0`.
- `:80-82` — badge de nivel.
- `:93-95` — barra de progreso de nivel de 5 px.
- `:98-129` — toast de cosecha con `z-[70]`; único patrón global de estado/feedback existente.
- `:146` — `@stack('scripts')` al final del body.
- `:148` — `@livewireScriptConfig`.

## 2.3 Exploraciones: rutas, controlador y modelo

- `routes/exploraciones.php:8-13` — `GET /exploraciones`, `GET /exploraciones/preview`, `POST /exploraciones`, y acciones `recoger`, `cancelar`, `cerrar`.
- `routes/exploraciones.php:16` — `POST /api/exploraciones/store-individual`.
- `app/Http/Controllers/ExploracionActivaController.php:46-70` — `index()`:
  - `:50` procesa pendientes del usuario (`ProcesarExploracionesPendientes::paraUsuario`) antes de renderizar.
  - `:54` una sola consulta `ExploracionActiva::with('reclutado.pokemon.types','habitat')->get()` **sin límite**.
  - `:56-58` activas = `regreso === null`.
  - `:61-63` terminadas = `regreso !== null` y no canceladas.
  - `:65` presentador completo.
- `:78-149` — `preview()` JSON de riesgo/recompensas; valida propiedad del reclutado con `Rule::exists(...)->where('user_id', Auth::id())` en `:81`.
- `:247-259` — `store()` redirige con flash, no devuelve la entidad.
- `:267-296` — `storeIndividual()` devuelve `{ok,id,inicio,fin,modo,estado}` con 201 en `:288-295`.
- `:376-401` — `cancelar()`.
- `:414-431` — `recoger()`; devuelve 409 si el procesamiento está ocupado (`:416-424`).
- `:436-447` — `cerrar()`; `abort_unless($exploracion->regreso !== null, 404)` y `$exploracion->delete()` en `:440`.
- `app/Models/ExploracionActiva.php` — usa `BelongsToUser`; campos `user_id`, `reclutado_id`, `habitat_id`, `nivel`, `modo`, `duracion_horas`, `hora_limite`, `indefinido`, `eventos`, `inicio_exploracion`, `llegada_destino`, `regreso`; casts de fechas y `eventos` a colección.
- `app/Models/Concerns/BelongsToUser.php` — global scope por `user_id` cuando hay `Auth::id()`; el scope queda inactivo en CLI.

## 2.4 Exploraciones: contrato de presentación

- `src/Exploraciones/Presentation/PresentadorExploraciones.php:140-169` — `toView()` devuelve `{activas, terminadas, nivelJugador}`.
- `:175-233` — cada activa incluye, entre otros:
  - `id`, `equipo`, `reclutado`, `habitat`, `habitat_id`, `nivel`, `min_lvl`, `indefinido`, `duracion_horas`.
  - `inicio`, `inicio_vuelta`, `fin` en ISO 8601.
  - `estado`, `volviendo`, `estado_label`, `estado_clase`.
  - `progreso` calculado en servidor y `restante_min`/`restante_label`.
  - `explorador`, `diario`, `bitacora`, `tiempo_perdido`.
- `:239-335` — cada terminada incluye resultado completo: capturados, caramelos, items, EXP, derrotados, pérdidas, diario, etc.
- `:608-635` — cálculo de fin y etiqueta de tiempo restante.
- `src/Exploraciones/Presentation/ExploradorVista.php:70-93` — `explorador` incluye `id`, `nombre`, `pokemon_id`, `icono` (`/images/iconos_webp/{id}.webp`), nivel, EXP, shiny, rol, tipos, HP, barreras, ánimo y progreso de nivel.
- `resources/views/exploraciones/index.blade.php:71-92` — la barra de progreso y el texto restante se renderizan con datos de servidor.
- `:425-494` — JS de página: `postAction()` hace POST y **`location.reload()`** en `:475`; no reindexa estado local.

## 2.5 Reclutamiento

- `routes/player.php:15` — `GET /reclutamiento`.
- `routes/player.php:23-25` — `POST /reclutamiento/recruit`, `/discard`, `/discard-all`.
- `app/Http/Controllers/PlayerController.php:63-78` — consulta `Reclutable::with('pokemon')`, ordena por `updated_at desc` y mapea a `{id, pokemon_id, nombre, cantidad}`.
- `app/Models/Reclutable.php` — usa `BelongsToUser`; campos `user_id`, `pokemon_id`, `cantidad`; relación `pokemon`.
- `database/migrations/2026_08_29_000004_add_user_id_to_reclutables_table.php:26-32` — `unique(user_id, pokemon_id)` e índice de `user_id`.
- `app/Http/Controllers/ReclutamientoController.php:27-67` — `recruit()`:
  - valida `reclutable_id` con `exists`;
  - `findOrFail` bajo global scope (comentario de propiedad en `:34-36`);
  - crea el reclutado y decrementa/elimina el reclutable (`:59-63`);
  - responde `{"success":true}` (`:65`).
- `:69-95` — `discard()` con `lockForUpdate()` y respuesta `{"success":true}` (`:93`).
- `:97-111` — `discardAll()` con `{"success":true,"candies":...}` (`:109`).
- `resources/views/reclutamiento/index.blade.php:6` — `x-data="reclutamientoApp()"`.
- `:198` — `items: @json($reclutables ?? [])`.
- `:208-225` — `recruit()` actualiza el estado local de forma optimista y hace `fetch` sin comprobar `response.ok`; solo `.catch`.
- `:231-243` — `discardAll()` vacía el estado antes de la respuesta.
- `:257-284` — `confirmDiscard()` sí comprueba `response.ok` y actualiza estado.
- `:78-110` y `:112-191` — patrón de modal existente: `fixed inset-0 z-50`, `role="dialog"`, `aria-modal`, cierre con Escape y `scrim`.

## 2.6 Creación asíncrona de reclutables

- `app/Jobs/CapturarPokemonJob.php:37-41` — `Reclutable::firstOrCreate([...])->increment('cantidad')`.
- `src/Exploraciones/App/PersistirRecompensas.php:116-130` — mismo patrón al repartir capturas de una exploración.
- `routes/console.php:13` — `Schedule::command('exploraciones:procesar')->everyFiveMinutes()`.
- `app/Console/Commands/ProcesarExploraciones.php` — procesa todos los usuarios.
- Consecuencia verificada: un reclutable nuevo puede aparecer **sin ninguna petición del navegador**. La colección cliente no puede enterarse solo por las respuestas de la UI de reclutamiento.

## 2.7 Frontend, componentes y estilos

- `resources/js/app.js:1-4` — importa `bootstrap` y Chart; no registra stores Alpine.
- `resources/js/bootstrap.js:1-13` — Alpine procede del bundle de Livewire; `Livewire.start()`.
- `package.json` — Alpine `^3.16.3`, Bootstrap `^5.3.8`, FontAwesome `^6.7.2`, Popper, Tailwind/Vite.
- `resources/css/app.css:4-5` — importa FontAwesome solid.
- `resources/css/components.css:74-75` — clase `.scrim`.
- Componentes disponibles: `sprite`, `tipo-badge`, `ui.alert`, `ui.button`, `ui.card`, `ui.input`; **no hay componente modal, dropdown, offcanvas ni tooltip reutilizable**.
- `resources/views/layouts/app.blade.php:12` — `[x-cloak]`.
- `resources/views/layouts/app.blade.php:20` — el layout ya contiene un `x-data` grande y un `fetch` inline para la cosecha; es el único precedente de lógica global en navbar.

## 2.8 Documentación y reglas

- `.ai/rules/frontend-minimal-logic.md` — el backend es dueño de la lógica y del orden; el frontend solo presenta.
- `.ai/rules/arrays-dto-collections.md` — prohíbe `array` como contrato entre capas; usar DTO/colección tipada. Los arrays de presentación son tolerados en la frontera de vista.
- `.ai/rules/ddd-modules.md` — `Domain` puro, `App`/`Infra` con Laravel, español en `src/`.
- `docs/ddd.md:669-679` — frontend Livewire 3 + Alpine + Tailwind 4; la migración es strangler.
- `docs/conventions.md:160-180` — Blade con `@extends`/`@include`; patrón de dropdowns Alpine con listener global y limpieza en `destroy()`.

---

# 3. Respuestas a las preguntas

## Pregunta 1 — ¿Qué representa “expediciones”?

**Es un sinónimo de exploraciones.** No existe un concepto distinto, ni modelo `Expedicion`, ni ruta `/expediciones`. El término se usa en comentarios (`database/migrations/2026_11_04_000001_add_modo_to_exploraciones_activas.php:14-15`), en la documentación de TASK-011 y en la petición del desarrollador.

En la práctica hay dos subconjuntos dentro de `exploraciones_activas`:

- **Activas**: `regreso === null` (`ExploracionActivaController.php:56-58`).
- **Terminadas**: `regreso !== null` y no canceladas (`:61-63`). La vista las titula “Resultados por revisar”.

No hay histórico persistente: al cerrar resultados se borra la fila (`:440`). Por tanto “ver historial para cargar todas” solo puede significar, con el modelo actual, “cargar todas las activas y terminadas pendientes”, no un archivo histórico.

## Pregunta 2 — ¿Qué datos y endpoints existen?

### (a) Reclutamiento

| Elemento | Contrato |
|---|---|
| `GET /reclutamiento` | HTML completo; inyecta `reclutables` como JSON |
| Item de `$reclutables` | `id`, `pokemon_id`, `nombre`, `cantidad` (`PlayerController.php:68-73`) |
| Imagen | No viene en el payload; se deriva de `pokemon_id` como `/images/iconos_webp/{id}.webp` (vista `:37`) |
| `POST /reclutamiento/recruit` | Body `{reclutable_id}`; respuesta `{success:true}` |
| `POST /reclutamiento/discard` | Body `{reclutable_id, cantidad}`; respuesta `{success:true}` |
| `POST /reclutamiento/discard-all` | Sin body; respuesta `{success:true, candies}` |
| Orden | `updated_at desc` |

No hay endpoint JSON de listado de reclutables. No hay paginación ni límite. El `unique(user_id,pokemon_id)` garantiza una fila por especie y usuario, con `cantidad` acumulada.

### (b) Exploraciones

| Elemento | Contrato |
|---|---|
| `GET /exploraciones` | HTML completo; ejecuta procesamiento y presentador |
| Activa presentada | `id`, `equipo`, `reclutado`, `habitat`, `habitat_id`, `nivel`, `min_lvl`, `indefinido`, `duracion_horas`, `inicio`, `inicio_vuelta`, `fin`, `estado`, `volviendo`, `estado_label`, `estado_clase`, `progreso`, `fase`, `restante_min`, `restante_label`, `explorador`, `diario`, `tiempo_perdido`, `bitacora` (`PresentadorExploraciones.php:204-232`) |
| Terminada presentada | Todo lo anterior más resultado completo: capturados, caramelos familia/EV/tipo, items, EXP, derrotados, pérdidas, hitos, spans de columnas (`:301-334`) |
| `GET /exploraciones/preview` | JSON de riesgo, capacidades, nivel, peligro, recompensas esperadas; valida propiedad del reclutado |
| `POST /api/exploraciones/store-individual` | 201 con `{ok,id,inicio,fin,modo,estado}`; **no incluye** nombre, hábitat, nivel, progreso ni explorador |
| `POST /exploraciones` | Redirección con flash; no devuelve entidad |
| `POST /exploraciones/{id}/recoger` | `{ok:true}` o 409 si está ocupado |
| `POST /exploraciones/{id}/cancelar` | `{ok:true}` |
| `POST /exploraciones/{id}/cerrar` | `{success:true}`; borra |

No hay endpoint de listado JSON de activas/terminadas. La única forma actual de obtener la colección es renderizar la página completa.

## Pregunta 3 — ¿Cómo se crean hoy y qué permitiría reindexar?

### Nueva exploración

Se crea desde el modal de hábitat con `POST /api/exploraciones/store-individual` (`resources/views/habitats/show.blade.php:1327`). El flujo actual, tras éxito, hace `location.reload()` (`:1338`). La respuesta contiene `id`, `inicio`, `fin`, `modo` y `estado`, pero no los datos presentados que necesita la tarjeta.

Para añadir la nueva exploración a una colección cliente sin recargar haría falta una de estas dos cosas:

1. Ampliar el contrato de respuesta con el ítem presentado completo; o
2. Hacer un `GET` de revalidación tras el POST.

Hoy no existe ni lo uno ni lo otro.

### Nuevo recluta

No se crea desde la página de reclutamiento. La página solo consume y elimina candidatos. Los reclutables nuevos aparecen por:

- `CapturarPokemonJob` (`app/Jobs/CapturarPokemonJob.php:37-41`), encolado desde `Src\Reclutamiento\App\ServicioCaptura`;
- `PersistirRecompensas` al finalizar una exploración (`src/Exploraciones/App/PersistirRecompensas.php:125-130`).

Ninguno de esos procesos emite un evento al navegador. No hay SSE, WebSocket, broadcast, Livewire con `wire:poll` ni `setInterval` para estas colecciones. El único evento global existente es `inventario:actualizado`, emitido por la cosecha (`layouts/app.blade.php:20`) y escuchado en hábitats/forja.

**Conclusión:** “cuando se añada un posible recluta se añade a la colección” no puede implementarse solo con la respuesta de los POST de reclutamiento. Requiere revalidación periódica, al abrir el modal, al recuperar foco o un mecanismo push. Ninguno existe hoy.

## Pregunta 4 — ¿Hay caché reutilizable? ¿Invalidación y riesgos?

**No se localizó caché de servidor para estas colecciones.** No hay `Cache::remember`, `Cache::put` ni `cache()` en los controladores/vistas revisados. El único uso relacionado es `Cache::lock` en `ProcesarExploracionesPendientes::despacharConLock()` para evitar procesamientos concurrentes.

**Cliente:** no hay `Alpine.store`, ni colección cacheada, ni `localStorage` para estos datos. `localStorage` solo se usa para el tema (`layouts/app.blade.php:14,83-84`).

**Invalidación natural:**

- Tras `POST /reclutamiento/recruit`, `/discard`, `/discard-all`.
- Tras `POST /api/exploraciones/store-individual`.
- Tras `POST /exploraciones/{id}/recoger`, `/cancelar`, `/cerrar`.
- Al abrir el panel/modal, al recuperar foco (`focus`/`visibilitychange`) y, si se acepta stale, por TTL.
- Tras `exploraciones:procesar` cada cinco minutos, que puede cambiar activas → terminadas y crear reclutables.

**Riesgos:**

- **Multi-pestaña y multi-dispositivo:** una caché en `localStorage`/`sessionStorage` no se invalida entre pestañas sin `storage` events.
- **Proceso programado:** puede mutar datos sin que el cliente lo sepa.
- **Caché de servidor por usuario:** invalidar desde el comando programado exige conocer los usuarios afectados; el procesador actual no expone esa lista.
- **GET con efectos secundarios:** reutilizar `index()` en un endpoint de navbar dispararía procesamiento en cada carga, algo que TASK-003 ya señaló como problema de seguridad.

## Pregunta 5 — ¿Qué se puede reutilizar como modal/panel?

**Reutilizable directamente:**

- `exploraciones/_explorador.blade.php` — ficha del explorador.
- `exploraciones/_diario.blade.php` y `_evento.blade.php` — bitácora.
- `exploraciones/_caramelo.blade.php` y `_perdidas.blade.php` — recompensas.
- `components/sprite.blade.php` — iconos Pokémon.
- `components/tipo-badge.blade.php` — tipos.

**No reutilizable sin extraer:**

- La tarjeta de activa está inline en `exploraciones/index.blade.php:36-148`.
- La tarjeta de terminada está inline en `:173-415`; es grande y mezcla resumen, derrotados, capturados, items, caramelos, pérdidas y diario.
- La cuadrícula de reclutamiento y sus dos modales están inline en `reclutamiento/index.blade.php:28-191`.

**Bloqueos de estado:**

- `exploracionesPage()` y `reclutamientoApp()` se declaran en `@push('scripts')` de cada página (`exploraciones/index.blade.php:425-494`, `reclutamiento/index.blade.php:194-288`). Si el modal vive en el layout, esas funciones no estarán definidas en el resto de páginas.
- El estado `x-data` de la página no es accesible desde el layout. Montar el modal en el layout exige un `x-data` propio o un store global.
- `$reclutables` solo se pasa a `reclutamiento.index`, no al layout.
- El modal de historial necesitaría el mismo contrato que la página; hoy no hay endpoint que lo entregue.

**Recomendación de análisis:** extraer parciales de tarjeta activa, tarjeta terminada, cuadrícula de reclutamiento y modales de descarte; mover las funciones JS compartidas a un módulo/`Alpine.data` global o a un archivo incluido por el layout. No se decide aquí la forma final.

## Pregunta 6 — ¿Cómo encaja el panel de 3 columnas y el modal?

**Problemas concretos de la navbar:**

1. **Recorte por overflow:** `layouts/app.blade.php:27` usa `overflow-x-auto`. Un panel `absolute` dentro de ese contenedor se recorta. Debe colocarse fuera del contenedor con scroll, o usar `fixed`/teleport.
2. **Posicionamiento:** el bloque derecho es `absolute right-0` (`:68`). El panel debe anclarse a la derecha o al icono, no al centro, para no tapar usuario/nivel/tema.
3. **Sticky y z-index:** el header es `sticky top-0 z-30 relative` (`:21`). Los modales actuales usan `z-50`; el toast usa `z-[70]`. Un panel debe quedar por encima del contenido pero por debajo del modal; hay que definir la escala.
4. **Responsive:** en móvil la barra ya depende de scroll horizontal. Un panel de tres columnas necesita un ancho mínimo y probablemente un layout alternativo (lista apilada o modal a pantalla completa).
5. **Accesibilidad:** el patrón existente de modal en reclutamiento (`:78-110`) usa `role="dialog"`, `aria-modal`, `aria-labelledby`, cierre con Escape y `scrim`. No hay focus trap ni retorno de foco. Un panel tipo popover necesita `aria-expanded`, `aria-controls`, rol adecuado y cierre por click fuera.
6. **Componentes:** no hay modal/dropdown/offcanvas reutilizable. Bootstrap está instalado, pero sus modales solo se usan en el combate Livewire (`livewire/partials/turn-bar.blade.php:27-28,53-54`). No hay un patrón Bootstrap consolidado en la navbar.

## Pregunta 7 — Seguridad y propiedad de datos

- Todas las rutas relevantes están dentro de `Route::middleware('auth')` (`routes/web.php:18-49`); las de exploraciones y reclutamiento se incluyen ahí (`:39-40`).
- `ExploracionActiva`, `Reclutable` y `Reclutado` usan `BelongsToUser`, que añade un global scope por `user_id` cuando hay sesión (`app/Models/Concerns/BelongsToUser.php`). En CLI el scope se desactiva deliberadamente.
- `PlayerController::reclutamiento()` no filtra explícitamente por usuario porque el global scope lo hace.
- `ReclutamientoController::recruit/discard/discardAll` validan con `exists` sin usuario, pero el `findOrFail` posterior queda acotado por el global scope; el comentario de `:34-36` lo documenta.
- `preview()` sí filtra explícitamente por `user_id` en la regla `exists` (`:81`).
- `cancelar/recoger/cerrar` reciben `{exploracion}` por implicit binding; se espera que el global scope impida acceder a filas de otro usuario. **No se ha verificado con ejecución**, solo por lectura del trait y del controlador.
- CSRF: el layout expone `meta[name=csrf-token]` (`:6`) y los fetch lo envían (`exploraciones/index.blade.php:463`, `reclutamiento/index.blade.php:220`).
- No se han encontrado Policies específicas para estos modelos.
- **Riesgo de seguridad/arquitectura:** `GET /exploraciones` ejecuta procesamiento. Un endpoint de lectura para la navbar no debería repetir ese efecto secundario sin decisión explícita. Si se crea un endpoint JSON, debe filtrar por usuario de forma explícita o confiar en el global scope, y no aceptar ids de otras cuentas.

## Pregunta 8 — Impacto de endpoints JSON y colección cliente

**Impacto positivo:**

- La navbar dejaría de depender de páginas completas y de presentadores pesados.
- Permite cachear y reindexar sin recargar.
- Permite separar el contrato de navbar del contrato de página.

**Impacto negativo/riesgos:**

- Añade peticiones por página o por apertura de panel.
- Duplica contratos si no se extraen DTOs/parciales.
- La colección cliente puede quedar desactualizada por el proceso programado.
- Si se implementa caché de servidor, la invalidación es más compleja que el propio endpoint.
- Los presentadores actuales devuelven arrays grandes; para navbar conviene un DTO compacto, no reutilizar `toView()` completo.
- El frontend no debe calcular progreso/tiempo como regla de negocio; el backend ya entrega `progreso` y `restante_label`. Si se quiere cuenta atrás fluida, debe decidirse si es formato de presentación permitido o si se refresca por polling.

---

# 4. Inventario de endpoints y estructuras

| Método | Ruta | Controlador | Respuesta | Autenticación | Observaciones |
|---|---|---|---|---|---|
| GET | `/reclutamiento` | `PlayerController@reclutamiento` | HTML | auth | Inyecta `reclutables` |
| POST | `/reclutamiento/recruit` | `ReclutamientoController@recruit` | `{success:true}` | auth | No devuelve el reclutado creado |
| POST | `/reclutamiento/discard` | `ReclutamientoController@discard` | `{success:true}` | auth | Lock de fila |
| POST | `/reclutamiento/discard-all` | `ReclutamientoController@discardAll` | `{success:true,candies}` | auth | Borra todos |
| GET | `/exploraciones` | `ExploracionActivaController@index` | HTML | auth | Procesa pendientes; sin límite |
| GET | `/exploraciones/preview` | `@preview` | JSON | auth | Riesgo/recompensas |
| POST | `/exploraciones` | `@store` | Redirect | auth | Crea exploración |
| POST | `/api/exploraciones/store-individual` | `@storeIndividual` | `{ok,id,inicio,fin,modo,estado}` 201 | auth | No devuelve tarjeta |
| POST | `/exploraciones/{id}/recoger` | `@recoger` | `{ok:true}` / 409 | auth | Puede tardar |
| POST | `/exploraciones/{id}/cancelar` | `@cancelar` | `{ok:true}` | auth | Marca regreso |
| POST | `/exploraciones/{id}/cerrar` | `@cerrar` | `{success:true}` | auth | **Borra la fila** |

**Estructuras clave:**

- Reclutable: `{id:int, pokemon_id:int, nombre:string, cantidad:int}`.
- Exploración activa presentada: ver §2.4; contiene todo lo necesario para la tarjeta de tres columnas (`explorador.nombre`, `explorador.icono`, `progreso`, `restante_label`, `estado`, `habitat`, `nivel`).
- Exploración terminada presentada: contrato grande con resultado; para el modal de historial se puede reutilizar, pero conviene un subconjunto si solo se muestra resumen.

---

# 5. Opciones de solución y tradeoffs

> No se decide la arquitectura final; se enumeran alternativas para el `architect`.

## Opción A — Embeber datos compactos en el layout + colección Alpine local

- El layout recibe dos colecciones ya presentadas y las inyecta con `@json`.
- La navbar no hace fetch inicial.
- Reindexado local tras POST.

**Ventajas:** sin endpoints nuevos; sin latencia de carga; reutiliza el patrón de `$navItems`/`$reclutables`.
**Inconvenientes:** consulta en **todas** las páginas; aumenta el HTML; no se actualiza tras el proceso programado; si se cachea en cliente, sigue habiendo stale; obliga a que el layout dependa de dos módulos.
**Riesgo:** alto acoplamiento del layout y posible degradación de rendimiento global.

## Opción B — Dos endpoints JSON de solo lectura + colección cliente con TTL/revalidación

- `GET /api/reclutamiento` y `GET /api/exploraciones` (nombres a decidir).
- Respuesta compacta con DTOs de presentación.
- Store Alpine con `items`, `lastLoadedAt`, `loading`, `error`; revalidar al abrir panel/modal, al recuperar foco y tras POST.
- Reindexar por `id` tras reclutar/descartar/crear exploración.

**Ventajas:** navbar ligera; carga bajo demanda; contrato explícito y reutilizable; no obliga a consultar en todas las páginas.
**Inconvenientes:** requiere nuevos endpoints, DTOs, manejo de errores, estados de carga y revalidación; el endpoint de exploraciones debe decidir si procesa pendientes o no.
**Riesgo:** si no se revalida, la colección queda obsoleta por el proceso programado.

## Opción C — Opción B + caché de servidor por usuario (`Cache::remember`) con invalidación

- Clave por `user_id` y tipo; TTL corto.
- Invalidar en recruit/discard/discard-all/store/recoger/cancelar/cerrar.
- Invalidar desde `exploraciones:procesar` para los usuarios procesados.

**Ventajas:** menos consultas y menos carga del presentador.
**Inconvenientes:** la invalidación desde el comando programado no es trivial; el procesador actual no expone una lista de usuarios afectados; riesgo de datos obsoletos entre procesos.
**Riesgo:** alto si no se diseña bien la invalidación. No recomendable sin necesidad medida.

## Opción D — Componente Livewire en la navbar con carga diferida

- Un componente Livewire para reclutamiento y otro para exploraciones, con `wire:init`/`wire:poll` opcional.
- Reutiliza el stack Livewire ya presente y evita escribir un store Alpine complejo.

**Ventajas:** estado y render en servidor; menos JS manual; encaja con la convención “Livewire 3 + Alpine”.
**Inconvenientes:** una petición Livewire por página/intervalo; el layout tendría que montar componentes Livewire; el combate ya usa Livewire, pero no hay precedente en navbar; puede ser más pesado que un endpoint JSON.
**Riesgo:** medio por complejidad y por polling.

## Opción E — Cargar parciales HTML bajo demanda en el modal

- `GET /exploraciones/panel` y `GET /reclutamiento/modal` devuelven HTML parcial.
- El modal inserta el HTML.

**Ventajas:** máxima reutilización de Blade; no hay que serializar un contrato de tarjeta; menos duplicidad visual.
**Inconvenientes:** HTML más pesado; reindexar un elemento nuevo exige renderizar una tarjeta parcial; difícil de cachear; mezcla presentación y fetch.
**Riesgo:** medio; no resuelve por sí solo el reindexado incremental.

## Opción F — “Historial” como archivo real

- Añadir tabla de histórico o cambiar `cerrar` para no borrar (soft delete/estado archivado).
- El modal de historial consulta ese archivo.

**Ventajas:** cumple literalmente “ver historial para cargar todas”.
**Inconvenientes:** migración, modelo, repositorio, retención y posible impacto en el procesador; cambia el comportamiento actual de “Cerrar resultados”.
**Riesgo:** alto de alcance si no se confirma que el desarrollador quiere un histórico real.

**Tradeoff transversal:** la opción B es la que mejor encaja con “no ocupar espacio en el menú” y “cachear la llamada”, pero exige resolver la revalidación. La opción A es la más simple y la más costosa en todas las páginas. La F solo es necesaria si “historial” significa archivo y no “resultados pendientes”.

---

# 6. Riesgos, ambigüedades y preguntas abiertas

## Riesgos

- **[alto] No existe histórico real.** `cerrar` borra la fila (`ExploracionActivaController.php:440`). “Ver historial para cargar todas” no puede cumplirse con el modelo actual si se espera un archivo. Mitigación: decidir si el historial es “activas + terminadas pendientes” o crear archivo/soft delete.
- **[alto] La colección cliente queda obsoleta por procesos asíncronos.** `exploraciones:procesar` corre cada 5 minutos y crea reclutables; no hay push. Mitigación: revalidar al abrir panel/modal, al recuperar foco o con polling explícito; documentar el stale aceptado.
- **[alto] Reutilizar `GET /exploraciones` como fuente de navbar repetiría procesamiento y presentador completo.** Mitigación: endpoint de lectura sin efectos secundarios y DTO compacto.
- **[medio] El panel se recorta si se monta dentro de `overflow-x-auto`.** `layouts/app.blade.php:27`. Mitigación: sacar el panel del contenedor con scroll o usar posicionamiento `fixed`.
- **[medio] Duplicidad de tarjetas y JS.** Las tarjetas están inline; `exploracionesPage()` y `reclutamientoApp()` son funciones de página. Mitigación: extraer parciales y estado compartido.
- **[medio] Estado optimista sin control de errores en reclutamiento.** `recruit()` no comprueba `response.ok` (`reclutamiento/index.blade.php:216-224`); si el servidor falla, la UI y la BD divergen. Mitigación: corregir al implementar la reindexación.
- **[medio] Riesgo de autorización si un endpoint nuevo no aplica el scope.** Aunque el global scope existe, un endpoint que consulte con `withoutUserScope` o joins manuales podría filtrar datos. Mitigación: filtro explícito por `user_id` + revisión de seguridad.
- **[medio] Rendimiento del layout si se embeben datos en todas las páginas.** El presentador de exploraciones es pesado y hace consultas de nombres/cadenas. Mitigación: carga bajo demanda.
- **[bajo] Iconos no definidos.** La petición dice “luego ajustamos el correcto”; hay que fijar `fa-*` y tamaño/aria-label.

## Ambigüedades

1. **“historial”**: ¿archivo histórico real o lista de terminadas pendientes?
2. **“cacheamos”**: ¿caché de servidor, caché HTTP, `sessionStorage`, `localStorage` o simple estado en memoria?
3. **“cuando se añada un posible recluta o exploracion”**: ¿cómo se detecta? No hay evento; el recluta nace por job/proceso programado.
4. **“se añade a la coleccion y reindexamos”**: ¿reindexar significa reordenar por fecha/estado o solo insertar por `id`?
5. **“tool tipo a 3 columnas”**: ¿popover anclado al icono, dropdown, modal o panel lateral?
6. **“ver historial para cargar todas”**: ¿cargar todas las activas y terminadas, o solo terminadas?
7. **“misma informacion que la pagina”**: ¿la tarjeta completa con diario/recompensas o solo el resumen de tres columnas?
8. **Acciones en el modal**: ¿reclutar/descartar/cancelar/recoger/cerrar dentro del modal, o solo lectura con enlace a la página?
9. **Comportamiento**: ¿click, hover o ambos? La petición dice “pulsar”, pero conviene confirmarlo.
10. **Móvil**: ¿panel a pantalla completa, bottom sheet o lista apilada?

## Preguntas bloqueantes para el arquitecto

- ¿Se acepta que “historial” sea solo el conjunto actual de activas + terminadas pendientes, o se autoriza un archivo/soft delete?
- ¿La colección puede quedar stale hasta que el usuario abra el panel, o se exige actualización automática?
- ¿El endpoint de exploraciones debe ejecutar `ProcesarExploracionesPendientes` o limitarse a leer?
- ¿Dónde vive la caché: cliente, servidor o ambas?
- ¿El modal de reclutamiento debe replicar todas las acciones actuales o solo el listado con “Reclutar/Descartar”?
- ¿Se extraen parciales Blade y se mueve el JS a un módulo compartido, o se duplica el markup en el layout?
- ¿Se mantienen las páginas actuales como fuente de verdad y los modales como vista alternativa?

---

# 7. Archivos relevantes y límites de alcance

## Archivos leídos y relevantes

- `resources/views/layouts/app.blade.php` — navbar, header, toast, stacks.
- `resources/views/exploraciones/index.blade.php` — página completa, tarjetas, JS de acciones.
- `resources/views/exploraciones/_explorador.blade.php`, `_diario.blade.php`, `_evento.blade.php` — parciales reutilizables.
- `resources/views/reclutamiento/index.blade.php` — cuadrícula, modales, JS optimista.
- `app/Http/Controllers/ExploracionActivaController.php` — index, preview, store, acciones.
- `app/Http/Controllers/PlayerController.php` — `reclutamiento()`.
- `app/Http/Controllers/ReclutamientoController.php` — recruit/discard/discardAll.
- `app/Models/ExploracionActiva.php`, `app/Models/Reclutable.php`, `app/Models/Reclutado.php`.
- `app/Models/Concerns/BelongsToUser.php`.
- `src/Exploraciones/Presentation/PresentadorExploraciones.php`, `ExploradorVista.php`, `ResultadoExploracionVista.php`.
- `src/Exploraciones/App/ProcesarExploracionesPendientes.php`, `PersistirRecompensas.php`.
- `app/Jobs/CapturarPokemonJob.php`.
- `routes/web.php`, `routes/player.php`, `routes/exploraciones.php`, `routes/console.php`.
- `resources/js/app.js`, `resources/js/bootstrap.js`, `package.json`, `resources/css/app.css`, `resources/css/components.css`.
- `app/Providers/AppServiceProvider.php` — view composer de nivel/progreso.
- `database/migrations/2026_05_27_000004_create_exploraciones_activas_table.php`, `2026_08_26_000004_add_habitat_id_and_level_to_exploraciones_activas.php`, `2026_08_29_000004_add_user_id_to_reclutables_table.php`, `2026_08_29_000006_add_user_id_to_exploraciones_activas_table.php`, `2026_09_02_000003_change_equipo_id_to_reclutado_id_in_exploraciones.php`, `2026_11_04_000001_add_modo_to_exploraciones_activas.php`.
- `.ai/rules/index.md`, `arrays-dto-collections.md`, `ddd-modules.md`, `frontend-minimal-logic.md`, `infra-controllers.md`, `domain-repositories.md`, `model-policy.md`.
- `docs/ddd.md`, `docs/conventions.md`.

## Límites del análisis

- No se modificó ningún archivo.
- No se ejecutaron migraciones, seeders, tests ni comandos destructivos.
- No se instalaron dependencias.
- No se verificó el comportamiento en ejecución de route model binding, global scopes ni del comando programado; las conclusiones sobre ellos son por lectura de código.
- No se inspeccionó el estado exacto de la base de datos ni el contenido de `.env`.
- No se ha decidido la arquitectura final; las opciones de §5 son material para el `architect`.

**Recomendación de análisis:** antes de diseñar, resolver las preguntas bloqueantes de §6, especialmente el significado de “historial”, la política de stale de la colección y si el endpoint de exploraciones puede tener efectos secundarios. Sin esas respuestas, cualquier diseño corre el riesgo de construir un modal que muestra datos incompletos o un “historial” que no existe.
