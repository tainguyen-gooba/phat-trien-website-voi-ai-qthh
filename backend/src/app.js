const express = require('express');
const cors = require('cors');
const path = require('path');
const config = require('./config/app.config');
const requestLogger = require('./middlewares/requestLogger.middleware');
const notFoundHandler = require('./middlewares/notFound.middleware');
const errorHandler = require('./middlewares/errorHandler.middleware');
const apiRoutes = require('./routes');

const app = express();

/**
 * 1. Security & CORS Configuration
 */
app.use(cors({
  origin: config.cors.origin === '*' ? '*' : config.cors.origin.split(','),
  methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization'],
  credentials: true,
}));

/**
 * 2. Body Parser Middleware
 */
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

/**
 * 3. Request Logging Middleware
 */
app.use(requestLogger);

/**
 * 4. Serve Frontend Static Assets (hỗ trợ cả frontend/, frontend/pages/, frontend/css/, frontend/js/)
 */
const frontendRoot = path.resolve(__dirname, '../../frontend');
const frontendPublicPath = path.resolve(__dirname, '../../frontend/public');
const frontendPagesPath = path.resolve(__dirname, '../../frontend/pages');

app.use(express.static(frontendRoot));
app.use(express.static(frontendPublicPath));
app.use('/pages', express.static(frontendPagesPath));
app.use('/css', express.static(path.resolve(frontendRoot, 'css')));
app.use('/js', express.static(path.resolve(frontendRoot, 'js')));
app.use('/assets', express.static(path.resolve(frontendRoot, 'assets')));

// Redirect trang chủ sang /pages/home.html
app.get('/', (req, res) => {
  res.redirect('/pages/home.html');
});

/**
 * 5. API Routes Registration
 */
app.use('/api/v1', apiRoutes);

// Root route redirect/info
app.get('/api', (req, res) => {
  res.redirect('/api/v1');
});

/**
 * 6. 404 Not Found Handling
 */
app.use(notFoundHandler);

/**
 * 7. Global Centralized Error Handling
 */
app.use(errorHandler);

module.exports = app;

