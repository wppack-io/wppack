<?php

declare(strict_types=1);

namespace WpPack\Component\Command;

use WpPack\Component\Command\Exception\LogicException;

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

        $this->registered = true;

        if (!class_exists(\WP_CLI::class, false)) {
            return;
        }

        foreach ($this->commands as $command) {
            $attribute = $command::getCommandAttribute();

            $args = [
                'shortdesc' => $attribute->description,
                'synopsis' => $command->getDefinition()->toSynopsis(),
            ];

            if ($attribute->hidden) {
                $args['when'] = 'before_wp_load';
            }

            \WP_CLI::add_command($attribute->name, new CommandRunner($command), $args);
        }
    }

    /** @return list<AbstractCommand> */
    public function all(): array
    {
        return $this->commands;
    }
}
