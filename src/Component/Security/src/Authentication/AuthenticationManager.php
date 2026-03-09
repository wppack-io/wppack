<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Authentication;

use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Event\AuthenticationFailureEvent;
use WpPack\Component\Security\Event\AuthenticationSuccessEvent;
use WpPack\Component\Security\Event\CheckPassportEvent;
use WpPack\Component\Security\Exception\AuthenticationException;

final class AuthenticationManager implements AuthenticationManagerInterface
{
    /** @var list<AuthenticatorInterface> */
    private array $authenticators = [];

    private ?TokenInterface $token = null;

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
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
    public function handleAuthentication(mixed $user, string $username, string $password): mixed
    {
        // If already authenticated (e.g., by another filter), pass through
        if ($user instanceof \WP_User) {
            return $user;
        }

        $request = Request::createFromGlobals();

        foreach ($this->authenticators as $authenticator) {
            // Skip stateless authenticators (handled by determine_current_user)
            if ($authenticator instanceof StatelessAuthenticatorInterface) {
                continue;
            }

            if (!$authenticator->supports($request)) {
                continue;
            }

            try {
                $passport = $authenticator->authenticate($request);
                $this->dispatcher->dispatch(new CheckPassportEvent($authenticator, $passport));
                $passport->ensureAllBadgesResolved();

                $token = $authenticator->createToken($passport);
                $this->token = $token;

                $this->dispatcher->dispatch(new AuthenticationSuccessEvent($token));
                $authenticator->onAuthenticationSuccess($request, $token);

                return $token->getUser();
            } catch (AuthenticationException $e) {
                $this->dispatcher->dispatch(new AuthenticationFailureEvent($e));
                $authenticator->onAuthenticationFailure($request, $e);

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

        $request = Request::createFromGlobals();

        foreach ($this->authenticators as $authenticator) {
            if (!$authenticator instanceof StatelessAuthenticatorInterface) {
                continue;
            }

            if (!$authenticator->supports($request)) {
                continue;
            }

            try {
                $passport = $authenticator->authenticate($request);
                $this->dispatcher->dispatch(new CheckPassportEvent($authenticator, $passport));
                $passport->ensureAllBadgesResolved();

                $token = $authenticator->createToken($passport);
                $this->token = $token;

                $this->dispatcher->dispatch(new AuthenticationSuccessEvent($token));
                $authenticator->onAuthenticationSuccess($request, $token);

                return $token->getUser()->ID;
            } catch (AuthenticationException $e) {
                $this->dispatcher->dispatch(new AuthenticationFailureEvent($e));
                $authenticator->onAuthenticationFailure($request, $e);
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

    /**
     * Register WordPress filter hooks.
     */
    public function register(): void
    {
        add_filter('authenticate', [$this, 'handleAuthentication'], 10, 3);
        add_filter('determine_current_user', [$this, 'handleStatelessAuthentication'], 30, 1);
    }
}
