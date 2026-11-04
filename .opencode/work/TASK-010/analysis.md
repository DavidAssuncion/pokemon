# TASK-010 — Análisis

- **Estado:** `COMPLETADO` (con limitaciones de cobertura declaradas)
- **Responsables:** `analyst` (A: nombres/seeders; B: sesión/token; C: shiny)
- **Fecha:** 2026-11-03
- **Base:** tres análisis independientes en paralelo, sin modificación de archivos ni ejecución de tests.

> **Limitaciones declaradas por los analistas:**
> - Análisis A y C alcanzaron el máximo de pasos del subagente antes de emitir el informe formal completo. La evidencia recogida es suficiente para las conclusiones, pero quedan puntos marcados como «pendiente de verificar».
> - Análisis A no revisó todos los seeders secundarios (`ProvinceSeeder`, `AbilitySeeder`, `MoveSeeder`) porque no insertan nombres de Pokémon; no confirmó el listado exhaustivo de consumidores case-sensitive fuera de los patrones buscados.
> - Análisis C no verificó la serialización de `/api/huevos`, el origen del shiny en eventos de exploración, `PokemonResumen`, ni la lista exacta de las 63 especies sin asset shiny.
> - Ningún analista modificó archivos, ejecutó tests ni leyó `.env`.
> - **Concurrencia:** durante el análisis, otra sesión cambió la rama de `master` a `refactor/ui-tokens` y creó el commit `100f836` (TASK-009, F1). TASK-009 está en `IMPLEMENTING` y toca vistas que TASK-010 también necesita. Ver `execution.md`.

---

## Análisis A — Nombres de Pokémon en seeders

### Fuente y flujo actual

- Los nombres provienen de un CSV local, no de PokeAPI en tiempo de ejecución: `database/seeders/PokemonSeeder.php:45` carga `storage/data/pokemon.csv`; la columna `name` se usa tal cual en `PokemonSeeder.php:116`.
- El CSV tiene nombres en minúsculas y ASCII (`bulbasaur`, `mr-mime`, `nidoran-f`, `ho-oh`, `porygon-z`). No hay `strtolower` ni `ucfirst` en el seeder.
- La inserción usa `Pokemon::upsert($chunk, ['id'], ['name', ...])` (`PokemonSeeder.php:188-198`). Es idempotente por PK y `name` está en las columnas actualizadas: **re-ejecutar el seeder reescribe los nombres**.
- `CatalogoSeeder` invoca `PokemonSeeder` (`database/seeders/CatalogoSeeder.php:22`) y `docker/entrypoint.sh:57` ejecuta `db:seed --class=CatalogoSeeder` al arrancar con `RUN_SEEDERS=true`; el cambio se propagaría a bases existentes al reiniciar/seedear.
- No hay mutadores, casts ni Value Object de nombre en `App\Models\Pokemon` (`app/Models/Pokemon.php:12-30`). `CrearReclutado.php:25` deja `nombre = null`; el nombre visible proviene del Pokémon.

### Estado de la base de datos (consulta de solo lectura)

- 1.083 filas en `pokemon`; las 1.083 tienen `name <> initcap(name)` (todas en minúsculas). Muestra: `bulbasaur, ivysaur, venusaur, charmander, charmeleon`.
- 134 nombres con guion, 0 con apóstrofo, 0 con caracteres fuera de `^[a-z0-9-]+$`.
- No hay restricción `unique` sobre `name` (`database/migrations/2026_02_24_171456_create_pokemon_table.php:17`).

### Consumidores que dependen del nombre

| Consumidor | Evidencia | Efecto de capitalizar |
|---|---|---|
| Ruta de icono legacy | `resources/views/reclutados/index.blade.php:82` usa `iconos/{{ $r->pokemon->name }}.png` | Rompería la URL; el directorio ya no existe, la vista ya está rota y el resto de la app usa `pokemon_id`. |
| Preferencias Showdown | `src/CombateEntrenadores/App/SelectorMovimientosRival.php:89` → `app/Support/PreferenciasShowdown.php:35,145-147` | Seguro: normaliza con `mb_strtolower`. |
| `ReclutadosSeeder` | `database/seeders/ReclutadosSeeder.php:41-72` usa `LOWER(name)`/`LIKE` y copia `nombre => $pokemon->name` | Seguro; el seeder está comentado en `DatabaseSeeder.php:23`. |
| JSON de hábitats | `app/Jobs/RecompilarHabitatJsonJob.php:49,55` copia `$p->name` | El JSON quedará desactualizado hasta re-ejecutar el job. |
| Presentación | `ReclutadoSerializer.php:57,218`, `PresentadorDetallePokemon.php:68,240`, `HabitatLecturaRepository.php:93`, `ExploradorVista.php:110-111`, etc. | Sin riesgo; la UI ya capitaliza por CSS/JS (`equipos/index.blade.php:1405-1421`, `reclutado/show.blade.php:17`). |

- No se encontraron comparaciones case-sensitive con `pokemon.name`, slugs, claves de caché ni llamadas API dependientes de minúsculas en los patrones buscados.

### Recomendación del analista

1. **Recomendada:** aplicar `Str::ucfirst($row['name'])` en el único punto de construcción de la fila, `PokemonSeeder.php:116`, usando `Illuminate\Support\Str` (multibyte). No tocar el CSV.
2. **Backfill:** no hace falta migración si se re-ejecuta el seeder, porque `upsert` actualiza `name`. Si se quiere backfill sin depender del arranque: `php artisan db:seed --class=PokemonSeeder --force`.
3. **No aplicar** en `ReclutadosSeeder`: ya deriva del nombre del Pokémon.
4. Alternativas descartadas o no preferidas:
   - Mutador en el modelo `Pokemon`: cubre escrituras futuras, pero no reescribe filas existentes y contradice la petición de hacerlo en seeders.
   - Migración con `UPDATE pokemon SET name = initcap(name)`: backfill inmediato, pero `initcap` altera el resto de la cadena y mezcla lógica de presentación en la base de datos.

### Efectos colaterales y riesgos

- `mr-mime` → `Mr-mime`, `ho-oh` → `Ho-oh`, `nidoran-f` → `Nidoran-f`: cumple literalmente «primera mayúscula, resto igual», pero no es Title Case.
- Tests existentes afirman nombres en minúsculas (`tests/Feature/PokemonSeederTest.php:34-35,129`); quedan fuera de alcance y no se ejecutan.
- Riesgo medio: consumidores no inventariados que comparen `pokemon.name` case-sensitive. Mitigación: grep final en revisión.
- Riesgo bajo: JSON de hábitats desincronizado hasta re-ejecutar el job.
- Riesgo bajo: el backfill depende de re-ejecutar el seeder; documentar el comando.

---

## Análisis B — Sesión y duración de 1 año

### Mecanismo real

- La aplicación **no usa tokens**: guard `web` con driver `session` y provider `users`/Eloquent (`config/auth.php:18-21,40-45,64-68`).
- Driver de sesión `database`, tabla `sessions` (`config/session.php:23,91`). Confirmado con `php artisan config:show session`: `driver=database`, `lifetime=120`, `expire_on_close=false`, `cookie=laravel-session`, `http_only=true`, `same_site=lax`, `secure=null`.
- Login por `name` + `password`; `Auth::attempt($credentials, $request->boolean('remember'))` y regeneración de ID (`app/Http/Controllers/AuthController.php:21-37`, especialmente `:28` y `:34`).
- Registro: `Auth::login($user)` sin `remember` (`AuthController.php:54-71`, `:67`, `:69`).
- No hay Sanctum, Passport, JWT, Breeze, Fortify ni Jetstream (`composer.json:8-12`).
- Existe `users.remember_token` (`database/migrations/0001_01_01_000000_create_users_table.php:21`) y el controlador acepta `remember`, pero **la vista de login no tiene checkbox `remember`** (`resources/views/auth/login.blade.php:20-61`): en el flujo normal `remember` es siempre `false`, no se emite cookie de recaller y no se usa el token.

### Comportamiento de expiración

- `lifetime` son minutos de **inactividad**, no caducidad absoluta (`config/session.php:30-37`; `StartSession.php:257-260`; `DatabaseSessionHandler.php:119-123`).
- Con `expire_on_close=false`, la cookie de sesión es persistente y se emite con `now + lifetime*60` (`StartSession.php:221-235,267-273`). Cerrar el navegador **no** cierra la sesión.
- La fila de `sessions` se considera expirada si `last_activity < now - lifetime` (`DatabaseSessionHandler.php:119-123`); el barrido es probabilístico 2/100 (`config/session.php:119`; `StartSession.php:169-178`).
- Un usuario que visite la app al menos una vez dentro del plazo no caduca nunca: es inactividad deslizante.
- No hay caducidad absoluta en el core. Para «1 año desde el login» haría falta lógica propia (por ejemplo, `login_at` en sesión y middleware).
- Si se activara «recordarme», la duración por defecto del recaller es 576.000 minutos = 400 días (`SessionGuard.php:62`), no 365. Para exactamente 365 días: `setRememberDuration(525600)` (`SessionGuard.php:904-920`), hoy no usado.

### Cambio mínimo para 1 año

Valor exacto: **`SESSION_LIFETIME=525600` minutos** (365 × 24 × 60). No usar `31536000`, que Laravel interpretaría como minutos.

1. `.env` (no versionado): `SESSION_LIFETIME=525600`.
2. `.env.example:31`: cambiar `120` por `525600`.
3. Opcional recomendable: fallback de `config/session.php:37` a `525600`. **No basta por sí solo** si el entorno define `SESSION_LIFETIME=120`, porque `env()` tiene prioridad.
4. `SESSION_EXPIRE_ON_CLOSE` debe seguir en `false` (`config/session.php:39`).
5. Sin migración: la tabla `sessions` ya existe (`create_users_table.php:31-38`).
6. No cambiar nombre ni atributos de cookie: su expiración se deriva de `lifetime` (`StartSession.php:267-273`).

Despliegue:

- Local: sin caché de configuración, el cambio surte efecto en la siguiente petición. No existe `bootstrap/cache/config.php`.
- Producción/Docker: `docker/entrypoint.sh:88-92` ejecuta `config:cache`; reiniciar el contenedor o ejecutar `config:clear`/`config:cache`.
- `docker-compose.yml` no define `SESSION_LIFETIME` en `app` (`:37-55`), `worker` (`:76-92`) ni `scheduler` (`:105-121`); solo `SESSION_DRIVER: database` (`:51`, `:90`, `:119`). Opciones: actualizar `.env.example` y recrear el contenedor, o añadir `SESSION_LIFETIME: 525600` al `environment` de los servicios.
- Usuarios con cookies antiguas conservan la expiración de 120 minutos hasta su siguiente respuesta; si vuelven dentro de ese plazo, reciben la cookie de 1 año. Si no, tendrán que iniciar sesión una vez.
- En producción HTTPS: `SESSION_SECURE_COOKIE=true`; hoy `secure=null` (`config/session.php:174`). No ponerlo en local HTTP.

### Riesgos de seguridad

| Riesgo | Nivel | Evidencia | Mitigación |
|---|---|---|---|
| Robo de cookie válida 1 año | Alto | `config/session.php:174` (`secure=null`); cookie persistente | HTTPS obligatorio, `SESSION_SECURE_COOKIE=true`, mantener `HttpOnly` y `SameSite=Lax` |
| Equipos compartidos | Alto | `expire_on_close=false`; sesión de 1 año | Logout visible; valorar sesión corta + remember opt-in |
| Payload de sesión sin cifrar | Medio | `config/session.php:52` (`encrypt=false`) | `SESSION_ENCRYPT=true` o acceso restringido a BD |
| Sin invalidación al cambiar contraseña | Medio | `bootstrap/app.php:15-17` sin `authenticateSessions()` | Activar `authenticateSessions()` o invalidar sesiones del usuario |
| Crecimiento de `sessions` | Medio | `lifetime=525600`; GC 2/100 (`config/session.php:119`) | Vigilar tamaño o limpieza programada |
| Fuerza bruta sin throttling | Medio | `routes/web.php:13-14` sin `throttle` | Añadir rate limit al login |
| Fijación de sesión | Bajo | Regeneración en login (`AuthController.php:34,69`) | Ya mitigado |
| CSRF | Bajo | `ValidateCsrfToken` y `@csrf` | Ya mitigado; no depende de la duración |

### Opciones planteadas

- **A. Ampliar la sesión (mínimo cambio, recomendada para la petición literal):** `SESSION_LIFETIME=525600` en `.env`, `.env.example`, fallback de config y Docker; sin migración. Ventaja: una variable, sin código. Inconveniente: sesión de 1 año para todos; solo inactividad.
- **B. «Recordarme» real:** checkbox en login y, opcionalmente, `setRememberDuration(525600)`; sesión corta + recaller. Ventaja: mejor seguridad. Inconveniente: cambios de UI/código; el recaller por defecto dura 400 días.
- **C. Híbrida / caducidad absoluta:** `lifetime` largo + middleware con `login_at`, o sesión de 30 días + remember de 1 año. Más código y estado; no parece necesario.

### Preguntas abiertas

- ¿«1 año» significa 1 año de inactividad (lo que hace `lifetime`) o 1 año máximo desde el login?
- ¿Aplica a todos los usuarios o solo a quien marque «recordarme»?
- ¿Producción es Docker con HTTPS? ¿Se puede fijar `SESSION_SECURE_COOKIE=true`?
- ¿Se acepta que los usuarios con cookie antigua tengan que iniciar sesión una vez?

---

## Análisis C — Shiny en la interfaz (pre-TASK-009, histórico)

> **Nota:** este análisis se realizó antes de que TASK-009 creara `x-sprite` y migrara las vistas. Se conserva como histórico. El estado actual y los fallos restantes están en el **Análisis E**.

### Causa raíz

El flag `es_shiny` **existe y se persiste correctamente por individuo**. El fallo está en presentación, con cuatro defectos independientes:

1. **Vistas que ignoran el flag y construyen siempre la URL normal.**
   - `resources/views/equipos/index.blade.php:1424-1427` (`iconoDe`) devuelve siempre `/images/iconos_webp/{id}.webp`; se usa en slots (`:113`), listado (`:347`) y modal de reemplazo (`:826`).
   - `resources/views/habitats/show.blade.php:239,320,678,731,1056` y `resources/views/gimnasios/show.blade.php:193` hardcodean la URL normal.
2. **URL shiny mal formada en el modal de detalle de `/equipos`.**
   - `equipos/index.blade.php:1430-1436` construye `/images/iconos_webp/shiny/{id}.webp`, pero los assets se llaman `{id}_s.webp`. Solo se ve shiny porque el `onerror` de `:875` reintenta con `_s.webp`: hay un 404 y un parpadeo antes de mostrarlo.
3. **Presenters que calculan la URL normal aunque ya tienen el flag.**
   - `src/Exploraciones/Presentation/ExploradorVista.php:75` genera el icono normal y `:80` expone `es_shiny`; `resources/views/exploraciones/_explorador.blade.php:33` pinta el icono normal y solo añade una estrella (`:40-45`).
   - `src/Exploraciones/Presentation/ResultadoExploracionVista.php:177` ignora `es_shiny`, que sí llega desde `RecompensaCapturaCollection.php:43`; la vista lo consume en `exploraciones/index.blade.php:311`.
4. **Cobertura incompleta de assets shiny.**
   - 1.200 WebP normales y 1.137 shiny. Faltan 63 `{id}_s.webp`; de ellos, 59 son especies/formas reales y 4 son assets legacy sin fila en `pokemon`. Para esos 59, el navegador hará fallback al sprite normal. No es un fallo de propagación, sino de datos/assets. Ver «Análisis D».

### Mapa contexto → comportamiento

| Contexto | Archivo clave | Actual | Esperado | Punto de corrección |
|---|---|---|---|---|
| Slots de equipo (`/equipos`) | `equipos/index.blade.php:113,1424-1427` | Normal | Shiny | `iconoDe()` shiny-aware |
| Listado de reclutados (`/equipos`) | `equipos/index.blade.php:347,1424-1427` | Normal | Shiny | `iconoDe()` shiny-aware |
| Modal detalle (`/equipos`) | `equipos/index.blade.php:868-875,1430-1436` | Shiny tras 404 | Shiny directo | URL con `_s.webp` |
| Modal reemplazo | `equipos/index.blade.php:826` | Normal | Shiny | `iconoDe()` |
| Campo de combate | `livewire/_pokemon-card.blade.php:110` + `CombatienteVista.php:46-47` | Correcto | Shiny | Sin cambio |
| Log de combate | `BatallaVistaPresenter.php:298` | Correcto | Shiny | Sin cambio |
| Modal captura de combate | `livewire/combate.blade.php:161-172` + `BatallaVistaPresenter.php:407-420` | Correcto | Shiny | Sin cambio |
| Cabecera de explorador | `ExploradorVista.php:75` + `_explorador.blade.php:33-45` | Normal + estrella | Shiny | Presenter shiny-aware |
| Capturados de exploración | `ResultadoExploracionVista.php:177` + `exploraciones/index.blade.php:311` | Normal | Shiny | Presenter shiny-aware |
| Eventos de exploración | `EventoExploracionVista.php:67,69` + `_evento.blade.php:29,41,65-67` | Normal, sin flag | Depende | Verificar origen del dato |
| Hábitats | `habitats/show.blade.php:239,320,678,731,1056` | Normal | Shiny | Exponer/usar `es_shiny` |
| Gimnasios | `gimnasios/show.blade.php:193` | Normal | Shiny | Exponer/usar `es_shiny` |
| Reclutamiento | `reclutamiento/index.blade.php:35` | Normal | Normal (especies) | Sin cambio salvo decisión de producto |
| Pokédex / hábitats (especies) | `pokedex/index.blade.php:771-778`, `habitats/show.blade.php:405` | Normal | Normal (especie) | Sin cambio |
| Huevos | `equipos/index.blade.php:535` | Normal | Indefinido | Verificar API de huevos |
| Ficha legacy | `ReclutadoVistaPresenter.php:61` + `reclutado/show.blade.php:14` | PNG inexistente | Shiny si se mantiene | Presenter + assets |
| `IconoController` | `IconoController.php:27-40`, `routes/web.php:24` | Ruta PNG legacy sin uso | — | No es la causa |

### Persistencia y recorrido

- `es_shiny` boolean en `reclutados` (`database/migrations/2026_05_27_000001_create_reclutados_table.php:20`) y `huevos` (`2026_09_15_000002_create_huevos_table.php:22`), con cast boolean (`app/Models/Reclutado.php:31`, `app/Models/Huevo.php:27`).
- Se asigna al reclutar/capturar (`app/Support/CrearReclutado.php:27`), eclosionar (`src/Huevos/App/EclosionarHuevo.php:74`), crear huevo (`src/Huevos/App/CrearHuevo.php:89,100`) y capturar shiny de ruta (`src/CombateRuta/App/OtorgarRecompensasRuta.php:163,167`). Equipos iniciales y seeders lo fijan a `false` (`CrearEquipoInicial.php:110`, `ReclutadosSeeder.php:65`).
- En `/equipos`: modelo → `ReclutadoSerializer.php:60`; el equipo se serializa con `$m->reclutado` crudo (`PlayerController.php:112`), que incluye el atributo.
- En combate: `ConstruirEquipoJugador.php:94` / `CombateExploracion.php:64` → `MapeadorPokemonBatalla.php:92` → `DatosPokemonBatalla.php:39` → `EquipoBatalla.php:195` → `Combatiente.php:67` → `CombatienteVista.php:46-47` → Blade. **Correcto.**
- URL shiny correcta: `"/images/iconos_webp/shiny/{$speciesId}{$sufijo}_s.webp"` en `CombatienteVista.php:281-283`; `BatallaVistaPresenter.php:415` lo replica. `IconoController` no recibe parámetro shiny; tiene ruta separada `/iconos/shiny/{filename}` y sirve PNG desde `resource_path('iconos/shiny')`, directorio inexistente. No participa en el render actual.
- `x-sprite` **no existe** en `resources/views/components/` ni se referencia en el repo. `_pokemon-card` recibe y usa correctamente `$p['es_shiny']`, `$p['icon']` e `$p['iconoNormal']` (`_pokemon-card.blade.php:110`).

### Contextos que muestran normal

- Slots de equipo, listado y reemplazo; detalle (con 404 previo); explorador; capturados de exploración; eventos de exploración; hábitats; gimnasios.
- Combate (campo, log y modal de captura) sí es correcto.
- El shiny es por individuo, no por especie. Evolucionar no lo borra: `ReclutadoController.php:241` solo actualiza `pokemon_id`. No se encontró ningún flujo que lo resetee.

### Contratos afectados

- **Cambian:** `ExploradorVista` (icono shiny-aware), `ResultadoExploracionVista::capturados` (icono shiny-aware), helper JS privado `iconoDe`/`iconoPrincipalDe` de `equipos/index`, y opcionalmente `EventoExploracionVista` y `ReclutadoVistaPresenter`.
- **No cambian:** `CombatienteVista`, `DatosPokemonBatalla`, `Combatiente`, `EquipoBatalla`, `MapeadorPokemonBatalla`, `ReclutadoSerializer`, `RecompensaCapturaCollection`, `BatallaVistaPresenter`, `PresentadorResultadoBatalla`.

### Opciones planteadas

1. **A (recomendada, mínima):** centralizar la URL del sprite en un helper de backend, p. ej. `SpritePokemon::url(int $pokemonId, bool $esShiny, string $sufijo = '')`, que devuelva `shiny/{id}_s.webp` o `{id}.webp`. Los presenters emiten el icono correcto y las vistas dejan de construir rutas. Cumple `.ai/rules/frontend-minimal-logic.md`.
2. **B (menor difusión):** corregir solo vistas/JS (`iconoDe` shiny-aware, `_s` y URLs hardcodeadas). Más rápido, pero duplica la regla en Blade y contradice la regla de frontend mínimo.
3. **C (contrato explícito):** añadir `icono_shiny` junto a `icono` en DTOs/presenters y que Blade elija. Más contratos.
4. **Fallback obligatorio:** mantener `onerror` hacia el sprite normal, porque faltan 63 assets shiny y podrían faltar formas con `formSuffix` (`CombatienteVista.php:279-283` genera `{id}_{sufijo}_s.webp`).

### Riesgos

- **Alto:** cobertura incompleta de assets (63 especies sin `_s.webp`). Sin ellos no se puede garantizar «siempre shiny»; hace falta fallback o generar los assets.
- **Alto:** regresión visual por cambiar `iconoDe()` globalmente; mitigar con cambio centralizado y fallback.
- **Medio:** formas con `formSuffix`; verificar rutas `{id}_{sufijo}_s.webp` o degradar a `{id}_s.webp`.
- **Medio:** `grayscale opacity-60` en tarjetas asignadas (`equipos/index.blade.php:354`) desatura el shiny aunque la URL sea correcta.
- **Medio:** eventos de exploración sin flag en origen; puede requerir cambio de contrato.
- **Bajo:** caché de 404 de la URL incorrecta; corregir la URL inicial.
- **Bajo:** vistas legacy con PNG ya rotas; excluirlas o eliminarlas en otra tarea.
- **Bajo:** rama distinta a la indicada y WIP de TASK-009; no mezclar.

### Pendiente de verificar

- Serialización de `/api/huevos` y si expone `es_shiny` para el preview de huevo (`equipos/index.blade.php:535`).
- Origen de datos de `EventoExploracionVista`: si el evento guarda el shiny del salvaje.
- Uso real de `PokemonResumen.php:22`.
- Lista exacta de las 63 especies sin `_s.webp` y si son formas o especies base.
- Confirmación de que `$hidden` de `Reclutado` no excluye `es_shiny` en el payload crudo de `PlayerController.php:112`.
- Vistas de mazmorras y otros partials no enumerados.

---

## Análisis D — Assets shiny faltantes (verificación focalizada)

### Conteo y clasificación

- 1.200 WebP normales y 1.137 shiny `{id}_s.webp`.
- La diferencia bruta es 63; la comparación por *stem* da 64 por un asset huérfano (`shiny/901_f_s.webp` sin normal `901_f.webp`; el normal es `901_f1.webp`).
- De los 63 reales: **59 son especies/formas reales** (1 especie base + 18 Alola + 20 Galar + 16 Hisui + 4 Paldea) y **4 son assets legacy sin fila en la BD** (`3_f950`, `6_f951`, `9_f952`, `901_f1`). El placeholder `0` también carece de shiny.
- Las 59 reales son obtenibles como shiny: `GeneradorEquipoRuta.php:101` aplica el roll a cada Pokémon del pool; `OtorgarRecompensasRuta.php:153-167` crea el reclutado shiny; `CrearHuevo.php:100` y `EclosionarHuevo.php:74` propagan el flag. Gimnasios y jefes fuerzan `shiny: false`.
- 58 de las 59 tienen hábitat; la excepción es `10178 darmanitan-galar-zen`.

### Fuentes alternativas encontradas

- **Dentro del proyecto:** los shiny de esas formas existen con otro nombre: `public/images/iconos_webp/shiny/{baseId}_f{formId}_s.webp` (por ejemplo, `19_f46_s.webp` para `10091 rattata-alola`). No coinciden con la URL que genera el código, que usa `{idNumerico}_s.webp`.
- **`/home/david/PkmnHomeIcons/UICONS/pokemon/`:** 15.147 PNG, 7.504 shiny `_s`, con el mismo esquema `{baseId}_f{formId}_s.png`. Fuente original probable.
- **`/home/david/pokemon_imagenes/shiny/`:** 1.137 PNG, mismo conjunto exacto que el proyecto; tampoco contiene `10091_s` ni `905_s`.
- **Caso 905 enamorus-incarnate:** no se encontró `905_s` en ninguna fuente local; solo las formas Therian (`905_f2802_s`, `905_f2803_s`). Habría que obtenerlo de PokeAPI/Showdown o aceptar fallback documentado.
- **`901_f`:** el shiny existente es `901_f_s.webp`, pero el normal es `901_f1.webp`; es un desajuste de nombres y `901_f1` no tiene fila en `pokemon`, por lo que hoy es inalcanzable.

### Cobertura de la URL y fallback

- `CombatienteVista.php:268-283` construye `shiny/{speciesId}{formSuffix}_s.webp`; en producción `formSuffix` siempre es `''` salvo el mock (`FabricaBatallaMock.php:155`). `MapeadorPokemonBatalla.php:97`, `GeneradorJefeMazmorra.php:66` y `GeneradorPokemonGimnasio.php:92` lo fuerzan vacío.
- `BatallaVistaPresenter.php:407-420` usa `pokemon_id` → `/shiny/{id}_s.webp`.
- `equipos/index.blade.php:1430-1436` genera primero `/shiny/{id}.webp` (sin `_s`), lo que provoca un 404 garantizado antes del `onerror` de `:875`.
- Fallback verificado en batalla y captura; **no verificado** en `livewire/combate.blade.php` ni en `exploraciones/_explorador.blade.php`.

### Recomendación del analista

1. **Generar/remappear los 59 assets reales** a partir de los `{baseId}_f{formId}_s.webp` del propio proyecto, con una tabla de correspondencia verificada contra `pokemon.name`; no tocar los 4 legacy.
2. **Añadir `0_s.webp`** si se quiere eliminar la rama muerta de `CombatienteVista.php:274-277`; es barato.
3. **905 incarnate:** obtener de PokeAPI/Showdown o documentar el fallback; es el único caso sin fuente local.
4. **Mantener el fallback** como red de seguridad, no como solución al requisito «siempre shiny».
5. **No re-ejecutar `PokemonSeeder` con el CSV actual:** el CSV tiene nombres base para Alola/Galar (`10091,rattata`, `10161,meowth`) con `is_default=0`; el seeder los omite por `regionOf() === null` (`PokemonSeeder.php:100-110`). No los sobrescribe, pero tampoco los capitaliza. El backfill de los 1.083 nombres existentes requiere una actualización de datos específica, no un re-seed.

### Riesgos

- **Alto:** mapeo incorrecto al renombrar `_f` → ID numérico; validar tabla de 59 entradas.
- **Alto:** asumir que el re-seed capitaliza las 59 formas; no lo hace porque las omite.
- **Medio:** 905 sin fuente shiny local.
- **Medio:** contextos sin `onerror` verificado.
- **Medio:** dos bases de datos (`laravel` local en `127.0.0.1:5433`; `pokemon` en Docker). Confirmar la autoritativa antes de backfill.
- **Bajo:** licencia de `PkmnHomeIcons`/UICONS si se usan sus PNG; revisar `THIRD_PARTY_NOTICES.md`.
- **Bajo:** `0_s.webp` ausente; solo afecta al placeholder.

---

## Análisis E — Estado actual del shiny tras TASK-009 (2026-11-04)

### `x-sprite` ya existe y centraliza buena parte del problema

`resources/views/components/sprite.blade.php:21-33` declara `id`, `src`, `fallback`, `name`, `shiny`, `suffix`, `size`, `loading`, `priority`, `decorative`, `wrapperClass`.

- URL normal: `sprite.blade.php:41` → `/images/iconos_webp/{id}{suffix}.webp`.
- URL shiny: `sprite.blade.php:42` → `/images/iconos_webp/shiny/{id}{suffix}_s.webp`.
- `:src` explícito gana sobre `:shiny` (`sprite.blade.php:44-47`): si se pasa una URL normal junto a `:shiny`, se muestra la normal y el flag solo influye en el fallback (línea 62).
- Fallback: con `:fallback` → fallback → `0.webp`; con `:shiny` → shiny → normal → `0.webp`; resto → `0.webp` (`sprite.blade.php:54-68`).
- El `onerror` estático se omite si el consumidor pasa `x-on:error` (`sprite.blade.php:54-57`); en ese caso el fallback lo gestiona el JS de la vista.
- Tamaños válidos: `128|96|64|inline|combat` (`sprite.blade.php:36`). `size="48"` cae a 96 (`sprite.blade.php:37`).

### Fallos shiny que quedan

#### 1. Exploraciones: capturados (confirmado)

- `resources/views/exploraciones/index.blade.php:308` usa `<x-sprite :src="$capturado['icono']">` sin `:shiny`.
- La cadena descarta el flag en tres capas:
  1. `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php:76-88` — `capturados()` no persiste `es_shiny`, aunque `RecompensaCapturaCollection::toArray()` lo expone (`src/Exploraciones/Domain/Collections/RecompensaCapturaCollection.php:43-47`).
  2. `src/Exploraciones/Presentation/PresentadorExploraciones.php:244-251` — `toTerminada()` vuelve a descartarlo.
  3. `src/Exploraciones/Presentation/ResultadoExploracionVista.php:168-179` — reconstruye `icono` normal.
- Consecuencia: el resumen de exploración nunca muestra shiny, aunque el combate de ruta sí (`app/Livewire/Presenters/BatallaVistaPresenter.php:409-421`).

#### 2. Eventos de exploración (limitación de contrato)

- `resources/views/exploraciones/_evento.blade.php:28,37,60,92,186` usan `:src` sin `:shiny`.
- `src/Exploraciones/Presentation/EventoExploracionVista.php:67-70` construye solo URL normal; el dominio de eventos no transporta `es_shiny`.
- No es corregible solo en Blade: requiere ampliar el contrato del evento o asumir que el diario no muestra shiny.

#### 3. Ficha de reclutado: siguiente evolución (confirmado)

- `resources/views/reclutado/show.blade.php:28` usa `<x-sprite :id="$siguiente['pokemon_id']">` sin `:shiny`.
- `app/Support/ReclutadoVistaPresenter.php:72-77` no expone `es_shiny` para `siguiente`; el sprite principal sí es correcto (`:60`).

#### 4. Regresión de TASK-009 en `turn-bar` (no shiny)

- `resources/views/livewire/partials/turn-bar.blade.php:7` usa `size="48"`, que no existe en `$allowedSizes` ni en CSS (`resources/css/components.css:24-41`, `tokens.css:73-77`); cae a 96. Antes era `size="inline"` (comparación con `git show e7837a9~1`). Es una regresión visual, no shiny.

#### 5. Assets shiny

- 1.200 normales y 1.137 shiny; 64 normales sin contrapartida shiny: `0`, formas `10091-10253`, `3_f950`, `6_f951`, `9_f952`, `901_f1`, `905`.
- De ellos, 59 son especies/formas reales; 4 son legacy sin fila en `pokemon`; `0` es el placeholder.
- Los 59 reales tienen shiny con otro nombre (`{baseId}_f{formId}_s.webp`) y se pueden remapear a `{id}_s.webp`; `905` incarnate no tiene fuente local.
- `0_s.webp` no existe, pero `CombatienteVista.php:265` y `BatallaVistaPresenter.php:417` la generan como fallback shiny de especie 0. Impacto bajo (mock).

### Contextos ya correctos

- `exploraciones/_explorador.blade.php:32-35` (`:id` + `:shiny`).
- `habitats/show.blade.php:233-236` (equipo, `:id` + `:shiny`).
- `livewire/combate.blade.php:160-164` (capturas, `:id` + `:shiny`; los campos `src`/`onerror` del presenter quedaron muertos).
- `livewire/_pokemon-card.blade.php:110-117` (`:src` shiny + `:fallback` normal + `:shiny`).
- `reclutado/show.blade.php:10`.
- Equipos: `equipos/index.blade.php:112,345,816,856` vía `iconoDe`/`iconoPrincipalDe` shiny-aware y `onSpriteError`; el JS usa `_s` (`:1409-1421`) y el fallback shiny → normal → `0.webp` (`:1448-1470`).
- Habitats/gimnasios JS: `habitats/show.blade.php:722,1042`, `gimnasios/show.blade.php:194` con `iconoReclutado` + `onSpriteError`.

### Recomendación mínima compatible con `x-sprite`

1. **Exploraciones:** propagar `es_shiny` en `TransformadorResultadoExploracion::capturados` (leer `$captura->esShiny`), conservarlo en `PresentadorExploraciones::toTerminada` y `ResultadoExploracionVista::capturados`, y usar `<x-sprite :id="$capturado['pokemon_id']" :shiny="$capturado['es_shiny'] ?? false" size="64">`.
2. **Reclutado evolución:** añadir `es_shiny` a `siguiente` en `ReclutadoVistaPresenter` y pasar `:shiny` en `reclutado/show.blade.php:28`.
3. **Turn-bar:** volver a `size="inline"` (o añadir token `48` si se desea 3rem), sin tocar el componente.
4. **Eventos:** documentar como limitación de contrato; si se quiere shiny en el diario, ampliar el dominio del evento.
5. **Assets:** decidir entre generar los 59 reales + `0_s.webp` o aceptar fallback a normal. Para «siempre shiny» hay que generarlos.
6. **Limpieza opcional:** eliminar `src`/`onerror` muertos de `BatallaVistaPresenter::capturas` y unificar `iconoDe`/`iconoPrincipalDe`.

### Riesgos

- **Medio:** corregir exploraciones toca dominio/persistencia (`TransformadorResultadoExploracion`), no solo Blade; las expediciones ya guardadas sin `es_shiny` mostrarán normal hasta nuevas capturas.
- **Bajo:** `size="48"` → 96 rompe el layout del turn-bar; cambio trivial.
- **Bajo:** `0_s.webp` solo afecta a especie 0/mock.
- **Bajo:** 64 assets shiny faltantes provocan fallback a normal.
