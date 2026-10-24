# Análisis previo — Motor de efectos para ataques y habilidades

## Objetivo

Preparar el dominio de `src/Battle` para representar y ejecutar de forma precisa los ataques y
habilidades descritos en:

- `storage/data/ataques.txt` (30 ataques, 8 sin descripción)
- `storage/data/poke skills - habilidades.csv` (59 filas, 70 claves por los grupos con `/`)
- `storage/data/poke habilidades.csv` (asignación pokédex → habilidades y movimientos)

El alcance de esta iteración es la **infraestructura del motor**: modelo de datos de efectos,
capacidades del combatiente, ganchos de cálculo y pipeline de ejecución. No se implementan todas
las habilidades una a una ni se cambia la UI; se dejan los puntos de extensión y una muestra de
efectos que valida el diseño.

## Hallazgos de los datos

### Cobertura de nombres

- Habilidades usadas en `poke habilidades.csv`: **96 etiquetas distintas** que, normalizando
  acentos/mayúsculas, son ~90 claves. Hay duplicados por casing (`Cabeza Roca`/`Cabeza roca`,
  `Evasion`/`evasion`, `Caparazon`/`Caparazón`, `Depredador Prehistórico`/`Prehistorico`).
- Movimientos usados en `poke habilidades.csv`: **59 etiquetas** (~55 canónicas).
- Movimientos definidos en `ataques.txt`: **30**.
- Movimientos usados pero **sin definición**: 33.
- Movimientos definidos pero **no usados** en la asignación: 6
  (`Arenas Ardientes`, `Maldición`, `Pantalla de luz / Reflejo`, `Poder pasado`, `Trampa Rocas`,
  `Tenaza`).
- Movimientos definidos con **descripción vacía**: 8
  (`Carambano`, `Cuerno Certero`, `Desenrrollar`, `Envite Igneo`, `Rizo defensa`, `Testarazo`,
  `Onda Mental`, `Hilo Venenoso`).
- Habilidades usadas pero **sin definición**: `Caparazón`, `Granizo`, `Sequía`, `Llovizna`,
  `Tormenta Arena`, `Torrente`, `Ira Gyarados`. Las cuatro primeras se pueden inferir como
  invocadores de clima; `Torrente` e `Ira Gyarados` no tienen texto y deben decidirse.
- Typo usado: `Predicion` (canónico `Predicción`), `Espsura` (canónico `Espesura`).

### Ambigüedades que requieren decisión de producto

1. **Foco Resplandor / Puño Meteoro**: “doble de daño a escudos, la mitad a la salud”. Se modela
   como multiplicador de escudo ×2 y de salud ×0,5, pero falta decidir si el excedente del escudo
   roto mantiene el ×2 o pasa al ×0,5 de salud.
2. **Pantalla de luz / Reflejo**: la capacidad del escudo se calcula “como un ataque de potencia
   70 ×5” con def/def.esp del usuario. Falta fijar la fórmula exacta (¿5 golpes? ¿×5 potencia?).
3. **Represión Metal**: “devuelve el 150 % del daño recibido del último movimiento del oponente en
   ese turno”. Requiere memoria del último daño por turno y decidir si el rebote ignora escudos.
4. **Pico Taladro**: “más daño a armadura; si la rompe, hace más daño”. Faltan porcentajes.
5. **Cañones**: “+25 % a ataques de cañón o lanzar”. Falta la lista cerrada de movimientos
   etiquetados como cañón/lanzamiento.
6. **Caparazón**, **Torrente**, **Ira Gyarados**: sin definición en el CSV.
7. **Dormilón**, **Refuerzos**, **Marioneta Inerte**, **Desafío**, **Muro Mágico**: necesitan
   estado de batalla/equipo (clon, control de aliado, taunt, daño indirecto) que hoy no existe.
8. **Drenadoras / Puas / Trampa Rocas / Maldición / Canto Mortal**: son efectos de campo o
   temporales sobre el objetivo, no daño inmediato. Necesitan una colección de efectos de equipo
   o de batalla.
9. **Precisión/Evasión**: `Evasion` (12,5 %) y `Hypnotizador` exigen una tirada de acierto que hoy
   no existe en `CadenaDanio`; `MultiplicadoresStats` ya tiene `accuracy`/`evasion` sin uso.

## Diseño propuesto

### 1. Perfil del movimiento (datos, no código por ataque)

`MovimientoBatalla` conserva su constructor actual y gana un `PerfilMovimiento` opcional al final:

- `golpes` y `fraccionPotenciaPorGolpe` (Doble/Triple/Cuádruple Ataque, Tenaza).
- `probabilidadEstado` (Arenas Ardientes 30 %, etc.).
- `drenajeFraccion`, `recoilFraccion`.
- `danoDirectoFraccion` (Puño Sombra 33 %, Sombra Traviesa 15 %).
- `multiplicadorDanoEscudo` / `multiplicadorDanoSalud` (Foco Resplandor, Puño Meteoro).
- `costeVelocidadFraccion` (Velocidad Extrema).
- `danoDiferido` (Maldición, Canto Mortal, Tenaza).
- `claveEfecto` para los ataques que necesitan lógica propia (Drenadoras, Puas, Desafío…).

Ventaja: los 30 ataques se declaran como datos y el ejecutor los interpreta sin ramas por nombre.

Ejemplos de declaración (los tipos son la inferencia inicial; quedan por confirmar):

```php
new MovimientoBatalla('Foco Resplandor', 100, TipoPokemon::ACERO, CategoriaMovimiento::ESPECIAL,
    perfil: new PerfilMovimiento(
        multiplicadorDanoEscudo: 2.0,
        multiplicadorDanoSalud: 0.5,
        claveEfecto: ClaveEfectoMovimiento::FOCO_RESPLANDOR,
    ));

new MovimientoBatalla('Puño Sombra', 80, TipoPokemon::FANTASMA, CategoriaMovimiento::FISICO,
    perfil: new PerfilMovimiento(
        danoDirectoFraccion: 0.33,
        claveEfecto: ClaveEfectoMovimiento::PUNO_SOMBRA,
    ));

new MovimientoBatalla('Doble Ataque', 100, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO,
    perfil: new PerfilMovimiento(golpes: 2, fraccionPotenciaPorGolpe: 0.5));

new MovimientoBatalla('Arenas Ardientes', 60, TipoPokemon::TIERRA, CategoriaMovimiento::ESPECIAL,
    statusEffect: EstadoPokemon::BURN,
    perfil: new PerfilMovimiento(probabilidadEstado: 0.30));
```


### 2. Capacidades de las habilidades (interfaces segregadas)

En `src/Battle/Domain/Effects/Capacidades/`:

- `ModificaDanioInfligido` / `ModificaDanioRecibido`
- `ModificaEstadistica`
- `InmunidadTipo`
- `InmuneCritico` / `AjustaCritico`
- `BonusStab`
- `RedirigeObjetivo`
- `ReaccionaAlContacto`
- `ReaccionaAlDebilitamiento`
- `ReaccionaAlEscudoRoto`
- `ReaccionaAlEstado`
- `ReaccionaAlSerObjetivo`
- `InmuneARecoil`
- `InmuneADanioIndirecto`
- `ProbabilidadEsquiva`
- `ReaccionaAlDanioRecibido` (resultado rico: HP, escudo, rotura)

`ColeccionEfectos` agrega las capacidades y `Combatiente` las expone. Las habilidades futuras solo
implementan la interfaz que necesitan; no se toca el motor.

### 3. Cadena de daño

Se añaden eslabones sin alterar el orden existente:

- `ManejadorInmunidadTipo`: deja el daño a 0 si el defensor es inmune (Absorción Agua/Eléctrico/
  Fuego). Se corrige `CadenaDanio` para que una inmunidad real devuelva 0 y no el mínimo 1.
- `ManejadorHabilidadesAtaque`: multiplicadores del atacante (Garra Dura, Enjambre, Acero
  Templado, Velo Arena, Depredador, Crueldad…).
- `ManejadorHabilidadesDefensa`: multiplicadores del defensor (Armadura Batalla, Escamas…).
- `ManejadorCritico`: consulta inmunidad y probabilidad extra (Predicción).
- `ManejadorSTAB`: consulta STAB extra (Vacío, Guardián de los mares).

Las estadísticas efectivas consultan `ModificaEstadistica` (Clorofila, Ímpetu Arena, Velo Agua,
Flexibilidad). El clima se cachea en el combatiente para que la consulta no dependa del agregado.

### 4. Ejecución

`ServicioEjecucionBatalla` interpreta el `PerfilMovimiento`:

1. Repite golpes con la potencia fraccionada.
2. Suma el daño directo del perfil al del atacante.
3. Aplica multiplicadores de escudo/salud.
4. Aplica estado con probabilidad y respetando `ReaccionaAlEstado`.
5. Aplica drenaje/recoil (respetando `InmuneARecoil`).
6. Devuelve un resultado rico (`dano`, `danoHp`, `danoEscudo`, `golpes`, `curado`, `recoil`).

Los ataques especiales (Drenadoras, Puas, Desafío…) se delegan a `InterfazEjecutorMovimiento` con
una clave; el servicio no crece con `switch` por nombre.

### 5. Catálogo y validación

- `NormalizadorNombre` (acentos, casing, alias) para que `Predicion`→`Predicción`,
  `Espsura`→`Espesura`, `Caparazon`→`Caparazón`, etc.
- `CatalogoHabilidades`/`CatalogoMovimientos` con clave canónica y estado
  (`LISTA`, `PENDIENTE`, `AMBIGUA`, `SIN_DEFINICION`).
- Un validador cruzado contra `poke habilidades.csv` para detectar nombres usados sin definición.

En esta iteración se prepara el modelo y la normalización; la carga masiva a BD queda para la
iteración de datos (la tabla `abilities` ya existe vacía, no hay tabla de movimientos).

## Archivos afectados

### Nuevos

- `src/Battle/Domain/ValueObjects/PerfilMovimiento.php`
- `src/Battle/Domain/ValueObjects/ResultadoDanio.php`
- `src/Battle/Domain/Enums/ClaveEfectoMovimiento.php`
- `src/Battle/Domain/Enums/CondicionDanio.php`
- `src/Battle/Domain/Enums/AmbitoDanio.php`
- `src/Battle/Domain/Effects/Capacidades/*.php` (interfaces)
- `src/Battle/Domain/Effects/EfectoInmunidadTipo.php`
- `src/Battle/Domain/Effects/EfectoModificadorDanio.php`
- `src/Battle/Domain/Effects/EfectoMultiplicadorEstadistica.php`
- `src/Battle/Domain/Effects/EfectoCuracionFinTurno.php`
- `src/Battle/Domain/Effects/EfectoDanioFinTurno.php`
- `src/Battle/Domain/Effects/EfectoContactoEstado.php`
- `src/Battle/Domain/Effects/EfectoRedireccionTipo.php`
- `src/Battle/Domain/Effects/EfectoAlDebilitar.php`
- `src/Battle/Domain/Effects/EfectoAlRomperEscudo.php`
- `src/Battle/Domain/Effects/EfectoInmuneCritico.php`
- `src/Battle/Domain/Effects/EfectoInmuneRecoil.php`
- `src/Battle/Domain/Effects/EfectoMuroMagico.php`
- `src/Battle/Domain/Effects/EfectoPresion.php`
- `src/Battle/Domain/Chain/ManejadorInmunidadTipo.php`
- `src/Battle/Domain/Chain/ManejadorHabilidadesAtaque.php`
- `src/Battle/Domain/Chain/ManejadorHabilidadesDefensa.php`

### Modificados

- `src/Battle/Domain/MovimientoBatalla.php` (perfil, serialización, `conPotencia`)
- `src/Battle/Presentation/DTOMovimientoBatalla.php` (perfil en Livewire)
- `src/Battle/Domain/Combatiente.php` (clima, resultado de daño, delegaciones, curación con excedente)
- `src/Battle/Domain/BarrerasVidaManager.php` (multiplicadores escudo/salud y resultado rico)
- `src/Battle/Domain/Effects/ColeccionEfectos.php` (agregadores de capacidades)
- `src/Battle/Domain/Effects/FabricaEfectos.php` (varios efectos por clave)
- `src/Battle/Domain/EquipoBatalla.php` (añadir todos los efectos de una clave)
- `src/Battle/Domain/Chain/CadenaDanio.php` (nuevos eslabones, inmunidad real)
- `src/Battle/Domain/Chain/ManejadorSTAB.php` (STAB extra)
- `src/Battle/Domain/Chain/ManejadorCritico.php` (inmunidad/probabilidad)
- `src/Battle/Domain/ServicioEjecucionBatalla.php` (perfil)
- `src/Battle/Presentation/DTOResultadoDanio.php` (resultado rico)
- `src/Battle/Domain/AgregadoBatalla.php` (propagación de clima, hooks de debilitamiento)
- `src/Battle/Domain/Enums/ClaveEfecto.php` (claves nuevas)
- `src/Battle/Domain/ReglasBatalla.php` (fracciones nuevas)
- `app/Providers/BattleEffectServiceProvider.php` (registro de efectos)
- `app/Livewire/Combate.php` (usar resultado rico y disparar hooks)
- `app/Livewire/Presenters/MovesPreviewPresenter.php` (exponer perfil/efectos)
- `app/Support/BattleSessionService.php` (`SESSION_VERSION` 12)

## Comportamientos a cubrir en el testing futuro (diferido)

### Unit

- `PerfilMovimiento::toArray/fromArray` conserva todos los campos y valores por defecto.
- `MovimientoBatalla::conPotencia` no muta el original y conserva perfil/estado/cambios.
- `CadenaDanio` devuelve 0 en inmunidad de tipo y mantiene el mínimo 1 en daño normal.
- `ManejadorCritico` no critica contra `InmuneCritico` y duplica probabilidad con `AjustaCritico`.
- `ManejadorSTAB` aplica STAB extra solo al tipo configurado.
- `EfectoModificadorDanio` aplica condiciones: vida baja, vida completa, estado del objetivo, tipo,
  clima y “una vez”.
- `EfectoInmunidadTipo` cura 25 % y aplica la subida de estadística.
- `EfectoCuracionFinTurno` desborda HP → escudo y respeta clima.
- `EfectoDanioFinTurno` daña solo a dormidos / escudos rivales.
- `EfectoContactoEstado` respeta probabilidad y no aplica sobre muertos.
- `ServicioEjecucionBatalla` multi-golpe: 4×25 %, 2×50 %, 3×33 %, y se detiene al debilitar.
- `ServicioEjecucionBatalla` aplica drenaje/recoil y respeta `InmuneARecoil`.
- `Combatiente::recibirDaño` con multiplicadores de escudo/salud devuelve el desglose correcto.
- Serialización de sesión: payload v11 carga con perfil/clima por defecto; v12 conserva todo.

### Feature

- Una batalla con Absorción Agua no recibe daño de un movimiento de agua y cura al portador.
- Una batalla con Enjambre al 33 % inflige ×1,5.
- Un movimiento de 4 golpes rompe escudo y desborda a HP igual que cuatro impactos reales.
- Recarga de sesión v11 → el combate sigue y las habilidades nuevas no rompen.

## Matriz de cobertura (estado tras esta iteración)

### Habilidades

| Grupo | Habilidades | Primitiva usada | Estado |
|---|---|---|---|
| Inmunidad + cura | Absorción Agua, Absorción Electricidad | `InmunidadTipo` + `EfectoInmunidadTipo` | LISTA |
| Inmunidad + boost | Absorción Fuego | `InmunidadTipo` + `EfectoBoostAbsorcion` | LISTA (atajo: consume el boost en el primer ataque con daño) |
| Redirección | Colector, Pararrayos | `RedireccionTipo` + `EfectoInmunidadTipo` | LISTA (el boost fijo a Def. Esp. queda pendiente de elegir el stat más alto) |
| Daño condicionado | Garra Dura, Armadura Batalla, Enjambre, Mar Llamas, Espesura, Crueldad, Acero Templado, Escamas | `ModificaDanioInfligido/Recibido` + `EfectoModificadorDanio` | LISTA |
| Daño por vida faltante | Depredador, Depredador Prehistórico, Malvado | `EfectoDanioPorVidaFaltante` | LISTA |
| Perforación de escudo | Sombra Traviesa | `EfectoPerforacionArmadura` | LISTA |
| Clima (stats) | Clorofila, Ímpetu Arena, Velo Agua, Depredador de Arena | `ModificaEstadistica` + `EfectoMultiplicadorEstadistica` | LISTA |
| Clima (daño) | Velo Arena | `EfectoModificadorDanio` ×3 tipos | LISTA |
| Invocadores | Sequía, Llovizna, Granizo, Tormenta Arena | `EfectoInvocadorClima` | LISTA |
| Curación fin de turno | Regeneración, Síntesis, Bendición, Protector, Campo Magnético, Fotosíntesis, Manto Helado | `EfectoCuracionFinTurno` | LISTA |
| Daño fin de turno | Mal Sueño, Presión, Flor Aromática | `EfectoDanioFinTurno` / `EfectoFlorAromatica` | LISTA |
| Contacto | Electricidad Estática, Punto Tóxico, Cuerpo Llama, Efecto Espora, Baba Tóxica, Enredaderas | `ReaccionaAlContacto` | LISTA (Baba Tóxica no apila veneno) |
| Reacciones | Bromista, Cabeza Roca, Detonación, Muro Mágico, Predicción, Intimidación | capacidades específicas | LISTA |
| Pasivas | Flexibilidad, Depredador Marino, Veleta, Actualización Software, Hipnotizador, Ira, Espinas, Piel Seca, Evasión, Alma Dragón, Antídoto, Vacío, Guardián de los Mares | capacidades específicas | LISTA |
| Estado del equipo | Refuerzos, Dormilón, Comealmas, Pantallista, Meditación | requieren estado de equipo/clon | PENDIENTE |
| Sin definición | Caparazón, Torrente, Ira Gyarados | — | AMBIGUA |
| No modeladas aún | Demoledor / Mandíbula Hidráulica, Cañones, Divide Dolor, Cuádruple/Doble/Triple Ataque | requieren multiplicador de escudo o multi-golpe de habilidad | PENDIENTE |

### Movimientos

| Grupo | Movimientos | Primitiva | Estado |
|---|---|---|---|
| Multi-golpe | Doble Ataque, Triple Ataque, Cuádruple Ataque, Tenaza | `PerfilMovimiento::golpes` | LISTA (perfil) |
| Estado con probabilidad | Arenas Ardientes, Agua Fría, Llama Embrujada, Poder Pasado | `probabilidadEstado` + cambios | LISTA (perfil) |
| Daño directo | Puño Sombra | `danoDirectoFraccion` | LISTA |
| Escudo/salud | Foco Resplandor, Puño Meteoro | `multiplicadorDanoEscudo/Salud` | LISTA (decisión de excedente pendiente) |
| Drenaje/recoil | Drenadoras, Testarazo, Vida Sueño | `drenajeFraccion`/`recoilFraccion` | LISTA (perfil); Drenadoras como efecto de campo, PENDIENTE |
| Coste de velocidad | Velocidad Extrema | `costeVelocidadFraccion` | LISTA (consumo en turnos, PENDIENTE) |
| Daño diferido | Maldición, Canto Mortal, Tenaza | `ClaveEfectoMovimiento` + ejecutor | PENDIENTE |
| Campo/equipo | Puas, Puas Tóxicas, Trampa Rocas, Pantalla de Luz, Reflejo, Desafío, Marioneta Inerte | `ClaveEfectoMovimiento` + ejecutor | PENDIENTE |
| Reacción | Represión Metal, Pico Taladro | `ClaveEfectoMovimiento` | PENDIENTE |
| Sin descripción | Carambano, Cuerno Certero, Desenrrollar, Envite Ígneo, Rizo Defensa, Testarazo, Onda Mental, Hilo Venenoso | — | AMBIGUA |

## Decisiones pendientes

1. Fórmula exacta de Pantalla de Luz / Reflejo (potencia 70 ×5 sobre def/def.esp).
2. Excedente del escudo roto en Foco Resplandor / Puño Meteoro.
3. Lista cerrada de movimientos “cañón/lanzar” para Cañones.
4. Porcentajes de Pico Taladro y de Represión Metal (si ignora escudos).
5. `Caparazón`, `Torrente`, `Ira Gyarados`: sin texto de efecto.
6. Si `Dormilón`/`Refuerzos`/`Comealmas`/`Pantallista`/`Meditación` entran en la siguiente iteración.
7. Si el boost de Colector/Pararrayos elige At. Esp. o Def. Esp. según cuál sea mayor, o siempre Def. Esp.
8. Si el veneno de Baba Tóxica escala a veneno grave.

## Iteración 2 — Catálogo de movimientos (2026-10-22)

### Decisiones cerradas

- **PP no se almacena**: los movimientos no se consumen.
- **`accuracy` nullable con default 100**: `100` = acierta siempre; `NULL` = nunca falla
  (se normaliza a precisión 1.0 en `PerfilMovimiento`).
- Se incluyen ya `target`, `flags`, `secondaries`, `self_effects` y `data` para no migrar
  cuando se implementen Drenadoras, Tenaza, Cañones o los efectos de campo.
- La asignación Pokémon → movimientos queda para otra tarea.

### Tabla `moves`

Columnas: `id`, `slug` (unique), `num` (nullable), `name`, `description` (nullable), `type_id`,
`category`, `base_power`, `accuracy` (nullable, default 100), `priority`, `target`, `crit_ratio`,
`will_crit`, `status_effect` (nullable), `status_chance`, `multihit_min`, `multihit_max`,
`multiaccuracy`, `drain_percent`, `recoil_percent`, `heal_percent`, `effect_key` (nullable),
`flags` json, `secondaries` json, `self_effects` json, `data` json, `timestamps`.

### Entidades/VOs

- `Movimiento` (catálogo, dominio): datos tipados + `aMovimientoBatalla()`.
- `ObjetivoMovimiento` (enum): `normal`, `self`, `allAdjacentFoes`, `allAdjacent`, `allySide`,
  `foeSide`, `all`, `scripted`, `randomNormal`, `any`, `adjacentAlly`, `adjacentAllyOrSelf`,
  `allies`, `allyTeam`.
- `FlagMovimiento` + `FlagsMovimientoCollection`: `contact`, `protect`, `mirror`, `punch`,
  `sound`, `bullet`, `pulse`, `bite`, `slicing`, `wind`, `dance`, `powder`, `heal`, `charge`,
  `recharge`, `futuremove`, `metronome`, etc.
- `GolpesMovimiento` (mín/máx; fijo o aleatorio).
- `EfectoSecundario` + colección: probabilidad, estado, cambios de stats, `esAuto`.
- `MovimientoFactory` (Infra): fila `Move` → `Movimiento`.
- `MovimientoRepository` + `EloquentMovimientoRepository`; excepción `MovimientoNoExiste`.

### Comportamientos a cubrir en testing futuro (unit)

- `Movimiento::aMovimientoBatalla()` conserva tipo, categoría, potencia, prioridad, estado y perfil.
- `accuracy=100` → precisión 1.0; `accuracy=NULL` → precisión 1.0; `accuracy=95` → 0.95.
- `multihit_min == multihit_max` → golpes fijos; rango → `GolpesMovimiento` conserva ambos.
- `flags.contact` → `PerfilMovimiento::esContacto = true`.
- `secondaries` con `chance=30` se conserva y no se aplica como cambio incondicional.
- `secondaries` con `chance=100` y `boosts` se traduce a `targetStatChanges`/`selfStatChanges`.
- `status_effect` inválido o desconocido → error de dominio.
- `effect_key` desconocido → `null` (se reporta como pendiente, no rompe la carga).



### Resultado de la carga (2026-10-22)

- `storage/data/moves.json` generado con **64 movimientos**: 30 de `ataques.txt` + 34 estándar
  adicionales usados en `poke habilidades.csv` (mapeados desde Showdown).
- `MoveSeeder` recorre el JSON y hace `updateOrCreate` por `slug`; ejecutado: **64 filas** en
  `moves`.
- `Pantalla de luz / Reflejo` se separa en dos filas (`pantalla_de_luz`, `reflejo`) con la
  descripción combinada y los datos de duración/capacidad.
- Foco Resplandor y Puño Meteoro comparten `data.multiplicador_dano_escudo=2.0` y
  `data.multiplicador_dano_salud=0.5`; no necesitan ejecutor propio.
- Tenaza guarda `distribucion_golpes` (37,5/37,5/12,5/12,5) en `data` para el futuro ejecutor.
- Los movimientos con lógica de campo/estado (`brecha_negra`, `canto_mortal`, `drenadoras`,
  `maldicion`, `puas`, `puas_toxicas`, `trampa_rocas`, `desafio`, `marioneta_inerte`,
  `pantalla_luz`, `reflejo`, `represion_metal`, `persecucion`, `pico_taladro`, `tenaza`,
  `velocidad_extrema`, `poder_pasado`, `superdiente`) llevan `effect_key`; el ejecutor se
  implementará en la siguiente iteración.

### Bloque 2 — Temporales y campo (2026-10-22)

- `MultiplicadoresStats` ahora tiene una sola instancia con capa `base` y capa `temporales`
  (5 turnos compartidos); el total se limita a 25 %–400 %. Los stacks no tienen tope, pero el
  multiplicador final se clampea a ×4.
- Los cambios de stats de los movimientos pasan a temporales: +30 % por etapa positiva y -30 %
  por negativa, con duración de 5 turnos (Rizo Defensa ya aplica ×1,30 durante 5 turnos).
- Veneno: 1/32 del HP máximo por carga, máximo 8. `Hilo Venenoso` aplica 2 cargas; el veneno
  grave (`BAD_POISON`) mantiene su contador/16.
- Ataques de fila: `FILA_VANGUARDIA`/`FILA_RETAGUARDIA` golpean a todos los vivos de esa
  posición con daño completo.
- Trick Room (5 turnos, invierte velocidad) y Tailwind (4 turnos, ×2 velocidad) implementados
  como movimientos `trick_room` y `tailwind` (66 movimientos en catálogo).
- Las inmunidades de clima siguen resolviéndose con `EfectoInmuneClima` + `CalculadorDañoClima`.
- Sesión v16.



### Bloque 4 — Combo, marca, vínculo, revive e invocaciones (2026-10-22)

- **Limit Break eliminado (2026-10-24)**: se retiraron el medidor, el movimiento `estallido_limite`,
  el flag `requiereLimitBreak`, la inyección universal del mapper y toda la UI asociada. En su
  lugar se añadieron **Explosión** (250 potencia, toda la fila del objetivo, media defensa,
  autodestrucción) y **Autodestrucción** (200 potencia, mismas reglas).
- **Combo/chain**: `Combatiente::combo` solo se activa en movimientos con
  `PerfilMovimiento::$potenciaCreciente` (`data.potencia_creciente`, p. ej. Bola Hielo,
  Desenrrollar). `ManejadorCombo` aplica `1 + min(combo, 5) × 0.10` únicamente si se repite el
  mismo movimiento; cambiar de movimiento, fallar, quedar bloqueado, usar un movimiento de estado,
  cambiar a Guardia/Cubrir o recibir interrupción/aturdimiento/recarga rompe la racha. No se rompe
  al recibir daño. Se muestra como badge y en la preview por movimiento.
- **Marca/hunt**: `ClaveCondicion::MARCADO` + `ManejadorMarcado` amplifican el daño recibido
  (`multiplicadorDanoRecibido`, por defecto ×1.3) durante N turnos. `Hunt` se normaliza a la misma
  condición; el origen queda en `origenId` para futuras reglas de cazador.
- **Vínculo de daño**: `ClaveCondicion::VINCULO_DANO` guarda `idVinculo` y
  `fraccionDanioCompartido`. `ServicioEjecucionBatalla::repartirVinculoDano()` transfiere esa
  fracción al aliado vinculado (máx. 50 %), registra el daño y dispara sus reacciones completas
  (`Undying`, `Espinas`, `Ira`, etc.) y el observer; el vínculo no se acumula y se refresca. La
  condición se coloca con `EjecutorVinculoDano` sobre los aliados vivos y el daño compartido no
  vuelve a activar otro vínculo.
- **Revive**: `EjecutorRevivir` revive al primer aliado debilitado con
  `fraccionRevivir` (50 % por defecto), limpia estados/condiciones/recursos y no resucita
  invocaciones temporales.
- **Invocaciones/pets**: `AgregadoBatalla::invocarCombatiente()` clona al usuario, limpia estados,
  objetos, cargas y medidores, aplica HP fraccional (35 % por defecto), posición y clima, y la
  inserta con `incorporarCombatiente()`. `turnosInvocacion` se descuenta en
  `triggerRoundEndEffects()` y la invocación se retira al caducar o morir. Límite configurable por
  movimiento (`invocacion_max`, por defecto 1).
- **Catálogo**: se añadieron `marca_del_cazador`, `vinculo_dano`, `bendicion_revivir`,
  `invocar_esbirro`, `explosion` y `autodestruccion` a `storage/data/moves.json`.
  Explosión y Autodestrucción se asignan a especies concretas mediante
  `PokemonMoveSeeder::ASIGNACIONES_EXTRA`.
- **Persistencia**: sesión v19 (compatible v9+) con defaults para combo, invocación y
  contador de invocaciones. `EstadoPendiente` ya no conserva `BENDICION_REVIVIR`, `MARCADO` ni
  `VINCULO_DANO`.
- **Verificación**: `pint`, PHPStan L6 y `view:cache` en verde; pruebas de dominio manuales de
  combo, marca, vínculo, revive, invocación, ciclo de vida y compatibilidad legacy. PHPUnit sigue
  diferido por indicación expresa.

### Bloque 5 — Objetos, habilidades y jefes (2026-10-22)

- **Catálogo de objetos**: `CatalogoObjetos` define `life_orb`, `leftovers`, `focus_sash`,
  `sitrus_berry`, `lum_berry`, `choice_band`, `choice_specs` y `assault_vest`, con nombre,
  icono y cargas (`-1` pasivo, `1` consumible). `ClaveItem` incluye las mismas claves.
- **Estado de objeto en Combatiente**: encapsulado en `ValueObjects\EstadoObjeto` (clave, cargas,
  consumido, ronda de bloqueo) y expuesto con `itemActivo()`. Se pueden destruir, robar,
  intercambiar, bloquear (Embargo/Zona Mágica) y reciclar objetos; un objeto bloqueado no aplica
  efectos ni bonus de daño.
- **Efectos de objeto**: Orbe Vida y Restos comprueban `itemActivo()`; Banda Focus deja a 1 HP
  una vez; Baya Sitrus cura 25 % al bajar del 50 %; Baya Lum cura el estado al inicio de ronda;
  Cinta/Gafas Elección dan +30 % al daño físico/especial; Chaleco Asalto da ×1,5 Def. Esp.
- **Interacción data-driven**: `PerfilMovimiento` incorpora `modoObjeto`, `turnosObjeto`,
  `modoHabilidad`, `turnosHabilidad`, `fraccionHpSustituto`, `turnosSustituto`,
  `tipoOverride`, `turnosTipo`, `potenciaCreciente` y `turnosMaximosPotencia`. Los ejecutores
  `interaccion_objeto`, `interaccion_habilidad`, `sustituto` y `cambio_tipo` aplican la regla sin
  una clase por ataque.
- **Choice items**: Cinta/Gafas Elección bloquean el movimiento usado; `movimientoDisponible()`
  devuelve `objeto_eleccion` para los demás y la UI lo muestra. Robar, destruir, intercambiar o
  reciclar el objeto limpia el bloqueo.
- **Movimientos añadidos**: `desarme`, `ladron`, `truco`, `embargo`, `zona_magica`, `reciclaje`,
  `jugo_gastrico`, `intercambio`, `imitacion`, `sustituto` y `camuflaje` (catálogo: 83 filas).
  `PokemonMoveSeeder` asigna además un mapa explícito del Bloque 5 (Meowth, Persian, Kadabra,
  Alakazam, Gengar, Mr. Mime, Ekans, Arbok, Bellsprout, Staryu/Starmie, Porygon, Umbreon,
  Murkrow y Sneasel). Resultado actual: **76 especies, 109 relaciones**.
- **Registro real de efectos**: `CreadorBatallaSesion` y `CombateExploracion` reciben la
  `FabricaEfectos` del contenedor y la pasan a `EquipoBatalla::fromData()`; sin esto los objetos y
  habilidades del catálogo se construían sin efectos en las batallas reales.
- **Habilidades**: `App\Models\Ability` + pivote `ability_pokemon`; `AbilitySeeder` carga 86
  habilidades desde los CSV y `PokemonAbilitySeeder` asigna 388 relaciones. `MapeadorPokemonBatalla`
  carga `pokemon.abilities`, traduce sus `data.effect_keys` y las inyecta en `DatosPokemonBatalla`.
  Si la especie tiene movimientos ofensivos en `pokemon_moves`, el mapeador usa el catálogo real;
  si no hay asignaciones (o solo hay estados), añade el fallback sintético por tipos para evitar
  combates sin ataques.
- **Habilidades completadas en esta iteración**: Danza Lunar (cura 1/6 con excedente a escudo),
  Hypnotizador (alias corregido), Ira Gyarados, Caparazón (inmune a crítico), Mandíbula
  Hidráulica (+50 % a movimientos de mordisco), Torrente/Enjambre/Mar Llamas/Espesura
  (potenciación por tipo al 33 % de HP), Doble/Triple/Cuádruple Ataque (2/3/4 golpes con
  50/33/25 % de potencia), Pantallista (duplica pantallas), Refuerzos (clon + buffs),
  Dormilón (duerme al 33 %, cura 50 % y ronca a todos), Meditación (duplica los cambios de
  estadísticas de movimientos de estado), Comealmas (escudos al caer un rival), Divide Dolor
  (daño repartido entre ambos escudos) y Gas Reactivo (anula habilidades rivales mientras vive).
  Todas las claves de habilidad del CSV tienen un efecto registrado.
- **Anular/copiar/intercambiar/ignorar**: `Combatiente::anularHabilidad()` retira los efectos de
  habilidad y los guarda para restaurarlos; `copiarHabilidadDe()` e `intercambiarHabilidadesCon()`
  clonan/intercambian solo efectos de habilidad (nunca objetos). `EfectoIgnoraHabilidad`
  (Rompemoldes) hace que atacante ignore inmunidades y modificadores defensivos del rival.
- **Sustituto y tipo**: `ClaveCondicion::SUSTITUTO` guarda PS propios, cuesta un 25 % del HP
  máximo y absorbe daño antes de las barreras/HP. `ClaveCondicion::TIPO_ALTERADO` permite cambiar
  el tipo defensivo temporalmente;
  la efectividad, STAB, clima y cálculos de inmunidad usan `tiposDefensivos()`.
- **Jefes**: `DatosPokemonBatalla` acepta `esJefe`, `fasesJefe` y `puntosDebiles`; `Combatiente`
  crea un `JefeEstado` con fases por umbral (75/50/25 %), enrage, multiplicadores de daño/velocidad
  y partes con HP. El daño al jefe activa fases y rompe la primera parte no rota; al romperse,
  el jefe queda `VULNERABLE` (×1.25 durante 3 turnos). `GeneradorJefeMazmorra` configura el jefe
  con dos partes y tres fases, y conserva las habilidades del Pokémon.
- **UI**: la tarjeta de combatiente muestra objeto/cargas/bloqueo, habilidad anulada, fase de jefe,
  Enrage y PS de cada parte. En Equipo hay selector de objeto por miembro con guardado inmediato
  (`PATCH /teams/{team}/items`); el detalle permite configurar el **loadout**: hasta 4 movimientos
  del catálogo de la especie y una habilidad activa (`PATCH /reclutado/{reclutado}/loadout`).
  El modal de detalle es ancho (`max-w-6xl`): tipos en el header; izquierda movimientos/habilidades
  con descripciones y loadout; derecha chart hexagonal de stats base con Chart.js en un canvas de
  270×220 px, barras de EV e IVs. En combate la postura se muestra como badge en la tarjeta y como
  resumen de solo lectura en la preparación; no se configura allí (se sigue configurando en Equipo).
- **Loadout por reclutado**: `reclutados.movimientos` guarda slugs seleccionados y
  `reclutados.habilidad_activa_id` la habilidad activa. `MapeadorPokemonBatalla` recibe ambos y
  `ConstruirEquipoJugador`/`CombateExploracion` los aplican; sin selección se usa el catálogo
  completo de la especie + fallback.
- **Persistencia**: sesión v20; los payloads v9-v19 cargan con defaults (objeto pasivo, sin
  supresión, sin sustituto y sin jefe).
- **Refactor**: `EstadoObjeto`, `EstadoHabilidad`, `EstadoTurno`, `EstadoRecursos` y
  `EstadoMovimientos` encapsulan objeto, supresión, turno/postura/prioridad, combo y
  cargas/usos. `CombatienteVista` genera el array de UI; `ResolutorObjetivosDanio`,
  `AplicadorPosturaDanio`, `AplicadorDanioObjetivo`, `RepartidorVinculoDanio` y
  `GestorEfectosMovimiento` dividen la ejecución de daño/efectos. `ActualizarObjetosEquipo`
  concentra el guardado de objetos y `MapeadorPokemonBatalla::movimientosDeConFallback()` unifica
  el catálogo real + fallback sintético. `Combatiente` baja a ~1.772 líneas y
  `ServicioEjecucionBatalla` a ~369; sigue pendiente separar la serialización y el estado de
  vida/estados.
- **Verificación**: `pint`, PHPStan L6 y `view:cache` en verde; script manual
  `/tmp/opencode/block5_check.php` con 54 comprobaciones (objetos, habilidades, sustituto, tipo,
  multi-golpe, multiobjetivo, Refuerzos, Marioneta Inerte, Comealmas, Divide Dolor, Gas Reactivo,
  Choice, combo por movimiento, cadena de daño, servicio y jefe) sin fallos; batalla automática
  completa y round-trip de serialización verificados. PHPUnit sigue diferido por indicación expresa.

### Bloque 3 — Carga, control y ritmo (2026-10-22)

- Carga de movimiento: solo para perfiles con `cargaMaxima` (por defecto `data.carga` o flag
  `charge`). Empieza en 0/3, avanza `cargaPorRonda` (1) al inicio de cada ronda y se reinicia a 0
  al usar el movimiento. El movimiento no consume la acción mientras se carga.
- Usos limitados: `PerfilMovimiento::$usosMaximos` (desde `data.usos_maximos`/`data.usos`) y
  contador por combatiente. Un movimiento con 0 usos no se puede seleccionar; el uso se descuenta
  al resolverlo, acierte o falle.
- Recarga: `data.recarga` o flag `recharge` añade la condición `RECARGA`; el portador pierde su
  siguiente acción. Aplica a `Distorsión` y `Romperrocas`.
- Retrasar turno: una vez por ronda, mueve la acción al final de la ronda sin consumirla. La IA y
  los efectos pueden seguir actuando; el retraso se limpia al consumir la acción.
- Silencio: condición que bloquea movimientos de estado (`potencia <= 0` o categoría estado)
  durante N turnos; no bloquea ataques.
- Interrupción: condición de un solo uso que cancela la prioridad declarada y todas las cargas
  del objetivo; su siguiente intento de actuar se consume.
- Aturdimiento / guard break: rompe la postura (vuelve a Atacar), anula prioridad y hace perder
  la siguiente acción. `rompeGuardia` se activa con `data.rompe_guardia` o `data.rompe_proteccion`
  (Golpe Umbrío). La reducción de Guardia se aplica antes de romperla.
- `ClaveCondicion` incorpora `SILENCIO`, `INTERRUPCION`, `ATURDIDO` y `RECARGA`; los antiguos
  pendientes equivalentes se retiraron de `EstadoPendiente`. Sesión v18 (compatible v9+).



### Bloque 1 — Preparación y postura (2026-10-22)

- `teams.posturas` (JSON por slot) + `PATCH /teams/{team}/posturas`; `ConstruirEquipoJugador`
  lee la postura del slot y la pasa a `DatosPokemonBatalla`/`Combatiente`.
- `PosturaCombate` (`atacar`, `guardia`, `cubrir`) y `FaseCombate::PREPARACION`.
- La postura es opcional y se configura una sola vez en la pantalla de equipo; la preparación de
  ronda ya no la vuelve a pedir. Si no hay prioridades disponibles, la preparación se confirma
  automáticamente; si las hay, el panel solo permite elegir el movimiento prioritario.
- `EquipoBatalla::normalizarCubridores()` reasigna automáticamente el aliado cubierto si el
  objetivo original muere o deja de ser válido (primer aliado vivo por defecto).
- Guardia: 50 % de reducción y parada perfecta con techo 25 % (Def/Def. Esp vs At/At. Esp).
- Cubrir: 1 cubridor 40/60; 2 cubridores 40/40/20. Máximo 2 por objetivo.
- `EfectoContraataque` (habilidad/buff): responde con potencia 30 física / 40 especial; se cancela
  si el portador muere; `Undying` lo mantendría vivo para contraatacar.
- Acción extra: al final de la ronda, máximo 1 por ronda; `GestorTurnos` resuelve primero todas
  las acciones normales y luego las extras.
- Selector de postura por miembro en la pantalla de equipo: guarda en `teams.posturas` mediante
  `PATCH /teams/{team}/posturas` y lo consume `ConstruirEquipoJugador` al crear la batalla.
- Prioridad declarada en preparación: el panel muestra los movimientos con `priority > 0` por
  Pokémon; al confirmar se ejecutan antes de las acciones normales y consumen la acción. La IA
  declara su mejor prioridad y también la usa si el jugador salta la preparación.
- `Undying`: `EfectoUndying` deja al portador a 1 HP una vez por batalla antes del contraataque;
  aliases `robustez`/`sturdy` comparten el efecto.



### Ejecución de efectos y condiciones (2026-10-22)

- `MovimientoBatalla` incorpora `objetivo`, `flags`, `secundarios` y `autoEfectos` (sesión v14).
- `ServicioEjecucionBatalla` aplica `secondaries` con probabilidad, estados, cambios y condiciones
  volátiles; también resuelve drenaje/recoil y coste de velocidad.
- Nuevas condiciones volátiles en `Combatiente` (`CondicionVolatil` + `ColeccionCondiciones`):
  confusión, drenadoras, maldición, canto mortal, desafío, atrapado y retroceso. Permiten
  coexistir con el estado mayor (p. ej. quemadura + confusión).
- Residuales al final de ronda: drenadoras (roba HP al objetivo y cura al origen), maldición
  (1/16), canto mortal (3 turnos), atrapado (1/8) y desafío/retroceso.
- Efectos de equipo: `EquipoBatalla` tiene `ColeccionEfectosEquipo`; `EfectoEquipoTrampa` (Puas,
  Puas Tóxicas, Trampa Rocas) y `EfectoEquipoPantalla` (Pantalla de Luz / Reflejo). La cadena
  incluye `ManejadorEquipo` para reducir daño con pantallas.
- Ejecutores por `effect_key`: Drenadoras, Maldición, Canto Mortal, Desafío, Tenaza, Brecha Negra,
  Represión Metal, Superdiente, Persecución, Puas/Puas Tóxicas/Trampa Rocas, Pantalla/Reflejo y
  Marioneta Inerte (reactiva 3 turnos a un aliado derribado).
- El selector IA respeta `objetivo = self` y el objetivo forzado por `Desafío`.
- `Cañones` usa `flags` (punch/pulse/bullet) para el +25 % de daño y además +25 % a barreras
  mediante `EfectoModificadorDanioEscudo`.
- Velocidad Extrema aplica un coste del 25 % a la velocidad efectiva de la ronda siguiente.
- **Implementado en fase 1**: prioridad de movimiento declarada en preparación y resuelta antes
  de las acciones normales (bracket de prioridad y velocidad; Trick Room invierte el desempate).
- **Implementado en esta iteración**: `allAdjacentFoes`, `allAdjacent` y `all` reparten daño entre
  todos los objetivos vivos; `Pico Taladro` lleva `multiplicador_dano_escudo=1.5`; Cañones suma
  +25 % a barreras; Marioneta Inerte ya reactiva y expira. Sigue pendiente la prioridad general
  fuera de la preparación.



### Relaciones Pokémon ↔ movimientos (2026-10-22)

- Migración `2026_10_22_000002_create_pokemon_moves_table.php`: pivote `pokemon_moves`
  (`pokemon_id`, `move_id`, `slot` 1-4, `source`), únicos por `(pokemon_id, slot)` y
  `(pokemon_id, move_id)`.
- `Pokemon::moves()` y `Move::pokemons()` (`BelongsToMany`, orden por `slot`).
- `PokemonMoveSeeder` lee `poke habilidades.csv`, valida TODOS los nombres antes de escribir y
  añade el mapa del Bloque 5. Resultado: **76 Pokémon, 109 relaciones**.
- `MapeadorPokemonBatalla` usa esos movimientos reales cuando hay al menos un movimiento ofensivo;
  si la especie no tiene asignaciones (o solo estados) añade el fallback sintético por tipos. Así
  el catálogo influye en la batalla sin dejar especies sin ataques.
- `Pokemon::abilities()` + `Ability`/`ability_pokemon` (`BelongsToMany` por `slot`):
  `AbilitySeeder` registra **86** habilidades y `PokemonAbilitySeeder` **388** relaciones.
  El mapeador inyecta sus claves de efecto en `effectKeys`.
- `Patada Salto Alto` se trata como alias de `Patada Salto Alta`; `Desenrrollar` se mapea a
  Rollout.



## Fuera de alcance (iteraciones siguientes)

- Prioridad general de movimiento fuera de la preparación.
- UI de administración del catálogo de movimientos/habilidades (la selección por reclutado ya existe).
- Configuración de jefes por hábitat (fases/partes por piso) en lugar de la plantilla fija.
- Objetos adicionales consumibles y efectos de formas.
- Implementar una clase por habilidad/ataque.
- Testing (se difiere al cierre del módulo, según regla del proyecto).

---

# Anexo — Análisis previo (tooling de calidad, cerrado)

# Análisis previo — Tooling de calidad: PHPStan 2.x, deptrac 4, hooks y scripts

## Objetivo

Sanear el pendiente 17 (PHPStan/deptrac/baselines/CRAP) y dejarlo operativo en el flujo
local: configuración válida de PHPStan 2.2.9 con baselines que congele la deuda preexistente,
migración del shim abandonado `qossmic/deptrac-shim` a `deptrac/deptrac` 4, hooks Git de
pre-commit/pre-push y scripts `composer` de calidad. Sin tocar código de `app/` ni `src/`,
sin ejecutar PHPUnit (testing diferido) y sin tocar el WIP de otra sesión.

## Diagnóstico (Fase 1)

### 1. Versión de PHPStan

```text
$ vendor/bin/phpstan --version
PHPStan - PHP Static Analysis Tool 2.2.9        # exit 0
```

Tras `composer require --dev deptrac/deptrac:^4.0 --with-all-dependencies`, Composer elevó
transitivamente `phpstan/phpstan` a 2.3.1. Se restauró 2.2.9 con
`composer update phpstan/phpstan:2.2.9 --with-dependencies` (actualización dirigida, no general)
para no introducir un cambio de analizador no aprobado. Versión final verificada: **2.2.9**.

### 2. PHPStan L6 (antes)

```text
$ vendor/bin/phpstan analyse -c phpstan.neon --memory-limit=512M
[ERROR] Found 282 errors                       # exit 1
```

- **280 errores reales** (tipado, genéricos, llamadas dinámicas a Eloquent…).
- **2 errores de configuración muerta**: `Ignored error pattern ... was not matched` para los
  `ignoreErrors` con `path: tests/*`. `paths` solo contiene `src/` y `app/`, y además `tests/`
  está excluido: esos patrones nunca pueden casar.
- `excludePaths.analyse` incluye `tests/Unit/Infra (?)` y `tests/Feature/Infra (?)`:
  entradas muertas (subsumidas por `tests/`; el sufijo `(?)` solo evita el aviso de ruta
  inexistente).

### 3. PHPStan L8 (antes)

```text
$ vendor/bin/phpstan analyse -c phpstan-hardener.neon --memory-limit=512M
Invalid configuration:
Unexpected item 'parameters › checkMissingIterableValueType'.
Unexpected item 'parameters › checkGenericClassInNonGenericObjectType'.
Unexpected item 'parameters › checkAlwaysTrueInstanceof'.
Unexpected item 'parameters › checkAlwaysTrueStrictComparison'.
                                                    # exit 1
```

PHPStan 2.x eliminó esos cuatro parámetros (los comportamientos asociados pasan a estar
siempre activos). `treatPhpDocTypesAsCertain` y `reportUnmatchedIgnoredErrors` siguen siendo
válidos en 2.x.

### 4. deptrac con el shim (antes)

```text
$ vendor/bin/deptrac --version
deptrac 1.0.2                                   # exit 0

$ vendor/bin/deptrac analyse
Syntax Error on File .../src/Battle/Domain/ValueObjects/MultiplicadoresStats.php
Syntax error, unexpected T_STRING, expecting '=' on line 22
Report: Violations 0 · Uncovered 0 · Allowed 0  # exit 0
```

El shim no parsea constantes tipadas (`public const float`). El «0 violaciones» era un falso
verde: `exclude_files` vaciaba el análisis (`src/*/Infra/*`, `app/Http/*`, `app/Console/*`,
`app/Exceptions/*`, `app/Providers/*`…).

### 5. Diagnóstico de capas vs. realidad

- `src/<Modulo>/Domain/` en todos los módulos.
- `src/<Modulo>/App/` + `Infra/` (canónico actual) y `src/Battle/Infrastructure/` +
  `Infrastructure/`/`Presentation/` (legado); `src/Exploraciones/`, `src/Habitats/` y
  `src/Battle/` conservan `Presentation/`.
- Legado Laravel en `app/`: `Http`, `Livewire`, `Console`, `View`; además `Models`, `Jobs`,
  `Support`, `Datagrid`, `Crud`, `Bus`, `Enums` (fuera de las capas DDD, quedan uncovered).
- `src/Shared/`: `Bus`, `Collections`, `Domain`, `Tipos`, `UI`; solo `Shared\Domain` cae en
  la capa Domain.

El `deptrac.yaml` previo no colectaba `App`/`Infrastructure` y excluía las capas reales, por
lo que no podía detectar nada.

### 6. CRAP y cobertura

```text
$ vendor/bin/phpunit --help | grep coverage-crap4j
  --coverage-crap4j <file>             Write code coverage report in Crap4J XML format to file
```

PHPUnit 11.5.55 soporta `--coverage-crap4j`. El CRAP necesita cobertura, que pertenece a la
fase de testing diferida. Receta para documentar en el cierre (no ejecutada):

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-crap4j build/crap4j.xml
# requiere extensión de cobertura (Xdebug/PCOV) y <source> en phpunit.xml
```

## Diseño

- **PHPStan**: `phpstan-baseline.php` (L6) incluido desde `phpstan.neon` y
  `phpstan-hardener-baseline.php` (errores exclusivos de L8) incluido desde
  `phpstan-hardener.neon`, que a su vez incluye el anterior. Los baselines congelan la deuda:
  no se añaden entradas nuevas y se regeneran al reducirla.
- **deptrac 4**: `classNameRegex` (sustituye a `className`; usa `value` con regex PCRE
  delimitada) y reglas estrictas Domain → ∅, Application → Domain, Infrastructure →
  Domain+Application, Presentation → todas. `exclude_files` reducido a `tests/*` y `vendor/*`.
- **Hooks**: `.githooks/pre-commit` (whitespace + Pint sobre PHP staged, excluyendo los
  baselines generados) y `.githooks/pre-push` (`composer run analyse`), POSIX sh y ejecutables.
- **Scripts composer**: `analyse`, `analyse:hard`, `arch`, `lint`, `hooks:install`, `check`.
  `check` no incluye `@arch` mientras deptrac tenga violaciones preexistentes.

## Resultados de la implementación

```text
PHPStan L6: 282 errores (2 de config muerta) -> baseline 280 -> 279 tras limpiar WIP
PHPStan L8: 196 errores adicionales           -> baseline 196 (sin cambio tras limpiar WIP)
L6/L8 en worktree limpio: [OK] No errors                                     (exit 0)
deptrac 4.7.2: Violations 71 · Uncovered 1126 · Allowed 572 · 0 warnings     (exit 1)
Pint: baselines excluidos; 588 ficheros PASS                                 (exit 0)
composer lint: PASS                                                          (exit 0)
Árbol actual con WIP: 1 error esperado en PlayerController:126 (L6 y L8)     (exit 1)
```

### Inventario deptrac completo (71 violaciones)

Las 71 violaciones tienen destino `Presentation`. El desglose anterior (32+17+16=65) era
incompleto: omitía 6 dependencias de controladores de módulo hacia el `Controller` base de
Laravel (`App\Http\Controllers\Controller`), que el colector clasifica como `Presentation`
(legado). Pares de capas reales:

| Origen → destino | Nº |
|---|---:|
| Infrastructure → Presentation | 38 |
| Application → Presentation | 17 |
| Domain → Presentation | 16 |
| **Total** | **71** |

Tokens destino:

| Token destino | Nº |
|---|---:|
| `Src\Exploraciones\Presentation\DTOObjetoPerdido` | 7 |
| `App\Http\Controllers\Controller` (base Laravel) | 6 |
| `Src\Habitats\Presentation\DTOFamiliaDisponible` | 6 |
| `Src\Habitats\Presentation\DTOFamiliasDisponibles` | 6 |
| `Src\Habitats\Presentation\DTOHabitatDetalle` | 6 |
| `Src\Habitats\Presentation\DTOPokemonFamilia` | 6 |
| `Src\Habitats\Presentation\DTOFamiliaEliminada` | 5 |
| `Src\Habitats\Presentation\DTOPokemonNivelActualizado` | 5 |
| `Src\Habitats\Presentation\DTOFamiliasSinHabitat` | 5 |
| `Src\Battle\Presentation\DTOAccionBatalla` | 3 |
| `Src\Exploraciones\Presentation\DTOIncidentesExploracion` | 3 |
| `Src\Habitats\Presentation\ColeccionPokemonFamilia` | 3 |
| `Src\Habitats\Presentation\DTOPokemonTipo` | 3 |
| `Src\Battle\Presentation\DTOResultadoDanio` | 2 |
| `Src\Habitats\Presentation\DTOFamiliaSinHabitat` | 2 |
| `Src\Habitats\Presentation\ColeccionTiposFamilia` | 2 |
| `Src\Exploraciones\Presentation\TransformadorResultadoExploracion` | 1 |
| **Total** | **71** |

Ficheros origen (18):

| Fichero origen | Capas | Nº |
|---|---|---:|
| `src/Habitats/Infra/HabitatRepository.php` | Infra → Presentation | 32 |
| `src/Exploraciones/App/FinalizarExploracionHandler.php` | App → Presentation | 11 |
| `src/Habitats/Domain/Repositories/HabitatRepositoryInterface.php` | Domain → Presentation | 6 |
| `src/Habitats/Domain/ResolvedorCadenasEvolutivas.php` | Domain → Presentation | 5 |
| `src/Battle/Domain/AgregadoBatalla.php` | Domain → Presentation | 3 |
| `src/Battle/Domain/ServicioEjecucionBatalla.php` | Domain → Presentation | 2 |
| `src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php` | Infra → Presentation | 1 |
| `src/CombateRuta/Infra/Controllers/CombateRutaController.php` | Infra → Presentation | 1 |
| `src/Gimnasios/Infra/Controllers/AdminGymController.php` | Infra → Presentation | 1 |
| `src/Gimnasios/Infra/Controllers/GimnasioController.php` | Infra → Presentation | 1 |
| `src/Habitats/App/AsignarFamiliaAHabitat.php` | App → Presentation | 1 |
| `src/Habitats/App/EliminarFamiliaDeHabitat.php` | App → Presentation | 1 |
| `src/Habitats/App/MoverPokemonDeNivel.php` | App → Presentation | 1 |
| `src/Habitats/App/ObtenerFamiliasDisponibles.php` | App → Presentation | 1 |
| `src/Habitats/App/ObtenerFamiliasSinHabitat.php` | App → Presentation | 1 |
| `src/Habitats/App/ObtenerHabitatDetalle.php` | App → Presentation | 1 |
| `src/Huevos/Infra/Controllers/HuevoController.php` | Infra → Presentation | 1 |
| `src/Mazmorras/Infra/Controllers/MazmorraController.php` | Infra → Presentation | 1 |

Los 6 controladores de módulo (`Entrenador`, `CombateRuta`, `AdminGym`, `Gimnasio`, `Huevo`,
`Mazmorra`) extienden el base `App\Http\Controllers\Controller`. Confirma el pendiente 12
(`AgregadoBatalla` → `DTOAccionBatalla`, 3 usos) y amplía la deuda a `Habitats` y
`Exploraciones`. No se arregla (fuera de alcance); por eso deptrac no entra en hooks ni en
`composer check`.

### Iteración QA — baselines regenerados contra el árbol limpio

El baseline anterior se había generado con el WIP de `PlayerController.php` presente. Se
regeneró en un worktree detached de `4d657f5` (`/tmp/pokemon-baseline`), sin el WIP:

```text
$ git worktree add /tmp/pokemon-baseline 4d657f5                  # exit 0
$ composer install --no-scripts --no-interaction                  # exit 0
  (primer intento de PHPStan falló: Laravel no arrancaba sin .env; se copió .env del repo
   principal y se repitió)
$ vendor/bin/phpstan analyse -c phpstan.neon --generate-baseline=phpstan-baseline.php
[OK] Baseline generated with 279 errors                           # exit 0 (antes 280)
$ vendor/bin/phpstan analyse -c phpstan-hardener.neon --generate-baseline=...
[OK] Baseline generated with 196 errors                           # exit 0 (antes 196)
$ vendor/bin/phpstan analyse -c phpstan.neon
[OK] No errors                                                    # exit 0
$ vendor/bin/phpstan analyse -c phpstan-hardener.neon
[OK] No errors                                                    # exit 0
```

Diferencia entrada a entrada (comparando mensaje/path/count):

| Nivel | Antes | Después | Eliminadas | Añadidas |
|---|---:|---:|---:|---:|
| L6 | 280 errores (222 entradas) | 279 errores (221 entradas) | 1 | 0 |
| L8 | 196 errores (157 entradas) | 196 errores (157 entradas) | 0 | 0 |

La única entrada contaminada era de L6:
`PlayerController.php` → `Dynamic call to static method Guard::id()` (WIP). Las otras 3
entradas de `PlayerController` del baseline **no** eran WIP: `Guard::user()`,
`Builder<Reclutable>::orderBy()` y el nullsafe `?->name` existen en la versión commiteada
(`git show 4d657f5:app/Http/Controllers/PlayerController.php`, líneas 49, 54 y login). La
entrada de L8 (`SinergiaEquipo::sinergiaPara($roles)`) tampoco era WIP: la versión commiteada
ya llama a `sinergiaPara` en la línea 80. Por tanto: de las 4 entradas L6 de `PlayerController`,
**solo 1 era del WIP y se eliminó**; en L8 **no se eliminó ninguna**.

En el árbol de trabajo actual (con el WIP) el resultado esperado es:

```text
$ vendor/bin/phpstan analyse -c phpstan.neon
  app/Http/Controllers/PlayerController.php:126
  Dynamic call to static method Illuminate\Contracts\Auth\Guard::id().
[ERROR] Found 1 error                                             # exit 1
$ vendor/bin/phpstan analyse -c phpstan-hardener.neon
  (el mismo error) [ERROR] Found 1 error                          # exit 1
```

Es deuda del WIP hasta que aterrice; no se toca ni se excluye. El error esperado lo reporta
**PHPStan en el `pre-push`**; si bloquea un push puntual, el escape documentado es
`git push --no-verify` (el `pre-commit` — whitespace + Pint — no se ve afectado). Cuando el WIP
aterrice, regenerar baselines si cambian sus errores.
El worktree se eliminó (`git worktree remove --force` + `git worktree prune`).

## Hallazgos menores de la iteración QA

- **`.deptrac.cache` trackeado**: confirmado con `git ls-files .deptrac.cache`. Se destrackeó
  con `git rm --cached .deptrac.cache`; el fichero sigue en disco y ya estaba en `.gitignore`.
  Así `deptrac analyse` deja de ensuciar `git status`.
- **Pint en ficheros ajenos**: `database/seeders/CatalogoSeeder.php` (import del propio
  namespace sin uso) y `tests/Feature/HabitatsViewTest.php` (falta newline final) normalizados
  con Pint, sin cambios de lógica. `pint --test` global y `composer lint` quedan en verde
  (588 ficheros, exit 0).
- **Borde `pint.json notPath` + pre-commit**: al pasar ficheros explícitos, Pint no aplica
  `notPath`; el hook ahora filtra `phpstan-baseline.php` y `phpstan-hardener-baseline.php`
  antes de invocar `pint --test`, de modo que un stage de baselines regenerados no bloquea.
  `sh -n .githooks/pre-commit` OK.

## Comportamientos a cubrir en el testing futuro

No aplica lógica de aplicación. La verificación de esta tarea es la ejecución de las propias
herramientas (L6, L8, deptrac, Pint, hooks), ya realizada. En el cierre del módulo tocará:

- **Unit/Feature**: ninguno (testing PHPUnit diferido, no se han tocado tests).

## Riesgos

- El baseline congela 475 errores preexistentes (279 L6 + 196 L8). Cualquier error nuevo falla.
- El árbol actual tiene **1 error esperado** de PHPStan por el WIP de `PlayerController`
  (`Guard::id()` en línea 126); desaparecerá al aterrizar ese cambio. No se regenera el
  baseline hasta entonces.
- `composer check` (`@analyse` + `@lint`) queda verde en árbol limpio, pero en el árbol actual
  fallará por ese error WIP (esperado); `composer lint` ya es verde.
- `composer.lock` arrastra actualizaciones transitivas de Symfony/php-parser
  (`--with-all-dependencies`). `phpstan/phpstan` se restauró a 2.2.9 con actualización dirigida.
- deptrac deja 1126 tokens uncovered (legado `app/*` y `src/Shared/*`): las dependencias hacia
  esos tokens no se auditan todavía. Las 71 violaciones son deuda preexistente (pendiente 12 +
  DTOs de `Presentation` en `Habitats`/`Exploraciones` + base Controller).
