# TASK-004 — Balance económico de caramelos, EV y recompensas

## Petición literal

> Economia, requisito y uso de objetos. Por ejemplo, los caramelos necesarios para aprender ataques en sendas, los caramelos para subir de nivel los nidos y granjas, EV, recompensas obtenidas... Debemos analizar costes y cuantas recompensas podemos recibir para balancear los costes, ahora es todo muy orientativo y debe ser mas justo y razonable

## Interpretación operativa

1. **Inventario de sumideros**: cuantificar todos los costes actuales en caramelos
   (familia, EV y tipo), requisitos de desbloqueo de ataques en Sendas, costes de
   mejora de nidos, refugios y complementos de granja, y cualquier otro consumo de
   objetos o EV.
2. **Inventario de fuentes**: cuantificar cuántos caramelos, EXP y demás recursos
   entrega cada actividad (combate de ruta, exploraciones, mazmorras, capturas,
   gimnasios u otras), con probabilidades, cantidades, factores y tiempos.
3. **Análisis de ratios**: comparar coste total de progresión frente a ingresos
   esperados por sesión/hora/día; detectar cuellos de botella, sumideros
   desproporcionados y fuentes dominantes.
4. **Propuesta de balance**: definir una economía objetivo justificada (tiempos de
   progresión, conversión caramelo→progreso, topes y salvaguardas) y el plan de
   implementación. Nada se implementa sin aprobación explícita del plan.

## Ampliación del requisito (2026-11-02)

> «Por ejemplo, los niveles 1 de habitats sera para encontrar pokemon y capturarlos "facilmente" el nivel 2 y 3 es para farmear caramelos, el combate sera mas duro, pero ahora mismo, no interesa, porque tardas mucho mas, es mas eficiente pelear 3 veces contra debiles y ganar en 4 ataques antes que plantearte varios turnos»

Interpretación operativa adicional:

- **Nivel 1 de hábitat**: rol de captura/colección. Debe mantener la ventaja de tasa de captura y bajo riesgo; no debe ser el mejor lugar para farmear caramelos.
- **Niveles 2 y 3**: rol de farmeo de caramelos. Aceptan combates más duros y largos, pero deben ser **más eficientes en caramelos por minuto** que el nivel 1 para un jugador capaz de ganarlos. El criterio es tiempo, no solo recompensa por victoria.
- El diseño debe evitar que «3 combates fáciles de 4 ataques» sigan siendo superiores a un combate duro de varios turnos. Si el nivel 2/3 no compensa el tiempo extra, no se usará.

## Segunda ampliación: IV/EV de los combates (2026-11-02)

> «Debemos plantear tambien los combates con IV/EV
>
> Rutas nivel
> 1 0 IV/EV
> 2 Maximo IV/EV los 2 stats mas altos, la mitad en el resto
> 3 Maximo IV/EV en todo
>
> Entrenadores
> 1 Maximo IV/EV en el stat mas altos, 0 en el resto
> 2 Maximo IV/EV los 2 stats mas altos, la mitad en el resto
> 3 Maximo IV/EV en todo
>
> Gimnasios
> 1-6 Maximo IV/EV en el stat mas altos, 0 en el resto
> 7-12 Maximo IV/EV los 2 stats mas altos, la mitad en el resto
> 13-18 Maximo IV/EV en todo y de ahi podremos afinar las recompensas»

Interpretación operativa:

- La dificultad de los rivales se define por una **política de IV/EV por tier de contenido**:
  - Rutas nivel 1-3 (nivel de hábitat elegido en ruta).
  - Entrenadores nivel 1-3 (nivel de entrenador del hábitat).
  - Gimnasios 1-18 (orden del catálogo, de menor a mayor nivel mínimo).
- «Máximo» se refiere a la escala del proyecto: IV 15, EV 255 por stat. «La mitad» debe definirse con un redondeo explícito y testeable.
- «Los 2 stats más altos» se calcula sobre los stats base de cada Pokémon rival, con desempate determinista.
- Esta política cambia el tiempo de combate y, por tanto, **es prerequisito para afinar las recompensas**. Las recompensas deben compensar la dificultad extra para que los tiers altos sigan siendo eficientes por minuto.
- El jugador conserva sus propios IV/EV; la política aplica a los rivales generados.
- Hay que decidir el tratamiento de shiny (hoy recibe IV máximos), de la etapa 4 (líder) dentro de cada gimnasio y de las mazmorras.

## Tercera ampliación: beneficios de Senda y movesets de rivales (2026-11-02)

> «Cada nivel de senda aportara beneficios, los ataques se desbloquean entre los niveles 1 y 3, y apartir de ahi, se dan mejores recompensas, mas % de captura a ese tipo, mas daño, o recibir menos daño de ese tipo +10-10%. Los pokemon rivales tendran disponible todos los movimientos de nuestra senda, pero no recibiran nuestras mejoras. Si soy nivel 2 y no tengo aprenddio lanzallamas, los rivales si pueden llevarlo equipado. Hay que plantear tambien su selector de movimientos, stab + cobertura/utilidad, tenemos en pokemon showdowsn recomendaiciones o movesets?»

Interpretación operativa:

- **Sendas niveles 1-3**: desbloquean ataques. Los nodos de ataque deben concentrarse
  en esos niveles.
- **Sendas niveles 4-5**: dejan de desbloquear ataques y otorgan beneficios
  pasivos de tipo: más % de captura de ese tipo, más daño con ese tipo y/o menos
  daño recibido de ese tipo. El usuario cita **+10 % / −10 %** como referencia;
  hay que definir la progresión exacta por nivel y si se acumula.
- **Rivales**: pueden usar todos los movimientos de la Senda del tipo
  correspondiente, aunque el jugador no los haya desbloqueado. No reciben los
  beneficios pasivos de la Senda del jugador.
- **Selector de movimientos**: hay que diseñar cómo eligen los 6 movimientos los
  rivales, priorizando STAB + cobertura/utilidad. Investigar si el repositorio
  de Pokémon Showdown incluido tiene recomendaciones/movesets utilizables o si
  hay que construir un selector propio.
- **Pool del rival (feedback posterior)**: usa sus movimientos por nivel **más**
  los que el jugador haya desbloqueado **y su especie pueda aprender por
  MT/tutor/huevo** (o por nivel a una altura superior), aunque no los tenga por
  nivel o le falte nivel. No puede usar un movimiento desbloqueado que su especie
  no aprende.
- **Tamaño y valores ajustables**: el rival usa 6 movimientos; el tamaño, los
  topes de potencia, los umbrales, los costes y los porcentajes de pasivos deben
  vivir en constantes/configuración para poder ajustarlos después sin tocar la
  lógica.
- Esta ampliación cambia el alcance del plan v2; se requiere plan v4 y nueva
  aprobación antes de implementar.

## Contexto conocido

- Proyecto: `/home/david/pokemon` — Laravel 12 / PHP 8.4, rama `master`.
- Módulos implicados: `app/Support/SendaCostes.php`, `SendaNiveles.php`,
  `SendaProgreso.php`, `ArbolSendas.php`, `ItemCatalogo.php`,
  `src/Granjas/`, `src/Exploraciones/`, `src/CombateRuta/`, `src/Mazmorras/`,
  `src/Battle/`, `app/Http/Controllers/SendasController.php`,
  `app/Http/Controllers/ReclutadoController.php`.
- El proyecto tiene WIP local extenso (117 modificados, 9 borrados, 73 sin
  seguimiento). Varios archivos de economía ya están modificados o son nuevos
  (`EconomiaRecompensas.php` sin seguimiento, `CalculadorRecompensas.php`,
  `EstimadorRecompensasExploracion.php`, `SendaProgreso.php`, `ArbolSendas.php`).
  Ese WIP es la línea base y **no debe revertirse ni sobrescribirse**.

## Restricciones

- Esta fase es de análisis y diseño: no se modifica código de aplicación.
- No ejecutar migraciones, seeders ni escrituras en base de datos.
- No leer `.env` ni secretos.
- No revertir, commitear ni limpiar cambios preexistentes.
- Toda afirmación debe citar archivo y línea; separar claramente «valor actual»
  de «valor propuesto».
- El balance debe ser explicable en términos de tiempo de juego y esfuerzo, no
  solo de números redondos.

## Fuera de alcance (por ahora)

- Rebalanceo de stats, fórmulas de combate o probabilidades de captura que no
  afecten a la economía de objetos.
- Cambios de UI no derivados de costes o recompensas.
- Nuevos objetos o monedas que no existan en el diseño actual.

## Estado

`RECEIVED`
