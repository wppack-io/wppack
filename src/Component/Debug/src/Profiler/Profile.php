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

namespace WpPack\Component\Debug\Profiler;

use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\Exception\InvalidArgumentException;

final class Profile
{
    /** @var array<string, DataCollectorInterface> */
    private array $collectors = [];

    private string $url = '';
    private string $method = 'GET';
    private int $statusCode = 200;

    public function __construct(
        private readonly string $token = '',
    ) {}

    public function addCollector(DataCollectorInterface $collector): void
    {
        $this->collectors[$collector->getName()] = $collector;
    }

    public function getCollector(string $name): DataCollectorInterface
    {
        if (!isset($this->collectors[$name])) {
            throw new InvalidArgumentException(sprintf('Collector "%s" does not exist.', $name));
        }

        return $this->collectors[$name];
    }

    /**
     * @return array<string, DataCollectorInterface>
     */
    public function getCollectors(): array
    {
        return $this->collectors;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getTime(): float
    {
        $requestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

        return (microtime(true) - $requestTime) * 1000;
    }
}
