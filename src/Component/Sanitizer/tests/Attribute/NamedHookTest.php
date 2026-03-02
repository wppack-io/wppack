<?php

declare(strict_types=1);

namespace WpPack\Component\Sanitizer\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Sanitizer\Attribute\Filter\EscAttrFilter;
use WpPack\Component\Sanitizer\Attribute\Filter\EscHtmlFilter;
use WpPack\Component\Sanitizer\Attribute\Filter\EscJsFilter;
use WpPack\Component\Sanitizer\Attribute\Filter\EscUrlFilter;
use WpPack\Component\Sanitizer\Attribute\Filter\PreInsertTermFilter;
use WpPack\Component\Sanitizer\Attribute\Filter\PreUserLoginFilter;
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeCommentMetaFilter;
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeEmailFilter;
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeFileNameFilter;
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeKeyFilter;
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizePostMetaFilter;
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeTermMetaFilter;
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeTextFieldFilter;
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeTitleFilter;
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeUserMetaFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function escAttrFilterHasCorrectHookName(): void
    {
        $filter = new EscAttrFilter();

        self::assertSame('esc_attr', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function escHtmlFilterHasCorrectHookName(): void
    {
        $filter = new EscHtmlFilter();

        self::assertSame('esc_html', $filter->hook);
    }

    #[Test]
    public function escJsFilterHasCorrectHookName(): void
    {
        $filter = new EscJsFilter();

        self::assertSame('esc_js', $filter->hook);
    }

    #[Test]
    public function escUrlFilterHasCorrectHookName(): void
    {
        $filter = new EscUrlFilter();

        self::assertSame('esc_url', $filter->hook);
    }

    #[Test]
    public function preInsertTermFilterHasCorrectHookName(): void
    {
        $filter = new PreInsertTermFilter();

        self::assertSame('pre_insert_term', $filter->hook);
    }

    #[Test]
    public function preUserLoginFilterHasCorrectHookName(): void
    {
        $filter = new PreUserLoginFilter();

        self::assertSame('pre_user_login', $filter->hook);
    }

    #[Test]
    public function sanitizeCommentMetaFilterHasCorrectHookName(): void
    {
        $filter = new SanitizeCommentMetaFilter();

        self::assertSame('sanitize_comment_meta', $filter->hook);
    }

    #[Test]
    public function sanitizeEmailFilterHasCorrectHookName(): void
    {
        $filter = new SanitizeEmailFilter();

        self::assertSame('sanitize_email', $filter->hook);
    }

    #[Test]
    public function sanitizeFileNameFilterHasCorrectHookName(): void
    {
        $filter = new SanitizeFileNameFilter();

        self::assertSame('sanitize_file_name', $filter->hook);
    }

    #[Test]
    public function sanitizeKeyFilterHasCorrectHookName(): void
    {
        $filter = new SanitizeKeyFilter();

        self::assertSame('sanitize_key', $filter->hook);
    }

    #[Test]
    public function sanitizePostMetaFilterHasCorrectHookName(): void
    {
        $filter = new SanitizePostMetaFilter();

        self::assertSame('sanitize_post_meta', $filter->hook);
    }

    #[Test]
    public function sanitizeTermMetaFilterHasCorrectHookName(): void
    {
        $filter = new SanitizeTermMetaFilter();

        self::assertSame('sanitize_term_meta', $filter->hook);
    }

    #[Test]
    public function sanitizeTextFieldFilterHasCorrectHookName(): void
    {
        $filter = new SanitizeTextFieldFilter();

        self::assertSame('sanitize_text_field', $filter->hook);
    }

    #[Test]
    public function sanitizeTitleFilterHasCorrectHookName(): void
    {
        $filter = new SanitizeTitleFilter();

        self::assertSame('sanitize_title', $filter->hook);
    }

    #[Test]
    public function sanitizeUserMetaFilterHasCorrectHookName(): void
    {
        $filter = new SanitizeUserMetaFilter();

        self::assertSame('sanitize_user_meta', $filter->hook);
    }

    #[Test]
    public function sanitizeTitleFilterAcceptsCustomPriority(): void
    {
        $filter = new SanitizeTitleFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new EscAttrFilter());
        self::assertInstanceOf(Filter::class, new EscHtmlFilter());
        self::assertInstanceOf(Filter::class, new EscJsFilter());
        self::assertInstanceOf(Filter::class, new EscUrlFilter());
        self::assertInstanceOf(Filter::class, new PreInsertTermFilter());
        self::assertInstanceOf(Filter::class, new PreUserLoginFilter());
        self::assertInstanceOf(Filter::class, new SanitizeCommentMetaFilter());
        self::assertInstanceOf(Filter::class, new SanitizeEmailFilter());
        self::assertInstanceOf(Filter::class, new SanitizeFileNameFilter());
        self::assertInstanceOf(Filter::class, new SanitizeKeyFilter());
        self::assertInstanceOf(Filter::class, new SanitizePostMetaFilter());
        self::assertInstanceOf(Filter::class, new SanitizeTermMetaFilter());
        self::assertInstanceOf(Filter::class, new SanitizeTextFieldFilter());
        self::assertInstanceOf(Filter::class, new SanitizeTitleFilter());
        self::assertInstanceOf(Filter::class, new SanitizeUserMetaFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[SanitizeTitleFilter(priority: 5)]
            public function onSanitizeTitle(): void {}
        };

        $filterMethod = new \ReflectionMethod($class, 'onSanitizeTitle');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('sanitize_title', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
