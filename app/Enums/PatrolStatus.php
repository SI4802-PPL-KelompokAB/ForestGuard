<?php

namespace App\Enums;

enum PatrolStatus: string
{
    case Assigned = 'Ditugaskan';
    case InProgress = 'Berlangsung';
    case Completed = 'Selesai';
}
