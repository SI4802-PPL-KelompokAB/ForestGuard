<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class PatrolAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $patrolId, public int $rangerId, public int $createdBy) {}
}
