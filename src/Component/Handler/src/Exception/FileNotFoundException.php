<?php

declare(strict_types=1);

namespace WpPack\Component\Handler\Exception;

class FileNotFoundException extends HandlerException
{
    public function __construct(string $path)
    {
        parent::__construct(\sprintf('File not found: %s', $path), 404);
    }
}
