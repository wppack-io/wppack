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
use WpPack\Component\EventDispatcher\EventDispatcher;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\AuthenticationManager;
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
    private SamlMetadataController $metadataController;
    private SamlLogoutHandler $logoutHandler;
    private AuthenticationManager $authenticationManager;

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

        $this->metadataController = new SamlMetadataController($samlConfig);
        $this->logoutHandler = new SamlLogoutHandler(new SamlAuthFactory($samlConfig));
        $this->authenticationManager = new AuthenticationManager(new EventDispatcher(), Request::create('/'));
    }

    protected function tearDown(): void
    {
        remove_all_actions('template_redirect');
    }

    private function createRegistrar(Request $request): SamlRouteRegistrar
    {
        return new SamlRouteRegistrar(
            $request,
            $this->metadataController,
            $this->logoutHandler,
            $this->authenticationManager,
        );
    }

    #[Test]
    public function registerAddsTemplateRedirectAction(): void
    {
        $registrar = $this->createRegistrar(Request::create('/'));
        $registrar->register();

        self::assertSame(1, has_action('template_redirect', [$registrar, 'handleRequest']));
    }

    #[Test]
    public function handleRequestDoesNothingForNonSamlPaths(): void
    {
        $registrar = $this->createRegistrar(Request::create('/about'));

        // Should not throw or exit
        $registrar->handleRequest();

        self::assertTrue(true);
    }

    #[Test]
    public function handleRequestDoesNothingForWrongMethod(): void
    {
        $registrar = $this->createRegistrar(Request::create('/saml/metadata', 'POST'));

        // Metadata only responds to GET, so POST should be ignored
        $registrar->handleRequest();

        self::assertTrue(true);
    }

    #[Test]
    public function handleRequestParsesPathFromQueryString(): void
    {
        $registrar = $this->createRegistrar(Request::create('/about?foo=bar'));

        // Should not throw or exit (path is /about, not a SAML path)
        $registrar->handleRequest();

        self::assertTrue(true);
    }
}
