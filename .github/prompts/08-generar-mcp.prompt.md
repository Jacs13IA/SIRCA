# Generar Servidor MCP

Actúa como especialista MCP.

Stack:

- Node.js 24
- Express 5
- ES Modules

Objetivo:

Crear herramientas MCP consumidas por Azure AI Foundry.

Herramientas requeridas:

- create_report
- get_report_status
- upload_evidence
- search_reports
- list_report_types

Alcance ciudadano:

- No registrar `update_report_status`; los cambios de estatus son administrativos.
- `search_reports` acepta únicamente un folio exacto, sin filtros globales.
- `upload_evidence` recibe una referencia temporal privada emitida por Laravel; nunca descarga una URL arbitraria.
- Autenticar Foundry al MCP con una credencial distinta de la usada por MCP hacia Laravel.

Restricción:

No acceder directamente a SQLite.

Consumir exclusivamente APIs Laravel.

Primero presentar arquitectura.
Después generar código.