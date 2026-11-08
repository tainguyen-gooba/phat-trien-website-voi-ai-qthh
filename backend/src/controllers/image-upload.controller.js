const crypto = require('crypto');
const fs = require('fs/promises');
const path = require('path');
const ApiResponse = require('../utils/apiResponse');
const HTTP_STATUS = require('../constants/httpStatusCodes');

const uploadsDirectory = path.resolve(__dirname, '../../../frontend/assets/uploads');
const IMAGE_TYPES = {
  'image/jpeg': {
    extension: '.jpg',
    matches: (buffer) => buffer.length >= 3
      && buffer[0] === 0xff && buffer[1] === 0xd8 && buffer[2] === 0xff,
  },
  'image/png': {
    extension: '.png',
    matches: (buffer) => buffer.length >= 8
      && buffer.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a])),
  },
  'image/webp': {
    extension: '.webp',
    matches: (buffer) => buffer.length >= 12
      && buffer.toString('ascii', 0, 4) === 'RIFF'
      && buffer.toString('ascii', 8, 12) === 'WEBP',
  },
};

async function uploadProductImage(req, res, next) {
  const contentType = String(req.get('content-type') || '').split(';')[0].trim().toLowerCase();
  const imageType = IMAGE_TYPES[contentType];
  const image = req.body;

  if (!imageType || !Buffer.isBuffer(image) || image.length === 0 || !imageType.matches(image)) {
    return ApiResponse.error(
      res,
      'Tệp không hợp lệ. Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.',
      HTTP_STATUS.BAD_REQUEST
    );
  }

  const filename = `${crypto.randomUUID()}${imageType.extension}`;
  try {
    await fs.mkdir(uploadsDirectory, { recursive: true });
    await fs.writeFile(path.join(uploadsDirectory, filename), image, { flag: 'wx', mode: 0o644 });
    return ApiResponse.created(res, 'Tải ảnh món ăn thành công.', {
      url: `/assets/uploads/${filename}`,
    });
  } catch (error) {
    return next(error);
  }
}

module.exports = { uploadProductImage };
