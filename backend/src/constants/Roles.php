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

    public static function getRoleIdByName(string $roleName): int {
        $name = strtolower(trim($roleName));
        $flipped = array_flip(self::ROLE_NAMES);
        return $flipped[$name] ?? self::CUSTOMER;
    }

    public static function isValidRole(int $roleId): bool {
        return isset(self::ROLE_NAMES[$roleId]);
    }

    public static function getPermissions(int $roleId): array {
        switch ($roleId) {
            case self::ADMIN:
                return [
                    'role'                => 'admin',
                    'can_manage_users'    => true,
                    'can_manage_products' => true,
                    'can_manage_categories' => true,
                    'can_manage_restaurants' => true,
                    'can_manage_orders'   => true,
                    'can_manage_reviews'  => true,
                    'can_manage_coupons'  => true,
                    'can_view_reports'    => true,
                    'can_configure_ai'    => true,
                    'can_chat_support'    => true,
                    'scope'               => 'FULL_SYSTEM_ADMIN'
                ];
            case self::STAFF:
                return [
                    'role'                => 'staff',
                    'can_manage_users'    => false,
                    'can_manage_products' => true,
                    'can_manage_categories' => true,
                    'can_manage_restaurants' => true,
                    'can_manage_orders'   => true,
                    'can_manage_reviews'  => true,
                    'can_manage_coupons'  => false,
                    'can_view_reports'    => true,
                    'can_configure_ai'    => false,
                    'can_chat_support'    => true,
                    'scope'               => 'STAFF_OPERATIONS'
                ];
            case self::CUSTOMER:
            default:
                return [
                    'role'                => 'customer',
                    'can_view_menu'       => true,
                    'can_order'           => true,
                    'can_apply_coupons'   => true,
                    'can_receive_vouchers'=> true,
                    'can_review'          => true,
                    'can_chat'            => true,
                    'can_track_orders'    => true,
                    'scope'               => 'CUSTOMER_CLIENT'
                ];
        }
    }

    public static function getAllRoles(): array {
        $result = [];
        foreach (self::ROLE_NAMES as $id => $name) {
            $result[] = [
                'id' => $id,
                'name' => $name,
                'label' => self::ROLE_LABELS[$id] ?? $name,
                'permissions' => self::getPermissions($id)
            ];
        }
        return $result;
    }
}

