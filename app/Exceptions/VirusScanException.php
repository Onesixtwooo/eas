<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class VirusScanException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $infected = false, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
