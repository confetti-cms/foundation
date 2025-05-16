<?php

declare(strict_types=1);

namespace ConfettiCms\Foundation\Helpers;

readonly class ComponentEntity
{
    public function __construct(
        public ?string      $key,
        public string       $generates, // For example: \model\homepage\title::class
        public string       $type,
        public array        $decorations,
        public ?string      $preview,
        public SourceEntity $source,
    )
    {

    }

    public function hasDecoration(string $key): bool
    {
        if ($this->getDecoration($key) !== null) {
            return true;
        }
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDecorations(): array
    {
        return $this->decorations;
    }

    /**
     * @param string|null $parameter to search deep, you can use dot notation: `size.min`
     */
    public function getDecoration(string $method, ?string $parameter = null): mixed
    {
        $data = $this->decorations[$method] ?? null;
        if ($data === null) {
            return null;
        }

        if ($parameter === null) {
            return $data;
        }

        // Search with parameter
        $parameters = explode('.', $parameter);
        foreach ($parameters as $parameter) {
            if (isset($data[$parameter])) {
                $data = $data[$parameter];
            } else {
                return null;
            }
        }
        return $data;
    }

    public function getLabel(): string
    {
        $label = $this->getDecoration('label', 'label');
        if ($label) {
            return $label;
        }
        return titleByKey($this->key);
    }

    public function dumpDecorations(): void
    {
        foreach ($this->decorations as $decoration) {
            echo '<pre>' . var_export($decoration->type, true) . '</pre>';
            echo '<pre>' . var_export($decoration->data, true) . '</pre>';
        }
    }
}
