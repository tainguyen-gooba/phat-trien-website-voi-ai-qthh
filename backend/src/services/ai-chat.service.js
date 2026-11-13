/**
 * FASTFOOD AI CHATBOT SERVICE FOR ADMIN & CUSTOMER SUPPORT
 * Cung cấp khả năng phân tích ngôn ngữ tự nhiên và trợ lý AI cho Admin
 */

class AIChatService {
  /**
   * Phân tích tin nhắn của khách hàng và gợi ý câu trả lời chuẩn mực cho Admin
   */
  generateReplySuggestion({ customerMessage, customerName = 'Quý khách' }) {
    const raw = String(customerMessage || '').trim();
    const normalized = raw
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'd')
      .toLowerCase();

    const name = customerName || 'bạn';

    // 1. Ý định: Khiếu nại giao trễ / thiếu món / đồ ăn nguội
    if (/(tre|lau qua|chua toi|chua den|nguoi|thieu mon|sai mon|do|hu|hong|do an do)/.test(normalized)) {
      return {
        intent: 'complaint',
        intentLabel: 'Khiếu nại / Giao trễ',
        suggestion: `Dạ chào bạn ${name}, FASTFOOD chân thành xin lỗi bạn vì sự bất tiện này! Mình đã ghi nhận thông tin và đang trực tiếp liên hệ tài xế/quán ăn để kiểm tra khẩn cấp và xử lý thỏa đáng cho bạn ngay ạ.`,
        quickReplies: [
          `FASTFOOD xin lỗi bạn ${name}. Đơn hàng đang được đẩy nhanh tiến độ, tài xế sẽ đến trong 5-7 phút nữa ạ.`,
          `Dạ ${name} ơi, FASTFOOD xin phép tặng bạn mã giảm giá bù đắp và gửi lại món còn thiếu ngay lập tức ạ!`,
        ],
      };
    }

    // 2. Ý định: Hỏi trạng thái đơn hàng / mã đơn
    if (/(don hang|ma don|order|kiem tra don|toi dau roi|khi nao giao|status)/.test(normalized)) {
      return {
        intent: 'order_status',
        intentLabel: 'Tra cứu đơn hàng',
        suggestion: `Chào bạn ${name}, mình đã kiểm tra trên hệ thống FASTFOOD: đơn hàng của bạn đang được quán chuẩn bị tươi nóng và sắp bàn giao cho tài xế. Bạn có thể theo dõi vị trí tài xế trực tiếp tại mục 'Theo dõi đơn' nhé!`,
        quickReplies: [
          `Đơn của bạn ${name} đã được quán nhận và đang trong quá trình chế biến nóng hổi ạ!`,
          `Tài xế FASTFOOD đang di chuyển giao món đến địa chỉ của bạn, bạn chú ý điện thoại nhé.`,
        ],
      };
    }

    // 3. Ý định: Yêu cầu hủy đơn hàng
    if (/(huy don|cancel|khong lay nua|huy ho|khong mua nua)/.test(normalized)) {
      return {
        intent: 'cancel_order',
        intentLabel: 'Hủy đơn hàng',
        suggestion: `Dạ chào bạn ${name}, nếu đơn hàng chưa được quán bắt đầu làm thì bạn có thể bấm nút 'Hủy đơn' trực tiếp trong trang theo dõi đơn. Lưu ý: theo chính sách sàn, đơn hủy sẽ làm tăng 3% phí ship cho lần đặt tiếp theo bạn nhé.`,
        quickReplies: [
          `Dạ nếu quán chưa làm món, FASTFOOD đã hỗ trợ bạn hủy đơn thành công trên hệ thống rồi ạ.`,
          `Dạ rất tiếc vì quán đã làm xong món nên không thể hủy đơn vào lúc này, mong bạn thông cảm giúp quán nhé!`,
        ],
      };
    }

    // 4. Ý định: Hỏi phí giao hàng / thời gian ship
    if (/(phi ship|giao hang|phi van chuyen|bao nhieu km|xa khong|ship)/.test(normalized)) {
      return {
        intent: 'shipping',
        intentLabel: 'Phí giao hàng & Ship',
        suggestion: `Chào bạn ${name}, phí ship được tính tự động dựa trên khoảng cách GPS từ quán ăn đến địa chỉ của bạn tại Buôn Ma Thuột. Đơn từ 100K được áp mã FREESHIP giảm ngay 18K phí ship nhé!`,
        quickReplies: [
          `Đơn từ 100.000đ được miễn phí giao hàng tối đa 18.000đ với mã FREESHIP bạn nhé.`,
          `Thời gian giao hàng trung bình từ 15 - 25 phút tùy khoảng cách và thời tiết ạ.`,
        ],
      };
    }

    // 5. Ý định: Khuyến mãi, voucher, mã giảm giá
    if (/(khuyen mai|ma giam|voucher|coupon|code|giam gia|uu dai)/.test(normalized)) {
      return {
        intent: 'promotion',
        intentLabel: 'Mã giảm giá & Khuyến mãi',
        suggestion: `Chào bạn ${name}, FASTFOOD đang có các mã hot: FASTFOOD20 (giảm 20K đơn từ 60K), FREESHIP (miễn phí ship đơn từ 100K) và GIAM50K (giảm 50K đơn từ 200K). Bạn chọn áp dụng trực tiếp ở trang Thanh toán nhé!`,
        quickReplies: [
          `Bạn nhập mã FASTFOOD20 để được giảm 20.000đ cho đơn hàng từ 60.000đ nhé!`,
          `Đơn từ 200.000đ bạn áp mã GIAM50K để được giảm ngay 50.000đ nhé!`,
        ],
      };
    }

    // 6. Ý định: Tư vấn đồ uống / trà sữa riêng biệt
    if (/(tra sua|nuoc ngot|ca phe|sinh to|tra chanh|pepsi|coca)/.test(normalized)) {
      return {
        intent: 'drink_recommendation',
        intentLabel: 'Tư vấn đồ uống',
        suggestion: `Chào bạn ${name}, các loại đồ uống và trà sữa 'best-seller' hiện có gồm: Trà Sữa Trân Châu Đường Đen Hoàng Gia, Hồng Trà Sữa Kem Cheese và Trà Chanh Giã Tay. Bạn muốn chọn loại size hay thêm topping gì không ạ?`,
        quickReplies: [
          `Trà Sữa Trân Châu Đường Đen Hoàng Gia size M đang có giá rất ưu đãi ạ!`,
          `Quán có hỗ trợ thêm trân châu trắng, thạch dừa và kem cheese theo sở thích của bạn nhé.`,
        ],
      };
    }

    // 6.1. Ý định: Hỏi thực đơn món ăn nhanh / đồ mặn
    if (/(mon nao ngon|thuc don|menu|quan nao|goi y|banh burger|com tam|pizza|pho|sushi)/.test(normalized)) {
      return {
        intent: 'menu_recommendation',
        intentLabel: 'Tư vấn món ăn & Quán',
        suggestion: `Chào bạn ${name}, các món ăn 'best-seller' được đặt nhiều nhất hôm nay gồm: Burger Bò Nướng Phô Mai Tan Chảy, Cơm Tấm Sườn Cốt Lết Mật Ong Rừng và Pizza Bò Băm Pepperoni. Bạn thích ăn món mặn nào để mình tư vấn thêm nhé!`,
        quickReplies: [
          `Quán Burger King Station và Cơm Tấm Sài Gòn Xưa đang có ưu đãi Flash Sale giảm đến 25% hôm nay đó bạn!`,
          `Dạ các món ăn đều được chế biến nóng sốt ngay khi bạn đặt hàng ạ.`,
        ],
      };
    }

    // 7. Ý định: Chào hỏi
    if (/\b(chao|xin chao|hello|hi|alo|ad oi|shop oi|admin oi)\b/.test(normalized)) {
      return {
        intent: 'greeting',
        intentLabel: 'Chào hỏi xã giao',
        suggestion: `Chào bạn ${name}! Mình là chuyên viên hỗ trợ của FASTFOOD. Rất vui được hỗ trợ bạn, bạn đang quan tâm đến món ăn hay đơn hàng nào hôm nay ạ?`,
        quickReplies: [
          `Dạ chào bạn ${name}, FASTFOOD có thể hỗ trợ gì cho bạn hôm nay ạ?`,
          `Chào bạn, bạn cần kiểm tra đơn hàng hay tư vấn chọn món ngon ạ?`,
        ],
      };
    }

    // Mặc định: Phản hồi chung lịch sự
    return {
      intent: 'general',
      intentLabel: 'Hỗ trợ chung',
      suggestion: `Dạ chào bạn ${name}, cảm ơn bạn đã nhắn tin cho FASTFOOD. Bạn vui lòng cung cấp thêm thông tin chi tiết (hoặc mã đơn hàng) để mình hỗ trợ bạn nhanh nhất nhé!`,
      quickReplies: [
        `Dạ mình đã nhận được tin nhắn, bạn gửi thêm mã đơn hoặc số điện thoại để mình kiểm tra nhé.`,
        `Cảm ơn bạn ${name}, nhân viên CSKH FASTFOOD đang xử lý yêu cầu của bạn đây ạ!`,
      ],
    };
  }

  /**
   * Trợ lý Chatbot AI tư vấn nghiệp vụ và quản trị dành riêng cho Admin
   */
  consultAdminAssistant({ prompt }) {
    const raw = String(prompt || '').trim();
    const query = raw
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'd')
      .toLowerCase();

    // 1. Quản lý doanh thu, báo cáo, biểu đồ
    if (/(doanh thu|doanh so|tien|bieu do|thong ke|ban duoc)/.test(query)) {
      return `📊 **Phân tích Doanh thu & Kinh doanh FASTFOOD:**
- Bạn có thể xem biểu đồ doanh thu 7 ngày gần nhất ngay trên **Dashboard Tổng quan**.
- Doanh thu được tính tự động từ các đơn hàng có trạng thái **Đã giao** và thanh toán thành công (MoMo, Banking, COD).
- Các đơn bị hủy sẽ tự động được loại trừ khỏi biểu đồ để đảm bảo số liệu kế toán chuẩn xác.
💡 *Mẹo:* Khung giờ đặt hàng cao điểm thường rơi vào **11:00 - 13:00** (bữa trưa) và **18:00 - 20:30** (bữa tối). Bạn nên nhắc các quán chuẩn bị sẵn nguyên liệu trước giờ cao điểm.`;
    }

    // 2. Chính sách phí ship và hủy đơn
    if (/(phi ship|huy don|chinh sach|tang phi|phat)/.test(query)) {
      return `📋 **Chính sách Vận chuyển & Hủy đơn hàng của FASTFOOD:**
- **Quy tắc tăng phí khi hủy đơn:** Nhằm hạn chế tình trạng boom hàng và hủy đơn vô cớ, hệ thống áp dụng chính sách: **Mỗi lần khách hủy đơn, phí giao hàng cho các đơn tiếp theo của khách đó sẽ tăng thêm 3%** (lũy kế).
- **Điều kiện hủy đơn:** Khách hàng chỉ có thể hủy đơn khi đơn ở bước *Đã đặt đơn* hoặc *Quán nhận đơn*. Khi quán đã bắt đầu làm món hoặc tài xế đang giao, khách không thể tự hủy trên web.
- **Miễn phí ship:** Áp dụng mã \`FREESHIP\` cho đơn từ 100.000đ (hỗ trợ tối đa 18.000đ).`;
    }

    // 3. Quản lý thực đơn và quán ăn
    if (/(quan an|mon an|san pham|them mon|sua mon|xoa mon|topping)/.test(query)) {
      return `🍔 **Quản lý Thực đơn & Quán ăn:**
- **Thêm/Sửa món:** Vào mục **Sản phẩm** > Bấm nút **Thêm mới** hoặc biểu tượng **Sửa**. Bạn có thể tùy chỉnh tên món, giá gốc, giá khuyến mãi, thời gian làm và danh sách **Toppings** kèm theo.
- **Tải ảnh món:** Hệ thống hỗ trợ upload ảnh trực tiếp lên server hoặc dán link ảnh chất lượng cao từ Unsplash.
- **Quán ăn tại Buôn Ma Thuột:** Các quán đối tác tiêu biểu gồm *Burger King Station (25 Nguyễn Tất Thành)*, *Cơm Tấm Sài Gòn Xưa (68 Phan Chu Trinh)*, *Pizza Hut Italia*, *GongCha*, *Phở Bếp Việt*, *Sushi Maki House*...`;
    }

    // 4. Hướng dẫn xử lý khiếu nại khách hàng
    if (/(khieu nai|khach chui|1 sao|danh gia xau|hoan tien|do an nguoi)/.test(query)) {
      return `🛡️ **Quy trình chuẩn xử lý khiếu nại khách hàng (CSKH 5 Sao):**
1. **Lắng nghe & Xin lỗi chân thành:** Dù lỗi do quán hay do tài xế, luôn cảm ơn khách và gửi lời xin lỗi đầu tiên để xoa dịu cảm xúc.
2. **Kiểm tra mã đơn & Trạng thái:** Tra cứu mã đơn trong mục **Đơn hàng** để xem thời gian giao và thông tin tài xế.
3. **Giải pháp đền bù:**
   - *Nếu thiếu món / sai món:* Hoàn tiền phần món thiếu hoặc liên hệ quán làm bù giao hỏa tốc.
   - *Nếu đồ ăn nguội / đổ vỡ do ship:* Gửi mã giảm giá \`FASTFOOD20\` hoặc \`GIAM50K\` cho đơn tiếp theo.
4. **Theo dõi đánh giá:** Xem phản hồi tại mục **Đánh giá khách hàng** để nhắc nhở quán cải thiện chất lượng đóng gói.`;
    }

    // 5. Soạn tin nhắn / Mẫu thông báo
    if (/(soan|tin nhan|thong bao|khuyen mai|mau|template)/.test(query)) {
      return `✍️ **Mẫu thông báo khuyến mãi / CSKH gợi ý cho Admin:**

**Mẫu 1: Ưu đãi Flash Sale cuối tuần**
> ⚡ *[FASTFOOD] ĐẠI TIỆC CUỐI TUẦN - GIẢM NGAY 50K!*
> Thưởng thức Burger bò nướng đẫm phô mai hay Cơm tấm sườn cọng giòn rụm với mã \`GIAM50K\` cho đơn từ 200K. Miễn phí ship với mã \`FREESHIP\`. Đặt ngay tại http://localhost:5000!

**Mẫu 2: Tin nhắn chăm sóc khách sau khi giải quyết khiếu nại**
> Dạ FASTFOOD chào bạn, vấn đề đơn hàng của bạn đã được giải quyết hoàn tất ạ. FASTFOOD xin gửi tặng bạn voucher \`FASTFOODHELLO\` giảm 20K cho lần đặt món tiếp theo. Chúc bạn có trải nghiệm thật ngon miệng!`;
    }

    // 6. Tài khoản và phân quyền
    if (/(tai khoan|admin|customer|mat khau|phan quyen)/.test(query)) {
      return `🔐 **Cơ cấu Tài khoản & Bảo mật FASTFOOD:**
- **Admin (\`admin@fastfood.vn\`):** Toàn quyền quản trị danh mục, món ăn, quán ăn, duyệt đơn, chat hỗ trợ và theo dõi biểu đồ doanh thu. Mật khẩu mặc định: \`Admin@123456\`. Tài khoản admin không có giỏ hàng để tránh nhầm lẫn nghiệp vụ.
- **Customer (\`customer@fastfood.vn\`):** Tài khoản khách hàng dùng để đặt món, tích lũy lịch sử đơn, đánh giá sao và chat với trợ lý.
- **Bảo mật:** Sử dụng JWT Token thời hạn 7 ngày, mã hóa mật khẩu bcrypt 1 chiều an toàn.`;
    }

    // Phản hồi tổng quan
    return `🤖 **Trợ lý AI Quản Trị FASTFOOD xin chào!**
Tôi là AI Co-pilot hỗ trợ Admin. Bạn có thể hỏi tôi bất kỳ điều gì:
- 📈 *Phân tích doanh thu và dự báo giờ cao điểm*
- 📋 *Chính sách giao hàng, phí ship và xử lý đơn hủy*
- ✍️ *Soạn thảo tin nhắn CSKH hoặc thông báo khuyến mãi*
- 🛡️ *Quy trình xử lý khiếu nại và phản hồi đánh giá 1 sao*
- 🍔 *Quản lý thực đơn, toppings và đối tác nhà hàng*

*Bạn đang cần hỗ trợ về chủ đề nào ạ?*`;
  }
}

module.exports = new AIChatService();

