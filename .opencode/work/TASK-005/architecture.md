# TASK-005 — Arquitectura

- **Arquitecto:** `architect`
- **Fecha:** 2026-11-02
- **Basado en:** `analysis.md`
- **Estado:** completado

## 1. Decisiones

1. **Imagen familia:** sprite de especie como principal. El presentador emite `candy_imagen = /images/iconos_webp/{id}.webp` si `pokemon_id>0`, si no `candy_src`. `candy_src` no cambia. Solo diario: `_caramelo.blade.php` lo comparten recompensas y Livewire de combate; tocarlo altera otra UI y queda como deuda. `iconos_webp` es más fiable que `candy_pokemon` (647 archivos para >1000 formas).
2. **Nombre familia:** ampliar `nombresPokemon()` al `bitacora` de terminadas con `idsDeEvento()` (misma query `whereIn`). **No** escanear `resultado['caramelos_familia']`: todos los hallazgos del diario llevan `pokemon_id` y ese resultado ya persiste `nombre`; añadir IDs sin consumidor solo engorda la query. **No** resolver por cadena (requiere consulta extra, sin valor aquí). Orden: `evento.nombre` → mapa[`pokemon_id`] → mapa[`pokemon_ids[0]`] → `null` → blade «Pokémon».
3. **Tipo:** `TipoEnum::fromId()` + `SlugTipo::de(label)`; legacy `slug` → `tipo_nombre` → `tipo_label`; sin resolución `oculto=true`. Se filtra en `DiarioExploracion` **antes** de `$transformados`/`$grupos` (así `total`, grupos y «Sin eventos» cuadran), y también en `activaToView` por paridad (hoy sin llamadores; evita una ruta divergente). No se filtra dentro de `EventoExploracionVista` (presenta un evento, no una colección) ni en `hitos`.
4. **EV:** entra en alcance, **solo backend**. Es la misma rama `hallazgo()` y el mismo defecto; activar `completarStats` para `subtype==='caramelo_ev'` exige corregir los slugs (`atksp`, `defsp`, `spd`) o se generan 404. Sin cambio de blade: `candy_nombre` deja de ser null y `candy_nombre_js` pasa a null.
5. **Explorador:** `ExploradorVista::nombre()`: `nombre` no vacío → `pokemon.name` si `relationLoaded('pokemon')` → `'Sin reclutado'`. Se usa `relationLoaded` (patrón de `tipos()`) para no provocar lazy loading ni romper el unit test sin BD; el controlador ya eager-carga `reclutado.pokemon.types` para activas y terminadas. Sin reclutado o sin Pokémon → `'Sin reclutado'`. **No** tocar `equipo` (185/275): no lo consume ninguna vista, su semántica es otra y los tests lo asertan; deuda documentada.
6. **Derrota/PV:** claves nuevas en el evento de combate: `hp_inicial`, `hp_max`, `rival_hp_inicial`, `rival_hp_final`, `rival_hp_max` (float). `rival_pokemon_id` **no** se persiste: se deriva del último `sub_combates[].pokemon_id` o de `pokemon_id`. `hp_inicial` = PV al inicio del **sub-combate decisivo** (el que produce la `resolucion` persistida), capturado tras aplicar `estadoInicial` y `triggerBattleStartEffects()`, justo antes de `ejecutarBatalla()`: es el «cuando enfrentó» real, ya aplicado el descanso previo. En emboscada múltiple, el primer sub-combate no explica la derrota; `sub_combates` sigue siendo la traza. Se añaden al VO con defaults `null` y a `EventoExploracion` (constructor/`desdeArray`/`aArray`). El presentador calcula `rondas` (líneas `--- Ronda N ---`), `rival_nombre` y `detalle_derrota`; la vista solo imprime. El evento raíz `derrota` **no** se enriquece con PV: lo escribe la finalización sin estado de combate; basta `reason_texto` humano y el combate previo.
7. **Compatibilidad:** `array_key_exists()` para floats (`hp_final=0` es válido; `?? 0` mentiría); `??` para strings/arrays. Claves ausentes → derivados `null` → bloque omitido. No se renombra ninguna clave existente ni se reescriben bitácoras históricas.

## 2. Contrato de datos final

| Evento | Clave | Tipo | Cuándo |
|---|---|---|---|
| combate (`encuentro`/`emboscada`) | `hp_inicial`, `hp_max` | float | nuevo; solo si el VO los aporta |
| combate | `rival_hp_inicial`, `rival_hp_final`, `rival_hp_max` | float | nuevo; ídem |
| combate | `resolucion`, `victoria`, `hp_final`, `barrera_*_final/max`, `log`, `sub_combates`, `duration_loss` | — | sin cambios |
| `caramelo_familia` (raíz y hallazgo) | `candy_imagen` | string\|null | nuevo; sprite si `pokemon_id>0`, si no `candy_src` |
| `caramelo_familia` | `candy_src`, `candy_alt`, `candy_nombre` | — | sin cambios |
| `caramelo_tipo` | `candy_slug`, `candy_nombre`, `candy_src`, `candy_alt`, `oculto` | string/bool | resueltos; `oculto=true` solo si irresoluble |
| `caramelo_ev` | `stat_nombre`, `stat_slug`, `candy_src` | string | completados en hallazgos |

Compatibilidad: aditivo; histórico ausente = `null`/omitido. `EventoExploracion::aArray()` es lista blanca: sin extenderlo, cualquier futura reescritura borra las claves nuevas.

## 3. Contrato de presentación (`EventoExploracionVista`)

- `oculto: bool` en la base (siempre presente; `true` solo en `caramelo_tipo` irresoluble).
- `caramelo_tipo`: `candy_slug="acero"`, `candy_nombre="Acero"`, `candy_src="/images/candy_type/acero.webp"`, `candy_alt="Caramelo de tipo Acero"`; legacy respeta `slug`/`tipo_nombre`/`tipo_label`; si no hay nada → `oculto=true` y el evento no llega a la vista.
- `caramelo_familia`: `candy_imagen` (nueva); nombre resuelto vía mapa. Sin nombre: `candy_nombre=null` y blade mantiene «Pokémon».
- `caramelo_ev`: `stat_nombre="Ataque Especial"`, `stat_slug="atksp"`, `candy_src="/images/candy_ev/atksp.webp"`, `candy_nombre_js=null`.
- Combate: `rondas: int|null`, `rival_nombre: string|null`, `detalle_derrota: string|null`. `detalle_derrota` solo si `resolucion==='derrota'` y existe `hp_inicial`; ejemplo: «Cayó con 0/39 PS tras 4 rondas; a Bronzor le quedaban 12/34 PS». Sin datos → `null` y no se pinta (histórico intacto).
- `derrota`: `reason_texto` (`explorador_debilitado` → «se quedó sin PS»), conservando `reason`.

La vista no calcula: solo imprime `detalle_derrota`/`reason_texto` y usa `candy_imagen`.

## 4. Cambios por archivo y propietario (en orden)

**Backend**

1. `EventoExploracionVista.php`: `candy_imagen`; `tipo_id`→label/slug+`oculto`; `oculto=false` base; completar stat en `hallazgo/caramelo_ev`; corregir 3 slugs de `STATS`; `rondas`/`rival_nombre`/`detalle_derrota`; `reason_texto`. (Actualizar shapes PHPDoc.)
2. `DiarioExploracion.php`: `continue` si `oculto===true`, antes de `$transformados` y `$grupos`.
3. `PresentadorExploraciones.php`: `nombresPokemon()` recorre `bitacora` de terminadas; mismo filtro en `activaToView`. Sin tocar `equipo`.
4. `ExploradorVista.php`: fallback de `nombre()`.
5. `CombateExploracion.php`: capturar HP propio/rival en `combatirDatos()`.
6. `ResultadoBatallaExploracion.php`: 4 props opcionales `?float = null`; `toArray()` solo no-null.
7. `ProcesarExploracionHandler.php`: `resolucionCombate()` añade las 5 claves (filtrando `null`, conservando `0`).
8. `EventoExploracion.php`: constructor + `desdeArray` + `aArray` de las 5 claves.

**Frontend**

9. `resources/views/exploraciones/_evento.blade.php`: familia (hallazgo y raíz) usa `candy_imagen`; `detalle_derrota` en `encuentro` y `emboscada`; `reason_texto` en `derrota`. Conservar el WIP existente.

Secuencia estricta backend 1→8 y después 9: el contrato debe existir antes de consumirlo. Ningún archivo es compartido entre ambos roles.

## 5. Matriz de tests

| Archivo | Caso |
|---|---|
| `Unit/Exploraciones/EventoExploracionVistaTest` | familia con `pokemon_id`+mapa → `candy_nombre`/`candy_imagen`; sin mapa → null sin excepción |
| ídem | `caramelo_tipo` `tipo_id=9` → Acero/acero/src; inválido sin legacy → `oculto=true`; legacy `slug` sigue funcionando |
| ídem | `hallazgo/caramelo_ev` stat 4 con `completarStats` → `atksp`, no `spAtk` |
| ídem | combate perdido con claves nuevas → `rondas`/`detalle_derrota`; histórico sin claves → `null` |
| `Unit/Exploraciones/DiarioExploracionTest` | oculto no suma en `total` ni en `grupos`; familia de terminada resuelta |
| `Unit/Exploraciones/ExploradorVistaTest` | `nombre=null` + `pokemon` en relación → nombre del Pokémon; sin relación → `Sin reclutado` |
| `Unit/Exploraciones/CombateExploracionTest` | `hpInicial`, `rivalHpInicial/Final/Max` coherentes, con y sin `estadoInicial` |
| `Feature/Exploraciones/ProcesarExploracionCombateTest` | encuentro perdido persiste las 5 claves; `hp_inicial>0`; evento histórico renderiza sin error |
| `Feature/ExploracionesPageTest` | terminada con `caramelo_familia` no capturado → nombre+sprite; `tipo_id=9` visible; reclutado sin nombre → nombre del Pokémon en tarjeta y diario |
| `Feature/ExploracionesViewTest` | HTML sin «Sin reclutado»/«Desconocido»; `tipo_id` irresoluble no aparece |

## 6. Riesgos y rollback

- [medio] `oculto` reduce `total`: tests que cuentan eventos con fixtures irresolubles. Mitigación: fixtures válidos y caso explícito.
- [medio] EV cambia `stat_nombre`/desaparece `statName()`: revisar asserts de vista.
- [bajo] Íconos de especie ausentes → `onerror` actual cae a `0.webp`; aceptable.
- [bajo] `relationLoaded` devuelve `'Sin reclutado'` si un futuro llamador no eager-carga; documentar el requisito en el PHPDoc.
- **Rollback:** revertir los hunks de los 9 archivos. Sin migraciones, sin datos, sin dependencias; las claves nuevas son aditivas y el histórico no se reescribe.

## 7. Confirmación

No se ha modificado ningún archivo, migración, dependencia ni regla de combate/economía. Solo lecturas y `git status`.

## 8. Addendum v2 — Pérdidas legibles (2026-11-02)

- **Decisión:** la resolución de nombres/imágenes de `objetos_perdidos` es de presentación; no se cambia el contrato persistido (`tipo`, `id`, `label`, `cantidad_perdida`).
- **Presentador:** `PresentadorExploraciones::objetosPerdidosVista()` recibe las pérdidas y el mapa `$nombres`, y emite `tipo`, `tipo_label`, `id`, `label`, `imagen`, `cantidad_perdida`.
  - Familia: `CadenasEvolutivas::miembrosDe()` una sola vez para todas las cadenas; base por menor `species_id`; `imagen = /images/candy_pokemon/{baseId}.webp`.
  - EV: `StatEnum::fromId()`; `imagen = /images/candy_ev/{slug}.webp`.
  - Tipo: `label` persistido; `imagen = /images/candy_type/{id}.webp`.
  - Captura: `$nombres[$id]` o `Pokémon #id`; `imagen = /images/iconos_webp/{id}.webp`.
- **Resultado:** `ResultadoExploracionVista::objetosPerdidos()` conserva los campos enriquecidos y elimina el fallback `label => id`.
- **Vista:** partial `_perdidas.blade.php` reutilizado en la tarjeta de resultados y dentro del diario. El aviso mantiene `role="alert"` y el texto «⚠️ Perdiste objetos».
- **Compatibilidad:** datos históricos se resuelven al renderizar; no se reescribe la BD. Si no hay resolución, se muestra `tipo_label` y el `label` puede quedar null; el ID se conserva en `id` y como `title`.
