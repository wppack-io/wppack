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
    private array $originalGet;

    /** @var array<string, mixed> */
    private array $originalPost;

    /** @var array<string, mixed> */
    private array $originalCookie;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->originalGet = $_GET;
        $this->originalPost = $_POST;
        $this->originalCookie = $_COOKIE;
        $this->collector = new RequestDataCollector();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
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
    public function getIndicatorValueReturnsMethodAndStatus(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->collector->collect();

        self::assertSame('GET 200', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsGreenFor200Status(): void
    {
        $this->collector->collect();

        self::assertSame('green', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsYellowFor300Status(): void
    {
        // Use captureStatusCode to set a 301 status
        $this->collector->captureStatusCode('HTTP/1.1 301 Moved Permanently', 301);
        $this->collector->collect();

        self::assertSame('yellow', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsRedFor400Status(): void
    {
        $this->collector->captureStatusCode('HTTP/1.1 404 Not Found', 404);
        $this->collector->collect();

        self::assertSame('red', $this->collector->getIndicatorColor());
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

    #[Test]
    public function collectGathersRequestData(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REQUEST_URI'] = '/api/resource';
        $_SERVER['HTTP_HOST'] = 'mysite.com';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('PUT', $data['method']);
        self::assertStringContainsString('mysite.com/api/resource', $data['url']);
        self::assertSame(200, $data['status_code']);
    }

    #[Test]
    public function collectBuildCorrectUrl(): void
    {
        // Test HTTP (HTTPS off)
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/page';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('http://example.com/page', $data['url']);

        // Test HTTPS (HTTPS on)
        $this->collector->reset();
        $_SERVER['HTTPS'] = 'on';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('https://example.com/page', $data['url']);
    }

    #[Test]
    public function collectMasksSensitivePostParams(): void
    {
        $_POST = [
            'username' => 'admin',
            'password' => 'my-secret',
            'token' => 'abc-123',
            'api_key' => 'key-456',
        ];

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('admin', $data['post_params']['username']);
        self::assertSame('********', $data['post_params']['password']);
        self::assertSame('********', $data['post_params']['token']);
        self::assertSame('********', $data['post_params']['api_key']);
    }

    #[Test]
    public function collectMasksSensitiveCookiesWithAuthToken(): void
    {
        $_COOKIE = ['auth_token' => 'secret-value', 'theme' => 'light'];

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('********', $data['cookies']['auth_token']);
        self::assertSame('light', $data['cookies']['theme']);
    }

    #[Test]
    public function collectMasksNestedSensitivePostData(): void
    {
        $_POST = [
            'form' => [
                'name' => 'John',
                'credentials' => [
                    'password' => 'top-secret',
                    'username' => 'john',
                ],
            ],
        ];

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('John', $data['post_params']['form']['name']);
        self::assertSame('john', $data['post_params']['form']['credentials']['username']);
        self::assertSame('********', $data['post_params']['form']['credentials']['password']);
    }

    #[Test]
    public function collectMasksSensitiveHeadersIncludingCookie(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer my-token';
        $_SERVER['HTTP_COOKIE'] = 'session=abc123';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('********', $data['request_headers']['Authorization']);
        self::assertSame('********', $data['request_headers']['Cookie']);
    }

    #[Test]
    public function collectPreserveNonSensitiveHeaders(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('application/json', $data['request_headers']['Accept']);
        self::assertSame('example.com', $data['request_headers']['Host']);
        self::assertSame('en-US', $data['request_headers']['Accept-Language']);
    }

    #[Test]
    public function captureStatusCodeUpdatesCode(): void
    {
        $this->collector->captureStatusCode('HTTP/1.1 503 Service Unavailable', 503);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(503, $data['status_code']);
    }

    #[Test]
    public function captureResponseHeadersUpdatesHeaders(): void
    {
        $headers = [
            'X-Custom-Header' => 'custom-value',
            'X-Request-Id' => 'req-123',
        ];

        $this->collector->captureResponseHeaders($headers);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('custom-value', $data['response_headers']['X-Custom-Header']);
        self::assertSame('req-123', $data['response_headers']['X-Request-Id']);
    }

    #[Test]
    public function captureHttpApiCallTracksRequests(): void
    {
        $this->collector->captureHttpApiCall(
            ['body' => '{"ok":true}', 'response' => ['code' => 200]],
            'response',
            'WP_Http',
            ['method' => 'POST', 'body' => '{"data":"value"}'],
            'https://api.example.com/v1/items',
        );

        $this->collector->captureHttpApiCall(
            ['body' => '{"ok":true}'],
            'response',
            'WP_Http',
            ['method' => 'GET'],
            'https://api.example.com/v1/users',
        );

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertCount(2, $data['http_api_calls']);
        self::assertSame('https://api.example.com/v1/items', $data['http_api_calls'][0]['url']);
        self::assertSame('https://api.example.com/v1/users', $data['http_api_calls'][1]['url']);
    }

    #[Test]
    public function captureHttpApiCallMasksSensitiveArgs(): void
    {
        $this->collector->captureHttpApiCall(
            ['body' => 'response'],
            'response',
            'WP_Http',
            [
                'headers' => ['Authorization' => 'Bearer secret-token'],
                'body' => ['password' => 'my-pass', 'username' => 'admin'],
            ],
            'https://secure.example.com/login',
        );

        $this->collector->collect();
        $data = $this->collector->getData();

        $call = $data['http_api_calls'][0];
        self::assertSame('********', $call['args']['headers']['Authorization']);
        self::assertSame('********', $call['args']['body']['password']);
        self::assertSame('admin', $call['args']['body']['username']);
    }

    #[Test]
    public function getIndicatorValueFormatsMethodAndStatus(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->collector->collect();

        self::assertSame('GET 200', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsGreenFor2xx(): void
    {
        $this->collector->captureStatusCode('HTTP/1.1 201 Created', 201);
        $this->collector->collect();

        self::assertSame('green', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsYellowFor3xx(): void
    {
        $this->collector->captureStatusCode('HTTP/1.1 302 Found', 302);
        $this->collector->collect();

        self::assertSame('yellow', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsRedFor4xx(): void
    {
        $this->collector->captureStatusCode('HTTP/1.1 500 Internal Server Error', 500);
        $this->collector->collect();

        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function collectGathersServerVars(): void
    {
        $_SERVER['SERVER_NAME'] = 'example.com';
        $_SERVER['SERVER_ADDR'] = '127.0.0.1';
        $_SERVER['SERVER_PORT'] = '443';
        $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['DOCUMENT_ROOT'] = '/var/www/html';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['REMOTE_PORT'] = '54321';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['QUERY_STRING'] = 'foo=bar';
        $_SERVER['CONTENT_TYPE'] = 'text/html';
        $_SERVER['CONTENT_LENGTH'] = '1024';
        $_SERVER['SCRIPT_FILENAME'] = '/var/www/html/index.php';
        $_SERVER['GATEWAY_INTERFACE'] = 'CGI/1.1';
        $_SERVER['PATH_INFO'] = '/extra';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('example.com', $data['server_vars']['SERVER_NAME']);
        self::assertSame('127.0.0.1', $data['server_vars']['SERVER_ADDR']);
        self::assertSame('443', $data['server_vars']['SERVER_PORT']);
        self::assertSame('Apache/2.4', $data['server_vars']['SERVER_SOFTWARE']);
        self::assertSame('HTTP/1.1', $data['server_vars']['SERVER_PROTOCOL']);
        self::assertSame('/var/www/html', $data['server_vars']['DOCUMENT_ROOT']);
        self::assertSame('192.168.1.1', $data['server_vars']['REMOTE_ADDR']);
        self::assertSame('54321', $data['server_vars']['REMOTE_PORT']);
        self::assertSame('/test', $data['server_vars']['REQUEST_URI']);
        self::assertSame('GET', $data['server_vars']['REQUEST_METHOD']);
        self::assertSame('foo=bar', $data['server_vars']['QUERY_STRING']);
        self::assertSame('text/html', $data['server_vars']['CONTENT_TYPE']);
        self::assertSame('1024', $data['server_vars']['CONTENT_LENGTH']);
        self::assertSame('/var/www/html/index.php', $data['server_vars']['SCRIPT_FILENAME']);
        self::assertSame('CGI/1.1', $data['server_vars']['GATEWAY_INTERFACE']);
        self::assertSame('/extra', $data['server_vars']['PATH_INFO']);
        self::assertSame('/index.php', $data['server_vars']['SCRIPT_NAME']);
    }

    #[Test]
    public function collectResolvesContentType(): void
    {
        $this->collector->captureResponseHeaders(['Content-Type' => 'text/plain; charset=utf-8']);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('text/plain; charset=utf-8', $data['content_type']);
    }

    #[Test]
    public function resetClearsAllState(): void
    {
        // Capture various data first
        $this->collector->captureStatusCode('HTTP/1.1 404 Not Found', 404);
        $this->collector->captureResponseHeaders(['X-Custom' => 'value']);
        $this->collector->captureHttpApiCall(
            ['body' => 'ok'],
            'response',
            'WP_Http',
            ['method' => 'GET'],
            'https://api.example.com',
        );
        $this->collector->collect();

        // Verify data was captured
        $dataBefore = $this->collector->getData();
        self::assertSame(404, $dataBefore['status_code']);
        self::assertNotEmpty($dataBefore['response_headers']);
        self::assertNotEmpty($dataBefore['http_api_calls']);

        // Reset and collect fresh
        $this->collector->reset();

        self::assertEmpty($this->collector->getData());

        // Collect again after reset - should have defaults
        $this->collector->collect();
        $dataAfter = $this->collector->getData();

        self::assertSame(200, $dataAfter['status_code']);
        self::assertEmpty($dataAfter['response_headers']);
        self::assertEmpty($dataAfter['http_api_calls']);
    }

    #[Test]
    public function getLabelReturnsRequest(): void
    {
        self::assertSame('Request', $this->collector->getLabel());
    }

    #[Test]
    public function collectBuildUrlWithServerNameFallback(): void
    {
        unset($_SERVER['HTTP_HOST']);
        $_SERVER['SERVER_NAME'] = 'fallback-server.com';
        $_SERVER['REQUEST_URI'] = '/path';
        $_SERVER['HTTPS'] = 'off';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('http://fallback-server.com/path', $data['url']);
    }

    #[Test]
    public function collectBuildUrlWithLocalhostFallback(): void
    {
        unset($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME']);
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['HTTPS'] = 'off';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('http://localhost/test', $data['url']);
    }

    #[Test]
    public function collectBuildUrlWithMissingRequestUri(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        unset($_SERVER['REQUEST_URI']);
        $_SERVER['HTTPS'] = 'off';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('http://example.com/', $data['url']);
    }

    #[Test]
    public function collectMasksProxyAuthorizationHeader(): void
    {
        $_SERVER['HTTP_PROXY_AUTHORIZATION'] = 'Basic abc123';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('********', $data['request_headers']['Proxy-Authorization']);
    }

    #[Test]
    public function collectMasksXApiKeyHeader(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'my-api-key';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('********', $data['request_headers']['X-Api-Key']);
    }

    #[Test]
    public function collectMasksXAuthTokenHeader(): void
    {
        $_SERVER['HTTP_X_AUTH_TOKEN'] = 'my-auth-token';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('********', $data['request_headers']['X-Auth-Token']);
    }

    #[Test]
    public function collectMasksCreditCardInPostParams(): void
    {
        $_POST = ['credit_card' => '4111111111111111', 'name' => 'John'];

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('********', $data['post_params']['credit_card']);
        self::assertSame('John', $data['post_params']['name']);
    }

    #[Test]
    public function collectMasksCvvAndSsnInPostParams(): void
    {
        $_POST = ['cvv' => '123', 'ssn' => '123-45-6789', 'visible' => 'ok'];

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('********', $data['post_params']['cvv']);
        self::assertSame('********', $data['post_params']['ssn']);
        self::assertSame('ok', $data['post_params']['visible']);
    }

    #[Test]
    public function collectMasksPrivateKeyAndRefreshToken(): void
    {
        $_POST = ['private_key' => 'pem-data', 'refresh_token' => 'rt-xyz'];

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('********', $data['post_params']['private_key']);
        self::assertSame('********', $data['post_params']['refresh_token']);
    }

    #[Test]
    public function collectMasksPartialSensitiveKeyMatch(): void
    {
        // Keys containing sensitive keywords (e.g., "my_password_field")
        $_POST = ['my_password_field' => 'hidden', 'user_apikey_v2' => 'secret'];

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('********', $data['post_params']['my_password_field']);
        self::assertSame('********', $data['post_params']['user_apikey_v2']);
    }

    #[Test]
    public function captureStatusCodeReturnsOriginalHeader(): void
    {
        $result = $this->collector->captureStatusCode('HTTP/1.1 200 OK', 200);

        self::assertSame('HTTP/1.1 200 OK', $result);
    }

    #[Test]
    public function captureHttpApiCallWithNonArrayParsedArgs(): void
    {
        // parsedArgs can be non-array (edge case)
        $this->collector->captureHttpApiCall(
            ['body' => 'response'],
            'response',
            'WP_Http',
            'not-an-array',
            'https://example.com/api',
        );

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertCount(1, $data['http_api_calls']);
        self::assertSame([], $data['http_api_calls'][0]['args']);
    }

    #[Test]
    public function collectServerVarsOmitsMissingKeys(): void
    {
        // Remove optional server vars
        unset(
            $_SERVER['HTTPS'],
            $_SERVER['CONTENT_TYPE'],
            $_SERVER['CONTENT_LENGTH'],
            $_SERVER['SCRIPT_FILENAME'],
            $_SERVER['GATEWAY_INTERFACE'],
            $_SERVER['PATH_INFO'],
            $_SERVER['SCRIPT_NAME'],
            $_SERVER['QUERY_STRING'],
        );

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayNotHasKey('HTTPS', $data['server_vars']);
        self::assertArrayNotHasKey('CONTENT_TYPE', $data['server_vars']);
    }
}
