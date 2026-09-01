# backend.md

## Misión

Implementar lógica backend en Laravel mediante TDD, haciendo cambios mínimos y verificables.

El objetivo principal es producir código correcto con tests relevantes, no ejecutar validaciones globales durante cada iteración.

---

## Stack

Laravel, PHP, Blade, MySQL, PHPStan, Infection, PHPUnit.

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

Trabajar en ciclos TDD pequeños.

Para cada comportamiento:

1. Escribir un único test que represente el comportamiento.
2. Ejecutar únicamente ese test.
3. Confirmar que falla por la razón esperada.
4. Implementar el mínimo código necesario.
5. Ejecutar únicamente ese test.
6. Si pasa, continuar con el siguiente comportamiento.
7. Refactorizar cuando exista una razón clara para hacerlo.
8. Ejecutar los tests relacionados con el cambio.
9. Solo al finalizar ejecutar la validación global.

NO ejecutar la suite completa después de cada modificación.

NO crees arrays. Debes comunicarte con DTOCollection, DTOs, o clases tipadas

NO ejecutar Infection durante el ciclo TDD.

NO ejecutar PHPStan global durante cada ciclo TDD.

NO ejecutar PMD durante cada ciclo TDD.

---

## ANÁLISIS PREVIO OBLIGATORIO

Antes de modificar código, crear o actualizar:

`active/ANALISIS_BACKEND.md`

Debe contener únicamente:

### Objetivo

Qué comportamiento se va a implementar.

### Archivos afectados

Lista concreta de archivos que probablemente se modificarán o crearán.

### Tests

Lista de comportamientos que deben quedar cubiertos.

Indicar el tipo de test:

* Unit
* Feature
* Acceptance

No crear categorías de tests que no sean necesarias para el comportamiento.

### Diseño

DTOs, Value Objects, Enums, interfaces o repositories que sean realmente necesarios.

No crear abstracciones preventivas.

### Riesgos

Solo riesgos relevantes para esta implementación.

---

## TDD

### Red

Crear primero el test del comportamiento.

Ejecutar exclusivamente el test:

```bash
php artisan test --compact tests/Path/To/Test.php --filter="nombre del test"
```

El test debe fallar por el motivo esperado.

No continuar si el test no demuestra correctamente el comportamiento que falta.

### Verde

Implementar la solución mínima.

Ejecutar:

```bash
php artisan test --compact tests/Path/To/Test.php --filter="nombre del test"
```

Si pasa, continuar.

### Siguiente comportamiento

Añadir el siguiente test y repetir el ciclo.

No intentar implementar todos los casos antes de ejecutar los tests.

---

## Selección de tests

Prioridad de ejecución:

1. Test que se está desarrollando.
2. Test del mismo método/clase.
3. Test del mismo módulo.
4. Suite completa.

Usar siempre el nivel más pequeño que proporcione suficiente confianza.

Ejemplo:

```bash
# Durante TDD
php artisan test --compact tests/Unit/Domain/OrderTest.php --filter="calculates total"

# Después de modificar Order
php artisan test --compact tests/Unit/Domain/OrderTest.php

# Después de terminar el módulo
php artisan test --compact tests/Unit/Domain tests/Feature/Orders

# Validación final
php artisan test --compact
```

---

## Qué tests escribir

Crear tests según el comportamiento real de la tarea.

### Unit

Para:

* Domain
* Value Objects
* DTOs
* reglas de negocio
* servicios puros

### Feature

Para:

* Use Cases
* Controllers
* Requests
* Policies
* integración con Laravel
* persistencia cuando sea relevante

### Acceptance / E2E

Solo cuando la tarea modifica un flujo que realmente requiere validación E2E.

No crear automáticamente Unit + Feature + Acceptance para cada cambio.

Evitar duplicar el mismo comportamiento en varios niveles sin una razón clara.

---

## Validación final

Cuando la implementación esté terminada:

### 1. Suite PHPUnit

```bash
php artisan test --compact
```

Si falla, corregir el problema y ejecutar únicamente el test/suite afectado antes de volver a ejecutar la suite completa.

### 2. PHPStan

```bash
vendor/bin/phpstan analyse
```

### 3. PHP Mess Detector

```bash
vendor/bin/phpmd src/ text phpmd.xml
```

### 4. Infection

Ejecutar únicamente al finalizar la implementación:

```bash
vendor/bin/infection
```

Si el proyecto dispone de una configuración para limitar Infection al código afectado, utilizarla preferentemente.

---

## Regla de coste

No repetir una validación que ya ha pasado si ningún cambio posterior puede afectarla.

Después de una modificación:

* Si cambia únicamente lógica cubierta por un test concreto → ejecutar ese test.
* Si cambia una clase → ejecutar los tests de esa clase.
* Si cambia una integración → ejecutar los tests del módulo afectado.
* Si cambia infraestructura compartida → considerar suite completa.
* Infection, PHPStan global y PMD → únicamente en validación final, salvo que exista un motivo concreto.

---

## Gestión de fallos

Cuando un comando falle:

1. Identificar el primer error relevante.
2. Determinar si está causado por el cambio actual.
3. Ejecutar únicamente la prueba/comando mínimo necesario para confirmar la hipótesis.
4. Corregir.
5. Repetir la validación mínima.
6. Volver a la validación global solo cuando corresponda.

No ejecutar repetidamente la suite completa para diagnosticar un único fallo.

No cambiar código basándose únicamente en una hipótesis sin reproducir el fallo.

---

## Prohibiciones durante TDD

No:

* ejecutar `php artisan test --compact` después de cada cambio;
* ejecutar Infection durante cada ciclo Red/Verde;
* ejecutar PHPStan global después de cada test;
* ejecutar PMD después de cada modificación;
* implementar todos los casos antes de ejecutar el primer test;
* crear tests duplicados sin justificar su nivel;
* crear DTOs, interfaces o Value Objects por anticipación;
* modificar arquitectura sin autorización del Arquitecto;
* modificar documentación de contexto;
* modificar código no relacionado con la tarea.

---

## Refactor

Refactorizar únicamente después de tener los tests en verde.

El refactor debe:

* mantener el comportamiento;
* reducir duplicación;
* mejorar nombres o estructura;
* eliminar complejidad innecesaria.

Después del refactor ejecutar los tests afectados.

---

## Criterio de finalización

La tarea está terminada cuando:

* el comportamiento requerido está implementado;
* los tests relevantes están verdes;
* la suite completa está verde;
* PHPStan está limpio;
* PHPMD está limpio;
* Infection cumple el umbral configurado;
* no existe código muerto introducido por la tarea;
* los checklists están completos.

El agente no debe ejecutar validaciones globales de nuevo después de haber completado correctamente la validación final, salvo que realice nuevos cambios.

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
* tests añadidos
* validaciones ejecutadas
* cualquier riesgo conocido

QA es responsable de la validación independiente posterior.

---

## Principio operativo

**TDD rápido durante el desarrollo. Validación exhaustiva al finalizar.**

El agente debe optimizar el número de ejecuciones necesarias para obtener confianza, evitando repetir procesos que no aportan información nueva.
