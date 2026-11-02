# TASK-003 — Arquitectura Fase 1

> Resultado del especialista `architect`, guardado sin alterar sus conclusiones. El informe original
> indica que no localizó `analysis.md` en la ruta delegada; el orchestrator sí lo guardó después.
> Las decisiones de producto vigentes están en `execution.md`.

## 1. Resumen ejecutivo

La exploración ya es individual en datos (`exploraciones_activas.reclutado_id`), pero la presentación, el bloqueo de equipos y la economía siguen hablando de equipos de 3. La Fase 1 corrige esa incoherencia sin migraciones:

1. **Economía aislada por política**: se introduce `EconomiaRecompensas` como VO de dominio. Solo `FinalizarExploracionHandler` usa la política `individual()` (factor 0,5 y reparto 50/50). `OtorgarRecompensasRuta` sigue usando la política `legado()` y no cambia su comportamiento ([OtorgarRecompensasRuta.php:130](src/CombateRuta/App/OtorgarRecompensasRuta.php:130)).
2. **Contrato de recompensas compatible**: `ResultadoRecompensas` conserva `expTotal` (cuenta) y `expPorMiembro` (destino), y añade `expTipoCuenta` con fallback al valor legado. No se renombran propiedades existentes ([ResultadoRecompensas.php:44-46](src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php:44)).
3. **Ciclo de vida**: `inicio_exploracion = now()` al crear; `llegada_destino` queda deprecada; `return_time` se resuelve relativo al inicio y salta al día siguiente si la hora ya pasó ([ExploracionActivaController.php:288](app/Http/Controllers/ExploracionActivaController.php:288), [CalculadorFinExploracion.php:24](src/Exploraciones/Domain/CalculadorFinExploracion.php:24)).
4. **Sin bloqueo de equipo**: se elimina `equiposEnExploracion` y `Team::isExploring()` del flujo de exploración; solo el reclutado ausente se marca y se bloquea individualmente ([Team.php:46-52](app/Models/Team.php:46), [PlayerController.php:142-159](app/Http/Controllers/PlayerController.php:142)).
5. **Personalidad**: capa de presentación con `ExploradorVista`, `DiarioExploracion`, `HitoExploracion` y `FraseAnimo`; el presentador precalcula y Blade solo pinta. Sin datos nuevos.

## 2. Decisiones

**D1. El 50 % se aplica una sola vez, en dominio, mediante política.**
`EconomiaRecompensas` encapsula `factor`, `fraccionCuenta`, `fraccionDestino` y `miembrosDestino`. `CalculadorRecompensas::calcular(..., ?EconomiaRecompensas $economia = null)` usa `legado()` por defecto. La exploración pasa `individual()`. Así no se duplica el descuento en persistencia ni en presentación y la ruta no se ve afectada.

**D2. Reparto de EXP con un único truncado por agregado.**
Para la política individual:
- `E_i = NivelHelper::expDerrota(base_experience_i, nivelSalvaje)`
- `T = Σ E_i`
- `pool = max(0, floor(T × multiplicadorCategoría × 0,5))`
- `expCuenta = intdiv(pool, 2)`
- `expReclutado = pool − expCuenta` (el resto va al reclutado)
- Invariante: `expCuenta + expReclutado = pool`.

El multiplicador de categoría sigue siendo el de `EvaluadorExploracion::multiplicador()` ([EvaluadorExploracion.php:253-263](src/Exploraciones/Domain/EvaluadorExploracion.php:253)).

**D3. EXP de tipo y sendas también se reparte.**
- `TR_t = Σ R_i` para cada tipo, con `R_i = E_i` si el Pokémon tiene 1 tipo, `intdiv(E_i, 2)` si tiene 2.
- `poolTipo_t = max(0, floor(TR_t × multiplicadorCategoría × 0,5))`
- `expTipoCuenta_t = intdiv(poolTipo_t, 2)`, `expTipoReclutado_t = poolTipo_t − expTipoCuenta_t`.
- `PersistirRecompensas` acredita `expTipoCuenta` a las Sendas y `expTipoPorMiembro` al reclutado. Para la ruta, `expTipoCuenta` es null y el DTO lo iguala a `expTipoPorMiembro`, preservando el comportamiento actual ([PersistirRecompensas.php:120-141](src/Exploraciones/App/PersistirRecompensas.php:120)).

**D4. Caramelos y capturas.**
- Familia/EV/tipo: `floor(cantidadBase × multiplicador × 0,5)`. Los hallazgos reciben `multiplicadorCaramelos × 0,5`.
- Capturas: se reduce la **probabilidad**, no la cantidad. `FinalizarExploracionHandler` multiplica el factor de captura por 0,5 antes de `ProbabilidadCaptura::intentarConFactor`. Evita que una captura única pase a 0 por truncado.
- No existen recompensas de “objetos” en `ResultadoRecompensas`; `objetos_perdidos` es una penalización por derrota y **no se toca** (recortarla aumentaría la recompensa neta).

**D5. Compatibilidad de contrato de vista.**
`PresentadorExploraciones` mantiene `'equipo'` como alias deprecado y añade `'reclutado'` y `'explorador'` ([PresentadorExploraciones.php:182](src/Exploraciones/Presentation/PresentadorExploraciones.php:182), [:260](src/Exploraciones/Presentation/PresentadorExploraciones.php:260)). Blade pasa a usar `explorador.nombre`; los tests actualizan aserciones, pero consumidores antiguos no rompen. La clave `'equipo'` se elimina en Fase 2.

**D6. Ciclo de vida sin “Preparando”.**
No hay fase de viaje real ni escritura de `llegada_destino`. Fase 1 emite `explorando` y `volviendo`; `preparando` queda para Fase 2 si producto define una duración de ida. `fase` se deriva de `inicio_vuelta`.

**D7. `return_time` como siguiente ocurrencia.**
`CalculadorFinExploracion` usa el inicio como base: `inicio->setTimeFromTimeString(hora)`, y si `<= inicio`, suma un día. El preview usa el mismo cálculo, no `Carbon::today()`. Esto corrige horas nocturnas y pasadas sin migración, porque `hora_limite` es `time` ([migración 2026_08_26_000004:19](database/migrations/2026_08_26_000004_add_habitat_id_and_level_to_exploraciones_activas.php:19)).

**D8. Bloqueo individual.**
Se eliminan `equiposEnExploracion` de `PlayerController`/`HabitatsController`, y `isExploring()` de `TeamController` y `src/Equipos/App/*`. En `ReclutadoController`, el bloqueo pasa de “equipo en exploración” a “este reclutado en exploración” ([ReclutadoController.php:45,135,320,435](app/Http/Controllers/ReclutadoController.php:45)). La UI marca al miembro ausente en la tarjeta del equipo; el equipo sigue siendo editable. Decisión abierta: si un equipo con un miembro ausente puede iniciar combate 3v3.

## 3. Contratos

### 3.1 Dominio

```php
final readonly class EconomiaRecompensas
{
    public function __construct(
        public float $factor,
        public float $fraccionCuenta,
        public float $fraccionDestino,
        public int $miembrosDestino,
    ) {}

    public static function legado(): self;      // 1.0, 1.0, 0.8, 3
    public static function individual(): self;  // 0.5, 0.5, 0.5, 1
    public function esIndividual(): bool;
}
```

`ResultadoRecompensas`:
```php
public function __construct(
    Collection|array $capturas,
    Collection|array $caramelosFamilia,
    Collection|array $caramelosEv,
    Collection|array $caramelosTipo,
    public readonly int $expTotal,          // cuenta
    public readonly int $expPorMiembro = 0, // destino (reclutado o cada miembro)
    public readonly array $expTipoPorMiembro = [],
    public readonly ?array $expTipoCuenta = null, // null → copia de expTipoPorMiembro
)
```
`sumarHallazgos()` y `restarPerdidas()` propagan `expTipoCuenta`.

### 3.2 JSON persistido `eventos['resultado']`

Aditivo, sin romper histórico:
```json
{
  "exp": 2500,
  "exp_reclutado": 2500,
  "capturados": [],
  "caramelos_familia": [],
  "caramelos_ev": [],
  "caramelos_tipo": [],
  "resultado": "exito",
  "duration_real": 240,
  "tiempo_perdido": 0,
  "incidentes": {"encuentros":0,"victorias":0,"huidas":0,"emboscadas":0,"contramientos":0}
}
```
`exp` conserva el significado “EXP de cuenta” para la vista actual; `exp_reclutado` es nuevo.

### 3.3 Vista `toActiva` / `toTerminada`

Se añade:
```php
'explorador' => [
  'id', 'nombre', 'pokemon_id', 'icono', 'nivel', 'exp_total',
  'exp_tipos', 'es_shiny', 'rol', 'rol_label', 'tipos',
  'hp_actual', 'hp_max', 'hp_pct', 'hp_estado', 'hp_label', 'hp_clase',
  'barreras' => ['fisica','fisica_max','especial','especial_max'],
  'animo' => ['texto','tono'],
  'progreso_nivel' => 0-100,
],
'diario' => [
  'total', 'eventos', 'grupos' => [['fecha','etiqueta','eventos']], 'hitos'
],
'fase' => 'ida|vuelta',
'restante_min' => int|null,
'restante_label' => string|null,
```
Se mantienen `'equipo'`, `'reclutado'`, `'bitacora'` para compatibilidad.

### 3.4 Endpoints

- `POST /api/exploraciones/store-individual`: request sin cambios. Response `201 {ok:true,id,inicio,fin,estado}` (aditivo). Errores 422 con `message` string.
- `POST /exploraciones` legado: se conserva y delega en la misma validación; marcado deprecado.
- `GET /exploraciones/preview`: se añade `economia: {factor:0.5, fraccion_cuenta:0.5, fraccion_reclutado:0.5}` y `riesgo` pasa a usar `CalculadorRiesgo::evaluar()` con un único miembro; se añaden `advertencias`, `peligro_estrellas`, `afinidad`, `matchups`, `recompensa_esperada`.

## 4. Cambios por archivo

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `src/Exploraciones/Domain/EconomiaRecompensas.php` | crear | VO de política económica; `legado()`/`individual()`. |
| `src/Exploraciones/Domain/ReglasExploracion.php` | modificar | `FACTOR_RECOMPENSAS=0.5`, `FRACCION_EXP_INDIVIDUAL=0.5`, `MIEMBROS_EQUIPO`; conservar `FRACCION_EXP_REPARTO=0.8` para ruta ([ReglasExploracion.php:20](src/Exploraciones/Domain/ReglasExploracion.php:20)). |
| `src/Exploraciones/Domain/CalculadorRecompensas.php` | modificar | Acepta `EconomiaRecompensas`; calcula pool y reparto; aplica factor a caramelos; conserva rama legada ([CalculadorRecompensas.php:337](src/Exploraciones/Domain/CalculadorRecompensas.php:337)). |
| `src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php` | modificar | Campo `expTipoCuenta` con fallback; propagación. |
| `src/Exploraciones/App/PersistirRecompensas.php` | modificar | Sendas con `expTipoCuenta`; destino con `expTipoPorMiembro`; sin doble descuento. |
| `src/Exploraciones/App/FinalizarExploracionHandler.php` | modificar | Pasa `EconomiaRecompensas::individual()`; factor 0,5 en capturas y hallazgos; propaga `expTipoCuenta` en `restarPerdidas`. |
| `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php` | modificar | Añade `exp_reclutado`; `exp` sigue siendo cuenta ([TransformadorResultadoExploracion.php:54](src/Exploraciones/Presentation/TransformadorResultadoExploracion.php:54)). |
| `src/Exploraciones/Presentation/ExploradorVista.php` | crear | DTO de cabecera: nombre, nivel, EXP, shiny, rol, tipos, HP, ánimo. |
| `src/Exploraciones/Presentation/DiarioExploracion.php` | crear | Agrupa bitácora por fecha, añade eventos raíz y ordena. |
| `src/Exploraciones/Presentation/HitoExploracion.php` | crear | Hitos derivados de incidentes y recompensas. |
| `src/Exploraciones/Presentation/FraseAnimo.php` | crear | Frase y tono deterministas según rol/HP/resultado. |
| `src/Exploraciones/Presentation/EventoExploracionVista.php` | modificar | Narrativa por nombre; `descanso` lee `duracion_minutos` y `hp_recuperado`; tipos `derrota`/`cancelada` ([EventoExploracionVista.php:53,89,93,122,150](src/Exploraciones/Presentation/EventoExploracionVista.php:53)). |
| `src/Exploraciones/Presentation/PresentadorExploraciones.php` | modificar | Construye `explorador`, `diario`, `fase`, `restante`; usa `CalculadorVueltaExploracion::progreso`; mantiene alias `equipo`. |
| `src/Exploraciones/Presentation/ResultadoExploracionVista.php` | modificar | Expone `exp_reclutado`, hitos y datos de cabecera. |
| `src/Exploraciones/Domain/CalculadorFinExploracion.php` | modificar | Fin relativo a `inicio`; suma día si la hora ya pasó. |
| `src/Exploraciones/Domain/EstimadorRecompensasExploracion.php` | modificar | Aplica 0,5 y etiquetas no nulas (`Caramelos de familia`, `Caramelos EV`) ([EstimadorRecompensasExploracion.php:93-96](src/Exploraciones/Domain/EstimadorRecompensasExploracion.php:93)). |
| `app/Http/Controllers/ExploracionActivaController.php` | modificar | `inicio_exploracion=now()`; preview con `CalculadorRiesgo` y `CalculadorFinExploracion`; response aditiva; validación mutua duración/return_time. |
| `app/Http/Controllers/PlayerController.php` | modificar | Elimina `equiposEnExploracion`; mantiene `reclutadosEnExploracion`; eager load `reclutado.pokemon.types`. |
| `app/Http/Controllers/HabitatsController.php` | modificar | Elimina `equiposEnExploracion`; pasa solo `reclutadosEnExploracion`. |
| `app/Http/Controllers/TeamController.php` | modificar | Elimina bloqueo `isExploring()` en `updateItems` ([TeamController.php:235](app/Http/Controllers/TeamController.php:235)). |
| `app/Http/Controllers/ReclutadoController.php` | modificar | Bloqueo por reclutado, no por equipo. |
| `app/Models/Team.php` | modificar | Elimina `isExploring()` (sin consumidores tras el cambio). |
| `src/Habitats/App/ValidadorExploracion.php` | modificar | Elimina `equipoDisponible*`; añade `reclutadoEnExploracion(int): bool`. |
| `src/Equipos/App/{AgregarMiembro,QuitarMiembro,EliminarEquipo,ActualizarFormacion,ActualizarPosturas,ActualizarRolMiembro}.php` | modificar | Retiran guardas `isExploring()`. |
| `resources/views/exploraciones/index.blade.php` | modificar | Usa `explorador`, `diario`, `exp_reclutado`; textos individuales. |
| `resources/views/exploraciones/_explorador.blade.php` | crear | Cabecera rica (icono, shiny, nivel, EXP, rol, tipos, HP). |
| `resources/views/exploraciones/_diario.blade.php` | crear | Diario agrupado, hitos, ánimo. |
| `resources/views/exploraciones/_evento.blade.php` | modificar | Usa campos narrados; añade `derrota`/`cancelada`/`descanso`. |
| `resources/views/habitats/show.blade.php` | modificar | Quita `equiposEnExploracion`; marca miembro ausente; no bloquea equipo entero. |
| `resources/views/equipos/index.blade.php` | modificar | Quita `equiposEnExploracion`; `enExploracion(id)` por miembro; desbloquea acciones de equipo. |
| `tests/**` | modificar/crear | Ver matriz. |

## 5. Flujo de datos

**Creación**
1. Validación de `reclutado_id`, `habitat_id`, `level`, duración/`return_time`.
2. `ValidadorExploracion::reclutadoDisponible()` comprueba solo ese reclutado.
3. Se crea con `inicio_exploracion = now()`, `llegada_destino = null`, `hora_limite = H:i` si procede.
4. `store-individual` responde 201 con `id`, `inicio`, `fin`, `estado`.

**Tick**
1. `ProcesarExploracionHandler` calcula `fin` con `CalculadorFinExploracion` relativo al inicio.
2. `SimuladorEncuentros` genera eventos; combate 1v1; estado en `eventos['explorador']` ([ProcesarExploracionHandler.php:210](src/Exploraciones/App/ProcesarExploracionHandler.php:210)).
3. Descanso escribe `duracion_minutos` y `hp_recuperado` ([ProcesarExploracionHandler.php:585-586](src/Exploraciones/App/ProcesarExploracionHandler.php:585)).
4. Al completar, escribe `retirada`/`derrota` raíz y despacha `FinalizarExploracionCommand` ([ProcesarExploracionHandler.php:100](src/Exploraciones/App/ProcesarExploracionHandler.php:100)).

**Finalización**
1. `FinalizarExploracionHandler` calcula categoría y multiplicador.
2. `CalculadorRecompensas::calcular(..., EconomiaRecompensas::individual())`.
3. `PersistirRecompensas`: caramelos/capturas al usuario; `expTotal` a la cuenta; `expPorMiembro` al reclutado; `expTipoCuenta` a Sendas; `expTipoPorMiembro` al reclutado.
4. `TransformadorResultadoExploracion` escribe `exp` y `exp_reclutado`; `regreso = now()`.

**Vista**
1. `PresentadorExploraciones` construye `explorador`, `diario`, `fase`, `restante`.
2. `ExploradorVista` precalcula HP/ánimo/progreso de nivel.
3. `DiarioExploracion` fusiona bitácora + eventos raíz y agrupa por fecha.
4. Blade solo pinta arrays/DTOs ya resueltos.

## 6. UI y personalidad

- **Cabecera**: icono `/images/iconos_webp/{pokemon_id}.webp`, nombre, estrella shiny, nivel del reclutado (`NivelHelper::nivelDesdeExperiencia`), EXP total, barra de progreso al siguiente nivel (`NivelHelper::progresoHaciaSiguienteNivel`), badge de rol y tipos. Todo desde `reclutados` + `pokemon`.
- **HP**: de `eventos['explorador']` (`EstadoExplorador`). Si `hp_max <= 0`, `hp_estado = 'sin_datos'` y texto “Intacto / sin incidencias”; no se inventa HP.
- **Diario**: agrupado por fecha local; etiquetas “Hoy/Ayer/dd/mm/yyyy”; eventos ordenados por `timestamp`. Incluye `retirada`, `derrota` y `cancelada` aunque estén en claves raíz. `cancelada` sigue excluida de “Resultados por revisar” por decisión actual ([ExploracionActivaController.php:52-56](app/Http/Controllers/ExploracionActivaController.php:52)); se narraría si se muestra en otro sitio.
- **Hitos**: `emboscada_superada`, `veterano` (victorias ≥ 5), `recolector` (caramelos > 0), `sin_contratiempos`, `expedicion_express` (≤ 60 min). Solo datos de `resultado.incidentes`, recompensas y duración.
- **Ánimo**: frase determinista por rol y estado. Ejemplos: HP < 50 % → “Vuelve tocado; conviene dejarlo descansar.”; `exito_excepcional` → “Expedición redonda.”; rol RECOLECTOR → “Regresó con las manos llenas.” Sin aleatoriedad.
- **Miembro ausente**: en `/equipos` y `/habitats` se pinta badge “En exploración” sobre el miembro concreto y se deshabilitan sus acciones (liberar/evolucionar/caramelos/loadout). La tarjeta del equipo no se bloquea. En el panel de equipos de hábitat, el equipo sigue visible; si se va a iniciar combate 3v3, se muestra el miembro ausente y se deshabilita la acción (decisión abierta).

## 7. Pruebas

**Unitarias**
- `EconomiaRecompensasTest`: constantes legado/individual; `esIndividual`.
- `CalculadorRecompensasTest`: casos individuales `T=10000 → pool 5000, cuenta 2500, reclutado 2500`; impar `T=3 → pool 1, cuenta 0, reclutado 1`; categoría 0,7; tipos duales; caramelos al 50 %; casos legados intactos.
- `CalculadorFinExploracionTest`: hora futura mismo día; hora pasada → día siguiente; nocturna 22:00→02:00; `duracion_horas`; indefinida.
- `EventoExploracionVistaTest`: descanso muestra `duracion_minutos` y `hp_recuperado`; narración con nombre; `derrota`/`cancelada`.
- `DiarioExploracionTest`: agrupación, orden, eventos raíz, ausencia de duplicados.

**Feature**
- `ExploracionesIndividualEndpointsTest`: `inicio_exploracion` no nulo; response 201 aditiva; otro miembro del equipo explorando no bloquea (ya existe, conservar); anti-IDOR.
- `ExploracionesPageTest`: `reclutado`/`explorador` en lugar de `equipo`; `return_time` 12:50; `return_time` pasado → día siguiente; fin coherente con inicio.
- `ExploracionesTest`: EXP de cuenta y reclutado con nueva economía; invariante `cuenta + reclutado = pool`; EXP de tipo dividida; caramelos al 50 %; ruta intacta.
- `ExploracionesPreviewTest`: riesgo con `CalculadorRiesgo`; etiquetas no nulas; economía reflejada.
- `EquiposPayloadTest`: sin `equiposEnExploracion`; con `reclutadosEnExploracion`; equipo editable con miembro ausente.
- `EquiposControllerTest`: eliminar expectativas de “No se puede modificar un equipo con exploraciones activas”; añadir que formación/items se permiten.
- `ReclutadoLiberarTest`: bloqueo solo del reclutado que explora, no del compañero.
- `ValidadorExploracionTest`: retirar `equipoDisponible*`; añadir `reclutadoEnExploracion`.
- `TeamTest`: retirar `isExploring`.

**Vista / accesibilidad**
- `ExploracionesViewTest`: nuevas cadenas individuales, cabecera, diario, hitos, `aria-label` de HP/EXP, foco y `aria-expanded` de bitácora. Los arrays con `'equipo'` siguen funcionando por alias.
- `HabitatsViewTest` / `FrontendHabitatModalTest`: retirar `equiposEnExploracion`; badge de miembro ausente.

**Arquitectura**
- `deptrac`: dominio sin `App\`/Eloquent; `EconomiaRecompensas` y `ResultadoRecompensas` en Domain.
- `phpstan` y `pint` sobre archivos nuevos.

Comandos del proyecto: `php artisan test` (script `composer test`) o `vendor/bin/phpunit`. No ejecutar con BD real.

## 8. Riesgos y rollback

| Riesgo | Mitigación |
|---|---|
| Doble descuento del 50 % | Solo `CalculadorRecompensas` aplica el factor a EXP/caramelos; `PersistirRecompensas` no descuenta; test de invariante. |
| Romper recompensas de ruta | `OtorgarRecompensasRuta` no pasa política → `legado()`; tests de ruta intactos. |
| Cambio de contrato de vista | `equipo` se mantiene como alias; Blade migra a `explorador`; tests actualizados. |
| WIP no commiteado | No revertir; aplicar cambios sobre el estado actual; revisar diff por archivo antes de editar. |
| Combate con miembro ausente | El equipo ya no se bloquea; añadir guarda de inicio de combate por miembro o mantener deshabilitada la acción con mensaje. Decisión abierta. |
| `return_time` pasado | Se interpreta como siguiente día; test explícito y aviso en UI (“Regresar antes de las…”). |
| Concurrencia del tick | Fuera de Fase 1; documentar `withoutOverlapping()` en backlog. |
| Datos históricos | `exp` conserva semántica de cuenta; no se migran resultados antiguos. Rollback: revertir archivos por commit; no hay migraciones. |

**Rollback**: no hay migraciones. Revertir los cambios de economía y presentación en orden inverso (primero vista, luego handler, luego dominio), manteniendo `ResultadoRecompensas` compatible. No usar `git reset --hard` ni `git clean`.

## 9. Backlog Fase 2

1. `withoutOverlapping()` y bloqueo pesimista en `exploraciones:procesar` ([routes/console.php:13](routes/console.php:13)).
2. Sustituir `ultimo_procesado` por idempotencia fuerte (tabla de ticks o `updated_at` condicional).
3. Eliminar `llegada_destino` y, si producto lo pide, añadir fase de viaje/“Preparando”.
4. Unificar preview de riesgo y recompensas en un único caso de uso; eliminar la fórmula simple del controlador.
5. DTOs completos para eventos/hallazgos y eliminar arrays asociativos en contratos de dominio.
6. Reducir `ProcesarExploracionHandler`/`FinalizarExploracionHandler` (god-handlers) en casos de uso más pequeños.
7. Eliminar alias `'equipo'` y constantes legadas de equipo si la ruta deja de reutilizar `PersistirRecompensas`.
8. Sustitución de miembro ausente en combate 3v3.
9. Revisar `SinergiaEquipo`/`CalculadorCapacidadEquipo` legados.

## 10. Decisiones abiertas

1. **“Preparando”**: ¿existe fase de viaje o se descarta? Fase 1 no la emite.
2. **Recogida anticipada**: ¿mostrar “Recoger ahora” en toda exploración activa o solo en indefinidas? El endpoint ya lo permite.
3. **Combate con miembro ausente**: ¿se permite 2v3, se bloquea el equipo o se exige sustitución?
4. **“Objetos”**: ¿el product owner se refería a caramelos o a objetos equipados? No hay recompensa de objetos en el DTO.
5. **Reparto de EXP de tipo**: se propone 50/50; confirmar si las Sendas deben recibir solo la mitad o el total.
6. **Canceladas**: siguen excluidas de “Resultados por revisar”; ¿deben tener histórico visible?
7. **`analysis.md`**: no estaba en la ruta indicada; confirmar que el contexto delegado es la fuente vigente.

## 11. Orden de implementación

1. Dominio: `EconomiaRecompensas`, `ReglasExploracion`, `ResultadoRecompensas`, `CalculadorRecompensas`.
2. Aplicación: `PersistirRecompensas`, `FinalizarExploracionHandler`.
3. Tiempo: `CalculadorFinExploracion`, `ExploracionActivaController`.
4. Presentación: `TransformadorResultadoExploracion`, `ExploradorVista`, `DiarioExploracion`, `HitoExploracion`, `FraseAnimo`, `EventoExploracionVista`, `PresentadorExploraciones`.
5. Vistas: partials de exploraciones, `index`, `habitats/show`, `equipos/index`.
6. Bloqueo individual: controladores, `Team`, `ValidadorExploracion`, `src/Equipos/App/*`.
7. Tests por capas y regresión.

---

**Estado**: diseño listo para revisión. **No implementado**, **no escrito en disco** y **sin pruebas ejecutadas**. Próximo paso: guardar este contenido como `.opencode/work/TASK-003/architecture.md` y solicitar aprobación del plan v1.

---

# 12. Addendum — Procesamiento bajo demanda en `/exploraciones`

> Requisito añadido por el desarrollador el 2026-11-01: «la vista de exploraciones debe ejecutar
> procesar, no esperar al job». Diseño del `architect`; se incorpora a la Fase 1.

## Decisión recomendada

**Procesar en `index()` de forma síncrona, antes de consultar las listas, solo para el usuario
autenticado, mediante un servicio compartido y un lock por exploración.** No usar AJAX en Fase 1:
añade una segunda petición, estados de carga y otra superficie de autorización; el `index()` ya es
el punto donde el usuario espera datos frescos. Un endpoint explícito de refresco queda para Fase 2.

## Diseño

### 1. Servicio compartido

Crear `Src\Exploraciones\App\ProcesarExploracionesPendientes`:

```php
public function paraUsuario(int $userId, int $limite = 10, int $presupuestoMs = 2000): ResultadoProcesamientoPendiente;
public function paraTodas(int $limite = 500): ResultadoProcesamientoPendiente; // consola
public function una(ExploracionActiva $exp, bool $forzarRegreso = false): bool; // recoger
```

`ResultadoProcesamientoPendiente` (readonly): `procesadas`, `omitidas`, `fallidas`,
`agotadoPorTiempo`.

**Selección para la vista**: `where('user_id', $userId)->whereNull('regreso')->orderBy('inicio_exploracion')->limit($limite)`
con eager loading `reclutado.pokemon.stats`, `reclutado.pokemon.types`, `habitat`. No usar
`withoutUserScope()` en HTTP: el global scope de `BelongsToUser` ya filtra por `Auth::id()`
([BelongsToUser.php:21-24](app/Models/Concerns/BelongsToUser.php:21)); el `user_id` explícito es
defensa en profundidad y hace el servicio testeable. La consola sí usa `withoutUserScope()`, como
ahora ([ProcesarExploraciones.php:32-42](app/Console/Commands/ProcesarExploraciones.php:32)).

**Throttle**: leer `eventos['ultimo_procesado']` y omitir si es posterior a `now() − 30 s`. Es JSON,
no columna indexada; se filtra en PHP tras cargar como máximo 10 filas. Un cursor futuro es válido:
el handler lo adelanta con el tiempo perdido
([ProcesarExploracionHandler.php:72-108](src/Exploraciones/App/ProcesarExploracionHandler.php:72)).

**Límite y presupuesto**: 10 exploraciones y 2 s por carga de página; la consola 500. Entre
elementos, si se agota el presupuesto, se detiene y se marca `agotadoPorTiempo`.

### 2. Lock

Clave `exploracion:procesar:{id}`, TTL 60 s, adquisición **no bloqueante** (`Cache::lock(...)->get()`),
liberación en `finally` alrededor de `$bus->dispatch()`.

Debe ir en el **servicio**, no en `ProcesarExploracionHandler`: el bus envuelve el handler en
`UnitOfWork::transaction()` ([LaravelCommandBus.php:28-33](app/Bus/LaravelCommandBus.php:28)); un
lock dentro del handler se liberaría antes del commit y abriría una ventana de lectura de datos no
confirmados. El lock por exploración permite paralelismo entre expediciones distintas y serializa
las del mismo `id`. El cache por defecto es `database` ([config/cache.php:20](config/cache.php:20)),
que soporta locks atómicos.

Si no se adquiere el lock: en `index()` se omite silenciosamente; en `recoger` se responde 409 JSON
o redirect con aviso «La exploración se está procesando; recarga en unos segundos», sin repetir
recompensas.

### 3. `index()` y `recoger`

`index()`:
1. `$resultado = $servicio->paraUsuario((int) Auth::id())`.
2. Una **única consulta** `ExploracionActiva::where('user_id', Auth::id())->with(...)->get()`.
3. Particionar en PHP: activas = `regreso === null`; terminadas = `regreso !== null` y no
   canceladas. Así una fila finalizada durante el procesamiento no aparece a la vez en ambas listas,
   que es el riesgo de las dos consultas actuales ([ExploracionActivaController.php:43-57](app/Http/Controllers/ExploracionActivaController.php:43)).
4. Pasar `avisoProcesamiento` y `agotadoPorTiempo` a la vista.

`recoger()` pasa a `$servicio->una($exploracion, forzarRegreso: true)`
([ExploracionActivaController.php:340](app/Http/Controllers/ExploracionActivaController.php:340)).
La consola usa `paraTodas()` y conserva su selección global; el lock evita que coincida con la
página.

### 4. Fallos y latencia

- `try/catch (Throwable)` por exploración; `Log::warning('exploraciones.procesar_pendientes.fallo', ['exploracion_id', 'user_id'])`;
  continuar. La página siempre renderiza.
- Aviso discreto en Blade si `fallidas > 0` o `agotadoPorTiempo`: «No se pudieron actualizar todas
  las expediciones; se reintentará automáticamente.» Sin detalles técnicos.
- Si el lock está ocupado, no es un error: otra petición/cron ya está procesando.
- La página puede tardar; el límite y el presupuesto acotan el coste. No se añaden colas ni workers.

## Archivos

| Archivo | Acción |
|---|---|
| `src/Exploraciones/App/ProcesarExploracionesPendientes.php` | crear |
| `src/Exploraciones/App/ResultadoProcesamientoPendiente.php` | crear |
| `app/Http/Controllers/ExploracionActivaController.php` | modificar `index()` y `recoger()` |
| `app/Console/Commands/ProcesarExploraciones.php` | modificar para usar `paraTodas()` |
| `resources/views/exploraciones/index.blade.php` | aviso de procesamiento |
| `tests/Unit/Exploraciones/ProcesarExploracionesPendientesTest.php` | crear |
| `tests/Feature/Exploraciones/ExploracionesIndexProcesaTest.php` | crear |
| `tests/Feature/Exploraciones/ExploracionesRecogerLockTest.php` | crear |
| `tests/Feature/ProcesarExploracionesCommandTest.php` | crear/ampliar |

## Pruebas

- **Unitarias** (bus falso): solo procesa el usuario indicado; respeta límite; omite por
  `ultimo_procesado` reciente; aísla excepciones; no adquiere lock ocupado; corta por presupuesto.
- **Feature `index`**: exploración vencida pasa de activa a terminada tras `GET /exploraciones` y
  persiste recompensas; no toca exploraciones de otro usuario; con handler que lanza, la página
  sigue 200 y muestra aviso; sin activas no despacha.
- **Feature `recoger`**: lock ocupado → 409/redirect y sin doble recompensa; lock libre → finaliza
  una vez.
- **Consola**: sigue procesando todos los usuarios y comparte lock.
- **Vista**: banner accesible (`role="status"`).

## Riesgos nuevos

| Riesgo | Mitigación |
|---|---|
| Duplicado cron/página/recoger | Lock por `id` fuera de la transacción, TTL 60 s, no bloqueante. |
| Latencia de página | Límite 10, presupuesto 2 s, lock ocupado se omite. |
| Lock expira si un tick tarda >60 s | TTL 60 s; si se detecta, subir a 120 s o renovar; el handler normal es rápido. |
| `GET` con efectos secundarios | Procesamiento idempotente y sin cambios visibles salvo recompensas/estado; aceptado en Fase 1. |
| Cursor JSON no indexado | Límite pequeño y filtro PHP; columna/index en Fase 2. |
| Scope global eludido | Nunca `withoutUserScope()` en HTTP; `user_id` explícito. |
| Cache sin locks | Store por defecto `database` soporta locks; validar `CACHE_STORE` en despliegue. |

**Movido a Fase 1**: lock compartido y servicio de procesamiento pendiente. **Sigue en Fase 2**:
`withoutOverlapping()` global, columna indexada de cursor, endpoint AJAX de refresco y colas.
