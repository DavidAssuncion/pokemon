# TASK-004 — QA

> **No aplica desde 2026-11-02.** El desarrollador desactivó la generación y
> mantenimiento de tests, y las reglas globales del entorno eliminaron la fase
> `TESTING`/`qa` del flujo. Se conserva el histórico de la Fase 1 como
> referencia. La validación actual se apoya en revisión estática, `php -l`,
> `php artisan view:cache` y comprobaciones manuales.

## Histórico — Fase 1

## Fase 1 — Estabilización contable y cadencia

- **Ejecutor:** `qa`
- **Veredicto:** `PASS` (alcance Fase 1; no certifica la suite global del árbol)

### Pruebas específicas

| Grupo | Resultado |
|---|---|
| Unitarias de Economía, Calculador, Cadencia, Captura y ReglasEv | **57 passed, 205 assertions** |
| Feature de Exploraciones, Persistencia y TopeEv | **62 passed, 249 assertions** |
| Feature de Mazmorras | **13 passed, 54 assertions** |
| `tests/Feature/Exploraciones` | **29 passed, 123 assertions** |
| Pint sobre 11 archivos de Fase 1 | **pass** |

### Criterios validados

- EXP: pool único, invariante `cuenta + destino×miembros <= pool`, vector 3×400 → 600/200, mazmorra 750 → 3750/1250 y caramelos 210/210.
- Hallazgos ≥1 por clase con multiplicador 0,5; dos de la misma familia ≥2; ceros no persistidos.
- Emboscada evitada fuera de `idsDerrotados`; 1 evento extra.
- Derrota `intdiv(cantidad,2)`; cantidad 1 no pierde.
- Cadencia por tiempo, contador persistido, tope 12, backfill sin duplicar.
- EV 255; 250+1→255; legado 300→300; `AplicarEvsFamilia` no reduce legados.
- Captura unificada ruta/exploración; sin factor 0,5; cap-25 intacto.

### Cobertura pendiente

- Recompensa final de **1 caramelo de familia** por emboscada evitada (implementada, sin test directo).
- Cadencia con ticks de **1 minuto** (probados 5/15/60).

### Suite global

- `php artisan test`: **21 failed, 9 skipped, 1358 passed**.
- Los fallos son de WIP fuera de Fase 1: vistas (`ExploracionesViewTest`, `FrontendEquiposRolTest`), seeders (`PokemonSeederTest`, `PokemonHabitatSeederTest`) y dos tests de Batalla (`CombatienteAvanzadoTest`, `ManejadorObjetosEquipadosTest`).
- PHPStan completo: 8 errores de baseline en `SendasController` (WIP de Fase 2B).
- No se reprodujo el error `relation "users" does not exist` reportado por el backend.

### Recomendaciones

1. Aceptar Fase 1 para su alcance.
2. Añadir los dos tests de cobertura pendiente.
3. Triar los 21 fallos globales antes del cierre de la tarea; no ocultarlos.
4. Repetir la suite completa cuando el WIP de Fase 2B/vistas/seeders esté estabilizado.
