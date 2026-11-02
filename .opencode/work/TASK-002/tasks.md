# TASK-002 — Tareas

| ID | Especialista | Tarea | Depende de | Estado |
|---|---|---|---|---|
| T1 | `analyst` | Analizar arnés IA vs IA, cobertura de efectos y estrategia de bug hunting | — | en curso |
| T2 | `architect` | Diseñar el arnés/test y los invariantes si T1 confirma que hace falta | T1 | pendiente |
| T3a | `backend` | Bugfix Dragoaliento: contrato `bool`, Livewire por delta y tests | Aprobación v1 | en curso |
| T3b | `backend` | Arnés IA vs IA: `maxRondas`, observador, helpers y tests | T3a | en curso |
| T3c | `backend` | Batería determinista de efectos (`EfectosDeterministasTest`) | T3b | en curso |
| T3d | `backend` | Batería determinista de movimientos/estados (`MovimientosEstadosDeterministasTest`) | T3b | en curso |
| T3e | `backend` | Corregir MEDIUM de revisión: campaña priorizada/pares/barrido y 20 batallas CI | T3b | completado |
| T4 | `qa` | Ejecutar simulaciones, clasificar fallos y evidencias | T3e + revisión | completado (`PASS`) |
| T5 | `reviewer` | Revisar arnés y tests | T3 | completado (`APPROVED`) |
| T6 | `security` | No aplica: sin auth, entradas, SQL ni secretos | T3 | no aplica |
| T7 | `documenter` | Trasladar la política de testing eficiente a `docs/testing.md` | Reanudación | completado |
