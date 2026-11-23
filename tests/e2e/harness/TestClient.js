/**
 * TestClient.js - Multi-persona authenticated HTTP client for FastFood E2E Tests.
 * Supports Customer (FASTFOOD_SESSID), Staff (FF_SESS_ADMIN), Admin (FF_SESS_ADMIN), and Guest contexts.
 * Uses native Node.js 24 fetch, FormData, and Blob. Zero npm dependencies.
 */

class TestClient {
  /**
   * @param {string} [baseUrl] - Base server URL (defaults to TEST_BASE_URL env or http://127.0.0.1:5000)
   */
  constructor(baseUrl) {
    this.baseUrl = (baseUrl || process.env.TEST_BASE_URL || 'http://127.0.0.1:5000').replace(/\/+$/, '');
    this.currentPersona = 'guest';
    this.customerCookie = null;
    this.adminCookie = null;
    this.jwtToken = null;
    this.currentUser = null;
    this.trackedSessions = new Set();
  }

  // =========================================================================
  // Authentication & Persona Management
  // =========================================================================

  /**
   * Switches to unauthenticated guest state, clearing all cookies and tokens.
   *
   * @returns {this}
   */
  asGuest() {
    this.currentPersona = 'guest';
    this.customerCookie = null;
    this.adminCookie = null;
    this.jwtToken = null;
    this.currentUser = null;
    return this;
  }

  /**
   * Authenticates as a Customer and stores FASTFOOD_SESSID session cookie.
   *
   * @param {string} [email='customer@fastfood.vn']
   * @param {string} [password='123456']
   * @returns {Promise<{ success: boolean, cookie: string, user: object, token: string }>}
   */
  async loginAsCustomer(email = 'customer@fastfood.vn', password = '123456') {
    this.asGuest();

    // 1. Authenticate via api/auth.php to acquire token and user details
    const authUrl = `${this.baseUrl}/api/auth.php`;
    try {
      const res = await fetch(authUrl, {
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
      try { json = JSON.parse(text); } catch (_) {}

      // Capture FASTFOOD_SESSID cookie
      const setCookie = res.headers.get('set-cookie');
      if (setCookie) {
        const match = setCookie.match(/FASTFOOD_SESSID=([^;]+)/i);
        if (match) {
          this.customerCookie = `FASTFOOD_SESSID=${match[1]}`;
        }
      }

      if (res.ok && json.success) {
        this.jwtToken = json.data?.token || null;
        this.currentUser = json.data?.user || null;
        this.currentPersona = 'customer';
        return {
          success: true,
          cookie: this.customerCookie,
          user: this.currentUser,
          token: this.jwtToken
        };
      }
    } catch (_) {}

    // 2. Web form fallback to /login.php
    const formParams = new URLSearchParams({ email, password });
    const res = await fetch(`${this.baseUrl}/login.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formParams.toString(),
      redirect: 'manual'
    });

    const setCookie = res.headers.get('set-cookie');
    if (setCookie) {
      const match = setCookie.match(/FASTFOOD_SESSID=([^;]+)/i);
      if (match) {
        this.customerCookie = `FASTFOOD_SESSID=${match[1]}`;
      }
    }

    this.currentPersona = 'customer';
    return {
      success: !!this.customerCookie,
      cookie: this.customerCookie,
      user: null,
      token: null
    };
  }

  /**
   * Authenticates as Staff (role 5) and stores FF_SESS_ADMIN session cookie.
   *
   * @param {string} [email='staff1@fastfood.vn']
   * @param {string} [password='123456']
   * @returns {Promise<{ success: boolean, cookie: string, user: object, token: string }>}
   */
  async loginAsStaff(email = 'staff1@fastfood.vn', password = '123456') {
    this.asGuest();

    const authUrl = `${this.baseUrl}/api/auth.php`;
    try {
      const res = await fetch(authUrl, {
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
      try { json = JSON.parse(text); } catch (_) {}

      const setCookie = res.headers.get('set-cookie');
      if (setCookie) {
        const match = setCookie.match(/FF_SESS_ADMIN=([^;]+)/i);
        if (match) {
          this.adminCookie = `FF_SESS_ADMIN=${match[1]}`;
        }
      }

      if (res.ok && json.success) {
        this.jwtToken = json.data?.token || null;
        this.currentUser = json.data?.user || null;
        this.currentPersona = 'staff';
        return {
          success: true,
          cookie: this.adminCookie,
          user: this.currentUser,
          token: this.jwtToken
        };
      }
    } catch (_) {}

    // Fallback to /admin/login.php
    const formParams = new URLSearchParams({ email, password });
    const res = await fetch(`${this.baseUrl}/admin/login.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formParams.toString(),
      redirect: 'manual'
    });

    const setCookie = res.headers.get('set-cookie');
    if (setCookie) {
      const match = setCookie.match(/FF_SESS_ADMIN=([^;]+)/i);
      if (match) {
        this.adminCookie = `FF_SESS_ADMIN=${match[1]}`;
      }
    }

    this.currentPersona = 'staff';
    return {
      success: !!this.adminCookie,
      cookie: this.adminCookie,
      user: null,
      token: null
    };
  }

  /**
   * Authenticates as Admin (role 1) and stores FF_SESS_ADMIN session cookie and JWT.
   *
   * @param {string} [email='admin@fastfood.vn']
   * @param {string} [password='123456']
   * @returns {Promise<{ success: boolean, cookie: string, user: object, token: string }>}
   */
  async loginAsAdmin(email = 'admin@fastfood.vn', password = '123456') {
    this.asGuest();

    const authUrl = `${this.baseUrl}/api/auth.php`;
    const res = await fetch(authUrl, {
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
    try { json = JSON.parse(text); } catch (_) {}

    const setCookie = res.headers.get('set-cookie');
    if (setCookie) {
      const match = setCookie.match(/FF_SESS_ADMIN=([^;]+)/i);
      if (match) {
        this.adminCookie = `FF_SESS_ADMIN=${match[1]}`;
      } else {
        this.adminCookie = setCookie.split(';')[0].trim();
      }
    }

    if (!res.ok || !json.success) {
      // Fallback to admin/login.php
      const formParams = new URLSearchParams({ email, password });
      const webRes = await fetch(`${this.baseUrl}/admin/login.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formParams.toString(),
        redirect: 'manual'
      });
      const webCookie = webRes.headers.get('set-cookie');
      if (webCookie) {
        const match = webCookie.match(/FF_SESS_ADMIN=([^;]+)/i);
        if (match) this.adminCookie = `FF_SESS_ADMIN=${match[1]}`;
      }
    } else {
      this.jwtToken = json.data?.token || null;
      this.currentUser = json.data?.user || null;
    }

    this.currentPersona = 'admin';
    return {
      success: !!this.adminCookie,
      cookie: this.adminCookie,
      user: this.currentUser,
      token: this.jwtToken
    };
  }

  /**
   * Ensures the client has an active admin session.
   */
  async ensureAdminAuth() {
    if (!this.adminCookie) {
      await this.loginAsAdmin();
    }
  }

  // =========================================================================
  // HTTP Request Infrastructure
  // =========================================================================

  /**
   * Builds request headers according to persona or explicit context.
   *
   * @param {object} [customHeaders={}]
   * @param {'customer'|'admin'|'guest'|null} [context=null]
   * @returns {Headers}
   */
  _buildHeaders(customHeaders = {}, context = null) {
    const headers = new Headers();
    const effectivePersona = context || this.currentPersona;

    if (effectivePersona === 'admin' || effectivePersona === 'staff') {
      if (this.adminCookie) {
        headers.set('Cookie', this.adminCookie);
      }
      if (this.jwtToken) {
        headers.set('Authorization', `Bearer ${this.jwtToken}`);
      }
      headers.set('Referer', `${this.baseUrl}/admin/index.php`);
      headers.set('X-Requested-Role', 'admin');
    } else if (effectivePersona === 'customer') {
      if (this.customerCookie) {
        headers.set('Cookie', this.customerCookie);
      }
      headers.set('Referer', `${this.baseUrl}/index.php`);
    }

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
   * Normalizes response object and keeps cookies synchronized.
   */
  async _normalizeResponse(res) {
    const text = await res.text();
    let json = null;
    try {
      json = JSON.parse(text);
    } catch {
      json = null;
    }

    const setCookie = res.headers.get('set-cookie');
    if (setCookie) {
      const sessMatch = setCookie.match(/FASTFOOD_SESSID=([^;]+)/i);
      if (sessMatch) {
        this.customerCookie = `FASTFOOD_SESSID=${sessMatch[1]}`;
      }
      const adminMatch = setCookie.match(/FF_SESS_ADMIN=([^;]+)/i);
      if (adminMatch) {
        this.adminCookie = `FF_SESS_ADMIN=${adminMatch[1]}`;
      }
    }

    const location = res.headers.get('location') || '';
    let orderCode = null;
    if (location) {
      const match = location.match(/[?&]id=([^&]+)/i);
      if (match) orderCode = decodeURIComponent(match[1]);
    }

    return {
      status: res.status,
      ok: res.ok,
      headers: res.headers,
      text,
      body: json !== null ? json : text,
      data: json?.data !== undefined ? json.data : null,
      orderCode,
      location,
      json: async () => json,
      raw: res
    };
  }

  /**
   * Performs an HTTP GET request.
   *
   * @param {string} path
   * @param {object} [headers={}]
   * @param {'customer'|'admin'|'guest'|null} [context=null]
   */
  async get(path, headers = {}, context = null) {
    const url = path.startsWith('http') ? path : `${this.baseUrl}${path.startsWith('/') ? '' : '/'}${path}`;
    const reqHeaders = this._buildHeaders(headers, context);
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
   * @param {any} [body={}]
   * @param {object} [headers={}]
   * @param {'customer'|'admin'|'guest'|null} [context=null]
   */
  async post(path, body = {}, headers = {}, context = null) {
    const url = path.startsWith('http') ? path : `${this.baseUrl}${path.startsWith('/') ? '' : '/'}${path}`;
    const reqHeaders = this._buildHeaders(headers, context);

    let serializedBody = body;
    if (typeof body === 'object' && !(body instanceof FormData) && !(body instanceof Blob) && !(body instanceof ArrayBuffer) && !(body instanceof URLSearchParams)) {
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
   * @param {object} [headers={}]
   * @param {'customer'|'admin'|'guest'|null} [context=null]
   */
  async postMultipart(path, formData, headers = {}, context = null) {
    const url = path.startsWith('http') ? path : `${this.baseUrl}${path.startsWith('/') ? '' : '/'}${path}`;
    const reqHeaders = this._buildHeaders(headers, context);
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
   * @param {object} [headers={}]
   * @param {'customer'|'admin'|'guest'|null} [context=null]
   */
  async delete(path, headers = {}, context = null) {
    const url = path.startsWith('http') ? path : `${this.baseUrl}${path.startsWith('/') ? '' : '/'}${path}`;
    const reqHeaders = this._buildHeaders(headers, context);
    const res = await fetch(url, {
      method: 'DELETE',
      headers: reqHeaders
    });
    return this._normalizeResponse(res);
  }

  // =========================================================================
  // Customer Actions
  // =========================================================================

  /**
   * Loads customer profile page (GET /profile.php).
   *
   * @param {object} [headers={}]
   */
  async getProfile(headers = {}) {
    return this.get('/profile.php', headers, 'customer');
  }

  /**
   * Updates customer profile personal details (POST /profile.php).
   *
   * @param {{ fullName?: string, phone?: string, address?: string }} data
   * @param {object} [headers={}]
   */
  async updateProfile(data = {}, headers = {}) {
    const params = new URLSearchParams();
    for (const [k, v] of Object.entries(data)) {
      params.append(k, v !== null && v !== undefined ? String(v) : '');
    }

    const reqHeaders = this._buildHeaders({
      'Content-Type': 'application/x-www-form-urlencoded',
      ...headers
    }, 'customer');

    const res = await fetch(`${this.baseUrl}/profile.php`, {
      method: 'POST',
      headers: reqHeaders,
      body: params.toString()
    });
    return this._normalizeResponse(res);
  }

  /**
   * Exchanges points for discount voucher (POST /api/loyalty.php).
   *
   * @param {number} packageId - Voucher package ID (1: 10k, 2: 20k, 3: 50k)
   * @param {object} [headers={}]
   */
  async exchangePointsForVoucher(packageId, headers = {}) {
    return this.post('/api/loyalty.php', {
      action: 'exchange_voucher',
      package_id: packageId
    }, headers, 'customer');
  }

  /**
   * Fetches customer loyalty info and benefits summary (GET /api/loyalty.php?action=get_loyalty_info).
   *
   * @param {object} [headers={}]
   */
  async getLoyaltyInfo(headers = {}) {
    return this.get('/api/loyalty.php?action=get_loyalty_info', headers, 'customer');
  }

  /**
   * Adds an item to the session cart (POST /api/cart.php).
   *
   * @param {number|string} productId
   * @param {number} [qty=1]
   * @param {object} [headers={}]
   */
  async addToCart(productId, qty = 1, headers = {}) {
    return this.post('/api/cart.php', {
      action: 'add',
      productId: String(productId),
      qty: Number(qty)
    }, headers, 'customer');
  }

  /**
   * Applies a coupon or exchanged voucher to session cart (POST /api/cart.php).
   *
   * @param {string} code - Coupon or voucher code
   * @param {object} [headers={}]
   */
  async applyCoupon(code, headers = {}) {
    return this.post('/api/cart.php', {
      action: 'apply_coupon',
      code: String(code)
    }, headers, 'customer');
  }

  /**
   * Submits an order on checkout.php with optional points direct redemption.
   *
   * @param {object} [orderPayload={}]
   * @param {object} [headers={}]
   */
  async checkoutOrder(orderPayload = {}, headers = {}) {
    const payload = {
      customer_name: 'Trần Khách Hàng',
      customer_phone: '0903000003',
      customer_address: '123 Đường Số 1, Quận 1, TP.HCM',
      customer_notes: 'Giao nhanh trước 12h',
      paymentMethod: 'cod',
      points_to_use: 0,
      ...orderPayload
    };

    const formParams = new URLSearchParams();
    for (const [k, v] of Object.entries(payload)) {
      formParams.append(k, v !== null && v !== undefined ? String(v) : '');
    }

    const reqHeaders = this._buildHeaders({
      'Content-Type': 'application/x-www-form-urlencoded',
      ...headers
    }, 'customer');

    const res = await fetch(`${this.baseUrl}/checkout.php`, {
      method: 'POST',
      headers: reqHeaders,
      body: formParams.toString(),
      redirect: 'manual'
    });

    return this._normalizeResponse(res);
  }

  /**
   * Cancels a pending order via order-tracking.php.
   *
   * @param {string} orderCode
   * @param {object} [headers={}]
   */
  async cancelOrder(orderCode, headers = {}) {
    const code = encodeURIComponent(orderCode);
    const url = `${this.baseUrl}/order-tracking.php?id=${code}`;
    const reqHeaders = this._buildHeaders({
      'Content-Type': 'application/x-www-form-urlencoded',
      ...headers
    }, 'customer');

    const res = await fetch(url, {
      method: 'POST',
      headers: reqHeaders,
      body: 'action_cancel=1',
      redirect: 'manual'
    });

    return this._normalizeResponse(res);
  }

  // =========================================================================
  // Admin Actions
  // =========================================================================

  /**
   * Retrieves paginated list of members with loyalty tiers and points (GET /admin/api.php?action=get_loyalty_members).
   *
   * @param {object} [params={}]
   * @param {object} [headers={}]
   */
  async adminGetMembers(params = {}, headers = {}) {
    const query = new URLSearchParams({ action: 'get_loyalty_members', ...params });
    return this.get(`/admin/api.php?${query.toString()}`, headers, 'admin');
  }

  /**
   * Retrieves points transaction ledger for a specific user (GET /admin/api.php?action=get_member_loyalty_history).
   *
   * @param {number|string} userId
   * @param {object} [headers={}]
   */
  async adminGetMemberHistory(userId, headers = {}) {
    const query = new URLSearchParams({ action: 'get_member_loyalty_history', user_id: String(userId) });
    return this.get(`/admin/api.php?${query.toString()}`, headers, 'admin');
  }

  /**
   * Updates tier definitions, spending thresholds, and loyalty rates (POST /admin/api.php?action=save_loyalty_config).
   *
   * @param {object} [config={}]
   * @param {object} [headers={}]
   */
  async adminSaveLoyaltyConfig(config = {}, headers = {}) {
    const body = { action: 'save_loyalty_config', ...config };
    return this.post('/admin/api.php?action=save_loyalty_config', body, headers, 'admin');
  }

  /**
   * Updates order status (e.g. 'completed', 'cancelled') to trigger points hooks (POST /admin/api.php?action=update_order_status).
   *
   * @param {string} orderCode
   * @param {string} status - 'completed', 'cancelled', 'preparing', etc.
   * @param {object} [headers={}]
   */
  async adminUpdateOrderStatus(orderCode, status, headers = {}) {
    const body = { action: 'update_order_status', orderCode, status };
    return this.post('/admin/api.php?action=update_order_status', body, headers, 'admin');
  }

  // =========================================================================
  // Legacy Chat Helpers (Backward Compatibility)
  // =========================================================================

  generateSessionId(prefix = 'e2e_sess') {
    const rand = Math.random().toString(36).slice(2, 8);
    const id = `${prefix}_${Date.now()}_${rand}`;
    this.trackedSessions.add(id);
    return id;
  }

  createSession(customId = null) {
    const id = customId || this.generateSessionId();
    this.trackedSessions.add(id);
    return id;
  }

  async sendCustomerMessage(sessionId, message, options = {}) {
    this.trackedSessions.add(sessionId);
    return this.post('/api/v1/chat/message', {
      sessionId,
      message,
      customerName: options.customerName || 'E2E Test Customer',
      ...options
    }, {}, 'customer');
  }

  async sendAdminReply(sessionId, replyMessage, options = {}) {
    await this.ensureAdminAuth();
    this.trackedSessions.add(sessionId);
    return this.post(`/api/v1/chat/admin/sessions/${sessionId}/reply`, {
      message: replyMessage,
      ...options
    }, {}, 'admin');
  }

  async markSessionAsRead(sessionId) {
    await this.ensureAdminAuth();
    return this.post(`/api/v1/chat/admin/sessions/${sessionId}/read`, {}, {}, 'admin');
  }

  async uploadChatImage(buffer, filename = 'test.jpg', mimeType = 'image/jpeg') {
    const formData = new FormData();
    const blob = new Blob([buffer], { type: mimeType });
    formData.append('image', blob, filename);
    return this.postMultipart('/api/v1/chat/upload-image', formData, {}, 'admin');
  }

  async deleteSession(sessionId) {
    await this.ensureAdminAuth();
    const res = await this.delete(`/api/v1/chat/admin/sessions/${sessionId}`, {}, 'admin');
    this.trackedSessions.delete(sessionId);
    return res;
  }

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

  async cleanupAllSessions() {
    if (this.trackedSessions.size === 0) return;
    await this.ensureAdminAuth();
    const sessionIds = Array.from(this.trackedSessions);
    for (const id of sessionIds) {
      try {
        await this.delete(`/api/v1/chat/admin/sessions/${id}`, {}, 'admin');
      } catch (_) {}
      this.trackedSessions.delete(id);
    }
  }
}

module.exports = TestClient;
