<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Authentication;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\Authentication\AuthenticatorInterface;
use WpPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\StatelessAuthenticatorInterface;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Exception\AuthenticationException;

final class AuthenticationManagerTest extends TestCase
{
    private EventDispatcherInterface $dispatcher;
    private AuthenticationManager $manager;

    protected function setUp(): void
    {
        $this->dispatcher = new class implements EventDispatcherInterface {
            /** @var list<object> */
            public array $events = [];

            public function dispatch(object $event): object
            {
                $this->events[] = $event;

                return $event;
            }
        };

        $this->manager = new AuthenticationManager($this->dispatcher);
    }

    #[Test]
    public function getTokenReturnsNullInitially(): void
    {
        self::assertNull($this->manager->getToken());
    }

    #[Test]
    public function handleAuthenticationPassesThroughWpUser(): void
    {
        if (!class_exists(\WP_User::class)) {
            self::markTestSkipped('WP_User class is not available.');
        }

        $user = new \WP_User();
        $user->ID = 1;

        $result = $this->manager->handleAuthentication($user, 'admin', 'pass');

        self::assertSame($user, $result);
    }

    #[Test]
    public function handleAuthenticationSkipsStatelessAuthenticators(): void
    {
        if (!class_exists(\WP_User::class)) {
            self::markTestSkipped('WP_User class is not available.');
        }

        $stateless = $this->createStatelessAuthenticator(true);
        $this->manager->addAuthenticator($stateless);

        $result = $this->manager->handleAuthentication(null, 'admin', 'pass');

        self::assertNull($result);
    }

    #[Test]
    public function handleStatelessAuthenticationSkipsNonStatelessAuthenticators(): void
    {
        if (!class_exists(\WP_User::class)) {
            self::markTestSkipped('WP_User class is not available.');
        }

        $regular = $this->createRegularAuthenticator(true);
        $this->manager->addAuthenticator($regular);

        $result = $this->manager->handleStatelessAuthentication(0);

        self::assertSame(0, $result);
    }

    #[Test]
    public function handleAuthenticationReturnsUserOnSuccess(): void
    {
        if (!class_exists(\WP_User::class)) {
            self::markTestSkipped('WP_User class is not available.');
        }

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
        if (!class_exists(\WP_User::class) || !class_exists(\WP_Error::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $authenticator = $this->createFailingAuthenticator();
        $this->manager->addAuthenticator($authenticator);

        $result = $this->manager->handleAuthentication(null, 'bad', 'creds');

        self::assertInstanceOf(\WP_Error::class, $result);
    }

    #[Test]
    public function handleStatelessAuthenticationReturnsUserIdOnSuccess(): void
    {
        if (!class_exists(\WP_User::class)) {
            self::markTestSkipped('WP_User class is not available.');
        }

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
        if (!class_exists(\WP_User::class)) {
            self::markTestSkipped('WP_User class is not available.');
        }

        $user = new \WP_User();
        $user->ID = 1;
        $user->user_login = 'testuser';
        $user->roles = ['subscriber'];

        $authenticator = $this->createRegularAuthenticator(true, $user);
        $this->manager->addAuthenticator($authenticator);

        $this->manager->handleAuthentication(null, 'testuser', 'pass');

        self::assertCount(2, $this->dispatcher->events);
    }

    #[Test]
    public function handleStatelessAuthenticationContinuesOnFailure(): void
    {
        if (!class_exists(\WP_User::class)) {
            self::markTestSkipped('WP_User class is not available.');
        }

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
}
