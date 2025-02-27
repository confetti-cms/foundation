<?php

declare(strict_types=1);

namespace Confetti\Foundation;

use Confetti\Foundation\Exceptions\FileNotFoundException;
use Confetti\Foundation\Render\RenderService;
use ColinODell\Json5\Json5Decoder;
use ErrorException;

class Kernel
{
    private string $body = '';

    public function setEnvironmentSettings(): void
    {
        $envKey = $_ENV['APP_STAGE'] ?? throw new \RuntimeException("Environment stage is not set. (Missing APP_STAGE)");
        try {
            $content = file_get_contents(RenderService::CONFIG_FILE_PATH);
            $config = Json5Decoder::decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Failed to parse 'app_config.json5': " . $e->getMessage());
        }
        $envConfig = null;
        foreach ($config['environments'] as $envConfig) {
            if ($envConfig['name'] === $envKey) {
                break;
            }
        }
        if ($envConfig === null) {
            throw new \RuntimeException("Environment '$envKey' not found in 'app_config.json5'.");
        }
        $_ENV['ENV_CONFIG'] = $envConfig;
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
            $stage = $_ENV['APP_STAGE'] ?? throw new \RuntimeException("Environment stage is not set. (Missing APP_STAGE)");
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
        // Print the body and exit 0
        echo $this->body;
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
}
