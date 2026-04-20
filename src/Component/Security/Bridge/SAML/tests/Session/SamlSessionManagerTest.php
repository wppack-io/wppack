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

namespace WPPack\Component\Security\Bridge\SAML\Tests\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Bridge\SAML\Session\SamlSessionManager;
use WPPack\Component\User\UserRepositoryInterface;

#[CoversClass(SamlSessionManager::class)]
final class SamlSessionManagerTest extends TestCase
{
    #[Test]
    public function savePersistsNameIdAndSessionIndex(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);

        $repo->expects(self::exactly(2))
            ->method('updateMeta')
            ->willReturnCallback(static function (int $userId, string $key, mixed $value) {
                self::assertSame(42, $userId);
                self::assertContains($key, ['_saml_name_id', '_saml_session_index']);
                self::assertTrue(match ($key) {
                    '_saml_name_id' => $value === 'nameId@example.com',
                    '_saml_session_index' => $value === 'sid-42',
                    default => false,
                });

                return true;
            });

        (new SamlSessionManager($repo))->save(42, 'nameId@example.com', 'sid-42');
    }

    #[Test]
    public function saveClearsSessionIndexWhenNullProvided(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);

        $repo->expects(self::once())
            ->method('updateMeta')
            ->with(42, '_saml_name_id', 'nameId@example.com');

        $repo->expects(self::once())
            ->method('deleteMeta')
            ->with(42, '_saml_session_index');

        (new SamlSessionManager($repo))->save(42, 'nameId@example.com', null);
    }

    #[Test]
    public function getNameIdReturnsStoredValue(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('getMeta')->with(42, '_saml_name_id', true)->willReturn('nameId@example.com');

        self::assertSame('nameId@example.com', (new SamlSessionManager($repo))->getNameId(42));
    }

    #[Test]
    public function getNameIdReturnsNullForEmptyString(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('getMeta')->willReturn('');

        self::assertNull((new SamlSessionManager($repo))->getNameId(42));
    }

    #[Test]
    public function getNameIdReturnsNullForNonString(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('getMeta')->willReturn(['not a string']);

        self::assertNull((new SamlSessionManager($repo))->getNameId(42));
    }

    #[Test]
    public function getSessionIndexReturnsStoredValue(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('getMeta')->with(42, '_saml_session_index', true)->willReturn('sid-42');

        self::assertSame('sid-42', (new SamlSessionManager($repo))->getSessionIndex(42));
    }

    #[Test]
    public function getSessionIndexReturnsNullWhenMissing(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('getMeta')->willReturn(false);

        self::assertNull((new SamlSessionManager($repo))->getSessionIndex(42));
    }

    #[Test]
    public function clearRemovesBothMetaKeys(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);

        $deleted = [];
        $repo->expects(self::exactly(2))
            ->method('deleteMeta')
            ->willReturnCallback(static function (int $userId, string $key) use (&$deleted): bool {
                $deleted[] = [$userId, $key];

                return true;
            });

        (new SamlSessionManager($repo))->clear(42);

        self::assertSame([
            [42, '_saml_name_id'],
            [42, '_saml_session_index'],
        ], $deleted);
    }
}
