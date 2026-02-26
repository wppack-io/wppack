<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer;

use WpPack\Component\Mailer\Exception\InvalidArgumentException;

final class Attachment
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $name = null,
        public readonly ?string $contentType = null,
        public readonly bool $inline = false,
    ) {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException(sprintf('Attachment file "%s" is not readable.', $path));
        }
    }
}
