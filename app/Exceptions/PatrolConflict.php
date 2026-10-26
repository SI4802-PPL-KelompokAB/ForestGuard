<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class PatrolConflict extends RuntimeException implements ShouldntReport {}
