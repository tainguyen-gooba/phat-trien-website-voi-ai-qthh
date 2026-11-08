const express = require('express');
const authController = require('../controllers/auth.controller');
const { authenticate } = require('../middlewares/auth.middleware');

const router = express.Router();

router.post('/login', (req, res, next) => authController.login(req, res, next));
router.post('/register', (req, res, next) => authController.register(req, res, next));
router.post('/complete-profile', authenticate, (req, res, next) => authController.completeCustomerProfile(req, res, next));
router.get('/me', authenticate, (req, res, next) => authController.getCurrentUser(req, res, next));
router.patch('/me', authenticate, (req, res, next) => authController.updateCustomerProfile(req, res, next));

module.exports = router;
