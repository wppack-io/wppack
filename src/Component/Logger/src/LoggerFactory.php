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

namespace WpPack\Component\Logger;

use WpPack\Component\Logger\Handler\HandlerInterface;

final class LoggerFactory
{
    /** @var array<string, Logger> */
    private array $loggers = [];

    /**
     * @param HandlerInterface[] $defaultHandlers
     */
    public function __construct(
        private readonly array $defaultHandlers = [],
    ) {}

    public function create(string $name): Logger
    {
        if (isset($this->loggers[$name])) {
            return $this->loggers[$name];
        }

        $logger = new Logger($name);

        foreach ($this->defaultHandlers as $handler) {
            $logger->pushHandler($handler);
        }

        $this->loggers[$name] = $logger;

        return $logger;
    }
}
