<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\RequestDataCollector;

final class RequestDataCollectorTest extends TestCase
{
    private RequestDataCollector $collector;

    /** @var array<string, mixed> */
    private array $originalServer;

    /** @var array<string, mixed> */
    private array $originalPost;

    /** @var array<string, mixed> */
    private array $originalCookie;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->originalPost = $_POST;
        $this->originalCookie = $_COOKIE;
        $this->collector = new RequestDataCollector();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_POST = $this->originalPost;
        $_COOKIE = $this->originalCookie;
    }

    #[Test]
    public function getNameReturnsRequest(): void
    {
        self::assertSame('request', $this->collector->getName());
    }

    #[Test]
    public function collectGathersServerData(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/test-page';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('POST', $data['method']);
        self::assertArrayHasKey('url', $data);
        self::assertArrayHasKey('status_code', $data);
        self::assertArrayHasKey('request_headers', $data);
        self::assertArrayHasKey('response_headers', $data);
        self::assertArrayHasKey('server_vars', $data);
    }

    #[Test]
    public function getBadgeValueReturnsMethodAndStatus(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->collector->collect();

        self::assertSame('GET 200', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsGreenFor200Status(): void
    {
        $this->collector->collect();

        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsYellowFor300Status(): void
    {
        // Use captureStatusCode to set a 301 status
        $this->collector->captureStatusCode('HTTP/1.1 301 Moved Permanently', 301);
        $this->collector->collect();

        self::assertSame('yellow', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsRedFor400Status(): void
    {
        $this->collector->captureStatusCode('HTTP/1.1 404 Not Found', 404);
        $this->collector->collect();

        self::assertSame('red', $this->collector->getBadgeColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->collect();
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }

    #[Test]
    public function collectMasksPasswordInPostParams(): void
    {
        $_POST = ['username' => 'admin', 'password' => 'secret123'];

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('admin', $data['post_params']['username']);
        self::assertSame('********', $data['post_params']['password']);
    }

    #[Test]
    public function collectMasksTokenAndApiKeyInPostParams(): void
    {
        $_POST = ['api_key' => 'sk-abc123', 'access_token' => 'tok-xyz', 'name' => 'test'];

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('********', $data['post_params']['api_key']);
        self::assertSame('********', $data['post_params']['access_token']);
        self::assertSame('test', $data['post_params']['name']);
    }

    #[Test]
    public function collectMasksNestedSensitiveData(): void
    {
        $_POST = ['user' => ['email' => 'a@b.com', 'password' => 'secret']];

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('a@b.com', $data['post_params']['user']['email']);
        self::assertSame('********', $data['post_params']['user']['password']);
    }

    #[Test]
    public function collectMasksSensitiveCookies(): void
    {
        $_COOKIE = ['session_token' => 'abc123', 'pref' => 'dark'];

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('********', $data['cookies']['session_token']);
        self::assertSame('dark', $data['cookies']['pref']);
    }

    #[Test]
    public function collectMasksSensitiveRequestHeaders(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret-token';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('********', $data['request_headers']['Authorization']);
        self::assertSame('text/html', $data['request_headers']['Accept']);
    }

    #[Test]
    public function collectIncludesContentType(): void
    {
        $this->collector->captureResponseHeaders(['Content-Type' => 'application/json']);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('application/json', $data['content_type']);
    }

    #[Test]
    public function collectIncludesContentTypeEmptyWhenNoHeaders(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('content_type', $data);
    }

    #[Test]
    public function collectIncludesScriptFilenameInServerVars(): void
    {
        $_SERVER['SCRIPT_FILENAME'] = '/var/www/html/index.php';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('/var/www/html/index.php', $data['server_vars']['SCRIPT_FILENAME']);
    }

    #[Test]
    public function collectIncludesGatewayInterfaceInServerVars(): void
    {
        $_SERVER['GATEWAY_INTERFACE'] = 'CGI/1.1';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('CGI/1.1', $data['server_vars']['GATEWAY_INTERFACE']);
    }

    #[Test]
    public function collectIncludesScriptNameInServerVars(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('/index.php', $data['server_vars']['SCRIPT_NAME']);
    }

    #[Test]
    public function collectMasksSensitiveHttpApiCallArgs(): void
    {
        $this->collector->captureHttpApiCall(
            ['response' => 'ok'],
            'response',
            'WP_Http',
            ['headers' => ['Authorization' => 'Bearer xxx'], 'body' => ['api_key' => 'secret']],
            'https://api.example.com',
        );

        $this->collector->collect();
        $data = $this->collector->getData();

        $call = $data['http_api_calls'][0];
        self::assertSame('https://api.example.com', $call['url']);
        self::assertSame('********', $call['args']['body']['api_key']);
    }

}
