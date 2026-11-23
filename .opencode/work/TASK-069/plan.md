# TASK-069 — Plan

- **ID:** TASK-069
- **Versión:** v1
- **Estado:** APROBADO
- **Fecha:** 2026-11-19
- **Proyecto:** `/home/david/pokemon`

## F1 — Bugs

1. `IniciarCombateGimnasio`: `[]` → `DatosPokemonBatallaCollection` vacía.
2. `darCaramelo`: lock del reclutado + EXP dentro de la misma transacción.
3. `darEvs`: eliminar `max(1, …)`.
4. `evolucionar`: lock + revalidación dentro de la transacción.
5. `toggleFavorito`: transacción, mutex por usuario y tolerancia a unique.
6. Soft-lock IA: consumir turno y finalizar la animación cuando no hay acción.
7. `ServicioEvolucion::opcionesEvolucion`: `get()` nullable.
8. `FabricaCapacidadesStats`: usuario nullable y `pokemon` nullable.
9. Autorización admin de gimnasios (`is_admin`, Gate, middleware).
10. Índices parciales únicos: favoritos globales y exploración activa por reclutado.
11. `AppServiceProvider`: cachear `Schema::hasTable` de forma perezosa.
12. `PresentadorResultadoBatalla::procesarGimnasio`: no perder la medalla en silencio.

## F2 — Código muerto

- Eliminar `Shared\Infrastructure\{Random\GeneradorAleatorioPhp,Time\RelojSistema}`,
  `EfectoMoody`, `EstadoPendiente`, `FactorAparicion`, `SelectorMovimientosMeta`.
- Eliminar métodos públicos sin uso de `PresentadorExploraciones`.
- Mantener `CurvaSenda` (tests) y `Avistamientos` (pendiente de decisión).

## F3 — Duplicación

- `StatEnum::key()` en los 4 mapeos repetidos.
- `DTOFamiliaSinHabitat extends DTOFamiliaDisponible`.
- Trait de helpers de objetivos de batalla (Livewire).
- `MovimientoBatalla` ↔ `DTOMovimientoBatalla` deserialización única.
- Validación `return_time` única; `Rule::enum(RolExploracion::class)`.
- Helper de respuesta JSON/redirect en `TeamController`.
- Helper `explorador+diario` en `PresentadorExploraciones`.

## F4 — Encapsulamiento

- Quitar `extends Controller` de controladores de módulo sin uso de la base.
- `ReclutadoSerializer`: usar `reclutado->user_id` en lugar de `auth()`.
- No mover DTOs de `Habitats\Presentation` en esta tarea (requiere cambio
  transversal; se documenta como deuda).

## F5 — Clases extensas

- Extraer helpers repetidos en `PresentadorExploraciones`.
- Reducir el `foreach + instanceof` de multiplicadores en `ColeccionEfectos`.
- No dividir `ReclutadoController`/`BatallaVistaPresenter`/`BattleEffectServiceProvider`
  en esta tarea: riesgo alto y sin tests; se documenta como deuda.

## F6 — Calidad estática

- Pint sobre archivos modificados.
- PHPStan sobre `src/` y `app/`; corregir errores nuevos.
- Actualizar baseline solo si procede y sin ocultar errores nuevos.

## Verificación

- `php -l` de cada archivo modificado.
- `vendor/bin/pint --dirty --format agent`.
- `vendor/bin/phpstan analyse --no-progress`.
- `vendor/bin/deptrac analyse` (comparar violaciones).
