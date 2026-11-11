/**
 * FASTFOOD CHAT CONTROLLER
 * Xử lý tiếp nhận câu hỏi từ khách hàng, điều phối dữ liệu MySQL,
 * tương tác với AI Service và lưu trữ lịch sử trò chuyện
 */

const BaseController = require('./base.controller');
const { pool } = require('../config/db.config');
const HTTP_STATUS = require('../constants/httpStatusCodes');
const productRecommendationService = require('../services/productRecommendationService');
const aiService = require('../services/aiService');

class ChatController extends BaseController {
  constructor() {
    super();
    this.sendMessage = this.sendMessage.bind(this);
    this.getHistory = this.getHistory.bind(this);
    this.newSession = this.newSession.bind(this);
    this.getRecommendations = this.getRecommendations.bind(this);
  }

  /**
   * Làm sạch và phòng chống XSS cơ bản cho tin nhắn
   */
  sanitizeText(input) {
    if (typeof input !== 'string') return '';
    return input
      .trim()
      .replace(/[<>]/g, '') // Loại bỏ thẻ HTML nguy hiểm
      .slice(0, 1000); // Giới hạn tối đa 1000 ký tự
  }

  /**
   * Tạo session ID ngẫu nhiên nếu client chưa có
   */
  generateSessionId() {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let result = 'ff_chat_';
    for (let i = 0; i < 24; i++) {
      result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
  }

  /**
   * Gửi tin nhắn và nhận phản hồi từ AI
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
      const customerName = user?.full_name || 'Khách hàng';

      // 1. Quản lý phiên hội thoại trong MySQL (ai_chat_sessions)
      const [existingSessions] = await pool.query(
        'SELECT * FROM ai_chat_sessions WHERE session_id = ? LIMIT 1',
        [sessionId]
      );

      if (existingSessions.length === 0) {
        await pool.query(
          'INSERT INTO ai_chat_sessions (session_id, user_id, customer_name, title) VALUES (?, ?, ?, ?)',
          [sessionId, userId, customerName, message.slice(0, 60)]
        );
      } else {
        const sessionRecord = existingSessions[0];
        // Bảo vệ quyền riêng tư: Nếu phiên này của user khác, không cho can thiệp
        if (sessionRecord.user_id && userId && Number(sessionRecord.user_id) !== userId) {
          return this.sendError(res, 'Bạn không có quyền truy cập phiên trò chuyện này.', HTTP_STATUS.FORBIDDEN);
        }
        // Cập nhật user_id nếu trước đó là khách vãng lai sau đó đăng nhập
        if (!sessionRecord.user_id && userId) {
          await pool.query('UPDATE ai_chat_sessions SET user_id = ?, customer_name = ? WHERE session_id = ?', [userId, customerName, sessionId]);
        }
      }

      // 2. Lấy lịch sử hội thoại gần nhất (tối đa 8 tin nhắn) để AI hiểu ngữ cảnh
      const [historyRows] = await pool.query(
        'SELECT sender, message FROM ai_chat_messages WHERE session_id = ? ORDER BY id ASC LIMIT 8',
        [sessionId]
      );

      // 3. Phân tích nội dung và truy vấn dữ liệu thực tế từ MySQL
      const normalizedMsg = message
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .toLowerCase();

      const contextData = {
        products: [],
        orderLookup: null,
        userRecentOrders: [],
        policies: productRecommendationService.getSystemPolicies(),
        searchedOrderCode: null,
      };

      // 3.1. Nhận diện tra cứu mã đơn hàng (ví dụ: FF7298, FF-xxxx, hoặc hỏi đơn của tôi)
      const orderCodeMatch = message.match(/#?(FF[\w\d-]{4,15})/i);
      if (orderCodeMatch) {
        const searchedCode = orderCodeMatch[1].toUpperCase();
        contextData.searchedOrderCode = searchedCode;
        contextData.orderLookup = await productRecommendationService.lookupOrder(searchedCode, userId);
      } else if (/(don hang cua toi|kiem tra don|xem don hang|don vua dat)/.test(normalizedMsg)) {
        if (userId) {
          contextData.userRecentOrders = await productRecommendationService.getUserRecentOrders(userId, 3);
          if (contextData.userRecentOrders.length > 0) {
            // Tra cứu sâu vào đơn gần nhất
            const latestCode = contextData.userRecentOrders[0].orderCode;
            contextData.orderLookup = await productRecommendationService.lookupOrder(latestCode, userId);
          }
        } else {
          contextData.orderLookup = {
            found: true,
            isAuthorized: false,
            orderCode: 'CHƯA_ĐĂNG_NHẬP',
            displayStatus: 'Yêu cầu đăng nhập',
          };
        }
      }

      // 3.2. Nhận diện ngân sách (dưới 50k, 30k, 70k...)
      const cleanNoDots = normalizedMsg.replace(/\./g, '');
      const budgetMatch = cleanNoDots.match(/duoi\s*(\d+)\s*(k|000)?/);
      if (budgetMatch || /(duoi 50|gia re|tiet kiem|it tien)/.test(normalizedMsg)) {
        let maxVal = 50000;
        if (budgetMatch) {
          const num = Number(budgetMatch[1]);
          maxVal = budgetMatch[2] === 'k' ? num * 1000 : (num < 1000 ? num * 1000 : num);
        }
        contextData.products = await productRecommendationService.getProductsUnderBudget(maxVal, 6);
      }
      // 3.3. Nhận diện combo / khuyến mãi
      else if (/(combo|set|giam gia nhieu|khuyen mai|uu dai)/.test(normalizedMsg)) {
        contextData.products = await productRecommendationService.getCombosAndDeals(6);
      }
      // 3.4. Nhận diện món bán chạy / hôm nay ăn gì
      else if (/(hom nay an gi|ban chay|best seller|mon hot|ngon nhat|goi y)/.test(normalizedMsg)) {
        contextData.products = await productRecommendationService.getBestSellers(6);
      }
      // 3.5. Tìm kiếm từ khóa món cụ thể (burger, pizza, com, tra sua, mi y, pho...)
      else {
        // Tách các từ khóa tìm kiếm
        const searchMatches = normalizedMsg.match(/(burger|pizza|com|ga ran|tra sua|mi y|pho|sushi|banh mi|khoai tay|lau|nuoc ngot)/g);
        const searchQuery = searchMatches ? searchMatches[0] : message.replace(/[?!.,]/g, '').trim();
        contextData.products = await productRecommendationService.searchProducts(searchQuery, 6);
        // Nếu không có sản phẩm nào khớp từ khóa cụ thể, lấy top best seller để làm phong phú câu trả lời
        if (contextData.products.length === 0) {
          contextData.products = await productRecommendationService.getBestSellers(4);
        }
      }

      // 4. Sinh câu trả lời thông qua AI Service
      const answer = await aiService.generateAnswer({
        userMessage: message,
        history: historyRows,
        contextData,
        user,
      });

      // 5. Lưu tin nhắn người dùng và phản hồi của AI vào MySQL (ai_chat_messages)
      await pool.query(
        'INSERT INTO ai_chat_messages (session_id, user_id, sender, message, metadata) VALUES (?, ?, ?, ?, ?)',
        [sessionId, userId, 'user', message, null]
      );

      const metadataToSave = JSON.stringify({
        products: contextData.products.slice(0, 4).map(p => ({ id: p.id, name: p.name, price: p.price, image: p.image })),
        order: contextData.orderLookup?.found ? { code: contextData.orderLookup.orderCode, status: contextData.orderLookup.displayStatus } : null,
      });

      await pool.query(
        'INSERT INTO ai_chat_messages (session_id, user_id, sender, message, metadata) VALUES (?, ?, ?, ?, ?)',
        [sessionId, userId, 'assistant', answer, metadataToSave]
      );

      // Cập nhật tiêu đề session nếu là tin nhắn đầu
      if (historyRows.length === 0) {
        await pool.query(
          'UPDATE ai_chat_sessions SET title = ? WHERE session_id = ?',
          [message.slice(0, 50), sessionId]
        );
      }

      // 6. Trả phản hồi về Frontend
      return this.sendSuccess(
        res,
        'Phản hồi AI thành công',
        {
          reply: answer,
          sessionId,
          products: contextData.products.slice(0, 6),
          order: contextData.orderLookup?.isAuthorized ? contextData.orderLookup : null,
          quickReplies: [
            'Hôm nay ăn gì?',
            'Món nào dưới 50.000đ?',
            'Có combo nào tiết kiệm không?',
            'Món nào bán chạy nhất?',
            'Kiểm tra đơn hàng của tôi',
          ],
        },
        HTTP_STATUS.OK
      );
    } catch (err) {
      console.error('[ChatController] sendMessage error:', err);
      return this.sendError(
        res,
        'Dạ FASTFOOD AI đang bận một chút, bạn thử lại sau giây lát nhé!',
        HTTP_STATUS.INTERNAL_SERVER_ERROR
      );
    }
  }

  /**
   * Lấy lịch sử cuộc trò chuyện
   * GET /api/v1/chat/history?sessionId=...
   */
  async getHistory(req, res) {
    try {
      const sessionId = String(req.query.sessionId || '').trim();
      const user = req.user || null;
      const userId = user?.id ? Number(user.id) : null;

      if (!sessionId) {
        return this.sendSuccess(res, 'Chưa có phiên trò chuyện', { messages: [] });
      }

      // Kiểm tra bảo mật phiên
      const [sessionRows] = await pool.query(
        'SELECT * FROM ai_chat_sessions WHERE session_id = ? LIMIT 1',
        [sessionId]
      );

      if (sessionRows.length > 0) {
        const s = sessionRows[0];
        if (s.user_id && userId && Number(s.user_id) !== userId) {
          return this.sendError(res, 'Không có quyền xem lịch sử phiên này', HTTP_STATUS.FORBIDDEN);
        }
      }

      const [messages] = await pool.query(
        'SELECT id, sender, message, metadata, created_at FROM ai_chat_messages WHERE session_id = ? ORDER BY id ASC LIMIT 50',
        [sessionId]
      );

      const formatted = messages.map(m => {
        let parsedMeta = null;
        try {
          if (m.metadata) parsedMeta = JSON.parse(m.metadata);
        } catch (_) {}
        return {
          id: m.id,
          sender: m.sender,
          message: m.message,
          metadata: parsedMeta,
          createdAt: m.created_at,
        };
      });

      return this.sendSuccess(res, 'Tải lịch sử chat thành công', { messages: formatted });
    } catch (err) {
      console.error('[ChatController] getHistory error:', err);
      return this.sendError(res, 'Không thể tải lịch sử trò chuyện', HTTP_STATUS.INTERNAL_SERVER_ERROR);
    }
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

      await pool.query(
        'INSERT INTO ai_chat_sessions (session_id, user_id, customer_name, title) VALUES (?, ?, ?, ?)',
        [newSessionId, userId, customerName, 'Cuộc trò chuyện mới']
      );

      return this.sendCreated(
        res,
        'Tạo phiên trò chuyện mới thành công',
        {
          sessionId: newSessionId,
          welcomeMessage: `Dạ FASTFOOD AI xin chào bạn! 💖\nTôi có thể giúp bạn chọn món ngon, tìm món dưới 50K, săn combo giảm giá hoặc tra cứu đơn hàng. Hãy chọn gợi ý bên dưới hoặc hỏi tôi bất cứ điều gì nhé!`,
          quickReplies: [
            'Hôm nay ăn gì?',
            'Món nào dưới 50.000đ?',
            'Có combo nào tiết kiệm không?',
            'Món nào bán chạy nhất?',
            'Kiểm tra đơn hàng của tôi',
          ],
        }
      );
    } catch (err) {
      console.error('[ChatController] newSession error:', err);
      return this.sendError(res, 'Không thể tạo phiên mới', HTTP_STATUS.INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Lấy danh sách gợi ý nhanh ban đầu từ MySQL
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
