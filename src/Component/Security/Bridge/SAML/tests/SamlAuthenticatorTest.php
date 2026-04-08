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
use LightSaml\Credential\X509Credential;
use LightSaml\Model\Assertion\Assertion;
use LightSaml\Model\Assertion\Attribute;
use LightSaml\Model\Assertion\AttributeStatement;
use LightSaml\Model\Assertion\AuthnStatement;
use LightSaml\Model\Assertion\NameID;
use LightSaml\Model\Assertion\Subject;
use LightSaml\Model\Protocol\Response as SamlResponse;
use LightSaml\Model\Protocol\Status;
use LightSaml\Model\Protocol\StatusCode;
use LightSaml\Model\XmlDSig\AbstractSignatureReader;
use LightSaml\SamlConstants;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Bridge\SAML\Badge\SamlAttributesBadge;
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\Multisite\CrossSiteRedirector;
use WpPack\Component\Security\Bridge\SAML\SamlAuthenticator;
use WpPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolverInterface;
use WpPack\Component\Security\Exception\AuthenticationException;
use WpPack\Component\Site\BlogContext;
use WpPack\Component\Transient\TransientManager;

#[CoversClass(SamlAuthenticator::class)]
final class SamlAuthenticatorTest extends TestCase
{
    private SamlAuthFactory $factory;
    private SamlUserResolverInterface $userResolver;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->factory = $this->createMock(SamlAuthFactory::class);
        $this->userResolver = $this->createMock(SamlUserResolverInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    private function createAuthenticator(string $acsPath = '/saml/acs'): SamlAuthenticator
    {
        return new SamlAuthenticator(
            $this->factory,
            $this->userResolver,
            $this->eventDispatcher,
            blogContext: new BlogContext(),
            transientManager: new TransientManager(),
            acsPath: $acsPath,
        );
    }

    /**
     * Configure factory mock to return a BindingFactory whose binding
     * populates the MessageContext with the given SamlResponse.
     */
    private function configureMockBinding(SamlResponse $samlResponse): void
    {
        $binding = $this->createMock(AbstractBinding::class);
        $binding->method('receive')
            ->willReturnCallback(function ($request, MessageContext $messageContext) use ($samlResponse): void {
                $messageContext->setMessage($samlResponse);
            });

        $bindingFactory = $this->createMock(BindingFactory::class);
        $bindingFactory->method('getBindingByRequest')->willReturn($binding);

        $this->factory->method('createBindingFactory')->willReturn($bindingFactory);
    }

    /**
     * Build a successful SamlResponse with the given attributes.
     *
     * @param array<string, list<string>> $attributes
     */
    private function buildSamlResponse(
        string $nameId = 'user@example.com',
        array $attributes = [],
        ?string $sessionIndex = '_session123',
        bool $signed = true,
    ): SamlResponse {
        $nameIdObj = new NameID($nameId, SamlConstants::NAME_ID_FORMAT_UNSPECIFIED);

        $subject = new Subject();
        $subject->setNameID($nameIdObj);

        $assertion = new Assertion();
        $assertion->setSubject($subject);

        if ($attributes !== []) {
            $attrStatement = new AttributeStatement();
            foreach ($attributes as $name => $values) {
                $attr = new Attribute($name);
                foreach ($values as $value) {
                    $attr->addAttributeValue($value);
                }
                $attrStatement->addAttribute($attr);
            }
            $assertion->addItem($attrStatement);
        }

        if ($sessionIndex !== null) {
            $authnStatement = new AuthnStatement();
            $authnStatement->setSessionIndex($sessionIndex);
            $assertion->addItem($authnStatement);
        }

        if ($signed) {
            $signature = $this->createMock(AbstractSignatureReader::class);
            $assertion->setSignature($signature);
        }

        $status = new Status(new StatusCode(SamlConstants::STATUS_SUCCESS));

        $response = new SamlResponse();
        $response->setStatus($status);
        $response->addAssertion($assertion);

        return $response;
    }

    private function configureFactoryWithConfiguration(): void
    {
        $config = new SamlConfiguration(
            idpSettings: new IdpSettings(
                entityId: 'https://idp.example.com',
                ssoUrl: 'https://idp.example.com/sso',
                sloUrl: 'https://idp.example.com/slo',
                x509Cert: 'MIICDummyCert==',
            ),
            spSettings: new SpSettings(
                entityId: 'https://sp.example.com',
                acsUrl: 'https://sp.example.com/acs',
            ),
        );

        $this->factory->method('getConfiguration')->willReturn($config);

        $mockKey = $this->createMock(XMLSecurityKey::class);
        $credential = $this->createMock(X509Credential::class);
        $credential->method('getPublicKey')->willReturn($mockKey);
        $this->factory->method('createCredential')->willReturn($credential);
    }

    #[Test]
    public function supportsWithValidRequest(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['SAMLResponse' => 'base64encodedresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        self::assertTrue($authenticator->supports($request));
    }

    #[Test]
    public function supportsWithGetRequest(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/saml/acs'],
        );

        self::assertFalse($authenticator->supports($request));
    }

    #[Test]
    public function supportsWithoutSamlResponse(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['action' => 'login'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        self::assertFalse($authenticator->supports($request));
    }

    #[Test]
    public function supportsWithWrongPath(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['SAMLResponse' => 'base64encodedresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login'],
        );

        self::assertFalse($authenticator->supports($request));
    }

    #[Test]
    public function supportsWithSubdirectoryInstall(): void
    {
        $authenticator = $this->createAuthenticator(acsPath: '/wp/saml/acs');

        $request = new Request(
            post: ['SAMLResponse' => 'base64encodedresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/wp/saml/acs'],
        );

        self::assertTrue($authenticator->supports($request));
    }

    #[Test]
    public function authenticate(): void
    {
        $samlResponse = $this->buildSamlResponse(
            nameId: 'user@example.com',
            attributes: ['email' => ['user@example.com']],
            sessionIndex: '_session123',
        );

        $this->configureMockBinding($samlResponse);
        $this->configureFactoryWithConfiguration();

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['SAMLResponse' => 'base64encodedresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        self::assertTrue($passport->hasBadge(UserBadge::class));
        self::assertTrue($passport->hasBadge(SamlAttributesBadge::class));

        $samlBadge = $passport->getBadge(SamlAttributesBadge::class);
        self::assertInstanceOf(SamlAttributesBadge::class, $samlBadge);
        self::assertSame('user@example.com', $samlBadge->getNameId());
        self::assertSame(['email' => ['user@example.com']], $samlBadge->getAttributes());
        self::assertSame('_session123', $samlBadge->getSessionIndex());
    }

    #[Test]
    public function authenticateWithErrors(): void
    {
        $bindingFactory = $this->createMock(BindingFactory::class);
        $bindingFactory->method('getBindingByRequest')
            ->willThrowException(new \RuntimeException('Signature validation failed'));

        $this->factory->method('createBindingFactory')->willReturn($bindingFactory);

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['SAMLResponse' => 'invalidresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('SAML authentication failed: Signature validation failed');

        $authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateWithErrorsIncludesDetailInMessageButNotInSafeMessage(): void
    {
        $bindingFactory = $this->createMock(BindingFactory::class);
        $bindingFactory->method('getBindingByRequest')
            ->willThrowException(new \RuntimeException('Signature validation failed. Certificate mismatch.'));

        $this->factory->method('createBindingFactory')->willReturn($bindingFactory);

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['SAMLResponse' => 'invalidresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        try {
            $authenticator->authenticate($request);
            self::fail('Expected AuthenticationException was not thrown.');
        } catch (AuthenticationException $e) {
            // getMessage() contains SAML error details for developers/logs
            self::assertStringContainsString('Signature validation failed', $e->getMessage());
            self::assertStringContainsString('Certificate mismatch', $e->getMessage());

            // getSafeMessage() remains generic (no detail leak to users)
            self::assertSame('Authentication failed.', $e->getSafeMessage());
        }
    }

    #[Test]
    public function createToken(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $authenticator = $this->createAuthenticator();

        $userBadge = new UserBadge('user@example.com', fn() => $user);
        $passport = new SelfValidatingPassport($userBadge);

        $token = $authenticator->createToken($passport);

        self::assertInstanceOf(PostAuthenticationToken::class, $token);
    }

    #[Test]
    public function onAuthenticationFailure(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $exception = new AuthenticationException('SAML authentication failed');

        $response = $authenticator->onAuthenticationFailure($request, $exception);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(site_url('wp-login.php', 'login') . '?saml_error=true', $response->url);
    }

    #[Test]
    public function onAuthenticationSuccess(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringContainsString('/wp-admin', $response->url);
    }

    #[Test]
    public function onAuthenticationSuccessIgnoresNonSameOriginRelayState(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['RelayState' => 'https://evil.example.com/phishing'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringNotContainsString('evil.example.com', $response->url);
        self::assertStringContainsString('/wp-admin', $response->url);
    }

    #[Test]
    public function onAuthenticationSuccessWithSameOriginRelayState(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        $authenticator = $this->createAuthenticator();

        $siteUrl = home_url('/custom-page');

        $request = new Request(
            post: ['RelayState' => $siteUrl],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    #[Test]
    public function onAuthenticationSuccessWithoutRelayState(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: [],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringContainsString('/wp-admin', $response->url);
    }

    #[Test]
    public function onAuthenticationSuccessWithInvalidRelayStateUrl(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $token = new PostAuthenticationToken($user, ['subscriber']);

        $authenticator = $this->createAuthenticator();

        // RelayState with no host
        $request = new Request(
            post: ['RelayState' => '/relative/path'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $token);

        self::assertInstanceOf(RedirectResponse::class, $response);
        // Should fall back to admin_url()
        self::assertStringContainsString('/wp-admin', $response->url);
    }

    #[Test]
    public function authenticateDispatchesEvent(): void
    {
        $samlResponse = $this->buildSamlResponse(
            nameId: 'user@example.com',
            attributes: ['email' => ['user@example.com']],
            sessionIndex: '_session456',
        );

        $this->configureMockBinding($samlResponse);
        $this->configureFactoryWithConfiguration();

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch');

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['SAMLResponse' => 'base64encodedresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $authenticator->authenticate($request);
    }

    #[Test]
    public function authenticateWithCrossSiteRedirectorNoRedirectNeeded(): void
    {
        $samlResponse = $this->buildSamlResponse(
            nameId: 'user@example.com',
            attributes: ['email' => ['user@example.com']],
            sessionIndex: '_session',
        );

        $this->configureMockBinding($samlResponse);
        $this->configureFactoryWithConfiguration();

        // CrossSiteRedirector is final, use a real instance
        // needsRedirect returns false for same-host URLs
        $crossSiteRedirector = new CrossSiteRedirector();

        $authenticator = new SamlAuthenticator(
            $this->factory,
            $this->userResolver,
            $this->eventDispatcher,
            blogContext: new BlogContext(),
            transientManager: new TransientManager(),
            acsPath: '/saml/acs',
            crossSiteRedirector: $crossSiteRedirector,
        );

        // Use a same-site relay state so needsRedirect returns false
        $sameHostUrl = site_url('/custom-page');

        $request = new Request(
            post: ['SAMLResponse' => 'base64response', 'RelayState' => $sameHostUrl],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    #[Test]
    public function authenticateWithCrossSiteRedirectorWithNullRelayState(): void
    {
        $samlResponse = $this->buildSamlResponse(
            nameId: 'user@example.com',
            attributes: ['email' => ['user@example.com']],
            sessionIndex: '_session',
        );

        $this->configureMockBinding($samlResponse);
        $this->configureFactoryWithConfiguration();

        // CrossSiteRedirector is final, use a real instance
        $crossSiteRedirector = new CrossSiteRedirector();

        $authenticator = new SamlAuthenticator(
            $this->factory,
            $this->userResolver,
            $this->eventDispatcher,
            blogContext: new BlogContext(),
            transientManager: new TransientManager(),
            acsPath: '/saml/acs',
            crossSiteRedirector: $crossSiteRedirector,
        );

        $request = new Request(
            post: ['SAMLResponse' => 'base64response'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    #[Test]
    public function createTokenWithCrossSiteRedirectorOnNonMultisite(): void
    {
        if (is_multisite()) {
            self::markTestSkipped('This test requires a non-multisite installation.');
        }

        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['subscriber'];

        $crossSiteRedirector = new CrossSiteRedirector();

        $authenticator = new SamlAuthenticator(
            $this->factory,
            $this->userResolver,
            $this->eventDispatcher,
            blogContext: new BlogContext(),
            transientManager: new TransientManager(),
            acsPath: '/saml/acs',
            crossSiteRedirector: $crossSiteRedirector,
        );

        $userBadge = new UserBadge('user@example.com', fn() => $user);
        $passport = new SelfValidatingPassport($userBadge);

        $token = $authenticator->createToken($passport);

        self::assertInstanceOf(PostAuthenticationToken::class, $token);
        // On non-multisite, blogId should be null even with crossSiteRedirector
        self::assertNull($token->getBlogId());
    }

    #[Test]
    public function createTokenWithoutCrossSiteRedirector(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 1;
        $user->roles = ['editor'];

        // No crossSiteRedirector provided
        $authenticator = $this->createAuthenticator();

        $userBadge = new UserBadge('user@example.com', fn() => $user);
        $passport = new SelfValidatingPassport($userBadge);

        $token = $authenticator->createToken($passport);

        self::assertInstanceOf(PostAuthenticationToken::class, $token);
        // blogId should be null when no crossSiteRedirector
        self::assertNull($token->getBlogId());
    }

    #[Test]
    public function authenticateWithNullSessionIndex(): void
    {
        $samlResponse = $this->buildSamlResponse(
            nameId: 'user@example.com',
            attributes: [],
            sessionIndex: null,
        );

        $this->configureMockBinding($samlResponse);
        $this->configureFactoryWithConfiguration();

        $authenticator = $this->createAuthenticator();

        $request = new Request(
            post: ['SAMLResponse' => 'base64encodedresponse'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);

        $samlBadge = $passport->getBadge(SamlAttributesBadge::class);
        self::assertInstanceOf(SamlAttributesBadge::class, $samlBadge);
        self::assertNull($samlBadge->getSessionIndex());
    }
}
