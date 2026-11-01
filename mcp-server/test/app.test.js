import test from 'node:test';
import assert from 'node:assert/strict';
import { once } from 'node:events';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';
import { createApp } from '../src/app.js';

const token = 'a'.repeat(48);

function createDependencies() {
  return {
    apiClient: {
      async listReportTypes() {
        return [{ id: 1, name: 'Alumbrado Público' }];
      },
    },
    logger: { error() {}, info() {} },
    serverToken: token,
  };
}

test('Streamable HTTP authenticates callers and exposes the citizen tools', async () => {
  const app = createApp(createDependencies());
  const httpServer = app.listen(0, '127.0.0.1');
  await once(httpServer, 'listening');
  const address = httpServer.address();
  const transport = new StreamableHTTPClientTransport(new URL(`http://127.0.0.1:${address.port}/mcp`), {
    requestInit: { headers: { Authorization: `Bearer ${token}` } },
  });
  const client = new Client({ name: 'sirca-mcp-test', version: '1.0.0' });

  try {
    await client.connect(transport);
    const result = await client.listTools();
    const names = result.tools.map((tool) => tool.name).sort();

    assert.deepEqual(names, [
      'create_report',
      'get_report_status',
      'list_report_types',
      'search_reports',
      'upload_evidence',
    ]);
  } finally {
    await client.close();
    await new Promise((resolve) => httpServer.close(resolve));
  }
});

test('Streamable HTTP rejects missing bearer authentication', async () => {
  const app = createApp(createDependencies());
  const httpServer = app.listen(0, '127.0.0.1');
  await once(httpServer, 'listening');
  const address = httpServer.address();

  try {
    const response = await fetch(`http://127.0.0.1:${address.port}/mcp`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ jsonrpc: '2.0', id: 1, method: 'initialize', params: {} }),
    });

    assert.equal(response.status, 401);
  } finally {
    await new Promise((resolve) => httpServer.close(resolve));
  }
});