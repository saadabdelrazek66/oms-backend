<?php

namespace App\Enums;

enum PlanTaskRole: string
{
    case RESPONSIBLE = 'responsible'; // المسئول
    case REVIEWER = 'reviewer';       // مراجع داخلي
    case EXECUTOR = 'executor';       // منفذ
}
