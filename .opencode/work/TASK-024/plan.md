# TASK-024 — Plan v1 — Ola 1: parciales de vistas + JS de combate

- **ID:** TASK-024
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-06
- **Base:** `.opencode/work/TASK-024/architecture.md`, `.opencode/work/TASK-024/analysis.md`, commit `dd83ca9`
- **Alcance autorizado por este plan:** solo la **Ola 1** (vistas + JS). La Ola 3 (`Livewire/Combate.php`) requiere plan y aprobación propios; 3E y Ola 4 quedan fuera.

---

## 1. Objetivo

Reducir el tamaño y la mezcla de responsabilidades de las tres piezas de frontend de mayor deuda, sin cambiar comportamiento, DOM, ids, clases, `wire:*`, contrato Livewire ni sesión:

1. **Frente A:** extraer `equipos/index` (3.284 líneas) a 7 parciales literales.
2. **Frente B:** extraer `habitats/show` (3.007 líneas) a 9 parciales literales.
3. **Frente C:** mover las 22 funciones JS de `combate_page` a un parcial Blade con `<script>` clásico, conservando el orden de carga.

Los tres frentes tienen archivos disjuntos y pueden ejecutarse en paralelo.

---

## 2. Frente A — `equipos/index.blade.php`

### 2.1 Parciales

| Parcial nuevo | Rango origen (`dd83ca9`) |
|---|---:|
| `resources/views/equipos/partials/panel-equipos.blade.php` | 18-244 |
| `resources/views/equipos/partials/panel-pokemon.blade.php` | 274-469 |
| `resources/views/equipos/partials/panel-huevos.blade.php` | 471-602 |
| `resources/views/equipos/partials/modal-exploracion.blade.php` | 610-756 |
| `resources/views/equipos/partials/modal-eliminar-equipo.blade.php` | 758-790 |
| `resources/views/equipos/partials/modal-reemplazo.blade.php` | 792-846 |
| `resources/views/equipos/partials/modal-detalle-pokemon.blade.php` | 848-1179 |

`panel-tabs` (249-272) se mantiene inline.

### 2.2 Reglas

- Extraer desde `git show dd83ca9:resources/views/equipos/index.blade.php`.
- Cada parcial es copia literal, con la misma indentación relativa, ids, clases, `wire:*`, `x-*` y `@include` internos.
- Los `@include` se insertan exactamente donde estaba el bloque.
- No se extraen `@push('scripts')`, datos `@json`, `@php` de presentación, raíces `x-data` ni cabeceras.
- No se crean `@push` en parciales.
- No se renombran ids, clases ni variables.

### 2.3 Criterios de aceptación

1. `diff` vacío entre cada rango original y su parcial.
2. `php artisan view:cache` termina correctamente; después `view:clear`.
3. Conjunto de `id="..."` del original = conjunto agregado de `index + parciales`.
4. Sin cambios en controladores, rutas, JS, Livewire ni sesión.
5. Sin lógica nueva: los parciales solo contienen el código copiado.
6. Revisión independiente APPROVED.

---

## 3. Frente B — `habitats/show.blade.php`

### 3.1 Parciales

| Parcial nuevo | Rango origen (`dd83ca9`) |
|---|---:|
| `resources/views/habitats/partials/columna-izquierda.blade.php` | 9-127 |
| `resources/views/habitats/partials/columna-derecha.blade.php` | 129-475 |
| `resources/views/habitats/partials/modal-exploracion.blade.php` | 478-670 |
| `resources/views/habitats/partials/modal-gestion-favoritos.blade.php` | 672-740 |
| `resources/views/habitats/partials/modal-formacion.blade.php` | 742-803 |
| `resources/views/habitats/partials/modal-gestion-admin.blade.php` | 805-983 |
| `resources/views/habitats/partials/modal-mazmorra.blade.php` | 985-1124 |
| `resources/views/habitats/partials/modal-granja.blade.php` | 1126-1414 |
| `resources/views/habitats/partials/modal-baya.blade.php` | 1416-1518 |

### 3.2 Reglas

- Mismas reglas de copia literal que el Frente A.
- `index` conserva `@extends`, título, `<div x-data="habitatShow()" x-init="init()">`, el grid y los `@include`.
- No se extraen scripts, `@json`, `@php` ni raíces Alpine.

### 3.3 Criterios de aceptación

1. `diff` vacío entre cada rango original y su parcial.
2. `view:cache` correcto; después `view:clear`.
3. Ids del original = ids agregados.
4. Sin cambios en controladores, rutas, JS, Livewire ni sesión.
5. Revisión independiente APPROVED.

---

## 4. Frente C — JS de combate

### 4.1 Cambios

- Nuevo: `resources/views/livewire/partials/combate-scripts.blade.php` con las líneas 11-614 de `combate_page.blade.php` (el `<script>` completo, incluida la etiqueta de cierre).
- `combate_page.blade.php` mantiene:

```blade
@push('scripts')
    @vite(['resources/js/combate.js'])
    @include('livewire.partials.combate-scripts')
@endpush
```

### 4.2 Reglas

- Script clásico, sin `type="module"`, sin IIFE, sin `window.*`.
- Se conservan `const VITALES`, `ESTADOS`, las 22 funciones y el listener `shown.bs.modal`.
- **No** se incluye desde `livewire/combate.blade.php` (doble inclusión y `const` duplicada).
- El `x-init` de `livewire/combate.blade.php` queda intacto.
- No se toca `resources/js/combate.js` ni `app.js`.
- No se requiere `npm run build`.

### 4.3 Criterios de aceptación

1. `diff` vacío entre líneas 11-614 originales y el parcial.
2. `grep -c 'function '` = 22 declaraciones; `shown.bs.modal` = 1.
3. `combate_page` conserva `@push`, `@vite` y `@include` en ese orden.
4. `livewire/combate.blade.php` sin cambios.
5. `view:cache` correcto.
6. Revisión manual: ataque, turno IA, daño/curación, estado, barrera, KO, ronda, log, modal de victoria.
7. Revisión independiente APPROVED.

---

## 5. Archivos y propiedad

### Frente A

- Modificar: `resources/views/equipos/index.blade.php`.
- Crear: `resources/views/equipos/partials/*` (7 archivos).
- No tocar: `resources/views/livewire/*`, `resources/js/*`, `app/Livewire/*`, `app/Http/*`, `routes/*`.

### Frente B

- Modificar: `resources/views/habitats/show.blade.php`.
- Crear: `resources/views/habitats/partials/*` (9 archivos).
- No tocar: `resources/views/habitats/index.blade.php`, `resources/js/*`, `app/Livewire/*`, `app/Http/*`, `routes/*`.

### Frente C

- Modificar: `resources/views/combate_page.blade.php`.
- Crear: `resources/views/livewire/partials/combate-scripts.blade.php`.
- No tocar: `resources/views/livewire/combate.blade.php`, `resources/js/*`, `app/Livewire/*`.

**Regla de concurrencia:** los tres frentes son disjuntos. Si aparece un archivo compartido, se detiene ese frente y se coordina.

---

## 6. Orden y dependencias

```text
Ola 1 (este plan)
  A: parciales de equipos  ─┐
  B: parciales de habitats ─┼─ en paralelo
  C: JS de combate         ─┘

Ola 2 (plan v2)
  Livewire/Combate.php: 3A-3D
  (3E condicionada a aprobación separada)
```

- A y B no dependen de C.
- C debe terminar antes de tocar `Livewire/Combate.php`.
- La Ola 2 no está autorizada por este plan.

---

## 7. Verificación

- **Blade:** `php artisan view:cache` y `php artisan view:clear`.
- **Copia literal:** `diff` de cada parcial contra su rango en `git show dd83ca9:`.
- **Ids:** comparación del conjunto de `id="..."` antes/después.
- **Estática:** Pint (solo si hay PHP tocado; no debería), PHPStan 34, deptrac 100, `git diff --check`.
- **Manual:** `/equipos`, `/habitats/{id}`, `/combate` con una batalla real.
- **Sin tests:** no se crean, actualizan ni ejecutan.

---

## 8. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Rango mal extraído y HTML desbalanceado | Medio | Diff literal + `view:cache` + revisión |
| `@include` fuera del `x-data` y pérdida de alcance Alpine | Medio | Insertar en la misma posición; revisar DOM |
| Ids duplicados o perdidos | Medio | Comparación de conjuntos de ids |
| Doble inclusión del JS | Alto | Incluir solo desde `combate_page`; nunca desde el componente |
| Cambio del orden de scripts | Alto | `@vite` primero, script clásico después; sin módulos nuevos |
| Whitespace distinto entre bloques | Bajo | Aceptado; no altera CSS ni estructura |
| Edición accidental de JS/Livewire | Medio | Archivos autorizados y `git status` |
| PHPStan/deptrac | Bajo | No se toca PHP; mantener 34/100 |

---

## 9. Rollback

- Un commit local por frente o un único commit de la ola, sin `git push`.
- `git revert` del commit correspondiente; sin `git reset --hard`, `git clean` ni `git checkout --`.
- `php artisan view:clear` tras revertir.
- No hay migraciones ni datos que revertir.

---

## 10. Fuera de alcance

- Ola 3: `Livewire/Combate.php` (3A-3D).
- 3E: `ResolutorAccionBatalla`.
- Ola 4: componentes compartidos de modal.
- Extracción de los `@push('scripts')` de Equipos y Hábitats (Ola 1C opcional).
- Datos `@json`, `@php` de presentación y raíces Alpine.
- Cambios de rutas, controladores, endpoints, `SESSION_VERSION`, `BattleSessionService`.
- Tests, migraciones, dependencias, `git push`.

---

## 11. Aprobación solicitada

Aprobar la **Ola 1** de TASK-024:

- **Frente A:** parciales de `equipos/index`.
- **Frente B:** parciales de `habitats/show`.
- **Frente C:** parcial del JS de combate.

La Ola 3 (`Livewire/Combate.php`) se planificará y aprobará por separado.
