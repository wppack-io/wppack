<?php

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
use WpPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolverInterface;
use WpPack\Component\Security\Exception\AuthenticationException;

final class SamlAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly SamlAuthFactory $authFactory,
        private readonly SamlUserResolverInterface $userResolver,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly string $acsPath = '/sso/verify',
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
                $this->crossSiteRedirector->redirect(
                    $relayState,
                    $request->post->get('SAMLResponse', ''),
                    $relayState,
                );
            }
        }

        $auth = $this->authFactory->create();
        $auth->processResponse();

        $errors = $auth->getErrors();

        if ($errors !== []) {
            if (function_exists('do_action')) {
                do_action('wppack_saml_authentication_error', $errors, $auth->getLastErrorReason());
            }

            throw new AuthenticationException('SAML authentication failed.');
        }

        $nameId = $auth->getNameId();
        $attributes = $auth->getAttributes();
        $sessionIndex = $auth->getSessionIndex();

        $this->dispatcher->dispatch(new SamlResponseReceivedEvent(
            $nameId,
            $attributes,
            $sessionIndex,
        ));

        if (function_exists('do_action')) {
            do_action('wppack_saml_authenticated', $nameId, $attributes);
        }

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

        if ($this->crossSiteRedirector !== null && function_exists('is_multisite') && is_multisite()) {
            $blogId = function_exists('get_current_blog_id') ? get_current_blog_id() : null;
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

        if ($this->addUserToBlog && function_exists('is_multisite') && is_multisite()) {
            if (function_exists('is_user_member_of_blog') && !is_user_member_of_blog($user->ID)) {
                if (function_exists('add_user_to_blog') && function_exists('get_current_blog_id')) {
                    $role = !empty($user->roles) ? $user->roles[0] : 'subscriber';
                    add_user_to_blog(get_current_blog_id(), $user->ID, $role);
                }
            }
        }

        $relayState = $request->post->get('RelayState');
        $fallback = function_exists('admin_url') ? admin_url() : '/wp-admin/';

        if ($relayState !== null && $this->isSameOrigin($relayState) && function_exists('wp_validate_redirect')) {
            $redirect = wp_validate_redirect($relayState, $fallback);
        } else {
            $redirect = $fallback;
        }

        return new RedirectResponse($redirect);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if (function_exists('do_action')) {
            do_action('wppack_saml_authentication_failed', $exception);
        }

        $loginUrl = function_exists('wp_login_url') ? wp_login_url() : '/wp-login.php';

        return new RedirectResponse($loginUrl . '?saml_error=1');
    }

    private function isSameOrigin(string $url): bool
    {
        $host = parse_url($url, \PHP_URL_HOST);

        if ($host === null || $host === false) {
            return false;
        }

        if (!function_exists('home_url')) {
            return false;
        }

        $siteHost = parse_url(home_url(), \PHP_URL_HOST);

        return $siteHost !== null && $host === $siteHost;
    }
}
