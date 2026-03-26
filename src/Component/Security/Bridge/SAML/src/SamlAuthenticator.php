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

final class SamlAuthenticator implements AuthenticatorInterface
{
    private ?string $lastNameId = null;
    private ?string $lastSessionIndex = null;

    public function __construct(
        private readonly SamlAuthFactory $authFactory,
        private readonly SamlUserResolverInterface $userResolver,
        private readonly EventDispatcherInterface $dispatcher,
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

        $auth = $this->authFactory->create();

        // onelogin/php-saml reads $_POST directly. WordPress's wp_magic_quotes()
        // has already applied addslashes() to $_POST, corrupting Base64 data.
        // Temporarily replace $_POST with the clean (wp_unslash'd) Request data.
        $originalPost = $_POST;
        $_POST = $request->post->all();

        try {
            $auth->processResponse();
        } finally {
            $_POST = $originalPost;
        }

        $errors = $auth->getErrors();

        if ($errors !== []) {
            $reason = $auth->getLastErrorReason();
            $detail = sprintf(
                'SAML authentication failed: [%s] %s',
                implode(', ', $errors),
                $reason ?? 'unknown reason',
            );

            do_action('wppack_saml_authentication_error', $errors, $reason);

            throw new AuthenticationException($detail);
        }

        $nameId = $auth->getNameId();
        $attributes = $auth->getAttributes();
        $sessionIndex = $auth->getSessionIndex();

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

        // @codeCoverageIgnoreStart
        if ($this->crossSiteRedirector !== null && is_multisite()) {
            $blogId = get_current_blog_id();
        }
        // @codeCoverageIgnoreEnd

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

        // @codeCoverageIgnoreStart
        if ($this->addUserToBlog && is_multisite()) {
            if (!is_user_member_of_blog($user->ID)) {
                $role = !empty($user->roles) ? $user->roles[0] : 'subscriber';
                add_user_to_blog(get_current_blog_id(), $user->ID, $role);
            }
        }
        // @codeCoverageIgnoreEnd

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
