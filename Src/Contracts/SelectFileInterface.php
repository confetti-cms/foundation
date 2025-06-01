<?php

declare(strict_types=1);

namespace ConfettiCms\Foundation\Contracts;

interface SelectFileInterface
{
    /**
     * @return \ConfettiCms\Parser\Components\Map[]
     */
    public function getOptions(): array;
}