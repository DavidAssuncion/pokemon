# TASK-056 — Informe final

- **ID:** TASK-056
- **Fecha:** 2026-11-18
- **Estado:** `IMPLEMENTING` — F1 cerrada (incl. UI v2), F2 implementada y aprobada; F3 bloqueada; documentación pendiente por permisos.
- **Plan aprobado:** v1 (`Aprobar plan v1`) y v2 (`Aprobar plan v2`).
- **Tests:** no se han creado, modificado ni ejecutado (política del proyecto: testing diferido).

## 1. Resumen

Se añadió a la Forja una sección de **Armas, Armaduras y Amuletos** por familia evolutiva con nido
construido, fabricables con bonguri/recipiente/baya y evolución por consumo de la pieza anterior, y
se implementó el **equipamiento de 3 slots** por reclutado con aplicación de stats y pasivas en los
5 modos de combate y CP.

La UI final muestra **una tarjeta por familia**; al pulsarla se abre un **modal de craft** con
pestañas por etapa/especie, selector de bioma y los 3 tipos de ítem.

## 2. Fases

| Fase | Estado | Revisión | Seguridad |
|---|---|---|---|
| F1 — Forja (dominio, API, UI) | Cerrada | APPROVED | APPROVED |
| F1-UI v2 — tarjeta por familia + modal | Cerrada | APPROVED | APPROVED |
| F2 — equipamiento 3 slots | Implementada | APPROVED | APPROVED |
| F2 — batalla + CP + serializer | Implementada | APPROVED | APPROVED |
| F3 — UI de equipos | BLOQUEADA | — | — |
| Documentación (`src/Forja/context.md`) | BLOQUEADA | — | — |

## 3. Archivos

### F1 — Forja

- `src/Shared/Domain/Items/ClavePieza.php` (nuevo)
- `src/Shared/Domain/Items/CategoriaItem.php` (+3 casos)
- `src/Shared/Domain/Ports/InventarioJugador.php` (+`bloquearClave`)
- `src/Shared/Infra/Inventario/InventarioJugadorEloquent.php` (carve-out + bloqueo)
- `src/Forja/Domain/Piezas/**` (tipos, reglas, calculadora, resolvedor, catálogo, DTOs, colecciones)
- `src/Forja/Domain/Ports/{NidosConstruidos,DefinicionesPieza,PiezasPorEspecie}.php`
- `src/Forja/App/{ListarPiezasForja,FabricarPieza,ListarRecetas}.php`
- `src/Forja/App/DataTransferObjects/{RecetaDetalle,ResultadoFabricacion}.php`
- `src/Forja/Infra/Repositorios/LectorFamiliasPiezas.php`
- `src/Forja/Infra/Controllers/ForjaController.php`
- `src/Granjas/Infra/Nidos/NidosConstruidosEloquent.php`
- `app/Providers/AppServiceProvider.php` (bindings)

### F1-UI v2

- `resources/views/forja/index.blade.php` (tarjeta por familia, modal, pestañas, 3 tipos, selector de bioma)

### F2 — Equipamiento

- `database/migrations/2026_11_17_000001_create_recruit_gear_table.php` (nuevo; **no ejecutada**)
- `app/Models/RecruitGear.php`
- `src/Equipos/Domain/ValueObjects/EquipamientoPiezas.php`
- `src/Equipos/Domain/Collections/EquipamientosPorReclutado.php`
- `src/Equipos/Domain/Ports/PiezasEquipadas.php`
- `src/Equipos/App/Piezas/{VerificadorReclutadoPropio,ObtenerPiezas,EquiparPieza,DesequiparPieza}.php`
- `src/Equipos/Infra/{Controllers/PiezasReclutadoController,Requests/EquiparPiezaRequest,Repositorios/PiezasEquipadasEloquent}.php`
- `routes/player.php` (GET/PUT/DELETE `/api/reclutados/{reclutado}/piezas[/{slot}]`)

### F2 — Batalla, CP y serializer

- `src/Pokemon/Domain/Stats/AplicadorBonusPiezas.php`
- `src/Forja/Domain/Piezas/{AgregadorBonosPiezas.php,DataTransferObjects/BonosEquipamiento.php}`
- `src/CombateEntrenadores/App/{MapeadorPokemonBatalla,ConstruirEquipoJugador}.php`
- `src/Exploraciones/App/CombateExploracion.php`
- `app/Support/Piezas/{SlotsPiezas,PiezasReclutado,FabricaPiezasReclutado}.php`
- `app/Support/Pc/{CalculadoraPcReclutado,ObtenerPcEquipoJugador}.php`
- `app/Support/ReclutadoSerializer.php`
- `app/Http/Controllers/{PlayerController,ReclutadoController}.php`
- `phpstan-baseline.php` (retirada de una entrada huérfana)

## 4. Decisiones

- Identidad de pieza por `{tipo}:{species_id}` en `player_inventory`; sin instancias.
- Evolución = receta que consume la pieza anterior.
- Gates por nido: cadenas de 3 etapas 1/3/5; de 2 etapas 2/4; sin evolución 3.
- Stats: Arma `Atk+SpA`; Armadura `Def+SpD+HP`; Amuleto `Vel + 50 %` del mayor de `Atk/Def/SpA/SpD`;
  factor por etapa `0.5/0.75/1.0`.
- Ingredientes: bonguri ×(1+etapa) + recipiente ×1 + baya rara ×etapa + pieza anterior desde etapa 2.
- Una receta por nido; una tarjeta por familia con modal y selector de bioma.
- `categoria` = clave de sección (`piezas`, `medicinas`, …); `tipo_pieza` = arma/armadura/amuleto.
- Persistencia de slots en `recruit_gear`; `obj_equipados` intacto.
- Batalla: bonos colapsados en stats base y pasivas en `effectKeys`; **sin tocar el motor** y
  `SESSION_VERSION` sigue en 21.
- D3: una unidad por jugador y clave, contando inventario **y** equipamiento.
- Locks globales: nido/reclutado → claves de inventario ordenadas → fila de equipamiento; reintentos 3.

## 5. Verificación

- `php -l` de todos los archivos tocados: OK.
- Pint (`--dirty`): OK.
- PHPStan L6 dirigido al alcance TASK-056: **0 errores**.
- `composer run analyse` global: 35 errores, todos preexistentes/WIP ajeno (PlayerController,
  Sendas, `src/Granjas/App`, `Reclutado::lockForUpdate`, `varTag.type`), ninguno de TASK-056.
- `php artisan view:cache`: OK.
- `php artisan route:list --path=forja`: 3 rutas; `--path=piezas`: 3 rutas.
- `git diff --check`: limpio.
- Deptrac: sin violaciones de Forja (104 globales, report-only).
- **Sin tests** (política del proyecto).

## 6. Hallazgos de revisión y seguridad

- F1 1ª revisión: 2 HIGH + 3 MEDIUM → corregidos (lock de unicidad, etapas sin padre, puertos,
  anclaje de regex, mensaje genérico).
- F2 1ª revisión: deadlock AB/BA y D3 roto con pieza equipada → corregidos.
- Revisión final F2: 2 MEDIUM (piezas vacías en `evolucionar`/`actualizarLoadout` y deadlock
  Fabricar↔Equipar) → corregidos (C17); LOW final de baseline → corregido (C18).
- Seguridad: APPROVED en F1 y F2. Sin CRITICAL/HIGH.
- Deuda aceptada: `throttle` ausente, sin CHECK de `slot`, cascada de etapa omitida, piezas sin
  consumidor UI hasta F3, `recruit_gear.slot` corrupto solo por escritura directa.

## 7. Riesgos residuales

- ~~La migración `recruit_gear` no se ha ejecutado~~: **aplicada el 2026-11-18** (única pendiente);
  tabla verificada con FK cascade, dos uniques e índice `item_key`. La consulta de
  `ObtenerPiezas::paraReclutados` ya no lanza `42P01`.
- `docs/context.md` y `SESSION_VERSION` del contexto están desactualizados (el código real es 21).
- El WIP ajeno mantiene `composer run analyse` en rojo (pre-push bloqueado hasta consolidarlo).
- F3 no puede empezar hasta confirmar el WIP de `resources/views/equipos/*` y `resources/css/*`.
- La documentación del módulo sigue bloqueada por permisos del agente `documenter` sobre `src/**`.

## 8. Próximos pasos

1. Autorizar y ejecutar `php artisan migrate` para crear `recruit_gear`.
2. Aplicar el permiso del `documenter` y crear `src/Forja/context.md` + registrar la decisión
   `categoria` = sección.
3. Confirmar el WIP de equipos/CSS y ejecutar F3 (partial de 3 slots, CP visible, assets).
4. QA visual de la Forja (tarjeta por familia, modal, fabricar, motivo `pieza_anterior_equipada`).
5. Testing específico del módulo cuando el desarrollador lo solicite (diferido).
