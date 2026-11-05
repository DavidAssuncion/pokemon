# ADR-0003: Cosecha global, menú de cosecha e iconos WebP de items

- **Estado:** Aceptado
- **Fecha:** 2026-11-04
- **Ámbito:** `src/Granjas`, `src/Shared/Domain/Items`, `src/Forja`, `resources/views`,
  `resources/css`, `public/images/items`, `package.json`
- **Tarea:** TASK-014 — plan v1

## Contexto

La cosecha de F2 solo existía por hueco (`POST .../plantings/{slot}/harvest`). Con varias
plantaciones repartidas entre hábitats, el jugador tenía que abrir cada granja y cosechar hueco a
hueco. La petición de producto fue una acción única desde el menú global.

En paralelo convivían cuatro problemas de presentación:

1. El menú usaba un botón `?` con un popup de ayuda; no había Font Awesome y los iconos eran SVG
   inline. Se pidió un botón de cosecha con `fa-solid fa-seedling`.
2. Los 16 PNG de `public/images/items` (8 pociones, 7 mejoras X y Éter) no estaban mapeados:
   `ClavePocion` y `ClaveMaterial` devolvían `unknown.webp`.
3. La forja agrupaba las 7 variantes de `material:recipiente` en un `<select>`, sin mostrar
   existencias ni procedencia del bonguri.
4. Tras cosechar, la granja abierta y la forja podían quedar con datos obsoletos.

Restricciones de la tarea: sin tests, sin migraciones, sin F6/F7, sin cambios en
`ReglasPlantacion` ni `CalculadorBotinExploracion`, sin commit ni push.

## Decisión

| ID | Decisión | Motivo |
|---|---|---|
| **D1** | Crear `CosecharTodo::handle(int $userId)` y `POST /api/farm/harvest-all` bajo `auth`, sin body | Una sola acción cubre todos los hábitats y nidos del jugador sin abrir cada granja. |
| **D2** | Una **transacción por nido**, con locks nido → plantaciones por `id`, recálculo de stock tras el lock y reintento 3 | Aísla fallos y reduce el tiempo de lock; mantiene el orden de locks ya usado por `PlantarBaya` y `CosecharPlantacion` para evitar deadlocks. |
| **D3** | Errores parciales: un nido fallido se reporta y el resto continúa; la respuesta es agregada (`total_bayas`, `total_plantaciones`, `nidos`, `bayas`, `errores`) | Un nido corrupto o bloqueado no debe impedir cosechar los demás. |
| **D4** | Botón de menú con Font Awesome 6 Free **local vía npm**, `fa-seedling`, estados de carga/éxito/error y evento `inventario:actualizado`; oculto en `/combate*` | Evita depender de CDN, da feedback inmediato y refresca granja/forja sin recargar la página. |
| **D5** | Convertir los 16 PNG a WebP (`cwebp -q 85 -m 6 -alpha_q 100 -metadata none`), organizarlos en `pocion/`, `mejora/` y `material/`, y mover los originales a `storage/app/imports/items-png/` | Reduce peso, elimina los `unknown.webp` y conserva los originales fuera de `public`. |
| **D6** | `material:recipiente` conserva el nombre visible **Recipiente** y la descripción “Recipiente para fabricar objetos”, pero usa el artwork de **Éter** (`material/recipiente.webp`) | El asset disponible es el de Éter; se evita renombrar la clave de inventario o la receta. |
| **D7** | `ClaveBonguri::origen(): string` como fuente única del origen; `IngredienteDetalle::$origen` opcional y relleno en `ListarRecetas` para `bonguri:*`; la forja lista las 7 variantes con stock y origen, disponibles primero | El origen ya existía en seeders, pero no llegaba a la UI; se expone sin duplicar el mapa. |
| **D8** | No cambiar el bonus de plantaciones (`min(2.0, 1 + 0.20 × plantas)`); solo añadir una nota estática en la UI | La fórmula ya cumple el comportamiento pedido y tocar el cálculo habría alterado el balance de exploración. |

### D1/D2 — Cosecha global por nido

`CosecharTodo` selecciona en tres consultas: granjas del usuario, nidos con `level >= 1` y
plantaciones candidatas. Calcula el stock con `ReglasPlantacion::stockDe()` y agrupa por nido.
Después, por cada nido candidato:

1. `DB::transaction(..., 3)`.
2. Lock del nido (`whereKey` + `where farm_id`).
3. Lock de sus plantaciones ordenadas por `id`.
4. Recálculo del stock tras el lock; si es `<= 0`, se omite.
5. `increment('harvest_count', stock)` y acumulación por `berry_key`.
6. Incremento del inventario agregado por baya, ordenado por `item_key`.

La idempotencia no depende de una marca en memoria: el stock se recalcula bajo lock y
`harvest_count` se actualiza en la misma transacción. Dos peticiones inmediatas devuelven 0 en la
segunda. El botón también se deshabilita mientras hay una petición en curso.

### D3 — Contrato de respuesta

```json
{
  "total_bayas": 12,
  "total_plantaciones": 5,
  "nidos": 3,
  "bayas": [
    {"clave": "baya:liechi", "nombre": "Lichi", "icono": "/images/berry/liechi-berry.webp", "cantidad": 6}
  ],
  "errores": [
    {"farm_id": 4, "habitat_id": 2, "nido_id": 42, "evolution_chain_id": 7, "mensaje": "..."}
  ]
}
```

- `200` también con errores parciales; el cliente muestra `+N bayas` o `Error` y el número de
  errores.
- `nidos` cuenta los nidos que aportaron al menos una plantación; `total_plantaciones` cuenta
  filas con stock. Sin candidatos devuelve ceros y listas vacías.
- `bayas` se ordena por cantidad descendente y nombre ascendente.
- Las excepciones de dominio conservan su mensaje; el resto se registra con `report()` y se
  devuelve un mensaje genérico.

### D4 — Menú y refresco

`resources/views/layouts/app.blade.php` sustituye el bloque `?`/popup por un botón con
`<i class="fa-solid" :class="cosechando ? 'fa-spinner fa-spin' : 'fa-seedling'">`. La petición usa
`fetch` con CSRF y `AbortController` de 15 s; el resultado se anuncia con `role="status"` y
`aria-live="polite"`. El botón se oculta en `/combate*` para no alterar el inventario a mitad de
batalla.

Al terminar (éxito o error de red) se emite `inventario:actualizado` con el payload. Los
listeners son:

- `habitats/show.blade.php`: si el modal de granja está abierto y no hay una acción en curso,
  `loadGranja({ preservarFamilia: true, silenciosa: true })`.
- `forja/index.blade.php`: `cargar(false)` para releer recetas y saldos.

### D5/D6 — Assets e iconos

| Grupo | Archivos | Enum |
|---|---|---|
| Pociones | `public/images/items/pocion/{pocion,super,hiper,maxima,curatotal,restaurar_todo,revivir,max_revivir}.webp` | `ClavePocion::archivoIcono()` |
| Mejoras | `public/images/items/mejora/{ataque,defensa,ataque_especial,defensa_especial,velocidad,precision,critico}.webp` | `ClaveMejora::archivoIcono()` |
| Material | `public/images/items/material/recipiente.webp` | `ClaveMaterial::archivoIcono()` |

Los PNG originales quedan en `storage/app/imports/items-png/` (16 archivos) y no se sirven desde
`public`. Los enums construyen la ruta con `{$this->value}.webp`; no hay tabla de mapeo adicional.

**Nota de implementación:** el plan D7 proponía mostrar el material como “Éter”. La implementación
conserva `ClaveMaterial::nombre() = 'Recipiente'` y solo usa el icono de Éter. La divergencia es
consciente y queda pendiente de decisión de producto (ver Deuda).

### D7 — Origen de bonguris en la forja

`ClaveBonguri::origen()` centraliza el mapa color → provincia:

| Color | Origen |
|---|---|
| Blanco | Galicia |
| Amarillo | Euskadi |
| Negro | Barcelona |
| Verde | Extremadura |
| Rosa | Nova Alacant |
| Azul | Mutxamel |
| Rojo | Andalucía |

`IngredienteDetalle` añade `?string $origen = null` y lo incluye en `toArray()` (null para
ingredientes que no son bonguri). `ListarRecetas` lo rellena solo para claves `bonguri:*`.
La UI de forja detecta `grupo.clave === 'material:recipiente'` y renderiza una lista con icono,
nombre, `disponible/requerido`, origen y botón Fabricar. `variantesRecipiente()` ordena primero
las variantes con existencias, manteniendo el orden estable del catálogo. El botón se deshabilita
si `puede_fabricar` es false o mientras se fabrica otra variante.

## Alternativas descartadas

| Alternativa | Motivo del descarte |
|---|---|
| Una única transacción global todo-o-nada | Un nido bloqueado o corrupto impediría cosechar el resto; además mantendría locks de todos los nidos a la vez. |
| Un `UPDATE` masivo sin locks por nido | No permite recalcular stock ni `harvest_count` de forma segura; riesgo de doble cosecha. |
| Cosecha por hábitat | Obligaba a repetir la acción por cada hábitat y no resolvía el caso de varios nidos. |
| Font Awesome por CDN | Dependencia externa, posible bloqueo o cambio de versión; el proyecto ya compila assets con Vite. |
| Mantener PNG y resolver los `unknown.webp` con un mapa | No reduce peso y deja assets sin optimizar; el mapeo habría duplicado información en los enums. |
| Exponer el origen con un `match` en la vista | Duplicaría el mapa de provincias y divergiría de los seeders. |
| Cron de cosecha automática | Fuera de alcance y cambia el modelo de juego (la cosecha sigue siendo una acción del jugador). |

## Consecuencias

**Positivas**

- Una sola acción cosecha todas las plantaciones listas, sin abrir cada granja.
- Los fallos quedan acotados a un nido y se reportan sin perder el resto de la cosecha.
- Los iconos de pociones, mejoras y recipiente dejan de ser `unknown.webp` y pesan menos.
- La forja muestra stock y origen de los 7 bonguris, y deshabilita las variantes sin materiales.
- Granja y forja se refrescan solas tras cosechar.

**Negativas / costes**

- La cosecha global abre una transacción por nido; con muchos nidos son más roundtrips que una
  operación única.
- El cliente debe gestionar respuestas parciales (éxito con `errores`) y no solo 200/error.
- Se añade una dependencia npm (`@fortawesome/fontawesome-free`) y peso al bundle CSS/fuentes.
- El nombre “Recipiente” y el icono de Éter no coinciden con la propuesta D7 del plan.

## Deuda y seguimiento

- **`config/ayuda.php` huérfano:** el popup de ayuda se eliminó, pero el archivo de configuración
  sigue en el repositorio sin referencias. Decidir si se elimina o se reutiliza.
- **PNG originales en `storage/app/imports/items-png/`:** se conservan para rollback. Definir
  política de retención y limpieza; no están versionados como assets públicos.
- **Sin rate limiting específico:** `POST /api/farm/harvest-all` está bajo `auth` y es idempotente,
  pero no tiene `throttle`. Añadirlo si se detecta abuso o ráfagas.
- **Nombre del material:** la UI y el catálogo muestran “Recipiente”; el plan D7 pedía “Éter”.
  Confirmar el nombre definitivo antes de tocar claves de inventario.
- **Patrón de cosecha duplicado:** `CosecharPlantacion` y `CosecharTodo` comparten reglas y orden
  de locks, pero no una rutina común. Si aparece una tercera vía, extraer un servicio de cosecha
  de nido.
- **Lógica Alpine inline:** el estado y el `fetch` del menú viven en un `x-data` extenso dentro del
  layout. Si crece, moverlo a un componente JS con pruebas de contrato.

## Referencias

- Plan: [`TASK-014/plan.md`](../../.opencode/work/TASK-014/plan.md)
- Diseño: [`TASK-014/architecture.md`](../../.opencode/work/TASK-014/architecture.md)
- Análisis: [`TASK-014/analysis.md`](../../.opencode/work/TASK-014/analysis.md)
- Ejecución: [`TASK-014/execution.md`](../../.opencode/work/TASK-014/execution.md)
- [`ADR-0001`](0001-inventario-compartido-f2-f5.md) — inventario compartido y plantaciones.
- [`ADR-0002`](0002-catalogo-bayas-y-detalle-especie.md) — catálogo de bayas y detalle de especie.
- Código: `src/Granjas/App/CosecharTodo.php`, `src/Granjas/Infra/Controllers/GranjaController.php`,
  `src/Shared/Domain/Items/{ClavePocion,ClaveMejora,ClaveMaterial,ClaveBonguri,CatalogoItems}.php`,
  `src/Forja/App/{ListarRecetas.php,DataTransferObjects/IngredienteDetalle.php}`.
- Vistas: `resources/views/layouts/app.blade.php`, `resources/views/habitats/show.blade.php`,
  `resources/views/forja/index.blade.php`.
