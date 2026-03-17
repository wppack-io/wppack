<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Exception;

final class ObjectNotFoundException extends StorageException
{
    public function __construct(string $key, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Object not found: "%s".', $key), 0, $previous);
    }
}
