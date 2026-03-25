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

namespace WpPack\Component\Hook;

use WpPack\Component\Hook\Attribute\Condition\ConditionInterface;

final class HookDiscovery
{
    public function __construct(
        private readonly HookRegistry $registry,
    ) {}

    public function register(object $subscriber): void
    {
        $reflection = new \ReflectionClass($subscriber);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $hookAttributes = $method->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);

            if ($hookAttributes === []) {
                continue;
            }

            $conditionAttributes = $method->getAttributes(
                ConditionInterface::class,
                \ReflectionAttribute::IS_INSTANCEOF,
            );

            $conditions = array_map(
                static fn(\ReflectionAttribute $attr): ConditionInterface => $attr->newInstance(),
                $conditionAttributes,
            );

            /** @var \Closure $closure */
            $closure = $method->getClosure($subscriber);
            $acceptedArgs = $method->getNumberOfParameters();

            foreach ($hookAttributes as $hookAttribute) {
                /** @var Hook $hook */
                $hook = $hookAttribute->newInstance();

                $this->registry->add(new RegisteredHook(
                    $hook,
                    $closure,
                    $acceptedArgs,
                    $conditions,
                ));
            }
        }
    }
}
