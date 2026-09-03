<?php

namespace App\Enums;

enum WorkType: string
{
    case REMOTE = 'remote';
    case ONSITE = 'onsite';
    case PER_TASK = 'per_task';
    case COMMISSION = 'commission';

    public function label(): string
    {
        return match($this) {
            self::REMOTE => 'عن بعد',
            self::ONSITE => 'من مقر العمل',
            self::PER_TASK => 'بالتاسك',
            self::COMMISSION => 'بالعمولة',
        };
    }
}
