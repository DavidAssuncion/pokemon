# TASK-067 — Seguridad

- **ID:** TASK-067
- **Estado:** PASS_CON_HALLAZGOS
- **Auditor:** `security`
- **Fecha:** 2026-11-19

## Resumen ejecutivo

No se introducen hallazgos CRITICAL/HIGH nuevos. El cambio cumple el objetivo:
las lecturas de Pokédex usan `user_id` explícito, el guard se ejecuta antes de
la transacción, el 404 es neutro, `CosecharTodo` prefiltra nidos ocultos y
`/farm` no expone datos de familias no vistas. Persiste una deuda de
autorización preexistente en los endpoints admin de familias, aceptada como
fuera de alcance.

## Hallazgos

### Alto (preexistente, fuera de alcance, aceptado)

- **Endpoints de gestión de familias sin rol/policy.**
  - Ubicación: `routes/habitats.php:20-26`,
    `app/Http/Controllers/HabitatsController.php:110-165`.
  - Vector: cualquier usuario autenticado puede listar y mutar familias del
    hábitat (incluidas las no avistadas).
  - Impacto: enumeración completa y escalada de privilegios.
  - Mitigación: policy/rol admin en tarea propia. No es bloqueante de TASK-067
    por decisión explícita del plan.

### Medio (condicionado a configuración)

- **`?unlock=1` desbloquea el filtro si el entorno no es producción.**
  - Ubicación: `app/Support/DesbloqueoPokedex.php:20-25`.
  - Condición: `APP_ENV` distinto de `production` en un entorno accesible.
  - Impacto: ver/operar familias no avistadas en QA compartido mal configurado.
  - Mitigación: allowlist de usuarios QA o flag explícito distinto de
    `APP_ENV`; verificar `APP_ENV=production` en infraestructura.

### Bajo

- **`POKEDEX_UNLOCK_ALL` es un kill-switch global sin segunda barrera.**
  - Ubicación: `config/pokedex.php:17`,
    `app/Support/DesbloqueoPokedex.php:20-22`.
  - Mitigación: condicionar el flag de entorno a `! app()->isProduction()` o a
    usuario QA explícito; documentar el despliegue.

- **`GET /api/habitats/{id}/farm` crea una fila en `granjas` (preexistente).**
  - Ubicación: `src/Granjas/App/AsegurarGranja.php:20-32`.
  - Impacto: efecto secundario acotado, sin fuga.

### Informativo

- El refugio de una familia oculta influye en el efecto de refugio de familias
  visibles (`RefugiosHabitatCollection::exclusivo`). Es infraestructura del
  propio usuario y un oráculo indirecto; no revela identidad.
- Sin rate limiting en los endpoints de granja (preexistente).

## Controles verificados

- **Aislamiento por usuario:** `PokedexLectura` usa `DB::table` con `user_id`
  explícito en `vistos`, `cadenasConAlgunVisto` y `tiposVistos`; no depende de
  `Auth` ni del global scope `BelongsToUser`. `InventarioGranja` usa
  `withoutUserScope()` + `where('user_id', $userId)`. `CosecharTodo` acota por
  granjas del usuario.
- **IDOR/autorización:** `GranjaController` obtiene el usuario del `Guard`; los
  endpoints no aceptan `userId`. `FamiliaEnHabitat` valida avistamiento y
  asignación; las acciones lo invocan con `$userId`.
- **404 neutro:** ambas ramas lanzan `RecursoNoExiste` con el mismo mensaje;
  `bootstrap/app.php` responde 404 idéntico.
- **No exposición en `/farm`:** solo se serializan familias proyectadas;
  `inventario.familia` filtrado; sin flags de visibilidad.
- **Cosecha global:** prefiltro antes de candidatos, sin excepciones por nido;
  los nidos ocultos no llegan a contadores, bayas ni errores.
- **Flag QA:** `?unlock=1` solo en `local|testing`; `harvest-all` usa solo
  `POKEDEX_UNLOCK_ALL`.
- **SQL/validación:** sin `DB::raw`/`whereRaw` en el cambio; `whereIn` sobre
  enteros; rutas con `whereNumber`; `PlantarBayaRequest` valida slot/berry.
- **Integridad:** guard antes de la transacción en todas las acciones.
- **CSRF/auth:** rutas bajo `web` + `auth`, CSRF activo.
- **Secretos/dependencias:** sin dependencias ni secretos nuevos.

## Dependencias

`composer audit --no-dev` reporta 15 advisories en dependencias preexistentes
(Laravel, Livewire, Symfony, etc.). Ninguna introducida por TASK-067; requieren
una tarea de actualización aprobada.

## Riesgos aceptados / fuera de alcance

1. Endpoints admin de familias sin rol/policy (lectura y mutación).
2. HTML de la página, `/api/habitats/{h}/pokemon` y detalle de especie siguen
   exponiendo datos no avistados.
3. `POKEDEX_UNLOCK_ALL` desbloquea también `harvest-all`; documentado.
4. Infraestructura de familias ocultas inaccesible hasta avistar; no se borra.
5. Sin tests; verificación estática/manual.

## Próximos pasos

1. Abrir tarea de seguridad para autorización de endpoints admin.
2. Decidir segunda barrera para `POKEDEX_UNLOCK_ALL`.
3. Planificar actualización de dependencias.
4. Opcional: filtrar `refugiosDeHabitat` por cadenas visibles.
5. Limpiar la entrada obsoleta de PHPStan para `DesbloqueoPokedex`.
