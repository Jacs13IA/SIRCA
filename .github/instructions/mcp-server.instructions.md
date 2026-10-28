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
- update_report_status
- get_report_status
- get_report_details
- upload_evidence
- search_reports
- list_report_types

## Código

- Utilizar async/await.
- Manejo obligatorio de errores con try/catch.
- Registrar errores mediante logger.

## Seguridad

- Validar parámetros antes de llamar APIs.
- No exponer trazas al usuario.