const fs = require('fs');
const path = require('path');

const SAMPLE_JPG_PATH = path.join(__dirname, 'sample.jpg');
const SAMPLE_PNG_PATH = path.join(__dirname, 'sample.png');

/**
 * Returns a Buffer of a valid JPEG image (from sample.jpg).
 */
function getValidJpgBuffer() {
  return fs.readFileSync(SAMPLE_JPG_PATH);
}

/**
 * Returns a Buffer of a valid PNG image (from sample.png).
 */
function getValidPngBuffer() {
  return fs.readFileSync(SAMPLE_PNG_PATH);
}

/**
 * Generates an oversized buffer (> 5MB) starting with valid JPEG magic bytes
 * so it satisfies file signature inspection but triggers maximum size limit validation (HTTP 400).
 *
 * @param {number} sizeBytes - Default: 6MB (6 * 1024 * 1024)
 * @returns {Buffer}
 */
function getOversizedBuffer(sizeBytes = 6 * 1024 * 1024) {
  const sample = getValidJpgBuffer();
  const buffer = Buffer.alloc(sizeBytes);
  // Copy valid JPEG header bytes
  sample.copy(buffer, 0, 0, Math.min(sample.length, 1024));
  return buffer;
}

/**
 * Returns a Buffer with disallowed content (e.g. PHP script / plain text).
 */
function getDisallowedBuffer() {
  return Buffer.from('<?php echo "This is a disallowed script file"; ?>', 'utf8');
}

module.exports = {
  SAMPLE_JPG_PATH,
  SAMPLE_PNG_PATH,
  getValidJpgBuffer,
  getValidPngBuffer,
  getOversizedBuffer,
  getDisallowedBuffer
};
