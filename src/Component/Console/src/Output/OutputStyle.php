<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Output;

final class OutputStyle
{
    public function __construct(
        private readonly OutputInterface $output,
    ) {}

    public function success(string $message): void
    {
        if ($this->output instanceof WpCliOutput) {
            \WP_CLI::success($message);
            return;
        }

        $this->output->writeln('[SUCCESS] ' . $message);
    }

    public function error(string $message): void
    {
        if ($this->output instanceof WpCliOutput) {
            \WP_CLI::error($message);
            return;
        }

        $this->output->writeln('[ERROR] ' . $message);
    }

    public function warning(string $message): void
    {
        if ($this->output instanceof WpCliOutput) {
            \WP_CLI::warning($message);
            return;
        }

        $this->output->writeln('[WARNING] ' . $message);
    }

    public function info(string $message): void
    {
        if ($this->output instanceof WpCliOutput) {
            \WP_CLI::log($message);
            return;
        }

        $this->output->writeln('[INFO] ' . $message);
    }

    public function line(string $message): void
    {
        $this->output->writeln($message);
    }

    public function newLine(int $count = 1): void
    {
        $this->output->newLine($count);
    }

    /**
     * @param list<string>         $headers
     * @param list<list<scalar>>   $rows
     */
    public function table(array $headers, array $rows): void
    {
        if ($this->output instanceof WpCliOutput) {
            $items = [];
            foreach ($rows as $row) {
                $item = [];
                foreach ($headers as $i => $header) {
                    $item[$header] = $row[$i] ?? '';
                }
                $items[] = $item;
            }

            \WP_CLI\Utils\format_items('table', $items, $headers);
            return;
        }

        $this->output->writeln(implode("\t", $headers));
        foreach ($rows as $row) {
            $this->output->writeln(implode("\t", array_map(strval(...), $row)));
        }
    }

    public function progress(int $count, string $message = 'Processing'): ProgressBar
    {
        return new ProgressBar($this->output, $count, $message);
    }

    public function confirm(string $question, bool $default = false): bool
    {
        if ($this->output instanceof WpCliOutput) {
            return (bool) \cli\confirm($question, $default);
        }

        $this->output->writeln($question . ($default ? ' [Y/n]' : ' [y/N]'));

        return $default;
    }

    public function ask(string $question, ?string $default = null): string
    {
        if ($this->output instanceof WpCliOutput) {
            return (string) \cli\prompt($question, $default);
        }

        $prompt = $default !== null ? sprintf('%s [%s]', $question, $default) : $question;
        $this->output->writeln($prompt);

        return $default ?? '';
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }
}
