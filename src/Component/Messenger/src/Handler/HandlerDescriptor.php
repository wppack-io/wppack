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

final class HandlerDescriptor
{
    /** @var \Closure(object): mixed */
    private readonly \Closure $handler;

    public function __construct(
        callable $handler,
        private readonly string $name = '',
    ) {
        $this->handler = $handler(...);
    }

    /**
     * @return \Closure(object): mixed
     */
    public function getHandler(): \Closure
    {
        return $this->handler;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
