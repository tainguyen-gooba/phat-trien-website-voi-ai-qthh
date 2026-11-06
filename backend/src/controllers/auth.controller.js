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
