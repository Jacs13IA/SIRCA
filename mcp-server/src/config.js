export function loadConfig(env = process.env) {
  const serverToken = required(env.MCP_SERVER_TOKEN, 'MCP_SERVER_TOKEN');
  const laravelToken = required(env.LARAVEL_MCP_API_TOKEN, 'LARAVEL_MCP_API_TOKEN');
  const baseUrl = new URL(required(env.LARAVEL_API_BASE_URL, 'LARAVEL_API_BASE_URL'));

  if (serverToken.length < 32) {
    throw new Error('MCP_SERVER_TOKEN must contain at least 32 characters.');
  }

  if (baseUrl.username || baseUrl.password || baseUrl.search || baseUrl.hash || baseUrl.pathname !== '/') {
    throw new Error('LARAVEL_API_BASE_URL must be an origin without credentials or a path.');
  }

  const localHosts = new Set(['localhost', '127.0.0.1', '[::1]']);
  if (baseUrl.protocol !== 'https:' && !(baseUrl.protocol === 'http:' && localHosts.has(baseUrl.hostname))) {
    throw new Error('LARAVEL_API_BASE_URL must use HTTPS except for localhost development.');
  }

  const timeoutMs = Number(env.MCP_REQUEST_TIMEOUT_MS ?? 8000);
  if (!Number.isInteger(timeoutMs) || timeoutMs < 1000 || timeoutMs > 30000) {
    throw new Error('MCP_REQUEST_TIMEOUT_MS must be between 1000 and 30000.');
  }

  const port = Number(env.PORT ?? 3100);
  if (!Number.isInteger(port) || port < 1 || port > 65535) {
    throw new Error('PORT must be a valid TCP port.');
  }

  return {
    host: env.HOST ?? '0.0.0.0',
    port,
    serverToken,
    laravelToken,
    laravelBaseUrl: baseUrl.origin,
    requestTimeoutMs: timeoutMs,
    logLevel: env.LOG_LEVEL ?? 'info',
  };
}

function required(value, name) {
  if (typeof value !== 'string' || value.trim() === '') {
    throw new Error(`${name} is required.`);
  }

  return value.trim();
}