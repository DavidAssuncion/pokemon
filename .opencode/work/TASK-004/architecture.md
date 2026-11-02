# TASK-004 — Arquitectura

> Resultados literales de `architect`: diseño base y addendum de roles de niveles
> de hábitat. El orchestrator no altera las conclusiones.

---

# Modelo económico objetivo y estrategia de rebalanceo

> Diseño **read-only**. No se ha modificado ningún archivo, no se han ejecutado migraciones, seeders ni escrituras, y no se ha leído `.env`. Todos los valores «actuales» citan `archivo:línea`; los «propuestos» son constantes o reglas a implementar.

## DECISIÓN

Sustituir la economía actual —tres políticas de recompensa conviviendo, precios escritos para una escala de ingresos que el juego no produce y cuatro fuentes sin cota— por **una única política de recompensas con multiplicadores por actividad, un invariante contable de EXP y una lista de precios recalibrada a una tasa de referencia de 60 caramelos por hora de juego activo**.

En una frase: **el problema no es cuánto produce el juego, sino cuánto cuesta lo que vende**. La evidencia lo respalda: el caramelo EV individual (26 caramelos → 255 EV) y la evolución a nivel 20 (127 caramelos) ya están bien precio; el nido nivel 5 cuesta 1.675 familia (28 h al ritmo real) y la Senda nivel 5 cuesta 1.250 tipo (21 h), cuando sus hitos equivalentes deberían costar 2,5 h y 8 h.

## CONTEXTO

### Síntesis del diagnóstico

Cinco hechos hacen inviable el balance actual:

1. **La economía crea EXP de la nada.** `EconomiaRecompensas::legado()` reparte `fraccionCuenta = 1.0` **y** `fraccionDestino = 0.8` (`src/Exploraciones/Domain/EconomiaRecompensas.php:43-44`), de modo que cada derrota genera **1,8× su EXP** (100 % a la cuenta + 80 % al equipo). La exploración usa la política individual (`FinalizarExploracionHandler.php:149`), cuyo reparto sí suma 1,0. De ahí el «4×» de I3: no es solo el factor 0,5, es inflación estructural.
2. **La lista de precios pertenece a otra economía.** Con la tasa real de ingresos (≈60 caramelos de cada clase por hora activa a nivel 25), los costes actuales implican:

   | Sumidero | Coste actual | Horas al ritmo real | Horas objetivo |
   |---|---|---|---|
   | Nido 5 | 1.675 familia | 28 h | 2,5 h |
   | Refugio 5 | 3.475 familia | 58 h | 3 h |
   | Complemento 5 (1 stat) | 4.032 EV + 1.344 familia | 67 h | 4,3 h |
   | Senda N5 (1 tipo) | 1.250 tipo | 21 h | 8 h |
   | Todos los nodos (639) | 57.920 tipo | 965 h | ~100 h |
   | EV 255 individual | 26 EV | 0,4 h | 0,4 h ✅ |
   | Evolución nivel 20 | 127 tipo | 2,1 h | 2,1 h ✅ |

3. **Las fuentes no están acotadas.** La ruta no tiene cooldown ni registro (`src/CombateRuta/App/RegistrarResultadoRuta.php:10-13`), el descarte convierte capturas en familia sin coste (`ReclutamientoController.php:126-131`), la mazmorra multiplica **también la EXP** por 70 (`OtorgarRecompensasMazmorra.php:39-40` → `CalculadorRecompensas.php:348`) y la emboscada evitada cobra como victoria completa más un caramelo y un evento extra (`FinalizarExploracionHandler.php:216-227`, `:322-354`).
4. **La generación de eventos depende de la frecuencia de llamadas, no del tiempo.** `numEncuentros()` suma el bonus de rango **por tick** (`ProcesarExploracionHandler.php:411-420`) mientras el scheduler corre cada 5 min (`routes/console.php:13`); con `intdiv(5, 30) = 0` el término temporal desaparece y refrescar la página multiplica la tasa por ~5. Con bonus 0 la exploración rinde literalmente cero.
5. **Dos topes de EV sobre el mismo campo.** `ReclutadoController.php:147` corta en 255 y `AplicarEvsFamilia.php:30` en 512. Un complemento de nivel 3 deja al reclutado en 256 y bloquea todos los caramelos EV individuales.

A esto se suman fallos de coherencia percibida: preview que usa otra fórmula (`EstimadorRecompensasExploracion.php:74-94`), caramelos con cantidad 0 persistidos (`CalculadorRecompensas.php:195-217` frente a `:223-232`), y la EXP de Senda que se otorga pero no se muestra en ningún modal (`PersistirRecompensas.php:134-137`).

### Supuesto de sesión (fijado para todo el documento)

- **Jugador típico**: 1 h/día × 5 días/semana = **5 h/semana ≈ 21,7 h/mes**.
- **Nivel de referencia**: L=25 (`base_experience` 80 ⇒ `e = intdiv(80×25,5) = 400` por derrota; `src/Shared/Domain/NivelHelper.php:39-42`).
- **Derrotado de referencia**: fase 1,5, `effort` 1,5, un tipo.
- **Hora de juego activo**: 10 combates de ruta (3 derrotados cada uno) o la mezcla equivalente ruta + 9 entrenadores/día.
- **Tasa de referencia resultante: 60 caramelos de cada clase por hora activa** (40 ruta + ~20 entrenadores/prorrateo). Es el ancla de todo el rebalanceo. La exploración es tiempo pasivo y no cuenta en esa hora.

## OPCIONES EVALUADAS

1. **Retocar solo constantes** (bajar 1.675→300, 400→50, etc.) manteniendo las tres políticas y las fugas. Descartada: los precios volverían a desajustarse en cuanto se arregle I1 o se explote I6, y no corrige la inflación 1,8× ni el doble tope de EV.
2. **Una sola política de recompensas con multiplicadores por actividad e invariante contable** (elegida). Coste: refactor de `CalculadorRecompensas` y de sus cuatro llamadores; beneficio: una sola fuente de verdad, precios estables y preview reutilizable.
3. **Introducir una moneda blanda (“polvo”, “energía”)** para desacoplar fuentes de sumideros. Descartada por restricción explícita: no hay necesidad demostrable; los tres caramelos ya funcionan como monedas separadas.
4. **Hacer la granja global por familia** en lugar de por hábitat. Descartada en esta iteración: exige migración de datos y cambia el modelo de dominio; se resuelve por UI (coste visible por hábitat) y queda como decisión abierta.

## 1. Principios económicos

1. **Una unidad por clase de caramelo, con conversión fija y visible**: `1 tipo = 100 EXP de tipo`, `1 EV = 10 EV individuales`, `1 familia = 1 punto de granja`. Ninguna pantalla puede insinuar otra equivalencia.
2. **Ninguna actividad crea EXP de la nada**: `fraccionCuenta + fraccionDestino = 1` y `factor ≤ 1`. El total repartido nunca supera `Σe`.
3. **Toda fuente está acotada por tiempo o por progresión**: límite diario con rendimientos decrecientes (ruta, descarte) o una sola vez (gimnasio, mazmorra, nodo).
4. **Todo sumidero tiene tope y precio visible antes de pagar**: nivel máximo, stat máximo, EXP máxima; la UI muestra coste real, coste acumulado y coste por hábitat.
5. **Ningún caramelo se destruye en silencio**: se rechaza antes de consumir, se avisa del último caramelo parcial, no se persisten cantidades 0.
6. **Una sola fórmula para preview y ejecución**: la estimación llama al mismo calculador de dominio con una muestra nominal determinista.
7. **Cada clase de caramelo tiene al menos dos sumideros independientes** para que ninguna moneda quede muerta al maxear su único uso.
8. **El coste de un hito se expresa en horas y no puede desviarse más de 2× de la mediana de su familia de hitos.** Es el criterio que permite revisar el balance sin volver a inventarlo.

## 2. Objetivos de progresión por sistema

Horas de **juego activo** para el jugador de referencia (60 caramelos/h). La columna «coste» es el gasto total del hito, no el salto final.

| Sistema | Hito | Coste objetivo | Horas objetivo | Justificación |
|---|---|---|---|---|
| Sendas | Primer nodo (N1) | 1 tipo | ~0,2 h (10 min reales por la tasa inicial) | Debe caber en la primera sesión; es el tutorial de la moneda. |
| Sendas | Senda N2 | 6.000 EXP = 60 tipo | 1 h | Primer «clic» de progresión. |
| Sendas | Senda N5 | 48.000 EXP = 480 tipo | **8 h** (≈1,5 semanas) | Hito horizontal por tipo; 18 tipos ⇒ ~100 h de contenido, coherente con un juego de colección. |
| Sendas | Senda completa de un tipo | 480 + ~330 nodos = 810 tipo | 13,5 h | Cierra la vertical de un tipo sin bloquear el resto. |
| Granja | Nido 5 | 150 familia | **2,5 h** | Mejora pasiva (IV + tiempo de huevo); no debe competir con la Senday. |
| Granja | Refugio 5 | 180 familia | **3 h** | Más potente (captura ×3, aparición ×3, habilita complementos); +20 % sobre nido. |
| Granja | Complemento 5 (1 stat) | 250 familia + 255 EV | **4,3 h** | Es la mejora más potente por stat; ~1,7× el refugio. |
| Granja | Granja completa de una familia | 1.830 familia + 1.530 EV | **30,5 h** | Dos monedas en paralelo; el cuello es familia. Objetivo de «familia favorita» a ~1,5 meses. |
| Reclutado | EV 255 en un stat | 26 EV | **0,4 h** | Ya está bien precio; no se toca. |
| Reclutado | Evolución a nivel 20 | 12.610 EXP = 127 tipo | **2,1 h** (1,6 h si se combina con EXP de combate) | Curva cúbica clásica; no se toca la fórmula. |
| Reclutado | Evolución a nivel 30 | 27.910 EXP = 280 tipo | **4,7 h** | — |
| Reclutado | Evolución a nivel 50 | 76.510 EXP = 766 tipo | **12,8 h** | Cola larga razonable. |
| Reclutado | Evolución a nivel 100 | 303.010 EXP = 3.031 tipo | **50 h** | Objetivo de largo plazo, no de sesión. |

> Los evolutivos de doble tipo duplican el coste (se exige el umbral **por cada tipo**, `ServicioEvolucion.php:100-112`). Es intencional y debe mostrarse en la UI.

## 3. Modelo de monedas y conversiones

| Moneda | Valor unitario | Se produce por | Se consume en |
|---|---|---|---|
| `tipo:{slug}` | **100 EXP de tipo** (Senda o evolución) | Combate: 40 % del EXP tipado del derrotado. Hallazgo de tipo. | Nodos de Senda, EXP de Senda, evolución de reclutado. |
| `ev:{stat}` | **10 EV** a un Pokémon; **1 EV familiar** en complemento | Combate: `effort` del derrotado. Hallazgo EV. | Caramelos EV individuales, complementos de granja. |
| `familia:{chain}` | **1 punto de granja** | Combate: fase evolutiva. Descarte de reclutables. Hallazgo de familia. | Nido, refugio, coste familia del complemento. |

### Reglas de conversión y equilibrio

- **Tipo**: la misma unidad en los dos sumideros (100 EXP). Un caramelo gastado en un nodo N3 (6) equivale a 600 EXP de Senda que no se gastan en la evolución. El coste de oportunidad es la decisión de diseño, y es idéntico en ambos lados.
- **EV**: hay un sobreprecio explícito y documentado del complemento. Un caramelo EV da 10 EV a un Pokémon; en un complemento da 1 EV **a toda la familia, retroactivo y permanente**. El ×10 paga el carácter familiar. Se escribe en la UI: «1 caramelo = 1 EV para toda la familia».
- **Familia**: es la única moneda sin conversión. Su demanda se agota al maxear la granja (13.214 → 1.830 por familia, y hay ~150 cadenas). Esto **agrava I12**: se propone un segundo sumidero en fase 5 (ver §9).
- **Desajuste evitado**: las tres monedas se producen en la misma proporción (~1:1:1 por combate) y sus demandas totales se han igualado a la misma escala temporal (30 h de granja, 100 h de Sendas, 50 h de evolución máxima). Ninguna moneda queda con demanda 10× superior a su oferta.

## 4. Tabla de cambios concretos

### 4.1 Sendas

| Elemento | Actual | Propuesto | Rationale | Archivos |
|---|---|---|---|---|
| Coste de nodo N1–N5 | 10 / 25 / 50 / 150 / 400 (`SendaCostes.php:14-20`) | **1 / 3 / 6 / 15 / 40** | El árbol completo pasa de 57.920 (965 h) a 6.009 (~100 h). N1 a 1 caramelo entra en la primera sesión. | `app/Support/SendaCostes.php` |
| Umbrales de nivel | 0 / 15.625 / 31.250 / 62.500 / 125.000 (`SendaProgreso.php:39-45`) | **0 / 6.000 / 12.000 / 24.000 / 48.000** | Progresión geométrica ×2 con tiempos 1/2/4/8 h. | `app/Support/SendaProgreso.php` |
| `EXP_MAXIMA` | 500.000 (`SendaProgreso.php:27`) | **48.000** (= umbral N5) | El tope de presentación pasa a ser el máximo real. Elimina el sumidero infinito y la barra que sube sin efecto. | `app/Support/SendaProgreso.php` |
| Gasto por encima del máximo | Permitido (`SendasController.php:168-170`) | **Rechazo 422** y consumo limitado a `ceil((48.000 − exp)/100)` | Cierra el sumidero sin retorno. | `app/Http/Controllers/SendasController.php`, `app/Support/SendaProgreso.php` |
| `EXP_POR_CARAMELO` | 100 (`SendaProgreso.php:32`) | **100 (sin cambio)** | Es la unidad canónica compartida con evolución. | — |
| Visibilidad de coste | Solo `nivel_requerido` y `coste` (`resources/views/sendas/show.blade.php:160`) | Añadir `exp_requerida` y `coste_exp` al nodo; línea «Nv 5 · 40 🍬 + 480 para el nivel» | Coste oculto (I11). | `app/Support/ArbolSendas.php`, vista `sendas/show` |

### 4.2 Multiplicadores por actividad y separación EXP/caramelos

| Actividad | M actual | M caramelos propuesto | M EXP propuesto | Notas |
|---|---|---|---|---|
| Exploración (éxito) | 0,5 (`ReglasExploracion.php:26`) | **0,5** | **0,5** | Sin cambio de factor; se corrige el reparto y la cadencia. |
| Ruta | 1,0 (`OtorgarRecompensasRuta.php:48`) | **1,0** | **1,0** | Deja de usar la economía legada (1,8×). |
| Entrenador | 2,0 (`OtorgarRecompensasEntrenador.php:34`) | **2,0** | **2,0** | Sin cambio. |
| Gimnasio | 10,0 (`OtorgarRecompensasEntrenador.php:36`) | **10,0** | **10,0** | Sin cambio. |
| Mazmorra | 70,0 (`OtorgarRecompensasMazmorra.php:39-40`) | **70,0** | **10,0** | El 70× es bono de botín de jefe; aplicado a EXP convertía un combate de 5 min en 4,4 h de progresión (I5). Un piso de mazmorra pasa de 52.500 a 3.750 EXP de cuenta. |

**Invariante nuevo** en `CalculadorRecompensas::calcular`:
`Σ repartido = floor(Σe_i × M_exp × factor)` con `cuenta + destino × miembrosDestino = pool` (el resto va al destino).

### 4.3 Límites y anti-grind

| Mecánica | Actual | Propuesto | Rationale | Archivos |
|---|---|---|---|---|
| Ruta | Sin límite (`RegistrarResultadoRuta.php:10-13`) | **Multiplicador diario 1,0 / 0,5 / 0,25** en combates 1-20 / 21-40 / >40 | Acota el grifo sin bloquear el juego ni tocar la tasa de shiny. Un jugador de 1 h/día nunca lo nota (10 combates). | `src/CombateRuta/Domain/PoliticaLimiteDiario.php` (nuevo), `OtorgarRecompensasRuta.php` |
| Descarte | `fase × cantidad` sin coste (`ReclutamientoController.php:126-131`) | Misma fórmula, **mismo contador diario que ruta** | Queda acotado por el límite de ruta: las capturas ya son la entrada. | `ReclutamientoController.php` |
| `$userId ?? 1` | Fallback al usuario 1 (`ReclutamientoController.php:135-139`) | **Excepción si no hay usuario autenticado** | Cierra I14 y evita caramelos ajenos. | `ReclutamientoController.php` |
| Senda | Gasto ilimitado | Tope en `EXP_MAXIMA` | Ver §4.1. | `SendasController.php` |
| Captura / shiny | Ruta float, exploración `mt_rand(1,100)/100` (`OtorgarRecompensasRuta.php:181-186`; `FinalizarExploracionHandler.php:484`) | **Una sola fuente `ProbabilidadCaptura` con roll `[0,1)`** | Coherencia (I13). El shiny **no** entra en el límite diario. | `src/Shared/Domain/ProbabilidadCaptura.php` |

### 4.4 Exploración: cadencia y reparto

| Elemento | Actual | Propuesto | Rationale |
|---|---|---|---|
| Nº de eventos | `intdiv(minutos, intervalo) + bonus` por tick (`ProcesarExploracionHandler.php:411-420`) | `esperados = floor(minutosTranscurridosDesdeInicio / intervaloEfectivo)`; `pendientes = esperados − eventos_generados` | Depende solo del tiempo transcurrido, nunca de la frecuencia de refresco (I1). Idempotente. |
| Bonus de rango | Suma eventos por tick (0-3) | **Reduce el intervalo**: `intervalo = 30 × (1−movilidad) / (1 + 0,10 × bonus)` | El rango acelera el ritmo pero no lo desacopla del reloj. |
| `MINUTOS_POR_ENCUENTRO` | 30 (`ReglasExploracion.php:19`) | **30 (sin cambio)** | Mantiene el diseño declarado: 2 eventos/h base. |
| Ráfaga por tick | Ilimitada | **Máx. 12 eventos por procesamiento**; el resto queda pendiente | Evita picos al volver de 72 h offline. |
| Hallazgo | `floor(1 × (1+bonus) × M)` ⇒ 0 por defecto (`CalculadorRecompensas.php:125-142`) | `max(1, floor(base × (1+bonus) × M))` | Un hallazgo siempre rinde ≥1 caramelo (I4). |
| Filas con 0 | Familia y EV no filtran (`:195-217`) | **Filtrar `cantidad > 0`** en las tres clases | Elimina filas basura en inventario y modal (I15). |
| Emboscada evitada | Cuenta como victoria + hallazgo + evento extra (`FinalizarExploracionHandler.php:216-227`, `:322-354`) | **No cuenta como derrotado**; conserva 1 hallazgo de familia y el evento extra | Elimina el doble premio sin quitar el incentivo a la detección (I7). |
| Castigo por derrota | `ceil(cantidad/2)` (`FinalizarExploracionHandler.php:571-621`) | **`intdiv(cantidad, 2)`** | Una pila de 1 deja de perderse al 100 % (I8). |
| Preview | Fórmula distinta y solo tipo dominante (`EstimadorRecompensasExploracion.php:74-94`, `:183-203`) | Reutiliza `CalculadorRecompensas` sobre una muestra nominal determinista del pool y devuelve **todos** los tipos | Elimina la divergencia preview/ejecución (I2, I10). |

### 4.5 Granja y EV

| Elemento | Actual | Propuesto | Rationale | Archivos |
|---|---|---|---|---|
| Coste nido | 25 / 75 / 225 / 450 / 900 = 1.675 (`CalculadoraBonosGranja.php:91-101`) | **10 / 20 / 30 / 40 / 50 = 150** | 2,5 h. | `CalculadoraBonosGranja.php` |
| Coste refugio | 25 / 75 / 225 / 450 / **2.700** = 3.475 (`:103-113`) | **15 / 25 / 35 / 45 / 60 = 180** | 3 h; elimina el salto anómalo ×6. | `CalculadoraBonosGranja.php` |
| Coste familia complemento | 64/128/256/384/512 = 1.344/stat (`:115-120`) | **30 / 40 / 50 / 60 / 70 = 250/stat** | 4,2 h; 6 stats = 1.500. | `CalculadoraBonosGranja.php` |
| Coste EV complemento | `3 × E(n)`: 192/384/768/1.152/1.536 = 4.032/stat (`:119`) | **51 por nivel = 255/stat** | 4,25 h. Alinea con el valor otorgado (255 EV). | `CalculadoraBonosGranja.php` |
| EV otorgado por complemento | 64/128/256/384/512 acumulados (`:122-132`) | **51 / 102 / 153 / 204 / 255 acumulados** (delta 51) | Un único tope de 255, sin excederlo nunca. | `CalculadoraBonosGranja.php`, `AplicarEvsFamilia.php` |
| Tope EV | 255 individual / **512 granja** (`ReclutadoController.php:147`; `AplicarEvsFamilia.php:30`) | **255 en ambas vías** (`ReglasEv::TOPE`) | Elimina el doble tope y el bloqueo de caramelos. | Nuevo `src/Shared/Domain/ReglasEv.php`; `AplicarEvsFamilia.php`, `ReclutadoController.php` |
| Desperdicio del último caramelo | Hasta 9 EV (`ReclutadoController.php:156-157`, `:184`) | Se mantiene el clamp, pero la UI avisa «último caramelo parcial» y el consumo se calcula con `intdiv` cuando el resto es 0 | Transparencia sin cambiar el modelo. | `ReclutadoController.php`, vista |
| Coste por hábitat | Oculto | Etiqueta «coste por hábitat» + resumen de familia global | El jugador debe saber que pagará otra vez en otro hábitat. | `resources/views/granjas/`, `ObtenerGranja.php` |

### 4.6 Evolución y captura

| Elemento | Actual | Propuesto | Rationale |
|---|---|---|---|
| Umbral de evolución | `10[(N+1)³−N³]` (`ServicioEvolucion.php:41-45`) | **Sin cambio** | Curva clásica; a 100 EXP/caramelo da tiempos aceptables (2,1 h a nivel 20). |
| Caramelo de tipo a reclutado | 100 EXP (`ReclutadoController.php:105,113`) | **Sin cambio** | Unidad canónica. |
| Probabilidad de captura | Cap 25/255; exploración ×0,5 adicional (`ProbabilidadCaptura.php:16-25`) | **Sin cambio de valor**; solo se unifica la fuente aleatoria | La captura no es el problema de balance. |
| Shiny | 1/2048 salvaje, 1/512 huevo (`ProbabilidadesShiny.php:13-14`) | **Sin cambio**, y **exento** del límite diario | El shiny hunting sigue siendo la válvula de escape del grinding. |

## 5. Correcciones estructurales I1–I15

| ID | Decisión | Contrato de comportamiento esperado | Archivos |
|---|---|---|---|
| **I1** | **Corregir ahora (fase 1)** | El número de eventos generados en un intervalo depende solo de los minutos transcurridos y de un contador persistido, no del número de ticks ni de refrescos. Dos regímenes de tick distintos producen el mismo total. | `ProcesarExploracionHandler.php`, `ReglasExploracion.php`, nuevo `CadenciaEventos.php` |
| **I2** | **Corregir ahora (fase 4, tras fijar la economía)** | El preview y la ejecución usan la misma fórmula y el mismo `EconomiaRecompensas`; para un pool y una semilla fijos, `preview == ejecución` en ±0. | `EstimadorRecompensasExploracion.php`, `ExploracionActivaController.php` |
| **I3** | **Corregir ahora (fase 1)** | Ninguna política crea más EXP que `Σe`; `fraccionCuenta + fraccionDestino = 1`; el factor por actividad solo reduce. | `EconomiaRecompensas.php`, `CalculadorRecompensas.php` |
| **I4** | **Corregir ahora (fase 1)** | Todo evento hallazgo produce ≥1 caramelo de su clase si el evento existe. | `CalculadorRecompensas.php` |
| **I5** | **Corregir ahora (fase 1)** | La mazmorra aplica 70× a caramelos y 10× a EXP; un piso produce 3.750 EXP de cuenta, no 52.500. | `OtorgarRecompensasMazmorra.php`, `CalculadorRecompensas.php` |
| **I6** | **Corregir ahora (fase 3)** | Ruta y descarte usan un contador diario persistido con multiplicador 1,0/0,5/0,25; el contador se incrementa en la misma transacción que el otorgamiento. | nuevo `PoliticaLimiteDiario`, `activity_daily_logs`, `OtorgarRecompensasRuta.php`, `ReclutamientoController.php` |
| **I7** | **Corregir ahora (fase 1)** | Una emboscada evitada no aporta recompensas de combate; solo el hallazgo único y el evento extra. | `FinalizarExploracionHandler.php` |
| **I8** | **Corregir ahora (fase 1)** | La pérdida por derrota es `floor(cantidad/2)` por clase; nunca se pierde una pila de 1. | `FinalizarExploracionHandler.php` |
| **I9** | **Fase posterior (limpieza)** | `nivelEntrenador` se elimina de la firma o se usa como modificador explícito; documentado que la EXP escala con el nivel del jugador a propósito. | `OtorgarRecompensasEntrenador.php` |
| **I10** | **Corregir ahora (fase 4)** | El preview lista todos los tipos con cantidad esperada, no solo el dominante. | `EstimadorRecompensasExploracion.php` |
| **I11** | **Corregir ahora (fase 2)** | Los modales de victoria y el preview muestran la EXP de Senda otorgada por tipo. | `DatosModalVictoria`, `ResultadoRuta`, `PresentadorResultadoBatalla` |
| **I12** | **Fase posterior (fase 5, requiere aprobación)** | Segundo sumidero de familia: conversión `familia → tipo del tipo primario de la cadena` a 2:1, con tope diario; o declarar «no aplica» y aceptar moneda muerta. | nuevo `ConvertirFamilia`, `ItemCatalogo` |
| **I13** | **Corregir ahora (fase 1)** | Una sola función de captura con roll `[0,1)` para ruta y exploración; probabilidad efectiva idéntica. | `ProbabilidadCaptura.php`, `FinalizarExploracionHandler.php` |
| **I14** | **Corregir ahora (fase 3)** | Sin usuario autenticado no se otorgan caramelos; se lanza excepción de dominio. | `ReclutamientoController.php` |
| **I15** | **Corregir ahora (fase 1)** | No se persisten filas con `cantidad = 0`; limpieza opcional de filas existentes (migración de datos, no destructiva). | `CalculadorRecompensas.php`, `PersistirRecompensas.php` |

## 6. Contratos e interfaces

### Dominio (puro, sin Laravel)

```php
// src/Exploraciones/Domain/EconomiaRecompensas.php  (MODIFICAR)
final readonly class EconomiaRecompensas
{
    public function __construct(
        public float $factor,           // ≤ 1.0
        public float $fraccionCuenta,
        public float $fraccionDestino,  // invariante: cuenta + destino = 1.0
        public int $miembrosDestino,
    ); // lanza InvalidArgumentException si el invariante no se cumple

    public static function individual(): self;    // 0.5, 0.5/0.5, 1
    public static function activaEquipo(): self;  // 1.0, 0.5/0.5, 3
    public static function legado(): self;        // @deprecated, redefinida 1.0, 0.2/0.8, 3
}

// src/Exploraciones/Domain/CadenciaEventos.php  (NUEVO)
final class CadenciaEventos
{
    public function intervaloEfectivo(int $minutosBase, CapacidadesStats $c, int $dificultad): int;
    public function esperados(int $minutosTranscurridos, CapacidadesStats $c, int $dificultad): int;
    public function pendientes(int $esperados, int $generados, int $maxPorTick): int;
}

// src/Exploraciones/Domain/CalculadorRecompensas.php  (MODIFICAR)
public function calcular(
    Collection $derrotados,
    callable $aleatorioCaptura,
    int $nivelSalvaje,
    float $multiplicador = 1.0,
    ?EconomiaRecompensas $economia = null,
    ?float $multiplicadorExp = null,   // nuevo; null ⇒ $multiplicador
): ResultadoRecompensas;
```

### Aplicación / dominio de cada módulo

```php
// src/CombateRuta/Domain/PoliticaLimiteDiario.php  (NUEVO)
final class PoliticaLimiteDiario
{
    public const USOS_COMPLETOS = 20;
    public const USOS_REDUCIDOS = 40;
    public function multiplicador(int $usosHoy): float; // 1.0 | 0.5 | 0.25
}

// src/Shared/Domain/ReglasEv.php  (NUEVO)
final class ReglasEv
{
    public const TOPE = 255;
    public const EV_POR_CARAMELO = 10;
    public static function caramelosHastaTope(int $actual): int; // ceil((255-actual)/10), mínimo 0
}

// src/Exploraciones/Domain/ProyeccionRecompensas.php  (NUEVO)
final readonly class ProyeccionRecompensas
{
    /** @return list<ItemProyectado> */
    public function proyectar(PoolHabitat $pool, int $horas, CapacidadesStats $c, int $dificultad, int $nivelJugador): array;
}
```

### DTOs modificados

- `ResultadoRecompensas`: sin cambios estructurales; se garantiza que `expTipoCuenta` **nunca** es `null` en las políticas nuevas (elimina el fallback implícito de `PersistirRecompensas.php:161-195`).
- `DatosModalVictoria`: añadir `array $expSendaPorTipo` (label ⇒ EXP) y `int $expSendaTotal` (I11).
- `ResultadoRuta`: añadir los mismos dos campos.
- `CosteCaramelos`: se mantiene; el complemento pasa a `new CosteCaramelos(ev: 51, familia: 30..70)`.

### Infraestructura

- `activity_daily_logs`: `user_id`, `actividad` (`ruta` | `descarte`), `fecha`, `contador`; `unique(user_id, actividad, fecha)`. Acceso vía `ContadorDiarioRepository` con `lockForUpdate` dentro de la transacción de otorgamiento.
- `PersistirRecompensas`: recibe el multiplicador diario ya calculado y no vuelve a descontar.

### Fuente única preview/ejecución

`EstimadorRecompensasExploracion` deja de tener fórmulas propias: construye una `Collection<PokemonDerrotado>` nominal (media ponderada del pool) y llama a `CalculadorRecompensas::calcular()` con la misma `EconomiaRecompensas` y los mismos multiplicadores. La cadencia sale de `CadenciaEventos`. Así I2 e I10 se corrigen por construcción, no por parche.

## 7. Flujo de datos (ejemplo canónico de prueba)

3 derrotados, `base_experience` 80, L=25 ⇒ `e=400`; fase 1,5; effort 1,5; 1 tipo.

**Ruta (fase 1, `activaEquipo`, M=1,0)**
1. `T = 3 × 400 = 1.200`.
2. `pool = floor(1.200 × 1,0 × 1,0) = 1.200`.
3. `cuenta = 600`; `destinoTotal = 600`; cada miembro `200`.
4. Caramelos: familia `floor(4,5 × 1,0) = 4`; EV `4`; tipo `floor(1.200 × 0,4 / 100 × 1,0) = 4`.
5. `PersistirRecompensas` suma EXP de cuenta, EXP por miembro y EXP tipada a Sendas.

**Exploración (individual, M=0,5)**
1. `pool = floor(1.200 × 0,5 × 0,5) = 300`; cuenta 150, destino 150. *(Con `factor 0,5` y `M 0,5`.)*
2. Caramelos: familia 2, EV 2, tipo `floor(1.200 × 0,4/100 × 0,5) = 2`.

**Mazmorra (activaEquipo, M_caramelos 70, M_exp 10)** — jefe `e=750`:
1. `pool = 750 × 10 = 7.500`; cuenta 3.750; cada miembro 1.250.
2. Caramelos: familia 315, EV 315, tipo 210.

## 8. Fases de implementación

### Fase 1 — Estabilización contable (la que arregla la economía)
**Objetivo:** que el sistema deje de crear recursos y de premiar dos veces. Sin estética.
- Invariante `fraccionCuenta + fraccionDestino = 1`; `activaEquipo()`; `legado()` deprecada.
- `calcular()` con `multiplicadorExp`; mazmorra 70/10.
- Hallazgo mínimo 1 y filtrado de ceros.
- Emboscada evitada fuera de `idsDerrotados`.
- Castigo de derrota `floor`.
- Tope EV único 255 (`ReglasEv`).
- Cadencia de eventos por tiempo + contador `eventos_generados`.
- Captura unificada.
- **Archivos:** `EconomiaRecompensas.php`, `CalculadorRecompensas.php`, `FinalizarExploracionHandler.php`, `ProcesarExploracionHandler.php`, `OtorgarRecompensasMazmorra.php`, `AplicarEvsFamilia.php`, `ReclutadoController.php`, `ReglasExploracion.php`, `ProbabilidadCaptura.php`, nuevo `CadenciaEventos.php`, nuevo `ReglasEv.php`.
- **Pruebas:** unitarias de invariante y de los valores canónicos; propiedad «dos regímenes de tick ⇒ mismo total de eventos».
- **Dependencias:** ninguna. **Es prerequisito de todo lo demás.**

### Fase 2 — Re-precio y curvas
- `SendaCostes`: 1/3/6/15/40. `SendaProgreso`: umbrales y `EXP_MAXIMA`.
- `CalculadoraBonosGranja`: nido, refugio, complemento (familia y EV), delta EV 51.
- Divisor de caramelo de tipo 500 → 250.
- Rechazo de gasto de Senda por encima del máximo.
- **Pruebas:** tablas de coste exactas; escenario «horas hasta hito» dentro de ±25 % de los objetivos.
- **Dependencias:** fase 1 (los precios se calibraron sobre la economía ya estabilizada).

### Fase 3 — Límites y anti-grind
- Migración `activity_daily_logs`.
- `PoliticaLimiteDiario` + integración en ruta y descarte, en la misma transacción que el otorgamiento.
- Guard de usuario en descarte.
- **Pruebas:** feature del multiplicador por contador; concurrencia (dos peticiones simultáneas no duplican contador).
- **Dependencias:** fase 1.

### Fase 4 — Coherencia preview/UI
- `ProyeccionRecompensas` compartida; preview con todos los tipos.
- EXP de Senda en modales y en el nodo.
- Coste por hábitat y saldo real en granja.
- **Pruebas:** igualdad preview/ejecución con semilla fija; snapshots de payloads.
- **Dependencias:** fases 1 y 2 (necesita los valores definitivos).

### Fase 5 — Sumidero de familia (opcional, requiere aprobación)
- Conversión `familia → tipo` a 2:1 con tope diario, o declarar I12 «no aplica».
- **Dependencias:** fase 3 (reutiliza el contador diario).

## 9. Criterios de aceptación verificables

1. **Invariante de EXP:** para cualquier colección de derrotados y cualquier política, `expCuenta + expDestino × miembrosDestino ≤ floor(Σe × M_exp × factor)`; la diferencia es solo el resto de `intdiv`.
2. **Sin inflación:** ninguna política produce más de `Σe` EXP total. `legado()` redefinida cumple el invariante.
3. **Ruta canónica:** 3 derrotados `e=400`, M=1,0 ⇒ cuenta 600, miembro 200, familia 4, EV 4, tipo 4.
4. **Mazmorra:** jefe `e=750` ⇒ cuenta 3.750 (no 52.500), familia 315, EV 315, tipo 210.
5. **Exploración determinista:** 1 h de expedición produce el mismo número de eventos con ticks de 1, 5 o 15 min, y con o sin refrescos intermedios.
6. **Hallazgos:** todo hallazgo de la bitácora aporta ≥1 caramelo; no existen filas con cantidad 0 tras persistir.
7. **Emboscada evitada:** no aparece en `idsDerrotados`; aporta exactamente 1 hallazgo de familia y 1 evento extra.
8. **Derrota:** una recompensa de cantidad 1 no se pierde; la pérdida es `floor(n/2)`.
9. **EV:** ningún reclutado puede superar 255 por ninguna vía; `darEvs` rechaza antes de consumir; el complemento 5 otorga 255 acumulados.
10. **Senda:** el gasto se detiene en 48.000 EXP y devuelve 422 con mensaje explícito; la barra llega a 100 % exactamente en el máximo.
11. **Precios:** `costeNido(5)=150`, `costeRefugio(5)=180`, `costeComplemento(5)=CosteCaramelos(ev:51, familia:70)`, `SendaCostes::coste(5)=40`, `SendaProgreso::umbral(5)=48_000`.
12. **Límite diario:** combate 20 ⇒ ×1,0; 21 ⇒ ×0,5; 41 ⇒ ×0,25; el contador es único por usuario, actividad y fecha.
13. **Preview = ejecución:** con pool y semilla fijos, la diferencia entre proyección y resultado real es ≤1 caramelo por clase.
14. **UI:** ningún endpoint devuelve un coste sin mostrar el acumulado del nivel ni el coste por hábitat.

### Estrategia de pruebas

- **Unitarias de dominio (PHPUnit, `tests/Unit`)**: `EconomiaRecompensas` (invariante), `CalculadorRecompensas` (valores canónicos por actividad, hallazgos ≥1, filtrado de ceros, reparto por tipo), `CadenciaEventos` (independencia del tick), `PoliticaLimiteDiario` (umbrales), `CalculadoraBonosGranja` (tablas), `SendaCostes`/`SendaProgreso` (tablas y clamp), `ReglasEv`.
- **Feature (`tests/Feature`)**: `POST /sendas/experiencia` con tope; `POST /sendas/desbloquear` con coste nuevo; `POST .../complements/{stat}/upgrade` con coste EV/familia nuevo; `POST /reclutado/{id}/dar-evs` en 255 y en tope; ruta con contador diario; descarte sin usuario.
- **Simulación económica determinista**: un test que recorre un escenario fijo (10 combates de ruta + 9 entrenadores + 8 h de exploración) y compara las horas hasta cada hito con la tabla de §2, tolerancia ±25 %. Sin aleatoriedad no controlada: los rolls se inyectan con una semilla fija.
- **Arquitectura**: test de que `src/Exploraciones/Domain` no importa `Illuminate`/`App` (ya existe la convención en `.ai/rules/ddd-modules.md`).

## 10. Riesgos, decisiones abiertas y alternativas descartadas

### Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| El nerf de la economía legada (−44 % de EXP total) se percibe como «me han quitado» | Alto | Aplicar en fase 1 junto con la subida de faucets legítimos (hallazgos ≥1) y comunicar en el changelog; el inventario y el progreso se conservan. |
| Jugadores con EV > 255 por complementos antiguos | Medio | **No migrar datos**: se conserva el valor, se bloquean nuevas ganancias y se muestra aviso. Requiere aprobación (§ decision abierta 1). |
| Jugadores que pagaron el refugio a 2.700 | Medio | Compensación opcional: devolver la diferencia de familia una vez. Requiere aprobación (§ decisión abierta 2). |
| Carrera en el contador diario | Medio | `lockForUpdate` y contador dentro de la misma transacción que el otorgamiento; test de concurrencia. |
| El límite diario de ruta puede sentirse arbitrario | Medio | Umbral 20 combates (2 h) por encima de la sesión típica; mensaje claro al entrar en tramo reducido. |
| Cambiar el divisor de tipo (500→250) duplica el botín de tipo de la mazmorra | Bajo | Aceptado porque los pisos son de una sola vez; si la mazmorra se hace repetible, bajar `M_caramelos` a 20. |
| `EXP_MAXIMA` 500.000 → 48.000 deja exp «huérfana» en BD | Bajo | Se conserva el valor, `resumen()` lo clampa y el gasto se bloquea. Sin migración. |

### Decisiones abiertas (requieren aprobación del desarrollador)

1. **EV > 255 existentes**: ¿clamp retroactivo con migración de datos, o legado intocable con bloqueo de nuevas ganancias? *Recomendación: legado intocable.*
2. **Compensación del refugio a 2.700**: ¿devolución única de la diferencia o ninguna?
3. **Sumidero de familia (I12)**: ¿conversión 2:1 a tipo, otro sumidero, o «no aplica»?
4. **Mazmorra**: confirmar que el 70× se mantiene en caramelos y baja a 10× en EXP (cambia una decisión de producto de 2026-10-31).
5. **Granja por hábitat vs por familia global**: se mantiene por hábitat en esta iteración (sin migración); confirmar que es aceptable.
6. **Siniestro sin nodos N5**: ¿se añaden nodos o se documenta como excepción?

### Alternativas descartadas

- **Subir el tope individual de EV a 512** para igualarlo con la granja: afecta al balance de combate, que está fuera de alcance.
- **Pity system / tablas de botín**: añade aleatoriedad no determinista y no resuelve el desajuste de precios.
- **Moneda blanda**: descartada por restricción de alcance; no hay necesidad demostrable.
- **Recalcular toda la economía por simulación Monte Carlo**: se sustituye por un escenario determinista con semilla fija, suficiente para las tolerancias del ±25 %.

## BORRADOR DE ADR

- **Título:** ADR-004 — Economía única de recompensas y re-precio de caramelos
- **Estado:** Propuesto (pendiente de aprobación del plan v1)
- **Contexto:** Tres políticas económicas convivían; la legada creaba 1,8× EXP por derrota; los precios de granja y Sendas estaban 3–15× por encima de la capacidad de ingresos; cuatro fuentes no tenían cota. La economía no era explicable en horas de juego.
- **Decisión:** Una sola política (`individual` para exploración, `activaEquipo` para ruta/entrenador/gimnasio/mazmorra) con invariante `fraccionCuenta + fraccionDestino = 1`; separación de multiplicadores EXP/caramelos; re-precio a una tasa de referencia de 60 caramelos/hora; topes únicos (EV 255, Senda 48.000 EXP); límite diario con rendimientos decrecientes en ruta y descarte; preview y ejecución sobre la misma fórmula.
- **Consecuencias:** El juego pierde ~44 % de EXP por derrota y gana predictibilidad; la granja pasa de 220 h a 30 h por familia; la Senda N5 de 21 h a 8 h; el árbol completo de 965 h a ~100 h. Se requiere una migración aditiva (`activity_daily_logs`) y ninguna migración de datos. Los jugadores conservan inventario y progreso. Los EV por encima de 255 quedan como legado (pendiente de decisión).

---

# ADDENDUM — Roles de niveles de hábitat

> Extiende `architecture.md` (TASK-004). No reescribe el documento: actualiza §2, §3, §4.2, §4.3, §5 y §6. Sin cambios en archivos, sin migraciones ni seeders, sin lectura de `.env`.

## 0. Qué cambia respecto al diseño anterior

| # | Sección afectada | Cambio |
|---|---|---|
| C1 | §3 (modelo de monedas) | La producción deja de asumirse 1:1:1. Medición: **familia 50/h, EV 42/h, tipo 60/h** en el nivel de referencia (2). |
| C2 | §2 (objetivos) | Los hitos de granja y EV se alargan entre +20 % y +40 % porque la tasa de EV medida es un 30 % inferior a la asumida. La Senday no cambia. |
| C3 | §4.2 (multiplicadores) | Se añade una dimensión nueva: **nivel de hábitat**. Dos multiplicadores (familia y EV), no uno. |
| C4 | §4.3 (captura) | Se sustituye el cap plano de 25/255 por una regla por nivel: **25 % / 14,7 % / 6,4 %**. El cap actual anula la diferencia de `capture_rate` medida. |
| C5 | §4.3 / §5 (descarte) | El descarte pasa de `fase × cantidad` a **1 familia por Pokémon** con contador diario. Deja de ser una fuente competitiva del nivel de farmeo. |
| C6 | §6 (contratos) | `DificultadHabitat` entra en el calculador; el nivel ya está en la batalla pero se descarta al otorgar. |
| C7 | §5 (I-nuevos) | Cuatro fallos estructurales adicionales (H1–H4), dos de ellos previos a este requisito. |

**No cambian** los precios de §4 (nodos, umbrales, nido, refugio, complemento) ni los topes (EV 255, Senda 48.000 EXP). El requisito de hábitats no altera la lista de precios; altera **quién produce a qué ritmo**.

## 1. Intención de diseño por nivel de hábitat

| Dimensión | Nivel 1 | Nivel 2 | Nivel 3 |
|---|---|---|---|
| **Rol** | Colección / captura | Farmeo equilibrado | Farmeo especializado |
| **Riesgo** | Bajo: stats totales ~302 (×1,00) | Medio: ~457 (×1,51) | Alto: ~505 (×1,67) |
| **Turnos por combate** | 6,0 | 13,3 | 16,8 |
| **Captura por derrotado** | **25,1 %** | 14,7 % | 6,4 % |
| **Capturas/hora** | **7,5** | 2,6 | 1,0 |
| **Familia/hora (total)** | 37,5 | 50,9 | **86,7** |
| **EV/hora** | 30,0 | 42,1 | **50,4** |
| **Tipo/hora** | 30,0 | **60,2** | 60,5 |
| **EXP de cuenta/hora** | 4.900 | **7.736** | 7.767 |
| **Uso esperado** | Completar Pokédex, cazar shiny, conseguir IVs, primeras capturas | Progresión general: EXP, Sendas, evolución | Farmear familia y EV para granja y complementos |
| **Jugador que lo elige** | Coleccionista, jugador nuevo, cazador de shiny | Jugador medio que sube nivel y Sendas | Jugador con equipo competitivo que invierte en granja |

**Lectura de la tabla:** el nivel 1 es el único que captura de verdad; el 2 es el mejor en EXP y tipo; el 3 es el mejor en familia y EV. Ninguno domina en las tres monedas, que es la condición para que los tres existan.

## 2. Mecanismo de incentivo

### Elección: **(a) multiplicador por nivel de hábitat, aplicado solo a familia y EV directos**, con la tabla derivada de (b) y verificada por el criterio de tiempo.

**Por qué no (c) recompensa por turno/tiempo:** premia el stalling de forma directa — cuanto más larga la pelea, más caramelo. Es exactamente el incentivo que hay que evitar, y el desarrollador lo señala («plantearse varios turnos»). Descartada.

**Por qué no (b) normalización completa por stats en tiempo de ejecución:** el calculador no recibe HP/Def/Atk (`PokemonDerrotado.php:19-28` solo lleva `baseExperience`, `fase`, `effort`, `tipos`, `captureRate`), y añadirlos permitiría que la recompensa dependiera de la composición del equipo del jugador (llevar un equipo débil → más turnos → más recompensa). Es explotable. Se usa (b) **solo como criterio de derivación estática** de la tabla.

**Por qué no un único multiplicador para las tres clases:** porque el `floor` destruye la señal de dificultad de forma distinta en cada una:

| Clase | Fórmula | Por combate (3 derrotados) | Crecimiento real |
|---|---|---|---|
| Familia | `floor(Σ fase × M)` (`CalculadorRecompensas.php:258-268`) | 3 / 4 / 7 | ×1,33 / ×2,33 |
| EV | `floor(Σ effort × M)` (`:276-287`) | 3 / 6 / 7 | ×2,00 / ×2,33 |
| Tipo | `floor(Σ e × 0,4/100 × M)` (`:313-335`) | 3 / 10 / 12 | ×3,33 / ×4,00 |
| Tiempo | modelo §3 | 1 / 1,66 / 1,98 | — |

El tipo ya crece por encima del tiempo porque divide un agregado grande de EXP. La familia es la que peor escala: `fase` media 1,07 → `floor(3×1,07)=3`, y su crecimiento por combate (×1,33) queda **por debajo** del crecimiento del tiempo (×1,66). Por eso el nivel 1 gana hoy.

### Tabla propuesta

| Nivel | `m_familia` | `m_ev` | `m_tipo` |
|---|---|---|---|
| 1 | **1,00** | **1,00** | 1,00 |
| 2 | **2,00** | **1,25** | 1,00 |
| 3 | **2,50** | **1,50** | 1,00 |

**Derivación (no es una tabla arbitraria):** el multiplicador corrige la diferencia entre el crecimiento de la recompensa base y el crecimiento del tiempo, hasta el objetivo declarado.

- Familia: `crecimiento_base × m / crecimiento_tiempo ≥ 1,30` (nivel 2) y `≥ 1,60` (nivel 3).
  Nivel 2: `1,33 × m / 1,66 ≥ 1,30 → m ≥ 1,62` → con `floor` se necesita **m = 2,00** (4 → 8).
  Nivel 3: `2,33 × m / 1,98 ≥ 1,60 → m ≥ 1,36`; se elige **2,50** para mantener el orden familia > EV y compensar que el `floor` de 3×2,40 = 7 pierde 0,2.
- EV: `crecimiento_base × m / crecimiento_tiempo ≥ 1,30 / 1,60`.
  Nivel 2: `2,00 × m / 1,66 ≥ 1,30 → m ≥ 1,08`; se elige **1,25** por margen ante el redondeo (6 → 7).
  Nivel 3: `2,33 × m / 1,98 ≥ 1,60 → m ≥ 1,36`; se elige **1,50** (7 → 10).

**No premiar el stalling:** el multiplicador es una constante indexada por el nivel del hábitat de la batalla, no por la duración. Alargar la pelea no cambia nada. ✓

**No se aplica a:** captura (tiene su propia regla, §5), descarte (idem), hallazgos de exploración, EXP de cuenta, EXP de Senda. Solo a `caramelosFamilia` y `caramelosEv` de los derrotados.

## 3. Modelo de tiempos y ratios

### Supuestos declarados

- 3 rivales por combate (`GeneradorEquipoRuta.php:64-67`), nivel de stats = nivel del jugador (`IniciarCombateRuta.php:47`), así que la dificultad diferencial viene de las especies.
- Turnos para derrotar a un rival ∝ `HP × Def` (aproximación de la fórmula de daño a nivel igual: `daño ∝ Atk/Def`, `turnos ∝ HP × Def / Atk`).
- `turnos_por_rival(nivel) = 2 × (HP×Def)_n / (HP×Def)_1`; nivel 1 = 2 turnos.
- Coste por turno **4 s** (selección + animación + mensaje); coste fijo por combate **20 s** (carga, transición, modal, curación).
- **Ancla absoluta: 10 combates/hora en nivel 1** (la misma de §2 del documento principal). Los niveles 2 y 3 se derivan por ratio, no por estimación independiente.

| Nivel | HP×Def | Turnos/rival | Turnos/combate | t (s) | Ratio tiempo | Combates/h |
|---|---|---|---|---|---|---|
| 1 | 2.590 | 2,00 | 6,0 | 44,0 | 1,00 | 10,00 |
| 2 | 5.736 | 4,43 | 13,3 | 73,2 | 1,66 | 6,02 |
| 3 | 7.240 | 5,60 | 16,8 | 87,2 | 1,98 | 5,04 |

> **Sensibilidad:** el coste fijo de 20 s es lo que impide que el ratio de tiempo iguale al de turnos (3,0). Si el coste fijo real fuese 0, el nivel 3 tardaría ×2,8 y los multiplicadores de EV tendrían que subir a 1,5 / 2,0. El test de aceptación mide **caramelos/minuto con este modelo declarado**, no turnos, para que la calibración sea explícita.

### Ratios por hora

**Sin mecanismo (estado actual):**

| Nivel | Familia directa/h | Descarte/h | Familia total/h | EV/h | Tipo/h | Ratio familia | Ratio EV |
|---|---|---|---|---|---|---|---|
| 1 | 30,0 | 7,5 | **37,5** | 30,0 | 30,0 | 1,00 | 1,00 |
| 2 | 24,1 | 2,6 | **26,7** | 36,1 | 60,2 | **0,71** | 1,20 |
| 3 | 35,3 | 1,0 | **36,3** | 35,3 | 60,5 | **0,97** | 1,18 |

→ **Confirmado el diagnóstico del desarrollador:** el nivel 2 rinde un 29 % menos de familia por minuto que el nivel 1, y el nivel 3 un 3 % menos. El nivel 1 domina el farmeo de familia pese a ser el nivel de captura. El EV mejora (1,20 / 1,18) pero no alcanza el objetivo.

**Con mecanismo (propuesto):**

| Nivel | Familia/combate | Familia/h | +Descarte | **Familia total/h** | EV/combate | **EV/h** | **Tipo/h** | **EXP/h** | **Capturas/h** |
|---|---|---|---|---|---|---|---|---|---|
| 1 | 3 | 30,0 | 7,5 | **37,5** | 3 | **30,0** | **30,0** | 4.900 | **7,5** |
| 2 | 8 | 48,2 | 2,6 | **50,9** | 7 | **42,1** | **60,2** | 7.736 | 2,6 |
| 3 | 17 | 85,7 | 1,0 | **86,7** | 10 | **50,4** | **60,5** | 7.767 | 1,0 |

| Métrica | Nivel 1 | Nivel 2 | Nivel 3 | Objetivo | Cumple |
|---|---|---|---|---|---|
| Familia/min | 1,00× | **1,36×** | **2,31×** | ≥1,30 / ≥1,60 | ✅ |
| EV/min | 1,00× | **1,40×** | **1,68×** | ≥1,30 / ≥1,60 | ✅ |
| Tipo/min | 1,00× | **2,01×** | **2,02×** | ≥1,30 / ≥1,60 | ✅ |
| Captura/min (L1 vs resto) | 1,00× | **0,35×** | **0,13×** | L1 ≥2× | ✅ |
| EXP/min | 1,00× | 1,58× | 1,59× | (sin objetivo) | — |

**Conclusión del modelo:** con `m_familia` 2,00/2,50 y `m_ev` 1,25/1,50, los dos niveles de farmeo superan el umbral en las tres monedas, y el nivel 1 conserva una ventaja de captura de 2,9× y 7,7×. El nivel 2 queda como el mejor en EXP y tipo; el 3 en familia y EV.

## 4. Impacto en el modelo ya propuesto

### 4.1 Tasa de referencia

| Concepto | Diseño anterior | Medición con datos reales (nivel 2, ruta sola) | Cambio |
|---|---|---|---|
| Familia/h | 60 | **50** | −17 % |
| EV/h | 60 | **42** | −30 % |
| Tipo/h | 60 | **60** | 0 |

Se mantiene el ancla de **10 combates/hora en nivel 1** y la referencia de farmeo pasa a ser el **nivel 2 sin entrenadores** (los entrenadores, gimnasios y mazmorras se contabilizan aparte como aceleradores).

### 4.2 Objetivos de progresión actualizados (§2 del documento principal)

| Hito | Coste (sin cambio) | Horas anteriores | **Horas nuevas** | Motivo |
|---|---|---|---|---|
| Senda N2 | 60 tipo | 1,0 | **1,0** | Tipo no cambia |
| Senda N5 | 480 tipo | 8,0 | **8,0** | Tipo no cambia |
| Senda completa (1 tipo) | ~810 tipo | 13,5 | **13,5** | Tipo no cambia |
| Nido 5 | 150 familia | 2,5 | **3,0** | Familia 50/h |
| Refugio 5 | 180 familia | 3,0 | **3,6** | Familia 50/h |
| Complemento 5 (1 stat) | 250 familia + 255 EV | 4,3 | **6,1** | EV 42/h: `255/42 = 6,1` (cuello) |
| Granja completa (1 familia) | 1.830 familia + 1.530 EV | 30,5 | **36,6** | `max(1830/50; 1530/42) = 36,6` |
| EV 255 individual | 26 EV | 0,4 | **0,6** | EV 42/h |
| Evolución nivel 20 | 127 tipo | 2,1 | **2,1** | Tipo no cambia |
| Evolución nivel 50 | 766 tipo | 12,8 | **12,8** | Tipo no cambia |

> Los cuatro hitos que se alargan lo hacen por **medición**, no por el requisito de hábitats: el documento anterior asumió 60 EV/h y la producción real de `effort` da 42. No se propone bajar precios para compensar (ver decisión abierta 6).

### 4.3 Multiplicadores por actividad (§4.2 del documento principal)

| Actividad | M caramelos | M EXP | Novedad |
|---|---|---|---|
| Ruta | 1,0 | 1,0 | **× `DificultadHabitat`** en familia y EV |
| Exploración | 0,5 | 0,5 | **× `DificultadHabitat`** en familia y EV (usa `$exploracion->nivel`) |
| Entrenador | 2,0 | 2,0 | Sin cambio (sin nivel de hábitat) |
| Gimnasio | 10,0 | 10,0 | Sin cambio |
| Mazmorra | 70,0 | 10,0 | Sin cambio |

El multiplicador **se compone con M**: `familia = floor(Σfase × M × m_familia(nivel))`. Con M=0,5 en exploración el efecto se atenúa proporcionalmente, que es lo correcto (la exploración es tiempo pasivo).

### 4.4 Inventario y compatibilidad

- Los caramelos ya obtenidos no cambian. No hay migración.
- Un jugador con reclutables acumulados del nivel 1 conserva su inventario; solo cambia lo que produce en adelante.
- La tasa de captura del nivel 1 sube del 9,8 % al 25,1 %: más reclutables por hora (×2,6). No requiere cambio de esquema; sí conviene revisar el límite de la vista de reclutables.

## 5. Descarte y captura

### 5.1 Captura: el cap actual anula la señal de dificultad

`ProbabilidadCaptura::probabilidad()` devuelve `min(capture_rate, 25)/255` (`src/Shared/Domain/ProbabilidadCaptura.php:16-25`). Con los datos medidos (166,0 / 74,8 / 54,7) **los tres niveles producen exactamente 9,8 %**: la diferencia de `capture_rate` entre niveles es inerte. Eso explica por qué hoy el nivel 1 no es «el nivel de captura»: lo es solo por velocidad de combate, no por probabilidad.

**Propuesta:**

```php
p(nivel) = min( 0.25 , (min(captureRate, 255) / 255) × factorCaptura(nivel) )
factorCaptura = [1 => 1.0, 2 => 0.5, 3 => 0.3]
```

| Nivel | `capture_rate` medio | Base | Factor | **p** | Capturas/combate | Capturas/h |
|---|---|---|---|---|---|---|
| 1 | 166,0 | 65,1 % | 1,0 | **25,0 %** | 0,75 | **7,5** |
| 2 | 74,8 | 29,3 % | 0,5 | **14,7 %** | 0,44 | 2,6 |
| 3 | 54,7 | 21,4 % | 0,3 | **6,4 %** | 0,19 | 1,0 |

- El tope del 25 % evita que especies con `capture_rate` 255 sean captura garantizada.
- El factor por nivel conserva la señal intra-nivel en 2 y 3 (el cap solo muerde en nivel 1).
- En exploración se mantiene el ×0,5 adicional (`FinalizarExploracionHandler.php:153`), que ahora se aplica sobre la probabilidad ya nivelada.

### 5.2 Descarte: deja de ser una fuente de farmeo

Hoy: `fase × cantidad` (`ReclutamientoController.php:126-131`). Con captura al 25 % en nivel 1, eso daría 7,5 × 1,07 ≈ **8 familia/h** solo por descartar, además de las 30/h directas. Sumado, el nivel 1 (37,5) se acercaría peligrosamente al nivel 2 (50,9) y rompería el incentivo.

**Propuesta:**

1. **Cantidad: `max(1, intdiv(fase, 2))`** → 1 caramelo por Pokémon en los tres niveles (media de fase 1,07 / 1,49 / 2,40). El descarte deja de escalar con la fase y se convierte en una propina por duplicado, no en una cosecha.
2. **Tope diario: 20 descartes con recompensa/día**, reutilizando el contador `activity_daily_logs` de la fase 3 del plan (`actividad = 'descarte'`). Por encima, el descarte sigue funcionando (libera espacio) pero no da caramelos.
3. **No recibe `DificultadHabitat`.** El descarte es ajeno al nivel del hábitat donde se capturó.
4. Se mantiene el guard de usuario: sin sesión, excepción (cierra I14).

**Resultado:** el descarte aporta 7,5 / 2,6 / 1,0 familia/h. Es el 20 % del ingreso del nivel 1 y menos del 5 % del nivel 3. No compite con el farmeo directo. La captura conserva su valor real: el Pokémon, sus IVs y el shiny.

## 6. Contratos

### Dominio (puro)

```php
// src/Exploraciones/Domain/DificultadHabitat.php  (NUEVO)
final readonly class DificultadHabitat
{
    public function __construct(public int $nivel);          // clamp 1..3
    public static function desdeNivel(?int $nivel): self;    // null → 1
    public function multiplicadorFamilia(): float;           // 1.00 | 2.00 | 2.50
    public function multiplicadorEv(): float;                // 1.00 | 1.25 | 1.50
    public function multiplicadorTipo(): float;              // 1.00 (explícito, para test)
}

// src/Exploraciones/Domain/CalculadorRecompensas.php  (MODIFICAR)
public function calcular(
    Collection $derrotados,
    callable $aleatorioCaptura,
    int $nivelSalvaje,
    float $multiplicador = 1.0,
    ?EconomiaRecompensas $economia = null,
    ?float $multiplicadorExp = null,
    ?DificultadHabitat $dificultad = null,   // NUEVO
): ResultadoRecompensas;
```

Aplicación interna, solo en dos métodos:
- `calcularCaramelosFamilia(..., DificultadHabitat $d)`: `floor(Σfase × M × d->multiplicadorFamilia())`.
- `calcularCaramelosEv(..., DificultadHabitat $d)`: `floor(Σeffort × M × d->multiplicadorEv())`.
- `calcularCaramelosTipo` **no** cambia.

### Aplicación

```php
// src/CombateRuta/App/RegistrarResultadoRuta.php
public function registrar(..., int $nivelHabitat = 1): ?ResultadoRuta;

// src/CombateRuta/App/OtorgarRecompensasRuta.php
public function otorgar(..., int $nivelHabitat = 1): ?ResultadoRuta;
```

### Captura

```php
// src/Shared/Domain/ProbabilidadCaptura.php  (EXTENDER, sin romper)
public const CAP_POR_NIVEL = [1 => 0.25, 2 => 0.25, 3 => 0.25];
public const FACTOR_POR_NIVEL = [1 => 1.0, 2 => 0.5, 3 => 0.3];

public static function probabilidadPorNivel(int $captureRate, int $nivelHabitat): float;
// @deprecated probabilidad() se conserva para flujos sin nivel (huevos, captura directa)
```

### Punto exacto donde hoy se pierde el dato

| Flujo | El nivel existe | Se usa al otorgar |
|---|---|---|
| Ruta | `meta['nivel']` (`IniciarCombateRuta.php:61`) | **No**: `procesarRuta()` solo lee `habitat_id` (`PresentadorResultadoBatalla.php:219`) y llama a `registrar()` sin nivel (`:236-242`) |
| Exploración | `$exploracion->nivel` (`ProcesarExploracionHandler.php:670`) | **No**: `calcular()` no lo recibe (`FinalizarExploracionHandler.php:149-157`) |
| Entrenador / gimnasio / mazmorra | No aplica | `nivelHabitat = 1` (multiplicador 1,0) |

El arreglo es de una línea en `procesarRuta()`: leer `$meta['nivel']` (ya persistido en la batalla) y propagarlo. **No** se reconsulta `pokemon_habitat`, porque el pivote puede cambiar y la batalla ya fijó su contexto.

### Archivos afectados

| Acción | Archivo |
|---|---|
| Crear | `src/Exploraciones/Domain/DificultadHabitat.php` |
| Modificar | `CalculadorRecompensas.php`, `ProbabilidadCaptura.php`, `RegistrarResultadoRuta.php`, `OtorgarRecompensasRuta.php`, `PresentadorResultadoBatalla.php`, `FinalizarExploracionHandler.php`, `ReclutamientoController.php` |
| Sin cambios | `GeneradorEquipoRuta.php` (el nivel ya llega), `OtorgarRecompensasEntrenador.php`, `OtorgarRecompensasMazmorra.php` |

## 7. Correcciones estructurales adicionales

| ID | Hallazgo | Decisión | Evidencia |
|---|---|---|---|
| **H1** | El cap de captura `25/255` **anula** la diferencia de `capture_rate` medida entre niveles (166 / 75 / 55 → 9,8 % en los tres). La columna de datos existe pero no produce efecto. | **Corregir ahora (fase 2)** | `ProbabilidadCaptura.php:16-25` |
| **H2** | El nivel de hábitat se persiste en la batalla pero **no llega al calculador**: `procesarRuta` lee `habitat_id` e ignora `nivel`. | **Corregir ahora (fase 2)** | `IniciarCombateRuta.php:58-64`; `PresentadorResultadoBatalla.php:219-242` |
| **H3** | `floor(fase)` y `floor(effort)` destruyen la señal de dificultad en valores pequeños (1/1/2 y 1/2/2), mientras `tipo` la conserva porque divide un agregado grande. La recompensa no depende de la dificultad real, solo de la actividad. | **Corregir ahora (fase 2)** con `DificultadHabitat` | `CalculadorRecompensas.php:258-287` vs `:313-335` |
| **H4** | La selección de especie dentro del nivel se pondera por `capture_rate` (`GeneradorEquipoRuta.php:118-140`), de modo que dentro de un mismo nivel aparecen más las especies fáciles de capturar (que suelen ser las más débiles). Sesga a la baja la dificultad efectiva y la recompensa media. | **No aplica en esta fase**; documentar y medir. Si se corrige, usar peso uniforme por especie. | `GeneradorEquipoRuta.php:118-140` |
| **H5** | `peligro` del hábitat se usa en exploración (`EvaluadorExploracion`) pero **no** en ruta, que no tiene noción de dificultad más allá del pool. | **Fase posterior**: unificar si se quiere una escala de dificultad única. | `Habitat.php:53-58`; `EvaluadorExploracion.php:61-71` |

## 8. Criterios de aceptación y pruebas

### Criterios

1. **Familia/min:** nivel 2 ≥ 1,30× nivel 1 y nivel 3 ≥ 1,60× nivel 1, con `m_familia = 1,00 / 2,00 / 2,50` y el modelo de tiempo de §3. Valores esperados: **1,36× / 2,31×**.
2. **EV/min:** nivel 2 ≥ 1,30× y nivel 3 ≥ 1,60×, con `m_ev = 1,00 / 1,25 / 1,50`. Esperado: **1,40× / 1,68×**.
3. **Tipo/min:** nivel 2 y 3 ≥ 1,60× sin multiplicador. Esperado: **2,01× / 2,02×**.
4. **Captura/min:** nivel 1 ≥ 2× nivel 2 y ≥ 2× nivel 3. Esperado: **2,9× / 7,7×**.
5. **Aislamiento:** el multiplicador no altera tipo, captura, descarte, hallazgos, EXP de cuenta ni EXP de Senda. Test explícito de que `multiplicadorTipo() === 1.0` y de que el resultado de `calcularCaramelosTipo` es idéntico con y sin `DificultadHabitat`.
6. **Flujos sin nivel:** entrenador, gimnasio y mazmorra producen exactamente lo mismo que antes del cambio (multiplicador 1,0).
7. **Descarte:** `max(1, intdiv(fase, 2))`; máximo 20 descartes con recompensa por usuario y día; el descarte 21 sigue liberando el Pokémon sin dar caramelos.
8. **Captura:** `p(1) = 25,0 %`, `p(2) = 14,7 %`, `p(3) = 6,4 %` con los `capture_rate` medios; ningún caso supera el 25 %.

### Pruebas

- **Unitarias de dominio**
  - `DificultadHabitatTest`: tabla de multiplicadores, clamp 1..3, `null → 1`.
  - `CalculadorRecompensasTest`: para un `Collection<PokemonDerrotado>` fijo (3 derrotados con los valores medios de cada nivel) y `M=1`, `DificultadHabitat` 1/2/3, afirmar familia `3 / 8 / 17` y EV `3 / 7 / 10` **exactos**; tipo `3 / 10 / 12`; y que el tipo no cambia al variar la dificultad.
  - `ProbabilidadCapturaTest`: `probabilidadPorNivel()` con `capture_rate` 166 / 74,8 / 54,7 y con 255 (cap).
  - `ModeloTiempoCombateTest` (helper de test, no producción): turnos 6,0 / 13,3 / 16,8 y ratios 1 / 1,66 / 1,98.
- **Feature**
  - Ruta: batalla con `meta['nivel'] = 3` otorga familia multiplicada por 2,5 y EV por 1,5; con `nivel = 1`, sin multiplicar.
  - Ruta: `meta` sin `nivel` (legado) → multiplicador 1,0, sin excepción.
  - Exploración: `$exploracion->nivel = 3` aplica los multiplicadores; `M=0,5` se compone correctamente.
  - Descarte: 20 descartes dan caramelos, el 21 no; el Pokémon se libera igual.
- **Simulación económica determinista** (`tests/Unit/Economia/SimulacionHabitatTest`): recorre los tres niveles con un pool fijo (medias medidas), sin aleatoriedad salvo un roll inyectado con semilla fija, y afirma los ratios de §3 con **tolerancia ±10 %**. Si el pool cambia y las medias se desvían >15 %, el test falla y obliga a re-derivar la tabla.

## 9. Riesgos y decisiones abiertas

### Riesgos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| La tabla de multiplicadores se calibró con las medias actuales del pool | Medio | Test de simulación con umbral de deriva del 15 %; tabla en un único VO (`DificultadHabitat`), no dispersa. |
| Subir la captura del nivel 1 del 9,8 % al 25 % multiplica por 2,6 los reclutables | Medio | Revisar paginación y acciones masivas de la vista de reclutables; el descarte ya está limitado a 20/día con recompensa. |
| Un jugador que no puede ganar nivel 3 farmea un 36 % más lento que quien sí | Bajo | Es el coste esperado de la verticalidad; nivel 2 sigue siendo válido para todo. |
| El modelo de tiempo (4 s/turno, 20 s fijos) es una suposición | Alto | Declarado explícitamente y probado como supuesto; la aceptación se mide con él. Si la telemetría lo contradice, se recalibra `m_ev`/`m_familia`, no la estructura. |
| Componer `M` con `DificultadHabitat` en exploración reduce el efecto (M=0,5) | Bajo | Es intencional: el tiempo pasivo no debe competir con el activo. |

### Decisiones abiertas (requieren aprobación)

1. **Tabla de multiplicadores**: `m_familia = 1,00 / 2,00 / 2,50` y `m_ev = 1,00 / 1,25 / 1,50`. ¿Se aprueba?
2. **Captura por nivel**: tope 25 % con factores 1,0 / 0,5 / 0,3. ¿Se aprueba, o se prefiere un nivel 1 más generoso (35 %) asumiendo más descarte?
3. **Descarte**: 1 familia por Pokémon y tope de 20/día con recompensa. ¿Se aprueba, o se prefiere eliminarlo por completo (0 caramelos)?
4. **Objetivos actualizados**: el complemento 5 pasa de 4,3 h a 6,1 h y la granja completa de 30,5 h a 36,6 h. ¿Se aceptan, o se bajan los precios de EV para conservar los objetivos anteriores?
5. **Hallazgos de exploración**: ¿deben escalar también con el nivel del hábitat, o se dejan planos en 1 caramelo?
6. **Nivel 3 como especialista**: aceptar que el nivel 2 sea el mejor en EXP/tipo y el 3 solo en familia/EV, o forzar que el 3 sea el mejor en todo subiendo su multiplicador (rompería la curva de EXP).
7. **H4 (peso por `capture_rate` en la selección)**: ¿se corrige a peso uniforme en esta tarea o se deja como deuda documentada?

---

# ADDENDUM II — Política de IV/EV por tier

> Extiende `architecture.md` (TASK-004) y su `ADDENDUM I`. Read-only: no se ha modificado ningún archivo ni ejecutado migraciones/seeders, y no se ha leído `.env`. Todas las cifras de stats se derivan de `BattleStats` con los valores medios del pool ya medidos.

## 0. Qué cambia respecto al diseño anterior

| # | Sección afectada | Cambio |
|---|---|---|
| C1 | Addendum I §2 (multiplicadores) | **Se sustituye la tabla completa**. `m_familia` 2,00/2,50 → **3,30/3,35**; `m_ev` 1,25/1,50 → **1,85/2,80**; `m_tipo` 1,00/1,00 → **1,60/1,90**. |
| C2 | Addendum I §3 (modelo de tiempos) | Los ratios de tiempo pasan de **1 / 1,66 / 1,98** a **1 / 2,62 / 3,26**: la política IV/EV multiplica la durabilidad efectiva del rival, no solo sus stats base. |
| C3 | Addendum I §1 (intención por nivel) | Se añade la columna de perfil IV/EV. El nivel 1 no solo captura más: ahora también es **más frágil** que hoy. |
| C4 | Addendum I §4.1 (tasas) | Las tasas de referencia **no cambian** (50 familia / 42 EV / 60 tipo por hora), porque la recalibración se hizo a "throughput constante". Lo que cambia es el precio por derrotado. |
| C5 | Addendum I §5.1 (captura) | Porcentajes intactos; **capturas/hora bajan** (7,5 / 1,68 / 0,59) al reducirse los combates por hora. |
| C6 | §4.6 (captura/shiny) del documento principal | El shiny deja de pelear con IV máximos; conserva IV máximos **solo como reclutado**. |
| C7 | §5 (I-nuevos) | Tres hallazgos nuevos (H6–H8), uno de ellos un choque de spec entre rutas y entrenadores. |
| C8 | §6 (contratos) | `DificultadHabitat` pasa a exponer multiplicadores por **tier**; se añade `PerfilIvsEvs` y `DistribuidorStatsValor::distribuirPorRanking`. |

**No cambian** los precios de §4 del documento principal, los topes ni los objetivos de §2. La política IV/EV **no altera el ritmo de progresión**: se paga sola con más caramelo por derrotado. Ese es el criterio de la recalibración.

## 1. Política IV/EV por tier

### 1.1 Definiciones

| Término | Valor | Justificación |
|---|---|---|
| **Máximo IV** | **15** | Tope de la escala del proyecto (`GeneradorIvs.php:27-30`). |
| **Máximo EV** | **255** | Tope por stat (`ReclutadoController.php:147`; `BattleStats` divide por 4). |
| **Mitad IV** | **8** | `ceil(15/2) = 8`. Entero, testeable, y `8×5 = 40` frente a `15×5 = 75` (53 % del máximo). |
| **Mitad EV** | **128** | `ceil(255/2) = 128`. Además `128/4 = 32` **exacto**, así que `floor(ev/4)` no pierde nada (`BattleStats.php:75,80`). Con 127 se perdería 0,75 → 31. |
| **"Máximo"** | 15 IV **y** 255 EV en ese stat | No significa "la mitad del total repartido". |
| **Desempate de stats** | `hp, atk, def, spAtk, spDef, speed` | Es el orden que ya aplica `DistribuidorStatsValor.php:21,32-36`. Se mantiene para no cambiar el comportamiento existente. |

> **Consecuencia conocida del desempate:** HP rara vez está en el top-2 (en el pool medio es el 4.º stat), así que en el perfil `TopDos` HP recibe el valor "mitad". Eso hace que el salto `TopDos → Completo` sea sobre todo un salto de **HP** (+18 % de HP y +0 % de Defensa en el nivel 2). Es correcto y hay que testearlo explícitamente.

### 1.2 Tabla de perfiles

| Tier | Perfil | Top-1 | Top-2 | Resto (4 stats) | Ejemplo de uso |
|---|---|---|---|---|---|
| T0 | `Nulo` | 0 / 0 | 0 / 0 | 0 / 0 | Ruta nivel 1 |
| T1 | `TopUno` | **15 / 255** | 0 / 0 | 0 / 0 | Entrenador nivel 1; gimnasios 1-6 |
| T2 | `TopDos` | **15 / 255** | **15 / 255** | **8 / 128** | Ruta nivel 2; entrenador nivel 2; gimnasios 7-12 |
| T3 | `Completo` | **15 / 255** | **15 / 255** | **15 / 255** | Ruta nivel 3; entrenador nivel 3; gimnasios 13-18 |

### 1.3 Asignación por contenido

| Contenido | Tier 1 | Tier 2 | Tier 3 |
|---|---|---|---|
| **Ruta** (nivel de hábitat) | 1 → **T0** | 2 → **T2** | 3 → **T3** |
| **Entrenador** (nivel de hábitat) | 1 → **T1** | 2 → **T2** | 3 → **T3** |
| **Gimnasio** (índice 1-18) | 1-6 → **T1** | 7-12 → **T2** | 13-18 → **T3** |
| **Gimnasio, etapa 4 (líder)** | 1-6 → **T2** | 7-12 → **T3** | 13-18 → **T3** |
| **Mazmorra** | — | — | fuera de alcance (§7) |

**Observación de diseño (H6):** con esta asignación, **entrenador nivel 2 y ruta nivel 2 son idénticos** (ambos T2), y lo mismo en nivel 3. Solo el nivel 1 diferencia (T1 vs T0). Los entrenadores siguen siendo más duros en la práctica por su equipo determinista y por `M = 2,0`, pero la spec no crea una escalera estricta. Ver decisión abierta 2.

## 2. Impacto en stats efectivos

Fórmula aplicada (`BattleStats.php:73-81`), con `K = iv×5 + floor(ev/4)`:

```
stat = floor((2×base + K) × nivel/100 + 5)
HP   = floor((2×base + K) × nivel/100 + nivel + 10)
```

Valores de `K` por perfil: `Nulo` 0 · `TopUno` 138/0 · `TopDos` 138/72 · `Completo` 138.

### 2.1 Hábitat nivel 1 (base media HP 49,9 · Atk 54,0 · Def 51,9)

| Nivel | Perfil | HP | Atk | Def | HP×Def |
|---|---|---|---|---|---|
| **25** | T0 (nuevo) | 59 | 32 | 30 | 1.770 |
| 25 | T1 | 59 | 66 | 30 | 1.770 |
| 25 | T2 | 77 | 66 | 65 | 5.005 |
| 25 | T3 | 94 | 66 | 65 | 6.110 |
| 25 | *Actual (IV aleatorio 7,5)* | *69* | *41* | *40* | *2.760* |
| **50** | T0 | 109 | 59 | 56 | 6.104 |
| 50 | T2 | 145 | 128 | 125 | 18.125 |
| 50 | T3 | 178 | 128 | 125 | 22.250 |
| **100** | T0 | 209 | 113 | 108 | 22.572 |
| 100 | T2 | 281 | 251 | 246 | 69.126 |
| 100 | T3 | 347 | 251 | 246 | 85.362 |

El nivel 1 de ruta pasa de `69/41/40` a `59/32/30`: **−15 % HP, −22 % Atk, −25 % Def**. Es el efecto buscado (nivel 1 = fácil), pero conviene saberlo: hoy el nivel 1 ya es más duro de lo que la spec pretende.

### 2.2 Nivel 25, comparativa entre niveles de hábitat

| Nivel / perfil | HP | Atk | Def | HP×Def | Ratio durabilidad |
|---|---|---|---|---|---|
| 1 / T0 | 59 | 32 | 30 | 1.770 | 1,00 |
| 2 / T2 | 90 | 80 | 78 | 7.020 | **3,97** |
| 3 / T3 | 111 | 87 | 82 | 9.102 | **5,14** |

**Hallazgo central:** la política IV/EV **triplica la brecha de dificultad** entre el nivel 1 y el 3. Hoy la brecha (IV aleatorios en ambos) es 1,78×; con la política es **5,14×**. Y el salto grande no es 2→3, es **1→2** (×3,97), porque el tier T2 maximiza exactamente los dos stats que gobiernan daño y defensa, mientras T3 solo añade HP y el resto.

### 2.3 Entrenadores y gimnasios

- **Entrenador** usa el mismo pool por nivel que la ruta (`GeneradorEquipoEntrenador.php:90-106`), así que su tabla coincide con §2.2 salvo el nivel 1 (T1 en vez de T0): `59/66/30`, durabilidad 1.770 igual que T0 pero **Atk ×2,06**.
- **Gimnasio**: el pool es el catálogo, no el hábitat. Comparación con una base ilustrativa 80 a L=50:

| Config | HP | Atk | Def | HP×Def | vs actual |
|---|---|---|---|---|---|
| Actual etapas 1-3 (IV 12/10, EV 64) | 173 | 123 | 118 | 20.414 | 1,00 |
| Actual líder (IV 14/12, EV 128/64) | 176 | 129 | 123 | 21.648 | 1,06 |
| Nuevo gimnasio 1-6 (T1) | 140 | 154 | 85 | 11.900 | **0,58** |
| Nuevo gimnasio 7-12 (T2) | 176 | 154 | 154 | 27.104 | **1,33** |
| Nuevo gimnasio 13-18 (T3) | 209 | 154 | 154 | 32.186 | **1,58** |

**T1 es un cañón de cristal**: pega un 25 % más que hoy y aguanta un 42 % menos. Los gimnasios 1-6 se vuelven más rápidos pero más castigadores. **T2 es el muro**: duplica el salto.

## 3. Impacto en tiempo de combate

Se mantiene el modelo del addendum I (3 rivales, `turnos_por_rival = 2 × D/D₀`, `t = 20 + 4×turnos`, ancla 10 combates/h en nivel 1) pero **`D` incluye ahora IV/EV**.

| Nivel / perfil | HP×Def | Turnos/rival | Turnos/combate | t (s) | Ratio tiempo | Combates/h |
|---|---|---|---|---|---|---|
| 1 / T0 | 1.770 | 2,00 | 6,0 | 44,0 | **1,00** | 10,00 |
| 1 / T1 | 1.770 | 2,00 | 6,0 | 44,0 | 1,00 | 10,00 |
| 1 / T2 | 5.005 | 5,66 | 17,0 | 87,9 | 2,00 | 5,00 |
| 1 / T3 | 6.110 | 6,90 | 20,7 | 102,8 | 2,34 | 4,28 |
| 2 / T2 | 7.020 | 7,93 | 23,8 | 115,2 | **2,62** | 3,82 |
| 2 / T3 | 8.268 | 9,34 | 28,0 | 132,1 | 3,00 | 3,33 |
| 3 / T3 | 9.102 | 10,29 | 30,9 | 143,4 | **3,26** | 3,07 |

**Sensibilidad declarada:** el `+20 s` fijo por combate es lo que comprime el ratio de turnos (1 / 3,97 / 5,14) hasta 1 / 2,62 / 3,26. Si el coste fijo real fuese 0, los ratios serían 1 / 3,97 / 5,14 y los multiplicadores de §4 tendrían que subir otro ~40 %. El test de aceptación mide **caramelos/minuto con este modelo**, no turnos.

**Riesgo de derrota:** con L=25 y un equipo de base 80 (IV 15 / EV 255), el rival medio pega `×2,5` (nivel 2) y `×2,7` (nivel 3) más que en nivel 1, mientras tarda en morir 4-5× más. El margen de combate se estrecha mucho: la política no solo alarga, también **hace perder más**. Eso justifica que el pago suba más que proporcionalmente en el nivel 3.

## 4. Recalibración de recompensas

### 4.1 Regla de calibración

> **Throughput constante:** la política IV/EV es una feature de dificultad, no un nerf de progresión. Cada clase de caramelo se recalibra para que la tasa por hora del nivel 2 siga siendo la del addendum I: **familia 50 / EV 42 / tipo 60**.

Fórmula usada (documentada para poder re-derivarla):

```
m_clase(nivel) = tasa_objetivo_clase × ratio_tiempo(nivel) / (10 combates/h × base_clase(nivel))
```

### 4.2 Tabla resultante

| Nivel | `m_familia` | `m_ev` | `m_tipo` | Familia/combate | EV/combate | Tipo/combate | Familia/h | EV/h | Tipo/h |
|---|---|---|---|---|---|---|---|---|---|
| 1 | **1,00** | **1,00** | **1,00** | 3 | 3 | 3 | 30,0 | 30,0 | 30,0 |
| 2 | **3,30** | **1,85** | **1,60** | 13 | 11 | 16 | 49,7 | 42,0 | 61,1 |
| 3 | **3,35** | **2,80** | **1,90** | 23 | 19 | 22 | 70,6 | 58,3 | 67,5 |

| Ratio por minuto | Nivel 1 | Nivel 2 | Nivel 3 | Objetivo | Cumple |
|---|---|---|---|---|---|
| Familia | 1,00× | **1,66×** | **2,35×** | ≥1,30 / ≥1,60 | ✅ |
| EV | 1,00× | **1,40×** | **1,94×** | ≥1,30 / ≥1,60 | ✅ |
| Tipo | 1,00× | **2,04×** | **2,25×** | ≥1,30 / ≥1,60 | ✅ |
| Captura | 1,00× | **0,22×** | **0,078×** | L1 ≥2× | ✅ (4,5× / 12,7×) |
| EXP | 1,00× | 1,00× | 0,97× | — | plano por diseño |

### 4.3 Antes / después (addendum I → addendum II)

| Métrica | Antes | Después | Motivo |
|---|---|---|---|
| Tiempo relativo L2 | 1,66 | **2,62** | IV/EV del rival |
| Tiempo relativo L3 | 1,98 | **3,26** | IV/EV del rival |
| `m_familia` L2 / L3 | 2,00 / 2,50 | **3,30 / 3,35** | Compensa el tiempo extra |
| `m_ev` L2 / L3 | 1,25 / 1,50 | **1,85 / 2,80** | Idem |
| `m_tipo` L2 / L3 | 1,00 / 1,00 | **1,60 / 1,90** | El tipo ya no cubre el tiempo por sí solo |
| Familia/h L2 / L3 | 50,9 / 86,7 | **49,7 / 70,6** | L2 preservado; L3 −19 % |
| EV/h L2 / L3 | 42,1 / 50,4 | **42,0 / 58,3** | L2 preservado; L3 +16 % |
| Tipo/h L2 / L3 | 60,2 / 60,5 | **61,1 / 67,5** | L2 preservado; L3 +12 % |
| Capturas/h L1/L2/L3 | 7,5 / 2,6 / 1,0 | **7,5 / 1,68 / 0,59** | Menos combates/hora |

**Por qué `m_familia` baja de L2 a L3 (3,30 → 3,35 es casi plano, pero no crece como el nivel):** el multiplicador compensa `tiempo / recompensa_base`, y la familia base crece más de 2 a 3 (×1,75) que de 1 a 2 (×1,33). La **recompensa por minuto sí es estrictamente creciente** (50 < 71), que es lo que percibe el jugador; el coeficiente interno no tiene por qué serlo.

**Decisión explícita sobre `M_exp`:** **no se añade multiplicador de EXP.** El EXP/hora queda plano (~4.900 / 4.900 / 4.730) porque el crecimiento de `base_experience` iguala el del tiempo. Es deliberado: el premium de dificultad se paga en caramelos, y así no se infla la curva de nivel ni la Senday. Ver decisión abierta 4.

## 5. Gimnasios

### 5.1 Índice 1-18 sin hardcodear slugs

`CatalogoGimnasios` guarda los gyms en un `array<string, Gimnasio>` cuya única referencia de orden es la inserción (`CatalogoGimnasios.php:22-24`). Para no depender de eso:

```php
// src/Gimnasios/Domain/CatalogoGimnasios.php
public function indiceDe(string $slug): int;   // 1..18
public function ordenados(): GimnasioCollection; // orden: nivelMinimo ASC, slug ASC
```

Implementación: ordenar `todos()` por `nivelMinimo` y desempatar por `slug` (`Gimnasio` ya expone `nivelMinimo`, `Gimnasio.php:22`). El orden resultante coincide con el actual (`bug 10 … dragon 100`) pero deja de depender de la inserción.

> Alternativa descartada: añadir `orden` al constructor de `Gimnasio` — obliga a tocar 18 literales y crea una segunda fuente de verdad con `nivelMinimo`.

### 5.2 Etapa 4 (líder)

**Regla propuesta: `tier_lider = min(3, tier_gimnasio + 1)`.**

| Gimnasio | Etapas 1-3 | Líder |
|---|---|---|
| 1-6 | T1 | **T2** |
| 7-12 | T2 | **T3** |
| 13-18 | T3 | **T3** |

Justificación:
- El líder es el cierre del gimnasio y debe notarse. Con `T + 1` el salto es el mismo que entre tramos (×2,28 de durabilidad), que ya está calibrado en recompensas.
- No se añade un cuarto tier: la spec solo define tres, y un "T4" rompería la tabla de multiplicadores.
- En 13-18 el líder ya es T3 y el climax lo aporta la composición del catálogo (3 Pokémon y mejores movimientos), sin necesidad de stats extra.
- Alternativa descartada: "mismo tier + bonus plano de stats" — introduce un parámetro nuevo y no reutiliza la escalera.

## 6. Shiny

**Decisión: el shiny pelea con los IV/EV de su tier; conserva IV máximos solo como reclutado.**

Hoy `GeneradorEquipoRuta.php:95` hace `ivs: $shiny ? GeneradorIvs::maximos() : GeneradorIvs::aleatorios()`. Con la nueva política, un shiny en ruta nivel 1 pelearía con 15/255 mientras sus compañeros pelean con 0/0: sería un mini-jefe sorpresa de **durabilidad ×3,45** sin aviso, y rompería la promesa "nivel 1 = fácil".

- **En batalla:** `ivs` y `evs` = perfil del tier. Consistencia de dificultad.
- **Al capturar:** se mantiene `GeneradorIvs::maximos()` en `CrearReclutado` (`OtorgarRecompensasRuta.php:137-149`). El premio del shiny es el reclutado perfecto, no un combate más duro.
- **EV del reclutado:** no se tocan; siguen viniendo de la granja (`ResolverBonosFamilia::evsIniciales`). Dar 255 EV al capturar convertiría el shiny hunting en un atajo competitivo.

## 7. Mazmorras

**Decisión: fuera de alcance en esta fase.**

- El jefe ya tiene su propia escalera de dificultad: stats ×10, HP ×7 y bonus inicial +30 % (`ReglasMazmorra.php:13-19`), y sus recompensas están calibradas aparte (M_candy 70 / M_exp 10 en el addendum del documento principal).
- Hoy el jefe se construye con `ivs: StatsValue::ceros()` y EV 0 (`GeneradorJefeMazmorra.php:46`), y las IV/EV se aplican **después** del escalado ×10/×7, así que un IV 15 solo añadiría ~8-10 % de durabilidad: no es la palanca de dificultad que necesita una mazmorra.
- Si más adelante se aplica, la regla sería **T3 solo en el último piso de cada mazmorra** y T2 en los intermedios, nunca T1 (un jefe glass-cannon con ×10 de ataque es un one-shot garantizado).

## 8. Contratos y archivos

### 8.1 VO de perfil

```php
// src/Shared/Domain/PerfilIvsEvs.php  (NUEVO)
enum PerfilIvsEvs: string
{
    case Nulo = 'nulo';           // 0 máximos, resto 0
    case TopUno = 'top_uno';      // 1 máximo, resto 0
    case TopDos = 'top_dos';      // 2 máximos, resto mitad
    case Completo = 'completo';   // 6 máximos

    public function cuantosMaximos(): int;  // 0 | 1 | 2 | 6
    public function ivMaximo(): int;        // 15
    public function evMaximo(): int;        // 255
    public function ivResto(): int;         // 0 | 0 | 8 | 15
    public function evResto(): int;         // 0 | 0 | 128 | 255
}
```

### 8.2 Servicio de política

```php
// src/Shared/Domain/PoliticaIvsEvs.php  (NUEVO)
final class PoliticaIvsEvs
{
    public const IV_MAXIMO = 15;
    public const EV_MAXIMO = 255;
    public const IV_MITAD = 8;
    public const EV_MITAD = 128;

    public static function perfilRuta(int $nivel): PerfilIvsEvs;        // 1→Nulo, 2→TopDos, 3→Completo
    public static function perfilEntrenador(int $nivel): PerfilIvsEvs;  // 1→TopUno, 2→TopDos, 3→Completo
    public static function perfilGimnasio(int $indice, bool $esLider = false): PerfilIvsEvs;

    public static function ivs(PerfilIvsEvs $perfil, DatosStats $base): StatsValue;
    public static function evs(PerfilIvsEvs $perfil, DatosStats $base): StatsValue;
}
```

### 8.3 Generalización del distribuidor (sin romper nada)

```php
// src/Shared/Domain/DistribuidorStatsValor.php  (MODIFICAR)
public static function distribuir(int $valorPrincipal, int $valorResto, DatosStats $base): StatsValue;
// ^ se conserva: delega en distribuirPorRanking(2, ...) — mismo comportamiento exacto

public static function distribuirPorRanking(
    int $cuantosPrincipales,   // 0..6, clamp
    int $valorPrincipal,
    int $valorResto,
    DatosStats $base,
): StatsValue;
```

`IvsRangoEntrenador::distribuir` y `EvsRangoEntrenador::distribuir` siguen funcionando sin cambios (llaman a `distribuir`). Se marcan `@deprecated` y se dejan de usar en los flujos nuevos.

### 8.4 Puntos de integración

| Flujo | Antes | Después | Archivo |
|---|---|---|---|
| Ruta | `ivs: $shiny ? maximos() : aleatorios()` | `PoliticaIvsEvs::ivs(perfilRuta($nivel), $stats)` + `evs(...)` | `GeneradorEquipoRuta.php:88-98` |
| Entrenador | `IvsRangoEntrenador::distribuir($ivPrincipal, $ivResto, ...)` y sin EV | perfil por nivel; IV y EV | `GeneradorEquipoEntrenador.php:76-80`; `IniciarCombateEntrenador.php:56-71` |
| Gimnasio | valores por `$etapa === 4` | `perfilGimnasio($catalogo->indiceDe($slug), $etapa === 4)` | `IniciarCombateGimnasio.php:64-76`; `GeneradorPokemonGimnasio.php:50-51` |
| Mazmorra | `ivs: ceros()` | sin cambio | `GeneradorJefeMazmorra.php:46` |

**Importante:** `GeneradorEquipoEntrenador` necesita un parámetro nuevo (`int $evPrincipal`, `int $evResto` o directamente `PerfilIvsEvs $perfil`). Se recomienda pasar **el perfil**, no cuatro enteros, para que la firma no vuelva a crecer.

### 8.5 Dónde viven

- `PerfilIvsEvs` y `PoliticaIvsEvs` → `src/Shared/Domain` (transversal a Ruta, Entrenadores y Gimnasios; `src/Shared` no depende de módulos, regla `.ai/rules/ddd-modules.md`).
- `DificultadHabitat` (addendum I) **permanece en `Src\Exploraciones\Domain`** porque solo la usan recompensas, y ahora toma sus multiplicadores de una tabla por nivel, no del perfil.

## 9. Fases

**Encaje: Fase 2A, inmediatamente antes del re-precio (Fase 2B).**

Motivo: los objetivos de precios (§2 del documento principal) están expresados en horas y dependen de la tasa de caramelo/hora. Si se re-precian antes de fijar la dificultad, los objetivos se rompen al introducir IV/EV. El orden correcto es: **dificultad → tasa → precios**.

| Fase | Entregable | Depende de |
|---|---|---|
| 1 | Estabilización contable (ya definida) | — |
| **2A** | `PerfilIvsEvs`, `PoliticaIvsEvs`, `distribuirPorRanking`, integración en ruta/entrenador/gimnasio, nueva tabla de `DificultadHabitat` | Fase 1 |
| **2B** | Re-precio de Sendas y granja (sin cambios respecto al documento principal) | 2A |
| 3 | Límites diarios y anti-grind | 1 |
| 4 | Preview/UI (debe mostrar el tier del rival) | 2A, 2B |
| 5 | Sumidero de familia (opcional) | 3 |

**Añadido a la fase 4:** la UI debe indicar el perfil del rival ("Rivales sin entrenar / entrenados / competitivos") antes de entrar al combate. Sin eso, el jugador no puede decidir si le compensa el tier, y el incentivo queda oculto.

## 10. Criterios de aceptación y tests deterministas

### 10.1 Vectores exactos de IV/EV

Pokémon de prueba: `hp 45, atk 60, def 40, spAtk 50, spDef 45, speed 70`.
Orden por base: speed(70), atk(60), spAtk(50), hp(45), spDef(45), def(40) — el empate hp/spDef lo gana hp.

| Perfil | hp | atk | def | spAtk | spDef | speed |
|---|---|---|---|---|---|---|
| `Nulo` | 0/0 | 0/0 | 0/0 | 0/0 | 0/0 | 0/0 |
| `TopUno` | 0/0 | 0/0 | 0/0 | 0/0 | 0/0 | **15/255** |
| `TopDos` | 8/128 | **15/255** | 8/128 | **15/255** | 8/128 | 8/128… |

> **Corrección del vector:** el top-2 es `speed` y `atk`. El resto (hp, def, spAtk, spDef) va a 8/128. La tabla correcta es: `hp 8/128 · atk 15/255 · def 8/128 · spAtk 8/128 · spDef 8/128 · speed 15/255`.

| `Completo` | 15/255 en los seis |

### 10.2 Stats efectivos (asserts exactos)

Con los medios del hábitat nivel 1 y L=25:

| Perfil | HP | Atk | Def |
|---|---|---|---|
| `Nulo` | 59 | 32 | 30 |
| `TopUno` | 59 | 66 | 30 |
| `TopDos` | 77 | 66 | 65 |
| `Completo` | 94 | 66 | 65 |

Y a L=50: `Nulo` 109/59/56 · `TopDos` 145/128/125 · `Completo` 178/128/125.
A L=100: `Nulo` 209/113/108 · `TopDos` 281/251/246 · `Completo` 347/251/246.

### 10.3 Ratios

1. Tiempo relativo: **1 / 2,62 / 3,26** (±5 %).
2. Familia/min: **1 / 1,66 / 2,35**; EV/min: **1 / 1,40 / 1,94**; tipo/min: **1 / 2,04 / 2,25**; todos ≥1,30 y ≥1,60.
3. Captura/min: nivel 1 ≥ 4× nivel 2 y ≥ 10× nivel 3.
4. Tasa del nivel 2: familia 49,7 ±3 · EV 42,0 ±3 · tipo 61,1 ±3 (throughput constante).
5. EXP/hora plano: 4.900 / 4.900 / 4.730 (±5 %).

### 10.4 No regresión

- `DistribuidorStatsValor::distribuir(15, 8, $base)` produce **exactamente** el mismo `StatsValue` que antes del cambio (test de igualdad contra el algoritmo top-2 actual).
- Entrenador de gimnasio etapa 1-3 y líder: los tests existentes deben actualizarse a los nuevos perfiles, pero el flujo (selección de etapa, orden de posiciones) no cambia.
- Mazmorra: `ivs`/`evs` del jefe siguen siendo cero (test de invariante).
- Shiny: el rival usa el perfil del tier, pero el reclutado creado tiene IV 15 en los seis stats.

### 10.5 Pruebas por capa

- **Unit dominio:** `PerfilIvsEvs`, `PoliticaIvsEvs` (tablas y clamp), `DistribuidorStatsValor` (N=0..6, empates), `BattleStats` con vectores fijos.
- **Unit simulación:** `SimulacionTierTest` recorre los tres niveles con el pool medio, aplica el modelo de tiempo y afirma los ratios de §10.3 con tolerancia ±10 %. Si las medias del pool se desvían >15 %, el test falla y obliga a re-derivar.
- **Feature:** ruta nivel 1/2/3 → IV/EV del rival en `DatosPokemonBatalla`; gimnasio 1 vs 7 vs 13 → T1/T2/T3; líder → +1 tier; shiny → perfil del tier en batalla y IV 15 al capturar.
- **Arquitectura:** `src/Shared/Domain/PerfilIvsEvs.php` sin imports de `App` ni `Illuminate`.

## 11. Riesgos y decisiones abiertas

### Riesgos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| T1 (top-1 máx, resto 0) convierte los gimnasios 1-6 en cañones de cristal: pegan +25 % y aguantan −42 % | Medio | Es el efecto literal de la spec; si se quiere "suave", usar top-1 al 50 % (IV 8/EV 128) en vez de 255. Decisión abierta 1. |
| La brecha de dificultad 1→3 pasa de 1,78× a 5,14× | Alto | Es intencional (tres roles), pero exige avisar en la UI del tier antes de entrar. |
| Los tests actuales de entrenador/gimnasio asumen IV 8/6, 10/8, 12/10 | Alto | Actualización planificada en fase 2A; `IvsRangoEntrenador`/`EvsRangoEntrenador` quedan `@deprecated` pero no se borran. |
| El modelo de tiempo (4 s/turno, 20 s fijos) condiciona toda la calibración | Alto | Declarado; la aceptación se mide con él y la tabla se re-deriva si la telemetría lo contradice. |
| Sube el riesgo de derrota en niveles 2-3 | Medio | El castigo por derrota ya se suavizó a `floor`; vigilar la tasa de derrota por tier. |

### Decisiones abiertas (requieren aprobación)

1. **`m_familia` 3,30 / 3,35 y `m_ev` 1,85 / 2,80** (throughput constante). ¿Se aprueba, o se prefiere el escenario "ritmo más lento" (multiplicadores más bajos y objetivos +20-30 %)?
2. **Solape entrenador/ruta en niveles 2 y 3** (ambos T2/T3). ¿Se acepta (los entrenadores ya son más duros por equipo y M=2) o se offseta el entrenador +1 tier (T1/T3/T3)?
3. **Líder = tier + 1** (T2 en gimnasios 1-6, T3 en 7-12, T3 en 13-18). ¿Se aprueba?
4. **Sin `m_exp`**: el EXP/hora queda plano entre niveles. ¿Se acepta, o se añade `m_exp = 1,15/1,25` como premium de riesgo?
5. **`mitad` = 8 IV / 128 EV** (redondeo hacia arriba). ¿Se aprueba, o se prefiere 7/127?
6. **T1 con 255 EV** en el top-1: ¿se aprueba el cañón de cristal, o se baja a 128 EV para suavizar los gimnasios 1-6?
7. **Mazmorra fuera de alcance**: ¿se confirma, o se aplica T3 al último piso?


<subagent sessionID="ses_e63bf5074ffeCsewg4aMXedhYE" state="completed">
ADDENDUM III — Sendas maestras y movesets de rivales

> Extiende `architecture.md` (TASK-004) y sus Addenda I/II. Read-only: no se ha modificado ningún archivo, no se han ejecutado migraciones/seeders y no se ha leído `.env`. Las cifras de nodos y costes se derivan de `type_path_nodes`, `SendaNiveles`, `SendaCostes` y `SendaProgreso`; las de Showdown, de `showdown/data/random-battles/gen9/*` y `showdown/data/moves.ts`.

## 0. Qué cambia respecto al diseño anterior

| # | Sección afectada | Cambio |
|---|---|---|
| C1 | §4.1 Sendas | Los niveles **visibles** de ataque pasan de 5 a **3**. Los niveles 4-5 dejan de desbloquear ataques y pasan a ser **pasivos**. |
| C2 | §4.1 Costes | El coste deja de ser plano por nivel (`2/5/10/25/60`) y pasa a derivarse de **nivel visible + potencia + tramo de utilidad**. El total se conserva en **9.584**. |
| C3 | §4.1 Umbrales | Se fijan `0 / 9.000 / 18.000 / 36.000 / 72.000` y `EXP_MAXIMA = 72.000`; 720 caramelos para N5. |
| C4 | §4.2 Recompensas | Se añaden pasivos de Senda: captura, daño infligido, daño recibido y caramelos de tipo, **+10 % en N4 y +20 % en N5**, aditivos. |
| C5 | §5.1 Captura | La captura incorpora el bono de Senda **después** del nivel de hábitat y del refugio. El tope efectivo sube a **30 %** con Senda 5 (única contradicción explícita con el cap-25). |
| C6 | §5.2 Caramelos de tipo | Divisor `500 → 250`; el bono de Senda se aplica **después** de `M × m_tipo` y antes del único `floor`. |
| C7 | §6 Contratos | Nuevos VO `NivelSenda`, `BeneficiosSenda`, `MapaSendaJugador`, `PoliticaSenda`, `PoliticaCosteSenda`; el mapa del jugador viaja en `Combatiente`. |
| C8 | §6/§7 Rivales | Los rivales usan **4 movimientos** elegidos por un selector determinista: pool de Senda 1-3 + learnset + preferencia Showdown. |
| C9 | §7 Showdown | Se elige **artefacto JSON build-time**; no tabla `pokemon_movesets`, no Node en runtime. |
| C10 | §8 Fases | Se insertan **2B (estructura de Senda)**, **2C (selector/movesets)** y **2D (recalibración económica)**. |
| C11 | §9 Economía | Los objetivos del Addendum II se declaran **sin pasivos** (base); se añade una sensibilidad “con Senda 5”. |
| C12 | §4.6/§6 UI | Página de Senda con ataques 1-3 y maestrías 4-5; selector con nivel/coste/beneficio; preview con tier IV/EV y aviso de moveset rival. |
| C13 | Sin cambios | Jugador conserva **6** movimientos; sets manuales de gimnasio, shiny, mazmorra y perfiles IV/EV del Addendum II no se alteran. |

**Valores del plan v2 que se preservan:** precios totales de Senda (9.584), umbrales, `EXP_MAXIMA`, multiplicadores `m_familia/m_ev/m_tipo`, captura base 25/14,7/6,4, granja, evolución y límites. **No se cambia** `m_familia`, `m_ev` ni `m_tipo`.

---

## 1. Modelo de niveles de Senda

### 1.1 Compresión de los 639 nodos a niveles 1-3

La compresión es **lógica, no de datos**. `type_path_nodes.required_level` sigue almacenando el **tramo 1-5** (sirve como banda de precio/rareza), pero todo lo que el jugador ve y desbloquea usa:

```text
nivelVisible(nodo) = min(3, max(1, required_level))
tramo(nodo)        = max(1, min(5, required_level))
```

- `required_level` 4 y 5 → **nivel visible 3**.
- No se requiere migración ni reescritura de los 639 nodos.
- Si se resiembra desde cero, `SendaNiveles` debe seguir devolviendo **1-5** para conservar el tramo; la compresión se aplica en lectura, no en el seeder.

**Reparto visible resultante:**

| Nivel visible | Físico | Especial | Estado | Total |
|---|---:|---:|---:|---:|
| 1 | 63 | 34 | 90 | **187** |
| 2 | 46 | 29 | 39 | **114** |
| 3 | 163 | 136 | 39 | **338** |
| **Total** | **272** | **199** | **168** | **639** |

El nivel 3 absorbe los antiguos niveles 3, 4 y 5: `160 + 104 + 74 = 338`. Por categoría: físico `77+52+34 = 163`; especial `52+46+38 = 136`; estado `31+6+2 = 39`.

### 1.2 Qué pasa con los 8 movimientos de estado de niveles 4-5

No se borran. Pasan al **nivel visible 3** como “maestrías de utilidad”, conservando su **tramo de precio** para no alterar el total:

| Movimiento | Tramo | Coste |
|---|---:|---:|
| `take-heart` (Bálsamo Osado) | 4 | 25 |
| `shore-up` (Recogearena) | 4 | 25 |
| `healing-wish` (Deseo Cura) | 4 | 25 |
| `lunar-blessing` (Plegaria Lunar) | 4 | 25 |
| `memento` (Legado) | 4 | 25 |
| `clangorous-soul` (Estruendo Escama) | 4 | 25 |
| `lunar-dance` (Danza Lunar) | 5 | 60 |
| `jungle-healing` (Cura Selvática) | 5 | 60 |

Motivo: son utilidad única (curación, sacrificio, boost) y eliminarlas reduciría contenido sin necesidad. Se marcan en la UI como **“Maestría de utilidad”** y no como ataques de nivel 3 ordinarios.

### 1.3 Modelo de coste: derivado, sin migración

**Decisión: coste derivado en una política pura (`PoliticaCosteSenda`), no columna `coste`.**

- No hay migración.
- No hay segunda fuente de verdad.
- El total se reproduce exactamente.
- `app/Support/SendaCostes` queda como adaptador de compatibilidad; la regla vive en `src/Shared/Domain/Sendas/PoliticaCosteSenda.php`.

**Tabla de coste:**

| Nivel visible | Categoría / potencia | Tramo | Coste |
|---:|---|---:|---:|
| 1 | cualquiera | 1 | **2** |
| 2 | cualquiera | 2 | **5** |
| 3 | estado con tramo ≤3 | 1-3 | **10** |
| 3 | estado con tramo 4 | 4 | **25** |
| 3 | estado con tramo 5 | 5 | **60** |
| 3 | daño ≤80 | 1-3 | **10** |
| 3 | daño 81-100 | 4 | **25** |
| 3 | daño >100 | 5 | **60** |
| 3 | daño con potencia 0 y categoría ≠ estado (OHKO/fijo) | 1-5 | **10** |

**Verificación del total:**

| Bloque | Nodos | Coste | Subtotal |
|---|---:|---:|---:|
| N1 | 187 | 2 | 374 |
| N2 | 114 | 5 | 570 |
| N3 daño 61-80 | 126 | 10 | 1.260 |
| N3 daño 81-100 | 98 | 25 | 2.450 |
| N3 daño >100 | 72 | 60 | 4.320 |
| N3 daño ≤60 / potencia 0 no estado | 3 | 10 | 30 |
| N3 estado tramo ≤3 | 31 | 10 | 310 |
| N3 estado tramo 4 | 6 | 25 | 150 |
| N3 estado tramo 5 | 2 | 60 | 120 |
| **Total** | **639** | | **9.584** |

Rebalanceo interno respecto al plan v2: `flamethrower` pasa de 10 a 25 (+15) y `fissure` de 25 a 10 (−15); el resto de nodos conserva su precio. El total no cambia.

### 1.4 Umbrales y compra de niveles 4-5

| Nivel | Umbral EXP | Caramelos acumulados | Caramelos marginales | Horas a 60 tipo/h | Horas a 72,7 tipo/h | Beneficio principal |
|---:|---:|---:|---:|---:|---:|---|
| 1 | 0 | 0 | — | — | — | Ataques ≤40 / utilidad básica |
| 2 | 9.000 | 90 | 90 | 1,5 | 1,24 | Ataques 41-60 |
| 3 | 18.000 | 180 | 90 | 3,0 | 2,48 | Ataques >60 (arsenal completo) |
| 4 | 36.000 | 360 | 180 | 6,0 | 4,95 | **+10 %** pasivos |
| 5 | 72.000 | 720 | 360 | 12,0 | **9,90** | **+20 %** pasivos |

- `EXP_POR_CARAMELO = 100` (`SendaProgreso.php:32`): 72.000 EXP = **720 caramelos**.
- El hito de **9,9 h** del plan v2 corresponde a **72,7 tipo/h efectivos** (mezcla de ruta, entrenadores y gimnasios). A 60 tipo/h puros son 12,0 h. Se mantiene 9,9 h como hito declarado y se expresan los deltas en %.
- Los niveles 4-5 **no se compran como nodo**: se alcanzan automáticamente al superar el umbral en `SendasController::experiencia()` (`SendasController.php:131-194`), que convierte caramelos en EXP y actualiza `player_type_paths.level` (`:168-170`).
- No hay nueva moneda ni nueva acción de UI: se usan los caramelos de tipo existentes.

### 1.5 Tabla de beneficios por nivel

| Nivel | Captura | Daño infligido | Daño recibido | Caramelos de tipo |
|---:|---:|---:|---:|---:|
| 1 | 0 % | 0 % | 0 % | 0 % |
| 2 | 0 % | 0 % | 0 % | 0 % |
| 3 | 0 % | 0 % | 0 % | 0 % |
| 4 | **+10 %** | **+10 %** | **−10 %** | **+10 %** |
| 5 | **+20 %** | **+20 %** | **−20 %** | **+20 %** |

**Decisiones concretas:**

1. **Niveles 1-3 no tienen pasivos numéricos.** Su beneficio es el **desbloqueo de ataques** (y utilidad) de esa Senda. Añadir pasivos pequeños en 1-3 rompería la economía del Addendum II y diluiría el salto a 4-5. Si el desarrollador quiere “cada nivel aporta algo”, la alternativa es +2,5 %/+5 % en N2/N3 (decisión abierta D8).
2. **+10 %/+20 % absolutos, no compuestos.** N4 = `1,10`; N5 = `1,20`; nunca `1,10 × 1,10 = 1,21`. Es más simple, testeable y evita compounding con STAB, objetos, clima y habilidades.
3. **Captura: solo Pokémon que poseen el tipo.** Se aplica al Pokémon derrotado si su tipo primario o secundario coincide. En un pool mixto, el bono efectivo es `1 + bono × fracción_de_especies_del_tipo`.
4. **Daño infligido: cualquier movimiento del tipo, de cualquier Pokémon del jugador.** No exige que el Pokémon sea de ese tipo. Un Pokémon de Agua con un movimiento de Fuego recibe el bono de la Senda de Fuego.
5. **Daño recibido: cualquier movimiento entrante del tipo, contra cualquier Pokémon del jugador.** No exige que el defensor sea de ese tipo.
6. **Caramelos: solo el caramelo de ese tipo.** En un derrotado de doble tipo, el caramelo de Fuego usa la Senda de Fuego y el de Volador la Senda de Volador; no se suman ni se usa el máximo.
7. **El rival nunca recibe estos beneficios.** Su `MapaSendaJugador` es vacío. Los rivales pueden usar movimientos de la Senda, pero no heredan captura, daño, defensa ni caramelos.
8. **Mazmorra: fuera de alcance en esta fase.** El jefe ya tiene ×10 stats, ×7 HP y bonus inicial (`ReglasMazmorra.php:13-19`); un −20 % de daño recibido podría trivializarlo. Se reevalúa en una fase posterior (decisión D4).

### 1.6 Tipo doble

| Caso | Regla | Ejemplo |
|---|---|---|
| **Captura** | `max(nivelSenda[tipo1], nivelSenda[tipo2])` | Fuego 5 / Volador 0 → +20 %; Fuego 5 / Volador 5 → +20 %, no +40 %. |
| **Caramelos de tipo** | Por tipo, sin máximo ni suma | Fuego 5 / Volador 0 → caramelo Fuego +20 %, caramelo Volador +0 %. |
| **Daño / defensa** | Por tipo del movimiento | Movimiento Fuego +20 %; movimiento Volador +0 %. |

Justificación: la captura es **una sola tirada** por Pokémon; sumar o aplicar dos veces convertiría a los duales en objetivos estrictamente mejores. Los caramelos ya se reparten 50/50 (`CalculadorRecompensas.php:289-303`), así que aplicar el bono por tipo es equivalente a una media ponderada, no a un doble conteo.

### 1.7 Orden con los multiplicadores de dificultad

| Flujo | Fórmula | Redondeo |
|---|---|---|
| Captura ruta | `p = min(0,30, pBase(nivelHabitat) × 1,0 × (1 + bonoCaptura))` | sin floor |
| Captura exploración | `p = min(0,30, pBase(nivelHabitat) × factorRefugio × (1 + bonoCaptura))` | sin floor |
| Caramelos de tipo | `floor(expTipo / 250 × M × m_tipo(nivelHabitat) × (1 + bonoCaramelo))` | **un solo floor al final** |
| Daño | `floor(base × STAB × efectividad × clima × … × (1 + bonoDano) × (1 − bonoDefensa))` | un floor al final de la cadena |
| Familia / EV | Sin cambio; el bono de Senda **no** aplica | floor actual |

- `M` es el multiplicador de actividad (`1,0` ruta, `0,5` exploración, `2,0` entrenador, `10,0` gimnasio, `70,0` mazmorra).
- `m_tipo` es el multiplicador por nivel de hábitat del Addendum II (`1,00 / 1,60 / 1,90`).
- El bono de Senda se aplica **después** de la dificultad, para que la dificultad no amplifique el bono y para que un solo `floor` no destruya la señal.
- **Objetivos económicos:** se declaran **sin pasivos** (base). La columna “con Senda 5” es una sensibilidad, no el objetivo. Si se calibrara con Senda 5, el juego sería demasiado lento para quien no la tiene.

---

## 2. Contratos y puntos de integración

### 2.1 Value objects y servicios nuevos

```php
// src/Shared/Domain/Sendas/NivelSenda.php
enum NivelSenda: int
{
    case N1 = 1;
    case N2 = 2;
    case N3 = 3;
    case N4 = 4;
    case N5 = 5;

    public static function desdeInt(int $nivel): self;   // clamp 1..5
    public function visible(): int;                       // min(3, nivel)
    public function tienePasivos(): bool;                 // >= 4
}

// src/Shared/Domain/Sendas/BeneficiosSenda.php
final readonly class BeneficiosSenda
{
    public function __construct(
        public float $captura,
        public float $danoInfligido,
        public float $danoRecibido,   // reducción: 0.10 | 0.20
        public float $carameloTipo,
    ) {}

    public static function sinBonus(): self;
    public static function para(NivelSenda $nivel): self; // 1-3 → 0; 4 → 0.10; 5 → 0.20
}

// src/Shared/Domain/Sendas/MapaSendaJugador.php
final readonly class MapaSendaJugador
{
    public static function vacio(): self;
    /** @param array<int, int> $niveles tipo_id => nivel */
    public static function desdeArray(array $niveles): self;

    public function nivel(TipoPokemon $tipo): NivelSenda;
    public function beneficios(TipoPokemon $tipo): BeneficiosSenda;
    public function bonoCaptura(TiposCollection $tipos): float;   // max de los tipos
    public function bonoDano(TipoPokemon $tipo): float;
    public function bonoDefensa(TipoPokemon $tipo): float;
    public function bonoCaramelo(TipoPokemon $tipo): float;
    /** @return array<int, int> */
    public function toArray(): array;
}

// src/Shared/Domain/Sendas/PoliticaSenda.php
final class PoliticaSenda
{
    public static function factorDanoInfligido(MapaSendaJugador $mapa, TipoPokemon $tipo): float; // 1.0 | 1.1 | 1.2
    public static function factorDanoRecibido(MapaSendaJugador $mapa, TipoPokemon $tipo): float;  // 1.0 | 0.9 | 0.8
    public static function factorCaptura(MapaSendaJugador $mapa, TiposCollection $tipos): float;  // 1.0 | 1.1 | 1.2
    public static function factorCaramelo(MapaSendaJugador $mapa, TipoPokemon $tipo): float;     // 1.0 | 1.1 | 1.2

    /** Expone el bruto sin floor para tests de orden de multiplicadores. */
    public static function caramelosTipoBrutos(
        float $expTipo,
        float $multiplicadorActividad,
        float $multiplicadorHabitat,
        float $factorSenda,
    ): float;
}

// src/Shared/Domain/Sendas/PoliticaCosteSenda.php
final class PoliticaCosteSenda
{
    public static function coste(
        int $nivelVisible,
        int $tramo,
        bool $esEstado,
        int $potencia,
    ): int;
}

// src/Shared/Domain/Sendas/CurvaSenda.php
final class CurvaSenda
{
    public const EXP_MAXIMA = 72_000;
    public const EXP_POR_CARAMELO = 100;
    public const NIVEL_MAXIMO = 5;
    public static function umbral(int $nivel): int;  // 0/9.000/18.000/36.000/72.000
    public static function nivel(int $exp): int;
}
```

`app/Support/SendaProgreso`, `SendaCostes` y `SendaNiveles` quedan como **adaptadores** de compatibilidad que delegan en `CurvaSenda` y `PoliticaCosteSenda`. Así el dominio no depende de `App\Support`.

### 2.2 Cómo inyectar el mapa del jugador en la batalla

**Decisión: mapa completo de los 18 tipos, adjunto a cada `Combatiente` del jugador.**

- El mapa es pequeño (≤18 enteros) y se serializa como `array<int,int>`.
- Se adjunta a los combatientes del jugador, no a los rivales. Los rivales reciben `MapaSendaJugador::vacio()`.
- No se añade a `AccionBatalla`; los manejadores leen `attacker` y `defender`, que ya están en la acción.

Cadena:

```text
IniciarCombate* ($userId)
  → CargarMapaSendaJugador::para($userId): MapaSendaJugador
  → ConstruirEquipoJugador::desdeEquipo(..., ?MapaSendaJugador $senda)
  → DatosPokemonBatalla(..., senda: $senda)
  → EquipoBatalla::fromData()
  → Combatiente::setSenda($member->senda ?? MapaSendaJugador::vacio())
  → sesión (serialize)
```

Contratos:

```php
// src/Battle/Domain/DatosPokemonBatalla.php  (MODIFICAR, parámetro al final)
public function __construct(
    // ... campos actuales ...
    public readonly ?MapaSendaJugador $senda = null,
);

// src/Battle/Domain/EquipoBatalla.php  (MODIFICAR)
$combatant->setSenda($member->senda ?? MapaSendaJugador::vacio());

// src/Battle/Domain/Combatiente.php  (MODIFICAR)
private MapaSendaJugador $senda;
public function setSenda(MapaSendaJugador $senda): void;
public function senda(): MapaSendaJugador;
```

Serialización compatible:

```php
// __serialize()
'senda' => $this->senda->toArray(),

// __unserialize()
$this->senda = MapaSendaJugador::desdeArray(
    isset($data['senda']) && is_array($data['senda']) ? $data['senda'] : []
);
```

Los payloads antiguos sin `senda` se hidratan con mapa vacío. No se rompe ninguna batalla en curso.

**Por qué no un mapa global en `AgregadoBatalla`:** la cadena de daño solo recibe `AccionBatalla` (`CadenaDanio.php:43-50`); añadir el mapa al agregado obligaría a pasar contexto extra por todos los manejadores. Adjuntarlo al combatiente es local, serializable y testeable.

### 2.3 Puntos exactos de aplicación

| Beneficio | Punto | Archivo:línea | Regla |
|---|---|---|---|
| Construcción del equipo | `desdeEquipo()` | `ConstruirEquipoJugador.php:49-100` | Recibe `MapaSendaJugador` y lo pasa a `DatosPokemonBatalla`. |
| Datos de batalla | constructor | `DatosPokemonBatalla.php:31-77` | Nuevo campo opcional `senda`. |
| Equipo | `fromData()` | `EquipoBatalla.php:156-234` | `setSenda()` en cada combatiente. |
| Combate | serialización | `Combatiente.php:166-221`, `:280` | `senda` serializada; default vacío. |
| Daño ofensivo | nuevo `ManejadorSendaOfensiva` | `CadenaDanio.php:15-35` | Después de `ManejadorBonificadorDanio`; usa `tipoEfectivoMovimiento()`. |
| Daño defensivo | nuevo `ManejadorSendaDefensiva` | `CadenaDanio.php:15-35` | Después del ofensivo, antes de `ManejadorAguante`; usa `defender->senda()`. |
| Captura ruta | `rollCaptura()` | `OtorgarRecompensasRuta.php:166-176` | `ProbabilidadCaptura::intentarConSenda(...)`. |
| Captura exploración | `rollAleatorio()` | `FinalizarExploracionHandler.php:470-494` | Compone refugio una sola vez y aplica Senda. |
| Caramelos de tipo | `calcularCaramelosTipo()` | `CalculadorRecompensas.php:313-335` | Divisor 250 + `factorSenda` + un floor. |
| Resultado ruta | `procesarRuta()` | `PresentadorResultadoBatalla.php:217-244` | Pasa `nivelHabitat` y `MapaSendaJugador`. |
| Selector ruta | `generar()` | `GeneradorEquipoRuta.php:88-98` | Sustituye `desdePokemon()` sin selección por selector de 4. |
| Selector entrenador | `generar()` | `GeneradorEquipoEntrenador.php:70-80` | Idem. |
| Selector gimnasio | `movimientosDe()` | `GeneradorPokemonGimnasio.php:181-198` | Set manual primero; selector solo si no hay. |
| Loadout jugador | `actualizarLoadout()` | `ReclutadoController.php:315-354` | Conserva máximo 6. |
| Serializer | `formatearMovimiento()` | `ReclutadoSerializer.php:259-300` | Clamp de nivel visible y coste real. |

### 2.4 Evitar doble aplicación

| Riesgo de doble aplicación | Prevención |
|---|---|
| **Refugio + Senda en captura** | Una sola llamada `intentarConSenda()`; el refugio se pasa como `factorRefugio` y la Senda como `factorSenda`. No se aplica Senda en `ServicioCaptura` ni en el roll shiny. |
| **STAB + Senda ofensiva** | STAB sigue en `ManejadorSTAB`; Senda es un manejador distinto que usa el tipo efectivo del movimiento. Test: base 100 × STAB 1,5 × Senda 1,2 = 180, no 225. |
| **Habilidades/objetos/clima + Senda** | Son multiplicadores independientes en la cadena; Senda se añade al final. Test de composición con un manejador simulado. |
| **Daño fijo** | `CadenaDanio::calculate()` excluye `formulaDano !== 'normal'` (`:52-56`); Senda no aplica a OHKO, Superdiente, Night Shade, etc. |
| **Caramelos de tipo + familia/EV** | Solo `calcularCaramelosTipo()` recibe `MapaSendaJugador`; familia y EV no cambian. |
| **Mazmorra** | Se pasa `null`/mapa vacío en esta fase; el jefe no recibe pasivos. |
| **Rival** | Los generadores rivales no pasan `senda`; `Combatiente` rival queda con mapa vacío. |

### 2.5 Dominio puro

- `MapaSendaJugador` se construye en **aplicación/infraestructura** desde `PlayerTypePath`; `src/Shared/Domain/Sendas` no importa `App\Models` ni `Illuminate`.
- `PoliticaSenda`, `PoliticaCosteSenda`, `CurvaSenda` y `BeneficiosSenda` son puros.
- `CatalogoMovimientosSenda` es un **puerto** en `src/Battle/Domain/Repositories`; la implementación Eloquent vive en `app/Infrastructure/Battle`.
- `SelectorMovimientosRival` y `PuntuadorMovimiento` viven en `src/Battle/Domain/Selector` y reciben DTOs, nunca modelos Eloquent.
- `deptrac.yaml` debe prohibir `App`/`Illuminate` en `src/Shared/Domain/Sendas` y `src/Battle/Domain/Selector`.

---

## 3. Selector de movimientos de rivales

### 3.1 Universo de candidatos

```text
C = (Senda(tipoPrimario) ∪ Senda(tipoSecundario)) ∩ (learnset ∪ movepoolShowdown)
    ∪ (learnset ∪ movepoolShowdown) de otros tipos para cobertura
```

- **Pool de Senda:** todos los nodos de `type_path_nodes` del tipo con `required_level` 1-5, visibles 1-3. **No consulta `player_move_unlocks`**: es independiente de lo que el jugador haya desbloqueado.
- **Learnset:** movimientos `level-up` de la especie y su línea (`MapeadorPokemonBatalla::movimientosDe()`, `:179-217`), más MT/tutor de respaldo (`:223-261`) si no hay level-up.
- **Showdown:** movepool y sets cerrados como **preferencia**, no como legalidad. Un movimiento de Showdown que la especie no aprende en nuestro catálogo no se usa.
- **Cobertura:** movimientos de otros tipos que la especie aprende; no se inventan coberturas que la especie no tiene.

**Decisión:** el pool de Senda es un **universo de candidatos**, no una concesión indiscriminada. Un Pidgey no puede llevar Llamarada solo porque exista en la Senda de Fuego. La frase “los rivales tienen todos los movimientos de nuestra Senda” se interpreta como “no dependen de tus desbloqueos”, no como “ignoran su learnset”. Si se ignorara el learnset, el selector y la integración con Showdown carecerían de sentido.

### 3.2 Algoritmo determinista

1. **Construir candidatos** con las fuentes anteriores.
2. **Filtrar implementación** (§3.4).
3. **Aplicar tope de potencia** del tier (§3.3).
4. **Puntuar** cada candidato:
   ```text
   score = potenciaEfectiva
         × factorSTAB          // 1.5 si tipo ∈ tipos de la especie; 1.0 si no
         × factorCobertura     // 1.0 si 0 tipos superefectivos; 1.15 si 1-3; 1.25 si ≥4
         × factorRol           // tabla §3.5
         × factorPrecision     // max(0.5, accuracy/100); null → 1.0
         × factorPenalizacion  // recoil 0.9; dos turnos 0.7; autodestrucción 0 (excluido)
   ```
   Para movimientos de estado, `potenciaEfectiva = 40` y se usa `factorUtilidad` del rol.
5. **Seleccionar por slots:**
   - Slot 1: mejor movimiento **STAB** dañino.
   - Slot 2: mejor **cobertura** dañina de tipo distinto a los de la especie, si existe.
   - Slot 3: mejor **utilidad/setup** permitido por el rol (si el rol lo permite).
   - Slot 4: mejor candidato restante.
6. **Rellenar** hasta 4 con los mejores restantes, sin duplicar tipo+categoría+efectos (el `DepuradorMovimientos` se aplica antes).
7. **Ordenar** los 4 en el orden de slots; el orden no afecta al combate, pero hace el test exacto.
8. **Semilla** solo para desempatar candidatos con la misma puntuación; nunca para alterar la puntuación.

**Vector sintético exacto** (`fixture fire-fast`):

| Candidato | Tipo | Potencia | Score Ruta 1 (cap 60) | Score Ruta 3 (cap 110) |
|---|---|---:|---:|---:|
| `flame-wheel` | Fuego | 60 | 90,0 | 90,0 |
| `ember` | Fuego | 40 | 60,0 | 60,0 |
| `bite` | Siniestro | 60 | 69,0 | 69,0 |
| `quick-attack` | Normal | 40 | 50,0 | 50,0 |
| `will-o-wisp` | Fuego (estado) | 0 | 40,0 | 40,0 |
| `flamethrower` | Fuego | 90 | — (cap) | 121,5 |
| `fire-blast` | Fuego | 110 | — (cap) | 140,3 |
| `thunder-punch` | Eléctrico | 75 | — (cap) | 86,3 |
| `swords-dance` | Normal (estado) | 0 | — (setup no permitido) | 56,0 |

- **Ruta 1:** `[flame-wheel, bite, quick-attack, will-o-wisp]`.
- **Ruta 3:** `[fire-blast, thunder-punch, swords-dance, flamethrower]`.

Este vector se fija en `tests/Unit/Battle/SelectorMovimientosRivalTest.php` con los slugs exactos. Para especies reales se usan **golden files** generados una vez y revisados; no se inventan slugs de especies reales en este documento.

### 3.3 Topes de potencia por tier

| Contenido | Tier IV/EV | Cap de potencia base | Máx. estados/utilidad | Máx. setup |
|---|---|---:|---:|---:|
| Ruta 1 | T0 | **60** | 1 | 0 |
| Ruta 2 | T2 | **90** | 2 | 1 |
| Ruta 3 | T3 | **110** | 2 | 1 |
| Entrenador 1 | T1 | **70** | 1 | 0 |
| Entrenador 2 | T2 | **95** | 2 | 1 |
| Entrenador 3 | T3 | **120** | 2 | 1 |
| Gimnasio 1-6 | T1 | **80** | 2 | 0 |
| Gimnasio 7-12 | T2 | **100** | 2 | 1 |
| Gimnasio 13-18 | T3 | **120** | 2 | 1 |
| Líder 1-6 | T2 | **90** | 2 | 1 |
| Líder 7-12 | T3 | **110** | 2 | 1 |
| Líder 13-18 | T3 | **130** | 2 | 1 |

Los caps se alinean con los perfiles IV/EV del Addendum II: a más tier, más potencia permitida. El líder recibe **+10** sobre el cap de su gimnasio y el rol `Lider`.

### 3.4 Movimientos excluidos y penalizados

| Criterio | Regla |
|---|---|
| OHKO / daño fijo | Excluir si `formulaDano !== 'normal'` (Guillotina, Fisura, Superdiente, Night Shade…). |
| Precisión | Excluir `< 70`; penalizar `70-79` con `×0,9`. |
| Recoil | Excluir `> 33 %`; penalizar `10-33 %` con `×0,9`. |
| Autodestrucción | Excluir `selfdestruct`/`explosion` y flags equivalentes. |
| Dos turnos / carga | Excluir `charge`, `recharge`, `twoTurn`. |
| Efectos no implementados | Excluir si el `effect_key`/estado no está en la whitelist del motor; los movimientos de estado sin efecto aplicable también se excluyen. |
| Duplicados | `DepuradorMovimientos` elimina redundantes antes de puntuar (`DepuradorMovimientos.php:56-76`). |
| Estado puro sin efecto | Excluir si `potencia = 0`, sin `statusEffect`, sin cambios de stats y sin curación. |

Los movimientos de estado y boost **sí se permiten**, pero con los topes de tabla y el `factorRol`. Un `Setup Sweeper` puede llevar 1 setup; un `Stall` puede llevar 2 utilidades; un `Fast Attacker` rara vez lleva más de 1.

### 3.5 Roles de Showdown y plantillas

| Rol Showdown | Plantilla de 4 | Pesos |
|---|---|---|
| `Fast Attacker` | 3 ataques + 1 utilidad/prioridad | potencia ×1,1; prioridad +10; estado ×0,7 |
| `Bulky Attacker` | 3 ataques + 1 recuperación/estado | potencia ×1,0; recoil ×0,9; estado ×0,9 |
| `Bulky Support` | 2 ataques + 2 utilidades | utilidad ×1,2; recuperación ×1,3; potencia ×0,9 |
| `Setup Sweeper` | 1 setup + 3 ataques | setup ×1,4; STAB ×1,1; cobertura ×1,0 |
| `Stall` | 1 ataque + 3 utilidades | estado ×1,4; recuperación ×1,3; potencia ×0,7 |
| `Lider` | 2 STAB + 1 cobertura + 1 utilidad | STAB ×1,1; cap +10; sin OHKO |
| Sin entrada Showdown | Rol por stats | `speed ≥ 100` → Fast; `hp+def+spDef ≥ 250` → Bulky Support; resto → Bulky Attacker |

El rol es una **preferencia**, no una obligación: las restricciones de slots y los topes mandan.

### 3.6 Determinismo y semilla

```text
contexto =
  ruta:        "ruta:{habitatId}"
  entrenador:  "entrenador:{habitatId}:{trainerIndex}"
  gimnasio:    "gimnasio:{slug}:{etapa}"
  líder:       "lider:{slug}:{etapa}"

semilla = crc32("{contexto}|{nivel}|{fecha}|{slot}")
```

- `fecha` en formato `Y-m-d`; en ruta, el moveset es estable durante el día, como el equipo de entrenador.
- `slot` 0-2 para los 3 rivales.
- El `Random\Randomizer(new Mt19937($semilla))` solo se usa para el `jitter` de desempate; no se usa `mt_srand` global.
- Test: misma semilla + mismos candidatos → mismos 4 slugs; semilla distinta solo puede cambiar empates.
- Test: los 4 slugs son exactos para el fixture y para los golden files.

### 3.7 Gimnasio y líder

| Caso | Comportamiento |
|---|---|
| Gimnasio con set manual | `GeneradorPokemonGimnasio::movimientosDe()` usa `movimientosDeSeleccion()` + `depurar` y **no llama al selector** (`:189-196`). |
| Gimnasio sin set manual | Selector con `PerfilMovesetRival::gimnasio($indice, $etapa === 4)`. |
| Líder con set manual | El set manual gana. |
| Líder sin set manual | Rol `Lider`, cap +10, plantilla 2 STAB + 1 cobertura + 1 utilidad. |

El líder usa un pool/rol distinto **solo cuando no hay set manual**. Esto preserva los sets curados existentes y añade variedad a los que no los tienen.

### 3.8 Jugador 6 vs rival 4

**Decisión: el jugador conserva 6 movimientos; el rival usa 4.**

- El usuario pidió 4 para rivales, no cambiar al jugador.
- `ReclutadoController` ya valida `max:6` (`:328`); cambiarlo a 4 invalidaría loadouts guardados y exigiría migración/UX.
- El motor soporta 6; el jugador tiene una “caja de herramientas” y el rival un set competitivo de 4.
- La asimetría es deliberada: el jugador tiene más opciones, el rival tiene más foco. Si se quiere paridad competitiva, se añade un “modo competitivo” que limite ambos a 4; no se cambia el valor por defecto.
- La UI debe avisar: “Tu Pokémon puede llevar hasta 6; los rivales usan 4.”

### 3.9 Fallback si el pool + learnset no llega a 4

1. Añadir los movimientos sintéticos de `GeneradorMovimientosTipo` (2 por tipo + 2 Normal; `:23-47`).
2. Aplicar `DepuradorMovimientos`.
3. Si aún faltan, relajar en este orden: precisión ≥60, utilidad, learnset de MT/tutor, cobertura de otro tipo.
4. Si la especie no tiene tipos (dato corrupto), usar 4 movimientos Normal sintéticos.
5. Nunca duplicar un movimiento. Si aun así no hay 4, el test falla y el dato se corrige; no se genera un rival con 3 movimientos en producción.

---

## 4. Integración de Showdown

### 4.1 Respuesta directa: ¿tenemos recomendaciones o movesets?

**Sí hay movesets, pero no son recomendaciones Smogon curadas.** Son:

- `showdown/data/random-battles/gen9/sets.json`: **509 especies**, 1-3 sets por especie, con `role`, `movepool` de 5-7 movimientos, `abilities`, `teraTypes`. Son **pools de Random Battle**, no sets cerrados.
- `showdown/data/random-battles/gen9/bss-factory-sets.json`: **114 especies** con sets cerrados de 4 movimientos, EVs, naturaleza, objeto, habilidad.
- `showdown/data/random-battles/gen9/factory-sets.json`: 6 tiers (Uber, OU, UU…), sets cerrados.

Sirven como **capa de preferencia** para el selector, no como fuente de legalidad ni como recomendación competitiva curada. La legalidad la da nuestro `pokemon_moves` + `moves`.

### 4.2 Opciones evaluadas

| Opción | Pros | Contras | Esfuerzo | Mantenimiento |
|---|---|---|---|---|
| **A. Artefacto JSON build-time** | Sin DB, determinista, versionable, sin Node en runtime, fácil de testear | El artefacto puede quedar obsoleto; hay que regenerarlo | Medio | Bajo-medio |
| B. Tabla `pokemon_movesets` | Consultable en SQL, editable desde admin | Migración + seeder, duplicación, proceso de actualización, más mantenimiento | Alto | Alto |
| C. Solo referencia offline | Cero infraestructura | No usa los datos; el selector queda pobre | Bajo | Bajo |

**Decisión: A, artefacto JSON build-time.** No se introduce tabla ni dependencia de Node.

### 4.3 Esquema del artefacto

`resources/data/showdown_movesets.json`:

```json
{
  "version": 1,
  "generated_at": "2026-11-02T00:00:00Z",
  "source": {
    "repo": "smogon/pokemon-showdown",
    "commit": "<sha>",
    "license": "MIT"
  },
  "coverage": {
    "species_total": 509,
    "species_mapped": 435,
    "moves_total": 348,
    "moves_mapped": 300
  },
  "moves": {
    "flamethrower": { "num": 53, "showdown_name": "Flamethrower" }
  },
  "species": {
    "charizard": {
      "species_id": 6,
      "showdown_name": "Charizard",
      "roles": ["Fast Attacker", "Setup Sweeper"],
      "movepool": ["flamethrower", "earthquake", "focus-blast", "hurricane", "will-o-wisp"],
      "moves_unmapped": [],
      "sets": [
        {
          "role": "Fast Attacker",
          "moves": ["flamethrower", "earthquake", "focus-blast", "hurricane"],
          "item": "heavy-duty-boots",
          "ability": "blaze",
          "nature": "Timid",
          "evs": {"spa": 252, "spe": 252, "hp": 4}
        }
      ],
      "source": "sets"
    }
  }
}
```

- Claves ordenadas y JSON canónico para idempotencia.
- `moves_unmapped` conserva los nombres no mapeados para diagnóstico; no se usan en el selector.
- `coverage` es informativo y testeable.

### 4.4 Comando artisan y mapeo

```bash
php artisan showdown:build-movesets
php artisan showdown:build-movesets --check
php artisan showdown:build-movesets --out=resources/data/showdown_movesets.json
```

- Lee `showdown/data/random-battles/gen9/sets.json`, `bss-factory-sets.json`, `factory-sets.json` y `showdown/data/moves.ts`.
- Mapea **por `num`**, no por nombre localizado: `moves.ts` nombre → `num`; `storage/data/moves.json` `num` → slug; `moves` table confirma que existe.
- Filtra movimientos no implementados con el mismo `FiltroMovimientosRival`.
- Especies: `pokemon.csv` `species_id`/nombre normalizado; las formas regionales no mapeadas se dejan fuera (435/509).
- `--check` recalcula en memoria y compara SHA-256; exit 0 si coincide, 1 si está obsoleto.
- Escritura atómica: temporal + `rename`; nunca deja un JSON a medias.
- Sin red, sin Node, sin `npm`.

**Cobertura:** 300/348 movimientos mapeables (86,2 %); 48 no mapeables por Gen 9/DLC ausentes. 435/509 especies mapeables (85,5 %); las no mapeables son formas regionales.

> **Cautela de medición:** el paquete de análisis fija 300/348 y 435/509. El comando debe mapear por `num`; un parser por nombre localizado puede dar otra cifra. El test congela las cifras del paquete y falla si el artefacto se regenera con otra fuente; cualquier cambio se documenta y se actualiza el vector, no se relaja el test.

### 4.5 Uso del rol y especies sin entrada

- Si la especie tiene entrada Showdown, `roles` alimenta `PuntuadorMovimiento` como preferencia.
- Si no tiene entrada, se usa el rol por stats (§3.5); el selector sigue funcionando con Senda + learnset.
- Si tiene entrada pero todos sus movimientos son no mapeados, se ignora su movepool y se usa el fallback.
- No se hace fallback a la especie base para formas regionales: sus movimientos y habilidades difieren.

### 4.6 Licencia y atribución

- `showdown/LICENSE:1-21` es MIT.
- Se conserva `showdown/LICENSE`.
- Añadir `THIRD_PARTY_NOTICES.md` con “Pokémon Showdown, MIT, https://github.com/smogorn/pokemon-showdown” y el commit usado.
- El artefacto incluye `source.license = "MIT"` y `source.commit`.
- No se afirma endorsement de Nintendo/Creatures/Game Freak.

---

## 5. Impacto económico y de tiempo

### 5.1 Modelo de tiempo de combate

Se reutiliza el Addendum II:

```text
turnos_por_rival = 2 × (HP × Def)_n / (HP × Def)_0
turnos_combate  = 3 × turnos_por_rival
t               = 20 + 4 × turnos_combate
ratio_tiempo    = t / t_0
combates/h      = 10 / ratio_tiempo
```

Base Addendum II: L1 `44,0 s` (ratio 1,00; 10,00 combates/h), L2 `115,2 s` (2,62; 3,82), L3 `143,4 s` (3,26; 3,07).

**Efecto de Senda 5 (+20 % daño infligido):**

| Nivel | Turnos/rival base | Turnos/rival con Senda 5 | Turnos/combate | t (s) | Ratio | Combates/h |
|---|---:|---:|---:|---:|---:|---:|
| 1 | 2,00 | 1,67 | 5,0 | 40,0 | 0,909 | **11,00** |
| 2 | 7,93 | 6,61 | 19,8 | 99,3 | 2,257 | **4,43** |
| 3 | 10,29 | 8,58 | 25,7 | 123,0 | 2,795 | **3,58** |

La reducción de daño recibido **no alarga el combate**; reduce la probabilidad de derrota. El modelo no simula cambios ni curas, así que no la convierte en tiempo. Se declara como supuesto.

**Efecto del moveset rival (4 movimientos con cobertura/utilidad):**

| Contenido | Factor de tiempo | Motivo |
|---|---:|---|
| Ruta 1 | **1,00** | Cap 60, pocos movimientos, sin setup. |
| Ruta 2 | **1,03** | Cobertura de 1 tipo + 1 utilidad. |
| Ruta 3 | **1,06** | Cobertura + setup. |
| Entrenador 1 / 2 / 3 | **1,02 / 1,05 / 1,08** | Más potencia y cobertura. |
| Gimnasio 1-6 / 7-12 / 13-18 | **1,03 / 1,06 / 1,09** | Set completo. |
| Líder | **1,10** | Rol Líder, cap +10. |

**Tiempo neto (Senda 5 + moveset):**

| Nivel | t base | t con Senda 5 | t con moveset | Ratio neto | Combates/h netos |
|---|---:|---:|---:|---:|---:|
| 1 | 44,0 | 40,0 | 40,0 | 0,909 | **11,00** |
| 2 | 115,2 | 99,3 | 102,3 | 2,325 | **4,30** |
| 3 | 143,4 | 123,0 | 130,4 | 2,964 | **3,37** |

El moveset rival se come 3-6 puntos del +10-14 % que aporta la Senda 5. El resultado neto sigue siendo favorable al jugador que ha invertido, pero no domina el juego.

### 5.2 Hitos económicos antes/después

| Hito | Antes (plan v2) | Después (con Senda 5) | Δ | Supuesto |
|---|---:|---:|---:|---|
| **Senda N5** | 9,9 h | **9,0 h** | **−9 %** | 720 caramelos; 50 % del coste después de N4; N4 aporta +10 % candy y +10 % daño. |
| **18 Sendas** | 306 h | **292 h** | **−4,6 %** | Mismo supuesto; los pasivos solo aceleran la Senda del tipo que se está subiendo. |
| **Granja completa** | 36,6 h | **33,0 h** | **−9,8 %** | El equipo de farmeo usa al menos un movimiento del tipo maxeado; +20 % daño y +6 % de moveset. Si no lo usa, se queda en 36,6 h. |
| **Evolución N20** | 1,75 h | **1,33 h** | **−24 %** | La evolución comparte tipo con una Senda 5; +20 % caramelos y +10 % tiempo. Si no, 1,75 h. |

**Captura/hora (solo Pokémon del tipo):**

| Hábitat | Base | Senda 5, pool puro | Senda 5, pool mixto 1/3 |
|---|---:|---:|---:|
| 1 | 7,50 | **9,90** | **8,30** |
| 2 | 1,68 | **2,27** | **1,88** |
| 3 | 0,59 | **0,78** | **0,65** |

El bono de captura se aplica solo a los Pokémon que poseen el tipo; en un pool mixto el efecto medio es menor. No se aplica al roll shiny ni al reclutamiento directo.

### 5.3 ¿Los pasivos rompen los objetivos?

**No, si los objetivos se declaran sin pasivos.** Los pasivos son una recompensa de maestría, no la base de calibración. Aun así, se proponen ajustes si el desarrollador quiere que los objetivos se cumplan **con Senda 5**:

| Opción | Efecto | Coste |
|---|---|---|
| **A. Objetivos base sin pasivos (recomendada)** | No se toca nada; la Senda 5 acelera al jugador. | Ninguno. |
| B. Subir umbrales un 10 % | N4 39.600; N5 79.200. | Contradice el plan v2; requiere aprobación. |
| C. Bajar `m_tipo` un 10 % (1,60→1,44; 1,90→1,71) | Compensa el bono, pero frena a todos los jugadores, tengan o no Senda. | Mala señal; no recomendada. |
| D. Bajar el bono de caramelos a +5 %/+10 % | Conserva la economía; recompensa más débil. | Menos atractivo. |
| E. Mantener cap 25 % y aplicar captura solo en hábitat 2/3 | Conserva el cap; N5 no ayuda en hábitat 1. | Peor UX para el rol de captura. |

No hay telemetría; todas las cifras son **supuestos de diseño** y se validan con la simulación determinista de §8.

---

## 6. UI/UX

### 6.1 Página de Senda

- Dos secciones: **“Ataques (niveles 1-3)”** y **“Maestrías (niveles 4-5)”**.
- Cada nodo muestra: nombre, tipo, categoría, potencia, precisión, **nivel visible** (1-3), **coste real**, estado (`bloqueado`, `disponible`, `desbloqueado`), y si es “maestría de utilidad”.
- Panel de nivel: nivel actual, umbral siguiente, EXP faltante, caramelos necesarios, **beneficios activos** (“N4: +10 % captura Fuego, +10 % daño Fuego, −10 % daño Fuego recibido, +10 % caramelos Fuego”).
- El nivel 4-5 no muestra nodos que comprar, solo el siguiente umbral y el beneficio.
- Los 338 nodos de nivel 3 se agrupan por categoría y banda de potencia; por defecto se muestran “disponibles” y “desbloqueados”, con el resto colapsado.

### 6.2 Selector de movimientos del jugador

- Cada movimiento muestra: `nivel_senda` (clamp 1-3), `coste_senda`, `desbloqueado`, `senda_desbloqueada`, y un texto de beneficio del nivel (“Senda Fuego N4: +10 % daño Fuego”）。
- Si está bloqueado por nivel, CTA “Ir a Senda”; si está bloqueado por caramelos, “Te faltan X caramelos”.
- Se conserva el máximo 6; aviso “Los rivales usan 4 movimientos”.
- `ReclutadoSerializer::formatearMovimiento()` ya expone `nivel_senda` y `coste_senda` (`:259-300`); solo hay que cambiar el clamp y el coste.

### 6.3 Preview de combate

- Mostrar el **tier IV/EV** del rival (Addendum II) y el **nivel de potencia del moveset** (“Moveset: 4 movimientos; cobertura/utilidad; tope 90”).
- Aviso explícito: “Los rivales pueden usar movimientos que tú aún no has desbloqueado. No heredan tus bonos de Senda.”
- Mostrar los beneficios de Senda activos del jugador para esa batalla, por tipo.
- No mostrar el moveset exacto del rival antes de entrar si se quiere preservar sorpresa; mostrar solo el perfil y el cap.

### 6.4 Accesibilidad y rendimiento

- 338 nodos: usar grupos colapsables (`<details>`/`aria-expanded`), filtros por categoría/potencia/estado, búsqueda por nombre y paginación o virtualización.
- No depender solo del color para `bloqueado`/`disponible`/`desbloqueado`; añadir icono y texto.
- Navegación por teclado, foco visible, `prefers-reduced-motion`, contraste AA.
- `ArbolSendas::para()` ya carga nodos con `with('move')` y 3-4 consultas (`:84-104`); no añadir consultas por nodo. Cachear el catálogo de Senda por tipo en memoria durante la petición.
- Imágenes de tipos con `loading="lazy"`; evitar JS pesado en la página de 338 nodos.

---

## 7. Fases y orden

**Encaje: después de 2A (IV/EV) y antes del re-precio final. El orden correcto es estructura → selector → economía → UI.**

| Fase | Entregable | Depende de | Criterio de salida |
|---|---|---|---|
| 1 | Estabilización contable (ya definida) | — | Contabilidad cuadrada. |
| **2A** | IV/EV por tier (Addendum II) | 1 | Perfiles y stats efectivos. |
| **2B** | **Estructura de Senda:** niveles 1-3, pasivos 4-5, `CurvaSenda`, `PoliticaCosteSenda`, `MapaSendaJugador`, clamp de nodos, UI de datos | 2A | Reparto 187/114/338; total 9.584; umbrales 72.000. |
| **2C** | **Selector/movesets:** `CatalogoMovimientosSenda`, `SelectorMovimientosRival`, `PuntuadorMovimiento`, artefacto Showdown, integración ruta/entrenador/gimnasio | 2B, 2A | 4 movimientos exactos; golden files; gimnasio manual intacto. |
| **2D** | **Recalibración económica:** simulación determinista, sensibilidad con Senda 5, ajuste de `m_tipo`/umbrales si procede | 2B, 2C | Ratios de §8; objetivos base cumplidos. |
| 3 | Límites diarios y anti-grind (sin cambio) | 1 | — |
| 4 | **UI/preview:** tier IV/EV, moveset, aviso de movimientos no desbloqueados, panel de maestrías | 2A-2D | Feature tests de UI. |
| 5 | Sumidero de familia (opcional) | 3 | — |

**Justificación del orden:** el selector necesita el pool de Senda y los caps por tier; la economía necesita el tiempo real del selector. Recalibrar antes de 2C sería calibrar contra una dificultad desconocida. La UI de datos puede ir con 2B/2C; la UI de preview, en 4.

**Dependencias explícitas:** 2C usa los tiers de 2A; 2D usa los ratios de 2A y el factor de moveset de 2C; la documentación (ADR, README de Sendas) se actualiza al cerrar 2D.

---

## 8. Criterios de aceptación y tests deterministas

### 8.1 Vectores exactos

| ID | Criterio | Vector |
|---|---|---|
| A1 | Reparto visible de nodos | **187 / 114 / 338**; por categoría: N1 `63/34/90`, N2 `46/29/39`, N3 `163/136/39`; total 639. |
| A2 | Coste total | **9.584** exactos con la tabla de §1.3. |
| A3 | Coste por nodo | `ember`=2; `will-o-wisp`=5; `flamethrower`=25; `fire-blast`=60; `take-heart`=25; `lunar-dance`=60; `guillotine`=10; `superdiente`=5. |
| A4 | Umbrales | `umbral(1..5) = 0/9.000/18.000/36.000/72.000`; `EXP_MAXIMA=72.000`; `nivel(8.999)=1`, `nivel(9.000)=2`, `nivel(18.000)=3`, `nivel(36.000)=4`, `nivel(72.000)=5`. |
| A5 | Beneficios | N1-3 = 0; N4 = 0,10; N5 = 0,20; **no** 1,21. |
| A6 | Captura | `captureRate=166`, hábitat 1: Senda 1/4/5 → **25,0 / 27,5 / 30,0 %**. Hábitat 2 (`74,8`): **14,65 / 16,12 / 17,58 %**. Hábitat 3 (`54,7`): **6,44 / 7,08 / 7,73 %**. Ningún caso >30 %. |
| A7 | Daño | Base 100, STAB 1,5, Senda ofensiva 1,2, defensiva 0,8 → **144**; sin defensa → 180; sin Senda → 150. Daño fijo no cambia. |
| A8 | Caramelos de tipo | `expTipo=2.500`, `M=1`, `m_tipo=1`: Senda 1/4/5 → **10 / 11 / 12**. `expTipo=1.000`, `m_tipo=1,9`, Senda 5 → **9** (un solo floor; si se redondea antes, 8). |
| A9 | Selector | Fixture `fire-fast`: Ruta 1 → `[flame-wheel, bite, quick-attack, will-o-wisp]`; Ruta 3 → `[fire-blast, thunder-punch, swords-dance, flamethrower]`. |
| A10 | Showdown | `coverage.moves_mapped=300`, `moves_total=348`; `species_mapped=435`, `species_total=509`; esquema válido; dos builds idénticos. |
| A11 | No regresión | Loadout 6; sets manuales de gimnasio; shiny; mazmorra sin Senda; IV/EV del Addendum II. |
| A12 | Arquitectura | `src/Shared/Domain/Sendas` y `src/Battle/Domain/Selector` sin `App`/`Illuminate`; sin `PlayerTypePath` en `src`. |

### 8.2 Pruebas por capa

**Unitarias de dominio**

- `NivelSendaTest`: clamp 1..5, `visible()`, `tienePasivos()`.
- `BeneficiosSendaTest`: tabla 0/0/0/0, 0,10, 0,20; suma aditiva, no compuesta.
- `MapaSendaJugadorTest`: `vacio()`, `desdeArray()`, `bonoCaptura()` con uno y dos tipos (max), `bonoDano/Defensa/Caramelo`.
- `PoliticaSendaTest`: factores; `caramelosTipoBrutos()` con vectores exactos; orden `M × m_tipo × factorSenda`.
- `PoliticaCosteSendaTest`: tabla de 9 bloques; total 9.584; nodos de borde.
- `PuntuadorMovimientoTest`: STAB, cobertura, rol, precisión, recoil, prioridad; vector `fire-fast`.
- `SelectorMovimientosRivalTest`: 4 exactos; slots; cap; determinismo con semilla; fallback sin Showdown; sin duplicados.
- `FiltroMovimientosRivalTest`: OHKO, fijo, precisión <70, recoil >33 %, autodestrucción, dos turnos.

**Integración**

- `CatalogoMovimientosSendaTest`: siembra un tipo con nodos 1-5; devuelve solo visibles 1-3; **no** consulta `player_move_unlocks`.
- `ShowdownArtefactoTest`: esquema, cobertura 300/348 y 435/509, idempotencia, `--check` falla con artefacto obsoleto.
- `CalculadorRecompensasSendaTest`: tipo con y sin Senda; un solo floor; familia/EV intactos.
- `ProbabilidadCapturaSendaTest`: refugio × Senda una sola vez; cap 30; sin Senda no cambia.
- `CombatienteSerializacionTest`: payload antiguo sin `senda` → mapa vacío; payload nuevo round-trip.

**Feature**

- Ruta L1/L2/L3 con Senda 1/4/5: captura, daño, caramelos de tipo.
- Ruta: `meta['nivel']` llega a `OtorgarRecompensasRuta` y a la captura (cierra H2 del Addendum I).
- Exploración: `$exploracion->nivel` + refugio + Senda; `M=0,5`.
- Entrenador: selector de 4; IV/EV del Addendum II.
- Gimnasio: set manual intacto; sin set manual, selector; líder con rol `Lider`.
- Mazmorra: sin Senda; daño/captura/caramelos idénticos a antes.
- Shiny: rival con perfil del tier; reclutado con IV 15.
- Loadout: 6 movimientos sigue aceptándose; 7 rechazado.

**Arquitectura**

- `deptrac`/`phpstan`: sin `App`/`Illuminate` en dominio; sin dependencias circulares.
- Test de que `src/Battle/Domain/Selector` no importa `App\Models`.

**Golden files**

- `tests/Fixtures/SelectorMovimientos/ruta-1.json`, `ruta-2.json`, `ruta-3.json`, `entrenador-3.json`, `gimnasio-lider.json`.
- Cada golden contiene 3-4 especies representativas por tier y los 4 slugs exactos, con la semilla y la fecha fijas.
- Se generan una vez desde el catálogo aprobado y se revisan a mano; cualquier cambio de catálogo/selector exige actualizar el golden y justificarlo.

---

## 9. Riesgos y decisiones abiertas

### 9.1 Riesgos

| ID | Riesgo | Impacto | Mitigación |
|---|---|---|---|
| R1 | El selector de 4 con cobertura/utilidad hace los combates más duros de lo previsto. | Medio | Caps por tier; factor de tiempo 1,00-1,10; simulación determinista; ajustar caps, no la estructura. |
| R2 | Los pasivos de Senda aceleran la economía y rompen los hitos. | Medio | Objetivos base sin pasivos; sensibilidad “con Senda 5”; si se desvía >15 %, ajustar umbrales o bono. |
| R3 | El cap de captura 30 % en N5 aumenta reclutables. | Medio | Solo aplica a Pokémon del tipo; en pool mixto el efecto medio es menor; revisar límite de descarte. |
| R4 | El artefacto Showdown queda obsoleto o cambia de cobertura. | Bajo | `--check`, versión, hash, test de cobertura; regenerar es un comando. |
| R5 | 338 nodos de nivel 3 degradan la UI. | Medio | Agrupar/filtrar/paginar; no consultas por nodo; lazy images. |
| R6 | La serialización de sesión se rompe al añadir `senda`. | Alto | Campo opcional con default vacío; test de payload antiguo; no cambiar claves existentes. |
| R7 | El `floor` de caramelos oculta el bono de +10 %. | Medio | Tests sobre `caramelosTipoBrutos()` y vectores con valores que cambian; no confiar solo en el resultado final. |
| R8 | El máximo de captura para duales favorece a los duales. | Bajo | Es intencional; test; no suma. |
| R9 | La asimetría 6 vs 4 se percibe injusta. | Bajo | Aviso en UI; modo competitivo opcional; no cambiar el default. |
| R10 | El pool de Senda se interpreta como “cualquier Pokémon puede llevar cualquier movimiento”. | Alto | El selector exige intersección con learnset/Showdown; test de especie sin el movimiento. |
| R11 | Los sets manuales de gimnasio se pierden. | Alto | Prioridad manual antes del selector; test de regresión. |
| R12 | La cifra 300/348 depende del parser de `moves.ts`. | Medio | Mapeo por `num`; test que congela la cifra; documentar cualquier cambio. |

### 9.2 Decisiones que requieren aprobación explícita

| ID | Decisión | Recomendación | Alternativa | Impacto |
|---|---|---|---|---|
| **D1** | ¿Jugador 6 o 4 movimientos? | **Mantener 6**; rival 4. | Limitar a 4 con migración/UX. | Cambia loadouts y equilibrio. |
| **D2** | ¿+10/+20 absolutos o compuestos? | **Absolutos**: 1,10 / 1,20. | Compuestos: 1,10 / 1,21. | El compuesto es más fuerte y menos testeable. |
| **D3** | ¿Cap de captura 30 % en N5? | **Sí, 30 %**; única excepción al cap-25. | Mantener 25 % y aplicar bono solo en hábitat 2/3. | Más capturas en N5. |
| **D4** | ¿Pasivos en mazmorra? | **No en esta fase**. | Daño/defensa sí, captura/caramelos no. | Evita trivializar al jefe ×10. |
| **D5** | ¿Showdown artefacto JSON o tabla? | **JSON build-time**. | Tabla `pokemon_movesets`. | Menos migración y mantenimiento. |
| **D6** | ¿Topes de potencia por tier? | **Tabla §3.3**. | Caps más bajos (p. ej. 50/80/100). | Afecta dificultad y variedad. |
| **D7** | ¿Captura de duales: max, primario o suma? | **Max**. | Primario o suma. | Max evita doble conteo. |
| **D8** | ¿Pasivos pequeños en N2/N3? | **No**; solo desbloqueo. | +2,5 %/+5 %. | Añadir pasivos complica la economía. |
| **D9** | ¿Objetivos base o con Senda 5? | **Base sin pasivos**. | Con Senda 5 y ajustar umbrales. | La base es más justa para nuevos. |
| **D10** | ¿Qué hacer con los 8 estados de tramo 4-5? | **Mantener en N3 con 25/60**. | Borrarlos o repreciar a 10 (total 9.394). | Mantener conserva contenido y total. |
| **D11** | ¿El pool de Senda ignora el learnset? | **No**; intersección con learnset/Showdown. | Pool indiscriminado. | Evita Pidgey con Llamarada. |
| **D12** | ¿Niveles 4-5 automáticos o compra manual? | **Automáticos al umbral**. | Acción “subir nivel” con coste extra. | Automático es coherente con `SendasController::experiencia()`. |

---

## Anexo — Borrador de ADR

- **Título:** Sendas maestras: 3 niveles de ataque y 2 de pasivos; selector determinista de 4 movimientos para rivales.
- **Estado:** Propuesto — requiere aprobación explícita del desarrollador (plan v3).
- **Contexto:** 639 nodos en `type_path_nodes`; niveles 4-5 con 178 nodos y 8 estados; los rivales usan todos los movimientos de nivel sin límite de 4; Showdown está vendorizado pero no se usa; la economía del Addendum II fija 9.584 caramelos y 72.000 EXP.
- **Decisión:** comprimir la **vista** a 3 niveles (tramo 1-5 como precio), convertir 4-5 en pasivos +10/+20, conservar el total 9.584 con coste derivado, añadir el mapa de Senda al `Combatiente` del jugador, aplicar captura/daño/caramelos en puntos únicos, y seleccionar 4 movimientos rivales con pool de Senda + learnset + preferencia Showdown (artefacto JSON).
- **Consecuencias:** no hay migración de datos; se añaden VOs y dos manejadores de daño; la sesión gana un campo opcional compatible; el artefacto Showdown se regenera con un comando; los objetivos económicos se miden sin pasivos; el jugador conserva 6 movimientos y los sets manuales de gimnasio siguen teniendo prioridad.
</subagent>