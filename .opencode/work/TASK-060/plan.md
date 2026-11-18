# TASK-060 — Plan v1

- **ID:** TASK-060
- **Versión:** v1
- **Fecha:** 2026-11-18
- **Estado:** AWAITING_APPROVAL
- **Proyecto:** `/home/david/pokemon`

## Objetivo

Quitar los chips no solicitados (`Fase`/`ENRAGE` y `BUFF`) de todas las tarjetas de combate,
mostrar cinco chips de `+200 %` por stat en el jefe de mazmorra y aplicar en mazmorra la
regla de que los movimientos que escalan con boosts solo cuenten el exceso real sobre
+4 etapas / ×3.

## Alcance

### Dentro

- Eliminar los chips «Fase»/«ENRAGE» y «BUFF» de `_pokemon-card.blade.php` para todos los
  combatientes.
- Sustituir el chip único `+200 %` por cinco chips: ATK, DEF, SpA, SpD y SPE, con el
  porcentaje derivado del multiplicador permanente.
- Exponer en `CombatienteVista` una lista `statsJefe` con `{clave,label,porcentaje}`.
- Añadir `bonificacionesPositivasSobreLimite()` y `bonificacionesPositivasParaDanio()`.
- Usar el valor limitado solo en `ManejadorDanioBase` para `objetivo_con_boost`,
  `objetivo_boost` y `atacante_boost`, cuando el combatiente tenga
  `multiplicadorStats > 1.0`.
- Documentar la regla.

### Fuera

- Cambiar `bonificacionesPositivas()` global o `EjecutorCantoEncantador`.
- Cambiar otros modos de combate.
- Cambiar `MultiplicadoresStats`, `BattleStats` o la serialización.
- Tests, dependencias, migraciones o base de datos.

## Diseño técnico

### 1. Chips

En `resources/views/livewire/_pokemon-card.blade.php`:

- Eliminar el bloque de fase (`jefe.nombreFase`) y ENRAGE.
- Eliminar el bloque `BUFF` (`buffTurns`).
- Sustituir el bloque `bonusStatsJefe` por:

```blade
@foreach($p['statsJefe'] as $stat)
    <span class="badge bg-warning-soft text-warning-ink"
          title="Estadística mejorada de forma permanente (+{{ $stat['porcentaje'] }} %)">
        +{{ $stat['porcentaje'] }} % {{ $stat['label'] }}
    </span>
@endforeach
```

- Mantener `JEFE`, partes, estado, K.O., INV, COMBO, condiciones y etapas.

En `src/Battle/Domain/CombatienteVista.php`:

- Sustituir `bonusStatsJefe` por `statsJefe`.
- Añadir un mapa privado de labels:
  `attack => ATK`, `defense => DEF`, `spAtk => SpA`, `spDef => SpD`, `speed => SPE`.
- `statsJefe` devuelve una lista vacía si el combatiente no es jefe o su multiplicador es
  `<= 1.0`; si aplica, devuelve los cinco stats con
  `porcentaje = (int) round((multiplicador - 1.0) * 100)`.

### 2. Regla del límite (opción C)

- Nueva constante en `src/Battle/Domain/ReglasBatalla.php`:
  `LIMITE_ETAPAS_BOOST_EXTRA = 4` con comentario: “tope de etapas que no cuenta para
  movimientos que escalan con boosts cuando el combatiente tiene multiplicador permanente”.
- En `src/Battle/Domain/Componentes/Combatiente/StatsCombatiente.php`:
  ```php
  public function bonificacionesPositivasSobreLimite(int $limite): int
  {
      $total = 0;
      foreach ($this->multiplicadores->obtenerEtapas()->toArray() as $etapas) {
          if ($etapas > $limite) {
              $total += $etapas - $limite;
          }
      }
      return $total;
  }
  ```
- En `src/Battle/Domain/Combatiente.php`:
  ```php
  public function bonificacionesPositivasParaDanio(): int
  {
      if ($this->pokemon->battleStats()->multiplicadorStats() > 1.0) {
          return $this->stats->bonificacionesPositivasSobreLimite(
              ReglasBatalla::LIMITE_ETAPAS_BOOST_EXTRA
          );
      }
      return $this->bonificacionesPositivas();
  }
  ```
- En `src/Battle/Domain/Chain/ManejadorDanioBase.php`, usar
  `bonificacionesPositivasParaDanio()` en:
  - `objetivo_con_boost` (Envidia Ardiente),
  - `objetivo_boost` (Castigo),
  - `atacante_boost` (Poder Reserva, Chulería).
- `EjecutorCantoEncantador` sigue usando `bonificacionesPositivas()`.

### 3. Documentación

- `src/Battle/context.md`: nuevo método y regla del límite.
- `src/Mazmorras/context.md`: regla de daño en mazmorra y chips.
- `docs/context.md` solo si alguna referencia queda obsoleta.

## Archivos y responsables

| Archivo | Cambio | Responsable |
|---|---|---|
| `resources/views/livewire/_pokemon-card.blade.php` | Quitar Fase/ENRAGE/BUFF; cinco chips | `frontend` |
| `src/Battle/Domain/CombatienteVista.php` | `statsJefe` con cinco labels/porcentajes | `backend` |
| `src/Battle/Domain/ReglasBatalla.php` | `LIMITE_ETAPAS_BOOST_EXTRA = 4` | `backend` |
| `src/Battle/Domain/Componentes/Combatiente/StatsCombatiente.php` | `bonificacionesPositivasSobreLimite()` | `backend` |
| `src/Battle/Domain/Combatiente.php` | `bonificacionesPositivasParaDanio()` | `backend` |
| `src/Battle/Domain/Chain/ManejadorDanioBase.php` | Usar el método limitado en tres fórmulas | `backend` |
| `src/Battle/context.md` | Documentar métodos/regla | `documenter` |
| `src/Mazmorras/context.md` | Documentar regla y chips | `documenter` |

## Orden de ejecución

1. `backend`: `ReglasBatalla`, `StatsCombatiente`, `Combatiente`, `ManejadorDanioBase`.
2. `backend`: `CombatienteVista`.
3. `frontend`: `_pokemon-card.blade.php`.
4. `documenter`: contextos.
5. `reviewer`: revisión independiente.
6. `security`: no aplica previsiblemente.

No hay paralelismo sobre archivos compartidos.

## Criterios de aceptación

- [ ] Ninguna tarjeta de combate muestra los chips «Fase», «ENRAGE» ni «BUFF».
- [ ] El jefe de mazmorra muestra exactamente cinco chips: `+200 % ATK`, `+200 % DEF`,
      `+200 % SpA`, `+200 % SpD`, `+200 % SPE`.
- [ ] Un combatiente sin multiplicador no muestra chips de +200 %.
- [ ] `bonificacionesPositivasParaDanio()` devuelve el valor actual para combatientes con
      multiplicador `<= 1.0`.
- [ ] Para el jefe con multiplicador 3.0, devuelve la suma de `max(0, etapas - 4)` por stat.
- [ ] Castigo, Poder Reserva, Chulería y Envidia Ardiente usan ese valor limitado.
- [ ] Canto Encantador sigue usando `bonificacionesPositivas()` sin límite.
- [ ] Otros modos no cambian.
- [ ] Sin cambios en tests, dependencias, migraciones ni `SESSION_VERSION`.

## Verificación requerida

Sin tests. Se ejecutará:

1. `php -l` sobre los PHP modificados.
2. `vendor/bin/phpstan analyse` sobre los PHP modificados.
3. `vendor/bin/pint` sobre los archivos modificados.
4. Comprobación manual:
   - `bonificacionesPositivasParaDanio()` con etapas +1, +4, +5 y +6.
   - Render de la tarjeta del jefe y de un Pokémon normal.
5. Revisión independiente.

No se ejecutará `php artisan test` ni ninguna suite.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| La opción C suprime buffs reales hasta +4; es la intención confirmada. | Documentarlo en `src/Mazmorras/context.md` y en el criterio de aceptación. |
| Cambiar `bonificacionesPositivas()` global afectaría a Canto Encantador y otros modos. | No se toca; se añade un método nuevo. |
| Chips extra aprietan la cabecera. | Usar `flex-wrap` existente y clases actuales; sin CSS nuevo. |
| `bonusStatsJefe` queda sin uso. | Se sustituye por `statsJefe`; no hay otros consumidores. |
| Rama sucia con TASK-055 y otras tareas. | Editar por líneas exactas; no reformatear. |
| `jefe.nombreFase`/`enrage` y `buffTurns` quedan sin consumidor visual. | Se conservan en el contrato; solo se dejan de pintar. |

## Rollback

- No hay migraciones ni datos.
- Revertir solo los archivos de la tabla anterior con el diff inverso.
- No usar `git reset --hard`, `git clean` ni `git checkout --` global.

## Aprobación

Este plan no autoriza implementación hasta que el desarrollador lo apruebe mencionando
`plan v1`. La aprobación se registrará en `approval.md` con el texto literal.
