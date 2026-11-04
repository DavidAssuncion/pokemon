# TASK-010 — Sprites shiny faltantes

- **Fecha:** 2026-11-04
- **Fuente:** `public/images/iconos_webp/` (1.200 WebP) vs `public/images/iconos_webp/shiny/` (1.137 WebP `_s`).
- **Resultado:** 64 *stems* normales sin `_s`: **59 Pokémon reales** + 4 assets legacy no alcanzables + el placeholder `0`.
- **Revisión 2026-11-04:** **59/59 tienen un shiny local** con formato `{baseId}_f{formId}_s.webp`. El mapeo se verificó por hash de la imagen normal decodificada: el `_f` normal es idéntico al `{id}.webp` de la forma regional. **0 no encontrados, 0 ambiguos, 0 sin shiny.**
- **Conclusión:** no hace falta buscar assets externos. Basta copiar/renombrar cada `{baseId}_f{formId}_s.webp` a `{id}_s.webp`; `x-sprite` ya construye esa URL.

## 1. Mapeo verificado (59/59)

| ID | Nombre | Asset normal `_f` | Shiny local |
|---|---|---|---|
| 905 | enamorus-incarnate | `905_f2802` | `905_f2802_s.webp` |
| 10091 | rattata-alola | `19_f46` | `19_f46_s.webp` |
| 10092 | raticate-alola | `20_f48` | `20_f48_s.webp` |
| 10100 | raichu-alola | `26_f50` | `26_f50_s.webp` |
| 10101 | sandshrew-alola | `27_f52` | `27_f52_s.webp` |
| 10102 | sandslash-alola | `28_f54` | `28_f54_s.webp` |
| 10103 | vulpix-alola | `37_f56` | `37_f56_s.webp` |
| 10104 | ninetales-alola | `38_f58` | `38_f58_s.webp` |
| 10105 | diglett-alola | `50_f60` | `50_f60_s.webp` |
| 10106 | dugtrio-alola | `51_f62` | `51_f62_s.webp` |
| 10107 | meowth-alola | `52_f64` | `52_f64_s.webp` |
| 10108 | persian-alola | `53_f66` | `53_f66_s.webp` |
| 10109 | geodude-alola | `74_f68` | `74_f68_s.webp` |
| 10110 | graveler-alola | `75_f70` | `75_f70_s.webp` |
| 10111 | golem-alola | `76_f72` | `76_f72_s.webp` |
| 10112 | grimer-alola | `88_f74` | `88_f74_s.webp` |
| 10113 | muk-alola | `89_f76` | `89_f76_s.webp` |
| 10114 | exeggutor-alola | `103_f78` | `103_f78_s.webp` |
| 10115 | marowak-alola | `105_f80` | `105_f80_s.webp` |
| 10161 | meowth-galar | `52_f2335` | `52_f2335_s.webp` |
| 10162 | ponyta-galar | `77_f2336` | `77_f2336_s.webp` |
| 10163 | rapidash-galar | `78_f2337` | `78_f2337_s.webp` |
| 10164 | slowpoke-galar | `79_f2582` | `79_f2582_s.webp` |
| 10165 | slowbro-galar | `80_f2583` | `80_f2583_s.webp` |
| 10166 | farfetchd-galar | `83_f2338` | `83_f2338_s.webp` |
| 10167 | weezing-galar | `110_f944` | `110_f944_s.webp` |
| 10168 | mr-mime-galar | `122_f2339` | `122_f2339_s.webp` |
| 10169 | articuno-galar | `144_f2801` | `144_f2801_s.webp` |
| 10170 | zapdos-galar | `145_f2800` | `145_f2800_s.webp` |
| 10171 | moltres-galar | `146_f2799` | `146_f2799_s.webp` |
| 10172 | slowking-galar | `199_f2584` | `199_f2584_s.webp` |
| 10173 | corsola-galar | `222_f2340` | `222_f2340_s.webp` |
| 10174 | zigzagoon-galar | `263_f946` | `263_f946_s.webp` |
| 10175 | linoone-galar | `264_f948` | `264_f948_s.webp` |
| 10176 | darumaka-galar | `554_f2341` | `554_f2341_s.webp` |
| 10177 | darmanitan-galar-standard | `555_f2342` | `555_f2342_s.webp` |
| 10178 | darmanitan-galar-zen | `555_f2343` | `555_f2343_s.webp` |
| 10179 | yamask-galar | `562_f2344` | `562_f2344_s.webp` |
| 10180 | stunfisk-galar | `618_f2345` | `618_f2345_s.webp` |
| 10229 | growlithe-hisui | `58_f2792` | `58_f2792_s.webp` |
| 10230 | arcanine-hisui | `59_f2793` | `59_f2793_s.webp` |
| 10231 | voltorb-hisui | `100_f2728` | `100_f2728_s.webp` |
| 10232 | electrode-hisui | `101_f2735` | `101_f2735_s.webp` |
| 10233 | typhlosion-hisui | `157_f2786` | `157_f2786_s.webp` |
| 10234 | qwilfish-hisui | `211_f2788` | `211_f2788_s.webp` |
| 10235 | sneasel-hisui | `215_f2794` | `215_f2794_s.webp` |
| 10236 | samurott-hisui | `503_f2787` | `503_f2787_s.webp` |
| 10237 | lilligant-hisui | `549_f2789` | `549_f2789_s.webp` |
| 10238 | zorua-hisui | `570_f2796` | `570_f2796_s.webp` |
| 10239 | zoroark-hisui | `571_f2797` | `571_f2797_s.webp` |
| 10240 | braviary-hisui | `628_f2798` | `628_f2798_s.webp` |
| 10241 | sliggoo-hisui | `705_f2790` | `705_f2790_s.webp` |
| 10242 | goodra-hisui | `706_f2791` | `706_f2791_s.webp` |
| 10243 | avalugg-hisui | `713_f2795` | `713_f2795_s.webp` |
| 10244 | decidueye-hisui | `724_f2785` | `724_f2785_s.webp` |
| 10250 | tauros-paldea-combat-breed | `128_f3006` | `128_f3006_s.webp` |
| 10251 | tauros-paldea-blaze-breed | `128_f3007` | `128_f3007_s.webp` |
| 10252 | tauros-paldea-aqua-breed | `128_f3008` | `128_f3008_s.webp` |
| 10253 | wooper-paldea | `194_f3009` | `194_f3009_s.webp` |

> Corrección de la revisión: `905_f2802` es la forma **incarnate** (su normal es idéntico a `905.webp`), no la Therian. Por tanto `905_f2802_s` sí sirve como shiny de `enamorus-incarnate`. El análisis anterior que decía «sin fuente local» era incorrecto.

## 2. Por qué el formato `27_f52_s`

- `27` es el **ID de la especie base** (sandshrew).
- `52` es un **ID de forma interno/legacy** del set UICONS, no el `form_id` actual de PokeAPI (que para sandshrew-alola es `10203`).
- La comprobación por hash demuestra que `27_f52.webp` es idéntico a `10101.webp` (sandshrew-alola) y que `27_f52_s.webp` es su shiny. El formato es correcto; solo hay que remapear el nombre.

## 3. Assets legacy no alcanzables (4)

| Stem normal | Motivo |
|---|---|
| `3_f950` | No existe fila en `pokemon`; el código de producción no genera `formSuffix`. |
| `6_f951` | Ídem. |
| `9_f952` | Ídem. |
| `901_f1` | El normal es `901_f1.webp`, pero el shiny existente es `901_f_s.webp`; no hay fila `901_f1` en `pokemon`. |

## 4. Placeholder (1)

| Stem | Motivo |
|---|---|
| `0` | Placeholder de especie 0; se usa en `CombatienteVista.php:265` y `BatallaVistaPresenter.php:417` como fallback. No es un Pokémon. |

## 5. Acción ejecutada

Se copiaron los 59 `_f_s` a `public/images/iconos_webp/shiny/{id}_s.webp` el 2026-11-04. Verificación: 59/59 existen, hash origen = hash destino, 0 incidencias. No requirió cambios de código: `x-sprite` ya usa `{id}_s.webp`.

Pendientes solo el placeholder `0` y los 4 legacy no alcanzables.
