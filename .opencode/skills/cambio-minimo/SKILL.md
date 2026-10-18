---
name: Cambio mínimo
description: "Modo desarrollador sénior vago: el cambio más pequeño que resuelve la tarea por completo. Úsala al escribir, corregir, refactorizar o revisar código; al elegir entre reutilizar o crear; al evaluar dependencias o abstracciones; y ante señales de sobreingeniería (YAGNI, simplicidad, bloat)."
---

# Cambio mínimo

El mejor código es el que no se escribe. Resuelve el problema completo con el menor código nuevo.

Al terminar, deja una o dos líneas: lo que omitiste o no comprobaste y cualquier riesgo que debas conocer.

## Antes de escribir

Lee la tarea y el código que toca. Enumera cada punto que tu cambio debe alcanzar: llamadores, tests, fixtures, config, exports. Comprueba qué puede romper: datos que destruiría o expondría, llamadores que dejarían de funcionar. Eso es el alcance; las features extra no lo son.

## Escalera de la solución mínima

Toma la primera opción que resuelva el problema por completo:

1. **¿Necesita existir?** No construyas features, opciones ni flexibilidad que nadie pidió; nómbralas en una línea. El recorte de alcance pertenece al Analista/usuario: el coder propone la opción menor, no decide por su cuenta.
2. **¿Ya existe en el proyecto** (helper, componente, servicio, patrón)? Úsalo como lo usa el código vecino.
3. **¿Lo cubre la librería estándar o una capacidad de la plataforma?** Úsala, salvo que el proyecto tenga su propia pieza.
4. **¿Está ya instalada una dependencia que lo hace?** Úsala. Nunca añadas una dependencia por unas pocas líneas; toda dependencia nueva requiere aprobación.
5. **¿Puede ser una línea que se lee de un vistazo?** Una línea.
6. **En caso contrario:** el mínimo código que funcione.

## Reglas del cambio mínimo

- Sé vago con la solución, nunca con el cambio: completa todo lo que la tarea necesita, incluidos los llamadores, fixtures y config que tu cambio rompa.
- Sin abstracciones, wrappers, conversiones, opciones, configuración ni código «para después» que nadie pidió. Borrar gana a añadir. Mantén las capas, interfaces y convenciones existentes.
- Gana el diff más corto que se entienda, una vez conoces todo lo que debe tocar. Una línea ilegible no es corta.
- Comenta solo el porqué que el código no puede mostrar, en una línea.
- Bugfix: antes de editar, localiza todos los llamadores de la función que tocas y arregla la causa raíz una sola vez en el código compartido.
- Entre opciones del mismo tamaño, elige la correcta en los casos límite.
- El código que muevas o fusiones conserva su manejo de errores y su validación.

## Nunca recortar

Validación en fronteras de confianza, manejo de errores que evite pérdida de datos, seguridad, accesibilidad y cualquier comportamiento que el usuario haya pedido.

## Verificación (testing diferido)

En este proyecto no se escribe ni ejecuta testing durante el desarrollo: el testing del módulo se difiere a su cierre.

- Lógica nueva no trivial (rama, bucle, parser, dinero o seguridad): registra el comportamiento a cubrir en el análisis previo (`active/ANALISIS_*`).
- Cambios triviales: no requieren nada.

## Atajos deliberados

Una simplificación con límite conocido se marca con un comentario:

```php
// atajo: solo contempla equipos de 3, revisar cuando se permitan invocaciones
```

En Blade/JS se usa la sintaxis de comentario equivalente. El Bibliotecario cosecha los atajos vigentes al cierre del módulo y los vuelca en los pendientes conocidos. Si un atajo se resuelve, su comentario se elimina.
