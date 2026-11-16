# TASK-035 — Análisis: comparación aproximada de PC en "Configurar Formación"

- **Fecha:** 2026-11-09
- **Rama:** `task-011`
- **Estado:** ANALYSIS (sin implementación)
- **Alcance:** ruta 3v3, entrenadores de hábitat y gimnasios

## 1. Resumen ejecutivo

1. **El concepto de CP ya existe** y está centralizado en `Src\Pokemon\Domain\Stats\CalculadorCP`; se usa para ordenar reclutados y mostrarlos en `/equipos`. No debe crearse un segundo CP.
2. **Los modales actuales no reciben datos suficientes para calcular CP en frontend**: reciben el equipo con `reclutado.pokemon`, pero sin `pokemon.stats`, sin nivel calculado y sin CP. El cálculo debe hacerse en backend.
3. **Ruta 3v3:** el rival se genera con azar real en cada inicio de combate. No es posible conocer el rival exacto antes de combatir; solo un **promedio ponderado del pool** del hábitat/nivel.
4. **Entrenador de hábitat:** el rival es determinista por `fecha + hábitat + nivel + índice`; se puede calcular su PC exacto antes del combate, pero devolviendo **solo el agregado**.
5. **Gimnasio:** el rival sale del catálogo `gyms/gym_stages` y de un nivel escalado. Su PC es calculable, pero la UI oculta deliberadamente equipo y nivel rival; mostrar PC es una **decisión de producto** que filtra fuerza, no composición.
6. **Recomendación:** endpoints backend de solo lectura que devuelvan `{equipo_pc, rival_pc, aproximado, ...}`; el frontend solo presenta. No calcular CP en Alpine/JS.

## 2. Requisitos

### Funcionales

- Mostrar, antes de confirmar combate, una comparación de PC entre el equipo seleccionado y el rival en:
  - combate de ruta 3v3;
  - combate contra entrenador de hábitat;
  - combate de gimnasio.
- Indicar claramente cuándo el PC rival es aproximado.
- Permitir al jugador cancelar y evitar el combate; el botón "Cancelar" ya existe en ambos modales.
- No revelar nombres, especies, tipos, niveles individuales, IV/EV ni movimientos del rival.
- Mostrar estados de carga, error y datos insuficientes.

### No funcionales

- Reutilizar la fórmula CP existente y las fórmulas reales de stats (`BattleStats`).
- Cálculo en backend; sin duplicar fórmulas de dominio en JavaScript.
- Sin migraciones ni cambios de esquema.
- Sin cambios de balance, fórmulas de combate ni dependencias.
- Accesibilidad: texto alternativo, `aria-live`, no depender solo del color.
- Rendimiento aceptable: una petición por apertura de modal o selección de equipo; cache opcional.
- Compatibilidad con Laravel 12 / PHP 8.4 / PostgreSQL y con el patrón Blade + Alpine existente.

### Criterios de aceptación propuestos

- Al abrir el modal con un equipo de exactamente 3 miembros, se muestra el PC del equipo y el del rival.
- En ruta, el rival se etiqueta como aproximado y no se muestra composición.
- En entrenador y gimnasio, no se muestra composición ni nivel rival; solo el agregado.
- Si no hay equipo, el equipo está incompleto, el rival no está definido o falla la petición, la UI muestra un estado explícito y **no bloquea** el combate por un fallo del indicador.
- El PC mostrado para el equipo propio coincide con el CP que ya muestra `/equipos` para los mismos reclutados y nivel de usuario.
- La formación vanguardia/retaguardia no altera el PC mostrado (solo la posición de combate).

### Ambigüedades

- ¿Se muestra un número exacto, un número redondeado o una banda cualitativa?
- ¿Se muestra un veredicto ("favorable/parejo/desfavorable") o solo los dos números?
- En gimnasio, ¿el diseño que oculta el equipo rival permite revelar un PC agregado? Requiere aprobación de producto.
- En ruta, ¿promedio esperado o rango (p. ej. "≈ 800–1.400")?
- ¿Debe mostrarse el PC por miembro del equipo propio o solo el total?
- ¿Qué hacer si el equipo tiene menos de 3 miembros: ocultar comparación o mostrar suma incompleta?

## 3. Alcance afectado

### Módulos

- `Src\Equipos` — obtención de equipos y miembros.
- `Src\CombateRuta` — generación del rival salvaje y endpoint de inicio.
- `Src\CombateEntrenadores` — generación determinista y endpoint de inicio.
- `Src\Gimnasios` — catálogo de etapas, escalado de nivel y endpoint de inicio.
- `Src\Battle` / `Src\Pokemon\Domain\Stats` — stats de combate y CP.
- `App\Support` — serialización/presentación compartida.
- Vistas `resources/views/habitats` y `resources/views/gimnasios`.

### Archivos relevantes

- `resources/views/habitats/partials/modal-formacion.blade.php`
- `resources/views/habitats/show.blade.php`
- `resources/views/gimnasios/show.blade.php`
- `app/Http/Controllers/HabitatsController.php`
- `app/Http/Controllers/GimnasiosViewController.php`
- `src/CombateRuta/Infra/Controllers/CombateRutaController.php`
- `src/CombateRuta/App/GeneradorEquipoRuta.php`
- `src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php`
- `src/CombateEntrenadores/App/GeneradorEquipoEntrenador.php`
- `src/Gimnasios/Infra/Controllers/GimnasioController.php`
- `src/Gimnasios/App/GeneradorPokemonGimnasio.php`
- `src/Gimnasios/App/ObtenerGimnasioDetalle.php`
- `src/Pokemon/Domain/Stats/CalculadorCP.php`
- `src/Pokemon/Domain/Stats/BattleStats.php`
- `app/Support/ReclutadoSerializer.php`
- `routes/ruta.php`, `routes/entrenadores.php`, `routes/gimnasios.php`

### Tablas / contratos

- Tablas existentes: `teams`, `team_members`, `reclutados`, `pokemon`, `pokemon_stats`, `gyms`, `gym_stages`.
- **No se propone ninguna migración ni cambio de esquema.**
- Contrato nuevo propuesto: respuesta agregada de PC por contexto.
- Contratos existentes que no deben romperse:
  - `$teams` enviado a ambos modales;
  - `GET /api/habitats/{habitat}/entrenadores`;
  - `GET /api/gimnasios/{gym}`;
  - `POST .../combatir` y `POST .../ruta/iniciar`.

### Vistas / endpoints

- Añadir bloque de comparación en los dos modales.
- Nuevos endpoints de solo lectura, uno por contexto (ver §6).

## 4. Evidencia

### 4.1. De dónde salen los datos de nuestro equipo en cada modal

**Hábitats (ruta y entrenadores):**

- El modal solo recorre `selectedTeamMembers` y muestra sprite/nombre y toggle de formación: `resources/views/habitats/partials/modal-formacion.blade.php:18-38`.
- No hay ninguna referencia a stats, nivel ni CP en el modal: `modal-formacion.blade.php:16-39`.
- El estado Alpine recibe `teams: @json($teams)` en `resources/views/habitats/show.blade.php:50`.
- `selectedTeamMembers` filtra miembros con `reclutado` y `reclutado.pokemon`, y ordena por `slot`: `habitats/show.blade.php:85-90`.
- El controlador envía `teams` desde `ObtenerEquipos`: `app/Http/Controllers/HabitatsController.php:81-91`.
- `ObtenerEquipos` delega en el repositorio: `src/Equipos/App/ObtenerEquipos.php:17-20`.
- El repositorio carga **solo** `members.reclutado.pokemon`, sin `stats` ni `types`: `src/Equipos/Infra/EloquentTeamRepository.php:16-23`.
- `TeamAggregate` conserva miembros como modelos Eloquent, deuda documentada: `src/Equipos/Domain/TeamAggregate.php:9-20`.
- Por tanto, el JSON del modal incluye `reclutado` (con `evs`, `ivs`, `exp`, etc.) y `reclutado.pokemon` (atributos de especie), pero **no incluye `pokemon.stats` ni un campo `cp`**.

**Gimnasios:**

- El modal itera `selectedTeamMembers` y muestra sprite/nombre y posición: `resources/views/gimnasios/show.blade.php:189-218`.
- El estado recibe `teams: @json($teams)`: `gimnasios/show.blade.php:259`.
- El getter de miembros filtra `reclutado && reclutado.pokemon`: `gimnasios/show.blade.php:268-276`.
- El controlador envía `teams` desde `ObtenerEquipos`: `app/Http/Controllers/GimnasiosViewController.php:22-29`.

**CP ya existente:**

- `ReclutadoSerializer` añade `stats_calculados` y `cp` al payload de reclutados: `app/Support/ReclutadoSerializer.php:76-77`.
- `cpDe()` construye `BattleStats` con stats base, EVs reales, nivel del usuario e IVs, y llama a `CalculadorCP::calcular($stats, $evTotal)`: `ReclutadoSerializer.php:359-377`.
- El listado se ordena por `cp` descendente: `ReclutadoSerializer.php:103-117`.
- La fórmula CP está centralizada: `src/Pokemon/Domain/Stats/CalculadorCP.php:7-40`.
- La fórmula de stats de combate está en `BattleStats`: `src/Pokemon/Domain/Stats/BattleStats.php:54-81`.

**Nivel usado en combate:**

- El jugador se construye con `nivel: $nivelJugador` en entrenadores: `src/CombateEntrenadores/App/IniciarCombateEntrenador.php:50-53`.
- También en ruta: `src/CombateRuta/App/IniciarCombateRuta.php:44-56`.
- También en gimnasio: `src/Gimnasios/App/IniciarCombateGimnasio.php:71-74`.
- `User::nivel()` deriva el nivel de `experiencia`: `app/Models/User.php:55-61`.
- Por tanto, el CP del equipo propio calculado al nivel del usuario coincide con el CP que ya muestra `/equipos` para los mismos reclutados.

### 4.2. Qué datos del rival existen antes de combatir

| Contexto | ¿Rival definido antes? | ¿Determinista? | Datos disponibles hoy | ¿PC exacto posible? |
|---|---|---|---|---|
| Ruta 3v3 | No; se genera al iniciar | No, azar real | Pool del hábitat/nivel en BD; endpoint `ruta/rivales` deprecated | No; solo promedio |
| Entrenador hábitat | Sí, reproducible | Sí, semilla por fecha | Solo índice y estado; sin equipo | Sí, agregado |
| Gimnasio | Sí, catálogo DB | Sí | Detalle oculta equipo y nivel rival | Sí, agregado |

**Ruta 3v3:**

- El generador documenta selección ponderada **con reemplazo** y rival variable en cada combate: `src/CombateRuta/App/GeneradorEquipoRuta.php:23-38`.
- Usa `Randomizer` sin semilla: `GeneradorEquipoRuta.php:178-188`.
- Selecciona 3 con `elegirConAleatorio` sobre el pool: `GeneradorEquipoRuta.php:80-95`.
- El pool se carga por hábitat y nivel de pivote, con stats y tipos: `GeneradorEquipoRuta.php:133-153`.
- La ponderación usa `capture_rate/hatch` vía `PoolHabitat::ponderado`: `GeneradorEquipoRuta.php:155-176`.
- El inicio de combate vuelve a generar el rival: `src/CombateRuta/App/IniciarCombateRuta.php:51-56`.
- Existe `GET /api/habitats/{habitat}/ruta/rivales`, pero está marcado deprecated y devuelve `nombre`, `species_id` y `nivel`: `src/CombateRuta/Infra/Controllers/CombateRutaController.php:30-54`. **No debe usarse para la UI del comparador**, porque revela composición y además genera un equipo distinto al que se combatirá.
- Conclusión: el PC rival de ruta solo puede ser **aproximado por promedio ponderado del pool**.

**Entrenador de hábitat:**

- La consulta de entrenadores no revela el equipo: `src/CombateEntrenadores/App/ObtenerEntrenadoresHabitat.php:14-17`.
- La API devuelve nivel, índice y desbloqueo: `src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php:23-38`; la vista consume `entrenador.indice` y `entrenador.desbloqueado`: `resources/views/habitats/partials/columna-derecha.blade.php:296-310`.
- El generador usa semilla `crc32("{$habitatId}|{$nivel}|{$entrenadorIndex}|{$fecha}")`: `src/CombateEntrenadores/App/GeneradorEquipoEntrenador.php:76-78`.
- Con 3+ especies únicas usa `shuffleArray` con `Mt19937` sembrado: `GeneradorEquipoEntrenador.php:150-160`.
- Con menos de 3 especies repite con ponderado determinista: `GeneradorEquipoEntrenador.php:162-175`.
- El combate genera con la misma fecha, hábitat, nivel, índice y nivel de jugador: `IniciarCombateEntrenador.php:55-69`.
- Conclusión: el PC exacto del rival es reproducible antes del combate; se puede devolver solo la suma.

**Gimnasio:**

- El catálogo se persiste en `gyms` y `gym_stages`; cada etapa tiene vanguardia/retaguardia por `species_id`: `src/Gimnasios/Infra/EloquentGymCatalogoRepository.php:134-153`.
- `Gimnasio::equipoEtapa($etapa)` devuelve el equipo de la etapa: `src/Gimnasios/Domain/Gimnasio.php:27-30`.
- El nivel rival escala desde el nivel mínimo: `src/Shared/Domain/EscaladorNivelRival.php:8-23`.
- El perfil IV/EV depende del índice del gimnasio y de si es líder: `src/Shared/Domain/PoliticaIvsEvs.php:62-80`; se aplica en `IniciarCombateGimnasio.php:66-69`.
- El generador construye el rival desde el catálogo y normaliza el roster a 3: `src/Gimnasios/App/GeneradorPokemonGimnasio.php:50-103` y `:117-163`.
- El detalle oculta expresamente equipo y nivel rival: `src/Gimnasios/App/ObtenerGimnasioDetalle.php:14-17`; el DTO no incluye esos campos: `src/Gimnasios/Domain/DataTransferObjects/DetalleGimnasio.php:42-57`.
- Conclusión: el PC exacto es calculable en backend, pero exponerlo contradice parcialmente el diseño de "sin revelar su equipo". Debe aprobarse como decisión de producto y mostrarse solo el agregado, nunca composición ni nivel rival.

### 4.3. Fórmulas reales

**Stats de combate (`BattleStats`):**

- HP: `floor(((2 * base + IV*5 + floor(EV/4)) * nivel / 100) + nivel + 10)`: `src/Pokemon/Domain/Stats/BattleStats.php:73-76`.
- Resto: `floor(((2 * base + IV*5 + floor(EV/4)) * nivel / 100) + 5)`: `BattleStats.php:78-81`.
- La constante `IV = 5`, `EV = 4`: `BattleStats.php:9-10`.
- Los stats se precalculan al nivel del combate en `EquipoBatalla::fromData`: `src/Battle/Domain/EquipoBatalla.php:157-193`.
- `DatosPokemonBatalla` transporta `nivel`, `evs` e `ivs`: `src/Battle/Domain/DatosPokemonBatalla.php:46-48` y `:60-61`.

**CP (`CalculadorCP`):**

```text
CP = floor( (Σ stats_combate) * nivel * 6 / 100
          + EV_total * ((nivel / 4) / 100 + 2) )
```

- Implementación: `src/Pokemon/Domain/Stats/CalculadorCP.php:28-40`.
- `EV_total` es la suma de los seis EVs (0–1530), tal como pasa `ReclutadoSerializer.php:374-376`.
- Unidades: PC entero por Pokémon; el PC del equipo es la suma de sus 3 miembros.
- No es probabilidad de victoria. No incorpora tipos, movimientos, habilidades, objetos, formación ni efectividad.

**Ejemplo verificable (Bulbasaur, nivel 5, IV/EV 0):**

Stats base: 45/49/49/65/65/45. A nivel 5:
- HP = 19, Atk = 9, Def = 9, SpA = 11, SpD = 11, Spe = 9 → suma = 68.
- CP = `floor(68 * 5 * 6 / 100) = floor(20.4) = 20`.
- Con EV_total = 0, el segundo término es 0.

**Ejemplo ilustrativo de escala (nivel 50, suma de stats de combate 600):**

- Base = `600 * 50 * 6 / 100 = 1.800`.
- Con EV_total = 510: extra = `510 * ((50/4)/100 + 2) = 510 * 2,125 = 1.083,75`.
- CP = `floor(1.800 + 1.083,75) = 2.883`.
- Equipo de 3 ≈ 5.400–8.649 según EVs.

**Observación importante:** el término de EVs es muy dominante. Un rival de gimnasio con perfil `Completo` tiene EV_total = 1.530; un jugador con EVs parciales puede parecer mucho más débil aunque sus stats base sean similares. Esto ya ocurre en el CP actual de `/equipos`. **No debe cambiarse la fórmula sin aprobación expresa**; si el resultado no es explicable para el jugador, la alternativa es mostrar bandas o calibrar el indicador como decisión de producto.

### 4.4. Contratos actuales que llegan al modal

- `$teams` es un array de `TeamAggregate` con miembros Eloquent; no incluye `cp`, `nivel` calculado ni `pokemon.stats`: `EloquentTeamRepository.php:16-23`, `TeamAggregate.php:9-20`.
- El modal accede a `miembro.reclutado.pokemon` y `miembro.reclutado.pokemon_id`: `modal-formacion.blade.php:21-23`; `gimnasios/show.blade.php:194-201`.
- La API de entrenadores solo devuelve índice/desbloqueo: `EntrenadorController.php:32-37`.
- La API de gimnasio devuelve detalle sin equipo rival ni nivel rival: `DetalleGimnasio.php:42-57`.
- `GET /ruta/rivales` devuelve composición y está deprecated: `CombateRutaController.php:30-54`.

## 5. Propuesta de PC aproximado

### 5.1. Definición

- **PC del equipo** = suma del CP de sus 3 miembros, calculado con el nivel del usuario y los EVs/IVs reales de cada reclutado.
- **PC rival exacto (entrenador/gimnasio)** = suma del CP de los 3 combatientes que el generador producirá, usando el mismo nivel, perfil IV/EV y stats base.
- **PC rival aproximado (ruta)** = `3 × Σ(p_i × CP_i)`, donde `p_i = peso_i / Σ pesos` del pool del hábitat/nivel y `CP_i` se calcula con el perfil IV/EV de ruta.
- **Redondeo de presentación:** a la decena o centena, con prefijo `≈` cuando sea promedio. El backend puede devolver el entero exacto y la UI redondear; o el backend devuelve ya redondeado. Decisión de producto.

### 5.2. Fórmula de ruta

Para cada especie del pool:
1. Cargar stats base (`pokemon.stats`).
2. Aplicar `PoliticaIvsEvs::perfilRuta($nivel)` → IVs/EVs.
3. Construir `BattleStats(base, evs, nivelJugador, ivs)`.
4. `CP_i = CalculadorCP::calcular($stats, array_sum($evs->toArray()))`.
5. Peso `w_i = capture_rate / hatch` (con las reglas de `PoolHabitat::ponderado`; no reimplementar).
6. `PC_rival = round(3 * Σ(w_i / Σw * CP_i))`.

Ejemplo: pool de 2 especies con pesos iguales y CP 100 y 200 → media por slot 150 → equipo aproximado 450.

### 5.3. Qué NO incluye el indicador

- No incluye tipos, efectividad, movimientos, habilidades, objetos, clima, formación ni IA.
- No es una probabilidad de victoria.
- La formación vanguardia/retaguardia no altera el PC: solo cambia la posición y el targeting. Evidencia: `ConstruirEquipoJugador.php:79` usa la formación para `posicionPara`, y `:154-167` clasifica la posición; no modifica stats.

## 6. Dónde calcular y qué contrato usar

### 6.1. Recomendación: backend

Motivos:

- El modal no tiene stats base ni nivel calculado; el frontend no puede calcular CP correctamente.
- Duplicar `BattleStats`/`CalculadorCP` en JS crearía dos fuentes de verdad.
- El backend ya tiene los generadores y el catálogo; puede reproducir el rival exacto de entrenador/gimnasio.
- Permite validar propiedad del equipo y no confiar en el cliente.

### 6.2. Endpoints propuestos

Separados por contexto, coherentes con los controladores existentes:

```http
GET /api/habitats/{habitat}/ruta/pc?team_id={id}&nivel={1..3}
GET /api/habitats/{habitat}/entrenadores/{nivel}/{trainer}/pc?team_id={id}
GET /api/gimnasios/{gym}/pc?team_id={id}
```

- Todos bajo el grupo `auth` de `routes/web.php`.
- Validar `team_id` con `Rule::exists('teams','id')->where('user_id', Auth::id())`.
- Validar `nivel` 1–3 y `trainer` 1–3; en entrenador, comprobar `desbloqueado`/`haGanadoHoy` si se quiere ocultar PC de entrenadores ya derrotados.
- En gimnasio, recalcular la etapa actual en servidor; no aceptar `etapa` del cliente.
- GET de solo lectura; no requiere CSRF. Si se prefiere POST por semántica, añadir CSRF, pero no es necesario.

### 6.3. Respuesta propuesta

```json
{
  "equipo": {
    "pc": 1240,
    "miembros": 3,
    "completo": true
  },
  "rival": {
    "pc": 1050,
    "aproximado": true,
    "metodo": "promedio_pool"
  },
  "comparacion": {
    "diferencia": 190,
    "ratio": 0.85,
    "banda": "parejo"
  }
}
```

- `rival.metodo`: `promedio_pool` (ruta), `generador_determinista` (entrenador), `catalogo_etapa` (gimnasio).
- No devolver nombres, `species_id`, tipos, niveles individuales, IV/EV ni movimientos del rival.
- Si el equipo no tiene 3 miembros: `equipo.completo: false`, `equipo.pc: null` o suma marcada como incompleta; la UI no muestra comparación.
- Si no hay pool/rival: `rival.pc: null`, `rival.motivo: "sin_rivales"`.
- Si faltan stats: `equipo.pc: null`, `motivo: "stats_no_disponibles"`.

### 6.4. Archivos a tocar en implementación (propuesta, no ejecutada)

- Nuevo servicio compartido: `app/Support/CalculadoraPcFormacion.php` (o nombre equivalente) + DTO readonly `app/Support/ComparacionPc.php`.
- Nuevos casos de uso de lectura:
  - `src/CombateRuta/App/CalcularPcRuta.php`
  - `src/CombateEntrenadores/App/CalcularPcEntrenador.php`
  - `src/Gimnasios/App/CalcularPcGimnasio.php`
- Controladores:
  - `src/CombateRuta/Infra/Controllers/CombateRutaController.php` (método `pc`)
  - `src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php` (método `pc`)
  - `src/Gimnasios/Infra/Controllers/GimnasioController.php` (método `pc`)
- Rutas:
  - `routes/ruta.php`, `routes/entrenadores.php`, `routes/gimnasios.php`.
- Vistas:
  - `resources/views/habitats/partials/modal-formacion.blade.php`
  - `resources/views/gimnasios/show.blade.php`
  - Estado Alpine en `resources/views/habitats/show.blade.php` y `resources/views/gimnasios/show.blade.php`.
- Sin migraciones, sin dependencias, sin cambios en fórmulas de combate.

### 6.5. Alternativas descartadas

1. **Calcular en frontend con `@json($teams)`**: descartada; faltan stats base y nivel, y duplicaría fórmulas.
2. **Añadir `cp` solo al payload `$teams`**: sirve para el equipo propio, pero no resuelve el rival; obligaría a un segundo contrato igualmente.
3. **Usar `GET /ruta/rivales` como preview**: descartada; revela composición y genera un rival distinto al del combate.
4. **Generar y guardar el rival de ruta antes de confirmar** para que coincida: descartada para MVP; cambia el flujo de batalla/sesión y aumenta el riesgo. Si se quisiera PC exacto en ruta, sería una decisión de arquitectura aparte.
5. **Simular el combate para dar probabilidad de victoria**: fuera de alcance; coste alto y resultado difícil de explicar.

## 7. UI/UX, estados y accesibilidad

### 7.1. Ubicación

- Insertar el bloque entre la lista de miembros y los botones, en `modal-formacion.blade.php` (después de `:39`, antes de `:41`).
- Insertar el bloque equivalente en `gimnasios/show.blade.php` (después de `:218`, antes de `:220`).

### 7.2. Copy propuesto

- Equipo propio: `PC de tu equipo: 1.240`.
- Rival exacto: `PC del rival: 1.050`.
- Rival aproximado: `PC estimado del rival: ≈ 1.050` + nota `Estimación basada en los Pokémon del hábitat; el rival se decide al combatir.`
- Gimnasio: `PC estimado del rival: 1.050` + nota `No se muestra su equipo.`
- Si no hay datos: `No se pudo calcular la comparación. Puedes combatir igualmente.`

### 7.3. Estados

| Estado | Comportamiento |
|---|---|
| Sin equipo seleccionado | Ocultar comparación; mostrar "Selecciona un equipo" (ya existe en hábitats; gimnasio permite abrir sin equipo) |
| Equipo con menos de 3 miembros | No mostrar comparación; avisar "El equipo necesita 3 miembros para combatir" |
| Cargando | `role="status"` + spinner + texto "Calculando PC..." |
| Error de red/422 | Mensaje inline; no bloquear el botón de combate salvo la validación existente |
| Rival no definido / pool vacío | "No hay rival disponible en este nivel" |
| Gimnasio bloqueado/completado | No abrir modal o no mostrar comparación |
| Entrenador ya derrotado | No permitir selección; no mostrar PC |

### 7.4. Accesibilidad

- Contenedor `role="group"` con `aria-labelledby`.
- Resultado en `aria-live="polite"` para anunciar el PC cuando llegue.
- Cargador con `role="status"` y texto visible.
- No usar solo color para el veredicto; añadir texto ("Parejo", "Desfavorable").
- Si se usa barra, `role="img"` con `aria-label` completo; o mejor, texto + `dl`.
- Respetar `prefers-reduced-motion` si se anima la barra.
- Mantener `Escape` y click en scrim; ya existen en ambos modales.
- Deuda existente: los modales no tienen focus trap; no ampliar alcance salvo que se apruebe.

## 8. Riesgos

| Severidad | Riesgo | Impacto | Mitigación |
|---|---|---|---|
| Alto | Ruta no determinista: mostrar un número como si fuera el rival real | El jugador decide con información falsa | Etiquetar `≈`, explicar que es promedio del hábitat; no usar `ruta/rivales` |
| Alto | Gimnasio oculta deliberadamente equipo y nivel rival | Rompe una decisión de diseño y filtra fuerza | Aprobación de producto; devolver solo agregado; considerar banda en vez de número |
| Alto | El término EV domina el CP | Comparaciones contraintuitivas | Validar con datos reales; no cambiar fórmula sin aprobación; opción de bandas/redondeo |
| Medio | Duplicar `BattleStats`/`CalculadorCP` en JS | Dos verdades y diferencias | Backend único; no calcular en Alpine |
| Medio | IDOR en `team_id` | Ver PC de equipos ajenos | `Rule::exists(...)->where('user_id', Auth::id())` |
| Medio | Determinismo de entrenador depende del orden del pool con <3 especies | Preview distinto al combate | Reutilizar el mismo servicio; orden explícito en la consulta o validación |
| Medio | Cambio de etapa de gimnasio entre preview y combate | PC mostrado obsoleto | Recalcular en servidor al abrir y al combatir; no confiar en el cliente |
| Medio | Coste de cargar pool/stats en cada apertura | Latencia | Cache por `habitat|nivel|nivelJugador|perfil`; carga bajo demanda |
| Bajo | Nivel de usuario cambia entre carga y combate | PC desactualizado | Refetch al abrir modal; calcular siempre con el nivel actual del servidor |
| Bajo | Formato/localización de números | Mala lectura | `toLocaleString('es-ES')` o formateo backend |
| Bajo | Accesibilidad si solo se usa color/barra | Exclusión | `aria-live`, texto, `role="status"` |

## 9. Duplicidades y deuda técnica

- **CP ya existe**; no crear otro concepto. Reutilizar `CalculadorCP` y `BattleStats`.
- **Dos modales duplican** la lógica de miembros, sprites y toggle de formación. Se podría extraer un partial de "comparación PC", pero no es obligatorio; hacerlo solo si reduce riesgo real.
- **`GET /ruta/rivales` está deprecated** y sigue devolviendo composición; no construir la feature sobre él. Valorar su eliminación en otra tarea.
- **`TeamAggregate` mantiene modelos Eloquent** (deuda documentada). No aprovechar para inyectar CP en ese payload sin una decisión de contrato.
- **`DetalleGimnasio` no incluye nivel rival ni equipo** por diseño; añadir PC requiere un contrato nuevo, no modificar el existente.
- **`ReclutadoSerializer::statsBaseValue` es privado**; si se necesita desde otro punto, extraer un mapper compartido en lugar de duplicar el `match` de stats.

## 10. Opciones

1. **Backend + endpoints agregados (recomendada).**
   - Reutiliza fórmulas, valida propiedad, no revela composición.
   - Ruta: promedio ponderado; entrenador/gimnasio: suma exacta.
2. **Backend + bandas cualitativas.**
   - En vez de números, "Bajo/Medio/Alto" o "Favorable/Parejo/Desfavorable".
   - Más seguro para gimnasio y para la varianza de ruta; menos informativo.
3. **Híbrido: enriquecer `$teams` con `cp` del jugador + endpoint solo para rival.**
   - Evita una petición para el equipo propio, pero sigue necesitando backend para el rival y duplica contratos.
4. **Frontend con datos existentes.**
   - Descartada: faltan stats/nivel; exigiría cargar stats y duplicar fórmulas.
5. **Preview de ruta con equipo generado y almacenado.**
   - Daría PC exacto, pero cambia el flujo de batalla/sesión y el diseño de "el rival se decide al combatir". Descartada para MVP.

## 11. Suposiciones

- El nivel de combate del jugador es siempre `User::nivel()` en los tres contextos (verificado en los tres `IniciarCombate*`).
- El CP canónico es el de `CalculadorCP`; no se desea un segundo indicador.
- La ponderación del pool de ruta es `capture_rate/hatch` según la documentación de `GeneradorEquipoRuta` y `PoolHabitat::ponderado`.
- El equipo seleccionado tiene exactamente 3 miembros cuando se muestra la comparación.
- Se acepta redondear el PC para presentación.
- No se necesita persistir el PC; se calcula bajo demanda.

## 12. Preguntas abiertas / bloqueantes

1. **Gimnasio:** ¿se permite mostrar un PC agregado aunque la UI oculte el equipo y el nivel rival? Si no, ¿se muestra solo una banda?
2. **Ruta:** ¿promedio esperado, rango o banda? La varianza del pool puede ser alta.
3. **Veredicto:** ¿se muestra "favorable/parejo/desfavorable"? ¿Qué umbrales? Propuesta inicial: `rival/equipo < 0.8` favorable, `0.8–1.2` parejo, `>1.2` desfavorable; requiere aprobación.
4. **Redondeo:** ¿a la decena, a la centena o sin redondear?
5. **Equipo propio:** ¿solo total o también PC por miembro?
6. **Equipo incompleto:** ¿ocultar comparación o mostrar suma parcial con aviso?
7. **Entrenador derrotado:** ¿ocultar PC o mostrarlo igualmente?
8. **Fórmula:** ¿se acepta el término EV actual aunque domine la comparación? Si no, es una decisión de balance/fórmula que requiere aprobación separada.

## 13. Recomendación

Implementar la opción 1 con estas condiciones:

- Backend como única fuente de cálculo; reutilizar `CalculadorCP` y `BattleStats`.
- Endpoints agregados por contexto, con validación de propiedad del equipo.
- Ruta: promedio ponderado etiquetado como `≈`; nunca composición.
- Entrenador/gimnasio: suma exacta del rival, pero devolver solo el agregado; en gimnasio, aprobar antes si se muestra número o banda.
- UI: bloque informativo, estados de carga/error/vacío, `aria-live`, sin color como única señal.
- No tocar fórmulas, balance, esquema, dependencias ni tests.
- Secuenciar después de TASK-034; no hay solapamiento detectado con sus vistas de recompensas, pero TASK-033 no se pudo revisar.

## Verificación ejecutada

- `git branch --show-current` → `task-011`.
- `git status --short` → cambios preexistentes detectados, incluidos dos presentadores no citados en el contexto.
- Lectura estática de modales, estados Alpine, controladores, rutas, generadores, DTOs, `BattleStats`, `CalculadorCP`, `PoliticaIvsEvs`, `EscaladorNivelRival` y serializadores.
- `grep`/`find` para localizar CP, stats, generadores, rutas y usos de `teams`.
- No se ejecutaron tests, migraciones, seeders ni comandos destructivos.
- No se ejecutó `php artisan route:list`; las rutas se verificaron leyendo `routes/web.php`, `routes/ruta.php`, `routes/entrenadores.php` y `routes/gimnasios.php`.
- No se consultó la base de datos.
