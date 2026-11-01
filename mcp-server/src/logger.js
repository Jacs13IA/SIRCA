import pino from 'pino';

export function createLogger(level = 'info') {
  return pino({
    level,
    redact: {
      paths: ['*.token', '*.reference', '*.email', '*.phone', 'authorization'],
      censor: '[REDACTED]',
    },
  });
}