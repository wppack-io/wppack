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

namespace WPPack\Plugin\PasskeyLoginPlugin\Tests\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Plugin\PasskeyLoginPlugin\Configuration\PasskeyLoginConfiguration;

#[CoversClass(PasskeyLoginConfiguration::class)]
final class PasskeyLoginConfigurationTest extends TestCase
{
    /** @var list<string> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        delete_option(PasskeyLoginConfiguration::OPTION_NAME);

        foreach (PasskeyLoginConfiguration::ENV_MAP as $envName) {
            if (isset($_ENV[$envName])) {
                $this->envBackup[$envName] = $_ENV[$envName];
                unset($_ENV[$envName]);
                putenv($envName);
            }
        }
    }

    protected function tearDown(): void
    {
        delete_option(PasskeyLoginConfiguration::OPTION_NAME);
        foreach ($this->envBackup as $name => $value) {
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }

    #[Test]
    public function defaultsMatchWebAuthnBestPractices(): void
    {
        $config = new PasskeyLoginConfiguration();

        self::assertTrue($config->enabled);
        self::assertSame('', $config->rpName);
        self::assertSame('', $config->rpId);
        self::assertFalse($config->allowSignup);
        self::assertSame('preferred', $config->requireUserVerification);
        self::assertSame([-7, -257], $config->algorithms);
        self::assertSame('none', $config->attestation);
        self::assertSame('', $config->authenticatorAttachment);
        self::assertSame(60000, $config->timeout);
        self::assertSame('required', $config->residentKey);
        self::assertSame('icon-text', $config->buttonDisplay);
        self::assertSame(3, $config->maxCredentialsPerUser);
    }

    #[Test]
    public function fromEnvironmentOrOptionsReturnsDefaultsWhenNoSourcePresent(): void
    {
        $config = PasskeyLoginConfiguration::fromEnvironmentOrOptions();

        self::assertTrue($config->enabled);
        self::assertSame([-7, -257], $config->algorithms);
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsWpOption(): void
    {
        update_option(PasskeyLoginConfiguration::OPTION_NAME, [
            'rpName' => 'WPPack Test',
            'rpId' => 'example.test',
            'timeout' => 30000,
            'allowSignup' => true,
            'algorithms' => [-7, -257, -8],
        ]);

        $config = PasskeyLoginConfiguration::fromEnvironmentOrOptions();

        self::assertSame('WPPack Test', $config->rpName);
        self::assertSame('example.test', $config->rpId);
        self::assertSame(30000, $config->timeout);
        self::assertTrue($config->allowSignup);
        self::assertSame([-7, -257, -8], $config->algorithms);
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsEnvironmentVariables(): void
    {
        $_ENV['PASSKEY_RP_NAME'] = 'From ENV';
        $_ENV['PASSKEY_TIMEOUT'] = '45000';
        $_ENV['PASSKEY_ALGORITHMS'] = '-7,-257,-8';
        $_ENV['PASSKEY_ALLOW_SIGNUP'] = 'true';

        $config = PasskeyLoginConfiguration::fromEnvironmentOrOptions();

        self::assertSame('From ENV', $config->rpName);
        self::assertSame(45000, $config->timeout);
        self::assertSame([-7, -257, -8], $config->algorithms);
        self::assertTrue($config->allowSignup);

        unset($_ENV['PASSKEY_RP_NAME'], $_ENV['PASSKEY_TIMEOUT'], $_ENV['PASSKEY_ALGORITHMS'], $_ENV['PASSKEY_ALLOW_SIGNUP']);
    }

    #[Test]
    public function wpOptionOverridesEnvironment(): void
    {
        $_ENV['PASSKEY_RP_NAME'] = 'From ENV';
        update_option(PasskeyLoginConfiguration::OPTION_NAME, ['rpName' => 'From Options']);

        $config = PasskeyLoginConfiguration::fromEnvironmentOrOptions();

        self::assertSame('From Options', $config->rpName, 'wp_option takes precedence over env');

        unset($_ENV['PASSKEY_RP_NAME']);
    }

    #[Test]
    public function emptyOptionStringFallsBackToEnv(): void
    {
        $_ENV['PASSKEY_RP_NAME'] = 'From ENV';
        update_option(PasskeyLoginConfiguration::OPTION_NAME, ['rpName' => '']);

        $config = PasskeyLoginConfiguration::fromEnvironmentOrOptions();

        self::assertSame('From ENV', $config->rpName, 'empty string in option is ignored');

        unset($_ENV['PASSKEY_RP_NAME']);
    }

    #[Test]
    public function boolEnvVariableAcceptsVariousTrueValues(): void
    {
        foreach (['1', 'true', 'YES', 'on'] as $trueValue) {
            $_ENV['PASSKEY_ENABLED'] = $trueValue;
            $config = PasskeyLoginConfiguration::fromEnvironmentOrOptions();
            self::assertTrue($config->enabled, "value {$trueValue} parses as true");
        }

        $_ENV['PASSKEY_ENABLED'] = 'no';
        $config = PasskeyLoginConfiguration::fromEnvironmentOrOptions();
        self::assertFalse($config->enabled);

        unset($_ENV['PASSKEY_ENABLED']);
    }

    #[Test]
    public function algorithmsEnvStringIsCsvParsed(): void
    {
        $_ENV['PASSKEY_ALGORITHMS'] = ' -7 , -257 ,  -8 ';

        $config = PasskeyLoginConfiguration::fromEnvironmentOrOptions();

        self::assertSame([-7, -257, -8], $config->algorithms);

        unset($_ENV['PASSKEY_ALGORITHMS']);
    }

    #[Test]
    public function nonNumericTimeoutEnvFallsBackToDefault(): void
    {
        $_ENV['PASSKEY_TIMEOUT'] = 'not-a-number';

        $config = PasskeyLoginConfiguration::fromEnvironmentOrOptions();

        self::assertSame(60000, $config->timeout);

        unset($_ENV['PASSKEY_TIMEOUT']);
    }

    #[Test]
    public function emptyAlgorithmCsvYieldsEmptyList(): void
    {
        $_ENV['PASSKEY_ALGORITHMS'] = '   ,   ';

        $config = PasskeyLoginConfiguration::fromEnvironmentOrOptions();

        self::assertSame([], $config->algorithms, 'all-empty entries filter out');

        unset($_ENV['PASSKEY_ALGORITHMS']);
    }

    #[Test]
    public function envMapCoversAllMutableFields(): void
    {
        $map = PasskeyLoginConfiguration::ENV_MAP;

        self::assertArrayHasKey('enabled', $map);
        self::assertArrayHasKey('rpName', $map);
        self::assertArrayHasKey('rpId', $map);
        self::assertArrayHasKey('allowSignup', $map);
        self::assertArrayHasKey('algorithms', $map);
        self::assertArrayHasKey('timeout', $map);
        self::assertArrayHasKey('buttonDisplay', $map);
        self::assertArrayHasKey('maxCredentialsPerUser', $map);
    }
}
