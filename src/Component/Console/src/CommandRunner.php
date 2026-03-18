<?php

declare(strict_types=1);

namespace WpPack\Component\Console;

use WpPack\Component\Console\Input\WpCliInput;
use WpPack\Component\Console\Output\OutputStyle;
use WpPack\Component\Console\Output\WpCliOutput;

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
            \WP_CLI::halt($exitCode);
        }
    }
}
