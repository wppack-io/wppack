<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Logger\Bridge\Monolog;

use Monolog\Handler\HandlerInterface;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\ProcessorInterface;
use Psr\Log\LoggerInterface;

final class MonologLoggerFactory
{
    /** @var array<string, MonologLogger> */
    private array $loggers = [];

    /**
     * @param HandlerInterface[]  $defaultHandlers
     * @param ProcessorInterface[] $defaultProcessors
     */
    public function __construct(
        private readonly array $defaultHandlers = [],
        private readonly array $defaultProcessors = [],
    ) {}

    public function create(string $name): LoggerInterface
    {
        if (isset($this->loggers[$name])) {
            return $this->loggers[$name];
        }

        $logger = new MonologLogger($name);

        foreach ($this->defaultHandlers as $handler) {
            $logger->pushHandler($handler);
        }

        foreach ($this->defaultProcessors as $processor) {
            $logger->pushProcessor($processor);
        }

        $this->loggers[$name] = $logger;

        return $logger;
    }
}
