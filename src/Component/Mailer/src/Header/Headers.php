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

namespace WpPack\Component\Mailer\Header;

use WpPack\Component\Mailer\Exception\InvalidArgumentException;

final class Headers
{
    /** @var array<string, list<string>> Keys are stored in lowercase. */
    private array $headers = [];

    /** @var array<string, string> Maps lowercase key to original-case name. */
    private array $originalNames = [];

    public function add(string $name, string $value): void
    {
        if (preg_match('/[\r\n\0]/', $name . $value)) {
            throw new InvalidArgumentException('Header contains invalid control characters.');
        }

        $key = strtolower($name);
        $this->headers[$key][] = $value;
        $this->originalNames[$key] ??= $name;
    }

    public function get(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /**
     * @return array<string, list<string>> Keys are in original case.
     */
    public function all(): array
    {
        $result = [];
        foreach ($this->headers as $key => $values) {
            $result[$this->originalNames[$key]] = $values;
        }

        return $result;
    }

    public function has(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function remove(string $name): void
    {
        $key = strtolower($name);
        unset($this->headers[$key], $this->originalNames[$key]);
    }
}
