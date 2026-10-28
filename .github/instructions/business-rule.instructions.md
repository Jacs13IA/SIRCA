---
  name: 'Reglas de Negocio'
  description: 'Usar al implementar funcionalidades relacionadas con reportes ciudadanos.'
  applyTo: 'app/**,database/**,agents/**'
---

## Contexto

Sistema de reportes ciudadanos del Ayuntamiento de Champotón.

## Dependencias iniciales

- Servicios Públicos Municipales
- Protección Civil

## Servicios Públicos

Atiende:

- Alumbrado Público
- Levantamientos eléctricos
- Reparaciones relacionadas con luminarias

## Protección Civil

Atiende:

- Árboles en riesgo
- Incendios
- Riesgos para la población

## Regla principal

Todo reporte ciudadano debe:

- Tener folio único.
- Tener historial.
- Tener estatus.
- Tener responsable asignado.
- Tener fecha de creación.

## Trazabilidad

No eliminar registros históricos.

Toda acción importante debe quedar registrada en auditoría.

## Escalabilidad

Preparar el sistema para futuras dependencias municipales sin alterar la estructura principal.