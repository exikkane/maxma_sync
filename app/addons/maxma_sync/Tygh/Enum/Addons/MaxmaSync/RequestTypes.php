<?php

namespace Tygh\Enum\Addons\MaxmaSync;

class RequestTypes
{
    const NEW_CLIENT = 'newClient';
    const SET_ORDER = 'setOrder';
    const CONFIRM_ORDER = 'confirmOrder';
    const CANCEL_ORDER = 'cancelOrder';
    const APPLY_RETURN = 'applyReturn';
    const CALCULATE_PURCHASE = 'calculatePurchase';
    const GET_BALANCE = 'getBalance';
    const GET_BONUS_HISTORY = 'getBonusHistory';
    const UPDATE_CLIENT = 'updateClient';

    public static function isValid(string $type): bool
    {
        return in_array($type, self::getAll(), true);
    }
    public static function getAll(): array
    {
        return (new \ReflectionClass(self::class))->getConstants();
    }
    public static function getQueueTypes(): array
    {
        return [
            self::NEW_CLIENT,
            self::SET_ORDER,
            self::CONFIRM_ORDER,
            self::CANCEL_ORDER,
            self::APPLY_RETURN,
            self::UPDATE_CLIENT,
        ];
    }
}
