<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Exception;

final class ObjectNotFoundException extends StorageException
{
    public function __construct(string $path, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Object not found: "%s".', $path), 0, $previous);
    }
}
