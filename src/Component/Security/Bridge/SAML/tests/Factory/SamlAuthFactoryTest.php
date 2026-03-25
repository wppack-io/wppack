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

namespace WpPack\Component\Security\Bridge\SAML\Tests\Factory;

use OneLogin\Saml2\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;

#[CoversClass(SamlAuthFactory::class)]
final class SamlAuthFactoryTest extends TestCase
{
    private SamlConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new SamlConfiguration(
            idpSettings: new IdpSettings(
                entityId: 'https://idp.example.com/metadata',
                ssoUrl: 'https://idp.example.com/sso',
                sloUrl: 'https://idp.example.com/slo',
                x509Cert: 'MIICDummyCert==',
            ),
            spSettings: new SpSettings(
                entityId: 'https://sp.example.com/metadata',
                acsUrl: 'https://sp.example.com/acs',
                sloUrl: 'https://sp.example.com/slo',
            ),
        );
    }

    #[Test]
    public function getConfiguration(): void
    {
        $factory = new SamlAuthFactory($this->configuration);

        self::assertSame($this->configuration, $factory->getConfiguration());
    }

    #[Test]
    public function createReturnsAuthInstance(): void
    {
        $factory = new SamlAuthFactory($this->configuration);
        $auth = $factory->create();

        self::assertInstanceOf(Auth::class, $auth);
    }
}
