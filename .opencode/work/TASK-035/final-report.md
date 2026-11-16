# TASK-035 — Informe final

- **ID:** TASK-035
- **Fecha:** 2026-11-09
- **Estado final:** **COMPLETED**
- **Plan aprobado:** v1 (`approval.md`, «Aprobar plan v1»)
- **Rama:** `task-011`
- **Proyecto:** `/home/david/pokemon`

## 1. Resumen

Se implementó la comparación aproximada de **PC** entre el equipo seleccionado y el rival en el modal **Configurar Formación**, antes de confirmar el combate:

- **Ruta 3v3:** PC del equipo vs PC estimado del rival (media ponderada del pool × 3), con `≈` y nota de que el rival se decide al combatir.
- **Entrenador de hábitat:** PC del equipo vs PC exacto del rival determinista que se combatirá.
- **Gimnasio:** PC del equipo vs PC exacto del roster de la etapa actual, sin revelar composición ni nivel rival.
- **Veredicto:** Favorable / Parejo / Desfavorable (`ratio = pc_rival / pc_equipo`; umbrales 0.8/1.2).
- **Cálculo backend** reutilizando el CP canónico (`CalculadorCP` + `BattleStats`); el frontend solo presenta.
- Estados de carga, error, equipo incompleto, sin rival y no disponible, sin bloquear nunca el botón “Combatir”.
- Accesibilidad: `aria-live`, `aria-busy`, `role="status"`, texto + icono, `motion-reduce`.

## 2. Archivos creados

### Dominio y soporte

- `src/Shared/Domain/Pc/EstadoComparacionPc.php`
- `src/Shared/Domain/Pc/MetodoPcRival.php`
- `src/Shared/Domain/Pc/VeredictoPc.php`
- `src/Shared/Domain/Pc/UmbralesVeredictoPc.php`
- `src/Shared/Domain/Pc/ComparadorPc.php`
- `src/Shared/Domain/Pc/ComparacionPc.php`
- `app/Support/Pc/EstadisticasBasePokemon.php`
- `app/Support/Pc/CalculadoraPcReclutado.php`
- `app/Support/Pc/ObtenerPcEquipoJugador.php`
- `app/Support/Pc/PcEquipoJugador.php`
- `app/Support/Pc/MuestraPcRival.php`
- `app/Support/Pc/CalculadoraPcRival.php`

### Casos de uso y DTO

- `src/CombateRuta/Domain/DataTransferObjects/EspeciePonderadaRuta.php`
- `src/CombateRuta/App/CalcularComparacionPcRuta.php`
- `src/CombateEntrenadores/App/CalcularComparacionPcEntrenador.php`
- `src/Gimnasios/App/CalcularComparacionPcGimnasio.php`

### Frontend

- `resources/views/partials/comparacion-pc.blade.php`
- `resources/views/partials/comparacion-pc-script.blade.php`

## 3. Archivos modificados

### Backend

- `src/Pokemon/Domain/Stats/CalculadorCP.php` — añadido `desdeStats()` aditivo; `calcular()` intacto.
- `app/Support/ReclutadoSerializer.php` — `cpDe()` y `statsBaseValue()` delegan en el soporte compartido, preservando el contrato.
- `src/CombateRuta/App/GeneradorEquipoRuta.php` — método aditivo `especiesPonderadas()`.
- `src/CombateRuta/Infra/Controllers/CombateRutaController.php` — método `pc()`.
- `src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php` — método `pc()`.
- `src/Gimnasios/Infra/Controllers/GimnasioController.php` — método `pc()`.
- `routes/ruta.php`
- `routes/entrenadores.php`
- `routes/gimnasios.php`

### Frontend

- `resources/views/habitats/partials/modal-formacion.blade.php`
- `resources/views/habitats/show.blade.php`
- `resources/views/gimnasios/show.blade.php`

## 4. Contratos

```http
GET /api/habitats/{habitat}/ruta/pc?team_id={int}&nivel={1|2|3}
GET /api/habitats/{habitat}/entrenadores/{nivel}/{trainer}/pc?team_id={int}
GET /api/gimnasios/{gym}/pc?team_id={int}
```

Respuesta:

```json
{
  "estado": "ok",
  "equipo": { "miembros": 3, "completo": true, "pc": 1240 },
  "rival": { "pc": 1050, "aproximado": true, "metodo": "promedio_pool" },
  "comparacion": { "ratio": 0.85, "veredicto": "parejo" },
  "contexto": { "tipo": "ruta", "habitat_id": 12, "nivel": 2 }
}
```

Estados: `ok`, `equipo_incompleto`, `sin_rivales`, `no_disponible`. Todos los endpoints bajo `auth`. `team_id` validado por propietario. Fecha de entrenador y etapa de gimnasio se resuelven en servidor. La respuesta no expone composición, especies, tipos, niveles individuales, IV/EV, movimientos ni `nivel_rival`.

## 5. Decisiones y ADR

- Se reutiliza el CP canónico existente; no se crea un segundo indicador ni se duplica la fórmula en JS.
- Ruta: media ponderada del pool × 3, marcada como aproximada; no se usa el endpoint deprecated `GET /ruta/rivales`.
- Entrenador/gimnasio: se reutilizan los generadores reales para que el rival previsualizado coincida con el del combate.
- Gimnasio: solo agregado, sin revelar composición ni nivel rival.
- Presentación: totales + veredicto; umbrales centralizados en `UmbralesVeredictoPc`.
- Formación vanguardia/retaguardia no altera el PC ni provoca refetch.
- Sin caché, migraciones, dependencias ni cambios de balance en el MVP.
- ADR borrador incluido en `architecture.md`; no se creó documentación adicional porque el proyecto no la solicitó.

## 6. Verificación ejecutada

### Análisis estático

- `php -l` sobre todos los archivos PHP nuevos/modificados: **OK**.
- PHPStan acotado a TASK-035: **0 errores**.
- Pint sobre los archivos del alcance: **pass**.
- `php artisan route:list --path=pc`: **3 rutas GET** bajo `web` + `Authenticate`.
- `php artisan view:cache`: **Blade compila correctamente**.
- `grep` de fórmulas CP en `resources/`: **sin coincidencias**.
- `tests/`: **sin cambios**.

Matiz: `composer analyse` global reporta 34 errores y `composer arch` 100 violaciones **preexistentes**, ajenos a los archivos de TASK-035. Filtrado por alcance, 0 errores y 0 violaciones nuevas.

### Comprobación runtime (tinker, solo lectura)

- Ruta, hábitat 1, nivel 2: `ok`, equipo 8.893, rival 9.191, ratio 1.03, **parejo**.
- Entrenador, hábitat 1, nivel 1, índice 1: `ok`, rival 3.193, **favorable**.
- Gimnasio `bug`: `ok`, rival 2.299, **favorable**.
- Gimnasio `poison` completado: `no_disponible`.
- `team_id` ajeno: **422**.
- JSON inspeccionado: sin `especies`, `species_id`, `nivel_rival`, `ivs`, `evs`, `movimientos`, `moves`, `tipos` ni `types`.

### No ejecutado

- Suite de tests: fuera de alcance.
- Verificación visual en navegador: no hay entorno interactivo; la compilación Blade y la revisión estática están cubiertas.

## 7. Hallazgos de revisión

**Veredicto del reviewer: APPROVED.** Sin hallazgos críticos, altos ni medios.

- **Bajo:** mapeo `DatosStats → StatsValue` duplicado en dos puntos; se puede centralizar.
- **Bajo:** `pc = 0` no se trata como `equipo_incompleto` (caso degenerado inalcanzable con datos reales).
- **Bajo:** miembro sin filas de stats se excluye del conteo; caso de datos degenerado.
- **NIT:** `no_disponible` del gimnasio no se muestra porque el modal no abre si el gimnasio no está disponible (defensivo).
- **NIT:** `selectTeam` del gimnasio recarga al pulsar el mismo equipo.

Ninguno bloquea la entrega; quedan como deuda menor opcional.

## 8. Hallazgos de seguridad

**Veredicto: FINDINGS, sin bloqueantes introducidos por TASK-035.**

- **Medio (preexistente, no introducido):** XSS reflejado en `resources/views/gimnasios/show.blade.php:11` por interpolar `$slug` dentro de `x-data="gimnasioShow('{{ $slug }}')"`. Un slug manipulado podría ejecutar JS en sesión autenticada. Recomendación: `data-slug="{{ $slug }}"` + `x-data="gimnasioShow($el.dataset.slug)"` o `@js($slug)`, y validar el slug en el controlador. **No bloquea TASK-035**, pero debe corregirse en tarea aparte o aceptarse por escrito.
- **Bajo:** endpoints de cálculo sin throttle (carencia global del proyecto).
- **Bajo:** JSON de usuario sin `Cache-Control: private, no-store` explícito.
- **Info:** nivel de ruta/entrenador no gateado en servidor (solo agregado; mismo comportamiento que el endpoint preexistente).
- **Info:** `console.error` en el mixin, sin datos sensibles.

Controles correctos: auth en las 3 rutas, validación de propiedad del equipo en controlador y caso de uso, parámetros validados, servidor como fuente de verdad, solo lectura, sin escrituras ni mass assignment, sin secretos en logs, sin dependencias nuevas.

`composer audit` reporta avisos preexistentes en Laravel/Symfony/Livewire/CommonMark; planificar actualización en tarea separada.

## 9. Riesgos residuales

- **Ruta no determinista:** el número es una esperanza matemática; mitigado con `aproximado`, `≈` y nota, pero puede leerse como rival exacto.
- **Peso del término EV:** el CP puede producir comparaciones contraintuitivas; los umbrales 0.8/1.2 no están calibrados con datos reales.
- **Latencia:** entrenador/gimnasio ejecutan el generador completo (incluye movimientos); sin caché en el MVP.
- **XSS preexistente** en `gimnasios/show.blade.php:11`.
- **Dependencias** con avisos de seguridad preexistentes.
- **Verificación visual** en navegador pendiente.

## 10. Próximos pasos

1. Corregir el XSS reflejado preexistente de `gimnasios/show.blade.php` en una tarea específica (prioridad Media/Alta según política interna).
2. Evaluar `throttle` y `Cache-Control: private, no-store` en los endpoints de cálculo como hardening.
3. Calibrar umbrales 0.8/1.2 con datos reales y valorar si el término EV del CP distorsiona el veredicto.
4. Planificar actualización de dependencias por `composer audit`.
5. Confirmar en despliegue `APP_DEBUG=false` y cookies `Secure`/`HttpOnly`/`SameSite`.
6. Revisar visualmente en navegador los estados de la UI (cargando, error, equipo incompleto, sin rival, no disponible).

## 11. Nota de concurrencia

El árbol contiene cambios de **TASK-036** en `app/Livewire/*`, `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php` y `resources/views/livewire/partials/moves-panel.blade.php`, y `bugs.txt` modificado. No pertenecen a TASK-035 y no se tocaron ni se incluyen en este informe.
