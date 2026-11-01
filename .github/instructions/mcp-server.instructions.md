---
  name: 'Servidor MCP'
  description: 'Usar al desarrollar herramientas MCP para Azure AI Foundry.'
  applyTo: 'mcp-server/**'
---

## Stack
- Node.js 24 LTS
- Express 5
- ES Modules

## Arquitectura

Azure AI Foundry Agent
↓
MCP Server
↓
Laravel API
↓
SQLite

## Reglas

- Nunca acceder directamente a SQLite.
- Toda operación debe consumir la API Laravel.

## Herramientas MCP

- create_report
- get_report_status
- upload_evidence
- search_reports
- list_report_types

El MCP ciudadano no registra `update_report_status` ni `get_report_details`. La búsqueda ciudadana requiere folio exacto. La evidencia se recibe mediante una referencia temporal emitida por Laravel; no descargar URLs arbitrarias.

## Código

- Utilizar async/await.
- Manejo obligatorio de errores con try/catch.
- Registrar errores mediante logger.

## Seguridad

- Validar parámetros antes de llamar APIs.
- No exponer trazas al usuario.