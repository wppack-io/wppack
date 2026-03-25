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

namespace WpPack\Component\EventDispatcher;

class WordPressEvent extends Event
{
    public const HOOK_NAME = '';

    /** @var array<string, int> Argument name to index mapping */
    protected array $argMap = [];

    /**
     * The filter return value. Listeners can modify this to change the
     * value returned by apply_filters(). Initialized to args[0].
     */
    public mixed $filterValue;

    /**
     * @param list<mixed> $args
     */
    public function __construct(
        public readonly string $hookName,
        public readonly array $args,
    ) {
        $this->filterValue = $args[0] ?? null;
    }

    /**
     * Proxy getXxx() calls to $this->args via $argMap.
     *
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (str_starts_with($name, 'get')) {
            $key = lcfirst(substr($name, 3));

            if (isset($this->argMap[$key]) && \array_key_exists($this->argMap[$key], $this->args)) {
                return $this->args[$this->argMap[$key]];
            }
        }

        throw new \BadMethodCallException(sprintf('Method %s::%s() does not exist.', static::class, $name));
    }
}
