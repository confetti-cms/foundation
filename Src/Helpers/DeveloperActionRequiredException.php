<?php

declare(strict_types=1);

namespace ConfettiCms\Foundation\Helpers;

use RuntimeException;
use Throwable;

class DeveloperActionRequiredException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, Throwable $previous = null)
    {
        parent::__construct(message: "Developer action required: " . $message, code: $code, previous: $previous);
    }
}