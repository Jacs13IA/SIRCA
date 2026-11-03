# API Laravel para MCP

La API es el único acceso del servidor MCP a los reportes. Azure AI Foundry llama al MCP; el MCP llama a Laravel. Ni Foundry ni MCP deben conectarse directamente a SQLite.

## Chat ciudadano y Foundry

El navegador envía mensajes y evidencia únicamente a endpoints web de Laravel, con sesión y CSRF. Laravel llama a la API GA de Microsoft Foundry usando el endpoint de proyecto y el agente ya publicado; no crea agentes ni replica sus instrucciones. Las conversaciones se conservan en la sesión Laravel y el historial de turnos lo administra Foundry.

Configura `FOUNDRY_PROJECT_ENDPOINT`, `FOUNDRY_AGENT_NAME` y `FOUNDRY_MODEL_DEPLOYMENT`. En desarrollo local, Laravel reutiliza la sesión de Azure CLI (`az login`) y obtiene un token con scope `https://ai.azure.com/.default`; no requiere tenant, client ID ni secreto adicionales. En Azure App Service se usa Managed Identity. Los tokens se mantienen en el servidor y se cachean por un periodo menor a su expiración.

Laravel envía `AZURE_CONFIG_DIR` al proceso de Azure CLI. En Windows, por defecto reutiliza el perfil del usuario del proceso (`%USERPROFILE%\.azure`); en otros sistemas utiliza `storage/app/private/azure-cli`. Una ruta alternativa debe permanecer fuera de `public` y tener permisos restringidos para la cuenta del proceso. No configures Azure CLI para guardar sus perfiles o logs en `public`. La regla Apache de `public/.htaccess` también deniega solicitudes a la ruta heredada `public/~/.azure/`.

El canal de runtime recomendado es `LOG_CHANNEL=daily`, con retención configurable mediante `LOG_DAILY_DAYS` (14 días por defecto). PHPUnit usa el canal nulo `testing` para no mezclar sus mensajes con los logs del runtime. Los logs de Foundry conservan etapa, código, estado HTTP y datos de correlación validados cuando están disponibles; no deben incluir tokens, mensajes ciudadanos, respuestas upstream ni salida de Azure CLI.

Las solicitudes verifican TLS usando el bundle de CA resuelto por `composer/ca-bundle`, con fallback a su bundle mantenido cuando el PHP local no tiene un trust store configurado. No desactives la validación de certificados.

Laravel crea conversaciones y envía respuestas por el endpoint GA `v1` del agente `/agents/{agent}/endpoint/protocols/openai`; el modelo de un agente publicado permanece configurado en Foundry y no se sobreescribe en cada turno. `FOUNDRY_MODEL_DEPLOYMENT` queda disponible para escenarios de llamada directa a un modelo. El navegador nunca recibe `conversation_id`, tokens ni errores internos. Las respuestas del agente usan el contrato JSON descrito en [`system-prompt-foundry.md`](system-prompt-foundry.md): `message` es el texto visible y `ui_actions` solo puede habilitar acciones permitidas por Laravel. El agente publicado debe incorporar este contrato para activar la opción de evidencia.

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

El navegador solo puede cargar a `POST /evidence-staging` después de que Laravel valide la acción estructurada `request_evidence` del agente. La ruta mantiene protección CSRF y límite de 10 solicitudes por minuto. Laravel valida JPG/JPEG/PNG/PDF y 10 MB, guarda el archivo en almacenamiento privado y conserva la referencia aleatoria de 64 caracteres (válida por 30 minutos) cifrada en la sesión; el JSON del endpoint nunca la devuelve. Las solicitudes directas o prematuras se rechazan también en el backend.

Laravel entrega la referencia a Foundry como un evento privado de plataforma, nunca como respuesta al navegador. Foundry pasa la referencia y el folio a `upload_evidence`. MCP llama a la ruta de asociación con `Authorization: Bearer <MCP_API_TOKEN>`. Laravel consume la referencia una sola vez, registra la evidencia, actividad y auditoría, y elimina el archivo temporal. Las referencias vencidas y sus archivos se purgan con la tarea programada `php artisan schedule:run` (configurar el scheduler de Laravel cada minuto en producción). MCP nunca descarga URLs ni accede a rutas privadas.

## Registro

`POST /api/v1/reports` acepta `report_type_id`, `description`, `location`, coordenadas opcionales y un objeto `citizen` con `name` obligatorio y `email`, `phone`, `contact_consent` opcionales. Si se envía correo o teléfono, `contact_consent` debe ser verdadero; el agente debe explicar que ese consentimiento permite guardar el contacto y vincular reportes de la misma persona. Cuando se entregan ambos contactos, solo se reutiliza un ciudadano activo si correo y teléfono normalizados identifican al mismo perfil único; con un solo dato, este debe identificar un único perfil. No se usa el nombre para deduplicar ni se sobrescriben los contactos existentes. Las coincidencias parciales o conflictivas crean el reporte y quedan para revisión administrativa, sin fusionar perfiles automáticamente. Laravel normaliza el correo y el teléfono a E.164 (México predeterminado) y almacena huellas HMAC indexadas; los contactos permanecen cifrados. La dirección se deriva del tipo activo; folio y estatus los establece Laravel. La respuesta incluye el folio generado para mostrárselo al ciudadano y no expone contactos ni huellas.

Configura `CITIZEN_MATCHING_KEY` con una clave aleatoria dedicada de al menos 32 bytes. No reutilices `APP_KEY` ni la guardes en el repositorio. Al rotarla, vuelve a ejecutar el comando de deduplicación en modo `--apply` para recalcular las huellas antes de aceptar registros.

Antes de habilitar el registro en un entorno con datos existentes, ejecuta `php artisan citizens:deduplicate` para revisar los conteos de la simulación. Tras confirmar que los perfiles a fusionar son duplicados exactos, ejecuta `php artisan citizens:deduplicate --apply`; los pares que coincidan solo parcialmente se agregarán a la bandeja de revisión administrativa.

El registro inicia en `registrado` y crea actividad/auditoría con origen `mcp`. La regla de negocio también indica que todo reporte debe tener responsable, pero el flujo actual permite registrar antes de asignar; hasta resolver esa política, la API sigue el flujo existente y no asigna personal automáticamente.

La búsqueda ciudadana requiere el folio completo con formato `SIR-AAAA-XXXXXXXX`; busca por igualdad y devuelve solo el resumen de estatus. No permite folios parciales, filtros por fechas/tipo/estatus ni listados globales.

## Respuestas y límites

Todas las respuestas siguen `{ "success": boolean, "message": string, "data": object|null }`. Los errores se expresan como JSON con `400` (validación), `401`, `403`, `404`, `429`, `503` o `500`; nunca incluyen stack traces. Los Resources no devuelven correo, teléfono, rutas privadas de almacenamiento ni campos administrativos innecesarios.

La API del agente ciudadano no permite asignar, cerrar, modificar el estatus ni eliminar reportes. Esas operaciones permanecen en el módulo administrativo. `update_report_status` no se debe registrar como herramienta para este agente MCP.

## Servidor MCP

El servidor usa `MCP_SERVER_TOKEN` para autenticar a Foundry y `LARAVEL_MCP_API_TOKEN` para autenticarse ante Laravel. Son credenciales distintas; la segunda debe coincidir con `MCP_API_TOKEN` configurado en Laravel. Solo se exponen `create_report`, `get_report_status`, `search_reports`, `list_report_types` y `upload_evidence`. `update_report_status` y `get_report_details` no forman parte del MCP ciudadano.