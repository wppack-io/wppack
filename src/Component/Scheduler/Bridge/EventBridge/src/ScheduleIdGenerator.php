<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Scheduler\Bridge\EventBridge;

final class ScheduleIdGenerator
{
    private const MAX_LENGTH = 64;
    private const WPCRON_PREFIX = 'wpcron_';
    private const AS_PREFIX = 'as_';

    /**
     * Generate a deterministic schedule ID for a WP-Cron event.
     *
     * - Recurring: wpcron_{hook}_{md5(json(args))[0:8]}
     * - Single: wpcron_{hook}_{md5(json(args))[0:8]}_{timestamp}
     * - Overflow (>64 chars): wpcron_{md5(full_id)[0:32]}
     *
     * @param array<mixed> $args
     */
    public function forWpCronEvent(string $hook, array $args, string|false $schedule, int $timestamp): string
    {
        $argsHash = substr(md5(json_encode($args, \JSON_THROW_ON_ERROR)), 0, 8);

        if ($schedule !== false) {
            $id = self::WPCRON_PREFIX . $hook . '_' . $argsHash;
        } else {
            $id = self::WPCRON_PREFIX . $hook . '_' . $argsHash . '_' . $timestamp;
        }

        if (\strlen($id) > self::MAX_LENGTH) {
            $id = self::WPCRON_PREFIX . substr(md5($id), 0, 32);
        }

        return $id;
    }

    /**
     * Generate a deterministic schedule ID for an Action Scheduler action.
     *
     * Format: as_{md5(hook+json(args))[0:16]}_{actionId}
     * Overflow (>64 chars): as_{md5(full_id)[0:32]}
     *
     * @param array<mixed> $args
     */
    public function forActionScheduler(string $hook, array $args, int $actionId): string
    {
        $hash = substr(md5($hook . json_encode($args, \JSON_THROW_ON_ERROR)), 0, 16);
        $id = self::AS_PREFIX . $hash . '_' . $actionId;

        if (\strlen($id) > self::MAX_LENGTH) {
            $id = self::AS_PREFIX . substr(md5($id), 0, 32); // @codeCoverageIgnore
        }

        return $id;
    }
}
