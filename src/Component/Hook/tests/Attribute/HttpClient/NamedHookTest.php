<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Tests\Attribute\HttpClient;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\HttpClient\Action\HttpApiDebugAction;
use WpPack\Component\Hook\Attribute\HttpClient\Filter\HttpApiCurlFilter;
use WpPack\Component\Hook\Attribute\HttpClient\Filter\HttpApiTransportsFilter;
use WpPack\Component\Hook\Attribute\HttpClient\Filter\HttpLocalRequestFilter;
use WpPack\Component\Hook\Attribute\HttpClient\Filter\HttpRequestArgsFilter;
use WpPack\Component\Hook\Attribute\HttpClient\Filter\HttpRequestHostIsExternalFilter;
use WpPack\Component\Hook\Attribute\HttpClient\Filter\HttpRequestRedirectCountFilter;
use WpPack\Component\Hook\Attribute\HttpClient\Filter\HttpRequestTimeoutFilter;
use WpPack\Component\Hook\Attribute\HttpClient\Filter\HttpResponseFilter;
use WpPack\Component\Hook\Attribute\HttpClient\Filter\HttpsLocalSslVerifyFilter;
use WpPack\Component\Hook\Attribute\HttpClient\Filter\HttpsSslVerifyFilter;
use WpPack\Component\Hook\Attribute\HttpClient\Filter\PreHttpRequestFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function httpApiDebugActionHasCorrectHookName(): void
    {
        $action = new HttpApiDebugAction();

        self::assertSame('http_api_debug', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function httpApiDebugActionAcceptsCustomPriority(): void
    {
        $action = new HttpApiDebugAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function httpApiCurlFilterHasCorrectHookName(): void
    {
        $filter = new HttpApiCurlFilter();

        self::assertSame('http_api_curl', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function httpApiTransportsFilterHasCorrectHookName(): void
    {
        $filter = new HttpApiTransportsFilter();

        self::assertSame('http_api_transports', $filter->hook);
    }

    #[Test]
    public function httpLocalRequestFilterHasCorrectHookName(): void
    {
        $filter = new HttpLocalRequestFilter();

        self::assertSame('http_local_request', $filter->hook);
    }

    #[Test]
    public function httpRequestArgsFilterHasCorrectHookName(): void
    {
        $filter = new HttpRequestArgsFilter();

        self::assertSame('http_request_args', $filter->hook);
    }

    #[Test]
    public function httpRequestHostIsExternalFilterHasCorrectHookName(): void
    {
        $filter = new HttpRequestHostIsExternalFilter();

        self::assertSame('http_request_host_is_external', $filter->hook);
    }

    #[Test]
    public function httpRequestRedirectCountFilterHasCorrectHookName(): void
    {
        $filter = new HttpRequestRedirectCountFilter();

        self::assertSame('http_request_redirect_count', $filter->hook);
    }

    #[Test]
    public function httpRequestTimeoutFilterHasCorrectHookName(): void
    {
        $filter = new HttpRequestTimeoutFilter();

        self::assertSame('http_request_timeout', $filter->hook);
    }

    #[Test]
    public function httpResponseFilterHasCorrectHookName(): void
    {
        $filter = new HttpResponseFilter();

        self::assertSame('http_response', $filter->hook);
    }

    #[Test]
    public function httpsLocalSslVerifyFilterHasCorrectHookName(): void
    {
        $filter = new HttpsLocalSslVerifyFilter();

        self::assertSame('https_local_ssl_verify', $filter->hook);
    }

    #[Test]
    public function httpsSslVerifyFilterHasCorrectHookName(): void
    {
        $filter = new HttpsSslVerifyFilter();

        self::assertSame('https_ssl_verify', $filter->hook);
    }

    #[Test]
    public function preHttpRequestFilterHasCorrectHookName(): void
    {
        $filter = new PreHttpRequestFilter();

        self::assertSame('pre_http_request', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new HttpApiDebugAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new HttpApiCurlFilter());
        self::assertInstanceOf(Filter::class, new HttpApiTransportsFilter());
        self::assertInstanceOf(Filter::class, new HttpLocalRequestFilter());
        self::assertInstanceOf(Filter::class, new HttpRequestArgsFilter());
        self::assertInstanceOf(Filter::class, new HttpRequestHostIsExternalFilter());
        self::assertInstanceOf(Filter::class, new HttpRequestRedirectCountFilter());
        self::assertInstanceOf(Filter::class, new HttpRequestTimeoutFilter());
        self::assertInstanceOf(Filter::class, new HttpResponseFilter());
        self::assertInstanceOf(Filter::class, new HttpsLocalSslVerifyFilter());
        self::assertInstanceOf(Filter::class, new HttpsSslVerifyFilter());
        self::assertInstanceOf(Filter::class, new PreHttpRequestFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[HttpApiDebugAction]
            public function onDebug(): void {}

            #[PreHttpRequestFilter(priority: 5)]
            public function onPreRequest(): void {}
        };

        $debugMethod = new \ReflectionMethod($class, 'onDebug');
        $attributes = $debugMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('http_api_debug', $attributes[0]->newInstance()->hook);

        $preRequestMethod = new \ReflectionMethod($class, 'onPreRequest');
        $attributes = $preRequestMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('pre_http_request', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
