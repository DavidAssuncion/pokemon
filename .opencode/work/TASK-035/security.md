# TASK-035 — Seguridad

- **Fecha:** 2026-11-09
- **Auditor:** `security` (sesión `ses_e4042d5d4ffeWBsnNVoTbybpYR`)
- **Veredicto:** **FINDINGS** — sin hallazgos bloqueantes introducidos por TASK-035.

## Resumen

Los tres endpoints nuevos y la UI de comparación de PC cumplen autenticación, autorización por propietario, validación de entrada y minimización de datos. No se introducen vulnerabilidades explotables. Se identifica un hallazgo **preexistente** de severidad Media (XSS reflejado en `gimnasios/show.blade.php:11`) y observaciones LOW/INFO.

## Hallazgos

### [MEDIO] XSS reflejado en `x-data` por interpolación del slug (preexistente)

- **Ubicación:** `resources/views/gimnasios/show.blade.php:11` — `x-data="gimnasioShow('{{ $slug }}')"`.
- **Flujo:** `routes/web.php:35` → `GimnasiosViewController::show` pasa `$slug` sin validar existencia → vista.
- **Vector:** URL tipo `/gimnasios/'),alert(document.domain),('x`. Blade escapa `'` como `&#039;`, pero el parser HTML decodifica la entidad antes de que Alpine evalúe el atributo.
- **Impacto:** ejecución de JavaScript arbitrario en sesión autenticada si la víctima abre un enlace manipulado; lectura del `csrf-token` del DOM. No permite leer cookies `HttpOnly`.
- **Remediación:** usar `data-slug="{{ $slug }}"` + `x-data="gimnasioShow($el.dataset.slug)"` o `@js($slug)`; validar el slug (`^[a-z0-9-]+$` y existencia) en el controlador y devolver 404.
- **Alcance:** preexistente; no introducido por TASK-035. No bloquea la entrega, pero debe corregirse en tarea aparte o aceptarse por escrito.

### [BAJO] Ausencia de throttle en endpoints de cálculo

- **Ubicación:** `routes/ruta.php`, `routes/entrenadores.php`, `routes/gimnasios.php`; cómputo en los casos de uso de entrenador/gimnasio.
- **Impacto:** consumo de CPU/BD por repetición de GET; no expone datos nuevos.
- **Remediación:** `throttle:30,1` por usuario o límite global. Carencia global del proyecto, no regresión de este cambio.

### [BAJO] JSON de usuario sin cabeceras de caché explícitas

- **Ubicación:** métodos `pc()` de los tres controladores.
- **Impacto:** si un proxy/CDN mal configurado ignora cookies, podría cachear el PC de un usuario.
- **Remediación:** `Cache-Control: private, no-store` o middleware de cabeceras.

### [INFO] Nivel no validado contra el progreso del jugador

- **Ubicación:** `CombateRutaController::pc`, `EntrenadorController::pc`.
- **Evidencia:** `nivel` se valida 1–3, pero no se comprueba `min_lvl_{nivel}` ni el desbloqueo del entrenador; el combate real tampoco lo hace. El gating es de UI.
- **Impacto:** consulta del PC agregado de un nivel no desbloqueado; sin composición ni stats individuales. Mismo comportamiento que `GET /ruta/rivales`.
- **Remediación:** validar en servidor si el desbloqueo es regla de producto.

### [INFO] `console.error` en el mixin

- **Ubicación:** `resources/views/partials/comparacion-pc-script.blade.php:41`.
- **Impacto:** ninguno relevante; no registra datos del equipo ni tokens.

## Controles correctos

- **Autenticación:** las 3 rutas están bajo `Route::middleware('auth')` (`routes/web.php:18-49`); `route:list` confirma `web` + `Authenticate`.
- **Autorización/IDOR:** `team_id` validado con `Rule::exists('teams','id')->where('user_id', Auth::id())` en los tres controladores y revalidado en `ObtenerPcEquipoJugador` con `where('user_id', $userId)->find()`. Un `team_id` ajeno/inexistente devuelve 422.
- **Recursos:** `habitat` validado con `Rule::exists('habitats','id')`; `nivel`/`trainer` 1–3; `gym` resuelto por repositorio (404 genérico).
- **Servidor como fuente de verdad:** fecha de entrenador y etapa de gimnasio se derivan en servidor.
- **Exposición de datos:** `ComparacionPc::toArray()` solo devuelve agregados y contexto acotado; no `nivel_rival`, etapa, especies, tipos, niveles individuales, IV/EV ni movimientos.
- **Entrada:** sin SQL crudo, sin `whereRaw`; todo Eloquent/validator. Sin mass assignment ni escrituras.
- **Solo lectura:** GET sin efectos secundarios; CSRF no aplica.
- **XSS en código nuevo:** el partial usa `x-text`, `x-show`, `:class`, `:aria-busy`; sin `innerHTML`, `eval`, `document.write` ni `{!! !!}`.
- **Secretos/logs:** sin secretos ni `Log::` en el código nuevo; errores genéricos.
- **Dependencias:** no se añadieron.

## Dependencias (preexistentes, ajenas a TASK-035)

`composer audit` reporta avisos en `laravel/framework`, `symfony/*`, `livewire/livewire` y `league/commonmark`. No se incorporó ninguna dependencia en este cambio. Planificar actualización en tarea separada.

## Riesgos residuales

- XSS reflejado preexistente en `gimnasios/show.blade.php:11` si no se corrige.
- Inferencia del rival por el PC exacto agregado de entrenador/gimnasio (aceptado por diseño).
- Nivel no gateado en servidor (solo agregado).
- Caché intermedia mal configurada en despliegue.
- `APP_DEBUG` debe estar desactivado en producción.
- Dependencias con avisos pendientes de parche.

## Recomendación

1. No bloquear TASK-035.
2. Abrir tarea de corrección para el XSS reflejado preexistente (`data-slug` + validación del slug).
3. Evaluar throttle y `Cache-Control: private, no-store` como hardening no bloqueante.
4. Planificar actualización de dependencias.
5. Confirmar en despliegue `APP_DEBUG=false` y cookies `Secure`/`HttpOnly`/`SameSite`.

## Archivos modificados por el auditor

Ninguno.
