<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML\Tests;

use OneLogin\Saml2\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\SamlEntryPoint;

#[CoversClass(SamlEntryPoint::class)]
final class SamlEntryPointTest extends TestCase
{
    #[Test]
    public function getLoginUrl(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->expects(self::once())
            ->method('login')
            ->with(null, [], false, false, true)
            ->willReturn('https://idp.example.com/sso?SAMLRequest=encoded');

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint($factory);
        $loginUrl = $entryPoint->getLoginUrl();

        self::assertSame('https://idp.example.com/sso?SAMLRequest=encoded', $loginUrl);
    }

    #[Test]
    public function getLoginUrlWithReturnTo(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->expects(self::once())
            ->method('login')
            ->with('https://sp.example.com/dashboard', [], false, false, true)
            ->willReturn('https://idp.example.com/sso?SAMLRequest=encoded&RelayState=...');

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint($factory);
        $loginUrl = $entryPoint->getLoginUrl('https://sp.example.com/dashboard');

        self::assertSame(
            'https://idp.example.com/sso?SAMLRequest=encoded&RelayState=...',
            $loginUrl,
        );
    }
}
