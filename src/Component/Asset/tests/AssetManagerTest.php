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

namespace WPPack\Component\Asset\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Asset\AssetManager;

final class AssetManagerTest extends TestCase
{
    private AssetManager $asset;

    protected function setUp(): void
    {
        $this->asset = new AssetManager();
    }

    #[Test]
    public function registerScriptReturnsBool(): void
    {
        $result = $this->asset->registerScript('test-script', '/js/test.js');

        self::assertTrue($result);
    }

    #[Test]
    public function enqueueScriptRegistersAndEnqueues(): void
    {
        $this->asset->enqueueScript('enqueue-test', '/js/enqueue.js', [], '1.0.0', true);

        self::assertTrue($this->asset->scriptIs('enqueue-test', 'enqueued'));
    }

    #[Test]
    public function dequeueScriptRemovesFromQueue(): void
    {
        $this->asset->enqueueScript('dequeue-test', '/js/dequeue.js');
        $this->asset->dequeueScript('dequeue-test');

        self::assertFalse($this->asset->scriptIs('dequeue-test', 'enqueued'));
    }

    #[Test]
    public function deregisterScriptRemovesRegistration(): void
    {
        $this->asset->registerScript('dereg-test', '/js/dereg.js');
        $this->asset->deregisterScript('dereg-test');

        self::assertFalse($this->asset->scriptIs('dereg-test', 'registered'));
    }

    #[Test]
    public function scriptIsReturnsFalseForUnregisteredHandle(): void
    {
        self::assertFalse($this->asset->scriptIs('nonexistent-script'));
    }

    #[Test]
    public function addInlineScriptReturnsBool(): void
    {
        $this->asset->registerScript('inline-test', '/js/inline.js');

        $result = $this->asset->addInlineScript('inline-test', 'var foo = "bar";');

        self::assertTrue($result);
    }

    #[Test]
    public function localizeScriptReturnsBool(): void
    {
        $this->asset->registerScript('l10n-test', '/js/l10n.js');

        $result = $this->asset->localizeScript('l10n-test', 'myObj', ['key' => 'value']);

        self::assertTrue($result);
    }

    #[Test]
    public function registerStyleReturnsBool(): void
    {
        $result = $this->asset->registerStyle('test-style', '/css/test.css');

        self::assertTrue($result);
    }

    #[Test]
    public function enqueueStyleRegistersAndEnqueues(): void
    {
        $this->asset->enqueueStyle('enqueue-style', '/css/enqueue.css', [], '1.0.0');

        self::assertTrue($this->asset->styleIs('enqueue-style', 'enqueued'));
    }

    #[Test]
    public function dequeueStyleRemovesFromQueue(): void
    {
        $this->asset->enqueueStyle('dequeue-style', '/css/dequeue.css');
        $this->asset->dequeueStyle('dequeue-style');

        self::assertFalse($this->asset->styleIs('dequeue-style', 'enqueued'));
    }

    #[Test]
    public function deregisterStyleRemovesRegistration(): void
    {
        $this->asset->registerStyle('dereg-style', '/css/dereg.css');
        $this->asset->deregisterStyle('dereg-style');

        self::assertFalse($this->asset->styleIs('dereg-style', 'registered'));
    }

    #[Test]
    public function styleIsReturnsFalseForUnregisteredHandle(): void
    {
        self::assertFalse($this->asset->styleIs('nonexistent-style'));
    }

    #[Test]
    public function addInlineStyleReturnsBool(): void
    {
        $this->asset->registerStyle('inline-style', '/css/inline.css');

        $result = $this->asset->addInlineStyle('inline-style', '.foo { color: red; }');

        self::assertTrue($result);
    }

    #[Test]
    public function addInlineScriptSupportsBeforePosition(): void
    {
        $this->asset->registerScript('position-test', '/js/position.js');

        $result = $this->asset->addInlineScript('position-test', 'var x = 1;', 'before');

        self::assertTrue($result);
    }

    #[Test]
    public function enqueueStyleSupportsMediaParameter(): void
    {
        $this->asset->enqueueStyle('media-test', '/css/print.css', [], '1.0.0', 'print');

        self::assertTrue($this->asset->styleIs('media-test', 'enqueued'));
    }
}
