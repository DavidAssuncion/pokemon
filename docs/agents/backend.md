# backend.md

## Misión

Implementar lógica backend en Laravel con cambios mínimos y tipados, centrándose únicamente en el código.

Durante el desarrollo NO se ejecuta testing: el testing del módulo se difiere a una fase posterior, cuando el módulo esté completo.

---

## Stack

Laravel, PHP, Blade, MySQL, PHPStan, PHPUnit, Infection.

---

## Fuentes de contexto

Leer siempre antes de modificar código:

* `docs/context.md`
* `docs/architecture.md`
* `docs/conventions.md`
* `active/RESUMEN_TAREA.md`
* `src/<modulo>/context.md`

No releer estos archivos durante la misma tarea salvo que cambien.

---

## Regla principal de ejecución

Centrarse exclusivamente en escribir código correcto y tipado.

1. Leer la especificación y el contexto.
2. Implementar el comportamiento completo.
3. Mantener el tipado estricto y la reutilización (DRY).
4. No ejecutar tests.
5. Documentar los comportamientos a cubrir en el testing futuro.
6. Aplicar formato (Pint) antes del handoff.

NO ejecutar la suite de tests.

NO ejecutar Infection.

NO ejecutar PHPStan global.

NO ejecutar PMD.

NO crear arrays. Debes comunicarte con DTOCollection, DTOs, Collections tipadas o clases tipadas.

---

## Tipado y datos (obligatorio)

* Nunca devolver `array` como contrato entre capas, métodos públicos, casos de uso, repositorios, controladores o APIs.
* Usar DTOs readonly, `DTOCollection` y Collections tipadas (`Src\Shared\Domain\Collection`).
* Usar Enums y Value Objects para primitivas cerradas.
* Propiedades `private`/`readonly` con getters tipados.
* Declarar `declare(strict_types=1)` y tipos completos en parámetros y retornos.
* Única excepción tolerada: `toArray()` interno requerido por Eloquent para persistencia; nunca como contrato público.

---

## DRY y reutilización (obligatorio)

* Antes de crear código nuevo, buscar reutilización en el módulo y en `src/Shared`.
* Código reutilizable por un módulo vive en ese módulo.
* Código que puede repetirse entre módulos vive en `src/Shared` (o `app/` si es infraestructura Laravel transversal).
* No duplicar bloques de más de 5 líneas.

---

## ANÁLISIS PREVIO OBLIGATORIO

Antes de modificar código, crear o actualizar:

`active/ANALISIS_BACKEND.md`

Debe contener únicamente:

### Objetivo

Qué comportamiento se va a implementar.

### Archivos afectados

Lista concreta de archivos que probablemente se modificarán o crearán.

### Comportamientos a cubrir en el testing futuro

Lista de comportamientos que deberán quedar cubiertos cuando se ejecute el testing del módulo.

Indicar el tipo previsto:

* Unit
* Feature
* Acceptance

No ejecutar esos tests ahora.

### Diseño

DTOs, Value Objects, Enums, interfaces, Collections o repositories que sean realmente necesarios.

No crear abstracciones preventivas.

### Riesgos

Solo riesgos relevantes para esta implementación.

---

## Implementación

Implementar el comportamiento completo de una vez, sin ciclos de test rojo/verde.

* Escribir código tipado y explícito.
* Reutilizar DTOs, Collections y servicios existentes.
* No crear abstracciones por anticipación.
* Mantener el código autoexplicativo.

---

## Validación de código (sin testing)

Antes del handoff:

1. Formato: `vendor/bin/pint --dirty --format agent`.
2. Revisar manualmente: tipado completo, sin arrays públicos, sin duplicación, sin código muerto.
3. NO ejecutar la suite de tests, Infection, PHPStan global ni PMD.

El testing se ejecutará en la fase de testing del módulo completo.

---

## Refactor

Refactorizar solo cuando reduzca duplicación o mejore nombres/estructura, preservando el comportamiento.

No ejecutar tests tras el refactor (testing diferido).

---

## Criterio de finalización

La tarea está terminada cuando:

* el comportamiento requerido está implementado;
* el código está tipado y sin arrays públicos;
* no hay duplicación >5 líneas;
* no hay código muerto introducido por la tarea;
* Pint está limpio;
* los comportamientos a testear quedan documentados en el análisis previo;
* el testing queda diferido a la fase de módulo completo.

---

## Handoff

Antes del handoff:

1. Verificar estado de Git.
2. Revisar diff.
3. Confirmar que solo se han modificado archivos relacionados con la tarea.
4. Crear commit atómico con mensaje convencional.
5. Obtener hash corto de 10 caracteres.

Entregar a QA:

* task name
* commit hash de 10 caracteres
* prioridad
* resumen breve de cambios
* comportamientos a cubrir en el testing futuro
* validaciones de código ejecutadas (Pint)
* cualquier riesgo conocido

QA es responsable de la revisión estática posterior.

---

## Principio operativo

**Código primero. Testing diferido al cierre del módulo.**

El agente debe centrarse en producir código correcto, tipado y reutilizable, minimizando ejecuciones que consumen tokens.
