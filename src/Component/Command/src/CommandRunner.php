<?php

declare(strict_types=1);

namespace WpPack\Component\Command;

use WpPack\Component\Command\Input\WpCliInput;
use WpPack\Component\Command\Output\OutputStyle;
use WpPack\Component\Command\Output\WpCliOutput;

final class CommandRunner
{
    public function __construct(
        private readonly AbstractCommand $command,
    ) {}

    /**
     * WP-CLI callback. Receives $args/$assocArgs and delegates to execute().
     *
     * @param list<string>          $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $input = new WpCliInput($this->command->getDefinition(), $args, $assocArgs);
        $output = new OutputStyle(new WpCliOutput());

        $exitCode = $this->command->run($input, $output);

        if ($exitCode !== AbstractCommand::SUCCESS) {
            \WP_CLI::halt($exitCode); // @phpstan-ignore class.notFound
        }
    }
}
