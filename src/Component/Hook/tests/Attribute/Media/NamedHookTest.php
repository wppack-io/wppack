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

namespace WPPack\Component\Hook\Tests\Attribute\Media;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Hook\Attribute\Filter;
use WPPack\Component\Hook\Attribute\Media\Action\DeleteAttachmentAction;
use WPPack\Component\Hook\Attribute\Media\Filter\AjaxQueryAttachmentsArgsFilter;
use WPPack\Component\Hook\Attribute\Media\Filter\GetAttachedFileFilter;
use WPPack\Component\Hook\Attribute\Media\Filter\IntermediateSizesAdvancedFilter;
use WPPack\Component\Hook\Attribute\Media\Filter\MediaUploadTabsFilter;
use WPPack\Component\Hook\Attribute\Media\Filter\UploadMimesFilter;
use WPPack\Component\Hook\Attribute\Media\Filter\WpCalculateImageSrcsetFilter;
use WPPack\Component\Hook\Attribute\Media\Filter\WpGenerateAttachmentMetadataFilter;
use WPPack\Component\Hook\Attribute\Media\Filter\WpGetAttachmentImageAttributesFilter;
use WPPack\Component\Hook\Attribute\Media\Filter\WpGetAttachmentImageSrcFilter;
use WPPack\Component\Hook\Attribute\Media\Filter\WpGetAttachmentUrlFilter;
use WPPack\Component\Hook\Attribute\Media\Filter\WpHandleUploadFilter;
use WPPack\Component\Hook\Attribute\Media\Filter\WpHandleUploadPrefilterFilter;
use WPPack\Component\Hook\Attribute\Media\Filter\WpImageEditorsFilter;
use WPPack\Component\Hook\Attribute\Media\Filter\WpReadImageMetadataFilter;
use WPPack\Component\Hook\Attribute\Media\Filter\WpResourceHintsFilter;
use WPPack\Component\Hook\Hook;
use WPPack\Component\Hook\HookType;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function ajaxQueryAttachmentsArgsFilterHasCorrectHookName(): void
    {
        $filter = new AjaxQueryAttachmentsArgsFilter();

        self::assertSame('ajax_query_attachments_args', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function intermediateSizesAdvancedFilterHasCorrectHookName(): void
    {
        $filter = new IntermediateSizesAdvancedFilter();

        self::assertSame('intermediate_image_sizes_advanced', $filter->hook);
    }

    #[Test]
    public function mediaUploadTabsFilterHasCorrectHookName(): void
    {
        $filter = new MediaUploadTabsFilter();

        self::assertSame('media_upload_tabs', $filter->hook);
    }

    #[Test]
    public function uploadMimesFilterHasCorrectHookName(): void
    {
        $filter = new UploadMimesFilter();

        self::assertSame('upload_mimes', $filter->hook);
    }

    #[Test]
    public function wpGenerateAttachmentMetadataFilterHasCorrectHookName(): void
    {
        $filter = new WpGenerateAttachmentMetadataFilter();

        self::assertSame('wp_generate_attachment_metadata', $filter->hook);
    }

    #[Test]
    public function wpGetAttachmentImageAttributesFilterHasCorrectHookName(): void
    {
        $filter = new WpGetAttachmentImageAttributesFilter();

        self::assertSame('wp_get_attachment_image_attributes', $filter->hook);
    }

    #[Test]
    public function wpHandleUploadFilterHasCorrectHookName(): void
    {
        $filter = new WpHandleUploadFilter();

        self::assertSame('wp_handle_upload', $filter->hook);
    }

    #[Test]
    public function wpHandleUploadPrefilterFilterHasCorrectHookName(): void
    {
        $filter = new WpHandleUploadPrefilterFilter();

        self::assertSame('wp_handle_upload_prefilter', $filter->hook);
    }

    #[Test]
    public function wpImageEditorsFilterHasCorrectHookName(): void
    {
        $filter = new WpImageEditorsFilter();

        self::assertSame('wp_image_editors', $filter->hook);
    }

    #[Test]
    public function uploadMimesFilterAcceptsCustomPriority(): void
    {
        $filter = new UploadMimesFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function deleteAttachmentActionHasCorrectHookName(): void
    {
        $action = new DeleteAttachmentAction();

        self::assertSame('delete_attachment', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertInstanceOf(Action::class, $action);
    }

    #[Test]
    public function wpResourceHintsFilterHasCorrectHookName(): void
    {
        self::assertSame('wp_resource_hints', (new WpResourceHintsFilter())->hook);
    }

    #[Test]
    public function wpCalculateImageSrcsetFilterHasCorrectHookName(): void
    {
        self::assertSame('wp_calculate_image_srcset', (new WpCalculateImageSrcsetFilter())->hook);
    }

    #[Test]
    public function getAttachedFileFilterHasCorrectHookName(): void
    {
        self::assertSame('get_attached_file', (new GetAttachedFileFilter())->hook);
    }

    #[Test]
    public function wpGetAttachmentUrlFilterHasCorrectHookName(): void
    {
        self::assertSame('wp_get_attachment_url', (new WpGetAttachmentUrlFilter())->hook);
    }

    #[Test]
    public function wpReadImageMetadataFilterHasCorrectHookName(): void
    {
        self::assertSame('wp_read_image_metadata', (new WpReadImageMetadataFilter())->hook);
    }

    #[Test]
    public function wpGetAttachmentImageSrcFilterHasCorrectHookName(): void
    {
        self::assertSame('wp_get_attachment_image_src', (new WpGetAttachmentImageSrcFilter())->hook);
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new AjaxQueryAttachmentsArgsFilter());
        self::assertInstanceOf(Filter::class, new IntermediateSizesAdvancedFilter());
        self::assertInstanceOf(Filter::class, new MediaUploadTabsFilter());
        self::assertInstanceOf(Filter::class, new UploadMimesFilter());
        self::assertInstanceOf(Filter::class, new WpGenerateAttachmentMetadataFilter());
        self::assertInstanceOf(Filter::class, new WpGetAttachmentImageAttributesFilter());
        self::assertInstanceOf(Filter::class, new WpHandleUploadFilter());
        self::assertInstanceOf(Filter::class, new WpHandleUploadPrefilterFilter());
        self::assertInstanceOf(Filter::class, new WpImageEditorsFilter());
        self::assertInstanceOf(Filter::class, new WpResourceHintsFilter());
        self::assertInstanceOf(Filter::class, new WpCalculateImageSrcsetFilter());
        self::assertInstanceOf(Filter::class, new GetAttachedFileFilter());
        self::assertInstanceOf(Filter::class, new WpGetAttachmentUrlFilter());
        self::assertInstanceOf(Filter::class, new WpReadImageMetadataFilter());
        self::assertInstanceOf(Filter::class, new WpGetAttachmentImageSrcFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[UploadMimesFilter(priority: 5)]
            public function onUploadMimes(): void {}
        };

        $filterMethod = new \ReflectionMethod($class, 'onUploadMimes');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('upload_mimes', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
