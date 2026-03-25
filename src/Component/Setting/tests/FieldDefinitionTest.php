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

namespace WpPack\Component\Setting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Setting\FieldDefinition;

#[CoversClass(FieldDefinition::class)]
final class FieldDefinitionTest extends TestCase
{
    #[Test]
    public function constructsWithAllProperties(): void
    {
        $callback = static fn(array $args): string => '<input />';
        $args = ['class' => 'regular-text', 'label_for' => 'my_field'];

        $field = new FieldDefinition(
            id: 'my_field',
            title: 'My Field',
            renderCallback: $callback,
            args: $args,
        );

        self::assertSame('my_field', $field->id);
        self::assertSame('My Field', $field->title);
        self::assertSame($callback, $field->renderCallback);
        self::assertSame($args, $field->args);
    }

    #[Test]
    public function constructsWithDefaultArgs(): void
    {
        $callback = static fn(array $args): string => '';

        $field = new FieldDefinition(
            id: 'api_key',
            title: 'API Key',
            renderCallback: $callback,
        );

        self::assertSame([], $field->args);
    }

    #[Test]
    public function renderCallbackIsInvocable(): void
    {
        $callback = static fn(array $args): string => '<input value="' . ($args['value'] ?? '') . '" />';

        $field = new FieldDefinition(
            id: 'test',
            title: 'Test',
            renderCallback: $callback,
        );

        $result = ($field->renderCallback)(['value' => 'hello']);

        self::assertSame('<input value="hello" />', $result);
    }

    #[Test]
    public function propertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(FieldDefinition::class);

        foreach (['id', 'title', 'renderCallback', 'args'] as $property) {
            self::assertTrue(
                $reflection->getProperty($property)->isReadOnly(),
                \sprintf('Property $%s should be readonly', $property),
            );
        }
    }
}
