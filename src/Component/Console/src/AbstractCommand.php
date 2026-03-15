<?php

declare(strict_types=1);

namespace WpPack\Component\Console;

use WpPack\Component\Console\Attribute\AsCommand;
use WpPack\Component\Console\Exception\LogicException;
use WpPack\Component\Console\Input\InputDefinition;
use WpPack\Component\Console\Input\InputInterface;
use WpPack\Component\Console\Output\OutputStyle;

abstract class AbstractCommand
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const INVALID = 2;

    private ?InputDefinition $definition = null;

    protected function configure(InputDefinition $definition): void {}

    abstract protected function execute(InputInterface $input, OutputStyle $output): int;

    /** @internal */
    public function run(InputInterface $input, OutputStyle $output): int
    {
        return $this->execute($input, $output);
    }

    /** @internal */
    public function getDefinition(): InputDefinition
    {
        if ($this->definition === null) {
            $this->definition = new InputDefinition();
            $this->configure($this->definition);
        }

        return $this->definition;
    }

    /** @internal */
    public static function getCommandAttribute(): AsCommand
    {
        $reflection = new \ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(AsCommand::class);

        if ($attributes === []) {
            throw new LogicException(sprintf(
                'The command class "%s" must have the #[AsCommand] attribute.',
                static::class,
            ));
        }

        return $attributes[0]->newInstance();
    }
}
