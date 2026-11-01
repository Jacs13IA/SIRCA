import { LaravelApiClient } from './laravel-client.js';
import { createApp } from './app.js';
import { loadConfig } from './config.js';
import { createLogger } from './logger.js';

const config = loadConfig();
const logger = createLogger(config.logLevel);
const apiClient = new LaravelApiClient({
  baseUrl: config.laravelBaseUrl,
  token: config.laravelToken,
  timeoutMs: config.requestTimeoutMs,
});
const app = createApp({ apiClient, logger, serverToken: config.serverToken });
const httpServer = app.listen(config.port, config.host, () => {
  logger.info({ host: config.host, port: config.port }, 'SIRCA MCP server started');
});

for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => {
    httpServer.close(() => process.exit(0));
  });
}