<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Tests\Storage\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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

        $result = $subscriber->filterEditors($editors);

        self::assertSame(StorageImageEditor::class, $result[0]);
        self::assertSame(\WP_Image_Editor_Imagick::class, $result[1]);
        self::assertSame(\WP_Image_Editor_GD::class, $result[2]);
        self::assertCount(3, $result);
    }

    #[Test]
    public function filterEditorsAddsToEmptyList(): void
    {
        $subscriber = new ImageEditorSubscriber(StorageImageEditor::class);

        $result = $subscriber->filterEditors([]);

        self::assertSame([StorageImageEditor::class], $result);
    }

    #[Test]
    public function filterEditorsDoesNotDuplicate(): void
    {
        $subscriber = new ImageEditorSubscriber(StorageImageEditor::class);

        $editors = [\WP_Image_Editor_GD::class];
        $result = $subscriber->filterEditors($editors);

        self::assertCount(2, $result);
        self::assertSame(StorageImageEditor::class, $result[0]);
        self::assertSame(\WP_Image_Editor_GD::class, $result[1]);
    }

    #[Test]
    public function filterEditorsAcceptsCustomEditorClass(): void
    {
        $customClass = 'App\\CustomImageEditor';
        $subscriber = new ImageEditorSubscriber($customClass);

        $editors = [\WP_Image_Editor_Imagick::class];
        $result = $subscriber->filterEditors($editors);

        self::assertSame($customClass, $result[0]);
    }
}
