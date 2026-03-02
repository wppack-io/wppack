<?php

declare(strict_types=1);

namespace WpPack\Component\Hook;

use WpPack\Component\Hook\Attribute\Condition\ConditionInterface;

/**
 * @internal
 */
final class RegisteredHook
{
    /** @var list<ConditionInterface> */
    private readonly array $conditions;

    /**
     * @param list<ConditionInterface> $conditions
     */
    public function __construct(
        public readonly Hook $hook,
        public readonly \Closure $callback,
        public readonly int $acceptedArgs,
        array $conditions = [],
    ) {
        $this->conditions = $conditions;
    }

    public function __invoke(mixed ...$args): mixed
    {
        foreach ($this->conditions as $condition) {
            if (!$condition->isSatisfied()) {
                return match ($this->hook->type) {
                    HookType::Filter => $args[0] ?? null,
                    HookType::Action => null,
                };
            }
        }

        return ($this->callback)(...$args);
    }
}
