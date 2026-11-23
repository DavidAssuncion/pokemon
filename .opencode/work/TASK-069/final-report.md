# TASK-069 — Informe final

- **Fecha:** 2026-11-19
- **Estado:** COMPLETADO con elementos diferidos
- **Plan:** v1 (aprobado)
- **Cambios:** 55 archivos, +580 / −895 líneas (neto −315)

## Entregado

### Bugs corregidos

1. **Gimnasio sin etapa** (`IniciarCombateGimnasio`): ya no hay fatal
   `isEmpty()` sobre `[]`; se lanza el 422 de dominio previsto.
2. **Caramelos de tipo** (`darCaramelo`): lock del reclutado, EXP y decremento
   en la misma transacción; constante `ReglasCaramelo::EXP_POR_CARAMELO`.
3. **Caramelos EV** (`darEvs`): sin `max(1, …)`; 422 coherente al tope.
4. **Evolución** (`evolucionar`): lock + revalidación dentro de la transacción;
   evita doble consumo y dobles evoluciones.
5. **Favoritos** (`toggleFavorito`): mutex por usuario y `insertOrIgnore`;
   invariantes reforzados con índices parciales.
6. **Soft-lock de IA** (`GestorAccionBatalla` + `GestorCicloTurnosBatalla`):
   cierre explícito del turno cuando no hay acción/objetivo/movimiento; el
   llamador no reconstruye la cola con estado obsoleto.
7. **`ServicioEvolucion::opcionesEvolucion`**: `map` nullable + `filter`
   efectivo.
8. **`FabricaCapacidadesStats`**: `?User` y `pokemon` nullable.
9. **Admin de gimnasios**: `users.is_admin`, Gate `admin`, `can:admin` en las
   rutas; verificado con `route:list -v`.
10. **Invariantes de BD**: índices parciales únicos de favoritos y exploración
    activa, con limpieza previa de duplicados.
11. **`Schema::hasTable`** perezoso por petición.
12. **Gimnasio inexistente** en resultado de batalla: `Log::error` + mensaje,
    sin perder la medalla en silencio.

### Limpieza

- 6 clases muertas eliminadas (reloj/random duplicados, `EfectoMoody`,
  `EstadoPendiente`, `FactorAparicion`, `SelectorMovimientosMeta`).
- 3 métodos públicos sin uso eliminados de `PresentadorExploraciones`.
- Duplicaciones: `StatEnum::key()`, trait `FamiliaEvolutivaComun`, trait
  `ObjetivosBatalla`, `CambiosStatsCollection::desdeSerializado()`,
  `horaDeVueltaValida()`, `Rule::enum(RolExploracion::class)`,
  `exploradorYDiario()`.
- 10 controladores de módulo desacoplados de `App\Http\Controllers\Controller`.

### Calidad estática

- **PHPStan nivel 6: 0 errores.**
- Baseline: 199 entradas (antes 201); 4 conteos corregidos, 2 entradas
  obsoletas eliminadas.
- `phpstan.neon`: excepciones documentadas para falsos positivos de Larastan
  (lockForUpdate/insertOrIgnore) y guards sobre JSON externo.
- **Deptrac: 118 violaciones** (antes 128).
- Pint y `php -l`: OK.

## Acciones manuales requeridas

1. `php artisan migrate` (dos migraciones nuevas).
2. Marcar al administrador: `UPDATE users SET is_admin = true WHERE id = ...;`
   (el default es `false`).
3. Revisar/reasignar el caramelo de tipo si se usaba otro valor distinto de
   100: ahora vive en `Src\Shared\Domain\ReglasCaramelo`.

## Diferido (con motivo)

- `src/Avistamientos/**` (24 archivos): módulo sin cablear; borrar o integrar
  requiere decisión del desarrollador.
- `ReclutadoSerializer::movimientosDisponibles` con `auth()`: archivo en edición
  por TASK-068; se evita el conflicto.
- Split de `ReclutadoController`, `BatallaVistaPresenter`,
  `BattleEffectServiceProvider` y `PresentadorExploraciones`: cambio amplio
  sin tests; se prioriza no romper.
- Mover DTOs de `Habitats\Presentation`/`Battle\Presentation` a `Domain`
  (42+62 violaciones de Deptrac) y reglas de `app/Support` a `src/`.
- FormRequests para las validaciones inline restantes.
- Regenerar la baseline del hardener (nivel 8): deuda preexistente.

## Notas

- No se ejecutaron tests (política del proyecto) ni se modificó `tests/`.
- No se hizo commit ni push.
- Los cambios de TASK-068 en el árbol de trabajo no se tocaron.
