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

namespace WPPack\Component\Templating\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Templating\Exception\ExceptionInterface;
use WPPack\Component\Templating\Exception\RenderingException;
use WPPack\Component\Templating\Exception\TemplateNotFoundException;

#[CoversClass(RenderingException::class)]
#[CoversClass(TemplateNotFoundException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function renderingExceptionIsRuntimeException(): void
    {
        $e = new RenderingException('render failed');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('render failed', $e->getMessage());
    }

    #[Test]
    public function templateNotFoundFormatsTemplateName(): void
    {
        $previous = new \RuntimeException('inner');
        $e = new TemplateNotFoundException('missing.php', $previous);

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('Template "missing.php" not found.', $e->getMessage());
        self::assertSame($previous, $e->getPrevious());
    }
}
