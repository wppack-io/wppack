<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Exception;

use Psr\Container\NotFoundExceptionInterface;

final class ServiceNotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Service "%s" not found.', $id));
    }
}
