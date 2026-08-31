<?php

namespace App\Enums;

enum WorkType: string
{
    case REMOTE = 'remote'; // من المنزل
    case ONSITE = 'onsite'; // من الشركة
}
