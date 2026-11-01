import express from 'express';
import { timingSafeEqual } from 'node:crypto';
import { rateLimit } from 'express-rate-limit';
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js';
import { createCitizenMcpServer } from './tools.js';

export function createApp({ apiClient, logger, serverToken }) {
  const app = express();
  app.disable('x-powered-by');
  app.use(express.json({ limit: '32kb', type: 'application/json' }));
  app.use('/mcp', rateLimit({
    windowMs: 60_000,
    limit: 60,
    standardHeaders: true,
    legacyHeaders: false,
    handler: (_request, response) => response.status(429).json({ error: 'Too many requests' }),
  }));

  app.get('/healthz', (_request, response) => response.json({ status: 'ok' }));

  app.post('/mcp', (request, response, next) => {
    if (!hasValidBearer(request.get('authorization'), serverToken)) {
      return response.status(401).json({ error: 'Unauthorized' });
    }

    next();
  }, async (request, response) => {
    const server = createCitizenMcpServer(apiClient, logger);
    const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined });

    response.on('close', () => {
      void transport.close();
      void server.close();
    });

    try {
      await server.connect(transport);
      await transport.handleRequest(request, response, request.body);
    } catch (error) {
      logger.error({ errorName: error?.name ?? 'Error' }, 'MCP request failed');
      if (!response.headersSent) {
        response.status(500).json({ error: 'Internal server error' });
      }
    }
  });

  app.all('/mcp', (_request, response) => response.status(405).json({ error: 'Method not allowed' }));
  app.use((error, _request, response, _next) => {
    logger.error({ errorName: error?.name ?? 'Error' }, 'HTTP request failed');
    response.status(error?.type === 'entity.too.large' ? 413 : 400).json({ error: 'Invalid request' });
  });

  return app;
}

function hasValidBearer(header, expectedToken) {
  if (typeof header !== 'string' || !header.startsWith('Bearer ')) {
    return false;
  }

  const provided = Buffer.from(header.slice(7));
  const expected = Buffer.from(expectedToken);

  return provided.length === expected.length && timingSafeEqual(provided, expected);
}