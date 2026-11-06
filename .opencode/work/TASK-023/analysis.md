# TASK-023 — Análisis

- **Estado:** COMPLETADO
- **Fecha:** 2026-11-06
- **Fase 4:** mapa existente en `.opencode/work/TASK-020/analysis.md` §1.2 (890 líneas, 19 propiedades, 39 métodos públicos, bloques y candidatos) y `.opencode/work/TASK-020/architecture.md` §3.2.
- **Fase 6B:** inventario nuevo delegado a `analyst` (`ses_e4f204c70ffenw7YF62l7eZ3YT`), resumido abajo.

## Fase 4 — `AgregadoBatalla`

- 890 líneas, 19 propiedades, 39 métodos públicos, 7 privados.
- Bloques: construcción/clon, getters, daño diferido, `__wakeup`, gas reactivo, clima/IA perezosos, dificultad/Trick Room, composición, log, efectos de ronda, debilitamientos, invocaciones, condiciones volátiles, motor de batalla e IA.
- **Restricción crítica:** sin `__serialize()` propio; se serializa el objeto completo. No se mueve estado y los componentes nuevos deben ser sin estado y serializables.
- Candidatos confirmados por TASK-020: `GestorClimaTerreno`, `GestorGasReactivo`, `GestorInvocaciones`, `ProcesadorDanioDiferido`, `ProcesadorCondicionesRonda`, `MotorRondas`, `EjecutorAccion`.

## Fase 6B — Inventario de código muerto y frontend

Método: referencias cruzadas sobre 917 clases PHP, 46 vistas, 110 rutas, 3 providers y 13 archivos de rutas; separación entre consumidores de producción y tests.

### Código muerto con confianza alta (recomendado eliminar)

| Archivo | Evidencia | Riesgo |
|---|---|---|
| `app/Support/AvistamientosPokedex.php` | 0 referencias producción y tests; los flujos despachan `ActualizarPokedexJob` inline | Bajo |
| `app/Support/CargadorPokemonsDerrotados.php` | 0 referencias; la carga está duplicada inline en 3 flujos | Bajo |
| `src/Exploraciones/Presentation/DTOObjetoPerdido.php` | 0 referencias; el flujo usa `ObjetoPerdido` + arrays | Bajo |
| `src/Exploraciones/Domain/ValueObjects/BitacoraExploracion.php` | 0 referencias en código; solo docs del módulo | Bajo |
| `resources/views/reclutados/index.blade.php` | 0 referencias; `/reclutados` redirige a `/equipos` | Bajo |
| `resources/views/welcome.blade.php` | 0 referencias; `/` sirve `HabitatsController@index` | Bajo |
| `config/crud.php` | 0 referencias tras la limpieza del CRUD | Bajo |

### No tocar en esta ola

| Elemento | Motivo |
|---|---|
| `resources/views/misiones/index.blade.php` | Usada por `tests/Feature/FrontendMisionesTest.php` |
| `src/CombateRuta/App/SelectorMovimientosMeta.php` | Usada por tests |
| `src/Battle/Domain/Effects/EfectoMoody.php` | No registrada; posible funcionalidad pendiente. Requiere decisión de producto |
| `src/Battle/Domain/Enums/EstadoPendiente.php` | Roadmap intencional de estados |
| `src/Shared/Infrastructure/Random/GeneradorAleatorioPhp.php` | Adaptador de puerto sin binding; posible uso DI previsto |
| `src/Shared/Infrastructure/Time/RelojSistema.php` | Adaptador de puerto sin binding; posible uso DI previsto |
| `config/ayuda.php` | Feature no conectada; requiere decisión de producto |
| `Livewire/Combate.php` (1.919 líneas) | Riesgo alto de romper contrato Livewire y sesión |
| Rutas `toggle-favorito` y `ruta/rivales` | Posible contrato externo; requieren aprobación aparte |
| `config/sendas.php` | Claves parcialmente usadas; poda aparte |
| `equipos/index` (3.284) y `habitats/show` (~2.600) | Deuda de frontend fuera de alcance |

### Deuda de frontend identificada (no se aborda)

- `combate_page.blade.php` (619 líneas) concentra 22 funciones JS globales en `@push('scripts')`.
- `livewire/combate.blade.php` (255 líneas) tiene un bloque Alpine `x-init` de ~95 líneas acoplado a esas funciones.
- No hay partials Blade duplicados exactos; la descomposición existente es correcta.
- `Livewire/Combate.php` mezcla orquestación, estado de presentación, resolución de acciones, grabación de animación y sincronización de vista.

### Rutas

- 110 rutas, sin duplicados método+URI, sin clases/métodos inexistentes.
- `POST /api/habitats/{habitat}/toggle-favorito` es no-op (`{"favorito":false,"count":0}`).
- `GET /api/habitats/{habitat}/ruta/rivales` está marcada `DEPRECATED` y sin consumidor interno.

### Proveedores y configuración

- Los 3 providers existen; todos los bindings apuntan a clases existentes.
- Sin bindings a clases eliminadas.
- `FabricaBatallaInterface → FabricaBatallaMock` es intencional; no cambiar en 6B.
