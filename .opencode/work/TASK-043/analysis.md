# TASK-043 — Análisis

- **Estado:** COMPLETADO (dos investigaciones en paralelo)
- **Analistas:**
  - Parte A (funcional): `analyst`, sesión `ses_e3a5bcb7dffe3tFz9hA39lkcs1`.
  - Parte B (técnica): `analyst`, sesión `ses_e3a5bcb7cffe29YHb9Mie7Yh58`.
- **Nota:** se reproducen los informes de los analistas sin alterar sus conclusiones. Ambos agentes alcanzaron el límite de pasos; las afirmaciones negativas se limitan al código revisado. No se modificó ningún archivo de aplicación.

---

## Parte A — Resumen funcional del juego actual

He alcanzado el máximo de pasos permitido para este agente. No haré más llamadas a herramientas. A continuación entrego el informe completo con la evidencia ya recopilada (solo lectura; no modifiqué archivos ni ejecuté migraciones/seeders/tests).

---

# TASK-043 — Resumen funcional de `/home/david/pokemon` (estado actual)

## Resumen ejecutivo

El juego es una aplicación Laravel 12 con módulos DDD donde el jugador encarna una cuenta que colecciona y entrena pokémon. Al entrar (`/` = `/habitats`) ve las provincias y sus hábitats, y desde el detalle de un hábitat el modo por defecto es el **combate de ruta 3v3** contra salvajes; ese es hoy el bucle central: combatir → EXP/caramelos/capturas → reforzar equipo → desbloquear/mejorar sistemas laterales.

Los sistemas satélite ya existen y están implementados: entrenadores diarios (3×3 por hábitat), exploraciones individuales 1v1 con modos, mazmorras por hábitat, 18 gimnasios con medallas, granjas (nidos/refugios/EV/plantaciones), forja de objetos, huevos, reclutamiento/evolución, Pokédex, equipos de exactamente 3, Sendas de tipo e inventario compartido.

La progresión tiene varios ejes **independientes**: nivel de cuenta (EXP global), progreso de gimnasio (por gimnasio), progreso de mazmorra (por hábitat), Pokédex (visto/atrapado), colección (reclutados), IV/EV/CP y Sendas (EXP por tipo). El nivel de cuenta desbloquea gimnasios y, en teoría, niveles 1/2/3 de exploración; **no** desbloquea hábitats: todos aparecen listados y accesibles desde el inicio.

La economía no tiene moneda. Se obtienen EXP, caramelos de familia/EV/tipo, bayas, bonguris, capturas, huevos/shiny y medallas. Los multiplicadores por actividad ya están fijados (ruta ×1,0; entrenador ×2,0; gimnasio ×10,0; mazmorra ×70,0; exploración individual ×0,5).

La cadencia es desigual: entrenadores tienen bloqueo diario por entrenador; ruta no tiene límite; exploraciones no tienen tope de cantidad pero sí 30 min por encuentro; plantaciones 1 cosecha/24 h; derrota en mazmorra 1 h de cooldown; gimnasios y huevos no tienen límite diario (los huevos sí temporizadores reales).

La narrativa es prácticamente inexistente: solo hay nombres de provincias/hábitats, colores de bonguri por provincia, tipos de medalla y textos del diario de exploración. No hay NPCs con nombre, diálogos ni trasfondo. Existe una vista `misiones` commiteada pero **sin rutas ni backend**, y `Tareas_pdte/misiones` está vacío.

Esto deja un hueco claro para misiones: hoy las actividades son repetibles y recompensan, pero no tienen objetivos ni guía; y el desbloqueo de hábitats no tiene ningún mecanismo de dosificación (los `min_lvl_1/2/3` existen pero no se escriben en ningún sitio revisado).

---

## 1. Pitch y bucle de juego

**Qué hace el jugador al entrar.** Tras autenticarse, `/` y `/habitats` muestran las provincias y sus hábitats (`routes/web.php:18-20`; `app/Http/Controllers/HabitatsController.php:40-47`; `resources/views/habitats/index.blade.php`). Si no tiene equipo, pasa por el onboarding de equipo inicial (`app/Http/Controllers/OnboardingController.php:16-31`). La barra de navegación ofrece Pokédex, Hábitats, Equipos, Sendas, Gimnasios, Reclutamiento, Exploraciones y Forja (`resources/views/layouts/app.blade.php:44-55`).

**Objetivo a corto plazo.** Elegir un hábitat y combatir en ruta (modo por defecto) para ganar EXP, caramelos y capturas; con ello mejorar el equipo de 3 y alimentar los sistemas laterales (huevos, granjas, forja).

**Qué decide.** Qué hábitat, qué nivel 1/2/3, qué equipo y formación; si combate en ruta, contra entrenadores, explora (y con qué reclutado, duración y modo), o si invierte en granja/forja/Sendas.

**Qué repite.** Combate de ruta 3v3 (sin límite diario, `src/CombateRuta/App/IniciarCombateRuta.php:20-23`), entrenadores (hasta 9 victorias/día/hábitat), exploraciones y recogidas, y gestión de granja/forja.

**Objetivo a medio plazo.** Subir el nivel de cuenta para abrir gimnasios, completar medallas, avanzar mazmorras, completar familias evolutivas (Pokédex/colección) y subir Sendas de tipo.

---

## 2. Mapa de sistemas

| Sistema | Para qué sirve | Cómo se accede | Qué consume | Qué devuelve |
|---|---|---|---|---|
| **Provincias/hábitats** | Catálogo y punto de entrada a todos los modos | `/habitats` y `/habitats/{id}` | — | Listado y detalle con niveles 1/2/3 y pool |
| **Combate de ruta 3v3** | Bucle principal de farmeo y capturas | `/habitats/{id}`, modo `ruta` (por defecto) | Equipo de exactamente 3 | EXP, caramelos familia/EV/tipo, capturas, avistados |
| **Entrenadores** | Combate PvE con recompensa doble y límite diario | `/habitats/{id}`, modo `entrenadores` | Equipo de 3 | EXP/caramelos ×2, avistados; sin capturas |
| **Exploraciones** | Expedición individual 1v1 con eventos, riesgo y botín | `/exploraciones` o modal del hábitat | 1 reclutado, tiempo real, modo | EXP 50/50 cuenta/reclutado, caramelos ×0,5, capturas, bayas/bonguris |
| **Mazmorras** | Secuencia de pisos con jefe 3v1 | `/habitats/{id}` (si `habitats.mazmorra` está configurado) | Equipo de 3; derrota → 1 h cooldown | Avance de piso y recompensa ×70 |
| **Gimnasios/medallas** | Progresión PvE por tipo, 4 etapas (3 entrenadores + líder) | `/gimnasios` | Equipo de 3; nivel mínimo | Medalla + recompensas ×10 |
| **Granjas** | Nidos, refugios, complementos EV y plantaciones por familia | Modal Granjas del hábitat | Caramelos familia/EV; bayas al plantar | Reducción de tiempo/IV, multiplicadores de aparición/captura, EV, bayas |
| **Forja** | Fabricar objetos de combate | `/forja` | Bayas, bonguris y recipiente | Pociones, mejoras, reductores, resistencias, recipiente |
| **Huevos** | Obtener reclutados con mejores IV | `/equipos` (API `/api/huevos`) | ≥2 reclutados distintos de la misma familia; tiempo real | Reclutado con IV según nido; shiny 1/512 |
| **Reclutamiento/evolución** | Convertir capturas en reclutados; evolucionar | `/reclutamiento`, detalle en `/equipos` | Cola `reclutables`; EXP de tipo para evolucionar | Reclutado con IV/EV; nueva especie |
| **Pokédex** | Registro visto/atrapado | `/pokedex` | — | Información y visibilidad de sprites |
| **Equipos** | Unidad de combate de exactamente 3 | `/equipos` | Miembros, formación, posturas, objetos | Equipo listo (o inválido si ≠3) |
| **Sendas de tipo** | Progresión por tipo y desbloqueo global de movimientos | `/sendas` | EXP de tipo y caramelos de tipo | Nodos/niveles, movimientos y beneficios pasivos |
| **Inventario** | Almacén único de objetos/caramelos | Transversal (menú, modales) | — | Saldos por clave |
| **PvP** | — | — | — | No he encontrado PvP en el código revisado |

Detalles de acceso y contratos: ruta (`routes/ruta.php`; `docs/architecture.md:478-487`), entrenadores (`routes/entrenadores.php`; `docs/architecture.md:459-463`), exploraciones (`routes/exploraciones.php`; `src/Exploraciones/context.md:224-244`), mazmorras (`routes/mazmorras.php`; `src/Mazmorras/context.md:59-74`), gimnasios (`src/Gimnasios/context.md:49-54`), granjas (`src/Granjas/context.md:102-118`), forja (`docs/architecture.md:387-393`), huevos (`routes/huevos.php`; `docs/architecture.md:525-531`).

---

## 3. Progresión del jugador

| Eje | Cómo sube | Qué desbloquea | ¿Conectado a otros ejes? |
|---|---|---|---|
| **Nivel de cuenta (EXP global)** | EXP de derrotas en ruta/entrenadores/gimnasios/mazmorras y exploraciones (`src/Exploraciones/App/PersistirRecompensas.php:151-160`) | Gimnasios por `nivel_min` (`src/Gimnasios/App/ObtenerGimnasios.php:24-45`); niveles de exploración por `min_lvl` (`app/Http/Controllers/ExploracionActivaController.php:326-343`) | Curva compartida: `exp = 10 × nivel³`, nivel 100 = 10.000.000 (`src/Shared/Domain/NivelHelper.php:6-12,30-33`) |
| **Medallas / gimnasios** | Progreso por gimnasio `current_stage` 1–5, independiente entre gimnasios | Cierre del gimnasio al completarlo | Solo requiere nivel; no exige gimnasios previos (`GimnasioController`/`ObtenerGimnasios`) |
| **Mazmorra** | `current_floor` por `user_id + habitat_id` | Siguiente piso | Solo requiere la mazmorra configurada y equipo de 3 |
| **Pokédex** | Avistado (cualquier combate) / atrapado (crear reclutado) | Visibilidad de sprites en hábitat y Pokédex | Alimenta la sensación de descubrimiento, no bloquea sistemas |
| **Colección** | Reclutados por captura/ruta/exploración/huevo | Equipos, granjas, huevos | Es la base de casi todo lo demás |
| **IV/EV/CP** | IV por generación/nido/huevo; EV por complementos de granja y caramelos EV; CP calculado al nivel del usuario | Eficacia en combate | Conectado a granjas y a la progresión de nivel |
| **Sendas de tipo** | EXP por tipo ganada en combates; nivel 1–5 con umbrales 0/9.000/18.000/36.000/72.000 | Movimientos globales y beneficios de nivel 4/5 | Depende de caramelos de tipo y de EXP tipada |
| **Hábitats** | — | No hay desbloqueo por nivel observable | Los niveles 1/2/3 tienen `min_lvl` pero no se escriben (ver §6) |

Conclusión: el nivel de cuenta es el único eje que hoy "abre puertas" (gimnasios y, si se rellenaran, niveles de exploración). Los demás ejes avanzan en paralelo y no se condicionan entre sí. No hay ningún eje que desbloquee hábitats.

---

## 4. Economía y recompensas

**No hay moneda.** No he encontrado referencias a moneda, oro, coins o similar en `app/`, `src/`, `routes/`, `resources/` ni `database/` (búsqueda negativa limitada a esos directorios). El único "capital" son objetos y caramelos en `player_inventory`, más reclutados y EXP.

| Recompensa | De dónde sale | Límites/multiplicadores | Evidencia |
|---|---|---|---|
| **EXP de cuenta** | Ruta, entrenadores, gimnasios, mazmorras, exploraciones | `exp = floor(baseExp × nivel / 5)` por derrota; reparto por economía | `src/Shared/Domain/NivelHelper.php:34-37`; `src/Exploraciones/Domain/CalculadorRecompensas.php:381-393` |
| **EXP por reclutado** | Ruta (miembros), exploración (explorador) | Exploración: 50 % cuenta / 50 % reclutado | `src/Exploraciones/context.md:45-54` |
| **Caramelos de familia** | Derrotados (fase evolutiva), descartes de reclutables | `floor(sum(fase) × multiplicador)`; descarte = fase × cantidad | `CalculadorRecompensas.php:273-283`; `app/Http/Controllers/ReclutamientoController.php:120-155` |
| **Caramelos EV** | Derrotados (effort de stats) | `floor(sum(effort) × multiplicador)` | `CalculadorRecompensas.php:291-302` |
| **Caramelos de tipo** | Derrotados (EXP tipada) | `floor(expTipo / 250 × multiplicador × (1 + bono Senda))` | `CalculadorRecompensas.php:330-369` |
| **Bayas/bonguris** | Exploraciones (modo botín incrementa) | Tiradas por modo; +10 pp de bonguri si es del hábitat; tope 85 % | `docs/architecture.md:590-601` |
| **Capturas** | Ruta y exploración | Probabilidad cap-25 (`min(capture_rate,25)/255`, ≈9,8 % máx.); en exploración ×0,5 | `src/Reclutamiento/context.md:29-31`; `src/Exploraciones/context.md:54` |
| **Shiny** | Ruta salvaje 1/2048 (captura garantizada, IV 15); huevo 1/512 | Por slot/por huevo | `docs/context.md:27-28`; `src/Shared/context.md:41` |
| **Huevos** | Puesta si ≥2 reclutados distintos de la familia | 1 gestando + 1 incubando + 1 activo por familia | `docs/architecture.md:515-523` |
| **Medallas** | Completar gimnasio | Una por gimnasio | `src/Gimnasios/context.md:49-54` |
| **Objetos de combate** | Forja (46 recetas / 39 resultados) | Consume bayas/bonguris/recipiente | `docs/architecture.md:367-393` |

**Multiplicadores por actividad (fijados en código):** ruta `1.0` (`src/CombateRuta/App/OtorgarRecompensasRuta.php:54`), entrenador `2.0` y gimnasio `10.0` (`src/CombateEntrenadores/App/OtorgarRecompensasEntrenador.php:36-38`), mazmorra `70.0` = gimnasio ×7 (`src/Mazmorras/Domain/ReglasMazmorra.php:16-27`), exploración individual `0.5` con reparto 50/50 (`src/Exploraciones/Domain/ReglasExploracion.php`; `src/Exploraciones/context.md:45-54`).

**Sumideros (sinks) existentes:** subir nidos (25/75/225/450/900 caramelos de familia), refugios (25/75/225/450/2700), complementos EV (3× EV + 1 caramelo de familia), plantar bayas, forjar objetos, desbloquear movimientos de Senda (coste 2/5/10/25/60 caramelos de tipo) y aportar EXP a Sendas (100 EXP por caramelo, tope 72.000) (`src/Granjas/context.md:42-67`; `config/sendas.php:3-8`).

---

## 5. Ritmo temporal

| Actividad | Cadencia / límite | Evidencia |
|---|---|---|
| **Entrenadores** | 3 niveles × 3 entrenadores = 9 victorias/día/hábitat; `won=true` bloquea ese entrenador ese día; perder es repetible | `src/CombateEntrenadores/App/ObtenerEntrenadoresHabitat.php:26-49`; `src/CombateEntrenadores/Infra/EloquentEntrenadorLogRepository.php:12-33`; `docs/architecture.md:459-463` |
| **Ruta** | Sin límite diario ni log; repetible sin coste | `src/CombateRuta/App/IniciarCombateRuta.php:20-23`; `docs/architecture.md:478-481` |
| **Exploraciones** | Sin tope de exploraciones simultáneas encontrado (solo 1 por reclutado); 30 min por encuentro; procesado programado cada 5 min y bajo demanda con throttle 30 s y lock 60 s; duración 1–72 h o hora de vuelta | `app/Http/Controllers/ExploracionActivaController.php:296-330`; `src/Exploraciones/context.md:84-103`; `routes/console.php:13` |
| **Mazmorras** | Derrota → cooldown 1 h por piso; victoria avanza | `src/Mazmorras/Domain/ReglasMazmorra.php:27`; `src/Mazmorras/context.md:43-48` |
| **Gimnasios** | Sin cooldown diario; bloqueo por nivel y etapa actual; completado permanente | `src/Gimnasios/context.md:49-54` |
| **Plantaciones** | 1 cosecha/24 h, stock máximo 3, cálculo on-read sin cron | `docs/architecture.md:269-282`; `src/Granjas/context.md:76-98` |
| **Huevos** | Tiempos reales (puesta `255/capture_rate` h; eclosión `ceil(hatch/15)` h); estados on-read | `docs/architecture.md:508-523` |
| **Habitats/gimnasios/Pokédex** | Sin cadencia | Código revisado |
| **Zona horaria** | `Europe/Madrid` | `config/app.php:70` |
| **Reset diario** | No hay job de reset: el "día" se materializa por fecha (`fought_at`) | `EloquentEntrenadorLogRepository.php:12-33` |

Las actividades que hoy son "diarias de facto" son los entrenadores; la ruta no tiene cadencia, y las exploraciones dependen del tiempo real, no de un reset diario. No existen misiones diarias/semanales/mensuales implementadas: `Tareas_pdte/misiones` solo contiene las cabeceras vacías `Diarias:`, `Semanales:`, `Mensuales:`.

---

## 6. Hábitats y desbloqueo

- **Listado:** todos los hábitats se listan por provincia sin comprobación de nivel ni de progreso (`app/Http/Controllers/HabitatsController.php:40-47`; `resources/views/habitats/index.blade.php`). Se excluye la provincia "Vacio".
- **Acceso:** `/habitats/{id}` abre el detalle para cualquier usuario autenticado (`routes/web.php:19-20`, `HabitatsController::show`).
- **Provincias:** 7 provincias reales + "Vacio" (`database/seeders/ProvinceSeeder.php:17-34`), con color de bonguri asignado por provincia (`database/seeders/BonguriHabitatSeeder.php:27-35`).
- **Restricciones `min_lvl_1/2/3`:** existen como columnas nullable (`database/migrations/2026_08_29_000010_add_min_lvl_columns_to_habitats_table.php:15-19`), se leen en el DTO y en la vista, y el backend de exploración las aplica (`app/Http/Controllers/ExploracionActivaController.php:326-343`). **No he encontrado ningún escritor**: ni el seeder (`database/seeders/HabitatSeeder.php:19-31`) ni ningún controlador/repositorio las actualiza. Si no se rellenan a mano, el bloqueo de nivel de exploración nunca se activa.
- **Alcance del bloqueo:** en la UI solo deshabilita el modo Pokédex/exploración (`resources/views/habitats/partials/columna-derecha.blade.php:220-245`); ruta y entrenadores ignoran el `min_lvl` (`resources/views/habitats/show.blade.php:127-134`; `src/CombateRuta/App/IniciarCombateRuta.php` no valida nivel de hábitat).
- **Mazmorra:** solo existe si `habitats.mazmorra` (JSON) está configurado; el módulo no crea mazmorras por defecto (`src/Mazmorras/context.md:34-35`).
- **Relación con narrativa:** ninguna. No hay lore que explique por qué un hábitat está disponible o bloqueado.

---

## 7. Narrativa actual

Lo que sí existe:

- **Nombres de provincias** con sabor local: Galicia, Euskadi, Barcelona, Extremadura, Nova Alacant, Mutxamel, Andalucía (`database/seeders/ProvinceSeeder.php:17-34`).
- **Nombres de hábitats** con cierta ambientación: Bahía Crustáceo, Bosque Brumoso, Caverna Gélida, Cueva Dragón, Sierra Quemada, etc. (`storage/data/habitats.csv`).
- **Color de bonguri por provincia** (blanco, amarillo, negro, verde, rosa, azul, rojo), que conecta territorio con recurso de forja (`database/seeders/BonguriHabitatSeeder.php:27-35`).
- **Nombres de medallas por tipo** ("Medalla Bicho", "Medalla Veneno"…) y tipos de gimnasio (`src/Gimnasios/Domain/CatalogoGimnasios.php:28-40`).
- **Textos funcionales del diario de exploración** ("¡Emboscada!", "Victoria con coste", "se quedó sin PS", "Grupo salvaje") (`src/Exploraciones/Presentation/EventoExploracionVista.php:151,320-331`).
- **Peligro 1–5** derivado del nombre (cueva/abismo = 5, bosque = 1) (`database/seeders/HabitatSeeder.php:34-45`), mostrado como estrellas.

Lo que **no** he encontrado en el código revisado:

- NPCs con nombre, diálogos, misiones narradas, facciones implementadas ni trasfondo del mundo.
- Entrenadores con identidad: el rival se llama "Entrenador Nivel {nivel}" (`src/CombateEntrenadores/App/IniciarCombateEntrenador.php`, `nombreRival`).
- Líderes de gimnasio con nombre propio en el catálogo de código (solo especies y medalla).
- Ninguna tabla o modelo de "historia", "capítulo", "facción" o "misión".

Hay material de intenciones **no implementado**: `Tareas_pdte/mejoras` propone facciones (Team Rocket, Magma, Aqua…), legendarios y recompensas de gimnasio; `Tareas_pdte/tabla legendarios.md` es una tabla externa. Son documentos de ideas, no diseño consolidado.

Además, existe una **vista de misiones commiteada pero huérfana**: `resources/views/misiones/index.blade.php` consume `/misiones`, `/misiones/activa`, `/misiones/{id}/aceptar`, `/misiones/{id}/cancelar` y una variable `$misionPeleaRuta` (líneas 252, 269, 393, 427, 453), pero no hay rutas, controlador, modelo ni tabla de misiones en el código revisado; el enlace del menú está comentado (`resources/views/layouts/app.blade.php:54`). La vista define de facto un contrato (misión activa única, objetivos tipo `enfrentar`/`recuperar`, progreso `{actual,total}`, estados `en_progreso`/`listo_para_pelea`) que hoy no tiene backend.

---

## 8. Fricciones y oportunidades para misiones

**Dónde el juego está vacío de objetivos**

- La ruta es infinita y rentable pero no persigue nada: no hay contador de victorias, ni objetivos por hábitat, ni razón para variar de zona (`IniciarCombateRuta` sin log ni límite).
- No hay logros, rankings ni temporadas (búsqueda negativa en `app/`, `src/`, `routes/`).
- La Pokédex solo registra; no propone "captura esta familia" ni "completa esta zona".
- El desbloqueo de hábitats es inexistente, así que no hay gancho de "abrir el mapa".

**Actividades que se beneficiarían de una misión**

| Actividad | Objetivo natural | Por qué encaja |
|---|---|---|
| Ruta | Derrotar N salvajes de un tipo/hábitat | Es el bucle más repetido y no tiene registro; requiere instrumentar contadores |
| Entrenadores | Vencer a los 3 de un nivel / a un entrenador concreto | Ya existe log diario reutilizable (`trainer_combat_log`) |
| Exploraciones | Completar una expedición de X horas, traer N caramelos/bayas | Ya hay diario y resultado estructurado |
| Granja | Subir un nido a nivel N, cosechar N bayas | Hay estado persistido por familia |
| Forja | Fabricar un objeto concreto | Hay recetas y saldos |
| Huevos | Eclosionar N huevos / uno shiny | Hay contadores y estados |
| Pokédex | Avistar/capturar especies de una zona | Hay `visto`/`atrapado` |
| Gimnasios/Mazmorras | Completar medalla / piso | Hay progreso persistido |

**Recompensas que encajan sin romper la economía**

- Caramelos de familia/EV/tipo: ya son la moneda de facto y tienen sumideros claros.
- Bayas/bonguris y objetos de forja: alimentan granja/forja sin crear moneda nueva.
- Suelo de IV en huevo, encuentro garantizado de una especie o shiny con probabilidad limitada.
- Desbloqueo de hábitat/nivel como recompensa de misión (sustituye al `min_lvl` inerte).
- Títulos/cosméticos no económicos (no existen hoy; habría que crearlos).

**Riesgos de diseño**

- **Daily chores:** si las diarias son "entra y haz 3 combates", se convierten en obligación. El juego ya tiene un diario (entrenadores) y no conviene duplicar la misma textura.
- **Misiones triviales:** "gana 1 combate" no aporta objetivo; la ruta ya lo hace sin misión.
- **Inflación de recompensas:** sumar caramelos de misión a los de ruta/entrenador/gimnasio/mazmorra puede romper la curva de nidos (900 caramelos de nivel 5) y de Sendas (72.000 EXP).
- **Farmeo de misión:** con ruta ilimitada, cualquier objetivo "derrota N" es trivialmente farmeable; habría que limitar por tiempo, por hábitat o por tipo.
- **Misión activa única:** la vista existente asume una sola misión activa; habrá que decidir si eso es suficiente para diarias+semanales+historia.
- **Narrativa retrofit:** hoy no hay hablantes ni conflicto; introducir misiones narrativas exige decidir quién habla (profesor, líder, facción) y dónde vive ese contenido.
- **Desbloqueo por misión vs nivel:** si se añade, conviene no mezclar tres fuentes de bloqueo (nivel, misión, medalla) sin una regla clara.

**Fricciones técnicas observables para el diseño de misiones**

- No hay un registro unificado de "acciones del jugador"; el único log de actividad es `trainer_combat_log` (victorias de entrenador por día). Ruta, exploración, granja y forja no dejan un evento consultable homogéneo.
- La captura en exploración va a `reclutables` (cola) y el `atrapado` de Pokédex solo se marca al crear el reclutado (`app/Support/CrearReclutado.php:30`); una misión de "captura" debe decidir si cuenta la cola o el reclutado.
- `min_lvl` no se escribe, así que no hay un modelo de desbloqueo listo para reutilizar.
- La vista de misiones ya promete un contrato (reclutado + hábitat al aceptar, pelea al completar) que no existe en backend; conviene decidir si se reutiliza o se reescribe.

---

## 9. Evidencia (archivos:líneas consultados para las afirmaciones clave)

- `README.md:17-60` — stack, arquitectura y tabla de módulos.
- `docs/context.md:11` — combate 3v3 y estado; `:23-24` entrenadores/ruta; `:25-33` shiny, huevos, granjas; `:100-114` esquema de BD.
- `docs/architecture.md:453-492` — entrenadores y ruta (reglas, límites, rutas); `:494-531` huevos; `:546-656` inventario F2–F5; `:813-855` esquema relacional.
- `src/Habitats/context.md:32-50` — familias, niveles, `min_lvl`, acceso.
- `src/Gimnasios/context.md:21-54` — catálogo, progresión, nivel mínimo, recompensas (nota: el contexto dice 5 gimnasios; el código tiene 18).
- `src/Gimnasios/Domain/CatalogoGimnasios.php:15-40,252-268` — 18 gimnasios y orden por dificultad.
- `src/Gimnasios/App/ObtenerGimnasios.php:24-45` — bloqueo por nivel y estado por gimnasio.
- `src/Mazmorras/context.md:34-55` — configuración JSON, 3v1, cooldown, recompensa 7×.
- `src/Mazmorras/Domain/ReglasMazmorra.php:16-27` — multiplicadores y 1 h.
- `src/Exploraciones/context.md:10-13,40-61,84-103,224-258` — exploración individual, economía 0,5, procesado y contratos.
- `src/Exploraciones/Domain/ReglasExploracion.php` — 30 min/encuentro, 0,5, reparto 50 %.
- `src/Exploraciones/Domain/CalculadorRecompensas.php:273-302,330-369,381-393` — fórmulas de caramelos y EXP.
- `src/Exploraciones/App/PersistirRecompensas.php:151-220` — EXP a cuenta, Sendas y reclutado.
- `src/Exploraciones/Domain/ModoExploracion.php` y `PesosEvento.php:52-59` — modos experiencia/reclutamiento/botín y pesos.
- `src/Reclutamiento/context.md:29-55` — captura cap-25, shiny, evolución por EXP de tipo.
- `app/Http/Controllers/ReclutamientoController.php:120-155` — descarte → caramelos de familia por fase.
- `app/Support/CrearReclutado.php:19-31` — EXP inicial 1500 y Pokédex `RECLUTADO`.
- `src/Equipos/context.md:36-55` — equipo de 3, formación, posturas, objetos.
- `src/Pokemon/context.md:31-46` — stats, IV 0–15, EV, CP.
- `src/Granjas/context.md:42-67,76-118` — nidos, refugios, EV, plantaciones y contratos.
- `src/Shared/context.md:38-43` — curva de nivel, cap-25, shiny, equipo.
- `src/Shared/Domain/NivelHelper.php:6-37` — `10×n³` y EXP por derrota.
- `config/sendas.php:3-8` — niveles, umbrales, costes, beneficios y captura de Sendas.
- `config/app.php:70` — zona horaria `Europe/Madrid`.
- `routes/web.php:18-49` — entrypoint y módulos montados; no hay `/misiones`.
- `routes/console.php:13` — `exploraciones:procesar` cada 5 min.
- `app/Http/Controllers/HabitatsController.php:40-47,49-91` — listado por provincia y detalle sin bloqueo.
- `resources/views/habitats/index.blade.php` — todos los hábitats listados.
- `resources/views/habitats/partials/columna-derecha.blade.php:220-245` — `min_lvl` solo bloquea modo pokémon.
- `app/Http/Controllers/ExploracionActivaController.php:296-343` — validación de exploración y `min_lvl` server-side.
- `database/migrations/2026_08_29_000010_add_min_lvl_columns_to_habitats_table.php:15-19` — columnas `min_lvl_1/2/3`.
- `database/seeders/HabitatSeeder.php:19-45` — carga CSV de hábitats y peligro; no escribe `min_lvl`.
- `database/seeders/ProvinceSeeder.php:17-34` y `BonguriHabitatSeeder.php:27-35` — provincias y color de bonguri.
- `storage/data/habitats.csv` — nombres de hábitats.
- `src/CombateRuta/App/IniciarCombateRuta.php:20-23` — sin límite diario (archivo con cambios locales).
- `src/CombateEntrenadores/App/ObtenerEntrenadoresHabitat.php:26-49` y `Infra/EloquentEntrenadorLogRepository.php:12-33` — estado diario de entrenadores.
- `src/CombateEntrenadores/App/OtorgarRecompensasEntrenador.php:36-38` — ×2 y ×10.
- `src/CombateRuta/App/OtorgarRecompensasRuta.php:54` — ×1,0.
- `app/Models/User.php:55-61` — nivel desde experiencia.
- `resources/views/misiones/index.blade.php:252,269,393,427,453` — contrato de misiones sin backend.
- `resources/views/layouts/app.blade.php:44-55` — enlace `/misiones` comentado.
- `Tareas_pdte/misiones` — solo cabeceras vacías Diarias/Semanales/Mensuales.
- `Tareas_pdte/mejoras` — facciones/legendarios como ideas no implementadas.

---

## 10. Supuestos y dudas

- **No verifiqué el estado real de la base de datos** (prohibido ejecutar consultas): no sé si `habitats.min_lvl_1/2/3` tienen valores cargados manualmente en el entorno del desarrollador. La conclusión "no hay bloqueo" se basa en que ningún writer aparece en el código revisado.
- **No verifiqué si algún hábitat tiene `mazmorra` configurada**; sin configuración, el módulo devuelve lista vacía (`src/Mazmorras/context.md:34-35`).
- **El árbol de trabajo tiene cambios locales preexistentes** (16 archivos modificados y varios untracked, entre ellos comparación de PC y `app/Support/Pc/`). No los interpreté como diseño consolidado. En particular, `src/CombateRuta/App/IniciarCombateRuta.php`, `routes/ruta.php`, `routes/entrenadores.php` y `routes/gimnasios.php` están modificados; sus contenidos pueden incluir trabajo no commiteado. Las reglas citadas están corroboradas por documentación commiteada (`docs/architecture.md`).
- **La documentación está desfasada en dos puntos**: `src/Gimnasios/context.md` habla de 5 gimnasios y el código tiene 18; `docs/context.md:100` dice 60 migraciones y hay 76. Usé el código como fuente para esos datos.
- **La vista `misiones` está commiteada** (aparece en `git log`), pero no encontré rutas ni backend; asumo que es un stub de UI dejado para una fase posterior, no una funcionalidad en curso.
- **No analicé en profundidad** el modelo de datos de batalla, eventos ni recompensas (lo cubre el otro analista).
- **No medí cobertura de tests** (desactivado por las reglas del entorno).

---

## Preguntas para el desarrollador

Solo las que bloquean el diseño de misiones/narrativa/desbloqueo:

1. **¿Quién es el emisor de las misiones?** ¿Un NPC recurrente (profesor/mentor), los líderes de gimnasio, facciones, o un tablón abstracto? De esto depende si la narrativa necesita NPCs y diálogos o solo textos.
2. **¿El desbloqueo de hábitats debe ser por misión, por nivel o mixto?** Hoy no hay desbloqueo; `min_lvl` existe pero no se rellena. Si las misiones van a dosificar hábitats, hay que decidir si sustituyen o complementan al nivel.
3. **¿La misión activa es única o habrá varias simultáneas?** La vista existente asume una sola; diarias+semanales+historia probablemente exigen varias.
4. **¿Se reutiliza la vista `misiones` existente y su contrato implícito** (aceptar con reclutado+hábitat, pelear al completar, objetivos `enfrentar`/`recuperar`), o se descarta y se rediseña?
5. **¿Las recompensas de misión son objetos/caramelos existentes o introducen algo nuevo** (moneda, tickets, cosméticos)? Hoy no hay moneda y la economía ya está calibrada con multiplicadores fijos.

---

## Trabajo restante y recomendaciones (por el límite de pasos)

**Estado:** la investigación funcional está completa en lo que respecta a documentos, rutas, controladores, vistas, migraciones, seeders y contextos de módulo. No se modificó ningún archivo ni se ejecutaron comandos de escritura.

**Pendiente de verificación (no bloqueante para el diseño, sí para precisar cifras):**
- Comprobar en BD si `habitats.min_lvl_1/2/3` tienen valores (determina si existe hoy algún bloqueo real de niveles de exploración).
- Comprobar qué hábitats tienen `mazmorra` configurada y cuántos pisos.
- Confirmar el número y nivel mínimo de los 18 gimnasios en código (leí los cinco primeros: 10/15/20/25/31).
- Revisar el otro informe técnico (modelo de datos, eventos, recompensas) para no duplicar ni contradecir.

**Recomendación de siguiente paso:** que el `architect` use este resumen para decidir el modelo de misión (emisor, cadencia, recompensa, desbloqueo) y que el desarrollador responda las cinco preguntas de producto. Sin esas respuestas, cualquier diseño de misiones sería especulativo.

---

## Parte B — Inventario técnico para misiones

**Nota de ejecución:** este agente alcanzó el máximo de pasos permitido. El inventario solicitado se completó con las lecturas realizadas hasta ese punto; no se modificó ningún archivo ni se ejecutaron migraciones, seeders, tests ni comandos de escritura. No quedan lecturas críticas pendientes para el alcance técnico solicitado. Lo que resta son decisiones de arquitectura, listadas al final.

---

# TASK-043 — Inventario técnico para el sistema de misiones

## Resumen ejecutivo

- El proyecto es Laravel 12 / PHP 8.2+ con módulos DDD en `src/` y modelos Eloquent todavía en `app/Models/` (`composer.json:8-13`).
- **No existe ningún módulo, tabla, modelo, ruta ni servicio de misiones.** La vista `resources/views/misiones/index.blade.php` es un stub huérfano: consume endpoints que no existen y la entrada del menú está comentada (`resources/views/layouts/app.blade.php:54`).
- La infraestructura de **combates y recompensas está consolidada**: ruta, entrenador, gimnasio, mazmorra y exploración comparten el mismo calculador de recompensas y el mismo inventario.
- El punto de cierre de cualquier batalla es `PresentadorResultadoBatalla::procesar()`, que despacha por `tipo` de batalla (`app/Livewire/Presenters/PresentadorResultadoBatalla.php:58-63`). Es el mejor punto de enganche para eventos de combate.
- **Exploraciones** sí persisten una bitácora rica en `exploraciones_activas.eventos` (JSONB): tipo, subtipo, `pokemon_id`, `tipo_id`, `stat`, resolución, etc.
- **Ruta, entrenador, gimnasio y mazmorra no guardan un log de derrotados.** Solo persisten resultado/progreso; los Pokémon derrotados existen en memoria durante la batalla y se pierden al terminar.
- El canal de recompensas ya cubre EXP de cuenta y de reclutado, caramelos de familia/EV/tipo, items de inventario, capturas en cola, reclutados shiny, avistados de Pokédex y medallas de gimnasio.
- **No hay moneda, tienda ni entidad económica** localizada. Las recompensas actuales son objetos, EXP, caramelos, capturas y reclutados.
- El tiempo se maneja con `Europe/Madrid` (`config/app.php:70`). El único “bucket diario” persistido es `trainer_combat_log.fought_at` (tipo `date` + unique). No hay patrón semanal ni reinicio de periodo.
- Los cooldowns existentes son por timestamp rodante: mazmorra 1 h y throttle de exploración 30 s. Los estados de huevo y el stock de plantaciones se calculan **on-read**, sin cron.
- Solo hay un comando programado: `exploraciones:procesar` cada 5 minutos (`routes/console.php:13`). No hay scheduler de misiones ni jobs de reset.
- La UI usa Blade + Alpine + Tailwind, con Font Awesome local. El patrón de tarjetas y modales ya está consolidado en `resources/views/habitats/`.
- El árbol de Git está sucio, pero los cambios locales son de comparación de PC y UI de combate; **no tocan misiones** ni el modelo de datos aquí inventariado.
- El riesgo principal no es la falta de recompensas, sino la **falta de un registro de eventos idempotente y consultable**: hoy cada flujo cuenta lo que necesita y lo descarta.
- La decisión de producto más bloqueante es qué significa “captura”: `reclutables` (cola de captura) y `reclutados` (ropa real) son entidades distintas.

---

## 1. Modelo de datos actual

### Tablas relevantes

| Tabla | Columnas / claves relevantes | Uso para misiones |
|---|---|---|
| `users` | `id`, `name`, `email` unique, `password`, `experiencia` | Nivel de jugador derivado de `experiencia`; no hay moneda ni contador de misiones |
| `provinces` | `id`, `name` | Agrupación de hábitats |
| `habitats` | `id`, `province_id` FK, `name`, `pokemons` JSON, `min_lvl_1/2/3`, `peligro`, `mazmorra` JSON, `bonguri` | Hábitat objetivo y desbloqueo por nivel; `pokemons` es una caché de avistados, no la fuente de verdad |
| `pokemon_habitat` | `pokemon_id` FK, `habitat_id` FK, `level` 1-3, unique (`pokemon_id`,`habitat_id`) | Pool real por hábitat y nivel |
| `pokemon` | `id`, `name`, `species_id`, `capture_rate`, `base_experience`, `hatch`, `evolution_chain_id` | Identificadores base de objetivo |
| `pokemon_types` | `id`, `pokemon_id` FK, `type` int, `slot` | Tipo(s) del Pokémon; `type` castea a `TipoEnum` |
| `pokemon_stats` | `pokemon_id`, `stat`, `base_stat`, `effort` | No relevante para objetivos, sí para recompensas de EV |
| `reclutados` | `id`, `user_id` FK, `pokemon_id` FK, `exp` JSONB `{total, tipos}`, `es_shiny`, `evs`, `ivs`, `behavior`, `movimientos`, `obj_equipados`, `habilidad_activa_id` | Ropa real del jugador y destinatario de EXP |
| `reclutables` | `id`, `user_id` FK, `pokemon_id` FK, `cantidad`, unique (`user_id`,`pokemon_id`) | **Cola de captura**, no reclutado real |
| `teams` | `id`, `user_id` FK, `name`, `formacion` JSON, `posturas` JSON | Equipo de combate |
| `team_members` | `team_id` FK, `pokemon_id` (id de `reclutados`), `slot`, `behavior`, unique de `pokemon_id` | Miembros del equipo; `pokemon_id` es engañoso: apunta a `reclutados.id` |
| `pokedex` | `id`, `user_id` FK, `pokemon_id` FK, `visto`, `atrapado`, unique (`user_id`,`pokemon_id`) | Estado de Pokédex por jugador |
| `player_inventory` | `id`, `user_id` FK, `item_key` varchar(64), `cantidad`, unique (`user_id`,`item_key`) | Inventario único de caramelos, bayas, bonguris, pociones, etc. |
| `player_type_paths` | `id`, `user_id` FK, `type_id`, `exp`, `level`, unique (`user_id`,`type_id`) | Sendas de tipo; reciben EXP tipada |
| `player_move_unlocks` | `id`, `user_id` FK, `move_id` FK, unique | Desbloqueos globales de movimientos |
| `exploraciones_activas` | `id`, `user_id`, `reclutado_id`, `habitat_id`, `nivel`, `modo`, `duracion_horas`, `hora_limite`, `indefinido`, `eventos` JSONB, `inicio_exploracion`, `llegada_destino`, `regreso` | Bitácora persistida de exploración |
| `trainer_combat_log` | `id`, `user_id`, `habitat_id`, `level`, `trainer_index`, `won`, `fought_at` date, unique de las 5 columnas | Límite diario de entrenadores |
| `gym_progress` | `id`, `user_id`, `gym_id` varchar(30), `current_stage`, `completed_at`, unique (`user_id`,`gym_id`) | Progreso de gimnasio |
| `gyms` / `gym_stages` | `gyms.slug` unique, `medalla`, `tipo`, `nivel_minimo`; `gym_stages.etapa`, `vanguardia`, `retaguardia`, `movimientos`, unique (`gym_id`,`etapa`) | Catálogo de gimnasios |
| `dungeon_progress` | `id`, `user_id`, `habitat_id`, `current_floor`, `completed_at`, unique (`user_id`,`habitat_id`) | Progreso de mazmorra |
| `dungeon_log` | `id`, `user_id`, `habitat_id`, `floor`, `won`, `fought_at` timestamp, índice (`user_id`,`habitat_id`,`floor`) | Cooldown de derrota |
| `huevos` | `id`, `user_id`, `pokemon_id`, `evolution_chain_id`, `capture_rate`, `hatch`, `nido_nivel`, `es_shiny`, `ivs`, `eclosionado_en` | Huevos y eclosión |
| `farms` / `nests` / `shelters` / `complements` | `farms` unique (`user_id`,`habitat_id`); `nests`/`shelters` unique (`farm_id`,`evolution_chain_id`); `complements` unique (`shelter_id`,`stat`) | Granjas |
| `nest_plantings` | `nest_id`, `slot_index`, `berry_key`, `planted_at`, `harvest_count`, unique (`nest_id`,`slot_index`) | Plantaciones |
| `favoritos` | `user_id`, `reclutado_id`, `habitat_id`, unique triple | Favoritos por hábitat |

### Relaciones clave

- `habitats` 1:N `pokemon_habitat` N:1 `pokemon`, con `level` en el pivote (`database/migrations/2026_02_24_201538_create_pokemon_habitat_table.php`).
- `pokemon` 1:N `pokemon_types`; `PokemonType::type` se castea a `TipoEnum` (`app/Models/PokemonType.php:20-22`).
- `pokemon` 1:N `reclutados`; `reclutados` 1:N `team_members`; `teams` 1:N `team_members`.
- `pokemon.evolution_chain_id` es un entero sin FK ni tabla de cadenas; la familia se agrupa por esa columna (`app/Support/CadenasEvolutivas.php:31-49`).
- `users` 1:N casi todo lo jugable mediante `user_id` y el trait `BelongsToUser` (`app/Models/Concerns/BelongsToUser.php:21-31`).
- No hay tabla `missions`, `quests`, `achievements`, `mission_progress`, `mission_events`, `currencies` ni equivalente.

### Observaciones de modelo

- `reclutables` y `reclutados` representan dos momentos distintos de la captura. Una misión “captura N de la especie S” puede referirse a:
  1. `reclutables.cantidad` (capturado pero no reclutado), o
  2. `reclutados` (reclutado real en el equipo).
- `pokedex.atrapado` se marca cuando se crea un `Reclutado`, no cuando se incrementa `reclutables` (`app/Support/CrearReclutado.php:34`, `app/Jobs/ActualizarPokedexJob.php:35-53`).
- Varias tablas usan JSON/JSONB para datos variables: `habitats.pokemons`, `habitats.mazmorra`, `reclutados.exp`, `equipos.formacion`, `equipos.posturas`, `exploraciones_activas.eventos`. Hay precedente para guardar definiciones de misión en JSONB, pero no para consultarlas eficientemente sin índices.

---

## 2. Fuentes de eventos

### Punto de cierre de batalla

`PresentadorResultadoBatalla::procesar()` lee la meta de sesión y despacha según `tipo` (`app/Livewire/Presenters/PresentadorResultadoBatalla.php:46-68`). La meta se guarda en sesión, no en BD (`app/Support/BattleSessionService.php:130-145`). La batalla serializada también vive en sesión (`app/Support/BattleSessionService.php:71-74`).

| Evento | Clase / método exacto | ¿Persistido? | Datos disponibles |
|---|---|---|---|
| Victoria/derrota en ruta | `PresentadorResultadoBatalla::procesarRuta()` (`:246-285`), `RegistrarResultadoRuta::registrar()` (`src/CombateRuta/App/RegistrarResultadoRuta.php:30-52`) | La victoria persiste recompensas; la derrota devuelve `null`. **No hay log de combate ni de derrotados** | `meta['habitat_id']`, `meta['nivel']`, `meta['team_id']`, `team2` con especies y shiny |
| Victoria/derrota vs entrenador | `procesarEntrenador()` (`:75-109`), `RegistrarResultadoEntrenador::registrar()` (`src/CombateEntrenadores/App/RegistrarResultadoEntrenador.php:20-29`), `EloquentEntrenadorLogRepository::registrarResultado()` (`src/CombateEntrenadores/Infra/EloquentEntrenadorLogRepository.php:24-36`) | Sí, `trainer_combat_log` con `won`, `fought_at` date y unique | `habitat_id`, `nivel`, `trainer_index`, `won`, `fecha`; especies rivales en `team2` |
| Victoria/derrota en gimnasio | `procesarGimnasio()` (`:116-172`), `RegistrarResultadoGimnasio::registrar()` (`src/Gimnasios/App/RegistrarResultadoGimnasio.php:21-38`) | Solo victoria válida y usuario autenticado; avanza `gym_progress.current_stage` | `gym_id`, `stage`, `nivel_rival`, `team_id`; medalla devuelta pero no almacenada |
| Victoria/derrota en mazmorra | `procesarMazmorra()` (`:179-238`), `RegistrarResultadoMazmorra::registrar()` (`src/Mazmorras/App/RegistrarResultadoMazmorra.php:23-43`) | Victoria avanza `dungeon_progress`; derrota inserta `dungeon_log` y activa cooldown | `habitat_id`, `floor`, `totalPisos`, `won` |
| Combate dentro de exploración | `GestorTickExploracion::resolverTick()` y `volcar()` (`src/Exploraciones/App/Procesamiento/GestorTickExploracion.php:182-284`), `EventoExploracion::aArray()` (`src/Exploraciones/Domain/ValueObjects/EventoExploracion.php:119-248`) | Sí, dentro de `exploraciones_activas.eventos` (`app/Models/ExploracionActiva.php:26-38`) | `tipo`, `subtype`, `pokemon_id`, `pokemon_ids`, `tipo_id`, `stat`, `cantidad`, `resolucion`, `victoria`, `derrota` |
| Derrotados de exploración para recompensa | `ClasificadorEventosExploracion::idsDerrotados()` (`src/Exploraciones/Domain/Recompensas/ClasificadorEventosExploracion.php:23-37`), `MapeadorPokemonsDerrotados::mapear()` (`src/Exploraciones/App/Finalizacion/MapeadorPokemonsDerrotados.php:28-45`) | Se deriva de la bitácora persistida | `pokemon_id` expandido; tipo, especie y cadena se consultan después en `pokemon` |
| Captura de exploración | `PersistirRecompensas::aplicarCapturas()` (`src/Exploraciones/App/PersistirRecompensas.php:118-130`) | Sí, `reclutables.cantidad` | `pokemon_id`, `cantidad` |
| Captura shiny de ruta | `OtorgarRecompensasRuta::otorgar()` (`src/CombateRuta/App/OtorgarRecompensasRuta.php:151-168`) | Sí, crea `Reclutado` inmediato | `pokemon_id`, IVs máximos, shiny |
| Avistado / capturado en Pokédex | `ActualizarPokedexJob::handle()` (`app/Jobs/ActualizarPokedexJob.php:35-53`) | Sí, `pokedex.visto/atrapado` | `user_id`, `pokemon_id`, estado |
| Eclosión de huevo | `EclosionarHuevo::eclosionar()` (`src/Huevos/App/EclosionarHuevo.php:31-77`) | Sí, `huevos.eclosionado_en` + nuevo `Reclutado` | `pokemon_id`, `evolution_chain_id`, shiny, IVs |
| Fabricación | `Fabricar::handle()` (`src/Forja/App/Fabricar.php:29-64`) | Sí, consume ingredientes e incrementa resultado en `player_inventory` | Receta, ingredientes, resultado, saldos |
| Plantación | `PlantarBaya::handle()` (`src/Granjas/App/PlantarBaya.php:29-91`) | Sí, `nest_plantings` | `habitat_id`, `evolution_chain_id`, `slot`, baya |
| Cosecha | `CosecharPlantacion` / `CosecharTodo` | Sí, `harvest_count` e inventario | Nido, hueco, baya, cantidad |
| Evolución | `ReclutadoController::evolucionar()` (`app/Http/Controllers/ReclutadoController.php:209-240`) | Sí, cambia `reclutados.pokemon_id` y consume EXP/caramelos | Reclutado, especie origen/destino |
| Reclutamiento desde cola | `POST /reclutamiento/recruit` (`routes/player.php:23`) y `ReclutamientoController::recruit()` | Sí, convierte `reclutables` en `reclutados` | `pokemon_id`, cantidad/coste |
| Compra/venta | No localizado | — | No hay tienda ni moneda en migraciones/rutas revisadas |

### Conclusión sobre eventos

- No hay bus de eventos de dominio ni listeners: no se localizan `Event::`, `event(` ni `Listener` en `app/` ni `src/`. Solo existe el bus de comandos propio (`Src\Shared\Bus`) usado por Exploraciones (`app/Providers/AppServiceProvider.php:53-54`).
- Las batallas de ruta/entrenador/gimnasio/mazmorra se resuelven en **un único request de Livewire** y su resultado se procesa ahí. Si se quiere progreso de misión duradero, hay que persistirlo en ese momento o justo después.
- La derrota en ruta no persiste nada (`RegistrarResultadoRuta.php:40-42`). Si una misión debe contar “derrotas” del jugador, no hay fuente actual.
- La exploración es la única fuente con historial detallado de encuentros y derrotas.

---

## 3. Identificadores de objetivo

| Objetivo posible | Datos existentes | Dónde |
|---|---|---|
| Derrotar X Pokémon concretos | `pokemon.id` | `Pokemon` en `pokemon`; `team2` de batalla; `evento.pokemon_id` en exploración |
| Derrotar N de la especie S | `pokemon.species_id` | `database/migrations/2026_02_24_171456_create_pokemon_table.php`; `PokemonDerrotado::$speciesId` (`src/Exploraciones/Domain/Recompensas/PokemonDerrotado.php:19-28`) |
| Derrotar N de la familia evolutiva F | `pokemon.evolution_chain_id` | Columna sin FK; mapa con `CadenasEvolutivas` |
| Derrotar N del tipo T | `pokemon_types.type` int 1-18 | `app/Models/PokemonType.php:20-22`; `TipoEnum` (`app/Enums/TipoEnum.php:7-26`); labels en `PokemonType::getTipoNombreAttribute()` |
| Derrotar N en el hábitat H | `habitat_id` | `pokemon_habitat`, `exploraciones_activas.habitat_id`, `meta` de batalla, `trainer_combat_log.habitat_id`, `dungeon_progress.habitat_id` |
| Derrotar N en el nivel L del hábitat | `pokemon_habitat.level` 1-3; `exploraciones_activas.nivel`; `meta['nivel']` | Pivote y columnas citadas |
| Derrotar a un entrenador | `habitat_id` + `nivel` + `trainer_index` | `trainer_combat_log`, `meta` de batalla |
| Completar gimnasio G / etapa E | `gym_id` + `current_stage` | `gym_progress`; `meta['gym_id']`, `meta['stage']` |
| Completar piso F de mazmorra | `habitat_id` + `floor` | `dungeon_progress`, `dungeon_log`, `meta['floor']` |
| Capturar N de la especie S | `reclutables.pokemon_id` + `cantidad`, o `reclutados.pokemon_id` | Ambigüedad ya señalada |
| Reclutar/eclosionar N | `reclutados`, `huevos` | `src/Huevos/App/EclosionarHuevo.php`, `app/Support/CrearReclutado.php` |
| Descubrir/avistar N especies | `pokedex.visto` | `app/Models/Pokedex.php:17-27` |
| Completar una exploración | `exploraciones_activas.regreso` no nulo y `eventos` | `FinalizarExploracionHandler` |
| Realizar X combates | No hay contador global | Solo `trainer_combat_log`, `dungeon_log` y bitácoras de exploración |

### Limitaciones de identificación

- `team_members.pokemon_id` apunta a `reclutados.id`, no a `pokemon.id`; no confundir al contar especies.
- En ruta, el `speciesId` del combatiente se usa después como id de `pokemon` para cargar stats/tipos (`src/CombateRuta/App/OtorgarRecompensasRuta.php:102-125`). Es coherente con el modelo actual, pero conviene documentarlo al crear objetivos.
- El tipo de un Pokémon solo se conoce consultando `pokemon_types`; no está desnormalizado en `team2` ni en `trainer_combat_log`.
- Para misiones por tipo/hábitat en entrenador/gimnasio/mazmorra habrá que resolver el tipo del rival contra el catálogo en el momento del cierre de batalla.
- No existe un identificador estable de “evento de combate” para idempotencia. `battleId` existe en sesión (`BattleSessionService::crearId()`, `:66-69`) pero no se persiste.

---

## 4. Recompensas

### Servicios y calculadores existentes

| Componente | Responsabilidad | Evidencia |
|---|---|---|
| `CalculadorRecompensas` | Calcula capturas, caramelos familia/EV/tipo y EXP repartida | `src/Exploraciones/Domain/CalculadorRecompensas.php:51-85` |
| `EconomiaRecompensas` | Política de factor y reparto (`legado`, `individual`) | `src/Exploraciones/Domain/EconomiaRecompensas.php:27-84` |
| `OtorgarRecompensasRuta` | Recompensas de ruta ×1.0, capturas, shiny inmediato | `src/CombateRuta/App/OtorgarRecompensasRuta.php:73-179` |
| `OtorgarRecompensasEntrenador` | Recompensas ×2.0 entrenador / ×10.0 gimnasio, sin capturas | `src/CombateEntrenadores/App/OtorgarRecompensasEntrenador.php:30-108` |
| `OtorgarRecompensasMazmorra` | Reutiliza el otorgador de entrenador con multiplicador de mazmorra | `src/Mazmorras/App/OtorgarRecompensasMazmorra.php:21` |
| `FinalizarExploracionHandler` | Cierra expedición y reparte recompensas con `UnitOfWork` | `src/Exploraciones/App/FinalizarExploracionHandler.php:90-122` |
| `PersistirRecompensas` | Escribe caramelos, items, capturas, EXP de cuenta y de reclutado | `src/Exploraciones/App/PersistirRecompensas.php:35-50` |
| `InventarioJugador` / `InventarioJugadorEloquent` | Puerto e implementación atómica de inventario | `src/Shared/Domain/Ports/InventarioJugador.php:19-58`; `src/Shared/Infra/Inventario/InventarioJugadorEloquent.php:87-149` |
| `CrearReclutado` | Crea reclutado con IVs/EVs y marca Pokédex | `app/Support/CrearReclutado.php:20-36` |
| `ResolverBonosFamilia` | EVs iniciales por familia y nivel de nido | Usado en `EclosionarHuevo` y `OtorgarRecompensasRuta` |

### Cómo se conceden hoy

- **Caramelos**: `PersistirRecompensas` incrementa `player_inventory` con claves `familia:{id}`, `ev:{stat}` y `tipo:{slug}` (`:55-80`).
- **Items**: `RecompensaItemCollection` incrementa claves de catálogo en `player_inventory` (`:82-91`).
- **Capturas**: incrementan `reclutables` (`:118-130`). El shiny de ruta crea un `Reclutado` inmediato (`OtorgarRecompensasRuta.php:151-168`).
- **EXP de cuenta**: incrementa `users.experiencia` (`PersistirRecompensas.php:153-155`).
- **EXP de reclutado**: actualiza `reclutados.exp` con total y tipos (`:160`, `:223-237`).
- **Sendas**: incrementa `player_type_paths.exp` y recalcula nivel (`:184-217`).
- **Pokédex**: `ActualizarPokedexJob` y `CrearReclutado` marcan visto/atrapado.
- **Medalla de gimnasio**: se devuelve en el DTO del resultado, pero no hay tabla de medallas; el avance queda en `gym_progress.current_stage/completed_at`.

### Idempotencia y transacciones

- **No existe ledger de recompensas ni clave de idempotencia.** Los incrementos son atómicos por fila, pero repetir el cierre de una batalla volvería a sumar.
- `PersistirRecompensas::persistir()` no abre transacción propia (`:35-50`). En exploración se invoca dentro de `UnitOfWork`; en ruta/entrenador/gimnasio se invoca desde el presentador sin una transacción global que cubra todas las escrituras.
- `PlayerInventory` usa unique (`user_id`,`item_key`) e `insertOrIgnore` + `increment` (`PersistirRecompensas.php:101-112`). Evita filas duplicadas, no evita dobles incrementos por doble evento.
- `EclosionarHuevo` sí es idempotente: rechaza un huevo ya eclosionado (`src/Huevos/App/EclosionarHuevo.php:42-45`).
- Los jobs `ActualizarPokedexJob`, `CapturarPokemonJob` y `RecompilarHabitatJsonJob` **no implementan `ShouldQueue`**; con el driver de cola actual se ejecutan de forma síncrona al despacharse. No hay worker de recompensas.

### Recompensas viables sin inventar entidades

- EXP de cuenta.
- EXP de reclutado concreto.
- Caramelos de familia, EV y tipo.
- Items del catálogo (`baya:*`, `bonguri:*`, `pocion:*`, `material:*`, `mejora:*`, `reductor:*`, `resistencia:*`).
- Captura en cola (`reclutables`).
- Reclutado directo (shiny, huevo, reclutamiento).
- Avistado/atrapado de Pokédex.
- Avance de gimnasio/mazmorra ya modelado.
- No hay moneda, puntos de misión, cosméticos ni slots de misión.

---

## 5. Tiempo y cadencia

| Mecanismo | Implementación actual | Reutilización para misiones |
|---|---|---|
| Zona horaria | `config/app.php:70` → `Europe/Madrid` | Base para “diaria/semanal” si se define el corte |
| Fecha diaria de entrenadores | `today()->toDateString()` en `EntrenadorController` (`:51`, `:69`, `:98`) y `PresentadorResultadoBatalla.php:87` | Patrón de clave de fecha `Y-m-d` |
| Persistencia de fecha | `trainer_combat_log.fought_at` tipo `date`, unique con `user_id`,`habitat_id`,`level`,`trainer_index` | Único ejemplo consolidado de bucket diario |
| Cooldown de mazmorra | `fought_at > now()->subHours(1)` (`EloquentDungeonProgresoRepository.php:51-75`) | Patrón de ventana rodante |
| Throttle de exploración | `eventos['ultimo_procesado']` y 30 s (`ProcesarExploracionesPendientes.php`) | Patrón de no repetir procesamiento |
| Lock de exploración | `Cache::lock('exploracion:procesar:{id}', 60)` no bloqueante (`ProcesarExploracionesPendientes.php:155-166`) | Patrón para cierre de misión |
| Scheduler | Solo `exploraciones:procesar` cada 5 min (`routes/console.php:13`) | No hay reset programado |
| Huevos | Estado calculado on-read desde `created_at` y snapshots; sin cron | Patrón para misiones con vencimiento sin job |
| Plantaciones | Stock calculado on-read desde `planted_at` y `harvest_count`; sin cron | Patrón para regeneración de misiones |
| Timestamps de dominio | `now()`, `today()`, ISO 8601 en eventos (`EventoExploracion`) | Hay mezcla de `date` y `timestamp`; hay que fijar convención |
| Semanal | No existe | Habría que definir semana ISO, lunes 00:00 o ventana rodante |

### Problemas detectados

- `today()` depende de `APP_TIMEZONE`; los timestamps de BD y la comparación de fechas pueden desalinearse si el servidor o PostgreSQL usan UTC. Hay que decidir si el corte es por fecha de aplicación o por UTC.
- Cambio de hora: una “diaria” basada en medianoche local puede tener días de 23/25 h. Una ventana rodante de 24 h evita el problema, pero no coincide con la expectativa del jugador.
- No hay unicidad de “misión diaria del usuario”: habría que crearla. El unique de `trainer_combat_log` solo protege ese dominio.
- Concurrencia: dos peticiones simultáneas cerca del corte pueden ver periodos distintos y generar dos misiones o dos recompensas. `trainer_combat_log` resuelve el duplicado de registro por unique, pero no impide que dos batallas se inicien antes de registrar el resultado.
- No hay reloj de dominio inyectado de forma consistente: `now()` y `today()` se usan directamente en varios controladores. Hay `Reloj` en Exploraciones (`Src\Shared\Domain\Ports\Reloj`), pero no se usa en todas las capas.
- El scheduler solo funciona si el contenedor ejecuta `schedule:run`. No hay evidencia de que esté configurado; no se debe asumir.

---

## 6. Persistencia y transacciones

### Convenciones observadas

- Migraciones aditivas con `Schema::table`, backfill idempotente y `down()` simétrico. Ejemplo destacado: bonguri por provincia (`database/migrations/2026_11_04_000002_add_bonguri_to_habitats.php`).
- JSON en PostgreSQL se usa como `jsonb` cuando importa consultar: `exploraciones_activas.eventos` se migró a JSONB (`database/migrations/2026_08_30_000003_change_eventos_to_jsonb_in_exploraciones_activas_table.php`).
- Unique compuestos como garantía de negocio:
  - `player_inventory (user_id,item_key)`.
  - `pokedex (user_id,pokemon_id)`.
  - `reclutables (user_id,pokemon_id)`.
  - `trainer_combat_log (user_id,habitat_id,level,trainer_index,fought_at)`.
  - `gym_progress (user_id,gym_id)`.
  - `dungeon_progress (user_id,habitat_id)`.
  - `nest_plantings (nest_id,slot_index)`.
  - `huevos` no tiene unique de “huevo activo por familia”; la regla se aplica por consulta.
- Transacciones explícitas:
  - `InventarioJugadorEloquent::consumirVarios()` bloquea filas ordenadas por `item_key` (`:106-149`).
  - `PlantarBaya` bloquea nido y hueco (`:39-91`).
  - `CosecharTodo` bloquea nido y plantaciones y aísla errores por nido.
  - `Fabricar` consume y produce en una transacción (`:37-64`).
  - `ReclutadoController` usa `DB::transaction` + `lockForUpdate` para caramelos/EVs/evolución (`:81-86`, `:153-170`, `:239-240`).
- `UnitOfWork` (`App\Bus\DatabaseUnitOfWork`) envuelve `DB::transaction` y `DB::afterCommit`; se usa en Exploraciones.
- No hay patrón de repositorio específico para “eventos de misión”; sí hay repositorios por módulo (`EntrenadorLogRepositoryInterface`, `GymProgressRepositoryInterface`, `DungeonProgresoRepositoryInterface`).

### Ejemplos que sirven de patrón para misiones

- **Único por periodo**: `trainer_combat_log` es el mejor precedente para “una misión diaria por usuario y fecha”.
- **Progreso agregado por clave**: `gym_progress` y `dungeon_progress` muestran el patrón “una fila por usuario + entidad”.
- **Evento inmutable con log**: `dungeon_log` y `trainer_combat_log` muestran el patrón “registro histórico además del agregado”.
- **Lock ordenado**: `InventarioJugadorEloquent` es el patrón a copiar si una misión concede varias recompensas.
- **Cálculo on-read**: huevos y plantaciones evitan jobs de avance; útil si una misión solo necesita saber si el periodo expiró.

### Huecos de persistencia

- No hay ledger de eventos de juego que una misión pueda consumir de forma genérica.
- No hay contador de “combates realizados”, “Pokémon derrotados totales” ni “capturas totales” fuera de logs específicos.
- No hay tabla de definiciones de misión ni de recompensas reclamadas.
- No hay clave de idempotencia de recompensa (por ejemplo `source_type` + `source_id` + `user_id`).

---

## 7. Puntos de integración UI

### Estado actual

- Layout principal con nav horizontal y array de items (`resources/views/layouts/app.blade.php:23-65`).
- La entrada de Misiones está **comentada**: `//['route' => '/misiones', 'label' => 'Misiones']` (`resources/views/layouts/app.blade.php:54`).
- La cosecha global ocupa el primer botón del nav y usa Font Awesome local (`fa-seedling`, `fa-spinner`) (`:28-42`).
- Toast global de cosecha con `role="status"` y `aria-live="polite"` (`:98-129`).
- Vista de hábitat: `x-data="habitatShow()"` y partials de modales (`resources/views/habitats/show.blade.php:6-26`).
- Columna izquierda: botones de acción del hábitat (ruta, granja, entrenadores, exploraciones, mazmorra, admin) con patrón `card`/`rounded-card` (`resources/views/habitats/partials/columna-izquierda.blade.php:33-113`).
- Columna derecha: paneles de exploraciones activas, equipos, favoritos y niveles (`resources/views/habitats/partials/columna-derecha.blade.php:1-160`).
- Modales Alpine ya existentes: exploración, favoritos, formación, admin, mazmorra, granja y baya (`show.blade.php:14-26`).
- Vista de provincias/hábitats con tarjetas 3:2 (`resources/views/habitats/index.blade.php:30-55`).
- Font Awesome 6 Free local, sin CDN (documentado en `docs/architecture.md`, sección “Menú y Font Awesome”).
- No hay policy ni gate de misiones porque no hay backend.

### Stub de misiones

- `resources/views/misiones/index.blade.php` existe y está versionado, pero **no tiene ruta ni controlador**.
- Espera `GET /misiones`, `GET /misiones/activa`, `POST /misiones/{id}/aceptar`, `POST /misiones/{id}/cancelar` y una ruta de pelea configurable (`$misionPeleaRuta`).
- La vista ya modela estados `en_progreso`, `listo_para_pelea`, `completada`, progreso `{actual,total}`, recompensas cerradas y selección de reclutado/hábitat.
- No se debe activar el nav ni enlazar la vista sin backend: hoy el propio frontend cae en “Función en preparación”.

### Dónde encajaría un panel de misiones

Opciones, sin decidir:

1. **Página independiente `/misiones`**: reutiliza el stub, el nav ya tiene el hueco y evita cargar la vista de hábitat. Requiere rutas, controlador y datos.
2. **Panel dentro de `/habitats/{id}`**: encaja en la columna derecha como tarjeta de “misiones del hábitat”, reutilizando el patrón de tarjetas y modales. Útil para misiones contextuales por hábitat.
3. **Híbrido**: página global para diarias/semanales/únicas y tarjeta contextual en hábitat para misiones locales.
4. **Modal desde el nav**: botón con contador y modal; menor coste visual, pero menos espacio para historia y progreso.

### Riesgos de UI

- El stub actual usa `alert()` y `console.error`; no sigue el patrón de toasts del layout.
- El stub asume que `/api/reclutados` devuelve un array plano y que `/datagrid/habitat` existe; el primero existe (`routes/player.php:71`), el segundo está registrado en Datagrid, pero hay que verificar el contrato exacto antes de reutilizarlo.
- La vista de hábitat es grande (1.564 líneas) y ya tiene muchos modales; añadir otro modal incrementa el riesgo de colisiones de estado Alpine.
- El árbol de trabajo tiene cambios locales en `resources/views/habitats/show.blade.php` y `modal-formacion.blade.php`; cualquier implementación futura debe partir de ese estado, no de `HEAD`.

---

## 8. Arquitectura y convenciones

### Convención de módulo

La estructura destino está documentada en `docs/ddd.md` (secciones “Estructura de módulo”, “Entidades”, “Contrato de repositorio”, “Rutas por módulo”):

```text
src/Misiones/
├── Domain/
│   ├── Entities/
│   ├── ValueObjects/
│   ├── Collections/
│   ├── DataTransferObjects/
│   ├── Exceptions/
│   └── Repositories/
├── App/
└── Infra/
    ├── Repositories/
    ├── Models/
    ├── Factories/
    ├── Requests/
    ├── Controllers/
    ├── Livewire/
    └── routes.php
```

Reglas duras:

- `Domain` puro: sin Eloquent, HTTP, facades ni `Request`.
- `App` e `Infra` pueden usar Laravel libremente.
- Entidades con `int $id` no nullable, propiedades públicas y `toArray()`.
- Colecciones propias que extienden `Src\Shared\Domain\Collection`, no `Illuminate\Support\Collection`.
- DTOs `readonly`; prohibido devolver arrays como contrato entre capas (`docs/conventions.md`, sección “Tipado y datos”).
- Repositorios con contrato en español: `obtenerPorId`, `insertar`, `insertarColeccion`, `actualizar`, `upsertColeccion`, `eliminar`. Sin `getCollection` genérico; los listados van por Datagrid.
- Controladores delgados, sin `try-catch`; las excepciones de dominio se mapean en `bootstrap/app.php:18-27`.
- Rutas del módulo en `src/Misiones/Infra/routes.php`, importadas desde `routes/web.php` dentro de `auth`.
- Composition root en `app/Providers/AppServiceProvider::register()` (`app/Providers/AppServiceProvider.php:46-62`).

### Excepciones disponibles

- `DominioExcepcion` → 400.
- `RecursoNoExiste` → 404.
- `ViolacionReglaNegocio` → 422.
- `PermisoDenegado` → 403.
- Mapa actual en `bootstrap/app.php:18-27`.

### Multiusuario

- `BelongsToUser` aplica un global scope por `Auth::id()` y se desactiva en CLI (`app/Models/Concerns/BelongsToUser.php:21-31`).
- Cualquier proceso de misión lanzado por scheduler/job debe usar `withoutUserScope()` y `user_id` explícito, como hace `ProcesarExploracionesPendientes::paraTodas()`.
- No se debe confiar en `Auth` dentro de jobs o comandos.

### Pruebas

- El flujo de agentes prohíbe crear, modificar o ejecutar tests (`AGENTS.md`, sección “Pruebas”).
- La verificación debe ser análisis estático y criterios de aceptación. No se debe plantear cobertura ni escribir tests para misiones en este flujo.

### Lo que no existe y habría que crear

- Módulo `src/Misiones`.
- Tablas de definición, instancia/progreso y recompensa reclamada.
- Catálogo de objetivos y recompensas.
- Servicio de progreso e idempotencia.
- Generador procedural determinista.
- Repositorios y controladores.
- Rutas y policies.
- Vista o panel real.
- Posible estado de desbloqueo de hábitats.

---

## 9. Riesgos técnicos

| Severidad | Riesgo | Impacto | Mitigación posible |
|---|---|---|---|
| Alto | **Eventos dispersos y no unificados** | Cada flujo (ruta, entrenador, gimnasio, mazmorra, exploración) tendría que llamar al progreso de misiones por separado; es fácil olvidar uno o duplicarlo | Punto único de cierre de batalla o servicio de aplicación `RegistrarEventoJuego`; no depender de que cada controlador lo recuerde |
| Alto | **Ambigüedad de “captura”** | Una misión puede contar `reclutables` o `reclutados`; los jugadores verán progreso inconsistente | Decidir semántica antes de modelar; documentar si “capturar” = cola o reclutado |
| Alto | **Sin idempotencia de recompensas** | Doble cierre de batalla, doble clic o reintento puede duplicar EXP/items/capturas | Ledger único por `source_type+source_id+user_id`; reclamar dentro de transacción con unique |
| Alto | **Corte diario/semanal mal definido** | Zona horaria, DST, concurrencia y expectativa del jugador | Definir periodo canónico; usar clave de periodo persistida y unique; reloj de dominio |
| Medio | **Crecimiento de tablas de log** | `dungeon_log` y una futura tabla de eventos de misión pueden crecer sin límite | Retención, agregados por periodo, o ledger con TTL/particionado |
| Medio | **Agregación en tiempo real** | Consultar derrotados desde logs en cada carga de misión puede ser costoso y incompleto (ruta no tiene log) | Snapshots/counters incrementales o proyección periódica |
| Medio | **Misiones procedurales no deterministas** | Dos jugadores o dos cargas pueden ver misiones distintas | Semilla estable por usuario+periodo+hábitat+slot, como `crc32(habitat|nivel|entrenador|fecha)` (`GeneradorEquipoEntrenador.php:77`) |
| Medio | **Colusión con límites diarios** | Una misión “derrota X” puede incentivar farming por encima del límite de entrenadores; ruta no tiene límite | Caps por misión o por periodo; contar solo fuentes permitidas |
| Medio | **Impacto en sesión de batalla** | La batalla ya se serializa en sesión; añadir estado de misión ahí aumenta tamaño y acoplamiento | No guardar progreso de misión en la sesión de batalla; usar BD y solo `mission_id`/contexto mínimo |
| Medio | **Recompensas no transaccionales en ruta/entrenador/gimnasio** | Fallo a mitad de `PersistirRecompensas` deja recompensas parciales | Envolver el cierre en `UnitOfWork`/`DB::transaction` y cobrar la recompensa de misión en la misma transacción |
| Medio | **Desbloqueo de hábitats inexistente** | Hoy no hay estado global de campaña; los hábitats se listan todos y solo se bloquean niveles por `min_lvl` | Crear estado de desbloqueo por usuario o por campaña; no reutilizar `min_lvl` como bloqueo global |
| Bajo | **Stub de misiones engañoso** | El jugador puede llegar a una página que promete funciones inexistentes | No activar nav hasta que exista backend, o marcar como “en preparación” de forma explícita |
| Bajo | **Árbol de trabajo sucio** | Riesgo de mezclar cambios de PC con la nueva funcionalidad | Trabajar en worktree o delimitando archivos; no revertir cambios locales |

### Suposiciones

- PostgreSQL es la base de ejecución; SQLite solo se usa en tests.
- El scheduler no está garantizado en producción; no se debe depender de él para el estado de misiones si el jugador puede consultarlas sin cron.
- “Captura” en el lenguaje de producto se refiere a obtener el Pokémon, pero el código distingue cola (`reclutables`) y reclutado (`reclutados`).
- Las misiones deben ser por usuario, no globales, salvo las de historia.
- El nivel de jugador es el derivado de `users.experiencia` (`app/Models/User.php:55-61`).

### Preguntas bloqueantes

1. ¿“Capturar” significa incrementar `reclutables` o crear/poseer un `Reclutado`?
2. ¿La misión diaria se reinicia a medianoche local, a una hora configurable o por ventana rodante de 24 h?
3. ¿La semana empieza en lunes ISO, domingo o ventana rodante de 7 días?
4. ¿El jugador debe aceptar la misión antes de progresar, o el progreso es pasivo y se reclama al final?
5. ¿Las recompensas se reclaman manualmente o se autoconceden al completar?
6. ¿Las misiones únicas de historia son globales o por usuario?
7. ¿El desbloqueo de hábitats es por usuario o por servidor/campaña?
8. ¿Se permite cancelar misiones y perder progreso?
9. ¿Las misiones cuentan derrotas del jugador o solo victorias?
10. ¿Se debe conservar historial de eventos para auditoría o basta un contador?

---

## 10. Evidencia clave

### Modelo y persistencia

- `database/migrations/2026_05_27_000001_create_reclutados_table.php` — creación de `reclutados`.
- `database/migrations/2026_08_29_000002_add_user_id_to_reclutados_table.php` — `user_id` FK, `exp` JSONB.
- `database/migrations/2026_09_15_000001_add_ivs_evs_to_reclutados_table.php` — `evs`/`ivs`.
- `database/migrations/2026_09_05_000001_add_behavior_to_reclutados_table.php` — `behavior` y check.
- `database/migrations/2026_08_26_000002_create_reclutables_table.php` — cola de captura.
- `database/migrations/2026_08_29_000004_add_user_id_to_reclutables_table.php` — unique `(user_id,pokemon_id)`.
- `database/migrations/2026_08_29_000007_create_player_inventory_table.php` — inventario y unique.
- `database/migrations/2026_08_31_000001_create_trainer_combat_log_table.php` — `fought_at` y unique diario.
- `database/migrations/2026_09_01_000001_create_gym_progress_table.php` — progreso de gimnasio.
- `database/migrations/2026_09_06_000002_create_dungeon_tables.php` — progreso y log de mazmorra.
- `database/migrations/2026_09_15_000002_create_huevos_table.php` y `2026_09_15_000003_add_hatch_and_evolution_chain_id_to_huevos_table.php` — huevos.
- `database/migrations/2026_10_26_000001_create_farm_tables.php` — granjas, nidos, refugios y complementos.
- `database/migrations/2026_11_04_000003_create_nest_plantings_table.php` — plantaciones.
- `database/migrations/2026_10_30_162641_create_player_type_paths_table.php` — Sendas.
- `database/migrations/2026_02_24_201538_create_pokemon_habitat_table.php` — pool por hábitat y nivel.
- `database/migrations/2026_08_29_000010_add_min_lvl_columns_to_habitats_table.php` — `min_lvl_1/2/3`.
- `database/migrations/2026_09_06_000001_add_mazmorra_to_habitats_table.php` — `mazmorra`.
- `database/migrations/2026_11_04_000002_add_bonguri_to_habitats.php` — `bonguri` y backfill.

### Eventos y recompensas

- `app/Livewire/Presenters/PresentadorResultadoBatalla.php:58-63` — despacho por tipo de batalla.
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php:75-109` — entrenador.
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php:116-172` — gimnasio.
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php:179-238` — mazmorra.
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php:246-285` — ruta.
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php:296-305` — `medirProgreso` solo mide EXP para el modal.
- `app/Support/BattleSessionService.php:71-74` — batalla serializada en sesión.
- `app/Support/BattleSessionService.php:130-145` — meta de batalla en sesión.
- `src/CombateRuta/App/RegistrarResultadoRuta.php:30-52` — derrota no persiste nada.
- `src/CombateRuta/App/OtorgarRecompensasRuta.php:73-179` — recompensas, capturas y shiny.
- `src/CombateEntrenadores/App/RegistrarResultadoEntrenador.php:20-29` — registro de entrenador.
- `src/CombateEntrenadores/Infra/EloquentEntrenadorLogRepository.php:24-36` — `updateOrCreate` diario.
- `src/Gimnasios/App/RegistrarResultadoGimnasio.php:21-38` — avance de gimnasio.
- `src/Gimnasios/Infra/EloquentGymProgressRepository.php:22-35` — persistencia de etapa.
- `src/Mazmorras/App/RegistrarResultadoMazmorra.php:23-43` — victoria/derrota.
- `src/Mazmorras/Infra/EloquentDungeonProgresoRepository.php:25-75` — progreso, log y cooldown.
- `src/Exploraciones/App/FinalizarExploracionHandler.php:90-122` — cierre de exploración.
- `src/Exploraciones/App/Procesamiento/GestorTickExploracion.php:260-283` — volcado de bitácora.
- `src/Exploraciones/Domain/ValueObjects/EventoExploracion.php:119-248` — contrato de evento.
- `src/Exploraciones/Domain/Recompensas/ClasificadorEventosExploracion.php:23-37` — derrotados desde bitácora.
- `src/Exploraciones/App/Finalizacion/MapeadorPokemonsDerrotados.php:28-45` — carga y normaliza derrotados.
- `src/Exploraciones/App/PersistirRecompensas.php:35-50` — escritura de recompensas.
- `src/Exploraciones/App/PersistirRecompensas.php:101-112` — inventario con `insertOrIgnore` + increment.
- `src/Exploraciones/App/PersistirRecompensas.php:118-130` — capturas en `reclutables`.
- `src/Exploraciones/App/PersistirRecompensas.php:153-155` — EXP de cuenta.
- `src/Exploraciones/Domain/CalculadorRecompensas.php:51-85` — cálculo de recompensas.
- `src/Exploraciones/Domain/EconomiaRecompensas.php:27-84` — políticas económicas.
- `app/Jobs/ActualizarPokedexJob.php:35-53` — visto/atrapado.
- `app/Support/CrearReclutado.php:20-36` — reclutado y marca de Pokédex.
- `src/Huevos/App/EclosionarHuevo.php:31-77` — eclosión idempotente.
- `src/Forja/App/Fabricar.php:29-64` — fabricación transaccional.
- `src/Granjas/App/PlantarBaya.php:29-91` — plantación con locks.
- `app/Http/Controllers/ReclutadoController.php:209-240` — evolución.

### Tiempo y concurrencia

- `config/app.php:70` — `Europe/Madrid`.
- `routes/console.php:13` — único schedule cada 5 min.
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php:87` — `fecha` en meta o `today()`.
- `src/Exploraciones/App/ProcesarExploracionesPendientes.php:155-166` — `Cache::lock` no bloqueante.
- `src/CombateEntrenadores/App/GeneradorEquipoEntrenador.php:77` — semilla determinista `crc32(...)`.
- `src/Shared/Infra/Inventario/InventarioJugadorEloquent.php:106-149` — bloqueo ordenado de inventario.
- `app/Models/Concerns/BelongsToUser.php:21-31` — scope global por usuario.

### UI

- `resources/views/layouts/app.blade.php:23-65` — nav y array de items.
- `resources/views/layouts/app.blade.php:54` — Misiones comentada.
- `resources/views/layouts/app.blade.php:28-42` — cosecha global.
- `resources/views/layouts/app.blade.php:98-129` — toast.
- `resources/views/habitats/show.blade.php:6-26` — `x-data` y partials de modales.
- `resources/views/habitats/show.blade.php:66-69` — `minLvls`.
- `resources/views/habitats/partials/columna-izquierda.blade.php:33-113` — botones de acción.
- `resources/views/habitats/partials/columna-derecha.blade.php:52-133` — panel de equipos.
- `resources/views/habitats/partials/columna-derecha.blade.php:225-255` — bloqueo por `min_lvl`.
- `resources/views/habitats/index.blade.php:30-55` — tarjetas de hábitat.
- `resources/views/misiones/index.blade.php:1-200` — stub de misiones y modal de aceptación.

### Arquitectura

- `docs/ddd.md` — estructura de módulo, entidades, colecciones, DTOs, excepciones, repositorio y rutas.
- `docs/conventions.md` — tipado, arrays, frontend, base de datos, tests y estructura de carpetas.
- `docs/architecture.md` — arquitectura por módulos y flujos actuales.
- `bootstrap/app.php:18-27` — mapa HTTP de excepciones.
- `app/Providers/AppServiceProvider.php:46-62` — bindings de repositorios y puertos.
- `composer.json:8-13` — Laravel 12, PHP 8.2+, Livewire 4.
- `src/*/context.md` — contexto por módulo, especialmente `src/Exploraciones/context.md`, `src/Huevos/context.md`, `src/Granjas/context.md`.

### Estado del árbol

- `git status` en `task-011`: 16 archivos modificados y varias carpetas sin seguimiento, incluidas `.opencode/work/TASK-032` a `TASK-043`.
- Los cambios modificados relevantes son de comparación de PC y UI de combate:
  - `app/Support/ReclutadoSerializer.php`
  - `resources/views/gimnasios/show.blade.php`
  - `resources/views/habitats/partials/modal-formacion.blade.php`
  - `resources/views/habitats/show.blade.php`
  - `routes/entrenadores.php`, `routes/gimnasios.php`, `routes/ruta.php`
  - `src/CombateRuta/...`, `src/CombateEntrenadores/...`, `src/Gimnasios/...`, `src/Pokemon/Domain/Stats/CalculadorCP.php`
- No hay cambios locales en un módulo de misiones porque no existe.
- `resources/views/misiones/index.blade.php` está versionado y limpio; no aparece como modificación local.

---

## Decisiones técnicas que debe tomar el arquitecto

1. **Semántica de captura**: `reclutables` (cola) vs `reclutados` (ropa real) vs `pokedex.atrapado`.
2. **Modelo de eventos**: ledger inmutable de eventos de juego, contadores incrementales por misión o ambos.
3. **Idempotencia**: clave única por `user_id` + `source_type` + `source_id`; definir qué es `source_id` en cada flujo.
4. **Punto de captura de eventos**: `PresentadorResultadoBatalla` como orquestador único, o servicios de aplicación por flujo.
5. **Periodicidad**: fecha local, UTC, ventana rodante, semana ISO o periodo configurable; una fila por periodo con unique.
6. **Resolución de periodo**: job programado, cálculo on-read o mixto; no asumir scheduler.
7. **Generación procedural**: semilla determinista por usuario+periodo+hábitat+slot; catálogo estático vs generación en runtime.
8. **Alcance de misión**: global, por hábitat, por tipo, por familia o por entrenador concreto.
9. **Recompensas**: reutilizar EXP/caramelos/items/capturas/reclutados existentes o introducir una entidad nueva; definir si hay recompensa manual.
10. **Reclamación**: automática al completar vs manual; transacción y bloqueo de la fila de misión.
11. **Desbloqueo de hábitats**: por usuario o por campaña; tabla nueva vs columnas en `habitats`; relación con `min_lvl_1/2/3`.
12. **Historia**: misiones únicas globales, por usuario o por servidor; necesidad de tablas de diálogo/narrativa.
13. **UI**: revivir `/misiones` como página, panel contextual en hábitat o híbrido; qué hacer con el stub actual.
14. **Retención**: cuánto tiempo se guardan eventos de misión y logs de combate.
15. **Protección contra farming**: límites por periodo, fuentes válidas y si las derrotas cuentan.
16. **Concurrencia**: política de locks para progreso y reclamación; uso de `lockForUpdate` y unique.
17. **Compatibilidad con sesión de batalla**: no almacenar progreso de misión en la sesión serializada; solo contexto mínimo.
18. **Contrato de “misión completada”**: qué ocurre si el jugador pierde, cancela o la misión expira a mitad de batalla.