<?php

namespace App\Enums;

enum DriveLinkType: string
{
    case PLAN_REVIEW = 'رابط مراجعة خطط';
    case PLAN_FINAL_DELIVERY = 'رابط تسليم نهائي خطط';
    case POST_REVIEW = 'رابط مراجعة بوستات';
    case POST_FINAL_DELIVERY = 'رابط تسليم نهائي بوستات';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}