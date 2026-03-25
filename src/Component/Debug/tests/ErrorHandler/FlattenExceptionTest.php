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

namespace WpPack\Component\Debug\Tests\ErrorHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\ErrorHandler\FlattenException;

final class FlattenExceptionTest extends TestCase
{
    #[Test]
    public function createFromThrowableHasCorrectClassMessageCodeFileLine(): void
    {
        $exception = new \RuntimeException('Something went wrong', 42);

        $flat = FlattenException::createFromThrowable($exception);

        self::assertSame(\RuntimeException::class, $flat->getClass());
        self::assertSame('Something went wrong', $flat->getMessage());
        self::assertSame(42, $flat->getCode());
        self::assertSame(__FILE__, $flat->getFile());
        self::assertSame(__LINE__ - 8, $flat->getLine());
    }

    #[Test]
    public function createFromThrowableDefaultStatusCodeIs500(): void
    {
        $exception = new \InvalidArgumentException('bad arg');

        $flat = FlattenException::createFromThrowable($exception);

        self::assertSame(500, $flat->getStatusCode());
    }

    #[Test]
    public function createFromThrowablePreservesStatusCodeFromHttpException(): void
    {
        $exception = new TestHttpException('Not Found', 404);

        $flat = FlattenException::createFromThrowable($exception);

        self::assertSame(404, $flat->getStatusCode());
    }

    #[Test]
    public function getTraceReturnsArrayOfFrames(): void
    {
        $exception = $this->createNestedCallException();

        $flat = FlattenException::createFromThrowable($exception);

        self::assertIsArray($flat->getTrace());
        self::assertNotEmpty($flat->getTrace());
    }

    #[Test]
    public function getTraceFramesHaveRequiredKeys(): void
    {
        $exception = new \RuntimeException('trace test');

        $flat = FlattenException::createFromThrowable($exception);
        $trace = $flat->getTrace();

        self::assertNotEmpty($trace);

        $requiredKeys = ['file', 'line', 'function', 'class', 'type', 'args', 'code_context', 'highlight_line'];
        foreach ($trace as $frame) {
            foreach ($requiredKeys as $key) {
                self::assertArrayHasKey($key, $frame, "Trace frame is missing key: {$key}");
            }
        }
    }

    #[Test]
    public function getChainForSingleExceptionHasLengthOne(): void
    {
        $exception = new \RuntimeException('single');

        $flat = FlattenException::createFromThrowable($exception);

        self::assertCount(1, $flat->getChain());
        self::assertSame(\RuntimeException::class, $flat->getChain()[0]['class']);
        self::assertSame('single', $flat->getChain()[0]['message']);
    }

    #[Test]
    public function getChainForExceptionWithPreviousHasMultipleEntries(): void
    {
        $previous = new \InvalidArgumentException('root cause');
        $exception = new \RuntimeException('wrapper', 0, $previous);

        $flat = FlattenException::createFromThrowable($exception);
        $chain = $flat->getChain();

        self::assertGreaterThanOrEqual(2, count($chain));
        self::assertSame(\RuntimeException::class, $chain[0]['class']);
        self::assertSame('wrapper', $chain[0]['message']);
        self::assertSame(\InvalidArgumentException::class, $chain[1]['class']);
        self::assertSame('root cause', $chain[1]['message']);
    }

    #[Test]
    public function traceFramesIncludeCodeContextWhenFileIsReadable(): void
    {
        // Create exception from this file (which is readable)
        $exception = new \RuntimeException('code context test');

        $flat = FlattenException::createFromThrowable($exception);
        $trace = $flat->getTrace();

        // At least one frame should have this file and thus code_context
        $hasCodeContext = false;
        foreach ($trace as $frame) {
            if ($frame['file'] !== '' && $frame['code_context'] !== []) {
                $hasCodeContext = true;
                self::assertGreaterThan(0, $frame['highlight_line']);
                break;
            }
        }

        self::assertTrue($hasCodeContext, 'Expected at least one trace frame with code_context');
    }

    #[Test]
    public function formatArgHandlesVariousTypes(): void
    {
        // Call a method with various arg types to generate a trace with formatted args
        try {
            $this->throwWithArgs(null, true, 42, 3.14, 'hello', [1, 2, 3], new \stdClass());
        } catch (\RuntimeException $e) {
            $flat = FlattenException::createFromThrowable($e);
            $trace = $flat->getTrace();

            // Find the frame for throwWithArgs
            $targetFrame = null;
            foreach ($trace as $frame) {
                if ($frame['function'] === 'throwWithArgs') {
                    $targetFrame = $frame;
                    break;
                }
            }

            self::assertNotNull($targetFrame, 'Could not find throwWithArgs frame in trace');
            $args = $targetFrame['args'];

            if ($args === []) {
                self::markTestSkipped('Trace args not available (zend.exception_ignore_args may be enabled).');
            }

            self::assertSame('null', $args[0]);
            self::assertSame('true', $args[1]);
            self::assertSame('42', $args[2]);
            self::assertSame('3.14', $args[3]);
            self::assertSame('"hello"', $args[4]);
            self::assertSame('array(3)', $args[5]);
            self::assertSame('stdClass', $args[6]);

            return;
        }

        self::fail('Expected RuntimeException to be thrown');
    }

    /**
     * Helper to throw an exception with various argument types for trace inspection.
     */
    private function throwWithArgs(
        mixed $null,
        mixed $bool,
        mixed $int,
        mixed $float,
        mixed $string,
        mixed $array,
        mixed $object,
    ): never {
        throw new \RuntimeException('args test');
    }

    /**
     * Helper to create an exception from a nested call to produce multiple trace frames.
     */
    private function createNestedCallException(): \RuntimeException
    {
        return $this->innerMethod();
    }

    private function innerMethod(): \RuntimeException
    {
        return new \RuntimeException('nested');
    }
}

/**
 * Test exception that mimics an HTTP exception with getStatusCode().
 */
class TestHttpException extends \RuntimeException
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
