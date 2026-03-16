<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Middleware;

interface StackInterface
{
    public function next(): MiddlewareInterface;
}
