# Informe final — TASK-001

## Resumen

Se ha implementado la evolución de la página de detalle de Sendas (`/sendas/{tipo}`) y la portada `/sendas`:

- Movimientos agrupados por nivel requerido dentro de las tres columnas de categoría, con divisores colapsables.
- Filtro de nombres en barra superior; eliminado el aside de progreso vertical.
- Curva cerrada de 5 niveles geométricos: `0 / 15.625 / 31.250 / 62.500 / 125.000`; nivel 5 hasta `500.000` exp. La exp real puede superar el tope y se acumula en BD sin subir de nivel.
- Consumo de caramelos de tipo para ganar `100 exp` por caramelo, con botones x1/x10/x100, límite por stock y actualización del árbol sin recarga.
- `Nivel X / 5` y progreso al siguiente tramo en portada y detalle.
- Sin migraciones ni cambios de esquema.

## Estado final

**COMPLETED** (testing PHPUnit diferido según `AGENTS.md`).

- Plan v2 aprobado explícitamente por el desarrollador («Aprobar plan v2»).
- Revisión final: **APPROVED**.
- QA estático final: **PASS**.
- Seguridad: **PASS_WITH_FINDINGS** (sin CRITICAL/HIGH; 2 LOW y 2 INFO).
- El desarrollador eligió corregir LOW/NIT y cerrar.

## Archivos modificados

- `app/Support/SendaProgreso.php` — curva v2, constantes, `umbral()`, `nivel()`, `resumen()`.
- `app/Support/ArbolSendas.php` — firmas sin `$nivelCuenta`; propagación del progreso v2; `ramas` planas.
- `app/Http/Controllers/SendasController.php` — `index/show/desbloquear` sin nivel de cuenta; nuevo `experiencia()`; totales preexistentes conservados.
- `routes/player.php` — `POST /sendas/experiencia` (`sendas.experiencia`).
- `resources/views/sendas/show.blade.php` — refactor completo de UI v2.
- `resources/views/sendas/index.blade.php` — sin textos de cap; `Nivel X / 5`; progreso `exp / exp_siguiente`.

No se crearon migraciones ni documentación permanente. No se hizo commit ni push. Los cambios locales preexistentes del árbol se conservaron.

## Decisiones

- Curva geométrica cerrada de 5 niveles; el tramo final va de 125.000 a 500.000 exp.
- `resumen()` devuelve `exp` clampeada a 500.000 para presentación; el valor real queda en `player_type_paths.exp`.
- `exp_siguiente` en nivel 5 representa el tope (500.000), no un sexto nivel.
- `player_type_paths.level` se mantiene como caché sincronizado y acotado a 5; no se lee para lógica de presentación.
- La agrupación por nivel es presentacional: se calcula en Alpine sobre listas planas ya ordenadas por backend; `ArbolSendas::nodo()` no se rompe.
- El filtro solo busca por nombre, ignora mayúsculas/acentos y autoexpande grupos mientras hay texto.
- El caramelo de tipo tiene doble uso: desbloquear movimientos y ganar exp. Aceptado como riesgo de producto.

## Pruebas ejecutadas

No se ejecutó PHPUnit: el testing está diferido por `AGENTS.md` y `docs/workflow.md`.

Validación realizada:

- `php -l` sobre los PHP del alcance → sin errores.
- `vendor/bin/pint --dirty` / Pint dirigido → pass.
- Compilación Blade (`php artisan view:cache` + `view:clear`) → OK; PHP compilado lintado.
- `node --check` del bloque Alpine → OK.
- `php artisan route:list --path=sendas` → 4 rutas, incluida `sendas.experiencia` bajo `auth`.
- Verificación de la curva con vectores de aceptación: umbrales 1–6, bordes de `nivel()`, porcentajes 15.624→99, 93.750→50, 312.500→50, 500.000→100, 600.000→100 con exp clampeada.
- Render estático de `sendas.show` con payload de nivel 5 → OK.
- Greps de regresión: sin `aside`, `bloqueado_por_cuenta`, `progreso_vertical`, `nivel_por_exp`, `cap`, “Nivel máximo” ni `exp_siguiente === null` en las vistas de Sendas.

## Hallazgos de revisión y seguridad

- **Reviewer:** APPROVED. Sin BLOCKER/HIGH/MEDIUM. NITs residuales: `toggleGrupo` inerte durante el filtro, doble fuente de caramelos en la respuesta y baseline de PHPStan obsoleto.
- **QA:** PASS en los 10 criterios de aceptación; sin tests automatizados de Sendas.
- **Seguridad:** PASS_WITH_FINDINGS.
  - LOW: sin rate limiting en endpoints mutantes de Sendas.
  - LOW: `$fillable` sobreexpuesto en `PlayerTypePath`/`PlayerInventory` (latente, no explotable en el flujo actual).
  - INFO: TOCTOU de disponibilidad/coste en `desbloquear` (nulo hoy; deuda futura).
  - INFO: `exp` real frente a `exp` de presentación clampeada.
- Controles verificados: CSRF, aislamiento por `user_id`, transacción con `lockForUpdate`, consumo mínimo, validación `cantidad in:1,10,100`, recálculo de `level`, sin IDOR ni mass assignment en el flujo.

## Riesgos residuales

- **Alto — reset de progresión:** con U(2)=15.625, un jugador con 9.050 exp pasa de nivel 4 a 1. Conserva los desbloqueos comprados, pero pierde acceso a nodos no comprados de niveles 2–4. No se aplicó backfill ni compensación; es una decisión de producto pendiente antes de producción.
- **Alto — economía:** llegar a nivel 5 exige 125.000 exp = 1.250 caramelos; el caramelo compite con el desbloqueo (10–400).
- **Alto — testing:** no hay `SendaProgresoTest` ni `SendasTest`; el comportamiento runtime del endpoint, la transacción, la concurrencia y los bordes no está cubierto por pruebas automatizadas.
- **Medio — PHPStan:** baseline obsoleto; el análisis puede seguir en rojo por conteos de archivos locales ajenos.
- **Bajo — rate limiting:** ausencia de `throttle` por usuario en las rutas de Sendas.
- **Bajo — `$fillable`:** riesgo latente si en el futuro se usa mass assignment con estos modelos.
- **Bajo — caché `level`:** filas existentes pueden estar desincronizadas hasta la próxima escritura; no se lee para lógica.
- **Bajo — `required_level`/`SendaCostes`:** siguen acotados a 5; deuda latente si se añade contenido por encima del nivel 5.
- **Bajo — exp > 500.000:** se acumula en BD pero la UI muestra el tope; decisión de producto.
- **Bajo — concurrencia UI:** las guardas `saving || savingExp` evitan solapamientos, pero no hay verificación en navegador.

## Nota sobre el modelo

Antes de la prohibición expresa, el backend inicial se ejecutó con `opencode-go/deepseek-v4-pro` siguiendo el diseño v1. El desarrollador prohibió ese modelo y la configuración global pasó a exigir `opencode-go/deepseek-v4.1-flash`. A partir de esa instrucción, todos los subagentes (arquitecto v2, backend v2, frontend, reviewer, QA y seguridad) usaron `deepseek-v4.1-flash`. El uso inicial de `pro` queda registrado como error de configuración y no se repitió.

## Próximos pasos recomendados

1. Decidir si se aplica backfill/compensación de exp antes de producción por el reset de progresión.
2. Añadir `throttle` por usuario a `sendas.experiencia` y `sendas.desbloquear`.
3. Endurecer `$fillable` de `PlayerTypePath`/`PlayerInventory` o escribir con `forceFill`/repositorio.
4. Programar `SendaProgresoTest` y `SendasTest` al cierre del módulo.
5. Regenerar el baseline de PHPStan con el árbol limpio y actualizar dependencias con advisories.
6. Hacer una prueba manual en navegador del filtro, colapsables, Enter/botón nativo de limpieza y consumo x1/x10/x100.
