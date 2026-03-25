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

namespace WpPack\Component\Scheduler\Message;

use WpPack\Component\Scheduler\Trigger\TriggerInterface;

interface ScheduledMessage
{
    public function getTrigger(): TriggerInterface;

    public function getMessage(): object;

    public function getName(): ?string;
}
