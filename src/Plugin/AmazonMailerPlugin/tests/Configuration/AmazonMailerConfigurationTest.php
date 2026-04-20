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

namespace WPPack\Plugin\AmazonMailerPlugin\Tests\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Plugin\AmazonMailerPlugin\Configuration\AmazonMailerConfiguration;

// Each test runs in its own process so defining the MAILER_DSN constant
// in one test does not permanently short-circuit subsequent tests that
// need to exercise the env / wp_options fallbacks.
#[CoversClass(AmazonMailerConfiguration::class)]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AmazonMailerConfigurationTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('MAILER_DSN');
        unset($_ENV['MAILER_DSN']);
        delete_option(AmazonMailerConfiguration::OPTION_NAME);
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

    #[Test]
    public function fromEnvironmentOrOptionsReadsDsnFromWpOptions(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined, cannot test wp_options fallback.');
        }

        update_option(AmazonMailerConfiguration::OPTION_NAME, ['dsn' => 'native://default']);

        $config = AmazonMailerConfiguration::fromEnvironmentOrOptions();

        self::assertSame('native://default', $config->dsn);
    }

    #[Test]
    public function hasConfigurationReturnsTrueWithWpOptions(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined, cannot test wp_options fallback.');
        }

        update_option(AmazonMailerConfiguration::OPTION_NAME, ['dsn' => 'native://default']);

        self::assertTrue(AmazonMailerConfiguration::hasConfiguration());
    }

    #[Test]
    public function hasConfigurationReturnsFalseWhenEmpty(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined, cannot test empty config.');
        }

        delete_option(AmazonMailerConfiguration::OPTION_NAME);

        self::assertFalse(AmazonMailerConfiguration::hasConfiguration());
    }

    #[Test]
    public function fromEnvironmentOrOptionsThrowsWhenNotConfigured(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined.');
        }

        delete_option(AmazonMailerConfiguration::OPTION_NAME);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MAILER_DSN is not configured.');

        AmazonMailerConfiguration::fromEnvironmentOrOptions();
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsEnvSuperglobal(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined.');
        }

        $_ENV['MAILER_DSN'] = 'smtp://user:pass@smtp.example.com:587';

        $config = AmazonMailerConfiguration::fromEnvironmentOrOptions();

        self::assertSame('smtp://user:pass@smtp.example.com:587', $config->dsn);
    }

    #[Test]
    public function hasConfigurationReturnsTrueWithEnvVariable(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined.');
        }

        putenv('MAILER_DSN=smtp://localhost');

        self::assertTrue(AmazonMailerConfiguration::hasConfiguration());
    }

    #[Test]
    public function maskedValueConstant(): void
    {
        self::assertSame('********', AmazonMailerConfiguration::MASKED_VALUE);
    }

    #[Test]
    public function optionNameConstant(): void
    {
        self::assertSame('wppack_mailer', AmazonMailerConfiguration::OPTION_NAME);
    }

    #[Test]
    public function hasConfigurationReturnsFalseWithEmptyDsn(): void
    {
        if (\defined('MAILER_DSN')) {
            $this->markTestSkipped('MAILER_DSN constant already defined.');
        }

        update_option(AmazonMailerConfiguration::OPTION_NAME, ['dsn' => '']);

        self::assertFalse(AmazonMailerConfiguration::hasConfiguration());
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsConstantWhenDefined(): void
    {
        \define('MAILER_DSN', 'ses+api://key:secret@default?region=ap-northeast-1');

        $config = AmazonMailerConfiguration::fromEnvironmentOrOptions();

        self::assertSame('ses+api://key:secret@default?region=ap-northeast-1', $config->dsn);
    }

    #[Test]
    public function hasConfigurationReturnsTrueWhenConstantIsDefined(): void
    {
        \define('MAILER_DSN', 'ses+api://default?region=us-east-1');

        self::assertTrue(AmazonMailerConfiguration::hasConfiguration());
    }
}
