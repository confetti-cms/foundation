<?php

declare(strict_types=1);

namespace Confetti\Foundation\Model;

use Confetti\Foundation\Helpers\ComponentEntity;
use Confetti\Foundation\Helpers\SourceEntity;

readonly class RawFile
{
    public function __construct(
        public string $path,
    ) {
    }

    public function getComponent(): ComponentEntity
    {
        return new ComponentEntity(
            null,
            self::class,
            'file',
            [],
            null,
            new SourceEntity(
                dirname($this->path),
                basename($this->path),
                0,
                0,
                0,
            ),
        );
    }

    // Make from the file name a label
    public function getLabel(): string
    {
        // Only the file name without the extension
        $label = pathinfo($this->path, PATHINFO_FILENAME);
        // Replace underscores and dashes with spaces
        $label = str_replace(['_', '-'], ' ', $label);
        // Uppercase the first letter of each word
        return ucwords($label);
    }

    public function getChildren(): array
    {
        return [];
    }
}
