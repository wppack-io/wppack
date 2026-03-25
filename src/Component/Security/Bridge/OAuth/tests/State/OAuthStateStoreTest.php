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

namespace WpPack\Component\Security\Bridge\OAuth\Tests\State;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\State\OAuthStateStore;
use WpPack\Component\Security\Bridge\OAuth\State\StoredState;

#[CoversClass(OAuthStateStore::class)]
final class OAuthStateStoreTest extends TestCase
{
    #[Test]
    public function storeAndRetrieve(): void
    {
        $store = new OAuthStateStore();
        $state = bin2hex(random_bytes(16));
        $storedState = StoredState::create('test-nonce', 'test-verifier', 'https://example.com/return');

        $store->store($state, $storedState);
        $retrieved = $store->retrieve($state);

        self::assertNotNull($retrieved);
        self::assertSame('test-nonce', $retrieved->getNonce());
        self::assertSame('test-verifier', $retrieved->getCodeVerifier());
        self::assertSame('https://example.com/return', $retrieved->getReturnTo());
    }

    #[Test]
    public function retrieveIsOneTimeUse(): void
    {
        $store = new OAuthStateStore();
        $state = bin2hex(random_bytes(16));
        $storedState = StoredState::create('test-nonce', null, null);

        $store->store($state, $storedState);

        // First retrieval succeeds
        $first = $store->retrieve($state);
        self::assertNotNull($first);

        // Second retrieval returns null (deleted)
        $second = $store->retrieve($state);
        self::assertNull($second);
    }

    #[Test]
    public function retrieveReturnsNullForNonExistentState(): void
    {
        $store = new OAuthStateStore();

        self::assertNull($store->retrieve('non-existent-state'));
    }

    #[Test]
    public function retrieveReturnsNullForExpiredState(): void
    {
        $store = new OAuthStateStore();
        $state = bin2hex(random_bytes(16));

        // Create a state with a very old timestamp
        $storedState = new StoredState('test-nonce', null, null, time() - 700);

        $store->store($state, $storedState);
        $retrieved = $store->retrieve($state);

        self::assertNull($retrieved);
    }

}
