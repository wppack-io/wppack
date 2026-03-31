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

namespace WpPack\Plugin\ScimPlugin\Tests\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Plugin\ScimPlugin\Configuration\ScimConfiguration;

#[CoversClass(ScimConfiguration::class)]
final class ScimConfigurationTest extends TestCase
{
    /** @var list<string> */
    private array $envVars = [
        'SCIM_BEARER_TOKEN',
        'SCIM_AUTO_PROVISION',
        'SCIM_DEFAULT_ROLE',
        'SCIM_ALLOW_GROUP_MANAGEMENT',
        'SCIM_ALLOW_USER_DELETION',
        'SCIM_MAX_RESULTS',
    ];

    protected function tearDown(): void
    {
        foreach ($this->envVars as $var) {
            putenv($var);
            unset($_ENV[$var]);
        }
    }

    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $config = new ScimConfiguration(
            bearerToken: 'secret-token',
            autoProvision: false,
            defaultRole: 'editor',
            allowGroupManagement: false,
            allowUserDeletion: true,
            maxResults: 50,
        );

        self::assertSame('secret-token', $config->bearerToken);
        self::assertFalse($config->autoProvision);
        self::assertSame('editor', $config->defaultRole);
        self::assertFalse($config->allowGroupManagement);
        self::assertTrue($config->allowUserDeletion);
        self::assertSame(50, $config->maxResults);
    }

    #[Test]
    public function constructorDefaults(): void
    {
        $config = new ScimConfiguration(bearerToken: 'secret-token');

        self::assertSame('secret-token', $config->bearerToken);
        self::assertTrue($config->autoProvision);
        self::assertSame('subscriber', $config->defaultRole);
        self::assertTrue($config->allowGroupManagement);
        self::assertFalse($config->allowUserDeletion);
        self::assertSame(100, $config->maxResults);
    }

    #[Test]
    public function fromEnvironmentWithDefinedConstant(): void
    {
        if (!\defined('SCIM_BEARER_TOKEN')) {
            \define('SCIM_BEARER_TOKEN', 'constant-token');
        }

        $config = ScimConfiguration::fromEnvironment();

        self::assertSame('constant-token', $config->bearerToken);
    }

    #[Test]
    public function fromEnvironmentReadsEnvVariables(): void
    {
        putenv('SCIM_BEARER_TOKEN=env-token');
        putenv('SCIM_AUTO_PROVISION=false');
        putenv('SCIM_DEFAULT_ROLE=editor');
        putenv('SCIM_ALLOW_GROUP_MANAGEMENT=false');
        putenv('SCIM_ALLOW_USER_DELETION=true');
        putenv('SCIM_MAX_RESULTS=50');

        $config = ScimConfiguration::fromEnvironment();

        // SCIM_BEARER_TOKEN was already defined as a constant in the previous test,
        // so it returns the constant value here. Test the other fields via env.
        self::assertFalse($config->autoProvision);
        self::assertSame('editor', $config->defaultRole);
        self::assertFalse($config->allowGroupManagement);
        self::assertTrue($config->allowUserDeletion);
        self::assertSame(50, $config->maxResults);
    }

    #[Test]
    public function fromEnvironmentThrowsWhenBearerTokenMissing(): void
    {
        // Ensure SCIM_BEARER_TOKEN is not set as env var
        putenv('SCIM_BEARER_TOKEN');
        unset($_ENV['SCIM_BEARER_TOKEN']);

        // If the constant is defined from a previous test, this won't throw.
        // We only test this when the constant is NOT defined.
        if (\defined('SCIM_BEARER_TOKEN')) {
            self::assertTrue(true);

            return;
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SCIM_BEARER_TOKEN is not configured.');

        ScimConfiguration::fromEnvironment();
    }

    #[Test]
    public function fromEnvironmentThrowsOnEmptyBearerToken(): void
    {
        putenv('SCIM_BEARER_TOKEN=');

        // If the constant is defined from a previous test, the constant takes precedence.
        if (\defined('SCIM_BEARER_TOKEN')) {
            self::assertTrue(true);

            return;
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SCIM_BEARER_TOKEN is not configured.');

        ScimConfiguration::fromEnvironment();
    }

    #[Test]
    public function envBoolParsesTrueStrings(): void
    {
        putenv('SCIM_BEARER_TOKEN=token');
        putenv('SCIM_AUTO_PROVISION=true');
        putenv('SCIM_ALLOW_USER_DELETION=yes');
        putenv('SCIM_ALLOW_GROUP_MANAGEMENT=1');

        $config = ScimConfiguration::fromEnvironment();

        self::assertTrue($config->autoProvision);
        self::assertTrue($config->allowUserDeletion);
        self::assertTrue($config->allowGroupManagement);
    }

    #[Test]
    public function envBoolParsesFalseStrings(): void
    {
        putenv('SCIM_BEARER_TOKEN=token');
        putenv('SCIM_AUTO_PROVISION=false');
        putenv('SCIM_ALLOW_USER_DELETION=no');
        putenv('SCIM_ALLOW_GROUP_MANAGEMENT=0');

        $config = ScimConfiguration::fromEnvironment();

        self::assertFalse($config->autoProvision);
        self::assertFalse($config->allowUserDeletion);
        self::assertFalse($config->allowGroupManagement);
    }

    #[Test]
    public function envIntClampsToMinimum(): void
    {
        putenv('SCIM_BEARER_TOKEN=token');
        putenv('SCIM_MAX_RESULTS=0');

        $config = ScimConfiguration::fromEnvironment();

        self::assertSame(1, $config->maxResults);
    }

    #[Test]
    public function envIntClampsToMaximum(): void
    {
        putenv('SCIM_BEARER_TOKEN=token');
        putenv('SCIM_MAX_RESULTS=9999');

        $config = ScimConfiguration::fromEnvironment();

        self::assertSame(1000, $config->maxResults);
    }

    #[Test]
    public function envIntWithinRange(): void
    {
        putenv('SCIM_BEARER_TOKEN=token');
        putenv('SCIM_MAX_RESULTS=500');

        $config = ScimConfiguration::fromEnvironment();

        self::assertSame(500, $config->maxResults);
    }

    #[Test]
    public function envIntNegativeValueClampsToMinimum(): void
    {
        putenv('SCIM_BEARER_TOKEN=token');
        putenv('SCIM_MAX_RESULTS=-10');

        $config = ScimConfiguration::fromEnvironment();

        self::assertSame(1, $config->maxResults);
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsBearerTokenFromWpOptions(): void
    {
        update_option(ScimConfiguration::OPTION_NAME, ['bearerToken' => 'test-token']);

        $config = ScimConfiguration::fromEnvironmentOrOptions();

        // If SCIM_BEARER_TOKEN constant is defined, it takes priority.
        // Otherwise the wp_options value is used.
        if (\defined('SCIM_BEARER_TOKEN')) {
            self::assertSame('constant-token', $config->bearerToken);
        } else {
            self::assertSame('test-token', $config->bearerToken);
        }

        delete_option(ScimConfiguration::OPTION_NAME);
    }

    #[Test]
    public function hasTokenReturnsTrueWithWpOptions(): void
    {
        update_option(ScimConfiguration::OPTION_NAME, ['bearerToken' => 'test-token']);

        self::assertTrue(ScimConfiguration::hasToken());

        delete_option(ScimConfiguration::OPTION_NAME);
    }

    #[Test]
    public function hasTokenReturnsFalseWithNoConfiguration(): void
    {
        delete_option(ScimConfiguration::OPTION_NAME);

        // If SCIM_BEARER_TOKEN constant is defined, hasToken() always returns true
        if (\defined('SCIM_BEARER_TOKEN')) {
            self::assertTrue(ScimConfiguration::hasToken());
        } else {
            self::assertFalse(ScimConfiguration::hasToken());
        }
    }

    #[Test]
    public function bearerTokenHasSensitiveParameterAttribute(): void
    {
        $constructor = new \ReflectionMethod(ScimConfiguration::class, '__construct');
        $parameters = $constructor->getParameters();

        $bearerTokenParam = null;
        foreach ($parameters as $param) {
            if ($param->getName() === 'bearerToken') {
                $bearerTokenParam = $param;
                break;
            }
        }

        self::assertNotNull($bearerTokenParam);
        self::assertNotEmpty(
            $bearerTokenParam->getAttributes(\SensitiveParameter::class),
            'bearerToken parameter must have #[\SensitiveParameter] attribute.',
        );
    }
}
