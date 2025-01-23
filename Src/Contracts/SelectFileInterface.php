<?php

declare(strict_types=1);

namespace Confetti\Foundation\Contracts;

interface SelectFileInterface
{
    /**
     * @return \Confetti\Parser\Components\Map[]
     */
    public function getOptions(): array;
}