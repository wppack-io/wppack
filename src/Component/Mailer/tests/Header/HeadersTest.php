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

namespace WPPack\Component\Mailer\Tests\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Mailer\Exception\InvalidArgumentException;
use WPPack\Component\Mailer\Header\Headers;

#[CoversClass(Headers::class)]
final class HeadersTest extends TestCase
{
    #[Test]
    public function addAndGetIsCaseInsensitive(): void
    {
        $headers = new Headers();
        $headers->add('Content-Type', 'text/html');

        self::assertSame('text/html', $headers->get('CONTENT-TYPE'));
        self::assertSame('text/html', $headers->get('content-type'));
    }

    #[Test]
    public function addAllowsMultipleValuesForSameHeader(): void
    {
        $headers = new Headers();
        $headers->add('X-Tag', 'campaign');
        $headers->add('X-Tag', 'welcome');

        self::assertSame('campaign', $headers->get('X-Tag'), 'get returns the first value');
        self::assertSame(['X-Tag' => ['campaign', 'welcome']], $headers->all());
    }

    #[Test]
    public function getReturnsNullForUnknownHeader(): void
    {
        self::assertNull((new Headers())->get('X-Unknown'));
    }

    #[Test]
    public function hasDetectsPresence(): void
    {
        $headers = new Headers();
        $headers->add('Reply-To', 'a@b.com');

        self::assertTrue($headers->has('reply-to'));
        self::assertFalse($headers->has('From'));
    }

    #[Test]
    public function removeDeletesEntry(): void
    {
        $headers = new Headers();
        $headers->add('X-Tag', 'one');
        $headers->remove('X-TAG');

        self::assertFalse($headers->has('X-Tag'));
        self::assertSame([], $headers->all());
    }

    #[Test]
    public function allPreservesOriginalCaseFromFirstInsertion(): void
    {
        $headers = new Headers();
        $headers->add('Content-Type', 'text/plain');
        $headers->add('CONTENT-TYPE', 'text/html'); // different casing

        $all = $headers->all();
        self::assertArrayHasKey('Content-Type', $all, 'first-seen case is preserved');
        self::assertSame(['text/plain', 'text/html'], $all['Content-Type']);
    }

    #[Test]
    public function rejectsHeaderInjectionAttempts(): void
    {
        $headers = new Headers();

        $this->expectException(InvalidArgumentException::class);

        $headers->add('X-Evil', "value\r\nBCC: attacker@example.com");
    }

    #[Test]
    public function rejectsInjectedNameCharacters(): void
    {
        $headers = new Headers();

        $this->expectException(InvalidArgumentException::class);

        $headers->add("X-Evil\r\nBCC", 'value');
    }
}
