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

namespace WpPack\Component\Console;

use WpPack\Component\Console\Exception\LogicException;

final class CommandRegistry
{
    /** @var list<AbstractCommand> */
    private array $commands = [];

    private bool $registered = false;

    public function add(AbstractCommand $command): void
    {
        if ($this->registered) {
            throw new LogicException('Cannot add commands after register() has been called.');
        }

        $this->commands[] = $command;
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        if (!class_exists(\WP_CLI::class, false)) {
            $this->registered = true;

            return;
        }

        foreach ($this->commands as $command) {
            $attribute = $command::getCommandAttribute();

            $args = [
                'shortdesc' => $attribute->description,
                'synopsis' => $command->getDefinition()->toSynopsis(),
            ];

            if ($attribute->usage !== '') {
                $args['longdesc'] = $attribute->usage;
            }

            \WP_CLI::add_command($attribute->name, new CommandRunner($command), $args);
        }

        $this->registered = true;
    }

    /** @return list<AbstractCommand> */
    public function all(): array
    {
        return $this->commands;
    }
}
