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

namespace WpPack\Component\Rest\Attribute;

use WpPack\Component\Rest\HttpMethod;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class RestRoute
{
    /** @var list<string> */
    public readonly array $methods;

    /**
     * @param HttpMethod|string|list<HttpMethod|string> $methods
     * @param array<string, string> $requirements
     */
    public function __construct(
        public readonly string $route = '',
        HttpMethod|string|array $methods = [],
        public readonly ?string $namespace = null,
        public readonly string $name = '',
        public readonly array $requirements = [],
    ) {
        if (!is_array($methods)) {
            $methods = [$methods];
        }

        $this->methods = array_map(
            static fn(HttpMethod|string $method): string => strtoupper($method instanceof HttpMethod ? $method->value : $method),
            $methods,
        );
    }
}
