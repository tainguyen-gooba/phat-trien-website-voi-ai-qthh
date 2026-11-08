const BaseController = require('./base.controller');
const AuthService = require('../services/auth.service');
const HTTP_STATUS = require('../constants/httpStatusCodes');

class AuthController extends BaseController {
  async login(req, res, next) {
    try {
      const { email, password } = req.body;
      if (typeof email !== 'string' || typeof password !== 'string' || !email.trim() || !password) {
        return this.sendError(res, 'Vui lòng nhập email và mật khẩu hợp lệ', HTTP_STATUS.BAD_REQUEST);
      }

      const result = await AuthService.login(email.trim().toLowerCase(), password);
      if (!result) {
        return this.sendError(res, 'Email hoặc mật khẩu không chính xác', HTTP_STATUS.UNAUTHORIZED);
      }

      return this.sendSuccess(res, 'Đăng nhập thành công', result, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }

  async register(req, res, next) {
    try {
      const { email, password } = req.body;
      const emailIsValid = typeof email === 'string'
        && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
      if (
        !emailIsValid
        || typeof password !== 'string' || password.length < 6
      ) {
        return this.sendError(
          res,
          'Vui lòng nhập email hợp lệ và mật khẩu tối thiểu 6 ký tự.',
          HTTP_STATUS.BAD_REQUEST
        );
      }

      const result = await AuthService.register({
        email: email.trim().toLowerCase(),
        password,
      });

      return this.sendSuccess(res, 'Đăng ký tài khoản thành công', result, HTTP_STATUS.CREATED);
    } catch (error) {
      if (error.code === 'ER_DUP_ENTRY') {
        return this.sendError(res, 'Email đã được đăng ký.', HTTP_STATUS.CONFLICT);
      }
      if (error.statusCode === HTTP_STATUS.CONFLICT) {
        return this.sendError(res, error.message, HTTP_STATUS.CONFLICT);
      }
      return next(error);
    }
  }

  async completeCustomerProfile(req, res, next) {
    try {
      const { fullName, phone, address } = req.body;
      const phoneIsValid = typeof phone === 'string'
        && /^\+?[0-9\s().-]{8,20}$/.test(phone.trim());
      if (
        typeof fullName !== 'string' || !fullName.trim() || fullName.trim().length > 150
        || !phoneIsValid
        || typeof address !== 'string' || !address.trim() || address.trim().length > 255
      ) {
        return this.sendError(
          res,
          'Vui lòng nhập họ tên, số điện thoại và địa chỉ hợp lệ.',
          HTTP_STATUS.BAD_REQUEST
        );
      }

      const result = await AuthService.completeCustomerProfile(req.user.id, {
        fullName: fullName.trim(),
        phone: phone.trim(),
        address: address.trim(),
      });
      if (!result) {
        return this.sendError(res, 'Tài khoản không còn hoạt động', HTTP_STATUS.UNAUTHORIZED);
      }

      return this.sendSuccess(res, 'Hoàn tất hồ sơ và nhận voucher thành công', result, HTTP_STATUS.OK);
    } catch (error) {
      if (error.code === 'ER_DUP_ENTRY') {
        const message = error.message.includes('phone')
          ? 'Số điện thoại đã được đăng ký.'
          : 'Không thể tạo mã voucher, vui lòng thử lại.';
        return this.sendError(res, message, HTTP_STATUS.CONFLICT);
      }
      if (error.statusCode === HTTP_STATUS.CONFLICT) {
        return this.sendError(res, error.message, HTTP_STATUS.CONFLICT);
      }
      return next(error);
    }
  }

  async updateCustomerProfile(req, res, next) {
    try {
      const { fullName, phone, address } = req.body;
      const phoneIsValid = typeof phone === 'string'
        && /^\+?[0-9\s().-]{8,20}$/.test(phone.trim());
      if (
        typeof fullName !== 'string' || !fullName.trim() || fullName.trim().length > 150
        || !phoneIsValid
        || typeof address !== 'string' || !address.trim() || address.trim().length > 255
      ) {
        return this.sendError(
          res,
          'Vui lòng nhập họ tên, số điện thoại và địa chỉ hợp lệ.',
          HTTP_STATUS.BAD_REQUEST
        );
      }

      const user = await AuthService.updateCustomerProfile(req.user.id, {
        fullName: fullName.trim(),
        phone: phone.trim(),
        address: address.trim(),
      });
      if (!user) {
        return this.sendError(res, 'Tài khoản không còn hoạt động', HTTP_STATUS.UNAUTHORIZED);
      }

      return this.sendSuccess(res, 'Cập nhật thông tin khách hàng thành công', { user }, HTTP_STATUS.OK);
    } catch (error) {
      if (error.code === 'ER_DUP_ENTRY' || error.statusCode === HTTP_STATUS.CONFLICT) {
        return this.sendError(res, 'Số điện thoại đã được đăng ký.', HTTP_STATUS.CONFLICT);
      }
      return next(error);
    }
  }

  async getCurrentUser(req, res, next) {
    try {
      const user = await AuthService.getActiveUser(req.user.id);
      if (!user) {
        return this.sendError(res, 'Tài khoản không còn hoạt động', HTTP_STATUS.UNAUTHORIZED);
      }

      return this.sendSuccess(res, 'Lấy thông tin tài khoản thành công', { user }, HTTP_STATUS.OK);
    } catch (error) {
      return next(error);
    }
  }
}

module.exports = new AuthController();
