# TASK-040 — Seguridad

- **Estado:** PASS_WITH_FINDINGS — no bloqueante
- **Auditor:** `security` (sesión `ses_e3fde1bbcffeKv6abEelsrRoUl`)
- **Nota:** resultado del auditor, sin alterar sus conclusiones.

---

# Auditoría de seguridad — TASK-040

## 1. Veredicto

**PASS_CON_HALLAZGOS** (`PASS_WITH_FINDINGS`).

- El cambio **no introduce** vulnerabilidades explotables nuevas: no añade endpoints, no acepta entrada de usuario nueva, no toca autenticación, autorización, sesiones, cookies, CSRF, SQL, archivos ni secretos.
- No hay hallazgos CRITICAL/HIGH atribuibles a este cambio.
- Se detecta deuda preexistente en dependencias (`composer audit`) que no bloquea el commit, pero requiere planificación antes de producción.
- Los hallazgos INFO de hardening no bloquean la entrega.

## 2. Superficie revisada

| # | Archivo | Verificación |
|---|---|---|
| 1 | `src/Battle/Domain/Componentes/Combatiente/RegistroDanio.php:77-81` | Predicado `puedeUsarProteccion()`; `desdeArray` clampa `max(0,(int))`; setter clampa `max(0,$ronda)`. |
| 2 | `src/Battle/Domain/Combatiente.php:506-509` | Delegación pura; sin estado adicional. |
| 3 | `src/Battle/Domain/MovimientoBatalla.php:99-105` | `esProteccion()` con `in_array(..., true)` sobre `perfil->claveEfecto`. |
| 4 | `src/Battle/Domain/Effects/Movimiento/EjecutorAguante.php:26-35` | Rama bloqueada solo log + `return`; marcador solo en la rama válida. |
| 5 | `src/Battle/Domain/Effects/Movimiento/EjecutorProteccion.php:26-35` | Idéntico; cubre Protección y Detección. |
| 6 | `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php:30-43` | `$ronda` desde `turnManager()->round()`; filtro con `continue`. |
| 7 | `src/Battle/Domain/AI/SelectorAccionIA.php:220-234` | Filtro antes de `esAutodirigido()`; ronda desde el agregado. |

Contexto verificado:

- `BattleSessionService.php:50` → `SESSION_VERSION = 21` sin cambios; sesión server-side (`SESSION_DRIVER` por defecto `database`).
- `SerializadorCombatiente::ORDEN` incluye `ultimaRondaProteccion` → persiste entre requests.
- `ColeccionEjecutoresMovimiento` enruta `PROTECCION`/`AGUANTE` a los dos ejecutores; no hay otra ruta que conceda `PROTEGIDO`/`AGUANTE` ni que escriba el marcador.
- `storage/data/moves.json`: solo tres movimientos con `effect_key` de protección (`proteccion` ×2, `aguante` ×1). No quedan fuera del filtro.
- `GestorPreparacionBatalla::aplicar()` valida índice, `priority > 0` y `movimientoDisponible()`; el ejecutor es la barrera final.
- `BatallaVistaPresenter::logVista()` escapa con `e($entry)` antes de inyectar iconos; el `{!! !!}` del partial no es XSS.
- No existe `#[Authorize]` en `app/` ni `src/`; no hay rutas `api/` ni Sanctum/Passport.

Verificaciones ejecutadas:

- `php -l` 7/7 sin errores.
- PHPStan acotado a los 7 archivos → sin errores.
- `composer audit --format=json` → advisories preexistentes.
- `git diff` de los 7 archivos → solo métodos/filtros previstos; sin secretos, sin cambios de serialización, sin rutas.

## 3. Hallazgos

### 3.1. Sobre el cambio

**No se identifican hallazgos CRITICAL, HIGH ni MEDIUM introducidos por TASK-040.**

- **Manipulación de `ultimaRondaProteccion`:** no es posible. Vive en el agregado serializado en la sesión server-side, no en propiedades públicas Livewire. `setUltimaRondaProteccion` solo se invoca en las ramas válidas. `$componente->round` no se usa en el predicado; la ronda sale de `turnManager()->round()`.
- **Predicado `>= ronda - 1`:** bloquea misma ronda, ronda anterior y valores futuros imposibles; `ultima == 0` no bloquea la ronda 1. No permite doble uso con acción extra.
- **IA:** el `continue` mantiene el fallback natural; `SelectorAccionIA` cae en `accionFallback()` si no quedan candidatos. Sin bucles; `puedeUsarProteccion` es O(1).
- **Afectación a otros usuarios:** ninguna; estado por sesión de batalla.
- **Autenticación/autorización/sesiones/cookies/CSRF/SQL/archivos/secretos/endpoints:** el diff no toca esas superficies.
- **`SESSION_VERSION` y compatibilidad:** intacta (21); el marcador ya estaba serializado.
- **Logs:** textos de juego con `nombre()`; sin PII ni secretos; escapados en presentación.
- **DoS/bucles/doble consumo:** sin bucles nuevos; un intento bloqueado consume acción/PP como antes.

### 3.2. Hardening (INFO/LOW, preexistentes)

- **[INFO] El predicado confía en el `int $ronda` recibido.** Hoy los 4 llamadores usan `turnManager()->round()`; no hay entrada externa. Remediación opcional: validar `$ronda > 0` o recibir el combatiente.
- **[INFO] `unserialize()` sin `allowed_classes` en `BattleSessionService`.** Preexistente; requiere compromiso previo de sesión/DB. Remediación en tarea de hardening.
- **[INFO] `ultimaRondaProteccion` malformado en payload.** `desdeArray` clampa a `max(0,(int))`; requiere escritura en sesión server-side; no alcanzable desde HTTP.

## 4. Dependencias (`composer audit`)

Preexistentes, fuera del alcance de TASK-040; no modifican `composer.json`/`composer.lock`. No bloquean el commit, pero deben resolverse o aceptarse por escrito antes de producción.

| Paquete | Advisory | Explotabilidad |
|---|---|---|
| `laravel/framework 12.67.0` | TokenGuard bypass | No aplicable: solo guard `web`/session. |
| `laravel/framework 12.67.0` | Destrucción por inclusión de relaciones | Condicional a relaciones controladas por usuario; no detectado. |
| `laravel/framework 12.67.0` | XSS en página de debug | Solo con `APP_DEBUG=true`; verificar despliegue. |
| `livewire/livewire 4.4.2` | Bypass de `#[Authorize]` | No aplicable: no se usa. |
| `league/commonmark 2.10.0` | Bypass/DoS Markdown | Sin uso detectado. |
| `symfony/http-foundation 7.4.17` | ReDoS, HEAD/access-control, path bypass, private_ranges, host | Requiere ruta que parsee cabeceras; actualizar a ≥7.4.23. |
| `symfony/http-kernel 7.4.17` | Forwarded headers | Sin `TrustProxies` configurado; exposición limitada. |
| `symfony/routing`, `mime`, `yaml`, `config`, `filesystem` | Varios | Reachability baja o nula. |

**Recomendación:** tarea separada de actualización (`laravel/framework ≥12.69.0`, `livewire ≥4.4.8`, `symfony/*` según rama). No mezclar con TASK-040.

## 5. Controles verificados

- Autoridad del servidor sobre el cooldown: único escritor del marcador son los ejecutores, con la ronda de `GestorTurnos`; ninguna propiedad Livewire participa.
- Defensa en profundidad: filtro de IA (declaración y selección) y barrera del ejecutor; el jugador no puede saltarse el límite aunque declare el movimiento restringido.
- Cobertura completa: `PROTECCION` (Protección/Detección) y `AGUANTE`; verificado contra `moves.json` y el registro de ejecutores.
- Rama bloqueada sin efectos secundarios: no actualiza marcador ni aplica condición; solo log y `return`.
- Serialización y versión sin cambios; el contador persiste.
- Sin nuevas superficies de entrada: ni rutas, ni controladores, ni parámetros, ni SQL, ni archivos.
- Logs escapados; sin secretos ni PII.
- `php -l` y PHPStan limpios en los 7 archivos.

## 6. Riesgos residuales

1. Sesiones antiguas sin `perfil`: `esProteccion()` no filtraría; el ejecutor evita el bucle perpetuo. Riesgo funcional bajo.
2. `SelectorAccionIA::elegirMejorMovimiento()` legacy sin filtro; sin llamadas de producción.
3. IA con solo protección restringida: cae en “Espera”.
4. Deuda de dependencias preexistente.
5. Integridad de la sesión depende del `SESSION_DRIVER` y `APP_KEY`; no se altera.

## 7. Recomendación final

**Aprobable la entrega de TASK-040 desde seguridad.** El cambio corrige el bloqueo sin abrir superficie de ataque y sin modificar autenticación, autorización, sesión, persistencia ni endpoints. No bloquea; los hallazgos son INFO/LOW preexistentes o deuda de dependencias. Antes de producción, planificar la actualización de dependencias o aceptar por escrito el riesgo residual.
