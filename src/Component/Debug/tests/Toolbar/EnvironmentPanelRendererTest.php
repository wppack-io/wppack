<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\Toolbar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\Panel\EnvironmentPanelRenderer;

final class EnvironmentPanelRendererTest extends TestCase
{
    private EnvironmentPanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new EnvironmentPanelRenderer();
    }

    private function createProfile(array $data): Profile
    {
        $profile = new Profile();
        $collector = new class ($data) implements DataCollectorInterface {
            public function __construct(private readonly array $data) {}
            public function getName(): string
            {
                return 'environment';
            }
            public function collect(): void {}
            public function getData(): array
            {
                return $this->data;
            }
            public function getLabel(): string
            {
                return 'Environment';
            }
            public function getBadgeValue(): string
            {
                return '';
            }
            public function getBadgeColor(): string
            {
                return 'default';
            }
            public function reset(): void {}
        };
        $profile->addCollector($collector);

        return $profile;
    }

    #[Test]
    public function renderWithBasicData(): void
    {
        $html = $this->renderer->render($this->createProfile([
            'php' => [
                'version' => '8.3.1',
                'zend_version' => '4.3.1',
                'zts' => false,
                'debug' => false,
                'gc_enabled' => true,
            ],
            'sapi' => 'fpm-fcgi',
            'os' => 'Linux',
            'architecture' => 64,
            'extensions' => [],
            'ini' => [],
            'opcache' => [],
        ]));

        self::assertStringContainsString('PHP', $html);
        self::assertStringContainsString('8.3.1', $html);
        self::assertStringContainsString('fpm-fcgi', $html);
        self::assertStringContainsString('4.3.1', $html);
        self::assertStringContainsString('64-bit', $html);
        self::assertStringContainsString('Linux', $html);
        self::assertStringContainsString('wpd-text-green">true', $html);
        self::assertStringContainsString('wpd-text-red">false', $html);
    }

    #[Test]
    public function renderWithOpcacheEnabled(): void
    {
        $html = $this->renderer->render($this->createProfile([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => [],
            'ini' => [],
            'opcache' => [
                'enabled' => true,
                'jit' => true,
                'cached_scripts' => 250,
                'hit_rate' => 97.5,
                'used_memory' => 10485760,  // 10 MB
                'free_memory' => 125829120, // 120 MB
            ],
        ]));

        self::assertStringContainsString('OPcache', $html);
        self::assertStringContainsString('wpd-text-green">true', $html);
        self::assertStringContainsString('250', $html);
        self::assertStringContainsString('97.5%', $html);
        self::assertStringContainsString('10.0 MB used', $html);
        self::assertStringContainsString('120.0 MB free', $html);
    }

    #[Test]
    public function renderWithOpcacheDisabled(): void
    {
        $html = $this->renderer->render($this->createProfile([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => [],
            'ini' => [],
            'opcache' => [
                'enabled' => false,
            ],
        ]));

        self::assertStringContainsString('OPcache', $html);
        self::assertStringContainsString('wpd-text-red">false', $html);
        // JIT, Hit Rate, Memory should not appear when disabled
        self::assertStringNotContainsString('Hit Rate', $html);
        self::assertStringNotContainsString('Cached Scripts', $html);
        self::assertStringNotContainsString('JIT', $html);
    }

    #[Test]
    public function renderWithHighHitRate(): void
    {
        $html = $this->renderer->render($this->createProfile([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => [],
            'ini' => [],
            'opcache' => [
                'enabled' => true,
                'hit_rate' => 98.7,
                'used_memory' => 0,
                'free_memory' => 0,
            ],
        ]));

        self::assertStringContainsString('wpd-text-green', $html);
        self::assertStringContainsString('98.7%', $html);
    }

    #[Test]
    public function renderWithMediumHitRate(): void
    {
        $html = $this->renderer->render($this->createProfile([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => [],
            'ini' => [],
            'opcache' => [
                'enabled' => true,
                'hit_rate' => 87.3,
                'used_memory' => 0,
                'free_memory' => 0,
            ],
        ]));

        self::assertStringContainsString('wpd-text-yellow', $html);
        self::assertStringContainsString('87.3%', $html);
    }

    #[Test]
    public function renderWithLowHitRate(): void
    {
        $html = $this->renderer->render($this->createProfile([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => [],
            'ini' => [],
            'opcache' => [
                'enabled' => true,
                'hit_rate' => 55.2,
                'used_memory' => 0,
                'free_memory' => 0,
            ],
        ]));

        self::assertStringContainsString('wpd-text-red', $html);
        self::assertStringContainsString('55.2%', $html);
    }

    #[Test]
    public function renderWithWastedPercentage(): void
    {
        $html = $this->renderer->render($this->createProfile([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => [],
            'ini' => [],
            'opcache' => [
                'enabled' => true,
                'hit_rate' => 95.0,
                'used_memory' => 0,
                'free_memory' => 0,
                'wasted_percentage' => 3.5,
            ],
        ]));

        self::assertStringContainsString('Wasted', $html);
        self::assertStringContainsString('3.5%', $html);
        self::assertStringContainsString('wpd-text-yellow', $html);
    }

    #[Test]
    public function renderWithOomRestarts(): void
    {
        $html = $this->renderer->render($this->createProfile([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => [],
            'ini' => [],
            'opcache' => [
                'enabled' => true,
                'hit_rate' => 90.0,
                'used_memory' => 0,
                'free_memory' => 0,
                'oom_restarts' => 3,
            ],
        ]));

        self::assertStringContainsString('OOM Restarts', $html);
        self::assertStringContainsString('wpd-text-red">3', $html);
    }

    #[Test]
    public function renderWithDisableFunctions(): void
    {
        $html = $this->renderer->render($this->createProfile([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => [],
            'ini' => [
                'disable_functions' => 'exec,passthru,shell_exec',
                'memory_limit' => '256M',
            ],
            'opcache' => [],
        ]));

        self::assertStringContainsString('Configuration', $html);
        self::assertStringContainsString('3 functions disabled', $html);
        self::assertStringContainsString('256M', $html);
    }

    #[Test]
    public function renderWithExtensions(): void
    {
        $html = $this->renderer->render($this->createProfile([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => ['mbstring', 'curl', 'openssl', 'pdo_mysql'],
            'ini' => [],
            'opcache' => [],
        ]));

        self::assertStringContainsString('Extensions (4)', $html);
        self::assertStringContainsString('wpd-tag-list', $html);
        self::assertStringContainsString('<span class="wpd-tag">mbstring</span>', $html);
        self::assertStringContainsString('<span class="wpd-tag">curl</span>', $html);
        self::assertStringContainsString('<span class="wpd-tag">openssl</span>', $html);
        self::assertStringContainsString('<span class="wpd-tag">pdo_mysql</span>', $html);
    }

    #[Test]
    public function renderWithEmptyExtensions(): void
    {
        $html = $this->renderer->render($this->createProfile([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => [],
            'ini' => [],
            'opcache' => [],
        ]));

        self::assertStringNotContainsString('Extensions', $html);
        self::assertStringNotContainsString('wpd-tag-list', $html);
    }

    #[Test]
    public function renderWithHostname(): void
    {
        $html = $this->renderer->render($this->createProfile([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'hostname' => 'web-server-01',
            'extensions' => [],
            'ini' => [],
            'opcache' => [],
        ]));

        self::assertStringContainsString('Hostname', $html);
        self::assertStringContainsString('web-server-01', $html);
    }

    #[Test]
    public function renderWithEmptyHostname(): void
    {
        $html = $this->renderer->render($this->createProfile([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'hostname' => '',
            'extensions' => [],
            'ini' => [],
            'opcache' => [],
        ]));

        self::assertStringNotContainsString('Hostname', $html);
    }

    #[Test]
    public function getNameReturnsEnvironment(): void
    {
        self::assertSame('environment', $this->renderer->getName());
    }
}
