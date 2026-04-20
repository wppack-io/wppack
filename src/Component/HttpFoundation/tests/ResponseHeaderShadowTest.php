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

/*
 * Response::sendHeaders() calls headers_sent() / http_response_code() /
 * header() unqualified. PHP resolves unqualified function calls in the
 * caller's namespace first, so declaring stubs in the same namespace
 * lets the existing ob_start-wrapped tests drive those branches
 * without actually emitting real HTTP headers.
 */

namespace WPPack\Component\HttpFoundation {
    if (!\function_exists(__NAMESPACE__ . '\\headers_sent')) {
        function headers_sent(): bool
        {
            return $GLOBALS['__response_test_headers_sent'] ?? false;
        }
    }

    if (!\function_exists(__NAMESPACE__ . '\\http_response_code')) {
        function http_response_code(?int $code = null): int|bool
        {
            if ($code !== null) {
                $GLOBALS['__response_test_http_code'] = $code;
            }

            return $GLOBALS['__response_test_http_code'] ?? 200;
        }
    }

    if (!\function_exists(__NAMESPACE__ . '\\header')) {
        function header(string $h): void
        {
            $GLOBALS['__response_test_headers'][] = $h;
        }
    }
}

namespace WPPack\Component\HttpFoundation\Tests {

    use PHPUnit\Framework\Attributes\CoversClass;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;
    use WPPack\Component\HttpFoundation\Response;

    #[CoversClass(Response::class)]
    final class ResponseHeaderShadowTest extends TestCase
    {
        protected function tearDown(): void
        {
            unset(
                $GLOBALS['__response_test_headers_sent'],
                $GLOBALS['__response_test_http_code'],
                $GLOBALS['__response_test_headers'],
            );
        }

        #[Test]
        public function sendEmitsHttpStatusAndHeadersWhenHeadersNotSent(): void
        {
            $GLOBALS['__response_test_headers_sent'] = false;

            $response = new Response('body', 404, ['X-Foo' => 'bar', 'Content-Type' => 'text/plain']);

            ob_start();
            $response->send();
            ob_end_clean();

            self::assertSame(404, $GLOBALS['__response_test_http_code']);
            self::assertContains('X-Foo: bar', $GLOBALS['__response_test_headers'] ?? []);
            self::assertContains('Content-Type: text/plain', $GLOBALS['__response_test_headers'] ?? []);
        }

        #[Test]
        public function sendSkipsHttpStatusAndHeadersWhenAlreadySent(): void
        {
            $GLOBALS['__response_test_headers_sent'] = true;

            $response = new Response('body', 500, ['X-Foo' => 'bar']);

            ob_start();
            $response->send();
            ob_end_clean();

            self::assertArrayNotHasKey('__response_test_http_code', $GLOBALS);
            self::assertArrayNotHasKey('__response_test_headers', $GLOBALS);
        }
    }
}
