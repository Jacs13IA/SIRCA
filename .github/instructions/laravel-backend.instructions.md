---
name: 'Backend Laravel'
description: 'Usar al desarrollar controladores, servicios, modelos y lógica de negocio.'
applyTo: 'app/**,routes/**'
---

## Arquitectura

Utilizar:

- Controllers
- Services
- Form Requests
- Policies
- Models Eloquent

Evitar lógica de negocio dentro de Controllers.

## Convenciones

- Utilizar Resource Controllers.
- Utilizar Route Model Binding.
- Utilizar Form Requests.
- Utilizar Policies para autorización.
- Utilizar Services para reglas de negocio.

## Código

- PHP 8.4 o superior.
- Laravel 12.
- Código compatible con SQLite.

## Seguridad

- Validar toda entrada.
- Evitar consultas SQL directas cuando Eloquent sea suficiente.
- Proteger contra Mass Assignment.

## Reportes

Todo reporte debe seguir el flujo:

Registrado
→ Asignado
→ En Proceso
→ Atendido
→ Cerrado

Opcional:
→ Cancelado