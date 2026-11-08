/**
 * FASTFOOD Main App Initialization & System Diagnostics
 */
document.addEventListener('DOMContentLoaded', async () => {
  console.log('🍔 FASTFOOD Platform Web Client Initialized');

  const serverStatusBadge = document.getElementById('server-status-badge');
  const dbStatusBadge = document.getElementById('db-status-badge');
  const pingBtn = document.getElementById('btn-ping-api');
  const systemDetailsBox = document.getElementById('system-details');

  async function checkSystemHealth() {
    if (serverStatusBadge) {
      serverStatusBadge.innerHTML = '<span class="status-dot"></span> Đang kiểm tra...';
      serverStatusBadge.className = 'badge badge-warning';
    }

    try {
      const result = await window.apiClient.checkHealth();
      const dbInfo = result.data?.database || {};
      const dbConnected = Boolean(dbInfo.databaseConnected);

      // 1. Cập nhật Server Badge: Chắc chắn Online vì API đã trả lời
      if (serverStatusBadge) {
        serverStatusBadge.innerHTML = '<span class="status-dot online"></span> Server Online (Cổng 5000)';
        serverStatusBadge.className = 'badge badge-success';
      }

      // 2. Cập nhật Database Badge
      if (dbStatusBadge) {
        if (dbConnected) {
          dbStatusBadge.innerHTML = `<span class="status-dot online"></span> MySQL Sẵn sàng (${dbInfo.database})`;
          dbStatusBadge.className = 'badge badge-success';
        } else {
          dbStatusBadge.innerHTML = '<span class="status-dot offline"></span> MySQL Chưa kết nối';
          dbStatusBadge.className = 'badge badge-danger';
        }
      }

      // 3. Hiển thị chi tiết chẩn đoán
      if (systemDetailsBox) {
        let dbStatusHtml = '';

        if (dbConnected) {
          dbStatusHtml = `
            <div style="background:var(--success-bg); border: 1px solid rgba(16,185,129,0.2); padding:1rem; border-radius:var(--radius-md); margin-top:1rem; display:flex; align-items:center; gap:0.75rem;">
              <span style="font-size:1.5rem;">✅</span>
              <div>
                <strong style="color:var(--success);">Cơ sở dữ liệu MySQL đã kết nối thành công!</strong>
                <p style="font-size:0.875rem; color:var(--text-secondary); margin-top:0.25rem;">
                  Đã tải <b>${dbInfo.totalTables || 16} bảng</b> dữ liệu chuẩn 3NF. Độ trễ truy vấn: <b>${dbInfo.latencyMs || 1}ms</b>.
                </p>
              </div>
            </div>
          `;
        } else {
          dbStatusHtml = `
            <div style="background:var(--warning-bg); border: 1px solid rgba(245,158,11,0.3); padding:1.25rem; border-radius:var(--radius-md); margin-top:1rem;">
              <div style="display:flex; align-items:flex-start; gap:0.75rem;">
                <span style="font-size:1.5rem;">⚠️</span>
                <div style="flex:1;">
                  <strong style="color:var(--warning); font-size:1rem;">Máy chủ API đang chạy, nhưng chưa kết nối được MySQL (${dbInfo.errorCode || 'ECONNREFUSED'})</strong>
                  <p style="font-size:0.875rem; color:var(--text-secondary); margin:0.35rem 0 0.75rem;">
                    ${dbInfo.hint || 'Hãy bật dịch vụ MySQL trên máy tính của bạn.'}
                  </p>
                  
                  <div style="background:rgba(255,255,255,0.7); padding:0.75rem 1rem; border-radius:var(--radius-sm); font-size:0.85rem; border-left:3px solid var(--warning);">
                    <b>Các bước kích hoạt MySQL:</b>
                    <ol style="margin: 0.35rem 0 0 1.25rem; line-height: 1.6;">
                      <li>Mở <b>XAMPP Control Panel</b> (hoặc Laragon / MySQL Service) và bấm <b>Start</b> ở mục <b>MySQL</b>.</li>
                      <li>Mở Terminal tại thư mục dự án và chạy lệnh: <code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-weight:bold;">npm run db:init</code> để tự động tạo database và 16 bảng.</li>
                      <li>Bấm lại nút <b>"Kiểm Tra Kết Nối Server & MySQL"</b> ở trên.</li>
                    </ol>
                  </div>
                </div>
              </div>
            </div>
          `;
        }

        systemDetailsBox.innerHTML = `
          <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
            <div style="background:var(--bg-body); padding:1rem; border-radius:var(--radius-md);">
              <small style="color:var(--text-muted); display:block;">Phiên bản Node.js</small>
              <strong>${result.data?.system?.nodeVersion || 'N/A'}</strong>
            </div>
            <div style="background:var(--bg-body); padding:1rem; border-radius:var(--radius-md);">
              <small style="color:var(--text-muted); display:block;">Thời gian Server chạy</small>
              <strong>${result.data?.uptimeSeconds || 0} giây</strong>
            </div>
            <div style="background:var(--bg-body); padding:1rem; border-radius:var(--radius-md);">
              <small style="color:var(--text-muted); display:block;">Trạng thái CSDL</small>
              <strong style="color: ${dbConnected ? 'var(--success)' : 'var(--danger)'}">
                ${dbConnected ? `Đã kết nối (v${dbInfo.version})` : 'Chưa bật MySQL'}
              </strong>
            </div>
            <div style="background:var(--bg-body); padding:1rem; border-radius:var(--radius-md);">
              <small style="color:var(--text-muted); display:block;">Số bảng MySQL</small>
              <strong>${dbConnected ? `${dbInfo.totalTables} bảng` : '0 bảng'}</strong>
            </div>
          </div>
          ${dbStatusHtml}
        `;
      }

      if (dbConnected) {
        window.Utils.showToast('Hệ thống và MySQL đã sẵn sàng!', 'success');
      } else {
        window.Utils.showToast('Server API đã Online, hãy bật MySQL để hoàn tất!', 'info');
      }
    } catch (error) {
      // Khi server node thực sự tắt
      if (serverStatusBadge) {
        serverStatusBadge.innerHTML = '<span class="status-dot offline"></span> Server Mất kết nối';
        serverStatusBadge.className = 'badge badge-danger';
      }
      if (dbStatusBadge) {
        dbStatusBadge.innerHTML = '<span class="status-dot offline"></span> MySQL Offline';
        dbStatusBadge.className = 'badge badge-danger';
      }
      if (systemDetailsBox) {
        systemDetailsBox.innerHTML = `
          <div style="padding:1.25rem; background:var(--danger-bg); color:var(--danger); border-radius:var(--radius-md); margin-top:1rem;">
            <strong>Không thể kết nối đến Backend:</strong> ${error.message}
            <div style="font-size:0.875rem; margin-top:0.5rem; color:var(--text-secondary);">
              Hãy chắc chắn rằng backend server đang chạy bằng lệnh: <code>npm run dev</code> trên cổng 5000.
            </div>
          </div>
        `;
      }
      window.Utils.showToast('Không thể kết nối tới Backend Server', 'error');
    }
  }

  if (pingBtn) {
    pingBtn.addEventListener('click', checkSystemHealth);
  }

  // Tự động kiểm tra sau 300ms
  setTimeout(checkSystemHealth, 300);
});
