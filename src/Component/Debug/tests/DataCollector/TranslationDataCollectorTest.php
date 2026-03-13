<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\TranslationDataCollector;

final class TranslationDataCollectorTest extends TestCase
{
    private TranslationDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new TranslationDataCollector();
    }

    #[Test]
    public function getNameReturnsTranslation(): void
    {
        self::assertSame('translation', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsTranslation(): void
    {
        self::assertSame('Translation', $this->collector->getLabel());
    }

    #[Test]
    public function captureGettextTracksLookupsAndDomainUsage(): void
    {
        $this->collector->captureGettext('Hello', 'Hello', 'default');
        $this->collector->captureGettext('Bonjour', 'Hello', 'my-plugin');
        $this->collector->captureGettext('Au revoir', 'Goodbye', 'my-plugin');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(3, $data['total_lookups']);
        self::assertSame(1, $data['domain_usage']['default']);
        self::assertSame(2, $data['domain_usage']['my-plugin']);
    }

    #[Test]
    public function captureGettextDetectsMissingTranslations(): void
    {
        // translated === text means untranslated
        $this->collector->captureGettext('Hello', 'Hello', 'my-plugin');
        $this->collector->captureGettext('Bonjour', 'Hello', 'my-plugin');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['missing_count']);
        self::assertSame('Hello', $data['missing_translations'][0]['original']);
        self::assertSame('my-plugin', $data['missing_translations'][0]['domain']);
    }

    #[Test]
    public function captureGettextIgnoresDefaultDomainForMissingDetection(): void
    {
        // Default domain should not be counted as missing even if translated === text
        $this->collector->captureGettext('Hello', 'Hello', 'default');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(0, $data['missing_count']);
        self::assertSame([], $data['missing_translations']);
    }

    #[Test]
    public function captureGettextWithContextTracksLookups(): void
    {
        $this->collector->captureGettextWithContext('Translated', 'Original', 'some-context', 'my-plugin');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_lookups']);
        self::assertSame(1, $data['domain_usage']['my-plugin']);
    }

    #[Test]
    public function captureGettextWithContextDetectsMissing(): void
    {
        $this->collector->captureGettextWithContext('Original', 'Original', 'some-context', 'my-plugin');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['missing_count']);
    }

    #[Test]
    public function captureNgettextTracksLookups(): void
    {
        $this->collector->captureNgettext('1 item', '1 item', '%d items', 1, 'my-plugin');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_lookups']);
        self::assertSame(1, $data['domain_usage']['my-plugin']);
    }

    #[Test]
    public function captureNgettextDetectsMissing(): void
    {
        // translated === single means untranslated
        $this->collector->captureNgettext('1 item', '1 item', '%d items', 1, 'my-plugin');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['missing_count']);
    }

    #[Test]
    public function captureTextdomainLoadedAddsDomain(): void
    {
        $this->collector->captureTextdomainLoaded('my-plugin', '/path/to/my-plugin.mo');
        $this->collector->captureTextdomainLoaded('another-plugin', '/path/to/another.mo');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertContains('my-plugin', $data['loaded_domains']);
        self::assertContains('another-plugin', $data['loaded_domains']);
    }

    #[Test]
    public function captureTextdomainUnloadedRemovesDomain(): void
    {
        $this->collector->captureTextdomainLoaded('my-plugin', '/path/to/my-plugin.mo');
        $this->collector->captureTextdomainLoaded('another-plugin', '/path/to/another.mo');
        $this->collector->captureTextdomainUnloaded('my-plugin');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertNotContains('my-plugin', $data['loaded_domains']);
        self::assertContains('another-plugin', $data['loaded_domains']);
    }

    #[Test]
    public function captureTextdomainUnloadedIgnoresUnknownDomain(): void
    {
        $this->collector->captureTextdomainUnloaded('nonexistent');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], array_values($data['loaded_domains']));
    }

    #[Test]
    public function getIndicatorValueReturnsMissingCount(): void
    {
        $this->collector->captureGettext('Hello', 'Hello', 'my-plugin');
        $this->collector->captureGettext('World', 'World', 'my-plugin');

        $this->collector->collect();

        self::assertSame('2', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyStringWhenNoMissing(): void
    {
        $this->collector->captureGettext('Bonjour', 'Hello', 'my-plugin');

        $this->collector->collect();

        self::assertSame('', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyStringBeforeCollect(): void
    {
        self::assertSame('', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsGreenWhenNoMissing(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['missing_count' => 0]);

        self::assertSame('green', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsYellowWhenSomeMissing(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['missing_count' => 5]);

        self::assertSame('yellow', $this->collector->getIndicatorColor());

        $reflection->setValue($this->collector, ['missing_count' => 20]);
        self::assertSame('yellow', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsRedWhenManyMissing(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['missing_count' => 21]);

        self::assertSame('red', $this->collector->getIndicatorColor());

        $reflection->setValue($this->collector, ['missing_count' => 100]);
        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function missingTranslationsAreDeduplicated(): void
    {
        // Same text and domain should appear only once
        $this->collector->captureGettext('Hello', 'Hello', 'my-plugin');
        $this->collector->captureGettext('Hello', 'Hello', 'my-plugin');
        $this->collector->captureGettext('Hello', 'Hello', 'my-plugin');

        // Different text in same domain should be separate
        $this->collector->captureGettext('World', 'World', 'my-plugin');

        // Same text in different domain should be separate
        $this->collector->captureGettext('Hello', 'Hello', 'other-plugin');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(3, $data['missing_count']);
        self::assertCount(3, $data['missing_translations']);

        $keys = array_map(
            static fn(array $entry): string => $entry['domain'] . '::' . $entry['original'],
            $data['missing_translations'],
        );
        self::assertContains('my-plugin::Hello', $keys);
        self::assertContains('my-plugin::World', $keys);
        self::assertContains('other-plugin::Hello', $keys);
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->captureGettext('Hello', 'Hello', 'my-plugin');
        $this->collector->captureTextdomainLoaded('my-plugin', '/path/to/my-plugin.mo');

        $this->collector->collect();
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());

        // After reset, collecting again should return empty state
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(0, $data['total_lookups']);
        self::assertSame([], array_values($data['loaded_domains']));
        self::assertSame([], $data['domain_usage']);
        self::assertSame([], $data['missing_translations']);
        self::assertSame(0, $data['missing_count']);
    }
}
