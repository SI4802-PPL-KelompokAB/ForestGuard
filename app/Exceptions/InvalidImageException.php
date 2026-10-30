<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class InvalidImageException extends RuntimeException implements ShouldntReport
{
    public function __construct(string $message = 'Berkas gambar tidak valid atau rusak.')
    {
        parent::__construct($message);
    }
}
