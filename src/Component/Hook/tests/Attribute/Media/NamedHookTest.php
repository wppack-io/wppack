<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Tests\Attribute\Media;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\Media\Filter\AjaxQueryAttachmentsArgsFilter;
use WpPack\Component\Hook\Attribute\Media\Filter\IntermediateSizesAdvancedFilter;
use WpPack\Component\Hook\Attribute\Media\Filter\MediaUploadTabsFilter;
use WpPack\Component\Hook\Attribute\Media\Filter\UploadMimesFilter;
use WpPack\Component\Hook\Attribute\Media\Filter\WpGenerateAttachmentMetadataFilter;
use WpPack\Component\Hook\Attribute\Media\Filter\WpGetAttachmentImageAttributesFilter;
use WpPack\Component\Hook\Attribute\Media\Filter\WpHandleUploadFilter;
use WpPack\Component\Hook\Attribute\Media\Filter\WpHandleUploadPrefilterFilter;
use WpPack\Component\Hook\Attribute\Media\Filter\WpImageEditorsFilter;

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
