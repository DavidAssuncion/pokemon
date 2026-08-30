# Controladores de módulo

Glob: `src/**/Infra/Controllers/**`

Reglas para controladores HTTP de módulo (canónica: `docs/ddd.md`):

- **SIN try-catch**: las excepciones de dominio las resuelve el handler global de `bootstrap/app.php` (mapa HTTP).
- **IDs por parámetro de URL, NUNCA por body — salvo POST** (en POST el id no se recibe: lo asigna el repositorio).
- **Prohibido `array_merge(validated, ['id' => ...])`** en update: construir la entidad con el `id` del path + los campos del FormRequest.
- **FormRequests separados por rol** (ej: `SaveRequest` con `required`, `IndexRequest` con `nullable`/`sometimes`).