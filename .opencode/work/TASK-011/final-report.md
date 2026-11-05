# TASK-011 — Informe final (F1)

- **Estado:** `COMPLETED` (solo Fase 1)
- **Fecha:** 2026-11-04
- **Proyecto:** `/home/david/pokemon` (Laravel 12 / PHP 8.4)
- **Rama:** `task-011`, creada desde `master` (`cd57b27`). Sin `push`.
- **Plan aprobado:** v2, mediante «Aprobar plan v2 (Recomendado)».
- **Alcance cerrado:** modos de exploración, bonguri por hábitat y botín de bayas/bonguris.
- **Fases F2–F7:** no autorizadas; quedan pendientes de plan y aprobación propias.

## 1. Resumen

Se ha implementado la primera fase del sistema de objetos:

1. Las exploraciones aceptan un **modo** (`experiencia`, `reclutamiento`, `botin`). `null` conserva la mezcla histórica de eventos.
2. Cada hábitat tiene un **bonguri** asignado por provincia (Galicia blanco, Euskadi amarillo, Barcelona negro, Extremadura verde, Nova Alacant rosa, Mutxamel azul, Andalucía rojo; “Vacio” sin color, con fallback blanco).
3. Al finalizar una expedición se calcula un **botín de bayas y bonguris**, se persiste en `player_inventory`, se muestra en el resumen y se reduce en las derrotas.
4. El botín se regula con pesos, rangos y probabilidades centralizadas; **no hay tope duro fijo**.
5. Se corrigieron dos vectores detectados en revisión: el backfill provincial en instalaciones nuevas y el farmeo instantáneo mediante `recoger`/`return_time`.

## 2. Archivos creados

### Datos
- `database/migrations/2026_11_04_000001_add_modo_to_exploraciones_activas.php`
- `database/migrations/2026_11_04_000002_add_bonguri_to_habitats.php`
- `database/seeders/BonguriHabitatSeeder.php`

### Catálogo compartido
- `src/Shared/Domain/Items/CategoriaItem.php`
- `src/Shared/Domain/Items/ClaveBaya.php` (36 bayas)
- `src/Shared/Domain/Items/ClaveBonguri.php` (7 bonguris)
- `src/Shared/Domain/Items/DefinicionItem.php`
- `src/Shared/Domain/Items/DefinicionItemCollection.php`
- `src/Shared/Domain/Items/CatalogoItems.php`
- `src/Shared/Domain/Exceptions/ItemDesconocido.php`

### Dominio de exploración
- `src/Exploraciones/Domain/ModoExploracion.php`
- `src/Exploraciones/Domain/PesosEvento.php`
- `src/Exploraciones/Domain/PerfilExploracion.php`
- `src/Exploraciones/Domain/ContextoBotin.php`
- `src/Exploraciones/Domain/CalculadorBotinExploracion.php`
- `src/Exploraciones/Domain/Recompensas/RecompensaItem.php`
- `src/Exploraciones/Domain/Recompensas/RecompensaItemCollection.php`

## 3. Archivos modificados

- `app/Http/Controllers/ExploracionActivaController.php`: validación/persistencia/entrega de `modo`; mínimo de 60 min para `return_time`; comentarios actualizados.
- `app/Models/ExploracionActiva.php`: `modo` en `$fillable`; retirado el TODO de bayas.
- `app/Models/Habitat.php`: `bonguri` en `$fillable`.
- `app/Support/ItemCatalogo.php`: resolución de claves `baya:*` y `bonguri:*`.
- `database/seeders/CatalogoSeeder.php`: llama a `BonguriHabitatSeeder` después de `HabitatSeeder`.
- `routes/exploraciones.php`: comentario actualizado.
- `resources/views/habitats/show.blade.php`: selector accesible de modo, envío en preview/store y badge de modo.
- `resources/views/exploraciones/index.blade.php`: sección “Objetos encontrados” con icono, nombre y cantidad.
- `src/Exploraciones/App/FinalizarExploracionHandler.php`: perfil por modo, multiplicadores de EXP/caramelos/captura, botín antes de pérdidas, pérdidas de bayas/bonguris, `factorTiempo`.
- `src/Exploraciones/App/PersistirRecompensas.php`: `guardarItems()` en `player_inventory`.
- `src/Exploraciones/App/ProcesarExploracionHandler.php`: perfil de modo en eventos normales y extra.
- `src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php`: `items` aditivo al final y `sumarBotin()`.
- `src/Exploraciones/Domain/SimuladorEncuentros.php`: pesos de evento parametrizables.
- `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php`: `items` en el contrato persistido.
- `src/Exploraciones/Presentation/PresentadorExploraciones.php`: resolución de items y pérdidas.
- `src/Exploraciones/Presentation/ResultadoExploracionVista.php`: normalización de items y `tiene_items`.

## 4. Decisiones aplicadas

| ID | Decisión |
|---|---|
| D-01 | Un único objeto equipado por Pokémon (no se toca en F1). |
| D-02 | Usar un objeto consume la acción del aliado (F5). |
| D-03 | Objetos contra el enemigo solo aplican debuff (F5). |
| D-04 | Equipar descuenta inventario y exige propiedad (F6). |
| D-05 | Orden: exploración/botín → granja → forja → combate. |
| D-06 | Bonguri por hábitat con mapping provincial; “Vacio” → fallback blanco. |
| D-07 | `modo` nullable; `null` = mezcla histórica de eventos. |
| D-08 | Botín calculado al finalizar, aditivo y tolerante a ausencia. |
| D-09 | Solo el bonguri del hábitat puede caer en su expedición. |
| D-10 | Sin tope duro por expedición; balance por pesos/probabilidades. |
| D-11 | `factorTiempo` para botín proporcional al tiempo efectivo; base `inicioVuelta` (75 % de la duración), 60 min para indefinidas. |
| D-12 | `multiplicadorBotin` escala la probabilidad de bonguri (BOTIN 60 %→90 %; EXPERIENCIA/RECLUTAMIENTO 25 %→12,5 %). |
| D-13 | `return_time` debe estar al menos 60 min en el futuro. |

Las decisiones D-11 a D-13 se registraron en el addendum del plan v2; no amplían el alcance de F1.

## 5. Verificación ejecutada

### Migraciones
- `php artisan migrate --no-interaction`: lote [20], ambas migraciones `Ran`.
- `modo`: `varchar(20) NULL`; `bonguri`: `varchar(16) NULL`.
- Backfill local: blanco 8, amarillo 6, negro 4, verde 7, rosa 8, azul 13, rojo 9, NULL 1; total 56; sin valores fuera del enum.
- `BonguriHabitatSeeder` idempotente; verificado en esquema PostgreSQL temporal limpio (56 hábitats, 55 con color, “Vacio” NULL).

### Análisis estático
- `php -l`: sin errores en todos los PHP nuevos/modificados.
- `phpstan` (nivel 6): 34 errores preexistentes; **ninguno** en archivos de F1.
- `deptrac`: 113 violaciones preexistentes; **ninguna** en las clases nuevas.
- `php artisan view:cache` / `view:clear`: correctos.
- `php artisan route:list --path=exploraciones`: 7 rutas esperadas.
- `git diff --check` (excluyendo `MVP.txt`): limpio.
- `git status -- tests/`: vacío; no se modificaron tests.
- `composer.json`, `composer.lock`, `package.json`, `package-lock.json`: sin cambios.

### Smoke tests (sin escrituras)
- `ModoExploracion::desde(null)` → `mixto`; pesos de `botin` → 20/45/10/15/10.
- Catálogo → 43 definiciones (36 bayas + 7 bonguris).
- `baya:oran` → Aranja + icono; `bonguri:azul` → Azul + PNG.
- Botín determinista:
  - `factorTiempo=1.0`, `multiplicadorBotin=1.0`, RNG 0.0 → 1 baya + 1 bonguri.
  - `factorTiempo=0.0` → 0 items.
  - `factorTiempo=0.5`, `multiplicadorBotin=1.5`, RNG 0.6 → 0 items.
  - `factorTiempo=1.0`, `multiplicadorBotin=1.5`, RNG 0.7 → 3 bayas + 1 bonguri.
- `return_time` a 1 min → 422; a 2 h → OK; a 40 min → 422.
- `factorTiempo`: 1 h/45 min → 1.0; 1 h/22-23 min → ~0.49-0.51; 72 h/54 h → 1.0; indefinida/30 min → 0.5; 1 h/1 min → ~0.022.

### Tests
- **No se crearon, actualizaron, repararon ni ejecutaron tests.** `tests/` queda fuera de alcance.

## 6. Revisión y seguridad

- **Revisión independiente:** `APPROVED`. Los dos hallazgos altos (backfill en instalación nueva y `multiplicadorBotin` muerto) quedaron resueltos. Hallazgos menores documentados: preview sin multiplicadores de modo, doble escalado del bonguri, seeder que sobrescribe, setup sin seeders.
- **Auditoría de seguridad:** `APPROVED`. Farmeo instantáneo y bypass por `return_time` cerrados. Sin hallazgos críticos ni altos. Residuales: idempotencia dependiente del lock de caché, sin rate limit por usuario, avisos de dependencias preexistentes.
- Ambos informes están en `review.md` y `security.md`.

## 7. Riesgos residuales

1. **Preview de recompensas esperadas:** no aplica los multiplicadores del modo; el botín real sí. Limitación conocida de F1.
2. **`modo = null`:** conserva la mezcla histórica de eventos, pero ahora también recibe botín.
3. **Balance:** BOTIN tiene ~90 % de bonguri; EXPERIENCIA/RECLUTAMIENTO ~12,5 %. Requiere telemetría y ajuste.
4. **Instalaciones nuevas:** el mapping provincial depende de ejecutar `CatalogoSeeder` (o `BonguriHabitatSeeder`). Sin seeders, el fallback blanco garantiza un valor válido.
5. **Idempotencia:** `regreso` + transacción + lock de caché; considerar `lockForUpdate` si se quiere blindaje adicional.
6. **Rate limit:** no hay throttle específico por usuario en `store-individual`/`recoger`; recomendable en una fase posterior.
7. **Seeder provincial:** reasigna `bonguri` en cada arranque con `RUN_SEEDERS=true`; hoy no hay edición manual.
8. **Deuda preexistente:** `phpstan` 34 errores, `deptrac` 113 violaciones y avisos de dependencias (Laravel 12.67.0, Livewire 4.4.2, Symfony 7.4.17, `league/commonmark`). No introducidos por F1.
9. **Deriva de datos preexistente:** `storage/data/habitats.csv` y los `province_id`/Pokémon locales no coinciden plenamente; una instalación limpia no reproduce exactamente los conteos locales. Fuera de alcance.

## 8. Próximos pasos

1. **F2 — Plantación y cosecha en nidos:** `nest_plantings`, `PlantarBaya`, `CosecharPlantacion`, 2 huecos por nivel, 24 h, stock máximo 3, UI de huecos.
2. **F3 — Influencia y bonus de hábitat:** `EsDelHabitat`, factores de plantación en el botín, auto-cosecha con `RECOLECTOR` + `BOTIN`.
3. **F4 — Forja:** `src/Forja`, catálogo de recetas, `Fabricar`, UI.
4. **F5 — Uso de objetos en combate:** `AccionObjeto`, efectos, fases Livewire, descuento de inventario.
5. **F6 — Equipables con coste e iconos definitivos.**
6. **F7 — Botín de bayas/bonguris en combates de ruta/entrenador.**
7. **Iconos pendientes:** falta `roseli-berry.webp` (Baya Hibis/Hada) y los 39 iconos de pociones, recipiente, mejoras, reductores y resistencias; los 7 bonguris son PNG.

## 9. Estado del árbol de trabajo

- Rama `task-011`; cambios **sin commitear** y sin `push`.
- Cambios locales preexistentes y ajenos, intactos: `.githooks/pre-commit` y `MVP.txt`.
- No se ejecutaron operaciones destructivas (`reset`, `clean`, `checkout --`, `migrate:fresh` sobre la BD principal).
- El desarrollador debe revisar el diff y decidir el commit. No se incluyen `MVP.txt` ni `.githooks/pre-commit` en el alcance.
