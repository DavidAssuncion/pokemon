# TASK-037 — Plan v1

- **ID:** TASK-037
- **Versión:** v1
- **Fecha:** 2026-11-09
- **Estado:** AWAITING_APPROVAL — no autoriza implementación
- **Base:** rama `task-011`, con TASK-035 en working tree
- **Análisis:** `analysis.md` (COMPLETED)
- **Diseño:** `architecture.md` (COMPLETED)

## 1. Objetivo

Añadir al modal **Configurar Formación** una leyenda de **stats e IV/EV del rival** basada en el **equipo exacto contra el que se peleará**, con stats anónimos por Pokémon (base, IV, EV y calculados) y el perfil IV/EV aplicado. La leyenda debe estar **siempre visible** y complementar el comparador de PC de TASK-035.

Para hacerlo posible en ruta 3v3, el rival se **fija al abrir el modal**, se guarda en sesión y el combate reutiliza exactamente ese rival.

## 2. Alcance

### 2.1. Incluido

- Fijación del rival de ruta 3v3 al abrir el modal, con `preview_id` en sesión y TTL de 1 hora.
- Reutilización exacta del rival fijado en `IniciarCombateRuta`.
- Ajuste de stats con `conNivel` si el jugador cambia de nivel entre preview y combate.
- Endpoint de limpieza `DELETE /api/habitats/{habitat}/ruta/preview/{previewId}`.
- Bloque aditivo `perfil_ivs_evs` en las respuestas GET de PC:
  - Perfil actual y los cuatro perfiles (Sin IV/EV, Top 1, Top 2, Todo al máximo).
  - Stats anónimos por Pokémon rival: `Rival 1..3`, stat, base, IV, EV, calculado.
- `team_id` opcional en el endpoint de gimnasio para mostrar la leyenda antes de elegir equipo.
- PC de ruta exacto (`metodo: rival_fijado`, `aproximado: false`).
- Partial compartido `leyenda-ivs-evs.blade.php`, siempre visible y accesible.
- Cuerpo desplazable en ambos modales para que la leyenda no rompa el layout.
- Limpieza del preview al cancelar/cerrar el modal y al iniciar combate.

### 2.2. Fuera de alcance

- Cambiar fórmulas, balance, IV/EV, stats o esquema.
- Añadir migraciones, dependencias, factories, seeders o tests.
- Eliminar el código muerto de la aproximación de TASK-035 (`MuestraPcRival`, `promedioPonderado`, `especiesPonderadas`, `EspeciePonderadaRuta`). Queda como limpieza posterior, una vez consolidado TASK-035; evita borrar archivos nuevos sin commit.
- Mostrar nombres, especies, tipos, movimientos, formación, posición, habilidad u objeto del rival.
- Exponer `nivel_stats` del rival.
- Cambiar el flujo de combate de entrenador o gimnasio.
- Tocar cambios concurrentes de TASK-036.

## 3. Decisiones de producto y diseño

1. **Ruta exacta:** el rival se fija al abrir el modal; el combate reutiliza el snapshot. No se regenera al combatir.
2. **Almacenamiento:** snapshot serializado en sesión con `preview_id` aleatorio de 128 bits y TTL de 1 hora. Se descarta la semilla porque no garantiza el mismo rival si cambian pool, nivel o movimientos.
3. **Cambio de nivel:** se conserva la composición y se recalculan stats con `conNivel`; el combate nunca mezcla niveles.
4. **PC de ruta:** exacto, `metodo: rival_fijado`, `aproximado: false`.
5. **Leyenda:** siempre visible, sin `<details>`, con los cuatro perfiles y el actual resaltado con texto “Este combate” y `aria-current`.
6. **Stats del rival:** anónimos por Pokémon (“Rival 1..3”); nunca nombres, especies, tipos ni movimientos.
7. **Gimnasio sin equipo:** `team_id` opcional; la leyenda se muestra y la sección de PC indica “Selecciona un equipo”.
8. **GET con fijación:** la primera llamada a `/pc` de ruta crea el snapshot. Es un efecto de sesión idempotente por `preview_id`, no una escritura de negocio. Se documenta.
9. **Limpieza:** `DELETE` al cancelar/cerrar; limpieza tras crear la batalla. Si el `preview_id` falta o caduca, el combate cae al generador actual.
10. **Sin `nivel_stats`:** los stats calculados permiten inferir el nivel del rival; producto lo acepta.
11. **Sin borrado de código muerto:** se deja para una tarea de limpieza posterior.
12. **Sin `preview_id` en la meta de batalla:** no es necesario para el MVP.
13. **Modal:** cuerpo desplazable `max-h-[70vh] overflow-y-auto` y botones fijos.

## 4. Arquitectura y contratos

Se sigue `architecture.md`:

- **Snapshot de ruta:** `App\Support\CombateRuta\RivalRutaFijado` + `AlmacenRivalRutaFijado`.
- **Dominio aditivo:** `MetodoPcRival::RIVAL_FIJADO`; `DatosPokemonBatalla::conNivel()` y `DatosPokemonBatallaCollection::conNivel()`.
- **Support de leyenda:** DTOs readonly `PerfilRivalAplicado`, `PerfilIvsEvsOpcion`, `StatPreview`, `StatsPreview`, `PokemonRivalPreview`, `LeyendaIvsEvs`, `VistaPreviaRival` y builder `ConstruirLeyendaIvsEvs`.
- **Casos de uso:** reescribir `CalcularComparacionPcRuta`; ampliar `CalcularComparacionPcEntrenador` y `CalcularComparacionPcGimnasio`; ampliar `IniciarCombateRuta`.
- **Controladores:** añadir `preview_id` y `team_id` opcional; añadir método `limpiarPreview`.
- **Frontend:** partial `leyenda-ivs-evs.blade.php`, cambios en `comparacion-pc.blade.php`, `comparacion-pc-script.blade.php`, modales y vistas.

### 4.1. Endpoints

```http
GET    /api/habitats/{habitat}/ruta/pc?team_id={int}&nivel={1|2|3}&preview_id={string?}
GET    /api/habitats/{habitat}/entrenadores/{nivel}/{trainer}/pc?team_id={int}
GET    /api/gimnasios/{gym}/pc?team_id={int?}
DELETE /api/habitats/{habitat}/ruta/preview/{previewId}
```

Todos bajo `auth`. `team_id` validado por propietario. `preview_id` solo en ruta. `fecha` de entrenador y etapa de gimnasio se resuelven en servidor.

### 4.2. Respuesta

```json
{
  "estado": "ok",
  "preview_id": "a1b2...",
  "equipo": { "miembros": 3, "completo": true, "pc": 1240 },
  "rival": { "pc": 1180, "aproximado": false, "metodo": "rival_fijado" },
  "comparacion": { "ratio": 0.95, "veredicto": "parejo" },
  "perfil_ivs_evs": {
    "actual": { "clave": "top_dos", "etiqueta": "Top 2" },
    "perfiles": [
      {
        "clave": "nulo",
        "etiqueta": "Sin IV/EV",
        "descripcion": "Ningún stat recibe el máximo; todos llevan IV 0 y EV 0.",
        "stats_al_maximo": 0,
        "iv_maximo": 15,
        "iv_resto": 0,
        "ev_maximo": 255,
        "ev_resto": 0,
        "actual": false
      }
    ],
    "pokemons": [
      {
        "etiqueta": "Rival 1",
        "stats": [
          { "clave": "hp", "etiqueta": "PS", "base": 70, "iv": 8, "ev": 128, "calculado": 166 }
        ]
      }
    ]
  },
  "contexto": { "tipo": "ruta", "habitat_id": 12, "nivel": 2 }
}
```

Sin `nombre`, `species_id`, `tipos`, `moves`, `posicion`, `nivel_stats`, `ivs`/`evs` completos fuera de los stats permitidos, ni formación del rival.

## 5. Archivos y responsables

### 5.1. Backend — dominio y soporte

| # | Acción | Archivo | Responsable |
|---|---|---|---|
| 1 | Modificar | `src/Shared/Domain/Pc/MetodoPcRival.php` (añadir `RIVAL_FIJADO`) | backend |
| 2 | Modificar | `src/Battle/Domain/DatosPokemonBatalla.php` (`conNivel`) | backend |
| 3 | Modificar | `src/Battle/Domain/Collections/DatosPokemonBatallaCollection.php` (`conNivel`) | backend |
| 4 | Crear | `app/Support/CombateRuta/RivalRutaFijado.php` | backend |
| 5 | Crear | `app/Support/CombateRuta/AlmacenRivalRutaFijado.php` | backend |
| 6 | Crear | `app/Support/Pc/PerfilRivalAplicado.php` | backend |
| 7 | Crear | `app/Support/Pc/PerfilIvsEvsOpcion.php` | backend |
| 8 | Crear | `app/Support/Pc/StatPreview.php` | backend |
| 9 | Crear | `app/Support/Pc/StatsPreview.php` | backend |
| 10 | Crear | `app/Support/Pc/PokemonRivalPreview.php` | backend |
| 11 | Crear | `app/Support/Pc/LeyendaIvsEvs.php` | backend |
| 12 | Crear | `app/Support/Pc/ConstruirLeyendaIvsEvs.php` | backend |
| 13 | Crear | `app/Support/Pc/VistaPreviaRival.php` | backend |
| 14 | Modificar | `app/Support/Pc/ObtenerPcEquipoJugador.php` (`?int $teamId`) | backend |

### 5.2. Backend — casos de uso, combate, controladores y rutas

| # | Acción | Archivo | Responsable |
|---|---|---|---|
| 15 | Modificar | `src/CombateRuta/App/CalcularComparacionPcRuta.php` | backend |
| 16 | Modificar | `src/CombateEntrenadores/App/CalcularComparacionPcEntrenador.php` | backend |
| 17 | Modificar | `src/Gimnasios/App/CalcularComparacionPcGimnasio.php` | backend |
| 18 | Modificar | `src/CombateRuta/App/IniciarCombateRuta.php` | backend |
| 19 | Modificar | `src/CombateRuta/Infra/Controllers/CombateRutaController.php` | backend |
| 20 | Modificar | `src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php` | backend |
| 21 | Modificar | `src/Gimnasios/Infra/Controllers/GimnasioController.php` | backend |
| 22 | Modificar | `routes/ruta.php` (DELETE preview) | backend |

### 5.3. Frontend

| # | Acción | Archivo | Responsable |
|---|---|---|---|
| 23 | Crear | `resources/views/partials/leyenda-ivs-evs.blade.php` | frontend |
| 24 | Modificar | `resources/views/partials/comparacion-pc.blade.php` | frontend |
| 25 | Modificar | `resources/views/partials/comparacion-pc-script.blade.php` | frontend |
| 26 | Modificar | `resources/views/habitats/partials/modal-formacion.blade.php` | frontend |
| 27 | Modificar | `resources/views/habitats/show.blade.php` | frontend |
| 28 | Modificar | `resources/views/gimnasios/show.blade.php` | frontend |

### 5.4. No aplica

- **Base de datos:** sin migraciones, constraints, índices ni transacciones nuevas. Solo sesión y lecturas.
- **Tests:** fuera de alcance; no se crean, modifican ni ejecutan.
- **Documentación:** no se solicita; el ADR queda como borrador en `architecture.md`.

## 6. Orden de ejecución y dependencias

1. **Fase A — Backend base (1–14):** dominio, snapshot, DTOs, builder y `ObtenerPcEquipoJugador`.
2. **Fase B — Backend de contexto (15–22):** casos de uso, `IniciarCombateRuta`, controladores y rutas.
3. **Fase C — Frontend (23–28):** leyenda, preview_id, limpieza, modales y scroll.
4. **Revisión independiente** (`reviewer`) y **auditoría de seguridad** (`security`) por el snapshot de sesión, el `preview_id` y la exposición de stats.
5. **Informe final**.

Dependencias y concurrencia:

- TASK-035 está sin commit; TASK-037 modifica sus archivos. No se revierte ni se commitea sin autorización.
- TASK-036 tiene cambios concurrentes en `app/Livewire/*`, `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php` y `resources/views/livewire/partials/moves-panel.blade.php`. No se tocan.
- `DatosPokemonBatalla`/`DatosPokemonBatallaCollection` se comparten con TASK-036 solo de forma aditiva (`conNivel`); verificar el diff antes de editar.
- Antes de la Fase C, comprobar `git status`/`git diff` de las vistas compartidas. Si hay cambios concurrentes, coordinar o usar worktree.
- No hacer `git push`, `git reset --hard`, `git clean` ni `git checkout --`.

## 7. Criterios de aceptación verificables

1. Al abrir el modal de ruta con un equipo de 3, se fija un rival y se devuelve `preview_id`; el combate iniciado con ese id usa exactamente el mismo rival (mismos stats/HP).
2. La leyenda está siempre visible en ambos modales, sin `<details>`, con los cuatro perfiles y el actual resaltado con texto “Este combate” y `aria-current`.
3. Se muestran stats anónimos por Pokémon (`Rival 1..3`): base, IV, EV y calculado, para los 6 stats.
4. El PC de ruta es exacto (`metodo: rival_fijado`, `aproximado: false`).
5. En gimnasio se puede cargar la leyenda sin equipo; la sección de PC muestra “Selecciona un equipo”.
6. Cambiar de equipo en ruta no cambia el rival; cambiar de nivel fija uno nuevo; cerrar/cancelar limpia el preview.
7. Si el jugador sube de nivel, se conserva la composición y se recalculan stats; el combate no mezcla niveles.
8. El JSON no contiene nombres, especies, tipos, movimientos, formación, posición ni `nivel_stats` del rival.
9. No hay fórmulas de stats/CP en JS/Blade; el frontend solo formatea.
10. No se modifican fórmulas, balance, IV/EV, esquema, dependencias ni tests.
11. El análisis estático no introduce errores nuevos.
12. El comparador de PC de TASK-035 sigue funcionando y el combate de TASK-036 no se ve afectado.

## 8. Verificación requerida

### 8.1. Análisis estático

```bash
php -l <archivos PHP nuevos/modificados>
vendor/bin/phpstan analyse -c phpstan.neon --no-progress <archivos del alcance>
vendor/bin/pint --format agent <archivos del alcance>
php artisan route:list --path=pc
php artisan route:list --path=preview
php artisan view:cache && php artisan view:clear
```

- PHPStan scoped sin errores nuevos.
- Pint limpio.
- `route:list`: 3 GET `/pc` y DELETE `/ruta/preview/{previewId}` bajo `auth`.
- Blade compila.

### 8.2. Comprobación runtime (sin tests)

- Abrir preview de ruta, obtener `preview_id`, volver a pedir con el mismo id y comprobar que los stats no cambian.
- Iniciar combate con ese `preview_id` y comparar el HP máximo de cada rival con los stats calculados de la leyenda.
- Cambiar de equipo y verificar que el rival no cambia.
- Cambiar de nivel y verificar que se fija un rival nuevo.
- Cancelar/cerrar y verificar que el `preview_id` se borra y un combate posterior genera otro rival.
- Gimnasio sin `team_id`: leyenda presente y `equipo` vacío.
- Entrenador: leyenda y PC coherentes con el rival determinista.
- `preview_id` ajeno o de otro hábitat/nivel: se ignora y se devuelve uno nuevo.
- `team_id` ajeno: 422.
- Inspeccionar JSON: sin claves prohibidas.

### 8.3. Revisión manual de UI

- Leyenda visible sin interacción, tablas legibles en móvil, scroll horizontal accesible y botones visibles.
- Perfil actual resaltado con texto y `aria-current`, no solo color.
- `caption`, `scope`, `role="region"`, `tabindex="0"`, `role="status"`/`alert`.
- No se rompe el modal de gimnasio sin equipo ni el de hábitats.

### 8.4. Sin tests

No se ejecuta la suite ni se crean/modifican archivos en `tests/`.

## 9. Riesgos y mitigaciones

| Severidad | Riesgo | Mitigación |
|---|---|---|
| Alto | Snapshot corrupto o cambio de formato entre despliegues | Versión, TTL, `instanceof`, `try/catch`, borrado del inválido |
| Alto | Subida de nivel entre preview y combate | `conNivel` conserva composición y recalcula stats |
| Alto | El rival se regenera si el `preview_id` no llega al combate | El frontend lo envía; fallback al generador y se documenta |
| Medio | Sesión caduca con el modal abierto | TTL 1 h; refresco al cambiar equipo/nivel; fallback |
| Medio | Múltiples pestañas | Cada `preview_id` es independiente |
| Medio | Fingerprinting de especies por stats base | Producto lo aprobó; sin nombres/tipos/movimientos |
| Medio | Inferencia del nivel de gimnasio | No se expone `nivel_stats`; se documenta |
| Medio | GET `/pc` con efecto de sesión | Idempotente por `preview_id`, solo sesión, TTL y limpieza |
| Medio | Tablas en `max-w-md` | `text-xs`, `tabular-nums`, scroll horizontal y vertical |
| Medio | Conflicto con TASK-036 en batalla/vistas | Backend aditivo; frontend al final; revisar diff |
| Bajo | Copy duplicado | El builder genera etiquetas y descripciones |
| Bajo | Confusión Nulo vs Top 1 | Tabla con “Stats al máximo” y descripción |
| Bajo | Confusión de orden con formación | Nota “El orden de los rivales no indica su posición en combate” |

## 10. Rollback

- Sin migraciones ni cambios de datos: el rollback es de código.
- Revertir los archivos de TASK-037 en orden inverso (frontend → controladores/rutas → aplicación → dominio).
- No se eliminan archivos de TASK-035; el código de aproximación queda intacto y puede volver a usarse si se revierte el caso de uso de ruta.
- No se ejecutan `git reset --hard`, `git clean` ni `git checkout --` sin aprobación.
- Si el frontend falla, puede desactivarse el partial de leyenda sin afectar al combate ni a los endpoints.

## 11. Aprobación

Este plan **no autoriza implementación** hasta recibir una aprobación explícita que mencione la versión:

- `Aprobar plan v1`
- `Implementa el plan v1`
- `Apruebo la versión 1 del plan`

La aprobación se registrará en `approval.md` con el texto literal, la fecha y el alcance autorizado.
