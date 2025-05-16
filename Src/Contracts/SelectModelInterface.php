<?php

declare(strict_types=1);

namespace ConfettiCms\Foundation\Contracts;

interface SelectModelInterface
{
    /**
     * @return \Confetti\Parser\Components\Map|\ConfettiCms\Foundation\Model\RawFile|null Return type is mixed
     *         because the return value will be narrowed down in the parent class.
     */
    public function getSelected(): mixed;
}