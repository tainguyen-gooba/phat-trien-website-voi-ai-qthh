/**
 * FASTFOOD CHAT CONTROLLER
 * Hợp nhất Chat Hỗ trợ Khách hàng & Trợ lý AI thông minh:
 * - Tự động trả lời khi Admin đang bận hoặc vắng mặt
 * - Đồng bộ realtime lên Hộp thư Admin để Admin xem và trực tiếp trả lời khách hàng
 * - Tự động khởi tạo bảng MySQL + có chế độ dự phòng RAM khi MySQL chưa sẵn sàng
 */

const BaseController = require('./base.controller');
const { pool } = require('../config/db.config');
const HTTP_STATUS = require('../constants/httpStatusCodes');
const productRecommendationService = require('../services/productRecommendationService');
const aiService = require('../services/aiService');

const DEFAULT_QUICK_REPLIES = [
  'Hôm nay ăn gì?',
  'Món nào dưới 50.000đ?',
  'Có combo nào tiết kiệm không?',
  'Món nào bán chạy nhất?',
  'Kiểm tra đơn hàng của tôi',
];

const WELCOME_MESSAGE = `Xin chào bạn! Tôi là **Nhân viên hỗ trợ** của FASTFOOD Buôn Ma Thuột 💖\nTôi có thể giúp bạn chọn món ngon, tìm món dưới 50K, săn combo giảm giá, tra cứu đơn hàng hoặc kết nối trực tiếp với Admin khi cần. Hãy chọn gợi ý bên dưới hoặc nhắn tin cho mình nhé!`;

class ChatController extends BaseController {
  constructor() {
    super();
    this._tablesEnsured = false;
    this._ensureTablesPromise = null;
    this._io = null;
    // null = tự nhận theo Admin online/offline; true = Admin đang bận (AI tự rep); false = Admin đang trực (tự rep)
    this._adminManualBusy = null;
    this._memorySessions = new Map();
    this._memoryMessages = new Map();
    this._memoryMsgSeq = 1;

    this.setIo = this.setIo.bind(this);
    this.ensureChatTables = this.ensureChatTables.bind(this);
    this.sendMessage = this.sendMessage.bind(this);
    this.getHistory = this.getHistory.bind(this);
    this.newSession = this.newSession.bind(this);
    this.getSupportStatus = this.getSupportStatus.bind(this);
    this.getRecommendations = this.getRecommendations.bind(this);
    this.getAdminConversations = this.getAdminConversations.bind(this);
    this.getAdminSessionMessages = this.getAdminSessionMessages.bind(this);
    this.sendAdminReply = this.sendAdminReply.bind(this);
    this.updateAdminBusyStatus = this.updateAdminBusyStatus.bind(this);
    this.toggleSessionAutomation = this.toggleSessionAutomation.bind(this);
  }

  setIo(io) {
    this._io = io;
  }

  getIo(req) {
    return req?.app?.get('io') || this._io || null;
  }

  getAdminsOnlineCount(io) {
    if (!io?.sockets?.adapter?.rooms) return 0;
    return io.sockets.adapter.rooms.get('admins')?.size || 0;
  }

  getGlobalSupportState(io) {
    const adminsOnline = this.getAdminsOnlineCount(io);
    const effectiveBusy = this._adminManualBusy !== null
      ? Boolean(this._adminManualBusy)
      : adminsOnline === 0;

    return {
      adminsOnline,
      adminOnline: adminsOnline > 0,
      adminManualBusy: this._adminManualBusy,
      effectiveBusy,
    };
  }

  isSessionAiPaused(session) {
    if (!session?.ai_paused_until) return false;
    const t = new Date(session.ai_paused_until).getTime();
    return Number.isFinite(t) && t > Date.now();
  }

  shouldAutoReplyForSession(io, session) {
    if (this.isSessionAiPaused(session)) return false;
    if (Number(session?.ai_force_auto) === 1) return true;
    return this.getGlobalSupportState(io).effectiveBusy;
  }

  /**
   * Tự động tạo và nâng cấp bảng ai_chat_sessions & ai_chat_messages trong MySQL
   */
  async ensureChatTables() {
    if (this._tablesEnsured) return true;
    if (this._ensureTablesPromise) return this._ensureTablesPromise;

    this._ensureTablesPromise = (async () => {
      try {
        await pool.query(`
          CREATE TABLE IF NOT EXISTS ai_chat_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL UNIQUE,
            user_id BIGINT UNSIGNED NULL,
            customer_name VARCHAR(150) NULL DEFAULT 'Khách hàng',
            title VARCHAR(255) NOT NULL DEFAULT 'Hội thoại mới',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            ai_paused_until DATETIME NULL DEFAULT NULL,
            ai_force_auto TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_chat_session_user (user_id),
            INDEX idx_chat_session_updated (updated_at)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        `);

        await pool.query(`
          CREATE TABLE IF NOT EXISTS ai_chat_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            sender ENUM('user', 'assistant', 'admin', 'system') NOT NULL DEFAULT 'user',
            message TEXT NOT NULL,
            metadata LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_chat_msg_session (session_id),
            INDEX idx_chat_msg_created (created_at)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        `);

        // Nâng cấp an toàn nếu bảng đã được tạo từ phiên bản cũ
        try {
          await pool.query("ALTER TABLE ai_chat_messages MODIFY COLUMN sender ENUM('user', 'assistant', 'admin', 'system') NOT NULL DEFAULT 'user'");
        } catch (_) {}
        try {
          await pool.query('ALTER TABLE ai_chat_sessions ADD COLUMN ai_paused_until DATETIME NULL DEFAULT NULL');
        } catch (_) {}
        try {
          await pool.query('ALTER TABLE ai_chat_sessions ADD COLUMN ai_force_auto TINYINT(1) NOT NULL DEFAULT 0');
        } catch (_) {}

        this._tablesEnsured = true;
        return true;
      } catch (err) {
        console.warn('[ChatController] Không thể tự khởi tạo bảng chat trong MySQL, dùng dự phòng RAM:', err.message);
        return false;
      } finally {
        this._ensureTablesPromise = null;
      }
    })();

    return this._ensureTablesPromise;
  }

  sanitizeText(input) {
    if (typeof input !== 'string') return '';
    return input.trim().replace(/[<>]/g, '').slice(0, 1000);
  }

  generateSessionId() {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let result = 'ff_chat_';
    for (let i = 0; i < 24; i++) {
      result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
  }

  parseMetadata(rawMeta) {
    if (!rawMeta) return null;
    if (typeof rawMeta === 'object') return rawMeta;
    try {
      return JSON.parse(rawMeta);
    } catch (_) {
      return null;
    }
  }

  formatMessageRow(row) {
    return {
      id: row.id,
      sessionId: row.session_id || row.sessionId,
      sender: row.sender,
      message: row.message,
      metadata: this.parseMetadata(row.metadata),
      createdAt: row.created_at || row.createdAt || new Date().toISOString(),
    };
  }

  async ensureSessionAccessAndHistory({ sessionId, userId, customerName, firstTitle }) {
    const dbReady = await this.ensureChatTables();

    if (dbReady) {
      try {
        const [existingSessions] = await pool.query(
          'SELECT * FROM ai_chat_sessions WHERE session_id = ? LIMIT 1',
          [sessionId]
        );

        let sessionRecord;
        if (existingSessions.length === 0) {
          await pool.query(
            'INSERT INTO ai_chat_sessions (session_id, user_id, customer_name, title) VALUES (?, ?, ?, ?)',
            [sessionId, userId, customerName, firstTitle]
          );
          const [createdRows] = await pool.query(
            'SELECT * FROM ai_chat_sessions WHERE session_id = ? LIMIT 1',
            [sessionId]
          );
          sessionRecord = createdRows[0] || {
            session_id: sessionId,
            user_id: userId,
            customer_name: customerName,
            title: firstTitle,
            ai_paused_until: null,
            ai_force_auto: 0,
          };
        } else {
          sessionRecord = existingSessions[0];
          if (sessionRecord.user_id && userId && Number(sessionRecord.user_id) !== userId) {
            return { forbidden: true, historyRows: [], session: null, usingDb: true };
          }
          if (!sessionRecord.user_id && userId) {
            await pool.query(
              'UPDATE ai_chat_sessions SET user_id = ?, customer_name = ? WHERE session_id = ?',
              [userId, customerName, sessionId]
            );
            sessionRecord.user_id = userId;
            sessionRecord.customer_name = customerName;
          } else if (customerName && customerName !== 'Khách hàng' && sessionRecord.customer_name !== customerName) {
            await pool.query(
              'UPDATE ai_chat_sessions SET customer_name = ? WHERE session_id = ?',
              [customerName, sessionId]
            );
            sessionRecord.customer_name = customerName;
          }
        }

        const [historyRows] = await pool.query(
          'SELECT sender, message FROM ai_chat_messages WHERE session_id = ? ORDER BY id DESC LIMIT 8',
          [sessionId]
        );
        return {
          forbidden: false,
          historyRows: historyRows.reverse(),
          session: sessionRecord,
          usingDb: true,
        };
      } catch (err) {
        console.warn('[ChatController] MySQL session query fallback:', err.message);
      }
    }

    let memSession = this._memorySessions.get(sessionId);
    if (!memSession) {
      memSession = {
        session_id: sessionId,
        user_id: userId,
        customer_name: customerName,
        title: firstTitle,
        ai_paused_until: null,
        ai_force_auto: 0,
        updated_at: new Date().toISOString(),
      };
      this._memorySessions.set(sessionId, memSession);
    } else {
      if (memSession.user_id && userId && Number(memSession.user_id) !== userId) {
        return { forbidden: true, historyRows: [], session: null, usingDb: false };
      }
      if (!memSession.user_id && userId) {
        memSession.user_id = userId;
        memSession.customer_name = customerName;
      }
    }

    const memMessages = this._memoryMessages.get(sessionId) || [];
    return {
      forbidden: false,
      historyRows: memMessages.slice(-8).map(m => ({ sender: m.sender, message: m.message })),
      session: memSession,
      usingDb: false,
    };
  }

  async saveSingleMessage({ sessionId, userId, sender, message, metadataObj = null, updateTitleIfFirst = false, usingDb = true }) {
    const metaStr = metadataObj ? JSON.stringify(metadataObj) : null;
    const nowIso = new Date().toISOString();

    if (usingDb) {
      try {
        const [result] = await pool.query(
          'INSERT INTO ai_chat_messages (session_id, user_id, sender, message, metadata) VALUES (?, ?, ?, ?, ?)',
          [sessionId, userId, sender, message, metaStr]
        );
        if (updateTitleIfFirst && sender === 'user') {
          await pool.query(
            'UPDATE ai_chat_sessions SET title = ?, updated_at = CURRENT_TIMESTAMP WHERE session_id = ?',
            [message.slice(0, 60), sessionId]
          );
        } else {
          await pool.query(
            'UPDATE ai_chat_sessions SET updated_at = CURRENT_TIMESTAMP WHERE session_id = ?',
            [sessionId]
          );
        }
        return {
          id: result.insertId,
          sessionId,
          sender,
          message,
          metadata: metadataObj,
          createdAt: nowIso,
        };
      } catch (err) {
        console.warn('[ChatController] Không thể ghi tin nhắn vào MySQL, lưu tạm trên RAM:', err.message);
      }
    }

    const list = this._memoryMessages.get(sessionId) || [];
    const saved = {
      id: this._memoryMsgSeq++,
      session_id: sessionId,
      sessionId,
      sender,
      message,
      metadata: metaStr,
      created_at: nowIso,
      createdAt: nowIso,
    };
    list.push(saved);
    this._memoryMessages.set(sessionId, list.slice(-100));

    const memSession = this._memorySessions.get(sessionId);
    if (memSession) {
      memSession.updated_at = nowIso;
      if (updateTitleIfFirst && sender === 'user') {
        memSession.title = message.slice(0, 60);
      }
    }

    return {
      id: saved.id,
      sessionId,
      sender,
      message,
      metadata: metadataObj,
      createdAt: nowIso,
    };
  }

  async buildContextData(message, userId) {
    const normalizedMsg = message
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[đĐ]/g, 'd')
      .toLowerCase();

    const contextData = {
      products: [],
      orderLookup: null,
      userRecentOrders: [],
      policies: productRecommendationService.getSystemPolicies(),
      searchedOrderCode: null,
    };

    const orderCodeMatch = message.match(/#?(FF[\w\d-]{4,15})/i);
    if (orderCodeMatch) {
      const searchedCode = orderCodeMatch[1].toUpperCase();
      contextData.searchedOrderCode = searchedCode;
      contextData.orderLookup = await productRecommendationService.lookupOrder(searchedCode, userId);
      return contextData;
    }

    if (/(don hang cua toi|kiem tra don|xem don hang|don vua dat|tinh trang don)/.test(normalizedMsg)) {
      if (userId) {
        contextData.userRecentOrders = await productRecommendationService.getUserRecentOrders(userId, 3);
        if (contextData.userRecentOrders.length > 0) {
          const latestCode = contextData.userRecentOrders[0].orderCode;
          contextData.orderLookup = await productRecommendationService.lookupOrder(latestCode, userId);
        } else {
          contextData.orderLookup = { found: false, orderCode: '' };
        }
      } else {
        contextData.orderLookup = {
          found: true,
          isAuthorized: false,
          orderCode: 'CHƯA_ĐĂNG_NHẬP',
          displayStatus: 'Yêu cầu đăng nhập',
        };
      }
      return contextData;
    }

    if (/(phi ship|bao nhieu tien ship|ma giam gia|voucher|coupon|code giam|gio mo cua|may gio|hotline|lien he|huy don|dat do an|cach dat|huong dan dat)/.test(normalizedMsg)) {
      return contextData;
    }

    if (/(combo|set|giam gia nhieu|khuyen mai|uu dai|flash deal)/.test(normalizedMsg)) {
      contextData.products = await productRecommendationService.getCombosAndDeals(6);
      return contextData;
    }

    const cleanNoDots = normalizedMsg.replace(/\./g, '');
    const budgetMatch = cleanNoDots.match(/duoi\s*(\d+)\s*(k|000)?/);
    if (budgetMatch || /(duoi 50|gia re|tiet kiem|it tien|binh dan)/.test(normalizedMsg)) {
      let maxVal = 50000;
      if (budgetMatch) {
        const num = Number(budgetMatch[1]);
        maxVal = budgetMatch[2] === 'k' ? num * 1000 : (num < 1000 ? num * 1000 : num);
      }
      contextData.products = await productRecommendationService.getProductsUnderBudget(maxVal, 6);
      return contextData;
    }

    if (/(hom nay an gi|ban chay|best seller|mon hot|ngon nhat|goi y|mon gi ngon)/.test(normalizedMsg)) {
      contextData.products = await productRecommendationService.getBestSellers(6);
      return contextData;
    }

    if (/^(chao|xin chao|hello|hi|alo|ad oi|bot oi|ai oi|shop oi)[!.?\s]*$/.test(normalizedMsg)) {
      return contextData;
    }

    const keywordRegex = /(com tam|ga ran|tra sua|mi y|banh mi|khoai tay|nuoc ngot|an vat|hai san|do uong|ca phe|burger|pizza|com|pho|bun|sushi|lau|salad|healthy|matcha|ga|tra|bo)/g;
    const searchMatches = normalizedMsg.match(keywordRegex);
    const searchQuery = searchMatches ? searchMatches.join(' ') : message.replace(/[?!.,]/g, '').trim();

    contextData.products = await productRecommendationService.searchProducts(searchQuery, 6);
    if (contextData.products.length === 0) {
      contextData.products = await productRecommendationService.getBestSellers(4);
    }

    return contextData;
  }

  /**
   * Gửi tin nhắn từ khách hàng:
   * - Luôn lưu và đẩy realtime lên Hộp thư Admin để Admin thấy & tự trả lời
   * - Chỉ AI tự động phản hồi khi Admin đang bận / vắng mặt (hoặc Admin bật tự động cho phiên này)
   * POST /api/v1/chat/message
   */
  async sendMessage(req, res) {
    try {
      const rawMessage = req.body?.message;
      let sessionId = String(req.body?.sessionId || '').trim();

      if (!rawMessage || typeof rawMessage !== 'string' || !rawMessage.trim()) {
        return this.sendError(res, 'Nội dung tin nhắn không được để trống.', HTTP_STATUS.BAD_REQUEST);
      }

      const message = this.sanitizeText(rawMessage);
      if (message.length < 1) {
        return this.sendError(res, 'Nội dung tin nhắn không hợp lệ.', HTTP_STATUS.BAD_REQUEST);
      }

      if (!sessionId) {
        sessionId = this.generateSessionId();
      }

      const user = req.user || null;
      const userId = user?.id ? Number(user.id) : null;
      const customerName = user?.full_name || String(req.body?.customerName || '').trim() || 'Khách hàng';
      const io = this.getIo(req);

      const sessionState = await this.ensureSessionAccessAndHistory({
        sessionId,
        userId,
        customerName,
        firstTitle: message.slice(0, 60),
      });

      if (sessionState.forbidden) {
        return this.sendError(res, 'Bạn không có quyền truy cập phiên trò chuyện này.', HTTP_STATUS.FORBIDDEN);
      }

      const isFirstMessage = sessionState.historyRows.length === 0;
      const savedUserMsg = await this.saveSingleMessage({
        sessionId,
        userId,
        sender: 'user',
        message,
        metadataObj: { customerName },
        updateTitleIfFirst: isFirstMessage,
        usingDb: sessionState.usingDb,
      });

      // Thông báo realtime cho tất cả Admin đang mở Dashboard
      if (io) {
        io.to('admins').emit('support:new_message', {
          sessionId,
          customerName,
          userId,
          message: savedUserMsg,
        });
        io.to('admins').emit('notification:new', {
          title: 'Tin nhắn khách hàng mới',
          content: `${customerName}: ${message.slice(0, 90)}`,
          type: 'chat',
          sessionId,
        });
      }

      const supportState = this.getGlobalSupportState(io);
      const shouldAutoReply = this.shouldAutoReplyForSession(io, sessionState.session);

      if (!shouldAutoReply) {
        return this.sendSuccess(
          res,
          'Tin nhắn của bạn đã được gửi.',
          {
            autoReplied: false,
            pendingAdminReply: true,
            adminBusy: false,
            adminOnline: supportState.adminOnline,
            userMessageId: savedUserMsg.id,
            reply: null,
            sessionId,
            products: [],
            order: null,
            quickReplies: DEFAULT_QUICK_REPLIES,
          },
          HTTP_STATUS.OK
        );
      }

      // Admin đang bận / vắng mặt -> Trợ lý tự động phản hồi thay
      const contextData = await this.buildContextData(message, userId);
      const answer = await aiService.generateAnswer({
        userMessage: message,
        history: sessionState.historyRows,
        contextData,
        user,
      });

      const metadataObj = {
        autoReplyReason: supportState.adminOnline ? 'admin_busy_or_auto' : 'admin_offline',
        products: contextData.products.slice(0, 4).map(p => ({
          id: p.id,
          name: p.name,
          price: p.price,
          originalPrice: p.originalPrice,
          discountPercent: p.discountPercent,
          restaurantName: p.restaurantName,
          image: p.image,
          imageCredit: p.imageCredit,
        })),
        order: contextData.orderLookup?.found ? contextData.orderLookup : null,
      };

      const savedAssistantMsg = await this.saveSingleMessage({
        sessionId,
        userId,
        sender: 'assistant',
        message: answer,
        metadataObj,
        usingDb: sessionState.usingDb,
      });

      if (io) {
        io.to('admins').emit('support:new_message', {
          sessionId,
          customerName,
          userId,
          message: savedAssistantMsg,
        });
      }

      return this.sendSuccess(
        res,
        'Nhân viên hỗ trợ tự động phản hồi thành công',
        {
          autoReplied: true,
          pendingAdminReply: false,
          adminBusy: true,
          adminOnline: supportState.adminOnline,
          messageId: savedAssistantMsg.id,
          reply: answer,
          sessionId,
          products: contextData.products.slice(0, 6),
          order: contextData.orderLookup?.isAuthorized ? contextData.orderLookup : null,
          quickReplies: DEFAULT_QUICK_REPLIES,
        },
        HTTP_STATUS.OK
      );
    } catch (err) {
      console.error('[ChatController] sendMessage error:', err);
      return this.sendError(
        res,
        'Dạ Nhân viên hỗ trợ đang bận một chút, bạn thử lại sau giây lát nhé!',
        HTTP_STATUS.INTERNAL_SERVER_ERROR
      );
    }
  }

  /**
   * Lấy lịch sử cuộc trò chuyện (kèm trạng thái Admin online/busy để đồng bộ UI khách)
   * GET /api/v1/chat/history?sessionId=...
   */
  async getHistory(req, res) {
    try {
      const sessionId = String(req.query.sessionId || '').trim();
      const user = req.user || null;
      const userId = user?.id ? Number(user.id) : null;
      const io = this.getIo(req);
      const supportState = this.getGlobalSupportState(io);

      if (!sessionId) {
        return this.sendSuccess(res, 'Chưa có phiên trò chuyện', {
          messages: [],
          supportStatus: supportState,
        });
      }

      const dbReady = await this.ensureChatTables();

      if (dbReady) {
        try {
          const [sessionRows] = await pool.query(
            'SELECT * FROM ai_chat_sessions WHERE session_id = ? LIMIT 1',
            [sessionId]
          );

          const session = sessionRows[0] || null;
          if (session && session.user_id && userId && Number(session.user_id) !== userId) {
            return this.sendError(res, 'Không có quyền xem lịch sử phiên này', HTTP_STATUS.FORBIDDEN);
          }

          const [messages] = await pool.query(
            'SELECT id, session_id, sender, message, metadata, created_at FROM ai_chat_messages WHERE session_id = ? ORDER BY id ASC LIMIT 80',
            [sessionId]
          );

          return this.sendSuccess(res, 'Tải lịch sử chat thành công', {
            messages: messages.map(m => this.formatMessageRow(m)),
            supportStatus: {
              ...supportState,
              aiPaused: this.isSessionAiPaused(session),
            },
          });
        } catch (err) {
          console.warn('[ChatController] getHistory DB fallback:', err.message);
        }
      }

      const memSession = this._memorySessions.get(sessionId) || null;
      if (memSession && memSession.user_id && userId && Number(memSession.user_id) !== userId) {
        return this.sendError(res, 'Không có quyền xem lịch sử phiên này', HTTP_STATUS.FORBIDDEN);
      }

      const memMessages = (this._memoryMessages.get(sessionId) || []).map(m => this.formatMessageRow(m));
      return this.sendSuccess(res, 'Tải lịch sử chat thành công', {
        messages: memMessages,
        supportStatus: {
          ...supportState,
          aiPaused: this.isSessionAiPaused(memSession),
        },
      });
    } catch (err) {
      console.error('[ChatController] getHistory error:', err);
      return this.sendError(res, 'Không thể tải lịch sử trò chuyện', HTTP_STATUS.INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Trạng thái nhân viên hỗ trợ hiện tại (bận/trực tuyến)
   * GET /api/v1/chat/status?sessionId=...
   */
  async getSupportStatus(req, res) {
    const io = this.getIo(req);
    const sessionId = String(req.query.sessionId || '').trim();
    const state = this.getGlobalSupportState(io);
    let aiPaused = false;

    if (sessionId) {
      const dbReady = await this.ensureChatTables();
      if (dbReady) {
        try {
          const [rows] = await pool.query('SELECT ai_paused_until FROM ai_chat_sessions WHERE session_id = ? LIMIT 1', [sessionId]);
          aiPaused = this.isSessionAiPaused(rows[0]);
        } catch (_) {}
      } else {
        aiPaused = this.isSessionAiPaused(this._memorySessions.get(sessionId));
      }
    }

    return this.sendSuccess(res, 'Lấy trạng thái hỗ trợ thành công', {
      ...state,
      aiPaused,
    });
  }

  /**
   * Bắt đầu phiên trò chuyện mới
   * POST /api/v1/chat/new-session
   */
  async newSession(req, res) {
    try {
      const newSessionId = this.generateSessionId();
      const user = req.user || null;
      const userId = user?.id ? Number(user.id) : null;
      const customerName = user?.full_name || 'Khách hàng';
      const io = this.getIo(req);

      let savedToDb = false;
      const dbReady = await this.ensureChatTables();

      if (dbReady) {
        try {
          await pool.query(
            'INSERT INTO ai_chat_sessions (session_id, user_id, customer_name, title) VALUES (?, ?, ?, ?)',
            [newSessionId, userId, customerName, 'Cuộc trò chuyện mới']
          );
          savedToDb = true;
        } catch (err) {
          console.warn('[ChatController] newSession DB fallback:', err.message);
        }
      }

      if (!savedToDb) {
        this._memorySessions.set(newSessionId, {
          session_id: newSessionId,
          user_id: userId,
          customer_name: customerName,
          title: 'Cuộc trò chuyện mới',
          ai_paused_until: null,
          ai_force_auto: 0,
          updated_at: new Date().toISOString(),
        });
      }

      return this.sendCreated(res, 'Tạo phiên trò chuyện mới thành công', {
        sessionId: newSessionId,
        welcomeMessage: WELCOME_MESSAGE,
        quickReplies: DEFAULT_QUICK_REPLIES,
        supportStatus: this.getGlobalSupportState(io),
      });
    } catch (err) {
      console.error('[ChatController] newSession error:', err);
      return this.sendError(res, 'Không thể tạo phiên mới', HTTP_STATUS.INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * [ADMIN] Danh sách toàn bộ cuộc trò chuyện từ khung Nhân viên hỗ trợ
   * GET /api/v1/chat/admin/conversations
   */
  async getAdminConversations(req, res) {
    try {
      const io = this.getIo(req);
      const supportStatus = this.getGlobalSupportState(io);
      const dbReady = await this.ensureChatTables();

      if (dbReady) {
        try {
          const [rows] = await pool.query(`
            SELECT s.session_id AS sessionId,
                   s.user_id AS userId,
                   COALESCE(u.full_name, s.customer_name, 'Khách hàng') AS customerName,
                   u.email AS email,
                   s.title AS title,
                   s.ai_paused_until AS aiPausedUntil,
                   s.ai_force_auto AS aiForceAuto,
                   s.updated_at AS updatedAt,
                   lm.message AS lastMessage,
                   lm.sender AS lastSender,
                   lm.created_at AS lastMessageAt
            FROM ai_chat_sessions s
            LEFT JOIN users u ON u.id = s.user_id
            LEFT JOIN ai_chat_messages lm ON lm.id = (
              SELECT MAX(m2.id) FROM ai_chat_messages m2 WHERE m2.session_id = s.session_id
            )
            WHERE lm.id IS NOT NULL
            ORDER BY COALESCE(lm.created_at, s.updated_at) DESC
            LIMIT 100
          `);

          return this.sendSuccess(res, 'Tải hộp thư hỗ trợ thành công', {
            supportStatus,
            conversations: rows.map(r => ({
              ...r,
              aiForceAuto: Boolean(r.aiForceAuto),
            })),
          });
        } catch (err) {
          console.warn('[ChatController] getAdminConversations DB fallback:', err.message);
        }
      }

      const memConversations = Array.from(this._memorySessions.values())
        .map(s => {
          const msgs = this._memoryMessages.get(s.session_id) || [];
          const last = msgs[msgs.length - 1];
          if (!last) return null;
          return {
            sessionId: s.session_id,
            userId: s.user_id,
            customerName: s.customer_name || 'Khách hàng',
            email: s.user_id ? `ID khách: ${s.user_id}` : 'Khách truy cập trực tiếp',
            title: s.title,
            aiPausedUntil: s.ai_paused_until,
            aiForceAuto: Boolean(s.ai_force_auto),
            updatedAt: s.updated_at,
            lastMessage: last.message,
            lastSender: last.sender,
            lastMessageAt: last.createdAt || last.created_at,
          };
        })
        .filter(Boolean)
        .sort((a, b) => new Date(b.lastMessageAt) - new Date(a.lastMessageAt));

      return this.sendSuccess(res, 'Tải hộp thư hỗ trợ thành công', {
        supportStatus,
        conversations: memConversations,
      });
    } catch (err) {
      console.error('[ChatController] getAdminConversations error:', err);
      return this.sendError(res, 'Không thể tải danh sách hội thoại', HTTP_STATUS.INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * [ADMIN] Lấy tin nhắn chi tiết của 1 phiên hỗ trợ
   * GET /api/v1/chat/admin/sessions/:sessionId/messages
   */
  async getAdminSessionMessages(req, res) {
    try {
      const sessionId = String(req.params.sessionId || '').trim();
      if (!sessionId) {
        return this.sendError(res, 'Thiếu mã phiên hội thoại.', HTTP_STATUS.BAD_REQUEST);
      }

      const dbReady = await this.ensureChatTables();
      if (dbReady) {
        try {
          const [messages] = await pool.query(
            'SELECT id, session_id, sender, message, metadata, created_at FROM ai_chat_messages WHERE session_id = ? ORDER BY id ASC LIMIT 150',
            [sessionId]
          );
          return this.sendSuccess(res, 'Tải lịch sử tin nhắn thành công', messages.map(m => this.formatMessageRow(m)));
        } catch (err) {
          console.warn('[ChatController] getAdminSessionMessages DB fallback:', err.message);
        }
      }

      const memMessages = (this._memoryMessages.get(sessionId) || []).map(m => this.formatMessageRow(m));
      return this.sendSuccess(res, 'Tải lịch sử tin nhắn thành công', memMessages);
    } catch (err) {
      console.error('[ChatController] getAdminSessionMessages error:', err);
      return this.sendError(res, 'Không thể tải tin nhắn hội thoại', HTTP_STATUS.INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * [ADMIN] Admin trực tiếp trả lời khách hàng vào chatbox "Nhân viên hỗ trợ"
   * POST /api/v1/chat/admin/sessions/:sessionId/reply
   */
  async sendAdminReply(req, res) {
    try {
      const sessionId = String(req.params.sessionId || '').trim();
      const rawMessage = req.body?.message;
      const message = this.sanitizeText(rawMessage);

      if (!sessionId || !message) {
        return this.sendError(res, 'Nội dung tin nhắn và mã phiên không được để trống.', HTTP_STATUS.BAD_REQUEST);
      }

      const adminUser = req.user || {};
      const adminName = adminUser.full_name || 'Nhân viên hỗ trợ';
      const io = this.getIo(req);
      const dbReady = await this.ensureChatTables();
      let sessionUserId = null;
      let usingDb = false;

      if (dbReady) {
        try {
          const [rows] = await pool.query(
            'SELECT user_id FROM ai_chat_sessions WHERE session_id = ? LIMIT 1',
            [sessionId]
          );
          if (rows.length === 0) {
            return this.sendError(res, 'Không tìm thấy phiên hội thoại.', HTTP_STATUS.NOT_FOUND);
          }
          sessionUserId = rows[0].user_id;
          // Admin trực tiếp trả lời -> tạm dừng AI tự động cho khách này 15 phút để Admin tiếp quản mượt
          await pool.query(
            'UPDATE ai_chat_sessions SET ai_paused_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE), ai_force_auto = 0, updated_at = CURRENT_TIMESTAMP WHERE session_id = ?',
            [sessionId]
          );
          usingDb = true;
        } catch (err) {
          console.warn('[ChatController] sendAdminReply DB fallback:', err.message);
        }
      }

      if (!usingDb) {
        const memSession = this._memorySessions.get(sessionId);
        if (!memSession) {
          return this.sendError(res, 'Không tìm thấy phiên hội thoại.', HTTP_STATUS.NOT_FOUND);
        }
        sessionUserId = memSession.user_id || null;
        memSession.ai_paused_until = new Date(Date.now() + 15 * 60 * 1000).toISOString();
        memSession.ai_force_auto = 0;
      }

      const savedMsg = await this.saveSingleMessage({
        sessionId,
        userId: sessionUserId,
        sender: 'admin',
        message,
        metadataObj: {
          adminId: adminUser.id || null,
          adminName,
        },
        usingDb,
      });

      if (io) {
        const eventPayload = {
          sessionId,
          userId: sessionUserId,
          message: savedMsg,
        };
        io.to(`support:${sessionId}`).emit('support:new_message', eventPayload);
        if (sessionUserId) {
          io.to(`user:${sessionUserId}`).emit('support:new_message', eventPayload);
          io.to(`user:${sessionUserId}`).emit('notification:new', {
            title: 'Nhân viên hỗ trợ đã phản hồi',
            content: message.slice(0, 90),
            type: 'chat',
            sessionId,
          });
        }
        io.to('admins').emit('support:new_message', eventPayload);
        io.to(`support:${sessionId}`).emit('support:status', {
          ...this.getGlobalSupportState(io),
          aiPaused: true,
        });
      }

      return this.sendSuccess(res, 'Đã gửi phản hồi trực tiếp cho khách hàng.', {
        message: savedMsg,
        aiPausedUntil: new Date(Date.now() + 15 * 60 * 1000).toISOString(),
      });
    } catch (err) {
      console.error('[ChatController] sendAdminReply error:', err);
      return this.sendError(res, 'Không thể gửi tin nhắn phản hồi', HTTP_STATUS.INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * [ADMIN] Đổi trạng thái tổng: Đang bận (AI tự động rep) vs Đang trực (Admin tự rep)
   * PATCH /api/v1/chat/admin/status
   */
  async updateAdminBusyStatus(req, res) {
    const busy = req.body?.busy;
    if (busy !== true && busy !== false && busy !== null) {
      return this.sendError(res, 'Trạng thái không hợp lệ (busy: true | false | null).', HTTP_STATUS.BAD_REQUEST);
    }

    this._adminManualBusy = busy;
    const io = this.getIo(req);
    const supportStatus = this.getGlobalSupportState(io);

    if (io) {
      io.emit('support:status', supportStatus);
    }

    const msg = supportStatus.effectiveBusy
      ? 'Đã bật chế độ Admin Đang Bận: Hệ thống sẽ tự động phản hồi khách hàng.'
      : 'Đã bật chế độ Admin Đang Trực: Tin nhắn khách sẽ chuyển thẳng để bạn tự trả lời.';

    return this.sendSuccess(res, msg, supportStatus);
  }

  /**
   * [ADMIN] Bật / Tạm dừng tự động trả lời cho từng khách hàng cụ thể
   * PATCH /api/v1/chat/admin/sessions/:sessionId/automation
   */
  async toggleSessionAutomation(req, res) {
    try {
      const sessionId = String(req.params.sessionId || '').trim();
      const { enabled, pauseMinutes = 30 } = req.body || {};

      if (!sessionId) {
        return this.sendError(res, 'Thiếu mã phiên hội thoại.', HTTP_STATUS.BAD_REQUEST);
      }

      const minutes = Math.max(5, Math.min(1440, Number(pauseMinutes) || 30));
      const pausedUntilIso = enabled ? null : new Date(Date.now() + minutes * 60 * 1000).toISOString();
      const dbReady = await this.ensureChatTables();

      if (dbReady) {
        try {
          if (enabled) {
            await pool.query(
              'UPDATE ai_chat_sessions SET ai_paused_until = NULL, ai_force_auto = 1 WHERE session_id = ?',
              [sessionId]
            );
          } else {
            await pool.query(
              'UPDATE ai_chat_sessions SET ai_paused_until = DATE_ADD(NOW(), INTERVAL ? MINUTE), ai_force_auto = 0 WHERE session_id = ?',
              [minutes, sessionId]
            );
          }
        } catch (err) {
          console.warn('[ChatController] toggleSessionAutomation DB fallback:', err.message);
        }
      }

      const memSession = this._memorySessions.get(sessionId);
      if (memSession) {
        memSession.ai_paused_until = pausedUntilIso;
        memSession.ai_force_auto = enabled ? 1 : 0;
      }

      const io = this.getIo(req);
      if (io) {
        io.to(`support:${sessionId}`).emit('support:status', {
          ...this.getGlobalSupportState(io),
          aiPaused: !enabled,
        });
      }

      return this.sendSuccess(
        res,
        enabled ? 'Đã bật trả lời tự động cho khách hàng này.' : `Đã tạm dừng tự động trong ${minutes} phút để Admin tiếp nhận.`,
        { enabled: Boolean(enabled), aiPausedUntil: pausedUntilIso }
      );
    } catch (err) {
      console.error('[ChatController] toggleSessionAutomation error:', err);
      return this.sendError(res, 'Không thể thay đổi chế độ tự động', HTTP_STATUS.INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Lấy danh sách gợi ý nhanh ban đầu
   * GET /api/v1/chat/recommendations
   */
  async getRecommendations(req, res) {
    try {
      const bestSellers = await productRecommendationService.getBestSellers(4);
      const under50k = await productRecommendationService.getProductsUnderBudget(50000, 4);
      const combos = await productRecommendationService.getCombosAndDeals(4);

      return this.sendSuccess(res, 'Lấy gợi ý món ăn thành công', {
        bestSellers,
        under50k,
        combos,
      });
    } catch (err) {
      console.error('[ChatController] getRecommendations error:', err);
      return this.sendError(res, 'Không thể lấy gợi ý món ăn', HTTP_STATUS.INTERNAL_SERVER_ERROR);
    }
  }
}

module.exports = new ChatController();
