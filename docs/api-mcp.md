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
| `GET` | `/api/v1/reports?folio={folio}` | Buscar un reporte por folio exacto; sin listados ni filtros globales |
| `GET` | `/api/v1/reports/{folio}` | Consultar detalle con campos permitidos |
| `GET` | `/api/v1/report-status/{folio}` | Consultar estado sin datos de contacto |
| `POST` | `/api/v1/reports/{folio}/evidence` | Adjuntar JPG/JPEG/PNG/PDF de hasta 10 MB |
| `POST` | `/api/v1/reports/{folio}/evidence/reference` | Asociar una referencia temporal de evidencia |

## Evidencia temporal desde el chat

El navegador carga el archivo a `POST /evidence-staging` con protección CSRF y límite de 10 solicitudes por minuto. Laravel valida JPG/JPEG/PNG/PDF y 10 MB, guarda el archivo en almacenamiento privado y responde con una referencia aleatoria de 64 caracteres válida por 30 minutos. La referencia en claro solo se devuelve al chat; Laravel conserva su hash.

Foundry pasa la referencia y el folio a `upload_evidence`. MCP llama a la ruta de asociación con `Authorization: Bearer <MCP_API_TOKEN>`. Laravel consume la referencia una sola vez, registra la evidencia, actividad y auditoría, y elimina el archivo temporal. Las referencias vencidas y sus archivos se purgan con la tarea programada `php artisan schedule:run` (configurar el scheduler de Laravel cada minuto en producción). MCP nunca descarga URLs ni accede a rutas privadas.

## Registro

`POST /api/v1/reports` acepta `report_type_id`, `description`, `location`, coordenadas opcionales y un objeto `citizen` con `name` obligatorio y `email`, `phone`, `contact_consent` opcionales. Si se envía correo o teléfono, `contact_consent` debe ser verdadero. La dirección se deriva del tipo activo; folio y estatus los establece Laravel. La respuesta incluye el folio generado para mostrárselo al ciudadano.

El registro inicia en `registrado` y crea actividad/auditoría con origen `mcp`. La regla de negocio también indica que todo reporte debe tener responsable, pero el flujo actual permite registrar antes de asignar; hasta resolver esa política, la API sigue el flujo existente y no asigna personal automáticamente.

La búsqueda ciudadana requiere el folio completo con formato `SIR-AAAA-XXXXXXXX`; busca por igualdad y devuelve solo el resumen de estatus. No permite folios parciales, filtros por fechas/tipo/estatus ni listados globales.

## Respuestas y límites

Todas las respuestas siguen `{ "success": boolean, "message": string, "data": object|null }`. Los errores se expresan como JSON con `400` (validación), `401`, `403`, `404`, `429`, `503` o `500`; nunca incluyen stack traces. Los Resources no devuelven correo, teléfono, rutas privadas de almacenamiento ni campos administrativos innecesarios.

La API del agente ciudadano no permite asignar, cerrar, modificar el estatus ni eliminar reportes. Esas operaciones permanecen en el módulo administrativo. `update_report_status` no se debe registrar como herramienta para este agente MCP.

## Servidor MCP

El servidor usa `MCP_SERVER_TOKEN` para autenticar a Foundry y `LARAVEL_MCP_API_TOKEN` para autenticarse ante Laravel. Son credenciales distintas; la segunda debe coincidir con `MCP_API_TOKEN` configurado en Laravel. Solo se exponen `create_report`, `get_report_status`, `search_reports`, `list_report_types` y `upload_evidence`. `update_report_status` y `get_report_details` no forman parte del MCP ciudadano.