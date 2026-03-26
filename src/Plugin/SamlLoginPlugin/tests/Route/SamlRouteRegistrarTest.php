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

namespace WpPack\Plugin\SamlLoginPlugin\Tests\Route;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\SamlLogoutHandler;
use WpPack\Component\Security\Bridge\SAML\SamlMetadataController;
use WpPack\Plugin\SamlLoginPlugin\Route\SamlRouteRegistrar;

#[CoversClass(SamlRouteRegistrar::class)]
final class SamlRouteRegistrarTest extends TestCase
{
    private SamlRouteRegistrar $registrar;

    protected function setUp(): void
    {
        $samlConfig = new SamlConfiguration(
            idpSettings: new IdpSettings(
                entityId: 'https://idp.example.com',
                ssoUrl: 'https://idp.example.com/sso',
                sloUrl: null,
                x509Cert: 'MIICert',
            ),
            spSettings: new SpSettings(
                entityId: 'https://sp.example.com',
                acsUrl: 'https://sp.example.com/saml/acs',
            ),
        );

        $metadataController = new SamlMetadataController($samlConfig);
        $logoutHandler = new SamlLogoutHandler(new SamlAuthFactory($samlConfig));

        $this->registrar = new SamlRouteRegistrar($metadataController, $logoutHandler);
    }

    protected function tearDown(): void
    {
        remove_all_actions('template_redirect');
        unset($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
    }

    #[Test]
    public function registerAddsTemplateRedirectAction(): void
    {
        $this->registrar->register();

        self::assertSame(1, has_action('template_redirect', [$this->registrar, 'handleRequest']));
    }

    #[Test]
    public function handleRequestDoesNothingForNonSamlPaths(): void
    {
        $_SERVER['REQUEST_URI'] = '/about';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Should not throw or exit
        $this->registrar->handleRequest();

        self::assertTrue(true);
    }

    #[Test]
    public function handleRequestDoesNothingForWrongMethod(): void
    {
        $_SERVER['REQUEST_URI'] = '/saml/metadata';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Metadata only responds to GET, so POST should be ignored
        $this->registrar->handleRequest();

        self::assertTrue(true);
    }

    #[Test]
    public function handleRequestParsesPathFromQueryString(): void
    {
        $_SERVER['REQUEST_URI'] = '/about?foo=bar';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Should not throw or exit (path is /about, not a SAML path)
        $this->registrar->handleRequest();

        self::assertTrue(true);
    }
}
