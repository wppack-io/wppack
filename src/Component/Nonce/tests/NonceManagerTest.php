<?php

declare(strict_types=1);

namespace WpPack\Component\Nonce\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Nonce\NonceManager;

final class NonceManagerTest extends TestCase
{
    private NonceManager $nonceManager;

    protected function setUp(): void
    {
        $this->nonceManager = new NonceManager();
    }

    #[Test]
    public function createReturnsString(): void
    {
        $nonce = $this->nonceManager->create('test-action');

        self::assertIsString($nonce);
        self::assertNotEmpty($nonce);
    }

    #[Test]
    public function verifyReturnsTrueForValidNonce(): void
    {
        $nonce = $this->nonceManager->create('test-action');

        self::assertTrue($this->nonceManager->verify($nonce, 'test-action'));
    }

    #[Test]
    public function verifyReturnsFalseForInvalidNonce(): void
    {
        self::assertFalse($this->nonceManager->verify('invalid-nonce', 'test-action'));
    }

    #[Test]
    public function verifyReturnsFalseForWrongAction(): void
    {
        $nonce = $this->nonceManager->create('test-action');

        self::assertFalse($this->nonceManager->verify($nonce, 'wrong-action'));
    }

    #[Test]
    public function fieldReturnsHiddenInput(): void
    {
        $html = $this->nonceManager->field('test-action');

        self::assertStringContainsString('<input', $html);
        self::assertStringContainsString('type="hidden"', $html);
        self::assertStringContainsString('name="_wpnonce"', $html);
    }

    #[Test]
    public function fieldSupportsCustomName(): void
    {
        $html = $this->nonceManager->field('test-action', 'custom_nonce');

        self::assertStringContainsString('name="custom_nonce"', $html);
    }

    #[Test]
    public function urlAppendsNonceParameter(): void
    {
        $url = $this->nonceManager->url('https://example.com/action', 'test-action');

        self::assertStringContainsString('_wpnonce=', $url);
        self::assertStringStartsWith('https://example.com/action', $url);
    }

    #[Test]
    public function tickReturnsPositiveInteger(): void
    {
        $tick = $this->nonceManager->tick();

        self::assertIsInt($tick);
        self::assertGreaterThan(0, $tick);
    }

}
