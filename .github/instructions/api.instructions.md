---
    name: 'API Laravel'
    description: 'Usar al crear endpoints REST y servicios consumidos por MCP.'
    applyTo: 'routes/api.php,app/Http/Controllers/Api/**'
---

## Reglas API

- Versionar endpoints bajo /api/v1.
- Responder siempre JSON.
- Utilizar Resource Responses.

## Formato de respuesta

{
  "success": true,
  "message": "",
  "data": {}
}

## Endpoints principales

- POST /reports
- GET /reports
- GET /reports/{id}
- PUT /reports/{id}
- POST /reports/{id}/evidence
- GET /report-status/{folio}

## Errores

- 400 Validación
- 401 No autenticado
- 403 No autorizado
- 404 No encontrado
- 500 Error interno