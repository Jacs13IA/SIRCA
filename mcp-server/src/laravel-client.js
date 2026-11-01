export class LaravelApiError extends Error {
  constructor(message, status = 0, code = 'UPSTREAM_ERROR') {
    super(message);
    this.name = 'LaravelApiError';
    this.status = status;
    this.code = code;
  }
}

const safeErrors = new Map([
  [400, ['La solicitud no pasó la validación.', 'VALIDATION_ERROR']],
  [401, ['La API Laravel rechazó la autorización.', 'API_UNAUTHORIZED']],
  [403, ['La operación no está autorizada.', 'API_FORBIDDEN']],
  [404, ['No se encontró el recurso solicitado.', 'NOT_FOUND']],
  [429, ['La API recibió demasiadas solicitudes; intenta más tarde.', 'RATE_LIMITED']],
  [503, ['La API Laravel no está configurada temporalmente.', 'API_UNAVAILABLE']],
]);

export class LaravelApiClient {
  constructor({ baseUrl, token, timeoutMs = 8000, fetchImpl = fetch }) {
    this.baseUrl = baseUrl;
    this.token = token;
    this.timeoutMs = timeoutMs;
    this.fetchImpl = fetchImpl;
  }

  async listReportTypes() {
    const data = await this.request('/api/v1/report-types');
    return data.report_types;
  }

  async createReport(report) {
    return this.request('/api/v1/reports', { method: 'POST', body: report });
  }

  async getReportStatus(folio) {
    return this.request(`/api/v1/report-status/${encodeURIComponent(folio)}`);
  }

  async searchReports(folio) {
    const query = new URLSearchParams({ folio });
    return this.request(`/api/v1/reports?${query}`);
  }

  async attachEvidence(folio, reference) {
    return this.request(`/api/v1/reports/${encodeURIComponent(folio)}/evidence/reference`, {
      method: 'POST',
      body: { reference },
    });
  }

  async request(path, { method = 'GET', body } = {}) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), this.timeoutMs);

    try {
      const response = await this.fetchImpl(new URL(path, this.baseUrl), {
        method,
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${this.token}`,
          ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
        },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
        signal: controller.signal,
      });

      let payload;
      try {
        payload = await response.json();
      } catch {
        throw new LaravelApiError('La API Laravel devolvió una respuesta inválida.', response.status, 'INVALID_RESPONSE');
      }

      if (!response.ok) {
        const [message, code] = safeErrors.get(response.status) ?? [
          'No fue posible completar la solicitud en Laravel.',
          'UPSTREAM_ERROR',
        ];
        throw new LaravelApiError(message, response.status, code);
      }

      if (payload?.success !== true) {
        throw new LaravelApiError('La API Laravel devolvió una respuesta inválida.', response.status, 'INVALID_RESPONSE');
      }

      return payload.data;
    } catch (error) {
      if (error instanceof LaravelApiError) {
        throw error;
      }

      if (controller.signal.aborted) {
        throw new LaravelApiError('La API Laravel tardó demasiado en responder.', 0, 'UPSTREAM_TIMEOUT');
      }

      throw new LaravelApiError('No fue posible conectar con la API Laravel.', 0, 'UPSTREAM_UNAVAILABLE');
    } finally {
      clearTimeout(timeout);
    }
  }
}