<?php

declare(strict_types=1);

namespace WpPack\Component\SiteHealth\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\SiteHealth\Attribute\AsDebugInfo;
use WpPack\Component\SiteHealth\Attribute\AsHealthCheck;
use WpPack\Component\SiteHealth\DebugSectionInterface;
use WpPack\Component\SiteHealth\HealthCheckInterface;
use WpPack\Component\SiteHealth\Exception\InvalidArgumentException;
use WpPack\Component\SiteHealth\Exception\LogicException;
use WpPack\Component\SiteHealth\Result;
use WpPack\Component\SiteHealth\SiteHealthRegistry;

#[AsHealthCheck(id: 'test_direct', label: 'Direct Test', category: 'performance')]
final class DirectHealthCheckStub implements HealthCheckInterface
{
    public function run(): Result
    {
        return Result::good('All good', 'Everything is fine.');
    }
}

#[AsHealthCheck(id: 'test_async', label: 'Async Test', category: 'security', async: true)]
final class AsyncHealthCheckStub implements HealthCheckInterface
{
    public function run(): Result
    {
        return Result::recommended('Needs update', 'Consider updating.');
    }
}

#[AsDebugInfo(section: 'test-section', label: 'Test Section')]
final class DebugSectionStub implements DebugSectionInterface
{
    public function getFields(): array
    {
        return [
            'version' => [
                'label' => 'Version',
                'value' => '1.0.0',
            ],
        ];
    }
}

#[AsDebugInfo(section: 'detailed-section', label: 'Detailed', description: 'A detailed section', showCount: true, private: true)]
final class DetailedDebugSectionStub implements DebugSectionInterface
{
    public function getFields(): array
    {
        return [
            'field1' => [
                'label' => 'Field 1',
                'value' => 'value1',
            ],
            'field2' => [
                'label' => 'Field 2',
                'value' => 'value2',
            ],
        ];
    }
}

final class NoAttributeHealthCheck implements HealthCheckInterface
{
    public function run(): Result
    {
        return Result::good('Test', 'Test');
    }
}

final class NoAttributeDebugSection implements DebugSectionInterface
{
    public function getFields(): array
    {
        return [];
    }
}

final class SiteHealthRegistryTest extends TestCase
{
    #[Test]
    public function registerHealthCheck(): void
    {
        $registry = new SiteHealthRegistry();

        $result = $registry->register(new DirectHealthCheckStub());

        self::assertInstanceOf(SiteHealthRegistry::class, $result);
    }

    #[Test]
    public function registerDebugSection(): void
    {
        $registry = new SiteHealthRegistry();

        $result = $registry->register(new DebugSectionStub());

        self::assertInstanceOf(SiteHealthRegistry::class, $result);
    }

    #[Test]
    public function registerHealthCheckWithoutAttributeThrowsException(): void
    {
        $registry = new SiteHealthRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is missing the #[AsHealthCheck] attribute');

        $registry->register(new NoAttributeHealthCheck());
    }

    #[Test]
    public function registerDebugSectionWithoutAttributeThrowsException(): void
    {
        $registry = new SiteHealthRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is missing the #[AsDebugInfo] attribute');

        $registry->register(new NoAttributeDebugSection());
    }

    #[Test]
    public function registerAfterBindThrowsLogicException(): void
    {
        if (!function_exists('add_filter')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $registry = new SiteHealthRegistry();
        $registry->bind();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot register after bind()');

        $registry->register(new DirectHealthCheckStub());
    }

    #[Test]
    public function bindIsIdempotent(): void
    {
        if (!function_exists('add_filter')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $registry = new SiteHealthRegistry();

        $registry->bind();
        $registry->bind();

        self::assertTrue(true);
    }

    #[Test]
    public function onSiteStatusTestsWithDirectTest(): void
    {
        $registry = new SiteHealthRegistry();
        $registry->register(new DirectHealthCheckStub());

        $tests = $registry->onSiteStatusTests([]);

        self::assertArrayHasKey('direct', $tests);
        self::assertArrayHasKey('test_direct', $tests['direct']);
        self::assertSame('Direct Test', $tests['direct']['test_direct']['label']);
        self::assertIsCallable($tests['direct']['test_direct']['test']);

        $result = ($tests['direct']['test_direct']['test'])();

        self::assertSame('good', $result['status']);
        self::assertSame('All good', $result['label']);
        self::assertSame('test_direct', $result['test']);
        self::assertSame('performance', $result['badge']['label']);
        self::assertSame('green', $result['badge']['color']);
    }

    #[Test]
    public function onSiteStatusTestsWithAsyncTest(): void
    {
        $registry = new SiteHealthRegistry();
        $registry->register(new AsyncHealthCheckStub());

        $tests = $registry->onSiteStatusTests([]);

        self::assertArrayHasKey('async', $tests);
        self::assertArrayHasKey('test_async', $tests['async']);
        self::assertSame('Async Test', $tests['async']['test_async']['label']);
        self::assertSame('test_async', $tests['async']['test_async']['test']);
        self::assertArrayHasKey('async_direct_test', $tests['async']['test_async']);
        self::assertIsCallable($tests['async']['test_async']['async_direct_test']);

        $result = ($tests['async']['test_async']['async_direct_test'])();

        self::assertSame('recommended', $result['status']);
        self::assertSame('Needs update', $result['label']);
    }

    #[Test]
    public function onSiteStatusTestsPreservesExistingTests(): void
    {
        $registry = new SiteHealthRegistry();
        $registry->register(new DirectHealthCheckStub());

        $existing = [
            'direct' => [
                'existing_test' => ['label' => 'Existing', 'test' => 'existing_func'],
            ],
        ];

        $tests = $registry->onSiteStatusTests($existing);

        self::assertArrayHasKey('existing_test', $tests['direct']);
        self::assertArrayHasKey('test_direct', $tests['direct']);
    }

    #[Test]
    public function onDebugInformationBasicSection(): void
    {
        $registry = new SiteHealthRegistry();
        $registry->register(new DebugSectionStub());

        $debugInfo = $registry->onDebugInformation([]);

        self::assertArrayHasKey('test-section', $debugInfo);
        self::assertSame('Test Section', $debugInfo['test-section']['label']);
        self::assertFalse($debugInfo['test-section']['private']);
        self::assertArrayNotHasKey('description', $debugInfo['test-section']);
        self::assertArrayNotHasKey('show_count', $debugInfo['test-section']);

        $fields = $debugInfo['test-section']['fields'];
        self::assertArrayHasKey('version', $fields);
        self::assertSame('Version', $fields['version']['label']);
        self::assertSame('1.0.0', $fields['version']['value']);
    }

    #[Test]
    public function onDebugInformationWithDescriptionAndShowCount(): void
    {
        $registry = new SiteHealthRegistry();
        $registry->register(new DetailedDebugSectionStub());

        $debugInfo = $registry->onDebugInformation([]);

        self::assertArrayHasKey('detailed-section', $debugInfo);
        self::assertSame('A detailed section', $debugInfo['detailed-section']['description']);
        self::assertTrue($debugInfo['detailed-section']['show_count']);
        self::assertTrue($debugInfo['detailed-section']['private']);
        self::assertCount(2, $debugInfo['detailed-section']['fields']);
    }

    #[Test]
    public function fluentRegistration(): void
    {
        $registry = new SiteHealthRegistry();

        $result = $registry
            ->register(new DirectHealthCheckStub())
            ->register(new DebugSectionStub());

        self::assertInstanceOf(SiteHealthRegistry::class, $result);

        $tests = $registry->onSiteStatusTests([]);
        $debugInfo = $registry->onDebugInformation([]);

        self::assertArrayHasKey('test_direct', $tests['direct']);
        self::assertArrayHasKey('test-section', $debugInfo);
    }
}
