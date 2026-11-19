# TASK-065 — Ejecución

- **ID:** TASK-065
- **Proyecto:** `/home/david/pokemon`
- **Estado:** COMPLETED — implementado, revisado; validación manual pendiente del desarrollador

## Preflight (2026-11-19)

### `git branch --show-current`

```text
master
```

### `git status --short`

```text
 M .githooks/pre-commit
 M app/Http/Controllers/PlayerController.php
 M app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php
 M app/Providers/DatagridServiceProvider.php
 M database/seeders/AbilitySeeder.php
 M resources/views/pokedex/index.blade.php
 M src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php
 M storage/data/pokemon_habitat.csv
?? .opencode/work/TASK-061/
?? .opencode/work/TASK-062/
?? .opencode/work/TASK-063/
?? .opencode/work/TASK-064/
?? app/Support/Pokedex/
?? docs/adr/0005-filtros-pokedex-y-descripciones-habilidades.md
?? resources/views/components/ui/combobox.blade.php
?? resources/views/partials/combobox-filtro-script.blade.php
?? storage/data/abilities_descripciones.json
```

### Cambios locales preexistentes

- TASK-061 (Pokédex/habilidades) y TASK-064 (debug/Pokédex) en curso.
- TASK-063 (turno extra tras KO) ya implementada y revisada, sin commitear.
- El trabajo de TASK-065 debe limitarse a los archivos de la habilidad Ausente,
  sus efectos y la marca visual; no se revertirá ni reformateará nada ajeno.

### Riesgos de preflight

- `master` con varios frentes abiertos: el diff de TASK-065 debe aislarse por
  archivo. `AbilitySeeder.php` y la vista de Pokédex ya están modificados por
  TASK-061; si hay que tocarlos, será de forma aditiva.
- No se ejecutarán operaciones destructivas ni `git push`.

## Delegaciones

| Fecha | Especialista | Objetivo | Resultado |
|-------|--------------|----------|-----------|
| 2026-11-19 | `analyst` | Investigar la implementación actual de Ausente, escudos, daño y marcas visuales | Completado (parcial por límite de pasos). Ver `analysis.md`: escudos = barreras; no existe estado anunciado; desfase Livewire `onTurnStart`/`puedeActuar`. |
| 2026-11-19 | `architect` | Diseñar la máquina de estados, contratos y timing de curación | Completado. Ver `architecture.md`: condición `AUSENTE` en `onRoundStart`, capacidad `ReaccionaAlPerderTurno`, boost temporal, curación al perder turno. |
| 2026-11-19 | `backend` | Implementar dominio, despachos, vista y descripción | Completado. 10 PHP + JSON; `php -l`, PHPStan dirigido y Pint en verde. |
| 2026-11-19 | `frontend` | Badge `AUSENTE` | Completado. `_pokemon-card.blade.php`, 3 líneas; `view:cache` OK. |
| 2026-11-19 | `reviewer` | Revisión independiente | APROBADO. Dos LOW corregidos después (`onFainted` retira boost y condición); MEDIO de cargas acotado y justificado. Ver `review.md`. |

## Decisiones del desarrollador (2026-11-19)

1. **Ventana del enfado:** si recibe daño **antes de su turno** (mientras la
   ausencia está anunciada), se enfada, sube +1 Ataque ese turno y **puede actuar**;
   la ausencia se cancela.
2. **Descanso:** si no recibe daño y se completa el turno ausente, pierde el turno y
   recupera 1/16 de escudos. Si recibe daño **después** del descanso, ya no puede
   actuar ese turno.
3. **Restauración:** al final del turno ausente, solo si no recibió daño directo.
4. **Ataque:** boost temporal de un turno (no etapa permanente).
5. **Disparador:** solo daño directo de movimientos.
6. **Escudos:** ambas barreras, física y especial, con tope en cada máximo.

## Implementación (2026-11-19)

- 10 archivos PHP modificados/creados, 1 Blade y la descripción de `ausente`.
- `EfectoTruant` rediseñado: `descansar`, `ausenciaAnunciada`, `enfadado`;
  `onRoundStart`, `alRecibirDanio`, `alPerderTurno`, `onTurnEnd`, `onRoundEnd`,
  `onFainted`, `__clone`; eliminado `onTurnStart`.
- Condición `AUSENTE` + rama primera en `puedeActuar`; capacidad
  `ReaccionaAlPerderTurno` despachada desde los 3 caminos; boost temporal con
  `retirarTemporal`; curación 6.25 en ambas barreras al perder el turno.
- Badge `AUSENTE` con tooltip en `_pokemon-card.blade.php`.
- Correcciones LOW post-review: `onFainted` retira boost y condición `AUSENTE`.
- Sin cambios de `SESSION_VERSION`, BD, dependencias, tests ni ADR.
- TASK-061/063/064/066/067 y demás cambios locales intactos.

## Verificación final

- `php -l` de los 10 PHP: OK.
- PHPStan dirigido: `[OK] No errors`.
- Pint dirigido y global: `pass`.
- `composer analyse`: 35 errores preexistentes ajenos; ninguno en TASK-065.
- `composer arch`: 105 violaciones preexistentes ajenas; ninguna en TASK-065.
- `grep "dispararTurnoPerdido"`: 1 definición + 3 llamadas.
- `grep "AUSENTE|ausente"`: sin lógica en `app/Livewire`; solo dominio, vista y
  descripción.
- `grep "onTurnStart"`: `EfectoTruant` ya no lo declara.
- JSON válido; `descripciones.ausente` actualizada (244 descripciones).
- Revisión independiente: APROBADO; delta de `onFainted` confirmado.
- Moveset de Slaking sin movimientos con `cargaMaxima > 0` (riesgo de cargas
  acotado).

## Estado

`COMPLETED` para código y revisión. La validación manual en partida (anuncio,
enfado, descanso, curación, daño posterior, daño indirecto, multigolpe y tope de
escudos) queda pendiente del desarrollador.
