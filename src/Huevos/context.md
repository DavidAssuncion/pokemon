# Contexto del módulo `Huevos`

## Propósito

Sistema de huevos del jugador (2026-09-16): se pone a gestar un huevo de una familia evolutiva de
la que el jugador ya tiene **≥2 reclutados distintos** (misma `evolution_chain_id`; especies
distintas de la misma cadena valen), se espera gestación + incubación y se eclosiona para obtener
un reclutado con IVs mejores que los salvajes (10-15, o 15 si es shiny). Los tiempos y estados se
**derivan de timestamps** (on-read); no hay ningún cron/worker que avance huevos.

## Estructura (convención DDD por módulos, `docs/ddd.md`)

| Carpeta / clase | Responsabilidad |
|---|---|
| `Domain/CalculadorTiemposHuevo` | Fórmulas puras (contrato B1 del cliente): `puestaHoras = 255 / max(capture_rate, 1)`; `eclosionHoras = ceil(max(hatch, 1) / 15)` (`DIVISOR_HATCH = 15`); `sumaHoras`. Guard suelo 1 en ambos divisores (hardening P1). |
| `Domain/CalculadorEstadoHuevo` | Estado derivado: `estadoDe(creadoEn, captureRate, hatch, ahora)` → GESTANDO si `now < finGestacion`, INCUBANDO si `finGestacion <= now < finIncubacion`, LISTO si `now >= finIncubacion`; `restanteHoras` redondeado a 2 decimales (evita "84h 60m" por 84.9999∞). |
| `Domain/EstadoHuevo` | Enum `GESTANDO` / `INCUBANDO` / `LISTO`. |
| `Domain/EstadoCalculadoHuevo` | VO readonly `{estado, restanteHoras}`. |
| `App/ListarHuevos` | Serializa huevos (horas float + `progreso` 0-100 + `puede_eclosionar`); `disponibles` por familia (≥2 individuos, dedupe 1/cadena, sin huevo activo de esa familia, hatch no-nulo; representante = menor `species_id`); `limites` (`max_gestacion=1`, `max_incubando=1`, flags activos). `serializarUno()` es público y lo reutiliza el controller en POST (201). |
| `App/CrearHuevo` | Valida pareja ≥2 reclutados de la familia, bloqueos (1 gestando activo, 1 activo por familia), rechaza especie sin `capture_rate`/`hatch`; fija `es_shiny` (1/512) e IVs; persiste snapshots `capture_rate`/`hatch`/`evolution_chain_id`. |
| `App/EclosionarHuevo` | Marca `eclosionado_en` y crea el reclutado con los IVs/shiny del huevo vía `App\Support\CrearReclutado`. Growth: rechaza huevo ya eclosionado (422) y huevo ajeno (404). |
| `Infra/Controllers/HuevoController` | HTTP: `index` (listar), `store` (crear, `evolution_chain_id` validado), `eclosionar` (serializa el reclutado nuevo con CP al nivel del usuario). |

## Dependencias

- `src/Shared/Domain/ProbabilidadesShiny` — rolls shiny compartidos (`HUEVO = 512`; `SALVAJE = 2048` usado por ruta).
- `src/Pokemon/Domain/Stats/GeneradorIvs` — `maximos()` (15) y `huevo()` (10-15 por stat); `StatsValue`/`DatosStats`.
- `app/Support/CrearReclutado` — creación del reclutado en eclosión (misma vía que ruta shiny).
- `app/Support/ReclutadoSerializer` — serialización del reclutado eclosionado (CP al nivel del usuario).
- Modelos Eloquent: `App\Models\Huevo` (BelongsToUser → anti-IDOR por scope), `App\Models\Reclutado`, `App\Models\Pokemon`.
- `routes/huevos.php` — requerido desde `routes/web.php` dentro del grupo `auth`.

## Reglas de negocio

1. **Puesta**: `255 / capture_rate` horas (suelo 1). **Eclosión**: `ceil(hatch / 15)` horas.
2. **Estados derivados por timestamps** (`created_at` + snapshots): GESTANDO → INCUBANDO → LISTO.
   `huevo.eclosionado_en` solo marca el final (null = activo).
3. **Límites** (semántica O1): máx. 1 huevo en GESTANDO y máx. 1 en INCUBANDO simultáneos → 2
   huevos activos de DISTINTO estado pueden convivir; máx. 1 huevo activo (sin eclosionar) por
   familia (`evolution_chain_id`).
4. **Pareja por familia** (semántica O2): ≥2 reclutados DISTINTOS con `pokemon.evolution_chain_id`
   = X; especies distintas de la misma cadena valen; especie del huevo = miembro de menor
   `species_id` (regla "primer integrante").
5. **Shiny**: 1/512 en creación; shiny → IVs 15/15/15/15/15/15; no-shiny → 10-15 por stat.
6. **Eclosión idempotente**: un huevo con `eclosionado_en` no nulo → 422 "Este huevo ya eclosionó."
   (sin duplicar reclutados); estado != LISTO → 422.
7. **Defensa en profundidad**: `EclosionarHuevo` revalida `user_id` (404 si ajeno) aunque el
   route-model binding ya acota con `BelongsToUser`.

## Endpoints API

```
GET  /api/huevos                       → { huevos[], disponibles[], limites }
     huevos[]: { id, estado: gestando|incubando|listo, tiempo_total_horas: float,
                 tiempo_restante: float, progreso: int 0-100, puede_eclosionar: bool,
                 pokemon: { id, name, icon } }
     disponibles[] (1/familia): { evolution_chain_id, pokemon: {id, name, icon},
                                  capture_rate, hatch }
     limites: { max_gestacion: 1, max_incubando: 1, gestacion_activa, incubacion_activa }

POST /api/huevos                      body: { evolution_chain_id }
     → 201 { huevo: { ...shape huevos[] } }
     → 422 si pareja insuficiente / gestación activa / huevo activo de esa familia / sin datos de eclosión

POST /api/huevos/{huevo}/eclosionar   → { reclutado } (ReclutadoSerializer con CP al nivel del usuario)
     → 422 si ya eclosionó o no está LISTO; 404 si el huevo no pertenece al usuario
```

## Casos especiales

- Filas legacy (migración aditiva nullable) sin `hatch`/`evolution_chain_id`: lectura con
  `hatch ?? 1` y bloqueo por familia con `whereNotNull` (el `where evolution_chain_id = ?` no casa
  con null). Especies sin `hatch` se filtran en `disponibles` y `CrearHuevo` las rechaza con 422.
- Redondeo a 2 decimales en `restanteHoras` y `tiempo_total_horas` (el frontend formatea con
  `floor(h) + round(min)`; 84.9999∞ mostraría "84h 60m").
- `limites.gestacion_activa`/`incubacion_activa` se derivan de los estados actuales de los huevos
  del usuario (INCUBANDO **o** LISTO cuentan como incubación activa; no se depositan huevos LISTO).
- La creación es user-initiated (`POST /api/huevos`): NO hay auto-trigger desde catálogo/seeders.

## Deudas conocidas

- **`habilidades=[]`** en `ReclutadoSerializer` (el reclutado eclosionado serializa habilidades
  vacías): pendiente de `app/Models/Ability` + pivot + relación en el serializer (pendiente 15 de
  `docs/context.md`; boundary propuesto por el Arquitecto).
- **Dedupe por familia sin FK estricta**: `huevos.evolution_chain_id` es entero con índice, sin FK
  ni tabla (misma estrategia que `pokemon.evolution_chain_id` tras el bug 23503). La unicidad "1
  activo por familia" se garantiza en `CrearHuevo` (App), no en BD.
- **OBS-01/02 (producto pendiente)**: la UI (`equipos/index.blade.php`) bloquea "Poner huevo" con
  un huevo INCUBANDO o LISTO, más restrictivo que el backend (que permite GESTANDO + INCUBANDO de
  otra familia). El backend ya expone `limites` correctos; falta alinear la UI.
- `CalculadorEstadoHuevo`/`CalculadorTiemposHuevo` son Domain puro (DateTimeInterface / ints); todo
  lo que toca Eloquent vive en `App/` e `Infra/`.

## Referencias

- `docs/context.md` — Resumen funcional del proyecto (iteración Ruta/shiny/huevos/EV/IV/CP)
- `docs/architecture.md` — Sección del módulo en la arquitectura
- `docs/ddd.md` — Convención canónica DDD por módulos
- `docs/testing.md` — Comportamientos a cubrir en la fase de testing diferida
- `routes/huevos.php` — Definición de rutas
- `database/migrations/2026_09_15_000002_create_huevos_table.php` — tabla base
- `database/migrations/2026_09_15_000003_add_hatch_and_evolution_chain_id_to_huevos_table.php` — snapshots hatch + cadena (O1/O2)
- `database/migrations/2026_09_15_000001_add_ivs_evs_to_reclutados_table.php` — IVs/EVs del reclutado

## Commits de la iteración "ruta/shiny/huevos/EV/IV/CP"

- Backend funcional: `79b7e45b27`
- Correcciones QA (B1/B2/O1/O2/O3/O6): `a8c46c1a57`
- Frontend: `3f217ff372` + `af1b676d4c`
- Refactor Cleaner: `51cc0c9`, `b661fad`, `8d3fd57`, `8eee2ed`
- Idempotencia eclosión + SESSION_VERSION 10: `869f84a217`
- Hardening guards capture_rate/hatch (+ doc): `f1ea5d2ebc` (+) `7c919d8f9d`