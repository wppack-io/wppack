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

namespace WPPack\Component\Scim\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Controller\ServiceProviderConfigController;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\Scim\Schema\ServiceProviderConfig;

#[CoversClass(ServiceProviderConfigController::class)]
final class ServiceProviderConfigControllerTest extends TestCase
{
    #[Test]
    public function emitsConfigFromInjectedValueObject(): void
    {
        $config = new ServiceProviderConfig(
            patchSupported: true,
            filterSupported: true,
            filterMaxResults: 500,
        );
        $controller = new ServiceProviderConfigController($config, baseUrl: 'https://example.test');

        $response = $controller();

        self::assertSame(200, $response->statusCode);
        self::assertSame(ScimConstants::CONTENT_TYPE, $response->headers['Content-Type']);

        $body = json_decode((string) $response->content, true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([ScimConstants::SERVICE_PROVIDER_CONFIG_SCHEMA], $body['schemas']);
        self::assertTrue($body['patch']['supported']);
        self::assertSame(500, $body['filter']['maxResults']);
        self::assertSame(
            'https://example.test/scim/v2/ServiceProviderConfig',
            $body['meta']['location'],
        );
    }
}
