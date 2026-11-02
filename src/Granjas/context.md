# Contexto del módulo `Granjas`

> Fuente de conocimiento del módulo. Si cambias una regla de producto, un contrato o una decisión
> de diseño, actualiza este archivo en el mismo cambio. Las búsquedas guiadas están al final.

## Propósito

Granjas por hábitat: el jugador construye y mejora **nidos**, **refugios** y **complementos EV**
para las familias evolutivas (`evolution_chain_id`) presentes en un hábitat. Los bonos se consumen
en Huevos (tiempo/IV) y en los modos de combate (aparición/captura), y los complementos EV se
aplican a todos los reclutados de la familia.

## Estructura y responsabilidades

| Ruta | Responsabilidad |
|---|---|
| `Domain/CalculadoraBonosGranja.php` | Cálculos puros: rango de IVs, factor de tiempo, multiplicadores de refugio, costes y EV de complementos. |
| `Domain/DataTransferObjects/` | `GranjaDetalle`, `NidoGranja`, `RefugioGranja`, `ComplementoGranja`, `BonosFamilia`, `CosteCaramelos`, etc. |
| `Domain/Collections/` | `FamiliasGranjaCollection`, `NivelesComplementoCollection`, `RefugiosHabitatCollection`, `CaramelosFamiliaCollection`, `CaramelosEvCollection`. |
| `App/AsegurarGranja.php` | Garantiza una granja por `user_id + habitat_id`. |
| `App/FamiliaEnHabitat.php` | Valida que la familia pertenezca al hábitat. |
| `App/InventarioGranja.php` | Lee/consume `player_inventory` (`familia:{chain}`, `ev:{stat}`). |
| `App/MejorarNido.php`, `MejorarRefugio.php`, `MejorarComplemento.php` | Compras/mejoras con transacción, `lockForUpdate` y coste. |
| `App/AlternarCondicionesInsalubres.php` | Activa/desactiva condiciones insalubres. |
| `App/ResolverBonosFamilia.php` | Resuelve los bonos vigentes de una familia (nido, refugio, EV). |
| `App/ObtenerGranja.php`, `AccionesGranja.php` | Fachada de lectura y acciones para el controlador. |
| `Infra/Controllers/GranjaController.php` | HTTP JSON; devuelve `GranjaDetalle::toArray()`. |

## Decisiones de producto y funcionamiento

### Granja y familia

1. **Una granja por `user_id + habitat_id`** (`AsegurarGranja`). No hay granja global.
2. **La familia debe pertenecer al hábitat** (`FamiliaEnHabitat`); se identifica por
   `evolution_chain_id`, no por especie.
3. **La familia puede estar en varios hábitats**. `ResolverBonosFamilia::habitatDeFamilia()` toma
   el primer `pokemon_habitat` sin orden explícito; si una familia vive en varios hábitats, el bono
   aplicado puede ser no determinista. **Decisión pendiente**: definir el hábitat canónico o
   resolver por hábitat de la operación.

### Nidos (0–5)

4. Coste de subida (caramelos de familia): **25 / 75 / 225 / 450 / 900**.
5. Cada nivel reduce el tiempo de huevo un **10 %** hasta un máximo del **50 %**
   (`1.0 - min(0.5, 0.10 × nivel)`).
6. Rango de IVs por nivel de nido: **0–3 / 4–7 / 8–9 / 10–11 / 12–14 / 15** (nivel 5 = 15).

### Refugios (1–5)

7. Coste: **25 / 75 / 225 / 450 / 2700**.
8. Multiplicador de aparición y captura (condiciones salubres): **×1.25 / ×1.5 / ×2.0 / ×2.5**.
9. **Nivel 5 es exclusivo**: solo una familia puede tener refugio nivel 5 en el hábitat; aparición
   ×1.0 para esa familia y ×0 para el resto; captura ×3.0.
10. **Condiciones insalubres** invierten el efecto: multiplicadores **×0.8 / ×0.6667 / ×0.5 / ×0.4**;
    en nivel 5 la familia queda excluida (aparición 0, captura ×0.2) y no se pueden mejorar
    complementos.
11. El refugio debe existir para mejorar complementos (nivel ≥ 1) y las condiciones deben ser
    salubres.

### Complementos EV (1–5)

12. EV por nivel: **64 / 128 / 256 / 384 / 512**.
13. Coste de subida: **3 × EV + 1 × caramelo de familia** (`CosteCaramelos`).
14. Se aplican **retroactivamente** a todos los reclutados de la familia (delta = EV nuevo − EV
    anterior), con **cap 512 por stat**; los nuevos reclutados reciben los EV vigentes al crearse.
15. Los complementos se guardan por refugio + stat (`Complemento`).

### Inventario y UI

16. Inventario unificado en `player_inventory`: `familia:{chain}` y `ev:{stat}`.
17. UI: modal **Granjas** dentro de `resources/views/habitats/show.blade.php`; las acciones mutan
    estado local tras 200 OK (sin refresco pesado).
18. `GranjaController` devuelve el detalle completo (`GranjaDetalle`) tras cada acción.

## Contratos y puntos de entrada

Rutas (`routes/granjas.php`):

```text
GET  /api/habitats/{habitat}/farm
POST /api/habitats/{habitat}/farm/nests/{chain}/upgrade
POST /api/habitats/{habitat}/farm/shelters/{chain}/upgrade
POST /api/habitats/{habitat}/farm/shelters/{chain}/toggle-unhealthy
POST /api/habitats/{habitat}/farm/shelters/{chain}/complements/{stat}/upgrade
```

- `GranjaController` delega en `AccionesGranja` y `ObtenerGranja`.
- Tablas: `granjas` (user+habitat), `nidos`, `refugios`, `complementos`, `player_inventory`.
- `ResolverBonosFamilia` es el punto de lectura para Huevos y modos de combate.

## Dependencias

- `Src\Pokemon\Domain\Stats\GeneradorIvs`/`RangoIvs` — rangos de IV.
- `Src\Huevos\App\CrearHuevo` — consume nido (tiempo/IV) vía `ResolverBonosFamilia`.
- `Src\Exploraciones` / `CombateRuta` / `CombateEntrenadores` — consumen factores de aparición y
  captura.
- `App\Models\Granja`, `Nido`, `Refugio`, `Complemento`, `PlayerInventory`.
- `App\Support\ItemCatalogo` — claves de inventario.

## Deuda y mejoras priorizadas (auditoría 2026-10-31)

- **[Alta] `habitatDeFamilia()` no determinista** si la familia está en varios hábitats: definir
  hábitat canónico o pasar el hábitat de la operación. Evidencia:
  `ResolverBonosFamilia::habitatDeFamilia()` usa `value('habitat_id')` sin `orderBy`.
- **[Media] Costes y multiplicadores viven en `match`** dentro de `CalculadoraBonosGranja`.
  Extraer constantes nombradas (`COSTE_NIDO_*`, `COSTE_REFUGIO_*`, `FACTOR_REFUGIO_*`) o una tabla
  de configuración tipada; mantener una sola fuente.
- **[Media] `MejorarNido`/`MejorarRefugio`/`MejorarComplemento` repiten el patrón**
  transacción → asegurar granja → buscar/crear → `lockForUpdate` → validar máximo → consumir →
  incrementar. Extraer un servicio de mejora genérico o un `GranjaUpgradeService`.
- **[Media] `ResolverBonosFamilia` mezcla lectura Eloquent, reglas y fallback neutro**. Separar el
  repositorio de bonos y el cálculo puro; el dominio ya tiene `CalculadoraBonosGranja`.
- **[Baja] `GranjaController::respuesta()` devuelve `toArray()`**; correcto como frontera, pero
  mantener el DTO como contrato y no añadir arrays ad hoc.
- **Norma**: no crear arrays nuevos para bonos/costes; usar los DTOs existentes (`NidoBonos`,
  `RefugioBonos`, `CosteCaramelos`, `BonosFamilia`).

## Guía de búsqueda (grep)

- Clases: `CalculadoraBonosGranja`, `ResolverBonosFamilia`, `AsegurarGranja`, `FamiliaEnHabitat`,
  `InventarioGranja`, `MejorarNido`, `MejorarRefugio`, `MejorarComplemento`, `GranjaDetalle`,
  `NidoBonos`, `RefugioBonos`, `BonosFamilia`, `CosteCaramelos`.
- Constantes: `CalculadoraBonosGranja::MAX_NIDO`, `MAX_REFUGIO`, `MAX_COMPLEMENTO`,
  `REDUCCION_TIEMPO_POR_NIVEL`, `REDUCCION_TIEMPO_MAXIMA`.
- Rutas/términos: `/api/habitats/{habitat}/farm`, `nests`, `shelters`, `complements`,
  `toggle-unhealthy`, `nido`, `refugio`, `insalubres`.
- Tablas: `granjas`, `nidos`, `refugios`, `complementos`, `player_inventory`.
- Tests: `tests/Feature/Granjas/`, `tests/Unit/Granjas/` (si existen), y tests de Huevos que usan
  `ResolverBonosFamilia`.
