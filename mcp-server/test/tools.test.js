import test from 'node:test';
import assert from 'node:assert/strict';
import { registerCitizenTools } from '../src/tools.js';

function registerTools(createReportImpl = async () => ({
  folio: 'SIR-2026-AB12CD34',
  status: { code: 'registrado' },
  report_type: { name: 'Luminaria' },
})) {
  const tools = new Map();
  const server = {
    registerTool(name, config, handler) {
      tools.set(name, { config, handler });
    },
  };
  const logger = { error() {} };
  const client = {
    async createReport(report) {
      return createReportImpl(report);
    },
    async getReportStatus() {
      return { folio: 'SIR-2026-AB12CD34', status: { code: 'registrado' } };
    },
    async searchReports() {
      return { reports: [{ folio: 'SIR-2026-AB12CD34' }] };
    },
    async listReportTypes() {
      return [{ id: 1, name: 'Luminaria' }];
    },
    async attachEvidence() {
      return { id: 10, original_name: 'photo.jpg' };
    },
  };

  registerCitizenTools(server, client, logger);
  return tools;
}

test('citizen tool registry contains five approved tools only', () => {
  const tools = registerTools();

  assert.deepEqual([...tools.keys()].sort(), [
    'create_report',
    'get_report_status',
    'list_report_types',
    'search_reports',
    'upload_evidence',
  ]);
  assert.equal(tools.has('update_report_status'), false);
});

test('search tool schema requires a complete folio', () => {
  const search = registerTools().get('search_reports');

  assert.equal(search.config.inputSchema.folio.safeParse('SIR-2026-AB12CD34').success, true);
  assert.equal(search.config.inputSchema.folio.safeParse('SIR-2026').success, false);
});

test('create_report returns the folio and initial status, not citizen details', async () => {
  const result = await registerTools().get('create_report').handler({
    report_type_id: 1,
    description: 'Luminaria apagada frente al parque.',
    location: 'Centro, Champotón',
    citizen: { name: 'Ciudadana' },
  });
  const data = JSON.parse(result.content[0].text);

  assert.deepEqual(data, {
    folio: 'SIR-2026-AB12CD34',
    status: { code: 'registrado' },
    report_type: 'Luminaria',
  });
});

test('create_report accepts and forwards null or numeric coordinates', async () => {
  const received = [];
  const tools = registerTools(async (report) => {
    received.push(report);
    return {
      folio: 'SIR-2026-AB12CD34',
      status: { code: 'registrado' },
      report_type: { name: 'Luminaria' },
    };
  });
  const createReport = tools.get('create_report');

  assert.equal(createReport.config.inputSchema.latitude.safeParse(null).success, true);
  assert.equal(createReport.config.inputSchema.longitude.safeParse(null).success, true);
  assert.equal(createReport.config.inputSchema.latitude.safeParse(19.3501).success, true);
  assert.equal(createReport.config.inputSchema.longitude.safeParse(-90.7202).success, true);
  assert.equal(createReport.config.inputSchema.latitude.safeParse(91).success, false);
  assert.equal(createReport.config.inputSchema.longitude.safeParse(-181).success, false);

  const baseInput = {
    report_type_id: 1,
    description: 'Luminaria apagada frente al parque.',
    location: 'Centro, Champotón',
    citizen: { name: 'Ciudadana' },
  };
  await createReport.handler({ ...baseInput, latitude: null, longitude: null });
  await createReport.handler({ ...baseInput, latitude: 19.3501, longitude: -90.7202 });

  assert.deepEqual(received.map(({ latitude, longitude }) => ({ latitude, longitude })), [
    { latitude: null, longitude: null },
    { latitude: 19.3501, longitude: -90.7202 },
  ]);
});

test('create_report requires consent before forwarding contact fields', async () => {
  const result = await registerTools().get('create_report').handler({
    report_type_id: 1,
    description: 'Luminaria apagada frente al parque.',
    location: 'Centro, Champotón',
    citizen: { name: 'Ciudadana', email: 'person@example.test' },
  });

  assert.equal(result.isError, true);
  assert.match(result.content[0].text, /consentimiento/);
});

test('status, type-list, exact-search, and evidence tools return their API results', async () => {
  const tools = registerTools();
  const folio = 'SIR-2026-AB12CD34';
  const reference = 'b'.repeat(64);
  const status = await tools.get('get_report_status').handler({ folio });
  const types = await tools.get('list_report_types').handler({});
  const reports = await tools.get('search_reports').handler({ folio });
  const evidence = await tools.get('upload_evidence').handler({ folio, reference });

  assert.deepEqual(JSON.parse(status.content[0].text), { folio, status: { code: 'registrado' } });
  assert.deepEqual(JSON.parse(types.content[0].text), [{ id: 1, name: 'Luminaria' }]);
  assert.deepEqual(JSON.parse(reports.content[0].text), [{ folio }]);
  assert.deepEqual(JSON.parse(evidence.content[0].text), { id: 10, original_name: 'photo.jpg' });
});