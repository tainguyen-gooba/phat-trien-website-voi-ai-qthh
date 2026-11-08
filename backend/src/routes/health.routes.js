const express = require('express');
const router = express.Router();
const healthController = require('../controllers/health.controller');

// GET /api/v1/health - Kiểm tra tổng thể Server & Database
router.get('/', (req, res, next) => healthController.getSystemHealth(req, res, next));

// GET /api/v1/health/db - Kiểm tra riêng kết nối MySQL
router.get('/db', (req, res, next) => healthController.getDatabaseHealth(req, res, next));

module.exports = router;

