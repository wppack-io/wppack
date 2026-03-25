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

namespace WpPack\Component\Messenger\Handler;

final class HandlerLocator implements HandlerLocatorInterface
{
    /** @var array<class-string, list<HandlerDescriptor>> */
    private array $handlers = [];

    /**
     * @param array<class-string, list<callable>> $handlers message class => callables
     */
    public function __construct(array $handlers = [])
    {
        foreach ($handlers as $messageClass => $callables) {
            foreach ($callables as $callable) {
                $this->addHandler($messageClass, $callable);
            }
        }
    }

    public function addHandler(string $messageClass, callable $handler, string $name = ''): void
    {
        if ($name === '' && is_array($handler) && \count($handler) >= 2) {
            $name = (is_object($handler[0]) ? $handler[0]::class : $handler[0]) . '::' . $handler[1];
        } elseif ($name === '' && $handler instanceof \Closure) {
            $name = 'Closure';
        } elseif ($name === '' && is_string($handler)) {
            $name = $handler;
        } elseif ($name === '' && is_object($handler)) {
            $name = $handler::class;
        }

        $this->handlers[$messageClass][] = new HandlerDescriptor($handler, $name);
    }

    /**
     * @return iterable<HandlerDescriptor>
     */
    public function getHandlers(object $message): iterable
    {
        $matched = $this->handlers[$message::class] ?? [];

        foreach (class_parents($message) ?: [] as $parent) {
            if (isset($this->handlers[$parent])) {
                $matched = [...$matched, ...$this->handlers[$parent]];
            }
        }

        foreach (class_implements($message) ?: [] as $interface) {
            if (isset($this->handlers[$interface])) {
                $matched = [...$matched, ...$this->handlers[$interface]];
            }
        }

        return $matched;
    }
}
