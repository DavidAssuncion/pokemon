# TASK-003 — Plan v1

- **ID:** TASK-003
- **Versión:** v2
- **Estado:** PENDIENTE DE APROBACIÓN
- **Fecha:** 2026-11-01
- **Proyecto:** `/home/david/pokemon`
- **Base:** `analysis.md` (analistas A y B) + `architecture.md` (architect) + decisiones del desarrollador.

## 1. Objetivo

Convertir Exploraciones en un módulo **individual coherente**: un reclutado por expedición, sin
nomenclatura ni bloqueos de equipo heredados, con una economía explícita (50 % de recompensas y
reparto de EXP 50/50 cuenta-reclutado) y una capa de personalidad construida con datos existentes
(nombre, rol, nivel, EXP, shiny, HP, descanso, incidentes y diario). La vista `/exploraciones`
procesa bajo demanda para no esperar al job. Sin migraciones.

## 2. Diagnóstico que justifica el plan

El flujo persistido ya es individual: `exploraciones_activas.reclutado_id`, combate 1v1 y un único
destinatario de recompensas. El síntoma «se envían equipos» se explica por tres residuos:

1. La presentación publica `equipo` y la UI dice «tus equipos».
2. Un miembro en exploración bloquea y etiqueta su equipo completo (`team_members` +
   `Team::isExploring()`).
3. La EXP se divide entre 3 (`MIEMBROS_EQUIPO`), aunque solo explore un reclutado.

No existe un envío real de equipos en rutas, controladores ni JS. Evidencia completa en
`analysis.md`.

## 3. Alcance (Fase 1)

### 3.1 Economía

- **Regla global:** todas las recompensas de una expedición se reducen al **50 %** de la tasa
  actual.
- **EXP:** el pool resultante se reparte **50 % cuenta / 50 % reclutado**.
  - `T = Σ expDerrota` de los derrotados.
  - `pool = max(0, floor(T × multiplicadorCategoría × 0,5))`.
  - `expCuenta = intdiv(pool, 2)`; `expReclutado = pool − expCuenta`.
  - Invariante: `expCuenta + expReclutado = pool`.
  - Ejemplo: `T = 1000`, multiplicador 1,0 → pool 500, cuenta 250, reclutado 250.
- **EXP de tipo:** mismo tratamiento (pool × 0,5, reparto 50/50); la mitad de la cuenta va a
  Sendas y la mitad del reclutado a `reclutados.exp.tipos`.
- **Caramelos:** `floor(cantidadBase × multiplicador × 0,5)`.
- **Capturas:** se reduce la **probabilidad** al 50 %, no la cantidad; así una captura única no
  desaparece por truncado.
- **Caramelos, capturas y objetos:** van íntegros a la cuenta del jugador (los reclutados no
  tienen inventario).
- **Objetos perdidos:** `objetos_perdidos` es una penalización por derrota, no una recompensa; no
  se reduce.
- **Ruta:** `OtorgarRecompensasRuta` conserva la política legada (sin cambios de economía), para
  no alterar las recompensas de combate de ruta.

### 3.2 Corrección del bug individual/equipo

- La vista deja de usar `equipo` como sujeto: pasa a `explorador`/`reclutado` y textos en singular.
- Se mantiene `'equipo'` como **alias deprecado** en el payload de presentación durante Fase 1
  para no romper consumidores; Blade no lo usa.
- Se elimina `equiposEnExploracion` de `PlayerController` y `HabitatsController`.
- Se elimina `Team::isExploring()` y las guardas de equipo de `src/Equipos/App/*` y
  `TeamController`.
- Solo se bloquea el **reclutado** que explora: no se puede enviar dos veces, liberar, evolucionar,
  cambiar loadout, etc. El equipo sigue visible y editable.
- La UI del equipo marca al **miembro ausente** individualmente.
- **Combate:** se exige equipo completo. Si un miembro está explorando, no se puede iniciar el
  combate (entrenadores, ruta, gimnasios, mazmorras) hasta que vuelva, con mensaje claro. La guarda
  se centraliza en `ConstruirEquipoJugador::desdeEquipo()`, usado por los cuatro flujos.
- `POST /exploraciones` legado se conserva y se marca deprecado; no se elimina en Fase 1.

### 3.3 Ciclo de vida y tiempos

- Al crear la exploración: `inicio_exploracion = now()`.
- `llegada_destino` queda deprecada; no se elimina la columna en Fase 1.
- `return_time` se interpreta como la **siguiente ocurrencia** desde el inicio:
  - Si la hora es posterior al inicio, hoy.
  - Si es igual o anterior, mañana.
  - Ejemplo: inicio 23:00, `return_time` 01:00 → mañana 01:00.
- El preview usa el mismo cálculo de fin que el procesamiento, no `Carbon::today()`.
- Si se envían `duracion_horas` y `return_time` a la vez, se rechaza con 422 (evita ambigüedad).
- `store-individual` responde `201 {ok, id, inicio, fin, estado}` de forma aditiva.
- Estados: `explorando` y `volviendo`; no se introduce «Preparando» en Fase 1. `fase` se deriva de
  `inicio_vuelta`.
- La tarjeta muestra progreso, tiempo restante y fecha/hora completa (no solo `HH:mm`).

### 3.4 Personalidad (sin datos nuevos)

- **Cabecera del explorador:** icono, nombre, shiny, nivel calculado, EXP total, progreso al
  siguiente nivel, rol y tipos.
- **HP y descanso:** barra de HP desde `eventos['explorador']`; si no hay datos, estado «sin
  incidencias». El evento `descanso` muestra `duracion_minutos` y `hp_recuperado` (hoy muestra 0).
- **Diario:** agrupado por fecha (`Hoy`, `Ayer`, `dd/mm/yyyy`), ordenado por timestamp, con la
  bitácora y los eventos raíz `retirada`, `derrota` y `cancelada`.
- **Hitos:** derivados de `resultado.incidentes` y recompensas: emboscada superada, veterano
  (≥5 victorias), recolector (caramelos > 0), sin contratiempos, expedición express (≤60 min).
- **Ánimo:** frase determinista por rol, HP y categoría final; sin aleatoriedad ni promesas de
  efectos inexistentes.
- **Preview:** etiquetas no nulas para caramelos de familia/EV y aviso de la economía 50/50.

### 3.5 Procesamiento bajo demanda

La vista `/exploraciones` deja de esperar al job de cada 5 minutos:

- `ExploracionActivaController::index()` ejecuta, antes de consultar las listas, un servicio
  `ProcesarExploracionesPendientes::paraUsuario()` para las exploraciones activas del usuario
  autenticado.
- **Límite**: 10 exploraciones y presupuesto de 2 s por carga; si se agota, se corta y se avisa.
- **Throttle**: se omite la exploración si `eventos['ultimo_procesado']` es posterior a
  `now() − 30 s`. Así un refresco repetido no vuelve a procesar lo mismo.
- **Lock**: clave `exploracion:procesar:{id}`, TTL 60 s, adquisición no bloqueante, liberación en
  `finally`. Se adquiere en el servicio, **fuera** de la transacción del `CommandBus`, para no
  liberar el lock antes del commit.
- **Una sola consulta** para activas y terminadas, particionada en PHP, para que una exploración
  finalizada durante el procesamiento no aparezca en ambas listas.
- **Fallos**: se capturan por exploración, se registran en log y la página sigue renderizando con
  un aviso discreto. Un lock ocupado no es error: otro proceso está trabajando.
- `recoger()` usa el mismo servicio; si el lock está ocupado, responde 409/redirect con aviso y no
  duplica recompensas.
- La consola `exploraciones:procesar` usa `paraTodas()` y comparte el lock, evitando solaparse con
  la página.

Sin AJAX, sin colas ni workers nuevos en Fase 1. El endpoint AJAX de refresco queda para Fase 2.

### 3.6 Fuera de alcance (Fase 2 o posterior)

- Expediciones por equipo real de 3 miembros.
- Rasgos persistentes, ánimo persistido, relaciones, heridas permanentes, historial completo.
- Inventario por reclutado.
- Refactor del god-handler `ProcesarExploracionHandler`.
- Unificación del preview de riesgo (`CalculadorRiesgo`/`CalculadorMatchups`) con el preview del
  controlador.
- Idempotencia fuerte del tick, `withoutOverlapping()` global y constraint único parcial. El lock
  por exploración sí entra en Fase 1 por el procesamiento bajo demanda.
- Eliminación de `llegada_destino`, del alias `equipo` y del endpoint legado `POST /exploraciones`.
- Objetos de botín nuevos con catálogo y reglas.
- Migraciones de base de datos.

## 4. Arquitectura y contratos

### 4.1 Política económica

Nuevo VO de dominio `EconomiaRecompensas`:

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
}
```

`CalculadorRecompensas::calcular(..., ?EconomiaRecompensas $economia = null)` usa `legado()` por
defecto. `FinalizarExploracionHandler` pasa `individual()`. El factor se aplica **una sola vez** en
dominio; persistencia y presentación no vuelven a descontar.

`ResultadoRecompensas` conserva `expTotal` (cuenta) y `expPorMiembro` (destino), y añade
`expTipoCuenta` con fallback a `expTipoPorMiembro` para no romper la ruta.

### 4.2 Contrato de vista

Se añade al payload de cada exploración:

```php
'explorador' => [
  'id', 'nombre', 'pokemon_id', 'icono', 'nivel', 'exp_total', 'exp_tipos',
  'es_shiny', 'rol', 'rol_label', 'tipos',
  'hp_actual', 'hp_max', 'hp_pct', 'hp_estado', 'hp_label', 'hp_clase',
  'barreras' => [...], 'animo' => ['texto', 'tono'], 'progreso_nivel',
],
'diario' => ['total', 'eventos', 'grupos' => [...], 'hitos'],
'fase' => 'ida|vuelta',
'restante_min' => ?int,
'restante_label' => ?string,
```

Se mantienen `'equipo'`, `'reclutado'` y `'bitacora'` como compatibilidad. Blade usa
`explorador` y `diario`.

### 4.3 Persistencia

Sin migraciones. `eventos['resultado']` añade `exp_reclutado` de forma aditiva; `exp` sigue siendo
la EXP de cuenta. Los resultados históricos no se migran.

## 5. Archivos y responsables

> Los archivos con WIP local no se revierten. Cada especialista revisa `git diff` del archivo antes
> de editarlo y limita el cambio a su responsabilidad.

| Archivo | Acción | Responsable |
|---|---|---|
| `src/Exploraciones/Domain/EconomiaRecompensas.php` | crear | `backend` |
| `src/Exploraciones/Domain/ReglasExploracion.php` | modificar | `backend` |
| `src/Exploraciones/Domain/CalculadorRecompensas.php` | modificar | `backend` |
| `src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php` | modificar | `backend` |
| `src/Exploraciones/App/PersistirRecompensas.php` | modificar | `backend` |
| `src/Exploraciones/App/FinalizarExploracionHandler.php` | modificar | `backend` |
| `src/Exploraciones/Domain/CalculadorFinExploracion.php` | modificar | `backend` |
| `src/Exploraciones/Domain/EstimadorRecompensasExploracion.php` | modificar | `backend` |
| `src/Exploraciones/App/ProcesarExploracionesPendientes.php` | crear | `backend` |
| `src/Exploraciones/App/ResultadoProcesamientoPendiente.php` | crear | `backend` |
| `app/Http/Controllers/ExploracionActivaController.php` | modificar | `backend` |
| `app/Console/Commands/ProcesarExploraciones.php` | modificar | `backend` |
| `app/Http/Controllers/PlayerController.php` | modificar | `backend` |
| `app/Http/Controllers/HabitatsController.php` | modificar | `backend` |
| `app/Http/Controllers/TeamController.php` | modificar | `backend` |
| `app/Http/Controllers/ReclutadoController.php` | modificar | `backend` |
| `app/Models/Team.php` | modificar | `backend` |
| `src/Habitats/App/ValidadorExploracion.php` | modificar | `backend` |
| `src/Equipos/App/{AgregarMiembro,QuitarMiembro,EliminarEquipo,ActualizarFormacion,ActualizarPosturas,ActualizarRolMiembro}.php` | modificar | `backend` |
| `src/CombateEntrenadores/App/ConstruirEquipoJugador.php` | modificar (guarda de equipo completo) | `backend` |
| `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php` | modificar | `backend` |
| `src/Exploraciones/Presentation/ExploradorVista.php` | crear | `backend` |
| `src/Exploraciones/Presentation/DiarioExploracion.php` | crear | `backend` |
| `src/Exploraciones/Presentation/HitoExploracion.php` | crear | `backend` |
| `src/Exploraciones/Presentation/FraseAnimo.php` | crear | `backend` |
| `src/Exploraciones/Presentation/EventoExploracionVista.php` | modificar | `backend` |
| `src/Exploraciones/Presentation/PresentadorExploraciones.php` | modificar | `backend` |
| `src/Exploraciones/Presentation/ResultadoExploracionVista.php` | modificar | `backend` |
| `resources/views/exploraciones/index.blade.php` | modificar | `frontend` |
| `resources/views/exploraciones/_explorador.blade.php` | crear | `frontend` |
| `resources/views/exploraciones/_diario.blade.php` | crear | `frontend` |
| `resources/views/exploraciones/_evento.blade.php` | modificar | `frontend` |
| `resources/views/habitats/show.blade.php` | modificar | `frontend` |
| `resources/views/equipos/index.blade.php` | modificar | `frontend` |
| `tests/Unit/Exploraciones/**` | crear/modificar | `backend` |
| `tests/Feature/*Explora*`, `tests/Feature/Equipos*`, `tests/Feature/ReclutadoLiberarTest.php` | crear/modificar | `backend` |
| `tests/Unit/Exploraciones/ProcesarExploracionesPendientesTest.php` | crear | `backend` |
| `tests/Feature/Exploraciones/ExploracionesIndexProcesaTest.php` | crear | `backend` |
| `tests/Feature/Exploraciones/ExploracionesRecogerLockTest.php` | crear | `backend` |
| `tests/Feature/ProcesarExploracionesCommandTest.php` | crear/ampliar | `backend` |
| `tests/Feature/*ViewTest.php`, `tests/Feature/Frontend*` | crear/modificar | `frontend` |
| `src/Exploraciones/context.md`, `src/Exploraciones/README.md` | actualizar | `documenter` |
| Migraciones | no aplica | — |

## 6. Orden de ejecución y dependencias

1. **Dominio económico** (`EconomiaRecompensas`, `ReglasExploracion`, `ResultadoRecompensas`,
   `CalculadorRecompensas`) + tests unitarios.
2. **Aplicación económica** (`PersistirRecompensas`, `FinalizarExploracionHandler`) + tests de
   integración.
3. **Tiempo y creación** (`CalculadorFinExploracion`, `ExploracionActivaController`) + tests.
4. **Procesamiento bajo demanda** (`ProcesarExploracionesPendientes`,
   `ResultadoProcesamientoPendiente`, `ExploracionActivaController::index/recoger`,
   `ProcesarExploraciones`) + tests de lock y refresco.
5. **Bloqueo individual y guarda de combate** (controladores, `Team`, `ValidadorExploracion`,
   `src/Equipos/App/*`, `ConstruirEquipoJugador`) + tests.
6. **Contratos de presentación** (`TransformadorResultadoExploracion`, `ExploradorVista`,
   `DiarioExploracion`, `HitoExploracion`, `FraseAnimo`, `EventoExploracionVista`,
   `PresentadorExploraciones`, `ResultadoExploracionVista`) + tests unitarios.
7. **Vistas** (`exploraciones/*`, `habitats/show`, `equipos/index`) + tests de vista/accesibilidad.
8. **Documentación** (`context.md`, `README.md`).
9. **Revisión, QA y seguridad** independientes.
10. **Informe final**.

Dependencias: 2 depende de 1; 3 y 4 pueden ir en secuencia tras 1; 5 depende de 1-4; 6 depende de 5;
7 depende de 6; 8 depende de 7; 9 depende de 8; 10 depende de 9. La base de datos no tiene trabajo en
Fase 1.

## 7. Criterios de aceptación

1. Enviar una exploración individual crea **una** fila con `reclutado_id`, `inicio_exploracion` no
   nulo y sin payload de equipo; la respuesta 201 incluye `id`, `inicio`, `fin` y `estado`.
2. La UI de exploraciones no usa «equipo» como sujeto del explorador; muestra nombre, rol, nivel,
   EXP, shiny y HP. El alias `equipo` sigue existiendo en el payload por compatibilidad, pero Blade
   no lo consume.
3. Un miembro en exploración **no** bloquea el equipo completo: el equipo sigue editable y visible;
   solo se marca/bloquea al reclutado. Iniciar combate con un miembro ausente muestra un error
   claro y no crea la batalla.
4. Economía verificable: `T=1000`, multiplicador 1,0 → pool 500, cuenta 250, reclutado 250;
   carameles base 10 → 5; probabilidad de captura ×0,5; recompensas de ruta sin cambios.
5. `return_time` 01:00 con inicio 23:00 → mañana 01:00; una hora pasada salta al día siguiente; no
   finaliza de inmediato. Enviar `duracion_horas` y `return_time` juntos devuelve 422.
6. El diario agrupa por fecha, muestra la duración del descanso y `hp_recuperado`, y narra
   retirada/derrota/cancelación. Los hitos y el ánimo usan solo datos existentes.
7. El preview muestra etiquetas no vacías para familia/EV y el factor económico 0,5.
8. `GET /exploraciones` procesa las exploraciones activas del usuario antes de renderizar: una
   expedición vencida pasa de «Activa» a «Resultados por revisar» en la misma carga, sin esperar al
   job. El lock impide doble procesamiento con el cron o con `recoger`; los fallos no rompen la
   página y se muestra un aviso discreto.
9. Todos los tests relevantes pasan; `phpstan`, `pint` y `deptrac` no añaden errores nuevos.
10. No se revierte ni se sobrescribe el WIP preexistente; no se añaden migraciones ni dependencias.
11. Revisión, QA y seguridad emiten veredictos sin hallazgos críticos abiertos.

## 8. Pruebas requeridas

**Unitarias**
- `EconomiaRecompensasTest`: políticas legado/individual.
- `CalculadorRecompensasTest`: pool 50 %, reparto 50/50, impar, categoría, tipos duales, caramelos
  50 %, rama legada intacta.
- `CalculadorFinExploracionTest`: hora futura, hora pasada, nocturna, duración, indefinida.
- `EventoExploracionVistaTest`: descanso, nombres, retirada/derrota/cancelada.
- `DiarioExploracionTest`: agrupación, orden, eventos raíz y no duplicados.
- `ExploradorVistaTest` / `HitoExploracionTest` / `FraseAnimoTest`: datos existentes, estados
  vacíos y determinismo.
- `ProcesarExploracionesPendientesTest`: solo el usuario indicado, límite, throttle por
  `ultimo_procesado`, aislamiento de excepciones, lock ocupado, presupuesto agotado.

**Feature**
- `ExploracionesIndividualEndpointsTest`: creación individual, `inicio_exploracion`, 201 aditiva,
  anti-IDOR, doble envío rechazado.
- `ExploracionesPageTest` / `ExploracionesViewTest`: contrato nuevo, textos individuales, cabecera,
  diario, hitos, accesibilidad.
- `ExploracionesTest`: economía nueva, invariante cuenta+reclutado, tipos, caramelos, capturas,
  ruta intacta, históricos compatibles.
- `ExploracionesPreviewTest`: etiquetas y factor económico.
- `EquiposControllerTest` / `EquiposViewTest`: equipo editable con miembro ausente; badge
  individual; sin `equiposEnExploracion`.
- `HabitatsViewTest` / `FrontendHabitatModalTest`: no se bloquea el equipo entero.
- `ReclutadoLiberarTest`: solo se bloquea el reclutado que explora.
- `ConstruirEquipoJugadorTest` (o el test de combate correspondiente): miembro ausente → error y
  no se crea batalla.
- `ValidadorExploracionTest`: `reclutadoEnExploracion`; sin `equipoDisponible*`.
- `TeamTest`: sin `isExploring`.
- `ExploracionesIndexProcesaTest`: una expedición vencida pasa de activa a terminada en el `GET`,
  no toca a otros usuarios, un fallo del handler no rompe la página y se muestra el aviso.
- `ExploracionesRecogerLockTest`: lock ocupado → 409/redirect sin doble recompensa; libre → una
  sola finalización.
- `ProcesarExploracionesCommandTest`: la consola procesa todos los usuarios y comparte el lock.

**Calidad**
- `vendor/bin/phpunit --filter ...` para iterar; una pasada de la suite relevante al cierre.
- `vendor/bin/phpstan analyse` y `vendor/bin/pint --test` sobre los archivos tocados.
- `vendor/bin/deptrac analyse` si el proyecto lo tiene configurado.

## 9. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Doble descuento del 50 % | Solo `CalculadorRecompensas` aplica el factor; test de invariante y revisión de `PersistirRecompensas`. |
| Cambio de economía rompe la ruta | `OtorgarRecompensasRuta` usa `legado()`; tests de ruta intactos. |
| Romper consumidores de la vista | Alias `equipo` + `reclutado`/`explorador`; Blade migra; tests actualizados. |
| WIP no commiteado | Revisar `git diff` por archivo; no revertir; cambios limitados. |
| Equipo editable con miembro ausente | Guarda central en `ConstruirEquipoJugador`; test de los cuatro flujos. |
| `return_time` nocturno | Cálculo relativo al inicio + test explícito. |
| Redondeos de EXP | Un único truncado por agregado; test de impar y de tipos duales. |
| Complejidad de presentación | DTOs pequeños y deterministas; Blade solo pinta. |
| Duplicado cron/página/recoger | Lock por exploración fuera de la transacción, TTL 60 s y no bloqueante. |
| Latencia de `GET /exploraciones` | Límite 10, presupuesto 2 s, throttle 30 s y continuación en el siguiente tick. |
| Lock expira en un tick largo | TTL 60 s; si se detecta, subir a 120 s o renovar; el tick normal es rápido. |
| Fallo al procesar desde la vista | `try/catch` por exploración, log y aviso; la página sigue renderizando. |
| `GET` con efectos secundarios | Procesamiento idempotente y acotado; aceptado en Fase 1; endpoint AJAX en Fase 2. |
| Concurrencia global del tick | `withoutOverlapping()` sigue en Fase 2; el lock por exploración cubre el caso de la vista. |

## 10. Rollback

No hay migraciones. Si la Fase 1 debe revertirse:

1. Revertir primero las vistas y los partials.
2. Revertir la capa de presentación (`ExploradorVista`, `DiarioExploracion`, etc.).
3. Revertir controladores y guardas de bloqueo/combate.
4. Revertir la aplicación económica (`PersistirRecompensas`, `FinalizarExploracionHandler`).
5. Revertir el dominio económico (`EconomiaRecompensas`, `CalculadorRecompensas`,
   `ResultadoRecompensas`).

`ResultadoRecompensas` mantiene compatibilidad para que los pasos 4-5 no rompan datos históricos.
No se usará `git reset --hard`, `git clean` ni `git checkout --`.

## 11. Decisiones resueltas y pendientes

Resueltas:

- Exploración individual coherente; no equipo real.
- Dos fases; Fase 1 acotada a bug, economía y personalidad con datos existentes.
- Recompensas al 50 %; EXP 50/50 cuenta/reclutado; caramelos/capturas a la cuenta.
- Solo se bloquea al reclutado; combate exige equipo completo.
- Sin «Preparando»; sin rasgos persistentes; sin migraciones.
- La vista `/exploraciones` procesa bajo demanda con límite, presupuesto, throttle y lock por
  exploración.

Pendientes menores (no bloquean la aprobación, se resolverán en implementación):

- Recogida anticipada para exploraciones temporizadas: se mantiene el comportamiento actual
  (solo indefinidas) en Fase 1.
- Historial de canceladas: se mantiene la exclusión actual de «Resultados por revisar».
- Objetos de botín nuevos: fuera de alcance; `objetos_perdidos` no se toca.

## 12. Aprobación

Este plan requiere una aprobación explícita que mencione la versión, por ejemplo:

> `APROBADO plan v2`

o

> `Implementa el plan v2`

Sin esa aprobación no se modificará ningún archivo de aplicación.
