# TASK-068 — Seguridad

- **ID:** TASK-068
- **Estado:** no aplica
- **Fecha:** 2026-11-19

Cambio exclusivamente de presentación: añade etiquetas y flags derivados del
enum `ObjetivoMovimiento` a payloads ya existentes y pinta badges/chips en
Blade/Alpine.

No toca autenticación, autorización, entrada de usuario, SQL, sesiones, cookies,
secretos, dependencias ni endpoints públicos. No se manejan datos personales ni
se altera el targeting, el daño o la IA del combate.

La salida se escapa con `{{ }}` (Blade) y `x-text` (Alpine), sin `x-html` ni
HTML dinámico.