<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
