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

namespace WpPack\Component\Security\Tests\Authentication;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\Authentication\AuthenticatorInterface;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\StatelessAuthenticatorInterface;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Authentication\Token\ServiceToken;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Event\AuthenticationFailureEvent;
use WpPack\Component\Security\Event\AuthenticationSuccessEvent;
use WpPack\Component\Security\Event\CheckPassportEvent;
use WpPack\Component\Security\Exception\AuthenticationException;

final class AuthenticationManagerTest extends TestCase
{
    /** @var EventDispatcherInterface&object{events: list<object>} */
    private EventDispatcherInterface $dispatcher;
    private AuthenticationManager $manager;

    protected function setUp(): void
    {
        ob_start();

        $this->dispatcher = new class implements EventDispatcherInterface {
            /** @var list<object> */
            public array $events = [];

            public function dispatch(object $event): object
            {
                $this->events[] = $event;

                return $event;
            }
        };

        $request = Request::create('/');

        $this->manager = new AuthenticationManager($this->dispatcher, $request, new AuthenticationSession());
    }

    protected function tearDown(): void
    {
        ob_end_clean();
    }

    #[Test]
    public function getTokenReturnsNullInitially(): void
    {
        self::assertNull($this->manager->getToken());
    }

    #[Test]
    public function handleAuthenticationPassesThroughWpUser(): void
    {
        $user = new \WP_User();
        $user->ID = 1;

        $result = $this->manager->handleAuthentication($user, 'admin', 'pass');

        self::assertSame($user, $result);
    }

    #[Test]
    public function handleAuthenticationSkipsStatelessAuthenticators(): void
    {
        $stateless = $this->createStatelessAuthenticator(true);
        $this->manager->addAuthenticator($stateless);

        $result = $this->manager->handleAuthentication(null, 'admin', 'pass');

        self::assertNull($result);
    }

    #[Test]
    public function handleStatelessAuthenticationSkipsNonStatelessAuthenticators(): void
    {
        $regular = $this->createRegularAuthenticator(true);
        $this->manager->addAuthenticator($regular);

        $result = $this->manager->handleStatelessAuthentication(0);

        self::assertSame(0, $result);
    }

    #[Test]
    public function handleAuthenticationReturnsUserOnSuccess(): void
    {
        $user = new \WP_User();
        $user->ID = 42;
        $user->user_login = 'testuser';
        $user->roles = ['subscriber'];

        $authenticator = $this->createRegularAuthenticator(true, $user);
        $this->manager->addAuthenticator($authenticator);

        $result = $this->manager->handleAuthentication(null, 'testuser', 'pass');

        self::assertInstanceOf(\WP_User::class, $result);
        self::assertSame(42, $result->ID);
        self::assertNotNull($this->manager->getToken());
    }

    #[Test]
    public function handleAuthenticationReturnsWpErrorOnFailure(): void
    {
        $authenticator = $this->createFailingAuthenticator();
        $this->manager->addAuthenticator($authenticator);

        $result = $this->manager->handleAuthentication(null, 'bad', 'creds');

        self::assertInstanceOf(\WP_Error::class, $result);
    }

    #[Test]
    public function handleStatelessAuthenticationReturnsUserIdOnSuccess(): void
    {
        $user = new \WP_User();
        $user->ID = 42;
        $user->user_login = 'testuser';
        $user->roles = ['subscriber'];

        $authenticator = $this->createStatelessAuthenticator(true, $user);
        $this->manager->addAuthenticator($authenticator);

        $result = $this->manager->handleStatelessAuthentication(0);

        self::assertSame(42, $result);
        self::assertNotNull($this->manager->getToken());
    }

    #[Test]
    public function handleStatelessAuthenticationPassesThroughOnAlreadyAuthenticated(): void
    {
        $result = $this->manager->handleStatelessAuthentication(99);

        self::assertSame(99, $result);
    }

    #[Test]
    public function handleAuthenticationDispatchesEvents(): void
    {
        $user = new \WP_User();
        $user->ID = 1;
        $user->user_login = 'testuser';
        $user->roles = ['subscriber'];

        $authenticator = $this->createRegularAuthenticator(true, $user);
        $this->manager->addAuthenticator($authenticator);

        $this->manager->handleAuthentication(null, 'testuser', 'pass');

        self::assertCount(2, $this->dispatcher->events);
        self::assertInstanceOf(CheckPassportEvent::class, $this->dispatcher->events[0]);
        self::assertInstanceOf(AuthenticationSuccessEvent::class, $this->dispatcher->events[1]);
    }

    #[Test]
    public function handleStatelessAuthenticationContinuesOnFailure(): void
    {
        $user = new \WP_User();
        $user->ID = 42;
        $user->user_login = 'testuser';
        $user->roles = ['subscriber'];

        $failing = $this->createFailingStatelessAuthenticator();
        $succeeding = $this->createStatelessAuthenticator(true, $user);

        $this->manager->addAuthenticator($failing);
        $this->manager->addAuthenticator($succeeding);

        $result = $this->manager->handleStatelessAuthentication(0);

        self::assertSame(42, $result);
    }

    #[Test]
    public function handleAuthenticationSkipsUnsupportedAuthenticator(): void
    {
        $unsupported = $this->createRegularAuthenticator(false);
        $this->manager->addAuthenticator($unsupported);

        $result = $this->manager->handleAuthentication(null, 'admin', 'pass');

        self::assertNull($result);
    }

    #[Test]
    public function handleStatelessAuthenticationSkipsUnsupportedAuthenticator(): void
    {
        $unsupported = $this->createStatelessAuthenticator(false);
        $this->manager->addAuthenticator($unsupported);

        $result = $this->manager->handleStatelessAuthentication(0);

        self::assertSame(0, $result);
    }

    #[Test]
    public function handleAuthenticationFailureDispatchesFailureEvent(): void
    {
        $authenticator = $this->createFailingAuthenticator();
        $this->manager->addAuthenticator($authenticator);

        $this->manager->handleAuthentication(null, 'bad', 'creds');

        self::assertCount(1, $this->dispatcher->events);
        self::assertInstanceOf(AuthenticationFailureEvent::class, $this->dispatcher->events[0]);
    }

    #[Test]
    public function handleAuthenticationFailureReturnsWpErrorWithSafeMessage(): void
    {
        $authenticator = $this->createFailingAuthenticator();
        $this->manager->addAuthenticator($authenticator);

        $result = $this->manager->handleAuthentication(null, 'bad', 'creds');

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('authentication_failed', $result->get_error_code());
    }

    #[Test]
    public function handleStatelessAuthenticationDispatchesEventsOnSuccess(): void
    {
        $user = new \WP_User();
        $user->ID = 42;
        $user->user_login = 'testuser';
        $user->roles = ['subscriber'];

        $authenticator = $this->createStatelessAuthenticator(true, $user);
        $this->manager->addAuthenticator($authenticator);

        $this->manager->handleStatelessAuthentication(0);

        self::assertCount(2, $this->dispatcher->events);
        self::assertInstanceOf(CheckPassportEvent::class, $this->dispatcher->events[0]);
        self::assertInstanceOf(AuthenticationSuccessEvent::class, $this->dispatcher->events[1]);
    }

    #[Test]
    public function handleStatelessAuthenticationFailureDispatchesFailureEvent(): void
    {
        $failing = $this->createFailingStatelessAuthenticator();
        $this->manager->addAuthenticator($failing);

        $this->manager->handleStatelessAuthentication(0);

        self::assertCount(1, $this->dispatcher->events);
        self::assertInstanceOf(AuthenticationFailureEvent::class, $this->dispatcher->events[0]);
    }

    #[Test]
    public function registerAddsWordPressFilterHooks(): void
    {
        $this->manager->register();

        try {
            self::assertSame(10, has_filter('authenticate', [$this->manager, 'handleAuthentication']));
            self::assertSame(30, has_filter('determine_current_user', [$this->manager, 'handleStatelessAuthentication']));
        } finally {
            remove_filter('authenticate', [$this->manager, 'handleAuthentication'], 10);
            remove_filter('determine_current_user', [$this->manager, 'handleStatelessAuthentication'], 30);
        }
    }

    #[Test]
    public function handleAuthenticationWithResponseFromSuccessHandler(): void
    {
        $user = new \WP_User();
        $user->ID = 42;
        $user->user_login = 'testuser';
        $user->roles = ['subscriber'];

        // Create an authenticator that returns a Response on success
        $authenticator = $this->createAuthenticatorWithSuccessResponse(true, $user);
        $this->manager->addAuthenticator($authenticator);

        // Note: this will call response->send() which calls headers_sent() etc.
        // In test env, headers may already be sent, so the response send will be a no-op
        $result = $this->manager->handleAuthentication(null, 'testuser', 'pass');

        self::assertInstanceOf(\WP_User::class, $result);
        self::assertSame(42, $result->ID);
    }

    #[Test]
    public function handleAuthenticationFailureWithResponseFromFailureHandler(): void
    {
        // Create an authenticator that returns a Response on failure
        $authenticator = $this->createFailingAuthenticatorWithResponse();
        $this->manager->addAuthenticator($authenticator);

        $result = $this->manager->handleAuthentication(null, 'bad', 'creds');

        self::assertInstanceOf(\WP_Error::class, $result);
    }

    #[Test]
    public function handleStatelessAuthenticationWithResponseFromSuccessHandler(): void
    {
        $user = new \WP_User();
        $user->ID = 42;
        $user->user_login = 'testuser';
        $user->roles = ['subscriber'];

        $authenticator = $this->createStatelessAuthenticatorWithSuccessResponse(true, $user);
        $this->manager->addAuthenticator($authenticator);

        $result = $this->manager->handleStatelessAuthentication(0);

        self::assertSame(42, $result);
    }

    #[Test]
    public function handleStatelessAuthenticationReturnsZeroForServiceToken(): void
    {
        $authenticator = $this->createServiceTokenStatelessAuthenticator();
        $this->manager->addAuthenticator($authenticator);

        $result = $this->manager->handleStatelessAuthentication(0);

        self::assertSame(0, $result);
        self::assertNotNull($this->manager->getToken());
        self::assertInstanceOf(ServiceToken::class, $this->manager->getToken());
    }

    #[Test]
    public function handleStatelessAuthenticationFailureWithResponse(): void
    {
        $authenticator = $this->createFailingStatelessAuthenticatorWithResponse();
        $this->manager->addAuthenticator($authenticator);

        $result = $this->manager->handleStatelessAuthentication(0);

        // Silently continues, returns 0 (passed in)
        self::assertSame(0, $result);
    }

    private function createRegularAuthenticator(bool $supports, ?\WP_User $user = null): AuthenticatorInterface
    {
        return new class ($supports, $user) implements AuthenticatorInterface {
            public function __construct(
                private readonly bool $doesSupport,
                private readonly ?\WP_User $user,
            ) {}

            public function supports(Request $request): bool
            {
                return $this->doesSupport;
            }

            public function authenticate(Request $request): Passport
            {
                $badge = new UserBadge('testuser', fn() => $this->user);
                $credentials = new CredentialsBadge('password');
                $credentials->markResolved();

                return new Passport($badge, $credentials);
            }

            public function createToken(Passport $passport): TokenInterface
            {
                $user = $passport->getUser();

                return new PostAuthenticationToken($user, $user->roles);
            }

            public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
            {
                return null;
            }

            public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
            {
                return null;
            }
        };
    }

    private function createStatelessAuthenticator(bool $supports, ?\WP_User $user = null): StatelessAuthenticatorInterface
    {
        return new class ($supports, $user) implements StatelessAuthenticatorInterface {
            public function __construct(
                private readonly bool $doesSupport,
                private readonly ?\WP_User $user,
            ) {}

            public function supports(Request $request): bool
            {
                return $this->doesSupport;
            }

            public function authenticate(Request $request): Passport
            {
                $badge = new UserBadge('testuser', fn() => $this->user);

                return new SelfValidatingPassport($badge);
            }

            public function createToken(Passport $passport): TokenInterface
            {
                $user = $passport->getUser();

                return new PostAuthenticationToken($user, $user->roles);
            }

            public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
            {
                return null;
            }

            public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
            {
                return null;
            }
        };
    }

    private function createFailingAuthenticator(): AuthenticatorInterface
    {
        return new class implements AuthenticatorInterface {
            public function supports(Request $request): bool
            {
                return true;
            }

            public function authenticate(Request $request): Passport
            {
                throw new AuthenticationException('Test authentication failure.');
            }

            public function createToken(Passport $passport): TokenInterface
            {
                throw new \LogicException('Should not be called.');
            }

            public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
            {
                return null;
            }

            public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
            {
                return null;
            }
        };
    }

    private function createFailingStatelessAuthenticator(): StatelessAuthenticatorInterface
    {
        return new class implements StatelessAuthenticatorInterface {
            public function supports(Request $request): bool
            {
                return true;
            }

            public function authenticate(Request $request): Passport
            {
                throw new AuthenticationException('Test stateless failure.');
            }

            public function createToken(Passport $passport): TokenInterface
            {
                throw new \LogicException('Should not be called.');
            }

            public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
            {
                return null;
            }

            public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
            {
                return null;
            }
        };
    }

    private function createAuthenticatorWithSuccessResponse(bool $supports, \WP_User $user): AuthenticatorInterface
    {
        return new class ($supports, $user) implements AuthenticatorInterface {
            public function __construct(
                private readonly bool $doesSupport,
                private readonly \WP_User $user,
            ) {}

            public function supports(Request $request): bool
            {
                return $this->doesSupport;
            }

            public function authenticate(Request $request): Passport
            {
                $badge = new UserBadge('testuser', fn() => $this->user);
                $credentials = new CredentialsBadge('password');
                $credentials->markResolved();

                return new Passport($badge, $credentials);
            }

            public function createToken(Passport $passport): TokenInterface
            {
                $user = $passport->getUser();

                return new PostAuthenticationToken($user, $user->roles);
            }

            public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
            {
                return new Response('Success', 200);
            }

            public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
            {
                return null;
            }
        };
    }

    private function createFailingAuthenticatorWithResponse(): AuthenticatorInterface
    {
        return new class implements AuthenticatorInterface {
            public function supports(Request $request): bool
            {
                return true;
            }

            public function authenticate(Request $request): Passport
            {
                throw new AuthenticationException('Test failure with response.');
            }

            public function createToken(Passport $passport): TokenInterface
            {
                throw new \LogicException('Should not be called.');
            }

            public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
            {
                return null;
            }

            public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
            {
                return new Response('Failure', 401);
            }
        };
    }

    private function createStatelessAuthenticatorWithSuccessResponse(bool $supports, \WP_User $user): StatelessAuthenticatorInterface
    {
        return new class ($supports, $user) implements StatelessAuthenticatorInterface {
            public function __construct(
                private readonly bool $doesSupport,
                private readonly \WP_User $user,
            ) {}

            public function supports(Request $request): bool
            {
                return $this->doesSupport;
            }

            public function authenticate(Request $request): Passport
            {
                $badge = new UserBadge('testuser', fn() => $this->user);

                return new SelfValidatingPassport($badge);
            }

            public function createToken(Passport $passport): TokenInterface
            {
                $user = $passport->getUser();

                return new PostAuthenticationToken($user, $user->roles);
            }

            public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
            {
                return new Response('Success', 200);
            }

            public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
            {
                return null;
            }
        };
    }

    private function createServiceTokenStatelessAuthenticator(): StatelessAuthenticatorInterface
    {
        return new class implements StatelessAuthenticatorInterface {
            public function supports(Request $request): bool
            {
                return true;
            }

            public function authenticate(Request $request): Passport
            {
                return new SelfValidatingPassport(
                    new UserBadge('scim-service'),
                );
            }

            public function createToken(Passport $passport): TokenInterface
            {
                return new ServiceToken(
                    serviceIdentifier: 'scim-service',
                    capabilities: ['scim_provision'],
                );
            }

            public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
            {
                return null;
            }

            public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
            {
                return null;
            }
        };
    }

    private function createFailingStatelessAuthenticatorWithResponse(): StatelessAuthenticatorInterface
    {
        return new class implements StatelessAuthenticatorInterface {
            public function supports(Request $request): bool
            {
                return true;
            }

            public function authenticate(Request $request): Passport
            {
                throw new AuthenticationException('Stateless failure with response.');
            }

            public function createToken(Passport $passport): TokenInterface
            {
                throw new \LogicException('Should not be called.');
            }

            public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
            {
                return null;
            }

            public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
            {
                return new Response('Failure', 401);
            }
        };
    }
}
