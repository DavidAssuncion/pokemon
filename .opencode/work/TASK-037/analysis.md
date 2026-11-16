# TASK-037 — Análisis: leyenda de stats e IV/EV en Configurar Formación

- **Fecha:** 2026-11-09
- **Rama:** `task-011`
- **Estado:** ANALYSIS (sin implementación)
- **Alcance:** modal de Configurar Formación (hábitats y gimnasios)

## Resumen

La leyenda de perfiles IV/EV puede añadirse sin tocar fórmulas, balance, IV/EV ni esquema. La fuente única correcta es `PoliticaIvsEvs`/`PerfilIvsEvs`; los cálculos de stats deben seguir delegándose en `BattleStats`. El frontend no puede calcular el ejemplo por sí solo: el payload de `$teams` cargado en los modales solo incluye `members.reclutado.pokemon`, sin `pokemon.stats` ni `stats_calculados`, y las reglas prohíben replicar fórmulas en JavaScript. Por tanto, la leyenda y su ejemplo numérico deben construirse en backend y viajar como bloque aditivo en la respuesta de PC ya existente.

Hallazgo crítico: en gimnasios el rival no se calcula al nivel del jugador, sino al nivel escalado (`$nivelRival = escalador->escalar(...)`), por lo que el ejemplo debe usar ese nivel y no `nivelJugador`. En ruta y entrenador sí se usa `nivelJugador`.

## Requisitos

### Funcionales

- Mostrar en el modal "Configurar Formación" una leyenda de los perfiles IV/EV del rival.
- Explicar los cuatro perfiles existentes: Nulo, TopUno, TopDos, Completo.
- Incluir un ejemplo numérico con stats base, IV/EV aplicados y stats calculados al nivel de combate.
- Resaltar el perfil que aplica al combate actual.
- No mostrar desglose por miembro del equipo ni composición del rival.

### No funcionales

- Sin cambios de fórmulas, balance, IV/EV ni esquema.
- Sin duplicar `BattleStats`/`PoliticaIvsEvs` en JS/Blade.
- Sin nuevas dependencias ni endpoints si es evitable.
- Compacta, accesible por teclado y compatible con el modal `max-w-md`.
- Sin filtrar composición, especies ni stats individuales del rival.

### Criterios de aceptación del análisis

- Semántica exacta de cada perfil con evidencia archivo:línea.
- Mapa contexto → perfil.
- Propuesta concreta de Pokémon de ejemplo y origen de sus stats.
- Recomendación backend/frontend y contrato aditivo.
- UX compacta y accesible.
- Riesgos, alternativas descartadas y preguntas abiertas.

### Ambigüedades

- No está definido si "nivel de combate" del ejemplo debe ser el del jugador o el del rival en gimnasios; la evidencia indica que debe ser el del rival.
- No está definido si la leyenda debe verse antes de seleccionar equipo.
- No está definido si el ejemplo debe incluir CP.
- No están definidas las etiquetas finales de los perfiles.

## Alcance afectado

### Módulos

- `src/Shared/Domain` / `src/Shared/Domain/Pc`.
- `src/CombateRuta/App`, `src/CombateEntrenadores/App`, `src/Gimnasios/App`.
- Controladores de esos tres módulos.
- `app/Support/Pc`.
- Vistas de hábitats, gimnasios y partial de comparación.

### Archivos (propuesta, no ejecutada)

- Modificar: `src/Shared/Domain/Pc/ComparacionPc.php`.
- Modificar: `src/CombateRuta/App/CalcularComparacionPcRuta.php`.
- Modificar: `src/CombateEntrenadores/App/CalcularComparacionPcEntrenador.php`.
- Modificar: `src/Gimnasios/App/CalcularComparacionPcGimnasio.php`.
- Modificar: `src/CombateRuta/Infra/Controllers/CombateRutaController.php`.
- Modificar: `src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php`.
- Modificar: `src/Gimnasios/Infra/Controllers/GimnasioController.php`.
- Nuevos en `app/Support/Pc`: DTOs de leyenda y `ConstruirLeyendaIvsEvs`.
- Nuevo partial: `resources/views/partials/leyenda-ivs-evs.blade.php`.
- Incluir desde: `resources/views/partials/comparacion-pc.blade.php`.

### Tablas / contratos

- Ninguna tabla nueva ni cambio de esquema.
- Contrato aditivo en las respuestas GET de PC.
- No cambia el contrato existente de `ComparacionPc::toArray()` si se añade un bloque separado.

### Vistas / endpoints

- `GET /api/habitats/{habitat}/ruta/pc`
- `GET /api/habitats/{habitat}/entrenadores/{nivel}/{trainer}/pc`
- `GET /api/gimnasios/{gym}/pc`
- Modales de `resources/views/habitats/partials/modal-formacion.blade.php` y `resources/views/gimnasios/show.blade.php`.

## Evidencia

- `src/Shared/Domain/PerfilIvsEvs.php:13-18` — casos `Nulo`, `TopUno`, `TopDos`, `Completo`.
- `src/Shared/Domain/PerfilIvsEvs.php:23-31` — `cuantosMaximos()`: 0, 1, 2, 6.
- `src/Shared/Domain/PerfilIvsEvs.php:33-41` — IV máximo 15 y EV máximo 255.
- `src/Shared/Domain/PerfilIvsEvs.php:46-53` — IV del resto: Nulo/TopUno 0; TopDos 8; Completo 15.
- `src/Shared/Domain/PerfilIvsEvs.php:58-65` — EV del resto: Nulo/TopUno 0; TopDos 128; Completo 255.
- `src/Shared/Domain/PoliticaIvsEvs.php:18-24` — `IV_MAXIMO=15`, `EV_MAXIMO=255`, `IV_MITAD=8`, `EV_MITAD=128`.
- `src/Shared/Domain/PoliticaIvsEvs.php:41-48` — ruta: 1→Nulo, 2→TopDos, 3→Completo.
- `src/Shared/Domain/PoliticaIvsEvs.php:53-60` — entrenador: 1→TopUno, 2→TopDos, 3→Completo.
- `src/Shared/Domain/PoliticaIvsEvs.php:66-80` — gimnasio: `ceil(indice/6)` → 1-6 TopUno, 7-12 TopDos, 13-18 Completo; líder sube un tier con tope Completo.
- `src/Shared/Domain/PoliticaIvsEvs.php:82-100` — IV/EV se reparten con `DistribuidorStatsValor::distribuirPorRanking`.
- `src/Shared/Domain/DistribuidorStatsValor.php:22` — orden de desempate fijo: hp, atk, def, spAtk, spDef, speed.
- `src/Shared/Domain/DistribuidorStatsValor.php:58-62` — ordena por stat base descendente y desempata por el orden fijo.
- `src/Shared/Domain/DistribuidorStatsValor.php:64-68` — asigna `valorResto` a todos y `valorPrincipal` a los N mejores.
- `src/Pokemon/Domain/Stats/BattleStats.php:9-10` — IV multiplica ×5; EV divide entre 4.
- `src/Pokemon/Domain/Stats/BattleStats.php:73-76` — fórmula de HP: `floor(((2*base + iv*5 + floor(ev/4)) * nivel/100) + nivel + 10)`.
- `src/Pokemon/Domain/Stats/BattleStats.php:78-81` — fórmula de stats no HP: `floor(((2*base + iv*5 + floor(ev/4)) * nivel/100) + 5)`.
- `src/Pokemon/Domain/Stats/DatosStats.php:14-24` — stats base tipados.
- `src/Pokemon/Domain/Stats/CalculadorCP.php:28-58` — CP canónico reutilizable con `desdeStats`.
- `src/Shared/Domain/Pc/ComparacionPc.php:13-26` — DTO de comparación sin perfil.
- `src/Shared/Domain/Pc/ComparacionPc.php:105-124` — contrato actual: estado, equipo, rival, comparación.
- `src/CombateRuta/App/CalcularComparacionPcRuta.php:52` — perfil `perfilRuta($nivel)`.
- `src/CombateRuta/App/CalcularComparacionPcRuta.php:55-62` — muestras con IV/EV del perfil al `nivelJugador`.
- `src/CombateEntrenadores/App/CalcularComparacionPcEntrenador.php:62` — perfil `perfilEntrenador($nivel)`.
- `src/CombateEntrenadores/App/CalcularComparacionPcEntrenador.php:56-66` — generación con `nivelRival: $nivelJugador`.
- `src/Gimnasios/App/CalcularComparacionPcGimnasio.php:52-55` — etapa actual y perfil `perfilGimnasio($indice, $etapa === 4)`.
- `src/Gimnasios/App/CalcularComparacionPcGimnasio.php:53` — `$nivelRival = escalador->escalar(nivelMinimo, nivelJugador)`.
- `src/Gimnasios/App/CalcularComparacionPcGimnasio.php:78-87` — stats del rival calculados al `nivelRival`, no al del jugador.
- `src/CombateRuta/Infra/Controllers/CombateRutaController.php:32-58` — validación y merge de `contexto`.
- `src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php:26-58` — validación y merge de `contexto`.
- `src/Gimnasios/Infra/Controllers/GimnasioController.php:28-52` — validación y merge de `contexto`.
- `resources/views/partials/comparacion-pc.blade.php:2-75` — sección PC con estados y notas contextuales; no hay leyenda.
- `resources/views/partials/comparacion-pc-script.blade.php:3-104` — Alpine de presentación; `pcComparacion = data` en línea 35.
- `resources/views/habitats/partials/modal-formacion.blade.php:41` — incluye el partial de comparación.
- `resources/views/gimnasios/show.blade.php:220` — incluye el partial de comparación.
- `resources/views/gimnasios/show.blade.php:246` — incluye el script de comparación.
- `resources/views/gimnasios/show.blade.php:362-367` — al seleccionar equipo carga `/api/gimnasios/{slug}/pc`.
- `resources/views/habitats/show.blade.php:31` — incluye el script.
- `resources/views/habitats/show.blade.php:88-93` — `selectedTeamMembers` sale de `teams`.
- `resources/views/habitats/show.blade.php:130-132` — recarga la comparación al cambiar nivel de ruta.
- `resources/views/habitats/show.blade.php:165-175` — apertura del modal y carga de comparación.
- `src/Equipos/Infra/EloquentTeamRepository.php:16-23` — `$teams` carga solo `members.reclutado.pokemon`, sin `pokemon.stats`.
- `app/Support/ReclutadoSerializer.php:76` — `/equipos` sí expone `stats_calculados`.
- `app/Support/ReclutadoSerializer.php:121-140` — desglose `base/iv/ev/total` calculado con `BattleStats`.
- `app/Support/ReclutadoSerializer.php:336-390` — helpers de stats, CP y stats base.
- `app/Support/Pc/CalculadoraPcRival.php:18-47` — CP rival reutilizando `CalculadorCP`.
- `src/Gimnasios/Infra/EloquentGymCatalogoRepository.php:46-63` — índice 1-18 por `nivelMinimo ASC, slug ASC`.
- `src/Gimnasios/Domain/CatalogoGimnasios.php:263-281` — misma semántica de índice.
- `app/Providers/AppServiceProvider.php:77-88` — `nivelJugador` compartido globalmente con las vistas.
- `docs/context.md:30` — documentación obsoleta que afirma IV de entrenadores 8-12; el código actual usa `PoliticaIvsEvs` 15/8.
- `src/Shared/Domain/IvsRangoEntrenador.php:14-40` y `src/Gimnasios/Domain/EvsRangoEntrenador.php:11-38` — clases legacy sin uso actual en generadores.
- `.ai/rules/frontend-minimal-logic.md:5-6` — prohíbe replicar fórmulas en frontend.
- `.ai/rules/arrays-dto-collections.md:5-32` — prohíbe arrays como contrato; usar DTOs readonly.
- `git status` (lectura) — TASK-035 está en working tree sin confirmar; no confundir con código base.

## Dependencias

- `PoliticaIvsEvs` depende de `DistribuidorStatsValor` y `DatosStats`.
- `PerfilIvsEvs` depende de constantes de `PoliticaIvsEvs`.
- `CalcularComparacionPc*` dependen de `App\Support\Pc\*` y de `Src\Shared\Domain\Pc\*`.
- Los controladores ya dependen de los casos de uso y de `Auth`.
- El frontend depende del contrato JSON de PC y de Alpine.
- El ejemplo numérico depende de `BattleStats` y de un conjunto de stats base.
- `src/Shared` tiene la regla de no depender de módulos (`src/Shared/README.md`), pero `PoliticaIvsEvs` ya importa `Src\Pokemon\Domain\Stats`. Un nuevo builder que use `BattleStats` no debería ubicarse en `src/Shared/Domain`; hay precedente de DTOs en `app/Support/Pc`.

## Duplicidades y deuda técnica

- `IvsRangoEntrenador` y `EvsRangoEntrenador` son legado sin uso en los generadores actuales; sus valores (8-12, 64/128/252) no coinciden con `PoliticaIvsEvs`. No deben usarse para la leyenda.
- `docs/context.md:30` describe un reparto de IV obsoleto.
- El bloque `contexto` se construye de forma casi idéntica en los tres controladores; un presentador compartido podría reducirlo, pero está fuera del alcance mínimo.
- El frontend ya tiene helpers de stats en `/equipos` (`hexStatsBarras`, `hexStatsCalculadosConAporte`), pero no son reutilizables en el modal porque los datos no se cargan allí y la lógica no debe replicarse.
- `ComparacionPc` no expone el perfil aplicado; los controladores tendrían que duplicar la resolución del gimnasio si no se añade al resultado del caso de uso.

## Riesgos

- **[Alto] Usar una fuente IV/EV distinta de `PoliticaIvsEvs`.** La leyenda contradiría el combate real (legacy 8-12 vs actual 15/8). Mitigación: única fuente `PoliticaIvsEvs`; no copiar constantes en Blade.
- **[Alto] Usar el nivel del jugador en gimnasios.** El rival se calcula al nivel escalado. Mitigación: que el caso de uso entregue `perfilRival` y `nivelStatsRival`; el builder no debe inferirlos.
- **[Medio] Filtrar información del rival si el ejemplo usa una especie del pool/roster.** Mitigación: ejemplo sintético y anónimo, etiquetado como ilustrativo.
- **[Medio] Duplicar la fórmula en frontend.** Mitigación: backend entrega stats ya calculados; JS solo formatea.
- **[Medio] Sobrecargar el modal.** Mitigación: `<details>` cerrado por defecto, tabla compacta, scroll horizontal accesible.
- **[Medio] Cambiar `ComparacionPc` y romper consumidores.** Mitigación: parámetros opcionales con valor por defecto y contrato aditivo; solo hay tres consumidores verificados.
- **[Medio] La leyenda no aparece antes de seleccionar equipo.** El endpoint exige `team_id`. Mitigación: aceptarlo o pasar la leyenda estática desde los controladores de vista.
- **[Bajo] Precisión float al serializar.** Mitigación: convertir a int en la frontera.
- **[Bajo] Accesibilidad de tablas y `<details>`.** Mitigación: `caption`, `scope`, texto además de color, foco visible.
- **[Bajo] Rendimiento.** El ejemplo sintético no requiere BD y el cálculo es trivial.
- **[Bajo] Confusión Nulo vs TopUno.** Ambos tienen resto 0; la diferencia es cuántos stats reciben el máximo. Mitigación: copy explícito.

## Opciones

1. **Backend aditivo en la respuesta de PC + ejemplo sintético + `<details>` en el partial (recomendada).**
   - Añadir al resultado del caso de uso el perfil aplicado y el nivel de stats del rival.
   - Controlador construye `leyenda_ivs_evs` con un builder de `app/Support/Pc`.
   - Partial incluido desde `comparacion-pc.blade.php`.
   - Ventajas: una sola petición, sin duplicar fórmulas, contexto siempre correcto.
   - Inconvenientes: la leyenda aparece solo tras seleccionar equipo.

2. **Leyenda estática en datos de vista + solo perfil actual desde PC.**
   - Los controladores de vista construyen la leyenda al renderizar; el endpoint solo añade el perfil actual.
   - Ventajas: visible antes de seleccionar equipo.
   - Inconvenientes: dos fuentes, más acoplamiento a las vistas, el gimnasio necesita el perfil actual por endpoint igualmente.

3. **Endpoint nuevo `GET /.../leyenda`.**
   - Descartada: petición extra, más estados de carga/error y sin ventaja real.

4. **Cálculo en frontend.**
   - Descartada: prohibido por reglas, faltan stats base en el payload de equipos y habría que duplicar `BattleStats` y `PoliticaIvsEvs`.

5. **Ejemplo con especie real cargada por slug en vez de sintética.**
   - Alternativa válida si producto la prefiere: datos reales sin duplicar números, pero añade consulta, dependencia del catálogo y posible confusión/ambigüedad de fuga. Descartada como opción principal.

## Suposiciones

- El modal afectado es el de hábitats (ruta/entrenadores) y el de gimnasios; mazmorras no está en alcance.
- "Nivel de combate" significa el nivel al que se calculan los stats del rival: `nivelJugador` en ruta/entrenador y `nivelRival` escalado en gimnasios.
- Producto acepta un Pokémon de ejemplo sintético, sin nombre ni sprite, etiquetado como ilustrativo.
- El perfil actual puede ser `null` en estados no disponibles; la leyenda debe degradar sin ejemplo.
- Las etiquetas propuestas son "Sin IV/EV", "Top 1", "Top 2" y "Todo al máximo", sujetas a copy final.
- No se requiere persistencia ni recuerdo del estado abierto/cerrado.

## Preguntas bloqueantes

1. ¿Se acepta un ejemplo sintético anónimo o producto exige una especie real del catálogo?
2. ¿La leyenda debe estar visible antes de seleccionar equipo?
3. ¿Debe incluirse el CP del ejemplo o solo los stats?
4. ¿Se confirma que mostrar el perfil aplicado al gimnasio/líder no se considera información sensible?
5. ¿Cuáles son las etiquetas y el copy definitivos?
6. ¿El ejemplo debe mostrar solo el perfil actual o los cuatro perfiles con números?
7. ¿Debe mostrarse "Nulo" también en contextos donde nunca se usa (entrenador/gimnasio), como referencia?

## Recomendación

Implementar la opción 1:

- Única fuente de verdad: `PoliticaIvsEvs`.
- Añadir al resultado de los tres casos de uso un `PerfilRivalAplicado` readonly con `PerfilIvsEvs $perfil` y `int $nivelStats`.
- En gimnasios, calcularlo con `perfilGimnasio($indice, $etapa === 4)` y `$nivelRival`; en ruta/entrenador, con su perfil y `nivelJugador`.
- Exponerlo en `ComparacionPc` como propiedad opcional o en un DTO envolvente; preferible la propiedad opcional para no introducir una abstracción nueva.
- Construir la leyenda en `app/Support/Pc/ConstruirLeyendaIvsEvs`, devolviendo DTOs readonly con `toArray()` solo en la frontera.
- Contrato aditivo propuesto:

```json
{
  "perfil_ivs_evs": {
    "actual": "top_dos",
    "etiqueta_actual": "Top 2",
    "perfiles": [
      {
        "clave": "nulo",
        "etiqueta": "Sin IV/EV",
        "stats_al_maximo": 0,
        "iv": { "maximo": 15, "resto": 0 },
        "ev": { "maximo": 255, "resto": 0 }
      }
    ],
    "ejemplo": {
      "etiqueta": "Ejemplo ilustrativo",
      "nivel": 50,
      "stats_base": { "hp": 70, "atk": 110, "def": 80, "spAtk": 65, "spDef": 75, "speed": 95 },
      "ivs": { "hp": 8, "atk": 15, "def": 8, "spAtk": 8, "spDef": 8, "speed": 15 },
      "evs": { "hp": 128, "atk": 255, "def": 128, "spAtk": 128, "spDef": 128, "speed": 255 },
      "stats_calculados": { "hp": 166, "atk": 184, "def": 121, "spAtk": 106, "spDef": 116, "speed": 169 }
    }
  }
}
```

Los números anteriores son una comprobación manual de las fórmulas de `BattleStats` al nivel 50 con el ejemplo propuesto (TopDos), no una ejecución.

- UI: `<details>` nativo cerrado por defecto, resumen "Perfiles de IV/EV del rival" con chip del perfil actual; dentro, tabla compacta de 4 perfiles + tabla de ejemplo de 5 columnas (Stat, Base, IV, EV, Calculado); resaltar el perfil actual con texto ("Este combate") y `aria-current`, no solo color; `caption` sr-only, `scope`, `tabular-nums` y contenedor `overflow-x-auto` con `role="region"` y foco.
- No mostrar nombre, sprite ni datos de especies del rival.

## Verificación ejecutada

- Inspección estática de archivos y búsquedas con `grep`/lectura.
- Verificación de estado de Git y rama (solo lectura).
- Comprobación manual de las fórmulas de `BattleStats` con el ejemplo propuesto al nivel 50.
- No se ejecutaron tests, linters ni comandos de escritura.
- No se ejecutaron migraciones, seeders ni consultas a base de datos.

## Problemas detectados

- `docs/context.md:30` está obsoleto respecto al reparto real de IV/EV.
- `IvsRangoEntrenador` y `EvsRangoEntrenador` son código legacy potencialmente engañoso.
- El payload de `$teams` en los modales no incluye stats base ni `stats_calculados`.
- En gimnasios el nivel del rival no coincide con el del jugador.
- Los controladores duplican la construcción de `contexto`.
- La leyenda no puede mostrarse antes de seleccionar equipo con el diseño actual del endpoint.

## Riesgos pendientes

- Confirmar con producto la aceptación del ejemplo sintético.
- Confirmar el copy y las etiquetas de los perfiles.
- Confirmar si se incluye CP en el ejemplo.
- Confirmar el tratamiento de `perfil_actual = null` en gimnasios no disponibles.
- Confirmar que mostrar el perfil del gimnasio/líder es aceptable.
- Validar el ancho real de la tabla en móvil dentro de `max-w-md`.
