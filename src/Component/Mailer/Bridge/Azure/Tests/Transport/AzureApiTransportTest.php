<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\Azure\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Bridge\Azure\Transport\AzureApiTransport;
use WpPack\Component\Mailer\PhpMailer;

final class AzureApiTransportTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('wp_json_encode')) {
            self::markTestSkipped('WordPress functions are not available.');
        }
    }

    #[Test]
    public function getNameReturnsAzureApi(): void
    {
        $transport = new AzureApiTransport('test.communication.azure.com', 'dGVzdC1rZXk=');

        self::assertSame('azureapi', $transport->getName());
    }

    #[Test]
    public function constructorAcceptsOptionalHttpClient(): void
    {
        $transport = new AzureApiTransport(
            endpoint: 'test.communication.azure.com',
            accessKey: 'dGVzdC1rZXk=',
            httpClient: null,
        );

        self::assertInstanceOf(AzureApiTransport::class, $transport);
    }
}
