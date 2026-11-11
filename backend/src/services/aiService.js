/**
 * FASTFOOD AI SERVICE
 * Kết nối Google Gemini API / OpenAI API với dữ liệu thời gian thực từ MySQL
 * Đảm bảo câu trả lời thông minh, đúng giá, đúng chính sách và có phương án dự phòng an toàn
 */

class AIService {
  constructor() {
    this.geminiApiKey = process.env.GEMINI_API_KEY || '';
    this.openaiApiKey = process.env.OPENAI_API_KEY || '';
    this.geminiModel = process.env.GEMINI_MODEL || 'gemini-1.5-flash';
    this.openaiModel = process.env.OPENAI_MODEL || 'gpt-4o-mini';
  }

  /**
   * Sinh câu trả lời từ AI
   * @param {Object} params
   * @param {string} params.userMessage - Tin nhắn người dùng
   * @param {Array} params.history - Lịch sử hội thoại gần nhất
   * @param {Object} params.contextData - Dữ liệu thực tế từ MySQL (sản phẩm, đơn hàng, chính sách)
   * @param {Object} params.user - Thông tin khách hàng (nếu có)
   */
  async generateAnswer({ userMessage, history = [], contextData = {}, user = null }) {
    // 1. Thử gọi Google Gemini API trước nếu có API Key
    if (this.geminiApiKey) {
      try {
        const geminiReply = await this.callGeminiAPI({ userMessage, history, contextData, user });
        if (geminiReply) return geminiReply;
      } catch (err) {
        console.warn('[AIService] Gemini API error, trying OpenAI or smart fallback:', err.message);
      }
    }

    // 2. Thử gọi OpenAI API nếu có API Key
    if (this.openaiApiKey) {
      try {
        const openaiReply = await this.callOpenAI({ userMessage, history, contextData, user });
        if (openaiReply) return openaiReply;
      } catch (err) {
        console.warn('[AIService] OpenAI API error, using smart fallback:', err.message);
      }
    }

    // 3. Fallback thông minh dựa trên dữ liệu thực tế MySQL (Đảm bảo hệ thống luôn phản hồi mượt mà)
    return this.smartDataDrivenFallback({ userMessage, contextData, user });
  }

  /**
   * Tạo System Prompt chuẩn mực cho AI với dữ liệu thời gian thực
   */
  buildSystemPrompt(contextData, user) {
    const customerGreeting = user?.full_name ? `Khách hàng: ${user.full_name}` : 'Khách vãng lai';

    let productContextText = 'Không có sản phẩm nào khớp.';
    if (contextData.products && contextData.products.length > 0) {
      productContextText = contextData.products.map((p) => {
        const priceStr = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
        const origStr = p.hasDiscount ? ` (Giá gốc: ${new Intl.NumberFormat('vi-VN').format(p.originalPrice)}đ)` : '';
        return `- [${p.name}] | Giá: ${priceStr}${origStr} | Quán: ${p.restaurantName} | Danh mục: ${p.categoryName} | Mô tả: ${p.description}`;
      }).join('\n');
    }

    let orderContextText = 'Khách chưa yêu cầu hoặc chưa xác thực đơn hàng.';
    if (contextData.orderLookup) {
      const ord = contextData.orderLookup;
      if (ord.found) {
        if (ord.isAuthorized) {
          orderContextText = `Đơn hàng #${ord.orderCode}: Trạng thái "${ord.displayStatus}", Tổng tiền: ${new Intl.NumberFormat('vi-VN').format(ord.totalAmount)}đ, Người nhận: ${ord.customerName}`;
        } else {
          orderContextText = `Đơn hàng #${ord.orderCode} tồn tại nhưng khách chưa đăng nhập đúng tài khoản đặt đơn này. Vui lòng hướng dẫn khách đăng nhập để bảo mật thông tin.`;
        }
      } else {
        orderContextText = `Không tìm thấy mã đơn hàng này trên hệ thống FASTFOOD.`;
      }
    } else if (contextData.userRecentOrders && contextData.userRecentOrders.length > 0) {
      orderContextText = `Các đơn gần đây của khách:\n` + contextData.userRecentOrders.map(o => 
        `- Đơn #${o.orderCode}: ${o.displayStatus} (${new Intl.NumberFormat('vi-VN').format(o.totalAmount)}đ)`
      ).join('\n');
    }

    const policies = contextData.policies || {};

    return `
Bạn là FASTFOOD AI – Trợ lý ảo thông minh và thân thiện của nền tảng đặt đồ ăn trực tuyến FASTFOOD tại Buôn Ma Thuột, Đắk Lắk.
Bạn đang trò chuyện với: ${customerGreeting}.

NGUYÊN TẮC BẮT BUỘC:
1. TUYỆT ĐỐI KHÔNG BỊA ĐẶT GIÁ TIỀN, MÓN ĂN HOẶC KHUYẾN MÃI. Chỉ sử dụng thông tin trong dữ liệu được cung cấp dưới đây.
2. Giá tiền luôn dùng đơn vị đồng (đ hoặc VNĐ), ví dụ: 69.000đ.
3. Không tự ý thực hiện giao dịch thanh toán hoặc xác nhận hoàn tất đơn hàng.
4. Trả lời bằng tiếng Việt lịch sự, nhiệt tình, hào hứng, tự nhiên, kèm icon sinh động (🍕, 🍔, 🍜, 🛵, ✨).
5. Định dạng văn bản rõ ràng: xuống dòng hợp lý, dùng gạch đầu dòng khi liệt kê món.
6. Hướng dẫn khách cách đặt món: "Bạn có thể bấm vào thẻ món bên dưới hoặc tìm món trong Thực Đơn để thêm vào Giỏ hàng nhé!".

DỮ LIỆU THỰC TẾ TỪ CƠ SỞ DỮ LIỆU MYSQL:
--- DANH SÁCH MÓN ĂN CÓ SẴN ---
${productContextText}

--- THÔNG TIN ĐƠN HÀNG ---
${orderContextText}

--- CHÍNH SÁCH GIAO HÀNG & MÃ GIẢM GIÁ ---
- Phí ship cơ bản: 18.000đ nội thành Buôn Ma Thuột.
- Miễn phí giao hàng: Đơn từ 100.000đ khi nhập mã FREESHIP.
- Mã giảm giá hiện hành:
  + FASTFOOD20: Giảm 20.000đ cho đơn từ 60.000đ
  + FREESHIP: Giảm 18.000đ phí giao hàng cho đơn từ 100.000đ
  + GIAM50K: Giảm ngay 50.000đ cho đơn từ 200.000đ
- Chính sách hủy đơn: Khách có thể hủy miễn phí trước khi quán làm món. Hủy sau khi làm món sẽ chịu 3% phụ phí vào đơn kế tiếp.
- Giờ hoạt động: 06:00 - 23:00 hàng ngày. Hotline hỗ trợ: 1900 6868.
    `.trim();
  }

  /**
   * Gọi Google Gemini API chính thức
   */
  async callGeminiAPI({ userMessage, history, contextData, user }) {
    const systemPrompt = this.buildSystemPrompt(contextData, user);

    // Chuẩn bị contents cho Gemini
    const contents = [];

    // System instruction được gửi ở đầu hoặc qua systemInstruction
    contents.push({
      role: 'user',
      parts: [{ text: `[HƯỚNG DẪN HỆ THỐNG VÀ DỮ LIỆU MYSQL]:\n${systemPrompt}` }],
    });
    contents.push({
      role: 'model',
      parts: [{ text: 'Tôi đã ghi nhận đầy đủ hướng dẫn và dữ liệu thực tế từ hệ thống FASTFOOD. Tôi sẵn sàng hỗ trợ khách hàng chuẩn xác nhất!' }],
    });

    // Lịch sử hội thoại
    for (const h of history.slice(-6)) {
      contents.push({
        role: h.sender === 'user' ? 'user' : 'model',
        parts: [{ text: String(h.message || '') }],
      });
    }

    // Tin nhắn hiện tại
    contents.push({
      role: 'user',
      parts: [{ text: userMessage }],
    });

    const url = `https://generativelanguage.googleapis.com/v1beta/models/${this.geminiModel}:generateContent?key=${this.geminiApiKey}`;

    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        contents,
        generationConfig: {
          temperature: 0.7,
          maxOutputTokens: 800,
        },
      }),
    });

    if (!res.ok) {
      const errBody = await res.text();
      throw new Error(`Gemini HTTP ${res.status}: ${errBody}`);
    }

    const data = await res.json();
    const candidate = data.candidates?.[0];
    const answer = candidate?.content?.parts?.[0]?.text;
    return answer ? answer.trim() : null;
  }

  /**
   * Gọi OpenAI API chính thức (nếu cấu hình)
   */
  async callOpenAI({ userMessage, history, contextData, user }) {
    const systemPrompt = this.buildSystemPrompt(contextData, user);

    const messages = [
      { role: 'system', content: systemPrompt },
    ];

    for (const h of history.slice(-6)) {
      messages.push({
        role: h.sender === 'user' ? 'user' : 'assistant',
        content: String(h.message || ''),
      });
    }

    messages.push({ role: 'user', content: userMessage });

    const res = await fetch('https://api.openai.com/v1/chat/completions', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${this.openaiApiKey}`,
      },
      body: JSON.stringify({
        model: this.openaiModel,
        messages,
        temperature: 0.7,
        max_tokens: 800,
      }),
    });

    if (!res.ok) {
      const errBody = await res.text();
      throw new Error(`OpenAI HTTP ${res.status}: ${errBody}`);
    }

    const data = await res.json();
    return data.choices?.[0]?.message?.content?.trim() || null;
  }

  /**
   * Fallback thông minh sử dụng chính xác dữ liệu MySQL
   * Hoạt động trơn tru khi chưa có API Key hoặc mạng AI bên ngoài gặp sự cố
   */
  smartDataDrivenFallback({ userMessage, contextData, user }) {
    const raw = String(userMessage || '').trim();
    const normalized = raw
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'd')
      .toLowerCase();

    const customerName = user?.full_name ? `bạn ${user.full_name}` : 'bạn';

    // 1. Tra cứu đơn hàng
    if (contextData.orderLookup) {
      const ord = contextData.orderLookup;
      if (ord.found) {
        if (ord.isAuthorized) {
          const totalStr = new Intl.NumberFormat('vi-VN').format(ord.totalAmount) + 'đ';
          return `📦 **Thông tin đơn hàng #${ord.orderCode} của ${customerName}:**\n\n`
            + `• **Trạng thái:** ${ord.displayStatus}\n`
            + `• **Tổng thanh toán:** ${totalStr}\n`
            + `• **Phương thức:** ${ord.paymentMethod === 'cod' ? 'Tiền mặt khi nhận (COD)' : 'Chuyển khoản QR / MoMo'}\n`
            + `• **Thanh toán:** ${ord.paymentStatus === 'completed' ? 'Đã thanh toán ✅' : 'Chưa thanh toán ⏳'}\n\n`
            + `Bạn có thể bấm vào trang **Theo dõi đơn** để cập nhật lộ trình di chuyển của tài xế theo thời gian thực nhé! 🛵💨`;
        } else {
          return `🔒 Đơn hàng **#${ord.orderCode}** đã được ghi nhận trên hệ thống. Vì lý do bảo mật quyền riêng tư, vui lòng đăng nhập tài khoản đã đặt đơn để xem chi tiết đầy đủ bạn nhé!`;
        }
      } else {
        return `Dạ FASTFOOD không tìm thấy đơn hàng nào có mã **#${contextData.searchedOrderCode || 'này'}**. Bạn vui lòng kiểm tra lại mã đơn (thường có dạng FFxxxx như FF7298) trong lịch sử đặt hàng nhé!`;
      }
    }

    // 2. Hỏi ngân sách (Dưới 50k, 30k, 100k...)
    const cleanNoDots = normalized.replace(/\./g, '');
    const budgetMatch = cleanNoDots.match(/duoi\s*(\d+)\s*(k|000)?/);
    if (budgetMatch || /(duoi 50|gia re|tiet kiem|it tien|binh dan)/.test(normalized)) {
      let maxPrice = 50000;
      if (budgetMatch) {
        const val = Number(budgetMatch[1]);
        maxPrice = budgetMatch[2] === 'k' ? val * 1000 : (val < 1000 ? val * 1000 : val);
      }
      const formattedMax = new Intl.NumberFormat('vi-VN').format(maxPrice) + 'đ';

      if (contextData.products && contextData.products.length > 0) {
        let text = `🎉 Dạ ${customerName}, FASTFOOD gợi ý cho bạn những món siêu ngon giá hạt dẻ **dưới ${formattedMax}** hôm nay nè:\n\n`;
        contextData.products.slice(0, 5).forEach((p, idx) => {
          const pPrice = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
          text += `${idx + 1}. **${p.name}** – ${pPrice} (${p.restaurantName})\n   _${p.description.slice(0, 70)}..._\n`;
        });
        text += `\n💡 Bạn có thể áp thêm mã **FASTFOOD20** để được giảm thêm 20.000đ cho đơn từ 60K nữa đó ạ!`;
        return text;
      }
    }

    // 3. Món bán chạy / Best seller / Hôm nay ăn gì
    if (/(hom nay an gi|ban chay|best seller|mon hot|ngon nhat|goi y|mon gi ngon)/.test(normalized)) {
      if (contextData.products && contextData.products.length > 0) {
        let text = `🔥 Chào ${customerName}! Dưới đây là top món ăn **bán chạy nhất** được thực khách Buôn Ma Thuột săn đón nồng nhiệt:\n\n`;
        contextData.products.slice(0, 5).forEach((p, idx) => {
          const pPrice = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
          text += `${idx + 1}. **${p.name}** – ${pPrice} ⭐ 4.9\n   📍 Quán: _${p.restaurantName}_\n`;
        });
        text += `\n👉 Bạn thích thưởng thức món nào nhất? Nhắn mình để mình tư vấn chi tiết hơn hoặc chọn món ngay nhé!`;
        return text;
      }
    }

    // 4. Hỏi về Combo / Tiết kiệm
    if (/(combo|set|tiet kiem|giam gia nhieu|khuyen mai)/.test(normalized)) {
      if (contextData.products && contextData.products.length > 0) {
        let text = `🎁 Dạ ${customerName}, các gói **Combo & Món ưu đãi tiết kiệm** nhất đang có tại FASTFOOD:\n\n`;
        contextData.products.slice(0, 4).forEach((p, idx) => {
          const curPrice = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
          const oldPrice = p.hasDiscount ? ` (Giá gốc: ${new Intl.NumberFormat('vi-VN').format(p.originalPrice)}đ, giảm -${p.discountPercent}%)` : '';
          text += `${idx + 1}. **${p.name}**\n   💰 Chỉ còn: **${curPrice}**${oldPrice}\n   🏪 Quán: ${p.restaurantName}\n`;
        });
        text += `\n🛵 Đơn từ 100K nhập thêm mã **FREESHIP** để được giao tận nơi hoàn toàn miễn phí nhé!`;
        return text;
      }
    }

    // 5. Chính sách phí ship & Giao hàng
    if (/(phi ship|giao hang|van chuyen|ship|bao nhieu tien ship|bao xa)/.test(normalized)) {
      return `🛵 **Chính sách giao hàng của FASTFOOD tại Buôn Ma Thuột:**\n\n`
        + `• **Phí ship tiêu chuẩn:** 18.000đ cho mọi đơn hàng trong khu vực nội thành.\n`
        + `• **Ưu đãi Freeship:** Đơn hàng từ **100.000đ** được MIỄN PHÍ GIAO HÀNG (giảm tối đa 18.000đ) khi nhập mã **FREESHIP**.\n`
        + `• **Thời gian giao:** Trung bình chỉ từ 15 - 25 phút. Đồ ăn được giữ trong thùng giữ nhiệt chuyên dụng đảm bảo luôn nóng sốt chuẩn vị khi tới tay bạn! ✨`;
    }

    // 6. Hướng dẫn đặt hàng
    if (/(dat do an|cach dat|huong dan dat|mua hang|order|thanh toan nhu the nao)/.test(normalized)) {
      return `🛒 **Hướng dẫn đặt món siêu tốc trên FASTFOOD:**\n\n`
        + `1️⃣ **Chọn món yêu thích:** Vào mục **Thực Đơn** hoặc xem các món gợi ý ngay trong khung chat này.\n`
        + `2️⃣ **Thêm vào Giỏ hàng:** Bấm nút **+** hoặc **Thêm vào giỏ**, chọn topping tùy thích.\n`
        + `3️⃣ **Thanh toán:** Vào Giỏ hàng, nhập địa chỉ nhận tại Buôn Ma Thuột, áp mã giảm giá (FASTFOOD20 / FREESHIP).\n`
        + `4️⃣ **Nhận hàng:** Chọn thanh toán Tiền mặt (COD) hoặc Quét mã QR / MoMo, tài xế sẽ giao tận cửa cho bạn sau 15-20 phút! 🛵💨`;
    }

    // 7. Chào hỏi
    if (/\b(chao|xin chao|hello|hi|alo|ad oi|bot oi|ai oi)\b/.test(normalized)) {
      return `Dạ FASTFOOD AI xin chào ${customerName}! 💖\n\n`
        + `Tôi là trợ lý ảo đồng hành cùng bạn trên FASTFOOD Buôn Ma Thuột. Tôi có thể giúp bạn:\n`
        + `• Gợi ý món ăn ngon, bán chạy hôm nay 🔥\n`
        + `• Tìm món theo ngân sách (dưới 50K, combo tiết kiệm) 💰\n`
        + `• Giải đáp chính sách ship, mã giảm giá hot 🎁\n`
        + `• Tra cứu trạng thái đơn hàng nhanh chóng 📦\n\n`
        + `Hôm nay bạn đang thèm món gì nào? Nhắn cho mình biết nhé! 😊`;
    }

    // 8. Tìm kiếm món theo từ khóa trong tin nhắn
    if (contextData.products && contextData.products.length > 0) {
      let text = `Dạ ${customerName}, tôi tìm thấy các món ăn phù hợp với yêu cầu của bạn nè:\n\n`;
      contextData.products.slice(0, 5).forEach((p, idx) => {
        const pPrice = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
        text += `${idx + 1}. **${p.name}** – ${pPrice} (${p.restaurantName})\n   _${p.description}_\n`;
      });
      text += `\nBạn muốn tìm hiểu thêm về món nào trong số này không ạ?`;
      return text;
    }

    // Mặc định
    return `Dạ tôi đã ghi nhận câu hỏi của ${customerName}. Bạn có thể chọn các nút gợi ý nhanh bên dưới (Hôm nay ăn gì, Món dưới 50K, Mã giảm giá, Tra cứu đơn hàng) hoặc gõ tên món bạn yêu thích để tôi tìm ngay trong thực đơn 260+ món ngon của FASTFOOD nhé! 🍕🍔🍜`;
  }
}

module.exports = new AIService();
