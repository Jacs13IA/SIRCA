---
name: chatbot-foundry
description: Crear interfaces de conversación conectadas a Azure AI Foundry.
---

# Objetivo

Implementar la experiencia de chat para ciudadanos.

## Arquitectura

1. Navegador
2. Laravel
3. Azure AI Foundry

## Reglas

- No conectar directamente el navegador con Foundry.
- Mantener conversation_id.
- Manejar errores y estados de carga.

## Componentes

- Blade.
- JavaScript.
- Controllers.
- Services.