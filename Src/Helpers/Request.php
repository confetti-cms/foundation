<?php

declare(strict_types=1);

namespace Confetti\Foundation\Helpers;

class Request
{
    public function host(): string
    {
        return $_SERVER['HTTP_HOST'];
    }

    public function uri(): string
    {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    public function cookie(string $key): ?string
    {
        return $_COOKIE[$key] ?? null;
    }

    public function parameter(string $key): ?string
    {
        return $_GET[$key] ?? null;
    }
}
