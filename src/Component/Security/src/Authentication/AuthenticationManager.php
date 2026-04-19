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

namespace WPPack\Component\Security\Authentication;

use Psr\EventDispatcher\EventDispatcherInterface;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Security\Authentication\Token\TokenInterface;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Event\AuthenticationFailureEvent;
use WPPack\Component\Security\Event\AuthenticationSuccessEvent;
use WPPack\Component\Security\Event\CheckPassportEvent;
use WPPack\Component\Security\Exception\AuthenticationException;

final class AuthenticationManager implements AuthenticationManagerInterface
{
    /** @var list<AuthenticatorInterface> */
    private array $authenticators = [];

    private ?TokenInterface $token = null;

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly Request $request,
        private readonly AuthenticationSession $authSession,
    ) {}

    public function addAuthenticator(AuthenticatorInterface $authenticator): void
    {
        $this->authenticators[] = $authenticator;
    }

    /**
     * WordPress `authenticate` filter handler (priority 10).
     *
     * Returns \WP_User on success, passes through $user otherwise.
     */
    public function handleAuthentication(mixed $user, string $username, #[\SensitiveParameter] string $password): mixed
    {
        // If already authenticated (e.g., by another filter), pass through
        if ($user instanceof \WP_User) {
            return $user;
        }

        foreach ($this->authenticators as $authenticator) {
            // Skip stateless authenticators (handled by determine_current_user)
            if ($authenticator instanceof StatelessAuthenticatorInterface) {
                continue;
            }

            if (!$authenticator->supports($this->request)) {
                continue;
            }

            try {
                $passport = $authenticator->authenticate($this->request);
                $this->dispatcher->dispatch(new CheckPassportEvent($authenticator, $passport));
                $passport->ensureAllBadgesResolved();

                $token = $authenticator->createToken($passport);
                $this->token = $token;

                $this->dispatcher->dispatch(new AuthenticationSuccessEvent($token));
                $response = $authenticator->onAuthenticationSuccess($this->request, $token);

                if ($response !== null) {
                    $this->establishAuthSession($token);
                    $response->send();
                }

                return $token->getUser();
            } catch (AuthenticationException $e) {
                $this->dispatcher->dispatch(new AuthenticationFailureEvent($e));
                $response = $authenticator->onAuthenticationFailure($this->request, $e);
                $response?->send();

                return new \WP_Error('authentication_failed', $e->getSafeMessage());
            }
        }

        return $user; // No authenticator handled this request
    }

    /**
     * WordPress `determine_current_user` filter handler (priority 30).
     *
     * Returns user ID on success, passes through $userId otherwise.
     */
    public function handleStatelessAuthentication(int $userId): int
    {
        // If already authenticated, pass through
        if ($userId > 0) {
            return $userId;
        }

        foreach ($this->authenticators as $authenticator) {
            if (!$authenticator instanceof StatelessAuthenticatorInterface) {
                continue;
            }

            if (!$authenticator->supports($this->request)) {
                continue;
            }

            try {
                $passport = $authenticator->authenticate($this->request);
                $this->dispatcher->dispatch(new CheckPassportEvent($authenticator, $passport));
                $passport->ensureAllBadgesResolved();

                $token = $authenticator->createToken($passport);
                $this->token = $token;

                $this->dispatcher->dispatch(new AuthenticationSuccessEvent($token));
                $response = $authenticator->onAuthenticationSuccess($this->request, $token);

                if ($response !== null) {
                    $this->establishAuthSession($token);
                    $response->send();
                }

                $user = $token->getUser();

                return $user !== null ? $user->ID : 0;
            } catch (AuthenticationException $e) {
                $this->dispatcher->dispatch(new AuthenticationFailureEvent($e));
                $response = $authenticator->onAuthenticationFailure($this->request, $e);
                $response?->send();
                // For stateless auth, silently continue to next authenticator
                continue;
            }
        }

        return $userId;
    }

    public function getToken(): ?TokenInterface
    {
        return $this->token;
    }

    private function establishAuthSession(TokenInterface $token): void
    {
        $user = $token->getUser();

        if ($user === null || headers_sent()) {
            return;
        }

        $this->authSession->login($user->ID, false, is_ssl());
    }

    /**
     * Register WordPress filter hooks.
     */
    public function register(): void
    {
        add_filter('authenticate', [$this, 'handleAuthentication'], 10, 3);
        add_filter('determine_current_user', [$this, 'handleStatelessAuthentication'], 30, 1);
    }
}
