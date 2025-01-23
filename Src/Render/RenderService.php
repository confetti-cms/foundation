<?php

declare(strict_types=1);

namespace Confetti\Foundation\Render;

class RenderService
{
    public const ROOT_PATH = '/src';
    public const REPOSITORY_PATH = '/src';
    public const CONFIG_FILE_PATH = '/src/app_config.json5';
    public const SHARED_RESOURCES = '/var/resources';
    private const CACHE_DIR = '/var/www/cache';
    /**
     * @var RenderInterface[]
     */
    private array $services;

    public function __construct(
    )
    {
        $this->services = [
            new RawService(self::SHARED_RESOURCES, 'resources'),
            new RawService(self::REPOSITORY_PATH),
            new BladeOneService(self::REPOSITORY_PATH, self::CACHE_DIR),
        ];
    }

    /**
     * @throws \Exception
     */
    public function renderUrl(string $uri): string
    {
        foreach ($this->services as $service) {
            if ($service->isCapable($uri)) {
                return $service->renderByUrl($uri);
            }
        }
        http_response_code(404);
        return '404 Not Found';
    }

    public function renderLocalView(string $view, $variables = []): string
    {
        $service = new BladeOneService(self::ROOT_PATH, self::CACHE_DIR);
        return $service->renderByView($view, $variables);
    }
}
