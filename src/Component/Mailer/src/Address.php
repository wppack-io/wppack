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

final class Address
{
    public readonly string $address;
    public readonly string $name;

    public function __construct(string $address, string $name = '')
    {
        // Parse "Name <email>" format
        if ($name === '' && preg_match('/^(.+?)\s*<([^>]+)>$/', $address, $matches)) {
            $address = trim($matches[2]);
            $name = trim($matches[1], " \t\n\r\x0B\"");
        }

        if (preg_match('/[\r\n\0]/', $address . $name)) {
            throw new InvalidArgumentException('Address contains invalid control characters.');
        }

        if (false === filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid email address.', $address));
        }

        $this->address = $address;
        $this->name = $name;
    }

    public function toString(): string
    {
        if ($this->name === '') {
            return $this->address;
        }

        return sprintf('"%s" <%s>', str_replace(['\\', '"'], ['\\\\', '\\"'], $this->name), $this->address);
    }
}
