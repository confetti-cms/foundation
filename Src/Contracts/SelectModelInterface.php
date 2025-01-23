<?php

declare(strict_types=1);

namespace Confetti\Foundation\Contracts;

interface SelectModelInterface
{
    /**
     * @return \Confetti\Parser\Components\Map|\Confetti\Foundation\Model\RawFile|null Return type is mixed
     *         because the return value will be narrowed down in the parent class.
     */
    public function getSelected(): mixed;
}