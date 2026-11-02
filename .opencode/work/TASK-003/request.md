# TASK-003 — Revisión y refactor del módulo de Exploraciones

## Petición literal

> Las exploraciones tienen algun bug, muestran pokemon individuales, pero luego se envian equipos
> de exporacion. Revisa este modulo y cuentame como funciona, vamos a refactorizarlo. Esta
> inspirado en las exploraciones del yerno de fallout shelter, donde trascurre el tiempo y traen
> recursos, experiencia... Demos darle mas personalidad

## Interpretación operativa

1. **Diagnóstico**: explicar cómo funciona hoy el módulo `Exploraciones` de punta a punta y
   localizar la causa del síntoma «la UI muestra Pokémon individuales pero luego se envían equipos
   de exploración».
2. **Diseño**: proponer un refactor que (a) elimine la ambigüedad individual/equipo, (b) haga
   evolucionar la expedición en el tiempo con recursos, experiencia y eventos, y (c) aporte
   personalidad al explorador y a la bitácora, inspirado en la exploración del yermo de
   *Fallout Shelter*.
3. **Aprobación**: presentar plan versionado antes de tocar código. Esta tarea no autoriza
   implementación hasta que exista aprobación explícita de una versión del plan.

## Contexto conocido

- Proyecto: `/home/david/pokemon` — Laravel 12 / PHP 8.4, rama `master`.
- Módulo: `src/Exploraciones/` (DDD por capas), `app/Http/Controllers/ExploracionActivaController.php`,
  `app/Models/ExploracionActiva.php`, `resources/views/exploraciones/`, `routes/exploraciones.php`,
  comando `app/Console/Commands/ProcesarExploraciones.php`.
- Existe una migración `change_equipo_id_to_reclutado_id_in_exploraciones` que confirma un cambio
  histórico de «exploración por equipo» a «exploración individual».
- `src/Exploraciones/context.md` declara que el flujo actual es individual, pero quedan
  estructuras de equipo (`CalculadorCapacidadEquipo`, `SinergiaEquipo`,
  `CalculadorRecompensas::MIEMBROS_EQUIPO = 3`, presentador con clave `equipo`).
- La vista `resources/views/exploraciones/index.blade.php` titula «Gestión de expediciones de tus
  equipos» y usa la clave `equipo` para mostrar al reclutado.
- Tests relevantes: `tests/Feature/Exploraciones*`, `tests/Feature/MigracionExploracionIndividualTest.php`,
  `tests/Unit/Exploraciones/`.
- Existe WIP local extenso (133 entradas en `git status`, 84 archivos en `git diff --stat`) que
  incluye `src/Exploraciones/App/ProcesarExploracionHandler.php`,
  `src/Exploraciones/App/FinalizarExploracionHandler.php`, calculadores y tests. **No se debe
  revertir, sobrescribir ni commitear.**

## Restricciones

- No implementar sin plan aprobado.
- No leer ni exponer `.env`, credenciales ni secretos.
- No modificar la base de datos de producción.
- No instalar dependencias ni ejecutar `git push`.
- Respetar el WIP preexistente y delimitar los archivos de cada especialista.

## Entregables esperados de esta fase

- `analysis.md`: funcionamiento real, bug(s) con evidencia `archivo:línea`, deuda y riesgos.
- `architecture.md`: diseño del refactor (modelo de dominio, estados, contratos, datos, UI y pruebas).
- `plan.md`: plan versionado, verificable y acotado.
- Aprobación explícita del desarrollador antes de implementar.
