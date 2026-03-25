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
