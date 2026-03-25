<?php

declare(strict_types=1);

namespace WpPack\Component\Handler\Tests\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Handler\Exception\ExceptionInterface;
use WpPack\Component\Handler\Exception\FileNotFoundException;
use WpPack\Component\Handler\Exception\HandlerException;
use WpPack\Component\Handler\Exception\SecurityException;

final class ExceptionTest extends TestCase
{
    #[Test]
    public function handlerExceptionImplementsInterface(): void
    {
        $e = new HandlerException('test');
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function securityExceptionHas403Code(): void
    {
        $e = new SecurityException('Access denied');
        self::assertInstanceOf(HandlerException::class, $e);
        self::assertSame(403, $e->getCode());
        self::assertSame('Access denied', $e->getMessage());
    }

    #[Test]
    public function fileNotFoundExceptionHas404Code(): void
    {
        $e = new FileNotFoundException('/missing.php');
        self::assertInstanceOf(HandlerException::class, $e);
        self::assertSame(404, $e->getCode());
        self::assertStringContainsString('/missing.php', $e->getMessage());
    }
}
