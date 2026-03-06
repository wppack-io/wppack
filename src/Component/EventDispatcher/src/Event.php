<?php

declare(strict_types=1);

namespace WpPack\Component\EventDispatcher;

use Psr\EventDispatcher\StoppableEventInterface;

class Event implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
