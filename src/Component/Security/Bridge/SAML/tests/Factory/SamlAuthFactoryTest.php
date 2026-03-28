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

use LightSaml\Binding\BindingFactory;
use LightSaml\Credential\X509Credential;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use WpPack\Component\HttpFoundation\Request;
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
    public function createBindingFactoryReturnsBindingFactory(): void
    {
        $factory = new SamlAuthFactory($this->configuration);
        $bindingFactory = $factory->createBindingFactory();

        self::assertInstanceOf(BindingFactory::class, $bindingFactory);
    }

    #[Test]
    public function createCredentialReturnsX509Credential(): void
    {
        // createCredential requires a valid X.509 certificate
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'Test'], $key);
        openssl_csr_sign($csr, null, $key, 365);
        openssl_x509_export(openssl_csr_sign($csr, null, $key, 365), $pem);

        // Extract the base64 body (without BEGIN/END lines)
        $lines = explode("\n", trim($pem));
        array_shift($lines);
        array_pop($lines);
        $certData = implode('', $lines);

        $config = new SamlConfiguration(
            idpSettings: new IdpSettings(
                entityId: 'https://idp.example.com/metadata',
                ssoUrl: 'https://idp.example.com/sso',
                sloUrl: 'https://idp.example.com/slo',
                x509Cert: $certData,
            ),
            spSettings: new SpSettings(
                entityId: 'https://sp.example.com/metadata',
                acsUrl: 'https://sp.example.com/acs',
                sloUrl: 'https://sp.example.com/slo',
            ),
        );

        $factory = new SamlAuthFactory($config);
        $credential = $factory->createCredential();

        self::assertInstanceOf(X509Credential::class, $credential);
    }

    #[Test]
    public function toSymfonyRequestConvertsRequest(): void
    {
        $request = new Request(
            query: ['foo' => 'bar'],
            post: ['baz' => 'qux'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/saml/acs'],
        );

        $symfonyRequest = SamlAuthFactory::toSymfonyRequest($request);

        self::assertInstanceOf(SymfonyRequest::class, $symfonyRequest);
        self::assertSame('bar', $symfonyRequest->query->get('foo'));
        self::assertSame('qux', $symfonyRequest->request->get('baz'));
    }
}
