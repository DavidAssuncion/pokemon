# TASK-028 — Arquitectura

- **Estado:** NO REQUIERE NUEVO DISEÑO DE APLICACIÓN
- **Diseño de verificación:** arnés temporal que instancia el componente Livewire en ambos árboles, ejecuta los mismos escenarios con `mt_srand`, captura el estado y compara JSON.
- **Worktree base:** `/tmp/opencode/task-028/base` en `fad7c65`, con symlink a `vendor` y variables de entorno de testing (`SESSION_DRIVER=array`, `CACHE_STORE=array`, `APP_KEY` de prueba).
- **Sin cambios** en código de aplicación ni en el repositorio.
