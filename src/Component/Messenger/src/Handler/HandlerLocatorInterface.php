<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Handler;

interface HandlerLocatorInterface
{
    /**
     * @return iterable<HandlerDescriptor>
     */
    public function getHandlers(object $message): iterable;
}
