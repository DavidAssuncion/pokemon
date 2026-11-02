# Frontend con lógica mínima

Glob: `resources/**`

- El backend es dueño de la lógica de negocio y del orden de las colecciones que se envían a la vista.
- El frontend no replica fórmulas, cálculos de stats/CP ni reglas de ordenación; solo presenta los datos recibidos y aplica filtros de UI.
- Cualquier dato derivado que necesite la UI debe venir ya calculado desde el backend.
