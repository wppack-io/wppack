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

namespace WpPack\Component\Security\Bridge\SAML;

use LightSaml\Context\Profile\MessageContext;
use LightSaml\Helper;
use LightSaml\Model\Assertion\Assertion;
use LightSaml\Model\Protocol\Response as SamlResponse;
use LightSaml\Model\XmlDSig\AbstractSignatureReader;
use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Authentication\AuthenticatorInterface;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Bridge\SAML\Badge\SamlAttributesBadge;
use WpPack\Component\Security\Bridge\SAML\Event\SamlResponseReceivedEvent;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\Multisite\CrossSiteRedirector;
use WpPack\Component\Security\Bridge\SAML\Session\SamlSessionManager;
use WpPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolverInterface;
use WpPack\Component\Security\Exception\AuthenticationException;
use WpPack\Component\Site\BlogContextInterface;
use WpPack\Component\Transient\TransientManager;

final class SamlAuthenticator implements AuthenticatorInterface
{
    private const REQUEST_ID_TRANSIENT = '_wppack_saml_request_id';

    private ?string $lastNameId = null;
    private ?string $lastSessionIndex = null;

    public function __construct(
        private readonly SamlAuthFactory $authFactory,
        private readonly SamlUserResolverInterface $userResolver,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly BlogContextInterface $blogContext,
        private readonly TransientManager $transientManager,
        private readonly ?SamlSessionManager $sessionManager = null,
        private readonly string $acsPath = '/saml/acs',
        private readonly ?CrossSiteRedirector $crossSiteRedirector = null,
        private readonly bool $addUserToBlog = true,
    ) {}

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && $request->post->has('SAMLResponse')
            && $request->getPathInfo() === $this->acsPath;
    }

    public function authenticate(Request $request): Passport
    {
        $relayState = $request->post->get('RelayState');

        if ($this->crossSiteRedirector !== null && $relayState !== null) {
            if ($this->crossSiteRedirector->needsRedirect($relayState)) {
                // @codeCoverageIgnoreStart
                $this->crossSiteRedirector->redirect(
                    $relayState,
                    $request->post->get('SAMLResponse', ''),
                    $relayState,
                );
                // @codeCoverageIgnoreEnd
            }
        }

        $symfonyRequest = SamlAuthFactory::toSymfonyRequest($request);
        $bindingFactory = $this->authFactory->createBindingFactory();

        try {
            $binding = $bindingFactory->getBindingByRequest($symfonyRequest);
            $messageContext = new MessageContext();
            $binding->receive($symfonyRequest, $messageContext);
        } catch (\Throwable $e) {
            do_action('wppack_saml_authentication_error', [$e->getMessage()], $e->getMessage());

            throw new AuthenticationException(\sprintf(
                'SAML authentication failed: %s',
                $e->getMessage(),
            ), previous: $e);
        }

        $response = $messageContext->asResponse();

        if (!$response instanceof SamlResponse) {
            throw new AuthenticationException('SAML message is not a Response.');
        }

        // Validate status
        $status = $response->getStatus();
        if (!$status->isSuccess()) {
            $statusCode = $status->getStatusCode()->getValue();
            $statusMessage = $status->getStatusMessage();
            $detail = \sprintf(
                'SAML authentication failed: status=%s %s',
                $statusCode,
                $statusMessage ?? '',
            );

            do_action('wppack_saml_authentication_error', [$statusCode], $statusMessage);

            throw new AuthenticationException(trim($detail));
        }

        // Validate signature on response or assertion
        $this->validateSignature($response);

        // Validate InResponseTo to prevent response substitution attacks
        $this->validateInResponseTo($response);

        $assertion = $response->getFirstAssertion();

        if ($assertion === null) {
            throw new AuthenticationException('No Assertion in SAML response.');
        }

        // Validate assertion time conditions (NotBefore / NotOnOrAfter)
        $this->validateAssertionConditions($assertion);

        $subject = $assertion->getSubject();
        $nameIdObj = $subject->getNameID();
        $nameId = $nameIdObj->getValue();

        if ($nameId === '') {
            throw new AuthenticationException('No NameID in SAML response.');
        }

        $attributes = $this->extractAttributes($assertion);
        $sessionIndex = $assertion->getFirstAuthnStatement()?->getSessionIndex();

        $this->lastNameId = $nameId;
        $this->lastSessionIndex = $sessionIndex;

        $this->dispatcher->dispatch(new SamlResponseReceivedEvent(
            $nameId,
            $attributes,
            $sessionIndex,
        ));

        do_action('wppack_saml_authenticated', $nameId, $attributes);

        $userResolver = $this->userResolver;

        return new SelfValidatingPassport(
            new UserBadge(
                $nameId,
                static fn(string $identifier): \WP_User => $userResolver->resolveUser($identifier, $attributes),
            ),
            [new SamlAttributesBadge($nameId, $attributes, $sessionIndex)],
        );
    }

    public function createToken(Passport $passport): TokenInterface
    {
        $user = $passport->getUser();

        $blogId = null;

        if ($this->crossSiteRedirector !== null && $this->blogContext->isMultisite()) {
            $blogId = $this->blogContext->getCurrentBlogId();
        }

        return new PostAuthenticationToken(
            $user,
            $user->roles,
            $blogId,
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();

        if ($this->sessionManager !== null && $this->lastNameId !== null) {
            $this->sessionManager->save($user->ID, $this->lastNameId, $this->lastSessionIndex);
        }

        if ($this->addUserToBlog && $this->blogContext->isMultisite()) {
            if (!is_user_member_of_blog($user->ID)) {
                $role = !empty($user->roles) ? $user->roles[0] : 'subscriber';
                add_user_to_blog($this->blogContext->getCurrentBlogId(), $user->ID, $role);
            }
        }

        $relayState = $request->post->get('RelayState');
        $fallback = admin_url();

        if ($relayState !== null && $this->isSameOrigin($relayState)) {
            $redirect = wp_validate_redirect($relayState, $fallback);
        } else {
            $redirect = $fallback;
        }

        return new RedirectResponse($redirect);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        do_action('wppack_saml_authentication_failed', $exception);

        return new RedirectResponse(site_url('wp-login.php', 'login') . '?saml_error=true');
    }

    /**
     * Validate the XML signature on the SAML Response or its first Assertion.
     */
    private function validateSignature(SamlResponse $response): void
    {
        $config = $this->authFactory->getConfiguration();

        if (!$config->wantAssertionsSigned() && !$config->isStrict()) {
            return;
        }

        $credential = $this->authFactory->createCredential();

        // Try response-level signature first
        $signature = $response->getSignature();
        if ($signature instanceof AbstractSignatureReader) {
            $signature->validate($credential->getPublicKey());

            return;
        }

        // Try assertion-level signature
        $assertion = $response->getFirstAssertion();
        if ($assertion !== null) {
            $assertionSignature = $assertion->getSignature();
            if ($assertionSignature instanceof AbstractSignatureReader) {
                $assertionSignature->validate($credential->getPublicKey());

                return;
            }
        }

        if ($config->wantAssertionsSigned()) {
            throw new AuthenticationException('SAML response has no valid signature.');
        }
    }

    /**
     * Validate InResponseTo attribute matches the original AuthnRequest ID.
     *
     * The request ID is stored in a transient by SamlEntryPoint when the
     * AuthnRequest is sent. This prevents response substitution attacks
     * where an attacker replays a response from a different request.
     *
     * For IdP-initiated SSO (no prior AuthnRequest), InResponseTo is absent
     * and validation is skipped.
     */
    private function validateInResponseTo(SamlResponse $response): void
    {
        $inResponseTo = $response->getInResponseTo();

        if ($inResponseTo === '') {
            // IdP-initiated SSO — no InResponseTo to validate
            return;
        }

        $storedRequestId = $this->transientManager->get(self::REQUEST_ID_TRANSIENT);

        // One-time use: delete after retrieval
        $this->transientManager->delete(self::REQUEST_ID_TRANSIENT);

        if (!\is_string($storedRequestId) || $storedRequestId === '') {
            throw new AuthenticationException('SAML InResponseTo validation failed: no stored request ID.');
        }

        if ($inResponseTo !== $storedRequestId) {
            throw new AuthenticationException('SAML InResponseTo validation failed: response does not match request.');
        }
    }

    /**
     * Validate Assertion time conditions (NotBefore / NotOnOrAfter).
     *
     * Allows 120 seconds clock skew to accommodate clock differences between
     * IdP and SP servers.
     */
    private function validateAssertionConditions(Assertion $assertion): void
    {
        $conditions = $assertion->getConditions();

        if ($conditions === null) {
            return;
        }

        $now = time();
        $allowedSkew = 120;

        $notBefore = $conditions->getNotBeforeTimestamp();
        if ($notBefore !== null && !Helper::validateNotBefore($notBefore, $now, $allowedSkew)) {
            throw new AuthenticationException('Assertion condition NotBefore is in the future.');
        }

        $notOnOrAfter = $conditions->getNotOnOrAfterTimestamp();
        if ($notOnOrAfter !== null && !Helper::validateNotOnOrAfter($notOnOrAfter, $now, $allowedSkew)) {
            throw new AuthenticationException('Assertion condition NotOnOrAfter is in the past.');
        }
    }

    /**
     * Extract attributes from all AttributeStatements in the Assertion.
     *
     * @return array<string, list<string>>
     */
    private function extractAttributes(Assertion $assertion): array
    {
        $attributes = [];

        foreach ($assertion->getAllAttributeStatements() as $statement) {
            foreach ($statement->getAllAttributes() as $attribute) {
                $name = $attribute->getName();
                $values = $attribute->getAllAttributeValues();
                if (isset($attributes[$name])) {
                    $attributes[$name] = array_merge($attributes[$name], $values);
                } else {
                    $attributes[$name] = $values;
                }
            }
        }

        return $attributes;
    }

    private function isSameOrigin(string $url): bool
    {
        $host = parse_url($url, \PHP_URL_HOST);

        if ($host === null || $host === false) {
            return false;
        }

        $siteHost = parse_url(home_url(), \PHP_URL_HOST);

        return $siteHost !== null && $host === $siteHost;
    }
}
