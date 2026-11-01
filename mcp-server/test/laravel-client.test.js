import test from 'node:test';
import assert from 'node:assert/strict';
import { LaravelApiClient, LaravelApiError } from '../src/laravel-client.js';

function response(data, status = 200) {
  return new Response(JSON.stringify({ success: status < 400, message: '', data }), {
    status,
    headers: { 'content-type': 'application/json' },
  });
}

test('Laravel API client maps only the exact folio lookup', async () => {
  let requestUrl;
  const client = new LaravelApiClient({
    baseUrl: 'https://laravel.example',
    token: 'server-secret',
    fetchImpl: async (url) => {
      requestUrl = url.toString();
      return response({ reports: [] });
    },
  });

  await client.searchReports('SIR-2026-AB12CD34');

  assert.equal(requestUrl, 'https://laravel.example/api/v1/reports?folio=SIR-2026-AB12CD34');
});

test('Laravel API client creates reports using the versioned API', async () => {
  let request;
  const client = new LaravelApiClient({
    baseUrl: 'https://laravel.example',
    token: 'server-secret',
    fetchImpl: async (url, options) => {
      request = { url: url.toString(), options };
      return response({ folio: 'SIR-2026-AB12CD34' }, 201);
    },
  });
  const report = {
    report_type_id: 3,
    description: 'Incidencia',
    location: 'Centro',
    citizen: { name: 'Ciudadana' },
  };

  await client.createReport(report);

  assert.equal(request.url, 'https://laravel.example/api/v1/reports');
  assert.equal(request.options.method, 'POST');
  assert.deepEqual(JSON.parse(request.options.body), report);
});

test('Laravel API client uses the implemented status route', async () => {
  let requestUrl;
  const client = new LaravelApiClient({
    baseUrl: 'https://laravel.example',
    token: 'server-secret',
    fetchImpl: async (url) => {
      requestUrl = url.toString();
      return response({ folio: 'SIR-2026-AB12CD34' });
    },
  });

  await client.getReportStatus('SIR-2026-AB12CD34');

  assert.equal(requestUrl, 'https://laravel.example/api/v1/report-status/SIR-2026-AB12CD34');
});

test('Laravel API client sends the opaque reference to the fixed evidence endpoint', async () => {
  let request;
  const client = new LaravelApiClient({
    baseUrl: 'https://laravel.example',
    token: 'server-secret',
    fetchImpl: async (url, options) => {
      request = { url: url.toString(), options };
      return response({ id: 5, original_name: 'photo.jpg' }, 201);
    },
  });

  await client.attachEvidence('SIR-2026-AB12CD34', 'a'.repeat(64));

  assert.equal(request.url, 'https://laravel.example/api/v1/reports/SIR-2026-AB12CD34/evidence/reference');
  assert.equal(request.options.headers.Authorization, 'Bearer server-secret');
  assert.deepEqual(JSON.parse(request.options.body), { reference: 'a'.repeat(64) });
});

test('Laravel API errors do not expose response details', async () => {
  const client = new LaravelApiClient({
    baseUrl: 'https://laravel.example',
    token: 'server-secret',
    fetchImpl: async () => new Response(JSON.stringify({ message: 'private details', success: false }), { status: 401 }),
  });

  await assert.rejects(client.listReportTypes(), (error) => {
    assert.ok(error instanceof LaravelApiError);
    assert.equal(error.message, 'La API Laravel rechazó la autorización.');
    assert.equal(error.message.includes('private details'), false);
    return true;
  });
});