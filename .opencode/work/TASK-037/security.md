# TASK-037 — Seguridad

- **Fecha:** 2026-11-09
- **Auditor:** `security` (sesión `ses_e3fb1d19affeAZ9jFCvrBDxtKh`)
- **Veredicto final:** **PASS** tras revalidación
- **Veredicto inicial:** FINDINGS (2 MEDIUM del cambio, corregidos)

## Resumen

La fijación del rival de ruta, el aislamiento por usuario, la limpieza del snapshot y la leyenda anónima son correctas. Los dos MEDIUM atribuibles a TASK-037 quedaron corregidos y revalidados:

1. **Crecimiento no acotado de snapshots:** corregido con poda y máximo de 5 previews vigentes por usuario.
2. **Orden del roster que revelaba formación:** corregido con orden neutral por hash determinista.

Queda un residual LOW aceptado (`unserialize` sin allow-list) y hallazgos preexistentes fuera del alcance.

## Hallazgos del cambio

### MEDIUM — Crecimiento no acotado de snapshots (corregido)

- **Ubicación original:** `app/Support/CombateRuta/AlmacenRivalRutaFijado.php`.
- **Vector:** cada GET de preview sin `preview_id` creaba una clave nueva; el TTL solo se evaluaba al leer.
- **Corrección:** `fijar()` llama a `podar($userId)`; se descartan payloads caducados/corruptos y se limita a 5 vigentes por usuario, eliminando los más antiguos.
- **Verificación:** 7 `fijar()` consecutivos dejan 5 claves; el primer preview desaparece y el último permanece. No se tocan claves de otros usuarios.

### MEDIUM — El orden de la leyenda revelaba vanguardia/retaguardia (corregido)

- **Ubicación original:** `app/Support/Pc/ConstruirLeyendaIvsEvs.php`.
- **Vector:** el orden de `DatosPokemonBatallaCollection` en gimnasio es vanguardia→retaguardia; “Rival 1” revelaba la posición.
- **Corrección:** orden neutral por `hash('sha256', nombre|speciesId|id)` antes de etiquetar; no se expone el hash.
- **Verificación:** el orden es idéntico con entrada directa e invertida; no coincide con la posición. La nota de UI “El orden de los rivales no indica su posición” pasa a ser correcta.

### LOW — `unserialize` sin allow-list (residual aceptado)

- **Ubicación:** `app/Support/CombateRuta/AlmacenRivalRutaFijado.php:65`.
- **Evidencia:** `unserialize($serializado)` con `try/catch` + `instanceof`. El payload lo genera el servidor; el cliente solo controla la clave.
- **Impacto:** object injection condicionado a escritura en el almacén de sesión; no hay vector remoto demostrado.
- **Recomendación:** `allowed_classes` con el grafo completo o serialización JSON/DTO en una tarea de endurecimiento; valorar `SESSION_ENCRYPT=true`.

### LOW — Validación laxa de `preview_id` en GET/POST (pendiente)

- **Ubicación:** `src/CombateRuta/Infra/Controllers/CombateRutaController.php:43` y `:101`.
- **Evidencia:** GET/POST aceptan `sometimes|string|max:64`; el DELETE exige `^[a-f0-9]{32}$`.
- **Impacto:** no explotable; la clave incluye `userId` y `fijar()` solo escribe ids hex del servidor. No hay traversal ni IDOR.
- **Recomendación:** unificar la regex en GET/POST.

### INFO — GET con efecto de sesión

- **Ubicación:** `CalcularComparacionPcRuta::resolverRival()`.
- **Impacto:** el GET crea snapshot si no hay `preview_id` válido; el riesgo CSRF es bajo (`SameSite=Lax`) y ahora el estado está acotado a 5 por usuario.
- **Recomendación:** valorar `POST /preview` o cache por `(userId, habitat, nivel)` en una tarea futura.

### INFO — Fingerprinting aceptado

- Los stats base permiten inferir especies; los stats calculados permiten inferir el nivel del gimnasio. Producto lo aceptó en el plan. No se expone `nombre`, `species_id`, tipos, movimientos, posición ni `nivel_stats`.

## Hallazgos preexistentes (fuera de TASK-037)

### HIGH — CRUD `/api/admin/gyms` sin autorización de rol

- **Ubicación:** `routes/gimnasios.php:13-18`, `AdminGymController`.
- **Evidencia:** cualquier usuario autenticado puede modificar el catálogo global de gimnasios; no hay Gate/Policy de admin.
- **Impacto:** integridad del catálogo global y del contenido de combate de todos los jugadores.
- **Recomendación:** Gate/Policy de administración o retirar esas rutas del grupo de usuarios. **No lo introduce TASK-037**, pero debe abordarse en tarea propia con prioridad.

### MEDIUM — XSS reflejado en `gimnasios/show.blade.php:11`

- **Ubicación:** `x-data="gimnasioShow('{{ $slug }}')"`.
- **Impacto:** un slug manipulado podría ejecutar JS en sesión autenticada.
- **Recomendación:** `x-data="gimnasioShow(@js($slug))"` o `data-slug` + `$el.dataset.slug`; validar el slug y devolver 404. No lo introduce TASK-037.

### MEDIUM — Dependencias con avisos

- `composer audit`: 17 advisories en 10 paquetes (`laravel/framework`, `symfony/*`, `livewire/livewire`, `league/commonmark`). No se añadieron dependencias en TASK-037. Planificar actualización controlada y `APP_DEBUG=false` en producción.

## Controles correctos

- **Autenticación:** las 4 rutas nuevas están bajo `web` + `Authenticate`.
- **Autorización/IDOR:** `team_id` validado por propietario en controlador y en `ObtenerPcEquipoJugador`; `preview_id` ligado a `userId` en la clave; DELETE solo borra la clave del usuario autenticado; 204 uniforme.
- **Snapshot:** `preview_id` de 128 bits, versión, hábitat, nivel, TTL, `instanceof`, `try/catch`, `forget`; poda y tope de 5.
- **Entrada:** `nivel` 1–3, `trainer` 1–3, `habitat` existente, `team_id` entero y propio; sin SQL crudo ni mass assignment.
- **Datos:** la leyenda no expone nombres, especies, `species_id`, tipos, movimientos, posición ni `nivel_stats`; `PerfilRivalAplicado::toArray()` omite `nivelStats`.
- **XSS en código nuevo:** solo `x-text`, `:aria-label`, `@json`; sin `{!! !!}` ni `innerHTML`.
- **Limpieza:** snapshot borrado tras crear la batalla y con DELETE; fallback al generador si falta o caduca.
- **CSRF:** DELETE y POST pasan por CSRF; el frontend envía `X-CSRF-TOKEN`.

## Riesgos residuales

- `unserialize` sin allow-list (LOW, aceptado).
- `preview_id` GET/POST más laxo que DELETE (LOW).
- GET con efecto de sesión (INFO).
- Más de 5 previews simultáneos: el más antiguo se evicta y ese combate cae al generador (funcional, no seguridad).
- Fingerprinting e inferencia de nivel (aceptado por producto).
- Hallazgos preexistentes: admin CRUD sin rol (HIGH), XSS del slug (MEDIUM), dependencias (MEDIUM).

## Recomendación

1. Abrir tarea urgente para el CRUD admin sin autorización (HIGH preexistente).
2. Corregir el XSS del slug en `gimnasios/show.blade.php`.
3. Endurecer `unserialize` y unificar la validación de `preview_id` en una tarea de hardening.
4. Planificar actualización de dependencias y confirmar `APP_DEBUG=false`.
5. No bloquea TASK-037.

## Archivos modificados por el auditor

Ninguno.
