<?php
namespace FastFood\Config;

/**
 * SePay & VietQR Configuration
 */
class SepayConfig {
    public const BANK_CODE = 'BIDV';
    public const ACCOUNT_NUMBER = '8855033140';
    public const ACCOUNT_NAME = 'NGUYEN HIEN TAI';
    public const API_KEY = 'YOUR_SEPAY_API_KEY';
    public const TEMPLATE = 'compact2';

    public static function getQrUrl(string $orderCode, int $amount): string {
        $cleanCode = preg_replace('/[^a-zA-Z0-9]/', '', $orderCode);
        $encodedAccName = urlencode(self::ACCOUNT_NAME);
        return "https://qr.sepay.vn/img?bank=" . self::BANK_CODE 
             . "&acc=" . self::ACCOUNT_NUMBER 
             . "&template=" . self::TEMPLATE 
             . "&amount={$amount}&des={$cleanCode}&accountName={$encodedAccName}";
    }
}

