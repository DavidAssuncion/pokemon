# Política de modelo

Glob: `**`

- **Modelo canónico: `opencode-go/deepseek-v4.1-flash`.** Todos los agentes y sesiones del proyecto
  deben usar ese modelo.
- **Nunca cambiar de modelo, proveedor ni variante** (`#high`, `#max`, etc.) durante una tarea ni
  entre tareas.
- No delegar en un agente configurado con otro modelo.
- Si una configuración, agente o herramienta intenta usar otro modelo, se considera un error de
  configuración: detener y reportar.
- Solo el desarrollador puede autorizar un cambio explícito por escrito; sin esa autorización, no
  se modifica la configuración de modelos.
