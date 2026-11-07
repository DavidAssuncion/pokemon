# TASK-029 — Petición

- **Fecha:** 2026-11-07
- **Origen:** petición del desarrollador tras cerrar TASK-028 (verificación 3F).
- **Petición literal:**
  > algunos movimientos deben poder usarse sobre un compañero. Por ejemplo Bucle Arena, Giro Fuego o Torbellino sobre los rivales hacen daño porcentual, pero Bucle Arena debera restaurar el escudo fisico de un aaliado, Torbellino restaura la salud, como Acua Aro, y Giro Fuego restaura el escudo especial
  > Otros movimientos como Maquinacion, Paz Mental, Danza Espada, Danza Dragon, Recuperacion, Corpulencia tambien podra usarse sobre aliados para darles a ellos esos beneficios

## Interpretación inicial

Permitir que una selección de movimientos pueda dirigirse a un aliado. Al elegir aliado, el movimiento no aplica su efecto ofensivo habitual sobre el rival, sino un efecto beneficioso equivalente:

| Movimiento | Sobre rival (actual) | Sobre aliado (nuevo) |
|---|---|---|
| Bucle Arena | daño porcentual | restaura escudo físico |
| Giro Fuego | daño porcentual | restaura escudo especial |
| Torbellino | daño porcentual | restaura salud (tipo Acua Aro) |
| Maquinación | mejora propia | aplica la mejora al aliado |
| Paz Mental | mejora propia | aplica la mejora al aliado |
| Danza Espada | mejora propia | aplica la mejora al aliado |
| Danza Dragón | mejora propia | aplica la mejora al aliado |
| Recuperación | curación propia | cura al aliado |
| Corpulencia | mejora propia | aplica la mejora al aliado |

La lista no se considera cerrada: hay que identificar todos los movimientos con esta semántica y confirmar el alcance real.

## Contexto

- `app/Livewire/Combate.php` es una fachada de 149 líneas; la lógica vive en `app/Livewire/Combate/Soporte/` y en `src/Battle/`.
- TASK-028 (3F) confirmó que la fachada reproduce el comportamiento del componente original en 11 escenarios.
- El contrato público de `Combate` (32 propiedades y 18 métodos) está congelado salvo decisión explícita.
- `SESSION_VERSION = 21` y `BattleSessionService` no se tocan sin autorización.

## Preguntas abiertas para `analyst`

1. ¿El combate es 1v1, 2v2 o ambos? ¿Cómo se selecciona al aliado?
2. ¿La lista de movimientos es cerrada o se define por categoría/efecto?
3. ¿Qué son exactamente «escudo físico» y «escudo especial» en el dominio actual: stat, barrera, buff o escudo separado?
4. ¿Qué porcentajes de daño/curación y duraciones se aplican?
5. ¿El uso sobre aliado consume turno y PP igual que sobre rival?
6. ¿La IA puede usar estos movimientos sobre aliados?
7. ¿Cambia la UI de selección de objetivo (aliado/rival) y el JS de combate?
8. ¿Afecta a la serialización de sesión y obliga a subir `SESSION_VERSION`?
9. ¿Hay movimientos que ya funcionen sobre aliados y sirvan de precedente?

## Restricciones

- No implementar sin plan aprobado con versión explícita.
- No crear, modificar ni ejecutar tests.
- No `git push`.
- No cambiar dependencias, migraciones, rutas ni esquema sin autorización.
- No tocar `SESSION_VERSION` ni `BattleSessionService` sin decisión explícita.

## Requisito adicional (2026-11-07)

- **Texto literal:** «Deben tener duracion de 5 turnos».
- **Precisión del desarrollador:** 5 **rondas** completas.
- **Alcance:** aplica también al modo propio actual, no solo al modo aliado.
- **Efectos afectados:** a decidir por el análisis (persistentes vs instantáneos).
- **Torbellino:** `AQUA_RING` persistente.

## Estado

`RECEIVED` — análisis completado; en curso el addendum de duración de 5 rondas.
