<?php

namespace ConfettiCms\Foundation\Render;

interface RenderInterface
{
    public function isCapable(string $uri): bool;
    public function renderByUrl(string $uri): string;
}
