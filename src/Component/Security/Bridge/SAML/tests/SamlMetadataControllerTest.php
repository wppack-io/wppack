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

namespace WpPack\Component\Security\Bridge\SAML\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpMetadataExporter;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
use WpPack\Component\Security\Bridge\SAML\SamlMetadataController;

#[CoversClass(SamlMetadataController::class)]
final class SamlMetadataControllerTest extends TestCase
{
    private function createExporter(): SpMetadataExporter
    {
        $configuration = new SamlConfiguration(
            idpSettings: new IdpSettings(
                entityId: 'https://idp.example.com/metadata',
                ssoUrl: 'https://idp.example.com/sso',
                sloUrl: 'https://idp.example.com/slo',
                x509Cert: 'MIICDummyCert==',
            ),
            spSettings: new SpSettings(
                entityId: 'https://sp.example.com/metadata',
                acsUrl: 'https://sp.example.com/acs',
                sloUrl: 'https://sp.example.com/slo',
            ),
        );

        return new SpMetadataExporter($configuration);
    }

    #[Test]
    public function invokeReturnsXmlResponse(): void
    {
        $controller = new SamlMetadataController($this->createExporter());
        $response = $controller();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->statusCode);
        self::assertSame('application/xml', $response->headers['Content-Type']);
        self::assertStringContainsString('EntityDescriptor', $response->content);
        self::assertStringContainsString('https://sp.example.com/metadata', $response->content);
    }
}
