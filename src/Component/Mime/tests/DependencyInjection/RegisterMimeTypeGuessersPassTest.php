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

namespace WPPack\Component\Mime\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Mime\DependencyInjection\RegisterMimeTypeGuessersPass;
use WPPack\Component\Mime\MimeTypes;

#[CoversClass(RegisterMimeTypeGuessersPass::class)]
final class RegisterMimeTypeGuessersPassTest extends TestCase
{
    #[Test]
    public function noOpWhenMimeTypesServiceIsAbsent(): void
    {
        $builder = new ContainerBuilder();

        // Should not throw despite no MimeTypes
        (new RegisterMimeTypeGuessersPass())->process($builder);

        self::assertFalse($builder->hasDefinition(MimeTypes::class));
    }

    #[Test]
    public function registersTaggedGuessersAsMethodCalls(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(MimeTypes::class);
        $builder->register('custom.guesser')->addTag('mime.mime_type_guesser');
        $builder->register('another.guesser')->addTag('mime.mime_type_guesser');

        (new RegisterMimeTypeGuessersPass())->process($builder);

        $calls = $builder->findDefinition(MimeTypes::class)->getMethodCalls();
        self::assertCount(2, $calls);
        foreach ($calls as $call) {
            self::assertSame('registerGuesser', $call['method']);
        }
    }

    #[Test]
    public function customTagNameIsRespected(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(MimeTypes::class);
        $builder->register('custom.guesser')->addTag('app.custom_guesser_tag');

        (new RegisterMimeTypeGuessersPass(tag: 'app.custom_guesser_tag'))->process($builder);

        $calls = $builder->findDefinition(MimeTypes::class)->getMethodCalls();
        self::assertCount(1, $calls);
    }

    #[Test]
    public function noCallsWhenNothingIsTagged(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(MimeTypes::class);

        (new RegisterMimeTypeGuessersPass())->process($builder);

        self::assertSame([], $builder->findDefinition(MimeTypes::class)->getMethodCalls());
    }
}
