# TASK-033 — Revisión

- **Estado:** APPROVED
- **Revisor:** `reviewer` (sesión `ses_e40b43bb3ffeCyeMS3IFI2Meen`)
- **Nota:** resultado del revisor, sin alterar sus conclusiones.

---

# Revisión — TASK-033

## 1. Veredicto

```text
APPROVED
```

## 2. Resumen de la revisión

Revisado el diff completo contra `HEAD` de los 7 archivos autorizados, junto con `request.md`, `analysis.md`, `architecture.md`, `plan.md` v1, `approval.md` y `execution.md`. El cambio se ajusta al alcance aprobado: los tres fallos se corrigen en los puntos previstos, sin métodos públicos nuevos, sin cambios de dominio no autorizados y sin tocar sesión, serialización, migraciones, tests ni dependencias.

- **Fallo 1:** los tres bloqueos de retaguardia aliada del camino del jugador se han eliminado (`BatallaVistaPresenter::refsAliado()` y los dos bloques de `GestorSeleccionBatalla`). No queda ningún filtro de posición aliada en `app/Livewire`: el único remanente es `SincronizadorVistaBatalla.php:49`, que es targeting rival. `actorAdmiteSoporte()` y `SelectorAccionIA` intactos.
- **Fallo 2:** `$prioridadesObjetivo` se inicializa en `abrir()`, se limpia en `limpiar()`/`cambiarPostura()`/rama sin prioridad, se valida en `aplicar()` y se revalida en `resolver()`. Autodirigidos, `DESAFIO`, fila legal, objetivo muerto y fallback IA cubiertos.
- **Fallo 3:** `$coberturas` se inicializa, sincroniza al cambiar postura, se valida en `aplicarCoberturaElegida()` (equipo, vivo, no autoobjetivo), la prioridad fuerza `ATACAR` y limpia cobertura, y `normalizarCubridores()` sigue siendo la red de seguridad.
- **Contrato Livewire:** 34 propiedades / 18 métodos públicos. Sin métodos nuevos y con defaults `[]`.
- **Dominio:** `AgregadoBatalla::objetivosRivalesLegalesPara()` es lectura pura (usa `filter` sobre una colección nueva; no muta).
- **Frontend:** `wire:model.live` solo en el movimiento de prioridad; `wire:model` diferido en objetivo y cobertura; `??` defensivos para snapshots antiguos; `wire:key`; etiquetas `for`/`aria-*`; sin lógica de negocio; modal no modificado.
- **Verificación estática ejecutada:** `php -l` sin errores en los 6 PHP; `phpstan analyse --no-progress` devuelve **34 errores, ninguno en los archivos modificados** (baseline declarado en `execution.md`); `git diff --check` limpio; sin comentarios `@phpstan-ignore` añadidos; `phpstan-baseline.php` no modificado.

**No se han detectado defectos CRITICAL, HIGH ni MEDIUM introducidos por TASK-033.** Los hallazgos son LOW/NIT y deuda preexistente documentada.

## 3. Hallazgos por severidad

### CRITICAL / HIGH / MEDIUM

Ninguno introducido por TASK-033.

### LOW

- **[LOW] `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php:312-321` — `objetivos` puede no ser un `list` real.**
  - **Evidencia:** `Collection::filter()` (`src/Shared/Domain/Collection.php:59-62`) usa `array_filter` y conserva claves; `Collection::map()` (`:112-115`) usa `array_map`, que también preserva claves numéricas con huecos. Como los rivales debilitados permanecen en `EquipoBatalla`, `vivos()` produce claves no consecutivas (p. ej. `[0,2]`) y `objetivosLegalesPara()` puede devolver un array disperso, contradiciendo su docblock `list<array{...}>` y serializándose como objeto JSON dentro de `$prioridadesDisponibles`. Blade lo itera sin error, por lo que no hay rotura funcional.
  - **Impacto:** contrato de dato inexacto y forma JSON inestable; riesgo futuro si otro consumidor espera `array_is_list`.
  - **Recomendación:** envolver el resultado en `array_values(...)` en `objetivosLegalesPara()`.

- **[LOW] Duplicación de helpers entre `GestorPreparacionBatalla` y `GestorPrioridadesBatalla`.**
  - **Evidencia:** `esAutodirigido()`, `objetivoForzadoDesafio()` y `textoMapa()` aparecen duplicados (`GestorPreparacionBatalla.php:323-348` y `GestorPrioridadesBatalla.php:183-208`), además del predicado de objetivo legal repetido en `validarObjetivoPrioridad()` y `objetivoPara()`. La regla de `DESAFIO` también existe en `GestorSeleccionBatalla::selectMove()` y `SelectorAccionIA`.
  - **Impacto:** riesgo de divergencia si cambia la semántica de autodirigidos o `DESAFIO`. No afecta a la corrección actual (las tres copias son equivalentes).
  - **Recomendación:** extraer a un colaborador de aplicación compartido o a un método de dominio en una tarea posterior; no bloquea porque el plan dividió explícitamente B3/B4.

### NIT

- **[NIT] `src/Battle/Domain/AgregadoBatalla.php:325-333` — comprobación redundante.**
  - **Evidencia:** `tieneVanguardiaViva()` y el posterior `filter(estaEnVanguardia)` + `isEmpty()` expresan la misma condición. Se evalúa dos veces.
  - **Impacto:** nulo (coste O(n) trivial, n ≤ 6).
  - **Recomendación:** simplificar a `filter` + `isEmpty`, conservando el fallback.

- **[NIT] `resources/views/livewire/partials/preparacion-panel.blade.php:24 y :91` — selects sin `@selected`.**
  - **Evidencia:** el valor seleccionado depende de la hidratación de `wire:model` en cliente; el HTML inicial no marca la opción actual. Funciona con Livewire, pero el estado no es correcto sin JS y puede verse un “Automático” transitorio.
  - **Impacto:** cosmético/accesibilidad.
  - **Recomendación:** añadir `@selected(($coberturas[$refId] ?? '') === $aliado['refId'])` y el equivalente para `$prioridadesObjetivo`.

### Riesgos de proceso / preexistentes (no defectos de TASK-033)

- `execution.md` no evidencia la verificación manual §7.2 del plan (13 escenarios). No es un defecto de código, pero debe completarse antes del cierre.
- Deuda preexistente: `$posturas` y `$prioridades` no se normalizan por tipo; con `declare(strict_types=1)`, un array manipulado en `$posturas` podría provocar `TypeError` en `PosturaCombate::tryFrom()`. No lo introduce TASK-033 (los mapas nuevos sí usan `textoMapa()`). Ya recogido por `security` como re-ejecución de preparación/valores públicos.
- Dependencias con advisories conocidos (`composer audit`), fuera de alcance; `security` concluyó PASS_WITH_FINDINGS no bloqueante.

## 4. Criterios CA1–CA22

| CA | Estado | Evidencia / nota |
|---|---|---|
| CA1 | Cumplido (estático) | `refsAliado()` ya no filtra retaguardia (`BatallaVistaPresenter.php:115-142`); requiere verificación manual. |
| CA2 | Cumplido (estático) | Bloques eliminados en `previewTarget()` y `selectMove()`; no queda rechazo por posición aliada. |
| CA3 | Cumplido (estático) | `GestorAccionBatalla::resolver()` enruta a `aplicarMovimientoAliado()` por `esAliado` + `CatalogoEfectosAliado`; sin cambios. |
| CA4 | Cumplido | `SincronizadorVistaBatalla.php:49` y `canTarget` intactos; único filtro de retaguardia restante es rival. |
| CA5 | Cumplido | `AgregadoBatalla::actorAdmiteSoporte()` y `SelectorAccionIA` sin diff. |
| CA6 | Cumplido (estático) | Selector condicional a `$prioridadElegida !== null`, no autodirigido y sin forzado; lista desde `objetivos`. Verificación manual pendiente. |
| CA7 | Cumplido (estático) | `resolver()` lee `prioridadesObjetivo`, `objetivoPara()` devuelve el declarado si es legal. |
| CA8 | Cumplido | `''` cae a `elegirObjetivoPara()`, comportamiento previo. |
| CA9 | Cumplido | `esAutodirigido()` (`esAutodirigido() || ALL`) devuelve el actor y limpia el objetivo. |
| CA10 | Cumplido | `validarObjetivoPrioridad()` exige rival, vivo y perteneciente a `objetivosRivalesLegalesPara()`; si no, limpia. |
| CA11 | Cumplido | Revalidación en `objetivoPara()` + guard final `! estaVivo()` en `resolver()`. |
| CA12 | Cumplido | `objetivoForzadoDesafio()` prevalece; UI oculta el selector y muestra aviso. |
| CA13 | Cumplido (estático) | Blade lista `team1Vista` vivos distintos de `$refId` con postura `cubrir`. |
| CA14 | Cumplido | `AplicadorPosturaDanio.php:60-65` sin cambios: 40 % al cubridor. |
| CA15 | Cumplido | `aplicarCoberturaElegida()` rechaza autoobjetivo, muerto, rival o id inexistente; luego `normalizarCubridores()` asigna fallback. |
| CA16 | Cumplido | `cambiarPostura()` pone `coberturas=''` y `normalizarCubridores()` limpia el dominio. |
| CA17 | Cumplido | Rama de prioridad: `setPostura(ATACAR)`, `setObjetivoCubiertoId('')`, `coberturas[$id]=''`. |
| CA18 | Cumplido | `aplicar(false)` aplica posturas/coberturas; `limpiar()` vacía ambos mapas en los tres caminos. |
| CA19 | Cumplido | `normalizarCubridores()` conserva dos cubridores; límite de 2 y reparto 40/40/20 intactos. |
| CA20 | Cumplido | Props nuevas con default `[]`; Blade usa `??` en todas las claves nuevas; `array_key_exists` evita sobrescribir dominio en snapshots antiguos. |
| CA21 | Cumplido | `SESSION_VERSION = 21`, `MINIMUM_COMPATIBLE = 9`; `BattleSessionService` sin diff. |
| CA22 | Cumplido | `git diff` no toca `tests`, `composer.*` ni `database/migrations`; `bugs.txt` es cambio preexistente del desarrollador y no se ha alterado por TASK-033. |

## 5. Riesgos residuales

1. **Verificación manual no evidenciada** (§7.2 del plan): selección visual de aliado en retaguardia, comportamiento de `wire:model.live` dentro del modal con `wire:ignore.self`, y reparto 40/40/20 en UI. Es el principal riesgo restante.
2. **`DESAFIO` en prioridades:** ahora se aplica explícitamente al jugador (antes también lo aplicaba la IA vía `elegirObjetivoPara`). Es la decisión aprobada, pero conviene probar el aviso y el objetivo forzado.
3. **Asimetría jugador/IA** en apoyo a retaguardia: documentada y aprobada; no es un defecto.
4. **Estado de mapas en cliente:** `$prioridadesObjetivo` y `$coberturas` viven solo en Livewire; si en el futuro la resolución se difiere a otra petición, habrá que persistirlos/revalidarlos. Hoy la resolución es síncrona.
5. **Duplicación de helpers** y forma dispersa de `objetivos` a futuro.
6. **Deuda preexistente** de normalización de tipos en `$posturas`/`$prioridades` y de guard de ronda en `confirmarPreparacion()`/`resolver()`; no introducida aquí.

## 6. Recomendación final

**APPROVED.** El cambio cumple el plan v1 y los CA1–CA22 por inspección estática, no amplía el contrato más allá de lo aprobado, mantiene la dirección de dependencias y no introduce defectos de severidad bloqueante. Antes de cerrar la tarea recomiendo:

1. Completar la verificación manual §7.2, especialmente modal + `wire:model.live` y reparto de cobertura.
2. Aplicar, como mejora no bloqueante, `array_values()` en `objetivosLegalesPara()` y `@selected` en los dos selects nuevos.
3. Programar en tarea separada la extracción de los helpers duplicados (`esAutodirigido`, `objetivoForzadoDesafio`, `textoMapa`) y el guard de ronda preexistente.

No requiere correcciones de backend ni frontend para aprobar; los puntos 2 son mejoras dentro del alcance de una corrección menor si el orquestador prefiere dejarlo impecable.

## 7. Correcciones post-revisión

- **NIT `@selected`:** aplicado en el select de cobertura y en el de objetivo de prioridad.
- **LOW `array_values()`:** no aplicado. La premisa del reviewer era incorrecta: `Collection::__construct()` recorre los items y los reindexa con `add()`, por lo que `filter()`/`map()` ya devuelven una lista real. PHPStan lo confirma con `arrayValues.list`; se restauró el retorno original y el baseline de 34 errores.
- **LOW duplicación de helpers:** diferido a tarea posterior.
- **NIT comprobación redundante en `objetivosRivalesLegalesPara()`:** no aplicado; sin impacto funcional.
