# SIRCA Citizen MCP

Node.js 24 and Express 5 MCP server for Azure AI Foundry. The server exposes citizen tools and accesses report data only through the Laravel v1 API.

## Configuration

Copy `.env.example` to `.env` and set `MCP_SERVER_TOKEN`, `LARAVEL_API_BASE_URL`, and `LARAVEL_MCP_API_TOKEN`. `MCP_SERVER_TOKEN` authenticates Foundry to this server; `LARAVEL_MCP_API_TOKEN` is sent only from this server to Laravel. Use distinct secrets. Do not commit `.env`.

For local development, Laravel's `MCP_API_TOKEN` must match `LARAVEL_MCP_API_TOKEN`. Production Laravel URLs must use HTTPS.

## Run

```powershell
npm.cmd install
npm.cmd test
npm.cmd start
```

The MCP endpoint is `POST /mcp`; `GET /healthz` is an unauthenticated liveness check. The MCP endpoint requires `Authorization: Bearer <MCP_SERVER_TOKEN>`.

## Citizen tools

- `create_report`
- `get_report_status`
- `search_reports` (exact folio only)
- `list_report_types`
- `upload_evidence` (Laravel staging reference; the MCP never downloads a URL)

`update_report_status` is intentionally excluded from the citizen server. Evidence upload first goes to Laravel's CSRF-protected staging route and yields a short-lived, one-use reference. MCP submits that reference to Laravel to associate it with the report.