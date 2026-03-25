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

namespace WpPack\Plugin\AmazonMailerPlugin\Tests\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Plugin\AmazonMailerPlugin\Configuration\AmazonMailerConfiguration;

#[CoversClass(AmazonMailerConfiguration::class)]
final class AmazonMailerConfigurationTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('MAILER_DSN');
        unset($_ENV['MAILER_DSN']);
    }

    #[Test]
    public function constructorSetsDsn(): void
    {
        $config = new AmazonMailerConfiguration(dsn: 'ses+api://default?region=us-east-1');

        self::assertSame('ses+api://default?region=us-east-1', $config->dsn);
    }

    #[Test]
    public function fromEnvironmentReadsConstant(): void
    {
        if (!\defined('MAILER_DSN')) {
            \define('MAILER_DSN', 'ses+api://key:secret@default?region=ap-northeast-1');
        }

        $config = AmazonMailerConfiguration::fromEnvironment();

        self::assertSame('ses+api://key:secret@default?region=ap-northeast-1', $config->dsn);
    }

    #[Test]
    public function fromEnvironmentReadsEnvVariable(): void
    {
        // Skip if constant is already defined from previous test
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined, cannot test env fallback.');
        }

        putenv('MAILER_DSN=ses+api://default?region=us-west-2');

        $config = AmazonMailerConfiguration::fromEnvironment();

        self::assertSame('ses+api://default?region=us-west-2', $config->dsn);
    }

    #[Test]
    public function fromEnvironmentReadsEnvSuperglobal(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined, cannot test env fallback.');
        }

        $_ENV['MAILER_DSN'] = 'ses+api://default?region=eu-west-1';

        $config = AmazonMailerConfiguration::fromEnvironment();

        self::assertSame('ses+api://default?region=eu-west-1', $config->dsn);
    }

    #[Test]
    public function fromEnvironmentThrowsWhenNotConfigured(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined, cannot test missing config.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MAILER_DSN is not configured');

        AmazonMailerConfiguration::fromEnvironment();
    }
}
