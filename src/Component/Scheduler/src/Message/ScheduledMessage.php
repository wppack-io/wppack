<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Message;

use WpPack\Component\Scheduler\Trigger\TriggerInterface;

interface ScheduledMessage
{
    public function getTrigger(): TriggerInterface;

    public function getMessage(): object;

    public function getName(): ?string;
}
