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

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'scheduler')]
final class SchedulerPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'scheduler';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/scheduler', [
            'cronTotal' => (int) ($data['cron_total'] ?? 0),
            'cronOverdue' => (int) ($data['cron_overdue'] ?? 0),
            'asAvailable' => (bool) ($data['action_scheduler_available'] ?? false),
            'asVersion' => (string) ($data['action_scheduler_version'] ?? ''),
            'asPending' => (int) ($data['as_pending'] ?? 0),
            'asFailed' => (int) ($data['as_failed'] ?? 0),
            'asComplete' => (int) ($data['as_complete'] ?? 0),
            'cronDisabled' => (bool) ($data['cron_disabled'] ?? false),
            'alternateCron' => (bool) ($data['alternate_cron'] ?? false),
            'cronEvents' => $data['cron_events'] ?? [],
            'fmt' => $this->getFormatters(),
        ]);
    }
}
