import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { LaravelApiError } from './laravel-client.js';

const folioSchema = z.string().regex(/^SIR-\d{4}-[A-Z0-9]{8}$/);
const referenceSchema = z.string().regex(/^[A-Za-z0-9]{64}$/);

export function createCitizenMcpServer(apiClient, logger) {
  const server = new McpServer({ name: 'sirca-citizen-mcp', version: '1.0.0' });
  registerCitizenTools(server, apiClient, logger);

  return server;
}

export function registerCitizenTools(server, apiClient, logger) {
  server.registerTool('create_report', {
    description: 'Registra un reporte ciudadano. Laravel genera el folio y el estatus inicial.',
    inputSchema: {
      report_type_id: z.number().int().positive(),
      description: z.string().min(1).max(3000),
      location: z.string().min(1).max(500),
      latitude: z.number().min(-90).max(90).nullable().optional(),
      longitude: z.number().min(-180).max(180).nullable().optional(),
      citizen: z.object({
        name: z.string().min(1).max(255),
        email: z.string().email().max(255).optional(),
        phone: z.string().max(30).optional(),
        contact_consent: z.boolean().optional(),
      }),
    },
  }, async (input) => runTool('create_report', logger, async () => {
    if ((input.citizen.email || input.citizen.phone) && input.citizen.contact_consent !== true) {
      throw new ToolArgumentError('Se requiere consentimiento para guardar datos de contacto.');
    }

    const report = await apiClient.createReport(input);
    return {
      folio: report.folio,
      status: report.status,
      report_type: report.report_type?.name,
    };
  }));

  server.registerTool('get_report_status', {
    description: 'Consulta el estatus de un reporte mediante su folio exacto.',
    inputSchema: { folio: folioSchema },
  }, async ({ folio }) => runTool('get_report_status', logger, () => apiClient.getReportStatus(folio)));

  server.registerTool('search_reports', {
    description: 'Busca un reporte ciudadano por folio exacto, sin filtros globales.',
    inputSchema: { folio: folioSchema },
  }, async ({ folio }) => runTool('search_reports', logger, async () => {
    const result = await apiClient.searchReports(folio);
    return result.reports;
  }));

  server.registerTool('list_report_types', {
    description: 'Lista los tipos de reporte activos disponibles para la ciudadanía.',
    inputSchema: {},
  }, async () => runTool('list_report_types', logger, () => apiClient.listReportTypes()));

  server.registerTool('upload_evidence', {
    description: 'Asocia al reporte una referencia temporal de evidencia cargada previamente en la plataforma.',
    inputSchema: {
      folio: folioSchema,
      reference: referenceSchema,
    },
  }, async ({ folio, reference }) => runTool('upload_evidence', logger, () => apiClient.attachEvidence(folio, reference)));

}

async function runTool(name, logger, operation) {
  try {
    const data = await operation();
    return { content: [{ type: 'text', text: JSON.stringify(data) }] };
  } catch (error) {
    const code = error instanceof LaravelApiError ? error.code : 'INVALID_ARGUMENT';
    const message = error instanceof LaravelApiError || error instanceof ToolArgumentError
      ? error.message
      : 'No fue posible completar la operación.';

    logger.error({ tool: name, code }, 'MCP tool call failed');

    return { isError: true, content: [{ type: 'text', text: message }] };
  }
}

class ToolArgumentError extends Error {}