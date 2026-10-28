---
name: 'Base de Datos de Reportes Ciudadanos'
description: 'Usar al crear o modificar migraciones, modelos Eloquent y estructura SQLite.'
applyTo: 'database/**,app/Models/**'
---

## Reglas de la base de datos

- Utilizar SQLite durante el desarrollo.
- Mantener compatibilidad futura con MySQL.
- Todas las tablas deben incluir:
  - id
  - created_at
  - updated_at
  - deleted_at

- Utilizar SoftDeletes en entidades principales.
- Utilizar foreignId()->constrained() para relaciones.

## Entidades principales

- users
- roles
- departments
- citizens
- report_types
- reports
- report_statuses
- report_assignments
- report_activities
- evidences
- notifications
- audit_logs

## Reportes

Todo reporte debe almacenar:

- folio único
- ciudadano
- dependencia
- tipo de reporte
- descripción
- estatus
- ubicación
- latitud
- longitud

## Auditoría

- Nunca eliminar reportes.
- Mantener historial de cambios.
- Registrar usuario responsable de cada modificación.

## Escalabilidad

Diseñar para soportar nuevas dependencias municipales sin modificar la estructura principal.