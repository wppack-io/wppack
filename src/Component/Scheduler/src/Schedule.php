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
