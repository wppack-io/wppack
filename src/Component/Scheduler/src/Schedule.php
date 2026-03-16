<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler;

use WpPack\Component\Scheduler\Message\ScheduledMessage;

final class Schedule
{
    /** @var list<ScheduledMessage> */
    private array $messages = [];

    public function add(ScheduledMessage $message): self
    {
        $this->messages[] = $message;

        return $this;
    }

    /** @return list<ScheduledMessage> */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
