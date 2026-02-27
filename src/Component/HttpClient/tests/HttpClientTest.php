<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpClient\HttpClient;

final class HttpClientTest extends TestCase
{
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
}
