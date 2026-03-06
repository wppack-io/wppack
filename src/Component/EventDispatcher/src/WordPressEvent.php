<?php

declare(strict_types=1);

namespace WpPack\Component\EventDispatcher;

class WordPressEvent extends Event
{
    public const string HOOK_NAME = '';

    /** @var array<string, int> Argument name to index mapping */
    protected array $argMap = [];

    /**
     * @param list<mixed> $args
     */
    public function __construct(
        public readonly string $hookName,
        public readonly array $args,
    ) {}

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
