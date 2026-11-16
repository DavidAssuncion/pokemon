# TASK-037 — Informe final

- **ID:** TASK-037
- **Fecha:** 2026-11-09
- **Estado final:** **COMPLETED**
- **Plan aprobado:** v1 (`approval.md`, «Aprobar plan v1»)
- **Rama:** `task-011`
- **Proyecto:** `/home/david/pokemon`
- **Base:** TASK-035 (comparador de PC) en working tree

## 1. Resumen

Se añadió al modal **Configurar Formación** una leyenda de **stats e IV/EV del rival** basada en el **equipo exacto contra el que se peleará**:

- **Ruta 3v3:** el rival se fija al abrir el modal y se guarda como snapshot en sesión (`preview_id`, TTL 1 h); el combate reutiliza exactamente ese rival.
- **Entrenador y gimnasio:** el rival ya era determinista; se calcula exacto sin cambiar el flujo de combate.
- **Leyenda siempre visible:** cuatro perfiles IV/EV (Sin IV/EV, Top 1, Top 2, Todo al máximo) y stats anónimos por Pokémon (`Rival 1..3`): base, IV, EV y calculado.
- **Gimnasio sin equipo:** la leyenda se muestra igualmente; `team_id` es opcional y la sección de PC indica “Selecciona un equipo”.
- **PC de ruta exacto:** `metodo: rival_fijado`, `aproximado: false`.
- **Cambio de nivel:** se conserva la composición y se recalculan stats con `conNivel`; el combate no mezcla niveles.
- Sin cambios de fórmulas, balance, IV/EV, esquema, dependencias ni tests.

## 2. Archivos creados

### Backend

- `app/Support/CombateRuta/RivalRutaFijado.php`
- `app/Support/CombateRuta/AlmacenRivalRutaFijado.php`
- `app/Support/Pc/PerfilRivalAplicado.php`
- `app/Support/Pc/PerfilIvsEvsOpcion.php`
- `app/Support/Pc/StatPreview.php`
- `app/Support/Pc/StatsPreview.php`
- `app/Support/Pc/PokemonRivalPreview.php`
- `app/Support/Pc/LeyendaIvsEvs.php`
- `app/Support/Pc/ConstruirLeyendaIvsEvs.php`
- `app/Support/Pc/VistaPreviaRival.php`

### Frontend

- `resources/views/partials/leyenda-ivs-evs.blade.php`

## 3. Archivos modificados

### Backend

- `src/Shared/Domain/Pc/MetodoPcRival.php` — añadido `RIVAL_FIJADO`.
- `src/Battle/Domain/DatosPokemonBatalla.php` — `conNivel()`.
- `src/Battle/Domain/Collections/DatosPokemonBatallaCollection.php` — `conNivel()`.
- `app/Support/Pc/ObtenerPcEquipoJugador.php` — `?int $teamId`.
- `src/CombateRuta/App/CalcularComparacionPcRuta.php` — rival fijado, PC exacto y leyenda.
- `src/CombateEntrenadores/App/CalcularComparacionPcEntrenador.php` — leyenda y generación antes de validar equipo.
- `src/Gimnasios/App/CalcularComparacionPcGimnasio.php` — leyenda, `team_id` opcional y nivel rival escalado.
- `src/CombateRuta/App/IniciarCombateRuta.php` — consume y limpia el snapshot.
- `src/CombateRuta/Infra/Controllers/CombateRutaController.php` — `preview_id`, DELETE y respuesta de leyenda.
- `src/Gimnasios/Infra/Controllers/GimnasioController.php` — `team_id` opcional.
- `routes/ruta.php` — `DELETE /api/habitats/{habitat}/ruta/preview/{previewId}`.

### Frontend

- `resources/views/partials/comparacion-pc.blade.php` — notas de ruta/gimnasio.
- `resources/views/partials/comparacion-pc-script.blade.php` — `pcPreviewId` y `limpiarPreviewRuta()`.
- `resources/views/habitats/partials/modal-formacion.blade.php` — leyenda, scroll y botones fijos.
- `resources/views/habitats/show.blade.php` — `preview_id` en URL/confirmación, limpieza y recarga por nivel.
- `resources/views/gimnasios/show.blade.php` — leyenda, scroll y carga sin equipo.

### No aplica

- **Base de datos:** sin migraciones, constraints, índices ni transacciones nuevas. Solo sesión y lecturas.
- **Tests:** fuera de alcance; no se crean, modifican ni ejecutan.
- **Documentación:** no se solicita; ADR borrador en `architecture.md`.

## 4. Contratos

```http
GET    /api/habitats/{habitat}/ruta/pc?team_id={int}&nivel={1|2|3}&preview_id={string?}
GET    /api/habitats/{habitat}/entrenadores/{nivel}/{trainer}/pc?team_id={int}
GET    /api/gimnasios/{gym}/pc?team_id={int?}
DELETE /api/habitats/{habitat}/ruta/preview/{previewId}
```

Respuesta:

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

Sin `nombre`, `species_id`, `tipos`, `movimientos`, `posicion`, `nivel_stats` ni formación del rival. Los stats se agrupan en `Rival 1..3` con orden neutral.

## 5. Decisiones y ADR

- **Snapshot, no semilla:** se guarda el equipo serializado en sesión para garantizar exactitud de especie, shiny, movimientos e IV/EV; una semilla no garantiza el mismo rival si cambian pool, nivel o movimientos.
- **`preview_id` aleatorio de 128 bits**, clave con `userId`, validación de hábitat/nivel, TTL 1 h y tope de 5 snapshots por usuario.
- **`conNivel`:** conserva composición y recalcula stats si el jugador cambia de nivel entre preview y combate.
- **PC de ruta exacto:** `rival_fijado`, `aproximado: false`.
- **Gimnasio sin equipo:** `team_id` opcional para que la leyenda esté siempre visible.
- **Leyenda anónima:** solo stats; sin nombres, especies, tipos, movimientos, posición ni `nivel_stats`.
- **Orden neutral:** `Rival 1..3` se ordena por hash determinista, no por posición, para no revelar vanguardia/retaguardia.
- **Sin eliminación del código muerto de TASK-035:** queda como limpieza posterior.
- **GET con fijación:** la primera llamada a `/pc` de ruta crea el snapshot; efecto de sesión idempotente por `preview_id`, documentado.
- **Sin `preview_id` en la meta de batalla.**
- ADR borrador en `architecture.md`; no se creó documentación adicional.

## 6. Verificación ejecutada

### Análisis estático

- `php -l` sobre todos los archivos PHP nuevos/modificados: **OK**.
- PHPStan scoped a TASK-037: **0 errores**.
- Pint scoped: **pass**.
- `php artisan route:list --path=pc`: **3 rutas GET** bajo `auth`.
- `php artisan route:list --path=preview`: **DELETE** bajo `auth`.
- `php artisan view:cache` + `view:clear`: **OK**.
- `grep` de claves prohibidas en las respuestas: **sin coincidencias**.
- `tests/`: **sin cambios**.

### Comprobación runtime (tinker, solo lectura/verificación)

- Ruta, equipo de 3, nivel 1: `estado: ok`, `preview_id` de 32 hex, 3 Pokémon, 6 stats, 4 perfiles, `rival_fijado`, `aproximado: false`.
- Reutilización con el mismo `preview_id`: mismos Pokémon y mismos stats.
- `preview_id` inválido: se genera uno nuevo.
- `conNivel`: HP del primer rival pasa de 70 a 72 al subir de nivel 30 → 31, conservando composición.
- Combate con `preview_id`: el HP máximo de los 3 rivales coincide exactamente con la leyenda; el snapshot se elimina tras crear la batalla.
- Gimnasio sin equipo: `equipo_incompleto`, `equipo.pc: null`, leyenda con 3 Pokémon.
- Gimnasio completado (`poison`): `no_disponible`, 0 Pokémon en el desglose, 4 perfiles.
- `team_id` ajeno: **422**.
- DELETE del preview: **204**; un uso posterior del id genera uno nuevo.
- Sin claves prohibidas (`nombre`, `species_id`, `nivel_rival`, `nivel_stats`, `moves`, `tipos`, `posicion`, etc.).
- Poda: 7 `fijar()` consecutivos dejan 5 snapshots; el más antiguo se elimina y el último permanece.
- Orden neutral: la leyenda mantiene el mismo orden con entrada directa e invertida.

### No ejecutado

- Suite de tests: fuera de alcance.
- Verificación visual en navegador: no hay entorno interactivo; la compilación Blade y la revisión estática están cubiertas.

## 7. Hallazgos de revisión

**Reviewer: APPROVED.** Sin hallazgos críticos, altos ni medios.

- **LOW (corregido):** claves de stat no canónicas (`attack`/`defense` → `atk`/`def`).
- **LOW (corregido):** snapshots sin tope → poda y máximo de 5.
- **LOW (corregido):** orden del roster en gimnasio revelaba formación → orden neutral por hash.
- **LOW (residual aceptado):** `unserialize` sin allow-list.
- **LOW (pendiente menor):** docblocks obsoletos en `MetodoPcRival`/`CalculadoraPcRival`.
- **NIT:** `MAXIMO_POKEMON` vs `ReglasEquipo::MIEMBROS`, `preview_id` GET/POST más laxo que DELETE, `nivelStats` no leído, `aria-live` verboso.

## 8. Hallazgos de seguridad

**Security final: PASS** tras correcciones. Inicialmente FINDINGS con dos MEDIUM del cambio:

1. **Crecimiento no acotado de snapshots** → corregido con poda y máximo de 5 por usuario; verificado.
2. **Orden del roster revelaba vanguardia/retaguardia** → corregido con orden neutral; verificado.

Residuales:

- **LOW aceptado:** `unserialize` sin `allowed_classes`; el payload es server-side y el cliente solo controla la clave. Recomendación: allow-list o serialización JSON/DTO en tarea de hardening.
- **LOW pendiente:** `preview_id` GET/POST `sometimes|string|max:64` vs DELETE `[a-f0-9]{32}`; no explotable.
- **INFO:** GET `/pc` con efecto de sesión; acotado a 5 snapshots y documentado.
- **INFO:** fingerprinting por stats base e inferencia del nivel de gimnasio; aceptado por producto.

Preexistentes fuera de TASK-037 (no bloquean esta tarea, requieren tarea propia):

- **HIGH:** CRUD `/api/admin/gyms` sin autorización de rol; cualquier usuario autenticado puede modificar el catálogo global.
- **MEDIUM:** XSS reflejado en `resources/views/gimnasios/show.blade.php:11` por el slug en `x-data`.
- **MEDIUM:** `composer audit` reporta 17 advisories en 10 paquetes; no se añadieron dependencias en esta tarea. `APP_DEBUG=true` en la configuración actual.

Controles correctos: auth en las 4 rutas, `team_id` propietario, `preview_id` ligado a usuario, DELETE uniforme 204, snapshot con versión/TTL/instanceof/forget, sin datos identificables del rival, sin SQL crudo, sin mass assignment, XSS en código nuevo controlado, limpieza tras combate y cancelación.

## 9. Riesgos residuales

- **Snapshot huérfano** si falla el DELETE o se abandona la página: TTL 1 h y tope de 5.
- **Más de 5 previews simultáneos:** el más antiguo se evicta; ese combate cae al generador (fallback funcional).
- **`unserialize` sin allow-list:** defensa en profundidad; sin vector remoto demostrado.
- **Fingerprinting** de especies por stats base e inferencia del nivel de gimnasio: aceptado.
- **Hallazgos preexistentes:** admin CRUD sin rol (HIGH), XSS del slug, dependencias.
- **Verificación visual** en navegador pendiente.

## 10. Próximos pasos

1. **Urgente:** corregir el CRUD `/api/admin/gyms` sin autorización de rol (HIGH preexistente).
2. Corregir el XSS reflejado del slug en `gimnasios/show.blade.php`.
3. Endurecer `unserialize` con allow-list o serialización JSON/DTO; unificar la validación de `preview_id`; valorar `SESSION_ENCRYPT=true`.
4. Planificar actualización de dependencias por `composer audit` y confirmar `APP_DEBUG=false` en producción.
5. Revisar visualmente en navegador la leyenda, el scroll del modal y los estados de carga/error.
6. Limpiar el código muerto de la aproximación de TASK-035 una vez consolidado su commit.

## 11. Nota de concurrencia

El árbol contiene cambios de **TASK-035** (base del comparador de PC) y de **TASK-036/038/039/040**. TASK-037 no toca los archivos de TASK-036/038/039/040 ni `bugs.txt`. No se hizo commit, push, reset ni clean.
