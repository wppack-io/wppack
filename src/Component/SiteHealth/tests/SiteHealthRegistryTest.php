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

namespace WPPack\Component\SiteHealth\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\SiteHealth\Attribute\AsDebugInfo;
use WPPack\Component\SiteHealth\Attribute\AsHealthCheck;
use WPPack\Component\SiteHealth\DebugSectionInterface;
use WPPack\Component\SiteHealth\HealthCheckInterface;
use WPPack\Component\SiteHealth\Exception\InvalidArgumentException;
use WPPack\Component\SiteHealth\Exception\LogicException;
use WPPack\Component\SiteHealth\Result;
use WPPack\Component\SiteHealth\SiteHealthRegistry;

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
    public function addHealthCheck(): void
    {
        $registry = new SiteHealthRegistry();

        $result = $registry->add(new DirectHealthCheckStub());

        self::assertInstanceOf(SiteHealthRegistry::class, $result);
    }

    #[Test]
    public function addDebugSection(): void
    {
        $registry = new SiteHealthRegistry();

        $result = $registry->add(new DebugSectionStub());

        self::assertInstanceOf(SiteHealthRegistry::class, $result);
    }

    #[Test]
    public function addHealthCheckWithoutAttributeThrowsException(): void
    {
        $registry = new SiteHealthRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is missing the #[AsHealthCheck] attribute');

        $registry->add(new NoAttributeHealthCheck());
    }

    #[Test]
    public function addDebugSectionWithoutAttributeThrowsException(): void
    {
        $registry = new SiteHealthRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is missing the #[AsDebugInfo] attribute');

        $registry->add(new NoAttributeDebugSection());
    }

    #[Test]
    public function addAfterRegisterThrowsLogicException(): void
    {
        $registry = new SiteHealthRegistry();
        $registry->register();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot add after register()');

        $registry->add(new DirectHealthCheckStub());
    }

    #[Test]
    public function registerIsIdempotent(): void
    {
        $registry = new SiteHealthRegistry();

        $registry->register();
        $registry->register();

        self::assertTrue(true);
    }

    #[Test]
    public function onSiteStatusTestsWithDirectTest(): void
    {
        $registry = new SiteHealthRegistry();
        $registry->add(new DirectHealthCheckStub());

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
        $registry->add(new AsyncHealthCheckStub());

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
        $registry->add(new DirectHealthCheckStub());

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
        $registry->add(new DebugSectionStub());

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
        $registry->add(new DetailedDebugSectionStub());

        $debugInfo = $registry->onDebugInformation([]);

        self::assertArrayHasKey('detailed-section', $debugInfo);
        self::assertSame('A detailed section', $debugInfo['detailed-section']['description']);
        self::assertTrue($debugInfo['detailed-section']['show_count']);
        self::assertTrue($debugInfo['detailed-section']['private']);
        self::assertCount(2, $debugInfo['detailed-section']['fields']);
    }

    #[Test]
    public function fluentAdd(): void
    {
        $registry = new SiteHealthRegistry();

        $result = $registry
            ->add(new DirectHealthCheckStub())
            ->add(new DebugSectionStub());

        self::assertInstanceOf(SiteHealthRegistry::class, $result);

        $tests = $registry->onSiteStatusTests([]);
        $debugInfo = $registry->onDebugInformation([]);

        self::assertArrayHasKey('test_direct', $tests['direct']);
        self::assertArrayHasKey('test-section', $debugInfo);
    }
}
