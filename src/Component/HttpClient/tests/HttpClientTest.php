<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpClient\Exception\ConnectionException;
use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\HttpClient\Request;
use WpPack\Component\HttpClient\Response;

final class HttpClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        add_filter('pre_http_request', [$this, 'mockHttpResponse'], 10, 3);
    }

    protected function tearDown(): void
    {
        remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);

        parent::tearDown();
    }

    /**
     * Mock HTTP responses via WordPress pre_http_request filter.
     *
     * Simulates httpbin.org-like behavior by reflecting request details
     * (method, headers, query params, body) back in the response.
     *
     * @param mixed               $preempt
     * @param array<string, mixed> $parsedArgs
     * @return array<string, mixed>|\WP_Error
     */
    public function mockHttpResponse(mixed $preempt, array $parsedArgs, string $url): array|\WP_Error
    {
        if (str_contains($url, 'invalid.domain.that.does.not.exist')) {
            return new \WP_Error('http_request_failed', 'Could not resolve host');
        }

        $method = $parsedArgs['method'] ?? 'GET';
        $body = $parsedArgs['body'] ?? '';
        $requestHeaders = $parsedArgs['headers'] ?? [];

        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $queryArgs);

        $responseData = [
            'args' => $queryArgs,
            'headers' => $requestHeaders,
            'url' => $url,
        ];

        if ($body !== '' && $body !== null) {
            $responseData['data'] = $body;
            $decoded = json_decode((string) $body, true);
            if ($decoded !== null) {
                $responseData['json'] = $decoded;
            }
        }

        return [
            'headers' => ['content-type' => 'application/json'],
            'body' => $method === 'HEAD' ? '' : (string) json_encode($responseData),
            'response' => ['code' => 200, 'message' => 'OK'],
            'cookies' => [],
            'filename' => null,
        ];
    }

    #[Test]
    public function withHeadersReturnsNewInstance(): void
    {
        $client = new HttpClient();
        $new = $client->withHeaders(['X-Custom' => 'value']);

        self::assertNotSame($client, $new);
    }

    #[Test]
    public function withBasicAuthReturnsNewInstance(): void
    {
        $client = new HttpClient();
        $new = $client->withBasicAuth('user', 'pass');

        self::assertNotSame($client, $new);
    }

    #[Test]
    public function timeoutReturnsNewInstance(): void
    {
        $client = new HttpClient();
        $new = $client->timeout(30);

        self::assertNotSame($client, $new);
    }

    #[Test]
    public function baseUriReturnsNewInstance(): void
    {
        $client = new HttpClient();
        $new = $client->baseUri('https://api.example.com');

        self::assertNotSame($client, $new);
    }

    #[Test]
    public function asJsonReturnsNewInstance(): void
    {
        $client = new HttpClient();
        $new = $client->asJson();

        self::assertNotSame($client, $new);
    }

    #[Test]
    public function asFormReturnsNewInstance(): void
    {
        $client = new HttpClient();
        $new = $client->asForm();

        self::assertNotSame($client, $new);
    }

    #[Test]
    public function asMultipartReturnsNewInstance(): void
    {
        $client = new HttpClient();
        $new = $client->asMultipart();

        self::assertNotSame($client, $new);
    }

    #[Test]
    public function queryReturnsNewInstance(): void
    {
        $client = new HttpClient();
        $new = $client->query(['page' => '1']);

        self::assertNotSame($client, $new);
    }

    #[Test]
    public function attachReturnsNewInstance(): void
    {
        $client = new HttpClient();
        $new = $client->attach('file', 'contents', 'file.txt');

        self::assertNotSame($client, $new);
    }

    #[Test]
    public function withOptionsReturnsNewInstance(): void
    {
        $client = new HttpClient();
        $new = $client->withOptions(['sslverify' => false]);

        self::assertNotSame($client, $new);
    }

    #[Test]
    public function fluentChainingProducesImmutableInstances(): void
    {
        $client = new HttpClient();

        $configured = $client
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout(30)
            ->baseUri('https://api.example.com')
            ->asJson()
            ->query(['page' => '1']);

        self::assertNotSame($client, $configured);
    }

    #[Test]
    public function sendRequestReturnsResponse(): void
    {

        $client = new HttpClient();
        $request = new Request('GET', 'https://httpbin.org/get');

        $response = $client->sendRequest($request);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function sendRequestThrowsConnectionExceptionOnWpError(): void
    {

        $client = (new HttpClient())->timeout(1);
        $request = new Request('GET', 'https://invalid.domain.that.does.not.exist.example');

        $this->expectException(ConnectionException::class);
        $client->sendRequest($request);
    }

    #[Test]
    public function getRequestSendsGetMethod(): void
    {

        $client = new HttpClient();
        $response = $client->get('https://httpbin.org/get');

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function postRequestSendsPostMethod(): void
    {

        $client = new HttpClient();
        $response = $client->post('https://httpbin.org/post', ['body' => 'test=value']);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function postRequestWithJsonBody(): void
    {

        $client = (new HttpClient())->asJson();
        $response = $client->post('https://httpbin.org/post', ['json' => ['key' => 'value']]);

        self::assertSame(200, $response->getStatusCode());
        $json = $response->json();
        self::assertArrayHasKey('json', $json);
    }

    #[Test]
    public function postRequestWithFormBody(): void
    {

        $client = (new HttpClient())->asForm();
        $response = $client->post('https://httpbin.org/post', ['form_params' => ['key' => 'value']]);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function baseUriIsPrepended(): void
    {

        $client = (new HttpClient())->baseUri('https://httpbin.org');
        $response = $client->get('/get');

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function queryParamsAreAppended(): void
    {

        $client = (new HttpClient())->query(['foo' => 'bar']);
        $response = $client->get('https://httpbin.org/get');

        self::assertSame(200, $response->getStatusCode());
        $json = $response->json();
        self::assertSame('bar', $json['args']['foo'] ?? null);
    }

    #[Test]
    public function putRequestSendsPutMethod(): void
    {

        $client = new HttpClient();
        $response = $client->put('https://httpbin.org/put', ['body' => 'data']);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function deleteRequestSendsDeleteMethod(): void
    {

        $client = new HttpClient();
        $response = $client->delete('https://httpbin.org/delete');

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function headRequestSendsHeadMethod(): void
    {

        $client = new HttpClient();
        $response = $client->head('https://httpbin.org/get');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->body());
    }

    #[Test]
    public function patchRequestSendsPatchMethod(): void
    {

        $client = new HttpClient();
        $response = $client->patch('https://httpbin.org/patch', ['body' => 'data']);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function requestWithCustomHeaders(): void
    {

        $client = (new HttpClient())->withHeaders(['X-Custom' => 'test-value']);
        $response = $client->get('https://httpbin.org/headers');

        self::assertSame(200, $response->getStatusCode());
        $json = $response->json();
        self::assertSame('test-value', $json['headers']['X-Custom'] ?? null);
    }

    #[Test]
    public function requestWithTimeout(): void
    {

        $client = (new HttpClient())->timeout(30);
        $response = $client->get('https://httpbin.org/get');

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function queryParamsAppendedToUrlWithExistingQuery(): void
    {

        $client = (new HttpClient())->query(['bar' => 'baz']);
        $response = $client->get('https://httpbin.org/get?foo=1');

        self::assertSame(200, $response->getStatusCode());
        $json = $response->json();
        self::assertSame('1', $json['args']['foo'] ?? null);
        self::assertSame('baz', $json['args']['bar'] ?? null);
    }

    #[Test]
    public function baseUriNotPrependedWhenUrlHasScheme(): void
    {

        $client = (new HttpClient())->baseUri('https://ignored.example.com');
        $response = $client->get('https://httpbin.org/get');

        self::assertSame(200, $response->getStatusCode());
        $json = $response->json();
        self::assertStringContainsString('httpbin.org', $json['url']);
    }
}
