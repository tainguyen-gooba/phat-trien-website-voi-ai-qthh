const logger = require('../utils/logger');

/**
 * Middleware ghi log chi tiết cho từng HTTP request
 */
function requestLogger(req, res, next) {
  const start = Date.now();
  const { method, originalUrl, ip } = req;

  res.on('finish', () => {
    const duration = Date.now() - start;
    const statusCode = res.statusCode;
    const statusCategory = Math.floor(statusCode / 100);

    const logMsg = `${method} ${originalUrl} ${statusCode} - ${duration}ms - ${ip}`;

    if (statusCategory === 5) {
      logger.error(logMsg);
    } else if (statusCategory === 4) {
      logger.warn(logMsg);
    } else {
      logger.info(logMsg);
    }
  });

  next();
}

module.exports = requestLogger;

