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

namespace WPPack\Component\Hook\Tests\Attribute\Privacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Hook\Attribute\Privacy\Action\WpPrivacyPersonalDataExportFileAction;
use WPPack\Component\Hook\Attribute\Privacy\Action\WpPrivacyPersonalDataExportFileCreatedAction;
use WPPack\Component\Hook\HookType;

#[CoversClass(WpPrivacyPersonalDataExportFileAction::class)]
#[CoversClass(WpPrivacyPersonalDataExportFileCreatedAction::class)]
final class NamedHookTest extends TestCase
{
    #[Test]
    public function wpPrivacyPersonalDataExportFileActionHasCorrectHookName(): void
    {
        $action = new WpPrivacyPersonalDataExportFileAction();

        self::assertSame('wp_privacy_personal_data_export_file', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
        self::assertInstanceOf(Action::class, $action);
    }

    #[Test]
    public function wpPrivacyPersonalDataExportFileActionAcceptsCustomPriority(): void
    {
        $action = new WpPrivacyPersonalDataExportFileAction(priority: 20);

        self::assertSame(20, $action->priority);
    }

    #[Test]
    public function wpPrivacyPersonalDataExportFileCreatedActionHasCorrectHookName(): void
    {
        $action = new WpPrivacyPersonalDataExportFileCreatedAction();

        self::assertSame('wp_privacy_personal_data_export_file_created', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertInstanceOf(Action::class, $action);
    }
}
