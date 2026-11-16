# TASK-047 — Petición

- **Fecha:** 2026-11-13
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** análisis de producto (sin implementación).
- **Estado:** RECEIVED → ANALYZING

## Texto literal

> Que mejoras podemos hacerlo al juego? qol, diversion, rpg...

## Interpretación de trabajo

Inventario del estado actual del juego y propuesta priorizada de mejoras en tres ejes:

1. **QoL (calidad de vida):** fricción de uso, navegación, gestión de equipos/inventario, ritmo de combate, feedback, onboarding, móvil, acciones repetitivas.
2. **Diversión:** bucles de juego, cadencia de recompensas, variedad, profundidad del combate, eventos, objetivos a corto y medio plazo.
3. **RPG:** progresión, narrativa, facciones, misiones, decisiones, legendarios, gimnasios, mazmorras, economía y personalización.

El entregable es un diagnóstico con evidencia y una lista priorizada de mejoras, no código.

## Entregables

1. `analysis.md` con Parte A (QoL/fricción) y Parte B (diversión/RPG/progresión).
2. Priorización por impacto/esfuerzo, dependencias y duplicidades con trabajo ya pendiente.
3. Preguntas abiertas y próximos pasos para el desarrollador.

## Fuentes obligatorias

- `.opencode/work/TASK-043/analysis.md` — resumen funcional y fricciones.
- `.opencode/work/TASK-044/`, `TASK-045/`, `TASK-046/` — facciones, misiones, eventos.
- `docs/` y READMEs de `src/` — arquitectura y módulos.
- `Tareas_pdte/` — ideas pendientes del desarrollador.
- Código de `app/`, `src/` y `resources/views/` — evidencia de fricción y mecánicas reales.

## Restricciones

- No implementar código ni modificar archivos de aplicación.
- No crear, modificar ni ejecutar tests.
- No tocar los cambios locales preexistentes (26 archivos modificados + `TASK-032..043`).
- No inventar especies, cifras, mecánicas ni APIs.
- No leer `.env`, credenciales ni secretos.

---

## Addendum 2026-11-16 — Implementación de mazmorras

### Texto literal

> La mazmorra son los pokemon del habitat ordenador por stat y agrupados por niveles 1-2-3., eso podemos dejarlo listo en este ticket

### Decisiones de producto confirmadas por el desarrollador

1. **Stat de orden:** suma de stats base (BST: HP + Ataque + Defensa + Ataque Especial + Defensa Especial + Velocidad).
2. **Pisos:** un piso por Pokémon del pool. Se agrupan por nivel 1 → 2 → 3 y, dentro de cada nivel, se ordenan por BST ascendente.
3. **Cobertura:** todos los hábitats con pool (44), excluyendo el hábitat 49 `Legendarios`. Los 12 hábitats sin pool no generan mazmorra.
4. **Niveles incompletos:** la mazmorra usa los pisos que existan; no se inventan jefes. 7 hábitats no tienen nivel 3.

### Alcance técnico previsto

- Generar y persistir `habitats.mazmorra` con el contrato existente
  `{"pisos":[{"piso":1,"species_id":25}, ...]}`.
- Seeder determinista e idempotente, registrado en el catálogo de seeders.
- Sin cambios de esquema, sin tests y sin tocar los cambios locales preexistentes.
- Verificación estática y comprobación de los datos generados.

### Estado

`ANALYZING → DESIGNING → AWAITING_APPROVAL` (la implementación requiere plan `v1` aprobado).

### Decisiones adicionales (2026-11-16)

- **Fuente de verdad:** la BD actual (`pokemon_habitat`, `pokemon`, `pokemon_stats`), no el CSV. La BD tiene 56 hábitats con pool y 1006 filas; el CSV 44 y 787.
- **Formas regionales:** cuentan como pisos independientes, porque la BD les da `species_id` propio.
- **Resultado esperado:** 55 hábitats con mazmorra (excluido el 49) y ~884 pisos, tras excluir las especies 132/201/235.
- **Persistencia:** solo rellenar hábitats con `mazmorra IS NULL`; sin migración.
