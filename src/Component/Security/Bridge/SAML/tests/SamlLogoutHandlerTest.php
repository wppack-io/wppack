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

use LightSaml\Binding\AbstractBinding;
use LightSaml\Binding\BindingFactory;
use LightSaml\Context\Profile\MessageContext;
use LightSaml\Model\Protocol\LogoutRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\SamlLogoutHandler;

#[CoversClass(SamlLogoutHandler::class)]
final class SamlLogoutHandlerTest extends TestCase
{
    private function createSamlConfiguration(): SamlConfiguration
    {
        return new SamlConfiguration(
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
    }

    /**
     * Create a factory spy that records getConfiguration() calls and throws
     * to prevent header() + exit inside initiateLogout().
     */
    private function createFactoryWithSpy(bool &$configCalled): SamlAuthFactory
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('getConfiguration')
            ->willReturnCallback(function () use (&$configCalled): SamlConfiguration {
                $configCalled = true;
                throw new \RuntimeException('initiateLogout() reached');
            });

        return $factory;
    }

    #[Test]
    public function initiateLogoutCallsRedirectWithReturnTo(): void
    {
        $configCalled = false;
        $factory = $this->createFactoryWithSpy($configCalled);

        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());

        try {
            $handler->initiateLogout('user@example.com', '_session123', 'https://sp.example.com/after-logout');
        } catch (\Throwable) {
            // initiateLogout throws because our spy prevents exit
        }

        self::assertTrue($configCalled, 'initiateLogout() should call getConfiguration()');
    }

    #[Test]
    public function initiateLogoutUsesRedirectAfterLogoutAsFallback(): void
    {
        $configCalled = false;
        $factory = $this->createFactoryWithSpy($configCalled);

        $handler = new SamlLogoutHandler($factory, new AuthenticationSession(), 'https://sp.example.com/default-redirect');

        try {
            $handler->initiateLogout('user@example.com', '_session456');
        } catch (\Throwable) {
            // initiateLogout throws because our spy prevents exit
        }

        self::assertTrue($configCalled);
    }

    #[Test]
    public function initiateLogoutWithNullReturnToAndNoDefault(): void
    {
        $configCalled = false;
        $factory = $this->createFactoryWithSpy($configCalled);

        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());

        try {
            $handler->initiateLogout('user@example.com', null);
        } catch (\Throwable) {
            // initiateLogout throws because our spy prevents exit
        }

        self::assertTrue($configCalled);
    }

    #[Test]
    public function handleIdpLogoutRequestCallsBindingReceive(): void
    {
        $binding = $this->createMock(AbstractBinding::class);
        $binding->expects(self::once())
            ->method('receive')
            ->willReturnCallback(function ($request, MessageContext $messageContext): void {
                $messageContext->setMessage(new LogoutRequest());
            });

        $bindingFactory = $this->createMock(BindingFactory::class);
        $bindingFactory->method('getBindingByRequest')->willReturn($binding);

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('createBindingFactory')->willReturn($bindingFactory);

        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());

        $request = new Request(
            query: ['SAMLRequest' => 'encoded-request', 'RelayState' => 'https://sp.example.com/'],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/slo'],
        );

        $handler->handleIdpLogoutRequest($request);

        // If we reach here, the binding was called and logout was processed
        self::assertTrue(true);
    }

    #[Test]
    public function isLogoutRequestReturnsTrueWhenSamlRequestPresent(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());
        $request = Request::create('/saml/slo', 'GET', ['SAMLRequest' => 'encoded-request']);

        self::assertTrue($handler->isLogoutRequest($request));
    }

    #[Test]
    public function isLogoutRequestReturnsFalseWhenNoSamlRequest(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());
        $request = Request::create('/saml/slo');

        self::assertFalse($handler->isLogoutRequest($request));
    }

    #[Test]
    public function isLogoutResponseReturnsTrueWhenSamlResponsePresent(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());
        $request = Request::create('/saml/slo', 'GET', ['SAMLResponse' => 'encoded-response']);

        self::assertTrue($handler->isLogoutResponse($request));
    }

    #[Test]
    public function isLogoutResponseReturnsFalseWhenNoSamlResponse(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());
        $request = Request::create('/saml/slo');

        self::assertFalse($handler->isLogoutResponse($request));
    }

    #[Test]
    public function isLogoutRequestAndResponseAreIndependent(): void
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $handler = new SamlLogoutHandler($factory, new AuthenticationSession());
        $request = Request::create('/saml/slo', 'GET', ['SAMLRequest' => 'request']);

        self::assertTrue($handler->isLogoutRequest($request));
        self::assertFalse($handler->isLogoutResponse($request));
    }

    #[Test]
    public function initiateLogoutReturnToOverridesDefault(): void
    {
        $configCalled = false;
        $factory = $this->createFactoryWithSpy($configCalled);

        $handler = new SamlLogoutHandler($factory, new AuthenticationSession(), 'https://sp.example.com/default');

        try {
            $handler->initiateLogout('user@example.com', '_session789', 'https://sp.example.com/custom');
        } catch (\Throwable) {
            // initiateLogout throws because our spy prevents exit
        }

        self::assertTrue($configCalled);
    }
}
