<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\Exception\ParameterNotFoundException;

final class ParameterNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function messageContainsParameterName(): void
    {
        $exception = new ParameterNotFoundException('bar');

        self::assertSame('Parameter "bar" not found.', $exception->getMessage());
    }

    #[Test]
    public function extendsInvalidArgumentException(): void
    {
        $exception = new ParameterNotFoundException('bar');

        self::assertInstanceOf(\InvalidArgumentException::class, $exception);
    }
}
