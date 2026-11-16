# TASK-034 — Análisis

- **Estado:** COMPLETED
- **Analista:** `analyst` (sesión `ses_e40c5b0ebffeVwb2VcgAavyu2g`)
- **Nota:** resultado del analista, sin alterar sus conclusiones.

---

# TASK-034 — Análisis de presentación de EXP tras combate

> Análisis de solo lectura. No se modificó ningún archivo. Rama verificada: `task-011`.

## 1. Resumen ejecutivo

La EXP ya se persiste correctamente en `PersistirRecompensas::persistir()`: la cuenta sube `users.experiencia` y las Sendas acumulan en `player_type_paths.exp`. Sin embargo, el modal de victoria solo muestra `exp_total` y `exp_miembro` como números (`resources/views/livewire/combate.blade.php:139-146` y `:224-232`), sin nivel, sin barra y sin animación. No existe ningún snapshot antes/después de la concesión.

La opción menos invasiva y más fiable es **capturar dos instantáneas de solo lectura alrededor de la única llamada que persiste recompensas**, desde `PresentadorResultadoBatalla`, y calcular el progreso por diferencia real de BD. Así:

- No se toca `PersistirRecompensas`.
- No se toca ningún DTO de dominio (`DatosModalVictoria`, `ResultadoRuta`).
- No se duplica ninguna escritura ni se recalcula la economía.
- Funciona igual para ruta, entrenador, gimnasio, mazmorra y, si se decide, exploración.
- Soporta múltiples level-ups porque compara la EXP real antes y después.

El modal recibiría una estructura aditiva `progreso` con la cuenta y las Sendas afectadas. Blade solo pinta; Alpine anima los segmentos y respeta `prefers-reduced-motion`.

**Requisitos detectados**

- Funcionales: mostrar nivel de cuenta y Sendas de los tipos que ganan EXP; animar subida; al subir de nivel vaciar y reajustar la barra; soportar varios niveles en una ganancia; mantener el modal actual y sus recompensas.
- No funcionales: no recalcular ni volver a conceder EXP; no introducir lógica de negocio en Blade; compatible con Livewire 4.4, Alpine 3.16, Tailwind 4 y Bootstrap 5.3; accesible y con movimiento reducido; sin nuevas dependencias.
- Criterios de aceptación del análisis: flujo actual evidenciado, snapshots definidos, estructura de presentación concreta, animación con trade-offs, archivos/riesgos/verificación y casos límite.
- Ambigüedades: alcance exacto (solo modal de combate o también listado de exploraciones), número máximo de Sendas visibles y política ante muchos level-ups.

## 2. Flujo actual por tipo de combate (evidencia)

### 2.1 Punto común

`GestorAccionBatalla::terminar()` cierra la batalla y llama al presentador:

- `app/Livewire/Combate/Soporte/GestorAccionBatalla.php:58-79` — marca `battle_over`, guarda sesión y ejecuta `PresentadorResultadoBatalla::procesar()`.
- `app/Livewire/Combate/Soporte/GestorAccionBatalla.php:68-71` — asigna `rewards` y `habitatId` al componente Livewire.
- `app/Livewire/Combate.php:31` — `public array $rewards = [];`.
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php:44-66` — despacha por `meta['tipo']`: `gimnasio`, `mazmorra`, `ruta` o entrenador por defecto.

### 2.2 Ruta (pokémon salvajes)

- `PresentadorResultadoBatalla.php:243-273` — construye especies y flags shiny del rival y llama a `RegistrarResultadoRuta`.
- `src/CombateRuta/App/RegistrarResultadoRuta.php:30-53` — si pierde devuelve `null`; si gana delega en `OtorgarRecompensasRuta::otorgar()`.
- `src/CombateRuta/App/OtorgarRecompensasRuta.php:73-179` — calcula recompensas, llama a `PersistirRecompensas` en `:147`, crea reclutados shiny y devuelve `ResultadoRuta`.
- `src/CombateRuta/App/OtorgarRecompensasRuta.php:170-178` — construye `ResultadoRuta` con `expTotal`, `expMiembro`, caramelos y capturas. **No incluye EXP de tipo**.
- `src/CombateRuta/Domain/DataTransferObjects/ResultadoRuta.php:42-52` — `aArray()` devuelve `exp_total`, `exp_miembro`, `caramelos_familia`, `caramelos_ev`, `caramelos_tipo`, `capturas`.
- `PresentadorResultadoBatalla.php:268-271` — `rewards` es ese `aArray()` o `[]`.

### 2.3 Entrenador

- `PresentadorResultadoBatalla.php:73-107` — registra el resultado y, si gana, llama a `otorgarRecompensas()`.
- `PresentadorResultadoBatalla.php:279-301` — resuelve `OtorgarRecompensasEntrenador::otorgar()`.
- `src/CombateEntrenadores/App/OtorgarRecompensasEntrenador.php:93-107` — `CalculadorRecompensas` → `PersistirRecompensas` (`:103`) → `DatosModalVictoria`.
- `src/CombateEntrenadores/App/OtorgarRecompensasEntrenador.php:123-130` — `aDatosModal()` solo conserva `expTotal`, `expMiembro` y caramelos.
- `src/CombateEntrenadores/Domain/DataTransferObjects/DatosModalVictoria.php:48-61` — `toArray()` devuelve `exp_total`, `exp_miembro`, `caramelos` y, opcionalmente, `medalla`.
- `PresentadorResultadoBatalla.php:101-103` — `rewards = $datosModal->toArray()`.

### 2.4 Gimnasio

- `PresentadorResultadoBatalla.php:114-169` — resuelve gimnasio, dificultad, registra resultado y, si `$won && $resultado->avance`, otorga recompensas.
- `PresentadorResultadoBatalla.php:150-158` — usa `OtorgarRecompensasEntrenador::MULTIPLICADOR_GIMNASIO` (`10.0`, definido en `OtorgarRecompensasEntrenador.php:38`).
- `PresentadorResultadoBatalla.php:160-165` — aplica `conMedalla()` y luego `toArray()`.
- `src/CombateEntrenadores/Domain/DataTransferObjects/DatosModalVictoria.php:30-38` — `conMedalla()` crea una instancia nueva. **Si en el futuro se añadiera `progreso` al DTO, este método tendría que conservarlo**; con la opción recomendada de no tocar el DTO, este riesgo desaparece.

### 2.5 Mazmorra

- `PresentadorResultadoBatalla.php:176-235` — registra avance y, si gana, llama a `OtorgarRecompensasMazmorra`.
- `src/Mazmorras/App/OtorgarRecompensasMazmorra.php:25-41` — reutiliza `OtorgarRecompensasEntrenador` con caramelos `×70` y EXP `×10`.
- `PresentadorResultadoBatalla.php:218-222` — construye manualmente `rewards` con `exp_total`, `exp_miembro` y `caramelos` legacy. **Aquí habría que añadir `progreso` explícitamente** si se usa la opción del presentador.

### 2.6 Exploración (no es modal de combate, pero también muestra EXP)

- `src/Exploraciones/App/Finalizacion/RepartidorRecompensasExploracion.php:52-79` — calcula y persiste (`:62`), y registra resultado (`:67-77`).
- `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php:56-69` — persiste `exp` y `exp_reclutado` en `eventos['resultado']`.
- `src/Exploraciones/Presentation/ResultadoExploracionVista.php:74-75` — expone `exp` y `exp_reclutado`.
- `resources/views/exploraciones/index.blade.php:200-205` — pinta badges `+EXP cuenta` y `+EXP explorador`, sin nivel ni barra.
- `resources/views/exploraciones/index.blade.php:173-415` — tarjeta de “Resultados por revisar”; aquí no hay modal.

### 2.7 Persistencia y reparto

- `src/Exploraciones/App/PersistirRecompensas.php:35-49` — orden: caramelos, items, capturas y EXP.
- `PersistirRecompensas.php:153-155` — `users.experiencia += expTotal` solo si `expTotal > 0`.
- `PersistirRecompensas.php:160` — Sendas: `expTipoCuenta ?? expTipoPorMiembro` (fallback legado).
- `PersistirRecompensas.php:184-217` — `otorgarExpSendas`: mapea label → `TipoEnum`, `firstOrCreate` con `exp = 0, level = 1`, incrementa `exp`, refresca y actualiza `level` con `SendaProgreso::nivel()`.
- `src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php:49-59` — contiene `expTotal`, `expPorMiembro`, `expTipoPorMiembro`, `expTipoCuenta`.
- `src/Exploraciones/Domain/CalculadorRecompensas.php:381-428` — reparto de EXP de cuenta y de tipo; `tiposCuenta` y `tiposDestino` se calculan con `floor` e `intdiv`.

### 2.8 Normalización del modal

- `app/Livewire/Presenters/BatallaVistaPresenter.php:426-451` — `rewardsVista()`:
  - Normaliza caramelos, capturas, `exp_total`, `exp_miembro`, `medalla` y caramelos legacy.
  - `:436-437` — decide `layout = 'ruta'` si hay caramelos o capturas; si no, `'entrenador'`.
- `resources/views/livewire/combate.blade.php:124-255` — modal; dos layouts:
  - Ruta: `:135-210`, EXP en `:139-146`.
  - Entrenador/gimnasio: `:211-243`, EXP en `:224-232`.
- `resources/views/livewire/partials/combate-scripts.blade.php:535-541` — `mostrarModalVictoria()` retira `modal-pendiente`.
- `resources/views/livewire/combate.blade.php:125-127` — el modal se oculta con `modal-pendiente` mientras dura la cola de eventos.

## 3. Datos disponibles y snapshots necesarios

### 3.1 Datos disponibles

**Cuenta**

- `users.experiencia` (`app/Models/User.php:24-29`, cast integer en `:46-52`).
- `User::nivel()` (`app/Models/User.php:58-61`).
- `NivelHelper::nivelDesdeExperiencia()`, `experienciaParaNivel()` y `progresoHaciaSiguienteNivel()` (`src/Shared/Domain/NivelHelper.php:16-34`, `:47-50`, `:55-67`).
- `nivelJugador` y `progresoNivel` se comparten globalmente (`app/Providers/AppServiceProvider.php:77-90`) y se pintan en el header (`resources/views/layouts/app.blade.php:80-94`).

**Sendas**

- `player_type_paths.exp` y `level` (`database/migrations/2026_10_30_162641_create_player_type_paths_table.php:17-27`).
- `level` está obsoleto para lectura: `CargarMapaSendaJugador` deriva el nivel desde `exp` (`app/Support/CargarMapaSendaJugador.php:10-18`, `:22-46`).
- `SendaProgreso::resumen()` (`app/Support/SendaProgreso.php:113-135`) devuelve `exp` clampeada, `nivel`, `nivel_maximo`, `exp_siguiente`, `exp_faltante`, `porcentaje` y `exp_por_caramelo`.
- Curva: `CurvaSenda::UMBRALES` 0 / 9.000 / 18.000 / 36.000 / 72.000 (`src/Shared/Domain/Sendas/CurvaSenda.php:15-32`); `SendaProgreso::NIVEL_MAXIMO = 5` y `EXP_MAXIMA = 72.000` (`app/Support/SendaProgreso.php:24-34`).
- `SendaProgreso::resumen()` clampea la EXP mostrada a 72.000, pero la EXP real sigue acumulándose por encima (`SendaProgreso.php:97-100`).

**Metadatos de tipo**

- `TipoPokemon::label()`, `slug()` y `color()` (`src/Shared/Tipos/TipoPokemon.php:36-89`).
- `SlugTipo::de()` (`src/Shared/Domain/SlugTipo.php:37-48`).
- Assets: `/images/type/{slug}.webp` y `/images/candy_type/{slug}.webp` usados en Sendas (`resources/views/sendas/index.blade.php:27-28`, `:55-56`).
- Variables CSS `--type-{slug}` emitidas globalmente (`resources/views/partials/tipos-tokens.blade.php:4-12`, incluidas en `resources/views/layouts/app.blade.php:9`).

### 3.2 Snapshots necesarios

Para animar fielmente hacen falta:

1. **Cuenta**
   - `experiencia` antes y después.
   - Nivel antes y después.
   - Porcentaje antes y después.
   - Umbral de inicio y siguiente nivel.
   - Segmentos por cada nivel atravesado.
2. **Cada Senda afectada**
   - `type_id`, label, slug, color e icono.
   - EXP real antes y después; EXP ganada.
   - Nivel y porcentaje antes y después.
   - Umbral de inicio y siguiente.
   - Segmentos por nivel.
   - Indicador de tope (nivel 5).
3. **Texto accesible** generado en servidor con el resultado final.

### 3.3 Dónde capturarlos sin duplicar escrituras

**Recomendado: dos instantáneas de solo lectura alrededor de la llamada que ya persiste.**

El presentador ya es quien orquesta la concesión. Se puede envolver cada llamada:

- Entrenador: `PresentadorResultadoBatalla.php:92-99`.
- Gimnasio: `PresentadorResultadoBatalla.php:150-158`.
- Mazmorra: `PresentadorResultadoBatalla.php:199-204`.
- Ruta: `PresentadorResultadoBatalla.php:258-266`.

Secuencia:

```text
capturar(userId)        // SELECT users.experiencia + SELECT player_type_paths
otorgarRecompensas(...) // única escritura, la que ya existe
capturar(userId)        // segunda lectura
comparar(antes, después)
```

Ventajas:

- No se modifica `PersistirRecompensas` (`:35-49`), usado por ruta, entrenador y exploración (`OtorgarRecompensasRuta.php:147`, `OtorgarRecompensasEntrenador.php:103`, `RepartidorRecompensasExploracion.php:62`).
- No se toca la firma `void` de `persistir()`, que está mockeada en varios tests con `createMock(PersistirRecompensas::class)` (`tests/Feature/CombateRuta/OtorgarRecompensasRutaTest.php:52-55`, `tests/Feature/CombateEntrenadores/SendaRecompensasEntrenadorTest.php:111-114`).
- No hay que replicar la semántica `expTipoCuenta ?? expTipoPorMiembro` (`PersistirRecompensas.php:160`): la diferencia de BD ya refleja lo que realmente se escribió.
- Funciona con múltiples tipos, múltiples level-ups y topes.
- Coste: 2 consultas extra por combate (usuario + Sendas), 4 si se cuentan antes y después. Es despreciable frente al combate.

**Riesgo residual:** si otro proceso modifica la EXP del mismo usuario entre las dos lecturas, la diferencia incluiría ese cambio. La ventana es la duración de la concesión de recompensas (sin jobs de EXP), por lo que el riesgo es bajo. Si se quiere eliminar por completo, se puede hacer la lectura “después” inmediatamente tras `persistir` y no después de `toArray`/`conMedalla`.

### 3.4 Opciones consideradas

| Opción | Descripción | Ventajas | Inconvenientes |
|---|---|---|---|
| **A. Snapshots en el presentador (recomendada)** | `PresentadorResultadoBatalla` lee antes/después y adjunta `progreso` al array `rewards`. | Cero cambios en dominio y persistencia; exacto; tolerante a datos antiguos; un solo punto por flujo. | Lecturas de BD en presentación; 2-4 consultas extra. |
| B. Snapshot dentro de `PersistirRecompensas` | Cambiar `persistir()` para devolver un VO con antes/después. | Una única fuente para todos los flujos, incluida exploración. | Afecta a 3 llamadores y a mocks de tests; mezcla persistencia con presentación; más superficie de regresión. |
| C. Captura en cada otorgador + cálculo determinista | Leer antes en `OtorgarRecompensasEntrenador/Ruta` y calcular después sumando lo concedido. | Sin segunda lectura. | Hay que propagar datos por DTOs (`DatosModalVictoria`, `ResultadoRuta`); duplicación en dos servicios; tests con constructores posicionales. |
| D. Evento/listener post-commit | Emitir evento con la ganancia y escuchar para construir la vista. | Desacoplado. | Sobrearquitectura para una animación; el modal necesita el dato en la misma respuesta. |

## 4. Propuesta de estructura de presentación

La estructura se generaría en servidor y viajaría como clave aditiva `progreso` dentro del array `rewards`. Ejemplo de contrato:

```php
[
    'cuenta' => [
        'exp_antes' => 12345,
        'exp_despues' => 13345,
        'exp_ganada' => 1000,
        'nivel_antes' => 10,
        'nivel_despues' => 11,
        'niveles_ganados' => 1,
        'porcentaje_antes' => 42,
        'porcentaje_despues' => 18,
        'exp_siguiente' => 13310,
        'segmentos' => [
            ['nivel' => 10, 'desde' => 42, 'hasta' => 100],
            ['nivel' => 11, 'desde' => 0, 'hasta' => 18],
        ],
        'tope' => false,
        'niveles_omitidos' => 0,
    ],
    'sendas' => [
        [
            'tipo_id' => 12,
            'label' => 'Planta',
            'slug' => 'planta',
            'color' => '#78C850',
            'icono' => '/images/type/planta.webp',
            'exp_antes' => 8500,
            'exp_despues' => 9100,
            'exp_ganada' => 600,
            'exp_maxima' => 72000,
            'nivel_antes' => 1,
            'nivel_despues' => 2,
            'nivel_maximo' => 5,
            'niveles_ganados' => 1,
            'porcentaje_antes' => 94,
            'porcentaje_despues' => 1,
            'exp_siguiente_antes' => 9000,
            'exp_siguiente_despues' => 18000,
            'segmentos' => [
                ['nivel' => 1, 'desde' => 94, 'hasta' => 100],
                ['nivel' => 2, 'desde' => 0, 'hasta' => 1],
            ],
            'tope' => false,
            'niveles_omitidos' => 0,
        ],
    ],
    'resumen_accesible' => 'Has ganado 1.600 de experiencia. Cuenta: nivel 10 a 11. Senda Planta: nivel 1 a 2.',
]
```

Reglas de construcción:

- Se incluye `cuenta` solo si `exp_despues > exp_antes`.
- Se incluye cada Senda solo si `exp_despues > exp_antes`; se ignoran tipos fuera del catálogo (`TipoPokemon::tryFrom()`).
- `segmentos` se construye recorriendo los niveles entre `nivel_antes` y `nivel_despues`:
  - Primer segmento: `desde = porcentaje_antes`.
  - Segmentos intermedios: `desde = 0`, `hasta = 100`.
  - Último segmento: `hasta = porcentaje_despues`.
- Si hay más de N segmentos (por ejemplo, 4), se conservan los primeros y el último, y se rellena `niveles_omitidos` para que la animación no dure eternamente.
- En Senda con nivel 5: `tope = true`, `porcentaje_antes = porcentaje_despues = 100`, sin segmentos útiles; se muestra “Tope alcanzado”.
- Si no hay ninguna ganancia (por ejemplo, recompensas ya otorgadas o array antiguo), el builder devuelve `null` y Blade no pinta nada.
- No se usa `player_type_paths.level`; se deriva de `exp` con `SendaProgreso::resumen()`.
- El texto `resumen_accesible` se genera en servidor para no construirlo en Blade ni depender solo de JS.

`BatallaVistaPresenter::rewardsVista()` (`:426-451`) solo tiene que reenviar la clave de forma tolerante:

```php
'progreso' => is_array($rewards['progreso'] ?? null) ? $rewards['progreso'] : null,
```

Blade comprobaría `!empty($rewardsVista['progreso'])` y renderizaría un partial. Así los tests que inyectan `rewards` sin `progreso` (`tests/Feature/CombateLivewireTest.php:38-52`) siguen funcionando.

## 5. Animación propuesta y accesibilidad

### 5.1 Patrones existentes aprovechables

- Alpine está integrado con Livewire (`resources/js/bootstrap.js:2-13`) y se usa en componentes de página (`sendasApp`, `exploracionesPage`).
- Ya existen animaciones CSS y keyframes en `resources/css/combate.css:710-758`, incluida la regla de `prefers-reduced-motion` (`:722-758`).
- `app.css` también respeta movimiento reducido (`resources/css/app.css:198-202`).
- El modal ya se revela desde `mostrarModalVictoria()` (`resources/views/livewire/partials/combate-scripts.blade.php:535-541`), lo que permite disparar la animación justo cuando el modal se hace visible.

### 5.2 Enfoque recomendado: Alpine + transición CSS por segmentos

1. Añadir a `combate-scripts.blade.php` un componente `rewardProgreso(datos)`.
2. El componente:
   - Detecta `window.matchMedia('(prefers-reduced-motion: reduce)')`.
   - Si hay movimiento reducido, fija directamente los porcentajes finales y el nivel final.
   - Si no, para cada barra recorre sus `segmentos`: anima `width` hasta `hasta`, y si hay siguiente segmento, muestra el aviso de subida, desactiva la transición, pone `width: 0`, fuerza reflow y anima al siguiente `desde/hasta`.
   - Usa una duración proporcional al tramo (por ejemplo, 250-700 ms) y limita la duración total por barra.
3. El bloque Blade se renderiza con el porcentaje inicial o final. Recomendación: renderizar el porcentaje **final** en el HTML y dejar que JS lo lleve al inicio antes de animar. Así, sin JS o con movimiento reducido, el usuario ve el resultado correcto.
4. Disparo:
   - Si el modal ya está visible al inicializar (`sin modal-pendiente`), iniciar en `x-init` con `$nextTick`.
   - Si está oculto por la cola de eventos, `mostrarModalVictoria()` debe emitir un evento (`window.dispatchEvent(new CustomEvent('reward-modal-visible'))`) y el componente escuchará `@reward-modal-visible.window="iniciar()"`.
   - Guardar un flag `iniciado` para no repetir la animación en morphs de Livewire.
5. Varias barras: animarlas en paralelo con un pequeño escalonado (60-100 ms) o en secuencia. Recomendación: paralelo con escalonado, para que la cuenta y las Sendas se sientan simultáneas sin alargar el modal.

### 5.3 Alternativas y trade-offs

| Enfoque | Ventajas | Inconvenientes |
|---|---|---|
| **Alpine + transición CSS (recomendado)** | Sin dependencias; control preciso del vaciado y reajuste; fácil de desactivar con reduced motion. | Requiere JS para la secuencia; hay que manejar el momento de visibilidad del modal. |
| Web Animations API | Cancelable, promesas, control fino. | Más código; menos alineado con los patrones actuales del proyecto. |
| Keyframes CSS puros | Muy simple. | Difícil con porcentajes dinámicos y múltiples level-ups; no permite reajustar la barra con precisión. |
| Solo transición de ancho, sin vaciado | Muy simple. | No cumple “se vacía y reajusta” en level-up. |

### 5.4 Accesibilidad

- Cada barra: `role="progressbar"`, `aria-valuemin="0"`, `aria-valuemax="100"`, `aria-valuenow` con el porcentaje final y `aria-label` descriptivo (“Senda de Planta: nivel 2, 1%”).
- Un único `aria-live="polite"` con `resumen_accesible` generado en servidor. No anunciar cada frame.
- Respetar `prefers-reduced-motion` en JS y en CSS (añadir las nuevas clases a la media query existente de `combate.css`).
- No depender del color: mostrar siempre nivel y texto.
- El modal ya tiene `role="dialog"`, `aria-modal` y `aria-labelledby` (`combate.blade.php:128`); se conserva.

## 6. Archivos afectados, orden y dependencias

### 6.1 Backend

1. **Nuevo** `app/Support/ProgresoRecompensas.php`
   - `capturar(int $userId): array` — lee `users.experiencia` y `player_type_paths.exp` sin scope de usuario.
   - `comparar(array $antes, array $despues): ?array` — construye cuenta, Sendas, segmentos, topes y resumen accesible.
   - Reutiliza `NivelHelper`, `SendaProgreso`, `TipoPokemon` y `SlugTipo`. No escribe nada.
2. `app/Livewire/Presenters/PresentadorResultadoBatalla.php`
   - Envolver las cuatro llamadas de recompensa con snapshots y añadir `$rewards['progreso']`.
   - Puntos: `:92-99` (entrenador), `:150-158` (gimnasio), `:199-204` (mazmorra), `:258-266` (ruta).
3. `app/Livewire/Presenters/BatallaVistaPresenter.php`
   - En `rewardsVista()` (`:426-451`), reenviar `progreso` de forma tolerante y, si se quiere, `tieneProgreso`.

### 6.2 Frontend

4. **Nuevo** `resources/views/livewire/partials/reward-progreso.blade.php`
   - Bloque de cuenta y lista de Sendas. Sin cálculos: solo recorre arrays y pinta.
5. `resources/views/livewire/combate.blade.php`
   - Incluir el partial en ambos layouts: después de `:137` (ruta) y después de `:222`/antes de la lista (entrenador/gimnasio).
6. `resources/views/livewire/partials/combate-scripts.blade.php`
   - Añadir `rewardProgreso()` y emitir el evento de visibilidad desde `mostrarModalVictoria()` (`:535-541`).
7. `resources/css/combate.css`
   - Clases de barra, relleno, aviso de subida y reglas de `prefers-reduced-motion` (junto a `:722-758`).

### 6.3 Lo que no se toca

- `src/Exploraciones/App/PersistirRecompensas.php`.
- `src/CombateEntrenadores/Domain/DataTransferObjects/DatosModalVictoria.php`.
- `src/CombateRuta/Domain/DataTransferObjects/ResultadoRuta.php`.
- `SESSION_VERSION`, `BattleSessionService`, rutas, migraciones, dependencias y tests.
- `bugs.txt`, `.opencode/work/TASK-032/`, `.opencode/work/TASK-033/`.

### 6.4 Dependencias

- Backend: `PresentadorResultadoBatalla` → `App\Support\ProgresoRecompensas` → `App\Models\User`, `App\Models\PlayerTypePath`, `Src\Shared\Domain\NivelHelper`, `App\Support\SendaProgreso`, `Src\Shared\Tipos\TipoPokemon`, `Src\Shared\Domain\SlugTipo`.
- Frontend: Alpine 3.16, Tailwind 4, Bootstrap 5.3, Livewire 4.4 (`package.json:17-23`, `composer.json:12`). No se añaden paquetes.
- `--type-*` ya está disponible en el layout (`layouts/app.blade.php:9`).

### 6.5 Duplicidades y deuda técnica relevante

- Existen dos contratos de recompensa: `DatosModalVictoria::toArray()` y `ResultadoRuta::aArray()`. `rewardsVista()` ya los unifica; añadir `progreso` en el array del presentador evita duplicar la lógica en ambos DTOs.
- `PersistirRecompensas` mapea label → `TipoEnum` localmente (`:190-193`); el presentador necesitará un mapeo equivalente vía `TipoPokemon`. Es una duplicación pequeña y aceptable; se puede extraer un helper compartido si el arquitecto lo considera.
- `player_type_paths.level` está obsoleto pero se sigue escribiendo (`PersistirRecompensas.php:216`, `SendasController.php:189`). La nueva vista no debe leerlo.
- El layout de `rewardsVista` clasifica como “entrenador” cualquier combate sin caramelos ni capturas (`BatallaVistaPresenter.php:436-437`). Una victoria de ruta con solo EXP mostraría el texto “Has derrotado al entrenador” (deuda preexistente). La barra de progreso es independiente, pero conviene corregirlo en otra tarea.

## 7. Riesgos y casos límite

| Riesgo | Severidad | Impacto | Mitigación |
|---|---|---|---|
| Cambio concurrente de EXP entre las dos lecturas | Media | La barra “después” incluiría una ganancia ajena | Ventana mínima alrededor de la concesión; en un solo jugador es improbable. Si preocupa, leer “después” inmediatamente tras `persistir` y no tras `toArray`/`conMedalla`. |
| Repetición de la animación en morphs de Livewire | Baja | La animación se reinicia | Flag `iniciado` y evento único de visibilidad. |
| Modal oculto (`modal-pendiente`) durante la cola de eventos | Media | La animación no se ve o empieza antes de tiempo | Disparar desde `mostrarModalVictoria()` con evento `reward-modal-visible`; si ya está visible, `x-init`. |
| Muchos level-ups en una sola ganancia (gimnasio ×10, mazmorra ×10) | Media | Animación demasiado larga | Limitar segmentos por barra (p. ej. 4) y exponer `niveles_omitidos`; duración total acotada. |
| Senda en nivel 5 / EXP > 72.000 | Baja | La barra no puede subir | Mostrar 100 %, “Tope alcanzado” y la EXP ganada; no simular un sexto nivel. |
| Cuenta en niveles altos | Baja | Muchos segmentos si la ganancia es enorme | Mismo límite de segmentos. `NivelHelper` no tiene tope de nivel (`:9-12`). |
| Datos antiguos sin `progreso` | Baja | Error de clave indefinida | `rewardsVista` devuelve `null` si falta; Blade no renderiza el partial. |
| `player_type_paths` antiguos con `level` pero `exp = 0` | Media | Nivel mostrado menor que el esperado | Ya ocurre en `CargarMapaSendaJugador`; no introducir regresión. Si hay datos así, evaluar backfill en otra tarea (no migración aquí). |
| Muchas Sendas afectadas (hasta 18) | Baja | Modal largo | `modal-dialog-scrollable` ya está; agrupar/compactar filas y ordenar por EXP ganada. |
| Consultas extra por combate | Baja | 2-4 SELECT | Aceptable; se puede cachear la lectura “antes” dentro del mismo request si se mide. |
| Combate sin EXP de tipo (`expTipoCuenta` vacío / rival sin tipos) | Baja | Solo se muestra cuenta | El builder devuelve solo `cuenta`; si tampoco hay EXP de cuenta, `progreso = null`. |
| Curva de cuenta con umbral de nivel 1 en 10 EXP (`NivelHelper.php:47-50`) | Baja | Barra al 0 % con EXP 0-9 | Aceptable; documentar. No se altera la fórmula. |
| Dependencia de `--type-*` | Baja | Sin color si falta el token | El payload incluye `color` hex de `TipoPokemon::color()` como fallback. |
| Sin JS | Baja | No hay animación | El HTML puede renderizar el porcentaje final; la app ya depende de Livewire/Alpine. |
| Tocar `PersistirRecompensas` | Alta (evitada) | Afecta a ruta, entrenador, exploración y mocks | No se toca en la opción recomendada. |
| `conMedalla()` perdiendo `progreso` | Alta si se modifica el DTO | El gimnasio perdería la barra | Evitado al no añadir `progreso` al DTO; se adjunta después de `conMedalla()`. |
| Layout “entrenador” en ruta sin caramelos/capturas | Media (preexistente) | Texto incorrecto, no la barra | Corregir en tarea aparte o añadir un flag de tipo de combate en `rewards`. |

**Casos límite que la implementación debe cubrir**

- Ganancia que no cruza nivel: un solo segmento.
- Ganancia que cruza 1 nivel: dos segmentos con vaciado.
- Ganancia que cruza varios niveles: N segmentos, con límite.
- Senda que empieza en 0 y no existía fila: antes = 0, nivel 1.
- Senda que alcanza exactamente 9.000 / 18.000 / 36.000 / 72.000.
- Senda ya en nivel 5: sin animación de subida.
- Cuenta con `experiencia` 0 o inferior a 10.
- Recompensas antiguas o inyectadas sin `progreso`.
- Combate perdido: no hay modal ni progreso.
- Combate con recompensas ya concedidas: `procesar()` no encuentra meta y devuelve `rewards = []` (`PresentadorResultadoBatalla.php:50-52`).
- Muchos tipos: el modal debe hacer scroll sin romper el layout.
- Movimiento reducido: los valores finales deben quedar visibles de inmediato.

## 8. Verificación propuesta

**Estática**

- `php -l` sobre los archivos PHP modificados/nuevos.
- `composer analyse` (`phpstan.neon`).
- `composer arch` (`deptrac`) para confirmar que `App\Livewire\Presenters` puede depender de `App\Support` y del dominio compartido.
- `vendor/bin/pint --dirty --format agent` (según AGENTS) o `composer lint` en modo comprobación.
- `npm run build` para compilar CSS/JS y verificar que `combate.css` entra en el bundle.

**Manual (sin ejecutar tests, según las reglas del proyecto)**

1. Ruta con un tipo: cuenta + una Senda; forzar EXP cerca del umbral 9.000 para ver el vaciado.
2. Ruta con doble tipo: dos barras de Senda.
3. Entrenador: comprobar que la barra aparece junto a `exp_total`/`exp_miembro`.
4. Gimnasio: comprobar que la medalla sigue apareciendo y que la barra no se pierde.
5. Mazmorra: comprobar que la barra aparece con el layout actual (y anotar el texto preexistente “Has derrotado al entrenador”).
6. Cuenta con EXP sembrada para cruzar 1 y varios niveles; verificar el reajuste y el aviso.
7. Senda en 71.999 → 72.100: nivel 5 y “Tope alcanzado”.
8. Senda ya en 72.000+ y nueva ganancia: sin animación de subida, con EXP ganada visible.
9. Recompensa inyectada sin `progreso` (equivalente al test de Livewire): el modal sigue funcionando.
10. `prefers-reduced-motion: reduce`: valores finales sin animación.
11. Lector de pantalla: `aria-live` anuncia el resumen final una sola vez.
12. Muchas Sendas: el modal hace scroll y no desborda.

**Criterios de aceptación funcionales**

- La animación no vuelve a conceder EXP ni modifica fórmulas.
- La barra de cuenta y las Sendas reflejan exactamente la diferencia persistida.
- Los múltiples level-ups se ven como vaciado + reajuste.
- El nivel 5 de Senda se muestra como tope.
- Sin `progreso` no hay errores.
- No se modifican `SESSION_VERSION`, `BattleSessionService`, tests, migraciones ni dependencias.

## 9. Preguntas abiertas y supuestos

**Preguntas abiertas**

1. **Alcance**: ¿solo el modal de combate (ruta, entrenador, gimnasio, mazmorra) o también la tarjeta de “Resultados por revisar” de exploraciones? La exploración se resuelve en segundo plano y se pinta tras recargar; animar allí implicaría decidir si la animación se repite en cada carga. Recomendación: fase 1 solo modal de combate; fase 2 resumen estático o modal de una sola vez para exploración.
2. ¿Se muestran todas las Sendas que ganan EXP o solo las N principales? Recomendación: todas, ordenadas por EXP ganada, con scroll.
3. ¿Cuántos level-ups se animan como máximo? Recomendación: 4 segmentos por barra y `niveles_omitidos`.
4. ¿Duración objetivo de la secuencia? Recomendación: 1,5-2,5 s en total; escalonado de 60-100 ms entre barras.
5. ¿Se muestran los números exactos de EXP antes/después o solo el delta y el nivel? Recomendación: nivel, delta y barra; los totales exactos en texto secundario.
6. ¿Se acepta que el presentador haga 2-4 lecturas extra por combate? Recomendación: sí; es la opción con menor riesgo.
7. ¿Debe actualizarse también la barra del header en tiempo real? Recomendación: no; el header se actualiza al navegar (el modal ya ofrece enlace de salida).
8. ¿Se corrige el texto “Has derrotado al entrenador” para mazmorra/ruta sin botín? Recomendación: tarea aparte; no bloquear esta.

**Supuestos**

- Rama de trabajo `task-011`; no hay cambios locales relevantes en los archivos analizados (`git status` solo muestra `bugs.txt` y directorios de tareas).
- Laravel 12, PHP 8.4, Livewire 4.4, Alpine 3.16, Tailwind 4 y Bootstrap 5.3 ya instalados.
- `player_type_paths` y `users.experiencia` existen y están migradas en el entorno de ejecución.
- `--type-*` está disponible en el layout de combate porque `combate_page` extiende `layouts.app`.
- No se ejecutan tests ni se modifican migraciones/dependencias.
- La EXP de tipo se otorga siempre que `PersistirRecompensas` la recibe; la diferencia de BD es la fuente de verdad, no una reconstrucción de la fórmula.

**Recomendación final**

Implementar la **Opción A**: snapshots de solo lectura en `PresentadorResultadoBatalla` + builder `App\Support\ProgresoRecompensas` + clave aditiva `progreso` en `rewards` + partial Blade + componente Alpine `rewardProgreso()` + estilos en `combate.css`. No tocar `PersistirRecompensas` ni los DTOs de recompensa. Con ello se cumple el requisito literal (“nivel de la cuenta y las sendas, barra que sube, animación al subir de nivel, vaciado y reajuste”) con el menor riesgo de regresión y sin duplicar la concesión de EXP.
