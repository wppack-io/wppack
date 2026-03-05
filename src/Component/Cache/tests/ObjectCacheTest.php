<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\ObjectCache;
use WpPack\Component\Cache\Tests\Adapter\InMemoryAdapter;

final class ObjectCacheTest extends TestCase
{
    private ObjectCache $cache;
    private InMemoryAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryAdapter();
        $this->cache = new ObjectCache($this->adapter, 'wp:');
    }

    // --- Basic get/set ---

    #[Test]
    public function getReturnsFalseForMissing(): void
    {
        $found = false;
        $result = $this->cache->get('nonexistent', 'default', false, $found);

        self::assertFalse($result);
        self::assertFalse($found);
    }

    #[Test]
    public function setAndGet(): void
    {
        $this->cache->set('key', 'value', 'default');

        $found = false;
        $result = $this->cache->get('key', 'default', false, $found);

        self::assertSame('value', $result);
        self::assertTrue($found);
    }

    #[Test]
    public function setStoresArray(): void
    {
        $data = ['a' => 1, 'b' => 2];
        $this->cache->set('key', $data);

        self::assertSame($data, $this->cache->get('key'));
    }

    #[Test]
    public function setStoresNull(): void
    {
        $this->cache->set('key', null);

        $found = false;
        $result = $this->cache->get('key', 'default', false, $found);

        // null is stored but get returns from runtime cache
        // Runtime cache uses isset() so null won't be found there
        // But the adapter will have it
        self::assertNull($result);
    }

    // --- Groups ---

    #[Test]
    public function differentGroupsAreSeparate(): void
    {
        $this->cache->set('key', 'value-a', 'group-a');
        $this->cache->set('key', 'value-b', 'group-b');

        self::assertSame('value-a', $this->cache->get('key', 'group-a'));
        self::assertSame('value-b', $this->cache->get('key', 'group-b'));
    }

    #[Test]
    public function emptyGroupDefaultsToDefault(): void
    {
        $this->cache->set('key', 'value', '');

        self::assertSame('value', $this->cache->get('key', 'default'));
        self::assertSame('value', $this->cache->get('key', ''));
    }

    // --- add ---

    #[Test]
    public function addSucceedsForNewKey(): void
    {
        self::assertTrue($this->cache->add('key', 'value'));
        self::assertSame('value', $this->cache->get('key'));
    }

    #[Test]
    public function addFailsForExistingKey(): void
    {
        $this->cache->set('key', 'existing');

        self::assertFalse($this->cache->add('key', 'new'));
        self::assertSame('existing', $this->cache->get('key'));
    }

    // --- replace ---

    #[Test]
    public function replaceSucceedsForExistingKey(): void
    {
        $this->cache->set('key', 'old');

        self::assertTrue($this->cache->replace('key', 'new'));
        self::assertSame('new', $this->cache->get('key'));
    }

    #[Test]
    public function replaceFailsForMissing(): void
    {
        self::assertFalse($this->cache->replace('key', 'value'));
    }

    // --- delete ---

    #[Test]
    public function deleteRemovesKey(): void
    {
        $this->cache->set('key', 'value');
        $this->cache->delete('key');

        self::assertFalse($this->cache->get('key'));
    }

    // --- increment/decrement ---

    #[Test]
    public function incrementIncreasesValue(): void
    {
        $this->cache->set('counter', 10);

        self::assertSame(11, $this->cache->increment('counter'));
    }

    #[Test]
    public function incrementByOffset(): void
    {
        $this->cache->set('counter', 10);

        self::assertSame(15, $this->cache->increment('counter', 5));
    }

    #[Test]
    public function incrementReturnsFalseForMissing(): void
    {
        self::assertFalse($this->cache->increment('nonexistent'));
    }

    #[Test]
    public function decrementDecreasesValue(): void
    {
        $this->cache->set('counter', 10);

        self::assertSame(9, $this->cache->decrement('counter'));
    }

    #[Test]
    public function decrementDoesNotGoBelowZero(): void
    {
        $this->cache->set('counter', 1);

        self::assertSame(0, $this->cache->decrement('counter', 5));
    }

    // --- flush ---

    #[Test]
    public function flushClearsAll(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2', 'other');

        self::assertTrue($this->cache->flush());
        self::assertFalse($this->cache->get('key1'));
        self::assertFalse($this->cache->get('key2', 'other'));
    }

    #[Test]
    public function flushGroupClearsOnlyGroup(): void
    {
        $this->cache->set('key', 'value-a', 'group-a');
        $this->cache->set('key', 'value-b', 'group-b');

        self::assertTrue($this->cache->flushGroup('group-a'));
        self::assertFalse($this->cache->get('key', 'group-a'));
        self::assertSame('value-b', $this->cache->get('key', 'group-b'));
    }

    #[Test]
    public function flushRuntimeClearsOnlyRuntime(): void
    {
        $this->cache->set('key', 'value');

        self::assertTrue($this->cache->flushRuntime());

        // Data should be fetched from adapter
        self::assertSame('value', $this->cache->get('key'));
    }

    // --- Multiple operations ---

    #[Test]
    public function getMultipleReturnsValues(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $results = $this->cache->getMultiple(['key1', 'key2', 'key3']);

        self::assertSame('value1', $results['key1']);
        self::assertSame('value2', $results['key2']);
        self::assertFalse($results['key3']);
    }

    #[Test]
    public function setMultipleStoresValues(): void
    {
        $results = $this->cache->setMultiple(['key1' => 'value1', 'key2' => 'value2']);

        self::assertTrue($results['key1']);
        self::assertTrue($results['key2']);
        self::assertSame('value1', $this->cache->get('key1'));
        self::assertSame('value2', $this->cache->get('key2'));
    }

    #[Test]
    public function addMultiple(): void
    {
        $this->cache->set('existing', 'old');

        $results = $this->cache->addMultiple([
            'new' => 'new-value',
            'existing' => 'would-overwrite',
        ]);

        self::assertTrue($results['new']);
        self::assertFalse($results['existing']);
        self::assertSame('new-value', $this->cache->get('new'));
        self::assertSame('old', $this->cache->get('existing'));
    }

    #[Test]
    public function deleteMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $results = $this->cache->deleteMultiple(['key1', 'key2']);

        self::assertTrue($results['key1']);
        self::assertTrue($results['key2']);
        self::assertFalse($this->cache->get('key1'));
        self::assertFalse($this->cache->get('key2'));
        self::assertSame('value3', $this->cache->get('key3'));
    }

    // --- Non-persistent groups ---

    #[Test]
    public function nonPersistentGroupDoesNotUseAdapter(): void
    {
        $this->cache->addNonPersistentGroups(['temp']);

        $this->cache->set('key', 'value', 'temp');
        self::assertSame('value', $this->cache->get('key', 'temp'));

        // Flush runtime — value should be gone (no adapter fallback)
        $this->cache->flushRuntime();
        self::assertFalse($this->cache->get('key', 'temp'));
    }

    // --- Global groups ---

    #[Test]
    public function globalGroupsShareAcrossBlogs(): void
    {
        $this->cache->addGlobalGroups(['global']);

        $this->cache->switchToBlog(1);
        $this->cache->set('key', 'global-value', 'global');

        $this->cache->switchToBlog(2);
        self::assertSame('global-value', $this->cache->get('key', 'global'));
    }

    #[Test]
    public function nonGlobalGroupsAreBlogSpecific(): void
    {
        $this->cache->switchToBlog(1);
        $this->cache->set('key', 'blog1-value', 'local');

        $this->cache->switchToBlog(2);
        self::assertFalse($this->cache->get('key', 'local'));
    }

    // --- Force fetch ---

    #[Test]
    public function forceBypassesRuntimeCache(): void
    {
        $this->cache->set('key', 'original');

        // Modify adapter directly to simulate external change
        // Since ObjectCache serializes, we need to set the serialized value
        $fullKey = 'wp:0:default:key';
        $this->adapter->set($fullKey, serialize('modified'));

        // Without force, returns runtime cache
        self::assertSame('original', $this->cache->get('key'));

        // With force, fetches from adapter
        self::assertSame('modified', $this->cache->get('key', 'default', true));
    }

    // --- Metrics ---

    #[Test]
    public function metricsTrackHitsAndMisses(): void
    {
        $this->cache->set('key', 'value');

        $this->cache->get('key');       // hit (runtime)
        $this->cache->get('missing');   // miss
        $this->cache->get('key');       // hit (runtime)

        $metrics = $this->cache->getMetrics();

        self::assertSame(2, $metrics->hits);
        self::assertSame(1, $metrics->misses);
        self::assertSame('in-memory', $metrics->adapterName);
    }

    // --- supports ---

    #[Test]
    public function supportsReturnsExpected(): void
    {
        self::assertTrue($this->cache->supports('flush_group'));
        self::assertTrue($this->cache->supports('flush_runtime'));
        self::assertTrue($this->cache->supports('get_multiple'));
        self::assertTrue($this->cache->supports('set_multiple'));
        self::assertTrue($this->cache->supports('add_multiple'));
        self::assertTrue($this->cache->supports('delete_multiple'));
        self::assertFalse($this->cache->supports('unknown_feature'));
    }

    // --- Null adapter (runtime only) ---

    #[Test]
    public function worksWithoutAdapter(): void
    {
        $cache = new ObjectCache(null);

        $cache->set('key', 'value');
        self::assertSame('value', $cache->get('key'));

        $cache->delete('key');
        self::assertFalse($cache->get('key'));
    }

    #[Test]
    public function nullAdapterMetrics(): void
    {
        $cache = new ObjectCache(null);
        $metrics = $cache->getMetrics();

        self::assertNull($metrics->adapterName);
    }

    // --- close ---

    #[Test]
    public function closeCallsAdapterClose(): void
    {
        // Just verify it doesn't throw
        $this->cache->close();
        self::assertTrue(true);
    }

    // --- increment from adapter ---

    #[Test]
    public function incrementFetchesFromAdapterWhenNotInRuntime(): void
    {
        // Store value directly in adapter (bypassing runtime cache)
        $fullKey = 'wp:0:default:counter';
        $this->adapter->set($fullKey, serialize(10));

        // increment should fetch from adapter since runtime is empty
        $result = $this->cache->increment('counter');

        self::assertSame(11, $result);
    }

    #[Test]
    public function incrementReturnsFalseForNonNumericValue(): void
    {
        $this->cache->set('key', 'not-a-number');

        self::assertFalse($this->cache->increment('key'));
    }

    #[Test]
    public function decrementByOffset(): void
    {
        $this->cache->set('counter', 20);

        self::assertSame(17, $this->cache->decrement('counter', 3));
    }

    #[Test]
    public function decrementClampsToZero(): void
    {
        $this->cache->set('counter', 2);

        self::assertSame(0, $this->cache->decrement('counter', 10));
    }

    // --- getMultiple with force ---

    #[Test]
    public function getMultipleWithForceBypassesRuntime(): void
    {
        $this->cache->set('key1', 'original1');
        $this->cache->set('key2', 'original2');

        // Modify adapter directly
        $this->adapter->set('wp:0:default:key1', serialize('modified1'));

        // Without force, returns runtime
        $results = $this->cache->getMultiple(['key1', 'key2']);
        self::assertSame('original1', $results['key1']);

        // With force, re-fetches from adapter
        $results = $this->cache->getMultiple(['key1', 'key2'], 'default', true);
        self::assertSame('modified1', $results['key1']);
        self::assertSame('original2', $results['key2']);
    }

    // --- setMultiple on non-persistent group ---

    #[Test]
    public function setMultipleOnNonPersistentGroup(): void
    {
        $this->cache->addNonPersistentGroups(['temp']);

        $results = $this->cache->setMultiple(['k1' => 'v1', 'k2' => 'v2'], 'temp');

        self::assertTrue($results['k1']);
        self::assertTrue($results['k2']);
        self::assertSame('v1', $this->cache->get('k1', 'temp'));
        self::assertSame('v2', $this->cache->get('k2', 'temp'));

        // Flush runtime — values should be gone (no adapter fallback)
        $this->cache->flushRuntime();
        self::assertFalse($this->cache->get('k1', 'temp'));
    }

    // --- deleteMultiple on non-persistent group ---

    #[Test]
    public function deleteMultipleOnNonPersistentGroup(): void
    {
        $this->cache->addNonPersistentGroups(['temp']);

        $this->cache->set('k1', 'v1', 'temp');
        $this->cache->set('k2', 'v2', 'temp');

        $results = $this->cache->deleteMultiple(['k1', 'k2'], 'temp');

        self::assertTrue($results['k1']);
        self::assertTrue($results['k2']);
        self::assertFalse($this->cache->get('k1', 'temp'));
        self::assertFalse($this->cache->get('k2', 'temp'));
    }

    // --- flushGroup on non-persistent group ---

    #[Test]
    public function flushGroupOnNonPersistentGroup(): void
    {
        $this->cache->addNonPersistentGroups(['temp']);

        $this->cache->set('k1', 'v1', 'temp');
        $this->cache->set('k2', 'v2', 'temp');

        self::assertTrue($this->cache->flushGroup('temp'));
        self::assertFalse($this->cache->get('k1', 'temp'));
        self::assertFalse($this->cache->get('k2', 'temp'));
    }

    // --- Null adapter edge cases ---

    #[Test]
    public function flushWithNullAdapter(): void
    {
        $cache = new ObjectCache(null);
        $cache->set('key', 'value');

        self::assertTrue($cache->flush());
        self::assertFalse($cache->get('key'));
    }

    #[Test]
    public function closeWithNullAdapter(): void
    {
        $cache = new ObjectCache(null);

        // close on null adapter should not throw
        $cache->close();
        self::assertTrue(true);
    }

    // --- switchToBlog ---

    #[Test]
    public function switchToBlogChangesKeyPrefix(): void
    {
        $this->cache->switchToBlog(1);
        $this->cache->set('key', 'blog1-value');

        $this->cache->switchToBlog(3);
        $this->cache->set('key', 'blog3-value');

        // Verify blog 3 value
        self::assertSame('blog3-value', $this->cache->get('key'));

        // Switch back to blog 1 — runtime cache is separate
        $this->cache->switchToBlog(1);
        self::assertSame('blog1-value', $this->cache->get('key'));
    }

    // --- add with adapter failure ---

    #[Test]
    public function addReturnsFalseWhenAdapterReturnsFalse(): void
    {
        // First add succeeds — key is now in adapter
        self::assertTrue($this->cache->add('key', 'first'));

        // Clear runtime so the second add won't be caught by runtime check
        $this->cache->flushRuntime();

        // Second add should fail because key exists in adapter
        self::assertFalse($this->cache->add('key', 'second'));

        // Value should not be in runtime after failed add
        // Force fetch from adapter to verify original value persists
        $found = false;
        $result = $this->cache->get('key', 'default', true, $found);
        self::assertTrue($found);
        self::assertSame('first', $result);
    }

    // --- replace checks adapter ---

    #[Test]
    public function replaceChecksAdapterWhenNotInRuntime(): void
    {
        // Store value directly in adapter (bypassing runtime cache)
        $fullKey = 'wp:0:default:key';
        $this->adapter->set($fullKey, serialize('adapter-value'));

        // replace should find the key in adapter and succeed
        self::assertTrue($this->cache->replace('key', 'new-value'));
        self::assertSame('new-value', $this->cache->get('key'));
    }
}
