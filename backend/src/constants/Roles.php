<?php
namespace FastFood\Constants;

/**
 * Roles Constant Definitions
 */
class Roles {
    public const ADMIN = 1;
    public const CUSTOMER = 2;
    public const RESTAURANT_OWNER = 3;
    public const SHIPPER = 4;
    public const STAFF = 5;

    public const ROLE_NAMES = [
        self::ADMIN => 'admin',
        self::CUSTOMER => 'customer',
        self::RESTAURANT_OWNER => 'restaurant',
        self::SHIPPER => 'shipper',
        self::STAFF => 'staff',
    ];

    public const ROLE_LABELS = [
        self::ADMIN => 'Quản trị viên',
        self::CUSTOMER => 'Khách hàng',
        self::RESTAURANT_OWNER => 'Chủ nhà hàng',
        self::SHIPPER => 'Tài xế',
        self::STAFF => 'Nhân viên hỗ trợ',
    ];

    public static function getName(int $roleId): string {
        return self::ROLE_NAMES[$roleId] ?? 'customer';
    }

    public static function getLabel(int $roleId): string {
        return self::ROLE_LABELS[$roleId] ?? 'Khách hàng';
    }
}

