<?php

namespace App\Enums;

enum SyncRecordStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
}
