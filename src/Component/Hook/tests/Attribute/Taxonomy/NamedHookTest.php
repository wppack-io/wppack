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

namespace WPPack\Component\Hook\Tests\Attribute\Taxonomy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Hook\Attribute\Filter;
use WPPack\Component\Hook\Hook;
use WPPack\Component\Hook\HookType;
use WPPack\Component\Hook\Attribute\Taxonomy\Action\CreateTermAction;
use WPPack\Component\Hook\Attribute\Taxonomy\Action\DeleteTermAction;
use WPPack\Component\Hook\Attribute\Taxonomy\Action\EditTermAction;
use WPPack\Component\Hook\Attribute\Taxonomy\Action\PreGetTermsAction;
use WPPack\Component\Hook\Attribute\Taxonomy\Action\RegisteredTaxonomyAction;
use WPPack\Component\Hook\Attribute\Taxonomy\Filter\GetTermsFilter;
use WPPack\Component\Hook\Attribute\Taxonomy\Filter\TermLinkFilter;
use WPPack\Component\Hook\Attribute\Taxonomy\Filter\TermsClausesFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function createTermActionHasCorrectHookName(): void
    {
        $action = new CreateTermAction(taxonomy: 'category');

        self::assertSame('create_category', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function createTermActionAcceptsCustomPriority(): void
    {
        $action = new CreateTermAction(taxonomy: 'category', priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function createTermActionPropertyIsAccessible(): void
    {
        $action = new CreateTermAction(taxonomy: 'category');

        self::assertSame('category', $action->taxonomy);
    }

    #[Test]
    public function deleteTermActionHasCorrectHookName(): void
    {
        $action = new DeleteTermAction(taxonomy: 'category');

        self::assertSame('delete_category', $action->hook);
    }

    #[Test]
    public function editTermActionHasCorrectHookName(): void
    {
        $action = new EditTermAction(taxonomy: 'category');

        self::assertSame('edit_category', $action->hook);
    }

    #[Test]
    public function preGetTermsActionHasCorrectHookName(): void
    {
        $action = new PreGetTermsAction();

        self::assertSame('pre_get_terms', $action->hook);
    }

    #[Test]
    public function registeredTaxonomyActionHasCorrectHookName(): void
    {
        $action = new RegisteredTaxonomyAction();

        self::assertSame('registered_taxonomy', $action->hook);
    }

    #[Test]
    public function getTermsFilterHasCorrectHookName(): void
    {
        $filter = new GetTermsFilter();

        self::assertSame('get_terms', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function termLinkFilterHasCorrectHookName(): void
    {
        $filter = new TermLinkFilter();

        self::assertSame('term_link', $filter->hook);
    }

    #[Test]
    public function termsClausesFilterHasCorrectHookName(): void
    {
        $filter = new TermsClausesFilter();

        self::assertSame('terms_clauses', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new CreateTermAction(taxonomy: 'test'));
        self::assertInstanceOf(Action::class, new DeleteTermAction(taxonomy: 'test'));
        self::assertInstanceOf(Action::class, new EditTermAction(taxonomy: 'test'));
        self::assertInstanceOf(Action::class, new PreGetTermsAction());
        self::assertInstanceOf(Action::class, new RegisteredTaxonomyAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new GetTermsFilter());
        self::assertInstanceOf(Filter::class, new TermLinkFilter());
        self::assertInstanceOf(Filter::class, new TermsClausesFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[CreateTermAction(taxonomy: 'post_tag')]
            public function onCreateTag(): void {}

            #[GetTermsFilter(priority: 5)]
            public function onGetTerms(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onCreateTag');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('create_post_tag', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onGetTerms');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('get_terms', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
