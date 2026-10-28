---
  name: 'Seguridad del Sistema'
  description: 'Usar al implementar autenticación, autorización y protección de datos.'
  applyTo: '**'
---

## Secretos

Nunca escribir:

- API Keys
- Tokens
- Contraseñas
- Connection Strings

Todo debe provenir de variables de entorno.

## Laravel

Utilizar:

- CSRF Protection
- Middleware
- Policies
- Gates

## Archivos

Permitir únicamente:

- jpg
- jpeg
- png
- pdf

## Validaciones

Validar:

- tamaño
- extensión
- tipo MIME

## IA

El agente no puede:

- eliminar reportes
- modificar reportes cerrados
- realizar operaciones fuera de las herramientas MCP autorizadas
