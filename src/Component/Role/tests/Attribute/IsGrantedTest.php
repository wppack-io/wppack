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

namespace WPPack\Component\Role\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Role\Attribute\IsGranted;

final class IsGrantedTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $attr = new IsGranted('edit_posts');

        self::assertSame('edit_posts', $attr->attribute);
        self::assertNull($attr->subject);
        self::assertSame('Access Denied.', $attr->message);
        self::assertSame(403, $attr->statusCode);
    }

    #[Test]
    public function allParametersCustomized(): void
    {
        $attr = new IsGranted(
            attribute: 'edit_post',
            subject: 42,
            message: 'You cannot edit this post.',
            statusCode: 401,
        );

        self::assertSame('edit_post', $attr->attribute);
        self::assertSame(42, $attr->subject);
        self::assertSame('You cannot edit this post.', $attr->message);
        self::assertSame(401, $attr->statusCode);
    }

    #[Test]
    public function targetsClassMethodAndIsRepeatable(): void
    {
        $reflection = new \ReflectionClass(IsGranted::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);
        $flags = $attributes[0]->newInstance()->flags;

        self::assertNotSame(0, $flags & \Attribute::TARGET_CLASS);
        self::assertNotSame(0, $flags & \Attribute::TARGET_METHOD);
        self::assertNotSame(0, $flags & \Attribute::IS_REPEATABLE);
    }

    #[Test]
    public function isRepeatableOnMethod(): void
    {
        $class = new class {
            #[IsGranted('edit_posts')]
            #[IsGranted('publish_posts')]
            public function handle(): void {}
        };

        $method = new \ReflectionMethod($class, 'handle');
        $attributes = $method->getAttributes(IsGranted::class);

        self::assertCount(2, $attributes);
        self::assertSame('edit_posts', $attributes[0]->newInstance()->attribute);
        self::assertSame('publish_posts', $attributes[1]->newInstance()->attribute);
    }

    #[Test]
    public function isRepeatableOnClass(): void
    {
        $class = new #[IsGranted('edit_posts')] #[IsGranted('ROLE_EDITOR')] class {};

        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(IsGranted::class);

        self::assertCount(2, $attributes);
        self::assertSame('edit_posts', $attributes[0]->newInstance()->attribute);
        self::assertSame('ROLE_EDITOR', $attributes[1]->newInstance()->attribute);
    }
}
