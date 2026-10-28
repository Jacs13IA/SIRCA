---
  name: 'Integración Azure AI Foundry'
  description: 'Usar al implementar la conexión entre Laravel y Azure AI Foundry.'
  applyTo: 'app/Services/Foundry/**,app/Http/Controllers/Chat/**'
---

## Arquitectura

El agente está definido y administrado en Azure AI Foundry.

No implementar lógica del agente dentro de Laravel.

Laravel únicamente debe comunicarse con la API de Foundry.

## Responsabilidades

Laravel debe:

- Crear conversaciones.
- Enviar mensajes.
- Recuperar respuestas.
- Mantener conversation_id.
- Mostrar respuestas al usuario.

## Restricciones

- No implementar lógica de clasificación local.
- No duplicar instrucciones del agente.
- No asumir comportamiento interno del agente.

## Configuración

Utilizar variables de entorno:

- FOUNDRY_PROJECT_ENDPOINT
- FOUNDRY_AGENT_NAME
- FOUNDRY_MODEL_DEPLOYMENT

Nunca hardcodear valores.

## Conversaciones

Mantener conversation_id asociado a cada sesión de usuario.

El historial es administrado por Azure AI Foundry.