# TASK-022 — Plan v1 — Ola 2: `ServicioEjecucionBatalla` + seguimientos

- **ID:** TASK-022
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-06
- **Base:** `TASK-020/architecture.md` §3.3, `TASK-021/final-report.md`, commit `a83c9ed`
- **Alcance autorizado por este plan:** Ola 2. La Ola 3 (Fase 4 + 6B) y la Ola 4 (Fase 5) requieren plan y aprobación propios.

---

## 1. Objetivo

1. **Fase 3A:** dividir `ServicioEjecucionBatalla` (580 líneas) en una fachada delgada y componentes de cálculo, preservando la secuencia exacta y los contratos públicos.
2. **Seguimiento A:** reducir `EstadosCombatiente::puedeActuar()` de 84 a ≤40 líneas y eliminar/usar `SerializadorCombatiente::serializarEfectos()`.
3. **Documentación:** retirar las referencias históricas al CRUD administrativo del README raíz, `docs/architecture.md` y `docs/context.md`.

Los tres frentes tienen archivos disjuntos y pueden ejecutarse en paralelo.

---

## 2. Frente A — Fase 3A: `ServicioEjecucionBatalla`

### 2.1 Situación actual

- 580 líneas.
- Métodos públicos: `__construct`, `calcularYAplicarDano`, `aplicarEstado`, `aplicarStatChanges`, `aplicarEstadoYStatChanges`, `generarLogMovimiento`.
- `calcularYAplicarDanoContra()` concentra ~240 líneas: precondiciones, precisión, inmunidades, multi-golpe, daño, post-impacto, estado secundario, control y contraataque.
- Ya existen `CalculadorGolpes`, `AplicadorDrenaje`, `AplicadorRecoil`, `AplicadorCosteVelocidad`, `AplicadorCuracion` y `GeneradorLogMovimiento` de la Fase 1.

### 2.2 Componentes objetivo

| Componente | Responsabilidad | Archivo |
|---|---|---|
| `EvaluadorPrecondicionesMovimiento` | Humedad, falla sin terreno, objetivo dormido, inmunidad de flags, telepatía, daño diferido | `src/Battle/Domain/Ejecucion/EvaluadorPrecondicionesMovimiento.php` |
| `ResolutorPrecision` | Precisión por clima, multiaccuracy, esquiva e indefensión | `src/Battle/Domain/Ejecucion/ResolutorPrecision.php` |
| `ResolutorImpacto` | Un golpe: cadena de daño, postura, reacciones, combo, inmunidad, efectos | `src/Battle/Domain/Ejecucion/ResolutorImpacto.php` |
| `AplicadorEfectosPostImpacto` | Rompe pantallas, elimina terreno, curación, coste de velocidad, efectos secundarios, control y contraataque | `src/Battle/Domain/Ejecucion/AplicadorEfectosPostImpacto.php` |
| `AplicadorEstadoMovimiento` | `aplicarEstado`, `aplicarStatChanges`, `aplicarEstadoYStatChanges`, Espejo Mágico y cuerpo áureo | `src/Battle/Domain/Ejecucion/AplicadorEstadoMovimiento.php` |

### 2.3 Reglas

- Conservar los 5 métodos públicos de dominio y el constructor con las mismas firmas.
- Conservar el orden exacto: precondiciones → objetivo → potencia/tipo → precisión → inmunidad → golpes → daño → reacciones → efectos globales → secundarios → control → contraataque.
- No añadir ni quitar tiradas RNG; `ResolutorPrecision` usa el mismo `GeneradorAleatorio`.
- No cambiar el texto de log ni el orden de las piezas.
- No tocar `Combatiente`, `AgregadoBatalla`, `BattleSessionService`, `SESSION_VERSION`, tests, rutas ni migraciones.
- Componentes ≤300 líneas; método ≤40; servicio ≤250 líneas.

### 2.4 Criterios de aceptación

1. `ServicioEjecucionBatalla` ≤250 líneas.
2. Los 5 métodos públicos de dominio conservan nombre, parámetros y retorno.
3. Los componentes nuevos ≤300 líneas; todos los métodos ≤40.
4. La secuencia y el número de tiradas RNG no cambian.
5. `calcularYAplicarDano` multiobjetivo conserva drenaje/recoil/coste una sola vez.
6. `aplicarEstadoYStatChanges` conserva la semántica de objetivos alcanzados y cuerpo áureo.
7. Pint pass; PHPStan 34; deptrac 105; sin `app()`/`now()`/`mt_rand()` nuevos.
8. Revisión independiente APPROVED.

---

## 3. Frente B — Seguimientos A

### 3.1 `puedeActuar`

- `src/Battle/Domain/Componentes/Combatiente/EstadosCombatiente.php`.
- Extraer guardas/veredicto en métodos privados ≤40 líneas, preservando el orden confusión → estado → combos y el resultado booleano.
- No cambiar firmas públicas ni serialización.

### 3.2 `serializarEfectos`

- `src/Battle/Domain/Componentes/Combatiente/SerializadorCombatiente.php`.
- Eliminar el helper si no se usa o usarlo desde `EfectosCombatiente::serializar()` para uniformar. Mantener el payload de 54 claves.

### 3.3 Criterios

1. `puedeActuar` ≤40 líneas; helpers ≤40.
2. Serialización de 54 claves intacta.
3. Pint pass; PHPStan 34; deptrac 105.
4. Sin cambios en `ServicioEjecucionBatalla` ni en otros frentes.
5. Revisión independiente APPROVED.

---

## 4. Frente C — Documentación histórica del CRUD

### 4.1 Alcance

- `README.md` raíz: retirar el CRUD administrativo y los 13 submódulos de `Crud/`.
- `docs/architecture.md`: retirar el diagrama/sección de `Crud`.
- `docs/context.md`: retirar las menciones al módulo `Crud` como existente; ajustar la numeración solo si es imprescindible.
- No tocar código, tests, rutas ni baselines.

### 4.2 Criterios

1. `grep` de `src/Crud`, `app/Crud`, `crud:generate` y `/cruds` en los 3 docs = 0.
2. No se introducen afirmaciones falsas sobre módulos existentes.
3. `git diff --check` OK.
4. Sin cambios de código.
5. Revisión documental o revisión C del orquestador.

---

## 5. Archivos y propiedad

### Frente A

- `src/Battle/Domain/ServicioEjecucionBatalla.php`
- Nuevos: `src/Battle/Domain/Ejecucion/{EvaluadorPrecondicionesMovimiento,ResolutorPrecision,ResolutorImpacto,AplicadorEfectosPostImpacto,AplicadorEstadoMovimiento}.php`
- Reutiliza los componentes existentes de `Ejecucion/`.

### Frente B

- `src/Battle/Domain/Componentes/Combatiente/EstadosCombatiente.php`
- `src/Battle/Domain/Componentes/Combatiente/SerializadorCombatiente.php`
- `src/Battle/Domain/Componentes/Combatiente/EfectosCombatiente.php` solo si se usa el helper.

### Frente C

- `README.md`
- `docs/architecture.md`
- `docs/context.md`

**Regla de concurrencia:** A y B son disjuntos (`Ejecucion/` y `Servicio` vs `Componentes/Combatiente/`). C es documentación. Si aparece un archivo compartido, se detiene el frente.

---

## 6. Orden y dependencias

```text
Ola 2
  A: Fase 3A        ─┐
  B: Seguimientos A ─┼─ en paralelo
  C: Docs históricas┘
```

- A depende de la fachada de `Combatiente` (Ola 1, completada).
- B no depende de A.
- C no depende de A/B.
- La Ola 3 (Fase 4 `AgregadoBatalla` + 6B) depende de A.
- La Ola 4 (Fase 5 serialización) depende de la Ola 3.

---

## 7. Verificación

- **Estática:** Pint, PHPStan L6, deptrac, `php -l`, `git diff --check`.
- **Búsquedas:** `grep` de `mt_rand`, `now()`, `app()`, `SESSION_VERSION`, imports prohibidos.
- **Manual:** comparar la secuencia de `calcularYAplicarDano` antes/después; medir líneas/métodos; comparar payload de serialización.
- **Sin tests:** no se crean, actualizan ni ejecutan.

---

## 8. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Reordenar precondiciones/precisión/daño | Alto | Extracción literal; revisar secuencia contra `git show a83c9ed:` |
| Cambiar número/orden de RNG | Alto | Mismo `GeneradorAleatorio`; no añadir tiradas |
| Duplicar drenaje/recoil/coste en multiobjetivo | Alto | Conservar la aplicación única al final |
| Romper `aplicarEstadoYStatChanges` | Medio | Preservar filtro de objetivos y cuerpo áureo |
| Cambiar serialización en B | Alto | Mantener 54 claves y orden |
| Conflictos A/B | Bajo | Archivos disjuntos |
| PHPStan/deptrac | Medio | No superar 34/105 |

---

## 9. Rollback

- Un commit local por frente, sin `git push`, para revertir de forma selectiva.
- Si se prefiere, consolidar en un commit al cerrar la ola.
- No hay migraciones ni datos que revertir.

---

## 10. Fuera de alcance

- Fase 4 (`AgregadoBatalla`).
- Fase 5 (serialización v22 / `SESSION_VERSION`).
- Fase 6B (código muerto/frontend), salvo la documentación histórica del Frente C.
- Tests, migraciones, dependencias, `git push`.

---

## 11. Aprobación solicitada

Aprobar la **Ola 2** de TASK-022:

- Frente A: Fase 3A `ServicioEjecucionBatalla`.
- Frente B: seguimientos A (`puedeActuar`, `serializarEfectos`).
- Frente C: documentación histórica del CRUD.
