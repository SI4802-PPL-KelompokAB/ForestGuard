<?php

namespace App\Enums;

enum ObservationCategory: string
{
    case ForestCondition = 'Kondisi hutan';
    case SuspiciousActivity = 'Aktivitas mencurigakan';
    case WildlifeFinding = 'Temuan satwa';
}
