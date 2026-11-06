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
      const { fullName, email, phone, password } = req.body;
      const emailIsValid = typeof email === 'string'
        && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
      const phoneIsValid = typeof phone === 'string'
        && /^\+?[0-9\s().-]{8,20}$/.test(phone.trim());
      if (
        typeof fullName !== 'string' || !fullName.trim()
        || !emailIsValid
        || !phoneIsValid
        || typeof password !== 'string' || password.length < 6
      ) {
        return this.sendError(
          res,
          'Vui lòng nhập họ tên, email, số điện thoại hợp lệ và mật khẩu tối thiểu 6 ký tự.',
          HTTP_STATUS.BAD_REQUEST
        );
      }

      const result = await AuthService.register({
        fullName: fullName.trim(),
        email: email.trim().toLowerCase(),
        phone: phone.trim(),
        password,
      });

      return this.sendSuccess(res, 'Đăng ký tài khoản thành công', result, HTTP_STATUS.CREATED);
    } catch (error) {
      if (error.code === 'ER_DUP_ENTRY') {
        return this.sendError(res, 'Email hoặc số điện thoại đã được đăng ký.', HTTP_STATUS.CONFLICT);
      }
      if (error.statusCode === HTTP_STATUS.CONFLICT) {
        return this.sendError(res, error.message, HTTP_STATUS.CONFLICT);
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
