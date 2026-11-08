function normalizeCustomerProfile(profile = {}) {
  const { phone, address } = profile;
  const name = profile.fullName ?? profile.name;
  const normalizedName = typeof name === 'string' ? name.trim().replace(/\s+/g, ' ') : '';
  const normalizedPhone = typeof phone === 'string'
    ? phone.trim().replace(/[\s().-]/g, '').replace(/^\+?84/, '0')
    : '';
  const normalizedAddress = typeof address === 'string' ? address.trim().replace(/\s+/g, ' ') : '';
  const nameIsValid = normalizedName.length >= 5
    && /^[\p{L}]+(?:[ '-][\p{L}]+)+$/u.test(normalizedName)
    && normalizedName.length <= 150;
  const phoneIsValid = /^0[35789]\d{8}$/.test(normalizedPhone);
  const addressWords = normalizedAddress.match(/[\p{L}]{2,}/gu) || [];
  const addressIsValid = normalizedAddress.length >= 12
    && normalizedAddress.length <= 255
    && /^[\p{L}\p{N}\s,.'/#-]+$/u.test(normalizedAddress)
    && addressWords.length >= (/\d/.test(normalizedAddress) ? 2 : 3);

  return {
    fullName: normalizedName,
    phone: normalizedPhone,
    address: normalizedAddress,
    valid: nameIsValid && phoneIsValid && addressIsValid,
    errors: {
      fullName: nameIsValid ? '' : 'Vui lòng nhập họ tên thật (ít nhất 5 ký tự), gồm họ và tên bằng chữ.',
      phone: phoneIsValid ? '' : 'Số điện thoại phải là số di động Việt Nam hợp lệ gồm 10 chữ số.',
      address: addressIsValid ? '' : 'Vui lòng nhập địa chỉ chi tiết (số nhà/đường và khu vực), không nhập ký tự tùy ý.',
    },
  };
}

module.exports = { normalizeCustomerProfile };
