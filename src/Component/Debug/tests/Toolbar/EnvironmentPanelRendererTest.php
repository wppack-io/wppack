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

namespace WpPack\Component\Debug\Tests\Toolbar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\Panel\EnvironmentPanelRenderer;

final class EnvironmentPanelRendererTest extends TestCase
{
    private Profile $profile;
    private EnvironmentPanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->profile = new Profile();
        $this->renderer = new EnvironmentPanelRenderer($this->profile);
    }

    private function setEnvironmentData(array $data): void
    {
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
            public function getIndicatorValue(): string
            {
                return '';
            }
            public function getIndicatorColor(): string
            {
                return 'default';
            }
            public function reset(): void {}
        };
        $this->profile->addCollector($collector);
    }

    #[Test]
    public function renderWithBasicData(): void
    {
        $this->setEnvironmentData([
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
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('PHP Runtime', $html);
        self::assertStringContainsString('8.3.1', $html);
        self::assertStringContainsString('fpm-fcgi', $html);
        self::assertStringContainsString('4.3.1', $html);
        self::assertStringContainsString('64-bit', $html);
        self::assertStringContainsString('Server', $html);
        self::assertStringContainsString('Linux', $html);
        self::assertStringContainsString('wpd-text-green">true', $html);
        self::assertStringContainsString('wpd-text-red">false', $html);
    }

    #[Test]
    public function renderWithOpcacheEnabled(): void
    {
        $this->setEnvironmentData([
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
        ]);
        $html = $this->renderer->renderPanel();

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
        $this->setEnvironmentData([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => [],
            'ini' => [],
            'opcache' => [
                'enabled' => false,
            ],
        ]);
        $html = $this->renderer->renderPanel();

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
        $this->setEnvironmentData([
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
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('wpd-text-green', $html);
        self::assertStringContainsString('98.7%', $html);
    }

    #[Test]
    public function renderWithMediumHitRate(): void
    {
        $this->setEnvironmentData([
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
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('wpd-text-yellow', $html);
        self::assertStringContainsString('87.3%', $html);
    }

    #[Test]
    public function renderWithLowHitRate(): void
    {
        $this->setEnvironmentData([
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
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('wpd-text-red', $html);
        self::assertStringContainsString('55.2%', $html);
    }

    #[Test]
    public function renderWithWastedPercentage(): void
    {
        $this->setEnvironmentData([
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
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('Wasted', $html);
        self::assertStringContainsString('3.5%', $html);
        self::assertStringContainsString('wpd-text-yellow', $html);
    }

    #[Test]
    public function renderWithOomRestarts(): void
    {
        $this->setEnvironmentData([
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
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('OOM Restarts', $html);
        self::assertStringContainsString('wpd-text-red">3', $html);
    }

    #[Test]
    public function renderWithDisableFunctions(): void
    {
        $this->setEnvironmentData([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => [],
            'ini' => [
                'disable_functions' => 'exec,passthru,shell_exec',
                'memory_limit' => '256M',
            ],
            'opcache' => [],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('PHP Configuration', $html);
        self::assertStringContainsString('3 functions disabled', $html);
        self::assertStringContainsString('256M', $html);
    }

    #[Test]
    public function renderWithExtensions(): void
    {
        $this->setEnvironmentData([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => ['mbstring', 'curl', 'openssl', 'pdo_mysql'],
            'ini' => [],
            'opcache' => [],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('PHP Extensions (4)', $html);
        self::assertStringContainsString('wpd-tag-list', $html);
        self::assertStringContainsString('<span class="wpd-tag">mbstring</span>', $html);
        self::assertStringContainsString('<span class="wpd-tag">curl</span>', $html);
        self::assertStringContainsString('<span class="wpd-tag">openssl</span>', $html);
        self::assertStringContainsString('<span class="wpd-tag">pdo_mysql</span>', $html);
    }

    #[Test]
    public function renderWithEmptyExtensions(): void
    {
        $this->setEnvironmentData([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => [],
            'ini' => [],
            'opcache' => [],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringNotContainsString('Extensions', $html);
        self::assertStringNotContainsString('wpd-tag-list', $html);
    }

    #[Test]
    public function renderWithHostname(): void
    {
        $this->setEnvironmentData([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'hostname' => 'web-server-01',
            'extensions' => [],
            'ini' => [],
            'opcache' => [],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('Hostname', $html);
        self::assertStringContainsString('web-server-01', $html);
    }

    #[Test]
    public function renderWithEmptyHostname(): void
    {
        $this->setEnvironmentData([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'hostname' => '',
            'extensions' => [],
            'ini' => [],
            'opcache' => [],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringNotContainsString('Hostname', $html);
    }

    #[Test]
    public function renderWithServerInfo(): void
    {
        $this->setEnvironmentData([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'fpm-fcgi',
            'os' => 'Linux',
            'hostname' => 'web-server-01',
            'extensions' => [],
            'ini' => [],
            'opcache' => [],
            'server' => [
                'software' => 'Apache/2.4.52 (Ubuntu)',
                'web_server' => ['name' => 'Apache', 'version' => '2.4.52', 'raw' => 'Apache/2.4.52 (Ubuntu)'],
                'name' => 'example.com',
                'addr' => '10.0.0.1',
                'port' => '443',
                'protocol' => 'HTTP/1.1',
                'document_root' => '/var/www/html',
            ],
            'runtime' => ['type' => '', 'details' => []],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('Web Server', $html);
        self::assertStringContainsString('Apache 2.4.52', $html);
        self::assertStringContainsString('web-server-01', $html);
        self::assertStringContainsString('HTTP/1.1', $html);
        self::assertStringContainsString('/var/www/html', $html);
        self::assertStringContainsString('443', $html);
        self::assertStringContainsString('Infrastructure', $html);
    }

    #[Test]
    public function renderWithEmptyServerInfo(): void
    {
        $this->setEnvironmentData([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'extensions' => [],
            'ini' => [],
            'opcache' => [],
            'server' => [
                'software' => '',
                'web_server' => ['name' => '', 'version' => '', 'raw' => ''],
                'name' => '',
                'addr' => '',
                'port' => '',
                'protocol' => '',
                'document_root' => '',
            ],
            'runtime' => ['type' => '', 'details' => []],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('Web Server', $html);
        self::assertStringContainsString('(not available)', $html);
        self::assertStringNotContainsString('Protocol', $html);
        self::assertStringNotContainsString('Document Root', $html);
        // OS is always shown
        self::assertStringContainsString('Linux', $html);
    }

    #[Test]
    public function renderWithLambdaRuntime(): void
    {
        $this->setEnvironmentData([
            'php' => ['version' => '8.4.0'],
            'sapi' => 'cli',
            'os' => 'Linux',
            'hostname' => '',
            'extensions' => [],
            'ini' => [],
            'opcache' => [],
            'server' => [
                'software' => '',
                'web_server' => ['name' => '', 'version' => '', 'raw' => ''],
            ],
            'runtime' => [
                'type' => 'lambda',
                'details' => [
                    'Function' => 'my-wp-handler',
                    'Memory' => '512',
                    'Region' => 'ap-northeast-1',
                    'Runtime' => 'provided.al2023',
                ],
            ],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('Infrastructure', $html);
        self::assertStringContainsString('Lambda', $html);
        self::assertStringContainsString('my-wp-handler', $html);
        self::assertStringContainsString('512', $html);
        self::assertStringContainsString('ap-northeast-1', $html);
        self::assertStringContainsString('provided.al2023', $html);
    }

    #[Test]
    public function renderWithEcsRuntime(): void
    {
        $this->setEnvironmentData([
            'php' => ['version' => '8.4.0'],
            'sapi' => 'fpm-fcgi',
            'os' => 'Linux',
            'hostname' => 'ecs-task-01',
            'extensions' => [],
            'ini' => [],
            'opcache' => [],
            'server' => [
                'software' => 'nginx/1.24.0',
                'web_server' => ['name' => 'Nginx', 'version' => '1.24.0', 'raw' => 'nginx/1.24.0'],
            ],
            'runtime' => [
                'type' => 'ecs',
                'details' => [
                    'Launch Type' => 'Fargate',
                    'Region' => 'us-east-1',
                ],
            ],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('Infrastructure', $html);
        self::assertStringContainsString('ECS', $html);
        self::assertStringContainsString('Fargate', $html);
        self::assertStringContainsString('us-east-1', $html);
        self::assertStringContainsString('Web Server', $html);
        self::assertStringContainsString('Nginx 1.24.0', $html);
    }

    #[Test]
    public function renderWithDockerRuntime(): void
    {
        $this->setEnvironmentData([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'fpm-fcgi',
            'os' => 'Linux',
            'hostname' => 'abc123def456',
            'extensions' => [],
            'ini' => [],
            'opcache' => [],
            'server' => [
                'software' => 'Apache/2.4.58',
                'web_server' => ['name' => 'Apache', 'version' => '2.4.58', 'raw' => 'Apache/2.4.58'],
            ],
            'runtime' => [
                'type' => 'docker',
                'details' => [
                    'Hostname' => 'abc123def456',
                ],
            ],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('Infrastructure', $html);
        self::assertStringContainsString('Docker', $html);
        // Hostname from runtime details should be skipped when it matches data hostname
        $hostnameCount = substr_count($html, 'abc123def456');
        self::assertSame(1, $hostnameCount, 'Hostname should appear only once (not duplicated from runtime details)');
    }

    #[Test]
    public function renderWithWebServerParsed(): void
    {
        $this->setEnvironmentData([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'fpm-fcgi',
            'os' => 'Linux',
            'extensions' => [],
            'ini' => [],
            'opcache' => [],
            'server' => [
                'software' => 'LiteSpeed',
                'web_server' => ['name' => 'Litespeed', 'version' => '', 'raw' => 'LiteSpeed'],
                'protocol' => 'HTTP/2.0',
            ],
            'runtime' => ['type' => '', 'details' => []],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('Web Server', $html);
        self::assertStringContainsString('Litespeed', $html);
        self::assertStringContainsString('HTTP/2.0', $html);
    }

    #[Test]
    public function renderWithNoRuntime(): void
    {
        $this->setEnvironmentData([
            'php' => ['version' => '8.3.0'],
            'sapi' => 'fpm-fcgi',
            'os' => 'Linux',
            'hostname' => 'web-01',
            'extensions' => [],
            'ini' => [],
            'opcache' => [],
            'server' => [
                'software' => 'Apache/2.4.52',
                'web_server' => ['name' => 'Apache', 'version' => '2.4.52', 'raw' => 'Apache/2.4.52'],
            ],
            'runtime' => ['type' => '', 'details' => []],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('Infrastructure', $html);
        // Runtime row should not appear (only "PHP Runtime" section title exists)
        self::assertStringNotContainsString('wpd-tag">Lambda', $html);
        self::assertStringNotContainsString('wpd-tag">ECS', $html);
        self::assertStringNotContainsString('wpd-tag">Docker', $html);
        self::assertStringNotContainsString('wpd-tag">EC2', $html);
        self::assertStringNotContainsString('wpd-tag">Kubernetes', $html);
        self::assertStringContainsString('Linux', $html);
        self::assertStringContainsString('web-01', $html);
    }

    #[Test]
    public function getNameReturnsEnvironment(): void
    {
        self::assertSame('environment', $this->renderer->getName());
    }

    #[Test]
    public function renderIndicatorShowsPhpVersion(): void
    {
        $this->setEnvironmentData([
            'server' => [
                'web_server' => ['name' => 'Apache', 'version' => '2.4', 'raw' => 'Apache/2.4'],
            ],
            'runtime' => ['type' => '', 'details' => []],
        ]);
        $this->setWordPressData([]);
        $this->setMemoryData([]);

        $html = $this->renderer->renderIndicator();

        self::assertStringContainsString('PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, $html);
    }

    #[Test]
    public function renderIndicatorShowsLambdaRuntime(): void
    {
        $this->setEnvironmentData([
            'server' => [
                'web_server' => ['name' => '', 'version' => '', 'raw' => ''],
            ],
            'runtime' => ['type' => 'lambda', 'details' => []],
        ]);
        $this->setWordPressData([]);
        $this->setMemoryData([]);

        $html = $this->renderer->renderIndicator();

        self::assertStringContainsString('Lambda', $html);
    }

    #[Test]
    public function renderIndicatorShowsEcsRuntime(): void
    {
        $this->setEnvironmentData([
            'server' => [
                'web_server' => ['name' => '', 'version' => '', 'raw' => ''],
            ],
            'runtime' => ['type' => 'ecs', 'details' => []],
        ]);
        $this->setWordPressData([]);
        $this->setMemoryData([]);

        $html = $this->renderer->renderIndicator();

        self::assertStringContainsString('ECS', $html);
    }

    #[Test]
    public function renderIndicatorShowsK8sRuntime(): void
    {
        $this->setEnvironmentData([
            'server' => [
                'web_server' => ['name' => '', 'version' => '', 'raw' => ''],
            ],
            'runtime' => ['type' => 'kubernetes', 'details' => []],
        ]);
        $this->setWordPressData([]);
        $this->setMemoryData([]);

        $html = $this->renderer->renderIndicator();

        self::assertStringContainsString('K8s', $html);
    }

    #[Test]
    public function renderIndicatorShowsWebServerWhenNoRuntime(): void
    {
        $this->setEnvironmentData([
            'server' => [
                'web_server' => ['name' => 'Nginx', 'version' => '1.24', 'raw' => 'nginx/1.24'],
            ],
            'runtime' => ['type' => '', 'details' => []],
        ]);
        $this->setWordPressData([]);
        $this->setMemoryData([]);

        $html = $this->renderer->renderIndicator();

        self::assertStringContainsString('Nginx', $html);
    }

    #[Test]
    public function renderIndicatorShowsWpVersionInTooltip(): void
    {
        $this->setEnvironmentData([
            'server' => [
                'web_server' => ['name' => '', 'version' => '', 'raw' => ''],
            ],
            'runtime' => ['type' => '', 'details' => []],
        ]);
        $this->setWordPressData(['wp_version' => '6.5.1', 'environment_type' => 'development']);
        $this->setMemoryData([]);

        $html = $this->renderer->renderIndicator();

        self::assertStringContainsString('WordPress 6.5.1', $html);
        self::assertStringContainsString('Env: development', $html);
    }

    #[Test]
    public function renderIndicatorShowsMemoryLimitInTooltip(): void
    {
        $this->setEnvironmentData([
            'server' => [
                'web_server' => ['name' => '', 'version' => '', 'raw' => ''],
            ],
            'runtime' => ['type' => '', 'details' => []],
        ]);
        $this->setWordPressData([]);
        $this->setMemoryData(['limit' => 268435456]); // 256 MB

        $html = $this->renderer->renderIndicator();

        self::assertStringContainsString('Memory Limit:', $html);
    }

    #[Test]
    public function renderIndicatorShowsWebServerRawInTooltip(): void
    {
        $this->setEnvironmentData([
            'server' => [
                'web_server' => ['name' => 'Apache', 'version' => '2.4', 'raw' => 'Apache/2.4.52 (Ubuntu)'],
            ],
            'runtime' => ['type' => '', 'details' => []],
        ]);
        $this->setWordPressData([]);
        $this->setMemoryData([]);

        $html = $this->renderer->renderIndicator();

        self::assertStringContainsString('Apache/2.4.52 (Ubuntu)', $html);
    }

    #[Test]
    public function renderIndicatorFallsBackToWebServerNameWhenRawEmpty(): void
    {
        $this->setEnvironmentData([
            'server' => [
                'web_server' => ['name' => 'Litespeed', 'version' => '', 'raw' => ''],
            ],
            'runtime' => ['type' => '', 'details' => []],
        ]);
        $this->setWordPressData([]);
        $this->setMemoryData([]);

        $html = $this->renderer->renderIndicator();

        self::assertStringContainsString('Litespeed', $html);
    }

    #[Test]
    public function renderIndicatorNoMemoryLimitWhenZero(): void
    {
        $this->setEnvironmentData([
            'server' => [
                'web_server' => ['name' => '', 'version' => '', 'raw' => ''],
            ],
            'runtime' => ['type' => '', 'details' => []],
        ]);
        $this->setWordPressData([]);
        $this->setMemoryData(['limit' => 0]);

        $html = $this->renderer->renderIndicator();

        self::assertStringNotContainsString('Memory Limit:', $html);
    }

    private function setWordPressData(array $data): void
    {
        $collector = new class ($data) implements DataCollectorInterface {
            public function __construct(private readonly array $data) {}
            public function getName(): string
            {
                return 'wordpress';
            }
            public function collect(): void {}
            public function getData(): array
            {
                return $this->data;
            }
            public function getLabel(): string
            {
                return 'WordPress';
            }
            public function getIndicatorValue(): string
            {
                return '';
            }
            public function getIndicatorColor(): string
            {
                return 'default';
            }
            public function reset(): void {}
        };
        $this->profile->addCollector($collector);
    }

    private function setMemoryData(array $data): void
    {
        $collector = new class ($data) implements DataCollectorInterface {
            public function __construct(private readonly array $data) {}
            public function getName(): string
            {
                return 'memory';
            }
            public function collect(): void {}
            public function getData(): array
            {
                return $this->data;
            }
            public function getLabel(): string
            {
                return 'Memory';
            }
            public function getIndicatorValue(): string
            {
                return '';
            }
            public function getIndicatorColor(): string
            {
                return 'default';
            }
            public function reset(): void {}
        };
        $this->profile->addCollector($collector);
    }
}
