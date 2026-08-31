<?php

namespace App\Enums;

enum PlanTaskRole: string
{
    case RESPONSIBLE = 'responsible'; // المسئول
    case SPECIALIST = 'specialist';   // المختص
    case EXECUTOR = 'executor';       // القائم بالخطة
}
