<?php

declare(strict_types=1);

namespace WpPack\Component\Command\Output;

final class ProgressBar
{
    private int $current = 0;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly int $total,
        private readonly string $message = 'Processing',
    ) {
        $this->display();
    }

    public function advance(int $step = 1): void
    {
        $this->current += $step;
        $this->display();
    }

    public function finish(): void
    {
        $this->current = $this->total;
        $this->display();
        $this->output->newLine();
    }

    public function getCurrent(): int
    {
        return $this->current;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    private function display(): void
    {
        $percent = $this->total > 0 ? (int) floor(($this->current / $this->total) * 100) : 0;
        $this->output->writeln(sprintf('%s: %d/%d (%d%%)', $this->message, $this->current, $this->total, $percent));
    }
}
