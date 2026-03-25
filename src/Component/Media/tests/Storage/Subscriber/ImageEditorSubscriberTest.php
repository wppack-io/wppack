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

namespace WpPack\Component\Media\Tests\Storage\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\EventDispatcher\WordPressEvent;
use WpPack\Component\Media\Storage\ImageEditor\StorageImageEditor;
use WpPack\Component\Media\Storage\Subscriber\ImageEditorSubscriber;

#[CoversClass(ImageEditorSubscriber::class)]
final class ImageEditorSubscriberTest extends TestCase
{
    #[Test]
    public function filterEditorsPrependsStorageImageEditor(): void
    {
        $subscriber = new ImageEditorSubscriber(StorageImageEditor::class);

        $editors = [
            \WP_Image_Editor_Imagick::class,
            \WP_Image_Editor_GD::class,
        ];

        $event = new WordPressEvent('wp_image_editors', [$editors]);
        $subscriber->filterEditors($event);

        self::assertSame(StorageImageEditor::class, $event->filterValue[0]);
        self::assertSame(\WP_Image_Editor_Imagick::class, $event->filterValue[1]);
        self::assertSame(\WP_Image_Editor_GD::class, $event->filterValue[2]);
        self::assertCount(3, $event->filterValue);
    }

    #[Test]
    public function filterEditorsAddsToEmptyList(): void
    {
        $subscriber = new ImageEditorSubscriber(StorageImageEditor::class);

        $event = new WordPressEvent('wp_image_editors', [[]]);
        $subscriber->filterEditors($event);

        self::assertSame([StorageImageEditor::class], $event->filterValue);
    }

    #[Test]
    public function filterEditorsDoesNotDuplicate(): void
    {
        $subscriber = new ImageEditorSubscriber(StorageImageEditor::class);

        $editors = [\WP_Image_Editor_GD::class];
        $event = new WordPressEvent('wp_image_editors', [$editors]);
        $subscriber->filterEditors($event);

        self::assertCount(2, $event->filterValue);
        self::assertSame(StorageImageEditor::class, $event->filterValue[0]);
        self::assertSame(\WP_Image_Editor_GD::class, $event->filterValue[1]);
    }

    #[Test]
    public function filterEditorsAcceptsCustomEditorClass(): void
    {
        $customClass = 'App\\CustomImageEditor';
        $subscriber = new ImageEditorSubscriber($customClass);

        $editors = [\WP_Image_Editor_Imagick::class];
        $event = new WordPressEvent('wp_image_editors', [$editors]);
        $subscriber->filterEditors($event);

        self::assertSame($customClass, $event->filterValue[0]);
    }
}
