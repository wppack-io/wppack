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

namespace WpPack\Component\OptionsResolver\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver as SymfonyOptionsResolver;
use WpPack\Component\OptionsResolver\OptionsResolver;

final class OptionsResolverTest extends TestCase
{
    #[Test]
    public function extendsSymfonyOptionsResolver(): void
    {
        $resolver = new OptionsResolver();

        self::assertInstanceOf(SymfonyOptionsResolver::class, $resolver);
    }

    #[Test]
    public function resolvesDefaultValues(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'count' => 10,
            'title' => 'hello',
        ]);

        $result = $resolver->resolve([]);

        self::assertSame(10, $result['count']);
        self::assertSame('hello', $result['title']);
    }

    #[Test]
    public function overridesDefaultValues(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'count' => 10,
            'title' => 'hello',
        ]);

        $result = $resolver->resolve(['count' => '5', 'title' => 'world']);

        self::assertSame('5', $result['count']);
        self::assertSame('world', $result['title']);
    }

    #[Test]
    public function worksWithSetAllowedValues(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['size' => 'medium']);
        $resolver->setAllowedValues('size', ['small', 'medium', 'large']);

        $result = $resolver->resolve(['size' => 'large']);

        self::assertSame('large', $result['size']);
    }

    #[Test]
    public function worksWithSetRequired(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['title' => '']);
        $resolver->setRequired('title');

        $result = $resolver->resolve(['title' => 'My Title']);

        self::assertSame('My Title', $result['title']);
    }

    #[Test]
    public function worksWithNormalizer(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['count' => 0]);
        $resolver->addNormalizer('count', static fn($resolver, $value) => (int) $value);

        $result = $resolver->resolve(['count' => '5']);

        self::assertSame(5, $result['count']);
    }

    #[Test]
    public function autoCastsStringToIntWhenAllowedTypeIsInt(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['count' => 0]);
        $resolver->setAllowedTypes('count', 'int');

        $result = $resolver->resolve(['count' => '5']);

        self::assertSame(5, $result['count']);
    }

    #[Test]
    public function autoCastsStringToFloatWhenAllowedTypeIsFloat(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['ratio' => 0.0]);
        $resolver->setAllowedTypes('ratio', 'float');

        $result = $resolver->resolve(['ratio' => '3.14']);

        self::assertSame(3.14, $result['ratio']);
    }

    #[Test]
    public function autoCastsTrueStringsToBoolWhenAllowedTypeIsBool(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['enabled' => false]);
        $resolver->setAllowedTypes('enabled', 'bool');

        foreach (['true', '1', 'yes', 'TRUE', 'Yes'] as $trueString) {
            $result = $resolver->resolve(['enabled' => $trueString]);
            self::assertTrue($result['enabled'], sprintf('Expected "%s" to be cast to true', $trueString));
        }
    }

    #[Test]
    public function autoCastsFalseStringsToBoolWhenAllowedTypeIsBool(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['enabled' => true]);
        $resolver->setAllowedTypes('enabled', 'bool');

        foreach (['false', '0', 'no', ''] as $falseString) {
            $result = $resolver->resolve(['enabled' => $falseString]);
            self::assertFalse($result['enabled'], sprintf('Expected "%s" to be cast to false', $falseString));
        }
    }

    #[Test]
    public function doesNotAutoCastWhenAllowedTypesIsArray(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['count' => 0]);
        $resolver->setAllowedTypes('count', ['int', 'string']);

        $result = $resolver->resolve(['count' => '5']);

        self::assertSame('5', $result['count']);
    }

    #[Test]
    public function doesNotAutoCastForStringType(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['title' => '']);
        $resolver->setAllowedTypes('title', 'string');

        $result = $resolver->resolve(['title' => 'hello']);

        self::assertSame('hello', $result['title']);
    }

    #[Test]
    public function nativeTypedValuePassesThroughUnchanged(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['count' => 0]);
        $resolver->setAllowedTypes('count', 'int');

        $result = $resolver->resolve(['count' => 42]);

        self::assertSame(42, $result['count']);
    }

    #[Test]
    public function userNormalizerCoexistsWithAutoCast(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['count' => 0]);
        $resolver->setAllowedTypes('count', 'int');
        $resolver->addNormalizer('count', static fn($resolver, $value) => max(1, $value));

        $result = $resolver->resolve(['count' => '0']);

        // Auto-cast: '0' → 0, then user normalizer: max(1, 0) → 1
        self::assertSame(1, $result['count']);
    }
}
