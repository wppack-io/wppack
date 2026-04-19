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

namespace WPPack\Component\Handler\Tests\Processor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Handler\Configuration;
use WPPack\Component\Handler\Processor\MultisiteProcessor;
use WPPack\Component\HttpFoundation\Request;

final class MultisiteProcessorTest extends TestCase
{
    private MultisiteProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new MultisiteProcessor();
    }

    #[Test]
    public function skipsWhenMultisiteDisabled(): void
    {
        $request = Request::create('/site1/wp-admin/');
        $config = new Configuration(['multisite' => false]);

        $result = $this->processor->process($request, $config);

        self::assertNull($result);
    }

    #[Test]
    public function rewritesMultisitePath(): void
    {
        $request = Request::create('/site1/wp-admin/');
        $request->server->set('PHP_SELF', '/site1/wp-admin/');
        $config = new Configuration(['multisite' => true]);

        $this->processor->process($request, $config);

        self::assertSame('/wp/wp-admin/', $request->server->get('PHP_SELF'));
        self::assertSame('/site1/wp-admin/', $request->attributes->get('original_path'));
    }

    #[Test]
    public function doesNotRewriteNonMatchingPath(): void
    {
        $request = Request::create('/about/');
        $request->server->set('PHP_SELF', '/about/');
        $config = new Configuration(['multisite' => true]);

        $this->processor->process($request, $config);

        self::assertSame('/about/', $request->server->get('PHP_SELF'));
    }
}
