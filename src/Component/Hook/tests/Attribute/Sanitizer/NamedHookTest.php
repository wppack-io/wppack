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

namespace WPPack\Component\Hook\Tests\Attribute\Sanitizer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Attribute\Filter;
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\PreInsertTermFilter;
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\PreUserLoginFilter;
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeCommentMetaFilter;
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeEmailFilter;
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeFileNameFilter;
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeKeyFilter;
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizePostMetaFilter;
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeTermMetaFilter;
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeTextFieldFilter;
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeTitleFilter;
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeUserMetaFilter;
use WPPack\Component\Hook\Hook;
use WPPack\Component\Hook\HookType;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function preInsertTermFilterHasCorrectHookName(): void
    {
        $filter = new PreInsertTermFilter();

        self::assertSame('pre_insert_term', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
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
        $filter = new SanitizeCommentMetaFilter(metaKey: 'rating');

        self::assertSame('sanitize_comment_meta_rating', $filter->hook);
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
        $filter = new SanitizePostMetaFilter(metaKey: 'price');

        self::assertSame('sanitize_post_meta_price', $filter->hook);
    }

    #[Test]
    public function sanitizeTermMetaFilterHasCorrectHookName(): void
    {
        $filter = new SanitizeTermMetaFilter(metaKey: 'color');

        self::assertSame('sanitize_term_meta_color', $filter->hook);
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
        $filter = new SanitizeUserMetaFilter(metaKey: 'nickname');

        self::assertSame('sanitize_user_meta_nickname', $filter->hook);
    }

    #[Test]
    public function sanitizeTitleFilterAcceptsCustomPriority(): void
    {
        $filter = new SanitizeTitleFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function metaFiltersAcceptCustomPriority(): void
    {
        $filter = new SanitizePostMetaFilter(metaKey: 'price', priority: 5);

        self::assertSame(5, $filter->priority);
        self::assertSame('sanitize_post_meta_price', $filter->hook);
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new PreInsertTermFilter());
        self::assertInstanceOf(Filter::class, new PreUserLoginFilter());
        self::assertInstanceOf(Filter::class, new SanitizeCommentMetaFilter(metaKey: 'test'));
        self::assertInstanceOf(Filter::class, new SanitizeEmailFilter());
        self::assertInstanceOf(Filter::class, new SanitizeFileNameFilter());
        self::assertInstanceOf(Filter::class, new SanitizeKeyFilter());
        self::assertInstanceOf(Filter::class, new SanitizePostMetaFilter(metaKey: 'test'));
        self::assertInstanceOf(Filter::class, new SanitizeTermMetaFilter(metaKey: 'test'));
        self::assertInstanceOf(Filter::class, new SanitizeTextFieldFilter());
        self::assertInstanceOf(Filter::class, new SanitizeTitleFilter());
        self::assertInstanceOf(Filter::class, new SanitizeUserMetaFilter(metaKey: 'test'));
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
