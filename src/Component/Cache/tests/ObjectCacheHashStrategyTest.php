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

namespace WpPack\Component\Cache\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\ObjectCache;
use WpPack\Component\Cache\ObjectCacheConfig;
use WpPack\Component\Cache\Strategy\AllOptionsHashStrategy;
use WpPack\Component\Cache\Strategy\NotOptionsHashStrategy;
use WpPack\Component\Cache\Strategy\SiteNotOptionsHashStrategy;
use WpPack\Component\Cache\Strategy\SiteOptionsHashStrategy;
use WpPack\Component\Cache\Tests\Adapter\InMemoryAdapter;
use WpPack\Component\Cache\Tests\Adapter\InMemoryHashableAdapter;

final class ObjectCacheHashStrategyTest extends TestCase
{
    private ObjectCache $cache;
    private InMemoryHashableAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryHashableAdapter();
        $this->cache = new ObjectCache($this->adapter, new ObjectCacheConfig(
            prefix: 'wp:',
            hashStrategies: [
                new AllOptionsHashStrategy(),
                new NotOptionsHashStrategy(),
                new SiteOptionsHashStrategy(),
                new SiteNotOptionsHashStrategy(),
            ],
        ));
    }

    // --- supports ---

    #[Test]
    public function supportsHashAlloptionsWithHashableAdapter(): void
    {
        self::assertTrue($this->cache->supports('hash_alloptions'));
    }

    #[Test]
    public function doesNotSupportHashAlloptionsWithNonHashableAdapter(): void
    {
        $cache = new ObjectCache(new InMemoryAdapter(), new ObjectCacheConfig(
            prefix: 'wp:',
            hashStrategies: [new AllOptionsHashStrategy()],
        ));

        self::assertFalse($cache->supports('hash_alloptions'));
    }

    #[Test]
    public function doesNotSupportHashAlloptionsWithoutStrategies(): void
    {
        $cache = new ObjectCache($this->adapter, new ObjectCacheConfig(prefix: 'wp:'));

        self::assertFalse($cache->supports('hash_alloptions'));
    }

    // --- alloptions set/get ---

    #[Test]
    public function setAlloptionsStoresAsHash(): void
    {
        $options = ['siteurl' => 'https://example.com', 'blogname' => 'My Blog'];

        $this->cache->set('alloptions', $options, 'options');

        // Verify hash data in adapter
        $hashes = $this->adapter->getHashData();
        $fullKey = 'wp:0:options:alloptions';

        self::assertArrayHasKey($fullKey, $hashes);
        self::assertSame(serialize('https://example.com'), $hashes[$fullKey]['siteurl']);
        self::assertSame(serialize('My Blog'), $hashes[$fullKey]['blogname']);
    }

    #[Test]
    public function getAlloptionsFromHash(): void
    {
        $options = ['siteurl' => 'https://example.com', 'blogname' => 'My Blog'];

        $this->cache->set('alloptions', $options, 'options');
        $this->cache->flushRuntime();

        $found = false;
        $result = $this->cache->get('alloptions', 'options', false, $found);

        self::assertTrue($found);
        self::assertSame($options, $result);
    }

    #[Test]
    public function alloptionsDiffUpdateOnlyChangedFields(): void
    {
        $options = ['siteurl' => 'https://example.com', 'blogname' => 'My Blog', 'admin_email' => 'a@b.com'];
        $this->cache->set('alloptions', $options, 'options');

        // Update: modify one, remove one, add one
        $updated = ['siteurl' => 'https://new.example.com', 'blogname' => 'My Blog', 'new_option' => 'hello'];
        $this->cache->set('alloptions', $updated, 'options');

        $hashes = $this->adapter->getHashData();
        $fullKey = 'wp:0:options:alloptions';

        self::assertSame(serialize('https://new.example.com'), $hashes[$fullKey]['siteurl']);
        self::assertSame(serialize('My Blog'), $hashes[$fullKey]['blogname']);
        self::assertSame(serialize('hello'), $hashes[$fullKey]['new_option']);
        self::assertArrayNotHasKey('admin_email', $hashes[$fullKey]);
    }

    // --- alloptions delete ---

    #[Test]
    public function deleteAlloptionsRemovesHash(): void
    {
        $this->cache->set('alloptions', ['siteurl' => 'https://example.com'], 'options');
        $this->cache->delete('alloptions', 'options');

        $hashes = $this->adapter->getHashData();
        $fullKey = 'wp:0:options:alloptions';

        self::assertArrayNotHasKey($fullKey, $hashes);
    }

    // --- notoptions ---

    #[Test]
    public function notoptionsStoresAsHashWithFlags(): void
    {
        $notoptions = ['nonexistent_1' => true, 'nonexistent_2' => true];

        $this->cache->set('notoptions', $notoptions, 'options');

        $hashes = $this->adapter->getHashData();
        $fullKey = 'wp:0:options:notoptions';

        self::assertArrayHasKey($fullKey, $hashes);
        self::assertSame('1', $hashes[$fullKey]['nonexistent_1']);
        self::assertSame('1', $hashes[$fullKey]['nonexistent_2']);
    }

    #[Test]
    public function notoptionsRoundTrip(): void
    {
        $notoptions = ['nonexistent_1' => true, 'nonexistent_2' => true];

        $this->cache->set('notoptions', $notoptions, 'options');
        $this->cache->flushRuntime();

        $result = $this->cache->get('notoptions', 'options');
        self::assertSame($notoptions, $result);
    }

    // --- site-options ---

    #[Test]
    public function siteOptionsStoresAsHash(): void
    {
        $this->cache->addGlobalGroups(['site-options']);
        $options = ['site_name' => 'Network', 'admin_email' => 'admin@example.com'];

        $this->cache->set('1:all', $options, 'site-options');

        $hashes = $this->adapter->getHashData();
        $fullKey = 'wp:0:site-options:1:all';

        self::assertArrayHasKey($fullKey, $hashes);
        self::assertSame(serialize('Network'), $hashes[$fullKey]['site_name']);
    }

    #[Test]
    public function siteOptionsRoundTrip(): void
    {
        $this->cache->addGlobalGroups(['site-options']);
        $options = ['site_name' => 'Network', 'admin_email' => 'admin@example.com'];

        $this->cache->set('1:all', $options, 'site-options');
        $this->cache->flushRuntime();

        $result = $this->cache->get('1:all', 'site-options');
        self::assertSame($options, $result);
    }

    // --- site-notoptions ---

    #[Test]
    public function siteNotoptionsStoresAsHash(): void
    {
        $this->cache->addGlobalGroups(['site-options']);
        $notoptions = ['missing_1' => true, 'missing_2' => true];

        $this->cache->set('1:notoptions', $notoptions, 'site-options');

        $hashes = $this->adapter->getHashData();
        $fullKey = 'wp:0:site-options:1:notoptions';

        self::assertArrayHasKey($fullKey, $hashes);
        self::assertSame('1', $hashes[$fullKey]['missing_1']);
    }

    // --- non-hash keys are unaffected ---

    #[Test]
    public function nonHashKeysUseNormalPath(): void
    {
        $this->cache->set('regular_key', 'value', 'options');

        // Should not appear in hash data
        $hashes = $this->adapter->getHashData();
        self::assertArrayNotHasKey('wp:0:options:regular_key', $hashes);

        // Should still work normally
        $this->cache->flushRuntime();
        self::assertSame('value', $this->cache->get('regular_key', 'options'));
    }

    // --- flush clears hash state ---

    #[Test]
    public function flushClearsHashState(): void
    {
        $this->cache->set('alloptions', ['siteurl' => 'https://example.com'], 'options');
        $this->cache->flush();

        // After flush, setting alloptions again should do a full write (no diff)
        $this->cache->set('alloptions', ['blogname' => 'New Blog'], 'options');

        $hashes = $this->adapter->getHashData();
        $fullKey = 'wp:0:options:alloptions';

        self::assertArrayHasKey($fullKey, $hashes);
        self::assertCount(1, $hashes[$fullKey]);
        self::assertSame(serialize('New Blog'), $hashes[$fullKey]['blogname']);
    }

    // --- runtime cache hit avoids adapter ---

    #[Test]
    public function runtimeCacheHitSkipsAdapter(): void
    {
        $options = ['siteurl' => 'https://example.com'];
        $this->cache->set('alloptions', $options, 'options');

        // Second get should hit runtime, not adapter
        $found = false;
        $result = $this->cache->get('alloptions', 'options', false, $found);

        self::assertTrue($found);
        self::assertSame($options, $result);
    }

    // --- get miss ---

    #[Test]
    public function getMissOnHashKeyReturnsFalse(): void
    {
        $found = false;
        $result = $this->cache->get('alloptions', 'options', false, $found);

        self::assertFalse($result);
        self::assertFalse($found);
    }
}
