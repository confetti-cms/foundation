<?php

declare(strict_types=1);

namespace ConfettiCms\Foundation\Helpers;


use Stringable;

class SourceEntity implements Stringable
{
    public function __construct(
        public readonly string $directory,
        public readonly string $file,
        public readonly int    $line,
        public readonly int    $from,
        public readonly int    $to,
    )
    {

    }

    public function getPath(): string
    {
        return '/' . $this->directory . '/' . $this->file;
    }

    public function __toString(): string
    {
        return $this->directory . '/' . $this->file . ':' . $this->line;
    }
}
