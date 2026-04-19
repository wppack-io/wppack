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

namespace WPPack\Component\Hook;

use WPPack\Component\Hook\Exception\LogicException;

final class HookRegistry
{
    /** @var list<RegisteredHook> */
    private array $hooks = [];

    private bool $registered = false;

    public function add(RegisteredHook $registeredHook): self
    {
        if ($this->registered) {
            throw new LogicException('Cannot register hooks after register() has been called.');
        }

        $this->hooks[] = $registeredHook;

        return $this;
    }

    public function addAction(string $hook, callable $callback, int $priority = 10): self
    {
        $closure = $callback(...);
        $acceptedArgs = (new \ReflectionFunction($closure))->getNumberOfParameters();

        return $this->add(new RegisteredHook(
            new class ($hook, $priority) extends Hook {
                public function __construct(string $hook, int $priority)
                {
                    parent::__construct($hook, HookType::Action, $priority);
                }
            },
            $closure,
            $acceptedArgs,
        ));
    }

    public function addFilter(string $hook, callable $callback, int $priority = 10): self
    {
        $closure = $callback(...);
        $acceptedArgs = (new \ReflectionFunction($closure))->getNumberOfParameters();

        return $this->add(new RegisteredHook(
            new class ($hook, $priority) extends Hook {
                public function __construct(string $hook, int $priority)
                {
                    parent::__construct($hook, HookType::Filter, $priority);
                }
            },
            $closure,
            $acceptedArgs,
        ));
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        foreach ($this->hooks as $registeredHook) {
            $hook = $registeredHook->hook;
            $fn = match ($hook->type) {
                HookType::Action => 'add_action',
                HookType::Filter => 'add_filter',
            };

            $fn($hook->hook, $registeredHook, $hook->priority, $registeredHook->acceptedArgs);
        }
    }

    /**
     * @return list<RegisteredHook>
     */
    public function getActions(): array
    {
        return array_values(array_filter(
            $this->hooks,
            static fn(RegisteredHook $h): bool => $h->hook->type === HookType::Action,
        ));
    }

    /**
     * @return list<RegisteredHook>
     */
    public function getFilters(): array
    {
        return array_values(array_filter(
            $this->hooks,
            static fn(RegisteredHook $h): bool => $h->hook->type === HookType::Filter,
        ));
    }

    /**
     * @return list<RegisteredHook>
     */
    public function all(): array
    {
        return $this->hooks;
    }
}
