<?php
/***************************************************************************
 *                             Larionov.tech                               *
 *                          https://larionov.tech                          *
 ***************************************************************************/

namespace {
    const BOOTSTRAP = true;
    const DIR_ROOT = '/';
    const TIME = 0;
    const POINTS = 'W';

    function __(string $key, array $params = []): string {}
    function fn_register_hooks(string ...$hooks): void {}
    function fn_check_promotion_notices(): void {}
    function fn_set_notification(string $type, string $title, string $message): void {}
    function fn_url(string $url, string $area): string {}
    function fn_format_price(float $price): float {}
    /** @return int|false */
    function fn_save_user_additional_data(string $type, int $data, int $user_id): int|false {}
    /** @return list<array<string, mixed>> */
    function db_get_array(string $query, mixed ...$args): array {}
    /** @return array<string, mixed> */
    function db_get_row(string $query, mixed ...$args): array {}
    /** @return scalar|null */
    function db_get_field(string $query, mixed ...$args): int|float|string|bool|null {}
    /** @return list<scalar|null> */
    function db_get_fields(string $query, mixed ...$args): array {}
    function db_query(string $query, mixed ...$args): mixed {}
    function db_quote(string $query, mixed ...$args): string {}
    function db_replace_into(string $table, array $data, bool $is_multiple = false): int {}
}

namespace Pimple {
    interface ServiceProviderInterface
    {
        public function register(Container $app);
    }

    /** @implements \ArrayAccess<string, mixed> */
    class Container implements \ArrayAccess
    {
        public function register(ServiceProviderInterface $provider, array $values = []): self {}
        public function offsetExists(mixed $offset): bool {}
        public function offsetGet(mixed $offset): mixed {}
        public function offsetSet(mixed $offset, mixed $value): void {}
        public function offsetUnset(mixed $offset): void {}
    }
}

namespace Tygh {
    class Tygh
    {
        public static \Pimple\Container $app;
    }

    class Registry
    {
        public static function get(string $key): mixed {}
        public static function set(string $key, mixed $value, bool $no_cache = false): void {}
        public static function del(string $key): void {}
    }
}

namespace Tygh\Enum {
    class SiteArea { public const ADMIN_PANEL = 'A'; }
    class OrderStatuses { public const PARENT = 'T'; public const INCOMPLETED = 'N'; }
    class UserTypes { public const CUSTOMER = 'C'; }
    class ObjectStatuses { public const ACTIVE = 'A'; }
}

namespace Tygh\Enum\Addons\Rma {
    class ReturnOperationStatuses
    {
        public const APPROVED = 'A';
        public const COMPLETED = 'C';
    }
}
