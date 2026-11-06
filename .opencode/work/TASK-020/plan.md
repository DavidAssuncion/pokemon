# TASK-020 — Plan v1 — Fundaciones de testabilidad y cálculo puro

- **ID:** TASK-020
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-05
- **Base:** `architecture.md`, `analysis.md`, auditoría TASK-019
- **Alcance:** backend de dominio, primera fase. Sin tests.

---

## 1. Objetivo

Crear la base para un backend componentizado y testeable sin cambiar comportamiento:

1. Introducir puertos mínimos de **azar** y **reloj**.
2. Extraer de `ServicioEjecucionBatalla` los cálculos puros de golpes, drenaje, recoil, coste de velocidad, curación y log.
3. Extraer de `FinalizarExploracionHandler` los cálculos puros de duración, incidentes y pérdidas.
4. Dejar los métodos originales como delegaciones compatibles, sin tocar serialización, constructores públicos ni shape de datos.

Esta fase no intenta todavía dividir `Combatiente` ni `AgregadoBatalla`; prepara el terreno para hacerlo con riesgo controlado.

## 2. Alcance incluido

### 2.1 Puertos compartidos

| Archivo | Contenido |
|---|---|
| `src/Shared/Domain/Ports/GeneradorAleatorio.php` | Interfaz: `entero(int $min, int $max): int`, `flotante(): float` |
| `src/Shared/Domain/Ports/Reloj.php` | Interfaz: `ahora(): DateTimeImmutable` |
| `src/Shared/Infrastructure/Random/GeneradorAleatorioPhp.php` | Implementación con `random_int`/`mt_rand` equivalente al comportamiento actual |
| `src/Shared/Infrastructure/Time/RelojSistema.php` | Implementación con `new DateTimeImmutable()` |

- Los puertos no dependen de Laravel.
- Las implementaciones se registran en el composition root si hace falta; los consumidores aceptan el puerto por constructor con valor por defecto para no romper llamadas existentes.

### 2.2 Battle — cálculo puro

| Archivo | Contenido |
|---|---|
| `src/Battle/Domain/Ejecucion/CalculadorGolpes.php` | Resuelve número de golpes/encadenamiento a partir del perfil y el azar |
| `src/Battle/Domain/Ejecucion/AplicadorDrenaje.php` | Calcula y aplica drenaje |
| `src/Battle/Domain/Ejecucion/AplicadorRecoil.php` | Calcula y aplica recoil |
| `src/Battle/Domain/Ejecucion/AplicadorCosteVelocidad.php` | Aplica coste de velocidad |
| `src/Battle/Domain/Ejecucion/AplicadorCuracion.php` | Aplica curación |
| `src/Battle/Domain/Ejecucion/GeneradorLogMovimiento.php` | Genera el texto de log del movimiento |
| `src/Battle/Domain/ServicioEjecucionBatalla.php` | Delegación a los componentes; `GeneradorAleatorio` opcional al final del constructor |

Reglas:

- Mantener las firmas públicas de `ServicioEjecucionBatalla`.
- Mantener el **orden exacto** de llamadas y el número de tiradas.
- `GeneradorAleatorio` sustituye `mt_rand` en `ServicioEjecucionBatalla`, no en efectos ni en `EstadoManager` (fase posterior).
- `GeneradorLogMovimiento` no cambia el texto ni el orden de las piezas.

### 2.3 Exploraciones — cálculo puro

| Archivo | Contenido |
|---|---|
| `src/Exploraciones/Domain/Tiempos/VentanaExploracion.php` | VO readonly: inicio, fin, ahora |
| `src/Exploraciones/Domain/Tiempos/CalculadoraDuracionExploracion.php` | `duracionReal`, `factorTiempo` |
| `src/Exploraciones/Domain/Incidentes/IncidentesExploracion.php` | VO readonly + `aArray()` |
| `src/Exploraciones/Domain/Incidentes/CalculadoraIncidentes.php` | Conteo de encuentros, victorias, huidas, emboscadas y contratiempos |
| `src/Exploraciones/Domain/Perdidas/ObjetoPerdido.php` | VO readonly: tipo, id, cantidad |
| `src/Exploraciones/Domain/Perdidas/ColeccionObjetosPerdidos.php` | Colección tipada |
| `src/Exploraciones/Domain/Perdidas/CalculadoraPerdidas.php` | Cálculo de pérdidas y resta de recompensas |
| `src/Exploraciones/App/FinalizarExploracionHandler.php` | Delegación de `duracionReal`, `factorTiempo`, `incidentes`, `objetosPerdidos`, `restarPerdidas`; `Reloj` opcional al final del constructor |

Reglas:

- Los métodos privados conservan **nombre, firma y tipo de retorno** (hay tests que los invocan por Reflection).
- `eventos`, `resultado`, `derrotados`, `objetos_perdidos` y `bonos.del_habitat` conservan exactamente sus claves y valores.
- No se toca `PersistirRecompensas`, `CombateExploracion`, `Lote`/`UnitOfWork` ni el timing de `save()`.
- `Reloj` se usa para construir `VentanaExploracion`; el valor por defecto es el reloj del sistema.

## 3. Fuera de alcance

- Dividir `Combatiente` en componentes (fase 2).
- Dividir `ServicioEjecucionBatalla` en resolutores de impacto/estado (fase 3).
- Extraer procesadores de `AgregadoBatalla` (fase 4).
- Orquestación de handlers de Exploraciones (fase 3).
- Cambiar `SESSION_VERSION`, `__serialize`, `__unserialize`, `__clone` o el payload de sesión (fase 5).
- Frontend, Blade, Alpine, Livewire.
- Limpieza de `app/Crud`/`src/Crud` (fase 6).
- Seguridad, concurrencia, rate limiting y autorización (descartados por el desarrollador).
- Tests: no se crean, actualizan ni ejecutan.
- Dependencias nuevas, migraciones, rutas o cambios de esquema.
- `git push`.

## 4. Archivos que se crearán o modificarán

### Nuevos

- `src/Shared/Domain/Ports/GeneradorAleatorio.php`
- `src/Shared/Domain/Ports/Reloj.php`
- `src/Shared/Infrastructure/Random/GeneradorAleatorioPhp.php`
- `src/Shared/Infrastructure/Time/RelojSistema.php`
- `src/Battle/Domain/Ejecucion/CalculadorGolpes.php`
- `src/Battle/Domain/Ejecucion/AplicadorDrenaje.php`
- `src/Battle/Domain/Ejecucion/AplicadorRecoil.php`
- `src/Battle/Domain/Ejecucion/AplicadorCosteVelocidad.php`
- `src/Battle/Domain/Ejecucion/AplicadorCuracion.php`
- `src/Battle/Domain/Ejecucion/GeneradorLogMovimiento.php`
- `src/Exploraciones/Domain/Tiempos/VentanaExploracion.php`
- `src/Exploraciones/Domain/Tiempos/CalculadoraDuracionExploracion.php`
- `src/Exploraciones/Domain/Incidentes/IncidentesExploracion.php`
- `src/Exploraciones/Domain/Incidentes/CalculadoraIncidentes.php`
- `src/Exploraciones/Domain/Perdidas/ObjetoPerdido.php`
- `src/Exploraciones/Domain/Perdidas/ColeccionObjetosPerdidos.php`
- `src/Exploraciones/Domain/Perdidas/CalculadoraPerdidas.php`

### Modificados

- `src/Battle/Domain/ServicioEjecucionBatalla.php`
- `src/Exploraciones/App/FinalizarExploracionHandler.php`
- `app/Providers/AppServiceProvider.php` solo si hace falta registrar las implementaciones por defecto (preferible no tocarlo si los constructores tienen default).

## 5. Orden de ejecución y dependencias

```text
1. Puertos compartidos (backend, secuencial)
2. Battle: calculadores puros + delegación en Servicio (backend)
3. Exploraciones: VOs/calculadores + delegación en Finalizar (backend)
```

- Los pasos 2 y 3 pueden ejecutarse en paralelo tras el paso 1 porque los archivos son disjuntos.
- Si hay que tocar `ServicioEjecucionBatalla` y `FinalizarExploracionHandler` en el mismo commit, hacerlo en secuencia para evitar conflictos.
- No se toca `Combatiente` ni `AgregadoBatalla`.

## 6. Criterios de aceptación

1. Los métodos públicos de `ServicioEjecucionBatalla` conservan nombre, parámetros y tipo de retorno.
2. `calcularYAplicarDano`, `aplicarEstado`, `aplicarStatChanges`, `aplicarEstadoYStatChanges` y `generarLogMovimiento` producen el mismo resultado y en el mismo orden.
3. `FinalizarExploracionHandler::duracionReal`, `factorTiempo`, `incidentes`, `objetosPerdidos` y `restarPerdidas` conservan firma y resultado; los tests existentes que los invocan por Reflection siguen compilando.
4. `eventos`, `resultado`, `derrotados`, `objetos_perdidos` y `bonos.del_habitat` no cambian de shape ni de claves.
5. No se modifica `Combatiente::__serialize`, `__unserialize`, `__clone`, `SESSION_VERSION` ni `BattleSessionService`.
6. Los nuevos componentes de dominio no importan `Illuminate`, `App\`, `Presentation`, `Eloquent` ni usan `app()`.
7. `ServicioEjecucionBatalla` no contiene `mt_rand` directo en el camino tocado; usa `GeneradorAleatorio`.
8. `FinalizarExploracionHandler` no contiene `now()` en los métodos delegados; usa `Reloj`.
9. Ningún método nuevo supera 40 líneas; ningún componente nuevo supera 200 líneas.
10. `vendor/bin/pint --test` pasa.
11. `vendor/bin/phpstan analyse -c phpstan.neon` no añade errores sobre los 34 actuales.
12. `vendor/bin/deptrac analyse --no-progress` no añade violaciones sobre las 113 actuales.
13. No se añaden tests, migraciones, rutas, dependencias ni cambios de esquema.
14. No se hace `git push`.

## 7. Verificación requerida

- **Estática:** Pint, PHPStan L6, deptrac, `git diff --check`.
- **Búsquedas:** `grep` de `mt_rand`/`now()`/`app()` en los archivos tocados; `grep` de imports prohibidos; `grep` de las firmas privadas.
- **Manual:** revisar el diff; comparar el JSON de una exploración local antes/después si hay datos disponibles; ejecutar una batalla local de humo si el entorno está levantado.
- **Sin tests:** no se crean, actualizan ni ejecutan.

## 8. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Cambiar la secuencia de RNG | `GeneradorAleatorioPhp` replica exactamente `mt_rand`; no se añaden ni quitan tiradas |
| Cambiar el resultado de daño | Extracción literal, sin reordenar; diff pequeño y revisable |
| Romper tests por Reflection | Mantener nombres, firmas y visibilidad de los privados |
| Cambiar el shape de `eventos` | Los calculadores devuelven VOs; el handler conserva el mapeo actual |
| Romper PHPStan/deptrac | No empeorar el baseline; revisar imports |
| Inyectar `Reloj` y alterar tiempos | Default `RelojSistema`; mismo `now()` efectivo |
| Sobreabstracción | Solo dos puertos y calculadores con una implementación; sin interfaces innecesarias |

## 9. Rollback

- Cada bloque (puertos, Battle, Exploraciones) se implementa en un commit separado y reversible.
- Si un bloque falla, revertir su commit y mantener el checkpoint `0317179`.
- No hay migraciones ni datos que revertir.
- No se hace `git push`.

---

**Nota:** este plan es la primera fase de la arquitectura de `architecture.md`. No autoriza las fases 2-6. Cualquier avance posterior requiere plan y aprobación propios.
