/**
 * TestClient.js - Opaque-box authenticated HTTP client for FastFood E2E Tests.
 * Uses native Node.js 24 fetch, FormData, and Blob.
 */

class TestClient {
  /**
   * @param {string} [baseUrl] - Base server URL (defaults to TEST_BASE_URL env or http://127.0.0.1:5000)
   */
  constructor(baseUrl) {
    this.baseUrl = baseUrl || process.env.TEST_BASE_URL || 'http://127.0.0.1:5000';
    // Remove trailing slash
    this.baseUrl = this.baseUrl.replace(/\/+$/, '');
    this.jwtToken = null;
    this.adminCookie = null;
    this.trackedSessions = new Set();
  }

  /**
   * Authenticates as admin to obtain JWT token and FF_SESS_ADMIN cookie.
   *
   * @param {string} [email='admin@fastfood.vn']
   * @param {string} [password='123456']
   * @returns {Promise<{ token: string, cookie: string, user: object }>}
   */
  async loginAsAdmin(email = 'admin@fastfood.vn', password = '123456') {
    const url = `${this.baseUrl}/api/auth.php`;
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        action: 'login',
        email,
        password
      })
    });

    const text = await res.text();
    let json = {};
    try {
      json = JSON.parse(text);
    } catch {
      throw new Error(`Admin login failed with unparseable response: ${text.slice(0, 200)}`);
    }

    if (!res.ok || !json.success) {
      throw new Error(`Admin login failed: ${json.message || res.statusText}`);
    }

    this.jwtToken = json.data?.token || null;

    // Parse Set-Cookie header
    const setCookie = res.headers.get('set-cookie');
    if (setCookie) {
      // Find FF_SESS_ADMIN or take first cookie pair
      const match = setCookie.match(/FF_SESS_ADMIN=([^;]+)/i);
      if (match) {
        this.adminCookie = `FF_SESS_ADMIN=${match[1]}`;
      } else {
        this.adminCookie = setCookie.split(';')[0].trim();
      }
    }

    return {
      token: this.jwtToken,
      cookie: this.adminCookie,
      user: json.data?.user
    };
  }

  /**
   * Ensures the client is authenticated as admin.
   */
  async ensureAdminAuth() {
    if (!this.jwtToken || !this.adminCookie) {
      await this.loginAsAdmin();
    }
  }

  /**
   * Builds full headers including admin credentials when available.
   *
   * @param {object} customHeaders
   * @returns {Headers}
   */
  _buildHeaders(customHeaders = {}) {
    const headers = new Headers();

    if (this.adminCookie) {
      headers.set('Cookie', this.adminCookie);
    }
    if (this.jwtToken) {
      headers.set('Authorization', `Bearer ${this.jwtToken}`);
    }

    // Default admin context hints
    headers.set('Referer', `${this.baseUrl}/admin/index.php`);
    headers.set('X-Requested-Role', 'admin');

    for (const [k, v] of Object.entries(customHeaders)) {
      if (v === null || v === undefined) {
        headers.delete(k);
      } else {
        headers.set(k, v);
      }
    }

    return headers;
  }

  /**
   * Normalizes response object.
   */
  async _normalizeResponse(res) {
    const text = await res.text();
    let json = null;
    try {
      json = JSON.parse(text);
    } catch {
      json = null;
    }

    return {
      status: res.status,
      ok: res.ok,
      headers: res.headers,
      text,
      body: json !== null ? json : text,
      data: json?.data !== undefined ? json.data : null,
      json: async () => json,
      raw: res
    };
  }

  /**
   * Performs an HTTP GET request.
   *
   * @param {string} path - E.g. '/api/v1/chat/admin/conversations'
   * @param {object} [headers]
   * @returns {Promise<{ status: number, ok: boolean, headers: Headers, text: string, body: any, data: any }>}
   */
  async get(path, headers = {}) {
    const url = path.startsWith('http') ? path : `${this.baseUrl}${path.startsWith('/') ? '' : '/'}${path}`;
    const reqHeaders = this._buildHeaders(headers);
    const res = await fetch(url, {
      method: 'GET',
      headers: reqHeaders
    });
    return this._normalizeResponse(res);
  }

  /**
   * Performs an HTTP POST request (JSON or text).
   *
   * @param {string} path
   * @param {any} body
   * @param {object} [headers]
   */
  async post(path, body = {}, headers = {}) {
    const url = path.startsWith('http') ? path : `${this.baseUrl}${path.startsWith('/') ? '' : '/'}${path}`;
    const reqHeaders = this._buildHeaders(headers);

    let serializedBody = body;
    if (typeof body === 'object' && !(body instanceof FormData) && !(body instanceof Blob) && !(body instanceof ArrayBuffer)) {
      if (!reqHeaders.has('Content-Type')) {
        reqHeaders.set('Content-Type', 'application/json');
      }
      serializedBody = JSON.stringify(body);
    }

    const res = await fetch(url, {
      method: 'POST',
      headers: reqHeaders,
      body: serializedBody
    });
    return this._normalizeResponse(res);
  }

  /**
   * Performs an HTTP POST multipart/form-data request.
   *
   * @param {string} path
   * @param {FormData} formData
   * @param {object} [headers]
   */
  async postMultipart(path, formData, headers = {}) {
    const url = path.startsWith('http') ? path : `${this.baseUrl}${path.startsWith('/') ? '' : '/'}${path}`;
    const reqHeaders = this._buildHeaders(headers);

    // Ensure Content-Type is not manually set, allowing fetch to set proper multipart boundary
    reqHeaders.delete('Content-Type');

    const res = await fetch(url, {
      method: 'POST',
      headers: reqHeaders,
      body: formData
    });
    return this._normalizeResponse(res);
  }

  /**
   * Performs an HTTP DELETE request.
   *
   * @param {string} path
   * @param {object} [headers]
   */
  async delete(path, headers = {}) {
    const url = path.startsWith('http') ? path : `${this.baseUrl}${path.startsWith('/') ? '' : '/'}${path}`;
    const reqHeaders = this._buildHeaders(headers);
    const res = await fetch(url, {
      method: 'DELETE',
      headers: reqHeaders
    });
    return this._normalizeResponse(res);
  }

  /**
   * Generates a unique, isolated session ID.
   *
   * @param {string} [prefix='e2e_sess']
   * @returns {string}
   */
  generateSessionId(prefix = 'e2e_sess') {
    const rand = Math.random().toString(36).slice(2, 8);
    const id = `${prefix}_${Date.now()}_${rand}`;
    this.trackedSessions.add(id);
    return id;
  }

  /**
   * Registers a session for tracking and teardown.
   *
   * @param {string} [customId]
   * @returns {string}
   */
  createSession(customId = null) {
    const id = customId || this.generateSessionId();
    this.trackedSessions.add(id);
    return id;
  }

  /**
   * Sends a customer message in the given session.
   *
   * @param {string} sessionId
   * @param {string} message
   * @param {object} [options]
   */
  async sendCustomerMessage(sessionId, message, options = {}) {
    this.trackedSessions.add(sessionId);
    return this.post('/api/v1/chat/message', {
      sessionId,
      message,
      customerName: options.customerName || 'E2E Test Customer',
      ...options
    });
  }

  /**
   * Sends an admin reply in the given session.
   *
   * @param {string} sessionId
   * @param {string} replyMessage
   * @param {object} [options]
   */
  async sendAdminReply(sessionId, replyMessage, options = {}) {
    await this.ensureAdminAuth();
    this.trackedSessions.add(sessionId);
    return this.post(`/api/v1/chat/admin/sessions/${sessionId}/reply`, {
      message: replyMessage,
      ...options
    });
  }

  /**
   * Marks a session as read.
   *
   * @param {string} sessionId
   */
  async markSessionAsRead(sessionId) {
    await this.ensureAdminAuth();
    return this.post(`/api/v1/chat/admin/sessions/${sessionId}/read`, {});
  }

  /**
   * Uploads an image using multipart FormData.
   *
   * @param {Buffer|Uint8Array} buffer
   * @param {string} [filename='test.jpg']
   * @param {string} [mimeType='image/jpeg']
   */
  async uploadChatImage(buffer, filename = 'test.jpg', mimeType = 'image/jpeg') {
    const formData = new FormData();
    const blob = new Blob([buffer], { type: mimeType });
    formData.append('image', blob, filename);
    return this.postMultipart('/api/v1/chat/upload-image', formData);
  }

  /**
   * Deletes a test session from the database.
   *
   * @param {string} sessionId
   */
  async deleteSession(sessionId) {
    await this.ensureAdminAuth();
    const res = await this.delete(`/api/v1/chat/admin/sessions/${sessionId}`);
    this.trackedSessions.delete(sessionId);
    return res;
  }

  /**
   * Cleans up local temporary rate limit files to avoid test suite throttling.
   */
  static resetRateLimits() {
    try {
      const os = require('os');
      const fs = require('fs');
      const path = require('path');
      const dir = os.tmpdir();
      const files = fs.readdirSync(dir).filter(f => f.startsWith('rate_limit_'));
      for (const f of files) {
        try { fs.unlinkSync(path.join(dir, f)); } catch {}
      }
    } catch {}
  }

  /**
   * Automated teardown: deletes all sessions tracked by this client instance.
   */
  async cleanupAllSessions() {
    if (this.trackedSessions.size === 0) return;
    await this.ensureAdminAuth();
    const sessionIds = Array.from(this.trackedSessions);
    for (const id of sessionIds) {
      try {
        await this.delete(`/api/v1/chat/admin/sessions/${id}`);
      } catch (err) {
        // Suppress teardown errors to avoid masking test failures
      }
      this.trackedSessions.delete(id);
    }
  }
}

module.exports = TestClient;
