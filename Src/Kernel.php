<?php

declare(strict_types=1);

namespace ConfettiCms\Foundation;

use ConfettiCms\Foundation\Exceptions\FileNotFoundException;
use ConfettiCms\Foundation\Render\RenderService;
use ColinODell\Json5\Json5Decoder;
use ErrorException;

class Kernel
{
    private string $body = '';

    private string $uri;

    public function __construct()
    {
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
    }

    public function setEnvironmentSettings(): void
    {
        $envKey = config('environment.stage') ?? throw new \RuntimeException("Config 'environments[*].stage' not found in config.json5.");
        try {
            $content = file_get_contents(RenderService::CONFIG_FILE_PATH);
            $config = Json5Decoder::decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Failed to parse 'config.json5': " . $e->getMessage());
        }
        $envConfig = null;
        foreach ($config['environments'] as $envConfig) {
            if ($envConfig['name'] === $envKey) {
                break;
            }
        }
        if ($envConfig === null) {
            throw new \RuntimeException('{"environment":{"stage": "' . $envKey . '"}} not found in \'config.json5\'.');
        }
    }

    public function run(): void
    {
        try {
            // trim / from the beginning of the uri
            $uri = trim($_SERVER["REQUEST_URI"], "/");

            $render     = (new RenderService());
            $this->body = $render->renderUrl($uri);
        } catch (FileNotFoundException $e) {
            http_response_code(404);
            $this->body = $e->getMessage();
        } catch (\Throwable|\TypeError|\ValueError $e) {
            $stage = config('environment.stage') ?? throw new \RuntimeException("Can't get 'environments[*].stage' from config.json5.");
            $render = (new RenderService());
            echo $render->renderLocalView('website.layouts.exception', ['exception' => $e, 'env' => $stage]);
            http_response_code(500);
            exit(1);
        }
        $this->printResponse();
    }

    // Print response
    public function printResponse(): void
    {
        // Enable Gzip only for HTML, JS, MJS, and CSS
        if ($this->shouldGzipResponse()) {
            if (!ob_start("ob_gzhandler")) {
                ob_start();
            }
        } else {
            ob_start();
        }

        echo $this->body;
        ob_end_flush(); // Flush output
        exit(0);
    }

    public function phpIniSettings(): void
    {
        // Set the maximum length of exception string parameters
        // `->evaluatePath('/var/www...` to `->evaluatePath('/var/www/cache/admin.index.blade.php')`
        ini_set('zend.exception_string_param_max_len', '200');
        ini_set('error_reporting', E_ALL);
        set_error_handler(/**
         * @throws \ErrorException
         */ function ($severity, $message, $filename, $line) {
            // error was suppressed with the @-operator
            if (0 === error_reporting()) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $filename, $line);
        });
    }

    private function shouldGzipResponse(): bool
    {
        // Extract file extension from the URI
        $extension = pathinfo(parse_url($this->uri, PHP_URL_PATH), PATHINFO_EXTENSION);

        // List of file types that should be gzipped
        $gzipExtensions = ['html', 'js', 'mjs', 'css'];

        return in_array($extension, $gzipExtensions, true);
    }
}
