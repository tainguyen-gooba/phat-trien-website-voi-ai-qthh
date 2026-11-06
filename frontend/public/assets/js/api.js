/**
 * FASTFOOD API Client
 * Xử lý fetch request, tự động đính kèm JWT Bearer token và chuẩn hóa phản hồi
 */
class ApiClient {
  constructor(baseUrl) {
    this.baseUrl = baseUrl || window.CONFIG?.API_BASE_URL || 'http://localhost:5000/api/v1';
  }

  /**
   * Tạo headers chuẩn kèm Authorization nếu có token
   */
  getHeaders(customHeaders = {}) {
    const headers = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...customHeaders,
    };

    const token = window.Utils?.storage.get(window.CONFIG?.STORAGE_KEYS.AUTH_TOKEN);
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }

    return headers;
  }

  /**
   * Core request wrapper
   */
  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;
    const config = {
      ...options,
      headers: this.getHeaders(options.headers),
    };

    try {
      const response = await fetch(url, config);
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || `Lỗi HTTP: ${response.status}`);
      }

      return data;
    } catch (error) {
      console.error(`[API Request Error] ${options.method || 'GET'} ${url}:`, error.message);
      throw error;
    }
  }

  get(endpoint, headers = {}) {
    return this.request(endpoint, { method: 'GET', headers });
  }

  post(endpoint, body = {}, headers = {}) {
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(body),
      headers,
    });
  }

  put(endpoint, body = {}, headers = {}) {
    return this.request(endpoint, {
      method: 'PUT',
      body: JSON.stringify(body),
      headers,
    });
  }

  delete(endpoint, headers = {}) {
    return this.request(endpoint, { method: 'DELETE', headers });
  }

  // Tiện ích kiểm tra sức khỏe hệ thống
  checkHealth() {
    return this.get('/health');
  }

  checkDatabaseHealth() {
    return this.get('/health/db');
  }
}

window.apiClient = new ApiClient();

