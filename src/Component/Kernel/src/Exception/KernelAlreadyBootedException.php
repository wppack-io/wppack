<?php

declare(strict_types=1);

namespace WpPack\Component\Kernel\Exception;

class KernelAlreadyBootedException extends \LogicException implements ExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Kernel has already been booted.');
    }
}
