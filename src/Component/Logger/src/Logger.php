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

use Psr\Log\AbstractLogger;
use WpPack\Component\Logger\Context\LoggerContext;
use WpPack\Component\Logger\Exception\InvalidArgumentException;
use WpPack\Component\Logger\Handler\HandlerInterface;

class Logger extends AbstractLogger
{
    /**
     * RFC 5424 severity levels.
     *
     * @var array<string, int>
     */
    private const LEVELS = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7,
    ];

    /** @var HandlerInterface[] */
    private array $handlers = [];

    /** @var array<string, mixed> */
    private array $persistentContext = [];

    public function __construct(
        private readonly string $name,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function pushHandler(HandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function withContext(LoggerContext $context): void
    {
        $this->persistentContext = array_merge($this->persistentContext, $context->all());
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $level = (string) $level;

        if (!isset(self::LEVELS[$level])) {
            throw new InvalidArgumentException(sprintf('Invalid log level "%s".', $level));
        }

        $context = array_merge($this->persistentContext, $context);
        $message = $this->interpolate((string) $message, $context);
        $context['_channel'] = $this->name;

        foreach ($this->handlers as $handler) {
            if ($handler->isHandling($level)) {
                $handler->handle($level, $message, $context);
            }
        }
    }

    public static function getLevelSeverity(string $level): int
    {
        if (!isset(self::LEVELS[$level])) {
            throw new InvalidArgumentException(sprintf('Invalid log level "%s".', $level));
        }

        return self::LEVELS[$level];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        $replacements = [];

        foreach ($context as $key => $value) {
            if (is_string($value) || $value instanceof \Stringable) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replacements);
    }
}
