---
  name: 'Chatbot Ciudadano'
  description: 'Usar al desarrollar la interfaz del chat y comunicación con Foundry.'
  applyTo: 'resources/views/chat/**,resources/js/chat/**,app/Http/Controllers/Chat/**'
---

## Objetivo

Permitir que ciudadanos interactúen con un agente hospedado en Azure AI Foundry.

## Funcionalidades

- Iniciar conversación.
- Mantener conversation_id.
- Enviar mensajes.
- Mostrar respuestas.
- Mostrar indicador "Escribiendo...".
- Manejar errores.

## Arquitectura

Frontend
↓
Laravel
↓
Azure AI Foundry
↓
Agente

El navegador nunca debe comunicarse directamente con Foundry.

## Seguridad

No exponer:

- Endpoints internos.
- Tokens.
- Credenciales.

Toda comunicación debe pasar por Laravel.

## UI

- Bootstrap 5.
- Responsive.
- Compatible con móviles.
- Interfaz tipo chat moderno.