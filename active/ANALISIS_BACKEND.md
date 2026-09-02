# ANÁLISIS_BACKEND — Fase 1: IA Contextual Básica

## Objetivo

Convertir `SelectorAccionIA` de una selección simple de objetivo/movimiento (efectividad × potencia) hacia un sistema contextual que analice amenazas, priorice KOs y evalúe supervivencia.

## Archivos afectados

### Crear
- `src/Battle/Domain/AI/NivelDificultad.php` (enum)
- `src/Battle/Domain/AI/ContextoDecisionIA.php` (DTO)
- `src/Battle/Domain/AI/ValueObjects/EvaluacionAmenaza.php` (DTO)
- `src/Battle/Domain/AI/ValueObjects/EstimacionDanio.php` (DTO)
- `src/Battle/Domain/AI/ValueObjects/EvaluacionAccion.php` (DTO)
- `src/Battle/Domain/AI/ValueObjects/ResultadoDecision.php` (DTO)
- `src/Battle/Domain/AI/EstimadorDanioIA.php` (interfaz + impl)
- `src/Battle/Domain/AI/AnalizadorAmenazas.php` (interfaz + impl)
- `src/Battle/Domain/AI/EvaluadorAccionIA.php` (interfaz + impl)
- `src/Battle/Domain/AI/SelectorAccionIA.php` (refactor: orquestador)

### Modificar
- `tests/Unit/Battle/SelectorAccionIATest.php` (tests nuevos de decisiones IA)

### No modificar
- `src/Battle/Domain/AgregadoBatalla.php` (API pública se mantiene)
- `src/Battle/Domain/Chain/` (no duplicar fórmula)
- `app/Livewire/Combate.php` (compatibilidad preservada)

## Tests

### Unit (SelectorAccionIATest.php — nuevos escenarios)
1. **KO prioritario** — Enemigo B (amenaza crítica) se prioriza sobre A (10% HP, amenaza baja)
2. **Amenaza inmediata** — Enemigo que puede KO al actor y actúa antes recibe prioridad
3. **KO garantizado** — Enemigo en rango de KO se prioriza si no hay amenaza más urgente
4. **Supervivencia** — Evaluar intercambio KO vs supervivencia del actor
5. **Último enemigo** — Preferir KO seguro sobre setup
6. **Estado sobre daño** — Sleep sobre amenaza crítica vs daño bajo

## Diseño

### DTOs
- `ContextoDecisionIA`: agrega battle, actor, aliados, enemigos, dificultad, turno
- `EvaluacionAmenaza`: score por enemigo con desglose ofensiva/KO/velocidad/setup/estratégica
- `EstimacionDanio`: min/max/esperado/probabilidadKO
- `EvaluacionAccion`: score por acción con desglose KO/daño/amenaza/supervivencia/riesgo
- `ResultadoDecision`: acción + amenazas + evaluaciones (logging)

### Servicios
- `EstimadorDanioIA`: reutiliza CadenaDanio en modo determinista (mt_srand)
- `AnalizadorAmenazas`: calcula ThreatScore por enemigo
- `EvaluadorAccionIA`: evalúa cada acción candidata
- `SelectorAccionIA` (refactor): orquesta el pipeline completo

> Nota: `EvaluadorPosicionIA` se descartó en Fase 1. La fórmula de score
> (`koValue + damageValue + threatReduction + survivalValue - risk`) no consume
> el score global de posición, por lo que era código muerto. Se reintroducirá
> cuando se especifique un scoring consciente de posición (posible Fase 2).

### Enum
- `NivelDificultad`: NORMAL, DIFICIL, PERFECTA

## Riesgos

1. `mt_srand()` en `EstimadorDanioIA` para determinismo — afecta RNG global temporalmente
2. Los métodos legacy `elegirObjetivoPara`/`elegirMejorMovimiento` cambian comportamiento al delegar al nuevo sistema (más inteligente pero posiblemente diferente)
3. `ejecutarAccion` en `AgregadoBatalla` sigue usando los métodos legacy — coexistencia temporal
