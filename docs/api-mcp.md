# API Laravel para MCP

La API es el único acceso del servidor MCP a los reportes. Azure AI Foundry llama al MCP; el MCP llama a Laravel. Ni Foundry ni MCP deben conectarse directamente a SQLite.

Configura un secreto fuerte y exclusivo para el servidor MCP en `.env`; nunca lo agregues al repositorio ni a prompts:

```env
MCP_API_TOKEN=<secreto-generado-y-privado>
```

Todas las rutas requieren `Authorization: Bearer <MCP_API_TOKEN>` y limitan solicitudes. Si el token no está configurado, la API falla cerrada con `503`.

## Endpoints v1

| Método | Ruta | Uso |
| --- | --- | --- |
| `GET` | `/api/v1/report-types` | Listar tipos y direcciones activas |
| `POST` | `/api/v1/reports` | Registrar reporte ciudadano |
| `GET` | `/api/v1/reports` | Buscar reportes con filtros y paginación |
| `GET` | `/api/v1/reports/{folio}` | Consultar detalle con campos permitidos |
| `GET` | `/api/v1/report-status/{folio}` | Consultar estado sin datos de contacto |
| `POST` | `/api/v1/reports/{folio}/evidence` | Adjuntar JPG/JPEG/PNG/PDF de hasta 10 MB |

## Registro

`POST /api/v1/reports` acepta `report_type_id`, `description`, `location`, coordenadas opcionales y un objeto `citizen` con `name` obligatorio y `email`, `phone`, `contact_consent` opcionales. Si se envía correo o teléfono, `contact_consent` debe ser verdadero. La dirección se deriva del tipo activo; folio y estatus los establece Laravel. La respuesta incluye el folio generado para mostrárselo al ciudadano.

El registro inicia en `registrado` y crea actividad/auditoría con origen `mcp`. La regla de negocio también indica que todo reporte debe tener responsable, pero el flujo actual permite registrar antes de asignar; hasta resolver esa política, la API sigue el flujo existente y no asigna personal automáticamente.

## Respuestas y límites

Todas las respuestas siguen `{ "success": boolean, "message": string, "data": object|null }`. Los errores se expresan como JSON con `400` (validación), `401`, `403`, `404`, `429`, `503` o `500`; nunca incluyen stack traces. Los Resources no devuelven correo, teléfono, rutas privadas de almacenamiento ni campos administrativos innecesarios.

La API del agente ciudadano no permite asignar, cerrar, modificar el estatus ni eliminar reportes. Esas operaciones permanecen en el módulo administrativo. `update_report_status` no se debe registrar como herramienta para este agente MCP.