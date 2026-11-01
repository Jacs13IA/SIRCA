import test from 'node:test';
import assert from 'node:assert/strict';
import { loadConfig } from '../src/config.js';

function validEnv(overrides = {}) {
  return {
    MCP_SERVER_TOKEN: 'a'.repeat(48),
    LARAVEL_MCP_API_TOKEN: 'laravel-secret',
    LARAVEL_API_BASE_URL: 'https://laravel.example',
    ...overrides,
  };
}

test('configuration allows HTTPS and localhost development URLs', () => {
  assert.equal(loadConfig(validEnv()).laravelBaseUrl, 'https://laravel.example');
  assert.equal(loadConfig(validEnv({ LARAVEL_API_BASE_URL: 'http://127.0.0.1:8000' })).laravelBaseUrl, 'http://127.0.0.1:8000');
});

test('configuration rejects remote HTTP and short inbound tokens', () => {
  assert.throws(() => loadConfig(validEnv({ LARAVEL_API_BASE_URL: 'http://laravel.example' })), /HTTPS/);
  assert.throws(() => loadConfig(validEnv({ MCP_SERVER_TOKEN: 'short' })), /32 characters/);
});

test('configuration rejects API URLs containing paths or credentials', () => {
  assert.throws(() => loadConfig(validEnv({ LARAVEL_API_BASE_URL: 'https://user:pass@laravel.example/api' })), /without credentials or a path/);
});