# TASK-071 — Petición

- **ID:** TASK-071
- **Fecha:** 2026-11-23
- **Proyecto:** `/home/david/pokemon`
- **Tipo:** rediseño de contenido temporal (retirada de torres + hábitats de facciones rivales)
- **Estado:** ANALYZING / DESIGNING (sin aprobación)

## Texto literal

> torres se va fuera, nunca debio existir. Deberia ser un habitat temporal condicionado al progreso. No tiene pokemon ni niveles, solo entrenadores de equipos rivales. En esta version solo plantearemos Team Rocket, Magma, Aqua, Galaxia y Skull.
>
> Conforme completemos medalla se desbloquearan estos habitats, se genera una provincia Temporal y estos habitats tendran un reborde segun contra quien te enfrentes, pondremos una imagen adecuada a cada uno. Cuando se ganen todos los combates el habitat desaparecera, si no hay mas, la provincia "Temporal" se oculta.
>
> Enfrentamos 3 veces, al inicio, mitad y sobre el final, vamos alternando enemigos. Debemos enfrentar a sus capitanes, Giovani, Aquiles, Guzman... Y equipos que suelan llevar estos equipos rivales. Es mas bien como un Gym pero en habitat

> Los combates contra lideres son los ultimos, antes sera contra reclutas y pokemon clasicos suyos

> Tendrian que tener mi nivel, Y las Oleadas, deberian ser 4 combates contra reclutas, o 3 reclutas y 1 jefe (No tiene porque ser el principal, hay secundarios)

> Mismo criterio de iv/ev que con gym y las tandas, al incio mas debiles, oleada 2 max iv solo en algunos y max iv/ev al final

## Decisiones confirmadas con el desarrollador

1. **Estructura:** cada facción tiene 3 oleadas repartidas a lo largo de la partida
   (inicio, mitad y final). Cada oleada tiene **4 combates**: 3 reclutas + 1 jefe.
2. **Jefes:** el 4.º combate de las oleadas 1 y 2 es un jefe secundario; el de la
   oleada 3 es el capitán principal. No todos los jefes tienen que ser el líder
   principal.
3. **Desbloqueo:** hitos de medallas más juntos, empezando en la 2.ª medalla.
   Propuesta del plan: Rocket 2, Magma 3, Aqua 4, Galaxia 5 y Skull 6.
4. **Nivel:** todos los rivales tienen exactamente el nivel del jugador. Sin bonus
   `+2/+4`.
5. **IV/EV:** mismo sistema que gimnasio (`TopUno`/`TopDos`/`Completo`):
   - Oleada 1: reclutas `TopUno`; jefe `TopDos`.
   - Oleada 2: reclutas `TopDos`; jefe `Completo`.
   - Oleada 3: reclutas `Completo`; capitán `Completo`.
6. **Recompensa:** EXP ×3 + caramelo del tipo insignia de la facción.
7. **Torres:** eliminar sin dejar rastro para instalaciones nuevas; no es necesario
   limpiar las tablas de la base de datos local existente.
8. **Total:** 12 combates por facción (3 oleadas × 4), 60 combates en total.

## Glosario de nombres canónicos

- Team Rocket — capitán Giovanni; jefes secundarios Proton y Ariana.
- Team Magma — capitán Magno; jefes secundarios Tabitha y Courtney.
- Team Aqua — capitán Aquiles; jefes secundarios Matt y Shelly.
- Team Galaxia — capitán Ciro; jefes secundarios Mars y Saturn.
- Team Skull — capitán Guzmán; jefe secundario Plumeria y un recluta de élite.

Se usan los nombres de la localización española. «Giovani», «Guzman» y «Galaxia»
del texto original se normalizan a «Giovanni», «Guzmán» y «Team Galaxia».
