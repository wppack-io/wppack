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

namespace WPPack\Component\Hook\Tests\Attribute\Filesystem;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Hook\Attribute\Filesystem\Action\WpFilesystemInitAction;
use WPPack\Component\Hook\Attribute\Filesystem\Filter\FileIsDisplayableImageFilter;
use WPPack\Component\Hook\Attribute\Filesystem\Filter\FilesystemMethodFileFilter;
use WPPack\Component\Hook\Attribute\Filesystem\Filter\FilesystemMethodFilter;
use WPPack\Component\Hook\Attribute\Filesystem\Filter\LoadImageToEditPathFilter;
use WPPack\Component\Hook\Attribute\Filesystem\Filter\PreWpUniqueFilenameFileListFilter;
use WPPack\Component\Hook\Attribute\Filesystem\Filter\UploadDirFilter;
use WPPack\Component\Hook\Attribute\Filesystem\Filter\WpDeleteFileFilter;
use WPPack\Component\Hook\Attribute\Filesystem\Filter\WpHandleSideloadPrefilterFilter;
use WPPack\Component\Hook\Attribute\Filesystem\Filter\WpMkdirModeFilter;
use WPPack\Component\Hook\Attribute\Filesystem\Filter\WpUniqueFilenameFilter;
use WPPack\Component\Hook\Attribute\Filesystem\Filter\WpUploadBitsFilter;
use WPPack\Component\Hook\Attribute\Filter;
use WPPack\Component\Hook\Hook;
use WPPack\Component\Hook\HookType;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function wpFilesystemInitActionHasCorrectHookName(): void
    {
        $action = new WpFilesystemInitAction();

        self::assertSame('wp_filesystem_init', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function wpFilesystemInitActionAcceptsCustomPriority(): void
    {
        $action = new WpFilesystemInitAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function fileIsDisplayableImageFilterHasCorrectHookName(): void
    {
        $filter = new FileIsDisplayableImageFilter();

        self::assertSame('file_is_displayable_image', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function filesystemMethodFileFilterHasCorrectHookName(): void
    {
        $filter = new FilesystemMethodFileFilter();

        self::assertSame('filesystem_method_file', $filter->hook);
    }

    #[Test]
    public function filesystemMethodFilterHasCorrectHookName(): void
    {
        $filter = new FilesystemMethodFilter();

        self::assertSame('filesystem_method', $filter->hook);
    }

    #[Test]
    public function loadImageToEditPathFilterHasCorrectHookName(): void
    {
        $filter = new LoadImageToEditPathFilter();

        self::assertSame('load_image_to_edit_path', $filter->hook);
    }

    #[Test]
    public function uploadDirFilterHasCorrectHookName(): void
    {
        $filter = new UploadDirFilter();

        self::assertSame('upload_dir', $filter->hook);
    }

    #[Test]
    public function wpDeleteFileFilterHasCorrectHookName(): void
    {
        $filter = new WpDeleteFileFilter();

        self::assertSame('wp_delete_file', $filter->hook);
    }

    #[Test]
    public function wpHandleSideloadPrefilterFilterHasCorrectHookName(): void
    {
        $filter = new WpHandleSideloadPrefilterFilter();

        self::assertSame('wp_handle_sideload_prefilter', $filter->hook);
    }

    #[Test]
    public function wpMkdirModeFilterHasCorrectHookName(): void
    {
        $filter = new WpMkdirModeFilter();

        self::assertSame('wp_mkdir_mode', $filter->hook);
    }

    #[Test]
    public function wpUniqueFilenameFilterHasCorrectHookName(): void
    {
        $filter = new WpUniqueFilenameFilter();

        self::assertSame('wp_unique_filename', $filter->hook);
    }

    #[Test]
    public function wpUploadBitsFilterHasCorrectHookName(): void
    {
        $filter = new WpUploadBitsFilter();

        self::assertSame('wp_upload_bits', $filter->hook);
    }

    #[Test]
    public function preWpUniqueFilenameFileListFilterHasCorrectHookName(): void
    {
        $filter = new PreWpUniqueFilenameFileListFilter();

        self::assertSame('pre_wp_unique_filename_file_list', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new WpFilesystemInitAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new FileIsDisplayableImageFilter());
        self::assertInstanceOf(Filter::class, new FilesystemMethodFileFilter());
        self::assertInstanceOf(Filter::class, new FilesystemMethodFilter());
        self::assertInstanceOf(Filter::class, new LoadImageToEditPathFilter());
        self::assertInstanceOf(Filter::class, new UploadDirFilter());
        self::assertInstanceOf(Filter::class, new WpDeleteFileFilter());
        self::assertInstanceOf(Filter::class, new WpHandleSideloadPrefilterFilter());
        self::assertInstanceOf(Filter::class, new WpMkdirModeFilter());
        self::assertInstanceOf(Filter::class, new WpUniqueFilenameFilter());
        self::assertInstanceOf(Filter::class, new WpUploadBitsFilter());
        self::assertInstanceOf(Filter::class, new PreWpUniqueFilenameFileListFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[WpFilesystemInitAction]
            public function onWpFilesystemInit(): void {}

            #[UploadDirFilter(priority: 5)]
            public function onUploadDir(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onWpFilesystemInit');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('wp_filesystem_init', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onUploadDir');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('upload_dir', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
