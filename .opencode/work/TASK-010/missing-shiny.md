# TASK-010 — Sprites shiny faltantes

- **Fecha:** 2026-11-04
- **Fuente:** `public/images/iconos_webp/` (1.200 WebP) vs `public/images/iconos_webp/shiny/` (1.137 WebP `_s`).
- **Resultado:** 64 *stems* normales sin `_s`: **59 Pokémon reales** + 4 assets legacy no alcanzables + el placeholder `0`.
- **Nota:** la mayoría de los 59 existen con otro nombre (`{baseId}_f{formId}_s.webp`) y se pueden remapear a `{id}_s.webp`. `905 enamorus-incarnate` no tiene fuente shiny local.

## 1. Especie base (1)

| ID | Nombre |
|---|---|
| 905 | enamorus-incarnate |

## 2. Formas de Alola (18)

| ID | Nombre | ID | Nombre |
|---|---|---|---|
| 10091 | rattata-alola | 10104 | ninetales-alola |
| 10092 | raticate-alola | 10105 | diglett-alola |
| 10100 | raichu-alola | 10106 | dugtrio-alola |
| 10101 | sandshrew-alola | 10107 | meowth-alola |
| 10102 | sandslash-alola | 10108 | persian-alola |
| 10103 | vulpix-alola | 10109 | geodude-alola |
| 10110 | graveler-alola | 10113 | muk-alola |
| 10111 | golem-alola | 10114 | exeggutor-alola |
| 10112 | grimer-alola | 10115 | marowak-alola |

## 3. Formas de Galar (20)

| ID | Nombre | ID | Nombre |
|---|---|---|---|
| 10161 | meowth-galar | 10171 | moltres-galar |
| 10162 | ponyta-galar | 10172 | slowking-galar |
| 10163 | rapidash-galar | 10173 | corsola-galar |
| 10164 | slowpoke-galar | 10174 | zigzagoon-galar |
| 10165 | slowbro-galar | 10175 | linoone-galar |
| 10166 | farfetchd-galar | 10176 | darumaka-galar |
| 10167 | weezing-galar | 10177 | darmanitan-galar-standard |
| 10168 | mr-mime-galar | 10178 | darmanitan-galar-zen |
| 10169 | articuno-galar | 10179 | yamask-galar |
| 10170 | zapdos-galar | 10180 | stunfisk-galar |

## 4. Formas de Hisui (16)

| ID | Nombre | ID | Nombre |
|---|---|---|---|
| 10229 | growlithe-hisui | 10237 | lilligant-hisui |
| 10230 | arcanine-hisui | 10238 | zorua-hisui |
| 10231 | voltorb-hisui | 10239 | zoroark-hisui |
| 10232 | electrode-hisui | 10240 | braviary-hisui |
| 10233 | typhlosion-hisui | 10241 | sliggoo-hisui |
| 10234 | qwilfish-hisui | 10242 | goodra-hisui |
| 10235 | sneasel-hisui | 10243 | avalugg-hisui |
| 10236 | samurott-hisui | 10244 | decidueye-hisui |

## 5. Formas de Paldea (4)

| ID | Nombre |
|---|---|
| 10250 | tauros-paldea-combat-breed |
| 10251 | tauros-paldea-blaze-breed |
| 10252 | tauros-paldea-aqua-breed |
| 10253 | wooper-paldea |

## 6. Assets legacy no alcanzables (4)

| Stem normal | Motivo |
|---|---|
| `3_f950` | No existe fila en `pokemon`; el código de producción no genera `formSuffix`. |
| `6_f951` | Ídem. |
| `9_f952` | Ídem. |
| `901_f1` | El normal es `901_f1.webp`, pero el shiny existente es `901_f_s.webp`; no hay fila `901_f1` en `pokemon`. |

## 7. Placeholder (1)

| Stem | Motivo |
|---|---|
| `0` | Placeholder de especie 0; se usa en `CombatienteVista.php:265` y `BatallaVistaPresenter.php:417` como fallback. No es un Pokémon. |

## 8. Ambigüedades de remapeo

Hay 51 formas con un único candidato `{baseId}_f{formId}_s.webp`. Estas 8 requieren validación con `pokeapi/data/v2/csv/pokemon_forms.csv` e inspección visual:

- `10107 meowth-alola` y `10161 meowth-galar` (misma especie base, dos formas).
- `10177 darmanitan-galar-standard` y `10178 darmanitan-galar-zen`.
- `10250`, `10251`, `10252` tauros-paldea (tres formas).
- `905 enamorus-incarnate` (solo hay formas Therian `_f2802`/`_f2803`; no hay `905_s`).

## 9. Conclusión

- **59 Pokémon reales** sin `{id}_s.webp`.
- **51** se pueden remapear de forma unívoca desde los `_f` existentes.
- **8** requieren validación; **905** no tiene fuente local y quedaría con fallback a normal salvo que se obtenga de PokeAPI/Showdown con licencia compatible.
- **4** assets legacy no son alcanzables y no forman parte del requisito.
