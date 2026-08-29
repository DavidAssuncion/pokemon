# Evolución del sistema de exploraciones — Pokémon Exploradores

## 1. Objetivo

Evolucionar el módulo actual desde un sistema principalmente determinista de generación de recompensas hacia un sistema de expediciones con:

- Riesgo real.
- Decisiones sobre qué equipo enviar.
- Roles de exploración.
- Sinergias entre los tres miembros.
- Afinidad entre Pokémon, equipo y hábitat.
- Encuentros con dificultad variable.
- Grupos enemigos y emboscadas.
- Contratiempos y oportunidades perdidas.
- Éxitos parciales y fracasos.
- Retiradas.
- Bitácora narrativa.
- Recompensas proporcionales al riesgo asumido.

La primera iteración no debería convertirse en un sistema de combate completo ni introducir HP persistente en los reclutados.

---

# 2. Funcionamiento actual

## Ciclo de vida

```text
POST /exploraciones (store)
        │
        ▼
exploraciones_activas
        │
        ▼
Scheduler cada 5 min
        │
        ▼
exploraciones:procesar
        │
        ▼
ProcesarExploracionHandler
        │
        ├── calcula inicio / fin / inicioVuelta
        ├── genera encuentros pendientes
        └── si corresponde, finaliza
                │
                ▼
FinalizarExploracionHandler
        │
        ├── lee eventos['bitacora']
        ├── calcula recompensas
        ├── persiste recompensas
        └── escribe eventos['resultado']
```

## Problema actual

La generación de eventos funciona aproximadamente así:

```text
slot de 5 minutos
      ↓
pokemon / caramelo_familia / caramelo_ev
      ↓
si es pokemon
      ↓
combate = victoria automática
      ↓
recompensa / captura
```

El riesgo es prácticamente cero.

Actualmente:

- Los combates siempre terminan en victoria.
- Los Pokémon no tienen HP persistente.
- Los reclutados no tienen estados que puedan perjudicar una expedición.
- El equipo está ocupado durante la exploración, pero ese es prácticamente el único coste.
- Cualquier combinación de tres Pokémon puede explorar cualquier hábitat con resultados razonables.
- Un encuentro Pokémon está fuertemente asociado a una recompensa.

El objetivo es que explorar pase a ser una decisión estratégica.

---

# 3. Principio fundamental

Actualmente:

```text
Exploración
    ↓
Encuentro
    ↓
Victoria
    ↓
Recompensa
```

Propuesta:

```text
Exploración
    ↓
Incidente
    ↓
Resolución
    ↓
Consecuencia
    ↓
Resultado de la expedición
    ↓
Recompensas
```

Un encuentro ya no implica necesariamente una recompensa.

Una expedición puede terminar como:

```text
ÉXITO EXCEPCIONAL
ÉXITO
ÉXITO PARCIAL
FRACASO
RETIRADA
```

Principio de diseño:

> La aleatoriedad decide qué ocurre; la preparación del equipo decide cómo de bien o de mal afronta lo que ocurre.

---

# 4. Eventos de exploración

En lugar de que `SimuladorEncuentros::generarEvento()` genere únicamente tipos planos, se recomienda una estructura jerárquica.

```text
Exploración
│
├── Hallazgo
│   ├── objeto
│   ├── caramelo
│   └── objeto raro
│
├── Encuentro
│   ├── Pokémon solitario
│   ├── grupo enemigo
│   ├── emboscada
│   └── Pokémon excepcional
│
├── Contratiempo
│   ├── trampa
│   ├── desorientación
│   ├── bloqueo
│   ├── clima
│   └── terreno
│
└── Situación especial
    ├── huida
    ├── retirada
    ├── ruta secreta
    └── evento raro
```

Esto evita que el simulador se convierta en un `switch` gigante y facilita añadir contenido posteriormente.

---

# 5. Probabilidades iniciales orientativas

No se recomienda mantener necesariamente el actual 60/20/20.

Una primera distribución podría ser:

```text
45% encuentro Pokémon
20% hallazgo
15% encuentro especial
10% contratiempo
10% evento neutral
```

Dentro de los encuentros Pokémon:

```text
65% encuentro normal
15% huida
10% grupo enemigo
7% emboscada
3% encuentro excepcional
```

Los porcentajes son orientativos. Deben ajustarse mediante pruebas y telemetría.

No mostrar las probabilidades exactas al jugador. La incertidumbre forma parte de la exploración.

La interfaz puede mostrar información cualitativa:

```text
Peligro: ★★★☆☆
Zona peligrosa
Se han observado grupos agresivos
```

---

# 6. Encuentro normal

Un encuentro Pokémon deja de equivaler automáticamente a victoria.

Ejemplo:

```text
14:10
El equipo encuentra un Rattata salvaje.

14:15
El equipo consigue derrotarlo.

14:20
El equipo continúa la exploración.
```

Resultado:

```text
victoria
→ EXP
→ posibilidad de captura
```

Este sigue siendo el caso habitual.

---

# 7. Huida

La huida introduce encuentros sin recompensa.

Ejemplo:

```text
El equipo encuentra un Pikachu salvaje.

Antes de que pueda comenzar el combate,
Pikachu huye entre los árboles.
```

Resultado:

```text
EXP = 0
captura = 0
recompensa = 0
```

Contrato:

```json
{
  "type": "huida",
  "pokemon_id": 25
}
```

La finalidad es romper la asociación:

```text
encuentro = recompensa garantizada
```

---

# 8. Grupos enemigos

Un encuentro puede involucrar varios Pokémon.

Ejemplo:

```text
Rattata Lv.8
Rattata Lv.8
Raticate Lv.12
```

La dificultad depende de:

- número de enemigos;
- nivel;
- fuerza individual;
- sinergia del grupo;
- hábitat;
- modificadores especiales.

Ejemplo:

```text
grupo enemigo
dificultad = 42
```

Los grupos permiten que la exploración sea más peligrosa sin necesidad de introducir todavía un combate táctico real.

---

# 9. Emboscadas

Una emboscada es diferente de un grupo normal porque el equipo no tiene tiempo de prepararse.

Ejemplo:

```text
20:15
El equipo escucha ruidos entre los arbustos.

20:20
¡Emboscada!
Un grupo de Pokémon salvajes aparece por sorpresa.

20:25
El equipo consigue repeler el ataque.

20:30
La emboscada ha hecho perder tiempo al equipo.
```

Posibles efectos:

- aumento de dificultad;
- pérdida de tiempo;
- mayor probabilidad de retirada;
- pérdida de una oportunidad futura.

Una Vanguardia o un Pokémon con alta Detección puede reducir o incluso evitar la penalización.

---

# 10. Contratiempos

Los contratiempos no deberían ser exclusivamente combates.

Ejemplos:

## Derrumbe

```text
Un desprendimiento bloquea el camino.
→ -10 minutos
```

## Desorientación

```text
El equipo pierde el rastro.
→ -15 minutos
→ se pierde una posible oportunidad
```

## Terreno complicado

```text
El terreno dificulta el avance.
→ -10 minutos
```

## Clima adverso

```text
Las condiciones dificultan la exploración.
→ penalización temporal
```

## Objeto perdido

```text
El equipo pierde parte de los recursos encontrados.
```

## Emboscada

```text
→ encuentro difícil
→ pérdida de tiempo si se supera
```

---

# 11. El tiempo como principal recurso de riesgo

Para la primera iteración no introducir HP persistente.

La principal moneda de riesgo será el tiempo.

Una exploración de 120 minutos puede perder:

```text
emboscada       -10 min
desorientación  -15 min
terreno         -10 min
```

Resultado:

```text
120 min previstos
      ↓
85 min efectivos
```

Esto es interesante porque el tiempo perdido no es simplemente una penalización numérica.

Es tiempo que ya no puede utilizarse para generar futuros eventos.

Conceptualmente:

```text
Oportunidades previstas
████████████████████████

Contratiempo
        ↓

Oportunidades disponibles
████████████████░░░░░░░░
```

Una mala expedición puede no perder una recompensa que ya tenía, sino perder la oportunidad de conseguir otras.

---

# 12. Éxito parcial

Evitar únicamente:

```text
éxito / fracaso
```

Usar varios grados:

```text
ÉXITO EXCEPCIONAL
ÉXITO
ÉXITO PARCIAL
FRACASO
RETIRADA
```

Ejemplo:

```text
Objetivo:
explorar durante 120 minutos

Resultado:
el equipo tuvo varios incidentes y aprovechó solo parte
de las oportunidades disponibles.
```

Resultado:

```text
ÉXITO PARCIAL
```

Las recompensas se reducen, pero no necesariamente a cero.

Ejemplo orientativo:

```text
ÉXITO EXCEPCIONAL → 120%
ÉXITO             → 100%
ÉXITO PARCIAL     → 60-80%
FRACASO           → 0-30%
RETIRADA          → recompensas encontradas hasta ese momento
```

Los multiplicadores son orientativos.

---

# 13. Retirada

La retirada es el resultado negativo principal antes de introducir HP.

Ejemplo:

```text
El equipo encuentra un grupo demasiado poderoso.

La capacidad del equipo no es suficiente.

El equipo decide retirarse.
```

La retirada:

- termina la exploración antes de tiempo;
- puede conservar parte de las recompensas ya obtenidas;
- evita borrar completamente el progreso;
- genera una consecuencia clara en la bitácora.

Ejemplo:

```json
{
  "type": "retirada",
  "reason": "grupo_enemigo"
}
```

---

# 14. Roles de exploración

Roles existentes:

| Rol | Encuentros | Objetos | EXP |
|---|---:|---:|---:|
| Vanguardia | +30% | Normal | +25% |
| Combatiente | Normal | Normal | Normal |
| Recolector | -30% | +50% | -20% |
| Soporte | Normal | Normal | +10% curativo |

Estos modificadores deberían evolucionar para afectar también a la resolución de incidentes.

No limitar los roles a simples multiplicadores.

---

# 15. Vanguardia

Especialista en abrir camino y asumir riesgos.

Posibles efectos:

- mayor probabilidad de encuentros;
- mayor EXP;
- mejor capacidad para afrontar obstáculos;
- detección de peligros;
- reducción de determinadas penalizaciones;
- mayor exposición a eventos peligrosos.

Posible interacción:

```text
Vanguardia
→ detecta emboscadas
→ reduce penalización de tiempo
→ permite avanzar por zonas difíciles
```

---

# 16. Combatiente

Especialista en resolver encuentros.

Efectos:

- mayor capacidad contra grupos;
- menor probabilidad de retirada;
- mejores resultados frente a encuentros difíciles;
- mayor rendimiento de EXP de combate.

Es el rol equilibrado para equipos que esperan encontrar enemigos.

---

# 17. Recolector

Especialista en recursos.

Efectos:

- más objetos;
- menos encuentros;
- menor EXP;
- mejor aprovechamiento de hallazgos;
- posibilidad de aumentar la calidad de ciertos objetos.

Conceptualmente:

```text
Recolector
→ evita problemas
→ busca recursos
→ progresa más lentamente
```

---

# 18. Soporte

Especialista en mantener al equipo operativo.

En la primera iteración:

- reduce determinadas penalizaciones;
- puede reducir pérdida de tiempo;
- puede mejorar resultados de recuperación;
- puede aumentar EXP de soporte.

En una segunda iteración podría interactuar con HP, heridas y estados temporales.

No es necesario introducir HP para que el rol tenga utilidad.

---

# 19. Nuevos roles potenciales

## Explorador

Especialista en descubrir rutas y eventos.

Puede:

- encontrar rutas alternativas;
- descubrir eventos ocultos;
- reducir pérdidas por desorientación;
- descubrir zonas especiales.

## Rastreador

Especialista en localizar Pokémon.

Puede:

- aumentar encuentros;
- reducir huidas;
- mejorar probabilidades de captura;
- encontrar especies específicas o raras.

## Guardián

Especialista en proteger al grupo.

Puede:

- reducir efectos de emboscadas;
- reducir pérdidas de objetos;
- disminuir probabilidad de retirada;
- proteger a otros roles de penalizaciones.

## Buscador

Especialista en calidad de hallazgos.

Puede:

- aumentar probabilidad de objetos raros;
- mejorar calidad de recompensas.

## Investigador

Especialista en eventos especiales.

Puede:

- descubrir información;
- detectar eventos poco frecuentes;
- desbloquear rutas o situaciones especiales.

## Superviviente

Especialista en afrontar condiciones adversas.

Puede:

- reducir consecuencias de clima;
- reducir penalizaciones de terreno;
- reducir pérdida de tiempo.

## Señuelo

Especialista en atraer encuentros deliberadamente.

Puede:

- aumentar encuentros;
- aumentar posibilidades de encontrar determinadas especies;
- aumentar riesgo de grupos o emboscadas.

## Explorador rápido / Movilidad

Especialista en desplazamiento.

Puede:

- reducir tiempo perdido;
- aprovechar rutas alternativas;
- mejorar la eficiencia de la exploración.

No es necesario implementar todos estos roles como clases rígidas. Algunos pueden convertirse en capacidades derivadas.

---

# 20. Roles frente a capacidades

Separar conceptualmente:

```text
ROL
→ determina el comportamiento

CAPACIDADES
→ determinan cómo resuelve los incidentes
```

Un Pokémon puede tener:

```text
Rol principal:
Recolector
```

pero contribuir con:

```text
Combate: 4
Detección: 6
Exploración: 7
Recolección: 9
Supervivencia: 5
Movilidad: 2
```

Esto permite que un Pokémon pueda ser un buen Recolector y, al mismo tiempo, aportar capacidades secundarias.

Evita crear demasiadas clases.

---

# 21. Capacidades recomendadas

Empezar con seis:

| Capacidad | Uso |
|---|---|
| Combate | Resolver enemigos |
| Detección | Detectar emboscadas |
| Exploración | Encontrar rutas y eventos |
| Recolección | Encontrar objetos |
| Supervivencia | Mitigar contratiempos |
| Movilidad | Reducir tiempo perdido |

Posibles capacidades futuras:

```text
Rastreo
Sigilo
Adaptación
Resistencia
```

Solo añadirlas si existe contenido que realmente las utilice.

---

# 22. Sinergias de roles

El equipo está compuesto por tres Pokémon.

La composición debe importar.

En lugar de:

```text
Pokémon A +10
Pokémon B +10
Pokémon C +10
= +30
```

buscar:

```text
rol A + rol B
        ↓
sinergia
        ↓
cambio de comportamiento
```

Las sinergias pueden ser multiplicadores, pero es preferible que algunas cambien la resolución de eventos.

---

# 23. Sinergias por parejas

## Vanguardia + Combatiente

**Asalto**

- mejor contra grupos;
- menor penalización en encuentros difíciles;
- menor probabilidad de retirada.

```text
Vanguardia
     +
Combatiente
     ↓
   Asalto
```

## Vanguardia + Soporte

**Avance seguro**

- menor pérdida de tiempo;
- mejor respuesta a emboscadas.

## Recolector + Explorador

**Prospección**

- más objetos;
- posibilidad de hallar objetos raros;
- mejor aprovechamiento de rutas.

## Rastreador + Combatiente

**Cacería**

- más encuentros;
- menos huidas;
- mejor captura.

## Explorador + Soporte

**Supervivencia**

- menos contratiempos;
- menor impacto de rutas alternativas.

## Guardián + cualquiera

**Protección**

- reducción de consecuencias negativas;
- especialmente útil frente a emboscadas.

---

# 24. Sinergias de los tres miembros

No bloquear el equipo a composiciones concretas, pero reconocer arquetipos.

## Equipo de combate

```text
Vanguardia
Combatiente
Combatiente
```

Sinergia:

**Dominio del combate**

Ventajas:

- gran capacidad contra grupos;
- mucha EXP;
- baja probabilidad de retirada.

Desventajas:

- pocos objetos;
- más encuentros;
- más exposición al peligro.

---

## Equipo de exploración

```text
Vanguardia
Explorador
Rastreador
```

Sinergia:

**Exploración profunda**

Ventajas:

- descubre eventos;
- encuentra rutas;
- localiza Pokémon;
- mayor posibilidad de encuentros especiales.

Desventaja:

- capacidad de combate inferior a un equipo especializado.

---

## Equipo de recolección

```text
Recolector
Recolector
Soporte
```

Sinergia:

**Recolección segura**

Ventajas:

- muchos objetos;
- pocos encuentros;
- menor impacto de contratiempos.

Desventajas:

- poca EXP;
- mala respuesta ante grupos.

---

## Equipo de caza

```text
Rastreador
Combatiente
Vanguardia
```

Sinergia:

**Cacería**

Ventajas:

- más encuentros;
- menos huidas;
- mejores capturas.

Desventajas:

- más riesgo.

---

## Equipo de supervivencia

```text
Guardián
Soporte
Superviviente
```

Sinergia:

**Expedición segura**

Ventajas:

- pocos contratiempos;
- baja probabilidad de retirada;
- excelente para zonas peligrosas.

Desventajas:

- menor cantidad de encuentros;
- menor recompensa máxima.

---

## Equipo equilibrado

```text
Vanguardia
Recolector
Soporte
```

Sinergia:

**Expedición equilibrada**

Ventajas:

- combate aceptable;
- buena recolección;
- buena supervivencia.

Es una composición adecuada para zonas desconocidas.

---

# 25. Sinergias negativas

No todas las combinaciones deben generar bonus.

Una mala composición debería tener consecuencias.

## Tres Recolectores

```text
Recolector
Recolector
Recolector
```

Puede generar:

**Especialistas en recolección**

pero:

- capacidad de respuesta baja;
- peor rendimiento contra grupos;
- mayor riesgo ante ciertos incidentes.

## Tres Vanguardias

```text
Vanguardia
Vanguardia
Vanguardia
```

Puede generar:

**Exploración agresiva**

pero:

- muchos encuentros;
- muchas oportunidades de emboscada;
- alto riesgo;
- mayor consumo de tiempo.

La finalidad es evitar que:

```text
más roles = siempre más bonus
```

---

# 26. Personalidad del equipo

La composición puede determinar el estilo de la expedición sin que el jugador tenga que seleccionar una estrategia explícita.

Ejemplo:

```text
Vanguardia + Vanguardia + Combatiente
```

Comportamiento:

> El equipo avanza directamente hacia las amenazas.

Mientras:

```text
Recolector + Explorador + Soporte
```

se comporta como:

> El equipo evita enfrentamientos y dedica más tiempo a buscar recursos.

Esto permite que la composición genere historias diferentes.

---

# 27. Peligro del hábitat

Cada hábitat debería tener un peligro base.

Ejemplo orientativo:

| Hábitat | Peligro |
|---|---:|
| Pradera | 10 |
| Bosque | 20 |
| Cueva | 30 |
| Montaña | 35 |
| Pantano | 40 |
| Volcán | 50 |
| Ruinas profundas | 60 |

La interfaz puede mostrar:

```text
Peligro: ★★★☆☆
```

en lugar del valor numérico.

El peligro no debe significar únicamente enemigos.

Puede afectar:

- probabilidad de emboscada;
- tamaño de grupos;
- probabilidad de retirada;
- contratiempos;
- pérdida de tiempo;
- rareza de encuentros;
- calidad de recompensas.

---

# 28. Afinidad Pokémon - hábitat

No todos los Pokémon deberían ser igual de adecuados para todas las zonas.

Ejemplo:

```text
Hábitat: Volcán

Charmander → muy adecuado
Charmeleon → muy adecuado
Geodude    → adecuado
Squirtle   → neutral/adecuado
Bulbasaur  → poco adecuado
```

El tipo Pokémon puede participar en el cálculo, pero no debería ser el único criterio.

Un Pokémon de tipo desfavorable no debería quedar automáticamente inutilizado.

---

# 29. Afinidad de equipo

La afinidad debería calcularse sobre el conjunto de tres.

Ejemplo:

```text
Hábitat: Volcán

Charmander  +10
Geodude      +8
Bulbasaur    -8

Afinidad total: +10
```

Esto permite equipos mixtos.

Un Pokémon poco adecuado puede ser compensado parcialmente por los otros miembros.

---

# 30. Capacidad de exploración

Crear una puntuación interna:

```text
capacidadExploracion
```

Conceptualmente:

```text
nivel
+
afinidad de hábitat
+
rol
+
capacidades
+
sinergias
+
herramientas
=
capacidad de exploración
```

Ejemplo:

```text
Equipo A → 72
Equipo B → 48
Equipo C → 31

Peligro de zona → 55
```

Resultado:

```text
Equipo A → preparado
Equipo B → riesgo moderado
Equipo C → peligroso
```

No es necesario mostrar la fórmula al jugador.

---

# 31. Resolución de incidentes

Cada incidente tiene dificultad.

Ejemplo:

```text
Encuentro normal  → 25
Grupo enemigo     → 45
Emboscada         → 60
Grupo dominante   → 80
```

Comparación:

```text
Capacidad >= dificultad
→ éxito

Capacidad ≈ dificultad
→ éxito con coste

Capacidad << dificultad
→ fracaso / retirada
```

No hace falta utilizar umbrales rígidos. Puede existir una pequeña componente aleatoria.

---

# 32. Respuesta al evento

El evento no debería ser directamente el resultado.

Modelo:

```text
evento
   ↓
capacidad relevante del equipo
   ↓
sinergia
   ↓
afinidad
   ↓
herramientas / movimientos
   ↓
azar
   ↓
resultado
```

Ejemplo:

```text
EMBOSCADA

Detección del equipo: 72
Peligro: 60

→ detectada antes de producirse
→ sin penalización
```

Otro:

```text
EMBOSCADA

Detección: 35
Peligro: 60

→ emboscada
→ resolución mediante combate
```

---

# 33. Movimientos como herramientas

Los movimientos pueden tener utilidad fuera del combate.

Ejemplos:

| Movimiento | Uso exploratorio |
|---|---|
| Corte | Eliminar obstáculos |
| Golpe Roca | Romper rocas |
| Excavar | Crear rutas |
| Surf | Atravesar agua |
| Vuelo | Reducir desplazamiento |
| Destello | Explorar cuevas |
| Dulce Aroma | Aumentar encuentros |

Esto introduce una capa Pokémon mucho más interesante.

Un equipo con menor poder bruto puede ser mejor para una exploración porque tiene las herramientas necesarias.

---

# 34. Cobertura del equipo

Puede existir una puntuación interna de cobertura:

```text
Equipo
│
├── Combate
├── Exploración
├── Recolección
├── Supervivencia
├── Detección
└── Movilidad
```

Ejemplo:

```text
Equipo A

Combate       ████████░░
Exploración   ██████░░░░
Recolección   ████░░░░░░
Supervivencia ██████░░░░
Terreno       ███████░░░
```

No es necesario mostrar estas barras al jugador.

Sirven para resolver eventos.

---

# 35. Eventos que aprovechan la cobertura

## Camino bloqueado

```text
Evento:
El camino está bloqueado por una roca.
```

Equipo con Golpe Roca:

```text
→ rompe la roca
→ 0 minutos perdidos
```

Equipo sin herramienta:

```text
→ busca otra ruta
→ -15 minutos
```

Equipo con Excavación:

```text
→ crea una ruta alternativa
→ posibilidad de descubrir un objeto oculto
```

Esto permite que los movimientos y capacidades generen resultados distintos.

---

# 36. Riesgo según el hábitat

El valor de los roles debe cambiar según la zona.

## Bosque

Especialmente útiles:

```text
Rastreador
Recolector
Explorador
```

Amenazas:

```text
emboscadas
pérdida de orientación
grupos salvajes
```

## Volcán

Especialmente útiles:

```text
Vanguardia
Combatiente
Superviviente
```

Amenazas:

```text
calor
terreno
grupos agresivos
```

## Cueva

Especialmente útiles:

```text
Explorador
Guardián
Rastreador
```

Amenazas:

```text
oscuridad
bifurcaciones
emboscadas
derrumbes
```

## Pantano

Especialmente útiles:

```text
Superviviente
Soporte
Explorador
```

Amenazas:

```text
terreno
movilidad
estados ambientales
```

La pregunta estratégica pasa a ser:

> ¿Qué equipo es bueno para este hábitat?

---

# 37. Riesgo y recompensa

Una zona peligrosa no debería ser simplemente peor.

Debe existir una relación:

```text
más peligro
    ↓
mayor probabilidad de perder
    ↓
pero también
    ↓
mejores oportunidades
```

Ejemplo:

```text
Pradera
Seguridad: ★★★★★
Recompensa: ★★☆☆☆

Volcán
Seguridad: ★★☆☆☆
Recompensa: ★★★★☆

Ruinas profundas
Seguridad: ★☆☆☆☆
Recompensa: ★★★★★
```

Esto crea decisiones reales.

---

# 38. Preparación antes de explorar

La pantalla de inicio puede mostrar:

```text
Hábitat:
Volcán

Peligro:
★★★★☆

Equipo:
Poco adecuado

Roles:
Vanguardia
Recolector
Soporte

Riesgo estimado:
Alto

Recompensa esperada:
Alta
```

La información puede ser cualitativa para mantener incertidumbre.

---

# 39. Bitácora narrativa

La bitácora debe ser parte importante de la experiencia.

En lugar de:

```text
Pokémon encontrado: Rattata
Caramelo encontrado
Pokémon encontrado: Pidgey
```

Propuesta:

```text
10:05
El equipo encuentra huellas recientes.

10:10
Un Rattata aparece entre la vegetación.

10:15
El Rattata huye antes de que comience el combate.

10:20
El equipo encuentra un objeto abandonado.

10:25
¡Emboscada!
Un grupo de Pokémon aparece detrás del equipo.

10:35
El equipo consigue escapar, pero pierde tiempo.

10:50
El equipo continúa la exploración.
```

La recompensa final es el resumen de la historia.

---

# 40. Ejemplo de exploración completa

```text
EXPLORACIÓN — BOSQUE

Equipo:
Charmander Lv.12
Pikachu Lv.11
Bulbasaur Lv.10

Roles:
Vanguardia
Combatiente
Recolector

Riesgo:
★★★☆☆

Afinidad:
Adecuada
```

Bitácora:

```text
14:00
El equipo entra en el bosque.

14:05
Encuentran un grupo de Pokémon salvajes.

14:10
El equipo consigue derrotarlos.

14:15
Obtienen experiencia.

14:25
Encuentran un objeto.

14:30
¡Emboscada!

14:35
El equipo consigue repeler el ataque,
pero pierde 10 minutos.

14:50
Un Pokémon salvaje huye.

15:05
El equipo encuentra otro objeto.

15:20
El camino queda bloqueado.

15:30
El equipo encuentra una ruta alternativa.

15:40
La exploración continúa.

16:00
El equipo regresa.
```

Resultado:

```text
ÉXITO PARCIAL

Encuentros: 3
Victorias: 1
Huidas: 1
Emboscadas: 1
Contratiempos: 1

Tiempo perdido: 20 min

Recompensas:
EXP: 85
Objetos: 3
Caramelos: 1
```

---

# 41. Contrato de eventos

Mantener el enfoque aditivo actual.

Ejemplo:

```json
{
  "type": "emboscada",
  "pokemon_ids": [19, 19, 20],
  "difficulty": 42,
  "resolution": "superada",
  "duration_loss": 10
}
```

Huida:

```json
{
  "type": "huida",
  "pokemon_id": 25
}
```

Contratiempo:

```json
{
  "type": "contratiempo",
  "subtype": "desorientacion",
  "duration_loss": 15
}
```

Retirada:

```json
{
  "type": "retirada",
  "reason": "grupo_enemigo"
}
```

Resultado final:

```json
{
  "resultado": "exito_parcial",
  "duration_real": 120,
  "incidentes": {
    "encuentros": 3,
    "emboscadas": 1,
    "huidas": 1,
    "contratiempos": 1
  },
  "contratiempos": [
    {
      "type": "perdida_tiempo",
      "minutes": 10
    }
  ],
  "recompensas": {
    "exp": 42,
    "caramelos": 2
  }
}
```

Los contratos deben ser aditivos para mantener compatibilidad con el sistema existente.

---

# 42. Nueva responsabilidad: EvaluadorExploracion

Arquitectura actual:

```text
FinalizarExploracionHandler
        ↓
CalculadorRecompensas
```

Propuesta:

```text
FinalizarExploracionHandler
        ↓
EvaluadorExploracion
        ↓
CalculadorRecompensas
        ↓
PersistirRecompensas
```

`EvaluadorExploracion` responde:

> ¿Qué ocurrió realmente durante la expedición?

`CalculadorRecompensas` responde:

> ¿Cuánto merece el equipo por lo ocurrido?

Esto evita introducir toda la lógica de riesgo dentro de `CalculadorRecompensas`.

---

# 43. Arquitectura propuesta completa

```text
POST /exploraciones
        │
        ▼
exploraciones_activas
        │
        ▼
Scheduler
        │
        ▼
ProcesarExploracionHandler
        │
        ├── CalculadorPeligro
        │
        ├── CalculadorCapacidadEquipo
        │
        ├── SimuladorEncuentros
        │
        └── GeneradorEventos
                │
                ▼
        eventos['bitacora']
                │
                ▼
FinalizarExploracionHandler
                │
                ▼
        EvaluadorExploracion
                │
                ├── éxito excepcional
                ├── éxito
                ├── éxito parcial
                ├── fracaso
                └── retirada
                │
                ▼
        CalculadorRecompensas
                │
                ▼
        PersistirRecompensas
                │
                ▼
        eventos['resultado']
```

---

# 44. Separación recomendada de responsabilidades

## CalculadorPeligro

Determina el peligro de la zona.

```text
hábitat
+
zona
+
modificadores ambientales
```

## CalculadorCapacidadEquipo

Determina la capacidad del equipo.

```text
Pokémon
+
niveles
+
roles
+
afinidades
+
capacidades
+
movimientos
+
sinergias
```

## SimuladorEncuentros

Decide qué evento aparece.

```text
encuentro
hallazgo
contratiempo
evento especial
```

## EvaluadorExploracion

Determina cómo resuelve el equipo cada incidente.

```text
capacidad
vs
dificultad
```

## CalculadorRecompensas

Determina la recompensa final.

## PersistirRecompensas

Mantiene su responsabilidad actual.

---

# 45. Segunda iteración: estados y heridas

No introducir HP en la primera versión.

Una vez que las expediciones tengan riesgo real, podría introducirse:

```text
heridas leves
fatiga
estados temporales
recuperación
```

Entonces Soporte adquiere una función mucho más profunda:

```text
Soporte
→ reduce heridas
→ acelera recuperación
→ evita que una mala expedición tenga consecuencias prolongadas
```

Pero esto debería ser una segunda fase porque afecta:

- esquema de reclutados;
- batalla;
- equipos;
- reclutamiento;
- persistencia;
- lógica de disponibilidad.

---

# 46. Roadmap recomendado

## Fase 1 — Riesgo básico

Implementar:

```text
- peligro del hábitat
- capacidad del equipo
- afinidad hábitat/Pokémon
- grupos enemigos
- emboscadas
- huidas
- contratiempos
- pérdida de tiempo
- éxito parcial
- retirada
```

Mantener:

```text
- recompensas actuales
- capturas actuales
- reclutados sin HP
- movimientos
- esquema principal
```

## Fase 2 — Roles

Evolucionar:

```text
Vanguardia
Combatiente
Recolector
Soporte
```

para que afecten a la resolución de incidentes.

Añadir inicialmente:

```text
Explorador
Rastreador
Guardián
```

## Fase 3 — Capacidades y herramientas

Añadir:

```text
Combate
Detección
Exploración
Recolección
Supervivencia
Movilidad
```

y utilizar movimientos como herramientas de exploración.

## Fase 4 — Eventos especiales

Añadir:

```text
Pokémon raro
grupo dominante
ruta secreta
objeto excepcional
evento ambiental
Pokémon que pide ayuda
jefe de zona
```

## Fase 5 — Estados persistentes

Solo si el sistema lo necesita:

```text
heridas
fatiga
estados
curación
recuperación
```

---

# 47. Modelo conceptual final

El sistema debería responder a cinco preguntas:

```text
1. ¿Dónde están?
   → Hábitat / peligro

2. ¿Quiénes van?
   → Equipo / roles / afinidades

3. ¿Qué encuentran?
   → Eventos / encuentros

4. ¿Cómo responden?
   → Capacidades + sinergias + dificultad

5. ¿Cómo termina?
   → Éxito / parcial / fracaso / retirada
```

Flujo completo:

```text
ELECCIÓN DEL EQUIPO
        ↓
RIESGO DEL HÁBITAT
        ↓
CAPACIDAD DEL EQUIPO
        ↓
EVENTOS ALEATORIOS
        ↓
SINERGIAS
        ↓
RESOLUCIONES
        ↓
CONSECUENCIAS
        ↓
HISTORIA EN LA BITÁCORA
        ↓
RESULTADO
        ↓
RECOMPENSA
```

El objetivo final no es crear un simulador de combate automático.

Es crear un **simulador de expediciones Pokémon**.

La composición del equipo debe determinar qué oportunidades aprovecha, qué problemas evita y cómo responde cuando la exploración se tuerce.

> La exploración debería contar una pequeña historia que el jugador no podía predecir completamente, pero en la que su elección de equipo haya importado.
