# TASK-060 — Análisis

> Resultado de `analyst` (sesión `ses_e125f6da2ffe9V6Ktuv49TnSe3`). El agente alcanzó el
> límite de pasos antes del informe formal, pero completó la investigación. Se reproduce
> sin alterar sus conclusiones.

## RESULTADO

La UI es un cambio acotado y de bajo riesgo. La regla de daño tiene una ambigüedad
numérica importante: el ×3 permanente **ya no suma potencia** en el código actual, por lo
que la petición literal puede ser un no-op o requerir una regla nueva con tres lecturas
distintas. El desarrollador ya eligió la opción C (solo el exceso real sobre +4 etapas).

## HALLAZGOS UI

Chips a eliminar en `resources/views/livewire/_pokemon-card.blade.php`:

- `:94-114` bloque `@if($p['esJefe'])` con `JEFE`, **Fase** (`:96-98`), **ENRAGE**
  (`:99-101`), `+200 %` (`:102-107`), partes (`:108-113`).
  - Fase: `{{ $p['jefe']['nombreFase'] ?? 'Fase 1' }}`.
  - ENRAGE: `@if(!empty($p['jefe']['enrage']))`.
  - `bonusStatsJefe`: badge único, a sustituir por cinco chips.
- **BUFF**: `:135-139` (`@if(($p['buffTurns'] ?? 0) > 0)`), fuera del bloque `esJefe`,
  por eso aparece en todas las tarjetas.
- Chips que **no** hay que tocar: `JEFE` (`:94-95`), partes (`:108-113`), `status`
  (`:115-123`), `K.O.` (`:124-126`), `INV` (`:127-129`), `COMBO` (`:130-134`),
  condiciones (`:140-144`), etapas (`:145-149`).
- La tarjeta se incluye desde `resources/views/livewire/partials/battle-field.blade.php`
  (ambos equipos, mismo parcial). No hay otro parcial de tarjeta.

Contrato actual (`CombatienteVista.php`):

- `:127` `esJefe`, `:128` `jefe` (`JefeEstado::toArray()` con `faseActual`, `nombreFase`,
  `enrage`, multiplicadores y partes), `:129` `jefePartes`.
- `:130` `'bonusStatsJefe' => (int) round(($battleStats->multiplicadorStats() - 1.0) * 100)`
  → escalar único, calculado para todos.
- `:84` `buffTurns` = `turnosTemporales()`; `:83` `stagesDetalle`; `:145-149` chips de
  etapas.
- `bonusStatsJefe` / `buffTurns` se consumen **solo** en este Blade; ningún otro test o
  presentador los usa.

Hecho relevante: `esJefe` solo lo activa `GeneradorJefeMazmorra` (`esJefe: true`). Gimnasios
y entrenadores no lo usan. Por tanto «jefe de mazmorra» ≡ `esJefe` hoy.

Labels: no existe mapa reutilizable. `StatClave::label()` devuelve «Ataque», «At. Especial»,
etc., no `ATK/SpA/SPE`. Los `ATK/SpA/SpD/SPE` solo existen como literales JS en
`equipos/index.blade.php` y `forja/index.blade.php`, y en `PlayerController.php:179-187`.

## HALLAZGOS DE CÁLCULO

Cadena de daño: `CadenaDanio` instancia `ManejadorDanioBase` para todos los modos; no hay
variante de mazmorra. `AccionBatalla` no expone contexto de batalla (ni meta, ni tipo). El
`meta['tipo']='mazmorra'` se guarda aparte en sesión, fuera del agregado.

Punto exacto: `ManejadorDanioBase::potenciaEfectiva()` `:131-137`:

- `:131` `objetivo_con_boost` → duplica potencia si `defensor->bonificacionesPositivas() > 0`
  (Envidia Ardiente).
- `:136` `objetivo_boost` → `potencia + 20 × bonificaciones(defensor)` (Castigo).
- `:137` `atacante_boost` → `potencia + 20 × bonificaciones(atacante)` (Poder Reserva,
  Chulería).

Fuente: `Combatiente::bonificacionesPositivas()` `:536-539` →
`StatsCombatiente::bonificacionesPositivas()` `:101-111` → itera solo
`multiplicadores->obtenerEtapas()` (base × temporal, 7 stats) y suma positivos.
`MultiplicadoresStats::obtenerEtapas()` deriva de `obtenerMultiplicador`, no de
`BattleStats`.

Conclusión clave: el ×3 vive en `BattleStats`, no en `MultiplicadoresStats`; el jefe
conserva `MultiplicadoresStats` neutro. Por tanto, **hoy `bonificacionesPositivas()` del
jefe = 0 con el ×3**, y esos cuatro movimientos ya no reciben potencia del multiplicador
permanente. Lo único que cuenta hoy son etapas reales del jefe (buff temporal o de
movimiento).

Opciones numéricas para el límite:

| Caso jefe ATK +1 real | Factor total (3.0×1.5) | A ratio | B producto−4 | C etapas reales−4 |
|---|---|---|---|---|
| 0 boosts | 3.0 | 0 | 0 | 0 |
| +1 | 4.5 | 1 (+20) | 3 (+60) | 0 |
| +2 | 6.0 | 2 (+40) | 6 (+120) | 0 |
| +6 | 12.0 | 6 (+120) | 22 (+440) | 2 (+40) |

- A (exceso relativo sobre ×3) → idéntico a `bonificacionesPositivas()` actual; no-op.
- B (producto→etapas −4) → multiplica por 3 el bonus del jefe.
- C (etapas reales con suelo +4) → suprime los buffs reales del jefe hasta +4.

El desarrollador eligió **C**: solo el exceso real sobre +4 etapas / ×3.

`factorAEtapa(3.0) = +4`; no existe constante de tope en `ReglasBatalla` ni en
`ReglasMazmorra`.

Canto Encantador: `EjecutorCantoEncantador.php:23` usa `bonificacionesPositivas()` para
confusión; es estado, no daño. Se recomienda no tocarlo.

Otros modos: `bonusInicialStats` se aplica a la capa base y hoy no tiene call sites con
valor > 0; si se activara, inflaría `obtenerEtapas()` y `bonificacionesPositivas()`. No
cambiar el método base evita sorpresas.

Detección de mazmorra:

1. `battleStats()->multiplicadorStats() > 1.0` — disponible en el combatiente, misma fuente
   que la UI, sin plumbing. Hoy equivale a jefe de mazmorra. Recomendado.
2. Bandera en `DatosPokemonBatalla` — requeriría propagarla a `Combatiente`.
3. Contexto de batalla (`meta.tipo`) — requeriría pasar contexto a `AccionBatalla`; coste
   alto.

## OPCIONES DE IMPLEMENTACIÓN UI

1. **Contrato per-stat (recomendada).** Añadir `statsJefe` en `CombatienteVista::para()`:
   lista `{clave,label,porcentaje}` para ATK, DEF, SpA, SpD, SPE, solo si
   `esJefe && multiplicadorStats > 1.0`. Pros: vista tonta, sin duplicar porcentaje.
2. Mantener `bonusStatsJefe` escalar y hardcodear la lista en Blade. Pros: mínimo diff.
   Contras: lógica de presentación en Blade, labels duplicados.
3. Derivar de `stagesDetalle`: no válido; el jefe tiene etapas 0 y confundiría el ×3 con
   etapas.

## OPCIONES DE CÁLCULO

1. **No-op + guarda documentada** (si solo se quería que el ×3 no contara): no tocar
   `ManejadorDanioBase`; documentar. Cero riesgo, pero no cambia nada.
2. **A explícita**: nuevo método que calcule ratio = factorEtapas / multiplicador y
   `factorAEtapa(ratio)`. Con ×3 equivale a hoy; no-op.
3. **B**: `max(0, factorAEtapa(multiplicador × factor) − 4)`. Multiplica por 3 el bonus del
   jefe; contradice «dejan de tener efecto».
4. **C**: contar solo etapas reales > 4. Máxima supresión; es la opción elegida.

En todas: aplicar a atacante y defensor, solo cuando `multiplicadorStats > 1.0`.
`EjecutorCantoEncantador` fuera de alcance.

## ARCHIVOS / LÍNEAS

- `resources/views/livewire/_pokemon-card.blade.php:94-114` (JEFE, fase `:96-98`, ENRAGE
  `:99-101`, +200 % `:102-107`, partes `:108-113`); `:135-139` (BUFF); `:145-149` (etapas,
  conservar).
- `src/Battle/Domain/CombatienteVista.php:127-130`, `:83-84`, `:160-200`.
- `src/Battle/Domain/Componentes/Combatiente/StatsCombatiente.php:101-111`, `:113-122`,
  `:124-147`.
- `src/Battle/Domain/Combatiente.php:536-539`, `:687-695`.
- `src/Battle/Domain/Chain/ManejadorDanioBase.php:131-137`, `:101-154`.
- `src/Battle/Domain/Effects/Movimiento/EjecutorCantoEncantador.php:23`.
- `src/Battle/Domain/ValueObjects/MultiplicadoresStats.php:17,70-75,264-301,325-332`.
- `src/Battle/Domain/Enums/StatClave.php:7-28`.
- `src/Pokemon/Domain/Stats/BattleStats.php:32,35-39,69-92`.
- `src/Battle/Domain/DatosPokemonBatalla.php:20,28-29,64-68,86-89,95-127`.
- `src/Battle/Domain/EquipoBatalla.php:175-181,226-231`.
- `src/Mazmorras/App/GeneradorJefeMazmorra.php:50-79`.
- `src/Mazmorras/Domain/ReglasMazmorra.php:17-23`.
- `src/Battle/Domain/Chain/CadenaDanio.php:12-34`.
- `src/Battle/Domain/AccionBatalla.php:11-20`.
- `src/Battle/Domain/ValueObjects/JefeEstado.php:169-181,220-228`.
- Docs: `src/Battle/context.md:692,1008-1016`, `src/Mazmorras/context.md:25,44-46`.

## TESTS IDENTIFICADOS (sin modificar)

- `tests/Unit/Battle/MovimientoEfectosTest.php:221-241` — Canto Encantador confunde solo
  con boosts (etapas reales).
- `tests/Unit/Battle/MovimientoEfectosTest.php:1180-1204` — `stages['defense']`/`spDef` de
  `aArrayVista`.
- `tests/Unit/Battle/EtapasStatsTest.php:110` — `obtenerEtapas()`.
- `tests/Feature/Mazmorras/MazmorraApiTest.php:118-137` — hardcodea HP ×7, stats ×10 y
  bonus 1.3; ya obsoleto por TASK-055, fuera de alcance.
- Ningún test referencia `bonusStatsJefe`, `buffTurns`, `stagesDetalle`, `jefePartes` ni
  los chips Fase/ENRAGE/BUFF.

## RIESGOS

- **[alto] Ambigüedad numérica A/B/C** — resuelta por el desarrollador: opción C.
- **[alto] Requisito posiblemente ya cumplido** — el ×3 ya no suma; C añade supresión de
  buffs reales hasta +4. Confirmado como intención.
- **[medio] Regresión en otros modos** si se modifica `bonificacionesPositivas()` global.
  Mitigación: método nuevo y condición por `multiplicadorStats > 1.0`.
- **[medio] `bonusInicialStats` latente** — no tocar el método base.
- **[bajo] UI** — cinco chips extra pueden apretar la cabecera; usar clases existentes.
- **[bajo] Contrato muerto** — `jefe.nombreFase`, `jefe.enrage` y `buffTurns` quedan sin
  consumidor en Blade; se pueden conservar en el contrato.
- **[bajo] Rama sucia** — editar por líneas exactas.

## PREGUNTAS ABIERTAS

1. ~~¿A/B/C?~~ → C.
2. ¿La regla aplica también al jefe como atacante? → sí, ambos.
3. ¿Disparador `multiplicadorStats() > 1.0`? → sí.
4. ¿Se conserva `bonusStatsJefe` escalar? → no; se sustituye por `statsJefe`.
5. ¿Labels en `CombatienteVista`? → sí, mapa privado.
6. ¿Canto Encantador fuera? → sí.
7. ¿Se eliminan del contrato `buffTurns`/`jefe.nombreFase`/`enrage`? → no; solo se dejan de
   pintar.

## RECOMENDACIÓN

1. UI: quitar Fase/ENRAGE y BUFF; añadir `statsJefe` per-stat y cinco chips.
2. Cálculo: opción C con método nuevo, sin tocar `bonificacionesPositivas()` ni Canto
   Encantador.
3. Aislar por `multiplicadorStats > 1.0`; aplicar a atacante y defensor en
   `ManejadorDanioBase`.
