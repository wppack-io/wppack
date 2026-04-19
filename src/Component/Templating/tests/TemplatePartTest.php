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

namespace WPPack\Component\Templating\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Templating\TemplatePart;

final class TemplatePartTest extends TestCase
{
    #[Test]
    public function renderCallsGetTemplatePart(): void
    {
        // get_template_part is a WordPress function that outputs template content.
        // This test verifies the method is callable without errors.
        ob_start();
        TemplatePart::render('nonexistent-template-slug');
        $output = ob_get_clean();

        // get_template_part returns empty for nonexistent templates
        self::assertSame('', $output);
    }

    #[Test]
    public function captureReturnsString(): void
    {
        $result = TemplatePart::capture('nonexistent-template-slug');

        self::assertIsString($result);
    }
}
