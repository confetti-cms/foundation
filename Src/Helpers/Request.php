<?php

declare(strict_types=1);

namespace ConfettiCms\Foundation\Helpers;

class Request
{
    /**
     * For example, http or https
     */
    public function scheme(): string
    {
        return config('environment.local') ? 'http' : 'https';
    }

    /**
     * For example, example.com
     */
    public function host(): string
    {
        return $_SERVER['HTTP_HOST'];
    }

    /**
     * For example, /about
     */
    public function uri(): string
    {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    /**
     * Get a parameter from the query string
     *
     * For example, if the URL is http://example.com?name=John
     * then $request->parameter('name') will return 'John'
     */
    public function parameter(string $key): ?string
    {
        return $_GET[$key] ?? null;
    }

    public function cookie(string $key): ?string
    {
        return $_COOKIE[$key] ?? null;
    }
}
