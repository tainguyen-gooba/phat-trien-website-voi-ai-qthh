<?php
namespace FastFood\Config;

/**
 * JWT Token Handler (Native PHP implementation without external dependencies)
 */
class JwtConfig {
    private static string $secret = 'fastfood_jwt_secret_key_2026_super_secure';

    public static function encode(array $payload, int $expiresInSeconds = 604800): string {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $payload['exp'] = time() + $expiresInSeconds;
        $payload['iat'] = time();

        $base64UrlHeader = self::base64UrlEncode(json_encode($header));
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', "{$base64UrlHeader}.{$base64UrlPayload}", self::$secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return "{$base64UrlHeader}.{$base64UrlPayload}.{$base64UrlSignature}";
    }

    public static function decode(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header64, $payload64, $signature64] = $parts;
        $expectedSignature = self::base64UrlEncode(
            hash_hmac('sha256', "{$header64}.{$payload64}", self::$secret, true)
        );

        if (!hash_equals($expectedSignature, $signature64)) return null;

        $payload = json_decode(self::base64UrlDecode($payload64), true);
        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

