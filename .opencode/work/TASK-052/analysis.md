# TASK-052 — Análisis

- **Autor:** `analyst` (subagente independiente).
- **Fecha:** 2026-11-17.
- **Alcance:** solo lectura; sin cambios de código ni assets.
- **Nota de coordinación:** el resultado se reproduce íntegro; no se alteran sus conclusiones.

---

# TASK-052 — Viabilidad de sprites de entrenador (`josewp808/pokemon-trainer-sprites`)

> Análisis de solo lectura. No se modificó ningún archivo del proyecto ni del clon externo.

---

## 1. Veredicto ejecutivo por caso de uso

| Caso de uso | Veredicto | Motivo en una línea |
|---|---|---|
| **Gimnasios (líderes y etapas)** | **Viable con cambios** | Los 18 líderes candidatos existen como PNG, pero el dominio no tiene identidad de líder: `Gimnasio::nombreEtapa()` devuelve literalmente `Entrenador 1/2/3` y `Líder`. Hay que decidir el mapeo y añadir un campo de presentación. |
| **Entrenadores de hábitat** | **Viable con cambios (solo presentación)** | `EstadoEntrenador` solo expone `indice` y `desbloqueado`; no hay nombre, clase ni facción. Asignar sprite por `(habitat, nivel, índice)` es 100 % presentación. |
| **Rival de ruta** | **Viable con cambios** | El rival es un equipo generado, sin entidad de entrenador (`RivalRutaFijado` solo tiene `habitatId`, `nivel`, `nivelJugador`, `equipo`). El sprite sería decorativo y requiere decidir una clase por hábitat. |
| **Facciones (Magma/Aqua/Rocket) y equipos rivales** | **No listo** | Los sprites existen (22 de 23 comprobados), pero **no hay ningún concepto de facción, rango, progresión ni combate asociado** en el código. La brecha es de dominio completo, no de imágenes. |

**Conclusión de licencia (avance):** los 572 PNG son, con altísima certeza, un espejo del directorio de sprites de Pokémon Showdown (570/572 byte a byte idénticos). El repo externo **no tiene LICENSE ni README ni atribución**, y Showdown tampoco licencia el arte (su AGPL-3.0 cubre el código cliente, no los sprites de Pokémon, que son IP de Nintendo/Game Freak/Creatures más arte de fans acreditado por artista). **Adoptar requiere decisión explícita del desarrollador sobre redistribución.**

---

## 2. Procedencia y licencia (evidencia)

### 2.1 Comprobación por SHA-256

Método: descarga de los 1 500 PNG listados en `https://play.pokemonshowdown.com/sprites/trainers/` y comparación de hash con los 572 del clon.

| Resultado | Nº | Detalle |
|---|---|---|
| Idénticos, mismo nombre | **537** | p. ej. `brock.png` → `ca422c284f250814332bcc61df1aaf174dd450477676028dec400750638a319d` en ambos lados |
| Idénticos, nombre distinto (alias) | **33** | ver tabla 2.2 |
| Mismo nombre, bytes distintos (arte actualizado upstream) | **3** | `bugcatcher.png`, `byron.png`, `ingo.png` |
| Sin correspondencia en Showdown actual | **2** | `byron.png`, `ingo.png` (revisiones antiguas del mismo archivo) |

**Total: 570/572 byte a byte idénticos; los 2 restantes son versiones antiguas de archivos que existen upstream.** No hay un solo archivo con origen distinto a Showdown.

Muestra explícita verificada una a una (20 nombres): `acetrainer`, `youngster`, `lass`, `hiker`, `swimmer`, `brock`, `misty`, `ltsurge`, `erika`, `koga`, `sabrina`, `blaine`, `giovanni`, `rocketgrunt`, `rocketgruntf`, `magmagrunt`, `yellgrunt`, `teamrocket`, `cynthia`, `leon`, `alder` → todos `IDENTICAL`.

### 2.2 Renombrados (el repo "canoniza" nombres que upstream sufija por generación)

| Archivo local | Equivalente upstream (hash idéntico) |
|---|---|
| `archie.png` | `archie-gen6.png` (`93304f1f25f8c53b187e8c7084fe0ace07ae4ae682d4649823e85ed6ff55d3ff`) |
| `maxie.png` | `maxie-gen6.png` (`7f8fb3e269da8db3cc841795e95c0cd020afb32da7d32c270c6c31db09fa49ca`) |
| `agatha.png` | `agatha-lgpe.png` |
| `lorelei.png` | `lorelei-lgpe.png` |
| `drake.png`, `rocker.png`, `tamer.png`, `painter.png`, `kindler.png`, `engineer.png`, `gamer.png`, `cueball.png`, `crushkin.png`, `oldcouple.png`, `srandjr.png`, `mrfuji.png`, `leaf.png`, `bugcatcher.png` | variantes `-gen3` / `-gen4dp` |
| `bugmaniac.png`, `expert.png`, `hexmaniac.png`, `model.png` | `-gen6` / `-gen8` |
| `arven.png`, `clavell.png`, `florian.png`, `juliana.png`, `nemona.png` | variantes `-s` |
| `clive.png` | `clive-v.png` |
| `phoebe.png` | `phoebe-masters.png` |
| `zirco.png` | `zirco-unite.png` |
| `jessiejames.png` | `jessiejames-gen1.png` |
| `officer.png` | `officer-gen2.png` |
| `channeler.png` | `channeler-lgpe.png` |

### 2.3 Cobertura y huecos del espejo

- Upstream tiene **1 500** sprites; el clon tiene **572** → cubre el **36 %**, sesgado a líderes, clases genéricas y villanos.
- **`aquagrunt.png` existe upstream (HTTP 200, `91ef4376…`) y NO está en el clon**; solo está `aquagruntf.png`. Es el único hueco relevante detectado en las facciones.
- El repo tiene **2 commits, ambos del 2025-12-27** (`1a2024b` "Add trainer sprites", 9 archivos; `ba2d41a` "Añadidos los sprites de entrenadores que faltaban", 563 archivos). Es un volcado, no un proyecto mantenido.

### 2.4 Licencia: qué implica que no haya LICENSE

- El repo externo **no declara licencia**. Por defecto, "sin licencia" = **todos los derechos reservados**; no se concede derecho de copia, modificación ni redistribución.
- Upstream (`smogon/pokemon-showdown-client`) es **AGPL-3.0** (verificado en `LICENSE`), pero su propio README advierte que la licencia cubre el código del cliente. El arte de Pokémon es **IP de Nintendo/Game Freak/Creatures**, y Showdown añade arte de fans con **créditos individuales** en su índice ("by Gnomowladny", "by kyledove", "by Brumirage", "by ZacWeavile"…). **El repo externo elimina esos créditos.**
- El proyecto **ya redistribuye arte de Pokémon** (`public/images` = 16 MB, 1 201 archivos en `iconos_webp`), pero `THIRD_PARTY_NOTICES.md` solo cubre los *datos* de Showdown bajo MIT (servidor), no imágenes. Es decir: ya existe el mismo riesgo de base, sin que este repo lo empeore sustancialmente — pero tampoco lo resuelve.

**Mitigaciones posibles, ordenadas por riesgo:**

| Opción | Riesgo | Comentario |
|---|---|---|
| Uso privado, sin publicación del repo | Bajo | Si el juego no se distribuye ni se publica el código, el riesgo práctico es bajo (sigue siendo infracción técnica). |
| Copiar + atribución explícita (THIRD_PARTY_NOTICES + créditos in-app + enlace a Showdown) | Medio | Mitiga el agravante de ocultar autoría, **no** concede derechos. |
| Hotlink a `play.pokemonshowdown.com` | Medio | Sin copia local, pero dependencia externa, `Referer` filtrado y posibilidad de bloqueo. Cabeceras observadas: `cache-control: max-age=691200`, Cloudflare. |
| Arte propio / placeholders generados | Bajo | Único camino que elimina el problema; coste de producción. |
| Fuente "oficial" (press kit) | Bajo | No cubre entrenadores de clase ni villanos. |

---

## 3. Calidad e inventario técnico

| Métrica | Valor | Evidencia |
|---|---|---|
| Archivos | 572 PNG | `ls | wc -l` |
| Peso total | **451 237 B (~441 KB)** | `du -sb` |
| Peso medio / máx / mín | 789 B / 3 134 B (`ash-hoenn.png`) / 415 B (`officer.png`) | `ls -lS` |
| Dimensiones | **80×80 en los 572** | `file`, IHDR |
| Modo de color | **571 indexados 8-bit (colortype 3)** + **1 RGBA (`ash-hoenn.png`, colortype 6)** | parseo IHDR |
| Transparencia | **571 con `tRNS`**; `ash-hoenn.png` usa canal alfa | parseo de chunks |
| Paleta | 5–111 colores, mediana 20 | parseo `PLTE` |
| Duplicados exactos | **0** | `sha256sum | uniq -d` |
| Nombres con guion | 19 (`ash-hoenn`, `mom-unova2`, `yukito-hideko`, `furisodegirl-black`…) | `ls | grep -- -` |
| Nombres con sufijo numérico | 4 (`aetherfoundation2`, `az-lza`, `colza`, `mom-unova2`) | — |

### Riesgo de escalado (punto crítico)

Los sprites son de **80×80**. Las escalas habituales del proyecto (`size="64"`, `size="96"`, `size="128"`) **no son divisores enteros de 80**:

- 80 → 40 px = factor 0,5 exacto → nítido (y en pantalla 2× son 80 px reales: perfecto).
- 80 → 64 px = factor 0,8 → **re-muestreo no entero: píxeles de tamaño irregular y bordes sucios**.
- 80 → 96/128 px = ampliación 1,2×/1,6× → **borroso** salvo `image-rendering: pixelated`.

**Recomendación técnica:** usar **40 px CSS** (o 80 px 1:1) y, si se necesita 64, aplicar `image-rendering: pixelated` + `-webkit-optimize-contrast`. Con `pixelated` a 64 px en HiDPI el resultado es "retro" aceptable pero no fiel; a 40 px es exacto en 2×.

**Formato:** el proyecto usa WebP en todo `public/images`. Convertir estos PNG indexados a WebP *lossless* es seguro (y probablemente reduce ~20-30 %); WebP con pérdida **destrozaría** el pixel art. No es imprescindible: 451 KB totales son irrelevantes.

---

## 4. Cobertura: gimnasios, rutas, facciones

### 4.1 Gimnasios — el juego NO usa líderes canónicos

Hecho verificado: `CatalogoGimnasios` define 18 gimnasios **por tipo**, con `slug`, `medalla`, `tipo`, `nivelMinimo` y equipos por etapa. **No hay nombre de líder, ni región, ni sprite.**

- `src/Gimnasios/Domain/Gimnasio.php:36-45` — `nombreEtapa()` devuelve `'Entrenador 1'`, `'Entrenador 2'`, `'Entrenador 3'`, `'Líder'`.
- `src/Gimnasios/App/ObtenerGimnasioDetalle.php:38-43` — el DTO `EtapaGimnasio` solo transporta `etapa` y `nombre`.
- `resources/views/gimnasios/show.blade.php:115` — pinta `etapa.nombre`; `:120-128` muestra un **avatar SVG genérico** (silueta), con `:title="'Entrenador: ' + etapa.nombre"`.
- `resources/views/gimnasios/show.blade.php:42` — la cabecera usa la **medalla** (`images/medallas/{tipo_slug}.webp`), no un líder.

**Por tanto el mapeo líder↔gimnasio es una decisión de producto, no un dato existente.** Los 18 gimnasios reales y candidatos canónicos (todos presentes como PNG):

| # | slug | tipo | Nv | Candidato canónico | ¿PNG? |
|---|---|---|---|---|---|
| 1 | `bug` | Bicho | 10 | `bugsy` / `aaron` | ✅ / ✅ |
| 2 | `poison` | Veneno | 15 | `koga` / `janine` / `roxie` | ✅ / ✅ / ✅ |
| 3 | `normal` | Normal | 20 | `norman` / `whitney` / `lenora` | ✅ / ✅ / ✅ |
| 4 | `grass` | Planta | 25 | `erika` / `gardenia` | ✅ / — (no comprobado) |
| 5 | `flying` | Volador | 31 | `falkner` / `winona` / `skyla` | ✅ / ✅ / ✅ |
| 6 | `rock` | Roca | 36 | `brock` / `roxanne` / `roark` | ✅ / ✅ / ✅ |
| 7 | `electric` | Eléctrico | 41 | `ltsurge` / `wattson` / `elesa` / `clemont` | ✅ ×4 |
| 8 | `ice` | Hielo | 46 | `pryce` / `wulfric` / `melony` | ✅ ×3 |
| 9 | `fire` | Fuego | 52 | `blaine` / `flannery` | ✅ / ✅ |
| 10 | `water` | Agua | 57 | `misty` / `wallace` / `crasherwake` / `nessa` | ✅ ×4 |
| 11 | `ground` | Tierra | 62 | `giovanni` / `clay` / `bertha` | ✅ ×3 |
| 12 | `psychic` | Psíquico | 67 | `sabrina` / `olympia` / `tate`+`liza` | ✅ / ✅ / ✅ (sin `tateandliza`) |
| 13 | `dark` | Siniestro | 73 | `karen` / `sidney` / `nanu` / `piers` | ✅ ×4 |
| 14 | `ghost` | Fantasma | 78 | `agatha` / `morty` / `fantina` / `allister` / `shauntal` | ✅ ×5 |
| 15 | `fighting` | Lucha | 83 | `bruno` / `brawly` / `maylene` / `korrina` / `bea` | ✅ ×5 |
| 16 | `fairy` | Hada | 88 | `valerie` / `mina` / `opal` / `bede` | ✅ ×4 |
| 17 | `steel` | Acero | 94 | `jasmine` / `byron` / `molayne` | ✅ ×3 (`byron` = arte antiguo) |
| 18 | `dragon` | Dragón | 100 | `lance` / `clair` / `drayden` / `raihan` | ✅ ×4 |

Nombres exactos confirmados presentes: `brock`, `misty`, `ltsurge`, `erika`, `koga`, `sabrina`, `blaine`, `giovanni` (los 8 de Kanto, ✅). Ausente relevante: `tateandliza` (sí `tate` y `liza` por separado), `grimsley` (grafía: existe como `grimsley`, no `grimbsley` — mi consulta usó la grafía incorrecta).

**Advertencia de coherencia:** el orden del juego es por nivel/tipo (Bicho→Dragón), no por región. Un set "Kanto" (Brock→Giovanni) **no** encaja con el orden del juego: Brock sería el gimnasio 6 y Misty el 10. Cualquier mapeo será una mezcla regional; eso es aceptable porque el juego ya es type-based, pero debe decidirse.

**Alternativa barata y coherente:** etapas 1–3 con **clases genéricas del tipo** (`bugcatcher`, `lass`, `hiker`, `swimmer`, `psychic`, `blackbelt`, `hexmaniac`…) y etapa 4 con el líder. Refuerza la fantasía de gimnasio y evita inventar tres líderes por tipo.

### 4.2 Rutas / hábitats — el rival no es una entidad

- `app/Support/CombateRuta/RivalRutaFijado.php:16-24` — el rival es un **snapshot de equipo** (`habitatId`, `nivel`, `nivelJugador`, `equipo`), en sesión, sin identidad.
- `src/CombateRuta/App/GeneradorEquipoRuta.php` — genera especies por hábitat/nivel; no hay nombre ni clase.
- `src/CombateEntrenadores/App/ObtenerEntrenadoresHabitat.php:26-45` — 3 entrenadores × 3 niveles, identificados solo por `indice`.
- `src/CombateEntrenadores/Domain/DataTransferObjects/EstadoEntrenador.php:17-21` — `indice` + `desbloqueado`.
- La UI pinta `'Entrenador ' + entrenador.indice` (`resources/views/habitats/partials/columna-derecha.blade.php:308`) **sin imagen**.
- Hay **56 hábitats** (`storage/data/habitats.csv`, 57 líneas con cabecera), p. ej. `Bahía Crustáceo`, `Bosque Brumoso`, `Bosque Crecido`, `Bosque Elemental`.

Clases genéricas del repo que encajan por hábitat (todas presentes): `acetrainer`, `acetrainerf`, `youngster`, `lass`, `hiker`, `swimmer`, `swimmerf`, `birdkeeper`, `bugcatcher`, `camper`, `picnicker`, `psychic`, `psychicf`, `blackbelt`, `sailor`, `cyclist`, `beauty`, `biker`, `burglar`, `channeler`, `dancer`, `engineer`, `fisher` ❌ (ausente), `gambler`, `gentleman`, `guitarist`, `hexmaniac`, `juggler`, `kindler`, `painter`, `pokemaniac`, `rocker`, `ruinmaniac`, `scientist`, `schoolkid`, `schoolkidf`, `skier`, `supernerd`, `tuber`, `waiter`, `waitress`, `worker`, `youngcouple`, `aromalady`, `backers`, `backersf`.

**Qué haría falta:** una tabla de presentación `habitat_id → slug de clase` (56 entradas, curada a mano) y un componente. Cero lógica de negocio. Si se quisiera que el rival "tenga clase" de verdad (nombre en el log, título en el modal), habría que añadir el dato al DTO de preview y al payload de inicio — cambio pequeño pero de backend.

### 4.3 Facciones (Magma/Aqua/Rocket) — sin dominio

Comprobación de presencia en el clon:

| Sprite | ¿Presente? |
|---|---|
| `archie`, `maxie`, `giovanni` | ✅ ✅ ✅ |
| `rocketgrunt`, `rocketgruntf`, `teamrocket` | ✅ ✅ ✅ |
| `aquagruntf`, `magmagrunt`, `magmagruntf` | ✅ ✅ ✅ |
| **`aquagrunt`** | ❌ **ausente** (sí upstream) |
| `yellgrunt`, `yellgruntf` | ✅ ✅ |
| `aquasuit`, `magmasuit` | ✅ ✅ |
| `archer`, `ariana`, `proton`, `petrel` (Rocket admins) | ✅ ×4 |
| `shelly`, `matt`, `courtney`, `tabitha` (Aqua/Magma admins) | ✅ ×4 |
| `zinnia` | ✅ |

**Estado del dominio:** búsqueda en `src/`, `app/`, `resources/`, `routes/`, `database/` de `magma|aqua|rocket|faccion|equipo rival` → **0 resultados** fuera de `CombateRuta` (donde "rival" significa "equipo salvaje generado"). No hay tabla, entidad, enum, ruta ni recompensa de facción.

**Brecha real (no es "poner imágenes"):**
1. Entidades: `Faccion`, `RangoFaccion`, `MiembroFaccion`, progresión por usuario.
2. Contenido: rutas/base por facción, encuentros, jefes, equipos por rango.
3. Combate: reutilizable sobre `CombateEntrenadores` (generador + log + recompensas), pero requiere identificar al oponente.
4. Recompensas y economía: ítems/medallas/reputación.
5. UI: mapa/progresión, diario, sprites por rango.

Estimación: **2–4 semanas** de trabajo de dominio + contenido, frente a **horas** en los otros tres casos.

---

## 5. Estado actual del dominio y puntos de inserción UI

### 5.1 Contratos que habría que tocar (si se quiere el sprite en servidor)

| Punto | Archivo | Qué falta |
|---|---|---|
| Catálogo de gimnasios | `src/Gimnasios/Domain/CatalogoGimnasios.php` + `Gimnasio.php:24-31` | campo `sprite`/`lider` |
| DTO de etapa | `src/Gimnasios/Domain/DataTransferObjects/EtapaGimnasio.php:14-17` | `spriteSlug` |
| Frontera JSON | `EtapaGimnasio::toArray()` (`@deprecated`, shape documentado) | nueva clave (cambio de contrato) |
| Listado de entrenadores | `ObtenerEntrenadoresHabitat` + `EstadoEntrenador` | `spriteSlug` o `clase` |
| Rival de ruta | `RivalRutaFijado` | `claseSlug` |

Alternativa **sin tocar backend**: resolver el sprite en Blade/Alpine con un manifiesto estático (`gym.slug + etapa` / `habitat_id` / `nivel+indice`). Cumple el contrato "presentación + catálogo de datos, no lógica de negocio", pero duplica el mapeo si mañana el backend necesita el dato (p. ej. log de combate).

### 5.2 Dónde encajaría visualmente

| Ubicación | Archivo:línea | Estado actual | Encaje |
|---|---|---|---|
| Tarjeta de etapa de gimnasio | `resources/views/gimnasios/show.blade.php:120-128` | círculo 36 px con SVG silueta | **Ideal**: sustituir SVG por `<x-trainer-sprite>` 40 px; el `:title` ya existe |
| Cabecera de gimnasio | `resources/views/gimnasios/show.blade.php:42` | medalla 4xl | Opcional: líder + medalla |
| Modal de formación de gimnasio | `show.blade.php:146-160` | texto "contra Medalla X" | Opcional: retrato del líder |
| Botón lateral "Entrenadores" | `resources/views/habitats/partials/columna-izquierda.blade.php:72` | `trainer.webp` 32 px | Reemplazable por clase genérica |
| Lista de entrenadores de hábitat | `resources/views/habitats/partials/columna-derecha.blade.php:296-315` | solo texto "Entrenador N" | **Ideal**: avatar 40 px por tarjeta |
| Panel de ruta | `columna-derecha.blade.php:216-260` + botón `:333-341` | sin imagen | Avatar de clase + nombre de ruta |
| Combate | `resources/views/livewire/combate.blade.php:162` (sprites Pokémon), `:215` ("Has derrotado al entrenador") | sin sprite de entrenador | Opcional: retrato en cabecera/modal de victoria |

### 5.3 Componentes reutilizables existentes

- `resources/views/components/sprite.blade.php` — **específico de Pokémon**: construye rutas `/images/iconos_webp/...`, tamaños `128|96|64|inline|combat`, fallback a `0.webp`. **No sirve** para entrenadores sin ensuciarlo.
- `resources/views/components/ui/{alert,button,card,input,modal}.blade.php` — sí reutilizables como contenedores.
- `resources/views/components/tipo-badge.blade.php` — patrón de referencia (props + resolución + `<img loading="lazy" decoding="async">`).
- **No existe** `x-trainer-sprite`. Habría que crearlo, siguiendo el patrón de `tipo-badge`.

---

## 6. Integración propuesta (sin implementar)

### 6.1 Ubicación y volumen de assets

- **`public/images/trainers/`** — coherente con `public/images/{medallas,misc,type,items}`; servido estático, cacheable, sin paso por PHP. **Descartar `storage/`**: obliga a ruta controladora, pierde caché de servidor web y complica el CDN.
- **Subconjunto curado, no los 572.** Fase 1: ~30–45 archivos (< 40 KB). Copiar 540 sprites sin uso aumenta superficie de licencia, ruido en el repo y mantenimiento sin beneficio.
- **Nombres:** conservar el slug upstream tal cual (minúsculas, guiones). No renombrar a camelCase. Si se corrige `aquagrunt` (ausente), tomarlo de upstream con su hash, no inventarlo.

### 6.2 Manifiesto / catálogo

Precedente en el proyecto: `resources/data/showdown_movesets.json` (artefacto build-time). Por coherencia:

- `resources/data/entrenadores_sprites.json` — mapa lógico → archivo:
  ```json
  { "gimnasios": { "bug": { "1": "bugcatcher", "2": "lass", "3": "youngster", "4": "bugsy" } },
    "rutas": { "1": "swimmer", "2": "bugcatcher" },
    "genericos": ["acetrainer", "lass"] }
  ```
- Resolutor fino en `app/Support/UI/` (equivalente a `Src\Shared\UI\TipoBadges`), **sin lógica de negocio**: solo `slug → URL` y validación de existencia.
- Si mañana el backend necesita el dato (log, accesibilidad, SEO), se promueve a campo del dominio; el manifiesto sigue siendo la fuente.

### 6.3 Componente

```blade
<x-trainer-sprite slug="brock" size="40" name="Brock" />
```
- `width`/`height` explícitos (evita CLS), `loading="lazy"`, `decoding="async"`, `class="trainer-sprite"` con `image-rendering: pixelated`.
- `alt`: nombre real si se conoce; `alt=""` + `aria-hidden` si es decorativo (caso de etapas genéricas).
- **Fallback** en cascada: `slug.png` → `acetrainer.png` → `/images/misc/trainer.webp` (ya existe, 3,1 KB) → ocultar.
- Tamaños permitidos: **40 y 80** (divisores exactos). Si se fuerza 64, documentar que se ve "pixelado".

### 6.4 Caché

Assets estáticos: `Cache-Control: public, max-age=31536000, immutable` a nivel de servidor web (no hay fingerprinting de Vite para `public/`). Si se quiere invalidación, `?v=YYYYMMDD` en el manifiesto.

---

## 7. Esfuerzo y fases

| Fase | Alcance | Tipo | Esfuerzo |
|---|---|---|---|
| **0 — Preparación** | Decisión de licencia, curación de ~30-45 PNG, manifiesto, `<x-trainer-sprite>`, CSS `pixelated`, fallback | Presentación | **4–6 h** |
| **1a — Gimnasios** | Mapeo 18 gimnasios (etapa 4 = líder; 1-3 = clase del tipo) + sustituir SVG en `show.blade.php:120-128` | Presentación | **2–4 h** |
| **1b — Entrenadores de hábitat** | Sprite determinista por `(habitat, nivel, índice)` en `columna-derecha:296-315` | Presentación | **2–3 h** |
| **1c — Rutas** | Mapa `habitat_id → clase` (56 entradas) + avatar en panel de ruta | Presentación | **3–5 h** |
| **2 — Identidad en backend (opcional)** | `spriteSlug` en `EtapaGimnasio`/`EstadoEntrenador`, contrato JSON, tests de contrato | Backend pequeño | **1 día** |
| **3 — Facciones** | Entidades, progresión, rutas, combates, recompensas, UI | **Dominio nuevo** | **2–4 semanas** |

**Fase 1 completa (solo presentación): ~1–1,5 días.** Nada de esto toca reglas de combate, balance ni persistencia.

---

## 8. Riesgos

| Severidad | Riesgo | Impacto | Mitigación |
|---|---|---|---|
| **Alto** | **Licencia**: arte de IP sin licencia, repo sin LICENSE, créditos de artistas eliminados | Reclamación DMCA si el proyecto se publica; obligación moral de atribuir | Decisión explícita del desarrollador; `THIRD_PARTY_NOTICES` + créditos; o arte propio |
| Medio | **Calidad de escalado**: 80 px a 64 px no entero | Sprites borrosos o con píxeles irregulares | Usar 40/80 px + `image-rendering: pixelated` |
| Medio | **Semántica**: gimnasios sin líder canónico; mapeo arbitrario | Incoherencia narrativa (Brock en gimnasio de Roca nivel 36) | Decisión de producto: mapeo por tipo, no por región; o nombres inventados |
| Medio | **Mantenimiento**: repo externo sin issues, sin versiones, 2 commits, arte actualizado upstream (`bugcatcher`, `byron`, `ingo` ya difieren) | Copia congelada; sin canal de actualización | Copiar subset + registrar commit/hash de origen en el manifiesto |
| Bajo | **Accesibilidad**: sprites decorativos con `alt` incorrecto | Ruido para lectores de pantalla | `alt=""` + `aria-hidden` cuando el nombre ya está en texto (caso actual de gimnasios y entrenadores) |
| Bajo | **Peso**: 451 KB totales; subset < 40 KB | Ninguno | `loading="lazy"`, subset |
| Bajo | **Dependencia externa** (solo si hotlink) | Caída o bloqueo de Showdown | Copiar localmente |
| Bajo | **Colisión de nombres**: `public/images/trainers/` nuevo | Ninguna | Directorio dedicado |
| Bajo | **Regresión**: cambio aditivo en vistas | Bajo | No toca lógica; el fallback preserva el estado actual |
| Bajo | **`aquagrunt` ausente** | Hueco en facciones | Traer de upstream si se aborda fase 3 |

---

## 9. Recomendación y decisiones pendientes

### Recomendación: **adoptar con condiciones**, en dos tiempos

1. **Fase 1 (ahora, ~1 día):** gimnasios + entrenadores de hábitat + rutas, **solo presentación**, con **subset curado de ~30–45 PNG** copiados a `public/images/trainers/`, manifiesto JSON y componente `<x-trainer-sprite>`. Sin tocar dominio ni contratos.
2. **Fase 3 (aplazar):** facciones. Los sprites están (falta `aquagrunt`), pero el valor está en el dominio, no en la imagen; hacerlo antes sería decorar una pantalla que no existe.

**No adoptar** solo si el proyecto va a publicarse como open source o comercialmente: en ese caso, arte propio o placeholders.

### Decisiones que necesito del desarrollador

1. **Distribución del proyecto**: ¿privado/personal, o público? Determina si el riesgo de licencia es teórico o real.
2. **Aceptación de arte de fans/Showdown con atribución** vs. arte propio. Si se acepta: ¿dónde se atribuye (THIRD_PARTY_NOTICES, pantalla de créditos, ambos)?
3. **Gimnasios**: ¿los líderes deben tener **nombre propio** (cambio de dominio) o basta un sprite mudo sobre "Líder" (presentación pura)?
4. **Mapeo de líderes**: ¿canónico por tipo (Bugsy→bug, Koga→poison…) o nombres inventados propios?
5. **Etapas 1–3**: ¿clases genéricas del tipo (recomendado) o tres líderes canónicos por gimnasio?
6. **Rutas**: ¿clase de entrenador fija por hábitat (56 entradas manuales) o un único sprite genérico de ruta?
7. **Tamaño visual**: ¿40 px (nítido exacto) o 64 px (pixelado)? Afecta al CSS del componente.

---

## 10. Archivos y comandos consultados

### Proyecto (`/home/david/pokemon`)

- `AGENTS.md`, `.opencode/work/TASK-052/*.md` (request, plan, execution)
- `src/Gimnasios/Domain/CatalogoGimnasios.php` (18 gimnasios), `Gimnasio.php:36-45`, `DataTransferObjects/{EtapaGimnasio,DetalleGimnasio,GimnasioResumen,EquipoEtapaGimnasio}.php`
- `src/Gimnasios/App/ObtenerGimnasioDetalle.php:38-60`, `Infra/Controllers/GimnasioController.php`
- `src/CombateEntrenadores/App/ObtenerEntrenadoresHabitat.php:26-45`, `Domain/DataTransferObjects/{EstadoEntrenador,EntrenadoresNivel}.php`
- `app/Support/CombateRuta/{RivalRutaFijado,AlmacenRivalRutaFijado}.php`
- `resources/views/gimnasios/show.blade.php:42,115,120-128,146-160`
- `resources/views/habitats/partials/columna-izquierda.blade.php:72`
- `resources/views/habitats/partials/columna-derecha.blade.php:216-260,282-315,333-341`
- `resources/views/livewire/combate.blade.php:162,215`
- `resources/views/components/{sprite,tipo-badge}.blade.php`, `components/ui/*`
- `THIRD_PARTY_NOTICES.md`, `docs/analysis/pokemon-showdown/*`, `storage/data/habitats.csv` (56 hábitats)
- `public/images/` (16 MB, 1 201 iconos WebP; `misc/trainer.webp` 3,1 KB)

### Clon externo (`/tmp/opencode/trainer-sprites/repo`)

- `git log`, `git show --stat 1a2024b ba2d41a`, `git remote -v`
- `trainers/` (572 PNG): `file`, `ls -lS`, `sha256sum`, parseo de chunks PNG (IHDR/PLTE/tRNS) con Python

### Comandos de red (solo lectura)

- `curl` a `https://play.pokemonshowdown.com/sprites/trainers/{nombre}.png` (1 500 descargas) y `…/trainers/` (índice)
- `curl` a `https://api.github.com/repos/smogon/pokemon-showdown-client/git/trees/master?recursive=1`
- `curl` a `https://raw.githubusercontent.com/smogon/pokemon-showdown-client/master/{LICENSE,README.md}`

### Comprobaciones NO ejecutadas (declaradas explícitamente)

- **No** se renderizó la aplicación ni se validó visualmente a 40/64 px: la evaluación de nitidez es teórica (basada en factores de escala y modo de color).
- **No** se abrió `.env` ni ningún secreto (prohibido por el entorno).
- **No** se consultó asesoría legal: la sección de licencia es análisis técnico, no dictamen jurídico.
- **No** se verificó la fecha exacta del snapshot del repo frente a Showdown (solo que 3 archivos difieren y 2 son revisiones antiguas).
- **No** se enumeraron los 1 500 nombres upstream uno a uno para clases genéricas atípicas (`fisher`, `mountaineer`, `cooltrainer` dieron ausencia local; no se comprobó si existen upstream con otro nombre).
- **No** se ejecutaron tests ni se modificó ningún archivo.
