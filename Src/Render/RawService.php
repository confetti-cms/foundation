<?php

declare(strict_types=1);

namespace Confetti\Foundation\Render;

class RawService implements RenderInterface
{
    // All file extensions that are supported by the raw service
    public const RAW_FILE_EXTENSIONS = [
        'css'         => 'text/css',
        'eot'         => 'application/vnd.ms-fontobject',
        'gif'         => 'image/gif',
        'htm'         => 'text/html',
        'html'        => 'text/html',
        'ico'         => 'image/ico',
        'jpeg'        => 'image/jpeg',
        'jpg'         => 'image/jpeg',
        'js'          => 'application/javascript',
        'mjs'         => 'application/javascript',
        'pdf'         => 'application/pdf',
        'png'         => 'image/png',
        'svg'         => 'image/svg+xml',
        'ttf'         => 'application/font-sfnt',
        'webmanifest' => 'application/manifest+json',
        'webp'        => 'image/webp',
        'woff'        => 'application/font-woff',
        'woff2'       => 'application/font-woff2',
    ];

    public function __construct(
        private readonly string  $repository,
        private readonly ?string $pathPrefix = null,
    )
    {
    }

    public function isCapable(string $uri): bool
    {
        if ($this->pathPrefix !== null && !str_starts_with($uri, $this->pathPrefix . '/')) {
            return false;
        }
        $extensions       = array_keys(self::RAW_FILE_EXTENSIONS);
        $currentExtension = $this->getCurrentExtension($uri);

        return in_array($currentExtension, $extensions, true);
    }

    /**
     * @throws \Exception
     */
    public function renderByUrl(string $uri): string
    {
        // Get and return content of file. For this, we remove the object prefix
        $uri = preg_replace('/^' . $this->pathPrefix . '\//', '', $uri, 1);

        // Get the content of the file (and suppress 'No such file or directory')
        $content = @file_get_contents($this->repository . '/' . $uri);
        if ($content !== false) {
            // Get the content type by file extension
            $contentType = $this->getContentType($uri);
            // Set the content type header
            header('Content-Type: ' . $contentType);
            // Set the cache control header, so the browser caches the file for 30 days
            header('Cache-Control: public, no-transform');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + (30 * 86.400)) . ' GMT');
            return $content;
        }
        // set status of response
        http_response_code(404);
        return '404 - File not found';
    }

    // Get header content type by file extension, use the current constants
    public function getContentType(string $uri): string
    {
        $extension = $this->getCurrentExtension($uri);
        return self::RAW_FILE_EXTENSIONS[$extension] ?? 'application/octet-stream';
    }

    private function getCurrentExtension(string $uri): string
    {
        $currentExtension = pathinfo($uri, PATHINFO_EXTENSION);
        return strtolower($currentExtension);
    }
}
