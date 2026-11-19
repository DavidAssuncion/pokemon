# TASK-065 — Aprobación

- **ID de tarea:** TASK-065
- **Estado:** APROBADO
- **Plan:** v1
- **Fecha:** 2026-11-19

## Texto literal de la aprobación

> Aprobar plan v1

## Alcance autorizado

- Implementar el rediseño de Ausente descrito en `plan.md` v1 y `architecture.md`:
  condición `AUSENTE`, máquina de estados de `EfectoTruant`, enfado con boost
  temporal, curación de 1/16 al perder el turno, despacho genérico de turno perdido,
  contrato de vista, badge y descripción de la habilidad.
- Archivos autorizados: los 12 de la tabla del plan (10 backend, 1 Blade, 1 JSON de
  descripciones).

No autorizado: tests, ADR, catálogo CSV, migraciones, dependencias, cambios de
`SESSION_VERSION` ni archivos fuera de la tabla.
