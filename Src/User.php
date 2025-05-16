<?php

namespace ConfettiCms\Foundation;

class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly string $name,
        public readonly string $pictureUrl,
    )
    {
    }
}