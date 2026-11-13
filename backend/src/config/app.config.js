const dotenv = require('dotenv');
const path = require('path');

// Load .env from backend root
dotenv.config({ path: path.resolve(__dirname, '../../.env') });

const momoEnvironment = process.env.MOMO_ENV
  || (process.env.NODE_ENV === 'production' ? 'production' : 'sandbox');
const vnpayEnvironment = process.env.VNPAY_ENV
  || (process.env.NODE_ENV === 'production' ? 'production' : 'sandbox');

const config = {
  env: process.env.NODE_ENV || 'development',
  port: parseInt(process.env.PORT || '5000', 10),
  isProduction: process.env.NODE_ENV === 'production',
  isDevelopment: process.env.NODE_ENV === 'development',

  db: {
    host: process.env.DB_HOST || 'localhost',
    port: parseInt(process.env.DB_PORT || '3306', 10),
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'fastfood_db',
    connectionLimit: parseInt(process.env.DB_CONNECTION_LIMIT || '10', 10),
    queueLimit: parseInt(process.env.DB_QUEUE_LIMIT || '0', 10),
    waitForConnections: process.env.DB_WAIT_FOR_CONNECTIONS !== 'false',
  },

  jwt: {
    secret: process.env.JWT_SECRET || 'fastfood_jwt_default_secret_key_2026',
    expiresIn: process.env.JWT_EXPIRES_IN || '7d',
  },

  cors: {
    origin: process.env.CORS_ORIGIN || '*',
  },

  payments: {
    publicUrl: process.env.PAYMENT_PUBLIC_URL || '',
    momo: {
      environment: momoEnvironment === 'production' ? 'production' : 'sandbox',
      partnerCode: process.env.MOMO_PARTNER_CODE || '',
      accessKey: process.env.MOMO_ACCESS_KEY || '',
      secretKey: process.env.MOMO_SECRET_KEY || '',
      endpoint: process.env.MOMO_API_URL || (momoEnvironment === 'production'
        ? 'https://payment.momo.vn/v2/gateway/api/create'
        : 'https://test-payment.momo.vn/v2/gateway/api/create'),
    },
    vnpay: {
      environment: vnpayEnvironment === 'production' ? 'production' : 'sandbox',
      tmnCode: process.env.VNPAY_TMN_CODE || '',
      hashSecret: process.env.VNPAY_HASH_SECRET || '',
      endpoint: process.env.VNPAY_URL || (vnpayEnvironment === 'production'
        ? 'https://pay.vnpay.vn/vpcpay.html'
        : 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
    },
    sepay: {
      apiKey: process.env.SEPAY_API_KEY || '',
      bankCode: process.env.SEPAY_BANK_CODE || '',
      accountNumber: process.env.SEPAY_ACCOUNT_NUMBER || '',
      accountName: process.env.SEPAY_ACCOUNT_NAME || '',
    },
  },
};

module.exports = config;
