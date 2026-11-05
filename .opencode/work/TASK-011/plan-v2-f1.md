# TASK-011 — Plan v2 — Fase 1: exploración por modos, bonguri y botín

- **Estado:** `AWAITING_APPROVAL`
- **Fecha:** 2026-11-04
- **Proyecto:** `/home/david/pokemon`
- **Base:** rama `master`, HEAD `cd57b27`.
- **Rama propuesta:** `task-011` desde `master`, sin `push`.
- **Referencias:** `analysis.md` (A1 + A2) y `architecture.md`.
- **Alcance de este plan:** solo **F1**. Las fases F2–F7 se aprueban por separado.
- **Cambio respecto a v1:** se eliminan los topes duros por expedición; el botín se regula con pesos, probabilidades y constantes ajustables. Se registra el mapping provincial de bonguris confirmado por el desarrollador.

## 1. Objetivo

Implementar la primera fase del sistema:

1. Añadir un **modo de exploración** configurable: `experiencia`, `reclutamiento` o `botin`.
2. Asignar un **bonguri único por hábitat** y hacer que las expediciones solo puedan devolver el de su hábitat.
3. Añadir **botín final de bayas y bonguris** a las exploraciones, persistido en `player_inventory`, mostrado en el resumen y descontado en las pérdidas por derrota.
4. No implementar todavía forja, uso de objetos en combate, plantación en nidos ni iconos nuevos.

## 2. Fuera de alcance (F1)

- Forja y recetas (`src/Forja`).
- Uso de objetos en combate (`AccionObjeto`, `ServicioUsoObjetos`, fases Livewire).
- Plantación, crecimiento y cosecha en nidos (`nest_plantings`).
- Bonus por Pokémon del hábitat y auto-cosecha (F3).
- Equipamiento con descuento de inventario (F6).
- Botín en combates de ruta/entrenador (F7).
- Iconos nuevos de pociones, recipiente, mejoras, reductores y resistencias.
- Tests: no se crean, actualizan ni ejecutan.

## 3. Decisiones aplicadas

| ID | Decisión |
|----|----------|
| D-01 | 1 objeto equipado por Pokémon (no se toca en F1). |
| D-02 | Usar objeto consume la acción del aliado (F5). |
| D-03 | Objetos contra enemigo solo debuff (F5). |
| D-04 | Equipar descuenta inventario (F6). |
| D-05 | Orden: exploración/botín → forja → combate. |
| D-06 | Un bonguri por hábitat, con backfill desde provincia. |
| D-07 | Modo nullable; `null` = comportamiento actual (`MIXTO`). |
| D-08 | Botín calculado al finalizar, aditivo y tolerante a ausencia. |
| D-09 | Solo el bonguri del hábitat puede caer en esa expedición. |
| D-10 | Sin tope duro por expedición: el botín se regula con pesos, probabilidades y constantes ajustables. |

## 4. Arquitectura de F1

### 4.1 Catálogo compartido

Crear `src/Shared/Domain/Items/`:

- `CategoriaItem` (enum: `baya`, `bonguri`; se dejan preparadas las demás categorías para fases posteriores).
- `ClaveBaya` (36 casos; slug inglés; nombre español; icono `/images/berry/{slug}-berry.webp`).
- `ClaveBonguri` (7 colores; icono PNG actual; `porDefecto()` = `blanco`).
- `DefinicionItem` (readonly: clave, nombre, categoría, equipable, usable en combate, consumible, icono, descripción).
- `CatalogoItems` (mapa estático, `existe`, `definicion`, `porCategoria`, `aPartirDeItemKey`).
- `DefinicionItemCollection` y `ItemDesconocido` (excepción de dominio).

No se crea tabla de catálogo. `sitrus_berry`/`lum_berry` siguen siendo las claves de combate existentes; `baya:sitrus`/`baya:lum` son claves de inventario con mapping explícito.

### 4.2 Exploración

- `ModoExploracion` (`experiencia`, `reclutamiento`, `botin`, `mixto` legacy).
- `PesosEvento` (VO que valida suma 100).
- `PerfilExploracion` (modo + pesos + multiplicadores de EXP, caramelos, captura y botín).
- `SimuladorEncuentros::generarEventosDesdePool(..., ?PerfilExploracion $perfil = null)`: cambio aditivo; sin perfil usa las constantes actuales.
- `RecompensaItem` y `RecompensaItemCollection`.
- `ContextoBotin` (modo, bonguri del hábitat, nivel, duración, categoría, derrota).
- `CalculadorBotinExploracion` (dominio puro, aleatorio inyectable).
- `ResultadoRecompensas`: nueva propiedad `items` al final del constructor con default `[]`; nuevo `sumarBotin()`.
- `FinalizarExploracionHandler`: calcula el botín tras las pérdidas y antes de persistir; incluye items en `objetosPerdidos`/`restarPerdidas`.
- `PersistirRecompensas`: incrementa `player_inventory` para cada `RecompensaItem`.
- `TransformadorResultadoExploracion`, `PresentadorExploraciones`, `ResultadoExploracionVista`: exponen `items` resueltos (nombre, icono, categoría, cantidad).
- `ItemCatalogo::resolve()`: ramas `baya:` y `bonguri:`; fallback `Desconocido` intacto.

### 4.3 Datos

Migraciones:

1. `add_modo_to_exploraciones_activas`: `modo VARCHAR(20) NULL`, sin índice.
2. `add_bonguri_to_habitats`: `bonguri VARCHAR(16) NULL`, backfill determinista desde `province_id` según `ProvinceSeeder`; provincia “Vacío” queda `NULL` con fallback en dominio.

Sin tablas nuevas en F1. Sin cambios destructivos.

**Mapping provincial de bonguris (backfill):**

| Provincia | Bonguri |
|---|---|
| Galicia | Blanco |
| Euskadi | Amarillo |
| Barcelona | Negro |
| Extremadura | Verde |
| Nova Alacant | Rosa |
| Mutxamel | Azul |
| Andalucía | Rojo |
| Vacío | `NULL`; fallback `blanco` en dominio |

### 4.4 Pesos y botín de F1

**Pesos de evento por modo** (suman 100; `null`/legacy = `mixto`):

| Modo | Encuentro | Hallazgo | Especial | Contratiempo | Neutral |
|---|---:|---:|---:|---:|---:|
| `mixto` | 45 | 20 | 15 | 10 | 10 |
| `experiencia` | 65 | 5 | 15 | 10 | 5 |
| `reclutamiento` | 55 | 10 | 15 | 10 | 10 |
| `botin` | 20 | 45 | 10 | 15 | 10 |

**Multiplicadores por modo:**

| Modo | EXP | Caramelos | Captura | Botín |
|---|---:|---:|---:|---:|
| `mixto` | ×1,00 | ×1,00 | ×1,00 | ×1,00 |
| `experiencia` | ×1,25 | ×0,75 | ×0,50 | ×0,50 |
| `reclutamiento` | ×0,75 | ×0,75 | ×2,00 | ×0,50 |
| `botin` | ×0,50 | ×0,50 | ×0,50 | ×1,50 |

**Bayas (peso base por rareza):**

| Grupo | Bayas | Peso |
|---|---|---:|
| Común | Aranja, Zidra, Zreza, Meloc, Safre, Perasi | 10 |
| Poco común | Algama, Ispero, Meluce, Uvav, Tamate | 6 |
| Rara | Lichi, Gonlan, Aslac, Yapati, Aricoc, Zonlan, Lagro | 3 |
| Tipo | Las 18 bayas de tipo | 2 |

La selección es ponderada y sin reemplazo dentro de la misma expedición cuando sea posible. La cantidad de tiradas se fija en constantes por modo (`botin` 1–3, `experiencia` 0–1, `reclutamiento` 0–1, `mixto` 0–2). **No hay tope duro por expedición**: estos rangos, pesos y probabilidades son parámetros de balance centralizados que se ajustarán con telemetría.

**Bonguri:**

| Modo | Probabilidad base |
|---|---:|
| `mixto` | 30 % |
| `experiencia` | 25 % |
| `reclutamiento` | 25 % |
| `botin` | 60 % |

Solo cae el bonguri del hábitat. En F1 se hace una tirada por expedición; si cae, se entrega 1 bonguri. No se impone un tope duro adicional: la frecuencia se regula con la probabilidad y con las constantes de balance. En F1 no se aplica el bonus de hábitat (F3).

### 4.5 UI

- `resources/views/habitats/show.blade.php`: selector de modo (3 tarjetas) en el modal de exploración; se envía en preview y store; badge “Conoce el terreno” se deja para F3.
- `resources/views/exploraciones/index.blade.php`: sección “Objetos encontrados” y soporte de `items` en el resumen.
- `_perdidas.blade.php` o parcial equivalente: ramas para bayas/bonguris.
- Sin cálculos de negocio en Blade: recibe DTOs ya resueltos.

## 5. Archivos y responsables

| Archivo / grupo | Acción | Responsable |
|---|---|---|
| `database/migrations/*_add_modo_to_exploraciones_activas.php` | Crear | `database` |
| `database/migrations/*_add_bonguri_to_habitats.php` | Crear + backfill | `database` |
| `src/Shared/Domain/Items/*` | Crear catálogo | `backend` |
| `src/Exploraciones/Domain/ModoExploracion.php`, `PesosEvento.php`, `PerfilExploracion.php` | Crear | `backend` |
| `src/Exploraciones/Domain/CalculadorBotinExploracion.php`, `ContextoBotin.php` | Crear | `backend` |
| `src/Exploraciones/Domain/Recompensas/RecompensaItem*.php` | Crear | `backend` |
| `src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php` | Modificar (aditivo) | `backend` |
| `src/Exploraciones/Domain/SimuladorEncuentros.php` | Parametrizar por modo | `backend` |
| `src/Exploraciones/App/FinalizarExploracionHandler.php`, `PersistirRecompensas.php`, `ProcesarExploracionHandler.php` | Modificar | `backend` |
| `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php`, `PresentadorExploraciones.php`, `ResultadoExploracionVista.php` | Modificar | `backend` |
| `app/Support/ItemCatalogo.php` | Añadir `baya:`/`bonguri:` | `backend` |
| `app/Models/ExploracionActiva.php`, `app/Models/Habitat.php` | Añadir a `$fillable` | `backend` |
| `app/Http/Controllers/ExploracionActivaController.php` | Validar y persistir `modo`; exponerlo | `backend` |
| `resources/views/habitats/show.blade.php` | Selector de modo | `frontend` |
| `resources/views/exploraciones/index.blade.php`, parcial de pérdidas | Resumen de objetos | `frontend` |
| Revisión independiente | Diff + criterios | `reviewer` |
| Auditoría de seguridad | Inventario, autorización, validación de claves | `security` |

## 6. Orden de ejecución

1. **F0 — Preparación:** crear rama `task-011` desde `master`; confirmar que `.githooks/pre-commit` y `MVP.txt` quedan fuera de alcance.
2. **F1.1 — Datos:** migraciones `modo` y `bonguri`; backfill; verificar `migrate` en local.
3. **F1.2 — Catálogo:** `src/Shared/Domain/Items` + `ItemCatalogo`.
4. **F1.3 — Dominio de exploración:** modos, perfil, botín, `ResultadoRecompensas`.
5. **F1.4 — Aplicación:** `FinalizarExploracionHandler`, `PersistirRecompensas`, `ProcesarExploracionHandler`, controlador.
6. **F1.5 — Presentación:** transformador, presentador, vistas.
7. **F1.6 — Verificación estática y manual.**
8. **F1.7 — Revisión y seguridad.**
9. **F1.8 — Informe final.**

Dependencias: F1.2 puede ir en paralelo con F1.1; F1.3 depende de F1.2; F1.4 depende de F1.3; F1.5 depende de F1.4. Los archivos compartidos se tocan en secuencia.

## 7. Criterios de aceptación

### Modo
- [ ] `exploraciones_activas.modo` existe, nullable, y una fila `null` se comporta exactamente como hoy (45/20/15/10/10).
- [ ] El backend valida `modo` contra `experiencia|reclutamiento|botin`.
- [ ] El modo cambia la mezcla de eventos de forma verificable.
- [ ] La UI envía el modo en preview y store.

### Bonguri
- [ ] Todos los hábitats tienen `bonguri` válido o `NULL` con fallback documentado.
- [ ] Una expedición solo puede devolver el bonguri de su hábitat.
- [ ] No se entrega bonguri si el hábitat no tiene color y el fallback está deshabilitado.

### Botín
- [ ] Finalizar una exploración incrementa el inventario exactamente con las cantidades del botín.
- [ ] Repetir la finalización no duplica el botín (idempotencia por `regreso`).
- [ ] El resumen muestra bayas y bonguris con nombre e icono.
- [ ] En derrota se pierde `intdiv(cantidad, 2)` de cada grupo y aparece en `objetos_perdidos`.
- [ ] Una clave desconocida nunca llega a `player_inventory`.
- [ ] No hay un tope duro fijo: el botín respeta los rangos, pesos y probabilidades centralizados y es ajustable.
- [ ] Resultados antiguos sin `items` no rompen la vista.

### Compatibilidad
- [ ] `ResultadoRecompensas` mantiene sus parámetros actuales; `items` es opcional y va al final.
- [ ] No se rompen los flujos de ruta/entrenador que construyen `ResultadoRecompensas`.
- [ ] `sitrus_berry`/`lum_berry` siguen funcionando como objetos de combate.
- [ ] No se modifica `tests/`.

## 8. Verificación requerida

Sin tests. Verificación estática y manual:

```bash
php -l <cada PHP nuevo/modificado>
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/deptrac analyse
php artisan view:cache && php artisan view:clear
php artisan route:list --path=exploraciones
git diff --check
```

Comprobación manual:

- Simular una exploración con cada modo y verificar que la mezcla de eventos cambia.
- Finalizar una exploración y comprobar que el inventario sube exactamente una vez.
- Repetir la finalización y comprobar que no duplica.
- Verificar que el bonguri del hábitat es el único posible.
- Verificar que una derrota resta la mitad de los items.
- Verificar que una exploración antigua sin `items` sigue renderizando.

## 9. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Regresión en `ResultadoRecompensas` | `items` con default `[]`; no reordenar; actualizar transformador/presentador/pérdidas en el mismo cambio. |
| Botín duplicado | Idempotencia por `regreso`; persistencia dentro de la transacción del `CommandBus`. |
| Inflación por modo `botin` | Probabilidades conservadoras y constantes de balance ajustables; telemetría posterior. |
| Bonguri incoherente | Backfill determinista y fallback explícito para “Vacío”. |
| Claves de item no válidas | Validar contra `CatalogoItems` antes de persistir. |
| Incompatibilidad con batallas | F1 no toca `SESSION_VERSION`; el cambio de sesión llega en F5. |
| Cambios locales ajenos | No tocar `.githooks/pre-commit` ni `MVP.txt`; delimitarlos en el preflight. |

## 10. Rollback

- Revertir los commits de F1 en la rama `task-011`.
- `migrate:rollback` de las dos migraciones nuevas (columnas aditivas, sin pérdida de datos).
- El inventario ya incrementado por botín puede quedar; si se exige reversión, limpiar manualmente las claves `baya:*`/`bonguri:*` creadas por la fase.
- No se hace `push`, `reset --hard` ni `clean` sin autorización.

## 11. Roadmap posterior (no autorizado por este plan)

| Fase | Contenido |
|---|---|
| F2 | Plantación/cosecha en nidos: `nest_plantings`, `PlantarBaya`, `CosecharPlantacion`, UI de huecos. |
| F3 | Influencia de plantaciones y bonus por Pokémon del hábitat; auto-cosecha opcional. |
| F4 | Forja: `src/Forja`, catálogo de recetas, `Fabricar`, UI. |
| F5 | Uso de objetos en combate: `AccionObjeto`, efectos, fases Livewire, descuento de inventario. |
| F6 | Equipables con coste y iconos definitivos. |
| F7 | Botín de bayas/bonguris en combates de ruta/entrenador. |

## 12. Aprobación solicitada

Se solicita aprobación explícita de **este plan v2 (F1)**. Las fases F2–F7 no quedan autorizadas por esta aprobación.

## Addendum de implementación (2026-11-04)

Correcciones dentro del alcance F1, derivadas de la revisión y la auditoría de seguridad. **No amplían el alcance ni autorizan F2–F7.**

1. **Backfill provincial en instalaciones nuevas.** La migración `add_bonguri_to_habitats` no puede rellenar en una BD limpia porque las tablas están vacías cuando se ejecuta. Se añade `BonguriHabitatSeeder`, idempotente, llamado desde `CatalogoSeeder` después de `HabitatSeeder`. La migración se mantiene para bases ya sembradas.
2. **Semántica de `multiplicadorBotin`.** Se aplica a la probabilidad de bonguri:
   - `BOTIN`: 60 % × 1,5 = 90 %.
   - `EXPERIENCIA` y `RECLUTAMIENTO`: 25 % × 0,5 = 12,5 %.
   - `MIXTO`: 30 % × 1,0 = 30 %.
   Las tiradas de bayas siguen rigiéndose por los rangos del modo.
3. **Factor de tiempo (`factorTiempo`).** Escala las tiradas de bayas y la probabilidad de bonguri para evitar el farmeo con `recoger` forzado:
   - Base = `inicioVuelta` (fin − duración/4), es decir, el 75 % de la duración planificada.
   - Exploración indefinida: base 60 minutos.
   - Resultado acotado a `[0, 1]`.
4. **Mínimo de `return_time`.** La hora de vuelta debe estar al menos 60 minutos en el futuro, tanto en `preview` como al crear. Cierra el ciclo de expediciones de ~1 minuto.
5. **Comentarios obsoletos** de “sin bayas / TODO bayas” corregidos en controlador, ruta y modelo.
